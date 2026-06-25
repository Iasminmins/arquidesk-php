<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
if ($user['role'] !== 'ADMIN_EMPRESA') redirect('/');
$id = (int) ($_POST['id'] ?? 0);
if ($id !== (int) $user['id']) {
    $stmt = db()->prepare('delete from users where id = ? and company_id = ?');
    $stmt->execute([$id, (int) $user['company_id']]);
}
redirect('/employees.php?ok=1');
