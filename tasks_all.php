<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// 1) Make mysqli throw exceptions so we see real errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // --- Token sahaja wajib ---
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token']);
        exit;
    }

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

    // --- Filters (optional) ---
    $status     = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : null; // pending|in_progress|completed
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $simple     = isset($_GET['simple']) ? (int)$_GET['simple'] : 0; // 1 = simple mode (no joins)

    $where = [];
    $params = [];
    $types  = '';

    if ($status && in_array($status, ['pending','in_progress','completed'], true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
        $types   .= 's';
    }
    if (!empty($project_id)) {
        $where[] = 't.project_id = ?';
        $params[] = $project_id;
        $types   .= 'i';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    if ($simple === 1) {
        // 2) SIMPLE MODE: query tasks table only to rule out JOIN issues
        $sql = "
            SELECT
              t.id AS task_id, t.project_id AS t_project_id, t.title AS t_title,
              t.description AS t_description, t.status AS t_status, t.due_date AS t_due_date,
              t.created_at AS t_created_at, t.updated_at AS t_updated_at
            FROM tasks t
            $whereSql
            ORDER BY t.created_at DESC, t.id DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rs = $stmt->get_result();

        $out = [];
        while ($row = $rs->fetch_assoc()) {
            $out[] = [
                'id'          => (int)$row['task_id'],
                'project_id'  => isset($row['t_project_id']) ? (int)$row['t_project_id'] : null,
                'title'       => $row['t_title'],
                'description' => $row['t_description'],
                'status'      => $row['t_status'],
                'due_date'    => $row['t_due_date'],
                'created_at'  => $row['t_created_at'],
                'updated_at'  => $row['t_updated_at'],
                'project'     => null,
                'client'      => null,
                'freelancers' => [],
            ];
        }
        echo json_encode(['success' => true, 'data' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // FULL query (with joins)
    $sql = "
        SELECT
            t.id               AS task_id,
            t.project_id       AS t_project_id,
            t.title            AS t_title,
            t.description      AS t_description,
            t.status           AS t_status,
            t.due_date         AS t_due_date,
            t.created_at       AS t_created_at,
            t.updated_at       AS t_updated_at,

            p.id               AS p_id,
            p.title            AS p_title,

            cu.id              AS client_user_id,
            cu.name            AS client_user_name,
            c.client_type      AS client_type,
            c.company_name     AS client_company_name,
            c.logo_url         AS client_logo_url,

            ta.freelancer_id   AS ta_freelancer_id,
            f.id               AS f_id,
            f.user_id          AS f_user_id,
            f.avatar_url           AS f_avatar,
            fu.name            AS f_name,
            fu.email           AS f_email

        FROM tasks t
        LEFT JOIN projects p        ON p.id = t.project_id
        LEFT JOIN users cu          ON cu.id = p.client_id        -- NOTE: pastikan p.client_id = users.id
        LEFT JOIN clients c         ON c.user_id = p.client_id    -- NOTE: pastikan clients.user_id wujud
        LEFT JOIN task_assignees ta ON ta.task_id = t.id
        LEFT JOIN freelancers f     ON f.id = ta.freelancer_id
        LEFT JOIN users fu          ON fu.id = f.user_id
        $whereSql
        ORDER BY t.created_at DESC, t.id DESC
    ";

    $stmt = $conn->prepare($sql); // if any column/table missing, this will now throw with details
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();

    $tasks = [];
    while ($row = $rs->fetch_assoc()) {
        $tid = (int)$row['task_id'];

        if (!isset($tasks[$tid])) {
            $client_type = $row['client_type'] ?? null;
            $display_name = $client_type === 'company'
                ? ($row['client_company_name'] ?? $row['client_user_name'])
                : ($client_type === 'individual'
                    ? ($row['client_user_name'] ?? $row['client_company_name'])
                    : ($row['client_company_name'] ?: $row['client_user_name']));

            $tasks[$tid] = [
                'id'          => $tid,
                'project_id'  => isset($row['t_project_id']) ? (int)$row['t_project_id'] : null,
                'title'       => $row['t_title'],
                'description' => $row['t_description'],
                'status'      => $row['t_status'],
                'due_date'    => $row['t_due_date'],
                'created_at'  => $row['t_created_at'],
                'updated_at'  => $row['t_updated_at'],
                'project'     => $row['p_id'] ? [
                    'id'    => (int)$row['p_id'],
                    'title' => $row['p_title'],
                ] : null,
                'client'      => $row['client_user_id'] ? [
                    'user_id'      => (int)$row['client_user_id'],
                    'display_name' => $display_name,
                    'client_type'  => $client_type,
                    'company_name' => $row['client_company_name'],
                    'logo_url'     => $row['client_logo_url'],
                ] : null,
                'freelancers' => [],
            ];
        }

        if (!empty($row['f_id'])) {
            $tasks[$tid]['freelancers'][] = [
                'freelancer_id' => (int)$row['f_id'],
                'user_id'       => (int)$row['f_user_id'],
                'name'          => $row['f_name'],
                'email'         => $row['f_email'],
                'avatar'        => $row['f_avatar'] ?? null,
            ];
        }
    }

    // dedupe assignees
    $data = array_values(array_map(function ($t) {
        if (!empty($t['freelancers'])) {
            $uniq = [];
            $seen = [];
            foreach ($t['freelancers'] as $f) {
                if (!isset($seen[$f['freelancer_id']])) {
                    $seen[$f['freelancer_id']] = true;
                    $uniq[] = $f;
                }
            }
            $t['freelancers'] = $uniq;
        }
        return $t;
    }, $tasks));

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // 3) Put the *real* message into "error" so frontend sees it directly
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),  // promote details
    ], JSON_UNESCAPED_UNICODE);
}
