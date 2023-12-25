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

use Hyperf\Utils\ApplicationContext;
use Hyperf\HttpServer\Router\Router;
use Hyperf\HttpServer\Request;
use App\Exception\ApiException;

Router::addRoute(['GET', 'POST', 'HEAD'], '/',
    'App\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

Router::addServer('apiHttp', function () {
    $container = ApplicationContext::getContainer();
    Router::post('/std/service', function (Request $request) use ($container) {
        try {
            // 获取当前请求
            $request = $container->get(Request::class);

            $serverCode = $request->input('method', '');
            $arr        = explode('.', $serverCode);
            if (count($arr) != 4) {
                throw new ApiException('接口错误');
            }

            $controller = $container->get(
                'App\Controller\Api\\'.ucwords($arr[0]).'Controller'
            );
            $action     = $arr[1].ucwords($arr[2]).ucwords($arr[3]);

            return $controller->$action($request, $request->input('param', []));
        } catch (Throwable $e) {
            throw new ApiException('接口请求错误');
        }
    });
});