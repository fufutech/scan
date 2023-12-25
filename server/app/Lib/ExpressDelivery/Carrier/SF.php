<?php

namespace App\Lib\ExpressDelivery\Carrier;

use App\Lib\ExpressDelivery\DeliveryInterface;
use App\Log;
use Psr\Log\LoggerInterface;

class SF implements DeliveryInterface
{
    protected string $partnerID;

    protected string $checkWord;

    protected string $serviceCode;

    protected array $methodParam; // 承运商数据库中配置的参数

    private LoggerInterface $logger;

    public function __construct()
    {
        // 获取配置
//        $this->partnerID = "Y7hHmkqf";//此处替换为您在丰桥平台获取的顾客编码 - 沙箱
        $this->partnerID = "RHFGJMY";//此处替换为您在丰桥平台获取的顾客编码

//        $this->checkWord = "ehpA2SxjBT0M0QY74VktZbPSx41xO9gt";//此处替换为您在丰桥平台获取的校验码 - 沙箱
        $this->checkWord
            = "hX1V7B241XmQdLpcpAMZY2VJ3T1JX3yf";//此处替换为您在丰桥平台获取的校验码
    }

    public function init()
    {
        $this->logger = Log::get('SF', 'express'); // Log 类

        $this->serviceCode = 'EXP_RECE_CREATE_ORDER'; // 下订单接口-速运类API
    }

    public function OrderCreate(mixed &$param): array
    {
        try {
            $this->init();

            $this->logger->info('OrderCreate', ['param' => $param]);

            $courierArguments = $param['channel']['courier']['arguments'] ?? '';
            if ($courierArguments) {
                $arguments = explode("\n", $courierArguments);
                foreach ($arguments as $val) {
                    $Arr                  = explode('|', $val);
                    $methodParam[$Arr[0]] = trim($Arr[1]);
                }
            }

            $this->methodParam = $methodParam ?? [];

            $requestID = $param['tid'] ?? '';

            $msgData = $this->handleMsgData($param);

            $timestamp = time();

            $msgDigest = base64_encode(
                md5((urlencode($msgData.$timestamp.$this->checkWord)), true)
            ); //通过MD5和BASE64生成数字签名

            //发送参数
            $post_data = array(
                'partnerID'   => $this->partnerID,
                'requestID'   => $requestID,
                'serviceCode' => $this->serviceCode,
                'timestamp'   => $timestamp,
                'msgDigest'   => $msgDigest,
                'msgData'     => $msgData,
            );

            $this->logger->info('postData', ['postData' => $post_data]);

            //沙箱环境的地址
            $CALL_URL_BOX = "http://sfapi-sbox.sf-express.com/std/service";
            //生产环境的地址
            $CALL_URL_PROD = "https://sfapi.sf-express.com/std/service";

            $response = $this->send_post($CALL_URL_PROD, $post_data); //沙盒环境

            $this->logger->info('sendPost', ['response' => $response]);

            $response = json_decode($response, true);

            $apiResultData = $response['apiResultData'] ?? '';
            if ($apiResultData) {
                $apiResultData = is_array($apiResultData) ? $apiResultData
                    : json_decode($apiResultData, true);
                $errorCode     = $apiResultData['errorCode'] ?? '';
            }

            // 成功
            if (isset($errorCode) && $errorCode === 'S0000') {
                $waybillNoInfoList
                                = $apiResultData['msgData']['waybillNoInfoList']
                    ?? []; // 顺丰运单号
                $routeLabelInfo = $apiResultData['msgData']['routeLabelInfo'] ??
                    []; // 路由标签，除少量特殊场景用户外，其余均会返回
                $filterResult   = $apiResultData['msgData']['filterResult'] ??
                    ''; // 筛单结果： 1：人工确认 2：可收派 3：不可以收派
                if ($filterResult != 2) {
                    $filterRemark     = $apiResultData['msgData']['remark'] ??
                        ''; // 如果filter_result=3时为必填， 不可以收派的原因代码： 1：收方超范围 2：派方超范围 3：其它原因 高峰管控提示信息 【数字】：【高峰管控提示信息】 (如 4：温馨提示 ，1：春运延时)
                    $filterRemarkData = [
                        '1' => '收方超范围',
                        '2' => '派方超范围',
                        '3' => '其它原因 高峰管控提示信息',
                        '4' => '温馨提示',
                    ];

                    return [false,
                            '取号遇到问题：'.$filterRemarkData[$filterRemark] ??
                            $filterRemark];
                }

                $motherWaybillNo = '';
                $sonWaybillNo    = [];
                foreach ($waybillNoInfoList as $item) {
                    // 	waybillType	Number (1)	否		运单号类型1：母单 2 :子单 3 : 签回单
                    if (isset($item['waybillType'])
                        && $item['waybillType'] == 1
                    ) {
                        $motherWaybillNo = $item['waybillNo'] ?? ''; // 母单
                    } else {
                        $sonWaybillNo[] = $item['waybillNo'] ?? '';
                    }
                }

                $result['waybill_no'] = $motherWaybillNo;

                $result['package_wdjc']
                    = $routeLabelInfo[0]['routeLabelData']['sourceTransferCode']
                    ?? ''; // 原寄地中转场
                $result['position']
                    = $routeLabelInfo[0]['routeLabelData']['destTransferCode']
                    ?? ''; // 目的地中转场
                $result['position_no']
                    = $routeLabelInfo[0]['routeLabelData']['destRouteLabel'] ??
                    ''; // 若返回路由标签，则此项必会返回。如果手打是一段码，检查是否地址异常。打单时的路由标签信息如果是大网的路由标签,这里的值是目的地网点代码,如果 是同城配的路由标签,这里的值是根据同城配的设置映射出来的值,不同的配置结果会不一样,不能根据-符号切分(如:上海同城配,可能是:集散点-目的地网点-接驳点,也有可能是目的地网点代码-集散点-接驳点)
                $result['proCode']
                    = $routeLabelInfo[0]['routeLabelData']['proCode'] ??
                    ''; // 	时效类型: 值为二维码中的K4
                $result['codingMapping']
                    = $routeLabelInfo[0]['routeLabelData']['codingMapping'] ??
                    ''; // 入港映射码 eg:S10 地址详细必会返回
                $result['checkCode']
                    = $routeLabelInfo[0]['routeLabelData']['checkCode'] ?? '';

                $result['sonWaybillNoList'] = $sonWaybillNo ?? [];

                return [true, $result];
            } else {
                $message = (isset($apiResultData['errorMsg'])) ? 'SF:'
                    .$apiResultData['errorMsg'] : '顺丰接口请求失败'; //失败

                return [false, $message];
            }
        } catch (\Exception $e) {
            return [false, '取号报错：'.$e->getMessage()];
        }
    }

