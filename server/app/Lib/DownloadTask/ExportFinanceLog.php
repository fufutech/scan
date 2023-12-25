<?php

namespace App\Lib\DownloadTask;

use App\Model\UserFinanceLogModel;
use Hyperf\DbConnection\Db;

class ExportFinanceLog
{
    public static function Handle($taskSynData, $page = 1): array
    {
        //修改任务为执行中
        Db::table('download_task')->where('task_id', $taskSynData['task_id'])
            ->increment('status', 1);
        //查询要导出的数据
        $data     = UserFinanceLogModel::with(['user' => function ($user) {
            $user->select('id', 'username');
        }])
            ->where($taskSynData['where'])
            ->orderBy('id', 'DESC')
            ->paginate(100, ['*'], 'page', $page);
        $list     = $data->items();
        $total    = $data->total();
        $lastPage = $data->lastPage();
        if (!$total) {
            Db::table('download_task')->where(
                'task_id', $taskSynData['task_id']
            )->update([
                'status'    => 2,
                'desc'      => '无导出数据',
                'over_time' => time(),
            ]);

            return ['status' => 201, 'message' => '无导出数据', 'data' => []];
        }
        $list = self::HandleList($list);

        return ['status'   => 200, 'message' => '处理成功', 'data' => $list,
                'lastPage' => $lastPage];
    }

    public static function HandleList($list): array
    {

        $data = [];
        foreach ($list as $key => $value) {
            switch ($value['type']) {
                default:
                case 1:
                    $type_str = '充值';
                    break;
                case 2:
                    $type_str = '订单扣费';
                    break;
                case 3:
                    $type_str = '账单调整';
                    break;

            }

            switch ($value['method']) {
                default:
                case 1:
                    $method_str = '余额';
                    break;
                case 2:
                    $method_str = '授信';
                    break;
            }
            //导出表格对应的数据
            $data[] = [
                "created"       => $value['created'],
                "type"          => $method_str,
                "context"       => $value['context'],
                "username"      => $value['user']['username'] ?? '',
                "tid"           => "\t".$value['tid'],
                "batch_no"      => "\t".$value['batch_no'],
                "amount_before" => $value['amount_before'],
                "amount"        => $value['amount'],
                "amount_after"  => $value['amount_after'],
            ];
        }

        return $data;
    }

}