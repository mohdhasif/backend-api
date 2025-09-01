<?php
/**
 * test_db_connection.php
 * File untuk test koneksi database dan display error jika gagal
 */

// Set header untuk JSON response
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Konfigurasi Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'finiteapp');

/**
 * Test koneksi database
 */
function test_db_connection(): array
{
    $start_time = microtime(true);
    
    try {
        // Cuba buat koneksi
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check jika ada error koneksi
        if ($conn->connect_error) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $conn->connect_error,
                'error_code' => $conn->connect_errno,
                'connection_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
                'timestamp' => date('Y-m-d H:i:s'),
                'config' => [
                    'host' => DB_HOST,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ]
            ];
        }
        
        // Set charset
        $conn->set_charset('utf8mb4');
        
        // Test query
        $result = $conn->query("SELECT 1 as test");
        if (!$result) {
            return [
                'success' => false,
                'error' => 'Query test failed: ' . $conn->error,
                'error_code' => $conn->errno,
                'connection_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
                'timestamp' => date('Y-m-d H:i:s'),
                'config' => [
                    'host' => DB_HOST,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ]
            ];
        }
        
        // Get database info
        $db_info = [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'charset' => $conn->character_set_name(),
            'server_version' => $conn->server_info,
            'protocol_version' => $conn->protocol_version,
            'connection_id' => $conn->thread_id
        ];
        
        // Close connection
        $conn->close();
        
        return [
            'success' => true,
            'message' => 'Database connection successful',
            'connection_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
            'timestamp' => date('Y-m-d H:i:s'),
            'database_info' => $db_info,
            'config' => [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage(),
            'error_code' => $e->getCode(),
            'connection_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER
            ]
        ];
    } catch (Error $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage(),
            'error_code' => $e->getCode(),
            'connection_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms',
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER
            ]
        ];
    }
}

// Jalankan test koneksi
$result = test_db_connection();

// Set HTTP status code
if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(500);
}

// Output result sebagai JSON
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>



