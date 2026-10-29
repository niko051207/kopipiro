<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect(home_for_role(current_user()['role']));
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, username, password, role, is_active FROM users WHERE username = ?"
        );
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Akun ini sedang dinonaktifkan. Hubungi owner.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']  = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                flash('success', 'Selamat datang, ' . $user['username'] . '!');
                redirect(home_for_role($user['role']));
            }
        } else {
            // Same message for "user not found" and "wrong password" — do not leak which one.
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Kopipiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="margin-top: 12vh;">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="mb-1">Masuk ke Kopipiro</h4>
                        <p class="text-muted small mb-4">Customer yang login mendapat diskon 10%.</p>

                        <?php render_flashes(); ?>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?= e($username) ?>" required autofocus autocomplete="username">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password"
                                       required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Login</button>
                        </form>

                        <hr class="my-4">
                        <p class="small text-muted mb-0">
                            Belum punya akun?
                            <a href="<?= e(url('register.php')) ?>">Daftar sebagai customer</a>
                        </p>
                    </div>
                </div>
                <p class="text-center mt-3 mb-0">
                    <a href="<?= e(url('index.php')) ?>" class="small text-decoration-none">&larr; Lihat menu tanpa login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
