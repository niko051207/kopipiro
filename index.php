<?php
// index.php
include 'auth.php';
include 'config.php';


$query = "SELECT s.id, s.name, s.price, s.url_image
          FROM product s";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Coffee Order</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">

                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td> <img src="<?= htmlspecialchars($row['url_image']) ?>" width="100" class="img-fluid" alt="coffee image"></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td>
                                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer'){ ?>
                                <strike><?= htmlspecialchars($row['price']) ?></strike><br/>
                                <?= htmlspecialchars($row['price']*0.9) ?>
                                <?php } else{ ?>
                                <?= htmlspecialchars($row['price']) ?>
                                <?php } ?>
                                </td>
                                <td class="text-center">
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>