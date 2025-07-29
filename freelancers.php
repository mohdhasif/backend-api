<?php

// Manual include PHPMailer classes
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json'); // ✅ PENTING!

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'fail', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Log ke file
$logFile = __DIR__ . '/freelancers.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($data, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// ✅ Response dalam JSON
// echo json_encode([
//     'status' => 'success',
//     'message' => 'Data received and logged.'
// ]);

$name = $data['name'] ?? 'N/A';
$email = $data['email'] ?? 'N/A';
$phone = $data['phone'] ?? 'N/A';
$portfolio = $data['portfolio'] ?? 'N/A';
$roles = $data['roles'] ?? [];

$whatsappLink = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);

// Formatkan senarai roles
$roleText = '';
foreach ($roles as $role) {
    $roleText .= "• $role\n";
}

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
    // // Config SMTP (Gmail example)
    // $mail->isSMTP();
    // $mail->Host = 'smtp.gmail.com';
    // $mail->SMTPAuth = true;
    // $mail->Username = 'yourgmail@gmail.com'; // Tukar
    // $mail->Password = 'your_app_password';   // App password, BUKAN password biasa
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    // $mail->Port = 465;

    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username = 'mohdhasif24181@gmail.com';
    $mail->Password   = 'bejt qgpy gntm vbst'; // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Encryption type
    $mail->Port       = 587; // SMTP port

    // Email info
    $mail->setFrom('mohdhasif24181@gmail.com', 'SS Discovery'); // ✅ buang satu '@'
    // $mail->addAddress($email, $name); // hantar ke pengguna  
    $mail->addAddress('marketing.finite@gmail.com', 'Finite Marketing'); // hantar ke pengguna
    // $mail->addAddress('mohdhasif24181@gmail.com', 'Mohd Hasif'); // hantar ke pengguna
    $mail->addReplyTo('mohdhasif24181@gmail.com');

    // Content
    $mail->isHTML(false);
    $mail->Subject = "Freelancer Application Received - $name";
    $mail->Body    = $body;

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'Email sent using PHPMailer']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'fail',
        'message' => "Mailer Error: {$mail->ErrorInfo}"
    ]);
}
