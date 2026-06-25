<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
if ($user['role'] !== 'ADMIN_EMPRESA') redirect('/');
$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('update users set active = if(active = 1, 0, 1) where id = ? and company_id = ?');
$stmt->execute([$id, (int) $user['company_id']]);
redirect('/employees.php?ok=1');
