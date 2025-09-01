<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

$uploadDir = "uploads/logo/";

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

// Log input
$logFile = __DIR__ . '/new_updated_client.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($_POST, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// Get form fields
$client_id = $_POST['client_id'] ?? null;
$company_name = $_POST['company_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$status = $_POST['status'] ?? 'pending';
$client_type = $_POST['client_type'] ?? 'company';

$avatar_url = $_POST['logo'] ?? null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $avatar = $_FILES['logo'];

    $fileName = basename($avatar['name']);
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = 'logo_' . $client_id . '_' . time() . '.' . $fileExtension;

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $path = $uploadDir . $newFileName;

    if (move_uploaded_file($avatar['tmp_name'], $path)) {
        $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $domain .= "://" . $_SERVER['HTTP_HOST'];
        // $avatar_url = $domain . '/' . $path;
        $avatar_url = '/' . $path;
    } else {
        json_error(500, 'Failed to move uploaded avatar');
    }
}

    // Update database with transaction
    // Start transaction
    $conn->begin_transaction();

    // Update clients table (without logo_url)
    $stmt = $conn->prepare("UPDATE clients SET company_name=?, phone=?, status=?, client_type=? WHERE id=?");
    $stmt->bind_param("ssssi", $company_name, $phone, $status, $client_type, $client_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update clients table: " . $stmt->error);
    }

    // Update users table with logo_url as avatar_url (only if logo_url is provided)
    if ($avatar_url) {
        $stmt2 = $conn->prepare("UPDATE users SET avatar_url=? WHERE id=(SELECT user_id FROM clients WHERE id=?)");
        $stmt2->bind_param("si", $avatar_url, $client_id);

        if (!$stmt2->execute()) {
            throw new Exception("Failed to update users table: " . $stmt2->error);
        }

        $stmt2->close();
    }

    // Commit transaction
    $conn->commit();

    json_ok([
        "message" => "Client updated successfully",
        "logo_url" => $avatar_url
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    json_error(500, $e->getMessage());
}
