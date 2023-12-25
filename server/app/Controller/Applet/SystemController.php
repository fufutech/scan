<?php

declare(strict_types=1);

namespace App\Controller\Applet;


use App\Services\SystemService;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;
use Hyperf\Di\Annotation\Inject;


/**
 * @AutoController(server="pdaHttp",prefix="system")
 */
class SystemController extends AbstractController
{
    /**
     * @Inject()
     * @var SystemService
     */
    protected SystemService $systemService;

    /**
     * @DOC 获取系统配置数据
     * @PostMapping(path="config")
     */
    public function config(): ResponseInterface
    {
        $code = $this->request->input('code');
        $data = $this->systemService->config($code);

        return $this->response->success('成功', $data);
    }

}
