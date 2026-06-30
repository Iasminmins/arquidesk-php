<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

if (!wants_json()) {
    redirect('/projects.php');
}

$companyId = (int) $user['company_id'];
$projectId = (int) ($_POST['project_id'] ?? 0);
$category  = $_POST['category'] ?? 'GERAL';

$allowedCategories = ['GERAL', 'PLANTA', 'CONTRATO', 'MEDICAO', 'MONTAGEM', 'FOTO', 'OUTRO'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'GERAL';
}

// Verifica que o projeto pertence à empresa
$stmt = db()->prepare('select id from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$projectId, $companyId]);
if (!$stmt->fetchColumn()) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

// PROJETISTA só acessa seus próprios projetos
if ($user['role'] === 'PROJETISTA') {
    $chk = db()->prepare('select id from client_projects where id = ? and company_id = ? and designer_id = ? limit 1');
    $chk->execute([$projectId, $companyId, (int) $user['id']]);
    if (!$chk->fetchColumn()) {
        json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
    }
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite do servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite permitido.',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
        UPLOAD_ERR_CANT_WRITE => 'Erro ao salvar no servidor.',
    ];
    $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    json_response(['ok' => false, 'error' => $uploadErrors[$errCode] ?? 'Erro no upload.'], 422);
}

$maxBytes = 10 * 1024 * 1024; // 10 MB
if ($_FILES['file']['size'] > $maxBytes) {
    json_response(['ok' => false, 'error' => 'Arquivo muito grande. Máximo: 10 MB.'], 422);
}

// Validação de MIME real (não só extensão)
$allowedMimes = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
];

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['file']['tmp_name']);

if (!in_array($mimeType, $allowedMimes, true)) {
    json_response(['ok' => false, 'error' => 'Tipo de arquivo não permitido. Envie imagens, PDFs ou documentos Office.'], 422);
}

$originalName = basename($_FILES['file']['name']);
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$storedName   = sprintf('%d_%d_%s_%s.%s', $companyId, $projectId, time(), bin2hex(random_bytes(4)), $ext);

$uploadDir = __DIR__ . '/../uploads/projects/' . $companyId . '/' . $projectId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $storedName)) {
    json_response(['ok' => false, 'error' => 'Não foi possível salvar o arquivo.'], 500);
}

// Cria tabela se ainda não existe (bootstrap automático)
try {
    db()->exec("create table if not exists project_files (
      id int unsigned auto_increment primary key,
      company_id int unsigned not null,
      client_project_id int unsigned not null,
      uploaded_by_user_id int unsigned null,
      original_name varchar(255) not null,
      stored_name varchar(255) not null,
      file_size int unsigned not null default 0,
      mime_type varchar(100) not null default '',
      category varchar(40) not null default 'GERAL',
      created_at timestamp not null default current_timestamp,
      index pf_project_idx (client_project_id, company_id)
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
} catch (Throwable) {}

$insert = db()->prepare(
    'insert into project_files (company_id, client_project_id, uploaded_by_user_id, original_name, stored_name, file_size, mime_type, category)
     values (?, ?, ?, ?, ?, ?, ?, ?)'
);
$insert->execute([$companyId, $projectId, (int) $user['id'], $originalName, $storedName, (int) $_FILES['file']['size'], $mimeType, $category]);

$fileId = (int) db()->lastInsertId();

json_response([
    'ok'   => true,
    'file' => [
        'id'            => $fileId,
        'original_name' => $originalName,
        'stored_name'   => $storedName,
        'file_size'     => (int) $_FILES['file']['size'],
        'mime_type'     => $mimeType,
        'category'      => $category,
        'url'           => '/uploads/projects/' . $companyId . '/' . $projectId . '/' . $storedName,
        'uploaded_by'   => $user['name'],
        'created_at'    => date('d/m/Y H:i'),
    ],
]);
