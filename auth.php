<?php
session_start();

function is_admin() {
    return $_SESSION['role'] === 'admin';
}

function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        die("403 - Access denied. This page is for admins only.");
    }
}
?>

