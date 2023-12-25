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
return [
    'adminHttp'  => [
        Hyperf\Validation\Middleware\ValidationMiddleware::class,
        /** 为了验证类 */
        App\Middleware\ResponseMiddleware::class,
        App\Middleware\Admin\LogMiddleware::class,
        App\Middleware\Admin\LoginMiddleware::class,/** 登陆校验token */
    ],
    'appletHttp' => [
        Hyperf\Validation\Middleware\ValidationMiddleware::class,
        /** 为了验证类 */
        App\Middleware\ResponseMiddleware::class,
        App\Middleware\Applet\LogMiddleware::class,
        App\Middleware\Applet\LoginMiddleware::class,/** 登陆校验token */
    ],
    'apiHttp'    => [
        Hyperf\Validation\Middleware\ValidationMiddleware::class,
        /** 为了验证类 */
        App\Middleware\ResponseMiddleware::class,
        App\Middleware\Api\LogMiddleware::class,
        App\Middleware\Api\LoginMiddleware::class,/** 登陆校验token */
    ],
];
