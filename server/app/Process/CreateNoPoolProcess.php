<?php
declare(strict_types=1);

namespace App\Process;

use App\Model\PoolWaybillModel;
use App\Services\WaybillService;
use Hyperf\Process\AbstractProcess;

class CreateNoPoolProcess extends AbstractProcess
{
    /**
     * 进程数量
     *
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称
     *
     * @var string
     */
    public $name = 'process-create-no-pool';

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

    public function handle(): void
    {
        $redis_key = 'POOL_FIRST_WAYBILL_NO';
        $limit     = 200; // 动态号池中的最大数量
        $length    = 10000; // 静态号池一次补充的数量

        $redis = $this->container->get(\Redis::class);
        $WaybillService = make(WaybillService::class);

        while (true) {
            $poolLen = $redis->lLen($redis_key);
            $sign    = $redis->get($redis_key.'_SIGN');
            // 当动态号池（Redis）中的剩余条数不足 20 时，需要从静态号池（MySQL）中补充进来
            if ($poolLen < 80 || $sign) {
                // 删除标记
                $limit = $sign ?: $limit; // 这是为了可以方便控制增加动态号池的数量
                $redis->del($redis_key.'_SIGN');
                // 查找静态号池（MySQL）中未使用的前 100 条数据补充到动态号池（Redis）中
                $column = PoolWaybillModel::where('status', 0)->limit($limit)
                    ->pluck('waybill_no', 'id')->toArray();
                $ids    = array_keys($column);
                $newNos = array_values($column);
                if (count($newNos) < $limit) {
                    // 说明静态号池中数据不充裕，需要补充静态号池

                    $WaybillService->setWaybillNo($length); // 补充号池
                } else {
                    // 说明静态号池中数据暂充裕，直接讲取到的数据补充到动态号池中即可。
                    $redis->lPush($redis_key, ...$newNos);

                    PoolWaybillModel::whereIn('id', $ids)->update(
                        ['status' => 2]
                    ); // 将数据改为待使用状态
                }
                unset($column, $ids, $newNos);
            } else {
                sleep(1); // 不需要频繁查看动态号池（Redis）数量
            }
        }
    }
}