<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
if (!in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true)) {
    redirect('/');
}
$companyId = (int) $user['company_id'];
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$percent = (float) ($_POST['commission_percent'] ?? 0);
$designerId = null_if_empty($_POST['designer_id'] ?? '');

$stmt = db()->prepare('select id from financial_commission_settings where company_id = ? and month = ? and year = ? and (designer_id = ? or (designer_id is null and ? is null)) limit 1');
$stmt->execute([$companyId, $month, $year, $designerId, $designerId]);
$id = $stmt->fetchColumn();
if ($id) {
    db()->prepare('update financial_commission_settings set commission_percent = ? where id = ?')->execute([$percent, $id]);
} else {
    db()->prepare('insert into financial_commission_settings (company_id, designer_id, month, year, commission_percent) values (?, ?, ?, ?, ?)')->execute([$companyId, $designerId, $month, $year, $percent]);
}
redirect('/finance.php?month=' . $month . '&year=' . $year . '&designer_id=' . urlencode((string) ($designerId ?? '')) . '&ok=1');
