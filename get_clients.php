<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Sambung ke DB
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Fetch all clients with user status
$sql = "
    SELECT 
        clients.id AS client_id,
        users.name,
        users.email,
        clients.company_name,
        clients.phone,
        clients.status AS client_status,
        clients.client_type,
        clients.selected_services,
        clients.approved_at,
        clients.logo_url,
        clients.progress
    FROM clients
    JOIN users ON clients.user_id = users.id
    WHERE users.role = 'client'
    ORDER BY users.created_at DESC
";

$result = $conn->query($sql);

$clients = [];
while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

echo json_encode($clients);
$conn->close();
?>
