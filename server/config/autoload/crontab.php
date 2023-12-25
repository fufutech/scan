<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;

return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        // Callback类型定时任务（默认）
        //        (new Crontab())->setName('Notify')->setRule('0 9,20 * * * ')
        //            ->setCallback([App\Task\NotifyTask::class, 'execute'])->setMemo('这是一个消息的通知定时任务'),
        (new Crontab())->setName('Consign')->setRule('0 8 * * * ')
            ->setCallback([App\Task\ConsignTask::class, 'execute'])->setMemo(
                '这是一个批量处理发货的定时任务（私有渠道发货）'
            ),
        // Command类型定时任务
        //        (new Crontab())->setType('command')->setName('Bar')->setRule('*/5 * * * * *')->setCallback([
        //            'command'         => 'swiftmailer:spool:send',
        //            // (optional) arguments
        //            'fooArgument'     => 'barValue',
        //            // (optional) options
        //            '--message-limit' => 1,
        //        ]),
    ],
];
