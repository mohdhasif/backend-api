<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include db.php untuk fungsi helper
require_once __DIR__ . '/db.php';

// Logging
$logFile = __DIR__ . '/approve_freelancer.log';
function logMessage($message)
{
  global $logFile;
  file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

try {
  // Dapatkan koneksi database dari db.php
  $conn = get_db_connection();

  // Read input
  $input = json_decode(file_get_contents("php://input"), true);
  logMessage("Input: " . print_r($input, true));

  if (!isset($input['freelancer_id'])) {
    json_error(400, 'Freelancer ID is required');
  }

  $freelancer_id = (int)$input['freelancer_id'];

    // Check freelancer info
    $stmt = $conn->prepare("
          SELECT 
              f.id AS freelancer_id, 
              f.status, 
              u.name, 
              u.email, 
              u.temp_password 
          FROM freelancers f
          LEFT JOIN users u ON f.user_id = u.id 
          WHERE f.id = ?
      ");
    $stmt->bind_param("i", $freelancer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    if (!$data) {
      json_error(404, 'Freelancer not found');
    }

    if ($data['status'] === 'approved') {
      json_error(400, 'Freelancer already approved');
    }

    // Approve freelancer
    $updateStmt = $conn->prepare("
          UPDATE freelancers 
          SET status = 'approved', approved_at = NOW() 
          WHERE id = ?
      ");
    $updateStmt->bind_param("i", $freelancer_id);
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

    $emailSent = false;

    try {
      $mail = new PHPMailer(true);
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = 'mohdhasif24181@gmail.com';
      $mail->Password = 'bejt qgpy gntm vbst'; // App password
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;

      $mail->setFrom('mohdhasif24181@gmail.com', 'finiteApp');
      $mail->addAddress($to, $name);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $body;
      $mail->AltBody = strip_tags($body);

      $mail->send();
      $emailSent = true;
    } catch (Exception $e) {
      logMessage("Email error: " . $mail->ErrorInfo);
    }

    json_ok([
      'email_sent' => $emailSent,
      'message' => $emailSent
        ? 'Freelancer approved and email sent'
        : 'Freelancer approved but email failed to send',
    ]);

  } catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    json_error(500, 'Approval failed: ' . $e->getMessage());
  }

// publish