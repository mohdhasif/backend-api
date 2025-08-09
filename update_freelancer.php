<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$uploadDir = "uploads/avatars/";

// DB connection
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Log input untuk debugging
$logFile = __DIR__ . '/update_freelancer.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($_POST, true) . "\n\n";
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
    echo json_encode(['success' => false, 'error' => 'freelancer_id is required']);
    exit;
}

// Dapatkan user_id berdasarkan freelancer_id
$getUserStmt = $conn->prepare("SELECT user_id FROM freelancers WHERE id = ?");
$getUserStmt->bind_param("i", $freelancer_id);
$getUserStmt->execute();
$getUserStmt->bind_result($user_id);
$getUserStmt->fetch();
$getUserStmt->close();

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User not found for this freelancer']);
    exit;
}

// Jika ada avatar baru dihantar sebagai fail
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $avatar = $_FILES['logo'];

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
        $avatar_url = $domain . '/' . $path;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded avatar']);
        exit;
    }
}

try {
    // Update users table (name & email)
    $stmtUser = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmtUser->bind_param("ssi", $name, $email, $user_id);
    $stmtUser->execute();
    $stmtUser->close();

    // Update freelancers table
    $stmtFreelancer = $conn->prepare("
        UPDATE freelancers 
        SET skillset = ?, availability = ?, status = ?, avatar_url = ?
        WHERE id = ?
    ");
    $stmtFreelancer->bind_param("sissi", $skillset, $availability, $status, $avatar_url, $freelancer_id);
    $stmtFreelancer->execute();
    $stmtFreelancer->close();

    echo json_encode(["success" => true, "message" => "Freelancer updated successfully"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
