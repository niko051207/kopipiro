<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Jakarta');

// The member discount is applied on the server only (see final_price() in helpers.php).
const DISCOUNT_RATE = 0.10;

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'db_kopipiro';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    $message = 'Database Kopipiro belum siap. Import database.sql melalui phpMyAdmin, lalu muat ulang halaman.';
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => $message]);
        exit;
    }
    exit($message);
}