    protected function handleMsgData(&$params): bool|string
    {
        $order_express_type = $params['express_type'] ?? 0; // 快递产品类型（1-标快 2-特快）
        if ($order_express_type == 2) {
            $expressTypeId = 1; // 1-T4-顺丰特快  2-T6-顺丰标快
        } else {
            $expressTypeId = 2;
        }
        // 查看系统中是否配置了产品类型，配置了就可以直接使用，未配置，则根据系统的标快、特快来
        if (!empty($this->methodParam['expressTypeId'])) {
            $expressTypeId = $this->methodParam['expressTypeId'];
        }
        $msgData = [
            "customsInfo"     => [],
            "expressTypeId"   => $expressTypeId,
            // https://open.sf-express.com/developSupport/734349?activeIndex=324604
            "extraInfoList"   => [],
            "isOneselfPickup" => 0,
            "language"        => "zh-CN",
            "monthlyCard"     => $this->methodParam['monthlyCard'] ??
                "7551234567", // 月结卡号
            "orderId"         => $params['tid'], // 客户订单号
            "parcelQty"       => $params['mother']['count'] ?? 1,
            // 包裹数，一个包裹对应一个运单号；若包裹数大于1，则返回一个母运单号和N-1个子运单号
            "payMethod"       => 1, // 付款方式，支持以下值： 1:寄方付 2:收方付 3:第三方付
            "totalWeight"     => $params['mother']['total_weight'] ?? 0,
            // 订单货物总重量（郑州空港海关必填）， 若为子母件必填， 单位千克， 精确到小数点后3位，如果提供此值， 必须>0 (子母件需>6)
            "cargoDetails"    => $this->handleCargoDetails($params), // 托寄物信息
            "contactInfoList" => [
                [
                    "contactType" => 1, // 地址类型： 1，寄件方信息 2，到件方信息
                    "address"     => $this->methodParam['address'] ?? '',
                    "city"        => $this->methodParam['city'] ?? '',
                    "company"     => "",
                    "contact"     => $this->methodParam['sender'] ?? '',
                    "county"      => $this->methodParam['county'] ?? '',
                    "mobile"      => $this->methodParam['tel'] ?? '',
                    "province"    => $this->methodParam['province'] ?? '',
                ],
                [
                    "contactType" => 2,
                    "address"     => $params['receiver']['detailed'] ?? '',
                    "city"        => $params['receiver']['city'] ?? '',
                    "contact"     => $params['receiver']['name'] ?? '',
                    "county"      => $params['receiver']['district'] ?? '',
                    "mobile"      => $params['receiver']['mobile'] ?? '',
                    "province"    => $params['receiver']['province'] ?? '',
                ],
            ],
        ];

        return json_encode($msgData, 256);
    }

    protected function handleCargoDetails(&$params): array
    {
        $item = $params['item'] ?? [];
        $data = [];
        foreach ($item as $value) {
            $data[] = [
                "amount"   => $value['amount'],
                "currency" => 'KRW',
                "count"    => $value['num'] ?? 1,
                "name"     => $value['title'] ?? '',
                "unit"     => "",
                "volume"   => 0,
                "weight"   => 0,
            ];
        }

        return $data;
    }

    protected function send_post($url, $post_data)
    {

        $postdata = http_build_query($post_data);
        $options  = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type:application/x-www-form-urlencoded;charset=utf-8',
                'content' => $postdata,
                'timeout' => 15 * 60, // 超时时间（单位:s）
            ),
        );
        $context  = stream_context_create($options);

        return file_get_contents($url, false, $context);
    }
}
