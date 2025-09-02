<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Include db.php for helper functions
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/push_helper.php'; // for push notification

// Log file setup
$logFile = __DIR__ . '/approve_freelancer.log';

function logError($message, $data = null)
{
    global $logFile;
    $logContent = date('Y-m-d H:i:s') . " - ERROR: $message";
    if ($data) {
        $logContent .= "\nData: " . print_r($data, true);
    }
    $logContent .= "\n\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
}

function logInfo($message, $data = null)
{
    global $logFile;
    $logContent = date('Y-m-d H:i:s') . " - INFO: $message";
    if ($data) {
        $logContent .= "\nData: " . print_r($data, true);
    }
    $logContent .= "\n\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
}

try {
  // Dapatkan koneksi database dari db.php
  $conn = get_db_connection();
  logInfo("Database connection established");

  // Read input
  $input = json_decode(file_get_contents("php://input"), true);
  logInfo("Received approval request", $input);

  if (!isset($input['freelancer_id'])) {
    logError("Freelancer ID is missing", $input);
    json_error(400, 'Freelancer ID is required');
  }

  $freelancer_id = (int)$input['freelancer_id'];
  logInfo("Processing freelancer approval", ['freelancer_id' => $freelancer_id]);

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
      logError("Freelancer not found", ['freelancer_id' => $freelancer_id]);
      json_error(404, 'Freelancer not found');
    }

    if ($data['status'] === 'approved') {
      logError("Freelancer already approved", ['freelancer_id' => $freelancer_id, 'status' => $data['status']]);
      json_error(400, 'Freelancer already approved');
    }
    
    logInfo("Freelancer found and ready for approval", [
      'freelancer_id' => $freelancer_id,
      'user_id' => $data['user_id'] ?? null,
      'name' => $data['name'],
      'email' => $data['email'],
      'current_status' => $data['status']
    ]);

    // Approve freelancer
    $updateStmt = $conn->prepare("
          UPDATE freelancers 
          SET status = 'approved', approved_at = NOW() 
          WHERE id = ?
      ");
    $updateStmt->bind_param("i", $freelancer_id);
    if (!$updateStmt->execute()) {
      logError("Failed to update freelancer status", ['freelancer_id' => $freelancer_id, 'error' => $updateStmt->error]);
      json_error(500, 'Failed to approve freelancer');
    }
    $updateStmt->close();
    logInfo("Freelancer status updated to approved", ['freelancer_id' => $freelancer_id]);

    // Prepare email
    $to = $data['email'];
    $name = $data['name'];
    $temp_password = $data['temp_password'];
    $user_id = $data['user_id'] ?? null;

    logInfo("Preparing approval email", ['freelancer_id' => $freelancer_id, 'user_id' => $user_id, 'email' => $to]);

    $subject = "Your Account Has Been Approved - Finite App";
    $body = "Dear $name,\n\n";
    $body .= "Congratulations! Your freelancer account has been approved and activated.\n\n";
    $body .= "You may now login with the following credentials:\n\n";
    $body .= "Email: $to\n";
    $body .= "Password: $temp_password\n\n";
    $body .= "Please login and change your password immediately for security purposes.\n\n";
    $body .= "If you have any questions, please don't hesitate to contact us at app@finite.my\n\n";
    $body .= "Best regards,\nThe Finite App Team";

    // Send email with anti-spam configuration
    $mail = new PHPMailer(true);
    $emailSent = false;

    try {
      logInfo("Starting email send process", ['to' => $to, 'subject' => $subject]);

      $mail->isSMTP();
      $mail->Host = 'mail.finite.my';
      $mail->SMTPAuth = true;
      $mail->Username = 'app@finite.my';
      $mail->Password = 'Marketing123456!';
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      $mail->Port = 465;
      
      // Anti-spam settings
      $mail->Priority = 3;
      $mail->XMailer = 'FiniteApp/1.0';
      $mail->CharSet = 'UTF-8';
      $mail->Encoding = '8bit';
      
              // Headers to prevent spam
      $mail->addCustomHeader('X-Priority', '3');
      $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
      $mail->addCustomHeader('Importance', 'Normal');
      $mail->addCustomHeader('X-Mailer', 'FiniteApp/1.0');
      $mail->addCustomHeader('List-Unsubscribe', '<mailto:app@finite.my?subject=unsubscribe>');
      $mail->addCustomHeader('Precedence', 'bulk');
      $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

      $mail->setFrom('app@finite.my', 'Finite App');
      $mail->addReplyTo('app@finite.my', 'Finite App Support');
      $mail->addAddress($to, $name);

      $mail->isHTML(false);
      $mail->Subject = $subject;
      $mail->Body = $body;

      if (!$mail->send()) {
        logError("Email send failed", [
          'freelancer_id' => $freelancer_id,
          'user_id' => $user_id,
          'to' => $to,
          'subject' => $subject,
          'error' => $mail->ErrorInfo
        ]);
        
        // Try fallback method - save to file
        try {
          $emailFile = __DIR__ . '/pending_emails.txt';
          $emailContent = "=== EMAIL QUEUE ===\n";
          $emailContent .= "Time: " . date('Y-m-d H:i:s') . "\n";
          $emailContent .= "To: $to\n";
          $emailContent .= "From: app@finite.my\n";
          $emailContent .= "Subject: $subject\n";
          $emailContent .= "Body:\n$body\n";
          $emailContent .= "==================\n\n";
          
          if (file_put_contents($emailFile, $emailContent, FILE_APPEND | LOCK_EX)) {
            logInfo("Email saved to file as fallback", ['freelancer_id' => $freelancer_id, 'file' => $emailFile]);
          }
        } catch (Exception $fileError) {
          logError("Failed to save email to file", ['error' => $fileError->getMessage()]);
        }
      } else {
        logInfo("Approval email sent successfully", ['freelancer_id' => $freelancer_id, 'user_id' => $user_id, 'to' => $to]);
        $emailSent = true;
      }
    } catch (Exception $e) {
      logError("Email error", [
        'freelancer_id' => $freelancer_id,
        'user_id' => $user_id,
        'to' => $to,
        'subject' => $subject,
        'error' => $e->getMessage()
      ]);
    }

            // Push notification to admin
    try {
      logInfo("Preparing push notification to admins");
      
      // Get admin IDs
      $adminIds = [];
      $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $adminIds[] = (int)$row['id'];
      }
      $stmt->close();
      
      if (!empty($adminIds)) {
        $notifTitle = "Freelancer Approved";
        $notifBody = "Freelancer $name has been approved successfully";
        $notifData = [
          'type' => 'freelancer_approved',
          'freelancer_id' => $freelancer_id,
          'user_id' => $user_id,
          'freelancer_name' => $name,
          'freelancer_email' => $to
        ];
        
        $notifResult = notify_users($conn, $adminIds, $notifTitle, $notifBody, $notifData, 'freelancer_approved');
        
        // Check notification result
        if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
          $sentCount = $notifResult['sent'] ?? 0;
          $failedCount = $notifResult['failed'] ?? 0;
          
          if ($sentCount > 0 && $failedCount == 0) {
            logInfo("✅ Push notification SUCCESS - All admins notified", [
              'freelancer_id' => $freelancer_id,
              'user_id' => $user_id,
              'admin_count' => count($adminIds),
              'sent_count' => $sentCount,
              'failed_count' => $failedCount,
              'notification_result' => $notifResult
            ]);
          } else if ($sentCount > 0 && $failedCount > 0) {
            logInfo("⚠️ Push notification PARTIAL SUCCESS - Some admins notified", [
              'freelancer_id' => $freelancer_id,
              'user_id' => $user_id,
              'admin_count' => count($adminIds),
              'sent_count' => $sentCount,
              'failed_count' => $failedCount,
              'notification_result' => $notifResult
            ]);
          } else {
            logError("❌ Push notification FAILED - No admins notified", [
              'freelancer_id' => $freelancer_id,
              'user_id' => $user_id,
              'admin_count' => count($adminIds),
              'sent_count' => $sentCount,
              'failed_count' => $failedCount,
              'notification_result' => $notifResult
            ]);
          }
        } else {
          logError("❌ Push notification FAILED - notify_users returned error", [
            'freelancer_id' => $freelancer_id,
            'user_id' => $user_id,
            'admin_count' => count($adminIds),
            'notification_result' => $notifResult
          ]);
        }
      } else {
        logInfo("No admin users found for notification", ['freelancer_id' => $freelancer_id, 'user_id' => $user_id]);
      }
      
    } catch (Exception $notifError) {
      logError("Push notification error", [
        'freelancer_id' => $freelancer_id,
        'user_id' => $user_id,
        'error' => $notifError->getMessage()
      ]);
    }

    logInfo("Freelancer approval process completed", [
      'freelancer_id' => $freelancer_id,
      'user_id' => $user_id,
      'email_sent' => $emailSent,
      'freelancer_name' => $name
    ]);

    json_ok([
      'email_sent' => $emailSent,
      'message' => $emailSent
        ? 'Freelancer approved and email sent'
        : 'Freelancer approved but email failed to send',
      'freelancer_id' => $freelancer_id,
      'user_id' => $user_id,
      'freelancer_name' => $name
    ]);

  } catch (Exception $e) {
    logError("Freelancer approval process failed", [
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString(),
      'input_data' => $input ?? null
    ]);
    json_error(500, 'Approval failed: ' . $e->getMessage());
  }

// publish