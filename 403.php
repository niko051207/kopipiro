<?php
// 403.php — rendered by require_role(). Standalone on purpose: no auth include, no loops.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="margin-top: 18vh;">
            <div class="col-md-6">
                <div class="card shadow-sm text-center">
                    <div class="card-body p-5">
                        <div class="display-1 fw-bold text-danger">403</div>
                        <h4 class="mb-2">Akses ditolak</h4>
                        <p class="text-muted mb-4">
                            Halaman ini hanya untuk peran tertentu dan akun kamu tidak memilikinya.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php" class="btn btn-dark">Kembali ke menu</a>
                            <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
