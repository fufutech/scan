<?php
declare(strict_types=1);

namespace App\Process;

use App\Exception\ApiException;
use App\Model\OrderWaybillModel;
use App\Services\Admin\CustomsAffairsService;
use App\Services\OrderService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Psr\Log\LoggerInterface;

class PushExpressRouteProcess extends AbstractProcess
{
    /**
     * 进程数量
     *
     * @var int
     */
    public $nums = 5;

    /**
     * 进程名称
     *
     * @var string
     */
    public $name = 'process-push-express-route';

    /**
     * 重定向自定义进程的标准输入和输出
     *
     * @var bool
     */
    public $redirectStdinStdout = false;

    /**
     * 管道类型
     *
     * @var int
     */
    public $pipeType = 2;

    /**
     * 是否启用协程
     *
     * @var bool
     */
    public $enableCoroutine = true;

    private LoggerInterface $logger;

    private array $orderData;
    private string $appkey = '200188';
    private string $secreat = 'cc1586973992fdc1e5347a49a526a558';

    public function handle(): void
    {
        $redis_key = 'queues:PushExpressRoute';

        $redis        = $this->container->get(\Redis::class);
        $this->logger = $this->container->get(LoggerFactory::class)->get(
            'PushExpressRouteProcess', 'requestPushExpressRoute'
        );
        $OrderService = make(OrderService::class);

        while (true) {
            $data = $redis->rPop($redis_key);
            if (!$data) {
                sleep(1);
                continue;
            }
            $this->logger->info('任务入参', ['data' => $data]);

            $data = json_decode($data, true);

            $tid        = $data['tid'] ?? '';
            $type       = $data['type'] ?? ''; // pickup-揽收 main-干线 cc-清关
            $outlet_id  = $data['outlet_id'] ?? 0; // pda 用户的所属网点 id
            $pushTime   = $data['pushTime'] ?? 0; // 需要推送的时间
            $branchCode = $data['branchCode'] ?? ''; // 指定国内的网点
            $pushYUNDA  = $data['pushYUNDA'] ?? false; // 目前在 PDA 清关揽件模板用
//            $isAuto = $data['is_auto'] ?? true; // 是否自动推送干线路由
            // 初始化
            $this->orderData = [];
            $numType         = '';
            if ($tid) {
                try {
                    if (env('APP_ENV', 'dev') == 'dev') {
                        throw new ApiException(
                            '由于测试环境，该部分代码不可推送'
                        );
                    }
                    $OrderService->checkOrder(['tid' => $tid], [102, 103, 104]
                    ); // 检查订单
                    $this->orderData = $OrderService->findModel(['tid' => $tid],
                        ['port'])->getOrderModel();
                    if (!$this->orderData) {
                        throw new ApiException('订单信息不存在');
                    }
                    if (!$this->orderData['label'] & (1 << 2)) {
                        throw new ApiException('订单未揽收');
                    }
                    if (!$this->orderData['first_waybill_no']) {
                        throw new ApiException('运单号不存在');
                    }
                    if ($outlet_id) {
                        // pda 用户的所属网点名称
                        $this->orderData['outletName'] = Db::table('outlet')
                            ->where('id', $outlet_id)->value('name');
                    }
                    // 推送时间
                    if ($pushTime) {
                        $this->orderData['pushTime'] = date(
                            'Y-m-d H:i:s', $pushTime
                        );
                    }
                    $this->orderData['branchCode'] = $branchCode; // 国内揽件网点
                    $this->orderData['pushYUNDA']  = $pushYUNDA; // 清关推送运单

                    // 清关 - 小件：取第一次取末端单号的时间往前推30-60分钟  大件：当前时间
                    if ($type == 'cc' && $this->orderData['size'] == 1
                        && $this->orderData['second_waybill_no']
                    ) {
                        $second = OrderWaybillModel::where('tid', $tid)
                            ->where(
                                'waybill_no_origin',
                                $this->orderData['first_waybill_no']
                            )
                            ->first();
                        if (!$second) {
                            throw new ApiException('末端单号取号时间获取失败');
                        }
                        $this->orderData['second_waybill_no_data']
                            = $second->toArray();
                    }
                    // 换单获取换单的快递公司名称
                    if ($type == 'change'
                        && isset($this->orderData['second_waybill_no'])
                        && $this->orderData['second_waybill_no']
                    ) {
                        $secondCourier = OrderWaybillModel::where('tid', $tid)
                            ->where(
                                'waybill_no',
                                $this->orderData['second_waybill_no']
                            )
                            ->with(['courier'])
                            ->first();
//                        echo '换单获取换单 => ' . $tid . ' == ' . $this->orderData['second_waybill_no'] . ' == ' . json_encode($secondCourier, 256) . PHP_EOL;
                        $company_name
                            = $secondCourier['courier']['company_name'] ?? '';
                        if (!$company_name) {
                            throw new ApiException('换单单号数据存在问题');
                        }
                        $data = explode('-', $company_name);

                        $this->orderData['ChangeCompanyName'] = $data[0] ??
                            $company_name; // 换单的快递公司名称
                    }

                    $noHead = substr(
                        $this->orderData['first_waybill_no'], 0, 3
                    );
                    if (!in_array($noHead, ['S82', 'Y82', 'D82', '779'])) {
                        throw new ApiException('单号不需要推送路由');
                    }

                    $numType = str_contains(
                        $this->orderData['first_waybill_no'], 'S82'
                    ) ? 'EWTP' : 'SYD';

                    switch ($numType) {
                        case 'EWTP':
                            $result = $this->pushRouteEWTP($type);
                            break;
                        default:
                        case 'SYD':
                            $result = $this->pushRouteSYD($type);
                            break;
                    }
                    $resCode = $result['code'] ?? 0;
                    if ($resCode == 200) {
                        // 成功
                        $this->logger->info(
                            'SUCCESS', ['tid'       => $tid, 'type' => $type,
                                        'numType'   => $numType,
                                        'orderData' => $this->orderData,
                                        'res'       => $result]
                        );
                    } else {
                        $this->logger->info(
                            'FAILURE', ['tid'       => $tid, 'type' => $type,
                                        'numType'   => $numType,
                                        'orderData' => $this->orderData,
                                        'res'       => $result]
                        );
                    }
                } catch (\Throwable $throwable) {
                    // 取号报错
                    $error = 'ERROR 推送路由报错：'.$throwable->getMessage();
                    $this->logger->error(
                        $error, ['tid'       => $tid, 'type' => $type,
                                 'numType'   => $numType ?? '',
                                 'orderData' => $this->orderData ?? []]
                    );
                }
                // 自动推送干线路由
//                if ($type == 'pickup' && $isAuto) {
//                    $pushData = ['tid' => $tid, 'type' => 'main'];
//                    $redis->lPush('queues:PushExpressRoute', json_encode($pushData, 256));
//                }

                // 推送清关完成后需要推送换单路由，对已换单的数据
                if ($type == 'cc'
                    && isset($this->orderData['second_waybill_no'])
                    && $this->orderData['second_waybill_no']
                ) {
                    $changePushTime = $this->getChangePushTime(); // 换单推送时间
                    $pushData       = ['tid'      => $tid, 'type' => 'change',
                                       'pushTime' => $changePushTime];
                    $redis->lPush(
                        'queues:PushExpressRoute', json_encode($pushData, 256)
                    );
                }
            } else {
                sleep(1);
            }
        }
    }

