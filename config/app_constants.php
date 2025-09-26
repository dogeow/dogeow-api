<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 分页配置
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
        'chat_default_per_page' => 50,
        'chat_max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | 聊天配置
    |--------------------------------------------------------------------------
    */
    'chat' => [
        'message' => [
            'max_length' => 2000,
            'min_length' => 1,
        ],
        'room' => [
            'name_max_length' => 100,
            'name_min_length' => 3,
            'description_max_length' => 500,
        ],
        'rate_limit' => [
            'messages_per_minute' => 10,
            'window_seconds' => 60,
        ],
        'presence' => [
            'timeout_minutes' => 5,
            'heartbeat_interval_seconds' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 文件上传配置
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'default_extension' => 'jpg',
    ],

    /*
    |--------------------------------------------------------------------------
    | 图片处理配置
    |--------------------------------------------------------------------------
    */
    'image' => [
        'thumbnail_size' => 200,
        'compressed_max_size' => 800,
        'quality' => [
            'compressed' => 85,
            'thumbnail' => 80,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'default_ttl' => 3600, // 1 hour
        'success_ttl' => 86400, // 24 hours
        'error_ttl' => 1800, // 30 minutes
        'prefixes' => [
            'title_favicon' => 'title_favicon',
            'chat_rooms' => 'chat_rooms',
            'online_users' => 'online_users',
            'user_activity' => 'user_activity',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 验证配置
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'user' => [
            'name_max_length' => 255,
            'password_min_length' => 8,
        ],
        'note' => [
            'title_max_length' => 255,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API响应配置
    |--------------------------------------------------------------------------
    */
    'api' => [
        'default_success_message' => 'Success',
        'default_error_message' => 'An error occurred',
        'include_debug_info' => env('APP_DEBUG', false),
    ],
];