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

use App\Lib\Curl;
use App\Lib\ExpressDelivery\DeliveryInterface;
use App\Log;
use Psr\Log\LoggerInterface;

class YUNDA implements DeliveryInterface
{
    private array $order = [];

    private array $receiver = [];

    private array $sender = [];

    private array $items = [];

    private array $apiConfig;

    private LoggerInterface $logger;

    public function init(&$param)
    {
        $this->logger = Log::get('YUNDA', 'express'); // Log 类
        $this->logger->info('OrderCreate', ['param' => $param]);

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
            $request_data = $this->Sign();
            $this->logger->info('RequestData', ['requestData' => $request_data]
            );

            $WaybillData = Curl::setType('xml')->setMethod('')->setHeader()
                ->send(
                    $this->apiConfig['url'], http_build_query($request_data)
                );
//            echo date('Y-m-d H:i:s') . ' YUNDA ' . json_encode(['param' => $param, 'body' => $request_data, 'b' => http_build_query($request_data), 'WaybillData' => $WaybillData], 256) . PHP_EOL;

//            echo "OrderCreate：" . json_encode($WaybillData, JSON_UNESCAPED_UNICODE) . PHP_EOL;

            $this->logger->info('SendPost', ['waybillData' => $WaybillData]);

            $response = (isset($WaybillData['response']))
                ? $WaybillData['response'] : [];

            if (!empty($response) && $response['status'] == 1) {
                $result['waybill_no']   = $response['mail_no'] ?? '';
                $pdfInfo                = json_decode(
                    $response['pdf_info'], true
                );
                $info                   = $pdfInfo[0][0] ?? [];
                $result['package_wdjc'] = $info['package_wdjc'] ?? '';
                $result['position']     = $info['position'] ?? '';
                $result['position_no']  = $info['position_no'] ?? '';

                return [true, $result];
            } else {
                $message = (isset($response['msg'])) ? $response['msg']
                    : '接口请求失败'; //失败

                return [false, $message];
            }
        } catch (\Exception $e) {
            return [false, '取号报错：'.$e->getMessage()];
        }
    }

    /**
     * @return array
     */
    protected function Sign()
    {
        $xmldata    = base64_encode($this->getOrderCreateData());
        $validation = strtolower(
            MD5(
                $xmldata.$this->apiConfig['traderId'].$this->apiConfig['passwd']
            )
        );

        return [
            'request'    => 'gosOrderApi', //目前写死
            'version'    => '1.0',
            'validation' => $validation,
            'xmldata'    => $xmldata,
            'partnerid'  => $this->apiConfig['traderId'],
        ];
    }

    public function getOrderCreateData(): string
    {
        $dataXML = '<orders>';
        $dataXML .= '<order>';
        $dataXML .= '<order_serial_no>'.$this->order['tid']
            .'</order_serial_no>';
        $dataXML .= '<khddh>'.$this->order['tid'].'</khddh>';
        $dataXML .= '<node_id>'.$this->apiConfig['cusId'].'</node_id>';
        $dataXML .= '<order_type>'.$this->apiConfig['ptype'].'</order_type>';
        $dataXML .= '<customer_id>'.$this->apiConfig['traderId']
            .'</customer_id>';
        $dataXML .= '<sender_id>'.($this->apiConfig['sender_country'] ?? 'KR')
            .'</sender_id>';
        $dataXML .= '<receiver_id>CN</receiver_id>';
        $dataXML .= '<isProtectPrivacy>0</isProtectPrivacy>';
        $dataXML .= '<delivery_status>0</delivery_status>';
        $dataXML .= $this->getSender();
        $dataXML .= $this->getReceiver();
        $dataXML .= '<one_code></one_code>';
        $dataXML .= '<two_code></two_code>';
        $dataXML .= '<three_code></three_code>';
        $dataXML .= '<weight></weight>';
        $dataXML .= '<size></size>';
        $dataXML .= '<value></value>';
        $dataXML .= $this->getItems();
        $dataXML .= '<remark></remark>';
        $dataXML .= '<cus_area1></cus_area1>';
        $dataXML .= '<cus_area2></cus_area2>';
        $dataXML .= '<multi_pack></multi_pack>';
        //        $dataXML .='<markingInfos>[{"type":"INSURED","markingValue":{"value":2100}}]</markingInfos>';
        $dataXML .= '<markingInfos></markingInfos>';
        $dataXML .= '</order>';
        $dataXML .= '</orders>';

        return $dataXML;

    }

    public function getSender(): string
    {
        $name      = $this->sender['name'] ?? '';
        $province  = $this->sender['province'] ?? '';
        $city      = $this->sender['city'] ?? '';
        $district  = $this->sender['district'] ?? '';
        $detailed  = $this->sender['detailed'] ?? '';
        $area_code = $this->sender['area_code'] ?? '';
        $mobile    = $this->sender['mobile'] ?? '';

        $senderXml = '';
        $senderXml .= '<sender>';
        $senderXml .= '<name>'.$name.'</name>';
        $senderXml .= '<company>RHF</company>';
        $senderXml .= '<city>'.$province.$city.$district.'</city>';
        $senderXml .= '<address>'.$province.$city.$district.$detailed
            .'</address>';
        $senderXml .= '<phone>'.($area_code.$mobile).'</phone>';
        $senderXml .= '<mobile>'.($area_code.$mobile).'</mobile>';
        $senderXml .= '<branch>'.$this->apiConfig['siteCode'].'</branch>';
        $senderXml .= '</sender>';

        return $senderXml;
    }

    public function getReceiver($deliverySiteCode = ''): string
    {
        $name      = $this->receiver['name'] ?? '';
        $province  = $this->receiver['province'] ?? '';
        $city      = $this->receiver['city'] ?? '';
        $district  = $this->receiver['district'] ?? '';
        $detailed  = $this->receiver['detailed'] ?? '';
        $area_code = $this->receiver['area_code'] ?? '';
        $mobile    = $this->receiver['mobile'] ?? '';

        $receiverXml = '';
        $receiverXml .= '<receiver>';
        $receiverXml .= '<name>'.$name.'</name>';
        $receiverXml .= '<company>RHF</company>';
        $receiverXml .= '<city>'.$province.$city.$district.'</city>';
        $receiverXml .= '<address>'.$province.$city.$district.$detailed
            .'</address>';
        $receiverXml .= '<phone>'.$mobile.'</phone>';
        $receiverXml .= '<mobile>'.$mobile.'</mobile>';
        $receiverXml .= '<postcode></postcode>';
        $receiverXml .= '<branch>'.$deliverySiteCode.'</branch>';
        $receiverXml .= '</receiver>';

        return $receiverXml;
    }

    public function getItems(): string
    {
        $itemXml = '<items>';
        foreach ($this->items as $value) {
            $itemXml .= '<item>';
            $itemXml .= '<name>'.($value['title'] ?? '').'</name>';
            $itemXml .= '<number>'.($value['num'] ?? 0).'</number>';
            $itemXml .= '<remark>'.($value['spec'] ?? '').'</remark>';
            $itemXml .= '</item>';
        }
        $itemXml .= '</items>';

        return $itemXml;
    }
}