    protected function pushRouteEWTP($type)
    {
        $url = 'http://trunkapi.suyoda.cn/ewtp/pushRoute';

        switch ($type) {
            default:
            case 'pickup':
                $outletName = isset($this->orderData['outletName'])
                && $this->orderData['outletName']
                    ? $this->orderData['outletName'] : '首尔东大门旗舰店';
                $typeStr    = 'SEA';
                $code       = 'PU_PICKUP_SUCCESS'; // 揽收
                $pushTime   = $this->orderData['pickup_time']; // 揽收时间
                $info       = $outletName.'已揽收成功';
                break;
            case 'main':
                $typeStr  = 'LAND';
                $code     = 'LH_DEPART'; // 干线起飞
                $pushTime = date(
                    'Y-m-d', strtotime($this->orderData['consign_time'])
                ); // 发货时间，需要时间模板的，传 Y-m-d 即可
                $info     = '干线起飞';
                $template = '106';
                break;
            case 'cc':
                $typeStr   = 'CLEAR';
                $code      = 'CC_IM_SUCCESS'; // 清关完成
                $pushTime  = $this->getCCPushTime(); // 第一次取末端单号的时间
                $info      = '清关完成';
                $stop_name = $this->orderData['port']['port_name'];
                break;
            case 'change':
                $typeStr   = 'IN';
                $code      = 'CC_HO_OUT_SUCCESS'; // 换单
                $pushTime  = $this->orderData['pushTime'];
                $info      = '已转单'.$this->orderData['ChangeCompanyName']
                    .'，单号为：'.$this->orderData['second_waybill_no'];
                $stop_name = '烟台清关处理中心';
                break;
        }
        $inputPushTime = isset($this->orderData['pushTime'])
        && $this->orderData['pushTime'] ? $this->orderData['pushTime'] : '';
        $pushData      = [
            "appkey"         => $this->appkey,
            "secreat"        => $this->secreat,
            "waybill_no"     => $this->orderData['first_waybill_no'],
            "stop_name"      => $stop_name ?? '首尔东大门旗舰店',
            "next_stop_name" => $type == 'SEA' ? '仁川机场' : '',
            "current_city"   => $type == 'SEA' ? '首尔' : '',
            "next_city"      => $type == 'SEA' ? '仁川' : '',
            "type"           => $typeStr,
            // type SEA-海外仓操作 LAND-干线 CLEAR-清关 IN-国内揽收派
            "push_time"      => $inputPushTime ?: $pushTime,
            "info"           => $info,
            "port"           => $this->orderData['port']['port_name'] ?? '烟台',
            "trans_type"     => '空运',
            "template"       => $template ?? '',
            "code"           => $code, // 揽收
        ];

        return $this->curl($url, $pushData);
    }

