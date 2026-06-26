<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
$companyId = (int) $user['company_id'];
$id = (int) ($_POST['id'] ?? 0);
$stage = $_POST['current_stage'] ?? 'PROJETO';
$existing = null;

if ($id) {
    $existingStmt = db()->prepare('select * from client_projects where id = ? and company_id = ? limit 1');
    $existingStmt->execute([$id, $companyId]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        redirect('/projects.php');
    }
}

function posted_value(string $key, $fallback = null)
{
    return array_key_exists($key, $_POST) ? $_POST[$key] : $fallback;
}

function posted_trim(string $key, $fallback = ''): string
{
    return trim((string) posted_value($key, $fallback));
}

function posted_date(string $key, $fallback = null): ?string
{
    $value = posted_value($key, $fallback);
    return $value === '' ? null : $value;
}

function posted_decimal(string $key, $fallback = null): ?float
{
    $value = posted_value($key, $fallback);
    return $value === '' || $value === null ? null : (float) $value;
}

$data = [
    'designer_id' => posted_value('designer_id', $existing['designer_id'] ?? '') !== '' ? (int) posted_value('designer_id', $existing['designer_id'] ?? '') : null,
    'client_name' => posted_trim('client_name', $existing['client_name'] ?? ''),
    'client_address' => posted_trim('client_address', $existing['client_address'] ?? ''),
    'client_phone' => posted_trim('client_phone', $existing['client_phone'] ?? ''),
    'project_name' => posted_trim('project_name', $existing['project_name'] ?? ''),
    'current_stage' => $stage,
    'project_status' => posted_trim('project_status', $existing['project_status'] ?? ''),
    'negotiation_status' => posted_trim('negotiation_status', $existing['negotiation_status'] ?? ''),
    'new_proposal_value' => posted_decimal('new_proposal_value', $existing['new_proposal_value'] ?? null),
    'closed_value' => posted_decimal('closed_value', $existing['closed_value'] ?? null),
    'entry_date' => posted_date('entry_date', $existing['entry_date'] ?? null),
    'presentation_date' => posted_date('presentation_date', $existing['presentation_date'] ?? null),
    'closing_date' => posted_date('closing_date', $existing['closing_date'] ?? null),
    'conference_status' => posted_trim('conference_status', $existing['conference_status'] ?? ''),
    'measurement_date' => posted_date('measurement_date', $existing['measurement_date'] ?? null),
    'sent_to_factory_date' => posted_date('sent_to_factory_date', $existing['sent_to_factory_date'] ?? null),
    'billing_date' => posted_date('billing_date', $existing['billing_date'] ?? null),
    'assembly_status' => posted_trim('assembly_status', $existing['assembly_status'] ?? ''),
    'assembly_started_date' => posted_date('assembly_started_date', $existing['assembly_started_date'] ?? null),
    'assembly_finished_date' => posted_date('assembly_finished_date', $existing['assembly_finished_date'] ?? null),
    'assistance_status' => posted_trim('assistance_status', $existing['assistance_status'] ?? ''),
    'order_date' => posted_date('order_date', $existing['order_date'] ?? null),
    'assistance_date' => posted_date('assistance_date', $existing['assistance_date'] ?? null),
    'notes' => posted_trim('notes', $existing['notes'] ?? ''),
];

if ($id) {
    $stmt = db()->prepare(
        'update client_projects
         set designer_id = ?, client_name = ?, client_address = ?, client_phone = ?, project_name = ?,
             project_status = ?, negotiation_status = ?, new_proposal_value = ?, closed_value = ?, entry_date = ?, presentation_date = ?,
             closing_date = ?, conference_status = ?, measurement_date = ?, sent_to_factory_date = ?, billing_date = ?,
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
        $data['new_proposal_value'],
        $data['closed_value'],
        $data['entry_date'],
        $data['presentation_date'],
        $data['closing_date'],
        $data['conference_status'],
        $data['measurement_date'],
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
          project_status, negotiation_status, new_proposal_value, closed_value, entry_date, presentation_date, closing_date,
          conference_status, measurement_date, sent_to_factory_date, billing_date, assembly_status, assembly_started_date,
          assembly_finished_date, assistance_status, order_date, assistance_date, notes)
         values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
        $data['new_proposal_value'],
        $data['closed_value'],
        $data['entry_date'],
        $data['presentation_date'],
        $data['closing_date'],
        $data['conference_status'],
        $data['measurement_date'],
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
