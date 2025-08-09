<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Database connection
$host = "localhost";
$dbname = "finiteapp";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit();
}

// SQL Query (JOIN tasks → projects → clients → users)
$sql = "
SELECT 
    tasks.id AS task_id,
    tasks.title AS task_title,
    tasks.description AS task_description,
    tasks.status AS task_status,
    tasks.due_date,
    tasks.created_at AS task_created_at,
    tasks.updated_at AS task_updated_at,

    projects.id AS project_id,
    projects.title AS project_title,
    projects.status AS project_status,

    clients.id AS client_id,
    clients.company_name AS client_name,
    clients.status AS client_status,
    clients.client_type,
    clients.logo_url,

    users.name AS client_user_name,
    users.email AS client_user_email

FROM tasks
LEFT JOIN projects ON tasks.project_id = projects.id
LEFT JOIN clients ON projects.client_id = clients.user_id
LEFT JOIN users ON clients.user_id = users.id
ORDER BY tasks.created_at DESC
";

$result = $conn->query($sql);

if ($result) {
    $tasks = [];

    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $tasks
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => $conn->error
    ]);
}

$conn->close();
