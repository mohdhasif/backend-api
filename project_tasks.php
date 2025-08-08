<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// --- Dapatkan token dari header ---
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token']);
    exit;
}

// --- Connect ke DB ---
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Sahkan token dan ambil user ---
$stmt = $conn->prepare("SELECT id, role FROM users WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$role = $user['role'];

// --- Hanya admin dibenarkan akses senarai client ---
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Admins only']);
    exit;
}

// --- Fetch semua client ---
$sql = "
    SELECT 
        clients.id AS client_id,
        users.name,
        users.email,
        clients.company_name,
        clients.phone,
        users.status
    FROM clients
    JOIN users ON clients.user_id = users.id
    ORDER BY users.created_at DESC
";
$result = $conn->query($sql);

$clients = [];
while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

echo json_encode($clients);
$conn->close();
