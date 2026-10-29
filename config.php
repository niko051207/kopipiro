<?php
// config.php — database connection. Include this before using $conn.

date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_kopipiro";

try {
    $conn = mysqli_connect($host, $user, $pass, $db);
    mysqli_set_charset($conn, "utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Never show the raw credentials/stack trace to the browser.
    http_response_code(500);
    die(
        "Koneksi database gagal. Pastikan MySQL sudah berjalan dan database "
        . "<code>" . htmlspecialchars($db) . "</code> sudah di-import "
        . "(<code>database.sql</code> lalu <code>seed.sql</code>)."
    );
}
