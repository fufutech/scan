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
$handlers = [
//    Hyperf\Validation\ValidationExceptionHandler::class,// 官方验证器异常处理
App\Exception\Handler\ValidationExceptionHandler::class, // 自定义验证器异常处理
App\Exception\Handler\ApiExceptionHandler::class,
Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler::class,
App\Exception\Handler\AppExceptionHandler::class,
];

return [
    'handler' => [
        'http'       => $handlers,
        'adminHttp'  => $handlers,
        'appletHttp' => $handlers,
        'apiHttp'    => $handlers,
    ],
];
