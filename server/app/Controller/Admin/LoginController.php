<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Exception\ApiException;
use App\Lib\Curl;
use App\Services\Admin\AuthService;
use App\Services\Admin\LoginService;
use Psr\Http\Message\ResponseInterface;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PatchMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\Di\Annotation\Inject;

/**
 * @Controller(server="adminHttp",prefix="login")
 */
class LoginController extends AbstractController
{
    /**
     * @Inject()
     * @var LoginService
     */
    protected LoginService $loginService;


    /**
     * @return ResponseInterface
     * @PostMapping(path="login")
     */
    public function login(): ResponseInterface
    {
        $params = $this->request->all();
        list($res, $token) = $this->loginService->login($params);
        if ($res !== true) {
            throw new ApiException($token);
        }
        return $this->response->success('登陆成功', ['adminToken' => $token]);
    }
}
