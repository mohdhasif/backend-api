<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // DB connect
    $conn = new mysqli("127.0.0.1", "root", "", "finiteapp");
    $conn->set_charset("utf8mb4");

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

    // Generate token baru (per-device/per-login)
    // generate_token() ada dalam db.php — kalau fail include, duplicate function simple di sini
    if (!function_exists('generate_token')) {
        function generate_token(): string
        {
            try {
                return bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                return bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
    }
    $token = generate_token();

    // Role rules:
    // - client, freelancer: single-device => revoke/hapus semua token lama sebelum insert baru
    // - admin: multi-device => JANGAN revoke; benarkan tambah token baru
    if ($role === 'client' || $role === 'freelancer') {
        // Revoke semua token lama
        $conn->query("UPDATE user_tokens SET revoked = 1 WHERE user_id = {$userId} AND revoked = 0");
        // (Option: atau DELETE FROM user_tokens WHERE user_id = ?)
    }

    // Insert token baru
    $stmt = $conn->prepare("
        INSERT INTO user_tokens (user_id, token, device_id, user_agent, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isss", $userId, $token, $installId, $userAgent);
    $stmt->execute();
    $stmt->close();

    // Backward compatibility:
    // Untuk endpoint lama yang masih semak users.token:
    // - client/freelancer: update users.token kepada token baru (supaya device lain auto-logout)
    // - admin: JANGAN update users.token (supaya device lama kekal hidup walaupun login dari device baru)
    if ($role === 'client' || $role === 'freelancer') {
        $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
        $stmt->bind_param("si", $token, $userId);
        $stmt->execute();
        $stmt->close();
    }
    // admin: biarkan users.token apa adanya.

    // Response
    unset($user['password']);
    echo json_encode([
        "success" => true,
        "message" => "Login success",
        "user" => $user,
        "token" => $token
    ]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error", "details" => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Unexpected error", "details" => $e->getMessage()]);
}
