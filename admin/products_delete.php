<?php
session_start();
if(empty($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
include_once '../config/database.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Ambil semua gambar produk
        $stmt = $pdo->prepare("SELECT image_name FROM product_images WHERE product_id = ?");
        $stmt->execute([$id]);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Hapus file gambar dari server
        foreach ($images as $img) {
            $path = '../assets/uploads/products/' . $img;
            if (file_exists($path)) {
                unlink($path);
            }
        }
        
        // Hapus dari tabel product_images
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        
        // Hapus produk
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        
        header("Location: products.php?msg=Produk berhasil dihapus");
    } catch (PDOException $e) {
        header("Location: products.php?msg=Gagal menghapus produk");
    }
} else {
    header("Location: products.php");
}
exit;