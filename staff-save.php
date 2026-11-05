<?php
require 'auth.php';
require 'config.php';
require_role('owner');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name !== '' && strlen($username) >= 3 && strlen($password) >= 8) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $statement = $conn->prepare('INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, "kasir")');
            $statement->bind_param('sss', $username, $name, $hash);
            $statement->execute();
            flash('Akun kasir berhasil dibuat.');
        } catch (mysqli_sql_exception $exception) {
            flash('Username sudah digunakan.', 'danger');
        }
    } else {
        flash('Isi data dengan benar. Username minimal 3 dan kata sandi minimal 8 karakter.', 'danger');
    }
}
header('Location: owner.php');
exit;
