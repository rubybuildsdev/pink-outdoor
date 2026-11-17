<?php
require_once '../config/config.php';
requireLogin();
$uid = getUserId();

// Batalkan pesanan per order_number atau per booking_id
if (isset($_GET['cancel']) && isLoggedIn()) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: bookings.php');
        exit;
    }
    $target = trim($_GET['cancel']);

    // Cek apakah cancel berdasarkan order_number atau ID angka
    if (str_starts_with($target, 'PO-')) {
        $chk = $conn->prepare("SELECT id, status, product_id FROM bookings WHERE order_number=? AND user_id=?");
        $chk->bind_param('si', $target, $uid);
    } else {
        $bid = (int)$target;
        $chk = $conn->prepare("SELECT id, status, product_id FROM bookings WHERE id=? AND user_id=?");
        $chk->bind_param('ii', $bid, $uid);
    }
    $chk->execute();
    $rows = $chk->get_result()->fetch_all(MYSQLI_ASSOC);
    $chk->close();

    if (!empty($rows)) {
        $canCancelAll = true;
        foreach ($rows as $r) {
            if (!in_array($r['status'], ['pending', 'diproses'])) {
                $canCancelAll = false;
                break;
            }
        }

        if ($canCancelAll) {
            foreach ($rows as $r) {
                $stmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
                $stmt->bind_param('i', $r['id']);
                $stmt->execute();
                $stmt->close();

                // Kembalikan stok
                $stmtP = $conn->prepare("UPDATE products SET stock = stock + 1 WHERE id = ?");
                $stmtP->bind_param('i', $r['product_id']);
                $stmtP->execute();
                $stmtP->close();
            }
            setFlash('success', 'Pesanan rental #' . htmlspecialchars($target) . ' berhasil dibatalkan.');
        } else {
            setFlash('error', 'Pesanan tidak dapat dibatalkan karena sudah dalam status sewa atau selesai.');
        }
    } else {
        setFlash('error', 'Pesanan tidak ditemukan.');
    }
    header('Location: bookings.php');
    exit;
}

// Upload bukti pembayaran per order_number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof'])) {
    requireCsrf();
    $orderNumber = trim($_POST['order_number'] ?? '');
    $bookingId   = (int)($_POST['booking_id'] ?? 0);
    $file        = $_FILES['proof'];

    // Ambil order_number jika hanya booking_id yang dikirim
    if (!$orderNumber && $bookingId > 0) {
        $getOrd = $conn->prepare("SELECT order_number FROM bookings WHERE id=? AND user_id=?");
        $getOrd->bind_param('ii', $bookingId, $uid);
        $getOrd->execute();
        $ordRow = $getOrd->get_result()->fetch_assoc();
        $getOrd->close();
        if ($ordRow) {
            $orderNumber = $ordRow['order_number'];
        }
    }

    if (!$orderNumber) {
        setFlash('error', 'Nomor pesanan tidak valid.');
        header('Location: bookings.php');
        exit;
    }

    // Pastikan pesanan benar milik user
    $chk = $conn->prepare("SELECT id FROM bookings WHERE order_number=? AND user_id=? LIMIT 1");
    $chk->bind_param('si', $orderNumber, $uid);
    $chk->execute();
    $bRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$bRow) {
        setFlash('error', 'Data pesanan tidak ditemukan.');
        header('Location: bookings.php');
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/payments/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0 && $file['size'] <= 4 * 1024 * 1024) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
            $cleanCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $orderNumber);
            $fname = 'pay_' . $uid . '_' . $cleanCode . '_' . time() . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
                // Update bukti transfer ke SEMUA pembayaran yang tergabung dalam order_number ini
                $s = $conn->prepare("
                    UPDATE payments pay
                    JOIN bookings b ON pay.booking_id = b.id
                    SET pay.proof_image = ?, pay.status = 'pending'
                    WHERE b.user_id = ? AND b.order_number = ?
                ");
                $s->bind_param('sis', $fname, $uid, $orderNumber);
                $s->execute();
                $affected = $s->affected_rows;
                $s->close();

                setFlash('success', 'Bukti pembayaran berhasil diupload! Tim staff akan segera memverifikasi pesanan Anda.');
            } else {
                setFlash('error', 'Gagal memindahkan file upload ke server.');
            }
        } else {
            setFlash('error', 'Format file tidak didukung. Harap upload gambar JPG, PNG, WEBP, atau dokumen PDF.');
        }
    } else {
        setFlash('error', 'File bukti transfer tidak valid atau ukuran melebihi batas maksimal 4MB.');
    }
    header('Location: bookings.php');
    exit;
}

