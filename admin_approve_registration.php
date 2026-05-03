<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

$admin = require_admin_user();
$data = get_json_input();

$requestId = (int)($data['request_id'] ?? 0);
$isAdminForNewUser = (bool)($data['is_admin'] ?? false);

if ($requestId <= 0) {
    json_response(['success' => false, 'message' => 'Hiányzó request_id.'], 400);
}

$reqResp = supabase_request(
    'GET',
    '/rest/v1/registration_requests?id=eq.' . $requestId . '&status=eq.pending&select=*',
    null,
    null,
    true
);

if (!$reqResp['ok'] || empty($reqResp['data'][0])) {
    json_response(['success' => false, 'message' => 'A regisztrációs kérelem nem található vagy már nem függőben van.'], 404);
}

$requestRow = $reqResp['data'][0];
$username = $requestRow['username'];
$email = synthetic_email($username);
$tempPassword = 'Tek!' . random_int(100000, 999999);

$createUserResp = supabase_request(
    'POST',
    '/auth/v1/admin/users',
    [
        'email' => $email,
        'password' => $tempPassword,
        'email_confirm' => true,
        'user_metadata' => [
            'username' => $username
        ]
    ],
    null,
    true
);

if (!$createUserResp['ok'] || empty($createUserResp['data']['id'])) {
    json_response([
        'success' => false,
        'message' => 'Nem sikerült létrehozni az auth usert.',
        'debug' => $createUserResp['data']
    ], 400);
}

$newUserId = $createUserResp['data']['id'];

$updateProfileResp = supabase_request(
    'PATCH',
    '/rest/v1/profiles?id=eq.' . rawurlencode($newUserId),
    [
        'full_name' => $username,
        'is_admin' => $isAdminForNewUser,
        'must_change_password' => true
    ],
    null,
    true
);

$approveResp = supabase_request(
    'PATCH',
    '/rest/v1/registration_requests?id=eq.' . $requestId,
    [
        'status' => 'approved',
        'approved_at' => gmdate('c'),
        'approved_by' => $admin['user_id'],
        'admin_note' => 'Jóváhagyva'
    ],
    null,
    true
);

if (!$approveResp['ok']) {
    json_response([
        'success' => false,
        'message' => 'A user létrejött, de a regisztrációs sor frissítése nem sikerült.',
        'temp_password' => $tempPassword
    ], 500);
}

json_response([
    'success' => true,
    'message' => 'A regisztráció jóváhagyva.',
    'username' => $username,
    'temp_password' => $tempPassword
]);