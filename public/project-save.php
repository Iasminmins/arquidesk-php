<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$stage = $_POST['current_stage'] ?? 'PROJETO';

$data = [
    'designer_id' => $_POST['designer_id'] !== '' ? (int) $_POST['designer_id'] : null,
    'client_name' => trim($_POST['client_name'] ?? ''),
    'client_address' => trim($_POST['client_address'] ?? ''),
    'client_phone' => trim($_POST['client_phone'] ?? ''),
    'project_name' => trim($_POST['project_name'] ?? ''),
    'current_stage' => $stage,
    'project_status' => trim($_POST['project_status'] ?? ''),
    'negotiation_status' => trim($_POST['negotiation_status'] ?? ''),
    'closed_value' => $_POST['closed_value'] !== '' ? (float) $_POST['closed_value'] : null,
    'entry_date' => $_POST['entry_date'] ?: null,
    'presentation_date' => $_POST['presentation_date'] ?: null,
    'closing_date' => $_POST['closing_date'] ?: null,
    'conference_status' => trim($_POST['conference_status'] ?? ''),
    'sent_to_factory_date' => $_POST['sent_to_factory_date'] ?: null,
    'billing_date' => $_POST['billing_date'] ?: null,
    'assembly_status' => trim($_POST['assembly_status'] ?? ''),
    'assembly_started_date' => $_POST['assembly_started_date'] ?: null,
    'assembly_finished_date' => $_POST['assembly_finished_date'] ?: null,
    'assistance_status' => trim($_POST['assistance_status'] ?? ''),
    'order_date' => $_POST['order_date'] ?: null,
    'assistance_date' => $_POST['assistance_date'] ?: null,
    'notes' => trim($_POST['notes'] ?? ''),
];

if ($id) {
    $stmt = db()->prepare(
        'update client_projects
         set designer_id = ?, client_name = ?, client_address = ?, client_phone = ?, project_name = ?,
             project_status = ?, negotiation_status = ?, closed_value = ?, entry_date = ?, presentation_date = ?,
             closing_date = ?, conference_status = ?, sent_to_factory_date = ?, billing_date = ?,
             assembly_status = ?, assembly_started_date = ?, assembly_finished_date = ?,
             assistance_status = ?, order_date = ?, assistance_date = ?, notes = ?
         where id = ? and company_id = ?'
    );
    $stmt->execute([
        $data['designer_id'],
        $data['client_name'],
        $data['client_address'],
        $data['client_phone'],
        $data['project_name'],
        $data['project_status'],
        $data['negotiation_status'],
        $data['closed_value'],
        $data['entry_date'],
        $data['presentation_date'],
        $data['closing_date'],
        $data['conference_status'],
        $data['sent_to_factory_date'],
        $data['billing_date'],
        $data['assembly_status'],
        $data['assembly_started_date'],
        $data['assembly_finished_date'],
        $data['assistance_status'],
        $data['order_date'],
        $data['assistance_date'],
        $data['notes'],
        $id,
        $companyId,
    ]);
} else {
    $stmt = db()->prepare(
        'insert into client_projects
         (company_id, designer_id, client_name, client_address, client_phone, project_name, current_stage,
          project_status, negotiation_status, closed_value, entry_date, presentation_date, closing_date,
          conference_status, sent_to_factory_date, billing_date, assembly_status, assembly_started_date,
          assembly_finished_date, assistance_status, order_date, assistance_date, notes)
         values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $companyId,
        $data['designer_id'],
        $data['client_name'],
        $data['client_address'],
        $data['client_phone'],
        $data['project_name'],
        $data['current_stage'],
        $data['project_status'],
        $data['negotiation_status'],
        $data['closed_value'],
        $data['entry_date'],
        $data['presentation_date'],
        $data['closing_date'],
        $data['conference_status'],
        $data['sent_to_factory_date'],
        $data['billing_date'],
        $data['assembly_status'],
        $data['assembly_started_date'],
        $data['assembly_finished_date'],
        $data['assistance_status'],
        $data['order_date'],
        $data['assistance_date'],
        $data['notes'],
    ]);
    $id = (int) db()->lastInsertId();

    $history = db()->prepare('insert into flow_history (company_id, client_project_id, to_stage, action, user_id) values (?, ?, ?, ?, ?)');
    $history->execute([$companyId, $id, $stage, 'Projeto criado', (int) $user['id']]);
}

redirect('/projects.php?stage=' . urlencode($stage) . '&ok=1');
