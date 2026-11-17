<?php
require_once '../config/config.php';
requireStaff();
$pageTitle = 'Dashboard Staff';
$active    = 'dashboard';

// Helper link WhatsApp
function getWhatsAppUrl(?string $phone, string $text = ''): string {
    if (empty($phone)) return '#';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0')) {
        $clean = '62' . substr($clean, 1);
    }
    return 'https://wa.me/' . $clean . ($text ? '?text=' . urlencode($text) : '');
}

// Stats operasional staff
$queries = [
    'pending'     => "SELECT COUNT(*) FROM bookings WHERE status='pending'",
    'diproses'    => "SELECT COUNT(*) FROM bookings WHERE status='diproses'",
    'sedang_sewa' => "SELECT COUNT(*) FROM bookings WHERE status='sedang_sewa'",
    'pay_verify'  => "SELECT COUNT(*) FROM payments WHERE status='pending'",
    'due_today'   => "SELECT COUNT(*) FROM bookings WHERE status='sedang_sewa' AND end_date = CURRENT_DATE()",
    'overdue'     => "SELECT COUNT(*) FROM bookings WHERE status='sedang_sewa' AND end_date < CURRENT_DATE()",
    'total_gear'  => "SELECT COUNT(*) FROM products WHERE status='available'",
    'low_stock'   => "SELECT COUNT(*) FROM products WHERE stock <= 2",
];

$stats = [];
foreach ($queries as $k => $q) {
    $r = $conn->query($q);
    $row = $r->fetch_row();
    $stats[$k] = $row[0];
}

