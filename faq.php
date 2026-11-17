<?php require_once 'config/config.php'; $pageTitle = 'FAQ'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>
<div class="page-hero-dark">
  <div class="container"><h1 class="fw-800 mb-1">Pertanyaan Umum (FAQ)</h1><p class="mb-0" style="color:#94a3b8;">Jawaban lengkap seputar penyewaan, jaminan identitas, dan layanan antar basecamp Garut</p></div>
</div>
<div class="container py-5" style="max-width:760px;">
  <div class="accordion" id="faqAcc">
    <?php $faqs = [
      ['Bagaimana cara memesan?','Pilih gear di Katalog, klik "Sewa Sekarang", isi tanggal, lalu checkout. Konfirmasi dikirim via WhatsApp dalam 1 jam.'],
      ['Berapa minimal masa sewa?','Minimal 1 hari. Tidak ada batas maksimal — semakin lama semakin hemat.'],
      ['Apakah ada deposit?','Ya, 50% dari total sewa sebagai jaminan. Dikembalikan setelah gear kembali dalam kondisi baik.'],
      ['Apa yang terjadi jika gear rusak?','Kerusakan ringan tidak dikenakan biaya. Kerusakan berat akibat kelalaian dikenakan biaya penggantian.'],
      ['Apakah bisa pesan mendadak (H-1)?','Bisa, selama stok tersedia. Disarankan pesan minimal H-3 untuk memastikan gear pilihan tersedia.'],
      ['Metode pembayaran apa saja?','Transfer Bank (BCA, BRI, Mandiri) dan E-wallet (GoPay, OVO, DANA).'],
      ['Apakah ada layanan antar-jemput?','Ada, gratis untuk area Garut dengan transaksi di atas Rp 150.000. Luar kota dikenakan ongkos kirim.'],
      ['Bagaimana cara menggunakan Paket Sewa?','Pilih paket di halaman Paket Sewa, tambahkan ke keranjang, lalu checkout. Semua gear dalam paket sudah termasuk.'],
    ]; foreach ($faqs as $i => [$q,$a]): ?>
    <div class="accordion-item border-0 mb-2" style="border-radius:var(--radius)!important;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
      <h2 class="accordion-header">
        <button class="accordion-button fw-600 <?= $i>0?'collapsed':'' ?>"
                style="font-size:.95rem;" type="button"
                data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
          <?= htmlspecialchars($q) ?>
        </button>
      </h2>
      <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i===0?'show':'' ?>" data-bs-parent="#faqAcc">
        <div class="accordion-body text-muted"><?= htmlspecialchars($a) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="section-card text-center mt-5">
    <h5 class="fw-700">Masih ada pertanyaan?</h5>
    <p class="text-muted">Tim kami siap membantu kamu via WhatsApp</p>
    <a href="https://wa.me/6281234567890" class="btn-pink px-5 py-2">
      <i class="bi bi-whatsapp me-2"></i>Chat WhatsApp
    </a>
  </div>
</div>
<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
