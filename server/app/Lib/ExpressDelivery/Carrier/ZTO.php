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
use App\Lib\ExpressDelivery\Carrier\SDK\ZTO\ZopClient;
use App\Lib\ExpressDelivery\Carrier\SDK\ZTO\ZopProperties;
use App\Lib\ExpressDelivery\Carrier\SDK\ZTO\ZopHttpUtil;
use App\Lib\ExpressDelivery\Carrier\SDK\ZTO\ZopRequest;
use App\Log;
use Psr\Log\LoggerInterface;

class ZTO implements DeliveryInterface
{
    private array $apiConfig;

    private LoggerInterface $logger;

    public function init(&$param)
    {
        $this->logger = Log::get('ZTO', 'express'); // Log 类

        $courierArguments = $param['channel']['courier']['arguments'] ?? '';
        if ($courierArguments) {
            $arguments = explode("\n", $courierArguments);
            foreach ($arguments as $val) {
                $Arr                  = explode('|', $val);
                $methodParam[$Arr[0]] = trim($Arr[1]);
            }
        }
        $this->apiConfig = $methodParam ?? [];
    }

    public function OrderCreate(mixed &$param): array
    {
        try {
            $this->init($param);
            $properties = new ZopProperties(
                $this->apiConfig['appkey'], $this->apiConfig['secret']
            );
            $client     = new ZopClient($properties);
            $request    = new ZopRequest();
            $body       = [
                'accountId' => $this->apiConfig['accountId'],
                'password'  => $this->apiConfig['password'],
                'appkey'    => $this->apiConfig['appkey'],
                'secret'    => $this->apiConfig['secret'],
                'url'       => $this->apiConfig['url'],
                'data'      => $this->getOrderCreateData($param),
            ];

            $this->logger->info('请求参数', ['body' => $body]);

            $bodyString = json_encode($body['data'], 256);
            $request->setBody($bodyString);
            $request->setUrl($this->apiConfig['url'].'zto.open.createOrder');
            $ret = $client->execute($request);

            $this->logger->info('返回数据', ['response' => $ret]);

            $retData = json_decode($ret, true);

            if (isset($retData['status']) && $retData['status'] == true) {
                $resultData            = $retData['result'];
                $result['waybill_no']  = (isset($resultData['billCode']))
                    ? $resultData['billCode'] : '';
                $result['package_wdjc']
                                       = (isset($resultData['bigMarkInfo']['bagAddr']))
                    ? $resultData['bigMarkInfo']['bagAddr'] : '';
                $result['position']
                                       = (isset($resultData['bigMarkInfo']['mark']))
                    ? $resultData['bigMarkInfo']['mark'] : '';
                $result['position_no'] = '';

                $result['siteCode'] = (isset($resultData['siteCode']))
                    ? $resultData['siteCode'] : '';
                $result['SiteName'] = (isset($resultData['SiteName']))
                    ? $resultData['SiteName'] : '';

                return [true, $result];
            }

            return [false, $retData['message'] ?? '中通取号失败'];
        } catch (\Exception $e) {
            return [false, '中通取号报错：'.$e->getMessage()];
        }
    }

