<?php
require 'auth.php';
require 'config.php';

if (logged_in()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || $name === '' || strlen($name) > 100 || strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Isi nama (maksimal 100 karakter), username valid (3–50 karakter), dan kata sandi (8–72 karakter).';
    } else {
        try {
            $statement = $conn->prepare('INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, "customer")');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $statement->bind_param('sss', $username, $name, $hash);
            $statement->execute();
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            flash('Akun berhasil dibuat. Diskon 10% aktif untuk pesananmu.');
            header('Location: index.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $error = 'Username sudah digunakan. Coba username lain.';
        }
    }
}
layout_start('Daftar');
?>
<section class="auth-section"><div class="auth-card"><span class="eyebrow">SELAMAT DATANG DI KOPIPIRO</span><h1>Satu langkah<br>menuju <em>favoritmu.</em></h1><p class="auth-intro">Buat akun dan dapatkan diskon 10% setiap kali pesan.</p><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="name">Nama lengkap</label><input class="form-control form-control-lg mb-3" id="name" name="name" required><label class="form-label" for="username">Username</label><input class="form-control form-control-lg mb-3" id="username" name="username" minlength="3" required><label class="form-label" for="password">Kata sandi</label><input class="form-control form-control-lg mb-4" type="password" id="password" name="password" minlength="8" required><button class="btn btn-dark btn-lg rounded-pill w-100">Buat akun</button></form><p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk</a></p></div></section>
<?php layout_end(); ?>
