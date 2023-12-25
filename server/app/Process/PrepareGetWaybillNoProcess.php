<?php
declare(strict_types=1);

namespace App\Process;

use App\Model\ChannelModel;
use App\Model\OrderPrepareInfoModel;
use App\Model\OrderPrepareModel;
use App\Services\OrderService;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;

class PrepareGetWaybillNoProcess extends AbstractProcess
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
    public $name = 'process-get-waybill-no-prepare';

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
     * @var bool
     */
    public $enableCoroutine = true;

    protected Redis $redis;

    protected string $tagBatchKey;

    public function handle(): void
    {
        $redis_key = 'queues:PrepareGetWaybillNo';

        $this->redis  = $this->container->get(\Redis::class);
        $Logger       = $this->container->get(LoggerFactory::class)->get(
            'PrepareGetWaybillNoProcess', 'requestGetWaybillNo'
        );
        $OrderService = make(OrderService::class);

        while (true) {
            $batch_no = $this->redis->rPop($redis_key);
            if ($batch_no) {
                try {
                    // 设置 redis 标记数据
                    $this->tagBatchKey = 'PrepareOrderGetWaybillCount_'
                        .$batch_no;

                    $this->redis->hSet(
                        $this->tagBatchKey, 'status', '0'
                    );// 状态开始
                    $this->redis->expire($this->tagBatchKey, 600); // 状态结束

                    $orderPrepareData = OrderPrepareModel::where(
                        'batch_no', $batch_no
                    )->with('item')->first();
                    if (!$orderPrepareData) {
                        continue; // 不存在这个数据或者该数据已完成
                    }
                    $prepareInfo = $orderPrepareData['item'] ?? [];
                    if (!$prepareInfo) {
                        continue;
                    }
                    // 开始循环组装数据
                    // 设置 UserData 信息
                    $OrderService->setUserData(
                        ['id' => $orderPrepareData['uid']]
                    );
                    // 获取预约取号的默认渠道
                    $channel = ChannelModel::where('prepare_default', 1)->first(
                    );
                    // 获取批次号
                    foreach ($prepareInfo as $item) {
                        if (isset($item['num']) && isset($item['complete_num'])
                            && $item['num'] > $item['complete_num']
                        ) {
                            $num          = $item['num']
                                - $item['complete_num'];
                            $params       = [
                                'batch_no'  => $item['batch_no'],
                                'client_id' => $item['client_id'] ?? 0,
                                'user_mark' => $item['user_mark'] ?? '',
                                'remarks'   => $item['remarks'] ?? '',
                                'detail'    => [
                                    ['amount' => 0, 'num' => 0],
                                ],
                                'line_id'   => $channel['line_id'] ?? 0,
                                'channel'   => [
                                    'id'   => $channel['id'] ?? 0,
                                    'size' => 2,
                                ],
                            ];
                            $complete_num = 0;

                            for ($i = 1; $i <= $num; $i++) {
                                try {
                                    $res = $OrderService->createPrepareOrder(
                                        $params
                                    );
                                    if (isset($res['tid']) && $res['tid']) {
                                        $complete_num++; // 已完成数量增加
                                        // 创建成功给 redis 制作一个标记，主要用来在 PDA 端判断制单结束。与之对应的还有个取号结束标记
                                        $this->redis->hIncrBy(
                                            $this->tagBatchKey, 'total', 1
                                        );
                                    }
                                } catch (\Throwable $e) {
                                    $error = $e->getMessage();
                                    $Logger->info(
                                        "预约创建订单新增数据失败，批次：{$batch_no} 原因："
                                        .$error
                                    );
                                }
                            }
                            if ($complete_num) {
                                OrderPrepareInfoModel::where('id', $item['id'])
                                    ->increment('complete_num', $complete_num);
                            }
                            if ($num == $complete_num) {
                                // 全部成功
                                $this->redis->hSet(
                                    $this->tagBatchKey, 'status', '1'
                                ); // 状态结束
                            } else {
                                $this->setRedisError($error ?? '取号异常');
                            }
                        }
                    }
                } catch (\Throwable $throwable) {
                    // 取号报错
                    $thr = $throwable->getMessage();
                    $Logger->info('预约创建订单报错：'.$thr);
                    $this->setRedisError($thr);
                }
            } else {
                sleep(1);
            }
        }
    }

    /**
     * @DOC 设置 redis 的错误标志
     *
     * @param  string  $error
     *
     * @throws \RedisException
     */
    protected function setRedisError(string $error)
    {
        $this->redis->hSet($this->tagBatchKey, 'status', '2'); // 状态有异常
        $this->redis->hSet($this->tagBatchKey, 'errorMsg', $error);
    }
}