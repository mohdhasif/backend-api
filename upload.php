<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

error_reporting(0); // suppress warning from being echoed to JSON
ini_set('display_errors', 0);

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $targetDir = __DIR__ . "/uploads/";
    $fileName = basename($_FILES['file']['name']);
    $safeName = time() . "_" . preg_replace("/[^A-Za-z0-9_\-\.]/", "_", $fileName);
    $targetPath = $targetDir . $safeName;

    // Make sure uploads/ directory exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        // Build public URL if using localhost or ngrok
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $publicUrl = $protocol . $_SERVER['HTTP_HOST'] . "/uploads/" . $safeName;
        $response = ['success' => true, 'url' => $publicUrl];
    } else {
        $response = ['success' => false, 'error' => 'Upload failed (write permission?)'];
    }
} else {
    $response = ['success' => false, 'error' => 'No file received or invalid request method'];
}

echo json_encode($response);
