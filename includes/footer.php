<footer class="mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="fw-bold text-warning">Kepri Punya Cerite</h5>
                <p class="small"><?= $current_lang=='id'?'Oleh-oleh khas Kepulauan Riau yang autentik dan berkualitas.':'Authentic and quality souvenirs from Riau Islands.' ?></p>
            </div>
            <div class="col-md-4">
                <h6><?= t('contact') ?></h6>
                <p class="small"><i class="fas fa-map-marker-alt me-2"></i><?= e(getSetting($pdo, 'store_address')) ?></p>
                <p class="small"><i class="fab fa-whatsapp me-2"></i><a href="https://wa.me/<?= e(getSetting($pdo, 'wa_number')) ?>" target="_blank"><?= e(getSetting($pdo, 'store_phone')) ?></a></p>
            </div>
            <div class="col-md-4">
                <h6><?= t('menu') ?></h6>
                <ul class="list-unstyled small">
                    <li><a href="index.php" class="text-decoration-none text-white-50"><?= t('home') ?></a></li>
                    <li><a href="products.php" class="text-decoration-none text-white-50"><?= t('menu') ?></a></li>
                    <li><a href="about.php" class="text-decoration-none text-white-50"><?= t('about') ?></a></li>
                    <li><a href="contact.php" class="text-decoration-none text-white-50"><?= t('contact') ?></a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary">
        <div class="text-center small">&copy; 2026 <strong>Kepri Punya Cerite</strong>. <?= $current_lang=='id'?'Dibuat dengan 💙 untuk Kepri':'Made with 💙 for Kepri' ?></div>
    </div>
</footer>

<!-- Bootstrap JS Bundle (CDN Eksternal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- ===== JAVASCRIPT INLINE (Internal) ===== -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Auto-close alert messages after 5 seconds (jika ada)
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.remove(); }, 500);
            }, 5000);
        });

        // 2. Konfirmasi hapus produk di keranjang (jika ada tombol hapus)
        const deleteButtons = document.querySelectorAll('.btn-outline-danger');
        deleteButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (!confirm('<?= $current_lang=='id'?'Yakin ingin menghapus item ini?':'Are you sure you want to remove this item?' ?>')) {
                    e.preventDefault();
                }
            });
        });

        // 3. Validasi form checkout & contact sederhana (client-side)
        const forms = document.querySelectorAll('form[action*="checkout"], form[action*="contact"]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const inputs = form.querySelectorAll('input[required], textarea[required]');
                let valid = true;
                inputs.forEach(function(input) {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                if (!valid) {
                    e.preventDefault();
                    alert('<?= $current_lang=='id'?'Harap isi semua field yang wajib diisi.':'Please fill in all required fields.' ?>');
                }
            });
        });

        // 4. Preview gambar produk di halaman detail (jika ada multiple image)
        const thumbnails = document.querySelectorAll('.img-thumbnail-preview');
        const mainImage = document.getElementById('mainImage');
        if (thumbnails.length > 0 && mainImage) {
            thumbnails.forEach(function(thumb) {
                thumb.addEventListener('click', function() {
                    mainImage.src = this.src;
                });
            });
        }

        // 5. Tambahan: Smooth scroll untuk anchor link (opsional)
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        console.log('✅ Kepri Punya Cerite - Website loaded successfully!');
    });
</script>
</body>
</html>