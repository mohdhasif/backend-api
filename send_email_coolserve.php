<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Jika preflight request (OPTIONS), hentikan terus
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Manual include PHPMailer classes
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Terima POST data dari React
$data = json_decode(file_get_contents("php://input"), true);

$orderId = $data['order_id'] ?? '';
$technician = $data['technician'] ?? '';
$remarks = $data['remarks'] ?? '';
$extraCharges = $data['extra_charges'] ?? '';

$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username = 'mohdhasif24181@gmail.com';
    $mail->Password   = 'bejt qgpy gntm vbst'; // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Encryption type
    $mail->Port       = 587; // SMTP port

    // Sender & recipient
    $mail->setFrom('mohdhasif24181@gmail.com', 'CoolServe System');
    $mail->addAddress('yongshean.utopia@gamil.com'); // ✅ Tukar kepada penerima sebenar
    // $mail->addAddress('mohdhasif24181@gmail.com'); // ✅ Tukar kepada penerima sebenar

    // Email content
    $mail->isHTML(true);
    $mail->Subject = "Job Completed: Order ID $orderId";
    $mail->Body = "
        <h3>Technician: $technician</h3>
        <p><strong>Order ID:</strong> $orderId</p>
        <p><strong>Remarks:</strong> $remarks</p>
        <p><strong>Extra Charges:</strong> RM$extraCharges</p>
    ";

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}
