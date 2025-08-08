<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$uploadDir = "uploads/logo/";

// DB connection
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Log input
$logFile = __DIR__ . '/new_updated_client.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($_POST['client_id'], true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// Get form fields
$client_id = $_POST['client_id'] ?? null;
$company_name = $_POST['company_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$status = $_POST['status'] ?? 'pending';
$client_type = $_POST['client_type'] ?? 'company';

$logo_url = null;
$logo_url = $_POST['logo_url'] ?? null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $logo = $_FILES['logo'];
    $path = $uploadDir . basename($logo['name']);

    $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $domain .= "://" . $_SERVER['HTTP_HOST']; // contoh: https://yourdomain.com

    $relativePath = $uploadDir . basename($logo['name']);

    $logo_url = $domain . '/' . $relativePath;
    if (move_uploaded_file($logo['tmp_name'], $path)) {
        echo json_encode(['success' => true, 'message' => 'Logo uploaded successfully', 'path' => $path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }
}

// Update database
$stmt = $conn->prepare("UPDATE clients SET company_name=?, phone=?, status=?, client_type=?, logo_url=? WHERE id=?");
$stmt->bind_param("sssssi", $company_name, $phone, $status, $client_type, $logo_url, $client_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
