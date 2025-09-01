<?php
// get_my_profile.php
// Pastikan laluan ini betul ikut struktur projek anda
require_once __DIR__ . '/db.php';

try {
    // Semak token & dapatkan user semasa
    $me = require_auth($conn);

    echo json_encode([
        'success' => true,
        'data' => [
            'id'         => (int)$me['id'],
            'name'       => $me['name'] ?? null,
            'email'      => $me['email'] ?? null,
            'phone'      => $me['phone'] ?? null,
            'gender'     => $me['gender'] ?? null,
            'dob'        => $me['dob'] ?? null,
            'avatar_url' => $me['avatar_url'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

// publish