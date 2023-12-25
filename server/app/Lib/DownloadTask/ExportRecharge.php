<?php

namespace App\Lib\DownloadTask;

use App\Model\UserRechargeModel;
use Hyperf\DbConnection\Db;

class ExportRecharge
{
    public static function Handle($taskSynData, $page = 1): array
    {
        //修改任务为执行中
        Db::table('download_task')->where('task_id', $taskSynData['task_id'])
            ->increment('status', 1);
        //查询要导出的数据
        $data     = UserRechargeModel::with(['user' => function ($user) {
            $user->select('id', 'username');
        }])
            ->where($taskSynData['where'])
            ->orderBy('created', 'DESC')
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
        foreach ($list as $key => $value) {
            switch ($value['type']) {
                case 1:
                    $type_str = '线下充值';
                    break;
                case 2:
                    $type_str = '余额充值';
                    break;
                default:
                    $type_str = '其他方式充值';
            }

            switch ($value['status']) {
                default:
                case 0:
                    $status_str = '待审核';
                    break;
                case 1:
                    $status_str = '审核通过';
                    break;
                case 2:
                    $status_str = '审核未通过';
                    break;
            }
            switch ($value['account_type']) {
                default:
                case 1:
                    $account_type_str = '余额';
                    break;
                case 2:
                    $account_type_str = '授信';
                    break;
            }
            switch ($value['pay_type']) {
                case 1:
                    $pay_type_str = '转账';
                    break;
                case 2:
                    $pay_type_str = '现金';
                    break;
                default:
                    $pay_type_str = '';
            }
            //导出表格对应的数据
            $data[] = [
                "name"         => $value['user']['username'] ?? '',
                "created"      => $value['created'],
                "amount"       => $value['amount'],
                "account_type" => $account_type_str,
                "type"         => $type_str,
                "pay_type"     => $pay_type_str,
                "trans_no"     => "\t".$value['trans_no'],
                "pay_name"     => $value['pay_name'],
                "bank_account" => $value['bank_account'],
                "status"       => $status_str,
                "remark"       => $value['remark'],
                "pay_img"      => $value['pay_img'],
            ];
        }

        return $data;
    }

}