    protected function pushRouteSYD($type)
    {
        switch ($type) {
            default:
            case 'pickup':
            case 'cc':
                $res = $this->pushRoutePickupSYD($type);
                break;
            case 'main':
                $res = $this->pushRouteLandSYD();
                break;
            case 'change':
                $res = $this->pushRouteChangeSYD();
                break;
        }

        return $res;
    }

    /**
     * @DOC 推送路由-速邮达-揽收清关路由
     */
    protected function pushRoutePickupSYD($type)
    {
        $url        = 'http://trunkapi.suyoda.cn/scan/createRoute';
        $dot_code   = '80000001'; // 网点代码
        $waybill_no = $this->orderData['first_waybill_no'];

        switch ($type) {
            default:
            case 'pickup':
                // branchCode 用于在 PDA 清关揽收操作中，推送国内揽收的网点代码
                if ($this->orderData['branchCode']) {
                    // 国内揽收
                    $pushTime   = $this->orderData['pushTime']; // 推送时间
                    $outletName = '烟台福山区古现公司';
                    $dot_code   = $this->orderData['branchCode']; // 国内揽收网点代码

                    $second_waybill_no = $this->orderData['second_waybill_no']
                        ?? '';
                    if (!str_starts_with($waybill_no, '779')
                        && !str_starts_with($second_waybill_no, '312')
                    ) {
                        throw new ApiException('该订单无法推送国内路由揽收');
                    }
                    if (str_starts_with($second_waybill_no, '312')) {
                        $waybill_no = $second_waybill_no; // 312 的单号
                    }
                } else {
                    $outletName = isset($this->orderData['outletName'])
                    && $this->orderData['outletName']
                        ? $this->orderData['outletName'] : '首尔东大门旗舰店';
                    $pushTime   = $this->orderData['pickup_time'];
                }
                $weight    = max(
                    $this->orderData['weight_pickup'],
                    $this->orderData['weight_report']
                );
                $stop_name = $outletName;
                $info      = $outletName.'已揽收成功';
                break;
            case 'cc':
                $pushTime  = $this->getCCPushTime(); // 第一次取末端单号的时间
                $weight    = max(
                    $this->orderData['weight_send'], $this->orderData['weight'],
                    $this->orderData['weight_pickup']
                );
                $stop_name = $this->orderData['port']['port_name'] ?? '';
                $info      = '清关完成';
                break;
        }

        $inputPushTime = isset($this->orderData['pushTime'])
        && $this->orderData['pushTime'] ? $this->orderData['pushTime'] : '';

        $pushData = [
            "appkey"     => $this->appkey,
            "secreat"    => $this->secreat,
            "waybill_no" => $waybill_no,
            "other_no"   => $this->orderData['tid'],
            "info"       => $info,
            "operator"   => '李庆伟',
            "push_time"  => $inputPushTime ?: $pushTime,
            "weight"     => $weight,
            "stop_name"  => $stop_name,
            "flight"     => '',
            "country"    => $type == 'pickup' ? 'KR' : 'CN',
            "dot_code"   => $dot_code,
            "length"     => '',
            "width"      => '',
            "height"     => '',
            "type"       => $type == 'pickup' ? 'SCAN' : 'CLEAR',
            "code"       => $type == 'pickup' ? '11' : '80',
        ];

        // PDA清关揽件，韵达清关推送路由
        if ($this->orderData['pushYUNDA'] === true && $type == 'cc') {
            if (!$stop_name) {
                throw new ApiException('韵达清关口岸信息必填');
            }
            $where          = [['trade_code', '=',
                                $this->orderData['trade_code']]];
            $CustomsAffairs = make(CustomsAffairsService::class)->getInfo(
                $where, ['flight']
            );

            $pushData['tp']         = 'YUNDA_OPEN'; // 代表要去请求韵达的类
            $pushData['clearance']  = $stop_name.'口岸'; // "口岸" 需要加上
            $pushData['dot_code']   = $dot_code; // 网点代码
            $pushData['stop_name']  = '韩国国际部'; // 网点名称
            $pushData['flight']     = $CustomsAffairs['flight']['flight_number']
                ?? ''; // 航班
            $pushData['country']    = 'CN'; // 国家
            $pushData['city']       = $stop_name; // 城市
            $pushData['store_type'] = '3'; // 站点类型 3-清关场
        }

        return $this->curl($url, $pushData);
    }

