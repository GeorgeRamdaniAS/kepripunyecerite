<?php
ob_start(); // Mulai output buffering untuk mencegah "headers already sent"
// index.php
// ============================================================
// Halaman Utama - Oleh-Oleh Khas Kepulauan Riau
// Dikembangkan ulang untuk meningkatkan UX, desain profesional,
// dan menghindari tampilan template generik.
// ============================================================

// Pastikan file konfigurasi dan fungsi tersedia
include_once 'config/database.php';
include_once 'includes/functions.php';
include_once 'includes/header.php';


// ------------------------------------------------------------
// 0. Proses pengiriman testimoni (POST)
// ------------------------------------------------------------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimoni'])) {
    $nama = trim($_POST['nama'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $pesan = trim($_POST['pesan'] ?? '');

    if ($nama === '') $errors[] = 'Nama wajib diisi.';
    if ($lokasi === '') $errors[] = 'Lokasi wajib diisi.';
    if ($pesan === '') $errors[] = 'Pesan testimoni wajib diisi.';
    if ($rating < 1 || $rating > 5) $rating = 5;

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO testimonials (name, location, rating, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $lokasi, $rating, $pesan]);
            ob_end_clean(); // Buang output buffer sebelum redirect
            header('Location: index.php?testimoni=success#testimoni-form');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan testimoni. Silakan coba lagi.';
        }
    }
}

