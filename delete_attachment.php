<?php
require 'db.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$stmt = $conn->prepare("SELECT file_name FROM task_attachments WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row) {
    $stmtDel = $conn->prepare("DELETE FROM task_attachments WHERE id=?");
    $stmtDel->bind_param("i", $id);
    $stmtDel->execute();

    $path = __DIR__ . '/uploads/attachments/' . $row['file_name'];
    if (is_file($path)) {
        @unlink($path);
    }
}

echo json_encode(['success' => true]);
