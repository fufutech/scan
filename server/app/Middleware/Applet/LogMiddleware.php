<?php

namespace App\Middleware\Applet;

use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class LogMiddleware implements MiddlewareInterface
{
    protected LoggerInterface $logger;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    public function __construct(protected ContainerInterface $container,
        protected LoggerFactory $loggerFactory, RequestInterface $request
    ) {
        $this->logger = $loggerFactory->get('request', 'pdaRequest');

        $this->request = $request;
    }

    public function process(ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $startTime = microtime(true); // 请求开始时间
        try {
            $dispatched = $this->request->path();

            $requestData = $request->getParsedBody();

            $response = $handler->handle($request);

            $uid      = $this->request->UserData['id'] ?? '';
            $username = $this->request->UserData['username'] ?? '';
            $uData    = $username."($uid)";

            $data    = $response->getBody();
            $endTime = microtime(true); // 请求结束时间

            $this->logger->info(
                "TOOK:".($endTime - $startTime)." user：".$uData." PATH:["
                .$dispatched."] request：".json_encode(
                    $requestData, JSON_UNESCAPED_UNICODE
                )." response：".$data
            );

            return $response;
        } catch (\Throwable $e) {
            $endTime = microtime(true); // 请求结束时间

            $this->logger->error(
                "TOOK:".($endTime - $startTime)." user：".($uData ?? '')
                ." PATH:[".($dispatched ?? '')."] request：".json_encode(
                    ($requestData ?? []), JSON_UNESCAPED_UNICODE
                )." error：".$e->getMessage()
            );
            throw new ApiException($e->getMessage());
        }
    }

}