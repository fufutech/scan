<?php

declare(strict_types=1);

namespace App\Controller\Applet;

use App\Services\Applet\UserService;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * Class UserController
 * @AutoController(server="pdaHttp",prefix="user")
 */
class UserController extends AbstractController
{

    /**
     * @Inject()
     * @var UserService
     */
    protected UserService $userService;

    /**
     * @DOC 获取授权的用户信息
     * @PostMapping(path="list")
     */
    public function list(): ResponseInterface
    {
        $params = $this->request->all();
        $this->userService->setUserData($this->request->UserData);
        $data = $this->userService->list($params);

        return $this->response->success('成功', $data);
    }

    /**
     * @DOC 获取授权的用户的客户信息
     * @PostMapping(path="listClient")
     */
    public function listClient(): ResponseInterface
    {
        $user_id = $this->request->input('user_id', 0);
        $this->userService->setUserData($this->request->UserData);
        $data = $this->userService->listClient($user_id);

        return $this->response->success('成功', $data);
    }
}
