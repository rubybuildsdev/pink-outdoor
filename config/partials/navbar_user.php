<?php
$_cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
$_curPage   = basename($_SERVER['PHP_SELF']);
?>
<!-- Top Utility Bar (Real Business Credibility) -->
<div class="top-utility-bar d-none d-md-block">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
      <span><i class="bi bi-geo-alt-fill text-pink me-1"></i>Basecamp Garut: <?= BASECAMP_ADDRESS ?></span>
      <span class="text-secondary opacity-50">|</span>
      <span><i class="bi bi-truck me-1 text-pink"></i>Antar-Jemput Stasiun & Basecamp Pendakian</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="top-utility-badge">
        <span class="live-dot"></span> <?= BASECAMP_HOURS ?>
      </span>
      <span class="text-secondary opacity-50">|</span>
      <a href="<?= STORE_WA_LINK ?>" target="_blank" rel="noopener">
        <i class="bi bi-whatsapp me-1" style="color:#25d366;"></i>CS: <?= STORE_PHONE ?>
      </a>
    </div>
  </div>
</div>

<nav class="navbar navbar-expand-lg main-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>index.php">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--pink);border-radius:6px;color:#fff;font-size:.9rem;">
        <i class="bi bi-triangle-fill" style="transform:scale(0.85);"></i>
      </span>
      <span style="font-weight:900;letter-spacing:-0.5px;">PINK<span style="color:var(--pink);">OUTDOOR</span></span>
      <span class="brand-badge d-none d-sm-inline">GARUT</span>
    </a>

    <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navUser" aria-label="Toggle navigation">
      <i class="bi bi-list fs-3"></i>
    </button>

    <div class="collapse navbar-collapse" id="navUser">
      <ul class="navbar-nav mx-auto gap-1 my-2 my-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='index.php' ? 'nav-active' : '' ?>" href="<?= BASE_URL ?>index.php">Beranda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='katalog.php' ? 'nav-active' : '' ?>" href="<?= BASE_URL ?>katalog.php">Katalog Gear</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='paket.php' ? 'nav-active' : '' ?>" href="<?= BASE_URL ?>paket.php">Paket Sewa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $_curPage==='bookings.php' ? 'nav-active' : '' ?>" href="<?= BASE_URL ?>user/bookings.php">
            <i class="bi bi-calendar-check me-1 text-pink"></i>Booking Saya
          </a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <!-- Keranjang Icon -->
        <a href="<?= BASE_URL ?>keranjang.php"
           class="nav-link text-white position-relative d-flex align-items-center"
           style="min-width:44px;min-height:44px;justify-content:center;border-radius:var(--radius-sm);background:rgba(255,255,255,.05);"
           title="Keranjang Sewa">
          <i class="bi bi-bag-fill fs-5"></i>
          <?php if ($_cartCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                style="background:var(--pink);font-size:.62rem;min-width:19px;padding:.26rem .45rem;">
            <?= $_cartCount ?>
          </span>
          <?php endif; ?>
        </a>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
          <button class="btn-pink btn-sm d-flex align-items-center gap-2 dropdown-toggle"
                  data-bs-toggle="dropdown" aria-expanded="false" style="font-weight:700;">
            <i class="bi bi-person-circle"></i>
            <span><?= htmlspecialchars(getUserName()) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow" style="border-radius:var(--radius);border:1px solid var(--border);padding:.5rem;">
            <li>
              <div class="px-3 py-2 border-bottom mb-1" style="border-color:var(--border)!important;">
                <div class="fw-700 text-truncate" style="font-size:.85rem;color:var(--text-primary);"><?= htmlspecialchars(getUserName()) ?></div>
                <div class="text-muted small text-truncate" style="font-size:.75rem;"><?= htmlspecialchars(function_exists('getUserEmail') ? getUserEmail() : ($_SESSION['email'] ?? '')) ?></div>
              </div>
            </li>
            <li>
              <a class="dropdown-item py-2 <?= $_curPage==='profile.php'?'fw-700 text-pink':'' ?>"
                 href="<?= BASE_URL ?>user/profile.php">
                <i class="bi bi-person me-2 text-pink"></i>Profil Saya
              </a>
            </li>
            <li>
              <a class="dropdown-item py-2 <?= $_curPage==='bookings.php'?'fw-700 text-pink':'' ?>"
                 href="<?= BASE_URL ?>user/bookings.php">
                <i class="bi bi-calendar2-check me-2 text-pink"></i>Riwayat Booking
              </a>
            </li>
            <?php if (isAdmin()): ?>
            <li>
              <a class="dropdown-item py-2 fw-700 text-primary" href="<?= BASE_URL ?>admin/index.php">
                <i class="bi bi-speedometer2 me-2"></i>Panel Admin
              </a>
            </li>
            <?php elseif (isStaff()): ?>
            <li>
              <a class="dropdown-item py-2 fw-700 text-primary" href="<?= BASE_URL ?>staff/index.php">
                <i class="bi bi-shield-check me-2"></i>Panel Staff
              </a>
            </li>
            <?php endif; ?>
            <li><hr class="dropdown-divider" style="border-color:var(--border);"></li>
            <li>
              <a class="dropdown-item py-2 text-danger fw-600" href="<?= BASE_URL ?>logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>