<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

if (!wants_json()) {
    redirect('/projects.php');
}

$companyId = (int) $user['company_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'select p.*, u.name as designer_name
     from client_projects p
     left join users u on u.id = p.designer_id
     where p.id = ? and p.company_id = ?
     limit 1'
);
$stmt->execute([$id, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

if ($user['role'] === 'PROJETISTA' && (int) $project['designer_id'] !== (int) $user['id']) {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

$stage = $project['current_stage'];
$field = status_field_for_stage($stage);
$nextStage = next_stage($stage);
$canEdit = can_write_project($user, $stage);
$canMove = $user['role'] !== 'CONFERENTE'
    && ($project['negotiation_status'] ?? '') !== 'Desistida'
    && $nextStage !== null;
$moveError = $canMove ? advance_stage_validation($project) : null;

$historyStmt = db()->prepare(
    'select h.*, u.name as user_name
     from flow_history h
     left join users u on u.id = h.user_id
     where h.client_project_id = ? and h.company_id = ?
     order by h.created_at desc
     limit 8'
);
$historyStmt->execute([$id, $companyId]);
$history = [];
foreach ($historyStmt->fetchAll() as $row) {
    $history[] = [
        'action' => $row['action'],
        'created_at' => date('d/m/Y H:i', strtotime($row['created_at'])),
        'user_name' => $row['user_name'] ?: 'Sistema',
        'from_stage' => $row['from_stage'] ? stage_label($row['from_stage']) : '',
        'to_stage' => stage_label($row['to_stage']),
        'notes' => $row['notes'] ?? '',
    ];
}

$dates = [];
foreach (project_dates_for_stage($project, $stage) as [$label, $value]) {
    $dates[] = ['label' => $label, 'value' => date_br($value)];
}

json_response([
    'ok' => true,
    'project' => [
        'id' => (int) $project['id'],
        'client_name' => $project['client_name'],
        'project_name' => $project['project_name'],
        'client_phone' => $project['client_phone'] ?? '',
        'current_stage' => $stage,
        'current_stage_label' => stage_label($stage),
        'designer_name' => $project['designer_name'] ?: '-',
        'status' => project_status_for_stage($project, $stage),
        'status_field' => $field,
        'status_options' => $field ? status_options($stage) : [],
        'closed_value' => (float) ($project['closed_value'] ?? 0),
        'closed_value_label' => money_br($project['closed_value'] ?? 0),
        'days_in_stage' => project_days_in_stage($project),
        'is_stale' => project_is_stale($project),
        'stale_threshold' => project_stale_threshold($stage),
        'dates' => $dates,
        'notes' => trim((string) ($project['notes'] ?? '')),
        'whatsapp_url' => whatsapp_url($project['client_phone'] ?? ''),
        'next_stage' => $nextStage,
        'next_stage_label' => $nextStage ? stage_label($nextStage) : '',
        'can_edit' => $canEdit,
        'can_move' => $canMove && !$moveError,
        'move_error' => $moveError,
        'can_delete' => can_delete_project($user),
        'negotiation_status' => $project['negotiation_status'] ?? '',
        'is_desistida' => ($project['negotiation_status'] ?? '') === 'Desistida',
    ],
    'history' => $history,
]);
