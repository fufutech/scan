<?php

namespace App\Lib\hjd;


use App\Exception\ApiException;

class Client
{
    public $apiConfig;

    protected $app_key = '10003';
    protected $app_secret = 'FKssBWZPJDW6DNz74cwwjtpziMccjzmRx1B';
    protected $sysParams = []; //系统参数
    protected $checkRequest = false; //true 测试环境 false:正式环境
    protected $checkHttps = true; //检测签名

    private $_Param = array();

    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->app_key    = $config['app_key'] ?? $this->app_key;
            $this->app_secret = $config['app_secret'] ?? $this->app_secret;
        }
        $this->setTestMode();
    }

    public function __set($name, $value)
    {
        $this->_Param['param'][$name] = $value;
    }

    public function __get($name)
    {
        if (!empty($this->_Param['param'][$name])) {
            return $this->_Param['param'][$name];
        }
    }

    public function __unset($name)
    {
        unset($this->_Param['param'][$name]);
    }

    public function setTestMode()
    {
        $Domain = $this->checkRequest ? 'hjd.com' : 'huijiedan.cn';
        if ($this->checkRequest) {
            $this->apiConfig['Url'] = 'http://api.'.$Domain
                .'/router/rest/v1.do';
        } else {
            $this->apiConfig['Url'] = 'https://gw.api.'.$Domain
                .'/router/rest/v1.do';
        }
    }

    protected function sysParams($method = '')
    {
        try {
            $method      = empty($method) ? $this->method : $method;
            $format      = $this->_Param['format'] ?? 'json';
            $v           = $this->_Param['v'] ?? '1.0';
            $sign_method = $this->_Param['sign_method'] ?? 'md5';

        } catch (\Throwable $e) {
            throw new ApiException('请设置 method');
        }

        $this->sysParams = array(
            "method"      => $method,
            "app_key"     => $this->app_key,
            "timestamp"   => date('Y-m-d H:i:s'),
            "format"      => $format,
            "v"           => $v,
            "sign_method" => $sign_method,
        );

        return $this->sysParams;
    }

    protected function methodMd5($params, $body)
    {
        $stringToBeSigned = $this->app_secret;
        $stringToBeSigned .= $this->methodSign($params);
        $stringToBeSigned .= $body;
        $stringToBeSigned .= $this->app_secret;

        return strtoupper(md5($stringToBeSigned));
    }

    protected function methodSign($params)
    {
        ksort($params);
        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = $this->methodSign($v); //递归调用
            }
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);

        return $stringToBeSigned;
    }

    public function execute($apiClass)
    {
        $method               = $apiClass->getApiMethodName();
        $sys_params           = $this->sysParams();
        $sys_params['method'] = $method;
        $body_params          = json_encode($apiClass->getApiParams());

        if ($this->checkHttps) {
            // 生成 sign
            $sys_params['sign'] = $this->methodMd5($sys_params, $body_params);
            if ($this->app_secret) {
                $sys_params['session'] = $this->app_secret;
            }
        }
        //当前接口url
        $currentLink = $this->apiConfig['Url'].'?'.http_build_query(
                $sys_params
            );

        // 发送http请求
        $res = json_decode($this->curl($currentLink, $body_params), true);

        return $this->handleResult($res);
    }

    protected function handleResult($res)
    {
        if (isset($res['code']) && $res['code'] == 200) {
            return $res['response'] ?? [];
        } else {
            $error_response = $res['error_response'] ?? [];
            throw new ApiException(
                $error_response['msg'] ??
                json_encode($error_response, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    private static function jsonDecode($str)
    {
        if (defined('JSON_BIGINT_AS_STRING')) {
            return json_decode($str, true, 512, JSON_BIGINT_AS_STRING);
        } else {
            return json_decode($str, true);
        }
    }

    public function curl($Url, $body = null)
    {
        $ch = curl_init();
        //在http 请求头加入 gzip压缩
        $headers = array();
        //$headers[] = 'User-Agent: Apipost client Runtime/+https://www.apipost.cn/';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept-Encoding: gzip';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //curl返回的结果，采用gzip解压
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        // 在尝试连接时等待的秒数
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        // 最大执行时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        // https 请求
        if (strlen($Url) > 5 && strtolower(substr($Url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_POST, true);


        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $reponse = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            throw new ApiException('接口返回404错误');
        }
        curl_close($ch);

        return $reponse;
    }

}