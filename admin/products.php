<?php
session_start();
if(empty($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
include_once '../config/database.php';

// Query produk + stok
$stmt = $pdo->query("
    SELECT 
        p.id, p.name_id, p.name_en, p.price, p.is_featured, p.slug, p.stock,
        c.name_id AS cat_name_id,
        c.name_en AS cat_name_en,
        (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image,
        (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) AS variant_count,
        (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = p.id) AS variant_stock_total
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT id, name_id, name_en, slug FROM categories ORDER BY name_id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Produk - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f4f7f9; font-family: 'Poppins', sans-serif; }
        .sidebar { background: #0A3D5C; min-height: 100vh; padding: 20px; border-radius: 0 20px 20px 0; }
        .sidebar h5 { color: #D4A24C; font-weight: 700; }
        .sidebar a { color: rgba(255,255,255,.7); display: block; padding: 12px 20px; border-radius: 12px;
                     text-decoration: none; transition: .3s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.1); color: white; }
        .sidebar a.text-danger:hover { background: rgba(220,53,69,.2); }
        .table-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2 sidebar">
            <h5>⚓ Kepri Admin</h5>
            <hr class="border-secondary">
            <a href="index.php"><i class="fas fa-chart-pie me-2"></i>Dashboard</a>
            <a href="products.php" class="active"><i class="fas fa-box me-2"></i>Produk</a>
            <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
        <div class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">📦 Kelola Produk</h2>
                <a href="products_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Tambah Produk
                </a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $p):
                                        $img = !empty($p['image']) ? '../assets/uploads/products/'.$p['image'] : '../img/placeholder.jpg';
                                        $has_var = (int)$p['variant_count'] > 0;
                                        $stok = $has_var ? (int)$p['variant_stock_total'] : (int)$p['stock'];
                                        $badge = $stok <= 0 ? 'bg-danger' : ($stok <= 5 ? 'bg-warning text-dark' : 'bg-success');
                                    ?>
                                    <tr>
                                        <td><img src="<?= htmlspecialchars($img) ?>" class="table-img" alt=""></td>
                                        <td>
                                            <?= htmlspecialchars($p['name_id']) ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($p['name_en']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($p['cat_name_id']) ?></td>
                                        <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="badge <?= $badge ?>"><?= (int)$stok ?></span>
                                            <?php if ($has_var): ?>
                                                <br><small class="text-muted"><?= (int)$p['variant_count'] ?> varian</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['is_featured']): ?>
                                                <span class="badge bg-warning text-dark">Unggulan</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Biasa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="products_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="products_delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                                               title="Hapus" onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>