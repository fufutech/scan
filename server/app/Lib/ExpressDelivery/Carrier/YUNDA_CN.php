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
use Exception;
use Psr\Log\LoggerInterface;

class YUNDA_CN implements DeliveryInterface
{
    private array $apiConfig;

    private LoggerInterface $logger;

    public function init(&$param)
    {
        $this->logger = Log::get('YUNDA_CN', 'express'); // Log 类

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
        $this->init($param);
        $createOrderInfo = $this->getCreateOrderInfo($param);
        $request_data    = self::Sign($createOrderInfo);

        $this->logger->info('请求参数', ['requestData' => $request_data]);

        $retData = self::post($this->apiConfig['url'], $request_data, 5);

        $this->logger->info('返回数据', ['retData' => $retData]);

        $xmlData  = self::xmlToArray($retData);
        $response = (isset($xmlData['response'])) ? $xmlData['response'] : [];
        if (!empty($response) && $response['status'] == 1) {
            $result['waybill_no']   = (isset($response['mail_no']))
                ? $response['mail_no'] : '0';
            $pdfInfo                = json_decode($response['pdf_info'], true);
            $info                   = $pdfInfo[0][0] ?? [];
            $result['package_wdjc'] = $info['package_wdjc'] ?? '';
            $result['position']     = $info['position'] ?? '';
            $result['position_no']  = $info['position_no'] ?? '';

            return [true, $result];
        }

        return [false, $response['msg'] ?? '取号失败'];
    }

    protected function getCreateOrderInfo($param)
    {
        $apiParam['tid']              = $param['tid'];
        $apiParam['order_serial_no']  = $param['tid'];
        $apiParam['khddh']            = $param['tid'];//防止重复下单
        $apiParam['nbckh']            = $param['tid'];//防止重复下单
        $apiParam['order_type']       = 'common';
        $apiParam['sender']           = $param['sender'];
        $apiParam['receiver']         = $param['receiver'];
        $apiParam['weight']           = number_format(
            $param['weight'] / 1000, 2
        );//重量
        $apiParam['size']             = '';
        $apiParam['value']            = '';
        $apiParam['collection_value'] = '';//重量
        $apiParam['special']          = '';//重量
        $apiParam['isProtectPrivacy'] = '';//重量
        $apiParam['remark']           = '';
        $apiParam['cus_area1']        = '';
        $apiParam['cus_area2']        = '';
        $apiParam['wave_no']          = '';
        $apiParam['items']            = $param['item'];

        return $this->dataXML($apiParam, 'create_order', '', '377');
    }

