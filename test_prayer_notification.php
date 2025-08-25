<?php
require_once 'db.php';

echo "=== Test Prayer Notification to All Devices ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Test OneSignal connection with SSL disabled (like the main cron)
function test_prayer_notification($subscriptionId, $prayerName = 'Test Prayer') {
    $ONESIGNAL_APP_ID = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
    $ONESIGNAL_REST_API_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi';
    
    $payload = [
        'app_id' => $ONESIGNAL_APP_ID,
        'include_subscription_ids' => [$subscriptionId],
        'headings' => ['en' => "Waktu $prayerName"],
        'contents' => ['en' => "Sudah masuk waktu $prayerName."],
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
        CURLOPT_SSL_VERIFYPEER => false, // Same as main cron
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    return [
        'success' => ($code >= 200 && $code < 300), 
        'code' => $code, 
        'response' => $res,
        'error' => $error,
        'errno' => $errno
    ];
}

// Get all active subscriptions (all 3 devices)
$sql = "SELECT subscription_id, user_id, install_id FROM user_push_subscriptions WHERE subscription_id IS NOT NULL";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    echo "Found " . $res->num_rows . " active devices:\n";
    echo "=====================================\n";
    
    $deviceCount = 0;
    while ($row = $res->fetch_assoc()) {
        $deviceCount++;
        $subscriptionId = $row['subscription_id'];
        $userId = $row['user_id'] ?? 'NULL';
        $installId = $row['install_id'] ?? 'NULL';
        
        echo "Device #$deviceCount:\n";
        echo "  Subscription ID: " . substr($subscriptionId, 0, 20) . "...\n";
        echo "  User ID: $userId\n";
        echo "  Install ID: $installId\n";
        
        // Send test notification to this device
        echo "  Sending test notification...\n";
        $result = test_prayer_notification($subscriptionId, 'Test Prayer');
        
        if ($result['success']) {
            echo "  ✅ SUCCESS: Test notification sent!\n";
            echo "  Response code: " . $result['code'] . "\n";
        } else {
            echo "  ❌ FAILED: Test notification failed\n";
            echo "  Response code: " . $result['code'] . "\n";
            echo "  cURL error: " . $result['error'] . " (errno: " . $result['errno'] . ")\n";
        }
        echo "\n";
    }
    
    echo "=====================================\n";
    echo "✅ Test completed! Check your 3 devices for notifications.\n";
    echo "You should receive: 'Waktu Test Prayer' with message 'Sudah masuk waktu Test Prayer.'\n\n";
    
} else {
    echo "❌ No active subscriptions found\n";
    echo "Make sure you have devices registered in the app.\n";
}

echo "=== System Status ===\n";
echo "Cron running: " . (shell_exec('tasklist /FI "IMAGENAME eq wscript.exe" 2>nul | find "wscript.exe" >nul && echo "YES" || echo "NO"')) . "\n";
echo "PHP working: " . (function_exists('curl_init') ? "YES" : "NO") . "\n";
echo "Database connected: " . ($conn ? "YES" : "NO") . "\n";

echo "\n=== Next Real Prayer Times ===\n";
echo "Maghrib: 19:23 (7:23 PM)\n";
echo "Isha: 20:33 (8:33 PM)\n";
echo "Fajr (tomorrow): 06:15 (6:15 AM)\n";
?>
