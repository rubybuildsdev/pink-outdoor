<?php require_once 'config/config.php'; $pageTitle = 'Tentang Kami'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tentang Kami — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>

<div class="page-hero-dark">
  <div class="container">
    <div class="max-w-700">
      <h1 class="fw-800 mb-1">Tentang Basecamp Pink Outdoor</h1>
      <p class="mb-0" style="color:#94a3b8;">Dedikasi kami menyediakan perlengkapan outdoor terawat bagi setiap pendaki di Garut</p>
    </div>
  </div>
</div>

<section class="py-5 bg-white">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="badge bg-pink text-white px-3 mb-3" style="border-radius:20px;">Cerita Kami</span>
        <h2 class="fw-800 mb-3">Lahir dari Cinta<br>Terhadap Alam</h2>
        <p class="text-muted lh-lg">Pink Outdoor Gear Rent lahir pada 2021 di Garut, Jawa Barat — dari kecintaan mendalam terhadap alam terbuka. Kami hadir karena kami sendiri tahu betapa mahalnya membeli perlengkapan outdoor berkualitas hanya untuk dipakai sesekali.</p>
        <p class="text-muted lh-lg">Dengan armada gear yang selalu terjaga kebersihannya dan tim yang responsif, kami telah melayani lebih dari <strong>2.000+ penyewa</strong> dari seluruh Jawa Barat.</p>
        <p class="text-muted lh-lg">Misi kami sederhana: <em>buat setiap orang bisa menikmati alam, tanpa hambatan biaya perlengkapan.</em></p>
      </div>
      <div class="col-lg-6">
        <div class="row g-3">
          <?php foreach ([
            ['stat-pink','2K+','Pelanggan Puas'],
            ['stat-purple','150+','Item Gear'],
            ['stat-blue','4.9★','Rating Rata-rata'],
            ['stat-green','2021','Tahun Berdiri'],
          ] as [$cls,$num,$lbl]): ?>
          <div class="col-6">
            <div class="stat-card <?= $cls ?> text-center p-4">
              <div class="stat-num"><?= $num ?></div>
              <div class="stat-label"><?= $lbl ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5 bg-light">
  <div class="container">
    <div class="text-center mb-5"><h2 class="fw-800">Nilai-Nilai Kami</h2></div>
    <div class="row g-4">
      <?php foreach ([
        ['bi-heart-fill','Passion','Kami peduli pada setiap petualanganmu, bukan sekadar transaksi sewa.'],
        ['bi-shield-check','Kepercayaan','Setiap gear kami cek dan bersihkan agar kamu tenang di alam.'],
        ['bi-people-fill','Komunitas','Kami membangun komunitas pendaki yang saling mendukung.'],
        ['bi-leaf','Alam','Kami mendorong prinsip Leave No Trace di setiap perjalanan.'],
      ] as [$ico,$title,$desc]): ?>
      <div class="col-sm-6 col-lg-3">
        <div class="section-card text-center h-100">
          <i class="bi <?= $ico ?> text-pink fs-1 mb-3 d-block"></i>
          <h6 class="fw-700"><?= $title ?></h6>
          <p class="text-muted small mb-0"><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container" style="max-width:700px;">
    <div class="text-center mb-4"><h2 class="fw-800">Tim Kami</h2></div>
    <div class="row g-4 justify-content-center">
      <?php foreach ([
        ['RD','Risa Dewi','Founder & Owner'],
        ['AS','Andi Saputra','Gear Manager'],
        ['NF','Nurul Fitri','Customer Support'],
      ] as [$ini,$name,$role]): ?>
      <div class="col-sm-4 text-center">
        <div class="section-card">
          <div class="mx-auto mb-3 fw-800 text-white d-flex align-items-center justify-content-center"
               style="width:64px;height:64px;border-radius:50%;background:var(--pink);font-size:1.4rem;">
            <?= $ini ?>
          </div>
          <div class="fw-700"><?= $name ?></div>
          <div class="text-muted small"><?= $role ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
