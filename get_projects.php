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
            projects.id,
            projects.title,
            projects.description,
            projects.status,
            projects.progress,
            users.name AS client_name
        FROM projects
        LEFT JOIN users ON projects.client_id = users.id
        ORDER BY projects.created_at DESC
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
?>
