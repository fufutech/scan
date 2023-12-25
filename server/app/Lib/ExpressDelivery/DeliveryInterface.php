<?php
//接口工厂类入库方法，该接口定义的方法继承者必须实现
namespace App\Lib\ExpressDelivery;

interface DeliveryInterface
{
    /**
     * @DOC 订单创建实现方法
     *
     * @param $param mixed 入参
     *
     * @return array
     */
    public function OrderCreate(mixed &$param): array;
}