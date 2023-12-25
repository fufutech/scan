<?php

namespace App\Lib\DownloadTask;

use App\Model\OrderModel;
use App\Services\Admin\OrderService;
use Hyperf\DbConnection\Db;

class CollecExport
{
    public static function Handle($taskSynData, $page = 1): array
    {
        // 账单汇总需要判断是用户汇总还是按唛头汇总
        $collectType = $taskSynData['collectType'] ?? 1;
        if ($collectType == 2) {
            $groupBy = ['trade_code', 'order.uid', 'order.goods_type_id',
                        'order.size']; // 用户汇总
        } else {
            $groupBy = ['trade_code', 'order.uid', 'order.goods_type_id',
                        'order.size', 'order_receiver.user_mark']; // 唛头汇总
        }
        //修改任务为执行中
        Db::table('download_task')->where('task_id', $taskSynData['task_id'])
            ->increment('status', 1);

        $params       = $taskSynData['params'] ?? [];
        $OrderService = make(OrderService::class);
        $query        = $OrderService->getListReportQuery($params);
        //查询要导出的数据
        $data     = $query->with([
            'user'           => function ($user) {
                $user->select('id', 'username');
            },
            'customsAffairs' => function ($customsAffairs) {
                $customsAffairs->with([
                    'flight' => function ($flight) {
                        $flight->select(
                            'id', 'flight_number', 'depart_time', 'arrive_day'
                        );
                    },
                ])->select('trade_code', 'code', 'flight_id', 'ship_start');
            },
            'receiver'       => function ($receiver) {
                $receiver->select(['tid', 'user_mark']);
            },
            'goodsType'      => function ($goodsType) {
                $goodsType->select(['id', 'name']);
            },
        ])
            ->join('order_receiver', 'order.tid', '=', 'order_receiver.tid')
            ->select(
                'trade_code', 'order.uid', 'order.tid', 'order.size',
                'order.goods_type_id',
                Db::raw('SUM(weight) as weight_sum'),
                Db::raw('SUM(pay_cost) as pay_cost_sum'),
                Db::raw('COUNT(order.id) as id_count')
            )
            ->groupBy($groupBy)
            ->paginate(100, ['*'], 'page', $page);
        $list     = $data->items();
        $total    = $data->total();
        $lastPage = $data->lastPage();
        if (!$total) {
            return ['status' => 201, 'message' => '无导出数据', 'data' => []];
        }
        $list = self::HandleList($list);

        return ['status'   => 200, 'message' => '处理成功', 'data' => $list,
                'lastPage' => $lastPage];
    }

    public static function HandleList($list): array
    {
        $data = [];
        foreach ($list as $value) {
            $flight_number    = $value['customsAffairs']['flight']['flight_number']
                ?? '';
            $ship_depart_time = $value['customsAffairs']['ship_start'] ?? '';

            //导出表格对应的数据
            $data[] = [
                "name"             => $value['user']['username'] ?? '',
                "trade_code"       => $value['customsAffairs']['code'] ?? '',
                "flight_id"        => $flight_number,
                "id_count"         => $value['id_count'],
                "weight_sum"       => number_format($value['weight_sum'], 2),
                "pay_cost_sum"     => number_format($value['pay_cost_sum'], 2),
                "user_mark"        => $value['receiver']['user_mark'] ?? '',
                "size_name"        => isset($value['size'])
                && $value['size'] == 2 ? '大件' : '小件',
                "goods_type_name"  => $value['goodsType']['name'] ?? '',
                "ship_depart_time" => $ship_depart_time ?? '',
                // 提单的计划时间 和 对应航班的计划起飞时间处理得到
            ];
        }

        return $data;
    }

}