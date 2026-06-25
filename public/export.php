<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$type = $_GET['type'] ?? 'projects';

$queries = [
    'projects' => ['sql' => 'select client_name, client_phone, project_name, current_stage, project_status, closed_value, entry_date, closing_date, notes from client_projects where company_id = ? order by updated_at desc', 'params' => [$companyId]],
    'finance' => ['sql' => 'select client_name, project_name, sold_value, payment_method, sale_date, notes from financial_sales where company_id = ? order by sale_date desc', 'params' => [$companyId]],
    'payments' => ['sql' => 'select s.client_name, s.project_name, p.payment_number, p.amount, p.payment_date, s.payment_method from financial_payments p join financial_sales s on s.id = p.financial_sale_id where p.company_id = ? order by p.payment_date desc', 'params' => [$companyId]],
    'goals' => ['sql' => 'select u.name as projetista, g.month as mes, g.year as ano, g.goal_amount as meta from designer_goals g join users u on u.id = g.designer_id where g.company_id = ? order by g.year desc, g.month desc', 'params' => [$companyId]],
    'employees' => ['sql' => 'select name as nome, email, role as permissao, active as ativo from users where company_id = ? order by name', 'params' => [$companyId]],
    'history' => ['sql' => 'select h.action as acao, h.from_stage as etapa_origem, h.to_stage as etapa_destino, u.name as usuario, h.notes as observacoes, h.created_at as criado_em from flow_history h left join users u on u.id = h.user_id where h.company_id = ? order by h.created_at desc', 'params' => [$companyId]],
];

if ($type === 'stage') {
    $stage = $_GET['stage'] ?? 'PROJETO';
    $stmt = db()->prepare(
        'select client_name, client_phone, project_name, current_stage, project_status, negotiation_status, conference_status, assembly_status, assistance_status, closed_value, entry_date, presentation_date, closing_date, sent_to_factory_date, billing_date, assembly_started_date, assembly_finished_date, assistance_date, order_date, notes
         from client_projects
         where company_id = ? and current_stage = ?
         order by updated_at desc'
    );
    $stmt->execute([$companyId, $stage]);
    $rows = $stmt->fetchAll();
} else {
    $q = $queries[$type] ?? $queries['projects'];
    $stmt = db()->prepare($q['sql']);
    $stmt->execute($q['params']);
    $rows = $stmt->fetchAll();
}

db()->prepare('insert into export_logs (company_id, type, format, filters, created_by_user_id) values (?, ?, ?, ?, ?)')->execute([$companyId, $type, 'csv', '{}', (int) $user['id']]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="arquidesk-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
}
fclose($out);
