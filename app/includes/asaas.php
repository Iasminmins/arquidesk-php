<?php

/**
 * Integração com o gateway de pagamento Asaas.
 * Portado do site-carteira (QRCertify), adaptado ao padrão de array-config do Arquidesk.
 *
 * Config esperada no array 'asaas' do config.local.php:
 *   env             => 'sandbox' | 'production'
 *   key             => chave de API (sandbox)
 *   key_prod        => chave de API (produção)
 *   webhook_token   => string secreta para validar o webhook
 */

function asaas_config(): array
{
    $config = require __DIR__ . '/../config/config.php';
    return $config['asaas'] ?? [];
}

function asaas_env(): string
{
    $cfg = asaas_config();
    return ($cfg['env'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
}

function asaas_base_url(): string
{
    return asaas_env() === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://api-sandbox.asaas.com/v3';
}

function asaas_api_key(): string
{
    $cfg = asaas_config();
    if (asaas_env() === 'production') {
        return (string) ($cfg['key_prod'] ?? $cfg['key'] ?? '');
    }
    return (string) ($cfg['key'] ?? '');
}

function asaas_webhook_token(): string
{
    $cfg = asaas_config();
    return (string) ($cfg['webhook_token'] ?? '');
}

/**
 * Faz uma requisição à API do Asaas.
 * Retorna o array decodificado da resposta (ou ['errors' => [...]] em caso de falha).
 */
function asaas_request(string $method, string $endpoint, array $body = []): array
{
    $apiKey = asaas_api_key();
    if ($apiKey === '') {
        error_log('[Asaas] Chave de API não configurada.');
        return ['errors' => [['description' => 'Gateway de pagamento não configurado.']]];
    }

    $url = asaas_base_url() . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'access_token: ' . $apiKey,
            'User-Agent: Arquidesk/1.0',
        ],
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method !== 'GET' && !empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log("[Asaas] Falha de conexão: {$curlErr}");
        return ['errors' => [['description' => 'Falha de conexão com o gateway.']]];
    }

    $decoded = json_decode($raw ?: '{}', true) ?: [];
    if ($code >= 400) {
        error_log("[Asaas] {$method} {$endpoint} → HTTP {$code}: {$raw}");
    }
    return $decoded;
}

/**
 * Monta a URL do checkout hospedado a partir do ID.
 */
function asaas_checkout_url(string $checkoutId): string
{
    $domain = asaas_env() === 'production' ? 'https://www.asaas.com' : 'https://sandbox.asaas.com';
    return $domain . '/checkoutSession/show?id=' . rawurlencode($checkoutId);
}


/**
 * Cria (ou atualiza) um cliente no Asaas e retorna o ID do cliente.
 * Retorna ['id' => 'cus_xxx'] em sucesso ou ['errors' => [...]].
 *
 * @param array $data name, cpfCnpj, email, phone (cpfCnpj é obrigatório no Asaas)
 */
function asaas_create_customer(array $data): array
{
    $body = [
        'name'     => $data['name'] ?? '',
        'cpfCnpj'  => preg_replace('/\D/', '', (string) ($data['cpfCnpj'] ?? '')),
        'email'    => $data['email'] ?? '',
        'mobilePhone' => preg_replace('/\D/', '', (string) ($data['phone'] ?? '')),
    ];
    return asaas_request('POST', '/customers', $body);
}

/**
 * Cria uma assinatura recorrente mensal no Asaas.
 * billingType: 'UNDEFINED' deixa o cliente escolher Pix/boleto/cartão no checkout.
 *
 * @param string $customerId  ID do cliente Asaas (cus_xxx)
 * @param float  $value       valor mensal
 * @param string $description descrição (ex.: 'Arquidesk - Plano Profissional')
 * @param string $nextDueDate vencimento da 1ª cobrança (Y-m-d)
 */
function asaas_create_subscription(string $customerId, float $value, string $description, string $nextDueDate): array
{
    $body = [
        'customer'    => $customerId,
        'billingType' => 'UNDEFINED',
        'value'       => $value,
        'nextDueDate' => $nextDueDate,
        'cycle'       => 'MONTHLY',
        'description' => $description,
    ];
    return asaas_request('POST', '/subscriptions', $body);
}

/**
 * Busca a primeira cobrança gerada por uma assinatura (para pegar o link de pagamento).
 * Retorna a URL da fatura (invoiceUrl) ou string vazia.
 */
function asaas_subscription_first_payment_url(string $subscriptionId): string
{
    $resp = asaas_request('GET', '/subscriptions/' . rawurlencode($subscriptionId) . '/payments');
    $first = $resp['data'][0] ?? null;
    return is_array($first) ? (string) ($first['invoiceUrl'] ?? '') : '';
}
