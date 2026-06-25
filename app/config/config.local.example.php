<?php

/**
 * Copie este arquivo para config.local.php e preencha com os dados do MySQL
 * no painel da Hostinger (Websites > Gerenciar > Bancos de dados MySQL).
 *
 * Na Hostinger o host costuma ser "localhost" (não use 127.0.0.1).
 * O nome do banco e do usuário vêm com prefixo, ex.: u123456789_arquidesk
 */
return [
    'env' => 'production',
    'base_url' => '',
    'db' => [
        'host' => 'localhost',
        'name' => 'u929376619_arquidesk',
        'user' => 'u929376619_arquidesk_user',
        'pass' => 'SUA_SENHA_AQUI',
        'charset' => 'utf8mb4',
    ],
];
