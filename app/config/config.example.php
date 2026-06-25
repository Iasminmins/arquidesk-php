<?php

/**
 * Valores padrao para desenvolvimento local (Laragon).
 * Em producao, copie config.local.example.php para config.local.php
 * e preencha com os dados do MySQL da Hostinger.
 */
return [
    'app_name' => 'Arquidesk',
    'base_url' => '',
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'arquidesk',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
