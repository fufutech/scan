<?php

declare(strict_types=1);

namespace App\Middleware\Admin;

use App\Exception\ApiException;
use App\Lib\AuthToken;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoginMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var HttpResponse
     */
    protected HttpResponse $response;

    protected array $url
        = [
            'login/login',
        ];

    protected int $time = 86400;

    public function __construct(ContainerInterface $container,
        HttpResponse $response, RequestInterface $request
    ) {
        $this->container = $container;
        $this->response  = $response;
        $this->request   = $request;
    }

    public function process(ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $dispatched = $this->request->path();

        if (in_array($dispatched, $this->url)) {
            return $handler->handle($request);
        }
        $this->request->UserData = $this->checkLogin();

        return $handler->handle($request);
    }

    /**
     * @DOC 检查登录
     * @return mixed
     */
    protected function checkLogin(): mixed
    {
        $token = $this->request->header('adminToken');
        if (empty($token)) {
            throw new ApiException('请先登录');
        }
        $deToken = AuthToken::deToken($token);
        if (isset($deToken['data']) && $deToken['data']) {
            $user = json_decode($deToken['data'], true);
        }
        if (!isset($user) || !$user) {
            throw new ApiException('登陆过期');
        }
        if ($user['update_time'] + $this->time < time()) {
            throw new ApiException('登陆过期', 401);
        }

        return $user;
    }
}