    /**
     * @param $apiParam
     */
    protected function dataXML($param, $senderCountry, $deliverySiteCode,
        $nodeId
    ) {
        $sort    = rand(100, 999); //保证重新取单号的时候跟之前的订单号不同，不要取回跟之前一样的运单号
        $dataXML = '<orders>';
        $dataXML .= '<order>';
        $dataXML .= '<order_serial_no>'.$param['tid'].$sort
            .'</order_serial_no>';
        $dataXML .= '<khddh>'.$param['tid'].$sort.'</khddh>';
        $dataXML .= '<node_id>'.$nodeId.'</node_id>';
        $dataXML .= '<order_type>'.$this->apiConfig['ptype'].'</order_type>';
        $dataXML .= '<customer_id>'.$this->apiConfig['traderId']
            .'</customer_id>';
        $dataXML .= '<sender_id>'.$senderCountry.'</sender_id>';
        $dataXML .= '<receiver_id>China</receiver_id>';
        $dataXML .= '<isProtectPrivacy>0</isProtectPrivacy>';
        $dataXML .= '<delivery_status>0</delivery_status>';
        $dataXML .= $this->getSender($param['sender']);
        $dataXML .= $this->getReceiver($param['receiver']);
        $dataXML .= '<one_code></one_code>';
        $dataXML .= '<two_code></two_code>';
        $dataXML .= '<three_code></three_code>';
        $dataXML .= '<weight></weight>';
        $dataXML .= '<size></size>';
        $dataXML .= '<value></value>';
        $dataXML .= $this->getGoods(
            !empty($param['item']) ? $param['item'] : []
        );
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

    protected function getSender($sender): string
    {
        $state     = $this->apiConfig['province'] ?? $sender['province'];
        $city      = $this->apiConfig['city'] ?? $sender['city'];
        $name      = $this->apiConfig['name'] ?? $sender['name'];
        $senderXml = '<sender>';
        $senderXml .= '<name>'.$name.'</name>';
        $senderXml .= '<company>RHF</company>';
        $senderXml .= '<city>'.$state.$city.'</city>';
        $senderXml .= '<address>'.$state.$city.'</address>';
        $senderXml .= '<phone>'.$sender['mobile'].'</phone>';
        $senderXml .= '<mobile>'.$sender['mobile'].'</mobile>';
        $senderXml .= '<branch>'.'</branch>';
        $senderXml .= '</sender>';

        return $senderXml;
    }

    protected function getReceiver($receiver): string
    {
        $receiverXml = '<receiver>';
        $receiverXml .= '<name>'.$receiver['name'].'</name>';
        $receiverXml .= '<company>RHF</company>';
        $receiverXml .= '<city>'.$receiver['province'].$receiver['city']
            .$receiver['district'].'</city>';
        $receiverXml .= '<address>'.$receiver['province'].$receiver['city']
            .$receiver['district'].$receiver['detailed'].'</address>';
        $receiverXml .= '<phone>'.$receiver['mobile'].'</phone>';
        $receiverXml .= '<mobile>'.$receiver['mobile'].'</mobile>';
        $receiverXml .= '<postcode></postcode>';
        $receiverXml .= '<branch>'.'</branch>';
        $receiverXml .= '</receiver>';

        return $receiverXml;
    }

    protected function getGoods($item): string
    {

        $goodsXml = '<items>';
        foreach ($item as $key => $value) {
            $goodsXml .= '<item>';
            $goodsXml .= '<name>'.$value['title'].'</name>';
            $goodsXml .= '<number>'.$value['num'].'</number>';
            $goodsXml .= '<remark>'.$value['amount'].'</remark>';
            $goodsXml .= '</item>';
        }
        $goodsXml .= '</items>';

        return $goodsXml;
    }

    protected function phone($Param)
    {
        $mobileRule = '/^\+[1-9]{1,3}[\s]/';
        $telRule    = '/^\+[1-9]{1,3}[\s]/';
        if (isset($Param['mobile'])) {
            $Param['mobile'] = preg_replace($mobileRule, '', $Param['mobile']);
        };
        if (isset($Param['tel'])) {
            $Param['tel'] = preg_replace($telRule, '', $Param['tel']);
        };

        return isset($Param['mobile']) && !empty($Param['mobile'])
            ? $Param['mobile'] : $Param['tel'];
    }


    protected function Sign($createOrderInfo)
    {
        $xmldata    = base64_encode($createOrderInfo);
        $validation = strtolower(
            MD5(
                $xmldata.$this->apiConfig['traderId'].$this->apiConfig['passwd']
            )
        );

        return [
            'request'    => $this->apiConfig['request'],
            'version'    => '1.0',
            'validation' => $validation,
            'xmldata'    => $xmldata,
            'partnerid'  => $this->apiConfig['traderId'],
        ];
    }


    protected static function xmlToArray($xml)
    {

        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $xml, true)) {
            xml_parser_free($xml_parser);

            return false;
        } else {
            libxml_disable_entity_loader(true);
            $xmlstring = simplexml_load_string(
                $xml, 'SimpleXMLElement', LIBXML_NOCDATA
            );
            $xmlstring = json_decode(json_encode($xmlstring), true);

            return $xmlstring;
        }
    }

    function data_to_xml($data, $item = 'item', $id = 'id')
    {
        $xml = $attr = '';
        foreach ($data as $key => $val) {
            if (is_numeric($key)) {
                $id && $attr = " {$id}=\"{$key}\"";
                $key = $item;
            }
            $xml .= "<{$key}{$attr}>";
            $xml .= (is_array($val) || is_object($val)) ? $this->data_to_xml(
                $val, $item, $id
            ) : $val;
            $xml .= "</{$key}>";
        }

        return $xml;
    }

    public static function post($url, array $post = array(), $timeout = 5,
        array $options = array()
    ) {
        $defaults = array(
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_URL            => $url,
            CURLOPT_FRESH_CONNECT  => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE   => 1,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            // 生成 URL-encode 之后的请求字符串，如果用该函数自动进行urlencode编码，请知悉
        );
        $ch       = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            $errorMessage = '调用接口错误，接口URL-'.$url.' ,错误信息-'.$error;
            throw new Exception($errorMessage, curl_errno($ch));
        }
        curl_close($ch);

        return $result;
    }
}