    protected function pushRouteLandSYD()
    {
        $url = 'http://trunkapi.suyoda.cn/push/pushRoute';

        $waybillNo = $this->orderData['first_waybill_no'];
        if (str_starts_with($waybillNo, "D82")) {
            $tp       = 8405;
            $template = 121;
        } elseif (str_starts_with($waybillNo, "Y82")) {
            $tp       = 128;
            $template = 122;
        } elseif (str_starts_with($waybillNo, "779")) {
            $tp       = 6;
            $template = 124;
        }

        $pushData = [
            "appkey"     => $this->appkey,
            "secreat"    => $this->secreat,
            "waybill_no" => $this->orderData['first_waybill_no'],
            "tid"        => $this->orderData['tid'],
            "order_no"   => $this->orderData['tid'],
            "port"       => $this->orderData['port']['port_name'] ?? '',
            "trans_type" => '空运',
            "tp"         => $tp ?? '',
            "template"   => $template ?? '',
            "push_time"  => date(
                'Y-m-d', strtotime($this->orderData['consign_time'])
            ), // 发货时间，需要时间模板的，传 Y-m-d 即可
        ];

        return $this->curl($url, $pushData);
    }

    protected function pushRouteChangeSYD()
    {
        $url = 'http://trunkapi.suyoda.cn/push/pushRouteSingle';

        $pushData = [
            "appkey"     => $this->appkey,
            "secreat"    => $this->secreat,
            "waybill_no" => $this->orderData['first_waybill_no'],
            "desc"       => '已转单'.$this->orderData['ChangeCompanyName']
                .'，单号为：'.$this->orderData['second_waybill_no'],
            "push_time"  => $this->orderData['pushTime'],
            "tp"         => 'SYD',
        ];

        return $this->curl($url, $pushData);
    }

    protected function curl($url, $params)
    {
        $postData = http_build_query($params);
        $options  = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type:application/x-www-form-urlencoded;charset=utf-8',
                'content' => $postData,
                'timeout' => 15 * 60, // 超时时间（单位:s）
            ),
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        $this->logger->info(
            'CURL',
            ['url' => $url, 'params' => $params, 'response' => $response]
        );

        return json_decode($response, true);
//        if ($err) {
//            $msg = '推送路由接口报错：' . $err;
//            $this->logger->error('CURL', ['url' => $url, 'params' => $params, 'error' => $msg]);
//            return $msg;
//        } else {
//
//        }
    }

    protected function getCCPushTime(): string
    {
        // 小件：取第一次取末端单号的时间往前推30-60分钟  大件：当前时间
        $size = $this->orderData['size'] ?? 0;
        $second_waybill_no_created
              = $this->orderData['second_waybill_no_data']['created'] ?? '';
        if ($size == 1) {
            if ($second_waybill_no_created) {
                $ago      = '-'.mt_rand(30, 60).' minutes';
                $pushTime = date(
                    'Y-m-d H:i:s',
                    strtotime($ago, strtotime($second_waybill_no_created))
                );
            }
        } elseif ($size == 2) {
            $pushTime = $this->orderData['pushTime'] ?? date('Y-m-d H:i:s');
        } else {
            throw new ApiException('订单的数据错误：size');
        }

        return $pushTime ?? '';
    }

    protected function getChangePushTime(): int
    {
        // 小件：取第一次取末端单号的时间  大件：当前时间往后推10-20分钟
        $size = $this->orderData['size'] ?? 0;
        if ($size == 1) {
            $pushTime = strtotime(
                $this->orderData['second_waybill_no_data']['created']
            );
        } elseif ($size == 2) {
            $inc      = '+'.mt_rand(10, 20).' minutes';
            $pushTime = strtotime(
                $inc, strtotime($this->orderData['pushTime'])
            );
        } else {
            throw new ApiException('订单的数据错误：size');
        }

        return $pushTime;
    }
}