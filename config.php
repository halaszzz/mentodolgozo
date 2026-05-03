<?php

declare(strict_types=1);

const SUPABASE_URL = 'https://lsbdbfzeugqnurkmdhxh.supabase.co';
const SUPABASE_ANON_KEY = 'sb_publishable_GrhobqQt4uN4Cl5G9t1YmQ_KCKw-wFP';
const SUPABASE_SERVICE_ROLE_KEY = 'IDE_MASOLD_BE_A_SERVICE_ROLE_SECRET_KEYT';

function synthetic_email(string $username): string
{
    $clean = mb_strtolower(trim($username));
    $clean = preg_replace('/[^a-z0-9._-]+/iu', '', $clean);
    return $clean . '@tek.local';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function supabase_request(
    string $method,
    string $path,
    ?array $body = null,
    ?string $bearerToken = null,
    bool $useServiceRole = false
): array {
    $url = rtrim(SUPABASE_URL, '/') . $path;

    $headers = [
        'Content-Type: application/json',
        'apikey: ' . ($useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY),
    ];

    if ($bearerToken) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    } elseif ($useServiceRole) {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'status' => 500,
            'data' => ['message' => 'cURL hiba', 'error' => $curlError],
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $decoded = ['raw' => $response];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'data' => $decoded,
    ];
}

function get_bearer_token(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$auth || !preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

function require_admin_user(): array
{
    $token = get_bearer_token();
    if (!$token) {
        json_response(['success' => false, 'message' => 'Hiányzó admin token.'], 401);
    }

    $userResp = supabase_request('GET', '/auth/v1/user', null, $token, false);
    if (!$userResp['ok'] || empty($userResp['data']['id'])) {
        json_response(['success' => false, 'message' => 'Érvénytelen munkamenet.'], 401);
    }

    $adminId = $userResp['data']['id'];

    $profileResp = supabase_request(
        'GET',
        '/rest/v1/profiles?id=eq.' . rawurlencode($adminId) . '&select=id,is_admin',
        null,
        null,
        true
    );

    if (
        !$profileResp['ok'] ||
        empty($profileResp['data'][0]) ||
        empty($profileResp['data'][0]['is_admin'])
    ) {
        json_response(['success' => false, 'message' => 'Nincs admin jogosultság.'], 403);
    }

    return [
        'token' => $token,
        'user_id' => $adminId,
    ];
}