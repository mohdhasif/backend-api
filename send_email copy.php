<?php
// Manual include PHPMailer classes
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
header('Content-Type: application/json');


// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'fail', 'message' => 'Method not allowed']);
    exit;
}
// Ambil data dari body request
$data = json_decode(file_get_contents('php://input'), true);

// Path ke file log
$logFile = __DIR__ . '/discovery.log';

// Kandungan untuk log
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($data, true) . "\n\n";

// Simpan ke file
file_put_contents($logFile, $logContent, FILE_APPEND);

// Optional: response ke client
echo json_encode([
    'status' => 'success',
    'message' => 'Data received and logged.'
]);

echo json_encode($data);

$mail = new PHPMailer(true);

// try {
//     // Server settings
//     $mail->isSMTP();
//     $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
//     $mail->SMTPAuth   = true;
//     $mail->Username   = 'mohdhasif24181@gmail.com'; // SMTP username
//     $mail->Password   = 'bejt qgpy gntm vbst'; // SMTP password
//     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Encryption type
//     $mail->Port       = 587; // SMTP port

//     // Recipients
//     $mail->setFrom('mohdhasif24181@gmail.com', 'Personal');
//     $mail->addAddress($email, $fullName);

//     // Content
//     $mail->isHTML(true);
//     $mail->Subject = 'Email Confirmation';
//     $mail->Body    = "Testing";

//     $mail->AltBody = "This is the body in plain text for non-HTML mail clients";

//     $mail->send();

//     echo json_encode(['status' => 'success', 'message' => 'Email has been sent']);
// } catch (Exception $e) {
//     http_response_code(500);
//     echo json_encode(['status' => 'fail', 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
// }
