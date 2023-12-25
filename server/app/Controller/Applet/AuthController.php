<?php

declare(strict_types=1);

namespace App\Controller\Applet;

use App\Services\Applet\AuthService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;

/**
 * @Controller(server="pdaHttp")
 */
class AuthController extends AbstractController
{
    /**
     * @Inject()
     * @var AuthService
     */
    protected AuthService $AuthService;

    /**
     * @return ResponseInterface
     * @PostMapping(path="/login")
     */
    public function login(): ResponseInterface
    {
        $params = $this->request->all();
        $token  = $this->AuthService->login($params);

        return $this->response->success('success', ['gtPdaToken' => $token]);
    }

    /**
     * @return ResponseInterface
     * @PostMapping(path="/differentiate")
     */
    public function differentiate(): ResponseInterface
    {
        $status = $this->AuthService->differentiate(
            $this->request->UserData['id']
        );

        return $this->response->success('success', ['status' => $status]);
    }
}
