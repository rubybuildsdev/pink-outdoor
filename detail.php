<?php
/**
 * detail.php — Product Detail Page
 * Clean UI redesign: gallery, live date picker, terms banner, reviews section.
 */
require_once 'config/config.php';

/* ── 1. Fetch product ──────────────────────────────────────────────────── */
$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: katalog.php'); exit;
}

$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON p.category_id = c.id
     WHERE p.id = ? AND p.status = 'available'
     LIMIT 1"
);
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: katalog.php'); exit;
}

/* ── 2. Handle new review submission ──────────────────────────────────── */
$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $reviewerName    = trim($_POST['reviewer_name']  ?? '');
    $reviewerComment = trim($_POST['reviewer_comment'] ?? '');
    $reviewerRating  = (int)($_POST['reviewer_rating'] ?? 0);

    if ($reviewerName === '' || $reviewerComment === '' || $reviewerRating < 1 || $reviewerRating > 5) {
        $reviewError = 'Nama, komentar, dan bintang (1–5) wajib diisi dengan lengkap.';
    } else {
        $userId = isLoggedIn() ? getUserId() : null;
        $ins = $conn->prepare(
            "INSERT INTO reviews (product_id, user_id, user_name, rating, comment) VALUES (?, ?, ?, ?, ?)"
        );
        $ins->bind_param('iisis', $productId, $userId, $reviewerName, $reviewerRating, $reviewerComment);
        $ins->execute();
        $ins->close();
        setFlash('success', 'Ulasan kamu berhasil dikirim! Terima kasih 🌟');
        header('Location: detail.php?id=' . $productId . '#ulasan'); exit;
    }
}

/* ── 3. Fetch reviews ─────────────────────────────────────────────────── */
$starFilter  = (int)($_GET['stars'] ?? 0);
$reviewsSql  = "SELECT * FROM reviews WHERE product_id = ?";
$reviewTypes = 'i';
$reviewParams = [$productId];
if ($starFilter >= 1 && $starFilter <= 5) {
    $reviewsSql  .= " AND rating = ?";
    $reviewTypes .= 'i';
    $reviewParams[] = $starFilter;
}
$reviewsSql .= " ORDER BY created_at DESC";

$rStmt = $conn->prepare($reviewsSql);
$rStmt->bind_param($reviewTypes, ...$reviewParams);
$rStmt->execute();
$reviews = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rStmt->close();

// Average rating (all, not filtered)
$avgRow = $conn->prepare("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM reviews WHERE product_id = ?");
$avgRow->bind_param('i', $productId);
$avgRow->execute();
$avgData = $avgRow->get_result()->fetch_assoc();
$avgRow->close();
$avgRating   = round((float)($avgData['avg_r'] ?? 0), 1);
$totalReviews = (int)($avgData['total'] ?? 0);

/* ── 4. Image helpers ─────────────────────────────────────────────────── */
$baseUrl     = BASE_URL;
$imgFilename = $product['image'] ?? '';
$imgPath     = UPLOAD_PATH . 'gear/' . $imgFilename;
$hasImage    = !empty($imgFilename) && $imgFilename !== 'no-image.png' && file_exists($imgPath) && is_file($imgPath);
$mainImgSrc  = $hasImage
    ? $baseUrl . 'uploads/gear/' . htmlspecialchars($imgFilename)
    : $baseUrl . 'assets/img/no-image.svg';

$conditionMap = [
    'excellent' => ['label' => 'Excellent', 'cls' => 'bg-success text-white'],
    'good'      => ['label' => 'Good',      'cls' => 'bg-warning text-dark'],
    'fair'      => ['label' => 'Fair',      'cls' => 'bg-secondary text-white'],
];
$condBadge = $conditionMap[$product['condition']] ?? ['label' => ucfirst($product['condition']), 'cls' => 'bg-secondary text-white'];

$flash = getFlash();

