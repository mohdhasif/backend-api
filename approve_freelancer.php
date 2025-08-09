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

// Logging
$logFile = __DIR__ . '/approve_freelancer.log';
function logMessage($message)
{
  global $logFile;
  file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Read input
$input = json_decode(file_get_contents("php://input"), true);
logMessage("Input: " . print_r($input, true));

if (!isset($input['freelancer_id'])) {
  echo json_encode(['success' => false, 'error' => 'Freelancer ID is required']);
  exit;
}

$freelancer_id = (int)$input['freelancer_id'];

try {
  // DB connection
  $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ];
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  logMessage("Database connection error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

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
} catch (Exception $e) {
  logMessage("Query error (fetch freelancer): " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Database query failed']);
  exit;
}

try {
  // Approve freelancer
  $updateStmt = $pdo->prepare("
        UPDATE freelancers 
        SET status = 'approved', approved_at = NOW() 
        WHERE id = :id
    ");
  $updateStmt->execute([':id' => $freelancer_id]);
} catch (Exception $e) {
  logMessage("Query error (update freelancer): " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Failed to update freelancer status']);
  exit;
}

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

echo json_encode([
  'success' => true,
  'email_sent' => $emailSent,
  'message' => $emailSent
    ? 'Freelancer approved and email sent'
    : 'Freelancer approved but email failed to send',
]);
