<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Jakarta');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'db_kopipiro';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    exit('Database Kopipiro belum siap. Import database.sql melalui phpMyAdmin, lalu muat ulang halaman.');
}
