<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$type = $_GET['type'] ?? 'projects';
$isDesigner = $user['role'] === 'PROJETISTA';
$designerFilter = $isDesigner ? ' and designer_id = ' . (int) $user['id'] : '';
$designerJoinFilter = $isDesigner ? ' and s.designer_id = ' . (int) $user['id'] : '';

$columnLabels = [
    'client_name' => 'Cliente',
    'client_phone' => 'Telefone',
    'project_name' => 'Projeto',
    'current_stage' => 'Etapa atual',
    'project_status' => 'Status do projeto',
    'negotiation_status' => 'Status da negociação',
    'conference_status' => 'Status da conferência',
    'assembly_status' => 'Status da montagem',
    'assistance_status' => 'Status da assistência',
    'closed_value' => 'Valor fechado',
    'entry_date' => 'Data de entrada',
    'presentation_date' => 'Data de apresentação',
    'closing_date' => 'Data de fechamento',
    'measurement_date' => 'Data de medição',
    'sent_to_factory_date' => 'Data de envio para fábrica',
    'billing_date' => 'Data de faturamento',
    'assembly_started_date' => 'Data de início da montagem',
    'assembly_finished_date' => 'Data de finalização da montagem',
    'assistance_date' => 'Data da assistência',
    'order_date' => 'Data do pedido',
    'notes' => 'Observações',
    'name' => 'Nome',
    'phone' => 'Telefone',
    'email' => 'E-mail',
    'address' => 'Endereço',
    'interest' => 'Interesse',
    'estimated_value' => 'Valor estimado',
    'contact_date' => 'Data do contato',
    'next_contact_date' => 'Próximo contato',
    'source' => 'Origem',
    'status' => 'Status',
    'responsavel' => 'Responsável',
    'sold_value' => 'Valor vendido',
    'payment_method' => 'Forma de pagamento',
    'sale_date' => 'Data da venda',
    'payment_number' => 'Parcela',
    'amount' => 'Valor pago',
    'payment_date' => 'Data de pagamento',
    'projetista' => 'Projetista',
    'mes' => 'Mês',
    'ano' => 'Ano',
    'meta' => 'Meta',
    'nome' => 'Nome',
    'permissao' => 'Permissão',
    'ativo' => 'Ativo',
    'acao' => 'Ação',
    'etapa_origem' => 'Etapa de origem',
    'etapa_destino' => 'Etapa de destino',
    'usuario' => 'Usuário',
    'observacoes' => 'Observações',
    'criado_em' => 'Criado em',
];

$moneyColumns = ['closed_value', 'estimated_value', 'sold_value', 'amount', 'meta'];
$dateColumns = [
    'entry_date',
    'presentation_date',
    'closing_date',
    'measurement_date',
    'sent_to_factory_date',
    'billing_date',
    'assembly_started_date',
    'assembly_finished_date',
    'assistance_date',
    'order_date',
    'contact_date',
    'next_contact_date',
    'sale_date',
    'payment_date',
];
$dateTimeColumns = ['criado_em'];

