<?php
// includes/functions.php
// Mendukung stok, validasi cart, produk tanpa/punya varian

if (session_status() === PHP_SESSION_NONE) session_start();

// ===== BAHASA =====
if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'id';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['id', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function t($key) {
    $lang = $_SESSION['lang'];
    $texts = [
        'home' => ['id' => 'Beranda', 'en' => 'Home'],
        'menu' => ['id' => 'Menu', 'en' => 'Menu'],
        'keranjang' => ['id' => 'Keranjang', 'en' => 'Cart'],
        'contact' => ['id' => 'Kontak', 'en' => 'Contact'],
        'about' => ['id' => 'Tentang Kami', 'en' => 'About Us'],
        'featured_products' => ['id' => 'Produk Unggulan', 'en' => 'Featured Products'],
        'view_detail' => ['id' => 'Lihat Detail', 'en' => 'View Detail'],
        'add_to_cart' => ['id' => 'Tambah ke Keranjang', 'en' => 'Add to Cart'],
        'buy_now' => ['id' => 'Beli Sekarang', 'en' => 'Buy Now'],
        'category' => ['id' => 'Kategori', 'en' => 'Category'],
        'price' => ['id' => 'Harga', 'en' => 'Price'],
        'stock' => ['id' => 'Stok', 'en' => 'Stock'],
        'out_of_stock' => ['id' => 'Stok Habis', 'en' => 'Out of Stock'],
        'available' => ['id' => 'Tersedia', 'en' => 'Available'],
        'variant' => ['id' => 'Pilih Varian', 'en' => 'Select Variant'],
        'qty' => ['id' => 'Jumlah', 'en' => 'Quantity'],
        'checkout' => ['id' => 'Checkout', 'en' => 'Checkout'],
        'name' => ['id' => 'Nama Lengkap', 'en' => 'Full Name'],
        'phone' => ['id' => 'Nomor HP', 'en' => 'Phone Number'],
        'address' => ['id' => 'Alamat', 'en' => 'Address'],
        'notes' => ['id' => 'Catatan Tambahan', 'en' => 'Additional Notes'],
        'send_order' => ['id' => 'Kirim Pesanan', 'en' => 'Send Order'],
        'total' => ['id' => 'Total', 'en' => 'Total'],
        'subtotal' => ['id' => 'Subtotal', 'en' => 'Subtotal'],
        'empty_cart' => ['id' => 'Keranjang masih kosong', 'en' => 'Cart is empty'],
        'continue_shopping' => ['id' => 'Lanjutkan Belanja', 'en' => 'Continue Shopping'],
        'order_success' => ['id' => 'Pesanan Berhasil!', 'en' => 'Order Success!'],
        'admin_panel' => ['id' => 'Panel Admin', 'en' => 'Admin Panel'],
        'login' => ['id' => 'Masuk', 'en' => 'Login'],
        'logout' => ['id' => 'Keluar', 'en' => 'Logout'],
        'manage_products' => ['id' => 'Kelola Produk', 'en' => 'Manage Products'],
        'save' => ['id' => 'Simpan', 'en' => 'Save'],
        'delete' => ['id' => 'Hapus', 'en' => 'Delete'],
        'edit' => ['id' => 'Edit', 'en' => 'Edit'],
        'no_products' => ['id' => 'Belum ada produk', 'en' => 'No products yet'],
        'all_categories' => ['id' => 'Semua Kategori', 'en' => 'All Categories'],
        'search' => ['id' => 'Cari produk...', 'en' => 'Search products...'],
        'cart' => ['id' => 'Keranjang', 'en' => 'Cart'],
        'full_name' => ['id' => 'Nama Lengkap', 'en' => 'Full Name'],
        'phone_wa' => ['id' => 'Nomor WhatsApp', 'en' => 'WhatsApp Number'],
        'order_summary' => ['id' => 'Ringkasan Pesanan', 'en' => 'Order Summary'],
        'process_order' => ['id' => 'Proses Pesanan', 'en' => 'Process Order'],
        'send_wa' => ['id' => 'Kirim via WhatsApp', 'en' => 'Send via WhatsApp'],
        'back_to_products' => ['id' => 'Kembali ke Produk', 'en' => 'Back to Products'],
        'remove' => ['id' => 'Hapus', 'en' => 'Remove'],
        'update' => ['id' => 'Update', 'en' => 'Update'],
    ];
    return $texts[$key][$lang] ?? $key;
}

// ============================================================
// CART
// ============================================================

function getCart() {
    return $_SESSION['cart'] ?? [];
}

function getCartCount() {
    $count = 0;
    foreach (getCart() as $item) $count += (int)($item['qty'] ?? 0);
    return $count;
}

/**
 * Tambah ke cart DENGAN validasi stok.
 * Return true sukses, false jika stok tidak cukup.
 */
function addToCart($product_id, $variant_id, $qty = 1) {
    $product_id = (int)$product_id;
    $variant_id = (int)$variant_id;
    $qty = (int)$qty;
    if ($product_id < 1 || $qty < 1) return false;

    if (!canFulfillStock($product_id, $variant_id, $qty)) return false;

    $key = $product_id . '-' . $variant_id;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
        if ($_SESSION['cart'][$key]['qty'] > 99) $_SESSION['cart'][$key]['qty'] = 99;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $product_id,
            'variant_id' => $variant_id,
            'qty' => $qty
        ];
    }
    return true;
}

function removeCartItem($product_id, $variant_id) {
    $key = (int)$product_id . '-' . (int)$variant_id;
    unset($_SESSION['cart'][$key]);
}

/**
 * Update qty. Return true jika valid. Kalau qty melebihi stok,
 * otomatis di-cap ke stok tersedia (return false menandakan di-cap).
 */
