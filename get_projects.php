<?php
// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();
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

    json_ok([
        "data" => $projects
    ]);

} catch (Exception $e) {
    json_error(500, 'Gagal mendapatkan projek: ' . $e->getMessage());
}

// publish