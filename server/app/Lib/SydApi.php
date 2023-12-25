<?php
/**
 * 对称加密解密
 */

namespace App\Lib;

class SydApi
{
    private $Request = null;
    private $app_key = '10002';
    private $app_secret = '5FXnAGEGzgSSCJvVxPZZWs7SIiSGyXfTvDy';
    private $link = 'https://gw.api.huijiedan.com/router/rest/v1.do';

    public function __construct()
    {
        if ($this->Request == null) {
            $this->Request = new Requests();
        }
    }

    /**
     * 获取地区列表
     */
    public function getArea($parentId, $countryId)
    {
        $paramsBase = array(
            "parent_id"  => $parentId,
            "country_id" => $countryId,
        );
        $params     = $this->getParams('tool.base.area.get', $paramsBase);
        $ret        = $this->send($params);
        if ($ret == false) {
            return false;
        } else {
            return $ret;
        }
    }

    /**
     * 地址解析
     */
    public function parseAddress($country, $string)
    {
        $paramsBase = array(
            "country" => $country,
            "q"       => $string,
        );
        $params     = $this->getParams('app.analyze.contacts.get', $paramsBase);
        $ret        = $this->send($params);
        if ($ret == false) {
            return false;
        } else {
            return $this->getRet($ret);
        }
    }

    /**
     * 批量地址解析
     */
    public function parseAddressBatch($country, $arr)
    {
        $paramsBase = array(
            "country" => $country,
            "batch"   => $this->getAddressArrRet($arr),
        );
        $params     = $this->getParams(
            'app.analyze.lists.address.get', $paramsBase
        );
        $ret        = $this->sendPost($params);
        if ($ret == false) {
            return false;
        } else {
            $result = array();
            foreach ($ret as $item) {
                $result[] = $this->getRet($item);
            }

            return $result;
        }
    }


    /**
     * 批量地址解析
     */
    public function parseContacts($country, $address)
    {
        $paramsBase = array(
            "country" => $country,
            "q"       => $address,
        );
        $params     = $this->getParams('app.analyze.contacts.get', $paramsBase);
        $ret        = $this->sendPost($params);
        if ($ret == false) {
            return false;
        } else {
            return $ret;
        }
    }


    /**
     * 商品标题关键词解析
     */
    public function parseGoodTitle($country, $string)
    {
        $paramsBase = array(
            "country" => $country,
            "q"       => $string,
        );
        $params     = $this->getParams('app.analyze.word.get', $paramsBase);
        $ret        = $this->send($params);
        if ($ret == false) {
            return false;
        } else {
            return $this->getGoodWord($ret);
        }
    }

    /**
     *  检测是否符合通关编码要求
     */
    public function IsIdCardCodeGate($code, $name)
    {
        if (empty($code) || empty($name)) {
            return false;
        }
        $paramsBase = [
            'code' => $code,
            'name' => $name,
        ];
        $params     = $this->getParams('app.customs.kr.cert.get', $paramsBase);

        return json_decode($this->sendReturnMessage($params), true);
    }

    /**
     * 检测是否符合通关编码要求
     */
    public function IsIdCardCodeGate1($code, $name, $tel = '')
    {
        if (empty($code) || empty($name)) {
            return false;
        }
        $paramsBase = [
            'code' => $code,
            'name' => $name,
        ];
        if (!empty($tel)) {
            //$paramsBase['tel'] = substr($tel,-4);
            $paramsBase['tel']       = str_replace('-', '', $tel);
            $paramsBase['user_type'] = 347;
        }
        $params = $this->getParams('syd.customs.cert.get', $paramsBase);

        return json_decode($this->sendReturnMessage($params), true);
    }

    /**
     *  获取商品信息
     */
    public function GetBarCode($BarCode)
    {
        $paramsBase = [
            "barcode"   => $BarCode,
            "area_code" => 'CHN',
        ];
        $params     = $this->getParams('app.gs1.barcode.get', $paramsBase);

        return $this->sendReturnMessage($params);
    }

