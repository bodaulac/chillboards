<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'b2' => [
            'driver' => 's3',
            'key' => env('B2_APPLICATION_KEY_ID'),
            'secret' => env('B2_APPLICATION_KEY'),
            'region' => env('B2_REGION', 'us-east-005'),
            'bucket' => env('B2_BUCKET_NAME', 'HHTMedia'),
            'endpoint' => env('B2_ENDPOINT', 'https://s3.us-east-005.backblazeb2.com'),
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
