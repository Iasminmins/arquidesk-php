<?php

// Proteção contra força bruta no login
// Máximo de tentativas por IP antes do bloqueio temporário

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_SECONDS', 900); // 15 minutos

function login_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0],
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $raw) {
        $ip = trim($raw);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function login_ensure_attempts_schema(): void
{
    try {
        db()->exec("
            create table if not exists login_attempts (
              id int unsigned auto_increment primary key,
              ip varchar(45) not null,
              email varchar(160) not null default '',
              attempted_at timestamp not null default current_timestamp,
              index la_ip_time_idx (ip, attempted_at)
            ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci
        ");
    } catch (Throwable) {}
}

function login_attempt_count(string $ip): int
{
    try {
        $cutoff = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
        $stmt = db()->prepare('select count(*) from login_attempts where ip = ? and attempted_at > ?');
        $stmt->execute([$ip, $cutoff]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function login_is_rate_limited(string $ip): bool
{
    return login_attempt_count($ip) >= LOGIN_MAX_ATTEMPTS;
}

function login_seconds_until_unlock(string $ip): int
{
    try {
        $cutoff = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
        $stmt = db()->prepare(
            'select attempted_at from login_attempts
             where ip = ? and attempted_at > ?
             order by attempted_at asc limit 1'
        );
        $stmt->execute([$ip, $cutoff]);
        $oldest = $stmt->fetchColumn();
        if (!$oldest) {
            return LOGIN_WINDOW_SECONDS;
        }
        return max(0, strtotime($oldest) + LOGIN_WINDOW_SECONDS - time());
    } catch (Throwable) {
        return LOGIN_WINDOW_SECONDS;
    }
}

function login_record_attempt(string $ip, string $email): void
{
    try {
        db()->prepare('insert into login_attempts (ip, email) values (?, ?)')->execute([$ip, mb_strtolower(trim($email))]);
        // Limpeza probabilística ~10% das tentativas — remove registros antigos
        if (random_int(1, 10) === 1) {
            $cutoff = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS * 2);
            db()->prepare('delete from login_attempts where attempted_at < ?')->execute([$cutoff]);
        }
    } catch (Throwable) {}
}

function login_clear_attempts(string $ip): void
{
    try {
        db()->prepare('delete from login_attempts where ip = ?')->execute([$ip]);
    } catch (Throwable) {}
}
