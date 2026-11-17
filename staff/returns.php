<?php
require_once '../config/config.php';
requireStaff();
$pageTitle = 'Pengembalian Gear';
$active    = 'returns';

// Proses pengembalian → completed
if (isset($_GET['complete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: returns.php'); exit;
    }
    $id  = (int)$_GET['complete'];
    $uid = getUserId();

    // BUG FIX 1: execute_query() diganti prepare/bind_param/execute
    $stmt = $conn->prepare("UPDATE bookings SET status = 'selesai', handled_by = ? WHERE id = ? AND status = 'sedang_sewa'");
    $stmt->bind_param('ii', $uid, $id);
    $stmt->execute();
    $stmt->close();

    // BUG FIX 2: query raw dengan $id interpolasi langsung diganti prepared statement
    // Query lama: UPDATE products p JOIN bookings b ON b.product_id = p.id SET p.stock = p.stock + 1 WHERE b.id = $id
    // Masalah: satu produk bisa punya banyak booking, JOIN tanpa filter status bisa menambah stok
    // berlebihan jika produk punya booking lain. Ganti dengan subquery yang lebih aman.
    $stmtStock = $conn->prepare("
        UPDATE products
        SET stock = stock + 1
        WHERE id = (SELECT product_id FROM bookings WHERE id = ?)
    ");
    $stmtStock->bind_param('i', $id);
    $stmtStock->execute();
    $stmtStock->close();

    setFlash('success', 'Booking selesai & stok gear dikembalikan!');
    header('Location: returns.php'); exit;
}

// Booking yang sedang aktif dan yang sudah dikembalikan
$returned = $conn->query("
    SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, p.image, p.price_per_day
    FROM bookings b
    JOIN users u    ON b.user_id    = u.id
    JOIN products p ON b.product_id = p.id
    WHERE b.status IN ('sedang_sewa')
    ORDER BY b.end_date ASC
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <div class="alert alert-info border-0 mb-4" style="border-radius:var(--radius);font-size:.88rem;">
        <i class="bi bi-info-circle me-2"></i>
        Halaman ini menampilkan gear yang <strong>sedang aktif disewa</strong>. Sistem secara otomatis menghitung <strong>hari keterlambatan & denda</strong> bagi penyewa yang melewati tanggal jatuh tempo.
      </div>

      <div class="row g-4">
        <?php if (empty($returned)): ?>
        <div class="col-12">
          <div class="section-card text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
            <p class="mt-3 text-muted">Tidak ada gear yang perlu diproses saat ini.</p>
          </div>
        </div>
        <?php else: foreach ($returned as $b): 
          $todayDate = date('Y-m-d');
          $endDate   = date('Y-m-d', strtotime($b['end_date']));
          $isOverdue = $endDate < $todayDate;
          $daysLate  = 0;
          $denda     = 0;
          if ($isOverdue) {
              $daysLate = (int)ceil((strtotime($todayDate) - strtotime($endDate)) / 86400);
              $denda    = $daysLate * (float)($b['price_per_day'] ?? 0);
          }
          $totalWithDenda = (float)$b['total_price'] + $denda;

          $waPhone = preg_replace('/[^0-9]/', '', $b['user_phone'] ?? '');
          if (str_starts_with($waPhone, '0')) $waPhone = '62' . substr($waPhone, 1);
          $waMsg = urlencode("Halo {$b['user_name']}, ini dari staff Pink Outdoor ingin mengonfirmasi pengembalian gear " . $b['product_name'] . ($isOverdue ? " yang telah terlambat {$daysLate} hari (Denda: Rp " . number_format($denda, 0, ',', '.') . "). Mohon segera mengembalikan gear ke basecamp." : ". Apakah gear sudah siap dikembalikan? Terima kasih."));
        ?>
        <div class="col-md-6 col-lg-4">
          <div class="section-card h-100 d-flex flex-column" style="<?= $isOverdue ? 'border:1.5px solid #f87171;' : '' ?>">
            <div class="d-flex align-items-center gap-3 mb-3">
              <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($b['image']) ?>"
                   width="60" height="45"
                   style="object-fit:cover;border-radius:8px;"
                   onerror="this.onerror=null;this.src='../assets/img/no-image.svg'">
              <div>
                <div class="fw-700"><?= htmlspecialchars($b['product_name']) ?></div>
                <span class="badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst(htmlspecialchars($b['status'])) ?></span>
                <?php if ($isOverdue): ?>
                <span class="badge bg-danger text-white ms-1" style="font-size:.68rem;">TERLAMBAT</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="border-top pt-3 flex-grow-1" style="font-size:.85rem;">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Penyewa</span>
                <span class="fw-600"><?= htmlspecialchars($b['user_name']) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">No. HP</span>
                <span><?= htmlspecialchars($b['user_phone'] ?? '-') ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Tanggal Sewa</span>
                <span><?= date('d/m/Y', strtotime($b['start_date'])) ?></span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Batas Kembali</span>
                <span class="<?= $isOverdue ? 'text-danger fw-700' : '' ?>">
                  <?= date('d/m/Y', strtotime($b['end_date'])) ?>
                  <?= $isOverdue ? ' ⚠️' : '' ?>
                </span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Biaya Sewa</span>
                <span class="fw-600">Rp <?= number_format($b['total_price'], 0, ',', '.') ?></span>
              </div>

              <!-- Kalkulator Denda Otomatis -->
              <?php if ($isOverdue): ?>
              <div class="p-2 mt-2 rounded" style="background:#fee2e2;border:1px solid #fca5a5;font-size:.82rem;">
                <div class="fw-700 text-danger d-flex align-items-center justify-content-between">
                  <span><i class="bi bi-clock-history me-1"></i>Terlambat: <strong><?= $daysLate ?> Hari</strong></span>
                  <span class="text-danger fw-800">Denda: Rp <?= number_format($denda, 0, ',', '.') ?></span>
                </div>
                <div class="text-muted mt-1 small d-flex justify-content-between" style="font-size:.76rem;">
                  <span>Tarif: Rp <?= number_format($b['price_per_day'], 0, ',', '.') ?>/hr</span>
                  <span class="fw-700 text-dark">Total Wajib Bayar: Rp <?= number_format($totalWithDenda, 0, ',', '.') ?></span>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <div class="mt-3">
              <?php if (!empty($waPhone)): ?>
              <a href="https://wa.me/<?= $waPhone ?>?text=<?= $waMsg ?>" target="_blank"
                 class="btn btn-sm btn-outline-success w-100 mb-2 py-1 fw-600 d-flex align-items-center justify-content-center gap-1"
                 style="border-radius:6px;font-size:.82rem;">
                <i class="bi bi-whatsapp"></i>Hubungi Penyewa
              </a>
              <?php endif; ?>

              <?php if ($b['status'] === 'sedang_sewa'): ?>
              <a href="returns.php?complete=<?= (int)$b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                 onclick="return confirm('Konfirmasi gear sudah diterima & kondisi baik?<?= $isOverdue ? '\n\nPERINGATAN: Penyewa terlambat ' . $daysLate . ' hari. Pastikan denda Rp ' . number_format($denda, 0, ',', '.') . ' sudah dilunasi!' : '' ?>')"
                 class="btn-pink w-100 text-center d-block py-2">
                <i class="bi bi-check-circle me-1"></i> Selesaikan & Kembalikan Stok
              </a>
              <?php else: ?>
              <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=sedang_sewa"
                 onclick="return confirm('Tandai gear sudah dikembalikan oleh penyewa?')"
                 class="btn-pink-outline w-100 text-center d-block py-2">
                <i class="bi bi-arrow-return-left me-1"></i> Tandai Dikembalikan
              </a>
              <?php endif; ?>
            </div>

          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
