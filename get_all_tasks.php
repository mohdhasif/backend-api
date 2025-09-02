<?php
// Include db.php for helper functions
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();

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

        json_ok([
            "data" => $tasks
        ]);
    } else {
        json_error(500, $conn->error);
    }

} catch (Exception $e) {
    json_error(500, 'Database error: ' . $e->getMessage());
}
