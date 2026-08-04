<?php

function designer_nav_groups(): array
{
    return [
        'flow' => [
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
        ],
        'independent' => [
            '/' => 'Dashboard',
            '/my-day.php' => 'Meu Dia',
            '/schedule.php' => 'Agendamentos',
            '/future-clients.php' => 'Clientes Futuros',
            '/finance.php' => 'Financeiro',
            '/contracts.php' => 'Contratos',
            '/project-files.php' => 'Arquivos de Projetos',
            '/goals.php?mode=my-goal' => 'Minha Meta',
            '/import-export.php' => 'Minhas Exportações',
        ],
    ];
}

function designer_configurable_nav(): array
{
    $groups = designer_nav_groups();
    return $groups['flow'] + $groups['independent'];
}

function filter_nav_by_preferences(array $nav, array $hiddenKeys): array
{
    $hidden = array_fill_keys($hiddenKeys, true);
    return array_filter($nav, static fn(string $label, string $href): bool => $href === '/menu-settings.php' || !isset($hidden[$href]), ARRAY_FILTER_USE_BOTH);
}

function ensure_nav_preferences_schema(): void
{
    static $ensured = false;
    if ($ensured) return;
    db()->exec("create table if not exists user_nav_preferences (
        id int unsigned auto_increment primary key,
        company_id int unsigned not null,
        user_id int unsigned not null,
        nav_key varchar(190) not null,
        visible tinyint(1) not null default 1,
        created_at timestamp not null default current_timestamp,
        updated_at timestamp null default null on update current_timestamp,
        unique key user_nav_preference_unique (user_id, nav_key),
        index user_nav_preference_company_idx (company_id),
        constraint user_nav_preference_company_fk foreign key (company_id) references companies(id) on delete cascade,
        constraint user_nav_preference_user_fk foreign key (user_id) references users(id) on delete cascade
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");
    $ensured = true;
}

function hidden_nav_keys_for_user(int $userId, int $companyId): array
{
    ensure_nav_preferences_schema();
    $stmt = db()->prepare('select nav_key from user_nav_preferences where user_id = ? and company_id = ? and visible = 0');
    $stmt->execute([$userId, $companyId]);
    return array_values(array_intersect($stmt->fetchAll(PDO::FETCH_COLUMN), array_keys(designer_configurable_nav())));
}