// 8 Antrean Booking Operasional (diurutkan berdasarkan urgensi)
$activeBookings = $conn->query("
    SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, p.image AS product_image,
           pay.status AS pay_status, pay.method AS pay_method
    FROM bookings b
    JOIN users u    ON b.user_id = u.id
    JOIN products p ON b.product_id = p.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    WHERE b.status IN ('pending','diproses','sedang_sewa')
    ORDER BY
        CASE
            WHEN b.status = 'sedang_sewa' AND b.end_date < CURRENT_DATE() THEN 1
            WHEN b.status = 'sedang_sewa' AND b.end_date = CURRENT_DATE() THEN 2
            WHEN b.status = 'diproses' THEN 3
            WHEN b.status = 'pending' THEN 4
            ELSE 5
        END,
        b.created_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Pengembalian gear hari ini atau lewat jatuh tempo
$dueReturns = $conn->query("
    SELECT b.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, p.image AS product_image
    FROM bookings b
    JOIN users u    ON b.user_id = u.id
    JOIN products p ON b.product_id = p.id
    WHERE b.status = 'sedang_sewa' AND b.end_date <= CURRENT_DATE()
    ORDER BY b.end_date ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Header & Shortcut Cepat -->
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <h4 style="font-weight:800;color:var(--text-primary);margin-bottom:.25rem;">
            Halo, <?= htmlspecialchars(getUserName()) ?> 👋
          </h4>
          <p style="color:var(--text-muted);font-size:.875rem;margin:0;">
            <?= date('l, d F Y') ?> · Dashboard Operasional Harian Rental Gear
          </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="returns.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;">
            <i class="bi bi-arrow-return-left text-pink"></i>Pengembalian
            <?php if ($stats['due_today'] + $stats['overdue'] > 0): ?>
            <span class="badge bg-danger rounded-pill" style="font-size:.65rem;"><?= $stats['due_today'] + $stats['overdue'] ?></span>
            <?php endif; ?>
          </a>
          <a href="payments.php" class="btn btn-sm btn-pink-outline d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;">
            <i class="bi bi-credit-card"></i>Verifikasi Bayar
            <?php if ($stats['pay_verify'] > 0): ?>
            <span class="badge bg-danger rounded-pill" style="font-size:.65rem;"><?= $stats['pay_verify'] ?></span>
            <?php endif; ?>
          </a>
          <a href="bookings.php" class="btn btn-sm btn-pink d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;">
            <i class="bi bi-calendar-check me-1"></i>Kelola Booking
          </a>
        </div>
      </div>

      <!-- Stat Cards Operasional -->
      <div class="row g-4 mb-5">
        <div class="col-6 col-lg-3">
          <a href="bookings.php?filter=pending" class="text-decoration-none">
            <div class="stat-card stat-orange" style="padding:1.5rem;">
              <div class="stat-num"><?= (int)$stats['pending'] ?></div>
              <div class="stat-label">Menunggu Proses</div>
              <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-lg-3">
          <a href="bookings.php?filter=diproses" class="text-decoration-none">
            <div class="stat-card stat-pink" style="padding:1.5rem;">
              <div class="stat-num"><?= (int)$stats['diproses'] ?></div>
              <div class="stat-label">Siap Diserahkan</div>
              <div class="stat-icon"><i class="bi bi-box-arrow-up-right"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-lg-3">
          <a href="bookings.php?filter=sedang_sewa" class="text-decoration-none">
            <div class="stat-card stat-blue" style="padding:1.5rem;">
              <div class="stat-num"><?= (int)$stats['sedang_sewa'] ?></div>
              <div class="stat-label">Sedang Disewa</div>
              <div class="stat-icon"><i class="bi bi-lightning-charge"></i></div>
            </div>
          </a>
        </div>
        <div class="col-6 col-lg-3">
          <a href="returns.php" class="text-decoration-none">
            <div class="stat-card <?= ($stats['overdue'] > 0) ? 'stat-orange' : 'stat-green' ?>" style="padding:1.5rem;">
              <div class="d-flex align-items-baseline gap-2">
                <div class="stat-num <?= ($stats['overdue'] > 0) ? 'text-danger' : '' ?>">
                  <?= (int)($stats['due_today'] + $stats['overdue']) ?>
                </div>
                <?php if ($stats['overdue'] > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size:.7rem;"><?= $stats['overdue'] ?> Telat</span>
                <?php endif; ?>
              </div>
              <div class="stat-label">Harus Kembali Hari Ini</div>
              <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            </div>
          </a>
        </div>
      </div>

      <!-- Main Operational Area -->
      <div class="row g-4 mb-4">

        <!-- Kolom Kiri: Antrean Booking Lapangan (col-lg-8) -->
        <div class="col-lg-8">
          <div class="section-card h-100" style="padding:1.75rem;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="section-title mb-0">📋 Antrean Booking Aktif</div>
                <small class="text-muted" style="font-size:.78rem;">Pesanan yang butuh penanganan staf</small>
              </div>
              <a href="bookings.php" class="btn-pink btn-sm">Buka Semua</a>
            </div>

            <div class="table-responsive">
              <table class="table table-custom align-middle mb-0">
                <thead>
                  <tr>
                    <th>#ID</th>
                    <th>Penyewa</th>
                    <th>Gear & Pengambilan</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th>Aksi Cepat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($activeBookings)): ?>
                  <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada antrean pesanan aktif saat ini.</td></tr>
                  <?php else:
                    $sl = [
                      'pending'     => 'Menunggu',
                      'diproses'    => 'Diproses',
                      'sedang_sewa' => 'Sedang Sewa',
                      'selesai'     => 'Selesai',
                      'cancelled'   => 'Dibatalkan'
                    ];
                    $sb = [
                      'pending'     => 'badge-pending',
                      'diproses'    => 'badge-diproses',
                      'sedang_sewa' => 'badge-sedang_sewa',
                      'selesai'     => 'badge-selesai',
                      'cancelled'   => 'badge-cancelled'
                    ];
                    $today = date('Y-m-d');
                    foreach ($activeBookings as $b):
                      $isOverdue = ($b['status'] === 'sedang_sewa' && $b['end_date'] < $today);
                      $isDueToday = ($b['status'] === 'sedang_sewa' && $b['end_date'] === $today);
                  ?>
                  <tr>
                    <td class="fw-700 text-muted" style="font-size:.8rem;">
                      #<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?>
                    </td>
                    <td>
                      <div class="fw-600" style="font-size:.88rem;"><?= htmlspecialchars($b['user_name']) ?></div>
                      <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="text-muted small" style="font-size:.75rem;"><?= htmlspecialchars($b['user_phone'] ?? '-') ?></span>
                        <?php if (!empty($b['user_phone'])): ?>
                        <a href="<?= getWhatsAppUrl($b['user_phone'], 'Halo ' . $b['user_name'] . ', ini dari Pink Outdoor terkait booking #' . str_pad($b['id'], 3, '0', STR_PAD_LEFT)) ?>"
                           target="_blank" class="text-success small text-decoration-none" title="Chat via WhatsApp">
                          <i class="bi bi-whatsapp"></i>
                        </a>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <div class="fw-600 text-truncate" style="max-width:180px;font-size:.85rem;" title="<?= htmlspecialchars($b['product_name']) ?>">
                        <?= htmlspecialchars($b['product_name']) ?>
                      </div>
                      <div class="mt-1">
                        <?php if (($b['pickup_method'] ?? 'pickup') === 'delivery'): ?>
                        <span class="badge-confirmed d-inline-flex align-items-center gap-1" style="font-size:.68rem;padding:.15rem .45rem;" title="Alamat: <?= htmlspecialchars($b['delivery_address'] ?? '-') ?>">
                          <i class="bi bi-truck"></i>Diantar
                        </span>
                        <?php else: ?>
                        <span class="badge-pending d-inline-flex align-items-center gap-1" style="font-size:.68rem;padding:.15rem .45rem;">
                          <i class="bi bi-shop"></i>Ambil Sendiri
                        </span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="small">
                      <div class="<?= $isOverdue ? 'text-danger fw-700' : ($isDueToday ? 'text-warning fw-700' : '') ?>">
                        <?= date('d/m/Y', strtotime($b['end_date'])) ?>
                        <?php if ($isOverdue): ?>
                        <span class="d-block text-danger" style="font-size:.68rem;">⚠️ Terlambat</span>
                        <?php elseif ($isDueToday): ?>
                        <span class="d-block text-warning" style="font-size:.68rem;">⚠️ Hari Ini</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <span class="<?= $sb[$b['status']] ?? 'badge-pending' ?>" style="font-size:.72rem;">
                        <?= $sl[$b['status']] ?? ucfirst($b['status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($b['status'] === 'pending'): ?>
                      <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=diproses&csrf_token=<?= csrfToken() ?>"
                         class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:.75rem;"
                         onclick="return confirm('Proses dan siapkan gear ini?')">
                        <i class="bi bi-check me-1"></i>Proses
                      </a>
                      <?php elseif ($b['status'] === 'diproses'): ?>
                      <a href="bookings.php?id=<?= (int)$b['id'] ?>&status=sedang_sewa&csrf_token=<?= csrfToken() ?>"
                         class="btn btn-sm btn-pink py-0 px-2" style="font-size:.75rem;"
                         onclick="return confirm('Serahkan gear ke penyewa / kurir?')">
                        <i class="bi bi-send me-1"></i>Serahkan
                      </a>
                      <?php elseif ($b['status'] === 'sedang_sewa'): ?>
                      <a href="returns.php?complete=<?= (int)$b['id'] ?>&csrf_token=<?= csrfToken() ?>"
                         class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;"
                         onclick="return confirm('Terima pengembalian dan pulihkan stok?')">
                        <i class="bi bi-arrow-return-left me-1"></i>Terima
                      </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Pengembalian Hari Ini & Terlambat (col-lg-4) -->
        <div class="col-lg-4">
          <div class="d-flex flex-column gap-4">

            <!-- Card Peringatan Pengembalian Segera -->
            <div class="section-card">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title mb-0 text-danger">⚠️ Pengembalian Hari Ini</div>
                <a href="returns.php" class="btn-pink-outline btn-sm py-0 px-2" style="font-size:.75rem;">Semua</a>
              </div>
              <?php if (empty($dueReturns)): ?>
              <div class="text-center py-3 text-muted small">
                <i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>
                Tidak ada gear yang jatuh tempo hari ini atau terlambat.
              </div>
              <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($dueReturns as $dr):
                  $isLate = ($dr['end_date'] < date('Y-m-d'));
                ?>
                <div class="list-group-item px-0 py-2 border-bottom bg-transparent">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <div class="fw-700 small"><?= htmlspecialchars($dr['user_name']) ?></div>
                    <span class="badge <?= $isLate ? 'bg-danger' : 'bg-warning text-dark' ?> rounded-pill" style="font-size:.65rem;">
                      <?= $isLate ? 'Lewat Batas!' : 'Hari Ini' ?>
                    </span>
                  </div>
                  <div class="text-muted small mb-1" style="font-size:.75rem;">
                    Gear: <span class="fw-600 text-dark"><?= htmlspecialchars($dr['product_name']) ?></span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center pt-1">
                    <span class="text-muted" style="font-size:.72rem;">
                      Kembali: <?= date('d M Y', strtotime($dr['end_date'])) ?>
                    </span>
                    <div class="d-flex gap-2">
                      <?php if (!empty($dr['user_phone'])): ?>
                      <a href="<?= getWhatsAppUrl($dr['user_phone'], 'Halo ' . $dr['user_name'] . ', kami mengingatkan masa sewa gear ' . $dr['product_name'] . ' berakhir tanggal ' . date('d/m/Y', strtotime($dr['end_date']))) ?>"
                         target="_blank" class="btn btn-sm btn-outline-success py-0 px-1" title="Ingatkan via WhatsApp" style="font-size:.7rem;">
                        <i class="bi bi-whatsapp"></i> Chat
                      </a>
                      <?php endif; ?>
                      <a href="returns.php?complete=<?= (int)$dr['id'] ?>&csrf_token=<?= csrfToken() ?>"
                         class="btn btn-sm btn-pink py-0 px-2" style="font-size:.7rem;"
                         onclick="return confirm('Konfirmasi gear sudah diterima?')">
                        Terima
                      </a>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Card Status Inventaris Singkat -->
            <div class="section-card">
              <div class="section-title mb-2">📦 Kondisi Stok Gear</div>
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span class="text-muted small">Gear Siap Disewa</span>
                <span class="fw-700 text-success"><?= (int)$stats['total_gear'] ?> Produk</span>
              </div>
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span class="text-muted small">Gear Stok Kritis (≤ 2)</span>
                <span class="fw-700 <?= $stats['low_stock'] > 0 ? 'text-danger' : 'text-muted' ?>">
                  <?= (int)$stats['low_stock'] ?> Produk
                </span>
              </div>
              <div class="mt-3">
                <a href="stock.php" class="btn btn-sm btn-pink-outline w-100 d-inline-flex align-items-center justify-content-center gap-1">
                  <i class="bi bi-boxes"></i>Perbarui Stok Gear
                </a>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
