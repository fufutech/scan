<?php

namespace App\Lib\ExpressDelivery\Carrier;

use App\Exception\ApiException;
use App\Lib\Curl;
use App\Lib\ExpressDelivery\DeliveryInterface;
use App\Services\WaybillService;

class SYD implements DeliveryInterface
{
    private array $apiConfig;

    private string $tid;

    private string $type;

    public function __construct()
    {
        // 获取配置
        $this->apiConfig = [
            'url'    => 'http://api.suyoda.cn/syd/num',
            'appKey' => '200064',
            'secret' => 'ac25170401ffc729f67cb5998250f6c99e0',
        ];
    }

    protected function setType(string $type)
    {
        if (!in_array($type, ['SYD-S', 'SYD-Y', 'SYD-D'])) {
            throw new ApiException('请填写正确的取号类型');
        }
        $this->type = $type;
    }

    public function init(&$param)
    {
        $this->tid = $param['tid'] ?? '';

        $this->setType($param['type'] ?? ''); // 取号类型
    }

    public function OrderCreate(mixed &$param): array
    {
        try {
            $this->init($param);
            if ($this->type == 'SYD-D') {
                // 从系统号池中取号
                $WaybillService = make(WaybillService::class);
                $waybill_no     = $WaybillService->getWaybillNo();
                $code           = 1000;
            } else {
                // 从速邮达取号
                $body = json_encode(
                    ['order_no' => $this->tid, 'type' => $this->type]
                );
                $sign = $this->Sign($body);

                $url = $this->apiConfig['url'].'?appkey='
                    .$this->apiConfig['appKey'].'&sign='.$sign;

                $WaybillData = Curl::setType('json')->setMethod()->setHeader(
                    ['content-type: application/json']
                )->send($url, $body);
//                echo date('Y-m-d H:i:s') . ' SYD ' . json_encode(['body' => $body, 'WaybillData' => $WaybillData], 256) . PHP_EOL;

                $code       = $WaybillData['code'] ?? '';
                $waybill_no = $WaybillData['data']['tp_waybill_no'] ?? '';
            }
            if ($code == 1000 && $waybill_no) {
                $result['waybill_no'] = $waybill_no;

                return [true, $result];
            } else {
                $message = $WaybillData['message'] ?? ('取号失败：'.json_encode(
                        $WaybillData ?? '', JSON_UNESCAPED_UNICODE
                    ));

                return [false, $message];
            }
        } catch (\Exception $e) {
            return [false, '取号报错：'.$e->getMessage()];
        }
    }

    /**
     * @DOC 加签
     * @return string
     */
    protected function Sign($body)
    {
        $bodyBase64 = base64_encode($body);
        $signStr    = $this->apiConfig['appKey'].$bodyBase64
            .$this->apiConfig['secret'];

        return strtoupper(md5($signStr));
    }
}
