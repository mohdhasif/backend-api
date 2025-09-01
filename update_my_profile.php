<?php
// api/update_my_profile.php
require_once __DIR__ . '/db.php';

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();
    
    // Auth by token (db.php already sets CORS + JSON header)
    $me  = require_auth($conn);
    $uid = (int)$me['id'];

    // Sumber data boleh jadi multipart atau JSON
    $isMultipart = !empty($_FILES) || (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false);
    $name   = null;
    $phone  = null;
    $gender = null;
    $dob    = null;
    $avatarUrlIncoming = null; // kalau tidak upload file

    if ($isMultipart) {
        $name   = isset($_POST['name'])   ? trim((string)$_POST['name'])   : null;
        $phone  = isset($_POST['phone'])  ? trim((string)$_POST['phone'])  : null;
        $gender = isset($_POST['gender']) ? strtolower(trim((string)$_POST['gender'])) : null;
        $dob    = isset($_POST['dob'])    ? trim((string)$_POST['dob'])    : null;
        $avatarUrlIncoming = isset($_POST['avatar_url']) ? trim((string)$_POST['avatar_url']) : null;
    } else {
        $body   = read_json_body();
        $name   = array_key_exists('name', $body)       ? trim((string)$body['name'])   : null;
        $phone  = array_key_exists('phone', $body)      ? trim((string)$body['phone'])  : null;
        $gender = array_key_exists('gender', $body)     ? strtolower(trim((string)$body['gender'])) : null;
        $dob    = array_key_exists('dob', $body)        ? trim((string)$body['dob'])    : null;
        $avatarUrlIncoming = array_key_exists('avatar_url', $body) ? trim((string)$body['avatar_url']) : null;
    }

    // Normalisasi nilai kosong
    if ($dob === '')    $dob = null;
    if ($gender === '') $gender = null;
    if ($phone === '')  $phone = null;

    $avatarUrlSaved = null;

    // Jika ada fail avatar, proses (mengikut pattern dari update_freelancer.php)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatar = $_FILES['avatar'];

        $fileName = basename($avatar['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = 'avatar_' . $uid . '_' . time() . '.' . $fileExtension;

        $uploadDir = "uploads/avatars/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $path = $uploadDir . $newFileName;

        if (move_uploaded_file($avatar['tmp_name'], $path)) {
            $avatarUrlSaved = '/' . $path;
        } else {
            json_error(500, 'Failed to move uploaded avatar');
        }
    } elseif ($avatarUrlIncoming) {
        // Tiada fail baru, retain url lama
        $avatarUrlSaved = $avatarUrlIncoming;
    }

    // Build query dinamik
    $fields = [];
    $params = [];
    $types  = '';

    if ($name !== null) {
        $fields[] = "name = ?";
        $params[] = $name;
        $types .= 's';
    }
    // if ($phone !== null) {
    //     $fields[] = "phone = ?";
    //     $params[] = $phone;
    //     $types .= 's';
    // }
    // if ($gender !== null) {
    //     $fields[] = "gender = ?";
    //     $params[] = $gender;
    //     $types .= 's';
    // }
    // if ($dob !== null) {
    //     $fields[] = "dob = ?";
    //     $params[] = $dob;
    //     $types .= 's';
    // }
    if ($avatarUrlSaved !== null) {
        $fields[] = "avatar_url = ?";
        $params[] = $avatarUrlSaved;
        $types .= 's';
    }

    if (!empty($fields)) {
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $types .= 'i';
        $params[] = $uid;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    // Pulangkan data terkini
    // $stmt = $conn->prepare("SELECT id, name, email, phone, gender, dob, avatar_url FROM users WHERE id = ?");
    $stmt = $conn->prepare("SELECT id, name, email, avatar_url FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    json_ok([
        'message' => 'Profile updated', 
        'data' => $row,
        'avatar_url' => $avatarUrlSaved
    ]);
} catch (Throwable $e) {
    json_error(500, $e->getMessage());
}
