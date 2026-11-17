<?php
require_once '../config/config.php';
requireLogin();
$uid  = getUserId();
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: ' . BASE_URL . 'keranjang.php');
    exit;
}

// Ambil data profil user saat ini
$stmtU = $conn->prepare("SELECT name, email, phone, address FROM users WHERE id = ? LIMIT 1");
$stmtU->bind_param('i', $uid);
$stmtU->execute();
$currUser = $stmtU->get_result()->fetch_assoc() ?: ['name'=>'','email'=>'','phone'=>'','address'=>''];
$stmtU->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $method = $_POST['payment_method'] ?? 'bca';
    $valid  = array_keys(PAYMENT_CHANNELS);
    if (!in_array($method, $valid)) $method = 'bca';

    $phone         = trim($_POST['phone'] ?? '');
    $customerNotes = trim($_POST['customer_notes'] ?? '');
    $start         = $_POST['start_date'] ?? '';
    $end           = $_POST['end_date']   ?? '';
    $pickupMethod  = $_POST['pickup_method'] ?? 'pickup';
    $deliveryAddr  = ($pickupMethod === 'delivery') ? trim($_POST['delivery_address'] ?? '') : null;
    $today         = date('Y-m-d');

    // Validasi form
    if (!$phone) {
        $error = 'Nomor WhatsApp aktif wajib diisi untuk konfirmasi dan serah-terima alat.';
    } elseif (!preg_match('/^[0-9+\s-]{9,20}$/', $phone)) {
        $error = 'Format nomor WhatsApp tidak valid. Masukkan minimal 10 digit angka.';
    } elseif (!$start || !$end) {
        $error = 'Pilih tanggal mulai sewa dan tanggal kembali.';
    } elseif ($start < $today) {
        $error = 'Tanggal sewa tidak boleh di masa lalu.';
    } elseif ($start >= $end) {
        $error = 'Tanggal kembali harus setelah tanggal sewa (minimal 1 hari sewa).';
    } elseif ($pickupMethod === 'delivery' && empty($deliveryAddr)) {
        $error = 'Alamat pengantaran wajib diisi jika memilih opsi Diantarkan.';
    } else {
        // Simpan pembaruan nomor HP ke data user jika berubah
        if ($phone !== ($currUser['phone'] ?? '')) {
            $upUser = $conn->prepare("UPDATE users SET phone = ?, address = COALESCE(NULLIF(?, ''), address) WHERE id = ?");
            $upUser->bind_param('ssi', $phone, $deliveryAddr, $uid);
            $upUser->execute();
            $upUser->close();
        }

        $days = (int)ceil((strtotime($end) - strtotime($start)) / 86400);

        // Buat nomor pesanan / invoice unik untuk seluruh transaksi checkout ini
        $orderNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        foreach ($cart as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            if (!$pid) continue;

            $qty       = max(1, (int)($item['qty'] ?? 1));
            $basePrice = (float)$item['price'] * $days * $qty;
            $fee       = round($basePrice * 0.05); // 5% biaya pemeliharaan/layanan
            $itemTotal = $basePrice + $fee;

            $itemTypeNote = ($item['type'] ?? '') === 'paket' ? '[Paket Sewa]' : '';
            $fullNotes    = trim($itemTypeNote . ($customerNotes ? ' ' . $customerNotes : ''));
            if ($qty > 1) {
                $fullNotes = trim($fullNotes . " (Qty: {$qty} unit)");
            }

            $stmt = $conn->prepare("INSERT INTO bookings (order_number, user_id, product_id, start_date, end_date, total_days, total_price, status, notes, pickup_method, delivery_address) VALUES (?,?,?,?,?,?,?,'pending',?,?,?)");
            $stmt->bind_param('siissidsss', $orderNumber, $uid, $pid, $start, $end, $days, $itemTotal, $fullNotes, $pickupMethod, $deliveryAddr);
            $stmt->execute();
            $bid = $conn->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO payments (booking_id, user_id, amount, method, status) VALUES (?,?,?,?,'pending')");
            $stmt2->bind_param('iids', $bid, $uid, $itemTotal, $method);
            $stmt2->execute();
            $stmt2->close();

            // Kurangi stok
            $ss = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
            $ss->bind_param('ii', $qty, $pid);
            $ss->execute();
            $ss->close();
        }

        $_SESSION['cart'] = [];
        setFlash('success', 'Pesanan rental Anda berhasil dibuat! Silakan lakukan transfer dan upload bukti pembayaran.');
        header('Location: bookings.php');
        exit;
    }
}

