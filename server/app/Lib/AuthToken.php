<?php

namespace App\Lib;

/**
 * TOKEN 加解密
 */
class AuthToken
{
    const SplitNumber = 6;
    const SplitTotal = 32;

    /**
     * 获取Token
     *
     * @param $UserData
     *
     * @return array
     */
    public static function enToken($UserData)
    {
        $MD5Str          = md5(
            time().json_encode(AuthToken::MethodSign($UserData)).time()
        );
        $base64Str       = base64_encode(
            urlencode(json_encode($UserData, JSON_UNESCAPED_UNICODE))
        );
        $SplitMD5String  = self::SplitMD5String($MD5Str);
        $result['name']  = $MD5Str;
        $result['token'] = $SplitMD5String['start'].$base64Str
            .$SplitMD5String['end'];

        return $result;
    }

    /**
     * 解析提交数据
     *
     * @param $TokenStr
     *
     * @return array
     */
    public static function deToken($TokenStr)
    {

        $length          = strlen($TokenStr);
        $result['start'] = substr($TokenStr, 0, self::SplitNumber);
        $center          = substr(
            $TokenStr, self::SplitNumber, ($length - self::SplitTotal)
        );
        $endStart        = $length - (self::SplitTotal - self::SplitNumber);
        $result['end']   = substr($TokenStr, $endStart, $length);

        $token['data'] = urldecode(base64_decode($center));
        $token['name'] = $result['start'].$result['end'];
        unset($result, $length, $center, $endStart);

        return $token;
    }

    /**
     * 分割MD5
     *
     * @param $String
     *
     * @return array
     */
    public static function SplitMD5String($String)
    {

        $result['start'] = substr($String, 0, self::SplitNumber);
        $result['end']   = substr($String, self::SplitNumber, 32);

        return $result;
    }

    /**
     * 拼装数据
     *
     * @param $params
     *
     * @return string
     */
    private static function MethodSign($params)
    {
        ksort($params);
        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = AuthToken::MethodSign($v); //递归调用
            }
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);

        return $stringToBeSigned;
    }
}
