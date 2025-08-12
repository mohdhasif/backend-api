<?php
// push_helper.php
// Reusable helpers to send OneSignal push by subscription_id (player_id) + logging

if (!defined('ONESIGNAL_APP_ID')) {
    // TODO: replace with your actual values (or load from env)
    define('ONESIGNAL_APP_ID',      'eff1e397-c7ae-468d-9cd5-c673ba80821d');
    define('ONESIGNAL_REST_API_KEY', 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi');
    define('PUSH_LOG_FILE', __DIR__ . '/push.log'); // lokasi fail log
    define('CACERT_PATH', __DIR__ . '/cacert.pem'); // optional, boleh null
}

/**
 * Write log line to PUSH_LOG_FILE
 */
function push_log(string $message): void
{
    $line = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    file_put_contents(PUSH_LOG_FILE, $line, FILE_APPEND);
}

/**
 * Resolve player_ids (subscription_id) for a user.
 * - admin  -> ALL devices
 * - others -> LATEST device only (by last_seen_at desc)
 */
function get_player_ids_for_user(mysqli $conn, int $userId): array
{
    $role = null;
    if ($stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id=? LIMIT 1")) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($role);
        $stmt->fetch();
        $stmt->close();
    }

    $sql = ($role === 'admin')
        ? "SELECT subscription_id FROM user_push_subscriptions
             WHERE user_id=? AND subscription_id <> ''"
        : "SELECT subscription_id FROM user_push_subscriptions
             WHERE user_id=? AND subscription_id <> ''
             ORDER BY last_seen_at DESC LIMIT 1";

    $ids = [];
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['subscription_id'])) $ids[] = $row['subscription_id'];
        }
        $stmt->close();
    }
    return $ids;
}

/**
 * Low-level OneSignal send by player_ids.
 * Returns ['success'=>bool, 'http_code'=>int, 'response'=>string]
 */
function onesignal_send_to_player_ids(array $playerIds, string $title, string $message, array $data = []): array
{
    if (empty($playerIds)) {
        push_log("No player_ids provided. Title: {$title}");
        return ['success' => false, 'http_code' => 0, 'response' => 'No player_ids'];
    }

    $payload = [
        'app_id'             => ONESIGNAL_APP_ID,
        'include_player_ids' => array_values(array_unique($playerIds)),
        'headings'           => ['en' => $title],
        'contents'           => ['en' => $message],
        'data'               => (object)$data,
    ];

    push_log("Sending to player_ids: " . json_encode($playerIds) . " | Payload: " . json_encode($payload));

    $ch = curl_init('https://onesignal.com/api/v1/notifications');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . ONESIGNAL_REST_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if (defined('CACERT_PATH') && CACERT_PATH && file_exists(CACERT_PATH)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, CACERT_PATH);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    push_log("HTTP {$http} | Error: {$err} | Response: {$response}");

    $ok = ($err === '' && $http >= 200 && $http < 300);
    return ['success' => $ok, 'http_code' => $http, 'response' => $response ?: $err];
}

/**
 * High-level: send to a user_id (resolves player_ids with role rules) + log to notifications table.
 */
function notify_user(mysqli $conn, int $userId, string $title, string $message, array $data = [], ?string $type = 'system'): array
{
    $playerIds = get_player_ids_for_user($conn, $userId);

    $notifId = null;
    if ($stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, data_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())")) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $stmt->bind_param("issss", $userId, $title, $message, $type, $json);
        $stmt->execute();
        $notifId = $stmt->insert_id ?: null;
        $stmt->close();
    }

    if (empty($playerIds)) {
        push_log("No active devices for user {$userId}. Title: {$title}");
        return [
            'success'         => false,
            'http_code'       => 0,
            'response'        => 'No active devices',
            'player_ids'      => [],
            'notification_id' => $notifId,
        ];
    }

    $r = onesignal_send_to_player_ids($playerIds, $title, $message, $data);
    $r['player_ids']      = $playerIds;
    $r['notification_id'] = $notifId;

    return $r;
}

/**
 * Notify multiple users and log each result.
 */
function notify_users(mysqli $conn, array $userIds, string $title, string $message, array $data = [], ?string $type = 'system'): array
{
    $results = [];
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        $results[$uid] = notify_user($conn, $uid, $title, $message, $data, $type);
    }
    return $results;
}
