<?php
// config/database.php
$host = 'localhost';
$dbname = 'db_kepripunyecerite';
$username = 'root';   // ganti jika pakai user lain
$password = '';       // ganti jika ada password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log($e->getMessage());
    die("Koneksi database gagal. Periksa konfigurasi database.");
}

// Pastikan variabel $pdo tersedia secara global
?>