<?php
// ============================================================
// admin/products_form.php — Tambah/Edit Produk + Stok + Varian
// ============================================================

session_start();
if(empty($_SESSION['admin_logged'])) { header("Location: login.php"); exit; }
include_once '../config/database.php';
include_once '../includes/functions.php';

$is_edit = isset($_GET['id']) && (int)$_GET['id'] > 0;
$product_id = $is_edit ? (int)$_GET['id'] : 0;

$errors = [];
$categories = $pdo->query("SELECT * FROM categories ORDER BY name_id")->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------
// POST: simpan
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_id  = trim($_POST['name_id'] ?? '');
    $name_en  = trim($_POST['name_en'] ?? '');
    $slug     = trim($_POST['slug'] ?? '');
    $desc_id  = trim($_POST['description_id'] ?? '');
    $desc_en  = trim($_POST['description_en'] ?? '');
    $cat_id   = (int)($_POST['category_id'] ?? 0);
    $price    = (float)($_POST['price'] ?? 0);
    $stock    = max(0, (int)($_POST['stock'] ?? 0));
    $featured = isset($_POST['is_featured']) ? 1 : 0;

    if ($name_id === '') $errors[] = 'Nama (ID) wajib diisi.';
    if ($name_en === '') $errors[] = 'Nama (EN) wajib diisi.';
    if ($cat_id <= 0)    $errors[] = 'Kategori wajib dipilih.';
    if ($price < 0)      $errors[] = 'Harga tidak valid.';

    if ($slug === '') $slug = createSlug($name_id);
    if (!$errors) {
        $sql = "SELECT id FROM products WHERE slug = ?" . ($is_edit ? " AND id != ?" : "");
        $chk = $pdo->prepare($sql);
        $chk->execute($is_edit ? [$slug, $product_id] : [$slug]);
        if ($chk->fetch()) $errors[] = 'Slug sudah dipakai produk lain.';
    }

    // Varian dari POST
    $variant_ids      = $_POST['variant_id']      ?? [];
    $variant_name_id  = $_POST['variant_name_id'] ?? [];
    $variant_name_en  = $_POST['variant_name_en'] ?? [];
    $variant_price    = $_POST['variant_price']   ?? [];
    $variant_stock    = $_POST['variant_stock']   ?? [];

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE products SET
                        name_id=?, name_en=?, slug=?, description_id=?, description_en=?,
                        category_id=?, price=?, stock=?, is_featured=?
                    WHERE id=?
                ");
                $stmt->execute([$name_id, $name_en, $slug, $desc_id, $desc_en,
                                $cat_id, $price, $stock, $featured, $product_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products
                        (name_id, name_en, slug, description_id, description_en,
                         category_id, price, stock, is_featured, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,NOW())
                ");
                $stmt->execute([$name_id, $name_en, $slug, $desc_id, $desc_en,
                                $cat_id, $price, $stock, $featured]);
                $product_id = (int)$pdo->lastInsertId();
            }

            // Simpan varian
            foreach ($variant_name_id as $i => $vname_id) {
                $vname_id = trim($vname_id);
                $vname_en = trim($variant_name_en[$i] ?? '');
                $vid      = (int)($variant_ids[$i] ?? 0);
                $vprice   = (float)($variant_price[$i] ?? 0);
                $vstock   = max(0, (int)($variant_stock[$i] ?? 0));

                if ($vname_id === '') continue;

                if ($vid > 0) {
                    $pdo->prepare("
                        UPDATE product_variants SET
                            variant_name_id=?, variant_name_en=?,
                            additional_price=?, stock=?
                        WHERE id=? AND product_id=?
                    ")->execute([$vname_id, $vname_en, $vprice, $vstock, $vid, $product_id]);
                } else {
                    $pdo->prepare("
                        INSERT INTO product_variants
                            (product_id, variant_name_id, variant_name_en, additional_price, stock)
                        VALUES (?,?,?,?,?)
                    ")->execute([$product_id, $vname_id, $vname_en, $vprice, $vstock]);
                }
            }

            // Handle hapus varian
            if (!empty($_POST['delete_variant'])) {
                foreach ($_POST['delete_variant'] as $did) {
                    $pdo->prepare("DELETE FROM product_variants WHERE id=? AND product_id=?")
                        ->execute([(int)$did, $product_id]);
                }
            }

            // Upload gambar utama baru
            if (!empty($_FILES['image']['name'])) {
                $uploaded = uploadImage($_FILES['image'], '../assets/uploads/products/');
                if ($uploaded) {
                    // Set semua primary=0 dulu, lalu insert baru primary=1
                    $pdo->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=?")
                        ->execute([$product_id]);
                    $pdo->prepare("INSERT INTO product_images (product_id, image_name, is_primary) VALUES (?,?,1)")
                        ->execute([$product_id, $uploaded]);
                }
            }

            $pdo->commit();
            header('Location: products.php?msg=' . urlencode('Produk berhasil disimpan'));
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Save product: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}

// ------------------------------------------------------------
// Load data
// ------------------------------------------------------------
$product = [
    'name_id' => '', 'name_en' => '', 'slug' => '',
    'description_id' => '', 'description_en' => '',
    'category_id' => 0, 'price' => 0, 'stock' => 0, 'is_featured' => 0,
];
$variants = [];
$primary_image = null;

if ($is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) { header('Location: products.php'); exit; }

    $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $vStmt->execute([$product_id]);
    $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    $imgStmt = $pdo->prepare("SELECT image_name FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $imgStmt->execute([$product_id]);
    $primary_image = $imgStmt->fetchColumn();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = [
        'name_id' => $_POST['name_id'] ?? '', 'name_en' => $_POST['name_en'] ?? '',
        'slug' => $_POST['slug'] ?? '', 'description_id' => $_POST['description_id'] ?? '',
        'description_en' => $_POST['description_en'] ?? '',
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'price' => (float)($_POST['price'] ?? 0),
        'stock' => (int)($_POST['stock'] ?? 0),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    ];
    // repopulate varian
    $variant_ids = $_POST['variant_id'] ?? [];
    $vnames_id = $_POST['variant_name_id'] ?? [];
    $vnames_en = $_POST['variant_name_en'] ?? [];
    $vprices   = $_POST['variant_price'] ?? [];
    $vstocks   = $_POST['variant_stock'] ?? [];
    foreach ($vnames_id as $i => $vn_id) {
        if (trim($vn_id) === '') continue;
        $variants[] = [
            'id' => (int)($variant_ids[$i] ?? 0),
            'variant_name_id' => $vn_id,
            'variant_name_en' => $vnames_en[$i] ?? '',
            'additional_price' => (float)($vprices[$i] ?? 0),
            'stock' => (int)($vstocks[$i] ?? 0),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $is_edit ? 'Edit' : 'Tambah' ?> Produk - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: #f4f7f9; font-family: 'Poppins', sans-serif; }
        .sidebar { background: #0A3D5C; min-height: 100vh; padding: 20px; border-radius: 0 20px 20px 0; }
        .sidebar h5 { color: #D4A24C; font-weight: 700; }
        .sidebar a { color: rgba(255,255,255,.7); display: block; padding: 12px 20px;
                     border-radius: 12px; text-decoration: none; transition: .3s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.1); color: white; }
        .sidebar a.text-danger:hover { background: rgba(220,53,69,.2); }
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
                <h2 class="fw-bold">
                    <i class="fas fa-<?= $is_edit ? 'pen' : 'plus' ?> me-2"></i>
                    <?= $is_edit ? 'Edit Produk' : 'Tambah Produk' ?>
                </h2>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Kembali
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white"><strong>Informasi Produk</strong></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nama (ID) *</label>
                                        <input type="text" name="name_id" class="form-control"
                                               value="<?= htmlspecialchars($product['name_id']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nama (EN) *</label>
                                        <input type="text" name="name_en" class="form-control"
                                               value="<?= htmlspecialchars($product['name_en']) ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Slug</label>
                                        <input type="text" name="slug" class="form-control"
                                               value="<?= htmlspecialchars($product['slug']) ?>"
                                               placeholder="auto-generate dari nama jika kosong">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kategori *</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">— Pilih —</option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= $c['id'] ?>"
                                                    <?= (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['name_id']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Harga (Rp) *</label>
                                        <input type="number" name="price" class="form-control" step="0.01" min="0"
                                               value="<?= (float)$product['price'] ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Deskripsi (ID)</label>
                                        <textarea name="description_id" rows="3" class="form-control"><?= htmlspecialchars($product['description_id']) ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Deskripsi (EN)</label>
                                        <textarea name="description_en" rows="3" class="form-control"><?= htmlspecialchars($product['description_en']) ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_featured"
                                                   id="is_featured" value="1"
                                                   <?= (int)$product['is_featured'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_featured">Produk Unggulan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <strong>Varian Produk</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addVariantRow()">
                                    <i class="fas fa-plus"></i> Tambah Varian
                                </button>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    Kosongkan jika produk tidak memiliki varian.
                                    Jika varian diisi, stok produk diambil dari total stok varian.
                                </p>
                                <div class="table-responsive">
                                    <table class="table align-middle" id="variantTable">
                                        <thead>
                                            <tr>
                                                <th>Nama ID</th>
                                                <th>Nama EN</th>
                                                <th style="width:130px;">Harga +</th>
                                                <th style="width:100px;">Stok</th>
                                                <th style="width:60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($variants)): foreach ($variants as $v): ?>
                                                <tr>
                                                    <input type="hidden" name="variant_id[]" value="<?= (int)$v['id'] ?>">
                                                    <td><input type="text" name="variant_name_id[]" class="form-control form-control-sm"
                                                               value="<?= htmlspecialchars($v['variant_name_id']) ?>"></td>
                                                    <td><input type="text" name="variant_name_en[]" class="form-control form-control-sm"
                                                               value="<?= htmlspecialchars($v['variant_name_en']) ?>"></td>
                                                    <td><input type="number" name="variant_price[]" step="0.01" min="0"
                                                               class="form-control form-control-sm"
                                                               value="<?= (float)$v['additional_price'] ?>"></td>
                                                    <td><input type="number" name="variant_stock[]" min="0"
                                                               class="form-control form-control-sm"
                                                               value="<?= (int)($v['stock'] ?? 0) ?>"></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="removeVariantRow(this)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 rounded-4 mb-3">
                            <div class="card-header bg-white"><strong>Stok & Gambar</strong></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Stok Produk</label>
                                    <input type="number" name="stock" class="form-control" min="0"
                                           value="<?= (int)$product['stock'] ?>">
                                    <small class="text-muted d-block mt-1">
                                        Diabaikan jika produk memiliki varian.
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gambar Utama</label>
                                    <?php if ($primary_image): ?>
                                        <div class="mb-2">
                                            <img src="../assets/uploads/products/<?= htmlspecialchars($primary_image) ?>"
                                                 alt="" class="img-thumbnail" style="max-height:120px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <small class="text-muted d-block mt-1">
                                        Format: JPG, PNG, WEBP. Maks 2MB.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Simpan
                            </button>
                            <a href="products.php" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="variantRowTemplate">
    <tr>
        <input type="hidden" name="variant_id[]" value="0">
        <td><input type="text" name="variant_name_id[]" class="form-control form-control-sm"></td>
        <td><input type="text" name="variant_name_en[]" class="form-control form-control-sm"></td>
        <td><input type="number" name="variant_price[]" step="0.01" min="0" value="0" class="form-control form-control-sm"></td>
        <td><input type="number" name="variant_stock[]" min="0" value="0" class="form-control form-control-sm"></td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeVariantRow(this)">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<script>
function addVariantRow() {
    const tpl = document.getElementById('variantRowTemplate').content.cloneNode(true);
    document.querySelector('#variantTable tbody').appendChild(tpl);
}
function removeVariantRow(btn) {
    const tr = btn.closest('tr');
    const vid = tr.querySelector('input[name="variant_id[]"]').value;
    if (vid !== '0') {
        if (!confirm('Hapus varian ini? (disimpan saat klik Simpan)')) return;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'delete_variant[]';
        hidden.value = vid;
        btn.closest('form').appendChild(hidden);
    }
    tr.remove();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>