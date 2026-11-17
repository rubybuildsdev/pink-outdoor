<nav class="sidebar" id="sidebar">
  <script>if(window.innerWidth>=992&&localStorage.getItem('sidebar_collapsed')==='1'){document.getElementById('sidebar').classList.add('collapsed');}</script>

  <!-- Brand Header: Clean White + Pink Accent Logo -->
  <div class="sidebar-brand">
    <div class="sidebar-brand-logo">PO</div>
    <div>
      <div class="sidebar-brand-text"><span>PINK</span> OUTDOOR</div>
      <div class="sidebar-brand-sub">Staff Panel</div>
    </div>
  </div>

  <div class="sidebar-menu">

    <div class="menu-label">Utama</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>staff/index.php" class="<?= ($active??'')==='dashboard'?'active':'' ?>" data-tooltip="Dashboard">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
    </ul>

    <div class="menu-label">Transaksi</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>staff/bookings.php" class="<?= ($active??'')==='bookings'?'active':'' ?>" data-tooltip="Booking Aktif">
        <i class="bi bi-calendar-check"></i><span>Booking Aktif</span></a></li>
      <li><a href="<?= BASE_URL ?>staff/history.php" class="<?= ($active??'')==='history'?'active':'' ?>" data-tooltip="Riwayat Booking">
        <i class="bi bi-clock-history"></i><span>Riwayat Booking</span></a></li>
      <li><a href="<?= BASE_URL ?>staff/returns.php" class="<?= ($active??'')==='returns'?'active':'' ?>" data-tooltip="Pengembalian">
        <i class="bi bi-arrow-return-left"></i><span>Pengembalian</span></a></li>
      <li><a href="<?= BASE_URL ?>staff/payments.php" class="<?= ($active??'')==='payments'?'active':'' ?>" data-tooltip="Verifikasi Bayar">
        <i class="bi bi-credit-card"></i><span>Verifikasi Bayar</span></a></li>
    </ul>

    <div class="menu-label">Katalog</div>
    <ul class="nav flex-column">
      <li><a href="<?= BASE_URL ?>staff/products.php" class="<?= ($active??'')==='products'?'active':'' ?>" data-tooltip="Kelola Gear">
        <i class="bi bi-box-seam"></i><span>Kelola Gear</span></a></li>
      <li><a href="<?= BASE_URL ?>staff/stock.php" class="<?= ($active??'')==='stock'?'active':'' ?>" data-tooltip="Update Stok">
        <i class="bi bi-boxes"></i><span>Update Stok</span></a></li>
    </ul>

  </div>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" data-tooltip="Keluar (Logout)">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
