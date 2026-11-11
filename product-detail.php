<?php
// ============================================================
// product-detail.php — Detail Produk + Stok
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include_once 'config/database.php';
include_once 'includes/functions.php';

$current_lang = $_SESSION['lang'] ?? 'id';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') { header('Location: products.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, c.name_id AS cat_name_id, c.name_en AS cat_name_en, c.slug AS cat_slug,
        (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.slug = ? LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $page_title = '404 - Produk Tidak Ditemukan';
    include_once 'includes/header.php';
    echo '<section class="container error-404 py-5 text-center">';
    echo '<h1 class="display-1 fw-bold">404</h1>';
    echo '<p class="lead">' . ($current_lang == 'id' ? 'Produk tidak ditemukan.' : 'Product not found.') . '</p>';
    echo '<a href="products.php" class="btn btn-primary-custom rounded-pill px-4">' . t('back_to_products') . '</a>';
    echo '</section>';
    include_once 'includes/footer.php';
    exit;
}

// Gambar
$images_stmt = $pdo->prepare("SELECT image_name, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
$images_stmt->execute([$product['id']]);
$product_images = $images_stmt->fetchAll();
if (empty($product_images)) $product_images = [['image_name' => '', 'is_primary' => 1]];

// Varian + STOK
$variant_stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
$variant_stmt->execute([$product['id']]);
$variants = $variant_stmt->fetchAll();

$available_by_variant = [];
$total_available = 0;

if (!empty($variants)) {
    foreach ($variants as $v) {
        $vid = (int)$v['id'];
        $available_by_variant[$vid] = getAvailableStock((int)$product['id'], $vid);
        $total_available += $available_by_variant[$vid];
    }
} else {
    $available_by_variant[0] = getAvailableStock((int)$product['id'], 0);
    $total_available = $available_by_variant[0];
}
$is_out_of_stock = ($total_available <= 0);

// Produk terkait
$related_stmt = $pdo->prepare("
    SELECT p.*, c.name_id AS cat_name_id, c.name_en AS cat_name_en,
        (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id != ?
    ORDER BY p.created_at DESC LIMIT 4
");
$related_stmt->execute([$product['category_id'], $product['id']]);
$related_products = $related_stmt->fetchAll();

// Reviews statis
$reviews = [
    ['name' => 'Budi Santoso', 'rating' => 5, 'date' => '2025-01-15',
     'text' => $current_lang == 'id' ? 'Produk sangat bagus, sesuai deskripsi. Pengiriman cepat!' : 'Great product, matches description. Fast shipping!'],
    ['name' => 'Siti Rahma', 'rating' => 4, 'date' => '2025-02-20',
     'text' => $current_lang == 'id' ? 'Kualitas bagus, hanya sedikit berbeda warna dari foto.' : 'Good quality, slightly different color from photo.'],
    ['name' => 'Ahmad Fauzi', 'rating' => 5, 'date' => '2025-03-05',
     'text' => $current_lang == 'id' ? 'Sangat puas! Akan beli lagi.' : 'Very satisfied! Will buy again.'],
];
$avg_rating = count($reviews) > 0 ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;

// POST add to cart
$detail_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if (!empty($variants) && $variant_id > 0) {
        $found = false;
        foreach ($variants as $v) if ((int)$v['id'] === $variant_id) { $found = true; break; }
        if (!$found) $detail_error = $current_lang == 'id' ? 'Varian tidak valid.' : 'Invalid variant.';
    } elseif (!empty($variants) && $variant_id === 0) {
        $detail_error = $current_lang == 'id' ? 'Silakan pilih varian.' : 'Please select a variant.';
    }

    if (!$detail_error && ($qty < 1 || $qty > 99)) {
        $detail_error = $current_lang == 'id' ? 'Jumlah harus 1–99.' : 'Quantity must be 1–99.';
    }

    if (!$detail_error) {
        $available = getAvailableStock((int)$product['id'], $variant_id);
        if ($qty > $available) {
            $detail_error = $current_lang == 'id'
                ? 'Stok tidak mencukupi. Tersisa: ' . $available
                : 'Insufficient stock. Only ' . $available . ' available.';
        }
    }

    if (!$detail_error) {
        try {
            if (!addToCart((int)$product['id'], $variant_id, $qty)) {
                $available = getAvailableStock((int)$product['id'], $variant_id);
                $detail_error = $current_lang == 'id'
                    ? 'Stok tidak mencukupi. Tersisa: ' . $available
                    : 'Insufficient stock. Only ' . $available . ' available.';
            }
        } catch (Throwable $e) {
            error_log('addToCart error: ' . $e->getMessage());
            $detail_error = 'Server error.';
        }
    }

    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        if ($detail_error) {
            echo json_encode(['success' => false, 'message' => $detail_error]);
        } else {
            echo json_encode([
                'success' => true,
                'cart_count' => getCartCount(),
                'message' => $current_lang == 'id' ? 'Berhasil ditambahkan ke keranjang!' : 'Added to cart successfully!'
            ]);
        }
        exit;
    } else {
        if (!$detail_error) { header('Location: cart.php'); exit; }
    }
}

// Variabel tampilan
$name = $current_lang === 'id' ? $product['name_id'] : $product['name_en'];
$description = $current_lang === 'id' ? $product['description_id'] : $product['description_en'];
$category = $current_lang === 'id' ? $product['cat_name_id'] : $product['cat_name_en'];
$category_slug = $product['cat_slug'];
$is_kaos = (stripos((string)$category, 'kaos') !== false || stripos((string)$category_slug, 'kaos') !== false);

$primary_image = !empty($product['primary_image'])
    ? 'assets/uploads/products/' . $product['primary_image']
    : 'https://via.placeholder.com/600x600/0A3D5C/FFFFFF?text=Kepri';

$gallery_images = [];
foreach ($product_images as $img) {
    $gallery_images[] = !empty($img['image_name'])
        ? 'assets/uploads/products/' . $img['image_name'] : $primary_image;
}

// Badge stok
if ($total_available <= 0) {
    $stock_badge_class = 'bg-danger';
    $stock_badge_label = $current_lang == 'id' ? 'Stok Habis' : 'Out of Stock';
} elseif ($total_available <= 5) {
    $stock_badge_class = 'bg-warning text-dark';
    $stock_badge_label = $current_lang == 'id' ? 'Stok Terbatas: ' . $total_available : 'Low Stock: ' . $total_available;
} else {
    $stock_badge_class = 'bg-success';
    $stock_badge_label = $current_lang == 'id' ? 'Stok: ' . $total_available : 'Stock: ' . $total_available;
}

$page_title = $name . ' - Kepri Punya Cerite';
$meta_description = substr(strip_tags((string)$description), 0, 160);

include_once 'includes/header.php';
?>

<style>
    .breadcrumb { font-size: .9rem; }
    .breadcrumb a { color: var(--primary-teal); text-decoration: none; }
    .breadcrumb a:hover { color: var(--primary-dark); }

    .gallery-main { border-radius: 20px; overflow: hidden; background: #f8f9fa; aspect-ratio: 1/1;
                    display: flex; align-items: center; justify-content: center; cursor: zoom-in; }
    .gallery-main img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
    .gallery-main:hover img { transform: scale(1.05); }
    .gallery-thumbs { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
    .gallery-thumbs img { width: 80px; height: 80px; object-fit: cover; border-radius: 12px;
                          cursor: pointer; border: 2px solid transparent; transition: all .2s; }
    .gallery-thumbs img.active { border-color: var(--primary-teal); }
    .gallery-thumbs img:hover { opacity: .8; }

    .product-title { font-weight: 800; color: var(--primary-dark); margin-bottom: .5rem; }
    .product-category-badge { background: rgba(26,122,140,.1); color: var(--primary-teal);
        font-weight: 600; padding: 5px 15px; border-radius: 30px; display: inline-block;
        font-size: .85rem; text-decoration: none; }
    .product-category-badge:hover { background: var(--primary-teal); color: #fff; }
    .product-price { font-size: 2rem; font-weight: 800; color: var(--accent-coral); margin: 1rem 0; }
    .product-desc  { color: #495057; line-height: 1.8; }
    .rating-stars  { color: var(--accent-gold); font-size: 1.1rem; }
    .rating-stars .far { color: #dee2e6; }
    .rating-number { font-weight: 700; color: var(--text-dark); }

    .variant-option { display: inline-block; margin-right: 10px; cursor: pointer; }
    .variant-option input[type="radio"] { display: none; }
    .variant-option label { display: inline-block; padding: 8px 20px; border: 2px solid var(--border-light);
        border-radius: 30px; font-weight: 500; transition: all .2s; cursor: pointer; }
    .variant-option input[type="radio"]:checked + label { border-color: var(--primary-teal);
        background: rgba(26,122,140,.1); color: var(--primary-teal); font-weight: 600; }
    .variant-option input[type="radio"]:disabled + label { opacity: .55; cursor: not-allowed; text-decoration: line-through; }
    .size-option label { min-width: 50px; text-align: center; padding: 10px 15px; font-weight: 700; }

    .qty-selector { display: inline-flex; align-items: center; gap: 15px; }
    .qty-btn { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #dee2e6;
        background: white; font-weight: bold; font-size: 1.2rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center; }
    .qty-btn:hover { background: #f1f3f5; }
    .qty-btn:disabled { opacity: .4; cursor: not-allowed; }
    .qty-input { width: 60px; text-align: center; border: 1px solid #dee2e6; border-radius: 10px;
        padding: 8px; font-weight: 600; }

    .btn-add-cart { background: var(--primary-teal); color: white; border: none; border-radius: 50px;
        padding: 12px 30px; font-weight: 600; width: 100%; transition: all .3s; }
    .btn-add-cart:hover { background: var(--primary-dark); color: #fff; transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,.15); }
    .btn-add-cart:disabled { background: #6c757d; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    .btn-wishlist { background: white; color: var(--text-dark); border: 1px solid #dee2e6;
        border-radius: 50px; padding: 12px 20px; font-weight: 500; width: 100%; }
    .btn-wishlist:hover { background: #f8f9fa; border-color: #adb5bd; }
    .stock-badge { font-size: .85rem; padding: 6px 14px; border-radius: 30px; font-weight: 600; }
    .related-card { transition: all .3s; }
    .related-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,.1); }

    @media (max-width: 768px) {
        .gallery-main { aspect-ratio: 4/3; }
        .product-price { font-size: 1.5rem; }
        .btn-add-cart, .btn-wishlist { font-size: .9rem; padding: 10px 20px; }
    }
</style>

<section class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php"><?= t('home') ?></a></li>
            <li class="breadcrumb-item"><a href="products.php"><?= t('menu') ?></a></li>
            <?php if ($category_slug): ?>
                <li class="breadcrumb-item"><a href="products.php?category=<?= urlencode($category_slug) ?>"><?= e($category) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($name) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="gallery-main" id="mainGallery">
                <img src="<?= e($gallery_images[0]) ?>" alt="<?= e($name) ?>" id="mainImage">
            </div>
            <?php if (count($gallery_images) > 1): ?>
            <div class="gallery-thumbs">
                <?php foreach ($gallery_images as $index => $img): ?>
                    <img src="<?= e($img) ?>" alt="<?= e($name) ?> thumbnail <?= $index + 1 ?>"
                         class="<?= $index === 0 ? 'active' : '' ?>"
                         onclick="changeMainImage(this.src, this)">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <span class="product-category-badge"><?= e($category) ?></span>
            <h1 class="product-title mt-2"><?= e($name) ?></h1>

            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                <div class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= round($avg_rating)): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <span class="rating-number"><?= number_format($avg_rating, 1) ?></span>
                <span class="text-muted">(<?= count($reviews) ?> <?= $current_lang == 'id' ? 'ulasan' : 'reviews' ?>)</span>
                <span class="badge <?= $stock_badge_class ?> stock-badge ms-auto">
                    <i class="fas fa-box me-1"></i><?= e($stock_badge_label) ?>
                </span>
            </div>

            <p class="product-price"><?= rupiah($product['price']) ?></p>
            <div class="product-desc mb-4"><?= nl2br(e($description)) ?></div>

            <?php if ($detail_error && !isset($_POST['ajax'])): ?>
                <div class="alert alert-danger"><?= e($detail_error) ?></div>
            <?php endif; ?>

            <?php if ($is_out_of_stock): ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $current_lang == 'id' ? 'Maaf, stok produk ini sedang habis.' : 'Sorry, this product is out of stock.' ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="addToCartForm">
                <?php if (!empty($variants)): ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-2">
                            <?= $is_kaos ? 'Pilih Ukuran' : t('variant') ?>
                        </label>
                        <div role="radiogroup">
                            <?php foreach ($variants as $index => $variant):
                                $vid = (int)$variant['id'];
                                $variant_name = $current_lang === 'id' ? $variant['variant_name_id'] : $variant['variant_name_en'];
                                $variant_price = (float)$variant['additional_price'];
                                $v_avail = $available_by_variant[$vid] ?? 0;
                                $v_out = ($v_avail <= 0);
                            ?>
                            <div class="variant-option <?= $is_kaos ? 'size-option' : '' ?>">
                                <input type="radio" name="variant_id" id="variant_<?= $vid ?>" value="<?= $vid ?>"
                                       data-stock="<?= $v_avail ?>"
                                       <?= ($index === 0 && !$v_out) ? 'checked' : '' ?>
                                       <?= $v_out ? 'disabled' : '' ?> required>
                                <label for="variant_<?= $vid ?>">
                                    <?= e($variant_name) ?>
                                    <?php if ($variant_price > 0): ?>
                                        <span class="text-muted small">(+<?= rupiah($variant_price) ?>)</span>
                                    <?php endif; ?>
                                    <span class="d-block small <?= $v_out ? 'text-danger' : 'text-muted' ?>">
                                        <?= $v_out ? 'Stok habis' : 'Stok: ' . $v_avail ?>
                                    </span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="variant_id" value="0">
                    <div class="mb-3 small text-muted">
                        <?= $current_lang == 'id' ? 'Tersedia' : 'Available' ?>:
                        <strong><?= $total_available ?></strong>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="form-label fw-bold mb-2" for="qtyInput"><?= t('qty') ?></label>
                    <div class="qty-selector">
                        <button type="button" class="qty-btn" onclick="decreaseQty()"
                                <?= $is_out_of_stock ? 'disabled' : '' ?>>−</button>
                        <input type="number" name="qty" id="qtyInput" class="qty-input" value="1"
                               min="1" max="<?= max(1, $total_available) ?>" <?= $is_out_of_stock ? 'disabled' : '' ?> required>
                        <button type="button" class="qty-btn" onclick="increaseQty()"
                                <?= $is_out_of_stock ? 'disabled' : '' ?>>+</button>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-8">
                        <button type="submit" name="add_to_cart" class="btn btn-add-cart" id="btnAddToCart"
                                <?= $is_out_of_stock ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <span class="btn-text">
                                <?= $is_out_of_stock
                                    ? ($current_lang == 'id' ? 'Stok Habis' : 'Out of Stock')
                                    : t('add_to_cart') ?>
                            </span>
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-wishlist" id="btnWishlist">
                            <i class="far fa-heart me-1"></i> <span><?= $current_lang == 'id' ? 'Simpan' : 'Save' ?></span>
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-4 p-3 bg-light rounded-3">
                <div class="row text-center">
                    <div class="col-4">
                        <i class="fas fa-truck text-primary fs-4"></i>
                        <div class="small mt-1"><?= $current_lang == 'id' ? 'Pengiriman Cepat' : 'Fast Shipping' ?></div>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-shield-alt text-primary fs-4"></i>
                        <div class="small mt-1"><?= $current_lang == 'id' ? 'Kualitas Terjamin' : 'Quality Guaranteed' ?></div>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-undo-alt text-primary fs-4"></i>
                        <div class="small mt-1"><?= $current_lang == 'id' ? 'Retur Mudah' : 'Easy Return' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($related_products) > 0): ?>
    <div class="mt-5">
        <h3 class="section-title mb-4"><?= $current_lang == 'id' ? 'Produk Terkait' : 'Related Products' ?></h3>
        <div class="row g-4">
            <?php foreach ($related_products as $rp):
                $rp_name = $current_lang == 'id' ? $rp['name_id'] : $rp['name_en'];
                $rp_image = !empty($rp['image']) ? 'assets/uploads/products/' . $rp['image'] : 'https://via.placeholder.com/300x250/0A3D5C/FFFFFF?text=Kepri';
                $rp_stock = getTotalProductStock((int)$rp['id']);
            ?>
            <div class="col-6 col-md-3">
                <div class="product-card related-card position-relative">
                    <?php if ($rp_stock <= 0): ?>
                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">Habis</span>
                    <?php endif; ?>
                    <div class="product-image"><img src="<?= e($rp_image) ?>" alt="<?= e($rp_name) ?>" loading="lazy"></div>
                    <div class="card-body">
                        <div class="product-category"><?= e($current_lang == 'id' ? $rp['cat_name_id'] : $rp['cat_name_en']) ?></div>
                        <h5 class="product-title"><?= e($rp_name) ?></h5>
                        <div class="product-price"><?= rupiah($rp['price']) ?></div>
                        <a href="product-detail.php?slug=<?= urlencode($rp['slug']) ?>" class="btn btn-view-detail"><?= t('view_detail') ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<script>
    window.DEBUG_CART = false;

    function changeMainImage(src, thumb) {
        document.getElementById('mainImage').src = src;
        document.querySelectorAll('.gallery-thumbs img').forEach(i => i.classList.remove('active'));
        thumb.classList.add('active');
    }

    function getCurrentMaxStock() {
        const sel = document.querySelector('input[name="variant_id"]:checked');
        if (sel) return parseInt(sel.dataset.stock) || 0;
        const input = document.getElementById('qtyInput');
        return parseInt(input?.max) || 1;
    }

    function updateQtyMax() {
        const max = getCurrentMaxStock();
        const input = document.getElementById('qtyInput');
        const btn = document.getElementById('btnAddToCart');
        if (!input) return;
        input.max = Math.max(1, max);
        if (parseInt(input.value) > max) input.value = Math.max(1, max);
        if (parseInt(input.value) < 1) input.value = 1;
        if (btn) {
            const disabled = max <= 0;
            btn.disabled = disabled;
            btn.querySelector('.btn-text').textContent = disabled
                ? '<?= $current_lang === "id" ? "Stok Habis" : "Out of Stock" ?>'
                : '<?= t("add_to_cart") ?>';
        }
    }

    function increaseQty() {
        const i = document.getElementById('qtyInput');
        const v = parseInt(i.value) || 1;
        if (v < Math.min(99, getCurrentMaxStock())) i.value = v + 1;
    }
    function decreaseQty() {
        const i = document.getElementById('qtyInput');
        const v = parseInt(i.value) || 1;
        if (v > 1) i.value = v - 1;
    }
    document.getElementById('qtyInput')?.addEventListener('change', function () {
        const max = getCurrentMaxStock();
        let v = parseInt(this.value) || 1;
        if (v < 1) this.value = 1;
        if (v > Math.min(99, max)) this.value = Math.min(99, max);
    });
    document.querySelectorAll('input[name="variant_id"]').forEach(r => r.addEventListener('change', updateQtyMax));

    function updateCartBadge(count) {
        ['#cart-count','#cartCount','[data-cart-count]','.cart-badge','.cart-count'].forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                el.textContent = count;
                if (count > 0) { el.classList.remove('d-none'); el.style.display = ''; }
                else el.classList.add('d-none');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateQtyMax();

        const form = document.getElementById('addToCartForm');
        const btnSubmit = document.getElementById('btnAddToCart');
        const btnText = btnSubmit?.querySelector('.btn-text');
        const spinner = btnSubmit?.querySelector('.spinner-border');

        form?.addEventListener('submit', function (e) {
            e.preventDefault();
            if (btnSubmit.disabled) return;

            btnSubmit.disabled = true;
            if (btnText) btnText.textContent = '<?= $current_lang === "id" ? "Memproses..." : "Processing..." ?>';
            spinner?.classList.remove('d-none');

            const fd = new FormData(form);
            fd.append('add_to_cart', '1');
            fd.append('ajax', '1');

            fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text().then(txt => {
                    try { return JSON.parse(txt); }
                    catch { throw new Error('Non-JSON: ' + txt.slice(0, 200)); }
                }))
                .then(data => {
                    if (data.success) {
                        updateCartBadge(data.cart_count ?? 0);
                        showToast(data.message || 'OK', 'success');

                        // Update stok di display
                        const sel = document.querySelector('input[name="variant_id"]:checked');
                        const addedQty = parseInt(document.getElementById('qtyInput').value) || 1;
                        if (sel) {
                            const cur = parseInt(sel.dataset.stock) || 0;
                            const newStock = Math.max(0, cur - addedQty);
                            sel.dataset.stock = newStock;
                            const label = sel.nextElementSibling;
                            const stockSpan = label?.querySelector('.small');
                            if (stockSpan) {
                                stockSpan.textContent = newStock > 0 ? 'Stok: ' + newStock : 'Stok habis';
                                stockSpan.className = 'd-block small ' + (newStock > 0 ? 'text-muted' : 'text-danger');
                            }
                            if (newStock <= 0) sel.disabled = true;
                        }
                        updateQtyMax();
                    } else {
                        showToast(data.message || 'Error', 'danger');
                    }
                })
                .catch(err => showToast(err.message || 'Network error', 'danger'))
                .finally(() => {
                    btnSubmit.disabled = false;
                    if (btnText) btnText.textContent = '<?= t("add_to_cart") ?>';
                    spinner?.classList.add('d-none');
                });
        });

        document.getElementById('btnWishlist')?.addEventListener('click', function () {
            alert('<?= $current_lang === "id" ? "Fitur wishlist akan segera hadir!" : "Wishlist feature coming soon!" ?>');
        });

        function showToast(message, type = 'success') {
            let c = document.querySelector('.toast-container');
            if (!c) {
                c = document.createElement('div');
                c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(c);
            }
            const el = document.createElement('div');
            el.className = `toast align-items-center text-white ${type === 'success' ? 'bg-success' : 'bg-danger'} border-0`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            c.appendChild(el);
            new bootstrap.Toast(el, { delay: 3000 }).show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        }
    });
</script>

<?php include_once 'includes/footer.php'; ?>