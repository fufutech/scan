<?php

namespace App\Lib\ExpressDelivery\Carrier\SDK\ZTO;

class ZopHttpUtil
{
    public function post($url, $headers, $querystring, $timeout)
    {
        echo 'post:'.json_encode([$url, $headers, $querystring],
                JSON_UNESCAPED_UNICODE).PHP_EOL;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);//设置链接
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置是否返回信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);//设置HTTP头
        curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式
//        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $querystring);

        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false); #使用DNS缓存
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);#禁用IPV6

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return "cURL Error #:".$err;
        }

        return $response;
    }

}