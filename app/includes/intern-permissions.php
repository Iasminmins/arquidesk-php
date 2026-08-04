<?php

function intern_permission_tabs(): array
{
    return [
        'dashboard' => 'Dashboard',
        'my_day' => 'Meu Dia',
        'schedule' => 'Agendamentos',
        'future_clients' => 'Clientes Futuros',
        'projects' => 'Projetos',
        'finance' => 'Financeiro',
        'contracts' => 'Contratos',
        'project_files' => 'Arquivos de Projetos',
        'goals' => 'Metas',
        'imports' => 'Importar / Exportar',
    ];
}

function intern_permission_levels(): array
{
    return [
        'NONE' => 'Sem acesso',
        'VIEW' => 'Somente visualizar',
        'EDIT' => 'Criar e editar',
        'DELETE' => 'Criar, editar e excluir',
    ];
}

function intern_level_allows(string $granted, string $required): bool
{
    $weights = ['NONE' => 0, 'VIEW' => 1, 'EDIT' => 2, 'DELETE' => 3];
    return ($weights[$granted] ?? 0) >= ($weights[$required] ?? PHP_INT_MAX);
}

function intern_has_permission(array $permissions, string $tab, string $required = 'VIEW'): bool
{
    return intern_level_allows((string) ($permissions[$tab] ?? 'NONE'), $required);
}

function intern_tab_for_href(string $href): ?string
{
    $path = (string) parse_url($href, PHP_URL_PATH);
    return match ($path) {
        '/', '/index.php' => 'dashboard',
        '/my-day.php' => 'my_day',
        '/schedule.php' => 'schedule',
        '/future-clients.php' => 'future_clients',
        '/projects.php', '/project-form.php', '/project-detail.php' => 'projects',
        '/finance.php' => 'finance',
        '/contracts.php' => 'contracts',
        '/project-files.php' => 'project_files',
        '/goals.php' => 'goals',
        '/import-export.php' => 'imports',
        default => null,
    };
}

function intern_filter_nav(array $nav, array $permissions): array
{
    return array_filter(
        $nav,
        static function (string $label, string $href) use ($permissions): bool {
            $tab = intern_tab_for_href($href);
            return $tab !== null && intern_has_permission($permissions, $tab, 'VIEW');
        },
        ARRAY_FILTER_USE_BOTH
    );
}

