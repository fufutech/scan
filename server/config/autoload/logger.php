<?php

declare(strict_types=1);

if (!function_exists('setHandle')) {
    function setHandle(string $name, string $dir = 'center'): array
    {
        return [
            'handler'   => [
                'class'       => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    'filename' => BASE_PATH.'/runtime/logs/'.$dir.'/'.$name.'/'
                        .$name.'.log',
                    'level'    => Monolog\Logger::DEBUG,
                ],
            ],
            'formatter' => [
                'class'       => Monolog\Formatter\LineFormatter::class,
                'constructor' => [
                    'format'                => null,
                    'dateFormat'            => 'Y-m-d H:i:s',
                    'allowInlineLineBreaks' => true,
                ],
            ],
        ];
    }
}

return [
    'default' => setHandle('request'),
    'express' => setHandle('express'),

    'adminRequest'  => setHandle('request', 'admin'),
    'appletRequest' => setHandle('request', 'applet'),
    'apiRequest'    => setHandle('request', 'api'),

    'sql' => setHandle('sql', 'sql'),

    'requestGetWaybillNo'     => setHandle('requestGetWaybillNo', 'process'),
    'requestPayOrder'         => setHandle('requestPayOrder', 'process'),
    'requestPushExpressRoute' => setHandle(
        'requestPushExpressRoute', 'process'
    ),

    'taskNotify'  => setHandle('notify', 'task'),
    'taskConsign' => setHandle('consign', 'task'),
];
