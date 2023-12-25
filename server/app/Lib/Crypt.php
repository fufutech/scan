<?php

namespace App\Lib;


class Crypt
{
    protected $encryptMethod = 'aes-256-cbc';
    protected $passwordEnd = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9Hl}U|rW52gHL:E0V;M$K%|E&WP2Z$7CasA';
    protected $cryptKey = ''; //加解密key

    protected function randomStr($passwordEnd = '')
    {
        $this->cryptKey = empty($passwordEnd) ? $this->passwordEnd
            : $passwordEnd;

        return $this->cryptKey;
    }

    /**
     */
    public function sslList()
    {
        $lists = openssl_get_cipher_methods();

        return $lists;
    }


    /**
     */
    public function encrypt($decrypt_str, $passwordEnd = '')
    {
        $passwordKey = $this->randomStr($passwordEnd);
        // 生成IV
        $iv = md5($passwordKey, true);
        // 加密
        $encrypted = openssl_encrypt(
            $decrypt_str, $this->encryptMethod, $passwordKey, 0, $iv
        );

        return $encrypted;
    }


    /**
     */
    public function decrypt($encrypt_str, $passwordEnd = '')
    {
        $passwordKey = $this->randomStr($passwordEnd);
        // 生成IV
        $iv = md5($passwordKey, true);
        // 解密
        $decrypted = openssl_decrypt(
            $encrypt_str, $this->encryptMethod, $passwordKey, 0, $iv
        );
        if (empty($decrypted)) {
            throw new Exception('解密失败');
        }

        return $decrypted;
    }


}
