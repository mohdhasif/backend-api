<?php
require_once 'db.php';

echo "=== Test Notification System ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Test OneSignal connection
function test_onesignal($subscriptionId) {
    $ONESIGNAL_APP_ID = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
    $ONESIGNAL_REST_API_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi';
    
    $payload = [
        'app_id' => $ONESIGNAL_APP_ID,
        'include_subscription_ids' => [$subscriptionId],
        'headings' => ['en' => 'Test Notification'],
        'contents' => ['en' => 'This is a test notification to verify the system is working.'],
        'ttl' => 300,
    ];

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $ONESIGNAL_REST_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    
    return ['success' => ($code >= 200 && $code < 300), 'code' => $code, 'response' => $res];
}

// Get active subscriptions
$sql = "SELECT subscription_id, user_id FROM user_push_subscriptions WHERE subscription_id IS NOT NULL LIMIT 1";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $testSubscription = $row['subscription_id'];
    
    echo "Testing notification to subscription: $testSubscription\n";
    echo "User ID: " . ($row['user_id'] ?? 'NULL') . "\n\n";
    
    $result = test_onesignal($testSubscription);
    
    if ($result['success']) {
        echo "✅ SUCCESS: Test notification sent!\n";
        echo "Response code: " . $result['code'] . "\n";
        echo "Response: " . $result['response'] . "\n";
    } else {
        echo "❌ FAILED: Test notification failed\n";
        echo "Response code: " . $result['code'] . "\n";
        echo "Response: " . $result['response'] . "\n";
    }
} else {
    echo "❌ No active subscriptions found\n";
}

echo "\n=== Next Prayer Times ===\n";
echo "Maghrib: 19:23 (7:23 PM)\n";
echo "Isha: 20:33 (8:33 PM)\n";

echo "\n=== System Status ===\n";
echo "Cron running: " . (shell_exec('tasklist /FI "IMAGENAME eq wscript.exe" 2>nul | find "wscript.exe" >nul && echo "YES" || echo "NO"')) . "\n";
echo "PHP working: " . (function_exists('curl_init') ? "YES" : "NO") . "\n";
echo "Database connected: " . ($conn ? "YES" : "NO") . "\n";
?>
