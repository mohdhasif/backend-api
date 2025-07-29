<?php
// Konfigurasi database
$host = '127.0.0.1';       // Nama host
$user = 'root';            // Nama pengguna MySQL
$password = '';            // Kata laluan MySQL
// $password = 'mohH4sif@1';            // Kata laluan MySQL
$database = 'finiteapp';  // Nama database

// Cipta sambungan
$conn = new mysqli($host, $user, $password, $database);

// Semak sambungan
if ($conn->connect_error) {
    die("Gagal sambung ke database: " . $conn->connect_error);
}

echo "Sambungan ke database berjaya!";
?>