function updateCartItem($product_id, $variant_id, $qty) {
    $key = (int)$product_id . '-' . (int)$variant_id;
    if (!isset($_SESSION['cart'][$key])) return false;

    $qty = (int)$qty;
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
        return true;
    }

    $dbStock = getDbStock($product_id, $variant_id);
    if ($qty > $dbStock) {
        $qty = $dbStock;
    }
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
        return true;
    }
    $_SESSION['cart'][$key]['qty'] = min($qty, 99);
    return ($_SESSION['cart'][$key]['qty'] === (int)$_POST['qty'] || true);
}

function clearCart() {
    $_SESSION['cart'] = [];
}

/**
 * Detail cart lengkap + variant_id (untuk stock decrement).
 */
function getCartItemsDetail($pdo) {
    $cart = getCart();
    $items = [];
    foreach ($cart as $key => $item) {
        $product_id = (int)$item['product_id'];
        $variant_id = (int)$item['variant_id'];

        if ($variant_id > 0) {
            $stmt = $pdo->prepare("
                SELECT p.*, pv.variant_name_id, pv.variant_name_en, pv.additional_price,
                       (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                FROM products p
                LEFT JOIN product_variants pv ON pv.id = ? AND pv.product_id = p.id
                WHERE p.id = ? AND pv.id IS NOT NULL
            ");
            $stmt->execute([$variant_id, $product_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       NULL as variant_name_id, NULL as variant_name_en, 0 as additional_price,
                       (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
                FROM products p
                WHERE p.id = ?
            ");
            $stmt->execute([$product_id]);
        }

        $product = $stmt->fetch();
        if ($product) {
            $product['cart_key']  = $key;
            $product['variant_id'] = $variant_id;
            $product['qty']       = (int)$item['qty'];
            $items[] = $product;
        }
    }
    return $items;
}

function getCartTotal($pdo) {
    $total = 0;
    foreach (getCart() as $item) {
        $product_id = (int)$item['product_id'];
        $variant_id = (int)$item['variant_id'];
        $qty = (int)$item['qty'];

        if ($variant_id > 0) {
            $stmt = $pdo->prepare("
                SELECT p.price, pv.additional_price
                FROM products p
                LEFT JOIN product_variants pv ON pv.id = ? AND pv.product_id = p.id
                WHERE p.id = ? AND pv.id IS NOT NULL
            ");
            $stmt->execute([$variant_id, $product_id]);
        } else {
            $stmt = $pdo->prepare("SELECT p.price, 0 as additional_price FROM products p WHERE p.id = ?");
            $stmt->execute([$product_id]);
        }
        $row = $stmt->fetch();
        if ($row) $total += ($row['price'] + $row['additional_price']) * $qty;
    }
    return $total;
}

// ============================================================
// STOK
// ============================================================

/**
 * Stok mentah di DB (produk atau varian).
 */
function getDbStock($product_id, $variant_id = 0) {
    global $pdo;
    if ($variant_id > 0) {
        $stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$variant_id, $product_id]);
    } else {
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$product_id]);
    }
    $row = $stmt->fetch();
    return $row ? (int)$row['stock'] : 0;
}

/**
 * Qty produk+variant ini yang ada di cart.
 */
function getCartQtyFor($product_id, $variant_id = 0) {
    $key = $product_id . '-' . $variant_id;
    return isset($_SESSION['cart'][$key]) ? (int)$_SESSION['cart'][$key]['qty'] : 0;
}

/**
 * Stok yang bisa dibeli (DB − cart).
 */
function getAvailableStock($product_id, $variant_id = 0) {
    return max(0, getDbStock($product_id, $variant_id) - getCartQtyFor($product_id, $variant_id));
}

/**
 * Apakah qty (tambahan) masih memenuhi stok?
 */
function canFulfillStock($product_id, $variant_id, $qty) {
    return getAvailableStock($product_id, $variant_id) >= (int)$qty;
}

/**
 * Kurangi stok atomic. Return true jika berhasil.
 */
function decreaseStockAtomic($product_id, $variant_id, $qty) {
    global $pdo;
    if ($variant_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE product_variants SET stock = stock - ?
            WHERE id = ? AND product_id = ? AND stock >= ?
        ");
        $stmt->execute([$qty, $variant_id, $product_id, $qty]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE products SET stock = stock - ?
            WHERE id = ? AND stock >= ?
        ");
        $stmt->execute([$qty, $product_id, $qty]);
    }
    return $stmt->rowCount() > 0;
}

/**
 * Kembalikan stok (untuk pembatalan / refund).
 */
function restoreStock($product_id, $variant_id, $qty) {
    global $pdo;
    if ($variant_id > 0) {
        $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ? AND product_id = ?")
            ->execute([$qty, $variant_id, $product_id]);
    } else {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
            ->execute([$qty, $product_id]);
    }
}

/**
 * Total stok produk: sum varian jika ada, else product.stock.
 */
function getTotalProductStock($product_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(stock),0) AS total
        FROM product_variants WHERE product_id = ?
    ");
    $stmt->execute([$product_id]);
    $row = $stmt->fetch();
    if ((int)$row['cnt'] > 0) return (int)$row['total'];
    return getDbStock($product_id, 0);
}

/**
 * Generate nomor pesanan unik.
 */
function generateOrderNumber() {
    return 'KPC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// ============================================================
// UTIL
// ============================================================

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function uploadImage($file, $target_dir = '../assets/uploads/products/') {
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) return false;
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
    if ($file['size'] > 2000000) return false;
    if (@getimagesize($file['tmp_name']) === false) return false;
    $new_name = uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_name)) return $new_name;
    return false;
}

function isAdmin() {
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

function getSetting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function buildWhatsAppLink($phone, $message) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
    return 'https://wa.me/' . $phone . '?text=' . urlencode($message);
}