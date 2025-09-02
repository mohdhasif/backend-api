<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

// Log file setup
$logFile = __DIR__ . '/claim_install_subscriptions.log';

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

    $user = require_auth($conn);
    logInfo("User authenticated", ['user_id' => $user['id']]);
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    logInfo("Received request data", $data);
    
    $install_id = $data['install_id'] ?? '';

    if (!$install_id) {
        logError("Missing install_id", ['data' => $data]);
        json_error(400, 'Missing install_id');
    }

    logInfo("Processing install subscription claim", [
        'user_id' => $user['id'],
        'install_id' => $install_id
    ]);

    // Move all anonymous records for this install_id to the actual user_id
    $stmt = $conn->prepare("
        UPDATE user_push_subscriptions
           SET user_id = ?
         WHERE install_id = ? AND (user_id IS NULL OR user_id <> ?)
    ");
    $stmt->bind_param("isi", $user['id'], $install_id, $user['id']);
    
    if (!$stmt->execute()) {
        logError("Failed to update push subscriptions", [
            'user_id' => $user['id'],
            'install_id' => $install_id,
            'error' => $stmt->error
        ]);
        json_error(500, 'Failed to update push subscriptions');
    }
    
    $affectedRows = $stmt->affected_rows;
    logInfo("Install subscription claim completed", [
        'user_id' => $user['id'],
        'install_id' => $install_id,
        'affected_rows' => $affectedRows
    ]);

    json_ok([
        'success' => true,
        'updated' => $affectedRows,
        'install_id' => $install_id,
        'user_id' => $user['id']
    ]);
} catch (Throwable $e) {
    logError("Install subscription claim process failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $user['id'] ?? null,
        'install_id' => $install_id ?? null
    ]);
    json_error(500, 'Server error: ' . $e->getMessage());
}

// publish