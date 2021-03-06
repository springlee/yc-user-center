<?php

return [
    'app_key' => env('ZLJOA_APP_KEY', ''), // oa 系统分配
    'app_secret' => env('ZLJOA_APP_SECRET', ''), // oa 系统分配
    'frontend_url' => env('ZLJOA_FRONTEND_URL',''),
    'backend_url' => env('ZLJOA_BACKEND_URL',''),
    //读取权限列表缓存的redis地址
    'redis' => [
        'host' => env('ZLJOA_REDIS_HOST', '127.0.0.1'),
        'port' => env('ZLJOA_REDIS_PORT', 6379),
        'options' => [
            'parameters' => [
                'password' => env('ZLJOA_REDIS_PASSWORD', ''),
                'database' => env('ZLJOA_REDIS_DATABASE', 0),
            ]
        ],
        'prefix'=>env('ZLJOA_REDIS_PREFIX', ''),
     ]
];
