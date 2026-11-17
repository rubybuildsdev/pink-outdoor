<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Manajemen Booking';
$active    = 'bookings';

// Ubah status booking
if (isset($_GET['status']) && isset($_GET['id'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: bookings.php'); exit;
    }
    $id      = (int)$_GET['id'];
    $status  = $_GET['status'];
    $allowed = ['pending', 'diproses', 'sedang_sewa', 'selesai', 'cancelled'];
    if (in_array($status, $allowed)) {
        $handledBy = getUserId();
        $stmt = $conn->prepare("UPDATE bookings SET status=?, handled_by=? WHERE id=?");
        $stmt->bind_param('sii', $status, $handledBy, $id);
        $stmt->execute();
        $stmt->close();

        // Jika selesai atau cancelled, kembalikan stok
        if (in_array($status, ['selesai', 'cancelled'])) {
            $stmtStock = $conn->prepare("UPDATE products SET stock = stock + 1 WHERE id = (SELECT product_id FROM bookings WHERE id = ?)");
            $stmtStock->bind_param('i', $id);
            $stmtStock->execute();
            $stmtStock->close();
        }

        setFlash('success', "Status booking #" . str_pad($id, 3, '0', STR_PAD_LEFT) . " diubah ke " . ucfirst(str_replace('_', ' ', $status)));
    }
    header('Location: bookings.php'); exit;
}

// Filter aman (mencegah SQL injection)
$filter = $_GET['filter'] ?? '';
$allowedFilters = ['', 'pending', 'diproses', 'sedang_sewa', 'selesai', 'cancelled'];
if (!in_array($filter, $allowedFilters)) {
    $filter = '';
}

if ($filter !== '') {
    $stmt = $conn->prepare("
        SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, p.price_per_day
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
        SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, p.price_per_day
        FROM bookings b
        JOIN users u    ON b.user_id    = u.id
        JOIN products p ON b.product_id = p.id
        ORDER BY b.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Filter Tabs -->
      <div class="section-card mb-3">
        <div class="d-flex gap-2 flex-wrap">
          <?php $filters = [
              ''            => 'Semua',
              'pending'     => 'Pending',
              'diproses'    => 'Diproses',
              'sedang_sewa' => 'Sedang Sewa',
              'selesai'     => 'Selesai',
              'cancelled'   => 'Cancelled'
          ]; ?>
          <?php foreach ($filters as $val => $label): ?>
          <a href="bookings.php?filter=<?= $val ?>"
             class="<?= $filter===$val ? 'btn-pink' : 'btn-pink-outline' ?> px-3 py-1" style="font-size:.85rem;border-radius:20px;">
            <?= $label ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="section-card">
        <div class="section-title">📋 Daftar Booking (<?= count($bookings) ?>)</div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead><tr><th>#</th><th>User</th><th>Gear</th><th>Tanggal Sewa</th><th>Hari</th><th>Total</th><th>Pengambilan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td>
                  <?php if (!empty($b['order_number'])): ?>
                  <span class="badge bg-dark fw-700" style="font-size:.7rem;"><?= htmlspecialchars($b['order_number']) ?></span>
                  <div class="text-muted" style="font-size:.68rem;">#<?= str_pad((int)$b['id'], 3, '0', STR_PAD_LEFT) ?></div>
                  <?php else: ?>
                  <span class="text-muted small fw-600">#<?= str_pad((int)$b['id'], 3, '0', STR_PAD_LEFT) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-600 small"><?= htmlspecialchars($b['user_name']) ?></div>
                  <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($b['user_phone'] ?? '') ?></div>
                </td>
                <td><?= htmlspecialchars($b['product_name']) ?></td>
                <td class="small">
                  <?= date('d M Y', strtotime($b['start_date'])) ?><br>
                  <span class="text-muted">→ <?= date('d M Y', strtotime($b['end_date'])) ?></span>
                </td>
                <td><?= (int)$b['total_days'] ?> hari</td>
                <td class="fw-600 text-pink">Rp <?= number_format($b['total_price'],0,',','.') ?></td>
                <td>
                  <?php if(($b['pickup_method']??'pickup')==='delivery'): ?>
                  <span class="badge-confirmed" style="font-size:.7rem;"><i class="bi bi-truck me-1"></i>Diantar</span>
                  <?php else: ?>
                  <span class="badge-pending" style="font-size:.7rem;"><i class="bi bi-shop me-1"></i>Ambil</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php 
                  $sl=['pending'=>'Menunggu','diproses'=>'Diproses','sedang_sewa'=>'Sedang Sewa','selesai'=>'Selesai','cancelled'=>'Dibatalkan']; 
                  $sb=['pending'=>'badge-pending','diproses'=>'badge-diproses','sedang_sewa'=>'badge-sedang_sewa','selesai'=>'badge-selesai','cancelled'=>'badge-cancelled']; 
                  ?>
                  <span class="<?= $sb[$b['status']]??'badge-pending' ?>"><?= $sl[$b['status']]??ucfirst($b['status']) ?></span>
                </td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary py-0 dropdown-toggle" data-bs-toggle="dropdown">Ubah</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <?php
                      $next = [
                          'pending'     => ['diproses','cancelled'],
                          'diproses'    => ['sedang_sewa','cancelled'],
                          'sedang_sewa' => ['selesai','cancelled']
                      ];
                      $options = $next[$b['status']] ?? [];
                      foreach ($options as $opt): ?>
                      <li><a class="dropdown-item small" href="#"
                             onclick="confirmAction('bookings.php?id=<?= (int)$b['id'] ?>&status=<?= $opt ?>&csrf_token=<?= csrfToken() ?>','Ubah status ke <?= ucfirst(str_replace('_',' ',$opt)) ?>?');return false;">
                        <?= ucfirst(str_replace('_',' ',$opt)) ?>
                      </a></li>
                      <?php endforeach; ?>
                      <?php if (empty($options)): ?><li><span class="dropdown-item text-muted small">Tidak ada aksi</span></li><?php endif; ?>
                    </ul>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($bookings)): ?><tr><td colspan="9" class="text-center text-muted py-4">Tidak ada booking</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
