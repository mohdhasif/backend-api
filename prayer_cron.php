<?php
/**
 * Prayer Cron Job - Role-based notifications
 * - Admin: All devices get notifications
 * - Client/Freelancer: Only latest device gets notifications
 * Runs every minute, checks prayer times, sends notifications
 */

require_once __DIR__ . '/db.php';

// Include push helper functions
require_once __DIR__ . '/push_helper.php';

// HTTP GET function
function http_get($url, $timeout = 20) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: prayer-cron/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log("HTTP GET failed ($httpCode): $error - $url");
        return null;
    }
    
    return $response;
}

// Cache helpers
function get_cache_dir() {
    $dir = __DIR__ . '/cache_prayer';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function fetch_prayer_times($lat, $lng) {
    $cache_file = sprintf('%s/gps_%s_%0.4f_%0.4f.json', 
        get_cache_dir(), 
        date('Y-m'), 
        $lat, 
        $lng
    );
    
    // Check cache first
    if (is_file($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    
    // Fetch from API
    $url = "https://api.waktusolat.app/v2/solat/{$lat}/{$lng}";
    $response = http_get($url, 25);
    
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || empty($data['prayers'])) {
        return null;
    }
    
    // Save to cache
    file_put_contents($cache_file, json_encode($data));
    return $data;
}

function get_today_prayer_times($month_data) {
    $today = (int)date('j');
    
    foreach ($month_data['prayers'] as $prayer) {
        if ((int)$prayer['day'] === $today) {
            return [
                'Fajr' => (int)$prayer['fajr'],
                'Dhuhr' => (int)$prayer['dhuhr'],
                'Asr' => (int)$prayer['asr'],
                'Maghrib' => (int)$prayer['maghrib'],
                'Isha' => (int)$prayer['isha'],
            ];
        }
    }
    
    return null;
}

function is_same_minute($time1, $time2) {
    return intdiv($time1, 60) === intdiv($time2, 60);
}

function get_user_role($conn, $userId) {
    if (!$userId) return 'guest';
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['role'] : 'guest';
}

// Main execution
$current_time = time();
$checked_count = 0;
$sent_count = 0;

// Get all active subscriptions with role-based filtering
$sql = "
SELECT 
    ups.id,
    ups.subscription_id,
    ups.user_id,
    ups.install_id,
    COALESCE(
        (SELECT method FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
        (SELECT method FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1),
        'GPS'
    ) AS method,
    COALESCE(
        (SELECT latitude FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
        (SELECT latitude FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1)
    ) AS latitude,
    COALESCE(
        (SELECT longitude FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
        (SELECT longitude FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1)
    ) AS longitude,
    COALESCE(
        (SELECT enabled FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
        (SELECT enabled FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1),
        1
    ) AS enabled,
    CASE 
        WHEN ups.user_id IS NOT NULL THEN (
            SELECT MAX(ups2.id) 
            FROM user_push_subscriptions ups2 
            WHERE ups2.user_id = ups.user_id
        )
        ELSE ups.id
    END as latest_device_id
FROM user_push_subscriptions ups
WHERE ups.subscription_id IS NOT NULL
";

$result = $conn->query($sql);
$subscriptions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

foreach ($subscriptions as $sub) {
    $checked_count++;
    
    // Skip if prayer notifications are disabled
    if ((int)($sub['enabled'] ?? 1) !== 1) {
        continue;
    }
    
    // Role-based device filtering
    if ($sub['user_id']) {
        $userRole = get_user_role($conn, $sub['user_id']);
        
        // For non-admin users, only process the latest device
        if ($userRole !== 'admin' && $sub['id'] != $sub['latest_device_id']) {
            continue;
        }
    }
    
    // Get prayer times
    $method = strtoupper((string)($sub['method'] ?? 'GPS'));
    $prayer_times = null;
    
    if ($method === 'GPS' && !empty($sub['latitude']) && !empty($sub['longitude'])) {
        $month_data = fetch_prayer_times((float)$sub['latitude'], (float)$sub['longitude']);
        if ($month_data) {
            $prayer_times = get_today_prayer_times($month_data);
        }
    }
    
    if (!$prayer_times) {
        continue;
    }
    
    // Check each prayer time
    foreach ($prayer_times as $prayer_name => $prayer_time) {
        if (!is_same_minute($current_time, $prayer_time)) {
            continue;
        }
        
        // Check if notification already sent today
        $today = date('Y-m-d');
        $check_sql = "
            SELECT COUNT(*) as count 
            FROM prayer_notifications_sent 
            WHERE install_id = ? AND prayer = ? AND DATE(sent_at) = ?
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $sub['install_id'], $prayer_name, $today);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['count'] > 0) {
            continue; // Already sent today
        }
        
        // Insert notification record to prevent duplicates
        $insert_sql = "
            INSERT IGNORE INTO prayer_notifications_sent (user_id, install_id, prayer, sent_at)
            VALUES (?, ?, ?, NOW())
        ";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iss", $sub['user_id'], $sub['install_id'], $prayer_name);
        $insert_stmt->execute();
        
        if ($insert_stmt->affected_rows === 0) {
            continue; // Already exists
        }
        
        // Send push notification using push helper
        $title = "Prayer Time: $prayer_name";
        $body = "It's time for $prayer_name prayer.";
        
        // Use push helper function to send notification
        $result = onesignal_send_to_player_ids([$sub['subscription_id']], $title, $body, [
            'type' => 'prayer_notification',
            'prayer' => $prayer_name,
            'timestamp' => time()
        ]);
        
        if (!$result['success']) {
            // Rollback if notification failed
            $delete_sql = "
                DELETE FROM prayer_notifications_sent 
                WHERE user_id = ? AND install_id = ? AND prayer = ? AND DATE(sent_at) = ?
            ";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("isss", $sub['user_id'], $sub['install_id'], $prayer_name, $today);
            $delete_stmt->execute();
        } else {
            $sent_count++;
        }
    }
}

// Output result
$output = [
    'status' => 'success',
    'checked' => $checked_count,
    'sent' => $sent_count,
    'timestamp' => date('Y-m-d H:i:s'),
    'timezone' => 'Asia/Kuala_Lumpur'
];

echo json_encode($output, JSON_PRETTY_PRINT);
?>