// Tab filter
$tab     = $_GET['tab'] ?? 'all';
$allowed = ['all','active','selesai','cancelled'];
if (!in_array($tab, $allowed)) $tab = 'all';

$where = match($tab) {
    'active'    => "AND b.status IN ('pending','diproses','sedang_sewa')",
    'selesai'   => "AND b.status = 'selesai'",
    'cancelled' => "AND b.status = 'cancelled'",
    default     => '',
};

// Ambil semua item rental user
$sql = "SELECT b.*, p.name AS product_name, p.image AS product_image, p.price_per_day,
               pay.id AS payment_id, pay.method, pay.status AS pay_status, pay.proof_image, pay.amount AS pay_amount
        FROM bookings b
        JOIN products p ON b.product_id = p.id
        LEFT JOIN payments pay ON pay.booking_id = b.id AND pay.user_id = ?
        WHERE b.user_id = ? $where
        ORDER BY b.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $uid, $uid);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Grouping per Order / Transaksi
$orders = [];
foreach ($bookings as $b) {
    $orderKey = !empty($b['order_number'])
        ? $b['order_number']
        : ('PO-' . date('Ymd', strtotime($b['created_at'])) . '-' . str_pad($b['id'], 3, '0', STR_PAD_LEFT));

    if (!isset($orders[$orderKey])) {
        $orders[$orderKey] = [
            'order_number'     => $orderKey,
            'user_id'          => $b['user_id'],
            'created_at'       => $b['created_at'],
            'start_date'       => $b['start_date'],
            'end_date'         => $b['end_date'],
            'total_days'       => (int)$b['total_days'],
            'pickup_method'    => $b['pickup_method'] ?? 'pickup',
            'delivery_address' => $b['delivery_address'] ?? '',
            'method'           => strtolower($b['method'] ?? 'bca'),
            'pay_status'       => $b['pay_status'] ?? 'pending',
            'proof_image'      => $b['proof_image'] ?? '',
            'status'           => $b['status'],
            'notes'            => $b['notes'],
            'items'            => [],
            'booking_ids'      => [],
            'grand_total'      => 0,
            'subtotal'         => 0,
            'fee'              => 0,
        ];
    }

    if (!empty($b['proof_image'])) {
        $orders[$orderKey]['proof_image'] = $b['proof_image'];
    }
    if (($b['pay_status'] ?? '') === 'verified') {
        $orders[$orderKey]['pay_status'] = 'verified';
    } elseif (($b['pay_status'] ?? '') === 'rejected' && $orders[$orderKey]['pay_status'] !== 'verified') {
        $orders[$orderKey]['pay_status'] = 'rejected';
    }

    $orders[$orderKey]['booking_ids'][] = (int)$b['id'];
    $itemPrice = (float)$b['total_price'];
    $orders[$orderKey]['grand_total'] += $itemPrice;

    $itemPureBase = round($itemPrice / 1.05);
    $orders[$orderKey]['subtotal'] += $itemPureBase;

    $orders[$orderKey]['items'][] = [
        'booking_id'    => $b['id'],
        'product_id'    => $b['product_id'],
        'product_name'  => $b['product_name'],
        'product_image' => $b['product_image'],
        'price_per_day' => (float)$b['price_per_day'],
        'item_total'    => $itemPrice,
        'item_base'     => $itemPureBase,
        'status'        => $b['status'],
        'notes'         => $b['notes'],
    ];
}