function intern_can_access_project(array $user, array $project): bool
{
    return ($user['role'] ?? '') !== 'ESTAGIARIO' || (int) ($project['intern_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function intern_allowed_project_stages(array $user): array
{
    return ($user['role'] ?? '') === 'ESTAGIARIO'
        ? ['PROJETO', 'NEGOCIACAO']
        : ['PROJETO', 'NEGOCIACAO', 'CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA', 'FINALIZADO'];
}

function intern_can_move_project(array $user, string $fromStage, string $toStage): bool
{
    if (($user['role'] ?? '') !== 'ESTAGIARIO') {
        return true;
    }
    return $fromStage === 'PROJETO' && $toStage === 'NEGOCIACAO';
}

function ensure_intern_permissions_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = db();
    $dbName = (string) $pdo->query('select database()')->fetchColumn();
    $columnStmt = $pdo->prepare('select count(*) from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?');

    $columnStmt->execute([$dbName, 'users', 'supervisor_user_id']);
    if (!(int) $columnStmt->fetchColumn()) {
        $pdo->exec("alter table users modify role enum('SUPER_ADMIN','ADMIN_EMPRESA','PROJETISTA','CONFERENTE','ESTAGIARIO') not null default 'ADMIN_EMPRESA'");
        $pdo->exec("alter table users add supervisor_user_id int unsigned null after role, add intern_data_scope enum('SUPERVISOR','COMPANY') not null default 'SUPERVISOR' after supervisor_user_id, add index users_supervisor_idx (supervisor_user_id), add constraint users_supervisor_fk foreign key (supervisor_user_id) references users(id) on delete set null");
    }

    $pdo->exec("create table if not exists intern_permissions (
        id int unsigned auto_increment primary key,
        company_id int unsigned not null,
        intern_user_id int unsigned not null,
        tab_key varchar(50) not null,
        access_level enum('VIEW','EDIT','DELETE') not null,
        created_at timestamp not null default current_timestamp,
        updated_at timestamp null default null on update current_timestamp,
        unique key intern_permission_unique (intern_user_id, tab_key),
        index intern_permission_company_idx (company_id),
        constraint intern_permission_company_fk foreign key (company_id) references companies(id) on delete cascade,
        constraint intern_permission_user_fk foreign key (intern_user_id) references users(id) on delete cascade
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");

    $columnStmt->execute([$dbName, 'client_projects', 'intern_user_id']);
    if (!(int) $columnStmt->fetchColumn()) {
        $pdo->exec('alter table client_projects add intern_user_id int unsigned null after designer_id, add index projects_intern_idx (intern_user_id), add constraint projects_intern_fk foreign key (intern_user_id) references users(id) on delete set null');
    }

    $ensured = true;
}

function intern_permissions_for_user(int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $stmt = db()->prepare('select tab_key, access_level from intern_permissions where intern_user_id = ?');
    $stmt->execute([$userId]);
    $cache[$userId] = [];
    foreach ($stmt->fetchAll() as $row) {
        $cache[$userId][$row['tab_key']] = $row['access_level'];
    }
    return $cache[$userId];
}

function intern_tab_for_request(string $path, array $request): ?string
{
    if (in_array($path, ['/project-save.php', '/project-delete.php', '/project-move.php', '/project-move-undo.php', '/project-negotiation-undo.php', '/project-desistir.php', '/project-reativar.php', '/project-status.php', '/project-to-future.php', '/project-to-future-undo.php', '/project-detail.php', '/project-history.php', '/pipeline-analysis.php'], true)) {
        return 'projects';
    }
    if (in_array($path, ['/project-file-list.php', '/project-file-upload.php', '/project-file-delete.php'], true)) {
        return 'project_files';
    }
    if (in_array($path, ['/contract-file.php', '/contract-print.php', '/contract-proof.php', '/contract-template-file.php'], true)) {
        return 'contracts';
    }
    if ($path === '/commission-save.php') {
        return 'finance';
    }
    if ($path === '/export.php') {
        return 'imports';
    }
    return intern_tab_for_href($path);
}

function intern_required_level_for_request(string $path, string $method, array $request): string
{
    $action = (string) ($request['action'] ?? '');
    if (in_array($path, ['/project-delete.php', '/project-file-delete.php'], true) || str_contains($action, 'delete') || !empty($request['delete']) || !empty($request['delete_sale'])) {
        return 'DELETE';
    }
    if (($path === '/project-form.php') || ($path === '/future-clients.php' && isset($request['edit'])) || ($path === '/finance.php' && (isset($request['edit']) || isset($request['new'])))) {
        return 'EDIT';
    }
    return $method === 'POST' ? 'EDIT' : 'VIEW';
}

function authorize_intern_request(array $user): void
{
    if (($user['role'] ?? '') !== 'ESTAGIARIO') {
        return;
    }
    ensure_intern_permissions_schema();
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/logout.php') {
        return;
    }
    $tab = intern_tab_for_request($path, $_REQUEST);
    $required = intern_required_level_for_request($path, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_REQUEST);
    $permissions = ['my_day' => 'EDIT', 'projects' => 'EDIT'];
    if ($path === '/' || $path === '/index.php') {
        redirect('/my-day.php');
    }
    if ($tab === null || !intern_has_permission($permissions, $tab, $required)) {
        http_response_code(403);
        exit('Sem permissão para acessar esta área.');
    }

    if (($path === '/project-form.php' || $path === '/project-save.php') && (int) ($_REQUEST['id'] ?? 0) <= 0) {
        http_response_code(403);
        exit('Estagiários não podem criar projetos.');
    }
    $blockedProjectActions = ['/project-delete.php', '/project-desistir.php', '/project-reativar.php', '/project-to-future.php', '/project-to-future-undo.php', '/project-move-undo.php', '/project-negotiation-undo.php'];
    if (in_array($path, $blockedProjectActions, true)) {
        http_response_code(403);
        exit('Ação não permitida para estagiários.');
    }

    $projectId = (int) ($request['client_project_id'] ?? $request['project_id'] ?? (($tab === 'projects' || $tab === 'project_files') ? ($request['id'] ?? 0) : 0));
    if ($projectId > 0) {
        $stmt = db()->prepare('select count(*) from client_projects where id = ? and company_id = ? and intern_user_id = ?');
        $stmt->execute([$projectId, (int) $user['company_id'], (int) $user['id']]);
        if (!(int) $stmt->fetchColumn()) {
            http_response_code(403);
            exit('Sem permissão para acessar este projeto.');
        }
    }
}

function intern_supervisor_filter_id(array $user): ?int
{
    if (($user['role'] ?? '') !== 'ESTAGIARIO' || ($user['intern_data_scope'] ?? 'SUPERVISOR') === 'COMPANY') {
        return null;
    }
    return (int) ($user['supervisor_user_id'] ?? 0) ?: -1;
}
