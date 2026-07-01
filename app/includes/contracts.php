<?php

function contracts_bootstrap(int $companyId = 0): void
{
    db()->exec("create table if not exists contract_templates (
      id int unsigned auto_increment primary key,
      company_id int unsigned not null,
      title varchar(160) not null,
      body longtext not null,
      source_file_original varchar(255) null,
      source_file_stored varchar(255) null,
      source_file_size int unsigned null,
      source_file_mime varchar(100) null,
      active tinyint(1) not null default 1,
      created_at timestamp not null default current_timestamp,
      updated_at timestamp null default null on update current_timestamp,
      index ct_company_idx (company_id, active)
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");

    db()->exec("create table if not exists project_contracts (
      id int unsigned auto_increment primary key,
      company_id int unsigned not null,
      client_project_id int unsigned not null,
      template_id int unsigned null,
      title varchar(180) not null,
      body longtext not null,
      source_file_original varchar(255) null,
      source_file_stored varchar(255) null,
      source_file_size int unsigned null,
      source_file_mime varchar(100) null,
      status enum('DRAFT','SENT','ACCEPTED','SIGNED_GOVBR','SIGNED_MANUAL','CANCELED') not null default 'DRAFT',
      signature_method enum('PENDING','INTERNAL_ACCEPTANCE','GOVBR_UPLOAD','MANUAL_DRAW') not null default 'PENDING',
      public_token varchar(64) not null unique,
      accepted_name varchar(160) null,
      accepted_document varchar(40) null,
      accepted_email varchar(160) null,
      accepted_ip varchar(80) null,
      accepted_user_agent varchar(255) null,
      accepted_at datetime null,
      manual_signature_data longtext null,
      signed_file_original varchar(255) null,
      signed_file_stored varchar(255) null,
      signed_file_size int unsigned null,
      signed_file_mime varchar(100) null,
      signed_file_uploaded_at datetime null,
      created_by_user_id int unsigned null,
      created_at timestamp not null default current_timestamp,
      updated_at timestamp null default null on update current_timestamp,
      index pc_company_status_idx (company_id, status),
      index pc_project_idx (client_project_id)
    ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci");

    contracts_add_column_if_missing('contract_templates', 'source_file_original', 'varchar(255) null after body');
    contracts_add_column_if_missing('contract_templates', 'source_file_stored', 'varchar(255) null after source_file_original');
    contracts_add_column_if_missing('contract_templates', 'source_file_size', 'int unsigned null after source_file_stored');
    contracts_add_column_if_missing('contract_templates', 'source_file_mime', 'varchar(100) null after source_file_size');
    contracts_add_column_if_missing('project_contracts', 'source_file_original', 'varchar(255) null after body');
    contracts_add_column_if_missing('project_contracts', 'source_file_stored', 'varchar(255) null after source_file_original');
    contracts_add_column_if_missing('project_contracts', 'source_file_size', 'int unsigned null after source_file_stored');
    contracts_add_column_if_missing('project_contracts', 'source_file_mime', 'varchar(100) null after source_file_size');
    contracts_add_column_if_missing('project_contracts', 'manual_signature_data', 'longtext null after accepted_at');
    contracts_update_contract_enums();

    if ($companyId > 0) {
        $stmt = db()->prepare('select count(*) from contract_templates where company_id = ?');
        $stmt->execute([$companyId]);
        if ((int) $stmt->fetchColumn() === 0) {
            db()->prepare('insert into contract_templates (company_id, title, body) values (?, ?, ?)')->execute([
                $companyId,
                'Contrato padrao de projeto',
                default_contract_template_body(),
            ]);
        }
    }
}

function contracts_update_contract_enums(): void
{
    try {
        db()->exec("alter table project_contracts modify status enum('DRAFT','SENT','ACCEPTED','SIGNED_GOVBR','SIGNED_MANUAL','CANCELED') not null default 'DRAFT'");
        db()->exec("alter table project_contracts modify signature_method enum('PENDING','INTERNAL_ACCEPTANCE','GOVBR_UPLOAD','MANUAL_DRAW') not null default 'PENDING'");
    } catch (Throwable) {
    }
}

function contracts_add_column_if_missing(string $table, string $column, string $definition): void
{
    try {
        $stmt = db()->prepare('select count(*) from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?');
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            db()->exec("alter table {$table} add {$column} {$definition}");
        }
    } catch (Throwable) {
    }
}

function default_contract_template_body(): string
{
    return "CONTRATO DE PRESTACAO DE SERVICOS\n\n"
        . "CONTRATANTE: {{cliente}}\n"
        . "Telefone: {{telefone}}\n"
        . "Endereco: {{endereco}}\n\n"
        . "CONTRATADA: {{empresa}}\n\n"
        . "Projeto: {{projeto}}\n"
        . "Projetista responsavel: {{projetista}}\n"
        . "Valor contratado: {{valor_fechado}}\n"
        . "Data de fechamento: {{data_fechamento}}\n\n"
        . "1. A contratada executara os servicos relacionados ao projeto acima descrito.\n"
        . "2. Prazos, entregas, montagem, assistencia e demais condicoes seguem as informacoes combinadas entre as partes.\n"
        . "3. O aceite eletronico ou a assinatura digital do contratante confirma ciencia e concordancia com este contrato.\n\n"
        . "Observacoes do projeto:\n{{observacoes}}\n";
}

function contract_status_label(string $status): string
{
    return [
        'DRAFT' => 'Rascunho',
        'SENT' => 'Enviado',
        'ACCEPTED' => 'Assinado',
        'SIGNED_GOVBR' => 'Assinado',
        'SIGNED_MANUAL' => 'Assinado',
        'CANCELED' => 'Cancelado',
    ][$status] ?? $status;
}

function contract_method_label(string $method): string
{
    return [
        'PENDING' => 'Pendente',
        'INTERNAL_ACCEPTANCE' => 'Aceite',
        'GOVBR_UPLOAD' => 'gov.br',
        'MANUAL_DRAW' => 'Manual',
    ][$method] ?? $method;
}

function render_contract_body(string $body, array $project, array $company): string
{
    $map = [
        '{{cliente}}' => $project['client_name'] ?? '',
        '{{telefone}}' => $project['client_phone'] ?? '',
        '{{endereco}}' => $project['client_address'] ?? '',
        '{{projeto}}' => $project['project_name'] ?? '',
        '{{projetista}}' => $project['designer_name'] ?? '',
        '{{valor_fechado}}' => money_br($project['closed_value'] ?? 0),
        '{{data_fechamento}}' => date_br($project['closing_date'] ?? null),
        '{{observacoes}}' => trim((string) ($project['notes'] ?? '')),
        '{{empresa}}' => $company['name'] ?? '',
        '{{empresa_documento}}' => $company['document'] ?? '',
        '{{empresa_email}}' => $company['email'] ?? '',
        '{{empresa_telefone}}' => $company['phone'] ?? '',
        '{{empresa_endereco}}' => $company['address'] ?? '',
        '{{data_hoje}}' => date('d/m/Y'),
    ];

    return strtr($body, $map);
}

function contract_template_file_url(array $row): string
{
    if (empty($row['source_file_stored']) || empty($row['company_id'])) {
        return '';
    }

    if (!empty($row['public_token'])) {
        return '/contract-file.php?t=' . rawurlencode($row['public_token']) . '&kind=source';
    }

    if (!empty($row['id'])) {
        return '/contract-template-file.php?id=' . (int) $row['id'];
    }

    return '';
}

function contract_signed_file_url(array $row): string
{
    if (empty($row['signed_file_stored']) || empty($row['public_token'])) {
        return '';
    }

    return '/contract-file.php?t=' . rawurlencode($row['public_token']) . '&kind=signed';
}

function contract_original_file_path(array $row): string
{
    if (empty($row['source_file_stored']) || empty($row['company_id'])) {
        return '';
    }

    return __DIR__ . '/../../uploads/contracts/templates/' . (int) $row['company_id'] . '/' . $row['source_file_stored'];
}

function contract_signed_file_path(array $row, string $storedName): string
{
    return __DIR__ . '/../../uploads/contracts/' . (int) $row['company_id'] . '/' . (int) $row['client_project_id'] . '/' . (int) $row['id'] . '/' . $storedName;
}

function contract_can_stamp_pdf(array $row): bool
{
    if (empty($row['source_file_stored'])) {
        return false;
    }

    $isPdf = ($row['source_file_mime'] ?? '') === 'application/pdf'
        || strtolower(pathinfo($row['source_file_original'] ?? '', PATHINFO_EXTENSION)) === 'pdf';

    return $isPdf && is_file(contract_original_file_path($row));
}

function contract_generate_manual_signed_pdf(array $row, string $signatureData, string $signerName, string $signedAt): ?array
{
    require_once __DIR__ . '/contract-pdf.php';

    $storedName = sprintf('contrato_%d_assinado_%s.pdf', (int) $row['id'], bin2hex(random_bytes(5)));
    $outputPath = contract_signed_file_path($row, $storedName);

    $ok = contract_pdf_generate($row, $signatureData, $signerName, $signedAt, $outputPath);

    if (!$ok) {
        return null;
    }

    $baseName = !empty($row['source_file_original'])
        ? preg_replace('/\.pdf$/i', '', $row['source_file_original']) . ' - assinado.pdf'
        : 'contrato-' . (int) $row['id'] . '-assinado.pdf';

    return [
        'original' => $baseName,
        'stored'   => $storedName,
        'size'     => filesize($outputPath) ?: null,
        'mime'     => 'application/pdf',
    ];
}

function contract_regenerate_manual_signed_pdf(array $row): ?array
{
    if (($row['status'] ?? '') !== 'SIGNED_MANUAL' || empty($row['manual_signature_data'])) {
        return null;
    }

    $signedPdf = contract_generate_manual_signed_pdf(
        $row,
        $row['manual_signature_data'],
        $row['accepted_name'] ?: 'Contratante',
        !empty($row['accepted_at']) ? date('d/m/Y H:i', strtotime($row['accepted_at'])) : date('d/m/Y H:i')
    );

    if (!$signedPdf) {
        return null;
    }

    if (!empty($row['signed_file_stored']) && $row['signed_file_stored'] !== $signedPdf['stored']) {
        $oldPath = contract_signed_file_path($row, $row['signed_file_stored']);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    db()->prepare(
        'update project_contracts
         set signed_file_original = ?, signed_file_stored = ?, signed_file_size = ?,
             signed_file_mime = ?, signed_file_uploaded_at = ?
         where id = ?'
    )->execute([
        $signedPdf['original'],
        $signedPdf['stored'],
        $signedPdf['size'],
        $signedPdf['mime'],
        $row['accepted_at'] ?: date('Y-m-d H:i:s'),
        (int) $row['id'],
    ]);

    return $signedPdf;
}

function serve_contract_file(string $path, string $downloadName, string $mimeType): never
{
    if (!is_file($path)) {
        http_response_code(404);
        exit('Arquivo nao encontrado.');
    }

    $safeName = str_replace(["\r", "\n", '"'], '', $downloadName);
    header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
