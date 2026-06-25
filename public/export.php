<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$type = $_GET['type'] ?? 'projects';

$queries = [
    'projects' => "select client_name, client_phone, project_name, current_stage, closed_value, entry_date, closing_date from client_projects where company_id = {$companyId}",
    'finance' => "select client_name, project_name, sold_value, payment_method, sale_date from financial_sales where company_id = {$companyId}",
    'payments' => "select s.client_name, s.project_name, p.payment_number, p.amount, p.payment_date from financial_payments p join financial_sales s on s.id = p.financial_sale_id where p.company_id = {$companyId}",
    'goals' => "select u.name as designer, g.month, g.year, g.goal_amount from designer_goals g join users u on u.id = g.designer_id where g.company_id = {$companyId}",
    'employees' => "select name, email, role, active from users where company_id = {$companyId}",
    'history' => "select action, from_stage, to_stage, notes, created_at from flow_history where company_id = {$companyId}",
];

if ($type === 'stage') {
    $stage = $_GET['stage'] ?? 'PROJETO';
    $stmt = db()->prepare(
        'select client_name, client_phone, project_name, current_stage, project_status, negotiation_status, conference_status, assembly_status, assistance_status, closed_value, entry_date, presentation_date, closing_date, sent_to_factory_date, billing_date, assembly_started_date, assembly_finished_date, assistance_date, order_date
         from client_projects
         where company_id = ? and current_stage = ?
         order by updated_at desc'
    );
    $stmt->execute([$companyId, $stage]);
    $rows = $stmt->fetchAll();
} else {
    $sql = $queries[$type] ?? $queries['projects'];
    $rows = db()->query($sql)->fetchAll();
}

db()->prepare('insert into export_logs (company_id, type, format, filters, created_by_user_id) values (?, ?, ?, ?, ?)')->execute([$companyId, $type, 'csv', '{}', (int) $user['id']]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="arquidesk-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '.csv"');
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
}
fclose($out);
