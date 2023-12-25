<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@Hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception\Handler;

use App\Exception\ApiException;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ApiExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // 判断被捕获到的异常是希望被捕获的异常
        if ($throwable instanceof ApiException) {
            // 格式化输出
            $data = json_encode([
                'code'     => $throwable->getCode() > 0 ? 200 : 201,
                'sub_code' => $throwable->getCode() > 0 ? $throwable->getCode()
                    : 'error',
                'msg'      => $throwable->getMessage(),
                'data'     => [],
            ], JSON_UNESCAPED_UNICODE);

            // 阻止异常冒泡
            $this->stopPropagation();
            $this->stopPropagation(); // 这个异常处理过，就不需要其他 handle 处理

            return $response->withBody(new SwooleStream($data));
        }

        return $response;

    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
