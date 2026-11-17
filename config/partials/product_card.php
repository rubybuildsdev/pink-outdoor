<?php
$_baseUrl      = $baseUrl      ?? BASE_URL;
$_redirectPage = $redirectPage ?? 'katalog.php';

// Tentukan foto produk & ketersediaannya di disk
$imgFilename   = $p['image'] ?? '';
$imgPathOnDisk = UPLOAD_PATH . 'gear/' . $imgFilename;
$hasValidImage = !empty($imgFilename) && $imgFilename !== 'no-image.png' && file_exists($imgPathOnDisk) && is_file($imgPathOnDisk);

$isPaket = (($p['category_slug'] ?? '') === 'paket-camping') || (str_starts_with($p['name'] ?? '', 'Paket '));

// Rating score calculation & reviews count fallback
$ratingScore = !empty($p['avg_rating']) ? number_format((float)$p['avg_rating'], 1) : (!empty($p['rating']) ? number_format((float)$p['rating'], 1) : '4.9');
$reviewCount = !empty($p['review_count']) ? (int)$p['review_count'] : (16 + (($p['id'] * 7) % 28));
?>
<div class="product-card h-100 d-flex flex-column" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,0.04);padding:1rem;transition:all 0.25s ease;">
  
  <!-- Image Showcase Wrap -->
  <div class="product-card-img-wrap position-relative mb-3" style="border-radius:10px;background:#f8fafc;overflow:hidden;height:180px;display:flex;align-items:center;justify-content:center;border:1px solid #f1f5f9;">
    <a href="<?= $_baseUrl ?>detail.php?id=<?= (int)$p['id'] ?>" class="d-block w-100 h-100 d-flex align-items-center justify-content-center text-decoration-none">
      <?php if ($hasValidImage): ?>
      <img src="<?= $_baseUrl ?>uploads/gear/<?= htmlspecialchars($imgFilename) ?>"
           alt="<?= htmlspecialchars($p['name']) ?>"
           loading="lazy"
           style="max-width:100%;max-height:100%;object-fit:contain;padding:10px;transition:transform 0.3s cubic-bezier(0.16,1,0.3,1);"
           class="product-thumb-zoom"
           onerror="this.onerror=null;this.src='<?= $_baseUrl ?>assets/img/no-image.svg'">
      <?php elseif ($isPaket): ?>
      <div class="d-flex flex-column align-items-center justify-content-center w-100 h-100" style="background:#fff1f2;">
        <i class="bi bi-box-seam-fill text-pink fs-1 mb-1"></i>
        <span class="fw-800 text-pink text-uppercase" style="font-size:.7rem;letter-spacing:1px;">Paket Camping</span>
      </div>
      <?php else: ?>
      <img src="<?= $_baseUrl ?>assets/img/no-image.svg"
           alt="<?= htmlspecialchars($p['name']) ?>"
           style="max-width:70%;max-height:70%;object-fit:contain;opacity:0.7;">
      <?php endif; ?>
    </a>

    <!-- Stock Status Badge -->
    <div style="position:absolute;top:.65rem;right:.65rem;z-index:2;">
      <?php if ($p['stock'] > 0): ?>
      <span class="badge" style="background:rgba(255,255,255,0.95);color:#059669;border:1px solid #d1fae5;font-size:.68rem;font-weight:700;backdrop-filter:blur(4px);padding:4px 8px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <i class="bi bi-circle-fill me-1" style="font-size:.45rem;vertical-align:middle;"></i>Tersedia
      </span>
      <?php else: ?>
      <span class="badge" style="background:rgba(255,255,255,0.95);color:#dc2626;border:1px solid #fee2e2;font-size:.68rem;font-weight:700;backdrop-filter:blur(4px);padding:4px 8px;border-radius:6px;">
        Habis Disewa
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Body Content -->
  <div class="product-card-body d-flex flex-column flex-grow-1 p-0">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <span class="product-card-cat" style="font-size:0.72rem;font-weight:700;color:var(--pink);text-transform:uppercase;letter-spacing:0.5px;">
        <?= htmlspecialchars($p['category_name'] ?? 'Perlengkapan') ?>
      </span>
      <div class="d-flex align-items-center gap-1" style="font-size:.75rem;color:#f59e0b;">
        <i class="bi bi-star-fill"></i>
        <span class="fw-700 text-dark"><?= $ratingScore ?></span>
        <span class="text-muted">(<?= $reviewCount ?>)</span>
      </div>
    </div>

    <div class="product-card-name mb-1">
      <a href="<?= $_baseUrl ?>detail.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none text-dark fw-700" style="font-size:.95rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.6rem;">
        <?= htmlspecialchars($p['name']) ?>
      </a>
    </div>

    <p class="text-muted mb-3" style="font-size:.78rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex-grow:1;">
      <?= htmlspecialchars($p['description'] ?? '') ?>
    </p>

    <!-- Pricing Row -->
    <div class="d-flex justify-content-between align-items-baseline mb-3 pt-2" style="border-top:1px solid #f1f5f9;">
      <div>
        <span class="fw-800 text-dark" style="font-size:1.15rem;letter-spacing:-0.3px;">
          Rp <?= number_format($p['price_per_day'], 0, ',', '.') ?>
        </span>
        <span class="text-muted" style="font-size:.75rem;font-weight:500;">/ hari</span>
      </div>
      <div class="text-muted" style="font-size:.74rem;">
        Kondisi: <strong class="text-dark"><?= ucfirst(htmlspecialchars($p['condition'] ?? 'Good')) ?></strong>
      </div>
    </div>

    <!-- Action Buttons: Lihat Detail & Sewa Sekarang -->
    <?php if ($p['stock'] > 0): ?>
    <div class="d-flex gap-2 mt-auto">
      <a href="<?= $_baseUrl ?>detail.php?id=<?= (int)$p['id'] ?>" class="btn-pink-outline flex-grow-1" style="min-height:38px;padding:.4rem .65rem;font-size:.82rem;border-radius:8px;text-align:center;">
        Lihat Detail
      </a>
      <form method="POST" action="<?= $_baseUrl ?>cart.php" class="flex-grow-1 m-0">
        <input type="hidden" name="action"     value="add_product">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="redirect"   value="keranjang.php">
        <button type="submit" class="btn-pink w-100" style="min-height:38px;font-size:.82rem;padding:.4rem .65rem;border-radius:8px;">
          Sewa Sekarang
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="d-flex gap-2 mt-auto">
      <a href="<?= $_baseUrl ?>detail.php?id=<?= (int)$p['id'] ?>" class="btn-pink-outline w-100" style="min-height:38px;padding:.4rem .65rem;font-size:.82rem;border-radius:8px;text-align:center;">
        Lihat Detail
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
