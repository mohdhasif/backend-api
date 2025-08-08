<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$uploadDir = "uploads/sample_logo/";

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (!isset($_FILES['logo'])) {
    echo json_encode(['success' => false, 'error' => 'No logo uploaded']);
    exit;
}

$logo = $_FILES['logo'];
$targetFile = $uploadDir . basename($logo['name']);
$fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

// Validate image
$allowedTypes = ['jpg', 'jpeg', 'png'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, JPEG, PNG allowed']);
    exit;
}

if (move_uploaded_file($logo['tmp_name'], $targetFile)) {
    echo json_encode(['success' => true, 'message' => 'Logo uploaded successfully', 'path' => $targetFile]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
