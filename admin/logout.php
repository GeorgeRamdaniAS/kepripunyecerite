<?php
// ============================================================
// admin/logout.php - Logout Admin
// Setelah logout, redirect ke halaman utama publik
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua data session admin
unset($_SESSION['admin_logged']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// (Opsional) Hapus seluruh session jika tidak ada user publik yang login
// Aktifkan baris di bawah jika kamu ingin sesi benar-benar bersih:
// session_unset();
// session_destroy();

// Redirect ke halaman utama publik
header('Location: ../index.php');
exit;