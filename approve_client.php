<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// DB config
$host = "localhost";
$dbname = "finiteapp";
$user = "root";
$pass = "";
$charset = "utf8mb4";

// Connect PDO
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

// Read input
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['client_id'])) {
  echo json_encode(['success' => false, 'error' => 'Client ID is required']);
  exit;
}

$client_id = (int) $input['client_id'];

try {
  // Get client & user info
  $stmt = $pdo->prepare("
    SELECT c.id AS client_id, c.status, u.name, u.email, u.temp_password 
    FROM clients c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.id = :client_id
  ");
  $stmt->execute([':client_id' => $client_id]);
  $data = $stmt->fetch();

  if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Client not found']);
    exit;
  }

  if ($data['status'] === 'active') {
    echo json_encode(['success' => false, 'error' => 'Client already approved']);
    exit;
  }

  // Update client status
  $updateStmt = $pdo->prepare("UPDATE clients SET status = 'active', approved_at = NOW() WHERE id = :id");
  $updateStmt->execute([':id' => $client_id]);

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

  echo json_encode([
    'success' => true,
    'email_sent' => $emailSent,
    'message' => $emailSent
      ? 'Client approved and email sent'
      : 'Client approved but email failed',
  ]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
