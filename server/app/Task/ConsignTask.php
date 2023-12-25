<?php

namespace App\Task;

use App\Model\OrderModel;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;

class ConsignTask
{
    private string $createdStart;
    private string $createdEnd;

    public function execute()
    {
        $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)
            ->get('Notify', 'taskConsign');
        try {
            $date               = date('Y-m-d', strtotime('-1 day'));
            $this->createdStart = $date.' 00:00:00';
            $this->createdEnd   = $date.' 23:59:59';
            $listConfig         = Db::table('channel_consign_config')->get();
            foreach ($listConfig as $item) {
                $channelId = $item->channel_id ?? 0;
                $uids      = $item->uids ?? '';
                if ($channelId) {
                    $this->execConsign($channelId, $uids);
                }
            }
        } catch (\Throwable $throwable) {
            $logger->error(
                '报错：'.$throwable->getMessage(),
                ['listConfig' => $listConfig ?? []]
            );
        }
    }

    public function execConsign($channelId, $uids)
    {
        $where   = [];
        $where[] = ['created', '>=', strtotime($this->createdStart)];
        $where[] = ['created', '<=', strtotime($this->createdEnd)];
        $where[] = ['status', '=', 103];

        $query = OrderModel::where($where)
            ->whereRaw('!(label & (1 << 3))')
            ->where('channel_id', $channelId);
        if ($uids) {
            $query = $query->whereIn('uid', explode(',', $uids));
        }

        $tidArr = $query->pluck('tid');

        Db::transaction(function () use ($tidArr, $where, $channelId, $uids) {

            $query = OrderModel::where($where)
                ->whereRaw('!(label & (1 << 3))')
                ->where('channel_id', $channelId);
            if ($uids) {
                $query = $query->whereIn('uid', explode(',', $uids));
            }
            $res = $query->update([
                'status'       => 104,
                'consign_time' => time(),
                'label'        => Db::raw('label ^ '. 1 << 3), // 修改为已支付
            ]);

            Db::table('task_consign_log')->insert([
                'created_start' => $this->createdStart,
                'created_end'   => $this->createdEnd,
                'number'        => $res ?? 0,
                'channel_id'    => $channelId,
                'uids'          => $uids,
                'tids'          => json_encode($tidArr, 256),
                'remarks'       => '执行成功',
                'created'       => date('Y-m-d H:i:s'),
            ]);

        });
    }
}
