<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

// Tunjukkan error mysqli sebagai Exception
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // --- Ambil token & project_id ---
    $headers    = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? '';
    $token      = str_replace('Bearer ', '', $authHeader);

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $status     = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : null; // optional

    if (!$token || $project_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token or project_id']);
        exit;
    }

    // --- DB connect ---
    $conn = new mysqli("localhost", "root", "", "finiteapp");
    $conn->set_charset("utf8mb4");

    // --- Auth by token ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $uidRes = $stmt->get_result();
    if ($uidRes->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $auth_user_id = (int)$uidRes->fetch_assoc()['id'];

    // --- Build WHERE ---
    $where  = ['t.project_id = ?'];
    $params = [$project_id];
    $types  = 'i';

    if ($status && in_array($status, ['pending', 'in_progress', 'completed'], true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
        $types   .= 's';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // --- Query utama (dibersihkan, tiada komen dalam SQL) ---
    $sql = "
        SELECT
            t.id              AS task_id,
            t.project_id      AS t_project_id,
            t.title           AS t_title,
            t.description     AS t_description,
            t.status          AS t_status,
            t.due_date        AS t_due_date,
            t.created_at      AS t_created_at,
            t.updated_at      AS t_updated_at,

            p.id              AS p_id,
            p.title           AS p_title,

            cu.id             AS client_user_id,
            cu.name           AS client_user_name,

            c.client_type     AS client_type,
            c.company_name    AS client_company_name,
            c.logo_url        AS client_logo_url,

            ta.freelancer_id  AS ta_freelancer_id,
            f.id              AS f_id,
            f.user_id         AS f_user_id,
            f.avatar_url      AS f_avatar,
            fu.name           AS f_name,
            fu.email          AS f_email

        FROM tasks t
        LEFT JOIN projects       p  ON p.id = t.project_id
        LEFT JOIN clients        c  ON c.id = p.client_id
        LEFT JOIN users          cu ON cu.id = c.user_id
        LEFT JOIN task_assignees ta ON ta.task_id = t.id
        LEFT JOIN freelancers    f  ON f.id = ta.freelancer_id
        LEFT JOIN users          fu ON fu.id = f.user_id
        $whereSql
        ORDER BY t.created_at DESC, t.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();

    // Kumpul ikut task
    $tasks = [];
    while ($row = $rs->fetch_assoc()) {
        $tid = (int)$row['task_id'];

        if (!isset($tasks[$tid])) {

            $client_type = $row['client_type'] ?? null;
            $display_name = null;
            if ($client_type === 'company') {
                $display_name = $row['client_company_name'] ?: ($row['client_user_name'] ?? null);
            } elseif ($client_type === 'individual') {
                $display_name = $row['client_user_name'] ?: ($row['client_company_name'] ?? null);
            } else {
                $display_name = $row['client_company_name'] ?: ($row['client_user_name'] ?? null);
            }


            $tasks[$tid] = [
                'id'          => $tid,
                'project_id'  => (int)$row['t_project_id'],
                'title'       => $row['t_title'],
                'description' => $row['t_description'],
                'status'      => $row['t_status'],
                'due_date'    => $row['t_due_date'],
                'created_at'  => $row['t_created_at'],
                'updated_at'  => $row['t_updated_at'],

                'project'     => [
                    'id'    => isset($row['p_id']) ? (int)$row['p_id'] : null,
                    'title' => $row['p_title'] ?? null,
                ],
                'client'      => [
                    'user_id'      => isset($row['client_user_id']) ? (int)$row['client_user_id'] : null,
                    'display_name' => $display_name,
                    'name'         => $row['client_user_name'] ?? null,
                    'client_type'  => $row['client_type'] ?? null,
                    'company_name' => $row['client_company_name'] ?? null,
                    'logo_url'     => $row['client_logo_url'] ?? null,
                ],
                'freelancers' => [],
            ];
        }

        // Tambah freelancers jika ada
        if (!empty($row['ta_freelancer_id']) || !empty($row['f_id'])) {
            $tasks[$tid]['freelancers'][] = [
                'id'      => isset($row['f_id']) ? (int)$row['f_id'] : null,
                'user_id' => isset($row['f_user_id']) ? (int)$row['f_user_id'] : null,
                'name'    => $row['f_name'] ?? null,
                'email'   => $row['f_email'] ?? null,
                'avatar'  => $row['f_avatar'] ?? null,
            ];
        }
    }

    // Dedupe freelancers per task
    $data = array_values(array_map(function ($t) {
        if (!empty($t['freelancers'])) {
            $seen = [];
            $uniq = [];
            foreach ($t['freelancers'] as $fr) {
                $key = ($fr['id'] ?? '0') . '-' . ($fr['user_id'] ?? '0');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniq[] = $fr;
                }
            }
            $t['freelancers'] = $uniq;
        }
        return $t;
    }, $tasks));

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
