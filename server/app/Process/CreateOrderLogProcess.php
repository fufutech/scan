<?php
declare(strict_types=1);

namespace App\Process;

use App\Model\OrderModel;
use App\Services\OrderLogService;
use Hyperf\Process\AbstractProcess;

class CreateOrderLogProcess extends AbstractProcess
{
    /**
     * 进程数量
     *
     * @var int
     */
    public $nums = 5;

    /**
     * 进程名称
     *
     * @var string
     */
    public $name = 'create-order-log';

    /**
     * 重定向自定义进程的标准输入和输出
     *
     * @var bool
     */
    public $redirectStdinStdout = false;

    /**
     * 管道类型
     *
     * @var int
     */
    public $pipeType = 2;

    /**
     * 是否启用协程
     *
     * @var void
     */
    public $enableCoroutine = true;

    public function handle(): void
    {
        //记录操作日志 [ id , id_type , uid , uid_type , info , type ]
//        $list = json_encode([
//            'id'       => $item['tid'],
//            'id_type'  => 1,
//            'uid'      => $this->UserData['id'],
//            'uid_type' => '1',
//            'info'     => '根据订单号打印小标签',
//            'type'     => '101'
//        ], 256);
//        $this->redis->lPush('queues:CREATE_ORDER_LOG', $list);
        $redis_key       = 'queues:CREATE_ORDER_LOG';
        $redis           = $this->container->get(\Redis::class);
        $OrderLogService = make(OrderLogService::class);
        while (true) {
            $list = $redis->rPop($redis_key);
            if ($list) {
                $list = json_decode($list, true);
                if (!$list) {
                    sleep(1);
                    continue;
                }
                $data = [];
                switch ($list['id_type']) {
                    default:
                    case '1';
                        $data[] = [
                            'tid'         => $list['id'],
                            'uid'         => $list['uid'],
                            'uid_type'    => $list['uid_type'],
                            'info'        => $list['info'],
                            'type'        => $list['type'],
                            'create_time' => $list['create_time'],
                        ];
                        $OrderLogService->addOrderLog($data);
                        break;
                    case '2';
                        $tidArr = OrderModel::where('batch_no', $list['id'])
                            ->select('tid')->get()->toArray();
                        if ($tidArr) {
                            foreach ($tidArr as $item) {
                                $data[] = [
                                    'tid'         => $item['tid'],
                                    'uid'         => $list['uid'],
                                    'uid_type'    => $list['uid_type'],
                                    'info'        => $list['info'],
                                    'type'        => $list['type'],
                                    'create_time' => $list['create_time'],
                                ];
                            }
                            $OrderLogService->addOrderLog($data);
                        }
                        break;
                }
            } else {
                sleep(1);
            }
        }
    }
}