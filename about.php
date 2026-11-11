<?php
include_once 'config/database.php';
include_once 'includes/functions.php';
include_once 'includes/header.php';
?>
<section class="container py-4">
    <h2 class="fw-bold mb-4"><i class="fas fa-info-circle me-2"></i><?= t('about') ?></h2>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="bg-white p-4 rounded-4 shadow-sm">
                <h5><?= $current_lang=='id'?'Tentang Kepri Punya Cerite':'About Kepri Punya Cerite' ?></h5>
                <p><?= $current_lang=='id'?
                    'Kepri Punya Cerite adalah toko oleh-oleh khas Kepulauan Riau yang berdiri sejak tahun 2020. Kami menyediakan berbagai produk autentik seperti kaos, gantungan kunci, miniatur rumah Melayu, dan suvenir lainnya yang mencerminkan kekayaan budaya Kepri.':
                    'Kepri Punya Cerite is a souvenir shop typical of Riau Islands, established in 2020. We provide authentic products such as t-shirts, keychains, Malay house miniatures, and other souvenirs that reflect the rich culture of Kepri.' ?></p>
                <p><?= $current_lang=='id'?
                    'Kami berkomitmen untuk memberikan produk berkualitas tinggi dengan harga terjangkau, serta pelayanan terbaik bagi setiap pelanggan, baik lokal maupun mancanegara.':
                    'We are committed to providing high-quality products at affordable prices, and the best service for every customer, both local and international.' ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="bg-white p-4 rounded-4 shadow-sm">
                <h5><?= t('vision_mission') ?></h5>
                <h6 class="fw-bold mt-3"><?= $current_lang=='id'?'Visi':'Vision' ?></h6>
                <p><?= $current_lang=='id'?
                    'Menjadi toko oleh-oleh terkemuka di Kepulauan Riau yang dikenal akan keaslian produk dan kepuasan pelanggan.':
                    'To become a leading souvenir shop in Riau Islands known for product authenticity and customer satisfaction.' ?></p>
                <h6 class="fw-bold mt-3"><?= $current_lang=='id'?'Misi':'Mission' ?></h6>
                <ul>
                    <li><?= $current_lang=='id'?'Menyediakan produk oleh-oleh berkualitas tinggi dengan harga bersaing.':'Provide high-quality souvenir products at competitive prices.' ?></li>
                    <li><?= $current_lang=='id'?'Melestarikan budaya Melayu melalui produk-produk autentik.':'Preserve Malay culture through authentic products.' ?></li>
                    <li><?= $current_lang=='id'?'Memberikan pengalaman berbelanja yang nyaman dan ramah.':'Provide a comfortable and friendly shopping experience.' ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php include_once 'includes/footer.php'; ?>a