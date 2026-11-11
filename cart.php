<?php
// ============================================================
// cart.php — Keranjang dengan Validasi Stok
// ============================================================

include_once 'config/database.php';
include_once 'includes/functions.php';

$current_lang = $_SESSION['lang'] ?? 'id';

// ------------------------------------------------------------
// Handle aksi (update, remove, clear)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $pid = (int)$_POST['product_id'];
        $vid = (int)($_POST['variant_id'] ?? 0);
        $qty = (int)$_POST['qty'];
        updateCartItem($pid, $vid, $qty);  // fungsi ini auto-cap ke stok DB
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['remove_item'])) {
        removeCartItem((int)$_POST['product_id'], (int)($_POST['variant_id'] ?? 0));
        header('Location: cart.php');
        exit;
    }
    if (isset($_POST['clear_cart'])) {
        clearCart();
        header('Location: cart.php');
        exit;
    }
}

// ------------------------------------------------------------
// Load cart + info stok
// ------------------------------------------------------------
$cart_items = getCartItemsDetail($pdo);
$total = getCartTotal($pdo);

$has_stock_issue = false;
foreach ($cart_items as &$ci) {
    $ci['db_stock']   = getDbStock((int)$ci['id'], (int)$ci['variant_id']);
    $ci['over_stock'] = ((int)$ci['qty'] > $ci['db_stock']);
    if ($ci['over_stock']) $has_stock_issue = true;
}
unset($ci);

$page_title = 'Keranjang - Kepri Punya Cerite';
include_once 'includes/header.php';
?>

