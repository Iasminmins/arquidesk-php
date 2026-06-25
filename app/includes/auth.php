<?php

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'arquidesk-php-sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'select users.*, companies.name as company_name, companies.primary_color, companies.secondary_color
         from users
         left join companies on companies.id = users.company_id
         where users.id = ? and users.active = 1
         limit 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }

    return $user;
}

function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare('select * from users where email = ? and active = 1 limit 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}
