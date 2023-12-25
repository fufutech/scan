<?php

namespace App\Task;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use MessageNotify\Channel\DingTalkChannel;
use MessageNotify\Notify;
use MessageNotify\Template\Markdown;

class NotifyTask
{
    public function execute()
    {
        $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)
            ->get('Notify', 'taskNotify');
        // 静态号池补充完成后推送钉钉消息
        $at    = ['18561673336', '+82-1020886125'];
        $atStr = '@'.implode('@', $at);
        $query = Notify::make()->setChannel(DingTalkChannel::class)
            ->setTemplate(Markdown::class)
            ->setTitle('落地提醒')
            ->setText(
                '落地提醒：'."\n\n".
                '系统第一版开发完成了，项目要尽快落地，要用起来并推广给到加盟商使用。'
                .$atStr
            )
            ->setAt($at)
            ->setPipeline('info');
        $res   = $query->send();
        if ($res === false) {
            $logger->warning('钉钉发送通知报错：'.$query->getErrorMessage());
        }
        $logger->info('钉钉发送成功');
    }

//    /**
//     * @Crontab(rule="*\/10 * * * * *", memo="notify")
//     */
//    public function notify()
//    {
//        echo 2222 . PHP_EOL;
//
//        var_dump('foo');
//        $logger->warning('The num of failed queue is ' . date('Y-m-d H:i:s', time()));
//    }
}
