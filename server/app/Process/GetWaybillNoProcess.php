<?php
declare(strict_types=1);

namespace App\Process;

use App\Exception\ApiException;
use App\Lib\ExpressDelivery\DeliveryClient;
use App\Services\OrderLogService;
use App\Services\OrderService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class GetWaybillNoProcess extends AbstractProcess
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
    public $name = 'process-get-waybill-no';

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

    public function handle(): void
    {
        $redis_key = 'queues:GetWaybillNo';

        $redis           = $this->container->get(\Redis::class);
        $Logger          = $this->container->get(LoggerFactory::class)->get(
            'GetWaybillNoProcess', 'requestGetWaybillNo'
        );
        $OrderService    = make(OrderService::class);
        $OrderLogService = make(OrderLogService::class);
        while (true) {
            $tid = $redis->rPop($redis_key);
            if ($tid) {
                try {
                    $OrderService->checkOrder(['tid' => $tid], 102
                    ); // 检查订单的状态，是待取号的状态
                    $orderData = $OrderService->findModel(['tid' => $tid],
                        ['sender', 'receiver', 'item'])->getOrderModel();

                    // 获取渠道的时候需要考虑一下预约取号的，订单数据中没有线路ID和渠道ID
                    $line_id    = $orderData['line_id'] ?? 0;
                    $channel_id = $orderData['channel_id'] ?? 0;
                    if ($line_id && $channel_id) {
                        $channel = $OrderService->checkParamsChannel(
                            $line_id, $channel_id
                        );

                        $companyCode = $channel['courier']['company_code'] ??
                            ''; // 快递公司的 code
                        if (!$companyCode) {
                            throw new ApiException('快递公司信息错误');
                        }
                    } else {
                        $companyCode = 'SYD-D'; // 预约取号的
                    }

                    if (in_array($companyCode, ['SYD-S', 'SYD-Y', 'SYD-D'])) {
                        $carrier = 'SYD';
                        $param   = ['tid' => $tid, 'type' => $companyCode];
                    } else {
                        // 目前主要是 YUNDA ，所以需要组装一下数据
                        $arr     = explode('-', $companyCode);
                        $param   = $orderData;
                        $carrier = $arr[0];

                        $param['channel'] = $channel ?? []; //  渠道和承运商信息
                    }

                    $DeliveryClient = make(DeliveryClient::class);

                    $DeliveryClient->setCarrier($carrier);
                    list($res, $data) = $DeliveryClient->pushDelivery($param);

                    $Logger->info(
                        json_encode(
                            ['carrier' => $carrier, 'params' => $param,
                             'bool'    => $res, 'data' => $data],
                            JSON_UNESCAPED_UNICODE
                        )
                    );

                    $waybillData = [
                        'tid'               => $tid,
                        'uid'               => $orderData['uid'],
                        'code'              => $companyCode,
                        'waybill_no_origin' => '',
                        'waybill_no'        => $data['waybill_no'] ?? '',
                        'waybill_data'      => json_encode(
                            $data, JSON_UNESCAPED_UNICODE
                        ),
                        'created'           => time(),
                    ];
                    if ($res) {
                        // 保存数据
                        Db::transaction(
                            function () use (&$tid, &$data, &$waybillData) {
                                Db::table('order')->where(
                                    ['tid' => $tid, 'status' => 102]
                                )
                                    ->update(
                                        ['first_waybill_no' => $data['waybill_no'],
                                         'status'           => 103,
                                         'error'            => '']
                                    );
                                Db::table('order_waybill')->insert(
                                    $waybillData
                                ); // 主运单号
                            }
                        );
                        //记录操作日志 [ id , id_type , uid , uid_type , info , type ]
                        $list = json_encode([
                            'id'          => $tid,
                            'id_type'     => 1,
                            'uid'         => 0,
                            'uid_type'    => '1',
                            'info'        => '取号成功',
                            'type'        => '101',
                            'create_time' => time(),
                        ], 256);
                        $redis->lPush('queues:CREATE_ORDER_LOG', $list);
                    } else {
                        $waybillData['status'] = 2; // 取号失败
                        Db::table('order_waybill')->insert(
                            $waybillData
                        ); // 取号失败记录换单日志
                        //记录操作日志 [ id , id_type , uid , uid_type , info , type ]
                        $list = json_encode([
                            'id'          => $tid,
                            'id_type'     => 1,
                            'uid'         => 0,
                            'uid_type'    => '1',
                            'info'        => '取号失败: '.$data,
                            'type'        => '101',
                            'create_time' => time(),
                        ], 256);
                        $redis->lPush('queues:CREATE_ORDER_LOG', $list);
                        $error = '取号失败：'.$data;
                    }
                } catch (\Throwable $throwable) {
                    // 取号报错
                    $error = '取号报错：'.$throwable->getMessage();
                }
                if (isset($error) && $error) {
                    // 失败报错
                    Db::table('order')
                        ->whereRaw(
                            'tid = '.$tid.' AND status ='. 102
                            .' AND !(label & (1 << 1))'
                        )
                        ->update(
                            ['label' => Db::raw('label ^ '. 1 << 1),
                             'error' => $error]
                        );
                }

                // 设置 redis 标记数据
                if (isset($orderData['batch_no']) && $orderData['batch_no']) {
                    $tagBatchKey = 'PrepareOrderGetWaybillCount_'
                        .$orderData['batch_no'];
                    if ($redis->exists($tagBatchKey)) {
                        $redis->hIncrBy($tagBatchKey, 'complete', 1);
                    }
                }
            } else {
                sleep(1);
            }
        }
    }
}