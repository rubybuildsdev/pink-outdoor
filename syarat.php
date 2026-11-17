<?php require_once 'config/config.php'; $pageTitle = 'Syarat & Ketentuan'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Syarat & Ketentuan — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>
<div class="page-hero-dark">
  <div class="container">
    <h1 class="fw-800 mb-1">Syarat & Ketentuan Sewa</h1>
    <p class="mb-0" style="color:#94a3b8;">Ketentuan resmi demi kenyamanan dan keselamatan ekspedisi alam terbukamu</p>
  </div>
</div>
<div class="container py-5" style="max-width:760px;">
  <?php $sections = [
    ['Ketentuan Umum', [
      'Penyewa wajib memiliki akun terdaftar dan berusia minimal 17 tahun atau didampingi orang tua/wali.',
      'Booking dianggap sah setelah pembayaran terverifikasi oleh admin.',
      'Pink Outdoor Gear Rent berhak menolak atau membatalkan pesanan yang mencurigakan.',
    ]],
    ['Pembayaran', [
      'Pembayaran dilakukan di muka (full payment) sebelum gear diserahkan.',
      'Deposit sebesar 50% dari total sewa dibebankan sebagai jaminan.',
      'Deposit dikembalikan setelah gear kembali dalam kondisi baik.',
    ]],
    ['Penggunaan Gear', [
      'Gear wajib digunakan sesuai fungsinya dan dijaga dengan baik.',
      'Dilarang menyewakan ulang gear kepada pihak lain.',
      'Penyewa bertanggung jawab atas kehilangan atau kerusakan gear selama masa sewa.',
    ]],
    ['Kerusakan & Kehilangan', [
      'Kerusakan ringan (lecet kecil, ritsleting sedikit macet) tidak dikenakan biaya.',
      'Kerusakan berat atau kehilangan gear dikenakan biaya penggantian sesuai nilai gear.',
      'Penyewa wajib melaporkan kerusakan atau kehilangan sebelum pengembalian.',
    ]],
    ['Pengembalian', [
      'Gear dikembalikan pada tanggal yang telah disepakati sesuai booking.',
      'Keterlambatan pengembalian dikenakan biaya Rp 25.000/jam atau Rp 50.000/hari.',
      'Pengembalian di luar jam operasional (08.00-20.00) harus dikonfirmasi terlebih dahulu.',
    ]],
    ['Pembatalan', [
      'Pembatalan lebih dari 3 hari sebelum tanggal sewa: refund 75%.',
      'Pembatalan 1-3 hari sebelum tanggal sewa: refund 50%.',
      'Pembatalan kurang dari 24 jam atau no-show: tidak ada refund.',
    ]],
  ];
  foreach ($sections as [$title, $items]): ?>
  <div class="section-card mb-3">
    <div class="section-title"><?= $title ?></div>
    <ul class="list-unstyled mb-0">
      <?php foreach ($items as $item): ?>
      <li class="mb-2 d-flex gap-2 align-items-start">
        <i class="bi bi-dot text-pink" style="font-size:1.4rem;line-height:1;flex-shrink:0;margin-top:-2px;"></i>
        <span class="text-muted small"><?= htmlspecialchars($item) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
  <p class="text-muted small text-center mt-4">Terakhir diperbarui: Januari 2025. Dengan melakukan booking, kamu menyetujui seluruh ketentuan di atas.</p>
</div>
<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
