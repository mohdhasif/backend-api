<?php
require 'db.php';
$input = json_decode(file_get_contents('php://input'), true);

$task_id = (int)($input['task_id'] ?? 0);
$sender_type = $input['sender_type'] ?? '';
$message = trim($input['message'] ?? '');
$sender_id = isset($input['sender_id']) ? (int)$input['sender_id'] : null;

if ($task_id <= 0 || !in_array($sender_type, ['admin', 'client']) || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO task_notes (task_id, sender_type, sender_id, message) VALUES (?,?,?,?)");
$stmt->bind_param("isis", $task_id, $sender_type, $sender_id, $message);
$stmt->execute();

echo json_encode([
    'success' => true,
    'note' => [
        'id' => $stmt->insert_id,
        'task_id' => $task_id,
        'sender_type' => $sender_type,
        'sender_id' => $sender_id,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ]
]);
