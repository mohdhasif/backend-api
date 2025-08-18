<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$user = auth_user($conn);              // <-- pusat
$auth_user_id = (int)$user['id'];

$user_id = $auth_user_id;

// --- Ambil projek berdasarkan client_id ---
$stmt = $conn->prepare("
    SELECT id, title, description, status, progress, created_at
    FROM projects
    WHERE client_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = $result->fetch_all(MYSQLI_ASSOC);

// --- Return JSON ---
echo json_encode($projects);
