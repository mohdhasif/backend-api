<?php
// Manual include PHPMailer
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
$logFile = __DIR__ . '/submit_discovery.log';

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

    // Input
    $input = json_decode(file_get_contents("php://input"), true);
    logInfo("Received discovery form data", $input);

            // Lookup for service
    $serviceLookup = [
        '1' => 'Branding – Brand Identity',
        '2' => 'Social Media – Content / Influencer / Strategy',
        '3' => 'Advertising – Digital Advertising & Outdoor',
        '4' => 'Website – Landing Page & Full Website',
        '5' => 'Photo & Video – Product / Corporate Shoot',
        '6' => 'Brand Activation – Sampling Booth, Roadshow, etc',
        '7' => 'Mobile App – Ready to Use / Custom',
        '8' => 'Production – Printing / Merchandise',
        '9' => 'Events – Launching, Conference, Dinner',
        '10' => 'Jingle – Get your daily postings',
        '11' => 'Mainstream Media – TV / Radio / Digital Portal',
        '12' => 'Others – A-la-Cart (Bespoke)',
    ];

    // Normalize input
    $name = $input["name"] ?? '';
    $email = $input["email"] ?? '';
    $phone = $input["phone"] ?? '';
    $client_type = $input["client_type"] ?? $input["clientType"] ?? 'company';
    $company_name = $client_type === 'company' ? ($input["company_name"] ?? $input["companyName"] ?? '') : null;
    $selectedServices = $input["selected_services"] ?? $input["selectedServices"] ?? [];

    if (!$name || !$email || !$phone || !is_array($selectedServices)) {
        logError("Missing or invalid fields", [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'selectedServices' => $selectedServices
        ]);
        json_error(400, "Missing or invalid fields");
    }

    logInfo("Form validation passed", [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'client_type' => $client_type,
        'company_name' => $company_name,
        'services_count' => count($selectedServices)
    ]);

    // Translate service IDs
    $serviceNames = [];
    foreach ($selectedServices as $id) {
        if (isset($serviceLookup[$id])) {
            $serviceNames[] = $serviceLookup[$id];
        }
    }
    $serviceListText = implode(", ", $serviceNames);

    // Generate password
    $password_raw = bin2hex(random_bytes(4)); // 8 char
    $password_hash = MD5($password_raw);
    $role = "client";
    $created_at = date("Y-m-d H:i:s");

    logInfo("Starting database operations");

    // Insert user
    $stmtUser = $conn->prepare("INSERT INTO users (name, email, password, role, created_at, temp_password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUser->bind_param("ssssss", $name, $email, $password_hash, $role, $created_at, $password_raw);
    if (!$stmtUser->execute()) {
        logError("Failed to insert user", ['name' => $name, 'email' => $email, 'error' => $stmtUser->error]);
        json_error(500, "Failed to insert user");
    }
    $user_id = $stmtUser->insert_id;
    logInfo("User inserted successfully", ['user_id' => $user_id]);

    // Insert client (temporary logo_url kosong dulu)
    $status = 'pending';
    $empty_logo = '';
    $stmtClient = $conn->prepare("INSERT INTO clients (user_id, company_name, phone, status, client_type, selected_services, logo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtClient->bind_param("issssss", $user_id, $company_name, $phone, $status, $client_type, $serviceListText, $empty_logo);
    if (!$stmtClient->execute()) {
        logError("Failed to insert client", ['user_id' => $user_id, 'company_name' => $company_name, 'error' => $stmtClient->error]);
        json_error(500, "Failed to insert client");
    }
    $client_id = $stmtClient->insert_id;
    logInfo("Client inserted successfully", ['client_id' => $client_id, 'user_id' => $user_id]);

    // ✅ Generate logo
    function generateClientLogo($text, $clientId)
    {
        $width = 200;
        $height = 200;
        $bgColor = [0, 122, 255]; // biru
        $textColor = [255, 255, 255]; // putih
        $fontSize = 5; // built-in font

        $image = imagecreate($width, $height);
        $background = imagecolorallocate($image, ...$bgColor);
        $color = imagecolorallocate($image, ...$textColor);

        // Ambil initial huruf
        $initials = strtoupper(substr($text, 0, 2));

        // Kira posisi tengah
        // $x = ($width - imagefontwidth($fontSize) * strlen($initials)) / 2;
        // $y = ($height - imagefontheight($fontSize)) / 2;

        $x = (int)(($width - imagefontwidth($fontSize) * strlen($initials)) / 2);
        $y = (int)(($height - imagefontheight($fontSize)) / 2);

        imagestring($image, $fontSize, $x, $y, $initials, $color);

        // Simpan logo
        $folder = __DIR__ . "/uploads/logos/";
        if (!is_dir($folder)) mkdir($folder, 0777, true);

        $filename = "logo_" . $clientId . ".png";
        $filepath = $folder . $filename;
        imagepng($image, $filepath);
        imagedestroy($image);

        $baseUrl = "/uploads/logos/"; // ganti ikut domain sebenar anda
        return $baseUrl . $filename;
    }

    // Update logo_url dalam DB
    $logo_text = $company_name ?: $name;
    $logo_url = generateClientLogo($logo_text, $client_id);
    $updateLogo = $conn->prepare("UPDATE clients SET logo_url = ? WHERE id = ?");
    $updateLogo->bind_param("si", $logo_url, $client_id);
    if (!$updateLogo->execute()) {
        logError("Failed to update logo URL", ['client_id' => $client_id, 'logo_url' => $logo_url]);
    } else {
        logInfo("Logo generated and updated successfully", ['client_id' => $client_id, 'logo_url' => $logo_url]);
    }

            // PHPMailer helper with anti-spam configuration
    function sendMail($to, $subject, $body, $recipientName = '', $isAdmin = false)
    {
        $mail = new PHPMailer(true);
        try {
            logInfo("Starting email send process", ['to' => $to, 'subject' => $subject, 'is_admin' => $isAdmin]);

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mohdhasif24181@gmail.com';
            $mail->Password   = 'bejt qgpy gntm vbst'; // app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // $mail->isSMTP();
            // $mail->Host = 'mail.finite.my';
            // $mail->SMTPAuth = true;
            // $mail->Username = 'app@finite.my';
            // $mail->Password = 'Marketing123456!';
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // $mail->Port = 465;

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
            $mail->addAddress($to, $recipientName);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            if (!$mail->send()) {
                logError("Email send failed", [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $mail->ErrorInfo,
                    'is_admin' => $isAdmin
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
                        logInfo("Email saved to file as fallback", ['to' => $to, 'file' => $emailFile]);
                    }
                } catch (Exception $fileError) {
                    logError("Failed to save email to file", ['error' => $fileError->getMessage()]);
                }

                return false;
            }

            logInfo("Email sent successfully", ['to' => $to, 'subject' => $subject]);
            return true;
        } catch (Exception $e) {
            logError("Email error", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'is_admin' => $isAdmin
            ]);
            return false;
        }
    }

    // Email to admin
    logInfo("Preparing admin email");
    $adminSubject = "New Discovery Form - $name";
    $adminBody = "Dear Admin,\n\n";
    $adminBody .= "A new discovery form has been submitted:\n\n";
    $adminBody .= "Applicant Name: $name\n";
    $adminBody .= "Contact Email: $email\n";
    $adminBody .= "Phone Number: $phone\n";
    $adminBody .= "Client Type: $client_type\n";
    if ($client_type === 'company') $adminBody .= "Company Name: $company_name\n";
    $adminBody .= "Selected Services: $serviceListText\n\n";
    $adminBody .= "Please review this application at your earliest convenience.\n\n";
    $adminBody .= "Regards,\nFinite App System";

    // $adminEmailResult = sendMail("helloinfo.finite@gmail.com", $adminSubject, $adminBody, "Finite", true);
    $adminEmailResult = sendMail("mohdhasif24181@gmail.com", $adminSubject, $adminBody, "Finite", true);

    // Email to client
    logInfo("Preparing client email", ['client_email' => $email]);
    $clientSubject = "Thank You for Your Discovery Form - Finite App";
    $clientBody = "Dear $name,\n\n";
    $clientBody .= "Thank you for submitting your discovery form to Finite App!\n\n";
    $clientBody .= "We have received your request with the following details:\n";
    $clientBody .= "• Name: $name\n";
    $clientBody .= "• Email: $email\n";
    $clientBody .= "• Phone: $phone\n";
    if ($client_type === 'company') $clientBody .= "• Company Name: $company_name\n";
    $clientBody .= "• Client Type: $client_type\n";
    $clientBody .= "• Selected Services: $serviceListText\n\n";
    $clientBody .= "Your request is currently under review by our team. We will contact you within 3-5 business days with an update on your application status.\n\n";
    $clientBody .= "If you have any questions, please don't hesitate to contact us at app@finite.my\n\n";
    $clientBody .= "Best regards,\nThe Finite App Team";

    $clientEmailResult = sendMail($email, $clientSubject, $clientBody, $name, false);

    // Push notification ke admin
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
            $notifTitle = "New Discovery Form Submission";
            $notifBody = "A new discovery form has been submitted by $name";
            $notifData = [
                'type' => 'discovery_form',
                'user_id' => $user_id,
                'client_id' => $client_id,
                'applicant_name' => $name,
                'applicant_email' => $email,
                'client_type' => $client_type,
                'company_name' => $company_name
            ];

            $notifResult = notify_users($conn, $adminIds, $notifTitle, $notifBody, $notifData, 'discovery_form');

            // Check notification result
            if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
                $sentCount = $notifResult['sent'] ?? 0;
                $failedCount = $notifResult['failed'] ?? 0;

                if ($sentCount > 0 && $failedCount == 0) {
                    logInfo("✅ Push notification SUCCESS - All admins notified", [
                        'user_id' => $user_id,
                        'client_id' => $client_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                } else if ($sentCount > 0 && $failedCount > 0) {
                    logInfo("⚠️ Push notification PARTIAL SUCCESS - Some admins notified", [
                        'user_id' => $user_id,
                        'client_id' => $client_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                } else {
                    logError("❌ Push notification FAILED - No admins notified", [
                        'user_id' => $user_id,
                        'client_id' => $client_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                }
            } else {
                logError("❌ Push notification FAILED - notify_users returned error", [
                    'user_id' => $user_id,
                    'client_id' => $client_id,
                    'admin_count' => count($adminIds),
                    'notification_result' => $notifResult
                ]);
            }
        } else {
            logInfo("No admin users found for notification", ['user_id' => $user_id, 'client_id' => $client_id]);
        }
    } catch (Exception $notifError) {
        logError("Push notification error", [
            'user_id' => $user_id,
            'client_id' => $client_id,
            'error' => $notifError->getMessage()
        ]);
    }

    // Done
    logInfo("Discovery form processing completed successfully", [
        'user_id' => $user_id,
        'client_id' => $client_id,
        'admin_email_sent' => $adminEmailResult,
        'client_email_sent' => $clientEmailResult,
        'logo_url' => $logo_url
    ]);

    json_ok([
        "message" => "Discovery form submitted successfully. Pending approval.",
        "user_id" => $user_id,
        "client_id" => $client_id,
        "logo_url" => $logo_url,
        "admin_email_sent" => $adminEmailResult,
        "client_email_sent" => $clientEmailResult
    ]);
} catch (Exception $e) {
    logError("Discovery form processing failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input_data' => $input ?? null
    ]);
    json_error(500, 'Discovery form processing error: ' . $e->getMessage());
}
