<?php
// Cegah session_start() double
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PENGATURAN HOSTING INFINITYFREE
$host = 'sql200.infinityfree.com';
$dbname = 'if0_42075069_volunteerone';
$user = 'if0_42075069';
$pass = 'password_hosting_kita'; // pw dummy

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>