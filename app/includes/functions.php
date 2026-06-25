<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header("Location: {$path}");
    exit;
}

function money_br($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function date_br(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('d/m/Y', strtotime($date));
}

function stage_label(string $stage): string
{
    $labels = [
        'PROJETO' => 'Projeto',
        'NEGOCIACAO' => 'Negociação',
        'CONFERENCIA' => 'Conferência',
        'MONTAGEM' => 'Montagem',
        'ASSISTENCIA' => 'Assistência',
        'FINALIZADO' => 'Finalizados',
    ];

    return $labels[$stage] ?? $stage;
}

function role_label(string $role): string
{
    return [
        'ADMIN_EMPRESA' => 'ADMIN EMPRESA',
        'PROJETISTA' => 'PROJETISTA',
        'CONFERENTE' => 'CONFERENTE',
        'SUPER_ADMIN' => 'SUPER ADMIN',
    ][$role] ?? $role;
}

function role_nav(string $role): array
{
    return match ($role) {
        'PROJETISTA' => [
            '/' => 'Dashboard',
            '/schedule.php' => 'Agendamentos',
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/goals.php?mode=my-goal' => 'Minha Meta',
            '/import-export.php' => 'Minhas Exportações',
        ],
        'CONFERENTE' => [
            '/' => 'Dashboard',
            '/schedule.php' => 'Agendamentos',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/goals.php?mode=team-goals' => 'Metas da Equipe',
            '/import-export.php' => 'Exportações Operacionais',
        ],
        'SUPER_ADMIN' => [
            '/super-admin.php' => 'Dashboard SaaS',
            '/super-admin.php?view=companies' => 'Empresas',
            '/super-admin.php?view=plans' => 'Planos',
            '/super-admin.php?view=subscriptions' => 'Assinaturas',
            '/super-admin.php?view=users' => 'Usuários Globais',
            '/super-admin.php?view=settings' => 'Configurações SaaS',
        ],
        default => [
            '/' => 'Dashboard',
            '/schedule.php' => 'Agendamentos',
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/finance.php' => 'Financeiro',
            '/goals.php' => 'Metas dos Projetistas',
            '/import-export.php' => 'Importar / Exportar',
            '/employees.php' => 'Funcionários',
            '/company-settings.php' => 'Configurações da Empresa',
            '/subscription.php' => 'Assinatura / Plano',
        ],
    };
}

function can_write_project(array $user, string $stage): bool
{
    if ($user['role'] === 'CONFERENTE') {
        return in_array($stage, ['CONFERENCIA', 'MONTAGEM', 'ASSISTENCIA'], true);
    }

    return $user['role'] !== 'SUPER_ADMIN';
}

function can_create_project(array $user, string $stage): bool
{
    return $user['role'] !== 'CONFERENTE' && in_array($stage, ['PROJETO', 'ASSISTENCIA'], true);
}

function can_delete_project(array $user): bool
{
    return in_array($user['role'], ['ADMIN_EMPRESA', 'PROJETISTA'], true);
}

function stage_order(string $stage): int
{
    $order = [
        'PROJETO' => 0,
        'NEGOCIACAO' => 1,
        'CONFERENCIA' => 2,
        'MONTAGEM' => 3,
        'ASSISTENCIA' => 4,
        'FINALIZADO' => 5,
    ];

    return $order[$stage] ?? 0;
}

function status_options(string $stage): array
{
    return match ($stage) {
        'PROJETO' => ['Sondagem', 'Medição', 'Projeto', 'Pronto para apresentação'],
        'NEGOCIACAO' => ['Detalhamento de venda', 'Proposta enviada', 'Em negociação', 'Aguardando retorno', 'Fechado', 'Perdido'],
        'CONFERENCIA' => ['Medição', 'Conferência', 'Detalhamento', 'Ajuste pendente', 'Liberado para fábrica'],
        'MONTAGEM' => ['Vistoria de montagem', 'Agendada', 'Início da montagem', 'Em montagem', 'Pendente', 'Finalizada'],
        'ASSISTENCIA' => ['Aberta', 'Em atendimento', 'Aguardando peça'],
        default => ['Finalizado'],
    };
}

function status_field_for_stage(string $stage): ?string
{
    return match ($stage) {
        'PROJETO' => 'project_status',
        'NEGOCIACAO' => 'negotiation_status',
        'CONFERENCIA' => 'conference_status',
        'MONTAGEM' => 'assembly_status',
        'ASSISTENCIA' => 'assistance_status',
        default => null,
    };
}

function next_stage(string $stage): ?string
{
    $flow = [
        'PROJETO' => 'NEGOCIACAO',
        'NEGOCIACAO' => 'CONFERENCIA',
        'CONFERENCIA' => 'MONTAGEM',
        'MONTAGEM' => 'ASSISTENCIA',
        'ASSISTENCIA' => 'FINALIZADO',
    ];

    return $flow[$stage] ?? null;
}

function active_nav(string $current, string $target): string
{
    return $current === $target ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100';
}

function null_if_empty($value)
{
    $value = is_string($value) ? trim($value) : $value;
    return $value === '' ? null : $value;
}

function month_range(int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    return [$start, $end];
}

function month_name_pt(int $month): string
{
    return [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ][$month] ?? (string) $month;
}

function payment_status(float $soldValue, float $received): string
{
    if ($received <= 0) {
        return 'Pendente';
    }
    if ($received + 0.01 >= $soldValue) {
        return 'Pago';
    }
    return 'Parcial';
}
