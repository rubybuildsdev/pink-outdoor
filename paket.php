<?php
require_once 'config/config.php';
$pageTitle = 'Paket Sewa Camping';
$flash = getFlash();

$pakets = [
  [
    'id'       => 'solo',
    'name'     => 'Paket Solo Hiker',
    'sub'      => 'Kapasitas 1 Orang (Ultra Light)',
    'icon'     => 'bi-person-fill',
    'price'    => 75000,
    'badge'    => '',
    'items'    => [
      '1x Carrier 45L + Rain Cover Waterproof',
      '1x Sleeping Bag Polar Bulu Hangat',
      '1x Matras Foam Alumunium',
      '1x Headlamp LED + Baterai',
    ]
  ],
  [
    'id'       => 'couple',
    'name'     => 'Paket Couple Camp',
    'sub'      => 'Kapasitas 2 Orang (Paling Diminati)',
    'icon'     => 'bi-people-fill',
    'price'    => 120000,
    'badge'    => 'Paling Favorit',
    'items'    => [
      '1x Tenda Dome 2P Double Layer Waterproof',
      '2x Carrier 45L + Rain Cover',
      '2x Sleeping Bag Polar Hangat & Bersih',
      '2x Matras Foam Nyaman',
      '1x Kompor Windproof Portable',
      '1x Nesting Cooking Set Lengkap',
      '1x Lampu Tenda Gantung LED',
    ]
  ],
  [
    'id'       => 'family',
    'name'     => 'Paket Family Weekend',
    'sub'      => 'Kapasitas 4 Orang (Keluarga / Teman)',
    'icon'     => 'bi-house-heart-fill',
    'price'    => 180000,
    'badge'    => '',
    'items'    => [
      '1x Tenda Dome 4P Double Layer Luas',
      '2x Carrier 45L Ergonomis',
      '4x Sleeping Bag Polar Wangi',
      '4x Matras Foam Nyaman',
      '1x Kompor Windproof + Adaptor Gas',
      '1x Nesting Cooking Set Lengkap',
      '2x Lampu Tenda Gantung LED',
    ]
  ],
  [
    'id'       => 'group',
    'name'     => 'Paket Group Explorer',
    'sub'      => 'Kapasitas 6–8 Orang (Rombongan)',
    'icon'     => 'bi-boxes',
    'price'    => 220000,
    'badge'    => '',
    'items'    => [
      '2x Tenda Dome 4P Double Layer',
      '4x Carrier 50L–60L Ergonomis',
      '4x Sleeping Bag Polar Tebal',
      '4x Matras Foam',
      '2x Kompor Portable + Nesting Set',
      '2x Headlamp LED + Lampu Tenda',
    ]
  ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paket Camping Lengkap — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body style="background:#f8fafc;color:var(--text-primary);">
<?php include 'config/partials/navbar.php'; ?>

<!-- Clean White Page Header -->
<div class="py-5" style="background:#ffffff;border-bottom:1px solid #f1f5f9;">
  <div class="container">
    <div class="max-w-700">
      <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background:#fff1f2;border:1px solid #ffe4e6;font-size:.76rem;font-weight:800;color:var(--pink);">
        <i class="bi bi-box-seam me-1"></i> BUNDLING HEMAT ESENSIAL
      </div>
      <h1 class="fw-800 text-dark mb-2" style="font-size:clamp(2rem, 3.8vw, 2.8rem);letter-spacing:-0.8px;">
        Paket Sewa Camping Lengkap
      </h1>
      <p class="text-secondary mb-0" style="font-size:1.05rem;line-height:1.65;max-width:620px;">
        Seluruh perlengkapan utama sudah kami siapkan dalam satu paket praktis — tenda waterproof, matras, sleeping bag bersih, dan alat masak siap pakai untuk jalur pendakian Garut.
      </p>
    </div>
  </div>
</div>

<div class="container py-5">
  <div class="row g-4 justify-content-center">
    <?php foreach ($pakets as $pkg): ?>
    <div class="col-md-6 col-lg-3">
      <div class="package-card-clean h-100 d-flex flex-column p-4 bg-white" 
           style="border:1.5px solid <?= $pkg['badge'] ? 'var(--pink)' : '#e2e8f0' ?>;border-radius:18px;box-shadow:<?= $pkg['badge'] ? '0 10px 30px rgba(225,29,72,0.12)' : '0 2px 10px rgba(0,0,0,0.03)' ?>;position:relative;transition:all 0.25s ease;">
        
        <?php if ($pkg['badge']): ?>
        <span class="position-absolute top-0 start-50 translate-middle badge" 
              style="background:var(--pink);color:#fff;border-radius:999px;padding:6px 16px;font-size:0.68rem;letter-spacing:0.8px;font-weight:800;box-shadow:0 3px 10px rgba(225,29,72,0.35);">
          <?= htmlspecialchars(strtoupper($pkg['badge'])) ?>
        </span>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="d-inline-flex align-items-center justify-content-center rounded-3" 
               style="width:38px;height:38px;background:#fff1f2;color:var(--pink);font-size:1.15rem;flex-shrink:0;">
            <i class="bi <?= $pkg['icon'] ?>"></i>
          </div>
          <h5 class="fw-800 text-dark mb-0" style="font-size:1.15rem;"><?= htmlspecialchars($pkg['name']) ?></h5>
        </div>
        <div class="text-muted small mb-3"><?= htmlspecialchars($pkg['sub']) ?></div>

        <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
          <div class="text-muted small" style="font-size:.74rem;">Tarif Sewa Harian:</div>
          <div class="fw-800 text-dark" style="font-size:1.45rem;letter-spacing:-0.5px;">
            Rp <?= number_format($pkg['price'], 0, ',', '.') ?>
            <span class="text-muted fw-normal" style="font-size:.8rem;">/ hari</span>
          </div>
        </div>

        <div class="small fw-700 text-dark mb-2 text-uppercase" style="font-size:.72rem;letter-spacing:.5px;">
          Rincian Isi Paket (<?= count($pkg['items']) ?> Alat):
        </div>
        <ul class="list-unstyled mb-4 flex-grow-1" style="font-size:.82rem;line-height:1.75;">
          <?php foreach ($pkg['items'] as $itemText): ?>
          <li class="d-flex align-items-start gap-2 mb-1">
            <i class="bi bi-check2-circle text-pink mt-1 flex-shrink-0"></i>
            <span class="text-secondary"><?= htmlspecialchars($itemText) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="mt-auto d-grid gap-2">
          <form method="POST" action="cart.php" class="m-0">
            <input type="hidden" name="action"       value="add_paket">
            <input type="hidden" name="paket_id"     value="<?= $pkg['id'] ?>">
            <input type="hidden" name="paket_name"   value="<?= htmlspecialchars('Paket ' . $pkg['name']) ?>">
            <input type="hidden" name="paket_price"  value="<?= $pkg['price'] ?>">
            <input type="hidden" name="redirect"     value="paket.php">
            <button type="submit" class="btn-pink-outline w-100 py-2" style="font-size:.84rem;border-radius:8px;background:#ffffff;">
              <i class="bi bi-bag-plus me-1"></i>Tambah ke Keranjang
            </button>
          </form>

          <form method="POST" action="cart.php" class="m-0">
            <input type="hidden" name="action"       value="add_paket">
            <input type="hidden" name="paket_id"     value="<?= $pkg['id'] ?>">
            <input type="hidden" name="paket_name"   value="<?= htmlspecialchars('Paket ' . $pkg['name']) ?>">
            <input type="hidden" name="paket_price"  value="<?= $pkg['price'] ?>">
            <input type="hidden" name="redirect"     value="keranjang.php">
            <button type="submit" class="btn-pink w-100 py-2" style="font-size:.84rem;border-radius:8px;">
              Sewa Sekarang
            </button>
          </form>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Operational Guarantees -->
  <div class="row g-4 mt-5 pt-4 border-top" style="border-color:#e2e8f0;">
    <div class="col-md-4">
      <div class="d-flex gap-3 align-items-start p-4 bg-white rounded-4 border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:#fff1f2;color:var(--pink);font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-shield-check"></i>
        </div>
        <div>
          <div class="fw-700 text-dark mb-1">Kondisi Teruji & Wangi</div>
          <div class="text-secondary small">Tenda telah dicek anti-bocor dan sleeping bag dicuci wangi setelah tiap sewa.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-3 align-items-start p-4 bg-white rounded-4 border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:#fff1f2;color:var(--pink);font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-truck"></i>
        </div>
        <div>
          <div class="fw-700 text-dark mb-1">Layanan Antar Basecamp</div>
          <div class="text-secondary small">Bisa diantar langsung ke Stasiun Garut atau basecamp Papandayan & Guntur.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="d-flex gap-3 align-items-start p-4 bg-white rounded-4 border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:#fff1f2;color:var(--pink);font-size:1.3rem;flex-shrink:0;">
          <i class="bi bi-whatsapp"></i>
        </div>
        <div>
          <div class="fw-700 text-dark mb-1">Konsultasi Gear Bebas</div>
          <div class="text-secondary small">Bingung menentukan alat? Konsultasikan rute pendakianmu via WhatsApp CS.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
