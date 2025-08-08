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
    // $mail->addAddress('yongshean.utopia@gamil.com'); // ✅ Tukar kepada penerima sebenar
    // $mail->addAddress('mohdhasif24181@gmail.com'); // ✅ Tukar kepada penerima sebenar

    // Email content

    // Kandungan email
    $mail->isHTML(true);
    $mail->Subject = 'New Order Received - ' . $orderId;
    $mail->Body    = "
        <h3>New Order Details</h3>
        <p><strong>Customer:</strong> {$data['customerName']}</p>
        <p><strong>Phone:</strong> {$data['phone']}</p>
        <p><strong>Email:</strong> {$data['customerEmail']}</p>
        <p><strong>Service:</strong> {$data['service']}</p>
        <p><strong>Quoted Price:</strong> RM {$data['quotedPrice']}</p>
        <p><strong>Technician:</strong> {$data['assignedTechnician']}</p>
        <p><strong>WhatsApp:</strong> {$data['customerWhatsApp']}</p>
        <p><strong>Address:</strong> {$data['address']}</p>
        <p><strong>Notes:</strong> {$data['adminNotes']}</p>
    ";

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}
