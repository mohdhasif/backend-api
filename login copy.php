<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Aktifkan exception

try {
    // Sambung DB
    $conn = new mysqli("127.0.0.1", "root", "", "finiteapp");
    $conn->set_charset("utf8mb4");

    // Dapatkan input
    $input = json_decode(file_get_contents("php://input"), true);
    $email = $input["email"] ?? '';
    $password = $input["password"] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(["error" => "Email and password required"]);
        exit();
    }

    // Fetch user sahaja
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "Email not found"]);
        exit();
    }

    if (md5($password) !== $user['password']) {
        http_response_code(401);
        echo json_encode(["error" => "Incorrect password"]);
        exit();
    }

    // Generate token & update
    $token = md5($user['id'] . time());
    $update = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $update->bind_param("si", $token, $user['id']);
    $update->execute();

    unset($user['password']); // Jangan hantar balik password
    $user['token'] = $token;

    echo json_encode([
        "status" => "success",
        "message" => "Login success",
        "user" => $user,
        "token" => $token
    ]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error", "details" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Unexpected error", "details" => $e->getMessage()]);
}
