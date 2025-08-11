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

try {
    $stmt = $conn->prepare("
    SELECT
    p.id,
    p.title,
    p.description,
    p.status,
    p.progress,
    CASE
        WHEN c.client_type = 'company' THEN c.company_name
        ELSE u.name
    END AS client_name
    FROM projects AS p
    LEFT JOIN clients AS c ON p.client_id = c.id
    LEFT JOIN users   AS u ON c.user_id = u.id
    ORDER BY p.created_at DESC;
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }

    echo json_encode($projects);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Gagal mendapatkan projek",
        "details" => $e->getMessage()
    ]);
}
