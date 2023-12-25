<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Request\Admin\ActivityRequest;
use App\Services\Admin\ScanService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;

/**
 * @Controller(server="adminHttp",prefix="scan")
 */
class ScanController extends AbstractController
{

    /**
     * @Inject()
     * @var ScanService
     */
    protected ScanService $scanService;

    /**
     * @DOC 批次列表
     * @PostMapping(path="batchList")
     */
    public function batchList(): ResponseInterface
    {
        $param = $this->request->all();
        $this->scanService->setUserData($this->request->UserData);
        $list = $this->scanService->batchList($param);

        return $this->response->success('成功', $list);
    }

    /**
     * @DOC 批次详情
     * @PostMapping(path="batchInfo")
     */
    public function batchInfo(): ResponseInterface
    {
        $param = $this->request->all();
        $this->scanService->setUserData($this->request->UserData);
        $list = $this->scanService->batchInfo($param);

        return $this->response->success('成功', $list);
    }

    /**
     * @DOC 创建批次
     * @PostMapping(path="batchCreate")
     */
    public function batchCreate(): ResponseInterface
    {
        $param = $this->request->all();
        $this->scanService->setUserData($this->request->UserData);
        [$res, $msg] = $this->scanService->batchCreate($param);
        if ($res) {
            return $this->response->success($msg);
        }

        return $this->response->fail('创建批次失败');
    }

}
