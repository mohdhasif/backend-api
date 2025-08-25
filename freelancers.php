<?php
// Manual include PHPMailer classes
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Sambung DB
require_once __DIR__ . '/db.php'; // pastikan file db.php ada (mysqli $conn)

// Request check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'fail', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Vars
$name      = $data['name'] ?? 'N/A';
$email     = $data['email'] ?? 'N/A';
$phone     = $data['phone'] ?? 'N/A';
$portfolio = $data['portfolio'] ?? 'N/A';
$roles     = $data['roles'] ?? [];

$whatsappLink = 'https://wa.me/6' . preg_replace('/[^0-9]/', '', $phone);

// Format roles
$roleText = '';
foreach ($roles as $role) {
    $roleText .= "• $role\n";
}

$response = '';
// Insert ke database
try {

    // Generate password
    $password_raw = bin2hex(random_bytes(4)); // 8 char
    $password_hash = MD5($password_raw);
    $role = "client";
    $created_at = date("Y-m-d H:i:s");

    // --- Insert ke users ---
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, created_at, temp_password) VALUES (?, ?, ?, ?, 'freelancer', ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $password_hash,  $phone, $created_at, $password_raw);

    $stmt->execute();
    $user_id = $stmt->insert_id;
    $stmt->close();

    // --- Insert ke freelancers ---
    $skillset = implode(',', $roles); // simpan roles jadi skillset
    $stmt2 = $conn->prepare("INSERT INTO freelancers (user_id, skillset, avatar_url) VALUES (?, ?, ?)");
    $defaultAvatar = null; // boleh fallback kalau perlu
    $stmt2->bind_param("iss", $user_id, $skillset, $defaultAvatar);
    $stmt2->execute();
    $stmt2->close();

    $response = 'Freelancer saved & email sent';
} catch (Exception $e) {
    $response = 'DB Error: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => 'DB Error: ' . $e->getMessage()]);
    exit;
}

$data['response'] = $response;

// Log ke file
$logFile = __DIR__ . '/freelancers.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($data, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// Email content
$body = <<<EOD
Hi Admin,

Anda terima satu penyertaan Freelancer baharu:

👤 Nama: $name  
📧 Email: $email  
📱 Nombor WhatsApp: $phone  
🔗 Link WhatsApp: $whatsappLink  
🌐 Portfolio: $portfolio

🛠️ Peranan (Roles) Dipilih:
$roleText

Terima kasih.
EOD;

// Setup PHPMailer
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mohdhasif24181@gmail.com';
    $mail->Password   = 'bejt qgpy gntm vbst'; // app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mohdhasif24181@gmail.com', 'Admin Finite');
    $mail->addAddress('mohdhasif24181@gmail.com', 'Mohd Hasif');

    $mail->isHTML(false);
    $mail->Subject = "Freelancer Application Received - $name";
    $mail->Body    = $body;

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Freelancer saved & email sent',
        'user_id' => $user_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'fail',
        'message' => "Mailer Error: {$mail->ErrorInfo}"
    ]);
}
