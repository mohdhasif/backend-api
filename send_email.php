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
$logFile = __DIR__ . '/discovery.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($data, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// ✅ Response dalam JSON
// echo json_encode([
//     'status' => 'success',
//     'message' => 'Data received and logged.'
// ]);

// Senarai servis
$serviceLookup = [
    '1' => 'Social media – Content Management',
    '2' => 'Advertising – Digital Ads & Billboards',
    '3' => 'Photography – Get your daily postings',
    '4' => 'Social media – Get your daily postings',
    '5' => 'Photography – Get your daily postings',
    '6' => 'Social media – Get your daily postings',
];

// Extract data
$type = $data['clientType'] ?? 'N/A';
$name = $data['name'] ?? 'N/A';
$email = $data['email'] ?? 'N/A';
$phone = $data['phone'] ?? 'N/A';
$message = $data['message'] ?? '';
$selectedServices = $data['selectedServices'] ?? [];

// WhatsApp Link
$whatsappLink = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);

// Format service list
$chosenServicesText = '';
foreach ($selectedServices as $id) {
    $chosenServicesText .= '• ' . ($serviceLookup[$id] ?? "Unknown Service ($id)") . "\n";
}

// Email content
$body = <<<EOD
Hi Admin,

Anda terima satu Discovery Call form dari pengguna:

🔹 Type: $type  
🔹 Nama: $name  
🔹 Email: $email  
🔹 WhatsApp: $phone  
🔹 Link WhatsApp: $whatsappLink

📝 Mesej:
$message

✅ Servis Dipilih:
$chosenServicesText

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
    $mail->addReplyTo('mohdhasif24181@gmail.com');

    // Content
    $mail->isHTML(false);
    $mail->Subject = "Discovery Call - $name";
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