    /**
     * 获取请求参数
     */
    private function getParams($method, $arr)
    {
        $params            = array(
            "method"      => $method,
            "app_key"     => $this->app_key,
            "timestamp"   => date('Y-m-d H:i:s'),
            "format"      => 'json',
            "v"           => '1.0',
            "sign_method" => 'md5',
        );
        $params            += $arr;
        $params['sign']    = $this->MethodMd5($params);
        $params['session'] = $this->app_secret;

        return $params;
    }

    /**
     * 地址批量解析生成接口所需格式
     */
    private function getAddressArrRet($arr)
    {
        if (empty($arr)) {
            return $arr;
        }
        $arrRet = array();
        foreach ($arr as $key => $item) {
            $arrRet[] = array(
                'id' => md5($item),
                'q'  => $item,
            );
        }

        return json_encode($arrRet, 256);
    }

    /**
     * 计算Method的MD5签名
     */
    protected function MethodMd5($params)
    {
        $stringToBeSigned = $this->app_secret;
        $stringToBeSigned .= $this->MethodSign($params);
        $stringToBeSigned .= $this->app_secret;

        return strtoupper(md5($stringToBeSigned));
    }

    /**
     * 获取单条结果
     */
    private function getRet($ret)
    {
        if (isset($ret['total']) && $ret['total'] == 1) {
            $result = array(
                "id"       => isset($ret['id']) ? $ret['id'] : '',
                "country"  => isset($ret['source']['country']['name'])
                    ? $ret['source']['country']['name'] : '',
                "province" => isset($ret['source']['province']['name'])
                    ? $ret['source']['province']['name'] : '',
                "city"     => isset($ret['source']['city']['name'])
                    ? $ret['source']['city']['name'] : '',
                "district" => isset($ret['source']['area']['name'])
                    ? $ret['source']['area']['name'] : '',
                "street"   => isset($ret['source']['street']['name'])
                    ? $ret['source']['street']['name'] : '',
                "address"  => isset($ret['source']['detail'])
                    ? $ret['source']['detail'] : '',
            );
        } else {
            $result = array(
                "id"       => isset($ret['id']) ? $ret['id'] : '',
                "country"  => '',
                "province" => '',
                "city"     => '',
                "district" => '',
                "street"   => '',
                "address"  => '',
            );
        }

        return $result;
    }

    /**
     *  整理
     */
    public function getGoodWord($ret)
    {
        return $ret;
    }

    /**
     * 拼装数据
     */
    protected function MethodSign($params)
    {
        ksort($params);
        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = $this->MethodSign($v); //递归调用
            }
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);

        return $stringToBeSigned;
    }


    /**
     * 发送请求
     */
    private function send($params)
    {
        $link = $this->createLink($params);
        $ret  = file_get_contents($link);
        $ret  = $this->parse($ret);

        return $ret;
    }

    /**
     * 发送请求
     */
    private function sendReturnMessage($params)
    {
        $link = $this->createLink($params);

        return file_get_contents($link);
    }

    /**
     * post方式发送请求，用于批量方式
     */
    private function sendPost($params)
    {
        $ret = $this->Request->post($this->link, $params);
        $ret = $this->parse($ret);

        return $ret;
    }

    /**
     * 解析
     */
    private function parse($ret)
    {
        $ret = json_decode($ret, true);
        if (!isset($ret['code']) || $ret['code'] != '200') {
            return false;
        }
        if (!isset($ret['response']) || empty($ret['response'])) {
            return false;
        } else {
            return $ret['response'];
        }
    }

    /**
     * 生成请求的url
     */
    private function createLink($params)
    {
        $link = $this->link;
        $link .= '?';
        $flag = 0;
        foreach ($params as $key => $val) {
            $val = urlencode($val);
            $tmp = "{$key}={$val}";
            if ($flag === 0) {
                $link .= $tmp;
                $flag = 1;
            } else {
                $link .= '&'.$tmp;
            }
        }

        return $link;
    }
}
