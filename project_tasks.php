<?php
require_once __DIR__ . '/db.php'; // include helper & $conn

header('Content-Type: application/json; charset=utf-8');

try {
    // --- Auth & role ---
    $user = require_auth($conn);
    $auth_user_id = (int)($user['id'] ?? 0);
    $role = strtolower((string)($user['role'] ?? ''));

    // --- Inputs ---
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $status     = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : null; // optional

    if ($project_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing project_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Build WHERE & params ---
    $where  = ['t.project_id = ?']; // asas: ikut projek
    $params = [$project_id];
    $types  = 'i';

    // Filter status jika valid
    $allowedStatus = ['pending', 'in_progress', 'completed'];
    if ($status && in_array($status, $allowedStatus, true)) {
        $where[]  = 't.status = ?';
        $params[] = $status;
        $types   .= 's';
    }

    /**
     * Access control ikut role
     * admin      : tiada tambahan
     * client     : hanya projek milik client tersebut (clients.user_id = auth_user_id)
     * freelancer : hanya task yang di-assign kepada freelancer ini (freelancers.user_id = auth_user_id)
     */
    if ($role === 'client') {
        // projek mesti milik client ini
        $where[]  = 'c.user_id = ?';
        $params[] = $auth_user_id;
        $types   .= 'i';
    } elseif ($role === 'freelancer') {
        // task mesti assign kepada freelancer ini (match melalui freelancers.user_id)
        $where[]  = 'f.user_id = ?';
        $params[] = $auth_user_id;
        $types   .= 'i';
    } else {
        // default admin / lain-lain: tiada sekatan tambahan
        // Boleh tambah require_role jika nak ketatkan:
        // require_role($user, ['admin']);
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // --- Query utama ---
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
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();

    // Kumpul ikut task
    $tasks = [];
    while ($row = $rs->fetch_assoc()) {
        $tid = (int)$row['task_id'];

        if (!isset($tasks[$tid])) {
            // Tentukan display_name (ikut client_type)
            $client_type   = $row['client_type'] ?? null;
            $display_name  = null;
            $company_name  = $row['client_company_name'] ?? null;
            $client_uname  = $row['client_user_name'] ?? null;

            if ($client_type === 'company') {
                $display_name = $company_name ?: $client_uname;
            } elseif ($client_type === 'individual') {
                $display_name = $client_uname ?: $company_name;
            } else {
                $display_name = $company_name ?: $client_uname;
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
                    'name'         => $client_uname,
                    'client_type'  => $client_type,
                    'company_name' => $company_name,
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

// publish