<?php
require_once __DIR__ . '/../app/includes/auth.php';
$user = require_auth();
$companyId = (int) $user['company_id'];
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$percent = (float) ($_POST['commission_percent'] ?? 0);
$stmt = db()->prepare('select id from financial_commission_settings where company_id = ? and designer_id is null and month = ? and year = ? limit 1');
$stmt->execute([$companyId, $month, $year]);
$id = $stmt->fetchColumn();
if ($id) {
    db()->prepare('update financial_commission_settings set commission_percent = ? where id = ?')->execute([$percent, $id]);
} else {
    db()->prepare('insert into financial_commission_settings (company_id, designer_id, month, year, commission_percent) values (?, null, ?, ?, ?)')->execute([$companyId, $month, $year, $percent]);
}
redirect('/finance.php?month=' . $month . '&year=' . $year . '&ok=1');
