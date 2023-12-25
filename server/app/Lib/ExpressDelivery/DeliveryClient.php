<?php

namespace App\Lib\ExpressDelivery;

use App\Exception\ApiException;
use App\Lib\Factory;
use MessageNotify\Channel\DingTalkChannel;
use MessageNotify\Notify;
use MessageNotify\Template\Markdown;

class DeliveryClient
{
    private string $Carrier = ''; // 承运商，比如：YUNDA

    /**
     * @DOC 设置承运商
     *
     * @param  string  $carrier
     *
     * @return $this
     */
    public function setCarrier(string $carrier): DeliveryClient
    {
        $this->Carrier = $carrier;

        return $this;
    }

    /**
     * @DOC 推送到快递公司，下单取号
     *
     * @param $param
     *
     * @return array
     */
    public function pushDelivery(&$param): array
    {
        $class = $this->getInstance($param);
        if (is_array($class) && isset($class['code'])) {
            $result = [false, $class['message'] ?? '获取承运商Class错误'];
        } else {
            $result = $class->OrderCreate($param);
        }
        list($res, $data) = $result;
        if (!$res) {
            // 取号失败了，需要 提示到钉钉群中
            $query   = Notify::make()->setChannel(DingTalkChannel::class)
                ->setTemplate(Markdown::class)
                ->setTitle('取号失败')
                ->setText(
                    '取号失败：'."\n\n".
                    '订单：'.($param['tid'] ?? '')."\n\n".
                    '原因：<font color="red">'.(is_string($data) ? $data
                        : json_encode($data, 256)).'</font>'
                )
                ->setPipeline('error');
            $dingRes = $query->send();
            if ($dingRes === false) {
                echo '钉钉发送通知报错：'.$query->getErrorMessage().PHP_EOL;
            }
        }

        return $result;
    }

    /**
     * @DOC 获取实例
     *
     * @param $param
     *
     * @return array|mixed
     */
    private function getInstance($param): mixed
    {
        try {
            if (!$this->Carrier) {
                throw new ApiException('Carrier cannot be empty');
            }
            //检测类是否可实例化
            $ClassPath = $this->IsClassObj();
            if (!$ClassPath) {
                throw new ApiException(
                    '未找到该对应的实例化类:'.$this->Carrier
                );
            }
            # 实例化类
            $FactoryObject = Factory::getInstance();

            return $FactoryObject::Get($ClassPath, $param);
        } catch (\Throwable $e) {
            return ['code' => 500, 'message' => $e->getMessage()];
        }
    }

    /**
     * @DOC 检测实例化类
     * @return false|string
     * @throws \ReflectionException
     */
    private function IsClassObj(): bool|string
    {
        $StringMethod    = str_replace('.', '\\', $this->Carrier);
        $Method          = str_replace('.', '\\', $StringMethod);
        $ClassPath       = 'App\Lib\ExpressDelivery\Carrier\\'.$Method;
        $reflectionClass = new \ReflectionClass($ClassPath);
        if (!$reflectionClass->isInstantiable()) {
            return false;
        }

        return $ClassPath;
    }

}