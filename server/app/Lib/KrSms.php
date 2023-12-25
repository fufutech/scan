<?php
/**
 * 韩国短信发送
 */

namespace App\Lib;

class KrSms
{
    private $Request = null;
    private $app_key = '10002';
    private $app_secret = '5FXnAGEGzgSSCJvVxPZZWs7SIiSGyXfTvDy';
    private $link = 'https://gw.api.huijiedan.cn';

    /**
     * 实例化
     * KrSms constructor.
     */
    public function __construct()
    {
        if ($this->Request == null) {
            $this->Request = new Requests();
        }
    }

    /**
     * 发送短信验证码
     *
     * @param $mobile
     * @param $code
     * @param $sign
     */
    public function send($mobile, $code, $sign)
    {
        $params = [
            'mobile'    => $mobile,
            'code'      => $code,
            'send_sign' => $sign,
        ];
        $ret    = $this->sendPost('kr.sms.get', $params);
        if ($ret['code'] == 200) {
            return true;
        } else {
            return "发送失败:".isset($ret['msg']) ? $ret['msg'] : '原因未知';
        }
    }

    /**
     * 发送post请求
     *
     * @param $method
     * @param $params
     *
     * @return bool
     */
    private function sendPost($method, $params)
    {
        $paramsRet = $this->handleParams($method, $params);
        $ret       = $this->Request->post(
            $this->link.'/router/rest/v1.do', $paramsRet
        );
        $ret       = $this->parse($ret);

        return $ret;
    }

    /**
     * 处理请求头
     *
     * @param $method
     * @param $params
     *
     * @return array
     */
    private function handleParams($method, $params)
    {
        $paramsRet            = array(
            "method"      => $method,
            "app_key"     => $this->app_key,
            "timestamp"   => date('Y-m-d H:i:s'),
            "format"      => 'json',
            "v"           => '1.0',
            "sign_method" => 'md5',
        );
        $paramsRet            = array_merge($paramsRet, $params);
        $paramsRet['sign']    = $this->MethodMd5($paramsRet);
        $paramsRet['session'] = $this->app_secret;

        return $paramsRet;
    }

    /**
     * 计算Method的MD5签名
     *
     * @param $params
     *
     * @return string
     */
    protected function MethodMd5($params)
    {
        $stringToBeSigned = $this->app_secret;
        $stringToBeSigned .= $this->MethodSign($params);
        $stringToBeSigned .= $this->app_secret;

        return strtoupper(md5($stringToBeSigned));
    }

    /**
     * 拼装数据
     *
     * @param $params
     *
     * @return string
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
     * 解析
     *
     * @param $ret
     *
     * @return bool
     */
    private function parse($ret)
    {
        $ret = json_decode($ret, true);
        if (!isset($ret['code']) || $ret['code'] != '200') {
            return false;
        }
        if (!isset($ret['response']) || empty($ret['response'])) return false;
        else return $ret['response'];
    }
}