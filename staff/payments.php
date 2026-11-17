<?php
require_once '../config/config.php';
requireStaff();
$pageTitle = 'Verifikasi Pembayaran';
$active    = 'payments';
$uid       = getUserId();

// 1. Verifikasi / Tolak Seluruh Order (Satu Transaksi Penuh)
if (isset($_GET['verify_order']) || isset($_GET['reject_order'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: payments.php');
        exit;
    }
    $orderNo = trim($_GET['verify_order'] ?? $_GET['reject_order'] ?? '');
    $status  = isset($_GET['verify_order']) ? 'verified' : 'rejected';
    $reason  = trim($_GET['reason'] ?? '');

    if ($orderNo) {
        // Update semua baris pembayaran dalam order ini
        $sAllPay = $conn->prepare("
            UPDATE payments pay
            JOIN bookings b ON pay.booking_id = b.id
            SET pay.status = ?, pay.verified_by = ?, pay.verified_at = NOW()
            WHERE b.order_number = ?
        ");
        $sAllPay->bind_param('sis', $status, $uid, $orderNo);
        $sAllPay->execute();
        $sAllPay->close();

        if ($status === 'verified') {
            $sAllB = $conn->prepare("UPDATE bookings SET status = 'diproses' WHERE order_number = ? AND status = 'pending'");
            $sAllB->bind_param('s', $orderNo);
            $sAllB->execute();
            $sAllB->close();
            setFlash('success', "Pesanan #$orderNo berhasil diverifikasi (Lunas)! Status sewa kini 'Diproses'.");
        } else {
            if ($reason) {
                $appendNote = "\n[Alasan Ditolak: $reason]";
                $sNote = $conn->prepare("UPDATE bookings SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE order_number = ?");
                $sNote->bind_param('ss', $appendNote, $orderNo);
                $sNote->execute();
                $sNote->close();
            }
            setFlash('success', "Pembayaran pesanan #$orderNo ditolak.");
        }

        // Catat di log aktivitas
        $act = "Verifikasi pembayaran $orderNo: $status";
        $l = $conn->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, ?, 'payments')");
        $l->bind_param('is', $uid, $act);
        $l->execute();
        $l->close();
    }
    header('Location: payments.php');
    exit;
}

// 2. Fallback: Verifikasi / Tolak Berdasarkan ID Pembayaran Tunggal
if (isset($_GET['verify']) || isset($_GET['reject'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: payments.php');
        exit;
    }
    $id     = (int)($_GET['verify'] ?? $_GET['reject']);
    $status = isset($_GET['verify']) ? 'verified' : 'rejected';

    // Cari order_number terlebih dahulu
    $stmtOrd = $conn->prepare("SELECT order_number FROM bookings WHERE id = (SELECT booking_id FROM payments WHERE id = ?)");
    $stmtOrd->bind_param('i', $id);
    $stmtOrd->execute();
    $ordRow = $stmtOrd->get_result()->fetch_assoc();
    $stmtOrd->close();

    if ($ordRow && !empty($ordRow['order_number'])) {
        $on = $ordRow['order_number'];
        $sAllPay = $conn->prepare("UPDATE payments pay JOIN bookings b ON pay.booking_id = b.id SET pay.status=?, pay.verified_by=?, pay.verified_at=NOW() WHERE b.order_number=?");
        $sAllPay->bind_param('sis', $status, $uid, $on);
        $sAllPay->execute();
        $sAllPay->close();

        if ($status === 'verified') {
            $sAllB = $conn->prepare("UPDATE bookings SET status = 'diproses' WHERE order_number=? AND status = 'pending'");
            $sAllB->bind_param('s', $on);
            $sAllB->execute();
            $sAllB->close();
        }
        setFlash('success', "Pesanan #$on berhasil " . ($status === 'verified' ? 'diverifikasi (Lunas)' : 'ditolak') . "!");
    } else {
        $stmt = $conn->prepare("UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->bind_param('sii', $status, $uid, $id);
        $stmt->execute();
        $stmt->close();

        if ($status === 'verified') {
            $stmtBid = $conn->prepare("SELECT booking_id FROM payments WHERE id = ?");
            $stmtBid->bind_param('i', $id);
            $stmtBid->execute();
            $row = $stmtBid->get_result()->fetch_row();
            $stmtBid->close();

            if ($row) {
                $bid      = (int)$row[0];
                $stmtBook = $conn->prepare("UPDATE bookings SET status = 'diproses' WHERE id = ? AND status = 'pending'");
                $stmtBook->bind_param('i', $bid);
                $stmtBook->execute();
                $stmtBook->close();
            }
        }
        setFlash('success', 'Pembayaran berhasil ' . ($status === 'verified' ? 'diverifikasi' : 'ditolak') . '!');
    }
    header('Location: payments.php');
    exit;
}

// 3. Ambil seluruh data booking & pembayaran untuk dikelompokkan per Order
$rawPayments = $conn->query("
    SELECT b.id AS booking_id, b.order_number, b.user_id, b.start_date, b.end_date, b.total_days,
           b.total_price, b.status AS booking_status, b.notes, b.pickup_method, b.delivery_address, b.created_at,
           p.name AS product_name, p.image AS product_image, p.price_per_day,
           u.name AS user_name, u.phone AS user_phone, u.email AS user_email,
           pay.id AS payment_id, pay.amount AS payment_amount, pay.method AS payment_method,
           pay.proof_image, pay.status AS pay_status, pay.verified_at,
           v.name AS verified_by_name
    FROM bookings b
    JOIN products p ON b.product_id = p.id
    JOIN users u    ON b.user_id    = u.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    LEFT JOIN users v ON pay.verified_by = v.id
    ORDER BY b.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Pengelompokan Pesanan Induk (Order Grouping)
$allOrders = [];
foreach ($rawPayments as $r) {
    $orderKey = !empty($r['order_number'])
        ? $r['order_number']
        : ('PO-' . date('Ymd', strtotime($r['created_at'])) . '-' . str_pad($r['booking_id'], 3, '0', STR_PAD_LEFT));

    if (!isset($allOrders[$orderKey])) {
        $allOrders[$orderKey] = [
            'order_number'     => $orderKey,
            'user_id'          => $r['user_id'],
            'user_name'        => $r['user_name'],
            'user_phone'       => $r['user_phone'],
            'user_email'       => $r['user_email'],
            'created_at'       => $r['created_at'],
            'start_date'       => $r['start_date'],
            'end_date'         => $r['end_date'],
            'total_days'       => (int)$r['total_days'],
            'pickup_method'    => $r['pickup_method'] ?? 'pickup',
            'delivery_address' => $r['delivery_address'] ?? '',
            'method'           => strtolower($r['payment_method'] ?? 'bca'),
            'pay_status'       => $r['pay_status'] ?? 'pending',
            'proof_image'      => $r['proof_image'] ?? '',
            'booking_status'   => $r['booking_status'],
            'notes'            => $r['notes'] ?? '',
            'verified_by_name' => $r['verified_by_name'] ?? '',
            'verified_at'      => $r['verified_at'] ?? '',
            'grand_total'      => 0,
            'items'            => [],
            'booking_ids'      => [],
        ];
    }

    if (!empty($r['proof_image'])) {
        $allOrders[$orderKey]['proof_image'] = $r['proof_image'];
    }
    if (($r['pay_status'] ?? '') === 'verified') {
        $allOrders[$orderKey]['pay_status'] = 'verified';
    } elseif (($r['pay_status'] ?? '') === 'rejected' && $allOrders[$orderKey]['pay_status'] !== 'verified') {
        $allOrders[$orderKey]['pay_status'] = 'rejected';
    }

    $allOrders[$orderKey]['grand_total'] += (float)$r['total_price'];
    $allOrders[$orderKey]['booking_ids'][] = (int)$r['booking_id'];

    $itemPrice = (float)$r['total_price'];
    $itemBase  = round($itemPrice / 1.05);

    $allOrders[$orderKey]['items'][] = [
        'booking_id'    => $r['booking_id'],
        'product_name'  => $r['product_name'],
        'product_image' => $r['product_image'],
        'price_per_day' => (float)$r['price_per_day'],
        'item_total'    => $itemPrice,
        'item_base'     => $itemBase,
    ];
}

// 4. Tab Filter & Hitung Jumlah
$counts = [
    'needs_review' => 0,
    'all'          => count($allOrders),
    'verified'     => 0,
    'rejected'     => 0,
    'unpaid'       => 0,
];

foreach ($allOrders as $ord) {
    $hasProof = !empty($ord['proof_image']);
    $pStatus  = $ord['pay_status'];

    if ($pStatus === 'verified') {
        $counts['verified']++;
    } elseif ($pStatus === 'rejected') {
        $counts['rejected']++;
    } elseif ($hasProof && $pStatus === 'pending') {
        $counts['needs_review']++;
    } else {
        $counts['unpaid']++;
    }
}

// Tab aktif (default 'needs_review' jika ada yang perlu dicek, agar staf langsung fokus)
$tab = $_GET['tab'] ?? ($counts['needs_review'] > 0 ? 'needs_review' : 'all');
$validTabs = ['needs_review', 'all', 'verified', 'rejected', 'unpaid'];
if (!in_array($tab, $validTabs)) $tab = 'all';

// Filter data sesuai tab
$filteredOrders = array_filter($allOrders, function($ord) use ($tab) {
    $hasProof = !empty($ord['proof_image']);
    $pStatus  = $ord['pay_status'];
    return match($tab) {
        'needs_review' => ($hasProof && $pStatus === 'pending'),
        'verified'     => ($pStatus === 'verified'),
        'rejected'     => ($pStatus === 'rejected'),
        'unpaid'       => (!$hasProof && $pStatus === 'pending'),
        default        => true,
    };
});

$flash = getFlash();
include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Header & Alert Notifikasi Cepat -->
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h4 class="fw-800 mb-1" style="font-family:'Space Grotesk',sans-serif;">💳 Verifikasi Pembayaran Transaksi</h4>
          <p class="text-muted small mb-0">Cek kesesuaian struk transfer dengan total pesanan rental pelanggan secara akurat.</p>
        </div>
        <div>
          <?php if ($counts['needs_review'] > 0): ?>
          <span class="badge bg-danger fs-6 px-3 py-2 fw-700 shadow-sm animate__animated animate__pulse animate__infinite">
            <i class="bi bi-bell-fill me-1"></i><?= $counts['needs_review'] ?> Pembayaran Menunggu Verifikasi
          </span>
          <?php else: ?>
          <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 fw-700 border">
            <i class="bi bi-check2-all me-1"></i>Semua Struk Telah Diverifikasi
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Tab Filter Navigasi Cepat -->
      <div class="section-card mb-3 p-2">
        <ul class="nav nav-pills gap-1">
          <li class="nav-item">
            <a class="nav-link <?= $tab==='needs_review' ? 'active' : '' ?> fw-700 position-relative py-2 px-3"
               style="<?= $tab==='needs_review' ? 'background:var(--pink);' : 'color:var(--text-secondary);' ?>"
               href="payments.php?tab=needs_review">
              <i class="bi bi-exclamation-circle-fill me-1"></i>Perlu Dicek
              <?php if ($counts['needs_review'] > 0): ?>
              <span class="badge rounded-pill bg-white text-danger ms-1 fw-800"><?= $counts['needs_review'] ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $tab==='all' ? 'active' : '' ?> fw-600 py-2 px-3"
               style="<?= $tab==='all' ? 'background:var(--pink);' : 'color:var(--text-secondary);' ?>"
               href="payments.php?tab=all">
              Semua Transaksi (<?= $counts['all'] ?>)
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $tab==='verified' ? 'active' : '' ?> fw-600 py-2 px-3"
               style="<?= $tab==='verified' ? 'background:var(--pink);' : 'color:var(--text-secondary);' ?>"
               href="payments.php?tab=verified">
              <i class="bi bi-check-circle-fill text-success me-1"></i>Terverifikasi (<?= $counts['verified'] ?>)
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $tab==='rejected' ? 'active' : '' ?> fw-600 py-2 px-3"
               style="<?= $tab==='rejected' ? 'background:var(--pink);' : 'color:var(--text-secondary);' ?>"
               href="payments.php?tab=rejected">
              <i class="bi bi-x-circle-fill text-danger me-1"></i>Ditolak (<?= $counts['rejected'] ?>)
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $tab==='unpaid' ? 'active' : '' ?> fw-600 py-2 px-3"
               style="<?= $tab==='unpaid' ? 'background:var(--pink);' : 'color:var(--text-secondary);' ?>"
               href="payments.php?tab=unpaid">
              Belum Upload Struk (<?= $counts['unpaid'] ?>)
            </a>
          </li>
        </ul>
      </div>

      <!-- Tabel Transaksi Terpadu -->
      <div class="section-card">
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="min-width:140px;">No. Pesanan</th>
                <th style="min-width:180px;">Pelanggan & WA</th>
                <th style="min-width:240px;">Rincian Gear yang Disewa</th>
                <th style="min-width:140px;" class="text-end">Total Transfer</th>
                <th style="min-width:100px;">Metode</th>
                <th style="min-width:110px;" class="text-center">Bukti Transfer</th>
                <th style="min-width:130px;">Status</th>
                <th style="min-width:180px;" class="text-center">Aksi Verifikasi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($filteredOrders as $ord): 
                $hasProof   = !empty($ord['proof_image']);
                $pStatus    = $ord['pay_status'];
                $itemCount  = count($ord['items']);
                $cleanWA    = preg_replace('/[^0-9]/', '', $ord['user_phone']);
                if (str_starts_with($cleanWA, '0')) $cleanWA = '62' . substr($cleanWA, 1);
              ?>
              <tr class="<?= ($hasProof && $pStatus==='pending') ? 'table-warning-subtle' : '' ?>">
                <!-- Kolom 1: No Pesanan & Tanggal -->
                <td>
                  <span class="badge bg-dark fw-800 text-uppercase" style="font-size:.78rem;letter-spacing:.5px;">
                    <?= htmlspecialchars($ord['order_number']) ?>
                  </span>
                  <div class="text-muted small mt-1" style="font-size:.72rem;">
                    <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?> WIB
                  </div>
                  <span class="badge bg-secondary-subtle text-secondary small mt-1" style="font-size:.68rem;">
                    <?= $itemCount ?> Item Gear
                  </span>
                </td>

                <!-- Kolom 2: Data Pelanggan & WhatsApp -->
                <td>
                  <div class="fw-700 text-dark" style="font-size:.88rem;"><?= htmlspecialchars($ord['user_name']) ?></div>
                  <?php if (!empty($ord['user_phone'])): ?>
                  <a href="https://wa.me/<?= $cleanWA ?>?text=Halo%20Kak%20<?= urlencode($ord['user_name']) ?>,%20kami%20dari%20Pink%20Outdoor%20Garut%20mengenai%20pesanan%20<?= urlencode($ord['order_number']) ?>"
                     target="_blank" class="d-inline-flex align-items-center gap-1 text-success fw-600 text-decoration-none mt-1 small"
                     style="font-size:.76rem;" title="Hubungi Pelanggan via WhatsApp">
                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($ord['user_phone']) ?>
                  </a>
                  <?php else: ?>
                  <span class="text-muted small" style="font-size:.74rem;"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($ord['user_email']) ?></span>
                  <?php endif; ?>
                </td>

                <!-- Kolom 3: Rincian Gear dalam Transaksi -->
                <td>
                  <div class="d-flex flex-column gap-1">
                    <?php foreach ($ord['items'] as $it): ?>
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($it['product_image']) ?>"
                           width="30" height="24" style="object-fit:cover;border-radius:4px;border:1px solid var(--border);"
                           onerror="this.style.display='none'">
                      <div class="text-truncate" style="max-width:180px;font-size:.82rem;">
                        <span class="fw-600 text-dark"><?= htmlspecialchars($it['product_name']) ?></span>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="text-muted mt-1" style="font-size:.72rem;">
                    <i class="bi bi-calendar-event me-1"></i><?= date('d M', strtotime($ord['start_date'])) ?> – <?= date('d M Y', strtotime($ord['end_date'])) ?> (<?= $ord['total_days'] ?> hari)
                  </div>
                </td>

                <!-- Kolom 4: Grand Total Tagihan Sesuai Struk -->
                <td class="text-end">
                  <div class="fw-800 text-pink fs-6">
                    Rp <?= number_format($ord['grand_total'], 0, ',', '.') ?>
                  </div>
                  <small class="text-muted" style="font-size:.68rem;">(Inc. Biaya Layanan 5%)</small>
                </td>

                <!-- Kolom 5: Metode Pembayaran -->
                <td>
                  <span class="badge bg-light text-dark border text-uppercase fw-700" style="font-size:.74rem;">
                    <?= htmlspecialchars($ord['method']) ?>
                  </span>
                </td>

                <!-- Kolom 6: Thumbnail Bukti & Modal Lightbox Cepat -->
                <td class="text-center">
                  <?php if ($hasProof): ?>
                  <button type="button" class="btn p-0 border-0 position-relative" data-bs-toggle="modal" data-bs-target="#modalProof_<?= md5($ord['order_number']) ?>" title="Klik untuk perbesar bukti transfer">
                    <img src="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($ord['proof_image']) ?>"
                         width="52" height="40" style="object-fit:cover;border-radius:6px;border:1.5px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.1);">
                    <span class="position-absolute bottom-0 end-0 bg-dark text-white rounded-circle p-1 d-flex align-items-center justify-content-center" style="width:18px;height:18px;font-size:.65rem;transform:translate(25%, 25%);">
                      <i class="bi bi-zoom-in"></i>
                    </span>
                  </button>

                  <!-- Modal Preview Lightbox Cepat -->
                  <div class="modal fade" id="modalProof_<?= md5($ord['order_number']) ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                      <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
                        <div class="modal-header bg-dark text-white py-3">
                          <h6 class="modal-title fw-800 mb-0">
                            <i class="bi bi-receipt me-2 text-pink"></i>Bukti Transfer — Pesanan #<?= htmlspecialchars($ord['order_number']) ?>
                          </h6>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4 text-center bg-light">
                          <div class="row g-3 align-items-center text-start mb-3">
                            <div class="col-sm-6">
                              <div class="p-3 bg-white rounded border">
                                <small class="text-muted d-block mb-1">Pelanggan:</small>
                                <div class="fw-700 fs-6"><?= htmlspecialchars($ord['user_name']) ?></div>
                                <div class="text-muted small"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($ord['user_phone'] ?: '-') ?></div>
                              </div>
                            </div>
                            <div class="col-sm-6">
                              <div class="p-3 bg-white rounded border text-sm-end">
                                <small class="text-muted d-block mb-1">Nominal yang Harus Masuk (Pas):</small>
                                <div class="fw-800 text-pink fs-4">Rp <?= number_format($ord['grand_total'], 0, ',', '.') ?></div>
                                <span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($ord['method']) ?></span>
                              </div>
                            </div>
                          </div>

                          <div class="p-2 bg-white rounded border shadow-sm d-inline-block w-100">
                            <img src="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($ord['proof_image']) ?>"
                                 class="img-fluid rounded" style="max-height:480px;object-fit:contain;" alt="Struk Pembayaran">
                          </div>
                        </div>
                        <div class="modal-footer justify-content-between">
                          <a href="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($ord['proof_image']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Buka Gambar di Tab Penuh
                          </a>
                          <div class="d-flex gap-2">
                            <?php if ($pStatus !== 'verified'): ?>
                            <a href="payments.php?verify_order=<?= urlencode($ord['order_number']) ?>&csrf_token=<?= csrfToken() ?>"
                               class="btn btn-sm btn-success px-3 fw-700">
                              <i class="bi bi-check-lg me-1"></i>Verifikasi Lunas Sekarang
                            </a>
                            <button type="button" class="btn btn-sm btn-danger px-3 fw-700"
                                    onclick="rejectOrderWithReason('<?= htmlspecialchars($ord['order_number']) ?>', '<?= csrfToken() ?>')">
                              <i class="bi bi-x-lg me-1"></i>Tolak Bukti
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Tutup</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php else: ?>
                  <span class="badge bg-light text-muted border" style="font-size:.7rem;">Belum Upload</span>
                  <?php endif; ?>
                </td>

                <!-- Kolom 7: Status Verifikasi -->
                <td>
                  <?php if ($pStatus === 'verified'): ?>
                  <span class="badge-active d-inline-block"><i class="bi bi-check-circle-fill me-1"></i>Terverifikasi</span>
                  <div class="text-muted mt-1" style="font-size:.68rem;">Oleh: <?= htmlspecialchars($ord['verified_by_name'] ?: 'Staff') ?></div>
                  <?php elseif ($pStatus === 'rejected'): ?>
                  <span class="badge-cancelled d-inline-block"><i class="bi bi-x-circle-fill me-1"></i>Ditolak</span>
                  <?php elseif ($hasProof): ?>
                  <span class="badge-pending d-inline-block"><i class="bi bi-hourglass-split me-1"></i>Perlu Dicek</span>
                  <?php else: ?>
                  <span class="badge-pending d-inline-block"><i class="bi bi-clock me-1"></i>Belum Bayar</span>
                  <?php endif; ?>
                </td>

                <!-- Kolom 8: Aksi Verifikasi Tunggal Seluruh Order -->
                <td class="text-center">
                  <div class="d-flex align-items-center justify-content-center gap-1 flex-wrap">
                    <?php if ($pStatus !== 'verified'): ?>
                    <a href="payments.php?verify_order=<?= urlencode($ord['order_number']) ?>&csrf_token=<?= csrfToken() ?>"
                       class="btn btn-sm btn-success py-1 px-2 fw-700 d-inline-flex align-items-center gap-1"
                       style="font-size:.76rem;"
                       onclick="return confirm('Verifikasi LUNAS pesanan #<?= htmlspecialchars($ord['order_number']) ?> sebesar Rp <?= number_format($ord['grand_total'],0,',','.') ?>?')">
                      <i class="bi bi-check-lg"></i>Verifikasi
                    </a>
                    <button type="button" class="btn btn-sm btn-danger py-1 px-2 fw-700 d-inline-flex align-items-center gap-1"
                            style="font-size:.76rem;"
                            onclick="rejectOrderWithReason('<?= htmlspecialchars($ord['order_number']) ?>', '<?= csrfToken() ?>')">
                      <i class="bi bi-x-lg"></i>Tolak
                    </button>
                    <?php endif; ?>

                    <a href="../user/nota.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank"
                       class="btn btn-sm btn-outline-secondary py-1 px-2 fw-600"
                       style="font-size:.76rem;" title="Lihat Nota Transaksi">
                      <i class="bi bi-receipt"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($filteredOrders)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                  Tidak ada data pembayaran pada kategori ini.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../config/partials/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Prompt alasan tolak sebelum melakukan reject order
function rejectOrderWithReason(orderNo, csrf) {
  Swal.fire({
    title: 'Tolak Pembayaran?',
    text: 'Masukkan alasan penolakan agar pelanggan mengetahui kendala bukti transfernya:',
    input: 'select',
    inputOptions: {
      'Nominal transfer tidak sesuai / kurang': 'Nominal transfer tidak sesuai / kurang',
      'Bukti transfer buram / tidak terbaca': 'Bukti transfer buram / tidak terbaca',
      'Transfer bukan ke rekening resmi Pink Outdoor': 'Transfer bukan ke rekening resmi Pink Outdoor',
      'Struk terindikasi palsu / duplikasi': 'Struk terindikasi palsu / duplikasi',
      'Lainnya': 'Lainnya'
    },
    inputPlaceholder: 'Pilih alasan penolakan...',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Tolak Pembayaran',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value) {
        return 'Pilih salah satu alasan penolakan!';
      }
    }
  }).then((res) => {
    if (res.isConfirmed && res.value) {
      window.location.href = 'payments.php?reject_order=' + encodeURIComponent(orderNo) +
                             '&reason=' + encodeURIComponent(res.value) +
                             '&csrf_token=' + csrf;
    }
  });
}

<?php if ($flash): ?>
Swal.fire({
  icon: '<?= $flash['type'] ?>',
  title: '<?= addslashes(htmlspecialchars($flash['msg'])) ?>',
  timer: 3000,
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timerProgressBar: true
});
<?php endif; ?>
</script>