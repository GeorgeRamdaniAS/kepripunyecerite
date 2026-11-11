<?php
// ============================================================
// HEADER GLOBAL - Kepri Punya Cerite
// Di-include di semua halaman publik
// ============================================================

// Pastikan session sudah berjalan (jika belum, start di sini)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil bahasa aktif dari session, default 'id'
$current_lang = $_SESSION['lang'] ?? 'id';

// Hitung total item di keranjang
if (function_exists('getCartCount')) {
    $cart_count = getCartCount();
} else {
    // Fallback jika functions.php belum dimuat
    $cart_count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += (int)($item['qty'] ?? 0);
        }
    }
}

// Tentukan halaman aktif untuk nav-link
$current_page = basename($_SERVER['PHP_SELF']);

// Siapkan URL untuk ganti bahasa (mempertahankan parameter lain)
$current_params = $_GET;
unset($current_params['lang']); // hapus lang lama
$lang_id_url = '?' . http_build_query(array_merge($current_params, ['lang' => 'id']));
$lang_en_url = '?' . http_build_query(array_merge($current_params, ['lang' => 'en']));
// Jika tidak ada parameter, gunakan '?lang=xx'
if (empty($current_params)) {
    $lang_id_url = '?lang=id';
    $lang_en_url = '?lang=en';
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kepri Punya Cerite - Toko oleh-oleh khas Kepulauan Riau. Kaos, gantungan kunci, dan suvenir unik bernilai seni tinggi.">
    <meta name="keywords" content="oleh-oleh, kepri, batam, souvenir, kaos, gantungan kunci">
    <title><?= isset($page_title) ? $page_title . ' - Kepri Punya Cerite' : 'Kepri Punya Cerite - Oleh-Oleh Khas Kepri' ?></title>

    <!-- ============================================
         FAVICON - Logo Kepri Punya Cerite
    ============================================ -->
    <link rel="icon" type="image/jpeg" href="img/kepripunyecerite logo.jpeg">
    <link rel="apple-touch-icon" href="img/kepripunyecerite logo.jpeg">
    <meta name="theme-color" content="#0A3D5C">

    <!-- Preconnect untuk performa -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ============================================
           GLOBAL STYLES - Kepri Punya Cerite
           ============================================ */
        :root {
            --primary-dark: #0A3D5C;
            --primary-teal: #1A7A8C;
            --accent-gold: #D4A24C;
            --accent-gold-hover: #C4903A;
            --accent-coral: #E85D4A;
            --bg-soft: #F4F7F9;
            --text-dark: #1E2A3A;
            --text-muted: #6C757D;
            --border-light: #E9ECEF;
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 30px rgba(10,61,92,0.1);
            --shadow-lg: 0 20px 40px rgba(10,61,92,0.15);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 40px;
            --transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-soft);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }

        a {
            text-decoration: none;
        }

        /* ============ NAVBAR ============ */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-bottom: 3px solid var(--accent-gold);
            box-shadow: var(--shadow-sm);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            color: var(--primary-dark);
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: var(--transition);
        }

        .navbar-brand:hover {
            color: var(--primary-teal);
        }

        .navbar-brand i {
            color: var(--accent-gold);
            font-size: 1.8rem;
        }

        /* Logo navbar (gambar) */
        .navbar-brand-logo {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--accent-gold);
            box-shadow: 0 2px 8px rgba(10,61,92,0.15);
            transition: var(--transition);
            flex-shrink: 0;
            background: #fff;
        }

        .navbar-brand:hover .navbar-brand-logo {
            transform: scale(1.08) rotate(-3deg);
            box-shadow: 0 4px 14px rgba(212,162,76,0.4);
        }

        .nav-link {
            color: var(--text-dark);
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 30px;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary-teal) !important;
            background: rgba(26, 122, 140, 0.1);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: var(--accent-coral);
            border-radius: 2px;
        }

        .navbar .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-md);
            border-radius: var(--radius-sm);
            overflow: hidden;
            padding: 0.5rem;
        }

        .navbar .dropdown-item {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .navbar .dropdown-item:hover {
            background: rgba(26, 122, 140, 0.1);
        }

        .navbar .dropdown-item.active {
            background: var(--primary-teal);
            color: white;
        }

        .cart-badge {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 0.65rem;
            transform: translate(25%, -25%);
        }

        /* ============ BUTTONS & UTILITIES ============ */
        .btn-primary-custom {
            background: var(--primary-teal);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .btn-gold {
            background: var(--accent-gold);
            color: white;
            border-radius: 50px;
            padding: 12px 35px;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-gold:hover {
            background: var(--accent-gold-hover);
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-teal {
            border: 2px solid var(--primary-teal);
            color: var(--primary-teal);
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            transition: var(--transition);
            background: transparent;
        }

        .btn-outline-teal:hover {
            background: var(--primary-teal);
            color: white;
        }

        /* ============ FORM CONTROLS ============ */
        .form-control {
            border-radius: 10px;
            border: 1px solid var(--border-light);
            padding: 0.7rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 0.25rem rgba(26,122,140,0.15);
        }

        /* ============ CARDS ============ */
        .card {
            border: none;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        /* ============ SECTION HEADINGS ============ */
        .section-title {
            font-weight: 800;
            color: var(--primary-dark);
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--accent-gold);
            border-radius: 2px;
        }

        /* ============ FOOTER ============ */
        footer {
            background: var(--primary-dark);
            color: rgba(255,255,255,0.8);
            padding: 3rem 0;
            margin-top: 4rem;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        footer a {
            color: rgba(255,255,255,0.8);
            transition: var(--transition);
        }

        footer a:hover {
            color: var(--accent-gold);
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 992px) {
            .navbar-collapse {
                background: white;
                border-radius: var(--radius-sm);
                padding: 1rem;
                margin-top: 0.5rem;
                box-shadow: var(--shadow-md);
            }
            .nav-link {
                padding: 0.7rem 1rem !important;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.15rem;
            }
            .navbar-brand-logo {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="img/kepripunyecerite logo.jpeg" 
                 alt="Kepri Punya Cerite" 
                 class="navbar-brand-logo">
            <span>Kepri Punya Cerite</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <!-- Home -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-home me-1"></i> <?= t('home') ?>
                    </a>
                </li>
                <!-- Produk -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>" href="products.php">
                        <i class="fas fa-store me-1"></i> <?= t('menu') ?>
                    </a>
                </li>
                <!-- Tentang -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'about.php' ? 'active' : '' ?>" href="about.php">
                        <i class="fas fa-info-circle me-1"></i> <?= t('about') ?>
                    </a>
                </li>
                <!-- Kontak -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'contact.php' ? 'active' : '' ?>" href="contact.php">
                        <i class="fas fa-envelope me-1"></i> <?= t('contact') ?>
                    </a>
                </li>

                <!-- Dropdown Bahasa -->
                <li class="nav-item dropdown mx-lg-2">
                    <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-globe me-1"></i> <?= strtoupper($current_lang) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                        <li>
                            <a class="dropdown-item <?= $current_lang == 'id' ? 'active' : '' ?>" href="<?= $lang_id_url ?>">
                                <span class="me-2">🇮🇩</span> Indonesia
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $current_lang == 'en' ? 'active' : '' ?>" href="<?= $lang_en_url ?>">
                                <span class="me-2">🇬🇧</span> English
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Keranjang -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="cart.php" aria-label="Keranjang belanja">
                        <i class="fas fa-shopping-cart fa-lg"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="badge rounded-pill bg-danger cart-badge" id="cart-count">
                                <?= $cart_count ?>
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-danger cart-badge d-none" id="cart-count">0</span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Admin / User Menu -->
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-teal btn-sm px-3" href="admin/">
                            <i class="fas fa-user-cog me-1"></i> Admin
                        </a>
                    </li>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary-custom btn-sm px-3" href="account.php">
                            <i class="fas fa-user me-1"></i> Akun
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-gold btn-sm px-3" href="admin/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Masuk
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Konten halaman dimulai di sini -->
<main>