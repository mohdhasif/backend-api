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

// PDO connect
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

// Log request
$logFile = __DIR__ . '/approve_freelancer.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Input: " . print_r($input, true) . "\n", FILE_APPEND);

// Validate input
if (!isset($input['freelancer_id'])) {
  echo json_encode(['success' => false, 'error' => 'Freelancer ID is required']);
  exit;
}

$freelancer_id = (int)$input['freelancer_id'];

try {
  // Check freelancer info
  $stmt = $pdo->prepare("
        SELECT 
            f.id AS freelancer_id, 
            f.status, 
            u.name, 
            u.email, 
            u.temp_password 
        FROM freelancers f
        LEFT JOIN users u ON f.user_id = u.id 
        WHERE f.id = :freelancer_id
    ");
  $stmt->execute([':freelancer_id' => $freelancer_id]);
  $data = $stmt->fetch();

  if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Freelancer not found']);
    exit;
  }

  if ($data['status'] === 'approved') {
    echo json_encode(['success' => false, 'error' => 'Freelancer already approved']);
    exit;
  }

  // Approve freelancer
  $updateStmt = $pdo->prepare("
        UPDATE freelancers 
        SET status = 'approved', approved_at = NOW() 
        WHERE id = :id
    ");
  $updateStmt->execute([':id' => $freelancer_id]);

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
    $mail->Password = 'bejt qgpy gntm vbst'; // gunakan app password sahaja!
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
    error_log("Email send failed to $to: " . $mail->ErrorInfo);
  }

  echo json_encode([
    'success' => true,
    'email_sent' => $emailSent,
    'message' => $emailSent
      ? 'Freelancer approved and email sent'
      : 'Freelancer approved but email failed to send',
  ]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
