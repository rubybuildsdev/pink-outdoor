<?php
require_once '../config/config.php';
requireStaff();
$pageTitle = 'Kelola Booking';
$active    = 'bookings';

// Staff: konfirmasi, aktifkan, tandai dikembalikan
if (isset($_GET['status']) && isset($_GET['id'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: bookings.php'); exit;
    }
    $id      = (int)$_GET['id'];
    $status  = $_GET['status'];
    $allowed = ['diproses', 'sedang_sewa', 'selesai'];
    if (in_array($status, $allowed)) {
        $uid = getUserId();
        // BUG FIX: execute_query() hanya ada di PHP 8.2+, menyebabkan Fatal Error (HTTP 500)
        // di PHP 7.4 / 8.0 / 8.1. Ganti dengan prepare() → bind_param() → execute().
        $stmt = $conn->prepare("UPDATE bookings SET status = ?, handled_by = ? WHERE id = ?");
        $stmt->bind_param('sii', $status, $uid, $id);
        $stmt->execute();
        $stmt->close();

        // Kembalikan stok jika selesai
        if ($status === 'selesai') {
            $stmtStock = $conn->prepare("UPDATE products SET stock = stock + 1 WHERE id = (SELECT product_id FROM bookings WHERE id = ?)");
            $stmtStock->bind_param('i', $id);
            $stmtStock->execute();
            $stmtStock->close();
        }

        setFlash('success', 'Status booking diperbarui!');
    }
    header('Location: bookings.php'); exit;
}

// Filter — whitelist nilai yang diizinkan untuk mencegah SQL injection
$filter  = $_GET['filter'] ?? '';
$allowed = ['', 'pending', 'diproses', 'sedang_sewa'];
if (!in_array($filter, $allowed)) $filter = '';

if ($filter !== '') {
    $stmt = $conn->prepare("
        SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name
        FROM bookings b
        JOIN users u    ON b.user_id    = u.id
        JOIN products p ON b.product_id = p.id
        WHERE b.status = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param('s', $filter);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $bookings = $conn->query("
        SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name
        FROM bookings b
        JOIN users u    ON b.user_id    = u.id
        JOIN products p ON b.product_id = p.id
        WHERE b.status IN ('pending','diproses','sedang_sewa')
        ORDER BY b.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <div class="section-card mb-3">
        <div class="d-flex gap-2 flex-wrap">
          <?php foreach (['' => 'Aktif', 'pending' => 'Menunggu', 'diproses' => 'Diproses', 'sedang_sewa' => 'Sedang Sewa'] as $v => $l): ?>
          <a href="bookings.php?filter=<?= urlencode($v) ?>"
             class="<?= $filter === $v ? 'btn-pink' : 'btn-pink-outline' ?> px-3 py-1"
             style="border-radius:20px;font-size:.85rem;">
            <?= htmlspecialchars($l) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-title">📋 Daftar Booking (<?= count($bookings) ?>)</div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead>
              <tr><th>#</th><th>User</th><th>Gear</th><th>Tanggal</th><th>Hari</th><th>Total</th><th>Pengambilan</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td>
                  <?php if (!empty($b['order_number'])): ?>
                  <span class="badge bg-dark fw-700" style="font-size:.7rem;"><?= htmlspecialchars($b['order_number']) ?></span>
                  <div class="text-muted" style="font-size:.68rem;">#<?= str_pad((int)$b['id'], 3, '0', STR_PAD_LEFT) ?></div>
                  <?php else: ?>
                  <span class="fw-600 small text-muted">#<?= str_pad((int)$b['id'], 3, '0', STR_PAD_LEFT) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-600 small"><?= htmlspecialchars($b['user_name']) ?></div>
                  <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($b['user_phone'] ?? '') ?></div>
                </td>
                <td><?= htmlspecialchars($b['product_name']) ?></td>
                <td class="small">
                  <?= date('d/m/y', strtotime($b['start_date'])) ?> –
                  <?= date('d/m/y', strtotime($b['end_date'])) ?>
                </td>
                <td><?= (int)$b['total_days'] ?>h</td>
                <td class="text-pink fw-600">Rp <?= number_format($b['total_price'], 0, ',', '.') ?></td>
                <td>
                  <?php if(($b['pickup_method']??'pickup')==='delivery'): ?>
                  <span class="badge-confirmed" style="font-size:.7rem;"><i class="bi bi-truck me-1"></i>Diantar</span>
                  <?php else: ?>
                  <span class="badge-pending" style="font-size:.7rem;"><i class="bi bi-shop me-1"></i>Ambil</span>
                  <?php endif; ?>
                </td>
                <?php $sl=['pending'=>'Menunggu','diproses'=>'Diproses','sedang_sewa'=>'Sedang Sewa','selesai'=>'Selesai','cancelled'=>'Dibatalkan']; ?>
                <td><span class="badge-<?= htmlspecialchars($b['status']) ?>"><?= $sl[$b['status']]??ucfirst($b['status']) ?></span></td>
                <td>
                  <?php if ($b['status'] === 'pending'): ?>
                  <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=diproses&csrf_token=<?= csrfToken() ?>"
                     onclick="return confirm('Proses booking ini?')"
                     class="btn btn-sm btn-success py-0">Proses</a>

                  <?php elseif ($b['status'] === 'diproses'): ?>
                  <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=sedang_sewa&csrf_token=<?= csrfToken() ?>"
                     onclick="return confirm('Set sebagai sedang disewa?')"
                     class="btn btn-sm btn-primary py-0">Sedang Sewa</a>

                  <?php elseif ($b['status'] === 'sedang_sewa'): ?>
                  <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=selesai&csrf_token=<?= csrfToken() ?>"
                     onclick="return confirm('Tandai booking selesai?')"
                     class="btn btn-sm btn-warning py-0">Selesai</a>

                  <?php else: ?>
                  <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($bookings)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada booking</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
