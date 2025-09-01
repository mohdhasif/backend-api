<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

// Avatar Upload
$uploadDir = 'uploads/avatars/';
$avatarUrl = null;

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $filename = basename($_FILES['avatar']['name']);
    $uniqueName = time() . '_' . $filename;
    $targetPath = $uploadDir . $uniqueName;

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
        $avatarUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $targetPath;
    }
}

// Data from form
$name = $_POST['name'];
$email = $_POST['email'];
$skillset = $_POST['skillset'];
$availability = $_POST['availability'];
$role = 'freelancer';
$created_at = date('Y-m-d H:i:s');
$temp_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
$password_hash = md5($temp_password);

// Insert into users
$userStmt = $conn->prepare("
    INSERT INTO users (name, email, password, role, created_at, temp_password) 
    VALUES (?, ?, ?, ?, ?, ?)
");

$userStmt->bind_param("ssssss", $name, $email, $password_hash, $role, $created_at, $temp_password);

    if ($userStmt->execute()) {
        $user_id = $userStmt->insert_id;

        // Insert into freelancers
        $freelancerStmt = $conn->prepare("
            INSERT INTO freelancers (user_id, skillset, availability, avatar_url)
            VALUES (?, ?, ?, ?)
        ");
        $freelancerStmt->bind_param("isis", $user_id, $skillset, $availability, $avatarUrl);

        if ($freelancerStmt->execute()) {
            json_ok([
                "message" => "Freelancer berjaya ditambah!",
                "user_id" => $user_id,
                "freelancer_id" => $freelancerStmt->insert_id,
                "temp_password" => $temp_password
            ]);
        } else {
            json_error(500, "Gagal tambah freelancer: " . $freelancerStmt->error);
        }

        $freelancerStmt->close();
    } else {
        json_error(500, "Gagal tambah pengguna: " . $userStmt->error);
    }

    $userStmt->close();

} catch (Exception $e) {
    json_error(500, 'Database error: ' . $e->getMessage());
}
