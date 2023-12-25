<?php

declare(strict_types=1);

namespace App\Middleware\Api;

use App\Exception\ApiException;
use App\Lib\AuthToken;
use Hyperf\DbConnection\Db;
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
        $this->checkSign();

        return $handler->handle($request);
    }

    /**
     * @DOC 检查接口入参
     */
    protected function checkSign()
    {
        $params = $this->request->all();
        $param = $params['param'] ?? [];
        $sign = $params['sign'] ?? '';
        $timestamp = $params['timestamp'] ?? '';
        $partnerId = $params['partner_id'] ?? '';
        if (!$sign) {
            throw new ApiException('缺少参数：sign');
        }
        if (!$partnerId) {
            throw new ApiException('缺少参数：partner_id');
        }
        $checkWord = Db::table('api_authorize')->where('partner_id', $partnerId)
            ->value('check_word');
        if (!$checkWord) {
            throw new ApiException('非法请求，请核对鉴权入参');
        }
        $msgDigest = base64_encode(
            md5((urlencode(json_encode($param).$timestamp.$checkWord)))
        ); //通过MD5和BASE64生成数字签名

        if ($msgDigest !== $sign) {
            throw new ApiException('请求失败：Sign 错误');
        }
    }
}
