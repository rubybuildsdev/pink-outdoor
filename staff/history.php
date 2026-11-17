<?php
// FIX #4: Staff dapat akses Riwayat Booking (semua booking termasuk yang selesai/batal)
require_once '../config/config.php';
requireStaff();

$pageTitle = 'Riwayat Booking';
$active    = 'history';

// Filter status
$filter  = $_GET['filter'] ?? '';
$allowed = ['', 'selesai', 'cancelled', 'returned'];
if (!in_array($filter, $allowed)) $filter = '';

// Filter tanggal
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

// Build query dengan prepared statement
$conditions = ["b.status IN ('selesai','cancelled')"];
$params     = [];
$types      = '';

if ($filter !== '') {
    $conditions[] = 'b.status = ?';
    $types .= 's';
    $params[] = $filter;
}
if ($from !== '') {
    $conditions[] = 'DATE(b.created_at) >= ?';
    $types .= 's';
    $params[] = $from;
}
if ($to !== '') {
    $conditions[] = 'DATE(b.created_at) <= ?';
    $types .= 's';
    $params[] = $to;
}

$where = 'WHERE ' . implode(' AND ', $conditions);
$sql   = "
    SELECT b.*, u.name AS user_name, u.phone AS user_phone,
           p.name AS product_name,
           pay.method, pay.status AS pay_status, pay.amount
    FROM bookings b
    JOIN users u    ON b.user_id    = u.id
    JOIN products p ON b.product_id = p.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    $where
    ORDER BY b.updated_at DESC, b.created_at DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $bookings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Hitung ringkasan
$totalRev = array_sum(array_column(
    array_filter($bookings, fn($b) => in_array($b['status'], ['selesai','returned'])),
    'total_price'
));

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Statistik ringkas -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card stat-green">
            <div class="stat-num"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'selesai')) ?></div>
            <div class="stat-label">Completed</div>
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-blue">
            <div class="stat-num"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'returned')) ?></div>
            <div class="stat-label">Returned</div>
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-orange">
            <div class="stat-num"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')) ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-pink">
            <div class="stat-num" style="font-size:1.1rem;">Rp <?= number_format($totalRev/1000, 0) ?>K</div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-icon"><i class="bi bi-cash"></i></div>
          </div>
        </div>
      </div>

      <!-- Filter -->
      <div class="section-card mb-3">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
          <div>
            <label class="form-label" style="font-size:.82rem;">Status</label>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach (['' => 'Semua', 'selesai' => 'Completed', 'returned' => 'Returned', 'cancelled' => 'Cancelled'] as $v => $l): ?>
              <a href="history.php?filter=<?= urlencode($v) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
                 class="<?= $filter === $v ? 'btn-pink' : 'btn-pink-outline' ?> px-3 py-1"
                 style="border-radius:20px;font-size:.82rem;">
                <?= $l ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="d-flex gap-2 align-items-end">
            <div>
              <label class="form-label" style="font-size:.82rem;">Dari</label>
              <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div>
              <label class="form-label" style="font-size:.82rem;">Sampai</label>
              <input type="date" name="to"   class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
            </div>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="btn-pink btn-sm px-3">Filter</button>
            <a href="history.php" class="btn-pink-outline btn-sm px-3">Reset</a>
          </div>
        </form>
      </div>

      <!-- Tabel Riwayat -->
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="section-title mb-0">📋 Riwayat Booking (<?= count($bookings) ?>)</div>
        </div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead>
              <tr><th>#</th><th>User</th><th>Gear</th><th>Tanggal Sewa</th><th>Hari</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td class="fw-600 small text-muted">#<?= str_pad((int)$b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="fw-600 small"><?= htmlspecialchars($b['user_name']) ?></div>
                  <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($b['user_phone'] ?? '') ?></div>
                </td>
                <td><?= htmlspecialchars($b['product_name']) ?></td>
                <td class="small">
                  <?= date('d/m/Y', strtotime($b['start_date'])) ?><br>
                  <span class="text-muted">→ <?= date('d/m/Y', strtotime($b['end_date'])) ?></span>
                </td>
                <td><?= (int)$b['total_days'] ?>h</td>
                <td class="fw-600 text-pink">Rp <?= number_format($b['total_price'], 0, ',', '.') ?></td>
                <td>
                  <span class="badge bg-light text-dark text-uppercase small"><?= htmlspecialchars($b['method'] ?? '-') ?></span><br>
                  <span class="badge-<?= htmlspecialchars($b['pay_status'] ?? 'pending') ?>" style="font-size:.7rem;">
                    <?= ucfirst(htmlspecialchars($b['pay_status'] ?? '-')) ?>
                  </span>
                </td>
                <td>
                  <span class="badge-<?= htmlspecialchars($b['status']) ?>">
                    <?= ucfirst(htmlspecialchars($b['status'])) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($bookings)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada riwayat booking</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
