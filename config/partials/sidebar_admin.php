<nav class="sidebar" id="sidebar">
  <script>if(window.innerWidth>=992&&localStorage.getItem('sidebar_collapsed')==='1'){document.getElementById('sidebar').classList.add('collapsed');}</script>

  <!-- Brand Header: Clean White + Pink Accent Logo -->
  <div class="sidebar-brand">
    <div class="sidebar-brand-logo">PO</div>
    <div>
      <div class="sidebar-brand-text"><span>PINK</span> OUTDOOR</div>
      <div class="sidebar-brand-sub">Admin Panel</div>
    </div>
  </div>

  <div class="sidebar-menu">

    <div class="menu-label">Eksekutif &amp; Finansial</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>admin/index.php" class="<?= ($active??'')==='dashboard'?'active':'' ?>" data-tooltip="Dashboard Eksekutif">
        <i class="bi bi-speedometer2"></i><span>Dashboard Eksekutif</span></a></li>
      <li><a href="<?= BASE_URL ?>admin/reports.php" class="<?= ($active??'')==='reports'?'active':'' ?>" data-tooltip="Laporan &amp; Pembukuan">
        <i class="bi bi-bar-chart-line"></i><span>Laporan &amp; Pembukuan</span></a></li>
    </ul>

    <div class="menu-label">Tata Kelola Tim</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>admin/staff.php" class="<?= ($active??'')==='staff'?'active':'' ?>" data-tooltip="Kelola Staff">
        <i class="bi bi-person-badge"></i><span>Kelola Staff</span></a></li>
      <li><a href="<?= BASE_URL ?>admin/users.php" class="<?= ($active??'')==='users'?'active':'' ?>" data-tooltip="Kelola Pelanggan">
        <i class="bi bi-people"></i><span>Kelola Pelanggan</span></a></li>
    </ul>

    <div class="menu-label">Supervisi &amp; Master Data</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>admin/bookings.php" class="<?= ($active??'')==='bookings'?'active':'' ?>" data-tooltip="Supervisi Booking">
        <i class="bi bi-calendar-check"></i><span>Supervisi Booking</span></a></li>
      <li><a href="<?= BASE_URL ?>admin/payments.php" class="<?= ($active??'')==='payments'?'active':'' ?>" data-tooltip="Verifikasi Bayar">
        <i class="bi bi-credit-card"></i><span>Verifikasi Bayar</span></a></li>
      <li><a href="<?= BASE_URL ?>admin/products.php" class="<?= ($active??'')==='products'?'active':'' ?>" data-tooltip="Master Katalog Gear">
        <i class="bi bi-box-seam"></i><span>Master Katalog Gear</span></a></li>
      <li><a href="<?= BASE_URL ?>admin/categories.php" class="<?= ($active??'')==='categories'?'active':'' ?>" data-tooltip="Master Kategori">
        <i class="bi bi-tags"></i><span>Master Kategori</span></a></li>
    </ul>

  </div>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" data-tooltip="Keluar (Logout)">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
