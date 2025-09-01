<?php
// upload_avatar.php
require_once __DIR__ . '/db.php';

try {
    $me  = require_auth($conn);
    $uid = (int)$me['id'];

    if (!isset($_FILES['avatar'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Upload error']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');

    // Optional: validasi MIME ringkas
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
        exit;
    }

    $safeName = 'u' . $uid . '_' . time() . '.' . $ext;
    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $dest = $dir . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to store file']);
        exit;
    }

    // Bina URL public ikut host semasa
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = $scheme . '://' . $host;
    $publicUrl = $base . '/uploads/avatars/' . $safeName;

    // Simpan ke DB
    $stmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
    $stmt->bind_param("si", $publicUrl, $uid);
    $stmt->execute();

    echo json_encode(['success' => true, 'url' => $publicUrl]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

// publish