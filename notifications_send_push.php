<?php
require_once 'db.php';

// .env-like constants (set properly)
const ONESIGNAL_APP_ID = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
const ONESIGNAL_REST_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi';

try {
    $admin = require_auth($conn); // check admin role if needed

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = (int)($payload['user_id'] ?? 0);
    $title = trim($payload['title'] ?? '');
    $body = trim($payload['body'] ?? '');
    $data = $payload['data'] ?? [];

    if ($user_id <= 0 || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id & title diperlukan']);
        exit;
    }

    // get player id
    $stmt = $conn->prepare("SELECT onesignal_player_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $player = $row['onesignal_player_id'] ?? null;

    if (!$player) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Player ID tiada']);
        exit;
    }

    $bodyReq = [
        'app_id' => ONESIGNAL_APP_ID,
        'include_player_ids' => [$player],
        'headings' => ['en' => $title],
        'contents' => ['en' => $body],
        'data' => $data,
        'ios_badgeType' => 'Increase',
        'ios_badgeCount' => 1,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_REST_KEY,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bodyReq));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $http >= 400) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Gagal hantar push']);
        exit;
    }
    echo json_encode(['success' => true, 'onesignal' => json_decode($resp, true)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ralat push']);
}

// publish