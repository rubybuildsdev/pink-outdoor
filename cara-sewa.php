<?php require_once 'config/config.php'; $pageTitle = 'Cara Sewa'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cara Sewa — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>
<div class="page-hero-dark">
  <div class="container">
    <h1 class="fw-800 mb-1">Alur & Cara Sewa Gear</h1>
    <p class="mb-0" style="color:#94a3b8;">Proses cepat, transparan, dan siap antar langsung ke basecamp pendakianmu</p>
  </div>
</div>
<div class="container py-5" style="max-width:800px;">
  <div class="d-flex flex-column gap-4">
    <?php $steps = [
      ['1','bi-person-plus','Daftar Akun','Buat akun gratis di halaman Daftar. Cukup isi nama, email, dan password.'],
      ['2','bi-search','Pilih Gear','Jelajahi Katalog Gear atau Paket Sewa. Pilih yang sesuai kebutuhanmu.'],
      ['3','bi-calendar-plus','Booking','Klik "Sewa Sekarang", isi tanggal sewa & kembali, pilih metode pembayaran, lalu konfirmasi.'],
      ['4','bi-credit-card','Bayar','Transfer sesuai jumlah ke rekening/e-wallet yang tertera. Upload bukti di halaman "Booking Saya".'],
      ['5','bi-check-circle','Konfirmasi','Tim kami verifikasi pembayaran dan menghubungimu via WhatsApp untuk detail pengambilan.'],
      ['6','bi-bag-check','Ambil Gear','Ambil gear di basecamp kami atau minta diantar (untuk area Garut, gratis di atas Rp 150.000).'],
      ['7','bi-arrow-return-left','Kembalikan','Kembalikan gear sesuai tanggal yang disepakati. Staf kami akan cek kondisi gear.'],
    ];
    foreach ($steps as [$num,$ico,$title,$desc]): ?>
    <div class="d-flex gap-4 align-items-start section-card">
      <div class="d-flex align-items-center justify-content-center fw-800 text-white flex-shrink-0"
           style="width:50px;height:50px;border-radius:50%;background:var(--pink);font-size:1.2rem;">
        <?= $num ?>
      </div>
      <div>
        <h6 class="fw-700 mb-1"><i class="bi <?= $ico ?> me-2 text-pink"></i><?= $title ?></h6>
        <p class="text-muted small mb-0"><?= $desc ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-5">
    <a href="katalog.php" class="btn-pink px-5 py-3 fw-700 me-3">Mulai Sewa Sekarang</a>
    <a href="faq.php" class="btn-pink-outline px-4 py-3">Lihat FAQ</a>
  </div>
</div>
<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