<style>
    .cart-card       { border: none; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,.05); }
    .cart-item       { display: flex; gap: 1rem; padding: 1.25rem; border-bottom: 1px solid #f1f3f5; align-items: center; }
    .cart-item:last-child { border-bottom: none; }
    .cart-item-img   { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; flex-shrink: 0; }
    .cart-item-name  { font-weight: 600; font-size: 1rem; color: #0A3D5C; text-decoration: none; }
    .cart-item-name:hover { color: var(--primary-teal, #1A7A8C); }
    .cart-item-variant { font-size: .85rem; color: #6c757d; }
    .cart-item-stock { font-size: .8rem; }
    .cart-item-price { font-weight: 700; color: var(--accent-coral, #FF6B6B); }
    .cart-item-subtotal { font-weight: 800; color: var(--primary-teal, #1A7A8C); font-size: 1.05rem; }

    .qty-group { display: inline-flex; align-items: center; gap: 6px; }
    .qty-group input { width: 65px; text-align: center; }
    .qty-btn-sm {
        width: 32px; height: 32px; border-radius: 50%; border: 1px solid #dee2e6;
        background: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all .2s;
    }
    .qty-btn-sm:hover { background: #f1f3f5; }
    .qty-btn-sm:disabled { opacity: .4; cursor: not-allowed; }

    .summary-card { border: none; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,.05); position: sticky; top: 20px; }
    .summary-row  { display: flex; justify-content: space-between; margin-bottom: .6rem; }
    .summary-total { font-size: 1.35rem; font-weight: 800; color: var(--accent-coral, #FF6B6B); }

    .btn-checkout {
        background: var(--accent-gold, #FFC107); color: #000; border: none;
        border-radius: 50px; padding: 14px; font-weight: 700; width: 100%;
        transition: all .3s; font-size: 1rem;
    }
    .btn-checkout:hover { background: #e0a800; color: #000; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.15); }
    .btn-checkout:disabled { background: #adb5bd; color: #fff; cursor: not-allowed; transform: none; box-shadow: none; }

    .empty-cart { text-align: center; padding: 4rem 1rem; }
    .empty-cart i { font-size: 5rem; color: #dee2e6; margin-bottom: 1rem; }

    .stock-warn { background: #FFF3CD; border: 1px solid #FFECB5; border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .9rem; }

    @media (max-width: 575.98px) {
        .cart-item { flex-wrap: wrap; }
        .cart-item-img { width: 80px; height: 80px; }
    }
</style>

<section class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php"><?= t('home') ?></a></li>
            <li class="breadcrumb-item active"><?= t('cart') ?></li>
        </ol>
    </nav>

    <h2 class="section-title mb-4">
        <i class="fas fa-shopping-cart me-2"></i><?= t('cart') ?>
        <?php if (count($cart_items) > 0): ?>
            <span class="badge bg-secondary fs-6 align-middle ms-2"><?= getCartCount() ?> item</span>
        <?php endif; ?>
    </h2>

    <?php if (empty($cart_items)): ?>
        <div class="cart-card empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h4><?= t('empty_cart') ?></h4>
            <p class="text-muted mb-4"><?= $current_lang == 'id' ? 'Yuk mulai belanja produk Kepri pilihan!' : 'Start shopping Kepri products!' ?></p>
            <a href="products.php" class="btn btn-primary-custom rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i><?= t('continue_shopping') ?>
            </a>
        </div>
    <?php else: ?>

        <?php if ($has_stock_issue): ?>
            <div class="stock-warn">
                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                <strong><?= $current_lang == 'id' ? 'Stok berubah!' : 'Stock changed!' ?></strong>
                <?= $current_lang == 'id'
                    ? 'Beberapa item melebihi stok tersedia. Perbaiki jumlah di bawah untuk melanjutkan checkout.'
                    : 'Some items exceed available stock. Fix quantities below to continue checkout.' ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="cart-card">
                    <?php foreach ($cart_items as $ci):
                        $pid   = (int)$ci['id'];
                        $vid   = (int)$ci['variant_id'];
                        $pname = $current_lang == 'id' ? $ci['name_id'] : $ci['name_en'];
                        $vname = $current_lang == 'id' ? $ci['variant_name_id'] : $ci['variant_name_en'];
                        $unit_price = (float)$ci['price'] + (float)$ci['additional_price'];
                        $subtotal   = $unit_price * (int)$ci['qty'];
                        $img = !empty($ci['image'])
                            ? 'assets/uploads/products/' . $ci['image']
                            : 'https://via.placeholder.com/100x100/0A3D5C/FFFFFF?text=Kepri';
                    ?>
                    <div class="cart-item">
                        <img src="<?= e($img) ?>" alt="<?= e($pname) ?>" class="cart-item-img">

                        <div class="flex-grow-1 min-w-0">
                            <a href="product-detail.php?slug=<?= urlencode($ci['slug']) ?>" class="cart-item-name">
                                <?= e($pname) ?>
                            </a>
                            <?php if (!empty($vname)): ?>
                                <div class="cart-item-variant">
                                    <i class="fas fa-tag me-1"></i><?= e($vname) ?>
                                </div>
                            <?php endif; ?>

                            <div class="cart-item-stock <?= $ci['over_stock'] ? 'text-danger fw-bold' : 'text-muted' ?>">
                                <?php if ($ci['over_stock']): ?>
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <?= $current_lang == 'id'
                                        ? 'Stok tersisa hanya ' . $ci['db_stock'] . ' (Anda pesan ' . $ci['qty'] . ')'
                                        : 'Only ' . $ci['db_stock'] . ' left (you ordered ' . $ci['qty'] . ')' ?>
                                <?php elseif ($ci['db_stock'] <= 5): ?>
                                    <i class="fas fa-clock me-1 text-warning"></i>
                                    <?= $current_lang == 'id'
                                        ? 'Sisa stok: ' . $ci['db_stock'] . ' — buruan!'
                                        : 'Only ' . $ci['db_stock'] . ' left — hurry!' ?>
                                <?php else: ?>
                                    <i class="fas fa-check-circle me-1 text-success"></i>
                                    <?= $current_lang == 'id'
                                        ? 'Stok tersedia: ' . $ci['db_stock']
                                        : 'In stock: ' . $ci['db_stock'] ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-2 d-flex flex-wrap align-items-center gap-3">
                                <form method="POST" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                                    <input type="hidden" name="variant_id" value="<?= $vid ?>">
                                    <div class="qty-group">
                                        <button type="button" class="qty-btn-sm"
                                                onclick="stepQty(this, -1, <?= $ci['db_stock'] ?>)"
                                                <?= $ci['db_stock'] <= 0 ? 'disabled' : '' ?>>−</button>
                                        <input type="number" name="qty" class="form-control form-control-sm"
                                               value="<?= (int)$ci['qty'] ?>"
                                               min="1" max="<?= max(1, $ci['db_stock']) ?>"
                                               <?= $ci['db_stock'] <= 0 ? 'disabled' : '' ?>>
                                        <button type="button" class="qty-btn-sm"
                                                onclick="stepQty(this, 1, <?= $ci['db_stock'] ?>)"
                                                <?= $ci['db_stock'] <= 0 ? 'disabled' : '' ?>>+</button>
                                    </div>
                                    <button type="submit" name="update_qty" class="btn btn-sm btn-outline-primary"
                                            title="<?= $current_lang == 'id' ? 'Update' : 'Update' ?>">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                </form>

                                <form method="POST" onsubmit="return confirm('<?= $current_lang == 'id' ? 'Hapus item ini?' : 'Remove this item?' ?>')">
                                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                                    <input type="hidden" name="variant_id" value="<?= $vid ?>">
                                    <button type="submit" name="remove_item" class="btn btn-sm btn-link text-danger p-0">
                                        <i class="fas fa-trash me-1"></i><?= t('remove') ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="text-end ms-3">
                            <div class="cart-item-subtotal"><?= rupiah($subtotal) ?></div>
                            <small class="text-muted d-block"><?= rupiah($unit_price) ?> × <?= (int)$ci['qty'] ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <a href="products.php" class="btn btn-outline-secondary rounded-pill">
                        <i class="fas fa-arrow-left me-1"></i><?= t('continue_shopping') ?>
                    </a>
                    <form method="POST" onsubmit="return confirm('<?= $current_lang == 'id' ? 'Kosongkan seluruh keranjang?' : 'Clear entire cart?' ?>')">
                        <button type="submit" name="clear_cart" class="btn btn-link text-danger text-decoration-none">
                            <i class="fas fa-trash-alt me-1"></i>
                            <?= $current_lang == 'id' ? 'Kosongkan Keranjang' : 'Clear Cart' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card summary-card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-receipt me-2"></i><?= t('order_summary') ?>
                        </h5>

                        <div class="summary-row">
                            <span><?= t('subtotal') ?> (<?= getCartCount() ?> item)</span>
                            <strong><?= rupiah($total) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span class="text-muted small">
                                <?= $current_lang == 'id' ? 'Ongkir' : 'Shipping' ?>
                            </span>
                            <span class="text-muted small">
                                <?= $current_lang == 'id' ? 'Dihitung saat konfirmasi' : 'Calculated at confirmation' ?>
                            </span>
                        </div>

                        <hr>
                        <div class="summary-row mb-3">
                            <span class="fw-bold"><?= t('total') ?></span>
                            <span class="summary-total"><?= rupiah($total) ?></span>
                        </div>

                        <?php if ($has_stock_issue): ?>
                            <button class="btn btn-checkout" disabled>
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $current_lang == 'id' ? 'Perbaiki Stok Dulu' : 'Fix Stock First' ?>
                            </button>
                            <p class="text-muted small text-center mt-2 mb-0">
                                <?= $current_lang == 'id'
                                    ? 'Kurangi qty item yang melebihi stok.'
                                    : 'Reduce qty of items exceeding stock.' ?>
                            </p>
                        <?php else: ?>
                            <a href="checkout.php" class="btn btn-checkout">
                                <i class="fas fa-credit-card me-2"></i><?= t('checkout') ?>
                            </a>
                        <?php endif; ?>

                        <div class="mt-3 p-2 bg-light rounded-3 text-center small text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            <?= $current_lang == 'id' ? 'Transaksi aman via WhatsApp' : 'Secure checkout via WhatsApp' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
function stepQty(btn, delta, maxStock) {
    const wrap = btn.parentElement;
    const input = wrap.querySelector('input[name="qty"]');
    let val = parseInt(input.value) || 1;
    let max = Math.min(99, maxStock || 99);
    val += delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>

<?php include_once 'includes/footer.php'; ?>