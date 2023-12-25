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

use App\Components\Response;
use App\Constants\ErrorCode;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * 自定义验证器异常处理
 * Class ValidationExceptionHandler
 *
 * @package App\Exception\Handler
 */
class ValidationExceptionHandler extends ExceptionHandler
{
    public function __construct(protected Response $response)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $body = $throwable->validator->errors()->first();

        $this->stopPropagation(); // 阻止冒泡

        return $this->response->fail($body);
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}
