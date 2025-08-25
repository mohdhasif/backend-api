<?php
/**
 * Test Prayer Notification
 * Sends a dummy notification to test the system
 */

require_once __DIR__ . '/db.php';

// Include push helper functions
require_once __DIR__ . '/push_helper.php';

// Test notification function using push helper
function send_test_notification($subscriptionId, $title, $body) {
    echo "📱 Sending test notification...\n";
    
    $result = onesignal_send_to_player_ids([$subscriptionId], $title, $body, [
        'type' => 'test_notification',
        'timestamp' => time()
    ]);
    
    if ($result['success']) {
        echo "✅ Notification sent successfully!\n";
        echo "HTTP Code: {$result['http_code']}\n";
        echo "Response: {$result['response']}\n";
        return true;
    } else {
        echo "❌ Notification failed!\n";
        echo "HTTP Code: {$result['http_code']}\n";
        echo "Response: {$result['response']}\n";
        return false;
    }
}

// Get all active subscriptions
$sql = "
SELECT 
    ups.id,
    ups.subscription_id,
    ups.user_id,
    ups.install_id,
    u.name as user_name,
    u.role as user_role
FROM user_push_subscriptions ups
LEFT JOIN users u ON ups.user_id = u.id
WHERE ups.subscription_id IS NOT NULL
ORDER BY ups.user_id, ups.id
";

$result = $conn->query($sql);
$subscriptions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

echo "=== Prayer Notification Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Total subscriptions found: " . count($subscriptions) . "\n\n";

if (empty($subscriptions)) {
    echo "❌ No active subscriptions found!\n";
    exit;
}

// Send test notification to each subscription
$success_count = 0;
$total_count = 0;

foreach ($subscriptions as $sub) {
    $total_count++;
    $user_name = $sub['user_name'] ?? 'Guest';
    $user_role = $sub['user_role'] ?? 'guest';
    
    echo "--- Testing Subscription #{$sub['id']} ---\n";
    echo "User: $user_name (Role: $user_role)\n";
    echo "Install ID: {$sub['install_id']}\n";
    echo "Subscription ID: {$sub['subscription_id']}\n";
    
    $title = "Test Prayer Notification";
    $body = "This is a test notification from the prayer cron system. Time: " . date('H:i:s');
    
    $success = send_test_notification($sub['subscription_id'], $title, $body);
    
    if ($success) {
        $success_count++;
    }
    
    echo "\n";
    
    // Only test first 3 subscriptions to avoid spam
    if ($total_count >= 3) {
        echo "⚠️  Limited to first 3 subscriptions to avoid spam\n";
        break;
    }
}

echo "=== Test Summary ===\n";
echo "Total tested: $total_count\n";
echo "Successful: $success_count\n";
echo "Failed: " . ($total_count - $success_count) . "\n";

if ($success_count > 0) {
    echo "\n✅ Test completed! Check your devices for notifications.\n";
} else {
    echo "\n❌ No notifications were sent successfully.\n";
}
?>