$queries = [
    'projects' => [
        'sql' => 'select client_name, client_phone, project_name, current_stage, project_status, negotiation_status, conference_status, assembly_status, assistance_status, closed_value, entry_date, presentation_date, closing_date, measurement_date, sent_to_factory_date, billing_date, assembly_started_date, assembly_finished_date, assistance_date, order_date, notes from client_projects where company_id = ?' . $designerFilter . ' order by updated_at desc',
        'params' => [$companyId],
        'label' => 'Projetos',
    ],
    'future_clients' => [
        'sql' => 'select fc.name, fc.phone, fc.email, fc.address, fc.interest, fc.estimated_value, fc.contact_date, fc.next_contact_date, fc.source, fc.status, fc.notes, u.name as responsavel from future_clients fc left join users u on u.id = fc.designer_id where fc.company_id = ?' . ($isDesigner ? ' and fc.designer_id = ' . (int) $user['id'] : '') . ' order by fc.created_at desc',
        'params' => [$companyId],
        'label' => 'Clientes futuros',
    ],
    'finance' => [
        'sql' => 'select client_name, project_name, sold_value, payment_method, sale_date, notes from financial_sales where company_id = ?' . $designerFilter . ' order by sale_date desc',
        'params' => [$companyId],
        'label' => 'Vendas financeiras',
    ],
    'payments' => [
        'sql' => 'select s.client_name, s.project_name, p.payment_number, p.amount, p.payment_date, s.payment_method from financial_payments p join financial_sales s on s.id = p.financial_sale_id where p.company_id = ?' . $designerJoinFilter . ' order by p.payment_date desc',
        'params' => [$companyId],
        'label' => 'Pagamentos recebidos',
    ],
    'goals' => [
        'sql' => 'select u.name as projetista, g.month as mes, g.year as ano, g.goal_amount as meta from designer_goals g join users u on u.id = g.designer_id where g.company_id = ?' . ($isDesigner ? ' and g.designer_id = ' . (int) $user['id'] : '') . ' order by g.year desc, g.month desc',
        'params' => [$companyId],
        'label' => 'Metas',
    ],
    'employees' => [
        'sql' => 'select name as nome, email, role as permissao, active as ativo from users where company_id = ? order by name',
        'params' => [$companyId],
        'label' => 'Funcionários',
    ],
    'history' => [
        'sql' => 'select h.action as acao, h.from_stage as etapa_origem, h.to_stage as etapa_destino, u.name as usuario, h.notes as observacoes, h.created_at as criado_em from flow_history h left join users u on u.id = h.user_id where h.company_id = ? order by h.created_at desc',
        'params' => [$companyId],
        'label' => 'Histórico de movimentações',
    ],
];

