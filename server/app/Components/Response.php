<?php

namespace App\Components;

use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

class Response
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ResponseInterface
     */
    protected $response;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->response  = $container->get(ResponseInterface::class);
    }

    /**
     * @DOC 成功
     *
     * @param  string  $msg
     * @param  array   $data
     * @param  string  $sub_code
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function success(string $msg = '', array $data = [],
        string $sub_code = 'success'
    ): \Psr\Http\Message\ResponseInterface {
        return $this->response->json([
            'code'     => 200,
            'sub_code' => $sub_code,
            'msg'      => $msg ?: '请求成功',
            'data'     => $data,
        ]);
    }

    /**
     * @DOC 失败
     *
     * @param  string  $msg
     * @param  array   $data
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function fail(string $msg = '', array $data = []
    ): \Psr\Http\Message\ResponseInterface {
        return $this->response->json([
            'code'     => 201,
            'sub_code' => 'error',
            'msg'      => $msg ?: '请求失败',
            'data'     => $data,
        ]);
    }

    public function raw($data): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->raw($data);
    }
}