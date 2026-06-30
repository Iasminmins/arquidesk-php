<?php

require_once __DIR__ . '/../app/includes/auth.php';

$user = require_auth();
require_active_subscription($user);

if (!wants_json()) {
    redirect('/projects.php');
}

$companyId = (int) $user['company_id'];
$projectId = (int) ($_GET['project_id'] ?? 0);

// Verifica acesso ao projeto
$stmt = db()->prepare('select id, designer_id from client_projects where id = ? and company_id = ? limit 1');
$stmt->execute([$projectId, $companyId]);
$project = $stmt->fetch();

if (!$project) {
    json_response(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
}

if ($user['role'] === 'PROJETISTA' && (int) $project['designer_id'] !== (int) $user['id']) {
    json_response(['ok' => false, 'error' => 'Sem permissão.'], 403);
}

try {
    $files = db()->prepare(
        'select pf.*, u.name as uploaded_by_name
         from project_files pf
         left join users u on u.id = pf.uploaded_by_user_id
         where pf.client_project_id = ? and pf.company_id = ?
         order by pf.created_at desc'
    );
    $files->execute([$projectId, $companyId]);
    $rows = $files->fetchAll();
} catch (Throwable) {
    // Tabela ainda não existe
    json_response(['ok' => true, 'files' => []]);
}

$categoryLabels = [
    'GERAL'     => 'Geral',
    'PLANTA'    => 'Planta',
    'CONTRATO'  => 'Contrato',
    'MEDICAO'   => 'Medição',
    'MONTAGEM'  => 'Montagem',
    'FOTO'      => 'Foto',
    'OUTRO'     => 'Outro',
];

$result = [];
foreach ($rows as $row) {
    $isImage = str_starts_with($row['mime_type'], 'image/');
    $result[] = [
        'id'              => (int) $row['id'],
        'original_name'   => $row['original_name'],
        'url'             => '/uploads/projects/' . $companyId . '/' . $projectId . '/' . $row['stored_name'],
        'mime_type'       => $row['mime_type'],
        'is_image'        => $isImage,
        'file_size_label' => file_size_label((int) $row['file_size']),
        'category'        => $row['category'],
        'category_label'  => $categoryLabels[$row['category']] ?? $row['category'],
        'uploaded_by'     => $row['uploaded_by_name'] ?: 'Sistema',
        'created_at'      => date('d/m/Y H:i', strtotime($row['created_at'])),
    ];
}

json_response(['ok' => true, 'files' => $result]);

function file_size_label(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}
