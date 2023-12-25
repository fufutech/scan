<?php
declare(strict_types=1);

namespace App\Process;

use App\Exception\ApiException;
use App\Model\ChannelEmpowerModel;
use App\Model\FreightDetailModel;
use App\Model\FreightModel;
use App\Model\GoodsTypeModel;
use App\Model\OrderModel;
use App\Model\OrderMotherInfoModel;
use App\Services\OrderService;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;

class PayOrderProcess extends AbstractProcess
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
    public $name = 'process-pay-order';

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
        $redis_key = 'queues:PayOrder';

        $redis        = $this->container->get(\Redis::class);
        $Logger       = $this->container->get(LoggerFactory::class)->get(
            'PayOrderProcess', 'requestPayOrder'
        );
        $OrderService = make(OrderService::class);

        while (true) {
            $tid = $redis->rPop($redis_key);
            if ($tid) {
                try {
                    $error     = ''; // 初始化
                    $temp      = $tempDetail = []; // 初始化
                    $orderData = $OrderService->findModel(['tid' => $tid], [
                        'receiver' => function ($receiver) {
                            $receiver->select(['tid', 'province']);
                        },
                        'mother'   => function ($mother) {
                            $mother->select(['tid', 'total_weight']);
                        },
                    ])->getOrderModel();

                    if ($orderData['label'] & (1 << 3)) {
                        throw new ApiException('订单重复支付');
                    }
                    if ($orderData['label'] & (1 << 4)) {
                        // 该订单属于子母件
                        $orderData['weight']
                            = $orderData['mother']['total_weight'] ?? 0;
                        // 获取子订单号集合
                        $sonTidArr = OrderMotherInfoModel::where(
                            'mother_tid', $tid
                        )
                            ->where('is_mother', 0)->pluck('tid')->toArray();
                        if (!$sonTidArr) {
                            throw new ApiException(
                                '子订单不存在，子母件存在异常'
                            );
                        }

                        // 获取整个子母件订单的基础数据
                        $orderTidArr   = $sonTidArr;
                        $orderTidArr[] = $tid; // 加入主订单的 tid
                        $ForData       = OrderModel::whereIn(
                            'tid', $orderTidArr
                        )
                            ->selectRaw(
                                'group_concat(tid) as group_tid,goods_type_id,SUM(GREATEST(`weight_pickup`, `weight_send`)) as total_weight'
                            )
                            ->groupBy(['goods_type_id'])
                            ->get()->toArray();
                    } else {
                        // 单个订单根据揽收重量、发运重量、体积重量取最大值作为实际重量
                        $orderData['weight'] = max(
                            $orderData['weight_pickup'],
                            $orderData['weight_send']
                        ); // 目前无体积重量
                        $ForData             = [['group_tid'     => $tid,
                                                 'goods_type_id' => $orderData['goods_type_id'],
                                                 'total_weight'  => $orderData['weight']]];
                    }
                    // 获取子母件或订单中的货物类型的数据集
                    $goodsTypeIds  = array_column($ForData, 'goods_type_id');
                    $goodsTypeData = GoodsTypeModel::whereIn(
                        'id', $goodsTypeIds
                    )->pluck('name', 'id');

                    $Logger->info('记录订单不同货物类型数据', [
                        'tid' => $tid, 'ForData' => $ForData,
                    ]);

                    $financeLog = [];
                    foreach ($ForData as $item) {
                        // 为了处理子母件中不同货物类型的价格计算问题
                        $orderData['goods_type_id'] = $item['goods_type_id'] ??
                            0;
                        $orderData['weight']        = round(
                            $item['total_weight'] ?? 0, 2
                        );
                        $orderData['goods_type_name']
                                                    = $goodsTypeData[$item['goods_type_id']
                            ?? 0] ?? ''; // 货物类型名称
                        $group_tid                  = explode(
                            ',', $item['group_tid'] ?? ''
                        );
                        if (!$group_tid) {
                            throw new ApiException(
                                '支付失败：必要数据 group_tid 错误'
                            );
                        }

                        $this->checkOrderData($orderData);

                        $channel = $OrderService->checkParamsChannel(
                            $orderData['line_id'], $orderData['channel_id']
                        );

                        $temp       = $this->getTempData(
                            $channel, $orderData
                        ); // 获取价格模板数据
                        $tempDetail = $this->getTempDetailData(
                            $temp['id'], $orderData
                        ); // 根据模板的 id 查找重量计费模板

                        $weightCost = $this->getWeightCost(
                            $tempDetail, $orderData
                        ); // 重量称重费用

                        // 保存数据
                        $uid = $orderData['uid'];

                        // 判断是否为主订单，价格模板的基础运费记入主订单中
                        if (in_array($tid, $group_tid)) {
                            $tempBaseCost           = round(
                                $temp['home_freight'] + $temp['sheet_freight']
                                + $temp['delivery_freight'], 2
                            );
                            $cost                   = $tempBaseCost
                                + $weightCost;
                            $orderData['is_master'] = true; // 主订单
                            unset(
                                $group_tid[array_search(
                                    $tid, $group_tid
                                )]
                            ); // 若是主订单所在的组集合中，先删除主订单 tid,再加到数组最后，后面 array_pop 逻辑会获取到
                            $group_tid[] = $tid;
                        } else {
                            $cost                   = $weightCost;
                            $orderData['is_master'] = false; // 子订单
                        }

                        $financeLog = $this->getFinanceLog(
                            $uid, round($cost, 2), $orderData
                        );

                        Db::transaction(
                            function () use (
                                $uid, $group_tid, $cost, &$financeLog, &
                                $orderData, &$temp
                            ) {
                                $tid = array_pop($group_tid);
                                $res = Db::table('order')
                                    ->whereRaw(
                                        'tid = ? AND !(label & (1 << 3))',
                                        [$tid]
                                    )
                                    ->update([
                                        'pay_cost'        => $cost, // 结算金额
                                        'weight'          => $orderData['weight'],
                                        'label'           => Db::raw(
                                            'label ^ '. 1 << 3
                                        ),
                                        'freight_temp_id' => $temp['id'],
                                        'error'           => '',
                                    ]); // 修改支付状态的标签
                                // 若是子母件，需要修改子订单的支付状态数据
                                if ($orderData['label'] & (1 << 4)
                                    && $group_tid
                                ) {
                                    Db::table('order')
                                        ->whereIn('tid', $group_tid)
                                        ->whereRaw('!(label & (1 << 3))')
                                        ->update([
                                            'pay_cost'        => 0, // 结算金额
                                            'weight'          => 0,
                                            'label'           => Db::raw(
                                                'label ^ '. 1 << 3
                                            ),
                                            'freight_temp_id' => $temp['id'],
                                            'error'           => '',
                                        ]); // 修改支付状态的标签
                                }
                                if ($res) {
                                    if ($financeLog['method'] == 1) {
                                        Db::table('user')->where('id', $uid)
                                            ->decrement('money', $cost); // 余额扣费
                                    } elseif ($financeLog['method'] == 2) {
                                        Db::table('user')->where('id', $uid)
                                            ->increment(
                                                'credit_use', $cost
                                            ); // 授信扣费
                                    }
                                    // 写入流水
                                    Db::table('user_finance_log')->insert(
                                        $financeLog
                                    );
                                }
                            }
                        );
                    }

                    // 记录日志
                    $redis->lPush(
                        'queues:CREATE_ORDER_LOG', json_encode([
                        'id'          => $tid,
                        'id_type'     => 1,
                        'uid'         => 0,
                        'uid_type'    => 0, // 用户类型: 0-系统 1-用户端 2-管理端 3-pda端
                        'info'        => '订单支付成功',
                        'type'        => 100, // 日志类型:100系统日志 101操作日志
                        'create_time' => time(),
                    ], 256)
                    );
                } catch (\Throwable $throwable) {
                    // 支付失败
                    $error = '支付失败：'.$throwable->getMessage();
                    Db::table('order')
                        ->whereRaw('tid = ? AND !(label & (1 << 3))', [$tid])
                        ->update(['error' => $error]); // 写入失败原因

                    // 记录日志
                    $redis->lPush(
                        'queues:CREATE_ORDER_LOG', json_encode([
                        'id'          => $tid,
                        'id_type'     => 1,
                        'uid'         => 0,
                        'uid_type'    => 0, // 用户类型: 0-系统 1-用户端 2-管理端 3-pda端
                        'info'        => $error,
                        'type'        => 100, // 日志类型:100系统日志 101操作日志
                        'create_time' => time(),
                    ], 256)
                    );
                }
                $Logger->info('支付失败', [
                    'tid'        => $tid, 'error' => $error,
                    'temp'       => $temp ?? [],
                    'tempDetail' => $tempDetail ?? [],
                ]);
            } else {
                sleep(1);
            }
        }
    }

    protected function getTempData(array &$channel, array &$orderData): array
    {
        // 判断渠道是否有给该用户授权，若没有授权，则按照渠道配置的默认价格模板
        if (ChannelEmpowerModel::where('uid', $orderData['uid'])->exists()) {
            // 有授权
            $ids = ChannelEmpowerModel::where('uid', $orderData['uid'])
                ->where('channel_id', $channel['id'])->pluck('freight_tem_id')
                ->toArray();
            if (!$ids) {
                throw new ApiException('未授权模板');
            }
            $temp = FreightModel::whereIn('id', $ids)
                ->where('status', 1)
                ->whereRaw(
                    "(storage = 0 OR storage = ?)", [$orderData['order_type']]
                ) // 仓储类型：1仓储，2非仓储
                ->whereRaw(
                    "(brand = 0 OR brand = ?)", [$orderData['goods_type_id']]
                ) //  货物类型
                ->whereRaw(
                    "(courier_id = 0 OR courier_id = ?)",
                    [$channel['carrier_id']]
                ) //  快递公司
                ->whereRaw(
                    "(transport = 0 OR transport = ?)",
                    [$orderData['delivery_method']]
                ) //  走件方式
                ->whereRaw("(port = '' OR port = ?)", [$orderData['port']]
                ) //  口岸 CODE
                ->whereRaw("(cargoes = 0 OR cargoes = ?)", [$orderData['size']]
                ) //  大小件 （1-小件 2-大件）
                ->orderBy('id', 'DESC')
                ->first();
            if (!$temp) {
                throw new ApiException('未匹配到模板');
            }
        } else {
            // 没有授权
            $price_template_id = $channel['price_template_id'] ?? 0; // 价格模板的 id
            if (!$price_template_id) {
                throw new ApiException('渠道未配置模板');
            }
            $temp = FreightModel::where('id', $price_template_id)
                ->where('status', 1)
                ->first();
            if (!$temp) {
                throw new ApiException('未匹配到模板');
            }
        }

        return $temp->toArray();
    }

    protected function getTempDetailData(int $tem_id, array &$orderData): array
    {
        // 根据模板的 id 查找重量计费模板
        $tempDetail = FreightDetailModel::where('tem_id', $tem_id)
            ->whereRaw(
                "(weight_min <= ? AND weight_max >= ?)",
                [$orderData['weight'], $orderData['weight']]
            )
            ->whereRaw("(type = 0 OR type = ?)", [$orderData['express_type']]
            ) // 快递产品类型（1-标快 2-特快）
            ->whereRaw(
                "(area = '' OR FIND_IN_SET(?, area))",
                [$orderData['receiver']['province']]
            ) //  收件地址的省份
            ->orderBy('id', 'DESC')
            ->first();
        if (!$tempDetail) {
            throw new ApiException('未匹配到重量计费模板');
        }

        return $tempDetail->toArray();
    }

    protected function getWeightCost(array &$tempDetail, array &$orderData
    ): float {
        $secondWeight = $orderData['weight']
            - $tempDetail['first_weight']; // 续重
        $secondWeight = round($secondWeight, 2);

        $firstCost = $tempDetail['first_value'];
        if ($secondWeight > 0 && $tempDetail['next_weight'] > 0) {
            // 说明重量超过首重
            $num        = ceil(
                round($secondWeight / $tempDetail['next_weight'], 2)
            ); // 几倍的续重
            $secondCost = round($num * $tempDetail['next_value'], 2);
        }

        return $firstCost + ($secondCost ?? 0); // 重量称重费用
    }

    protected function getFinanceLog(int $uid, float $cost, array &$orderData
    ): array {
        $uData = Db::table('user')->where('id', $uid)->first();
        if (!$uData) {
            throw new ApiException('用户数据错误');
        }
        // 余额不够扣款是，使用授信，若授信也不扣，需要报错，支付失败
        if ($uData->money - $cost >= 0) {
            // 余额支付
            $payMethod     = 1; //余额
            $amount_before = $uData->money;
            $amount_after  = round($uData->money - $cost, 2);
        } else {
            $remainCredit  = round(
                $uData->credit_limit - $uData->credit_use, 2
            ); // 剩余额度
            $payMethod     = 2; // 授信
            $amount_before = $remainCredit;
            $amount_after  = round($remainCredit - $cost, 2);
            if ($remainCredit - $cost < 0) {
                // 扣款逻辑：优先扣余额，余额不够扣授信，授信不够，已生成的订单，直接吧扣余额，扣成负数
                $payMethod     = 1; //余额
                $amount_before = $uData->money;
                $amount_after  = round($uData->money - $cost, 2);
            }
        }
        $context = '订单发运扣费，'.($orderData['goods_type_name'] ?? '').' 重量：'
            .($orderData['weight'] ?? '');
        if (isset($orderData['is_master']) && $orderData['is_master']) {
            $context .= ' 包含模板基础运费';
        }

        return [
            'uid'           => $uid,
            'tid'           => $orderData['tid'],
            'batch_no'      => $orderData['batch_no'],
            'amount_before' => $amount_before,
            'amount'        => -$cost,
            'amount_after'  => $amount_after,
            'type'          => 2, // 交易类型 （1-充值 2-订单扣费）
            'method'        => $payMethod, // 1-余额  2-授信
            'context'       => $context,
            'created'       => time(),
        ];
    }

    protected function checkOrderData(array &$orderData)
    {
        // 获取渠道的时候需要考虑一下预约取号的，订单数据中没有线路ID和渠道ID
        $line_id    = $orderData['line_id'] ?? 0;
        $channel_id = $orderData['channel_id'] ?? 0;
        $weight     = $orderData['weight'] ?? 0;
        if (!$line_id || !$channel_id) {
            throw new ApiException('订单渠道信息错误');
        }
        if ((float)$weight <= 0) {
            throw new ApiException('订单实际重量错误');
        }
    }
}