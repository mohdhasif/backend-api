<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

try {
  // Dapatkan koneksi database dari db.php
  $conn = get_db_connection();

  // Read input
  $input = json_decode(file_get_contents("php://input"), true);

  if (!isset($input['client_id'])) {
    json_error(400, 'Client ID is required');
  }

  $client_id = (int) $input['client_id'];

  // Get client & user info
  $stmt = $conn->prepare("
    SELECT c.id AS client_id, c.status, u.name, u.email, u.temp_password 
    FROM clients c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.id = ?
  ");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $data = $res->fetch_assoc();
  $stmt->close();

  if (!$data) {
    json_error(404, 'Client not found');
  }

  if ($data['status'] === 'active') {
    json_error(400, 'Client already approved');
  }

  // Update client status
  $updateStmt = $conn->prepare("UPDATE clients SET status = 'active', approved_at = NOW() WHERE id = ?");
  $updateStmt->bind_param("i", $client_id);
  $updateStmt->execute();
  $updateStmt->close();

  // Prepare email
  $to = $data['email'];
  $name = $data['name'];
  $temp_password = $data['temp_password'];

  $subject = "Your Account Has Been Approved - FiniteApp";
  $body = "
    Hello $name,<br><br>
    Your account has been <strong>approved</strong> and activated.<br><br>
    You may now login with the following credentials:<br><br>
    <strong>Email:</strong> $to<br>
    <strong>Password:</strong> $temp_password<br><br>
    Please login and change your password immediately.<br><br>
    Regards,<br>FiniteApp Team
  ";

  $mail = new PHPMailer(true);
  $emailSent = false;

  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mohdhasif24181@gmail.com';
    $mail->Password = 'bejt qgpy gntm vbst'; // App password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('mohdhasif24181@gmail.com', 'finiteApp');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);

    $mail->send();
    $emailSent = true;
  } catch (Exception $e) {
    error_log("Email error to $to: " . $mail->ErrorInfo);
  }

  json_ok([
    'email_sent' => $emailSent,
    'message' => $emailSent
      ? 'Client approved and email sent'
      : 'Client approved but email failed',
  ]);

} catch (Exception $e) {
  json_error(500, 'Approval failed: ' . $e->getMessage());
}

// publish