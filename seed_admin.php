
<?php
include 'config.php'; 
$username = 'admin'; 
$password = password_hash('admin123', PASSWORD_DEFAULT); 
$role     = 'admin'; 

$stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)"); 
mysqli_stmt_bind_param($stmt, "sss", $username, $password, $role); 
mysqli_stmt_execute($stmt); 
echo "Admin account created. Username: admin, Password: admin123. Delete this file now!"; ?> 