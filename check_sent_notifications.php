<?php
require_once 'db.php';

echo "=== Notifications Sent Today ===\n";
$today = date('Y-m-d');
echo "Date: $today\n\n";

// Check the structure of the table first
$sql = "DESCRIBE prayer_notifications_sent";
$res = $conn->query($sql);
echo "Table structure:\n";
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n";

// Check what was sent today
$sql2 = "SELECT * FROM prayer_notifications_sent WHERE DATE(sent_at) = ?";
$stmt = $conn->prepare($sql2);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Notifications sent today:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  " . $row['prayer'] . " at " . $row['sent_at'] . "\n";
    }
} else {
    echo "No notifications sent today yet.\n";
}
?>
