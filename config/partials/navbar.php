<?php
$_cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
$_curPage   = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg main-navbar sticky-top" style="background:#ffffff !important;border-bottom:1px solid #f1f5f9;box-shadow:0 1px 3px rgba(0,0,0,0.03);padding:0.75rem 0;">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>index.php">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:var(--pink);border-radius:8px;color:#fff;font-size:.9rem;box-shadow:0 2px 6px rgba(225,29,72,0.25);">
        <i class="bi bi-triangle-fill" style="transform:scale(0.85);"></i>
      </span>
      <span style="font-weight:900;letter-spacing:-0.5px;color:#0f172a;font-size:1.25rem;">PINK<span style="color:var(--pink);">OUTDOOR</span></span>
      <span class="brand-badge d-none d-sm-inline" style="background:#fff1f2;color:var(--pink);border:1px solid #ffe4e6;font-size:0.65rem;padding:2px 7px;border-radius:6px;font-weight:800;letter-spacing:1px;">GARUT</span>
    </a>

    <button class="navbar-toggler border-0 shadow-none text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <i class="bi bi-list fs-2 text-dark"></i>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-auto gap-1 my-2 my-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='index.php' && !isset($_GET['section']) ? 'nav-active' : '' ?>"
             href="<?= BASE_URL ?>index.php">Beranda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='katalog.php' ? 'nav-active' : '' ?>"
             href="<?= BASE_URL ?>katalog.php">Katalog Gear</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='paket.php' ? 'nav-active' : '' ?>"
             href="<?= BASE_URL ?>paket.php">Paket Camping</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='cara-sewa.php' ? 'nav-active' : '' ?>"
             href="<?= BASE_URL ?>cara-sewa.php">Cara Sewa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='tentang.php' ? 'nav-active' : '' ?>"
             href="<?= BASE_URL ?>tentang.php">Tentang Basecamp</a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <!-- Cart Button -->
        <a href="<?= BASE_URL ?>keranjang.php" class="cart-nav-btn me-2" title="Keranjang Sewa" style="background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a;">
          <i class="bi bi-bag"></i>
          <?php if ($_cartCount > 0): ?>
          <span class="cart-badge-count" style="border:2px solid #ffffff;"><?= $_cartCount ?></span>
          <?php endif; ?>
        </a>

        <?php if (isLoggedIn()): ?>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-light d-flex align-items-center gap-2 dropdown-toggle py-2 px-3"
                  style="border-radius:var(--radius-pill);border-color:#e2e8f0;background:#ffffff;color:#0f172a;box-shadow:var(--shadow-xs);"
                  data-bs-toggle="dropdown" aria-expanded="false">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:24px;height:24px;background:#fff1f2;color:var(--pink);font-size:.75rem;font-weight:700;">
              <?= strtoupper(substr(getUserName(), 0, 1)) ?>
            </span>
            <span class="fw-700" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem;color:#0f172a;">
              <?= htmlspecialchars(getUserName()) ?>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-radius:12px;border-color:#e2e8f0;min-width:200px;padding:.5rem;">
            <li>
              <a class="dropdown-item py-2 rounded-2" href="<?= BASE_URL ?>user/profile.php">
                <i class="bi bi-person me-2 text-pink"></i>Profil Saya
              </a>
            </li>
            <li>
              <a class="dropdown-item py-2 rounded-2" href="<?= BASE_URL ?>user/bookings.php">
                <i class="bi bi-calendar-check me-2 text-pink"></i>Riwayat Sewa Gear
              </a>
            </li>
            <li><hr class="dropdown-divider my-1" style="border-color:#f1f5f9;"></li>
            <li>
              <a class="dropdown-item py-2 rounded-2 text-danger" href="<?= BASE_URL ?>logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </div>
        <?php else: ?>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-outline-secondary px-3 py-2 fw-700" style="border-radius:var(--radius-pill);border-color:#e2e8f0;background:#ffffff;color:#334155;font-size:.85rem;">
          Masuk
        </a>
        <a href="<?= BASE_URL ?>register.php" class="btn-pink btn-sm" style="border-radius:var(--radius-pill);padding:0.45rem 1.15rem;">
          Daftar Sewa
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php
$_flashToast = $flash ?? getFlash();
if (!empty($_flashToast)):
  $isError = ($_flashToast['type'] ?? '') === 'error';
?>
<!-- Floating Flash Toast Notification -->
<div id="floatingFlashToast" class="floating-flash-toast <?= $isError ? 'toast-error' : '' ?>" role="alert" aria-live="assertive">
  <div class="toast-icon">
    <i class="bi bi-<?= $isError ? 'exclamation-circle-fill' : 'bag-check-fill' ?>"></i>
  </div>
  <div class="toast-content">
    <div class="fw-700" style="color:var(--text-primary);font-size:.88rem;"><?= htmlspecialchars($_flashToast['msg']) ?></div>
    <?php if (!$isError): ?>
    <a href="<?= BASE_URL ?>keranjang.php" class="fw-700 text-pink" style="font-size:.82rem;">
      Buka Keranjang & Checkout →
    </a>
    <?php endif; ?>
  </div>
  <button type="button" class="toast-close" onclick="dismissFlashToast()" aria-label="Tutup" title="Tutup">
    <i class="bi bi-x-lg" style="font-size:.8rem;"></i>
  </button>
</div>
<script>
function dismissFlashToast() {
  const t = document.getElementById('floatingFlashToast');
  if (t) {
    t.classList.add('toast-hiding');
    setTimeout(() => { if (t) t.remove(); }, 320);
  }
}
setTimeout(dismissFlashToast, 5000);
</script>
<?php endif; ?>
