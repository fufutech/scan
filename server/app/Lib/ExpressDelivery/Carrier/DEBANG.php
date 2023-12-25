<?php
/**
 * *****************************************************************
 * 这不是一个自由软件,谢绝修改再发布.
 *
 * @Created by PhpStorm
 * @Name    :   YunDaApi.class.php
 * @Email   :  550583243@qq.com
 * @Author  : layor
 * @Date    :   2017/2/27 23:48
 * @Link    :   http://layor.cn
 * *****************************************************************
 */

namespace App\Lib\ExpressDelivery\Carrier;

use App\Lib\ExpressDelivery\DeliveryInterface;
use App\Log;
use Psr\Log\LoggerInterface;

class DEBANG implements DeliveryInterface
{
    private array $order = [];

    private array $receiver = [];

    private array $sender = [];

    private array $items = [];

    private array $apiConfig;

    private LoggerInterface $logger;

    public function __construct()
    {
        // 获取配置
        $this->apiConfig = [
            'companyCode'   => 'EWBQDRHFGJ',
            'appkey'        => '004bd98f60e0e0a48545a3c2aa4543c7',
            'sign'          => 'KCSV',
            'url'           => 'http://gwapi.deppon.com/dop-interface-async/standard-order/createOrderNotify.action',
            'senderName'    => 'RHF',
            'senderPhone'   => '17667546005',
            'address'       => '潮水镇空港路1号烟台蓬莱国际机场',
            'province'      => '山东省',
            'city'          => '烟台市',
            'area'          => '蓬莱区',
            'orderType'     => '2',
            'transportType' => 'RCP',
            'customerCode'  => '920100740',
            'isInsure'      => 1,
        ];
    }

    public function init(&$param)
    {
        $this->logger = Log::get('DEBANG', 'express'); // Log 类

        if (isset($param['receiver']) && $param['receiver']) {
            $this->receiver = $param['receiver'];
        }
        if (isset($param['sender']) && $param['sender']) {
            $this->sender = $param['sender'];
        }
        if (isset($param['item']) && $param['item']) {
            $this->items = $param['item'];
        }
        unset($param['receiver'], $param['sender'], $param['goods']);

        if ($param) {
            $this->order = $param;
        }
    }

    public function OrderCreate(mixed &$param): array
    {
        try {
            $this->init($param);

            $this->logger->info('OrderCreate', ['param' => $param]);

            $request_data = $this->Sign();
            $this->logger->info('Sign', ['requestData' => $request_data]);

            $WaybillData = $this->send($this->apiConfig['url'], $request_data);
            $this->logger->info('Send', ['waybillData' => $WaybillData]);

            $WaybillData = json_decode($WaybillData, true);
            $res         = $WaybillData['result'] ?? '';

            if ($res == 'true') {
                $mailNo     = $WaybillData['mailNo'] ??
                    ''; // 比如：DPK360000000000,DPK380000000001,DPK380000000002,生产环境一般DPK36开头为母单号且排在第一位，零担一个订单无论多少件仅会返回一个运单号
                $waybillNos = explode(',', $mailNo);

                $result['waybill_no'] = $waybillNos[0]; // 母运单号

                $result['package_wdjc'] = $WaybillData['arrivedOrgSimpleName']
                    ?? ''; // 原寄地中转场
                $result['position']     = $WaybillData['resultCode'] ??
                    ''; // 原寄地中转场

                unset($waybillNos[0]);
                $result['sonWaybillNoList'] = array_values($waybillNos);

                return [true, $result];
            } else {
                $message = (isset($WaybillData['reason'])) ? 'DEBANG:'
                    .$WaybillData['reason'] : '德邦接口请求失败'; //失败

                return [false, $message];
            }
        } catch (\Exception $e) {
            return [false, '取号报错：'.$e->getMessage()];
        }
    }

    /**
     * @return array
     */
    public function Sign()
    {
        list($t1, $t2) = explode(' ', microtime());
        $timestamp = (float)sprintf(
            '%.0f', (floatval($t1) + floatval($t2)) * 1000
        );

        $parmas = json_encode($this->getOrderCreateData());
        $digest = base64_encode(
            md5($parmas.$this->apiConfig['appkey'].$timestamp)
        );

        return array(
            'companyCode' => $this->apiConfig['companyCode'],
            'params'      => $parmas,
            'digest'      => $digest,
            'timestamp'   => $timestamp,
        );
    }

    public function getOrderCreateData(): array
    {
        $orderData                  = [];
        $orderData['logisticID']    = $this->apiConfig['sign']
            .$this->order['tid'];
        $orderData['companyCode']   = $this->apiConfig['companyCode'];
        $orderData['orderType']     = $this->apiConfig['orderType'];
        $orderData['transportType'] = $this->apiConfig['transportType'];
        $orderData['customerCode']  = $this->apiConfig['customerCode'];
        $orderData['sender']        = $this->getSender();
        $orderData['receiver']      = $this->getReceiver();
        $orderData['packageInfo']   = $this->getItems();
        $orderData['gmtCommit']     = date('Y-m-d H:i:s');
        $orderData['payType']       = 2;

        return $orderData;
    }

    public function getSender(): array
    {
        $senderData             = [];
        $senderData['name']     = $this->apiConfig['senderName'] ?? '润亨丰';
        $senderData['mobile']   = $this->apiConfig['senderPhone'] ??
            '17667546005';
        $senderData['province'] = $this->apiConfig['province'] ?? '山东省';
        $senderData['city']     = $this->apiConfig['city'] ?? '青岛市';
        $senderData['county']   = $this->apiConfig['area'] ?? '城阳区';
        $senderData['address']  = $this->apiConfig['address'] ?? '春海路润亨丰';

        return $senderData;
    }

    public function getReceiver(): array
    {
        $receiverData             = [];
        $receiverData['name']     = $this->receiver['name'];
        $receiverData['mobile']   = $this->receiver['mobile'];
        $receiverData['province'] = $this->receiver['province'];
        $receiverData['city']     = $this->receiver['city'];
        $receiverData['county']   = $this->receiver['district'];
        $receiverData['address']  = $this->receiver['detailed'];

        return $receiverData;
    }

    public function getItems(): array
    {
        $packageInfo                   = [];
        $packageInfo['cargoName']      = '生活用品';
        $packageInfo['deliveryType']
                                       = '4'; // 1、自提； 2、送货进仓； 3、送货（不含上楼）； 4、送货上楼； 5、大件上楼 ;7、送货安装；8、送货入户
        $packageInfo['totalNumber']
                                       = (string)($this->order['mother']['count']
            ??
            1); // 包裹数(指实际交接给我司已打包后的总包裹数)，子母件场景下,一个包裹对应一个运单号；若包裹数大于1，则返回一个母运单号和N-1个子运单号，（单次下单总件数上限为30件，如果大于30请分多次下单）
        $packageInfo['totalVolume']    = '0.001';
        $packageInfo['totalWeight']
                                       = (string)($this->order['mother']['total_weight']
            ?? 1);
        $packageInfo['packageService'] = '纸';
//        foreach ($this->items as $key => $item) {
//            $packageInfo[$key]['cargoName']    = $item['title'];
//            $packageInfo[$key]['totalNumber']  = $item['num'];
//            $packageInfo[$key]['totalWeight']  = 0;
//            $packageInfo[$key]['deliveryType'] = 1;
//        }
        return $packageInfo;
    }

    public function send($uri, $data)
    {
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $uri);//访问地址
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//是否自动输出内容
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}
