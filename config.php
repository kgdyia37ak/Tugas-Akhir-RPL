<?php
declare(strict_types=1);
session_start();

$host = 'localhost';
$dbname = 'pengadaan_pa_palu';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan database pengadaan_pa_palu sudah diimpor dan pengaturan config.php benar.');
}
