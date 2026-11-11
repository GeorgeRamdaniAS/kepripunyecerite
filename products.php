<?php
// ============================================================
// products.php - Halaman Produk (Versi User Friendly)
// ============================================================

include_once 'config/database.php';
include_once 'includes/functions.php';

// ------------------------------------------------------------
// HELPER: tampilkan label yang ramah (tanpa underscore)
// ------------------------------------------------------------
if (!function_exists('tt')) {
    /**
     * Translate + auto-humanize.
     * Jika t() mengembalikan key itu sendiri atau masih mengandung '_',
     * ubah menjadi Title Case (contoh: sort_newest -> Sort Newest).
     */
    function tt($key, $fallback = null) {
        $translated = function_exists('t') ? t($key) : null;

        // Jika kosong, sama dengan key, atau masih ada '_' → humanize
        if (empty($translated) || $translated === $key || strpos($translated, '_') !== false) {
            if ($fallback !== null) return $fallback;
            return ucwords(str_replace('_', ' ', (string)$key));
        }
        return $translated;
    }
}

// ------------------------------------------------------------
// 1. Ambil parameter dari URL
// ------------------------------------------------------------
$category_slug = trim($_GET['category'] ?? '');
$search        = trim($_GET['search'] ?? '');
$sort          = $_GET['sort'] ?? 'newest';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 12;

// Validasi sort
$allowed_sorts = [
    'newest'    => 'p.created_at DESC',
    'oldest'    => 'p.created_at ASC',
    'price_low' => 'p.price ASC',
    'price_high'=> 'p.price DESC',
    'name_asc'  => 'p.name_id ASC',
    'name_desc' => 'p.name_id DESC',
];
if (!array_key_exists($sort, $allowed_sorts)) {
    $sort = 'newest';
}
$order_by = $allowed_sorts[$sort];

// ------------------------------------------------------------
// 2. Ambil kategori
// ------------------------------------------------------------
$categories = $pdo->query("SELECT * FROM categories ORDER BY name_id ASC")->fetchAll();

// ------------------------------------------------------------
// 3. Hitung total produk
// ------------------------------------------------------------
$count_query = "
    SELECT COUNT(DISTINCT p.id)
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE 1=1
";
$count_params = [];

if ($category_slug) {
    $count_query .= " AND c.slug = ?";
    $count_params[] = $category_slug;
}
if ($search) {
    $count_query .= " AND (p.name_id LIKE ? OR p.name_en LIKE ? OR p.description_id LIKE ? OR p.description_en LIKE ?)";
    $like = "%$search%";
    array_push($count_params, $like, $like, $like, $like);
}

$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($count_params);
$total_products = (int)$stmt_count->fetchColumn();

