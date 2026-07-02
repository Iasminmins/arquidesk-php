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

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sessão expirada ou formulário inválido. Volte, atualize a página e tente novamente.');
    }
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json') || strtolower($xrw) === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
            '/my-day.php' => 'Meu Dia',
            '/schedule.php' => 'Agendamentos',
            '/future-clients.php' => 'Clientes Futuros',
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/finance.php' => 'Financeiro',
            '/contracts.php' => 'Contratos',
            '/project-files.php' => 'Arquivos de Projetos',
            '/goals.php?mode=my-goal' => 'Minha Meta',
            '/import-export.php' => 'Minhas Exportações',
        ],
        'CONFERENTE' => [
            '/' => 'Dashboard',
            '/my-day.php' => 'Meu Dia',
            '/schedule.php' => 'Agendamentos',
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/finance.php' => 'Financeiro',
            '/goals.php?mode=team-goals' => 'Metas da Equipe',
            '/import-export.php' => 'Exportações Operacionais',
        ],
        'SUPER_ADMIN' => [
            '/super-admin.php' => 'Dashboard SaaS',
            '/super-admin.php?view=companies' => 'Empresas',
            '/super-admin.php?view=subscriptions' => 'Assinaturas',
            '/super-admin.php?view=users' => 'Usuários Globais',
        ],
        default => [
            '/' => 'Dashboard',
            '/my-day.php' => 'Meu Dia',
            '/schedule.php' => 'Agendamentos',
            '/future-clients.php' => 'Clientes Futuros',
            '/projects.php?stage=PROJETO' => 'Projeto',
            '/projects.php?stage=NEGOCIACAO' => 'Negociação',
            '/projects.php?stage=CONFERENCIA' => 'Conferência',
            '/projects.php?stage=MONTAGEM' => 'Montagem',
            '/projects.php?stage=ASSISTENCIA' => 'Assistência',
            '/projects.php?stage=FINALIZADO' => 'Finalizados',
            '/finance.php' => 'Financeiro',
            '/contracts.php' => 'Contratos',
            '/project-files.php' => 'Arquivos de Projetos',
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
        'NEGOCIACAO' => ['Detalhamento de venda', 'Proposta enviada', 'Em negociação', 'Aguardando retorno', 'Fechado', 'Perdido', 'Desistida'],
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

function plan_config(): array
{
    return [
        'ESSENCIAL' => ['name' => 'Start', 'legacy' => 'ESSENCIAL', 'price' => 149, 'priceLabel' => 'R$ 149/mês', 'users' => 'Até 4 usuários', 'userLimit' => 4, 'description' => 'Para empresas pequenas que querem sair das planilhas e organizar a operação.', 'highlighted' => false, 'badge' => ''],
        'PROFISSIONAL' => ['name' => 'Profissional', 'legacy' => 'PROFISSIONAL', 'price' => 297, 'priceLabel' => 'R$ 297/mês', 'users' => 'Até 8 usuários', 'userLimit' => 8, 'description' => 'Para empresas que precisam controlar projetos, financeiro, metas e equipe.', 'highlighted' => true, 'badge' => 'Mais escolhido'],
        'PREMIUM' => ['name' => 'Business', 'legacy' => 'PREMIUM', 'price' => 497, 'priceLabel' => 'R$ 497/mês', 'users' => 'Até 15 usuários', 'userLimit' => 15, 'description' => 'Para operações maiores que precisam de mais usuários, controle e suporte.', 'highlighted' => false, 'badge' => ''],
    ];
}

function plan_label(string $plan): string
{
    $plans = plan_config();
    return $plans[$plan]['name'] ?? $plans['PROFISSIONAL']['name'];
}

function plan_user_limit(string $plan): int
{
    $plans = plan_config();
    return $plans[$plan]['userLimit'] ?? $plans['PROFISSIONAL']['userLimit'];
}

function plan_included_features(): array
{
    return [
        'Gestão completa de projetos',
        'Fluxo de etapas da operação',
        'Dashboard gerencial',
        'Financeiro',
        'Metas de projetistas',
        'Controle de comissões',
        'Agenda',
        'Permissões por função',
        'Importação e exportação',
        'Identidade visual da empresa',
    ];
}

function commission_rate(float $totalReceived): float
{
    if ($totalReceived > 150000) return 0.07;
    if ($totalReceived > 100000) return 0.06;
    return 0.05;
}

function get_subscription(int $companyId): array
{
    $stmt = db()->prepare('select * from subscriptions where company_id = ? limit 1');
    $stmt->execute([$companyId]);
    $sub = $stmt->fetch();
    if (!$sub) {
        db()->prepare("insert into subscriptions (company_id, plan, status, trial_ends_at) values (?, 'PROFISSIONAL', 'TRIAL', date_add(curdate(), interval 30 day))")->execute([$companyId]);
        $stmt->execute([$companyId]);
        $sub = $stmt->fetch();
    }
    return $sub;
}

function is_subscription_blocked(array $subscription): bool
{
    return in_array($subscription['status'], ['CANCELED', 'BLOCKED'], true);
}

/**
 * Régua de carência (dunning) das assinaturas.
 *
 * Dado o vencimento, calcula em que fase a empresa está SEM nunca apagar dados.
 * Bloquear = apenas suspender acesso (redirect para pagamento).
 *
 * Fases (dias após o vencimento):
 *   0          -> 'active'   : em dia (ou ainda não venceu)
 *   1 a 7      -> 'grace'    : venceu, ainda usa, aviso normal
 *   8 a 10     -> 'critical' : venceu, ainda usa, aviso urgente
 *   11+        -> 'blocked'  : acesso suspenso
 *
 * Status administrativos manuais têm prioridade:
 *   CANCELED/BLOCKED -> sempre 'blocked'
 *   ACTIVE com vencimento futuro -> 'active'
 */
function subscription_grace_days(): int
{
    return 10; // total de dias de tolerância após o vencimento
}

function subscription_critical_days(): int
{
    return 3; // últimos dias (dentro da carência) com aviso urgente
}

function subscription_state(array $subscription): array
{
    $status = $subscription['status'] ?? 'TRIAL';

    // Bloqueio administrativo manual sempre vence.
    if (in_array($status, ['CANCELED', 'BLOCKED'], true)) {
        return ['phase' => 'blocked', 'days_left' => 0, 'days_overdue' => null];
    }

    $endRaw = !empty($subscription['current_period_end'])
        ? $subscription['current_period_end']
        : ($subscription['trial_ends_at'] ?? null);

    // Sem data de referência: não bloqueia (evita cortar por dado faltante).
    if (!$endRaw) {
        return ['phase' => 'active', 'days_left' => null, 'days_overdue' => null];
    }

    $today = new DateTimeImmutable('today');
    $end = new DateTimeImmutable($endRaw);
    $grace = subscription_grace_days();
    $critical = subscription_critical_days();

    if ($today <= $end) {
        // Ainda não venceu.
        $daysLeft = (int) $today->diff($end)->days;
        return ['phase' => 'active', 'days_left' => $daysLeft, 'days_overdue' => 0];
    }

    $daysOverdue = (int) $end->diff($today)->days;
    $daysToBlock = $grace - $daysOverdue; // quantos dias faltam para bloquear

    if ($daysOverdue > $grace) {
        return ['phase' => 'blocked', 'days_left' => 0, 'days_overdue' => $daysOverdue];
    }
    if ($daysToBlock <= $critical) {
        return ['phase' => 'critical', 'days_left' => max(0, $daysToBlock), 'days_overdue' => $daysOverdue];
    }
    return ['phase' => 'grace', 'days_left' => max(0, $daysToBlock), 'days_overdue' => $daysOverdue];
}

function require_active_subscription(array $user, string $allowedPage = ''): void
{
    if ($user['role'] === 'SUPER_ADMIN') return;
    $sub = get_subscription((int) $user['company_id']);

    // Bloqueia se: status manual CANCELED/BLOCKED, OU a carência pós-vencimento estourou.
    // IMPORTANTE: bloquear = apenas suspender acesso. Nenhum dado é apagado.
    $state = subscription_state($sub);
    if ($state['phase'] !== 'blocked') return;

    $allowed = ['/subscription.php', '/company-settings.php', '/logout.php'];
    $current = strtok($_SERVER['REQUEST_URI'], '?');
    if (in_array($current, $allowed, true)) return;
    redirect('/subscription.php?blocked=1');
}

function advance_stage_validation(array $project): ?string
{
    $stage = $project['current_stage'];
    if ($stage === 'PROJETO') {
        if (empty($project['client_name']) || empty($project['client_phone']) || empty($project['project_name']) || empty($project['designer_id'])) {
            return 'Preencha cliente, telefone, projeto e projetista responsável antes de avançar.';
        }
    }
    if ($stage === 'NEGOCIACAO') {
        if (empty($project['closed_value']) || empty($project['closing_date'])) {
            return 'Informe valor fechado e data de fechamento antes de avançar.';
        }
    }
    return null;
}


function project_status_for_stage(array $project, string $stage): string
{
    $field = status_field_for_stage($stage);
    if ($field && !empty($project[$field])) {
        return $project[$field];
    }

    return match ($stage) {
        'PROJETO' => 'Sondagem',
        'NEGOCIACAO' => 'Detalhamento de venda',
        'CONFERENCIA' => 'Medição',
        'MONTAGEM' => 'Vistoria de montagem',
        'ASSISTENCIA' => 'Aberta',
        default => 'Finalizado',
    };
}

function project_dates_for_stage(array $project, string $stage): array
{
    return match ($stage) {
        'PROJETO' => [['Entrada', $project['entry_date']], ['Medição', $project['measurement_date']], ['Apresentação', $project['presentation_date']]],
        'NEGOCIACAO' => [['Entrada', $project['entry_date']], ['Apresentação', $project['presentation_date']], ['Fechamento', $project['closing_date']]],
        'CONFERENCIA' => [['Medição', $project['measurement_date']], ['Envio fábrica', $project['sent_to_factory_date']], ['Faturamento', $project['billing_date']]],
        'MONTAGEM' => [['Início montagem', $project['assembly_started_date']], ['Fim montagem', $project['assembly_finished_date']]],
        'ASSISTENCIA' => [['Pedido', $project['order_date']], ['Assistência', $project['assistance_date']]],
        default => [['Finalizado', $project['finished_at']]],
    };
}

function project_days_in_stage(array $project): int
{
    $ref = null;
    $projectId = (int) ($project['id'] ?? 0);
    $companyId = (int) ($project['company_id'] ?? 0);
    $stage = (string) ($project['current_stage'] ?? '');

    if ($projectId > 0 && $companyId > 0 && $stage !== '') {
        try {
            $stmt = db()->prepare(
                'select max(created_at)
                 from flow_history
                 where client_project_id = ? and company_id = ? and to_stage = ?'
            );
            $stmt->execute([$projectId, $companyId, $stage]);
            $ref = $stmt->fetchColumn() ?: null;
        } catch (Throwable) {
            $ref = null;
        }
    }

    $ref ??= $project['created_at'] ?? $project['updated_at'] ?? null;
    if (!$ref) {
        return 0;
    }

    $updated = new DateTimeImmutable(substr((string) $ref, 0, 10));
    $today = new DateTimeImmutable('today');

    return max(0, (int) $updated->diff($today)->days);
}

function project_stale_threshold(string $stage): int
{
    return in_array($stage, ['PROJETO', 'NEGOCIACAO'], true) ? 7 : 5;
}

function project_is_stale(array $project): bool
{
    $stage = (string) ($project['current_stage'] ?? '');
    if ($stage === '' || $stage === 'FINALIZADO') {
        return false;
    }
    if (($project['negotiation_status'] ?? '') === 'Desistida') {
        return false;
    }

    return project_days_in_stage($project) >= project_stale_threshold($stage);
}

function metric_delta(float $current, float $previous): array
{
    if ($previous == 0.0 && $current == 0.0) {
        return ['percent' => 0.0, 'direction' => 'flat', 'label' => '0%'];
    }
    if ($previous == 0.0) {
        return ['percent' => 100.0, 'direction' => 'up', 'label' => '+100%'];
    }

    $pct = (($current - $previous) / $previous) * 100;
    $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
    $sign = $pct > 0 ? '+' : '';

    return [
        'percent' => $pct,
        'direction' => $direction,
        'label' => $sign . number_format($pct, 0, ',', '.') . '%',
    ];
}

function previous_period_range(string $period, string $periodStart, string $periodEnd): array
{
    if ($period === 'today') {
        $day = date('Y-m-d', strtotime($periodStart . ' -1 day'));

        return [$day, $day];
    }
    if ($period === 'week') {
        return [
            date('Y-m-d', strtotime($periodStart . ' -7 days')),
            date('Y-m-d', strtotime($periodEnd . ' -7 days')),
        ];
    }
    if ($period === 'year') {
        $year = (int) date('Y', strtotime($periodStart)) - 1;

        return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
    }
    if ($period === 'custom') {
        $days = (int) ((strtotime($periodEnd) - strtotime($periodStart)) / 86400) + 1;
        $prevEnd = date('Y-m-d', strtotime($periodStart . ' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . max(0, $days - 1) . ' days'));

        return [$prevStart, $prevEnd];
    }

    $prevStart = date('Y-m-01', strtotime($periodStart . ' -1 month'));
    $prevEnd = date('Y-m-t', strtotime($prevStart));

    return [$prevStart, $prevEnd];
}

function whatsapp_url(string $phone): string
{
    $clean = preg_replace('/\D/', '', $phone);
    if (!$clean) {
        return '';
    }
    if (strlen($clean) <= 11) {
        $clean = '55' . $clean;
    }

    return 'https://wa.me/' . $clean;
}

function whatsapp_link(string $phone, bool $iconOnly = false): string
{
    $url = whatsapp_url($phone);
    if (!$url) {
        return '';
    }
    $icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
    if ($iconOnly) {
        return '<a href="' . $url . '" target="_blank" rel="noopener" title="Chamar no WhatsApp" class="inline-flex items-center">' . $icon . '</a>';
    }
    return '<a href="' . $url . '" target="_blank" rel="noopener" title="Chamar no WhatsApp" class="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700">' . $icon . '</a>';
}

function money_compact(float $value): string
{
    if ($value >= 1_000_000) {
        return 'R$ ' . number_format($value / 1_000_000, 1, ',', '.') . 'M';
    }
    if ($value >= 1_000) {
        return 'R$ ' . number_format($value / 1_000, 0, ',', '.') . 'k';
    }
    return $value > 0 ? money_br($value) : 'R$ 0';
}
