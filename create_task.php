<?php
require_once __DIR__ . '/db.php';
// require_once __DIR__ . '/notifications_helper.php';
require_once __DIR__ . '/push_helper.php';


$user = require_auth($conn);

$input = json_decode(file_get_contents('php://input'), true);

$title       = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$status      = trim($input['status'] ?? 'pending');
$due_date    = trim($input['due_date'] ?? '');               // 'YYYY-MM-DD' (optional, kekalkan)
$start_at    = trim($input['start_at'] ?? '');               // 'YYYY-MM-DD HH:MM:SS'
$end_at      = trim($input['end_at'] ?? '');                 // 'YYYY-MM-DD HH:MM:SS'
$project_id  = (int)($input['project_id'] ?? 0);

if (!$title || $project_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sila isi Title dan pilih Project']);
    exit;
}

$allowed_status = ['pending', 'in_progress', 'completed'];
if (!in_array($status, $allowed_status, true)) $status = 'pending';

// Validasi start/end (jika diberi)
if ($start_at && $end_at) {
    if (strtotime($end_at) < strtotime($start_at)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'End time mesti selepas Start time']);
        exit;
    }
}

try {
    // Pastikan project wujud & ambil client_id
    $stmt = $conn->prepare("SELECT client_id FROM projects WHERE id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Project tidak ditemui']);
        exit;
    }

    $stmt2 = $conn->prepare("
    INSERT INTO tasks
      (project_id, title, description, status, due_date, start_at, end_at, created_at, updated_at)
    VALUES
      (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NOW(), NOW())
  ");
    $stmt2->bind_param(
        "issssss",
        $project_id,
        $title,
        $description,
        $status,
        $due_date,
        $start_at,
        $end_at
    );
    $stmt2->execute();

    $lastId = $conn->insert_id; // integer, ID auto_increment terakhir

    // Get admin IDs
    $adminIds = [];
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $adminIds[] = (int)$row['id'];
    }
    $stmt->close();

    // Get client ID (the project owner)
    $clientUserId = null;
    $stmt = $conn->prepare("SELECT c.user_id FROM clients c 
                           INNER JOIN projects p ON c.id = p.client_id 
                           WHERE p.id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $clientUserId = (int)$row['user_id'];
    }
    $stmt->close();

    // Prepare notification payload
    $notifTitle = "Task created";
    $notifBody  = "Task \"$title\" has been created (ID #$lastId).";
    $data = [
        'type' => 'task_created',
        'task_id' => $lastId,
        'project_id' => $project_id
    ];

    // Send notifications to admins AND the client
    $allUserIds = $adminIds;
    if ($clientUserId && !in_array($clientUserId, $allUserIds)) {
        $allUserIds[] = $clientUserId;
    }

    $result = notify_users($conn, $allUserIds, $notifTitle, $notifBody, $data, 'task_created');

    echo json_encode(['success' => true, 'message' => 'Task berjaya ditambah', 'task_id' => $stmt2->insert_id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal menambah task']);
}
