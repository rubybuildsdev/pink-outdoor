<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Laporan Booking';
$active    = 'reports';

// Filter tanggal aman
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

// Export CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $stmtExp = $conn->prepare("
        SELECT b.id, u.name AS user_name, u.phone AS user_phone, p.name AS product_name,
               b.start_date, b.end_date, b.total_days, b.total_price, b.status, b.created_at
        FROM bookings b
        JOIN users u    ON b.user_id = u.id
        JOIN products p ON b.product_id = p.id
        WHERE DATE(b.created_at) BETWEEN ? AND ?
        ORDER BY b.created_at DESC
    ");
    $stmtExp->bind_param('ss', $from, $to);
    $stmtExp->execute();
    $rows = $stmtExp->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtExp->close();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan-booking-' . $from . '-sampai-' . $to . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID Booking', 'Nama Pelanggan', 'No HP', 'Gear', 'Mulai', 'Kembali', 'Hari', 'Total Harga', 'Status', 'Tanggal Booking']);
    foreach ($rows as $r) {
        fputcsv($output, [
            '#' . $r['id'],
            $r['user_name'],
            $r['user_phone'],
            $r['product_name'],
            $r['start_date'],
            $r['end_date'],
            $r['total_days'],
            $r['total_price'],
            $r['status'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Statistik periode (Prepared Statement)
$stmtStats = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
        COALESCE(SUM(CASE WHEN status='selesai' THEN total_price ELSE 0 END), 0) AS revenue
    FROM bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmtStats->bind_param('ss', $from, $to);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

// Top produk
$stmtTop = $conn->prepare("
    SELECT p.name, COUNT(b.id) AS count, COALESCE(SUM(b.total_price), 0) AS revenue
    FROM bookings b
    JOIN products p ON b.product_id = p.id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY b.product_id, p.name
    ORDER BY count DESC
    LIMIT 5
");
$stmtTop->bind_param('ss', $from, $to);
$stmtTop->execute();
$topProducts = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTop->close();

// Detail booking
$stmtBookings = $conn->prepare("
    SELECT b.*, u.name AS user_name, p.name AS product_name
    FROM bookings b
    JOIN users u    ON b.user_id = u.id
    JOIN products p ON b.product_id = p.id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
    ORDER BY b.created_at DESC
");
$stmtBookings->bind_param('ss', $from, $to);
$stmtBookings->execute();
$bookings = $stmtBookings->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtBookings->close();

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Filter -->
      <div class="section-card mb-4">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
          <div>
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
          </div>
          <div>
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
          </div>
          <button type="submit" class="btn-pink px-4">Filter</button>
        </form>
      </div>

      <!-- Statistik -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="stat-card stat-pink"><div class="stat-num"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label">Total Booking</div><div class="stat-icon"><i class="bi bi-calendar"></i></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card stat-green"><div class="stat-num"><?= (int)($stats['selesai'] ?? 0) ?></div><div class="stat-label">Selesai</div><div class="stat-icon"><i class="bi bi-check-circle"></i></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card stat-orange"><div class="stat-num"><?= (int)($stats['cancelled'] ?? 0) ?></div><div class="stat-label">Dibatalkan</div><div class="stat-icon"><i class="bi bi-x-circle"></i></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card stat-blue"><div class="stat-num">Rp <?= number_format(($stats['revenue'] ?? 0)/1000,0) ?>K</div><div class="stat-label">Revenue</div><div class="stat-icon"><i class="bi bi-cash"></i></div></div></div>
      </div>

      <div class="row g-4">
        <!-- Top Produk -->
        <div class="col-lg-4">
          <div class="section-card">
            <div class="section-title">🏆 Top Gear</div>
            <?php foreach ($topProducts as $i => $tp): ?>
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
              <div>
                <span class="fw-700 text-pink me-2">#<?= $i+1 ?></span>
                <span class="small"><?= htmlspecialchars($tp['name']) ?></span>
              </div>
              <div class="text-end">
                <div class="badge-confirmed"><?= $tp['count'] ?>x</div>
                <div class="text-muted" style="font-size:.72rem;">Rp <?= number_format($tp['revenue'],0,',','.') ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topProducts)): ?><p class="text-muted small">Tidak ada data</p><?php endif; ?>
          </div>
        </div>

        <!-- Tabel Detail -->
        <div class="col-lg-8">
          <div class="section-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="section-title mb-0">📋 Detail Booking (<?= count($bookings) ?>)</div>
              <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=1" class="btn-pink-outline px-3 py-1" style="font-size:.82rem;">
                <i class="bi bi-download me-1"></i>Export CSV
              </a>
            </div>
            <div class="table-responsive">
              <table class="table table-custom table-sm mb-0">
                <thead><tr><th>#</th><th>User</th><th>Gear</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                  <?php 
                  $sb=['pending'=>'badge-pending','diproses'=>'badge-diproses','sedang_sewa'=>'badge-sedang_sewa','selesai'=>'badge-selesai','cancelled'=>'badge-cancelled'];
                  $sl=['pending'=>'Pending','diproses'=>'Diproses','sedang_sewa'=>'Sedang Sewa','selesai'=>'Selesai','cancelled'=>'Dibatalkan'];
                  foreach ($bookings as $b): ?>
                  <tr>
                    <td>#<?= str_pad($b['id'],3,'0',STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($b['user_name']) ?></td>
                    <td><?= htmlspecialchars($b['product_name']) ?></td>
                    <td class="text-pink fw-600">Rp <?= number_format($b['total_price'],0,',','.') ?></td>
                    <td><span class="<?= $sb[$b['status']] ?? 'badge-pending' ?>"><?= $sl[$b['status']] ?? ucfirst($b['status']) ?></span></td>
                    <td class="small text-muted"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($bookings)): ?><tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
