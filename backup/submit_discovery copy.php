<?php
// Manual include PHPMailer
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// DB connection
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Input
$input = json_decode(file_get_contents("php://input"), true);

// Log input
$logFile = __DIR__ . '/new_discovery.log';
$logContent = date('Y-m-d H:i:s') . " - Received Data:\n" . print_r($input, true) . "\n\n";
file_put_contents($logFile, $logContent, FILE_APPEND);

// Lookup untuk service
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

// Validate
if (!$name || !$email || !$phone || !is_array($selectedServices)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid fields"]);
    exit();
}

// // Translate service IDs to names
// $serviceNames = array_map(fn($id) => $serviceLookup[$id] ?? 'Unknown', $selectedServices);
// $serviceListText = implode(", ", $serviceNames);
// $serviceListJSON = json_encode($selectedServices); // Store original IDs as JSON

$serviceNames = [];

foreach ($selectedServices as $id) {
    if (isset($serviceLookup[$id])) {
        $serviceNames[] = $serviceLookup[$id];
    }
}

$serviceListText = implode(", ", $serviceNames); // ✅ nama services, comma

// Generate password
$password_raw = bin2hex(random_bytes(4)); // 8 char
$password_hash = MD5($password_raw);
$role = "client";
$created_at = date("Y-m-d H:i:s");

// Insert user
$stmtUser = $conn->prepare("INSERT INTO users (name, email, password, role, created_at, temp_password) VALUES (?, ?, ?, ?, ?, ?)");
$stmtUser->bind_param("ssssss", $name, $email, $password_hash, $role, $created_at, $password_raw);
if (!$stmtUser->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert user"]);
    exit();
}
$user_id = $stmtUser->insert_id;

// Insert client
$status = 'pending';
$stmtClient = $conn->prepare("INSERT INTO clients (user_id, company_name, phone, status, client_type, selected_services) VALUES (?, ?, ?, ?, ?, ?)");
$stmtClient->bind_param("isssss", $user_id, $company_name, $phone, $status, $client_type, $serviceListText);
if (!$stmtClient->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to insert client"]);
    exit();
}

// PHPMailer helper
function sendMail($to, $subject, $body, $replyTo = "mohdhasif24181@gmail.com")
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mohdhasif24181@gmail.com';
        $mail->Password = 'bejt qgpy gntm vbst'; // SMTP App Password (Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('mohdhasif24181@gmail.com', 'finiteApp');
        $mail->addAddress($to);
        $mail->addReplyTo($replyTo);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($body);
        $mail->AltBody = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Email error to $to: " . $mail->ErrorInfo);
    }
}

// Email to admin
$adminBody = "New $client_type client submitted the discovery form:\n\n"
    . "Name: $name\nEmail: $email\nPhone: $phone\n";
if ($client_type === 'company') $adminBody .= "Company Name: $company_name\n";
$adminBody .= "Selected Services: $serviceListText\n";

sendMail("mohdhasif24181@gmail.com", "New Discovery Form", $adminBody);

// Email to client
$clientBody = "Hi $name,\n\nThank you for submitting the discovery form.\nYour request is pending approval.\n\nSelected Services:\n$serviceListText\n\nWe’ll notify you once approved.\n\n- finiteApp Team";
sendMail($email, "Discovery Form Received", $clientBody);

// Done
echo json_encode(["success" => true, "message" => "Form submitted. Pending approval."]);
