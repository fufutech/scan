<?php

namespace App\Services\Admin;

use App\Exception\ApiException;
use App\Lib\Data;
use App\Model\AdminModel;
use App\Model\StockCodeModel;
use App\Model\StockModel;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Arr;

class ScanService extends BaseService
{

    /**
     * @DOC 批次列表
     */
    public function batchList($param): array
    {
        $where   = [];
        $where[] = ['uid', '=', $this->UserData['uid']];
        $list    = StockModel::where($where)
                             ->with('code')
                             ->orderBy('create_time', 'Desc')
                             ->get()
                             ->toArray();
        foreach ($list as $key => $batch) {
            $numSum              = $this->calculateNumSumRecursive($batch);
            $list[$key]['count'] = $numSum;
        }

        return $list;
    }

    // 递归计算所有 num 总和的函数
    public function calculateNumSumRecursive($batch)
    {
        $numSum = 0;
        if (isset($batch['code'])) {
            foreach ($batch['code'] as $code) {
                $numSum += isset($code['num']) ? $code['num'] : 0;
            }
        }

        return $numSum;
    }

    /**
     * @DOC 批次详情
     */
    public function batchInfo($param): array
    {
        if (empty($param['stock_id'])) {
            throw new ApiException('请填写条码id');
        }
        $where   = [];
        $where[] = ['stock_id', '=', $param['stock_id']];

        return StockCodeModel::where($where)->select(
            ['id', 'code', 'num', 'create_time']
        )->get()->toArray();
    }

    /**
     * @DOC 创建批次
     */
    public function batchCreate($param): array
    {
        if (empty($param['name'])) {
            throw new ApiException('请填写批次号');
        }
        if (StockModel::where([
            'uid'  => $this->UserData['uid'],
            'name' => $param['name'],
        ])->first()) {
            throw new ApiException('该批次已存在,确认后再添加!');
        }
        $data = [
            'name'        => $param['name'],
            'uid'         => $this->UserData['uid'],
            'create_time' => time(),
        ];
        $res  = StockModel::insertGetId($data);
        if ($res) {
            return [true, '新增批次成功'];
        }

        return [false, '新增批次失败'];
    }

    /**
     * @DOC 移除条码
     */
    public function codeDelete($param): array
    {
        if (empty($param['stock_id'])) {
            throw new ApiException('请填写条码id');
        }
        $num = StockCodeModel::where([
            'stock_id' => $param['stock_id'],
            'code'     => $param['code'],
        ])->value('num');
        //如果条码数量大于1就减少1数量
        if ($num > 1) {
            $res = StockCodeModel::where([
                'stock_id' => $param['stock_id'],
                'code'     => $param['code'],
            ])->decrement('num');
        }
        //如果条码数量小于等于1就直接删除该条码
        if ($num <= 1) {
            $res = StockCodeModel::where([
                'stock_id' => $param['stock_id'],
                'code'     => $param['code'],
            ])->delete();
        }
        if ($res) {
            return [true, '移除成功'];
        }

        return [false, '移除失败'];
    }

}