$pageTitle = htmlspecialchars($product['name']) . ' — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* ── Detail Page Supplemental Styles ── */
    .detail-breadcrumb {
      background: #ffffff;
      border-bottom: 1px solid #f1f5f9;
      padding: 0.85rem 0;
    }
    .detail-breadcrumb .breadcrumb { margin: 0; font-size: .83rem; }
    .detail-breadcrumb .breadcrumb-item a { color: #64748b; }
    .detail-breadcrumb .breadcrumb-item.active { color: #0f172a; font-weight: 600; }

    /* Image Box */
    .detail-img-box {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      overflow: hidden;
      aspect-ratio: 4/3;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
    .detail-img-box img {
      width: 100%; height: 100%;
      object-fit: contain;
      padding: 1rem;
      transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
    }
    .detail-img-box:hover img { transform: scale(1.04); }
    .detail-img-box .no-img-placeholder {
      display: flex; flex-direction: column; align-items: center; gap: .5rem;
      color: #94a3b8; font-size: .9rem; text-align: center;
    }

    /* Thumbnails */
    .detail-thumb-row {
      display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem;
    }
    .detail-thumb {
      width: 72px; height: 60px;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      transition: border-color 0.15s ease;
      background: #f8fafc;
      flex-shrink: 0;
    }
    .detail-thumb.active { border-color: #e11d48; }
    .detail-thumb img { width: 100%; height: 100%; object-fit: cover; }

    /* Right Column Card */
    .detail-info-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2rem;
      position: sticky;
      top: 90px;
    }
    .cat-pill-sm {
      display: inline-flex; align-items: center; gap: .3rem;
      background: #fff1f2; color: #e11d48;
      border: 1px solid #ffe4e6; border-radius: 999px;
      font-size: .75rem; font-weight: 700; padding: .2rem .75rem;
    }
    .detail-product-name {
      font-size: 1.55rem; font-weight: 800; color: #0f172a;
      line-height: 1.25; letter-spacing: -.3px; margin: .6rem 0 .4rem;
    }
    .detail-price-block { margin: 1rem 0; display: flex; align-items: baseline; gap: .4rem; }
    .detail-price { font-size: 2rem; font-weight: 800; color: #e11d48; letter-spacing: -.5px; }
    .detail-price-unit { font-size: .9rem; color: #64748b; font-weight: 600; }
    .detail-rating { display: flex; align-items: center; gap: .4rem; font-size: .88rem; color: #334155; }
    .star-rating-gold { color: #f59e0b; }

    /* Date Picker */
    .date-picker-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.1rem 1.25rem;
      margin: 1.25rem 0;
    }
    .date-picker-box label { font-size: .78rem; font-weight: 700; color: #334155; letter-spacing: .3px; margin-bottom: .3rem; display: block; }
    .date-picker-box input[type="date"] {
      width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;
      padding: .5rem .75rem; font-size: .88rem; font-family: inherit;
      color: #0f172a; background: #ffffff; cursor: pointer;
    }
    .date-picker-box input[type="date"]:focus { outline: none; border-color: #e11d48; box-shadow: 0 0 0 3px rgba(225,29,72,.08); }
    .date-calc-result {
      display: flex; justify-content: space-between; align-items: center;
      background: #fff1f2; border: 1px solid #ffe4e6; border-radius: 10px;
      padding: .6rem 1rem; margin-top: .75rem; font-size: .86rem;
    }
    .date-calc-result strong { color: #e11d48; font-weight: 800; }

    /* Qty Stepper */
    .qty-stepper {
      display: flex; align-items: center; gap: 0;
      border: 1px solid #e2e8f0; border-radius: 999px;
      overflow: hidden; width: fit-content; height: 42px;
    }
    .qty-stepper button {
      width: 42px; height: 42px; border: none; background: #f8fafc;
      font-size: 1.1rem; font-weight: 700; cursor: pointer;
      color: #334155; transition: background .15s ease; flex-shrink: 0;
    }
    .qty-stepper button:hover { background: #fff1f2; color: #e11d48; }
    .qty-stepper input {
      width: 50px; text-align: center; border: none;
      border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
      font-size: .95rem; font-weight: 700; color: #0f172a; height: 100%;
      background: #fff;
    }
    .qty-stepper input:focus { outline: none; }

    /* Stock Indicator */
    .stock-indicator {
      display: inline-flex; align-items: center; gap: .4rem;
      font-size: .82rem; font-weight: 600; padding: .3rem .85rem;
      border-radius: 999px; border: 1px solid;
    }
    .stock-ok  { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .stock-low { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .stock-out { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

    /* ── Terms Banner ── */
    .terms-banner {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2rem;
      margin: 2.5rem 0;
    }
    .terms-banner h5 {
      font-weight: 800; color: #0f172a; margin-bottom: 1.5rem;
      font-size: 1.1rem; display: flex; align-items: center; gap: .5rem;
    }
    .terms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
    }
    .terms-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.1rem;
      text-align: center;
      transition: all .2s ease;
    }
    .terms-card:hover { background: #fff1f2; border-color: #ffe4e6; }
    .terms-card .terms-icon {
      font-size: 1.75rem; margin-bottom: .4rem; display: block;
    }
    .terms-card .terms-title {
      font-weight: 700; font-size: .82rem; color: #0f172a; margin-bottom: .2rem;
    }
    .terms-card .terms-desc { font-size: .75rem; color: #64748b; line-height: 1.4; }

    /* ── Reviews ── */
    .reviews-section { margin: 2.5rem 0 3rem; }
    .reviews-section h4 { font-weight: 800; font-size: 1.25rem; margin-bottom: 1.5rem; }
    .review-avg-block {
      background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px;
      padding: 1.5rem 2rem; display: flex; align-items: center; gap: 2rem;
      margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .review-avg-num {
      font-size: 3rem; font-weight: 900; color: #0f172a; letter-spacing: -1px;
      line-height: 1;
    }
    .review-avg-stars { font-size: 1.3rem; }
    .review-avg-count { font-size: .85rem; color: #64748b; margin-top: .2rem; }
    .review-filter-pills { display: flex; gap: .4rem; flex-wrap: wrap; margin-left: auto; }
    .review-filter-pill {
      border: 1px solid #e2e8f0; background: #ffffff; border-radius: 999px;
      padding: .3rem .9rem; font-size: .8rem; font-weight: 600; color: #334155;
      cursor: pointer; text-decoration: none; transition: all .15s ease;
    }
    .review-filter-pill:hover, .review-filter-pill.active {
      background: #fff1f2; border-color: #ffe4e6; color: #e11d48;
    }

    .review-card {
      background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px;
      padding: 1.4rem 1.5rem; margin-bottom: 1rem;
      transition: box-shadow .15s ease;
    }
    .review-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,.06); }
    .review-avatar {
      width: 42px; height: 42px; border-radius: 50%;
      background: linear-gradient(135deg, #e11d48, #be123c);
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: .95rem; flex-shrink: 0;
    }
    .review-name { font-weight: 700; font-size: .9rem; color: #0f172a; }
    .review-date { font-size: .77rem; color: #94a3b8; }
    .review-stars { color: #f59e0b; font-size: .9rem; }
    .review-body { font-size: .88rem; color: #334155; line-height: 1.6; margin-top: .5rem; }

    /* Review Form */
    .review-form-card {
      background: #f8fafc; border: 1px solid #e2e8f0;
      border-radius: 14px; padding: 1.75rem;
    }
    /* Star rating input — handled by JS */
    .star-rating-interactive { display: flex; flex-direction: row; gap: .15rem; width: fit-content; }
    .star-rating-interactive .star-btn { font-size: 1.75rem; cursor: pointer; color: #d1d5db; transition: color .12s; user-select: none; }

    .detail-section-divider {
      height: 1px; background: #e2e8f0; margin: 2.5rem 0;
    }

    @media (max-width: 991.98px) {
      .detail-info-card { position: static; padding: 1.25rem; }
      .detail-product-name { font-size: 1.3rem; }
      .detail-price { font-size: 1.6rem; }
      .terms-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>

<!-- Flash Toast -->
<?php if ($flash): ?>
<div class="floating-flash-toast <?= $flash['type'] === 'error' ? 'toast-error' : '' ?>" id="flashToast">
  <div class="toast-icon"><i class="bi <?= $flash['type'] === 'error' ? 'bi-exclamation-circle-fill' : 'bi-bag-check-fill' ?>"></i></div>
  <div class="toast-content"><?= htmlspecialchars($flash['msg']) ?></div>
  <button class="toast-close" onclick="document.getElementById('flashToast').remove()"><i class="bi bi-x"></i></button>
</div>
<?php endif; ?>

<!-- Breadcrumbs -->
<div class="detail-breadcrumb">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= $baseUrl ?>index.php">Beranda</a></li>
        <li class="breadcrumb-item"><a href="<?= $baseUrl ?>katalog.php">Katalog</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
      </ol>
    </nav>
  </div>
</div>

<!-- ══ MAIN PRODUCT SECTION ══════════════════════════════════════════ -->
<div class="container py-5">
  <div class="row g-5">

    <!-- Left: Image Gallery -->
    <div class="col-lg-6">
      <div class="detail-img-box" id="mainImgBox">
        <?php if ($hasImage): ?>
          <img src="<?= $mainImgSrc ?>" alt="<?= htmlspecialchars($product['name']) ?>"
               id="mainImg" loading="eager"
               onerror="this.onerror=null;this.src='<?= $baseUrl ?>assets/img/no-image.svg'">
        <?php else: ?>
          <div class="no-img-placeholder">
            <i class="bi bi-image" style="font-size:3rem;color:#cbd5e1;"></i>
            <span>Foto belum tersedia</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Thumbnail gallery (main image repeated + placeholder variants for feel) -->
      <div class="detail-thumb-row" id="thumbRow">
        <?php if ($hasImage): ?>
        <div class="detail-thumb active" onclick="switchMainImg(this, '<?= $mainImgSrc ?>')">
          <img src="<?= $mainImgSrc ?>" alt="thumb-1" loading="lazy">
        </div>
        <!-- Placeholder additional angles using same image with different brightness for demo feel -->
        <div class="detail-thumb" onclick="switchMainImg(this, '<?= $mainImgSrc ?>')"
             style="filter:brightness(0.95) saturate(0.85);">
          <img src="<?= $mainImgSrc ?>" alt="thumb-2" loading="lazy" style="filter:brightness(0.95) saturate(0.85);">
        </div>
        <div class="detail-thumb" onclick="switchMainImg(this, '<?= $mainImgSrc ?>')"
             style="filter:brightness(1.05) saturate(1.1);">
          <img src="<?= $mainImgSrc ?>" alt="thumb-3" loading="lazy" style="filter:brightness(1.05) saturate(1.1);">
        </div>
        <?php endif; ?>
      </div>

      <!-- Description (desktop below image) -->
      <div class="mt-4 d-none d-lg-block">
        <h6 class="fw-700 mb-2" style="font-size:.88rem;color:#334155;text-transform:uppercase;letter-spacing:.5px;">Deskripsi Produk</h6>
        <p style="font-size:.93rem;color:#334155;line-height:1.75;">
          <?= nl2br(htmlspecialchars($product['description'] ?? 'Belum ada deskripsi.')) ?>
        </p>
      </div>
    </div>

    <!-- Right: Product Info -->
    <div class="col-lg-6">
      <div class="detail-info-card">

        <!-- Category + Condition -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="cat-pill-sm">
            <i class="bi bi-tag-fill"></i><?= htmlspecialchars($product['category_name']) ?>
          </span>
          <span class="badge <?= $condBadge['cls'] ?>" style="font-size:.72rem;padding:.28rem .65rem;border-radius:999px;">
            <i class="bi bi-patch-check-fill me-1"></i><?= $condBadge['label'] ?>
          </span>
        </div>

        <!-- Name -->
        <h1 class="detail-product-name"><?= htmlspecialchars($product['name']) ?></h1>

        <!-- Rating -->
        <div class="detail-rating mb-1">
          <span class="star-rating-gold">
            <?php
              $full  = floor($avgRating);
              $half  = ($avgRating - $full) >= 0.5 ? 1 : 0;
              $empty = 5 - $full - $half;
              echo str_repeat('<i class="bi bi-star-fill"></i>', (int)$full);
              if ($half) echo '<i class="bi bi-star-half"></i>';
              echo str_repeat('<i class="bi bi-star"></i>', (int)$empty);
            ?>
          </span>
          <span class="fw-700" style="color:#0f172a;"><?= $avgRating > 0 ? number_format($avgRating,1) : '—' ?></span>
          <span class="text-muted">(<?= $totalReviews ?> Ulasan)</span>
        </div>

        <!-- Price -->
        <div class="detail-price-block">
          <span class="detail-price">Rp <?= number_format($product['price_per_day'], 0, ',', '.') ?></span>
          <span class="detail-price-unit">/ hari</span>
        </div>

        <!-- Stock -->
        <div class="mb-3">
          <?php if ($product['stock'] <= 0): ?>
            <span class="stock-indicator stock-out"><i class="bi bi-x-circle-fill"></i> Stok Habis</span>
          <?php elseif ($product['stock'] <= 2): ?>
            <span class="stock-indicator stock-low"><i class="bi bi-exclamation-circle-fill"></i> Stok Terbatas (<?= $product['stock'] ?> tersisa)</span>
          <?php else: ?>
            <span class="stock-indicator stock-ok"><i class="bi bi-circle-fill" style="font-size:.45em;"></i> Tersedia · <?= $product['stock'] ?> unit</span>
          <?php endif; ?>
        </div>

        <!-- Description mobile -->
        <div class="mb-3 d-lg-none">
          <p style="font-size:.88rem;color:#334155;line-height:1.7;">
            <?= nl2br(htmlspecialchars($product['description'] ?? '')) ?>
          </p>
        </div>

        <?php if ($product['stock'] > 0): ?>

        <!-- Date Picker -->
        <div class="date-picker-box">
          <h6 class="fw-700 mb-3" style="font-size:.82rem;color:#0f172a;text-transform:uppercase;letter-spacing:.5px;">
            <i class="bi bi-calendar3 me-1" style="color:#e11d48;"></i> Tentukan Tanggal Sewa
          </h6>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label>Mulai Sewa</label>
              <input type="date" id="startDate" name="start_date"
                     min="<?= date('Y-m-d') ?>"
                     value="<?= date('Y-m-d') ?>"
                     oninput="calcDates()">
            </div>
            <div class="col-6">
              <label>Selesai Sewa</label>
              <input type="date" id="endDate" name="end_date"
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     value="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     oninput="calcDates()">
            </div>
          </div>
          <div class="date-calc-result" id="calcResult">
            <span>Durasi: <strong id="calcDays">1 hari</strong></span>
            <span>Subtotal: <strong id="calcPrice">Rp <?= number_format($product['price_per_day'],0,',','.') ?></strong></span>
          </div>
        </div>

        <!-- Qty Stepper -->
        <div class="d-flex align-items-center justify-content-between mb-4">
          <span class="fw-700" style="font-size:.88rem;color:#334155;">Jumlah Unit</span>
          <div class="qty-stepper">
            <button type="button" onclick="changeQty(-1)" aria-label="Kurang"><i class="bi bi-dash"></i></button>
            <input type="number" id="qtyInput" value="1" min="1" max="<?= (int)$product['stock'] ?>" readonly>
            <button type="button" onclick="changeQty(1)" aria-label="Tambah"><i class="bi bi-plus"></i></button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-2">
          <!-- Add to Cart (POST to cart.php, stay on page) -->
          <form method="POST" action="<?= $baseUrl ?>cart.php" id="addToCartForm">
            <input type="hidden" name="action"     value="add_product">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="redirect"   value="detail.php?id=<?= (int)$product['id'] ?>">
            <input type="hidden" name="qty"        id="cartQty" value="1">
            <input type="hidden" name="start_date" id="cartStart" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="end_date"   id="cartEnd"  value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            <button type="submit" class="btn-pink-outline w-100" style="min-height:48px;font-size:.95rem;">
              <i class="bi bi-bag-plus"></i> Tambah ke Keranjang
            </button>
          </form>
          <!-- Rent Now (add then go to cart) -->
          <form method="POST" action="<?= $baseUrl ?>cart.php">
            <input type="hidden" name="action"     value="add_product">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="redirect"   value="keranjang.php">
            <input type="hidden" name="qty"        id="rentQty"  value="1">
            <input type="hidden" name="start_date" id="rentStart" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="end_date"   id="rentEnd"   value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            <button type="submit" class="btn-pink w-100" style="min-height:48px;font-size:.95rem;">
              <i class="bi bi-lightning-charge-fill"></i> Sewa Sekarang
            </button>
          </form>
        </div>

        <?php else: ?>
        <button class="btn btn-light border w-100 fw-700 text-muted" disabled style="min-height:48px;">
          <i class="bi bi-lock me-1"></i> Stok Sedang Habis — Hubungi Kami
        </button>
        <?php endif; ?>

      </div><!-- /detail-info-card -->
    </div>
  </div><!-- /row -->

  <div class="detail-section-divider"></div>

  <!-- ══ TERMS & CONDITIONS BANNER ════════════════════════════════════ -->
  <div class="terms-banner">
    <h5><i class="bi bi-shield-check-fill text-pink"></i> Syarat dan Ketentuan Sewa</h5>
    <div class="terms-grid">
      <div class="terms-card">
        <span class="terms-icon">📅</span>
        <div class="terms-title">Durasi Sewa</div>
        <div class="terms-desc">Minimal 1 hari, dihitung penuh per hari. Perpanjangan bisa diajukan H-1 sebelum jatuh tempo.</div>
      </div>
      <div class="terms-card">
        <span class="terms-icon">🛡️</span>
        <div class="terms-title">Kebijakan Kerusakan</div>
        <div class="terms-desc">Penyewa bertanggung jawab atas kerusakan atau kehilangan. Biaya ganti rugi sesuai kondisi alat.</div>
      </div>
      <div class="terms-card">
        <span class="terms-icon">🪪</span>
        <div class="terms-title">Jaminan Identitas</div>
        <div class="terms-desc">Wajib menyerahkan KTP / SIM asli yang masih berlaku sebagai jaminan selama masa sewa.</div>
      </div>
      <div class="terms-card">
        <span class="terms-icon">⏰</span>
        <div class="terms-title">Keterlambatan</div>
        <div class="terms-desc">Pengembalian melebihi waktu yang disepakati dikenakan biaya tambahan 50% dari tarif harian.</div>
      </div>
      <div class="terms-card">
        <span class="terms-icon">🚫</span>
        <div class="terms-title">Pembatalan</div>
        <div class="terms-desc">Pembatalan lebih dari 24 jam sebelum sewa: bebas biaya. Kurang dari 24 jam: dikenakan 50% biaya.</div>
      </div>
    </div>
  </div>

  <div class="detail-section-divider" id="ulasan"></div>

  <!-- ══ REVIEWS SECTION ═══════════════════════════════════════════════ -->
  <div class="reviews-section">
    <h4><i class="bi bi-chat-square-quote-fill text-pink me-2"></i>Ulasan Produk</h4>

    <!-- Avg block + filter pills -->
    <div class="review-avg-block">
      <div class="text-center" style="min-width:80px;">
        <div class="review-avg-num"><?= $avgRating > 0 ? number_format($avgRating,1) : '—' ?></div>
        <div class="review-avg-stars star-rating-gold">
          <?php
            if ($avgRating > 0) {
              $f = floor($avgRating); $h = ($avgRating - $f) >= 0.5 ? 1 : 0; $e = 5 - $f - $h;
              echo str_repeat('<i class="bi bi-star-fill"></i>', (int)$f);
              if ($h) echo '<i class="bi bi-star-half"></i>';
              echo str_repeat('<i class="bi bi-star"></i>', (int)$e);
            } else { echo '<span style="color:#d1d5db">★★★★★</span>'; }
          ?>
        </div>
        <div class="review-avg-count"><?= $totalReviews ?> ulasan</div>
      </div>

      <div class="review-filter-pills">
        <a href="detail.php?id=<?= $productId ?>#ulasan"
           class="review-filter-pill <?= $starFilter === 0 ? 'active' : '' ?>">Semua</a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
        <a href="detail.php?id=<?= $productId ?>&stars=<?= $s ?>#ulasan"
           class="review-filter-pill <?= $starFilter === $s ? 'active' : '' ?>">
          <i class="bi bi-star-fill" style="color:#f59e0b;"></i> <?= $s ?>
        </a>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Review List -->
    <?php if (empty($reviews)): ?>
    <div class="text-center py-5" style="color:#94a3b8;">
      <i class="bi bi-chat-left-text" style="font-size:2.5rem;"></i>
      <p class="mt-2 fw-600">Belum ada ulasan<?= $starFilter ? ' untuk bintang ' . $starFilter : '' ?>.</p>
    </div>
    <?php else: ?>
    <?php foreach ($reviews as $rv): ?>
    <div class="review-card">
      <div class="d-flex align-items-start gap-3">
        <div class="review-avatar">
          <?= strtoupper(mb_substr(trim($rv['user_name']), 0, 1)) ?>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
            <div>
              <span class="review-name"><?= htmlspecialchars($rv['user_name']) ?></span>
              <span class="ms-2 review-stars">
                <?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$rv['rating']) ?>
                <?= str_repeat('<i class="bi bi-star"></i>', 5 - (int)$rv['rating']) ?>
              </span>
            </div>
            <span class="review-date">
              <i class="bi bi-clock me-1"></i><?= date('d M Y', strtotime($rv['created_at'])) ?>
            </span>
          </div>
          <p class="review-body mb-0"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Submit Review Form -->
    <div class="review-form-card mt-4">
      <h6 class="fw-700 mb-3" style="font-size:.95rem;">Tulis Ulasan Kamu</h6>

      <?php if ($reviewError): ?>
      <div class="alert alert-danger border-0 rounded-3 mb-3 py-2 px-3" style="font-size:.88rem;">
        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($reviewError) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="detail.php?id=<?= $productId ?>#ulasan">
        <input type="hidden" name="submit_review" value="1">

        <!-- Star Rating -->
        <div class="mb-3">
          <label class="form-label fw-700" style="font-size:.82rem;color:#334155;">Rating Bintang <span class="text-pink">*</span></label>
          <div class="star-rating-interactive" id="starRater">
            <?php for ($s = 1; $s <= 5; $s++): ?>
            <span class="star-btn" data-val="<?= $s ?>" role="button"
                  style="font-size:1.75rem;cursor:pointer;color:#d1d5db;transition:color .1s;"
                  onclick="selectStar(<?= $s ?>)">★</span>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="reviewer_rating" id="starRatingInput" value="0">
          <div class="text-muted mt-1" style="font-size:.78rem;" id="starRatingLabel">Klik bintang di atas untuk memberi rating</div>
        </div>

        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label fw-700" style="font-size:.82rem;color:#334155;">Nama Kamu <span class="text-pink">*</span></label>
            <input type="text" name="reviewer_name" class="form-control"
                   placeholder="Misal: Rafi A."
                   value="<?= htmlspecialchars($_POST['reviewer_name'] ?? (isLoggedIn() ? getUserName() : '')) ?>"
                   style="border-radius:8px;" required maxlength="100">
          </div>
          <div class="col-md-7">
            <label class="form-label fw-700" style="font-size:.82rem;color:#334155;">Komentar <span class="text-pink">*</span></label>
            <textarea name="reviewer_comment" class="form-control" rows="3"
                      placeholder="Bagikan pengalaman kamu dengan alat ini..."
                      style="border-radius:8px;resize:none;" required maxlength="1000"><?= htmlspecialchars($_POST['reviewer_comment'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn-pink btn-sm px-4">
            <i class="bi bi-send-fill me-1"></i> Kirim Ulasan
          </button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /container -->

<?php include 'config/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Date Calculation ── */
const pricePerDay = <?= (int)$product['price_per_day'] ?>;

function calcDates() {
  const s = document.getElementById('startDate').value;
  const e = document.getElementById('endDate').value;
  if (!s || !e) return;

  const start = new Date(s);
  const end   = new Date(e);
  if (end <= start) {
    // Auto-advance end by 1 day
    const next = new Date(start);
    next.setDate(next.getDate() + 1);
    document.getElementById('endDate').value = next.toISOString().split('T')[0];
    calcDates(); return;
  }

  const diffMs   = end - start;
  const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
  const qty      = parseInt(document.getElementById('qtyInput').value) || 1;
  const subtotal = diffDays * pricePerDay * qty;

  document.getElementById('calcDays').textContent  = diffDays + ' hari';
  document.getElementById('calcPrice').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');

  // Sync to form hidden fields
  document.getElementById('cartStart').value = s;
  document.getElementById('cartEnd').value   = e;
  document.getElementById('rentStart').value = s;
  document.getElementById('rentEnd').value   = e;
}

/* ── Qty Stepper ── */
function changeQty(delta) {
  const inp   = document.getElementById('qtyInput');
  const max   = parseInt(inp.max) || 99;
  const newV  = Math.min(max, Math.max(1, parseInt(inp.value) + delta));
  inp.value   = newV;
  document.getElementById('cartQty').value = newV;
  document.getElementById('rentQty').value = newV;
  calcDates(); // Recalculate subtotal with new qty
}

/* ── Thumbnail Switcher ── */
function switchMainImg(thumb, src) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
  const main = document.getElementById('mainImg');
  if (main) { main.src = src; }
}

/* ── Star Rating Interactive ── */
const stars = document.querySelectorAll('#starRater .star-btn');
const starLabels = ['', 'Sangat Buruk', 'Kurang Bagus', 'Cukup Bagus', 'Bagus', 'Sangat Bagus!'];

function selectStar(val) {
  document.getElementById('starRatingInput').value = val;
  document.getElementById('starRatingLabel').textContent = starLabels[val] || '';
  stars.forEach(s => {
    s.style.color = parseInt(s.dataset.val) <= val ? '#f59e0b' : '#d1d5db';
  });
}

stars.forEach(star => {
  star.addEventListener('mouseover', function() {
    const v = parseInt(this.dataset.val);
    stars.forEach(s => {
      s.style.color = parseInt(s.dataset.val) <= v ? '#f59e0b' : '#d1d5db';
    });
  });
  star.addEventListener('mouseout', function() {
    const selected = parseInt(document.getElementById('starRatingInput').value) || 0;
    stars.forEach(s => {
      s.style.color = parseInt(s.dataset.val) <= selected ? '#f59e0b' : '#d1d5db';
    });
  });
});

/* ── Toast auto-dismiss ── */
const toast = document.getElementById('flashToast');
if (toast) { setTimeout(() => { toast.classList.add('toast-hiding'); setTimeout(() => toast.remove(), 400); }, 4500); }

/* ── Init ── */
calcDates();
</script>
</body>
</html>