// Fallback jika ada error POST
$postStart   = $_POST['start_date']     ?? '';
$postEnd     = $_POST['end_date']       ?? '';
$postPhone   = $_POST['phone']          ?? ($currUser['phone'] ?? '');
$postNotes   = $_POST['customer_notes'] ?? '';
$postPickup  = $_POST['pickup_method']  ?? 'pickup';
$postAddress = $_POST['delivery_address'] ?? ($currUser['address'] ?? '');
$postMethod  = $_POST['payment_method'] ?? 'bca';

// Hitung hari & total preview jika tanggal sudah terisi
$previewDays  = 0;
$previewItems = [];
if ($postStart && $postEnd && $postEnd > $postStart) {
    $previewDays = (int)ceil((strtotime($postEnd) - strtotime($postStart)) / 86400);
}
foreach ($cart as $item) {
    $iDays  = max(1, $previewDays);
    $iQty   = max(1, (int)($item['qty'] ?? 1));
    $iTotal = ($previewDays > 0) ? (float)$item['price'] * $iDays * $iQty : 0;
    $previewItems[] = [
        'name'  => $item['name'],
        'icon'  => $item['icon'] ?? null,
        'image' => $item['image'] ?? 'no-image.png',
        'type'  => $item['type'] ?? 'product',
        'price' => (float)$item['price'],
        'qty'   => $iQty,
        'days'  => $iDays,
        'total' => $iTotal,
    ];
}
$previewSubtotal = array_sum(array_column($previewItems, 'total'));
$previewFee      = $previewDays > 0 ? round($previewSubtotal * 0.05) : 0;
$previewTotal    = $previewSubtotal + $previewFee;