    public function getOrderCreateData($param): array
    {
        return [
            "partnerType"      => '2',  //合作模式 ，1：集团客户；2：非集团客户
            "orderType"        => '1',
            //partnerType为1时，orderType：1：全网件 2：预约件。 partnerType为2时，orderType：1：全网件 2：预约件（返回运单号） 3：预约件（不返回运单号） 4：星联全网件
            "partnerOrderCode" => (string)$param['tid'],//合作商订单号
            "accountInfo"      => [  //账号信息
                                     'accountId' => $this->apiConfig['accountId'],
                                     //电子面单账号（partnerType为2，orderType传1,2,4时必传）
                                     //                    'customerId'=>'',//客户编码（partnerType传1时必传）
                                     'type'      => '1',
                                     //单号类型:1.普通电子面单；74.星联电子面单；默认是1
                                     //                    'accountPassword'=>self::$methodParam['password'],//电子面单密码（测试环境传ZTO123）

            ],
            //                "billCode"=>'',//运单号
            "senderInfo"       => $this->getSender(),
            "receiveInfo"      => $this->getReceiver($param['receiver']),
            "orderVasList"     => [
//                    [
//                    'accountNo'=>'',//代收账号（有代收货款增值时必填）
//                    'vasType'=>'',//增值类型 （COD：代收； vip：尊享； insured：保价； receiveReturnService：签单返回； twoHour：两小时；standardExpress：标快）
//                    'vasAmount'=>'',//增值价格，如果增值类型涉及金额会校验，vasType为COD、insured时不能为空，单位：分
//                    'vasPrice'=>'',//增值价格（暂时不用）
//                    'vasDetail'=>'',//增值详情
//                    ]
            ],
            //                "hallCode"=>'',//门店/仓库编码（partnerType为1时可使用）
            "summaryInfo"      => (object)[ //汇总信息
                                            //                    'collectMoneyType'=>'', //到达收取币种
                                            //                    'quantity'=>'',//订单包裹内货物总数量
                                            //                    'premium'=>'',//险费（单位：元）
                                            //                    'size'=>'',//订单包裹大小（单位：厘米、格式：”长，宽，高”，用半角的逗号来分隔）
                                            //                    'price'=>'',//商品总价值（单位：元）
                                            //                    'otherCharges'=>'',//其他费用(单位:元)
                                            //                    'freight'=>'',//运输费（单位：元）
                                            //                    'packCharges'=>'',//包装费(单位:元)
                                            //                    'startTime'=>'',//取件开始时间
                                            //                    'endTime'=>'',//取件截止时间
                                            //                    'orderSum'=>'',//保险费

            ],
            //                "siteCode"=>'',//网点code（orderVasList.vasType为receiveReturnService必填）
            //                "siteName"=>'',//网点名称（orderVasList.vasType为receiveReturnService必填
            //                "remark"=>'',//备注
            //                "orderItems"=>self::goods($param['item']),
            "orderItems"       => [],
            "cabinet"          => (object)[],
        ];
    }

    /**
     * 发件人信息
     *
     * @param $Param
     */
    protected function getSender()
    {

        return [
            'senderPhone'    => '',//发件人座机（与senderMobile二者不能同时为空）
            'senderName'     => 'RHF',//发件人姓名
            'senderAddress'  => '春海路青岛润亨丰',//发件人地址
            'senderDistrict' => '城阳区',//发件人区
            'senderMobile'   => '17667546005',//发件人手机号（与senderPhone二者不能同时为空）
            'senderProvince' => '山东省',//发件人省
            'senderCity'     => '青岛市',//发件人市
        ];
    }

    /**
     * 收件人信息
     *
     * @param $Param
     */
    protected function getReceiver($Param)
    {
        return [
            'receiverCity'     => $Param['city'] ?? '',//收件人市
            'receiverAddress'  => $Param['detailed'] ?? '',//收件人详细地址
            'receiverPhone'    => '',//收件人座机（与receiverMobile二者不能同时为空）
            'receiverName'     => $Param['name'] ?? '',//收件人姓名
            'receiverDistrict' => $Param['district'] ?? '',//收件人区
            'receiverMobile'   => $Param['mobile'] ?? '',
            //收件人手机号（与 receiverPhone二者不能同时为空）
            'receiverProvince' => $Param['province'] ?? '',//收件人省
        ];
    }

    /**
     * 编辑商品数据
     *
     * @param $Param
     *
     * @return array
     */
    protected function getItems($Param)
    {
        $Goods = array();
        if (is_array($Param)) {
            $i = 0;
            foreach ($Param as $key => $val) {
                $Goods[$key]['name']      = $val['title'];
                $Goods[$key]['category']  = $val['title'];
                $Goods[$key]['material']  = '';
                $Goods[$key]['size']      = '';
                $Goods[$key]['weight']    = '';
                $Goods[$key]['unitprice'] = '';
                $Goods[$key]['quantity']  = $val['num'];
                $Goods[$key]['remark']    = $val['amount'];
                $i++;
            }
        }

        return $Goods;
    }
}
