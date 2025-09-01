<?php
// Manual include PHPMailer classes
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Sambung DB
require_once __DIR__ . '/db.php'; // pastikan file db.php ada (mysqli $conn)
require_once __DIR__ . '/push_helper.php'; // untuk push notification

// Log file setup
$logFile = __DIR__ . '/freelancers.log';

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

// Request check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['status' => 'fail', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
logInfo("Received request data", $data);

// Vars
$name      = $data['name'] ?? 'N/A';
$email     = $data['email'] ?? 'N/A';
$phone     = $data['phone'] ?? 'N/A';
$portfolio = $data['portfolio'] ?? 'N/A';
$roles     = $data['roles'] ?? [];

$whatsappLink = 'https://wa.me/6' . preg_replace('/[^0-9]/', '', $phone);

// Format roles
$roleText = '';
foreach ($roles as $role) {
    $roleText .= "• $role\n";
}

// Insert ke database
try {
    logInfo("Starting database operations");

    // Generate password
    $password_raw = bin2hex(random_bytes(4)); // 8 char
    $password_hash = MD5($password_raw);
    $role = "client";
    $created_at = date("Y-m-d H:i:s");

    // --- Insert ke users ---
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, created_at, temp_password) VALUES (?, ?, ?, ?, 'freelancer', ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $password_hash,  $phone, $created_at, $password_raw);

    if (!$stmt->execute()) {
        $error = "Failed to insert user: " . $stmt->error;
        logError($error, ['name' => $name, 'email' => $email]);
        echo json_encode(['status' => 'fail', 'message' => $error]);
        exit;
    }
    $user_id = $stmt->insert_id;
    $stmt->close();
    logInfo("User inserted successfully", ['user_id' => $user_id]);

    // --- Insert ke freelancers ---
    $skillset = implode(',', $roles); // simpan roles jadi skillset
    $stmt2 = $conn->prepare("INSERT INTO freelancers (user_id, skillset, avatar_url) VALUES (?, ?, ?)");
    $defaultAvatar = null; // boleh fallback kalau perlu
    $stmt2->bind_param("iss", $user_id, $skillset, $defaultAvatar);

    if (!$stmt2->execute()) {
        $error = "Failed to insert freelancer: " . $stmt2->error;
        logError($error, ['user_id' => $user_id, 'skillset' => $skillset]);
        echo json_encode(['status' => 'fail', 'message' => $error]);
        exit;
    }
    $stmt2->close();
    logInfo("Freelancer inserted successfully", ['user_id' => $user_id, 'skillset' => $skillset]);

    logInfo("Database operations completed successfully");
} catch (Exception $e) {
    $error = 'DB Error: ' . $e->getMessage();
    logError($error, ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    echo json_encode(['status' => 'fail', 'message' => $error]);
    exit;
}

// Email content
$body = <<<EOD
Hi Admin,

Anda terima satu penyertaan Freelancer baharu:

👤 Nama: $name  
📧 Email: $email  
📱 Nombor WhatsApp: $phone  
🔗 Link WhatsApp: $whatsappLink  
🌐 Portfolio: $portfolio

🛠️ Peranan (Roles) Dipilih:
$roleText

Terima kasih.
EOD;

// Setup PHPMailer
$mail = new PHPMailer(true);

try {
    logInfo("Starting email send process");

    // $mail->isSMTP();
    // $mail->Host       = 'smtp.gmail.com';
    // $mail->SMTPAuth   = true;
    // $mail->Username   = 'mohdhasif24181@gmail.com';
    // $mail->Password   = 'bejt qgpy gntm vbst'; // app password
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // $mail->Port       = 587;

    $mail->isSMTP();
    $mail->Host       = 'mail.finite.my';
    $mail->SMTPAuth   = true;                               // authentication ON
    $mail->Username   = 'app@finite.my';                    // ikut config anda
    $mail->Password   = 'Marketing123456!';                 // password mailbox
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;        // SSL/TLS
    $mail->Port       = 465;                                // SSL
    
    // Anti-spam settings
    $mail->Priority = 3;                                    // Normal priority
    $mail->XMailer = 'FiniteApp/1.0';                      // Custom mailer
    $mail->CharSet = 'UTF-8';                              // Proper charset
    $mail->Encoding = '8bit';                              // Proper encoding
    
    // Headers untuk elak spam
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
    $mail->addCustomHeader('Importance', 'Normal');
    $mail->addCustomHeader('X-Mailer', 'FiniteApp/1.0');
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:app@finite.my?subject=unsubscribe>');
    $mail->addCustomHeader('Precedence', 'bulk');
    $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

    $mail->setFrom('app@finite.my', 'Finite App');          // From mesti domain finite.my
    $mail->addReplyTo('app@finite.my', 'Finite App');       // atau support@finite.my, pilihan
    
    // $mail->addAddress('mohdhasif24181@gmail.com', 'Mohd Hasif');
    $mail->addAddress('recruitment.finite@gmail.com', 'Finite Marketing');

    // Clean subject dan body untuk elak spam
    $cleanSubject = "Freelancer Application - $name";
    $cleanBody = "Dear Admin,\n\n";
    $cleanBody .= "A new freelancer application has been received:\n\n";
    $cleanBody .= "Applicant Name: $name\n";
    $cleanBody .= "Contact Email: $email\n";
    $cleanBody .= "Phone Number: $phone\n";
    $cleanBody .= "Portfolio: $portfolio\n\n";
    $cleanBody .= "Skills/Roles:\n$roleText\n";
    $cleanBody .= "Contact via WhatsApp: $whatsappLink\n\n";
    $cleanBody .= "Please review this application at your earliest convenience.\n\n";
    $cleanBody .= "Regards,\nFinite App System";

    $mail->isHTML(false);
    $mail->Subject = $cleanSubject;
    $mail->Body    = $cleanBody;

    // Hantar!
    if (!$mail->send()) {
        $error = "Email send failed: " . $mail->ErrorInfo;
        logError($error, [
            'user_id' => $user_id,
            'email' => $email,
            'mailer_error' => $mail->ErrorInfo,
            'subject' => $mail->Subject,
            'body_length' => strlen($cleanBody)
        ]);
        
                 // Try fallback method - save to file
         try {
             $emailFile = __DIR__ . '/pending_emails.txt';
             $emailContent = "=== EMAIL QUEUE ===\n";
             $emailContent .= "Time: " . date('Y-m-d H:i:s') . "\n";
             $emailContent .= "To: mohdhasif24181@gmail.com\n";
             $emailContent .= "From: app@finite.my\n";
             $emailContent .= "Subject: $cleanSubject\n";
             $emailContent .= "Body:\n$cleanBody\n";
             $emailContent .= "==================\n\n";
             
             if (file_put_contents($emailFile, $emailContent, FILE_APPEND | LOCK_EX)) {
                 logInfo("Email saved to file as fallback", ['user_id' => $user_id, 'file' => $emailFile]);
                 
                 // Also try to send via database queue
                 try {
                     $conn = get_db_connection();
                     $createTable = "CREATE TABLE IF NOT EXISTS email_queue (
                         id INT AUTO_INCREMENT PRIMARY KEY,
                         to_email VARCHAR(255) NOT NULL,
                         from_email VARCHAR(255) NOT NULL,
                         subject TEXT NOT NULL,
                         body TEXT NOT NULL,
                         created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                         status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                         attempts INT DEFAULT 0
                     )";
                     $conn->query($createTable);
                     
                     $stmt = $conn->prepare("INSERT INTO email_queue (to_email, from_email, subject, body) VALUES (?, ?, ?, ?)");
                     $stmt->bind_param("ssss", 'mohdhasif24181@gmail.com', 'app@finite.my', $cleanSubject, $cleanBody);
                     $stmt->execute();
                     
                     logInfo("Email also queued in database", ['user_id' => $user_id, 'queue_id' => $stmt->insert_id]);
                 } catch (Exception $dbError) {
                     logError("Failed to queue email in database", ['error' => $dbError->getMessage()]);
                 }
                 
                                   // Hantar push notification ke admin (even if email failed)
                  try {
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
                           $notifTitle = "New Freelancer Application";
                           $notifBody = "A new freelancer application has been submitted by $name";
                           $notifData = [
                               'type' => 'freelancer_application',
                               'user_id' => $user_id,
                               'applicant_name' => $name,
                               'applicant_email' => $email
                           ];
                           
                           $notifResult = notify_users($conn, $adminIds, $notifTitle, $notifBody, $notifData, 'freelancer_application');
                           
                           // Check notification result (fallback case)
                           if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
                               $sentCount = $notifResult['sent'] ?? 0;
                               $failedCount = $notifResult['failed'] ?? 0;
                               
                               if ($sentCount > 0 && $failedCount == 0) {
                                   logInfo("✅ Push notification SUCCESS (fallback case) - All admins notified", [
                                       'user_id' => $user_id,
                                       'admin_count' => count($adminIds),
                                       'sent_count' => $sentCount,
                                       'failed_count' => $failedCount,
                                       'notification_result' => $notifResult,
                                       'context' => 'Email failed but notification succeeded'
                                   ]);
                               } else if ($sentCount > 0 && $failedCount > 0) {
                                   logInfo("⚠️ Push notification PARTIAL SUCCESS (fallback case) - Some admins notified", [
                                       'user_id' => $user_id,
                                       'admin_count' => count($adminIds),
                                       'sent_count' => $sentCount,
                                       'failed_count' => $failedCount,
                                       'notification_result' => $notifResult,
                                       'context' => 'Email failed and notification partially failed'
                                   ]);
                               } else {
                                   logError("❌ Push notification FAILED (fallback case) - No admins notified", [
                                       'user_id' => $user_id,
                                       'admin_count' => count($adminIds),
                                       'sent_count' => $sentCount,
                                       'failed_count' => $failedCount,
                                       'notification_result' => $notifResult,
                                       'context' => 'Both email and notification failed'
                                   ]);
                               }
                           } else {
                               logError("❌ Push notification FAILED (fallback case) - notify_users returned error", [
                                   'user_id' => $user_id,
                                   'admin_count' => count($adminIds),
                                   'notification_result' => $notifResult,
                                   'context' => 'Both email and notification failed'
                               ]);
                           }
                       }
                      
                  } catch (Exception $notifError) {
                      logError("Push notification error (fallback case)", [
                          'user_id' => $user_id,
                          'error' => $notifError->getMessage()
                      ]);
                  }

    echo json_encode([
        'status' => 'success',
                      'message' => 'Freelancer saved but email queued due to spam filter',
                      'user_id' => $user_id,
                      'email_queued' => true
                  ]);
                  exit;
             }
         } catch (Exception $fileError) {
             logError("Failed to save email to file", ['error' => $fileError->getMessage()]);
         }
        
        echo json_encode([
            'status' => 'fail',
            'message' => $error
        ]);
        exit;
    }
    
         logInfo("Admin email sent successfully", ['user_id' => $user_id, 'email' => $email]);

     // Hantar email ke freelancer (thank you message)
     try {
         $freelancerMail = new PHPMailer(true);
         
         $freelancerMail->isSMTP();
         $freelancerMail->Host = 'mail.finite.my';
         $freelancerMail->SMTPAuth = true;
         $freelancerMail->Username = 'app@finite.my';
         $freelancerMail->Password = 'Marketing123456!';
         $freelancerMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
         $freelancerMail->Port = 465;
         
         // Anti-spam settings
         $freelancerMail->Priority = 3;
         $freelancerMail->XMailer = 'FiniteApp/1.0';
         $freelancerMail->CharSet = 'UTF-8';
         $freelancerMail->Encoding = '8bit';
         
         // Headers untuk elak spam
         $freelancerMail->addCustomHeader('X-Priority', '3');
         $freelancerMail->addCustomHeader('X-MSMail-Priority', 'Normal');
         $freelancerMail->addCustomHeader('Importance', 'Normal');
         $freelancerMail->addCustomHeader('X-Mailer', 'FiniteApp/1.0');
         $freelancerMail->addCustomHeader('List-Unsubscribe', '<mailto:app@finite.my?subject=unsubscribe>');
         $freelancerMail->addCustomHeader('Precedence', 'bulk');
         $freelancerMail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
         
         $freelancerMail->setFrom('app@finite.my', 'Finite App');
         $freelancerMail->addReplyTo('app@finite.my', 'Finite App Support');
         $freelancerMail->addAddress($email, $name);
         
         $freelancerSubject = "Thank You for Your Application - Finite App";
         $freelancerBody = "Dear $name,\n\n";
         $freelancerBody .= "Thank you for submitting your freelancer application to Finite App!\n\n";
         $freelancerBody .= "We have received your application with the following details:\n";
         $freelancerBody .= "• Name: $name\n";
         $freelancerBody .= "• Email: $email\n";
         $freelancerBody .= "• Phone: $phone\n";
         $freelancerBody .= "• Portfolio: $portfolio\n";
         $freelancerBody .= "• Skills/Roles: " . implode(', ', $roles) . "\n\n";
         $freelancerBody .= "Your application is currently under review by our team. We will contact you within 3-5 business days with an update on your application status.\n\n";
         $freelancerBody .= "If you have any questions, please don't hesitate to contact us at app@finite.my\n\n";
         $freelancerBody .= "Best regards,\nThe Finite App Team";
         
         $freelancerMail->isHTML(false);
         $freelancerMail->Subject = $freelancerSubject;
         $freelancerMail->Body = $freelancerBody;
         
         if ($freelancerMail->send()) {
             logInfo("Freelancer thank you email sent successfully", ['user_id' => $user_id, 'email' => $email]);
         } else {
             logError("Failed to send freelancer thank you email", [
                 'user_id' => $user_id,
                 'email' => $email,
                 'error' => $freelancerMail->ErrorInfo
             ]);
         }
         
     } catch (Exception $freelancerEmailError) {
         logError("Freelancer email error", [
             'user_id' => $user_id,
             'email' => $email,
             'error' => $freelancerEmailError->getMessage()
         ]);
     }

     // Hantar push notification ke admin
     try {
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
             $notifTitle = "New Freelancer Application";
             $notifBody = "A new freelancer application has been submitted by $name";
             $notifData = [
                 'type' => 'freelancer_application',
                 'user_id' => $user_id,
                 'applicant_name' => $name,
                 'applicant_email' => $email
             ];
             
             $notifResult = notify_users($conn, $adminIds, $notifTitle, $notifBody, $notifData, 'freelancer_application');
             
             // Check notification result
             if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
                 $sentCount = $notifResult['sent'] ?? 0;
                 $failedCount = $notifResult['failed'] ?? 0;
                 
                 if ($sentCount > 0 && $failedCount == 0) {
                     logInfo("✅ Push notification SUCCESS - All admins notified", [
                         'user_id' => $user_id,
                         'admin_count' => count($adminIds),
                         'sent_count' => $sentCount,
                         'failed_count' => $failedCount,
                         'notification_result' => $notifResult
                     ]);
                 } else if ($sentCount > 0 && $failedCount > 0) {
                     logInfo("⚠️ Push notification PARTIAL SUCCESS - Some admins notified", [
                         'user_id' => $user_id,
                         'admin_count' => count($adminIds),
                         'sent_count' => $sentCount,
                         'failed_count' => $failedCount,
                         'notification_result' => $notifResult
                     ]);
                 } else {
                     logError("❌ Push notification FAILED - No admins notified", [
                         'user_id' => $user_id,
                         'admin_count' => count($adminIds),
                         'sent_count' => $sentCount,
                         'failed_count' => $failedCount,
                         'notification_result' => $notifResult
                     ]);
                 }
             } else {
                 logError("❌ Push notification FAILED - notify_users returned error", [
                     'user_id' => $user_id,
                     'admin_count' => count($adminIds),
                     'notification_result' => $notifResult
                 ]);
             }
         } else {
             logInfo("No admin users found for notification", ['user_id' => $user_id]);
         }
         
     } catch (Exception $notifError) {
         logError("Push notification error", [
             'user_id' => $user_id,
             'error' => $notifError->getMessage()
         ]);
     }

     echo json_encode([
         'status' => 'success',
         'message' => 'Freelancer saved & notifications sent',
        'user_id' => $user_id
    ]);
} catch (Exception $e) {
    $error = "Mailer Error: " . $e->getMessage();
    logError($error, [
        'user_id' => $user_id,
        'email' => $email,
        'mailer_error' => $mail->ErrorInfo,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo json_encode([
        'status' => 'fail',
        'message' => $error
    ]);
}

// publish