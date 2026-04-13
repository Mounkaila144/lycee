<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app'), 'throw' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false],
        'tenant' => [
            'driver' => 'local',
            'root' => storage_path('app/tenants'),
            'url' => env('APP_URL').'/storage/tenants',
            'visibility' => 'private',
            'throw' => false,
        ],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
