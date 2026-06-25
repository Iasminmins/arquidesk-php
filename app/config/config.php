<?php

$config = [
    'app_name' => 'Arquidesk',
    'env' => 'local',
    'base_url' => '',
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'arquidesk',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];

$localPath = __DIR__ . '/config.local.php';
if (is_readable($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
