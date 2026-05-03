<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Csak POST kérés engedélyezett.'], 405);
}

$admin = require_admin_user();
$data = get_json_input();

$requestId = (int)($data['request_id'] ?? 0);

if ($requestId <= 0) {
    json_response(['success' => false, 'message' => 'Hiányzó request_id.'], 400);
}

$rejectResp = supabase_request(
    'PATCH',
    '/rest/v1/registration_requests?id=eq.' . $requestId . '&status=eq.pending',
    [
        'status' => 'rejected',
        'rejected_at' => gmdate('c'),
        'rejected_by' => $admin['user_id'],
        'admin_note' => 'Elutasítva'
    ],
    null,
    true
);

if (!$rejectResp['ok']) {
    json_response([
        'success' => false,
        'message' => 'Nem sikerült elutasítani a regisztrációt.',
        'debug' => $rejectResp['data']
    ], 400);
}

json_response([
    'success' => true,
    'message' => 'A regisztráció elutasítva.'
]);