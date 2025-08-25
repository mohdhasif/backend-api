<?php
require_once 'db.php';

echo "=== User Roles and Devices ===\n";

// Check if users table has role column
$sql = "SHOW COLUMNS FROM users LIKE 'role'";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    echo "✅ Users table has 'role' column\n\n";
    
    // Get all users with their roles and device counts
    $sql2 = "
    SELECT 
        u.id,
        u.role,
        COUNT(DISTINCT ups.subscription_id) as device_count,
        GROUP_CONCAT(DISTINCT ups.subscription_id) as devices
    FROM users u
    LEFT JOIN user_push_subscriptions ups ON u.id = ups.user_id
    GROUP BY u.id, u.role
    ORDER BY u.id
    ";
    
    $res2 = $conn->query($sql2);
    
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            echo "User ID: " . $row['id'] . " | Role: " . $row['role'] . " | Devices: " . $row['device_count'] . "\n";
            if ($row['devices']) {
                echo "  Device IDs: " . $row['devices'] . "\n";
            }
            echo "---\n";
        }
    }
} else {
    echo "❌ Users table does NOT have 'role' column\n";
    echo "You need to add a 'role' column to your users table.\n\n";
    
    echo "SQL to add role column:\n";
    echo "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'client';\n";
    echo "UPDATE users SET role = 'admin' WHERE id = 1; -- Set your admin user\n\n";
    
    // Show current device distribution without roles
    echo "=== Current Device Distribution ===\n";
    $sql3 = "
    SELECT 
        ups.user_id,
        COUNT(DISTINCT ups.subscription_id) as device_count,
        GROUP_CONCAT(DISTINCT ups.subscription_id) as devices
    FROM user_push_subscriptions ups
    WHERE ups.user_id IS NOT NULL
    GROUP BY ups.user_id
    ORDER BY ups.user_id
    ";
    
    $res3 = $conn->query($sql3);
    if ($res3) {
        while ($row = $res3->fetch_assoc()) {
            echo "User ID: " . $row['user_id'] . " | Devices: " . $row['device_count'] . "\n";
            echo "  Device IDs: " . $row['devices'] . "\n";
            echo "---\n";
        }
    }
}

echo "\n=== Latest Device IDs ===\n";
$sql4 = "
SELECT 
    user_id,
    subscription_id,
    id as device_id
FROM user_push_subscriptions 
WHERE user_id IS NOT NULL
ORDER BY user_id, id DESC
";

$res4 = $conn->query($sql4);
if ($res4) {
    $current_user = null;
    while ($row = $res4->fetch_assoc()) {
        $latest = ($current_user !== $row['user_id']) ? " (LATEST)" : "";
        echo "User " . $row['user_id'] . " | Device: " . $row['subscription_id'] . $latest . "\n";
        $current_user = $row['user_id'];
    }
}
?>
