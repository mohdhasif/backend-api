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
$skillset = $_POST['skillset'] ?? '';
$name = $_POST['name'] ?? '';
$availability = isset($_POST['availability']) ? (int)$_POST['availability'] : 1;
$status = $_POST['status'] ?? 'pending';
$avatar_url = $_POST['avatar_url'] ?? null; // fallback jika tiada fail dihantar

// Jika ada avatar baru yang dihantar
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
        // Generate full URL for frontend
        $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $domain .= "://" . $_SERVER['HTTP_HOST'];
        $avatar_url = $domain . '/' . $path;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded avatar']);
        exit;
    }
}

// Kemas kini freelancer
$stmt = $conn->prepare("
    UPDATE freelancers 
    SET name = ?, skillset = ?, availability = ?, status = ?, avatar_url = ?
    WHERE id = ?
");
$stmt->bind_param("sissi", $name, $skillset, $availability, $status, $avatar_url, $freelancer_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Freelancer updated successfully"]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