// ------------------------------------------------------------
// 1. Ambil Produk Unggulan (is_featured = 1)
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        c.name_id AS cat_name_id,
        c.name_en AS cat_name_en,
        c.slug AS cat_slug,
        (SELECT image_name FROM product_images 
         WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_featured = 1
    ORDER BY p.created_at DESC
    LIMIT 6
");
$stmt->execute();
$featured = $stmt->fetchAll();

// ------------------------------------------------------------
// 2. Ambil Produk Terbaru (non-featured)
// ------------------------------------------------------------
$latest_stmt = $pdo->prepare("
    SELECT 
        p.*,
        c.name_id AS cat_name_id,
        c.name_en AS cat_name_en,
        c.slug AS cat_slug,
        (SELECT image_name FROM product_images 
         WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_featured = 0
    ORDER BY p.created_at DESC
    LIMIT 8
");
$latest_stmt->execute();
$latest_products = $latest_stmt->fetchAll();

// ------------------------------------------------------------
// 3. Ambil Kategori dengan Jumlah Produk
// ------------------------------------------------------------
$catStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name_id
    LIMIT 6
");
$categories = $catStmt->fetchAll();

// ------------------------------------------------------------
// 4. Data statis untuk section keunggulan
// ------------------------------------------------------------
$features = [
    ['icon' => 'fa-shipping-fast', 'title' => 'Pengantaran Cepat'],
    ['icon' => 'fa-lock', 'title' => 'Pembayaran Aman'],
    ['icon' => 'fa-gem', 'title' => 'Produk Berkualitas'],
    ['icon' => 'fa-headset', 'title' => 'Layanan Pelanggan'],
];

// ------------------------------------------------------------
// 5. Data testimoni statis
// ------------------------------------------------------------
$testimonials = [
    [
        'name' => 'Budi Santoso',
        'location' => 'Batam',
        'text' => 'testimonial_1',
        'rating' => 5,
        'avatar' => 'https://ui-avatars.com/api/?name=Budi+Santoso&background=D4AF37&color=fff',
        'type' => 'static'
    ],
    [
        'name' => 'Siti Rahma',
        'location' => 'Tanjung Pinang',
        'text' => 'testimonial_2',
        'rating' => 5,
        'avatar' => 'https://ui-avatars.com/api/?name=Siti+Rahma&background=0A3D5C&color=fff',
        'type' => 'static'
    ],
    [
        'name' => 'Ahmad Fauzi',
        'location' => 'Jakarta',
        'text' => 'testimonial_3',
        'rating' => 4,
        'avatar' => 'https://ui-avatars.com/api/?name=Ahmad+Fauzi&background=6c757d&color=fff',
        'type' => 'static'
    ],
    [
        'name' => 'Dewi Lestari',
        'location' => 'Batam',
        'text' => 'testimonial_4',
        'rating' => 5,
        'avatar' => 'https://ui-avatars.com/api/?name=Dewi+Lestari&background=D4AF37&color=fff',
        'type' => 'static'
    ],
];

// Ambil testimoni dari database
$db_testimonials = [];
try {
    $stmt_testi = $pdo->query("SELECT name, location, rating, message FROM testimonials ORDER BY created_at DESC LIMIT 10");
    $db_testimonials = $stmt_testi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabel mungkin belum ada, abaikan
}

// Format data testimoni database
$db_testimonials_formatted = array_map(function($t) {
    return [
        'name' => $t['name'],
        'location' => $t['location'],
        'rating' => (int)$t['rating'],
        'text' => $t['message'],
        'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($t['name']) . '&background=D4AF37&color=fff',
        'type' => 'db'
    ];
}, $db_testimonials);

// Gabungkan: database di depan
$testimonials = array_merge($db_testimonials_formatted, $testimonials);

// ------------------------------------------------------------
// 6. Data FAQ
// ------------------------------------------------------------
$faqs = [
    [
        'question' => 'Bagaimana cara memesan?',
        'answer' => 'Pilih produk, tambahkan ke keranjang, lalu checkout. Pesanan akan langsung terkirim ke admin via WhatsApp.'
    ],
    [
        'question' => 'Apakah ada ongkos kirim?',
        'answer' => 'Ongkos kirim dihitung berdasarkan alamat tujuan dan berat paket. Admin akan menginformasikan biaya pastinya.'
    ],
    [
        'question' => 'Berapa lama pengiriman?',
        'answer' => 'Estimasi 2-5 hari kerja tergantung lokasi tujuan.'
    ],
    [
        'question' => 'Apakah bisa retur?',
        'answer' => 'Bisa, selama produk masih dalam kondisi baik dan belum dipakai. Hubungi admin untuk proses retur.'
    ],
];

// Hero media: gunakan folder img
$hero_media = 'img/kepripunyecerite logo.jpeg'; // pastikan file ada di dalam folder img/
// Placeholder untuk produk tanpa gambar
$placeholder_img = 'img/placeholder.jpg'; // pastikan file placeholder ada
?>

<!-- ============================================================
     STYLE KHUSUS UNTUK HALAMAN INI
============================================================ -->
<style>
    /* ============================================
       LOADING SCREEN
    ============================================ */
    .loading-screen {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #0A3D5C 0%, #1A7A8C 50%, #0D5C7A 100%);
        background-size: 200% 200%;
        animation: loadingBgShift 6s ease infinite;
        transition: opacity 0.7s ease, visibility 0.7s ease;
        overflow: hidden;
    }

    .loading-screen.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    @keyframes loadingBgShift {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .loading-screen::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 30%, rgba(212,175,55,0.15) 0%, transparent 45%),
                    radial-gradient(circle at 80% 70%, rgba(255,255,255,0.08) 0%, transparent 40%);
        pointer-events: none;
    }

    .loading-content {
        position: relative;
        z-index: 2;
        text-align: center;
        padding: 2rem;
        animation: loadingFadeIn 0.8s ease-out;
    }

    @keyframes loadingFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .loading-logo-wrapper {
        position: relative;
        width: 180px;
        height: 180px;
        margin: 0 auto 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .loading-ring {
        position: absolute;
        border-radius: 50%;
        border: 2px solid transparent;
    }

    .loading-ring-1 {
        inset: 0;
        border-top-color: #D4AF37;
        border-right-color: #D4AF37;
        animation: ringSpin 1.8s linear infinite;
    }

    .loading-ring-2 {
        inset: 12px;
        border-bottom-color: rgba(255,255,255,0.6);
        border-left-color: rgba(255,255,255,0.6);
        animation: ringSpinReverse 2.4s linear infinite;
    }

    .loading-ring-3 {
        inset: 24px;
        border-top-color: rgba(212,175,55,0.4);
        border-left-color: rgba(212,175,55,0.4);
        animation: ringSpin 3s linear infinite;
    }

    @keyframes ringSpin {
        to { transform: rotate(360deg); }
    }
    @keyframes ringSpinReverse {
        to { transform: rotate(-360deg); }
    }

    .loading-logo-inner {
        position: relative;
        width: 110px;
        height: 110px;
        border-radius: 50%;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(255,255,255,0.15),
                    0 0 40px rgba(212,175,55,0.45),
                    0 12px 30px rgba(0,0,0,0.3);
        animation: logoPulse 2s ease-in-out infinite;
    }

    .loading-logo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    @keyframes logoPulse {
        0%, 100% { transform: scale(1);    box-shadow: 0 0 0 4px rgba(255,255,255,0.15), 0 0 40px rgba(212,175,55,0.45), 0 12px 30px rgba(0,0,0,0.3); }
        50%      { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(255,255,255,0.2),  0 0 60px rgba(212,175,55,0.7),  0 16px 40px rgba(0,0,0,0.35); }
    }

    .loading-brand {
        font-family: 'Poppins', sans-serif;
        font-weight: 800;
        font-size: 2rem;
        letter-spacing: -0.5px;
        color: #fff;
        margin-bottom: 0.5rem;
        background: linear-gradient(90deg, #fff 0%, #D4AF37 25%, #fff 50%, #D4AF37 75%, #fff 100%);
        background-size: 200% auto;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: brandShimmer 3s linear infinite;
    }

    @keyframes brandShimmer {
        to { background-position: -200% center; }
    }

    .loading-tagline {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.75);
        font-weight: 300;
        letter-spacing: 0.5px;
        margin-bottom: 2rem;
    }

    .loading-progress {
        width: 220px;
        height: 4px;
        background: rgba(255,255,255,0.15);
        border-radius: 999px;
        margin: 0 auto 1.5rem;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
    }

    .loading-progress-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #D4AF37 0%, #F0D77B 50%, #D4AF37 100%);
        background-size: 200% 100%;
        border-radius: 999px;
        box-shadow: 0 0 12px rgba(212,175,55,0.7);
        animation: progressShimmer 1.5s linear infinite;
        transition: width 0.3s ease-out;
    }

    @keyframes progressShimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .loading-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
    }

    .loading-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #D4AF37;
        animation: dotBounce 1.4s ease-in-out infinite both;
    }

    .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
    .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
    .loading-dots span:nth-child(3) { animation-delay: 0s; }

    @keyframes dotBounce {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
        40%           { transform: scale(1.2); opacity: 1; }
    }

    .loading-wave {
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 60px;
        background: radial-gradient(ellipse at 50% 100%, rgba(255,255,255,0.08) 0%, transparent 70%);
        pointer-events: none;
    }

    @media (max-width: 575.98px) {
        .loading-logo-wrapper { width: 140px; height: 140px; }
        .loading-logo-inner   { width: 88px; height: 88px; }
        .loading-brand        { font-size: 1.5rem; }
        .loading-tagline      { font-size: 0.8rem; }
        .loading-progress     { width: 180px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .loading-screen,
        .loading-screen::before,
        .loading-ring,
        .loading-logo-inner,
        .loading-brand,
        .loading-progress-bar,
        .loading-dots span {
            animation: none !important;
        }
    }

    /* ============================================
       HALAMAN UTAMA
    ============================================ */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

    :root {
        --primary: #0A3D5C;
        --primary-light: #0D5C7A;
        --accent: #D4AF37;
        --accent-hover: #F0D77B;
        --dark: #212529;
        --muted: #6c757d;
        --bg-soft: #F8F9FA;
        --radius: 16px;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
        --shadow-md: 0 8px 20px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: #fff;
        color: var(--dark);
    }

    /* ============ HERO SECTION ============ */
    .hero-section {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, #1B6B8F 100%);
        background-size: 200% 200%;
        animation: gradientShift 8s ease infinite;
        position: relative;
        overflow: hidden;
        min-height: 85vh;
        display: flex;
        align-items: center;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at 30% 40%, rgba(212,175,55,0.15) 0%, transparent 50%),
                    radial-gradient(circle at 80% 80%, rgba(255,255,255,0.08) 0%, transparent 40%);
        animation: heroPattern 15s infinite linear;
    }

    @keyframes heroPattern {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .hero-content {
        position: relative;
        z-index: 2;
        color: #fff;
    }

    .hero-title {
        font-weight: 800;
        letter-spacing: -1.5px;
        line-height: 1.2;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .hero-sub {
        font-size: 1.15rem;
        font-weight: 300;
        opacity: 0.9;
        max-width: 500px;
    }

    .hero-btn {
        background: var(--accent);
        color: var(--primary);
        border: none;
        padding: 12px 30px;
        font-weight: 600;
        border-radius: 50px;
        transition: var(--transition);
        animation: pulse 2s infinite;
    }

    .hero-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(212,175,55,0.6); }
        70% { box-shadow: 0 0 0 15px rgba(212,175,55,0); }
        100% { box-shadow: 0 0 0 0 rgba(212,175,55,0); }
    }

    .hero-search {
        max-width: 480px;
        margin-top: 2rem;
    }

    .hero-search .input-group {
        border-radius: 50px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .hero-search .input-group-text {
        background-color: #fff;
        border: none;
        padding-left: 1.2rem;
        border-radius: 50px 0 0 50px;
    }

    .hero-search .form-control {
        border: none;
        padding: 0.9rem 0.75rem;
        font-size: 1rem;
        background-color: #fff;
    }

    .hero-search .form-control:focus {
        box-shadow: none;
        border-color: transparent;
    }

    .hero-search .btn-warning {
        background-color: var(--accent);
        border: none;
        color: var(--primary);
        padding: 0 30px;
        font-weight: 600;
        border-radius: 0 50px 50px 0;
        transition: var(--transition);
    }

    .hero-search .btn-warning:hover {
        background-color: var(--accent-hover);
        transform: scale(1.03);
    }

    .hero-section .badge {
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .hero-illustration {
        position: relative;
        max-width: 480px;
        margin: 0 auto;
        padding: 1rem;
        border: 1px solid rgba(255,255,255,0.28);
        border-radius: 28px;
        background: rgba(255,255,255,0.12);
        box-shadow: 0 24px 50px rgba(0,0,0,0.28);
        transform: rotate(2deg);
        transition: transform 0.5s ease, box-shadow 0.5s ease;
    }

    .hero-illustration::after {
        content: 'KEPRI PUNYA CERITE';
        position: absolute;
        right: -1rem;
        bottom: 1.5rem;
        padding: 0.55rem 0.9rem;
        border-radius: 999px;
        background: var(--accent);
        color: var(--primary);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        box-shadow: 0 8px 18px rgba(0,0,0,0.2);
    }

    .hero-illustration img {
        display: block;
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 20px;
    }

    .hero-illustration:hover {
        transform: rotate(0deg) translateY(-6px);
        box-shadow: 0 30px 60px rgba(0,0,0,0.35);
    }

    /* Kategori */
    .category-card {
        background: #fff;
        border-radius: var(--radius);
        padding: 1.5rem 1rem;
        text-align: center;
        transition: var(--transition);
        border: 1px solid rgba(0,0,0,0.05);
        height: 100%;
        display: block;
        text-decoration: none;
    }

    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--accent);
    }

    .category-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(212,175,55,0.1);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.8rem;
        transition: var(--transition);
    }

    .category-card:hover .category-icon {
        background: var(--accent);
        color: #fff;
        transform: rotateY(360deg);
    }

    .category-name {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .category-count {
        font-size: 0.85rem;
        color: var(--muted);
    }

    /* Produk Card */
    .product-card {
        border: none;
        border-radius: var(--radius);
        overflow: hidden;
        background: #fff;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: var(--shadow-md);
    }

    .product-card .product-img-wrapper {
        position: relative;
        overflow: hidden;
        height: 220px;
    }

    .product-card .product-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .product-card:hover .product-img-wrapper img {
        transform: scale(1.1);
    }

    .product-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background: var(--accent);
        color: var(--primary);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 30px;
        z-index: 2;
    }

    .product-card .card-body {
        padding: 1.25rem;
    }

    .product-category {
        font-size: 0.75rem;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .product-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.5rem;
        color: var(--dark);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-price {
        font-weight: 700;
        color: var(--primary);
        font-size: 1.15rem;
        margin-bottom: 0.75rem;
    }

    .btn-primary-custom {
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 30px;
        padding: 8px 20px;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-primary-custom:hover {
        background: var(--primary-light);
        color: #fff;
        transform: translateY(-2px);
    }

    /* Section Headings */
    .section-heading {
        font-weight: 700;
        color: var(--primary);
        position: relative;
        display: inline-block;
        margin-bottom: 0.5rem;
    }

    .section-heading::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 50px;
        height: 4px;
        background: var(--accent);
        border-radius: 2px;
        transition: width 0.3s;
    }

    .section-heading:hover::after {
        width: 100%;
    }

    /* Feature Items */
    .feature-item {
        text-align: center;
        padding: 2rem 1rem;
        background: #fff;
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        height: 100%;
        transition: var(--transition);
    }

    .feature-item:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-5px);
    }

    .feature-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(10,61,92,0.05);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.2rem;
        font-size: 2rem;
        transition: var(--transition);
    }

    .feature-item:hover .feature-icon {
        background: var(--primary);
        color: #fff;
        transform: scale(1.1);
    }

    /* Testimonial */
    .testimonial-card {
        background: #fff;
        border-radius: var(--radius);
        padding: 2rem;
        box-shadow: var(--shadow-sm);
        height: 100%;
        border-left: 4px solid var(--accent);
    }

    .testimonial-text {
        font-style: italic;
        color: #495057;
        margin-bottom: 1rem;
    }

    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .testimonial-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .testimonial-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .testimonial-name {
        font-weight: 600;
        margin: 0;
    }

    .testimonial-location {
        font-size: 0.85rem;
        color: var(--muted);
    }

    .carousel-indicators [data-bs-target] {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--primary);
        opacity: 0.4;
        transition: opacity 0.3s;
    }

    .carousel-indicators .active {
        opacity: 1;
        background-color: var(--accent);
    }

    /* Newsletter */
    .newsletter-section {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 20px;
        padding: 3rem 2rem;
        color: #fff;
        margin: 4rem 0;
    }

    .newsletter-form .form-control {
        border-radius: 50px;
        padding: 12px 20px;
        border: none;
    }

    .newsletter-form .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 0.25rem rgba(212,175,55,0.25);
    }

    .newsletter-form .btn {
        border-radius: 50px;
        background: var(--accent);
        color: var(--primary);
        font-weight: 600;
        border: none;
        padding: 12px 30px;
        transition: var(--transition);
    }

    .newsletter-form .btn:hover {
        background: var(--accent-hover);
        transform: scale(1.05);
    }

    /* FAQ */
    .faq-section .accordion-button:not(.collapsed) {
        background-color: rgba(26,122,140,0.1);
        color: var(--primary);
        font-weight: 600;
    }
    .faq-section .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(26,122,140,0.2);
    }

    /* Promo */
    .promo-banner {
        background: linear-gradient(45deg, #E85D4A, #D4A24C);
        color: #fff;
        border-radius: 20px;
        padding: 2rem;
        text-align: center;
        margin: 3rem 0;
        animation: promoGlow 3s ease-in-out infinite alternate;
    }

    @keyframes promoGlow {
        from { box-shadow: 0 0 10px rgba(232,93,74,0.3); }
        to { box-shadow: 0 0 25px rgba(212,162,76,0.6); }
    }

    .promo-banner h3 {
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    /* Fade-in */
    .fade-in {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Scroll Top */
    .scroll-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        border: none;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        z-index: 999;
        transition: var(--transition);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .scroll-top:hover {
        background: var(--primary-light);
        transform: translateY(-3px);
    }
    .scroll-top:hover i {
        transform: rotate(180deg);
        transition: transform 0.3s;
    }

    /* Form Testimoni */
    #testimoni-form .card {
        background: #fff;
        border-radius: 20px;
    }

    #testimoni-form .form-control,
    #testimoni-form .form-select {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #dee2e6;
    }

    #testimoni-form .form-control:focus,
    #testimoni-form .form-select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 0.2rem rgba(212,175,55,0.25);
    }

    /* Responsive */
    @media (max-width: 991.98px) {
        .hero-section {
            min-height: auto;
            padding: 4rem 0;
        }
        .hero-title {
            font-size: 2.5rem;
        }
        .hero-sub {
            font-size: 1rem;
        }
        .product-card .product-img-wrapper {
            height: 160px;
        }
    }

    @media (max-width: 768px) {
        .hero-section {
            min-height: 70vh;
            padding: 3rem 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fade-in,
        .hero-section,
        .hero-btn,
        .product-card:hover,
        .product-img-wrapper img,
        .scroll-top:hover i,
        .category-card,
        .feature-item,
        .promo-banner,
        .hero-illustration {
            animation: none;
            transition: none;
            opacity: 1;
            transform: none;
        }
    }
</style>

<!-- ============================================================
     LOADING SCREEN
============================================================ -->
<div id="loadingScreen" class="loading-screen">
    <div class="loading-content">

        <!-- Logo dengan ring berputar -->
        <div class="loading-logo-wrapper">
            <div class="loading-ring loading-ring-1"></div>
            <div class="loading-ring loading-ring-2"></div>
            <div class="loading-ring loading-ring-3"></div>
            <div class="loading-logo-inner">
                <img src="img/kepripunyecerite logo.jpeg" 
                     alt="Kepri Punya Cerite" 
                     class="loading-logo">
            </div>
        </div>

        <!-- Brand name dengan shimmer -->
        <h1 class="loading-brand">Kepri Punya Cerite</h1>

        <!-- Tagline -->
        <p class="loading-tagline">
            <?= ($current_lang ?? 'id') == 'id' 
                ? 'Oleh-Oleh Khas Kepulauan Riau' 
                : 'Authentic Riau Islands Souvenirs' ?>
        </p>

        <!-- Progress bar -->
        <div class="loading-progress">
            <div class="loading-progress-bar" id="loadingProgressBar"></div>
        </div>

        <!-- Loading dots -->
        <div class="loading-dots">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Decorative wave di bawah -->
    <div class="loading-wave"></div>
</div>

<!-- HERO SECTION -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-7 hero-content fade-in text-center text-lg-start">
                <h1 class="hero-title display-2 fw-bold mb-3">
                    <?= $current_lang == 'id' ? 'Oleh-Oleh Khas<br>Kepulauan Riau' : 'Authentic Souvenirs<br>from Riau Islands' ?>
                </h1>
                <p class="hero-sub lead mb-4">
                    <?= $current_lang == 'id' 
                        ? 'Temukan kaos, gantungan kunci, dan suvenir unik bernilai seni tinggi yang mencerminkan kekayaan budaya Melayu.' 
                        : 'Discover t-shirts, keychains, and unique high-art souvenirs that reflect the rich Malay cultural heritage.' ?>
                </p>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start mb-4">
                    <a href="products.php" class="btn hero-btn btn-lg px-4 py-3">
                        <i class="fas fa-store me-2"></i><?= t('menu') ?>
                    </a>
                    <a href="#categories" class="btn btn-outline-light btn-lg px-4 py-3 rounded-pill">
                        <i class="fas fa-th-large me-2"></i>
                        <?= $current_lang == 'id' ? 'Jelajahi Kategori' : 'Explore Categories' ?>
                    </a>
                </div>
                <form action="products.php" method="GET" class="hero-search mx-auto mx-lg-0">
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="search" name="search" class="form-control" 
                               placeholder="<?= $current_lang == 'id' ? 'Cari produk, kaos, gantungan kunci...' : 'Search products, t-shirts, keychains...' ?>" 
                               aria-label="Search products" autocomplete="off">
                        <button class="btn btn-warning px-4 fw-semibold" type="submit">
                            <?= $current_lang == 'id' ? 'Cari' : 'Search' ?>
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-lg-5 mt-5 mt-lg-0 d-none d-lg-block">
                <div class="hero-illustration text-center">
                    <img src="<?= htmlspecialchars($hero_media, ENT_QUOTES, 'UTF-8') ?>" 
                         alt="Oleh-Oleh Khas Kepulauan Riau" 
                         class="img-fluid rounded-4 shadow-lg">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- KATEGORI -->
<div class="container py-5" id="categories">
    <div class="text-center mb-5">
        <h2 class="section-heading"><?= $current_lang == 'id' ? 'Kategori Produk' : 'Product Categories' ?></h2>
        <p class="text-muted"><?= $current_lang == 'id' ? 'Pilih berdasarkan jenis oleh-oleh yang Anda cari' : 'Choose by the type of souvenir you are looking for' ?></p>
    </div>
    <div class="row g-4 justify-content-center">
        <?php foreach ($categories as $cat): 
            $name = $current_lang == 'id' ? $cat['name_id'] : $cat['name_en'];
            $icon = match($cat['slug']) {
                'kaos' => 'fa-tshirt',
                'gantungan-kunci' => 'fa-key',
                'topi' => 'fa-hat-cowboy',
                default => 'fa-box-open'
            };
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="products.php?category=<?= urlencode($cat['slug']) ?>" class="category-card fade-in">
                <div class="category-icon">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div class="category-name"><?= e($name) ?></div>
                <div class="category-count"><?= $cat['product_count'] ?> <?= $current_lang == 'id' ? 'produk' : 'items' ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- PRODUK UNGGULAN -->
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-heading"><?= t('featured_products') ?></h2>
            <p class="text-muted mb-0"><?= $current_lang == 'id' ? 'Pilihan terbaik untuk oleh-oleh khas Kepri' : 'Best picks for authentic Kepri souvenirs' ?></p>
        </div>
        <a href="products.php" class="btn btn-outline-primary rounded-pill mt-2 mt-md-0">
            <?= t('view_detail') ?> <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="row g-4">
        <?php if (count($featured) > 0): foreach ($featured as $p):
            // Gunakan placeholder dari folder img jika tidak ada gambar
            $img = !empty($p['image']) ? 'assets/uploads/products/'.$p['image'] : $placeholder_img;
            $product_name = $current_lang == 'id' ? $p['name_id'] : $p['name_en'];
            $cat_name = $current_lang == 'id' ? $p['cat_name_id'] : $p['cat_name_en'];
        ?>
        <div class="col-6 col-md-4 col-lg-4 fade-in">
            <div class="product-card">
                <div class="product-img-wrapper">
                    <img src="<?= e($img) ?>" alt="<?= e($product_name) ?>" loading="lazy">
                    <span class="product-badge"><?= $current_lang == 'id' ? 'Unggulan' : 'Featured' ?></span>
                </div>
                <div class="card-body">
                    <div class="product-category"><?= e($cat_name) ?></div>
                    <h5 class="product-title"><?= e($product_name) ?></h5>
                    <div class="product-price"><?= rupiah($p['price']) ?></div>
                    <div class="d-grid gap-2">
                        <a href="product-detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-primary-custom">
                            <i class="fas fa-eye me-2"></i><?= t('view_detail') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <p class="text-muted"><?= t('no_products') ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- PRODUK TERBARU -->
<?php if (count($latest_products) > 0): ?>
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-heading"><i class="fas fa-clock me-2"></i><?= $current_lang == 'id' ? 'Produk Terbaru' : 'Latest Products' ?></h2>
        </div>
        <a href="products.php" class="btn btn-outline-primary rounded-pill mt-2 mt-md-0">
            <?= $current_lang == 'id' ? 'Lihat Semua' : 'View All' ?> <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="row g-4">
        <?php foreach ($latest_products as $p): 
            $img = !empty($p['image']) ? 'assets/uploads/products/'.$p['image'] : $placeholder_img;
            $product_name = $current_lang == 'id' ? $p['name_id'] : $p['name_en'];
            $cat_name = $current_lang == 'id' ? $p['cat_name_id'] : $p['cat_name_en'];
        ?>
        <div class="col-6 col-md-3 fade-in">
            <div class="product-card">
                <div class="product-img-wrapper">
                    <img src="<?= e($img) ?>" alt="<?= e($product_name) ?>" loading="lazy">
                </div>
                <div class="card-body">
                    <div class="product-category"><?= e($cat_name) ?></div>
                    <h5 class="product-title"><?= e($product_name) ?></h5>
                    <div class="product-price"><?= rupiah($p['price']) ?></div>
                    <a href="product-detail.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-primary-custom w-100">
                        <?= t('view_detail') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- KEUNGGULAN -->
<div class="bg-soft py-5" style="background-color: #F8F9FA;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading"><?= $current_lang == 'id' ? 'Kenapa Memilih Kami?' : 'Why Choose Us?' ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($features as $feature): ?>
            <div class="col-6 col-md-3 fade-in">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas <?= $feature['icon'] ?>"></i>
                    </div>
                    <h5 class="fw-bold"><?= t($feature['title']) ?></h5>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- TESTIMONI -->
<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="section-heading"><?= $current_lang == 'id' ? 'Testimoni Pelanggan' : 'Customer Testimonials' ?></h2>
        <p class="text-muted"><?= $current_lang == 'id' ? 'Apa kata mereka tentang produk kami' : 'What they say about our products' ?></p>
    </div>
    <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php foreach ($testimonials as $index => $testi): ?>
            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="testimonial-card text-center">
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $testi['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if (isset($testi['type']) && $testi['type'] === 'db'): ?>
                                <p class="testimonial-text">"<?= e($testi['text']) ?>"</p>
                            <?php else: ?>
                                <p class="testimonial-text">"<?= t($testi['text']) ?>"</p>
                            <?php endif; ?>
                            <div class="testimonial-author justify-content-center">
                                <div class="testimonial-avatar">
                                    <img src="<?= $testi['avatar'] ?>" alt="<?= e($testi['name']) ?>">
                                </div>
                                <div class="text-start">
                                    <p class="testimonial-name"><?= e($testi['name']) ?></p>
                                    <p class="testimonial-location mb-0"><?= e($testi['location']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
        <div class="carousel-indicators position-relative mt-4">
            <?php for ($i = 0; $i < count($testimonials); $i++): ?>
            <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="<?= $i ?>" 
                    class="<?= $i === 0 ? 'active' : '' ?>" 
                    aria-current="<?= $i === 0 ? 'true' : 'false' ?>" 
                    aria-label="Testimonial <?= $i + 1 ?>"></button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Form Testimoni -->
    <div class="row justify-content-center mt-5" id="testimoni-form">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-3 text-center">
                        <?= $current_lang == 'id' ? 'Kirim Testimoni Anda' : 'Submit Your Testimonial' ?>
                    </h3>
                    <?php if (isset($_GET['testimoni']) && $_GET['testimoni'] === 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $current_lang == 'id' ? 'Terima kasih! Testimoni Anda berhasil dikirim.' : 'Thank you! Your testimonial has been submitted.' ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form action="index.php#testimoni-form" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nama" class="form-label"><?= $current_lang == 'id' ? 'Nama' : 'Name' ?> *</label>
                                <input type="text" class="form-control" id="nama" name="nama" required 
                                       value="<?= e($_POST['nama'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="lokasi" class="form-label"><?= $current_lang == 'id' ? 'Lokasi' : 'Location' ?> *</label>
                                <input type="text" class="form-control" id="lokasi" name="lokasi" required 
                                       value="<?= e($_POST['lokasi'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label for="rating" class="form-label"><?= $current_lang == 'id' ? 'Rating' : 'Rating' ?></label>
                                <select class="form-select" id="rating" name="rating">
                                    <option value="5" <?= (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'selected' : '' ?>>5 - <?= $current_lang == 'id' ? 'Sangat Baik' : 'Excellent' ?></option>
                                    <option value="4" <?= (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'selected' : '' ?>>4 - <?= $current_lang == 'id' ? 'Baik' : 'Good' ?></option>
                                    <option value="3" <?= (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'selected' : '' ?>>3 - <?= $current_lang == 'id' ? 'Cukup' : 'Average' ?></option>
                                    <option value="2" <?= (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'selected' : '' ?>>2 - <?= $current_lang == 'id' ? 'Kurang' : 'Poor' ?></option>
                                    <option value="1" <?= (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'selected' : '' ?>>1 - <?= $current_lang == 'id' ? 'Sangat Kurang' : 'Very Poor' ?></option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="pesan" class="form-label"><?= $current_lang == 'id' ? 'Pesan Testimoni' : 'Testimonial Message' ?> *</label>
                                <textarea class="form-control" id="pesan" name="pesan" rows="4" required><?= e($_POST['pesan'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" name="submit_testimoni" class="btn hero-btn px-5 py-3">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    <?= $current_lang == 'id' ? 'Kirim Testimoni' : 'Submit Testimonial' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FAQ -->
<div class="container py-5 faq-section">
    <div class="text-center mb-5">
        <h2 class="section-heading"><?= $current_lang == 'id' ? 'Pertanyaan Umum' : 'Frequently Asked Questions' ?></h2>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="accordion" id="faqAccordion">
                <?php foreach ($faqs as $index => $faq): ?>
                <div class="accordion-item border-0 mb-3 shadow-sm rounded-3">
                    <h2 class="accordion-header" id="faqHeading<?= $index ?>">
                        <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $index ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="faqCollapse<?= $index ?>">
                            <?= e($faq['question']) ?>
                        </button>
                    </h2>
                    <div id="faqCollapse<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="faqHeading<?= $index ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            <?= e($faq['answer']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Scroll Top -->
<button class="scroll-top" id="scrollTopBtn" title="<?= $current_lang == 'id' ? 'Kembali ke atas' : 'Back to top' ?>">
    <i class="fas fa-chevron-up"></i>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        /* ============================================
           LOADING SCREEN - Auto hide
        ============================================ */
        const loadingScreen = document.getElementById('loadingScreen');
        const progressBar = document.getElementById('loadingProgressBar');

        if (loadingScreen) {
            let progress = 0;

            // Simulasi progress 0 → 90% sambil halaman dimuat
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15 + 5; // naik 5-20%
                if (progress > 90) progress = 90;
                if (progressBar) progressBar.style.width = progress + '%';
            }, 150);

            // Fungsi untuk menutup loader
            function hideLoader() {
                clearInterval(progressInterval);
                if (progressBar) progressBar.style.width = '100%';

                setTimeout(() => {
                    loadingScreen.classList.add('hidden');
                    // Hapus dari DOM setelah animasi selesai
                    setTimeout(() => {
                        loadingScreen.remove();
                    }, 800);
                }, 300);
            }

            // Fallback 1: window.load (semua resource termasuk gambar selesai)
            window.addEventListener('load', hideLoader);

            // Fallback 2: max timeout 4 detik (jika ada resource yang lambat/error)
            setTimeout(() => {
                if (document.body.contains(loadingScreen)) {
                    hideLoader();
                }
            }, 4000);
        }

        /* ============================================
           FADE-IN ANIMATION
        ============================================ */
        const fadeElements = document.querySelectorAll('.fade-in');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        fadeElements.forEach(el => observer.observe(el));

        /* ============================================
           SCROLL TO TOP
        ============================================ */
        const scrollBtn = document.getElementById('scrollTopBtn');
        if (scrollBtn) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    scrollBtn.style.display = 'flex';
                } else {
                    scrollBtn.style.display = 'none';
                }
            });
            scrollBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    });
</script>

<?php include_once 'includes/footer.php'; ?>