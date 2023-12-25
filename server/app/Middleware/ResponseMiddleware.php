<?php

namespace App\Middleware;

use App\Exception\ApiException;
use Hyperf\Context\Context;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ResponseMiddleware implements MiddlewareInterface
{
    protected $container;
    protected $loggerFactory;
    protected $logger;

    public function __construct(ContainerInterface $container,
        LoggerFactory $loggerFactory
    ) {
        $this->container     = $container;
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
//        $requestData = $request->getParsedBody();
//        $request = $request->withParsedBody(array_merge_recursive($requestData, ['uni_name' => uniqid()]));
//        $request = $request->withQueryParams(['uni_name' => uniqid()]);
//        Context::set(ServerRequestInterface::class, $request);
        try {
            $requestData      = $request->getParsedBody();
            $tag_batch_option = $requestData['tag_batch_option'] ??
                false; // 批量操作标识

            $response = $handler->handle($request);


            if ($tag_batch_option) {
                $data = $response->getBody();
                $data = json_decode($data, true);
                $code = $data['code'] ?? '';
                if ($code == 201) {
                    $data['code']     = 200;
                    $data['sub_code'] = 202;
                }

                $response = Context::set(
                    ResponseInterface::class, $response->withBody(
                    new SwooleStream(json_encode($data, 256))
                )
                );
            }

            return $response;
        } catch (Throwable $throwable) {
            $errorCode = $throwable->getCode();
            if (isset($tag_batch_option) && $tag_batch_option && !$errorCode) {
                $errorCode = 202;
                throw new ApiException($throwable->getMessage(), $errorCode);
            }
            throw $throwable;
        }

//        $data = $response->getBody();
//
//        $data = json_decode($data, true);
//        $data['return_time'] = date('Y-m-d H:i:s');
//
//        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
//
//        $response = $response->withBody(new SwooleStream($data));
//
//        $response = Context::set(ResponseInterface::class, $response);

        return $response;
    }

}
