<?php
require 'auth.php';
require 'config.php';

if (logged_in()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statement = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? AND is_active = 1');
    $statement->bind_param('s', $_POST['username']);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['role'] === 'owner' ? 'owner.php' : ($user['role'] === 'kasir' ? 'cashier.php' : 'index.php')));
        exit;
    }
    $error = 'Username atau kata sandi belum sesuai.';
}
layout_start('Masuk');
?>
<section class="auth-section"><div class="auth-card"><span class="eyebrow">SENANG MELIHATMU LAGI</span><h1>Masuk ke<br><em>Kopipiro.</em></h1><p class="auth-intro">Masuk untuk menikmati diskon 10% untuk pesananmu.</p><?php show_flash(); ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><label class="form-label" for="username">Username</label><input class="form-control form-control-lg mb-3" id="username" name="username" required autofocus><label class="form-label" for="password">Kata sandi</label><input class="form-control form-control-lg mb-4" type="password" id="password" name="password" required><button class="btn btn-dark btn-lg rounded-pill w-100">Masuk</button></form><p class="auth-switch">Belum punya akun? <a href="register.php">Daftar sekarang</a></p></div></section>
<?php layout_end(); ?>
