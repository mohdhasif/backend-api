<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

$uploadDir = "uploads/avatars/";

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

// Log input untuk debugging
$logFile = __DIR__ . '/update_freelancer.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($_POST, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

$logContent = date('Y-m-d H:i:s') . " - Received Files:\n" . print_r($_FILES, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// Ambil data dari form
$freelancer_id = $_POST['freelancer_id'] ?? null;
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$skillset = $_POST['skillset'] ?? '';
$availability = isset($_POST['availability']) ? (int)$_POST['availability'] : 1;
$status = $_POST['status'] ?? 'pending';
$avatar_url = $_POST['avatar'] ?? null;

    if (!$freelancer_id) {
        json_error(400, 'freelancer_id is required');
    }

// Dapatkan user_id berdasarkan freelancer_id
$getUserStmt = $conn->prepare("SELECT user_id FROM freelancers WHERE id = ?");
$getUserStmt->bind_param("i", $freelancer_id);
$getUserStmt->execute();
$getUserStmt->bind_result($user_id);
$getUserStmt->fetch();
$getUserStmt->close();

    if (!$user_id) {
        json_error(404, 'User not found for this freelancer');
    }

// Jika ada avatar baru dihantar sebagai fail
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $avatar = $_FILES['avatar'];

    $fileName = basename($avatar['name']);
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = 'avatar_' . $freelancer_id . '_' . time() . '.' . $fileExtension;

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

    // Start transaction
    $conn->begin_transaction();
    
    // Update users table (name, email, and avatar_url)
    $stmtUser = $conn->prepare("UPDATE users SET name = ?, email = ?, avatar_url = ? WHERE id = ?");
    $stmtUser->bind_param("sssi", $name, $email, $avatar_url, $user_id);
    
    if (!$stmtUser->execute()) {
        throw new Exception("Failed to update users table: " . $stmtUser->error);
    }
    $stmtUser->close();

    // Update freelancers table (without avatar_url)
    $stmtFreelancer = $conn->prepare("
        UPDATE freelancers 
        SET skillset = ?, availability = ?, status = ?
        WHERE id = ?
    ");
    $stmtFreelancer->bind_param("sisi", $skillset, $availability, $status, $freelancer_id);
    
    if (!$stmtFreelancer->execute()) {
        throw new Exception("Failed to update freelancers table: " . $stmtFreelancer->error);
    }
    $stmtFreelancer->close();

    // Commit transaction
    $conn->commit();
    
    json_ok([
        "message" => "Freelancer updated successfully",
        "avatar_url" => $avatar_url
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    json_error(500, $e->getMessage());
}