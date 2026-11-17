<?php
require_once '../config/config.php';
requireLogin();

$uid     = getUserId();
$orderNo = trim($_GET['order'] ?? '');
$bid     = (int)($_GET['id'] ?? 0);

if (!$orderNo && $bid <= 0) {
    setFlash('error', 'Nomor nota / pesanan tidak valid.');
    header('Location: bookings.php');
    exit;
}

// Cari data berdasarkan order_number atau booking_id
if (!$orderNo && $bid > 0) {
    $sOrder = $conn->prepare("SELECT order_number FROM bookings WHERE id = ?");
    $sOrder->bind_param('i', $bid);
    $sOrder->execute();
    $rowOrd = $sOrder->get_result()->fetch_assoc();
    $sOrder->close();
    if ($rowOrd && !empty($rowOrd['order_number'])) {
        $orderNo = $rowOrd['order_number'];
    }
}

if ($orderNo) {
    $stmt = $conn->prepare("
        SELECT b.*, 
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               p.name AS product_name, p.price_per_day, p.image AS product_image,
               c.name AS category_name,
               pay.amount AS pay_amount, pay.method AS pay_method, pay.status AS pay_status, pay.created_at AS pay_date
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN products p ON b.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN payments pay ON pay.booking_id = b.id
        WHERE b.order_number = ?
        ORDER BY b.id ASC
    ");
    $stmt->bind_param('s', $orderNo);
} else {
    $stmt = $conn->prepare("
        SELECT b.*, 
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               p.name AS product_name, p.price_per_day, p.image AS product_image,
               c.name AS category_name,
               pay.amount AS pay_amount, pay.method AS pay_method, pay.status AS pay_status, pay.created_at AS pay_date
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN products p ON b.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN payments pay ON pay.booking_id = b.id
        WHERE b.id = ?
        ORDER BY b.id ASC
    ");
    $stmt->bind_param('i', $bid);
}

$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    setFlash('error', 'Data pesanan tidak ditemukan.');
    header('Location: bookings.php');
    exit;
}

$first = $items[0];

// Otorisasi: hanya pemilik booking ATAU admin/staff yang boleh membuka
if ($first['user_id'] != $uid && !isAdmin() && !isStaff()) {
    setFlash('error', 'Anda tidak memiliki akses ke nota ini.');
    header('Location: bookings.php');
    exit;
}

// Nomor Nota Resmi
$invoiceNo = !empty($first['order_number'])
    ? $first['order_number']
    : ('INV/PO/' . date('Ymd', strtotime($first['created_at'])) . '/' . str_pad($first['id'], 4, '0', STR_PAD_LEFT));

// Perhitungan biaya total akumulasi seluruh item dalam order
$totalPrice = 0;
$basePrice  = 0;

foreach ($items as $it) {
    $itemTotal = (float)$it['total_price'];
    $itemBase  = round($itemTotal / 1.05);
    $totalPrice += $itemTotal;
    $basePrice  += $itemBase;
}
$serviceFee = $totalPrice - $basePrice;

$statusBadge = match($first['status']) {
    'sedang_sewa' => 'badge-success',
    'selesai'     => 'badge-primary',
    'diproses'    => 'badge-info',
    'pending'     => 'badge-warning',
    default       => 'badge-secondary',
};

$statusLabel = match($first['status']) {
    'pending'     => 'Menunggu Konfirmasi',
    'diproses'    => 'Pesanan Dikonfirmasi (Disiapkan)',
    'sedang_sewa' => 'Sedang Berjalan (Disewa)',
    'selesai'     => 'Selesai & Dikembalikan',
    'cancelled'   => 'Dibatalkan',
    default       => ucfirst($first['status']),
};

$payStatusLabel = match($first['pay_status'] ?? '') {
    'verified' => 'LUNAS (Terverifikasi)',
    'rejected' => 'DITOLAK',
    default    => 'MENUNGGU VERIFIKASI',
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nota #<?= htmlspecialchars($invoiceNo) ?> — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
      background: #f8fafc;
      color: #0f172a;
      padding: 30px 15px;
    }
    .nota-card {
      max-width: 840px;
      margin: 0 auto;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.06);
      padding: 40px;
      border: 1px solid #ebebeb;
      position: relative;
    }
    .brand-title {
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: #0f172a;
    }
    .brand-title span {
      color: #e11d48;
    }
    .table-nota th {
      background: #fafafa;
      font-weight: 700;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #555;
      border-top: 1px solid #eee;
      border-bottom: 2px solid #ddd;
    }
    .table-nota td {
      font-size: 0.9rem;
      vertical-align: middle;
      border-bottom: 1px solid #eee;
      padding: 12px 10px;
    }
    .badge-status {
      padding: 4px 10px;
      font-size: 0.75rem;
      font-weight: 700;
      border-radius: 50px;
      display: inline-block;
    }
    .badge-lunas { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .badge-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    .stamp {
      border: 2px dashed #e11d48;
      color: #e11d48;
      font-weight: 800;
      font-size: 0.9rem;
      padding: 8px 18px;
      border-radius: 8px;
      display: inline-block;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      transform: rotate(-3deg);
    }
    @media print {
      body {
        background: #fff !important;
        padding: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .nota-card {
        box-shadow: none !important;
        border: none !important;
        padding: 10px !important;
        max-width: 100% !important;
      }
    }
  </style>
</head>
<body>

<!-- Tombol Aksi di Atas (Disembunyikan saat Print) -->
<div class="no-print text-center mb-4">
  <div class="d-inline-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-700 shadow-sm" style="background:#e11d48;border-color:#e11d48;border-radius:8px;">
      <i class="bi bi-printer me-2"></i>Cetak Nota / Simpan PDF
    </button>
    <a href="bookings.php" class="btn btn-outline-secondary px-3 py-2 fw-600" style="border-radius:8px;">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Booking
    </a>
  </div>
</div>

<div class="nota-card">
  <!-- Header Nota -->
  <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4 flex-wrap gap-3">
    <div>
      <div class="brand-title mb-1"><span>PINK</span> OUTDOOR</div>
      <div class="text-muted small" style="line-height:1.4;">
        Penyewaan Perlengkapan Camping & Outdoor Adventure<br>
        <?= BASECAMP_ADDRESS ?><br>
        WhatsApp: <strong><?= STORE_PHONE ?></strong> · Jam Operasional: <?= BASECAMP_HOURS ?>
      </div>
    </div>
    <div class="text-end">
      <div class="fw-800 text-uppercase" style="letter-spacing:1px;color:#e11d48;font-size:1.15rem;">BUKTI SEWA GEAR</div>
      <div class="fw-700 text-dark" style="font-size:.95rem;"><?= htmlspecialchars($invoiceNo) ?></div>
      <div class="text-muted small">Tgl Pemesanan: <?= date('d/m/Y H:i', strtotime($first['created_at'])) ?> WIB</div>
      <div class="mt-2">
        <span class="badge-status <?= ($first['pay_status'] ?? '') === 'verified' ? 'badge-lunas' : 'badge-pending' ?>">
          <i class="bi bi-<?= ($first['pay_status'] ?? '') === 'verified' ? 'check-circle-fill' : 'clock-fill' ?> me-1"></i>
          <?= htmlspecialchars($payStatusLabel) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Data Penyewa & Pengambilan -->
  <div class="row g-3 mb-4" style="font-size:.9rem;">
    <div class="col-sm-6">
      <div class="p-3 rounded-3" style="background:#f8f9fa;border:1px solid #eee;height:100%;">
        <div class="text-muted small fw-700 text-uppercase mb-2" style="letter-spacing:.5px;">Data Penyewa</div>
        <div class="fw-700 text-dark"><?= htmlspecialchars($first['user_name']) ?></div>
        <div class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($first['user_phone'] ?: '-') ?></div>
        <div class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($first['user_email']) ?></div>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="p-3 rounded-3" style="background:#f8f9fa;border:1px solid #eee;height:100%;">
        <div class="text-muted small fw-700 text-uppercase mb-2" style="letter-spacing:.5px;">Metode Pengambilan & Status</div>
        <div class="mb-1">
          <span class="text-muted">Metode: </span>
          <strong><?= ($first['pickup_method'] ?? 'pickup') === 'delivery' ? 'Diantarkan ke Lokasi' : 'Ambil di Basecamp' ?></strong>
        </div>
        <?php if (($first['pickup_method'] ?? 'pickup') === 'delivery' && !empty($first['delivery_address'])): ?>
        <div class="small text-muted mb-2">
          <i class="bi bi-geo-alt me-1"></i><?= nl2br(htmlspecialchars($first['delivery_address'])) ?>
        </div>
        <?php endif; ?>
        <div>
          <span class="text-muted">Status Sewa: </span>
          <span class="badge bg-dark fw-600"><?= htmlspecialchars($statusLabel) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Rincian Periode Sewa -->
  <div class="p-3 rounded-3 mb-4" style="background:#fff1f2;border:1px solid rgba(225,29,72,.2);">
    <div class="row text-center g-2" style="font-size:.88rem;">
      <div class="col-4">
        <div class="text-muted small">Mulai Sewa</div>
        <div class="fw-700 text-dark"><?= date('d M Y', strtotime($first['start_date'])) ?></div>
      </div>
      <div class="col-4 border-start border-end">
        <div class="text-muted small">Batas Pengembalian</div>
        <div class="fw-700 text-dark"><?= date('d M Y', strtotime($first['end_date'])) ?></div>
      </div>
      <div class="col-4">
        <div class="text-muted small">Total Durasi</div>
        <div class="fw-700" style="color:#e11d48;"><?= (int)$first['total_days'] ?> Hari</div>
      </div>
    </div>
  </div>

  <!-- Tabel Item Sewa (Semua Item dalam Transaksi Ini) -->
  <div class="table-responsive mb-4">
    <table class="table table-nota w-100">
      <thead>
        <tr>
          <th>Perlengkapan / Paket</th>
          <th>Kategori</th>
          <th class="text-end">Tarif / Hari</th>
          <th class="text-center">Durasi</th>
          <th class="text-end">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): 
          $itPrice = (float)$it['total_price'];
          $itBase  = round($itPrice / 1.05);
        ?>
        <tr>
          <td>
            <div class="fw-700 text-dark"><?= htmlspecialchars($it['product_name']) ?></div>
            <?php if (!empty($it['notes'])): ?>
            <small class="text-muted"><?= htmlspecialchars($it['notes']) ?></small>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($it['category_name'] ?? 'Gear') ?></span></td>
          <td class="text-end">Rp <?= number_format($it['price_per_day'], 0, ',', '.') ?></td>
          <td class="text-center"><?= (int)$it['total_days'] ?> hari</td>
          <td class="text-end fw-600">Rp <?= number_format($itBase, 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Rincian Pembayaran & Grand Total -->
  <div class="row g-4 align-items-start mb-4">
    <div class="col-sm-6">
      <div class="p-3 rounded border" style="background:#fafafa;font-size:.82rem;">
        <div class="fw-700 mb-1 text-dark">Informasi Pembayaran:</div>
        <div>Metode: <strong class="text-uppercase"><?= htmlspecialchars($first['pay_method'] ?? 'Transfer') ?></strong></div>
        <div>Waktu Verifikasi: <?= $first['pay_date'] ? date('d/m/Y H:i', strtotime($first['pay_date'])) : '-' ?></div>
        <div class="mt-2 text-muted" style="line-height:1.4;">
          Simpan nota ini sebagai bukti transaksi resmi saat pengambilan dan pengembalian perlengkapan outdoor di basecamp.
        </div>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="border rounded p-3" style="background:#fafafa;font-size:.88rem;">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Subtotal Sewa Gear:</span>
          <span>Rp <?= number_format($basePrice, 0, ',', '.') ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Biaya Layanan & Perawatan (5%):</span>
          <span>Rp <?= number_format($serviceFee, 0, ',', '.') ?></span>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-700 text-dark" style="font-size:1rem;">Total Tagihan:</span>
          <span class="fw-800" style="font-size:1.25rem;color:#e11d48;">
            Rp <?= number_format($totalPrice, 0, ',', '.') ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Ketentuan & Tanda Tangan -->
  <div class="border-top pt-4 mt-2">
    <div class="row align-items-end">
      <div class="col-8">
        <div class="fw-700 small text-uppercase mb-1" style="letter-spacing:.5px;">Ketentuan Sewa:</div>
        <ol class="text-muted small ps-3 mb-0" style="line-height:1.5;">
          <li>Penyewa wajib membawa kartu identitas asli (KTP/SIM/KTM) saat pengambilan gear di basecamp.</li>
          <li>Pengembalian melebihi batas tanggal sewa akan dikenakan denda sesuai tarif harian resmi.</li>
          <li>Kerusakan atau kehilangan alat menjadi tanggung jawab penuh penyewa sesuai ketentuan.</li>
        </ol>
      </div>
      <div class="col-4 text-center">
        <?php if (($first['pay_status'] ?? '') === 'verified'): ?>
        <div class="stamp mb-2">VERIFIED</div>
        <?php endif; ?>
        <div class="fw-700 text-dark small">PINK OUTDOOR GEAR RENT</div>
        <div class="text-muted" style="font-size:.72rem;">Customer Service & Staff</div>
      </div>
    </div>
  </div>

</div>

</body>
</html>