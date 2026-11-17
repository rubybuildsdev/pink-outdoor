<?php
require_once 'config/config.php';
$pageTitle  = 'Beranda';
$products   = $conn->query("
  SELECT p.*, c.name AS category_name, c.slug AS category_slug,
         (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE product_id = p.id) AS avg_rating,
         (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) AS review_count
  FROM products p 
  JOIN categories c ON p.category_id=c.id 
  WHERE p.status='available' AND c.slug != 'paket-camping' 
  ORDER BY p.id DESC 
  LIMIT 8
")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM categories WHERE slug != 'paket-camping' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$flash      = getFlash();
$baseUrl    = BASE_URL;
$redirectPage = 'index.php#katalog';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= SITE_NAME ?> — Sewa Perlengkapan Camping & Pendakian Resmi di Garut</title>
  <meta name="description" content="Pusat sewa perlengkapan camping dan pendakian resmi di Garut. Tenda waterproof, sleeping bag bersih, carrier, dan paket camping lengkap. Siap antar ke basecamp Sagara, Papandayan, Guntur, dan Cikuray.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body style="background:#ffffff;color:var(--text-primary);">
<?php include 'config/partials/navbar.php'; ?>

<!-- ═══ 1. HERO SECTION (CLEAN WHITE AESTHETIC & BASECAMP SHOWCASE) ════════════ -->
<section class="py-5 py-lg-6" style="background:#ffffff;border-bottom:1px solid #f1f5f9;position:relative;">
  <div class="container">
    <div class="row align-items-center g-5">
      <!-- Left Column: Value Proposition & CTAs -->
      <div class="col-lg-7">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background:#fff1f2;border:1px solid #ffe4e6;font-size:.78rem;font-weight:700;color:var(--pink);letter-spacing:.4px;">
          <i class="bi bi-geo-alt-fill"></i> BASECAMP RESMI KABUPATEN GARUT
        </div>
        <h1 class="fw-800 text-dark mb-3" style="font-size:clamp(2.3rem, 4.5vw, 3.4rem);line-height:1.15;letter-spacing:-1px;">
          Explore Our Top-Quality Outdoor Gear Rentals
        </h1>
        <p class="text-secondary mb-4" style="font-size:1.05rem;line-height:1.75;max-width:580px;">
          Tenda double-layer waterproof, sleeping bag wangi terawat, carrier ergonomis, nesting kompor, hingga paket hemat lengkap. Ambil di basecamp kami atau kami antar langsung ke Stasiun Garut dan titik kumpul basecamp pendakian (Sagara, Papandayan, Guntur, Cikuray).
        </p>

        <div class="d-flex gap-3 flex-wrap mb-4">
          <a href="katalog.php" class="btn-pink btn-lg" style="border-radius:var(--radius-pill);box-shadow:0 4px 14px rgba(225,29,72,0.28);">
            <i class="bi bi-grid-fill me-1"></i> Pilih Gear Sekarang
          </a>
          <a href="paket.php" class="btn-pink-outline btn-lg" style="border-radius:var(--radius-pill);border-color:#e2e8f0;background:#ffffff;color:#0f172a;">
            <i class="bi bi-box-seam me-1"></i> Paket Camping Lengkap
          </a>
        </div>

        <!-- Trust Badges Underneath Hero -->
        <div class="d-flex align-items-center gap-3 gap-md-4 flex-wrap pt-4 border-top" style="border-color:#f1f5f9!important;">
          <div class="d-flex align-items-center gap-2 text-secondary fw-600" style="font-size:.84rem;">
            <i class="bi bi-check-circle-fill text-pink"></i> 100% Bersih & Kering
          </div>
          <div class="d-flex align-items-center gap-2 text-secondary fw-600" style="font-size:.84rem;">
            <i class="bi bi-check-circle-fill text-pink"></i> Cek Fisik Bersama
          </div>
          <div class="d-flex align-items-center gap-2 text-secondary fw-600" style="font-size:.84rem;">
            <i class="bi bi-check-circle-fill text-pink"></i> Jaminan 1 KTP/SIM
          </div>
          <div class="d-flex align-items-center gap-2 text-secondary fw-600" style="font-size:.84rem;">
            <i class="bi bi-truck text-pink"></i> Siap Antar Jalur
          </div>
        </div>
      </div>

      <!-- Right Column: Aesthetic Showcase Frame ("OUR BASECAMP") -->
      <div class="col-lg-5">
        <div class="p-4 p-md-5 rounded-4 position-relative" 
             style="background: linear-gradient(145deg, #ffffff 0%, #fff1f2 100%); border: 1.5px solid #ffe4e6; box-shadow: 0 15px 35px rgba(225,29,72,0.06);">
          
          <div class="d-flex align-items-center justify-content-between mb-4">
            <span class="badge" style="background:#ffffff;color:var(--pink);border:1px solid #fecdd3;padding:6px 14px;border-radius:999px;font-size:0.75rem;font-weight:800;letter-spacing:1px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
              <i class="bi bi-compass-fill me-1"></i> OUR BASECAMP
            </span>
            <span class="badge" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;padding:6px 12px;border-radius:999px;font-size:0.72rem;font-weight:700;">
              <i class="bi bi-patch-check-fill me-1"></i> Buka 07.00 - 21.00
            </span>
          </div>

          <!-- Basecamp Info Box -->
          <div class="bg-white p-3 rounded-3 mb-3 border" style="border-color:#f1f5f9;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <div class="d-flex align-items-center gap-3">
              <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:#fff1f2;color:var(--pink);font-size:1.3rem;flex-shrink:0;">
                <i class="bi bi-geo-alt-fill"></i>
              </div>
              <div>
                <div class="fw-800 text-dark" style="font-size:0.95rem;">Jl. Patriot No. 12, Tarogong Kidul</div>
                <div class="text-muted small">Titik temu strategis Garut Kota dekat terminal & jalur gunung</div>
              </div>
            </div>
          </div>

          <!-- Quick Gear Finder Search Form -->
          <div class="bg-white p-3 p-md-4 rounded-3 border" style="border-color:#f1f5f9;box-shadow:0 4px 16px rgba(0,0,0,0.04);">
            <div class="fw-800 text-dark mb-2" style="font-size:1rem;">Cari Gear Siap Pakai</div>
            <form action="katalog.php" method="GET">
              <div class="mb-3">
                <label class="form-label text-muted small fw-700 mb-1">Kategori Gear</label>
                <select name="cat" class="form-select form-select-sm" style="border-radius:8px;border-color:#e2e8f0;">
                  <option value="0">Semua Kategori Outdoor</option>
                  <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small fw-700 mb-1">Nama Alat atau Spesifikasi</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Misal: Tenda 4P, Carrier Osprey..." style="border-radius:8px;border-color:#e2e8f0;">
              </div>

              <button type="submit" class="btn-pink w-100 py-2 fw-700" style="border-radius:8px;font-size:0.86rem;">
                <i class="bi bi-search me-1"></i> Cek Ketersediaan Alat
              </button>
            </form>
          </div>

          <!-- Customer Trust Rating -->
          <div class="d-flex align-items-center justify-content-between mt-3 pt-2 text-muted" style="font-size:.76rem;">
            <span><i class="bi bi-star-fill text-warning me-1"></i> <strong>4.9/5.0</strong> dari 500+ pendaki</span>
            <span><i class="bi bi-shield-check text-pink me-1"></i> Nota & Kwitansi Resmi</span>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ 2. "BROWSE ALL YOU NEED" CATEGORY SECTION ═══════════════════════════════ -->
<section class="py-5" style="background:#f8fafc;border-bottom:1px solid #f1f5f9;">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-4 mb-lg-5">
      <span class="badge" style="background:#fff1f2;color:var(--pink);border:1px solid #ffe4e6;padding:5px 14px;border-radius:999px;font-size:.74rem;font-weight:800;letter-spacing:.6px;">
        KATEGORI PERLENGKAPAN
      </span>
      <h2 class="fw-800 text-dark mt-2 mb-2" style="font-size:clamp(1.75rem, 3vw, 2.3rem);letter-spacing:-0.5px;">
        Browse All You Need
      </h2>
      <p class="text-secondary mb-0" style="font-size:.95rem;">
        Pilih kategori gear untuk melihat spesifikasi lengkap, ketersediaan harian, dan tarif sewa bersahabat.
      </p>
    </div>

    <!-- Category Icon Cards Grid -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 g-lg-4">
      <?php foreach ($categories as $cat): ?>
      <div class="col">
        <a href="katalog.php?cat=<?= (int)$cat['id'] ?>" 
           class="category-browse-card d-flex flex-column align-items-center justify-content-center text-decoration-none h-100 p-3 bg-white"
           style="border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);transition:all 0.25s ease;">
          <div class="d-flex align-items-center justify-content-center rounded-3 mb-2" 
               style="width:52px;height:52px;background:#fff1f2;color:var(--pink);font-size:1.5rem;transition:all 0.25s ease;">
            <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
          </div>
          <span class="fw-700 text-dark text-center" style="font-size:0.9rem;">
            <?= htmlspecialchars($cat['name']) ?>
          </span>
          <span class="text-pink small fw-600 mt-1" style="font-size:0.75rem;">
            Pilih Gear →
          </span>
        </a>
      </div>
      <?php endforeach; ?>
      <!-- Bundling Paket Camping Shortcut -->
      <div class="col">
        <a href="paket.php" 
           class="category-browse-card d-flex flex-column align-items-center justify-content-center text-decoration-none h-100 p-3 bg-white"
           style="border:1.5px dashed #fecdd3;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.03);transition:all 0.25s ease;background:#fff1f2!important;">
          <div class="d-flex align-items-center justify-content-center rounded-3 mb-2" 
               style="width:52px;height:52px;background:var(--pink);color:#ffffff;font-size:1.5rem;">
            <i class="bi bi-boxes"></i>
          </div>
          <span class="fw-800 text-dark text-center" style="font-size:0.9rem;">
            Paket Camping
          </span>
          <span class="text-pink small fw-700 mt-1" style="font-size:0.75rem;">
            Hemat 20% →
          </span>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ═══ 3. GEAR UNGGULAN SIAP SEWA (CATALOG SECTION) ════════════════════════════ -->
<section id="katalog" class="py-5 py-lg-6" style="background:#ffffff;">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <span class="badge" style="background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;">
          STOK SIAP PAKAI
        </span>
        <h2 class="fw-800 text-dark mt-2 mb-1" style="font-size:clamp(1.75rem, 3vw, 2.2rem);letter-spacing:-0.5px;">
          Gear Outdoor Siap Pakai
        </h2>
        <p class="text-secondary small mb-0">
          Setiap alat telah dicek kelayakan pasak, frame, jahitan, dan kebersihannya sebelum disewakan.
        </p>
      </div>
      <a href="katalog.php" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex align-items-center gap-2 fw-700 px-3 py-2" style="border-radius:var(--radius-pill);border-color:#e2e8f0;color:#0f172a;background:#ffffff;">
        Lihat Semua Gear (<?= count($products) ?>+) <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <!-- Product Grid: 4-Column Layout With Generous Spacing -->
    <div class="row g-4 g-lg-5">
      <?php foreach ($products as $p): ?>
      <div class="col-sm-6 col-lg-3">
        <?php include 'config/partials/product_card.php'; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-5">
      <a href="katalog.php" class="btn-pink btn-lg px-5" style="border-radius:var(--radius-pill);box-shadow:0 4px 14px rgba(225,29,72,0.25);">
        <i class="bi bi-grid me-2"></i> Jelajahi Seluruh Katalog Gear
      </a>
    </div>
  </div>
</section>

<!-- ═══ 4. PROMO BANNER SECTION (SPECIAL SAVING OFFER) ═════════════════════════ -->
<section class="py-4" style="background:#ffffff;">
  <div class="container">
    <div class="p-4 p-lg-5 rounded-4 position-relative overflow-hidden" 
         style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 50%, #ffffff 100%); border: 1.5px solid #fecdd3; box-shadow: 0 4px 20px rgba(225,29,72,0.06);">
      <div class="row align-items-center g-4 position-relative" style="z-index:2;">
        <div class="col-lg-8">
          <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background:#ffffff;border:1px solid #fda4af;font-size:.76rem;font-weight:800;color:var(--pink);letter-spacing:0.5px;">
            <i class="bi bi-tag-fill"></i> PROMO SPESIAL PENDAKIAN GARUT
          </div>
          <h3 class="fw-800 text-dark mb-2" style="font-size:clamp(1.5rem, 3vw, 2.1rem);letter-spacing:-0.5px;">
            Sewa 2 Item atau Lebih, Hemat Hingga 20%!
          </h3>
          <p class="text-secondary mb-0" style="font-size:0.95rem;max-width:580px;line-height:1.65;">
            Rencanakan petualangan mendakimu ke Sagara, Papandayan, Guntur, atau Cikuray bersama teman. Dapatkan potongan bundling otomatis dan jaminan perlengkapan bersih, wangi, serta siap pakai langsung di basecamp.
          </p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="katalog.php" class="btn-pink btn-lg" style="border-radius:var(--radius-pill);box-shadow:0 4px 14px rgba(225,29,72,0.3);">
            <i class="bi bi-lightning-charge-fill me-1"></i> Klaim Diskon Sekarang
          </a>
        </div>
      </div>
      <!-- Aesthetic soft glow circles -->
      <div style="position:absolute;right:-40px;top:-40px;width:220px;height:220px;background:rgba(225,29,72,0.08);border-radius:50%;filter:blur(30px);pointer-events:none;"></div>
      <div style="position:absolute;left:-30px;bottom:-30px;width:180px;height:180px;background:rgba(255,255,255,0.8);border-radius:50%;filter:blur(25px);pointer-events:none;"></div>
    </div>
  </div>
</section>

<!-- ═══ 5. PAKET CAMPING LENGKAP ════════════════════════════════════════════════ -->
<section id="paket" class="py-5 py-lg-6" style="background:#f8fafc;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <span class="badge" style="background:#fff1f2;color:var(--pink);border:1px solid #ffe4e6;padding:5px 14px;border-radius:999px;font-size:.74rem;font-weight:800;letter-spacing:0.5px;">
        PAKET HEMAT LENGKAP
      </span>
      <h2 class="fw-800 text-dark mt-2 mb-2" style="font-size:clamp(1.75rem, 3vw, 2.3rem);letter-spacing:-0.5px;">
        Paket Sewa Camping Siap Berangkat
      </h2>
      <p class="text-secondary" style="font-size:.95rem;">
        Tidak perlu pusing sewa satu per satu. Paket bundling sudah termasuk tenda waterproof, matras, sleeping bag wangi, kompor, dan alat esensial dengan harga lebih ekonomis.
      </p>
    </div>

    <div class="row g-4 justify-content-center">
      <?php
      $packageList = [
        [
          'id'       => 'solo',
          'name'     => 'Paket Solo Hiker',
          'target'   => 'Kapasitas 1 Orang (Ultra Light)',
          'price'    => 75000,
          'featured' => false,
          'items'    => [
            '1x Carrier 45L + Rain Cover',
            '1x Sleeping Bag Polar Bulu Hangat',
            '1x Matras Foam Alumunium',
            '1x Headlamp LED + Baterai Cadangan',
          ]
        ],
        [
          'id'       => 'couple',
          'name'     => 'Paket Couple Camp',
          'target'   => 'Kapasitas 2 Orang (Paling Diminati)',
          'price'    => 120000,
          'featured' => true,
          'items'    => [
            '1x Tenda Dome 2P Double Layer Waterproof',
            '2x Sleeping Bag Polar Hangat',
            '2x Matras Foam Nyaman',
            '1x Kompor Windproof Portable',
            '1x Nesting Cooking Set (Panci & Wajan)',
            '1x Lampu Tenda Gantung LED',
          ]
        ],
        [
          'id'       => 'family',
          'name'     => 'Paket Family Weekend',
          'target'   => 'Kapasitas 4 Orang (Keluarga / Teman)',
          'price'    => 180000,
          'featured' => false,
          'items'    => [
            '1x Tenda Dome 4P Double Layer Luas',
            '4x Sleeping Bag Polar Bersih',
            '4x Matras Nyaman',
            '1x Kompor Windproof + Adaptor',
            '1x Nesting Cooking Set Lengkap',
            '2x Lampu Tenda Gantung',
          ]
        ],
        [
          'id'       => 'group',
          'name'     => 'Paket Group Explorer',
          'target'   => 'Kapasitas 6–8 Orang (Rombongan)',
          'price'    => 220000,
          'featured' => false,
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

      foreach ($packageList as $pkg):
      ?>
      <div class="col-md-6 col-lg-3">
        <div class="package-card-clean h-100 d-flex flex-column p-4 bg-white" 
             style="border:1px solid <?= $pkg['featured'] ? 'var(--pink)' : '#e2e8f0' ?>;border-radius:16px;box-shadow:<?= $pkg['featured'] ? '0 8px 24px rgba(225,29,72,0.12)' : '0 2px 8px rgba(0,0,0,0.03)' ?>;position:relative;">
          
          <?php if ($pkg['featured']): ?>
          <span class="position-absolute top-0 start-50 translate-middle badge" 
                style="background:var(--pink);color:#fff;border-radius:999px;padding:5px 14px;font-size:0.68rem;letter-spacing:0.8px;font-weight:800;box-shadow:0 2px 8px rgba(225,29,72,0.3);">
            PALING FAVORIT
          </span>
          <?php endif; ?>

          <div class="fw-800 text-dark mb-1" style="font-size:1.15rem;"><?= $pkg['name'] ?></div>
          <div class="text-muted small mb-3"><?= $pkg['target'] ?></div>

          <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #f1f5f9;">
            <div class="text-muted small" style="font-size:.74rem;">Tarif Sewa Harian:</div>
            <div class="fw-800 text-dark" style="font-size:1.45rem;letter-spacing:-0.5px;">
              Rp <?= number_format($pkg['price'], 0, ',', '.') ?>
              <span class="text-muted fw-normal" style="font-size:.8rem;">/ hari</span>
            </div>
          </div>

          <div class="small fw-700 text-dark mb-2 text-uppercase" style="font-size:.72rem;letter-spacing:.5px;">Termasuk Dalam Paket:</div>
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
              <input type="hidden" name="action"   value="add_paket">
              <input type="hidden" name="paket_id" value="<?= $pkg['id'] ?>">
              <input type="hidden" name="redirect" value="keranjang.php">
              <button type="submit" class="btn-pink w-100 py-2" style="border-radius:8px;font-size:.84rem;">
                <i class="bi bi-bag-plus me-1"></i> Sewa Paket Ini
              </button>
            </form>
            <a href="paket.php" class="btn btn-sm btn-outline-secondary w-100 py-2 text-dark fw-600" style="border-radius:8px;font-size:.82rem;border-color:#e2e8f0;background:#ffffff;">
              Rincian Paket Lengkap
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ 6. DESTINASI PENDAKIAN GARUT (WELL-SPACED WHITE CARDS) ═════════════════ -->
<section class="py-5 py-lg-6" style="background:#ffffff;">
  <div class="container">
    <div class="row align-items-center mb-5">
      <div class="col-lg-8">
        <span class="badge" style="background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;">
          JALUR DUKUNGAN KAMI
        </span>
        <h2 class="fw-800 text-dark mt-2 mb-1" style="font-size:clamp(1.75rem, 3vw, 2.2rem);letter-spacing:-0.5px;">
          Destinasi Pendakian Terdekat di Garut
        </h2>
        <p class="text-secondary small mb-0">Basecamp kami melayani pengambilan mandiri atau pengantaran langsung ke titik kumpul pendakian</p>
      </div>
      <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
        <a href="https://wa.me/6281234567890?text=Halo%20Pink%20Outdoor,%20saya%20ingin%20tanya%20layanan%20antar%20gear%20ke%20jalur%20pendakian" target="_blank" class="btn btn-outline-secondary fw-700 btn-sm px-3 py-2" style="border-radius:var(--radius-pill);border-color:#e2e8f0;color:#0f172a;background:#ffffff;">
          <i class="bi bi-whatsapp me-1 text-success"></i> Konsultasi Antar Gear
        </a>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-md-4">
        <div class="p-4 rounded-4 bg-white border h-100 d-flex flex-column" style="border-color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="fw-800 text-dark mb-0">Gunung Papandayan</h5>
            <span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.7rem;">2.665 MDPL</span>
          </div>
          <div class="text-pink fw-700 small mb-2"><i class="bi bi-geo-alt me-1"></i>Jalur Cisurupan, Garut</div>
          <p class="text-secondary mb-3 flex-grow-1" style="font-size:.86rem;line-height:1.65;">
            Destinasi favorit ramah pemula dengan pesona Kawah Belerang, Hutan Mati, dan padang edelweiss Tegal Alun. Titik drop gear: Camp David / Basecamp Cisurupan.
          </p>
          <div class="small fw-700 text-dark pt-3 border-top" style="border-color:#f1f5f9;">
            <i class="bi bi-check-lg text-success me-1"></i> Rekomendasi: Tenda Double Layer + SB Polar
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-4 rounded-4 bg-white border h-100 d-flex flex-column" style="border-color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="fw-800 text-dark mb-0">Gunung Guntur</h5>
            <span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.7rem;">2.249 MDPL</span>
          </div>
          <div class="text-pink fw-700 small mb-2"><i class="bi bi-geo-alt me-1"></i>Jalur Citiis, Tarogong Kaler</div>
          <p class="text-secondary mb-3 flex-grow-1" style="font-size:.86rem;line-height:1.65;">
            Trek pasir menantang dengan panorama savana luas dan pemandangan gemerlap kota Garut di malam hari. Titik drop gear: Basecamp Citiis (hanya 15 menit dari toko kami).
          </p>
          <div class="small fw-700 text-dark pt-3 border-top" style="border-color:#f1f5f9;">
            <i class="bi bi-check-lg text-success me-1"></i> Rekomendasi: Trekking Pole + Headlamp Terang
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-4 rounded-4 bg-white border h-100 d-flex flex-column" style="border-color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="fw-800 text-dark mb-0">Gunung Cikuray</h5>
            <span class="badge" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.7rem;">2.821 MDPL</span>
          </div>
          <div class="text-pink fw-700 small mb-2"><i class="bi bi-geo-alt me-1"></i>Jalur Pemancar & Bayongbong</div>
          <p class="text-secondary mb-3 flex-grow-1" style="font-size:.86rem;line-height:1.65;">
            Atap tertinggi Kabupaten Garut yang tersohor dengan lautan awan 360 derajat dan rute menanjak tanpa jeda. Titik drop gear: Basecamp Pemancar Dayeuhmanggung.
          </p>
          <div class="small fw-700 text-dark pt-3 border-top" style="border-color:#f1f5f9;">
            <i class="bi bi-check-lg text-success me-1"></i> Rekomendasi: Carrier 50L + SB Polar Ekstra
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ 7. ALUR SEWA MUDAH & CEPAT (CLEAN WHITE CARDS) ═════════════════════════ -->
<section class="py-5 py-lg-6" style="background:#f8fafc;border-top:1px solid #f1f5f9;">
  <div class="container">
    <div class="text-center max-w-700 mx-auto mb-5">
      <span class="badge" style="background:#fff1f2;color:var(--pink);border:1px solid #ffe4e6;padding:5px 14px;border-radius:999px;font-size:.74rem;font-weight:800;letter-spacing:0.5px;">
        PANDUAN PRAKTIS
      </span>
      <h2 class="fw-800 text-dark mt-2 mb-2" style="font-size:clamp(1.75rem, 3vw, 2.3rem);letter-spacing:-0.5px;">
        Alur Penyewaan Gear di Pink Outdoor
      </h2>
      <p class="text-secondary small">Empat langkah mudah mulai dari booking online hingga alat siap digunakan di puncak gunung</p>
    </div>

    <div class="row g-4 text-center">
      <div class="col-6 col-md-3">
        <div class="p-4 rounded-4 bg-white border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:52px;height:52px;background:#fff1f2;color:var(--pink);font-size:1.4rem;font-weight:800;">
            1
          </div>
          <div class="fw-700 text-dark mb-2" style="font-size:0.95rem;">Pilih Alat & Tanggal</div>
          <p class="text-muted small mb-0">Tentukan gear atau paket camping serta durasi peminjaman lewat katalog online.</p>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="p-4 rounded-4 bg-white border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:52px;height:52px;background:#fff1f2;color:var(--pink);font-size:1.4rem;font-weight:800;">
            2
          </div>
          <div class="fw-700 text-dark mb-2" style="font-size:0.95rem;">Booking & Konfirmasi</div>
          <p class="text-muted small mb-0">Kunci ketersediaan alat melalui website atau WhatsApp CS resmi tanpa antre.</p>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="p-4 rounded-4 bg-white border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:52px;height:52px;background:#fff1f2;color:var(--pink);font-size:1.4rem;font-weight:800;">
            3
          </div>
          <div class="fw-700 text-dark mb-2" style="font-size:0.95rem;">Serah Terima & Cek</div>
          <p class="text-muted small mb-0">Ambil di basecamp atau kami antar ke Stasiun Garut / titik kumpul pendakian.</p>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="p-4 rounded-4 bg-white border h-100" style="border-color:#e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:52px;height:52px;background:#fff1f2;color:var(--pink);font-size:1.4rem;font-weight:800;">
            4
          </div>
          <div class="fw-700 text-dark mb-2" style="font-size:0.95rem;">Pengembalian Mudah</div>
          <p class="text-muted small mb-0">Selesai mendaki, kembalikan alat dan ambil kembali kartu identitas jaminanmu.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
