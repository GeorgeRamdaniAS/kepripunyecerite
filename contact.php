<?php
include_once 'config/database.php';
include_once 'includes/functions.php';

$wa_number = getSetting($pdo, 'wa_number');
$address   = 'Gerbang Dermaga Penyengat, Jl. Pos No.16, Tanjungpinang Kota, Kec. Tj. Pinang Kota, Kota Tanjung Pinang, Kepulauan Riau 29111';
$phone     = getSetting($pdo, 'store_phone');
$email     = getSetting($pdo, 'store_email');

// URL embed peta (tanpa API Key)
$map_embed_url = 'https://maps.google.com/maps?q=' . urlencode($address) . '&t=&z=17&ie=UTF8&iwloc=&output=embed';
$maps_link     = 'https://maps.app.goo.gl/RnXsc3y8Ek5q6T4Q6';

// Jika form dikirim, arahkan ke WhatsApp
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $email_input = trim($_POST['email'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    if ($name === '' || !filter_var($email_input, FILTER_VALIDATE_EMAIL) || $message === '') {
        $form_error = 'Mohon isi nama, email yang valid, dan pesan.';
    } else {
        $wa_msg = "Halo Admin Kepri Punya Cerite!\n\nNama: $name\nEmail: $email_input\nPesan: $message";
        header('Location: https://wa.me/' . rawurlencode($wa_number) . '?text=' . urlencode($wa_msg));
        exit;
    }
}
include_once 'includes/header.php';
?>

<style>
    .contact-info p {
        margin-bottom: 0.75rem;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        line-height: 1.5;
    }
    .contact-info i {
        color: var(--primary-teal);
        margin-top: 0.25rem;
        flex-shrink: 0;
    }
    .map-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border: 1px solid #eef2f6;
    }
    .map-container iframe {
        display: block;
        width: 100%;
        height: 250px;
        border: 0;
    }
    .btn-open-maps {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: var(--primary-teal);
        text-decoration: none;
        font-weight: 600;
        margin-top: 0.5rem;
        transition: all 0.2s;
    }
    .btn-open-maps:hover {
        color: var(--primary-dark);
        gap: 0.75rem;
    }
</style>

<section class="container py-4">
    <h2 class="fw-bold mb-4"><i class="fas fa-envelope me-2"></i><?= t('contact') ?></h2>
    <div class="row g-4">
        <!-- Form Kirim Pesan -->
        <div class="col-md-6">
            <div class="bg-white p-4 rounded-4 shadow-sm h-100">
                <?php if (!empty($form_error)): ?>
                    <div class="alert alert-danger"><?= e($form_error) ?></div>
                <?php endif; ?>
                <h5><?= $current_lang == 'id' ? 'Kirim Pesan' : 'Send Message' ?></h5>
                <form method="POST">
                    <div class="mb-3">
                        <label><?= t('your_name') ?></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label><?= t('your_email') ?></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label><?= t('your_message') ?></label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold w-100 rounded-pill">
                        <i class="fab fa-whatsapp me-2"></i><?= t('send_message') ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Informasi Kontak + Peta -->
        <div class="col-md-6">
            <div class="bg-white p-4 rounded-4 shadow-sm h-100">
                <h5><?= $current_lang == 'id' ? 'Informasi Kontak' : 'Contact Info' ?></h5>
                <div class="contact-info mt-3">
                    <p>
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= e($address) ?></span>
                    </p>
                    <p>
                        <i class="fas fa-phone"></i>
                        <span><?= e($phone) ?></span>
                    </p>
                    <p>
                        <i class="fas fa-envelope"></i>
                        <span><?= e($email) ?></span>
                    </p>
                    <p>
                        <i class="fab fa-whatsapp"></i>
                        <a href="https://wa.me/<?= e($wa_number) ?>" target="_blank" rel="noopener">
                            <?= e($phone) ?>
                        </a>
                    </p>
                </div>

                <!-- Peta Lokasi -->
                <div class="mt-4">
                    <div class="map-container">
                        <iframe
                            src="<?= htmlspecialchars($map_embed_url, ENT_QUOTES, 'UTF-8') ?>"
                            width="100%"
                            height="250"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="<?= $current_lang == 'id' ? 'Lokasi Gerbang Dermaga Penyengat' : 'Gerbang Dermaga Penyengat Location' ?>">
                        </iframe>
                    </div>
                    <a href="<?= e($maps_link) ?>" target="_blank" rel="noopener" class="btn-open-maps">
                        <i class="fas fa-external-link-alt"></i>
                        <?= $current_lang == 'id' ? 'Buka di Google Maps' : 'Open in Google Maps' ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>