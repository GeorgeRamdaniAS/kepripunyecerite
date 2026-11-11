<?php
// ============================================================
// admin/index.php — Dashboard Admin (UI/UX Modern & User-Friendly)
// ============================================================

session_start();
if (empty($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
include_once '../config/database.php';

$msg = $_GET['msg'] ?? '';

// ------------------------------------------------------------
// Handle Update Status Pesanan (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id   = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $valid_statuses = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        $qs = '?msg=Status+pesanan+berhasil+diperbarui';
        if (isset($_POST['start_date'])) $qs .= '&start_date=' . urlencode($_POST['start_date']);
        if (isset($_POST['end_date']))   $qs .= '&end_date=' . urlencode($_POST['end_date']);
        
        header("Location: index.php$qs");
        exit;
    }
}

// ------------------------------------------------------------
// Statistik Utama
// ------------------------------------------------------------
$prodCount  = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$pendingCount = 0;
try {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// Filter Pendapatan
// ------------------------------------------------------------
$start_date = $_GET['start_date'] ?? date('Y-m-01'); 
$end_date   = $_GET['end_date']   ?? date('Y-m-t');

$revenueFiltered = 0;
try {
    $stmtRev = $pdo->prepare("
        SELECT COALESCE(SUM(total),0) FROM orders
        WHERE status IN ('paid','confirmed','shipped','completed')
          AND DATE(created_at) >= ? AND DATE(created_at) <= ?
    ");
    $stmtRev->execute([$start_date, $end_date]);
    $revenueFiltered = (float)$stmtRev->fetchColumn();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// Stok Menipis
// ------------------------------------------------------------
$lowStockProducts = [];
try {
    $lowStockProducts = $pdo->query("
        SELECT id, name_id, stock FROM products
        WHERE stock <= 5 AND stock > -1
        ORDER BY stock ASC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$lowStockCount = 0;
try {
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5")->fetchColumn();
} catch (Throwable $e) {}

// ------------------------------------------------------------
// Pesanan Terbaru
// ------------------------------------------------------------
$recentOrders = [];
try {
    $recentOrders = $pdo->query("
        SELECT id, order_number, customer_name, customer_phone, total, status, created_at, items_json, shipping_method
        FROM orders ORDER BY created_at DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentOrders as &$order) {
        $items = json_decode($order['items_json'] ?? '[]', true);
        if (empty($items)) {
            $stmtItems = $pdo->prepare("SELECT product_name, variant_name, qty FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order['id']]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }
        $order['items'] = $items;
    }
} catch (Throwable $e) {}

$featuredCount = 0;
try {
    $featuredCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_featured = 1")->fetchColumn();
} catch (Throwable $e) {}

// Helper Badge Warna Status
function getStatusClass($status) {
    return match($status) {
        'pending'   => 'bg-warning text-dark',
        'confirmed' => 'bg-info text-dark',
        'shipped'   => 'bg-primary text-white',
        'completed' => 'bg-success text-white',
        'cancelled' => 'bg-danger text-white',
        default     => 'bg-secondary text-white'
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Kepri Punya Cerite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #0A3D5C;
            --sidebar-hover: rgba(255,255,255,0.08);
            --primary-color: #1A7A8C;
        }
        body { background: #F4F7F9; font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        /* Sidebar Modern */
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            padding: 24px 16px;
            border-radius: 0 24px 24px 0;
            box-shadow: 4px 0 24px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
        }
        .sidebar h5 { color: #D4A24C; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 2rem; text-align: center; }
        .sidebar a {
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 8px;
        }
        .sidebar a i { width: 24px; text-align: center; margin-right: 12px; font-size: 1.1rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--sidebar-hover); color: white; transform: translateX(4px); }
        .sidebar a.active { background: var(--primary-color); color: white; box-shadow: 0 4px 12px rgba(26,122,140,0.3); }
        .sidebar a.text-danger:hover { background: rgba(220,53,69,0.2); color: #ff8a8a; }

        /* Stat Cards */
        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 16px;
        }
        .stat-title { font-size: 0.85rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: #0A3D5C; margin: 0; line-height: 1.2; }

        /* Tabel Modern */
        .card-table { border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; }
        .card-table .card-header { background: #fff; border-bottom: 1px solid #f0f2f5; padding: 20px 24px; }
        .table { margin-bottom: 0; }
        .table th { font-weight: 600; color: #8392a5; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f0f2f5; padding: 16px 24px; background: #fafbfc; }
        .table td { padding: 16px 24px; vertical-align: middle; border-bottom: 1px solid #f0f2f5; }
        .table tbody tr:hover { background-color: #f8f9fa; }

        /* Status Select Dropdown ala Badge */
        .status-form select {
            appearance: none;
            border: none;
            padding: 6px 32px 6px 16px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            outline: none;
        }
        
        /* Item Chip */
        .item-chip {
            display: inline-block;
            background: #f1f3f5;
            color: #495057;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin: 2px 0;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
            <h5>⚓ Kepri Admin</h5>
            <a href="index.php" class="active"><i class="fas fa-chart-pie"></i>Dashboard</a>
            <a href="products.php"><i class="fas fa-box"></i>Produk</a>
            <a href="logout.php" class="text-danger mt-5"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>

        <!-- CONTENT -->
        <div class="col-md-9 col-lg-10 p-4 p-md-5">
            
            <?php if ($msg): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" style="border-radius: 12px;">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
                <div>
                    <h2 class="fw-bold mb-1" style="color: #0A3D5C;">Dashboard Overview</h2>
                    <p class="text-muted mb-0"><i class="far fa-calendar-alt me-1"></i> <?= date('l, d F Y') ?></p>
                </div>
                <a href="products_form.php" class="btn btn-primary rounded-pill px-4 py-2" style="background: var(--primary-color); border: none;">
                    <i class="fas fa-plus me-2"></i>Tambah Produk
                </a>
            </div>

            <!-- STAT CARDS -->
            <div class="row g-4 mb-5">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div>
                            <div class="stat-icon bg-light text-primary"><i class="fas fa-box"></i></div>
                            <div class="stat-title">Total Produk</div>
                            <h2 class="stat-value"><?= $prodCount ?></h2>
                        </div>
                        <?php if ($featuredCount > 0): ?>
                            <small class="text-muted mt-2 d-block"><i class="fas fa-star text-warning me-1"></i> <?= $featuredCount ?> Produk Unggulan</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-shopping-bag"></i></div>
                            <div class="stat-title">Total Pesanan</div>
                            <h2 class="stat-value"><?= number_format($orderCount) ?></h2>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                            <small class="text-warning fw-semibold mt-2 d-block"><i class="fas fa-clock me-1"></i> <?= $pendingCount ?> Menunggu</small>
                        <?php else: ?>
                            <small class="text-success mt-2 d-block"><i class="fas fa-check me-1"></i> Semua Terproses</small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PENDAPATAN + FILTER -->
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-money-bill-wave"></i></div>
                            </div>
                            <div class="stat-title">Pendapatan</div>
                            <h2 class="stat-value text-success" style="font-size: 1.5rem;">Rp <?= number_format($revenueFiltered, 0, ',', '.') ?></h2>
                        </div>
                        <form method="GET" class="mt-3">
                            <div class="input-group input-group-sm">
                                <input type="date" name="start_date" class="form-control form-control-sm border-0 bg-light" value="<?= htmlspecialchars($start_date) ?>" required>
                                <input type="date" name="end_date" class="form-control form-control-sm border-0 bg-light" value="<?= htmlspecialchars($end_date) ?>" required>
                                <button type="submit" class="btn btn-light border-0" title="Filter"><i class="fas fa-search text-primary"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div>
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="stat-title">Stok Menipis</div>
                            <h2 class="stat-value text-danger"><?= $lowStockCount ?></h2>
                        </div>
                        <?php if ($lowStockCount > 0): ?>
                            <small class="text-danger fw-semibold mt-2 d-block"><i class="fas fa-arrow-right me-1"></i> Perlu Segera Restock</small>
                        <?php else: ?>
                            <small class="text-success mt-2 d-block"><i class="fas fa-check me-1"></i> Stok Aman</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RECENT ORDERS & LOW STOCK -->
            <div class="row g-4">
                <!-- Tabel Pesanan Terbaru -->
                <div class="col-xl-8">
                    <div class="card card-table h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold" style="color: #0A3D5C;"><i class="fas fa-history me-2"></i>Pesanan Terbaru</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>INFO PESANAN</th>
                                        <th>PELANGGAN</th>
                                        <th>ITEM DIPESAN</th>
                                        <th class="text-end">TOTAL</th>
                                        <th class="text-center">UBAH STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fas fa-inbox fs-1 d-block mb-3 text-light"></i>
                                                Belum ada pesanan masuk
                                            </td>
                                        </tr>
                                    <?php else: foreach ($recentOrders as $o): ?>
                                        <tr>
                                            <td>
                                                <a href="#" class="fw-bold text-decoration-none" style="color: var(--primary-color);"><?= htmlspecialchars($o['order_number']) ?></a>
                                                <div class="text-muted small mt-1"><i class="far fa-clock me-1"></i><?= date('d M, H:i', strtotime($o['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($o['customer_name']) ?></div>
                                                <div class="text-muted small"><i class="fab fa-whatsapp text-success me-1"></i><?= htmlspecialchars($o['customer_phone']) ?></div>
                                            </td>
                                            <td>
                                                <?php foreach ($o['items'] as $it): 
                                                    $pname = $it['product_name'] ?? 'Produk';
                                                    $vname = !empty($it['variant_name']) ? ' ('.$it['variant_name'].')' : '';
                                                ?>
                                                    <span class="item-chip"><i class="fas fa-box text-muted me-1"></i><?= htmlspecialchars($pname) ?><?= htmlspecialchars($vname) ?> <strong>x<?= (int)$it['qty'] ?></strong></span><br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td class="text-end fw-bold text-dark">
                                                Rp <?= number_format($o['total'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center">
                                                <!-- FORM DROPDOWN STATUS BADGE -->
                                                <form method="POST" class="status-form d-inline-block">
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                                                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    
                                                    <select name="new_status" class="form-select form-select-sm <?= getStatusClass($o['status']) ?>" 
                                                            onchange="this.form.submit()" style="width: 145px; margin: 0 auto;">
                                                        <option value="pending" <?= $o['status'] == 'pending' ? 'selected' : '' ?>>⏳ Menunggu</option>
                                                        <option value="confirmed" <?= $o['status'] == 'confirmed' ? 'selected' : '' ?>>🔄 Diproses</option>
                                                        <option value="shipped" <?= $o['status'] == 'shipped' ? 'selected' : '' ?>>
                                                            <?= $o['shipping_method'] === 'pickup' ? '📦 Siap Pickup' : '🚚 Dikirim' ?>
                                                        </option>
                                                        <option value="completed" <?= $o['status'] == 'completed' ? 'selected' : '' ?>>✅ Selesai</option>
                                                        <option value="cancelled" <?= $o['status'] == 'cancelled' ? 'selected' : '' ?>>❌ Batal</option>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Panel Stok Menipis -->
                <div class="col-xl-4">
                    <div class="card card-table h-100">
                        <div class="card-header">
                            <h5 class="mb-0 fw-bold" style="color: #0A3D5C;"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Restock Needed</h5>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <?php if (empty($lowStockProducts)): ?>
                                <div class="text-center text-muted py-5 my-auto">
                                    <i class="fas fa-check-circle text-success fs-1 d-block mb-3"></i>
                                    <h6 class="fw-bold">Stok Aman</h6>
                                    <p class="small mb-0">Tidak ada produk yang perlu direstock.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush flex-grow-1">
                                    <?php foreach ($lowStockProducts as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light rounded p-2 text-muted"><i class="fas fa-box"></i></div>
                                                <span class="fw-semibold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($p['name_id']) ?></span>
                                            </div>
                                            <span class="badge <?= $p['stock'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> rounded-pill px-3 py-2">
                                                Sisa <?= (int)$p['stock'] ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="p-3 border-top mt-auto">
                                    <a href="products.php" class="btn btn-outline-primary w-100 rounded-pill">
                                        <i class="fas fa-box me-1"></i> Kelola Stok
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>