// Hitung fee & canCancel per order
foreach ($orders as &$ord) {
    $ord['fee'] = $ord['grand_total'] - $ord['subtotal'];
    $ord['can_cancel'] = in_array($ord['status'], ['pending', 'diproses']) && $ord['pay_status'] !== 'verified';
}
unset($ord);

// Hitung jumlah per tab (berdasarkan order unik)
$counts = ['all'=>0,'active'=>0,'selesai'=>0,'cancelled'=>0];
$allB = $conn->prepare("SELECT order_number, status, id, created_at FROM bookings WHERE user_id=? ORDER BY id DESC");
$allB->bind_param('i', $uid);
$allB->execute();
$allRows = $allB->get_result()->fetch_all(MYSQLI_ASSOC);
$allB->close();

$seenOrders = [];
foreach ($allRows as $r) {
    $k = !empty($r['order_number']) ? $r['order_number'] : ('PO-' . date('Ymd', strtotime($r['created_at'])) . '-' . str_pad($r['id'], 3, '0', STR_PAD_LEFT));
    if (!isset($seenOrders[$k])) {
        $seenOrders[$k] = true;
        $counts['all']++;
        if (in_array($r['status'], ['pending','diproses','sedang_sewa'])) $counts['active']++;
        if ($r['status'] === 'selesai')   $counts['selesai']++;
        if ($r['status'] === 'cancelled') $counts['cancelled']++;
    }
}

$statusLabel = [
    'pending'     => 'Menunggu Konfirmasi',
    'diproses'    => 'Disiapkan Staff',
    'sedang_sewa' => 'Sedang Disewa',
    'selesai'     => 'Selesai',
    'cancelled'   => 'Dibatalkan',
];

$statusBadge = [
    'pending'     => 'badge-pending',
    'diproses'    => 'badge-confirmed',
    'sedang_sewa' => 'badge-active',
    'selesai'     => 'badge-completed',
    'cancelled'   => 'badge-cancelled',
];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat & Status Booking — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
  <style>
    .instruction-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .order-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0,0,0,.04);
      transition: border-color .15s ease;
      overflow: hidden;
    }
    .order-card-header {
      background: #fafbfc;
      border-bottom: 1px solid var(--border);
      padding: 14px 20px;
    }
    .order-card-body {
      padding: 20px;
    }
    .gear-item-row {
      padding: 10px 0;
      border-bottom: 1px dashed var(--border);
    }
    .gear-item-row:last-child {
      border-bottom: none;
    }
    .pay-info-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: var(--radius);
      padding: 16px;
    }
    .copy-chip-sm {
      font-family: monospace;
      font-weight: 700;
      color: var(--text-primary);
      background: #fff;
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid var(--border);
      font-size: .95rem;
    }
  </style>
</head>
<body>
<?php include '../config/partials/navbar_user.php'; ?>

