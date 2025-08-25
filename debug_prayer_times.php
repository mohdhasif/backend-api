<?php
require_once 'db.php';

echo "=== Prayer Time Debug ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "Current timestamp: " . time() . "\n\n";

// Get all subscriptions with their settings
$sql = "
SELECT 
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
  ) AS enabled
FROM user_push_subscriptions ups
WHERE ups.subscription_id IS NOT NULL
";

$res = $conn->query($sql);
$subs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

foreach ($subs as $r) {
  echo "=== Subscription: " . $r['subscription_id'] . " ===\n";
  echo "User ID: " . ($r['user_id'] ?? 'NULL') . "\n";
  echo "Method: " . $r['method'] . "\n";
  echo "Latitude: " . ($r['latitude'] ?? 'NULL') . "\n";
  echo "Longitude: " . ($r['longitude'] ?? 'NULL') . "\n";
  echo "Enabled: " . $r['enabled'] . "\n";
  
  if ($r['latitude'] && $r['longitude']) {
    // Get prayer times for this location
    $cache = sprintf('%s/gps_%s_%0.4f_%0.4f.json', __DIR__ . '/cache_prayer', date('Y-m'), (float)$r['latitude'], (float)$r['longitude']);
    
    if (is_file($cache)) {
      $data = json_decode(file_get_contents($cache), true);
      if ($data && !empty($data['prayers'])) {
        $day = (int)date('j');
        foreach ($data['prayers'] as $row) {
          if ((int)$row['day'] === $day) {
            echo "Today's Prayer Times:\n";
            echo "  Fajr: " . date('H:i', $row['fajr']) . " (" . $row['fajr'] . ")\n";
            echo "  Dhuhr: " . date('H:i', $row['dhuhr']) . " (" . $row['dhuhr'] . ")\n";
            echo "  Asr: " . date('H:i', $row['asr']) . " (" . $row['asr'] . ")\n";
            echo "  Maghrib: " . date('H:i', $row['maghrib']) . " (" . $row['maghrib'] . ")\n";
            echo "  Isha: " . date('H:i', $row['isha']) . " (" . $row['isha'] . ")\n";
            
            $now = time();
            echo "Current time: " . date('H:i', $now) . " (" . $now . ")\n";
            
            // Check if any prayer time matches current minute
            $prayers = [
              'Fajr' => $row['fajr'],
              'Dhuhr' => $row['dhuhr'],
              'Asr' => $row['asr'],
              'Maghrib' => $row['maghrib'],
              'Isha' => $row['isha']
            ];
            
            foreach ($prayers as $name => $time) {
              if (intdiv($now, 60) === intdiv($time, 60)) {
                echo "  ✅ MATCH: $name prayer time!\n";
              } else {
                echo "  ❌ No match: $name (" . date('H:i', $time) . ")\n";
              }
            }
            break;
          }
        }
      }
    } else {
      echo "❌ No cache file found: $cache\n";
    }
  } else {
    echo "❌ No coordinates set\n";
  }
  echo "\n";
}

// Check if notifications were already sent today
echo "=== Notifications Sent Today ===\n";
$today = date('Y-m-d');
$sql2 = "SELECT subscription_id, prayer, sent_at FROM prayer_notifications_sent WHERE DATE(sent_at) = ?";
$stmt = $conn->prepare($sql2);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo "Sent: " . $row['subscription_id'] . " - " . $row['prayer'] . " at " . $row['sent_at'] . "\n";
  }
} else {
  echo "No notifications sent today yet.\n";
}
?>
