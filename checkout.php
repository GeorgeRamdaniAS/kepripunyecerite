<?php
// ============================================================
// checkout.php — Checkout WhatsApp + INSERT Order + Kurangi Stok
// (Disesuaikan dengan struktur tabel orders bawaan project)
// ============================================================

include_once 'config/database.php';
include_once 'includes/functions.php';

$current_lang = $_SESSION['lang'] ?? 'id';

// Success state (setelah POST berhasil & redirect)
$checkout_success = null;
if (isset($_GET['success']) && isset($_SESSION['checkout_success'])) {
    $checkout_success = $_SESSION['checkout_success'];
}

// Kalau cart kosong & bukan success → balik ke cart
if (empty(getCart()) && !$checkout_success) {
    header('Location: cart.php');
    exit;
}

$cart_items = $checkout_success ? [] : getCartItemsDetail($pdo);
$total = $checkout_success ? $checkout_success['total'] : getCartTotal($pdo);

$errors = [];
$wa_link = '';

$store_address = getSetting($pdo, 'store_address');
$store_phone   = getSetting($pdo, 'store_phone');

// ------------------------------------------------------------
// Proses checkout
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama'] ?? '');
    $hp      = trim($_POST['hp'] ?? '');
    $method  = $_POST['shipping_method'] ?? 'pickup';
    $alamat  = trim($_POST['alamat'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (!in_array($method, ['pickup', 'courier'])) $method = 'pickup';

    // Validasi
    if (empty($nama)) $errors['nama'] = t('full_name') . ' wajib diisi';
    if (empty($hp)) {
        $errors['hp'] = t('phone_wa') . ' wajib diisi';
    } elseif (!preg_match('/^[0-9+\-\s]{8,15}$/', $hp)) {
        $errors['hp'] = 'Format nomor HP tidak valid';
    }
    if ($method === 'courier' && empty($alamat)) {
        $errors['alamat'] = t('address') . ' wajib diisi';
    }

    // Validasi stok sebelum simpan
    if (empty($errors)) {
        foreach ($cart_items as $item) {
            $pid = (int)$item['id'];
            $vid = (int)$item['variant_id'];
            $qty = (int)$item['qty'];
            $dbStock = getDbStock($pid, $vid);
            if ($dbStock < $qty) {
                $pn = $current_lang == 'id' ? $item['name_id'] : $item['name_en'];
                $errors['stock'] = $current_lang == 'id'
                    ? "Stok tidak mencukupi untuk \"$pn\". Tersisa: $dbStock"
                    : "Insufficient stock for \"$pn\". Only $dbStock left.";
                break;
            }
        }
    }

    if (empty($errors)) {
        // Bangun pesan WhatsApp
        $order_number = generateOrderNumber();
        $wa_message  = "*PESANAN BARU - KEPRI PUNYA CERITE*\n";
        $wa_message .= "No. Pesanan: *$order_number*\n\n";
        $wa_message .= "Nama: $nama\n";
        $wa_message .= "No HP: $hp\n";

        if ($method === 'pickup') {
            $wa_message .= "Metode: *Pickup di Toko*\n";
            $wa_message .= "Lokasi Pickup: $store_address\n";
        } else {
            $wa_message .= "Metode: *Dikirim via Kurir*\n";
            $wa_message .= "Alamat: $alamat\n";
        }
        if ($catatan) $wa_message .= "Catatan: $catatan\n";
        $wa_message .= "\n*Detail Pesanan:*\n";
        foreach ($cart_items as $item) {
            $pn = $current_lang == 'id' ? $item['name_id'] : $item['name_en'];
            $vn = $current_lang == 'id' ? $item['variant_name_id'] : $item['variant_name_en'];
            $unit_price = $item['price'] + $item['additional_price'];
            $subtotal_item = $unit_price * $item['qty'];
            $variant_suffix = !empty($vn) ? " ($vn)" : '';
            $wa_message .= "- {$pn}{$variant_suffix} x {$item['qty']} = " . rupiah($subtotal_item) . "\n";
        }
        $wa_message .= "\n*Total: " . rupiah($total) . "*";
        $wa_message .= "\n\nTerima kasih.";

        // ===== TRANSAKSI: INSERT ORDER + KURANGI STOK =====
        $pdo->beginTransaction();
        try {
            // --- PERSIAPAN DATA UNTUK KOLOM BAWAAN DB ---
            $items_json = json_encode($cart_items);
            $customer_address = $method === 'courier' ? $alamat : ($store_address ?: 'Pickup at Store');

            // 1) Insert ke orders (Mencakup semua kolom: baru & lama)
            $orderStmt = $pdo->prepare("
                INSERT INTO orders
                    (order_number, customer_name, customer_phone, customer_address, 
                     items_json, total_price, shipping_method, shipping_address, 
                     notes, total, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $orderStmt->execute([
                $order_number,
                $nama,
                $hp,
                $customer_address, // Untuk customer_address
                $items_json,       // Untuk items_json
                $total,            // Untuk total_price
                $method,
                $method === 'courier' ? $alamat : null,
                $catatan ?: null,
                $total,            // Untuk total (tambahan kita)
            ]);
            $order_id = (int)$pdo->lastInsertId();

            // 2) Insert ke order_items + kurangi stok
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, variant_name,
                     qty, price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($cart_items as $item) {
                $pid = (int)$item['id'];
                $vid = (int)$item['variant_id'];
                $qty = (int)$item['qty'];

                // Kurangi stok atomic (rollback jika gagal)
                if (!decreaseStockAtomic($pid, $vid, $qty)) {
                    $pn = $current_lang == 'id' ? $item['name_id'] : $item['name_en'];
                    throw new RuntimeException(
                        $current_lang == 'id'
                            ? "Stok tidak mencukupi untuk \"$pn\"."
                            : "Insufficient stock for \"$pn\"."
                    );
                }

                $pn = $current_lang == 'id' ? $item['name_id'] : $item['name_en'];
                $vn = $current_lang == 'id' ? $item['variant_name_id'] : $item['variant_name_en'];
                $unit_price = $item['price'] + $item['additional_price'];
                $subtotal_item = $unit_price * $qty;

                $itemStmt->execute([
                    $order_id,
                    $pid,
                    $vid ?: null,
                    $pn,
                    $vn ?: null,
                    $qty,
                    $unit_price,
                    $subtotal_item,
                ]);
            }

            $pdo->commit();

            // Build WA link
            $admin_phone = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : '6282385167381';
            $wa_link = buildWhatsAppLink($admin_phone, $wa_message);

            // Simpan flash untuk halaman sukses
            $_SESSION['checkout_success'] = [
                'order_id'     => $order_id,
                'order_number' => $order_number,
                'wa_link'      => $wa_link,
                'total'        => $total,
                'nama'         => $nama,
            ];
            clearCart();

            header('Location: checkout.php?success=1');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Checkout error: ' . $e->getMessage());
            $errors['stock'] = 'Gagal memproses pesanan: ' . $e->getMessage();
        }
    }
}

$page_title = 'Checkout - Kepri Punya Cerite';
include_once 'includes/header.php';
?>

<!-- SISA KODE HTML & JAVASCRIPT SAMA PERSIS SEPERTI SEBELUMNYA -->
<!-- (Silakan copy mulai dari <style> sampai include footer dari file checkout.php sebelumnya) -->

<style>
    .checkout-card { border: none; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,.05); }
    .checkout-card .card-body { padding: 2rem; }
    .shipping-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
    .shipping-option { position: relative; cursor: pointer; margin: 0; }
    .shipping-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
    .shipping-option-content { display: flex; flex-direction: column; align-items: center; text-align: center;
        padding: 1.25rem 1rem; border: 2px solid #e9ecef; border-radius: 16px; background: #fff;
        transition: all .25s; height: 100%; }
    .shipping-option:hover .shipping-option-content { border-color: #b8d4da; background: #f8fbfc; }
    .shipping-option input[type="radio"]:checked + .shipping-option-content {
        border-color: var(--primary-teal, #1A7A8C); background: rgba(26,122,140,.06);
        box-shadow: 0 4px 14px rgba(26,122,140,.15); }
    .shipping-option input[type="radio"]:checked + .shipping-option-content::after {
        content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
        position: absolute; top: 8px; right: 10px; color: var(--primary-teal, #1A7A8C); font-size: 1.1rem; }
    .shipping-icon { width: 46px; height: 46px; border-radius: 50%; background: rgba(26,122,140,.1);
        color: var(--primary-teal, #1A7A8C); display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; margin-bottom: .5rem; }
    .shipping-option input[type="radio"]:checked + .shipping-option-content .shipping-icon {
        background: var(--primary-teal, #1A7A8C); color: #fff; }
    .shipping-title { font-weight: 700; font-size: .95rem; color: #1E2A3A; margin-bottom: .15rem; }
    .shipping-desc { font-size: .78rem; color: #6c757d; line-height: 1.3; }
    .pickup-info-box { background: linear-gradient(135deg, #F4F9FB, #E8F4F7); border: 1px solid #D6E5EB;
        border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 1.5rem; display: flex;
        gap: .85rem; align-items: flex-start; }
    .pickup-info-box .icon-wrapper { flex-shrink: 0; width: 38px; height: 38px;
        background: var(--primary-teal, #1A7A8C); color: #fff; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .pickup-info-box .info-content { flex: 1; font-size: .88rem; color: #2a3f52; line-height: 1.5; }
    .btn-wa { background: #25D366; color: #fff; border: none; border-radius: 50px;
        padding: 12px 30px; font-weight: 600; width: 100%; transition: all .3s; text-decoration: none; display: inline-block; }
    .btn-wa:hover { background: #1DA851; color: #fff; transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,.15); }
    .btn-gold { background: var(--accent-gold, #FFC107); color: #000; border: none;
        border-radius: 50px; padding: 12px 30px; font-weight: 600; transition: all .3s; }
    .btn-gold:hover { background: #e0a800; color: #000; transform: translateY(-2px); }
    .summary-item { display: flex; justify-content: space-between; align-items: center;
        margin-bottom: .75rem; padding-bottom: .75rem; border-bottom: 1px solid #f1f3f5; }
    .summary-item:last-child { border-bottom: none; }
    .product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; margin-right: 10px; }
    .summary-product-info { flex: 1; }
    .summary-product-name { font-weight: 600; font-size: .95rem; }
    .summary-product-variant { font-size: .85rem; color: #6c757d; }
    .summary-product-qty { font-size: .85rem; color: #6c757d; }
    .form-control.is-invalid { border-color: #dc3545; }
    .invalid-feedback { display: block; }
    .success-card { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,.08);
        padding: 3rem 2rem; text-align: center; }
    @media (max-width: 575.98px) {
        .shipping-methods { grid-template-columns: 1fr; }
        .checkout-card .card-body { padding: 1.25rem; }
    }
</style>

<section class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php"><?= t('home') ?></a></li>
            <li class="breadcrumb-item"><a href="cart.php"><?= t('cart') ?></a></li>
            <li class="breadcrumb-item active"><?= t('checkout') ?></li>
        </ol>
    </nav>

    <?php if ($checkout_success): ?>
        <!-- ===== HALAMAN SUKSES ===== -->
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card success-card">
                    <i class="fas fa-check-circle text-success" style="font-size:5rem;"></i>
                    <h2 class="fw-bold mt-3"><?= $current_lang == 'id' ? 'Pesanan Berhasil!' : 'Order Placed!' ?></h2>
                    <p class="text-muted mb-1">
                        <?= $current_lang == 'id' ? 'No. Pesanan:' : 'Order No.:' ?>
                        <strong><?= e($checkout_success['order_number']) ?></strong>
                    </p>
                    <p class="text-muted">
                        <?= $current_lang == 'id' ? 'Nama:' : 'Name:' ?>
                        <strong><?= e($checkout_success['nama']) ?></strong>
                    </p>
                    <p class="fs-5 fw-bold mt-3">Total: <?= rupiah($checkout_success['total']) ?></p>
                    <p class="text-muted small mb-4">
                        <?= $current_lang == 'id'
                            ? 'Klik tombol di bawah untuk mengirim detail pesanan via WhatsApp ke admin.'
                            : 'Click the button below to send order details via WhatsApp to admin.' ?>
                    </p>
                    <a href="<?= e($checkout_success['wa_link']) ?>" target="_blank" class="btn btn-wa btn-lg mb-3">
                        <i class="fab fa-whatsapp me-2"></i><?= t('send_wa') ?>
                    </a>
                    <a href="products.php" class="btn btn-outline-secondary rounded-pill">
                        <i class="fas fa-arrow-left me-1"></i><?= t('continue_shopping') ?>
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- ===== FORM CHECKOUT ===== -->
        <h2 class="section-title mb-4">
            <i class="fas fa-shopping-bag me-2"></i><?= t('checkout') ?>
        </h2>

        <?php if (!empty($errors['stock'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?= e($errors['stock']) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card checkout-card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-user-edit me-2"></i><?= $current_lang == 'id' ? 'Data Pemesan' : 'Customer Info' ?>
                        </h5>

                        <form method="POST" id="checkoutForm" novalidate>
                            <div class="mb-3">
                                <label for="nama" class="form-label fw-semibold">
                                    <?= t('full_name') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama" id="nama"
                                       class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($_POST['nama'] ?? '') ?>" required
                                       placeholder="<?= $current_lang == 'id' ? 'Masukkan nama lengkap' : 'Full name' ?>">
                                <?php if (isset($errors['nama'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['nama']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="hp" class="form-label fw-semibold">
                                    <?= t('phone_wa') ?> <span class="text-danger">*</span>
                                </label>
                                <input type="tel" name="hp" id="hp"
                                       class="form-control <?= isset($errors['hp']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($_POST['hp'] ?? '') ?>" required
                                       placeholder="08xxxxxxxxxx" inputmode="numeric"
                                       pattern="[0-9+\-\s]{8,15}" maxlength="15">
                                <?php if (isset($errors['hp'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['hp']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold d-block mb-2">
                                    <?= $current_lang == 'id' ? 'Metode Pengambilan' : 'Delivery Method' ?>
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="shipping-methods">
                                    <label class="shipping-option">
                                        <input type="radio" name="shipping_method" value="pickup"
                                            <?= (($_POST['shipping_method'] ?? 'pickup') === 'pickup') ? 'checked' : '' ?>>
                                        <div class="shipping-option-content">
                                            <div class="shipping-icon"><i class="fas fa-store"></i></div>
                                            <div class="shipping-title"><?= $current_lang == 'id' ? 'Pickup di Toko' : 'Store Pickup' ?></div>
                                            <div class="shipping-desc"><?= $current_lang == 'id' ? 'Ambil langsung di lokasi kami' : 'Pick up at our store' ?></div>
                                        </div>
                                    </label>
                                    <label class="shipping-option">
                                        <input type="radio" name="shipping_method" value="courier"
                                            <?= (($_POST['shipping_method'] ?? '') === 'courier') ? 'checked' : '' ?>>
                                        <div class="shipping-option-content">
                                            <div class="shipping-icon"><i class="fas fa-truck"></i></div>
                                            <div class="shipping-title"><?= $current_lang == 'id' ? 'Dikirim via Kurir' : 'Courier Delivery' ?></div>
                                            <div class="shipping-desc"><?= $current_lang == 'id' ? 'Dikirim ke alamat Anda' : 'Delivered to your address' ?></div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="pickup-info-box" id="pickupInfo" style="display:none;">
                                <div class="icon-wrapper"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="info-content">
                                    <strong><?= $current_lang == 'id' ? 'Lokasi Pickup:' : 'Pickup Location:' ?></strong><br>
                                    <?= e($store_address) ?>
                                    <?php if (!empty($store_phone)): ?>
                                        <br><i class="fas fa-phone me-1 mt-1"></i>
                                        <a href="tel:<?= e($store_phone) ?>" class="text-decoration-none"><?= e($store_phone) ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3" id="addressField" style="display:none;">
                                <label for="alamat" class="form-label fw-semibold">
                                    <?= t('address') ?> <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat" id="alamat"
                                          class="form-control <?= isset($errors['alamat']) ? 'is-invalid' : '' ?>"
                                          rows="3"
                                          placeholder="<?= $current_lang == 'id' ? 'Alamat lengkap (jalan, no, RT/RW, kelurahan, kecamatan, kota, kode pos)' : 'Full address' ?>"><?= e($_POST['alamat'] ?? '') ?></textarea>
                                <?php if (isset($errors['alamat'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['alamat']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="catatan" class="form-label fw-semibold">
                                    <?= t('notes') ?>
                                    <span class="text-muted">(<?= $current_lang == 'id' ? 'opsional' : 'optional' ?>)</span>
                                </label>
                                <textarea name="catatan" id="catatan" class="form-control" rows="2"><?= e($_POST['catatan'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-gold btn-lg w-100 rounded-pill" id="btnSubmitCheckout">
                                <i class="fab fa-whatsapp me-2"></i>
                                <span class="btn-text"><?= t('process_order') ?></span>
                                <span class="spinner-border spinner-border-sm d-none"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card checkout-card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-receipt me-2"></i><?= t('order_summary') ?>
                        </h5>
                        <?php foreach ($cart_items as $item):
                            $pn = $current_lang == 'id' ? $item['name_id'] : $item['name_en'];
                            $vn = $current_lang == 'id' ? $item['variant_name_id'] : $item['variant_name_en'];
                            $unit_price = $item['price'] + $item['additional_price'];
                            $subtotal_item = $unit_price * $item['qty'];
                            $product_image = !empty($item['image'])
                                ? 'assets/uploads/products/' . $item['image']
                                : 'https://via.placeholder.com/50x50/0A3D5C/FFFFFF?text=Kepri';
                        ?>
                        <div class="summary-item">
                            <img src="<?= e($product_image) ?>" alt="<?= e($pn) ?>" class="product-thumb" loading="lazy">
                            <div class="summary-product-info">
                                <div class="summary-product-name"><?= e($pn) ?></div>
                                <?php if (!empty($vn)): ?>
                                    <div class="summary-product-variant"><?= e($vn) ?></div>
                                <?php endif; ?>
                                <div class="summary-product-qty"><?= (int)$item['qty'] ?> x <?= rupiah($unit_price) ?></div>
                            </div>
                            <span class="fw-semibold"><?= rupiah($subtotal_item) ?></span>
                        </div>
                        <?php endforeach; ?>

                        <hr class="my-3">
                        <div class="d-flex justify-content-between fs-5 fw-bold">
                            <span><?= t('total') ?></span>
                            <span><?= rupiah($total) ?></span>
                        </div>

                        <a href="cart.php" class="btn btn-outline-secondary btn-sm rounded-pill mt-3">
                            <i class="fas fa-arrow-left me-1"></i>
                            <?= $current_lang == 'id' ? 'Kembali ke keranjang' : 'Back to cart' ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('checkoutForm');
    if (!form) return;

    const submitBtn = document.getElementById('btnSubmitCheckout');
    const btnText = submitBtn?.querySelector('.btn-text');
    const spinner = submitBtn?.querySelector('.spinner-border');
    const hpInput = document.getElementById('hp');
    const pickupInfo = document.getElementById('pickupInfo');
    const addressField = document.getElementById('addressField');
    const alamatInput = document.getElementById('alamat');
    const methodRadios = document.querySelectorAll('input[name="shipping_method"]');

    function updateShippingUI() {
        const sel = document.querySelector('input[name="shipping_method"]:checked')?.value || 'pickup';
        if (sel === 'pickup') {
            pickupInfo.style.display = 'flex';
            addressField.style.display = 'none';
            if (alamatInput) alamatInput.required = false;
        } else {
            pickupInfo.style.display = 'none';
            addressField.style.display = 'block';
            if (alamatInput) alamatInput.required = true;
        }
    }
    methodRadios.forEach(r => r.addEventListener('change', updateShippingUI));
    updateShippingUI();

    if (hpInput) {
        hpInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+\-\s]/g, '');
            if (this.value.replace(/\D/g, '').length > 15) this.value = this.value.slice(0, 15);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback.dynamic').forEach(el => el.remove());

        let valid = true;
        const method = document.querySelector('input[name="shipping_method"]:checked')?.value || 'pickup';

        const namaInput = document.getElementById('nama');
        if (!namaInput.value.trim()) {
            namaInput.classList.add('is-invalid');
            namaInput.insertAdjacentHTML('afterend',
                '<div class="invalid-feedback dynamic"><?= t("full_name") ?> wajib diisi</div>');
            valid = false;
        }

        const hpValue = hpInput.value.trim();
        if (!hpValue || !/^[0-9+\-\s]{8,15}$/.test(hpValue)) {
            hpInput.classList.add('is-invalid');
            hpInput.insertAdjacentHTML('afterend',
                '<div class="invalid-feedback dynamic">Format nomor HP tidak valid (8–15 digit)</div>');
            valid = false;
        }

        if (method === 'courier' && !alamatInput.value.trim()) {
            alamatInput.classList.add('is-invalid');
            alamatInput.insertAdjacentHTML('afterend',
                '<div class="invalid-feedback dynamic"><?= t("address") ?> wajib diisi</div>');
            valid = false;
        }

        if (!valid) {
            const first = form.querySelector('.is-invalid');
            first?.focus();
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            if (btnText) btnText.textContent = '<?= $current_lang === "id" ? "Memproses..." : "Processing..." ?>';
            spinner?.classList.remove('d-none');
        }
        form.submit();
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>