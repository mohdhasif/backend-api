<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

// Logo Upload
$uploadDir = 'uploads/';
$logoUrl = null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $filename = basename($_FILES['logo']['name']);
    $uniqueName = time() . '_' . $filename;
    $targetPath = $uploadDir . $uniqueName;

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
        $logoUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $targetPath;
    }
}

// Data dari form
$company_name = $_POST['company_name'];
$phone = $_POST['phone'];
$status = $_POST['status'];
$client_type = $_POST['client_type'];
$selected_services = $_POST['selected_services'];
$progress = $_POST['progress'] ?? 0;

// user_id dan approved_at kita biar NULL dulu
$stmt = $conn->prepare("
    INSERT INTO clients 
    (user_id, company_name, phone, status, approved_at, progress, logo_url, client_type, selected_services)
    VALUES (NULL, ?, ?, ?, NULL, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssisss",
    $company_name,
    $phone,
    $status,
    $progress,
    $logoUrl,
    $client_type,
    $selected_services
);

    if ($stmt->execute()) {
        json_ok([
            "message" => "Client berjaya ditambah!",
            "client_id" => $conn->insert_id
        ]);
    } else {
        json_error(500, "Gagal tambah client: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    json_error(500, 'Database error: ' . $e->getMessage());
}
