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

$logFile = __DIR__ . '/new_uploaded_client_logo.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($_FILES, true) . "\n\n";
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

    // $logo_url = $domain . '/' . $relativePath;
    $logo_url = '/' . $relativePath;
    if (move_uploaded_file($logo['tmp_name'], $path)) {
        // echo json_encode(['success' => true, 'message' => 'Logo uploaded successfully', 'path' => $path]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }
}

// Update database with transaction
try {
    // Start transaction
    $conn->begin_transaction();
    
    // Update clients table (without logo_url)
    $stmt = $conn->prepare("UPDATE clients SET company_name=?, phone=?, status=?, client_type=? WHERE id=?");
    $stmt->bind_param("ssssi", $company_name, $phone, $status, $client_type, $client_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update clients table: " . $stmt->error);
    }
    
    // Update users table with logo_url as avatar_url (only if logo_url is provided)
    if ($logo_url) {
        $stmt2 = $conn->prepare("UPDATE users SET avatar_url=? WHERE id=(SELECT user_id FROM clients WHERE id=?)");
        $stmt2->bind_param("si", $logo_url, $client_id);
        
        if (!$stmt2->execute()) {
            throw new Exception("Failed to update users table: " . $stmt2->error);
        }
        
        $stmt2->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(["success" => true, "message" => "Client updated successfully"]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$stmt->close();
$conn->close();