$total_pages = max(1, ceil($total_products / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// ------------------------------------------------------------
// 4. Query produk
// ------------------------------------------------------------
$query = "
    SELECT 
        p.*,
        c.name_id AS cat_name_id,
        c.name_en AS cat_name_en,
        c.slug AS cat_slug,
        (SELECT image_name FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE 1=1
";
$params = [];

if ($category_slug) {
    $query .= " AND c.slug = ?";
    $params[] = $category_slug;
}
if ($search) {
    $query .= " AND (p.name_id LIKE ? OR p.name_en LIKE ? OR p.description_id LIKE ? OR p.description_en LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$query .= " ORDER BY $order_by LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ------------------------------------------------------------
// 5. Label sort (pakai tt() agar tidak muncul underscore)
// ------------------------------------------------------------
$sort_labels = [
    'newest'     => tt('sort_newest'),
    'oldest'     => tt('sort_oldest'),
    'price_low'  => tt('sort_price_low'),
    'price_high' => tt('sort_price_high'),
    'name_asc'   => tt('sort_name_asc'),
    'name_desc'  => tt('sort_name_desc'),
];

include_once 'includes/header.php';
?>

<!-- ============================================================
     STYLE KHUSUS (User Friendly)
============================================================ -->
<style>
    :root {
        --primary-dark: #0A3D5C;
        --primary-teal: #1A7A8C;
        --accent-gold: #D4A24C;
        --accent-coral: #E85D4A;
        --bg-soft: #F4F7F9;
        --text-dark: #1E2A3A;
        --text-muted: #6C757D;
        --border-light: #E9ECEF;
    }

    .filter-sidebar { position: sticky; top: 90px; }

    .filter-section {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.04);
        border: 1px solid #eef2f6;
    }

    .filter-title {
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 1rem;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .category-list { list-style: none; padding: 0; margin: 0; }
    .category-list li { margin-bottom: 0.4rem; }
    .category-list a {
        display: block;
        padding: 0.6rem 1rem;
        border-radius: 10px;
        color: var(--text-dark);
        font-weight: 500;
        transition: all 0.2s;
        text-decoration: none;
        position: relative;
    }
    .category-list a:hover { background: rgba(26,122,140,0.08); color: var(--primary-teal); }
    .category-list a.active { background: var(--primary-teal); color: white; font-weight: 600; }
    .category-list a.active::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 1rem;
        font-size: 0.8rem;
    }

    .search-sort-bar {
        background: white;
        border-radius: 20px;
        padding: 1rem 1.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.04);
        border: 1px solid #eef2f6;
        margin-bottom: 2rem;
    }

    .search-input-group { position: relative; }
    .search-input-group .form-control {
        border-radius: 50px;
        padding-left: 2.5rem;
        padding-right: 2.5rem;
        border: 1px solid var(--border-light);
    }
    .search-input-group .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }
    .search-input-group .clear-btn {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #adb5bd;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.2s;
        display: none;
    }
    .search-input-group .clear-btn:hover { background: #f1f3f5; color: #495057; }

    .product-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        height: 100%;
        background: #fff;
        display: flex;
        flex-direction: column;
    }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 15px 30px rgba(10,61,92,0.12); }

    .product-image {
        position: relative;
        overflow: hidden;
        height: 230px;
        background: #f8f9fa;
        flex-shrink: 0;
    }
    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    .product-card:hover .product-image img { transform: scale(1.08); }

    .product-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: var(--accent-gold);
        color: var(--primary-dark);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        z-index: 2;
    }

    .card-body { padding: 1.2rem; display: flex; flex-direction: column; flex: 1; }
    .product-category {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.3rem;
    }
    .product-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
    }
    .product-price {
        font-weight: 700;
        color: var(--accent-coral);
        font-size: 1.2rem;
        margin-bottom: 0.8rem;
    }
    .btn-view-detail {
        background: var(--primary-teal);
        color: #fff;
        border: none;
        border-radius: 30px;
        padding: 8px 20px;
        font-weight: 500;
        font-size: 0.9rem;
        width: 100%;
        transition: all 0.3s;
    }
    .btn-view-detail:hover { background: var(--primary-dark); color: #fff; }

    .pagination .page-link {
        border: none;
        border-radius: 12px;
        margin: 0 4px;
        color: var(--primary-dark);
        font-weight: 500;
        padding: 0.5rem 1rem;
        transition: all 0.2s;
    }
    .pagination .page-item.active .page-link { background: var(--primary-teal); color: white; }
    .pagination .page-link:hover { background: rgba(26,122,140,0.1); color: var(--primary-teal); }

    @media (max-width: 991.98px) {
        .filter-sidebar { position: static; }
        .filter-section { display: none; }
        .product-image { height: 180px; }
    }

    .offcanvas-filter .filter-section {
        display: block;
        box-shadow: none;
        border: none;
        padding: 1rem;
    }
</style>

<!-- ============================================================
     KONTEN HALAMAN PRODUK
============================================================ -->
<section class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><?= tt('home') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= tt('menu') ?></li>
            </ol>
        </nav>
        <h2 class="section-title">
            <i class="fas fa-store me-2"></i><?= tt('menu') ?>
        </h2>
        <p class="text-muted">
            <?= $current_lang == 'id' 
                ? 'Temukan oleh-oleh khas Kepulauan Riau yang sempurna untuk Anda.' 
                : 'Find the perfect Riau Islands souvenir for you.' ?>
        </p>
    </div>

    <!-- Search & Sort Bar -->
    <div class="search-sort-bar">
        <div class="row g-3 align-items-center">
            <div class="col-lg-6 col-md-6 col-12">
                <form method="GET" id="searchForm" role="search">
                    <?php if ($category_slug): ?>
                        <input type="hidden" name="category" value="<?= e($category_slug) ?>">
                    <?php endif; ?>
                    <div class="search-input-group">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="<?= tt('search') ?>" 
                               value="<?= e($search) ?>" 
                               aria-label="Search products"
                               id="searchInput">
                        <button type="button" class="clear-btn" id="clearSearchBtn" title="Clear search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-6 col-md-6 col-12">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <button class="btn btn-outline-teal rounded-pill d-lg-none" type="button" 
                            data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                        <i class="fas fa-filter me-1"></i> <?= tt('filter') ?>
                    </button>
                    
                    <form method="GET" id="sortForm" class="flex-grow-1 flex-md-grow-0">
                        <?php if ($category_slug): ?>
                            <input type="hidden" name="category" value="<?= e($category_slug) ?>">
                        <?php endif; ?>
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?= e($search) ?>">
                        <?php endif; ?>
                        <div class="input-group">
                            <label class="input-group-text bg-white border-0" for="sortSelect">
                                <i class="fas fa-sort-amount-down text-primary"></i>
                            </label>
                            <select class="form-select border-0" id="sortSelect" name="sort" onchange="this.form.submit()">
                                <?php foreach ($allowed_sorts as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $sort == $key ? 'selected' : '' ?>>
                                        <?= $sort_labels[$key] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Filter Desktop -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="filter-sidebar">
                <div class="filter-section">
                    <h3 class="filter-title">
                        <span><i class="fas fa-list me-2"></i><?= tt('all_categories') ?></span>
                        <?php if ($category_slug || $search): ?>
                            <a href="products.php" class="btn btn-sm btn-link text-decoration-none p-0">
                                <small><?= tt('reset') ?></small>
                            </a>
                        <?php endif; ?>
                    </h3>
                    <ul class="category-list">
                        <li>
                            <a href="products.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                               class="<?= empty($category_slug) ? 'active' : '' ?>">
                                <?= tt('all_categories') ?>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): 
                            $cat_name = $current_lang == 'id' ? $cat['name_id'] : $cat['name_en'];
                            $is_active = ($category_slug == $cat['slug']);
                        ?>
                        <li>
                            <a href="products.php?category=<?= urlencode($cat['slug']) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="<?= $is_active ? 'active' : '' ?>">
                                <?= e($cat_name) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Daftar Produk -->
        <div class="col-lg-9 col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted small">
                    <?= $current_lang == 'id' 
                        ? "Menampilkan <strong>" . count($products) . "</strong> dari <strong>$total_products</strong> produk" 
                        : "Showing <strong>" . count($products) . "</strong> of <strong>$total_products</strong> products" ?>
                </div>
                <?php if ($category_slug): 
                    $active_cat_name = '';
                    foreach ($categories as $cat) {
                        if ($cat['slug'] == $category_slug) {
                            $active_cat_name = $current_lang == 'id' ? $cat['name_id'] : $cat['name_en'];
                            break;
                        }
                    }
                ?>
                <div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <?= e($active_cat_name) ?> 
                        <a href="products.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                           class="text-white ms-2 text-decoration-none" title="Remove filter">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (count($products) > 0): ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): 
                        $img = !empty($p['image']) ? 'assets/uploads/products/' . $p['image'] : 'https://via.placeholder.com/300x250/0A3D5C/FFFFFF?text=Kepri';
                        $product_name = $current_lang == 'id' ? $p['name_id'] : $p['name_en'];
                        $cat_name = $current_lang == 'id' ? $p['cat_name_id'] : $p['cat_name_en'];
                    ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?= e($img) ?>" alt="<?= e($product_name) ?>" loading="lazy">
                                <?php if ($p['is_featured']): ?>
                                    <span class="product-badge">
                                        <?= $current_lang == 'id' ? 'Unggulan' : 'Featured' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="product-category"><?= e($cat_name) ?></div>
                                <h5 class="product-title"><?= e($product_name) ?></h5>
                                <div class="product-price"><?= rupiah($p['price']) ?></div>
                                <a href="product-detail.php?slug=<?= urlencode($p['slug']) ?>" 
                                   class="btn btn-view-detail">
                                    <?= tt('view_detail') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor;
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h4 class="fw-bold text-muted"><?= tt('no_products') ?></h4>
                    <p class="text-muted">
                        <?= $current_lang == 'id' 
                            ? 'Tidak ada produk yang cocok dengan pencarian Anda.' 
                            : 'No products match your search.' ?>
                    </p>
                    <a href="products.php" class="btn btn-primary-custom rounded-pill px-4">
                        <i class="fas fa-undo me-2"></i>
                        <?= $current_lang == 'id' ? 'Reset Filter' : 'Reset Filters' ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Offcanvas Filter untuk Mobile -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="filterOffcanvasLabel"><?= tt('filter') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body offcanvas-filter">
        <div class="filter-section">
            <h3 class="filter-title"><i class="fas fa-list me-2"></i><?= tt('all_categories') ?></h3>
            <ul class="category-list">
                <li>
                    <a href="products.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                       class="<?= empty($category_slug) ? 'active' : '' ?>">
                        <?= tt('all_categories') ?>
                    </a>
                </li>
                <?php foreach ($categories as $cat): 
                    $cat_name = $current_lang == 'id' ? $cat['name_id'] : $cat['name_en'];
                    $is_active = ($category_slug == $cat['slug']);
                ?>
                <li>
                    <a href="products.php?category=<?= urlencode($cat['slug']) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="<?= $is_active ? 'active' : '' ?>">
                        <?= e($cat_name) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- JavaScript untuk interaktivitas -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const searchForm = document.getElementById('searchForm');

    function toggleClearButton() {
        clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
    }

    toggleClearButton();
    searchInput.addEventListener('input', toggleClearButton);

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        toggleClearButton();
        searchForm.submit();
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>