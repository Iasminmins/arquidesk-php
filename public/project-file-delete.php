<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);
require_csrf();

$companyId = (int) $user['company_id'];
$fileId    = (int) ($_POST['file_id'] ?? 0);

// Apenas ADMIN e PROJETISTA podem excluir
if ($user['role'] === 'CONFERENTE') {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

$stmt = db()->prepare(
    'select pf.*, p.designer_id
     from project_files pf
     join client_projects p on p.id = pf.client_project_id and p.company_id = pf.company_id
     where pf.id = ? and pf.company_id = ?
     limit 1'
);
$stmt->execute([$fileId, $companyId]);
$file = $stmt->fetch();

if (!$file) {
    json_response(['ok' => false, 'error' => 'Arquivo não encontrado.'], 404);
}

// PROJETISTA só exclui arquivos dos próprios projetos
if ($user['role'] === 'PROJETISTA' && (int) $file['designer_id'] !== (int) $user['id']) {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

// Remove do disco
$filePath = __DIR__ . '/../uploads/projects/' . $companyId . '/' . $file['client_project_id'] . '/' . $file['stored_name'];
if (file_exists($filePath)) {
    @unlink($filePath);
}

db()->prepare('delete from project_files where id = ? and company_id = ?')->execute([$fileId, $companyId]);

json_response(['ok' => true, 'file_id' => $fileId]);
