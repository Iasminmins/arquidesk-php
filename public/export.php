<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$type = $_GET['type'] ?? 'projects';
$isDesigner = $user['role'] === 'PROJETISTA';
$designerFilter = $isDesigner ? ' and designer_id = ' . (int) $user['id'] : '';
$designerJoinFilter = $isDesigner ? ' and s.designer_id = ' . (int) $user['id'] : '';

$queries = [
    'projects' => ['sql' => 'select client_name, client_phone, project_name, current_stage, project_status, closed_value, entry_date, closing_date, notes from client_projects where company_id = ?' . $designerFilter . ' order by updated_at desc', 'params' => [$companyId], 'label' => 'Projetos'],
    'future_clients' => ['sql' => 'select fc.name, fc.phone, fc.email, fc.address, fc.interest, fc.estimated_value, fc.contact_date, fc.next_contact_date, fc.source, fc.status, fc.notes, u.name as responsavel from future_clients fc left join users u on u.id = fc.designer_id where fc.company_id = ?' . ($isDesigner ? ' and fc.designer_id = ' . (int) $user['id'] : '') . ' order by fc.created_at desc', 'params' => [$companyId], 'label' => 'Clientes Futuros'],
    'finance' => ['sql' => 'select client_name, project_name, sold_value, payment_method, sale_date, notes from financial_sales where company_id = ?' . $designerFilter . ' order by sale_date desc', 'params' => [$companyId], 'label' => 'Vendas'],
    'payments' => ['sql' => 'select s.client_name, s.project_name, p.payment_number, p.amount, p.payment_date, s.payment_method from financial_payments p join financial_sales s on s.id = p.financial_sale_id where p.company_id = ?' . $designerJoinFilter . ' order by p.payment_date desc', 'params' => [$companyId], 'label' => 'Pagamentos'],
    'goals' => ['sql' => 'select u.name as projetista, g.month as mes, g.year as ano, g.goal_amount as meta from designer_goals g join users u on u.id = g.designer_id where g.company_id = ?' . ($isDesigner ? ' and g.designer_id = ' . (int) $user['id'] : '') . ' order by g.year desc, g.month desc', 'params' => [$companyId], 'label' => 'Metas'],
    'employees' => ['sql' => 'select name as nome, email, role as permissao, active as ativo from users where company_id = ? order by name', 'params' => [$companyId], 'label' => 'Funcionarios'],
    'history' => ['sql' => 'select h.action as acao, h.from_stage as etapa_origem, h.to_stage as etapa_destino, u.name as usuario, h.notes as observacoes, h.created_at as criado_em from flow_history h left join users u on u.id = h.user_id where h.company_id = ? order by h.created_at desc', 'params' => [$companyId], 'label' => 'Historico'],
];

db()->prepare('insert into export_logs (company_id, type, format, filters, created_by_user_id) values (?, ?, ?, ?, ?)')->execute([$companyId, $type, 'csv', '{}', (int) $user['id']]);

if ($type === 'all') {
    // Export all tables in a single CSV with section headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="arquidesk-completo.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($queries as $key => $q) {
        $stmt = db()->prepare($q['sql']);
        $stmt->execute($q['params']);
        $rows = $stmt->fetchAll();
        fputcsv($out, ['=== ' . $q['label'] . ' (' . count($rows) . ' registros) ==='], ';');
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
        }
        fputcsv($out, [''], ';');
    }
    fclose($out);
    exit;
}

if ($type === 'stage') {
    $stage = $_GET['stage'] ?? 'PROJETO';
    $stmt = db()->prepare(
        'select client_name, client_phone, project_name, current_stage, project_status, negotiation_status, conference_status, assembly_status, assistance_status, closed_value, entry_date, presentation_date, closing_date, sent_to_factory_date, billing_date, assembly_started_date, assembly_finished_date, assistance_date, order_date, notes
         from client_projects where company_id = ? and current_stage = ?' . $designerFilter . ' order by updated_at desc'
    );
    $stmt->execute([$companyId, $stage]);
    $rows = $stmt->fetchAll();
} else {
    $q = $queries[$type] ?? $queries['projects'];
    $stmt = db()->prepare($q['sql']);
    $stmt->execute($q['params']);
    $rows = $stmt->fetchAll();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="arquidesk-' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
}
fclose($out);