<div class="container py-4" style="max-width:960px;">
  <!-- Header Title -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
      <h3 class="fw-800 mb-1" style="font-family:'Space Grotesk',sans-serif;">📋 Riwayat & Status Booking</h3>
      <p class="text-muted small mb-0">Pantau verifikasi pembayaran satu transaksi, persiapan unit gear, hingga serah-terima alat.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= BASE_URL ?>katalog.php" class="btn-pink btn-sm py-2 px-3 fw-700">
        <i class="bi bi-plus-lg me-1"></i>Sewa Gear Lain
      </a>
    </div>
  </div>

  <!-- Panduan Basecamp & Syarat Identitas -->
  <div class="instruction-card p-4 mb-4">
    <div class="row g-3 align-items-center">
      <div class="col-md-7 border-end-md pe-md-4">
        <div class="d-flex align-items-start gap-3">
          <div style="width:40px;height:40px;border-radius:10px;background:var(--pink-subtle);color:var(--pink);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <div>
            <h6 class="fw-800 mb-1">Syarat Pengambilan & Jaminan Identitas</h6>
            <p class="text-muted small mb-2" style="line-height:1.45;">
              Wajib membawa <strong>1 identitas asli (KTP / SIM / KTM)</strong> yang masih berlaku atas nama pemesan untuk dititipkan saat serah-terima alat di basecamp.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge" style="background:var(--surface-2);color:var(--text-secondary);border:1px solid var(--border);font-size:.72rem;">
                <i class="bi bi-check-circle-fill text-success me-1"></i>Alat Bersih & Teruji
              </span>
              <span class="badge" style="background:var(--surface-2);color:var(--text-secondary);border:1px solid var(--border);font-size:.72rem;">
                <i class="bi bi-clock-history text-pink me-1"></i>Buka 07.00 – 21.00 WIB
              </span>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-5 ps-md-4">
        <h6 class="fw-800 mb-1" style="font-size:.86rem;"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Lokasi Basecamp Garut</h6>
        <div class="text-muted small mb-3" style="font-size:.78rem;">
          <?= BASECAMP_ADDRESS ?>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= MAPS_LINK ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2 fw-600" style="font-size:.76rem;">
            <i class="bi bi-map me-1"></i>Petunjuk Maps
          </a>
          <a href="<?= STORE_WA_LINK ?>" target="_blank" class="btn btn-sm btn-success py-1 px-3 fw-600" style="font-size:.76rem;">
            <i class="bi bi-whatsapp me-1"></i>Chat CS Toko
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab Filter Pesanan -->
  <div style="border-bottom:2px solid var(--border);" class="mb-4">
    <div class="d-flex gap-1 flex-wrap">
      <?php
      $tabDefs = [
        'all'       => ['label' => 'Semua Pesanan', 'icon' => 'bi-grid'],
        'active'    => ['label' => 'Sedang Berjalan', 'icon' => 'bi-hourglass-split'],
        'selesai'   => ['label' => 'Selesai', 'icon' => 'bi-check2-circle'],
        'cancelled' => ['label' => 'Dibatalkan', 'icon' => 'bi-x-circle'],
      ];
      foreach ($tabDefs as $k => $item):
        $isActive = ($tab === $k);
      ?>
      <a href="bookings.php?tab=<?= $k ?>"
         class="px-3 py-2 d-inline-flex align-items-center gap-2 fw-600 text-decoration-none"
         style="color:<?= $isActive ? 'var(--pink)' : 'var(--text-secondary)' ?>;border-bottom:2.5px solid <?= $isActive ? 'var(--pink)' : 'transparent' ?>;margin-bottom:-2px;font-size:.88rem;">
        <i class="bi <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($counts[$k] > 0): ?>
        <span class="badge rounded-pill" style="background:<?= $isActive ? 'var(--pink)' : 'var(--border)' ?>;color:<?= $isActive ? '#fff' : 'var(--text-secondary)' ?>;font-size:.65rem;padding:.2rem .5rem;">
          <?= $counts[$k] ?>
        </span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Konten Daftar Order Rental Terpadu -->
  <?php if (empty($orders)): ?>
  <div class="section-card text-center py-5">
    <div style="font-size:3.5rem;">⛺</div>
    <h5 class="fw-700 mt-3 mb-1">Belum Ada Pesanan<?= $tab !== 'all' ? ' di Tab Ini' : '' ?></h5>
    <p class="text-muted small mb-3">Siapkan petualanganmu dengan gear outdoor berkualitas dan terawat.</p>
    <a href="<?= BASE_URL ?>katalog.php" class="btn-pink px-4 py-2 d-inline-block fw-700">Mulai Eksplorasi Katalog</a>
  </div>

  <?php else: ?>
  <div class="d-flex flex-column gap-4">
    <?php
    foreach ($orders as $ord):
      $methodKey   = $ord['method'];
      $channelInfo = PAYMENT_CHANNELS[$methodKey] ?? PAYMENT_CHANNELS['bca'];
      $bStatus     = $ord['status'];
      $payStatus   = $ord['pay_status'];
      $proofImage  = $ord['proof_image'];
      $canCancel   = $ord['can_cancel'];
      $itemCount   = count($ord['items']);
    ?>
    <div class="order-card">
      <!-- Order Header Bar -->
      <div class="order-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge bg-dark fw-800" style="font-size:.78rem;letter-spacing:.5px;">
            <?= htmlspecialchars($ord['order_number']) ?>
          </span>
          <span class="text-muted small">
            Dipesan pada <?= date('d M Y, H:i', strtotime($ord['created_at'])) ?> WIB
          </span>
          <span class="badge bg-secondary-subtle text-secondary small fw-600">
            <?= $itemCount ?> Barang Sewa
          </span>
        </div>
        <div>
          <span class="<?= $statusBadge[$bStatus] ?? 'badge-pending' ?>">
            <?= $statusLabel[$bStatus] ?? ucfirst($bStatus) ?>
          </span>
        </div>
      </div>

      <!-- Order Body -->
      <div class="order-card-body">
        <div class="row g-4">
          <!-- Kolom Kiri: Rincian Barang & Pengambilan -->
          <div class="col-lg-7">
            <!-- Periode Sewa & Pengambilan -->
            <div class="mb-3 p-3 rounded" style="background:var(--surface-2);border:1px solid var(--border);font-size:.84rem;">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2 border-bottom" style="border-color:var(--border)!important;">
                <div>
                  <i class="bi bi-calendar3 me-1 text-pink"></i>
                  Periode: <strong><?= date('d M Y', strtotime($ord['start_date'])) ?></strong> s/d <strong><?= date('d M Y', strtotime($ord['end_date'])) ?></strong>
                  <span class="text-pink fw-700">(<?= $ord['total_days'] ?> Hari Sewa)</span>
                </div>
                <div>
                  <?php if ($ord['pickup_method'] === 'delivery'): ?>
                  <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">
                    <i class="bi bi-truck me-1"></i>Diantarkan ke Lokasi
                  </span>
                  <?php else: ?>
                  <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;">
                    <i class="bi bi-shop me-1"></i>Ambil di Basecamp Garut
                  </span>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($ord['pickup_method'] === 'delivery' && !empty($ord['delivery_address'])): ?>
              <div class="small text-muted mb-1">
                <i class="bi bi-geo-alt me-1 text-danger"></i>Alamat Pengantaran: <?= htmlspecialchars($ord['delivery_address']) ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($ord['notes'])): ?>
              <div class="small text-muted">
                <i class="bi bi-chat-text me-1 text-primary"></i>Catatan: <?= htmlspecialchars($ord['notes']) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Daftar Item Gear dalam Pesanan Ini -->
            <div class="mb-3">
              <div class="fw-700 small text-muted text-uppercase mb-2" style="font-size:.74rem;letter-spacing:.5px;">
                Daftar Gear Outdoor
              </div>
              <?php foreach ($ord['items'] as $item): ?>
              <div class="gear-item-row d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                  <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($item['product_image']) ?>"
                       width="54" height="42" style="object-fit:cover;border-radius:6px;border:1px solid var(--border);"
                       onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.svg'">
                  <div>
                    <div class="fw-700" style="font-size:.88rem;color:var(--text-primary);"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="text-muted small" style="font-size:.76rem;">
                      Rp <?= number_format($item['price_per_day'], 0, ',', '.') ?>/hari × <?= $ord['total_days'] ?> hari
                    </div>
                  </div>
                </div>
                <div class="fw-700 text-dark" style="font-size:.9rem;white-space:nowrap;">
                  Rp <?= number_format($item['item_base'], 0, ',', '.') ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Rincian Biaya -->
            <div class="p-3 rounded" style="background:#fafbfc;border:1px solid var(--border);font-size:.84rem;">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Subtotal Sewa (<?= $itemCount ?> item)</span>
                <span class="fw-600">Rp <?= number_format($ord['subtotal'], 0, ',', '.') ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Biaya Layanan & Perawatan (5%)</span>
                <span class="fw-600">Rp <?= number_format($ord['fee'], 0, ',', '.') ?></span>
              </div>
              <hr class="my-2" style="border-color:var(--border);">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-800 fs-6">Total Pembayaran Transaksi</span>
                <span class="fw-800 text-pink fs-4">Rp <?= number_format($ord['grand_total'], 0, ',', '.') ?></span>
              </div>
            </div>
          </div>

          <!-- Kolom Kanan: Pembayaran & Upload Bukti Tunggal -->
          <div class="col-lg-5">
            <div class="pay-info-box">
              <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom" style="border-color:var(--border)!important;">
                <div class="fw-700 small">
                  <i class="bi bi-credit-card me-1 text-pink"></i>Status Pembayaran
                </div>
                <div>
                  <?php if ($payStatus === 'verified'): ?>
                  <span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Lunas / Terverifikasi</span>
                  <?php elseif ($payStatus === 'rejected'): ?>
                  <span class="badge-cancelled"><i class="bi bi-x-circle-fill me-1"></i>Bukti Ditolak</span>
                  <?php elseif ($proofImage): ?>
                  <span class="badge-pending"><i class="bi bi-hourglass-split me-1"></i>Menunggu Verifikasi Staff</span>
                  <?php else: ?>
                  <span class="badge-pending"><i class="bi bi-clock me-1"></i>Belum Bayar (Menunggu Transfer)</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Rekening Tujuan Transfer -->
              <div class="mb-3">
                <div class="text-muted small" style="font-size:.78rem;">Transfer ke Rekening Resmi Toko:</div>
                <div class="fw-700 text-dark" style="font-size:.88rem;"><?= htmlspecialchars($channelInfo['name']) ?></div>
                <div class="text-muted small" style="font-size:.76rem;">a.n. <strong><?= htmlspecialchars($channelInfo['holder_name']) ?></strong></div>

                <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                  <span class="copy-chip-sm flex-grow-1 text-center" id="acc-no-<?= md5($ord['order_number']) ?>"><?= $channelInfo['account_no'] ?></span>
                  <button type="button" class="btn btn-sm btn-outline-pink py-1 px-2" style="font-size:.76rem;"
                          onclick="copyText('<?= $channelInfo['account_no'] ?>', this)">
                    <i class="bi bi-copy me-1"></i>Salin
                  </button>
                </div>

                <div class="mt-2 p-2 rounded text-center" style="background:#fff;border:1px dashed var(--pink);">
                  <div class="text-muted small" style="font-size:.72rem;">Nominal Pas yang Harus Ditransfer:</div>
                  <div class="fw-800 text-pink fs-5">Rp <?= number_format($ord['grand_total'], 0, ',', '.') ?></div>
                </div>
              </div>

              <!-- Bukti Transfer Preview jika sudah ada -->
              <?php if ($proofImage): ?>
              <div class="p-2 rounded mb-3"
                   style="background:<?= $payStatus==='verified'?'rgba(34,197,94,.06)':($payStatus==='rejected'?'rgba(239,68,68,.06)':'rgba(234,179,8,.06)') ?>;border:1px solid <?= $payStatus==='verified'?'rgba(34,197,94,.2)':($payStatus==='rejected'?'rgba(239,68,68,.2)':'rgba(234,179,8,.2)') ?>;">
                <div class="d-flex align-items-center gap-2">
                  <img src="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($proofImage) ?>"
                       width="46" height="38" style="object-fit:cover;border-radius:4px;border:1px solid var(--border);"
                       onerror="this.style.display='none'">
                  <div class="flex-grow-1">
                    <div class="fw-700" style="font-size:.78rem;color:var(--text-primary);">Bukti Transfer Terlampir</div>
                    <a href="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($proofImage) ?>" target="_blank" class="text-pink fw-600" style="font-size:.74rem;">
                      <i class="bi bi-file-earmark-image me-1"></i>Lihat File Bukti Transfer →
                    </a>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Form Upload Bukti Transfer Tunggal -->
              <?php if (!$proofImage || $payStatus === 'rejected'): ?>
              <?php if ($payStatus === 'rejected'): ?>
              <div class="alert alert-danger py-1 px-2 mb-2" style="font-size:.74rem;border-radius:6px;">
                <i class="bi bi-exclamation-triangle me-1"></i>Bukti pembayaran sebelumnya ditolak. Harap unggah foto struk transfer yang jelas.
              </div>
              <?php endif; ?>

              <form method="POST" enctype="multipart/form-data" class="mt-2">
                <?= csrfField() ?>
                <input type="hidden" name="order_number" value="<?= htmlspecialchars($ord['order_number']) ?>">

                <label class="form-label small fw-700 mb-1" style="font-size:.78rem;">Unggah Bukti Transfer (Struk/Screenshot):</label>
                <input type="file" name="proof" class="form-control form-control-sm mb-2"
                       accept="image/jpeg,image/png,image/webp,application/pdf" required>

                <button type="submit" class="btn btn-pink btn-sm w-100 py-2 fw-700 shadow-sm">
                  <i class="bi bi-upload me-1"></i><?= $payStatus === 'rejected' ? 'Upload Ulang Bukti Transfer' : 'Kirim Bukti Pembayaran' ?>
                </button>
              </form>
              <?php endif; ?>

              <!-- Tombol Nota Digital & CS -->
              <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color:var(--border)!important;">
                <a href="nota.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary py-1 px-2 fw-600" style="font-size:.76rem;" title="Cetak Nota Resmi">
                  <i class="bi bi-receipt text-pink me-1"></i>Nota Sewa Digital
                </a>
                <a href="https://wa.me/6281234567890?text=Halo%20Pink%20Outdoor,%20saya%20ingin%20tanya%20mengenai%20pesanan%20<?= urlencode($ord['order_number']) ?>"
                   target="_blank"
                   class="btn btn-sm btn-light border py-1 px-2 text-muted fw-600" style="font-size:.76rem;">
                  <i class="bi bi-whatsapp text-success me-1"></i>Tanya CS
                </a>
              </div>

              <!-- Tombol Batalkan Pesanan -->
              <?php if ($canCancel): ?>
              <div class="mt-2 text-center">
                <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none py-0" style="font-size:.75rem;"
                        onclick="confirmCancel('<?= htmlspecialchars($ord['order_number']) ?>', '<?= csrfToken() ?>')">
                  <i class="bi bi-x-circle me-1"></i>Batalkan Pesanan Ini
                </button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Fungsi salin nomor rekening
function copyText(str, btn) {
  navigator.clipboard.writeText(str).then(() => {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg text-success me-1"></i>Tersalin!';
    setTimeout(() => {
      btn.innerHTML = originalHtml;
    }, 2000);
  });
}

// Dialog konfirmasi pembatalan pesanan dengan SweetAlert2
function confirmCancel(orderNo, csrf) {
  Swal.fire({
    title: 'Batalkan Pesanan Rental?',
    text: 'Pesanan #' + orderNo + ' beserta seluruh item gear di dalamnya akan dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Ya, Batalkan Pesanan',
    cancelButtonText: 'Tutup'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'bookings.php?cancel=' + encodeURIComponent(orderNo) + '&csrf_token=' + csrf;
    }
  });
}

// Flash toast notification
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
</body>
</html>