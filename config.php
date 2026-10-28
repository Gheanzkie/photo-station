<?php


$config = [
    
    'pin' => '1234',

    
    'base_dir' => 'Pictures',

    
    'role' => 'students',

    
    'folders' => [
        'students' => 'students',
        'parents'  => 'parents',
    ],

    
    'labels' => [
        'students' => 'Students',
        'parents'  => 'Parents / Fetchers',
    ],

    
    'size'         => 512,
    'jpeg_quality' => 90,

    
    'recent_limit' => 12,

    
    'max_bytes' => 4 * 1024 * 1024,
];


if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