$cartPrices = [];
foreach ($cart as $key => $item) {
    $cartPrices[] = [
        'key'   => $key,
        'name'  => $item['name'],
        'price' => (float)$item['price'],
        'qty'   => max(1, (int)($item['qty'] ?? 1)),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout Sewa Gear — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
  <style>
    .pay-radio-input { display:none; }
    .pay-card-opt {
      cursor: pointer;
      transition: all .2s ease;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 12px 14px;
      user-select: none;
    }
    .pay-card-opt:hover {
      border-color: var(--pink-hover);
      transform: translateY(-1px);
    }
    .pay-card-opt.pay-selected,
    .pay-radio-input:checked + .pay-card-opt {
      border-color: var(--pink) !important;
      background: var(--pink-subtle) !important;
      box-shadow: 0 0 0 1px var(--pink);
    }
    .pay-detail-box {
      border: 1px dashed var(--pink);
      background: #fff;
      border-radius: var(--radius);
      padding: 16px;
    }
    .copy-chip {
      font-family: monospace;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      color: var(--text-primary);
      background: var(--surface-2);
      padding: 6px 12px;
      border-radius: 6px;
      border: 1px solid var(--border);
    }
  </style>
</head>
<body>
<?php include '../config/partials/navbar_user.php'; ?>

<div class="container py-4" style="max-width:980px;">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <a href="<?= BASE_URL ?>keranjang.php" class="d-inline-flex align-items-center gap-1 mb-2 text-pink fw-600 text-decoration-none">
        <i class="bi bi-arrow-left"></i> Kembali ke Keranjang
      </a>
      <h3 class="fw-800 mb-1" style="font-family:'Space Grotesk',sans-serif;">Konfirmasi Sewa Gear</h3>
      <p class="text-muted small mb-0">Lengkapi data kontak, jadwal rental, dan selesaikan pembayaran untuk reservasi unit.</p>
    </div>
    <div class="d-none d-md-block text-end">
      <span class="badge" style="background:var(--pink-subtle);color:var(--pink);font-size:.78rem;font-weight:700;padding:.4rem .75rem;border-radius:20px;border:1px solid rgba(225,29,72,.2);">
        <i class="bi bi-shield-check me-1"></i>Reservasi Aman & Terverifikasi
      </span>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:var(--radius);">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div><?= htmlspecialchars($error) ?></div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Kolom Formulir Kiri -->
    <div class="col-lg-7">
      <form method="POST" id="coForm" autocomplete="off">
        <?= csrfField() ?>

        <!-- 1. Data Pemesan & Kontak WhatsApp -->
        <div class="section-card mb-4">
          <div class="section-title d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-badge me-2 text-pink"></i>Data Pemesan & Kontak</span>
            <small class="text-muted fw-normal" style="font-size:.75rem;">Staff akan konfirmasi via WA</small>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-700">Nama Lengkap</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($currUser['name']) ?>" readonly style="background:var(--surface-2);">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-700">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-whatsapp text-success"></i></span>
                <input type="tel" name="phone" id="userPhone" class="form-control"
                       placeholder="08123456789" required
                       value="<?= htmlspecialchars($postPhone) ?>">
              </div>
              <small class="text-muted" style="font-size:.72rem;">Wajib aktif untuk jadwal serah-terima alat & koordinasi.</small>
            </div>
            <div class="col-12">
              <label class="form-label small fw-700">Catatan Khusus / Permintaan Tambahan <span class="text-muted fw-normal">(Opsional)</span></label>
              <textarea name="customer_notes" class="form-control" rows="2"
                        placeholder="Contoh: Estimasi ambil jam 09.00 pagi, butuh warna tertentu jika tersedia, dll..."><?= htmlspecialchars($postNotes) ?></textarea>
            </div>
          </div>
        </div>

        <!-- 2. Tanggal Sewa -->
        <div class="section-card mb-4">
          <div class="section-title d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar2-range me-2 text-pink"></i>Jadwal Sewa Gear</span>
            <small class="text-muted fw-normal" style="font-size:.75rem;">Hitungan per hari kalender</small>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label small fw-700">Tanggal Mulai Ambil *</label>
              <input type="date" name="start_date" id="startDate" class="form-control"
                     min="<?= date('Y-m-d') ?>" required
                     value="<?= htmlspecialchars($postStart) ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-700">Tanggal Pengembalian *</label>
              <input type="date" name="end_date" id="endDate" class="form-control"
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                     value="<?= htmlspecialchars($postEnd) ?>">
            </div>
          </div>
          <!-- Durasi info -->
          <div id="durasiInfo" class="mt-3 rounded px-3 py-2 d-flex align-items-center justify-content-between"
               style="background:var(--pink-subtle);border:1px solid rgba(225,29,72,.15);font-size:.83rem;display:<?= $previewDays>0?'flex':'none' ?>;">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-clock-history text-pink fs-6"></i>
              <span id="durasiText" class="fw-700 text-pink">
                <?= $previewDays>0 ? $previewDays.' Hari Sewa' : '' ?>
              </span>
            </div>
            <span class="text-muted" style="font-size:.75rem;">Pengembalian maks 21.00 WIB pada hari terakhir</span>
          </div>
        </div>

        <!-- 3. Metode Pengambilan -->
        <div class="section-card mb-4">
          <div class="section-title">
            <i class="bi bi-geo-alt me-2 text-pink"></i>Metode Pengambilan
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="w-100 mb-0">
                <input type="radio" name="pickup_method" value="pickup" class="pay-radio-input pickup-radio" <?= $postPickup==='pickup'?'checked':'' ?> onchange="toggleDelivery()">
                <div class="pay-card-opt <?= $postPickup==='pickup'?'pay-selected':'' ?>" id="pickupCard">
                  <div class="fw-700 small text-truncate"><i class="bi bi-shop me-1 text-pink"></i>Ambil di Basecamp</div>
                  <div class="text-muted mt-1" style="font-size:.72rem;">Gratis · Cek kelengkapan bersama</div>
                </div>
              </label>
            </div>
            <div class="col-6">
              <label class="w-100 mb-0">
                <input type="radio" name="pickup_method" value="delivery" class="pay-radio-input pickup-radio" <?= $postPickup==='delivery'?'checked':'' ?> onchange="toggleDelivery()">
                <div class="pay-card-opt <?= $postPickup==='delivery'?'pay-selected':'' ?>" id="deliveryCard">
                  <div class="fw-700 small text-truncate"><i class="bi bi-truck me-1 text-pink"></i>Diantarkan ke Lokasi</div>
                  <div class="text-muted mt-1" style="font-size:.72rem;">Area Garut Kota & Sekitarnya</div>
                </div>
              </label>
            </div>
          </div>
          <div id="deliveryAddressBlock" style="display:<?= $postPickup==='delivery'?'block':'none' ?>;">
            <label class="form-label small fw-700">Alamat Lengkap Pengiriman <span class="text-danger">*</span></label>
            <textarea name="delivery_address" id="deliveryAddress" class="form-control" rows="2"
                      placeholder="Jalan, No. Rumah, Patokan (Ongkir dikonfirmasi staff via WhatsApp)"><?= htmlspecialchars($postAddress) ?></textarea>
          </div>
        </div>

        <!-- 4. Metode Pembayaran & Rekening Tujuan -->
        <div class="section-card mb-4">
          <div class="section-title d-flex justify-content-between align-items-center">
            <span><i class="bi bi-credit-card me-2 text-pink"></i>Metode Pembayaran</span>
            <span class="badge bg-light text-dark fw-normal" style="font-size:.72rem;">Transfer Bank / E-Wallet</span>
          </div>
          <div class="row g-2 mb-3">
            <?php foreach (PAYMENT_CHANNELS as $key => $chn): ?>
            <div class="col-4 col-sm-4">
              <label class="w-100 mb-0">
                <input type="radio" name="payment_method" value="<?= $key ?>" class="pay-radio-input pay-method" <?= $postMethod===$key?'checked':'' ?>>
                <div class="pay-card-opt text-center <?= $postMethod===$key?'pay-selected':'' ?>">
                  <div class="fw-700" style="font-size:.82rem;"><?= htmlspecialchars(strtoupper($key)) ?></div>
                  <div class="text-muted" style="font-size:.68rem;"><?= $chn['type']==='bank'?'Bank Transfer':'E-Wallet' ?></div>
                </div>
              </label>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Dynamic Instruksi Pembayaran Box -->
          <div class="pay-detail-box" id="payDetailBox">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="badge bg-dark mb-1 text-uppercase" id="payMethodBadge">BCA</span>
                <div class="fw-700" id="payChannelName">BCA (Bank Central Asia)</div>
                <div class="text-muted small">Atas Nama: <strong class="text-dark" id="payHolderName">PINK OUTDOOR GEAR</strong></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-pink d-flex align-items-center gap-1" id="copyAccBtn" onclick="copyAccount()">
                <i class="bi bi-copy"></i> <span id="copyAccLabel">Salin Rekening</span>
              </button>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="copy-chip flex-grow-1 text-center" id="payAccountNo">148-098-7654</div>
            </div>
            <p class="text-muted small mb-0" id="payInstructions" style="font-size:.78rem;">
              Transfer via ATM / m-BCA / KlikBCA ke nomor rekening di atas. Simpan struk/screenshot untuk diupload setelah checkout.
            </p>
          </div>
        </div>

        <!-- Tombol Konfirmasi -->
        <button type="submit" class="btn-pink w-100 py-3 fw-700 fs-6 shadow-sm" id="submitBtn">
          <i class="bi bi-check2-circle me-2 fs-5 align-middle"></i>Konfirmasi & Buat Pesanan
        </button>

        <!-- Jaminan & Kebijakan Singkat -->
        <div class="mt-3 p-3 rounded d-flex align-items-start gap-2" style="background:var(--surface-2);border:1px solid var(--border);font-size:.76rem;color:var(--text-muted);">
          <i class="bi bi-info-circle text-pink fs-6 flex-shrink-0 mt-1"></i>
          <div>
            <strong>Syarat Pengambilan:</strong> Wajib membawa 1 kartu identitas asli (KTP / SIM / KTM) yang masih berlaku untuk jaminan serah terima di basecamp. Pelunasan/bukti transfer diverifikasi oleh tim sebelum gear diserahkan.
          </div>
        </div>
      </form>
    </div>

    <!-- Kolom Ringkasan Kanan -->
    <div class="col-lg-5">
      <div class="section-card sticky-top" style="top:84px;">
        <div class="section-title d-flex justify-content-between align-items-center">
          <span>🛒 Ringkasan Pesanan</span>
          <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;"><?= count($cart) ?> Barang</span>
        </div>

        <!-- Daftar Item Rental -->
        <div class="mb-3" style="max-height:300px;overflow-y:auto;">
          <?php foreach ($previewItems as $idx => $item): ?>
          <div class="d-flex align-items-center gap-3 mb-2 pb-2 border-bottom" style="border-color:var(--border)!important;">
            <?php if ($item['type'] === 'paket' && $item['icon']): ?>
            <div style="width:44px;height:44px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
              <?= htmlspecialchars($item['icon']) ?>
            </div>
            <?php else: ?>
            <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($item['image']) ?>"
                 width="44" height="44" style="object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0;"
                 onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.svg'">
            <?php endif; ?>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-700 text-truncate" style="font-size:.82rem;color:var(--text-primary);"><?= htmlspecialchars($item['name']) ?></div>
              <div style="font-size:.74rem;color:var(--text-muted);">
                Rp <?= number_format($item['price'],0,',','.') ?>/hari × <?= $item['qty'] ?> unit × <span class="item-days-<?= $idx ?>"><?= $item['days'] ?></span> hari
              </div>
            </div>
            <div class="fw-700 text-pink text-end" style="font-size:.88rem;white-space:nowrap;" id="item-total-<?= $idx ?>">
              <?= $previewDays>0 ? 'Rp '.number_format($item['total'],0,',','.') : '—' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between mb-1" style="font-size:.83rem;">
          <span class="text-muted">Subtotal Sewa</span>
          <span class="fw-600" id="summarySubtotal"><?= $previewDays>0 ? 'Rp '.number_format($previewSubtotal,0,',','.') : '—' ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size:.83rem;">
          <span class="text-muted">Biaya Layanan & Perawatan (5%)</span>
          <span class="fw-600" id="summaryFee"><?= $previewDays>0 ? 'Rp '.number_format($previewFee,0,',','.') : '—' ?></span>
        </div>
        <hr style="border-color:var(--border);">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="fw-800">Total Pembayaran</span>
          <span class="fw-800 text-pink fs-4" id="summaryTotal">
            <?= $previewDays>0 ? 'Rp '.number_format($previewTotal,0,',','.') : '—' ?>
          </span>
        </div>

        <div class="rounded p-2 mb-0" style="background:var(--surface-2);font-size:.75rem;color:var(--text-muted);">
          <i class="bi bi-arrow-repeat me-1 text-pink"></i>
          Biaya otomatis diperbarui saat Anda mengubah tanggal mulai dan pengembalian.
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Data items & channels dari backend PHP
const cartItems       = <?= json_encode(array_values($cartPrices)) ?>;
const paymentChannels = <?= json_encode(PAYMENT_CHANNELS) ?>;

function fmt(n) {
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function updateSummary() {
  const s = document.getElementById('startDate').value;
  const e = document.getElementById('endDate').value;
  const infoBox  = document.getElementById('durasiInfo');
  const infoText = document.getElementById('durasiText');

  if (!s || !e || e <= s) {
    document.getElementById('summarySubtotal').textContent = '—';
    document.getElementById('summaryFee').textContent      = '—';
    document.getElementById('summaryTotal').textContent    = '—';
    document.querySelectorAll('[id^="item-total-"]').forEach(el => el.textContent = '—');
    document.querySelectorAll('[class*="item-days-"]').forEach(el => el.textContent = '—');
    if (infoBox) infoBox.style.display = 'none';
    return;
  }

  const startTs = new Date(s).getTime();
  const endTs   = new Date(e).getTime();
  const days    = Math.ceil((endTs - startTs) / 86400000);

  if (days <= 0) return;

  if (infoBox)  infoBox.style.display  = 'flex';
  if (infoText) infoText.textContent   = days + ' Hari Sewa';

  let subtotal = 0;
  cartItems.forEach((item, idx) => {
    const lineTotal = item.price * days * item.qty;
    subtotal += lineTotal;
    const el = document.getElementById('item-total-' + idx);
    if (el) el.textContent = fmt(lineTotal);
    document.querySelectorAll('.item-days-' + idx).forEach(el2 => el2.textContent = days);
  });

  const fee   = Math.round(subtotal * 0.05);
  const total = subtotal + fee;

  document.getElementById('summarySubtotal').textContent = fmt(subtotal);
  document.getElementById('summaryFee').textContent      = fmt(fee);
  document.getElementById('summaryTotal').textContent    = fmt(total);
}

// Sinkronisasi tanggal
document.getElementById('startDate').addEventListener('change', function() {
  const d = new Date(this.value);
  d.setDate(d.getDate() + 1);
  const minEnd = d.toISOString().split('T')[0];
  document.getElementById('endDate').min = minEnd;
  if (document.getElementById('endDate').value <= this.value) {
    document.getElementById('endDate').value = minEnd;
  }
  updateSummary();
});
document.getElementById('endDate').addEventListener('change', updateSummary);

// Render info rekening saat metode pembayaran dipilih
function updatePaymentDetails(methodKey) {
  const channel = paymentChannels[methodKey];
  if (!channel) return;
  document.getElementById('payMethodBadge').textContent = methodKey.toUpperCase();
  document.getElementById('payChannelName').textContent  = channel.name;
  document.getElementById('payHolderName').textContent   = channel.holder_name;
  document.getElementById('payAccountNo').textContent    = channel.account_no;
  document.getElementById('payInstructions').textContent = channel.instructions;
}

// Event listener pilihan radio pembayaran
document.querySelectorAll('.pay-method').forEach(r => {
  r.addEventListener('change', function() {
    document.querySelectorAll('.pay-method + .pay-card-opt').forEach(c => c.classList.remove('pay-selected'));
    this.nextElementSibling.classList.add('pay-selected');
    updatePaymentDetails(this.value);
  });
});

// Fitur Salin Nomor Rekening 1-Klik
function copyAccount() {
  const accNo = document.getElementById('payAccountNo').textContent.trim();
  navigator.clipboard.writeText(accNo).then(() => {
    const btn = document.getElementById('copyAccBtn');
    const lbl = document.getElementById('copyAccLabel');
    btn.classList.remove('btn-outline-pink');
    btn.classList.add('btn-pink');
    lbl.textContent = 'Tersalin!';
    setTimeout(() => {
      btn.classList.remove('btn-pink');
      btn.classList.add('btn-outline-pink');
      lbl.textContent = 'Salin Rekening';
    }, 2000);
  });
}

// Toggle Delivery Address
function toggleDelivery() {
  const isDel = document.querySelector('input[name="pickup_method"][value="delivery"]').checked;
  const addrBlock = document.getElementById('deliveryAddressBlock');
  const addrInput = document.getElementById('deliveryAddress');
  addrBlock.style.display = isDel ? 'block' : 'none';
  addrInput.required      = isDel;
  document.getElementById('pickupCard').classList.toggle('pay-selected', !isDel);
  document.getElementById('deliveryCard').classList.toggle('pay-selected',  isDel);
}

// Inisialisasi awal
const checkedMethod = document.querySelector('.pay-method:checked');
if (checkedMethod) {
  updatePaymentDetails(checkedMethod.value);
}
<?php if ($postStart && $postEnd): ?>
updateSummary();
<?php endif; ?>
</script>
</body>
</html>