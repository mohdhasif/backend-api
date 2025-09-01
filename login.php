<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

    // Input
    $input = json_decode(file_get_contents("php://input"), true);
    $email = trim($input["email"] ?? '');
    $password = (string)($input["password"] ?? '');
    $installId = $input["install_id"] ?? null;     // optional: device id dari app
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Email/password required"]);
        exit;
    }

    // Cari user
    $stmt = $conn->prepare("SELECT id, name, email, password, role, avatar_url FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid credentials"]);
        exit;
    }

    // Verify password
    $hashed = (string)$user['password'];
    $ok = false;

    // Sokong password_hash() dan juga MD5 legacy
    if (preg_match('/^\$2y\$/', $hashed) || preg_match('/^\$argon2.*/', $hashed)) {
        $ok = password_verify($password, $hashed);
    } else {
        // fallback md5
        $ok = (strtolower($hashed) === md5($password));
    }

    if (!$ok) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid credentials"]);
        exit;
    }

    $userId = (int)$user['id'];
    $role = $user['role'];

    // Generate token baru menggunakan fungsi dari db.php
    $token = generate_token();

    // Gunakan fungsi issue_token dari db.php untuk handle token management
    $token = issue_token($conn, $userId, $role, $installId, $userAgent);

    // Response
    unset($user['password']);
    json_ok([
        "message" => "Login success",
        "user" => $user,
        "token" => $token
    ]);
} catch (mysqli_sql_exception $e) {
    json_error(500, $e->getMessage(), ["details" => $e->getMessage()]);
} catch (Throwable $e) {
    json_error(500, "Unexpected error", ["details" => $e->getMessage()]);
}

// publish