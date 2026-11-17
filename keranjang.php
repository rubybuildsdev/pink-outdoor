<?php
require_once 'config/config.php';
$pageTitle = 'Keranjang';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart  = $_SESSION['cart'];
$flash = getFlash();

// Hitung subtotal (days=1 karena durasi diisi saat checkout)
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
}
$fee   = round($subtotal * 0.05);
$total = $subtotal + $fee;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Keranjang — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'config/partials/navbar.php'; ?>

<div class="page-hero-dark">
  <div class="container">
    <h1 class="fw-800 mb-1"><i class="bi bi-bag me-2"></i>Keranjang Sewa Gear</h1>
    <p class="mb-0" style="color:#94a3b8;"><?= count($cart) ?> item terpilih · Durasi sewa dan metode serah terima ditentukan saat checkout</p>
  </div>
</div>

<div class="container py-4">
  <?php if (empty($cart)): ?>
  <div class="section-card text-center py-5 mt-3">
    <div class="mb-3 text-muted">
      <i class="bi bi-cart-x fs-1"></i>
    </div>
    <h5 class="fw-800 mt-2 text-dark">Keranjang Sewa Masih Kosong</h5>
    <p class="text-muted small">Belum ada perlengkapan camping atau paket yang kamu tambahkan.</p>
    <a href="<?= BASE_URL ?>katalog.php" class="btn-pink px-4 py-2 mt-2 d-inline-block">
      <i class="bi bi-grid me-1"></i>Pilih Gear di Katalog
    </a>
  </div>

  <?php else: ?>
  <div class="row g-4 mt-1">

    <!-- Item List (BUG 5: Hapus fitur Update Durasi) -->
    <div class="col-lg-8">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-700 mb-0">Item Keranjang</h5>
        <button type="button" class="btn btn-sm text-danger border-0 fw-600 bg-transparent"
                onclick="document.getElementById('clearForm').submit()">
          <i class="bi bi-trash me-1"></i>Kosongkan
        </button>
      </div>

      <?php foreach ($cart as $key => $item): ?>
      <div class="section-card mb-3">
        <div class="d-flex gap-3 align-items-center">
          <!-- Thumbnail: gambar gear atau ikon untuk paket -->
          <?php if (($item['type'] ?? '') === 'paket'): ?>
          <div style="width:84px;height:64px;flex-shrink:0;border-radius:8px;background:var(--surface-2);
                      border:1px solid var(--border);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-box-seam-fill text-pink fs-3"></i>
          </div>
          <?php else: ?>
          <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($item['image'] ?? 'no-image.png') ?>"
               width="84" height="64"
               style="object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid var(--border);"
               onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.svg'">
          <?php endif; ?>

          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h6 class="fw-700 mb-0"><?= htmlspecialchars($item['name']) ?></h6>
                <small class="text-muted">
                  Rp <?= number_format($item['price'] ?? 0, 0, ',', '.') ?>/hari
                  <?php if (($item['type'] ?? '') === 'paket'): ?>
                  · <span class="badge-confirmed" style="font-size:.68rem;">Paket</span>
                  <?php endif; ?>
                </small>
              </div>
              <button type="button" class="border-0 bg-transparent text-danger p-0 ms-2"
                      onclick="removeItem('<?= htmlspecialchars(addslashes($key)) ?>')"
                      title="Hapus item">
                <i class="bi bi-x-circle fs-5"></i>
              </button>
            </div>
            <div class="mt-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Jumlah:</span>
                <div class="d-inline-flex align-items-center border rounded-pill px-1" style="background:#fff;border-color:var(--border)!important;">
                  <form method="POST" action="cart.php" class="m-0 p-0">
                    <input type="hidden" name="action" value="update_qty">
                    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                    <input type="hidden" name="delta" value="-1">
                    <button type="submit" class="btn btn-sm btn-link text-decoration-none text-dark p-0 px-2 fw-700"
                            style="font-size:1rem;line-height:1.2;" title="Kurangi jumlah">−</button>
                  </form>
                  <span class="px-2 fw-700 text-dark" style="font-size:.88rem;min-width:24px;text-align:center;">
                    <?= (int)($item['qty'] ?? 1) ?>
                  </span>
                  <form method="POST" action="cart.php" class="m-0 p-0">
                    <input type="hidden" name="action" value="update_qty">
                    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                    <input type="hidden" name="delta" value="1">
                    <button type="submit" class="btn btn-sm btn-link text-decoration-none text-dark p-0 px-2 fw-700"
                            style="font-size:1rem;line-height:1.2;" title="Tambah jumlah">+</button>
                  </form>
                </div>
              </div>
              <div class="text-end">
                <span class="text-muted small">Subtotal:</span>
                <span class="fw-700 text-pink ms-1">
                  Rp <?= number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 0, ',', '.') ?>/hari
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Hidden forms untuk clear & remove -->
      <form id="clearForm" method="POST" action="cart.php" style="display:none;">
        <input type="hidden" name="action" value="clear">
      </form>
      <form id="removeForm" method="POST" action="cart.php" style="display:none;">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="key"    id="removeKey" value="">
      </form>

      <!-- Lanjut belanja -->
      <a href="<?= BASE_URL ?>index.php#katalog" class="btn-pink-outline py-2 px-4 d-inline-flex align-items-center gap-1 mt-1">
        <i class="bi bi-plus-lg"></i>Tambah Gear Lain
      </a>
    </div>

    <!-- Ringkasan -->
    <div class="col-lg-4">
      <div class="section-card sticky-top" style="top:80px;">
        <h5 class="fw-700 mb-3">Ringkasan</h5>

        <?php 
        $totalUnits = 0;
        foreach ($cart as $item): 
          $itemQty = (int)($item['qty'] ?? 1);
          $totalUnits += $itemQty;
          $itemDailyTotal = ($item['price'] ?? 0) * $itemQty;
        ?>
        <div class="d-flex justify-content-between mb-2 align-items-center" style="font-size:.83rem;">
          <div class="d-flex align-items-center gap-2">
            <?php if (($item['type'] ?? '') === 'paket'): ?>
            <i class="bi bi-box-seam text-pink"></i>
            <?php else: ?>
            <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($item['image'] ?? 'no-image.png') ?>"
                 width="28" height="22" style="object-fit:cover;border-radius:4px;"
                 onerror="this.style.display='none'">
            <?php endif; ?>
            <span class="text-muted"><?= htmlspecialchars(mb_substr($item['name'],0,18)) ?> <small class="fw-700 text-dark">×<?= $itemQty ?></small></span>
          </div>
          <span class="fw-600">Rp <?= number_format($itemDailyTotal, 0, ',', '.') ?>/hr</span>
        </div>
        <?php endforeach; ?>

        <div class="rounded p-2 mt-2 mb-3" style="background:var(--surface-2);font-size:.78rem;color:var(--text-muted);">
          <i class="bi bi-calendar3 me-1"></i>Total akhir + biaya layanan dihitung saat pilih tanggal di checkout
        </div>

        <hr class="my-2" style="border-color:var(--border);">
        <div class="d-flex justify-content-between text-muted mb-1" style="font-size:.82rem;">
          <span>Subtotal Sewa:</span>
          <span class="fw-600 text-dark">Rp <?= number_format($subtotal, 0, ',', '.') ?>/hari</span>
        </div>
        <div class="d-flex justify-content-between fw-700 text-pink mb-3" style="font-size:1rem;">
          <span><?= $totalUnits ?> Unit Gear</span>
          <span>Siap Checkout</span>
        </div>

        <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>user/checkout.php" class="btn-pink w-100 py-3 text-center d-block fw-700">
          <i class="bi bi-credit-card me-2"></i>Lanjut Checkout
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>login.php" class="btn-pink w-100 py-3 text-center d-block fw-700">
          <i class="bi bi-box-arrow-in-right me-2"></i>Login untuk Checkout
        </a>
        <p class="text-center mt-2 mb-0" style="font-size:.82rem;color:var(--text-muted);">
          Belum punya akun? <a href="<?= BASE_URL ?>register.php" class="text-pink fw-600">Daftar gratis</a>
        </p>
        <p class="text-center mt-1 mb-0" style="font-size:.75rem;color:var(--text-muted);">
          <i class="bi bi-bag me-1"></i>Keranjang tersimpan otomatis
        </p>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function removeItem(key) {
  if (!confirm('Hapus item ini dari keranjang?')) return;
  document.getElementById('removeKey').value = key;
  document.getElementById('removeForm').submit();
}
</script>
</body>
</html>
