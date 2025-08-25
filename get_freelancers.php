<?php

require_once __DIR__ . '/db.php'; // pastikan include db.php

$base_url = '/uploads/avatars/';
$default_avatar = $base_url . 'avatars.png';

try {

    $sql = "
    SELECT 
        f.id AS freelancer_id,
        u.id AS user_id,
        u.name,
        u.email,
        f.skillset,
        f.availability,
        f.avatar_url,
        f.status
    FROM freelancers f
    INNER JOIN users u ON f.user_id = u.id
    WHERE u.role = 'freelancer'
";

    $result = $conn->query($sql);
    $freelancers = [];

    while ($row = $result->fetch_assoc()) {
        $avatar = $row['avatar_url'] ? $row['avatar_url'] : $default_avatar;

        $freelancers[] = [
            'id' => (int)$row['freelancer_id'],
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'skillset' => $row['skillset'],
            'availability' => (bool)$row['availability'],
            'avatar' => $avatar,
            'status' => $row['status'],
        ];
    }

    echo json_encode($freelancers);
} catch (\Throwable $th) {
    echo json_encode($th->getMessage());
}
