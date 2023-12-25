<?php

namespace App\Lib;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Response;

class ExportLogic
{
    /**
     * Describe:   导数数据 （csv 格式）
     *
     * @param  array   $head
     * @param  array   $body
     * @param  string  $fileName  '测试.csv','测试.xlsx'
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    static function export(array $head, array $body, string $fileName)
    {
        $head_keys   = array_keys($head);
        $head_values = array_values($head);
        $fileData    = self::utfToGbk(implode(',', $head_values))."\n";

        foreach ($body as $value) {
            $temp_arr = [];
            foreach ($head_keys as $key) {
                $temp_arr[] = $value[$key] ?? '';
            }
            $fileData .= self::utfToGbk(implode(',', $temp_arr))."\n";
        }
        $response    = new Response();
        $contentType = 'text/csv';

        return $response->withHeader('content-description', 'File Transfer')
            ->withHeader('content-type', $contentType)
            ->withHeader(
                'content-disposition', "attachment; filename={$fileName}"
            )
            ->withHeader('content-transfer-encoding', 'binary')
            ->withHeader('pragma', 'public')
            ->withBody(new SwooleStream($fileData));
    }

    /**
     * 字符转换（utf-8 => GBK）
     *
     * @param $data
     *
     * @return false|string
     */
    static function utfToGbk($data)
    {
        return mb_convert_encoding($data, "GBK", "UTF-8");
        # return iconv('utf-8', 'GBK', $data);
    }
}
