<?php
/**
 * One-off script: Create a user
 * Usage: php create_user.php
 * Or via Docker: docker compose exec app php create_user.php
 */
require_once __DIR__ . '/db.php';

$email = 'mohdhassif24181@gmail.com';
$password = 'test123';
$name = 'Mohd Hassif';
$role = 'admin'; // admin = no need clients/freelancers entry

$conn = get_db_connection();
$password_hash = md5($password);
$created_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $password_hash, $role, $created_at);

try {
    $stmt->execute();
    $user_id = $stmt->insert_id;
    echo "User created successfully!\n";
    echo "ID: $user_id\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Role: $role\n";
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // Duplicate entry
        echo "User with email $email already exists.\n";
    } else {
        throw $e;
    }
}
$stmt->close();
