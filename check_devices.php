<?php
require_once 'db.php';

echo "=== Device Count per User ===\n";

$sql = "
SELECT 
  user_id,
  COUNT(DISTINCT subscription_id) as device_count,
  GROUP_CONCAT(DISTINCT subscription_id) as devices
FROM user_push_subscriptions 
WHERE user_id IS NOT NULL
GROUP BY user_id
ORDER BY user_id
";

$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "User ID: " . $row['user_id'] . " | Devices: " . $row['device_count'] . "\n";
        echo "  Device IDs: " . $row['devices'] . "\n";
        echo "---\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n=== Total Subscriptions ===\n";
$sql2 = "SELECT COUNT(*) as total FROM user_push_subscriptions";
$res2 = $conn->query($sql2);
$total = $res2->fetch_assoc()['total'];
echo "Total subscriptions: " . $total . "\n";
?>
