<?php

namespace App\Lib;

use App\Exception\ApiException;

class Curl
{
    protected static array $header = [];

    protected static string $method = 'POST';

    protected static string $type = 'json';

    protected static array $opt
        = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
        ];

    public static function getUrl(array $query): string
    {
        $queryUrl = '';
        foreach ($query as $key => $value) {
            $queryUrl .= $key.'='.$value.'&';
        }

        return '?'.trim($queryUrl, '&');
    }

    public static function setHeader(array $header = []): Curl
    {
        if ($header) {
            self::$opt[CURLOPT_HTTPHEADER] = $header;
        } else {
            unset(self::$opt[CURLOPT_HTTPHEADER]);
        }

        return new self();
    }

    public static function setType(string $type = 'json'): Curl
    {
        self::$type = $type;

        return new self();
    }

    public static function setMethod(string $method = 'post'): Curl
    {
        if ($method) {
            self::$opt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
        } else {
            unset(self::$opt[CURLOPT_CUSTOMREQUEST]);
        }

        return new self();
    }

    public static function send(string $url, mixed $data = []): mixed
    {
        $opt                     = self::$opt;
        $opt[CURLOPT_URL]        = $url;
        $opt[CURLOPT_POSTFIELDS] = $data;

        $curl = curl_init();

        curl_setopt_array($curl, $opt);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

//        echo date('Y-m-d H:i:s') . ' CURL ' . json_encode([self::$type, $response, json_decode($response, true)], 256) . PHP_EOL;

        curl_close($curl);

        if ($err) {
            throw new ApiException("cURL Error #:".$err);
        } else {
            switch (self::$type) {
                default:
                case 'json':
                    $data = json_decode($response, true);
                    break;
                case 'xml':
                    $data = Data::xmlToArray($response);
                    break;
            }
            if (!$data) {
                return $response;
            }

            return $data;
        }
    }
}