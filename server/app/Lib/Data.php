<?php

namespace App\Lib;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class Data
{
    /**
     * @DOC 字符串排序
     *
     * @param  string  $string
     * @param  string  $sort
     *
     * @return string
     */
    public static function stringSort(string $string = '', string $sort = 'asc'
    ): string {
        if (empty($string)) {
            return '';
        }
        //拆分成数组，然后排序
        $arr = mb_str_split($string);
        if ($sort == 'asc') {
            sort($arr);//值升序
        } else {
            rsort($arr);//值降序
        }

        //返回新字符串
        return implode('', $arr);
    }

    /**
     * @DOC 通过redis生成唯一订单号
     *
     * @param $dataCenterId
     * @param $workId
     *
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \RedisException
     */
    public static function uuid($dataCenterId = 0, $workId = 0)
    {
        $container = ApplicationContext::getContainer();
        $redis     = $container->get(Redis::class);
        // 格式化当前时间戳
        $dateTime = time();
        // 设置redis键值，每秒钟的请求次数
        $reqNoKey = 'UUID'.$dataCenterId.'_'.$workId.'_'.$dateTime;
        $reqNo    = $redis->incr($reqNoKey); // 将redis值加1
        $redis->expire($reqNoKey, 3); // 设置redis过期时间,避免垃圾数据过多
        $Max   = 99999;
        $reqNo = 1000 + $reqNo; // 补齐订单号长度
        if ($reqNo >= $Max) {
            sleep(1);
        }
        $orderNo = $dateTime.$dataCenterId.$workId.$reqNo; // 生成订单号

        return $orderNo;
    }

    public static function tree($list, $pidName = 'pid', $children = 'children')
    {
        $list  = self::arrayDesignKey($list, 'id');
        $trees = self::handleTree($list, $pidName, $children);
        unset($list);

        return $trees;
    }

    /**
     * @DOC 二维数组指定数组下的一个字段的值为key
     *
     * @param $array
     * @param $design_key
     *
     * @return array
     */
    public static function arrayDesignKey($array, $design_key): array
    {
        $newData = [];
        foreach ($array as $key => $data) {
            if (is_array($design_key)) {
                $key = implode(
                    '-', array_map(function ($value) use ($data) {
                    return $data[$value];
                }, $design_key)
                );
            } else {
                $key = $data[$design_key];
            }
            $newData[$key] = $data;
        }

        return $newData;
    }

    /**
     * @DOC 处理树状数据
     *
     * @param $list
     * @param $pidName
     * @param $children
     *
     * @return array
     */
    protected static function handleTree($list, $pidName = 'pid',
        $children = 'children'
    ) {
        $trees = [];
        foreach ($list as $value) {
            if ($value[$pidName] != 0) {
                $list[$value[$pidName]][$children][] = &$list[$value['id']];
            } else {
                $trees[] = &$list[$value['id']];
            }
        }

        return $trees;
    }

    /**
     * @DOC xml 转 array
     *
     * @param $xml
     *
     * @return false|mixed
     */
    public static function xmlToArray($xml)
    {
        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $xml, true)) {
            xml_parser_free($xml_parser);

            return false;
        } else {
            $xmlstring = simplexml_load_string(
                $xml, 'SimpleXMLElement', LIBXML_NOCDATA
            );
            $xmlstring = json_decode(json_encode($xmlstring, 256), true);

            return $xmlstring;
        }
    }
}