
    <?php // navbar.php — include right after the <body> tag ?>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Coffee Order</a>
            <div class="d-flex align-items-center gap-2">
                <?php if (is_admin()): ?>
                    <a href="users.php" class="btn btn-outline-light btn-sm">Users</a>
                <?php endif; ?>
                <?php if(isset($_SESSION['user_id'])){
                    ?>
                    <span class="text-white small">
                        <?= htmlspecialchars($_SESSION['username']) ?>
                        <span class="badge bg-secondary"><?= $_SESSION['role'] ?></span>
                    </span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
                <?php
                } else{
                    ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
                <?php
                }   ?>
            </div>
        </div>
    </nav>