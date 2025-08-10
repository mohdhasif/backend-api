<?php
require 'db.php';

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($task_id <= 0 || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing task_id or file']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/attachments/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload error ' . $f['error']]);
    exit;
}

$ext = pathinfo($f['name'], PATHINFO_EXTENSION);
$basename = pathinfo($f['name'], PATHINFO_FILENAME);
$slug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $basename);
$finalName = $slug . '_' . date('YmdHis') . '.' . $ext;
$destPath = $uploadDir . $finalName;

if (!move_uploaded_file($f['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to move file']);
    exit;
}

$fileUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
    . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
    . '/uploads/attachments/' . $finalName;

$stmt = $conn->prepare("INSERT INTO task_attachments (task_id, file_name, file_url, mime_type, size_bytes) VALUES (?,?,?,?,?)");
$stmt->bind_param("isssi", $task_id, $finalName, $fileUrl, $f['type'], $f['size']);
$stmt->execute();

echo json_encode([
    'success' => true,
    'attachment' => [
        'id' => $stmt->insert_id,
        'task_id' => $task_id,
        'file_name' => $finalName,
        'file_url' => $fileUrl,
    ]
]);