function export_text($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function export_stage_label(?string $stage): string
{
    return [
        'PROJETO' => 'Projeto',
        'NEGOCIACAO' => 'Negociação',
        'CONFERENCIA' => 'Conferência',
        'MONTAGEM' => 'Montagem',
        'ASSISTENCIA' => 'Assistência',
        'FINALIZADO' => 'Finalizado',
    ][$stage ?? ''] ?? (string) $stage;
}

function export_role_label(?string $role): string
{
    return [
        'ADMIN_EMPRESA' => 'Administrador da empresa',
        'PROJETISTA' => 'Projetista',
        'CONFERENTE' => 'Conferente',
        'SUPER_ADMIN' => 'Super admin',
    ][$role ?? ''] ?? (string) $role;
}

function export_date_br(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return $date;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

function export_money_br($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function export_yes_no($value): string
{
    return ((int) $value === 1) ? 'Sim' : 'Não';
}

function export_format_value(string $key, $value, array $moneyColumns, array $dateColumns, array $dateTimeColumns): string
{
    if (in_array($key, $moneyColumns, true)) {
        return export_money_br($value);
    }
    if (in_array($key, $dateColumns, true)) {
        return export_date_br($value);
    }
    if (in_array($key, $dateTimeColumns, true)) {
        return export_date_br($value, true);
    }
    if (in_array($key, ['current_stage', 'etapa_origem', 'etapa_destino'], true)) {
        return export_stage_label($value);
    }
    if ($key === 'permissao') {
        return export_role_label($value);
    }
    if ($key === 'ativo') {
        return export_yes_no($value);
    }

    return (string) ($value ?? '');
}

function export_filename(string $type): string
{
    $names = [
        'all' => 'arquidesk-completo.xls',
        'stage' => 'arquidesk-etapa.xls',
        'projects' => 'arquidesk-projetos.xls',
        'future_clients' => 'arquidesk-clientes-futuros.xls',
        'finance' => 'arquidesk-vendas-financeiras.xls',
        'payments' => 'arquidesk-pagamentos.xls',
        'goals' => 'arquidesk-metas.xls',
        'employees' => 'arquidesk-funcionarios.xls',
        'history' => 'arquidesk-historico.xls',
    ];

    return $names[$type] ?? 'arquidesk-exportacao.xls';
}

function export_html_start(string $title): void
{
    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; color: #13231f; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .meta { color: #51677f; font-size: 12px; margin-bottom: 18px; }
        .section-title { background: #13231f; color: #fff; font-size: 15px; font-weight: 700; padding: 8px 10px; }
        .section-count { background: #f4f1ed; color: #51677f; font-size: 12px; padding: 6px 10px; }
        table { border-collapse: collapse; margin-bottom: 22px; width: 100%; }
        th { background: #e8efe9; border: 1px solid #d8ded8; color: #13231f; font-weight: 700; padding: 8px; text-align: left; white-space: nowrap; }
        td { border: 1px solid #e1e4df; padding: 7px; vertical-align: top; white-space: nowrap; }
        .empty { color: #7a8797; font-style: italic; }
    </style></head><body>';
    echo '<h1>' . export_text($title) . '</h1>';
    echo '<div class="meta">Gerado em ' . date('d/m/Y H:i') . '</div>';
}

function export_html_end(): void
{
    echo '</body></html>';
}

function export_section(string $label, array $rows, array $columnLabels, array $moneyColumns, array $dateColumns, array $dateTimeColumns): void
{
    echo '<table>';
    echo '<tr><td class="section-title" colspan="30">' . export_text($label) . '</td></tr>';
    echo '<tr><td class="section-count" colspan="30">' . count($rows) . ' registro(s)</td></tr>';

    if (!$rows) {
        echo '<tr><td class="empty">Nenhum registro encontrado</td></tr>';
        echo '</table>';
        return;
    }

    echo '<tr>';
    foreach (array_keys($rows[0]) as $key) {
        echo '<th>' . export_text($columnLabels[$key] ?? $key) . '</th>';
    }
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            echo '<td>' . export_text(export_format_value($key, $value, $moneyColumns, $dateColumns, $dateTimeColumns)) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
}

db()->prepare('insert into export_logs (company_id, type, format, filters, created_by_user_id) values (?, ?, ?, ?, ?)')->execute([$companyId, $type, 'xls', '{}', (int) $user['id']]);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . export_filename($type) . '"');

if ($type === 'all') {
    export_html_start('Relatório completo Arquidesk');

    foreach ($queries as $q) {
        $stmt = db()->prepare($q['sql']);
        $stmt->execute($q['params']);
        export_section($q['label'], $stmt->fetchAll(), $columnLabels, $moneyColumns, $dateColumns, $dateTimeColumns);
    }

    export_html_end();
    exit;
}

if ($type === 'stage') {
    $stage = $_GET['stage'] ?? 'PROJETO';
    $stmt = db()->prepare(
        'select client_name, client_phone, project_name, current_stage, project_status, negotiation_status, conference_status, assembly_status, assistance_status, closed_value, entry_date, presentation_date, closing_date, measurement_date, sent_to_factory_date, billing_date, assembly_started_date, assembly_finished_date, assistance_date, order_date, notes
         from client_projects where company_id = ? and current_stage = ?' . $designerFilter . ' order by updated_at desc'
    );
    $stmt->execute([$companyId, $stage]);

    export_html_start('Projetos - ' . export_stage_label($stage));
    export_section('Projetos - ' . export_stage_label($stage), $stmt->fetchAll(), $columnLabels, $moneyColumns, $dateColumns, $dateTimeColumns);
    export_html_end();
    exit;
}

$q = $queries[$type] ?? $queries['projects'];
$stmt = db()->prepare($q['sql']);
$stmt->execute($q['params']);

export_html_start($q['label']);
export_section($q['label'], $stmt->fetchAll(), $columnLabels, $moneyColumns, $dateColumns, $dateTimeColumns);
export_html_end();
