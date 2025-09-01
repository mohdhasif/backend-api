<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

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
        users.avatar_url,
        clients.progress
    FROM clients
    JOIN users ON clients.user_id = users.id
    WHERE users.role = 'client'
    ORDER BY users.created_at DESC
";

    $result = $conn->query($sql);

    if ($result) {
        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }

        json_ok([
            "data" => $clients
        ]);
    } else {
        json_error(500, $conn->error);
    }

} catch (Exception $e) {
    json_error(500, 'Database error: ' . $e->getMessage());
}

// publish