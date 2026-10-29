<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect(home_for_role(current_user()['role']));
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username 3–50 karakter, hanya huruf, angka, dan garis bawah.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);

        if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
            $errors[] = 'Username sudah dipakai. Pilih yang lain.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Role is hard-coded: registration can only ever create a customer.
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (username, password, role) VALUES (?, ?, 'customer')"
            );
            mysqli_stmt_bind_param($stmt, 'ss', $username, $hash);
            mysqli_stmt_execute($stmt);

            flash('success', 'Akun berhasil dibuat. Silakan login untuk mendapat diskon 10%.');
            redirect('login.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'Username sudah dipakai. Pilih yang lain.'
                : 'Akun gagal dibuat. Coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Kopipiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="margin-top: 8vh;">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="mb-1">Daftar akun customer</h4>
                        <p class="text-muted small mb-4">
                            Setelah login, semua harga otomatis mendapat diskon 10%.
                        </p>

                        <?php render_flashes(); ?>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger py-2 small">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?= e($username) ?>" required minlength="3" maxlength="50"
                                       pattern="[A-Za-z0-9_]+" autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password"
                                       required minlength="8" autocomplete="new-password">
                                <div class="form-text">Minimal 8 karakter.</div>
                            </div>
                            <div class="mb-4">
                                <label for="password_confirm" class="form-label">Ulangi password</label>
                                <input type="password" class="form-control" id="password_confirm"
                                       name="password_confirm" required minlength="8" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-dark w-100">Daftar</button>
                        </form>

                        <hr class="my-4">
                        <p class="small text-muted mb-0">
                            Sudah punya akun?
                            <a href="<?= e(url('login.php')) ?>">Login di sini</a>
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
