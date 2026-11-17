<?php
require_once '../config/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . 'katalog.php');
    exit;
}

$stmt = $conn->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 'available'");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    setFlash('error', 'Gear tidak ditemukan atau sedang tidak tersedia.');
    header('Location: ' . BASE_URL . 'katalog.php');
    exit;
}

$error = '';
$uid   = getUserId();

// Ambil profil user saat ini
$stmtU = $conn->prepare("SELECT name, email, phone, address FROM users WHERE id = ? LIMIT 1");
$stmtU->bind_param('i', $uid);
$stmtU->execute();
$userRow = $stmtU->get_result()->fetch_assoc() ?: ['name'=>'','email'=>'','phone'=>'','address'=>''];
$stmtU->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $start         = $_POST['start_date']        ?? '';
    $end           = $_POST['end_date']          ?? '';
    $phone         = trim($_POST['phone']        ?? '');
    $notes         = trim($_POST['notes']        ?? '');
    $method        = $_POST['payment_method']    ?? 'bca';
    $pickupMethod  = $_POST['pickup_method']     ?? 'pickup';
    $deliveryAddr  = trim($_POST['delivery_address'] ?? '');

    $validMethods = array_keys(PAYMENT_CHANNELS);
    if (!in_array($method, $validMethods)) $method = 'bca';
    if (!in_array($pickupMethod, ['pickup','delivery'])) $pickupMethod = 'pickup';

    $today = date('Y-m-d');

    // Validasi
    if (!$phone) {
        $error = 'Nomor WhatsApp aktif wajib diisi untuk koordinasi serah-terima alat.';
    } elseif (!preg_match('/^[0-9+\s-]{9,20}$/', $phone)) {
        $error = 'Format nomor WhatsApp tidak valid. Masukkan minimal 10 digit angka.';
    } elseif (!$start || !$end) {
        $error = 'Pilih tanggal mulai sewa dan tanggal kembali.';
    } elseif ($start < $today) {
        $error = 'Tanggal sewa tidak boleh di masa lalu.';
    } elseif ($start >= $end) {
        $error = 'Tanggal kembali harus setelah tanggal sewa (minimal 1 hari sewa).';
    } elseif ($product['stock'] < 1) {
        $error = 'Maaf, stok gear ini baru saja habis.';
    } elseif ($pickupMethod === 'delivery' && empty($deliveryAddr)) {
        $error = 'Alamat pengiriman wajib diisi jika memilih opsi Diantarkan ke Lokasi.';
    } else {
        // Simpan update telepon & alamat ke user profile
        if ($phone !== ($userRow['phone'] ?? '')) {
            $upUser = $conn->prepare("UPDATE users SET phone = ?, address = COALESCE(NULLIF(?, ''), address) WHERE id = ?");
            $upUser->bind_param('ssi', $phone, $deliveryAddr, $uid);
            $upUser->execute();
            $upUser->close();
        }

        $days       = (int)ceil((strtotime($end) - strtotime($start)) / 86400);
        $baseTotal  = $days * (float)$product['price_per_day'];
        $fee        = round($baseTotal * 0.05); // 5% biaya perawatan
        $finalTotal = $baseTotal + $fee;
        $deliveryVal= ($pickupMethod === 'delivery') ? $deliveryAddr : null;
        $orderNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $stmt = $conn->prepare("
            INSERT INTO bookings (order_number, user_id, product_id, start_date, end_date, total_days, total_price, notes, pickup_method, delivery_address, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param('siissidsss', $orderNumber, $uid, $id, $start, $end, $days, $finalTotal, $notes, $pickupMethod, $deliveryVal);
        $stmt->execute();
        $bookingId = $conn->insert_id;
        $stmt->close();

        $stmt2 = $conn->prepare("INSERT INTO payments (booking_id, user_id, amount, method, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt2->bind_param('iids', $bookingId, $uid, $finalTotal, $method);
        $stmt2->execute();
        $stmt2->close();

        // Kurangi stok 1 unit
        $ss = $conn->prepare("UPDATE products SET stock = GREATEST(0, stock - 1) WHERE id = ?");
        $ss->bind_param('i', $id);
        $ss->execute();
        $ss->close();

        setFlash('success', 'Reservasi berhasil dibuat! Silakan transfer dan kirim bukti pembayaran.');
        header('Location: bookings.php');
        exit;
    }
}

$postPhone   = $_POST['phone'] ?? ($userRow['phone'] ?? '');
$postStart   = $_POST['start_date'] ?? '';
$postEnd     = $_POST['end_date'] ?? '';
$postNotes   = $_POST['notes'] ?? '';
$postPickup  = $_POST['pickup_method'] ?? 'pickup';
$postAddress = $_POST['delivery_address'] ?? ($userRow['address'] ?? '');
$postMethod  = $_POST['payment_method'] ?? 'bca';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sewa <?= htmlspecialchars($product['name']) ?> — <?= SITE_NAME ?></title>
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
      padding: 10px 12px;
      user-select: none;
    }
    .pay-card-opt:hover {
      border-color: var(--pink-hover);
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
      padding: 14px;
    }
    .copy-chip {
      font-family: monospace;
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary);
      background: var(--surface-2);
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid var(--border);
    }
  </style>
</head>
<body>
<?php include '../config/partials/navbar_user.php'; ?>

<div class="container py-4" style="max-width:980px;">
  <a href="<?= BASE_URL ?>katalog.php" class="text-pink fw-600 d-inline-flex align-items-center gap-1 mb-3 text-decoration-none">
    <i class="bi bi-arrow-left"></i> Kembali ke Katalog Gear
  </a>

  <?php if ($error): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:var(--radius);">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div><?= htmlspecialchars($error) ?></div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Form Pemesanan Kiri -->
    <div class="col-lg-7">
      <div class="section-card">
        <h4 class="fw-800 mb-1" style="font-family:'Space Grotesk',sans-serif;">Form Reservasi Sewa</h4>
        <p class="text-muted small mb-4">Lengkapi tanggal dan kontak untuk memesan gear ini secara langsung.</p>

        <form method="POST" id="bookingForm" autocomplete="off">
          <?= csrfField() ?>
          <input type="hidden" id="price_per_day" value="<?= (float)$product['price_per_day'] ?>">

          <!-- 1. Kontak WhatsApp -->
          <div class="mb-4 pb-3 border-bottom" style="border-color:var(--border)!important;">
            <label class="form-label small fw-700">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-whatsapp text-success"></i></span>
              <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required
                     value="<?= htmlspecialchars($postPhone) ?>">
            </div>
            <small class="text-muted" style="font-size:.72rem;">Digunakan untuk konfirmasi jadwal serah-terima dan panduan pemakaian alat.</small>
          </div>

          <!-- 2. Tanggal Sewa -->
          <div class="mb-4 pb-3 border-bottom" style="border-color:var(--border)!important;">
            <label class="form-label small fw-700">Jadwal Sewa Gear <span class="text-danger">*</span></label>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label text-muted" style="font-size:.78rem;">Tanggal Mulai Ambil</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required
                       min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($postStart) ?>">
              </div>
              <div class="col-sm-6">
                <label class="form-label text-muted" style="font-size:.78rem;">Tanggal Pengembalian</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($postEnd) ?>">
              </div>
            </div>
            <div id="durasiInfo" class="mt-2 rounded px-3 py-2 align-items-center justify-content-between"
                 style="background:var(--pink-subtle);border:1px solid rgba(225,29,72,.15);font-size:.82rem;display:none;">
              <span class="fw-700 text-pink" id="durasiText">—</span>
              <span class="text-muted" style="font-size:.74rem;">Batas pengembalian 21.00 WIB</span>
            </div>
          </div>

          <!-- 3. Metode Pengambilan -->
          <div class="mb-4 pb-3 border-bottom" style="border-color:var(--border)!important;">
            <label class="form-label small fw-700">Metode Pengambilan *</label>
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="w-100 mb-0">
                  <input type="radio" name="pickup_method" value="pickup" class="pay-radio-input" id="pickupRadio" <?= $postPickup==='pickup'?'checked':'' ?> onchange="toggleDelivery()">
                  <div class="pay-card-opt <?= $postPickup==='pickup'?'pay-selected':'' ?>" id="pickupCard">
                    <div class="fw-700 small text-truncate"><i class="bi bi-shop me-1 text-pink"></i>Ambil di Basecamp</div>
                    <div class="text-muted" style="font-size:.72rem;">Gratis · Cek fisik bersama staf</div>
                  </div>
                </label>
              </div>
              <div class="col-6">
                <label class="w-100 mb-0">
                  <input type="radio" name="pickup_method" value="delivery" class="pay-radio-input" id="deliveryRadio" <?= $postPickup==='delivery'?'checked':'' ?> onchange="toggleDelivery()">
                  <div class="pay-card-opt <?= $postPickup==='delivery'?'pay-selected':'' ?>" id="deliveryCard">
                    <div class="fw-700 small text-truncate"><i class="bi bi-truck me-1 text-pink"></i>Diantar ke Alamat</div>
                    <div class="text-muted" style="font-size:.72rem;">Wilayah Garut Kota & Sekitar</div>
                  </div>
                </label>
              </div>
            </div>

            <div id="deliveryAddressBlock" style="display:<?= $postPickup==='delivery'?'block':'none' ?>;">
              <label class="form-label small fw-700 mt-2">Alamat Lengkap Pengiriman <span class="text-danger">*</span></label>
              <textarea name="delivery_address" id="deliveryAddress" class="form-control" rows="2"
                        placeholder="Nama jalan, perumahan, nomor rumah, dan patokan"><?= htmlspecialchars($postAddress) ?></textarea>
              <small class="text-muted" style="font-size:.72rem;"><i class="bi bi-info-circle me-1"></i>Ongkos kirim disesuaikan dengan jarak dan dikoordinasikan via WhatsApp</small>
            </div>
          </div>

          <!-- 4. Catatan Tambahan -->
          <div class="mb-4 pb-3 border-bottom" style="border-color:var(--border)!important;">
            <label class="form-label small fw-700">Catatan Tambahan <span class="text-muted fw-normal">(Opsional)</span></label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Estimasi pickup jam 08.00 pagi, minta cek baterai, dll."><?= htmlspecialchars($postNotes) ?></textarea>
          </div>

          <!-- 5. Metode Pembayaran -->
          <div class="mb-4">
            <label class="form-label small fw-700">Metode Pembayaran *</label>
            <div class="row g-2 mb-3">
              <?php foreach (PAYMENT_CHANNELS as $key => $chn): ?>
              <div class="col-4">
                <label class="w-100 mb-0">
                  <input type="radio" name="payment_method" value="<?= $key ?>" class="pay-radio-input pay-method" <?= $postMethod===$key?'checked':'' ?>>
                  <div class="pay-card-opt text-center <?= $postMethod===$key?'pay-selected':'' ?>">
                    <div class="fw-700" style="font-size:.82rem;"><?= htmlspecialchars(strtoupper($key)) ?></div>
                    <div class="text-muted" style="font-size:.68rem;"><?= $chn['type']==='bank'?'Bank':'E-Wallet' ?></div>
                  </div>
                </label>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Box Rekening Tujuan -->
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
                Transfer sesuai nominal ke rekening di atas dan simpan bukti transfer Anda.
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn-pink w-100 py-3 fw-700 fs-6 shadow-sm" id="submitBtn" disabled>
            <i class="bi bi-calendar2-check me-2 fs-5 align-middle"></i>Konfirmasi Booking Gear
          </button>
        </form>
      </div>
    </div>

    <!-- Info Gear & Estimasi Kanan -->
    <div class="col-lg-5">
      <div class="section-card sticky-top" style="top:84px;">
        <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($product['image']) ?>"
             class="w-100 mb-3" style="border-radius:var(--radius);object-fit:cover;height:180px;border:1px solid var(--border);"
             onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.svg'">

        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="badge bg-secondary-subtle text-secondary small fw-600"><?= htmlspecialchars($product['cat_name']) ?></span>
          <span class="badge bg-success-subtle text-success small fw-600"><i class="bi bi-check-circle me-1"></i>Stok: <?= (int)$product['stock'] ?> unit</span>
        </div>

        <h5 class="fw-800 mt-2 mb-1" style="color:var(--text-primary);"><?= htmlspecialchars($product['name']) ?></h5>
        <p class="text-muted small mb-3"><?= htmlspecialchars(mb_substr($product['description'] ?? '', 0, 95)) ?>...</p>

        <div class="p-3 rounded mb-3" style="background:var(--surface-2);border:1px solid var(--border);">
          <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span class="text-muted">Tarif Sewa:</span>
            <span class="fw-700">Rp <?= number_format($product['price_per_day'],0,',','.') ?> / hari</span>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span class="text-muted">Durasi Sewa:</span>
            <span class="fw-700" id="summaryDays">—</span>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span class="text-muted">Biaya Layanan & Cek (5%):</span>
            <span class="fw-700" id="summaryFee">—</span>
          </div>
          <hr class="my-2" style="border-color:var(--border);">
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-800">Total Biaya:</span>
            <span class="fw-800 text-pink fs-4" id="total_price_display">—</span>
          </div>
        </div>

        <!-- Catatan Syarat Basecamp -->
        <div class="p-3 rounded" style="background:var(--pink-subtle);border:1px solid rgba(225,29,72,.15);font-size:.76rem;color:var(--text-secondary);">
          <div class="fw-700 text-pink mb-1"><i class="bi bi-shield-check me-1"></i>Jaminan Identitas Wajib:</div>
          <div>Bawa 1 kartu identitas asli (KTP / SIM / KTM) yang masih berlaku saat serah-terima alat di basecamp Pink Outdoor Garut.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const paymentChannels = <?= json_encode(PAYMENT_CHANNELS) ?>;
const pricePerDay = parseFloat(document.getElementById('price_per_day').value) || 0;
const submitBtn = document.getElementById('submitBtn');

function fmt(n) {
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function updateTotal() {
  const s = document.getElementById('start_date').value;
  const e = document.getElementById('end_date').value;
  const durasiBox = document.getElementById('durasiInfo');
  const durasiText = document.getElementById('durasiText');

  if (s && e && e > s) {
    const sDate = new Date(s);
    const eDate = new Date(e);
    const days  = Math.ceil((eDate - sDate) / 86400000);

    if (days > 0) {
      const subtotal = days * pricePerDay;
      const fee      = Math.round(subtotal * 0.05);
      const grand    = subtotal + fee;

      durasiBox.style.display = 'flex';
      durasiText.textContent = days + ' Hari Sewa';
      document.getElementById('summaryDays').textContent = days + ' hari';
      document.getElementById('summaryFee').textContent  = fmt(fee);
      document.getElementById('total_price_display').textContent = fmt(grand);
      submitBtn.disabled = false;
      return;
    }
  }

  durasiBox.style.display = 'none';
  document.getElementById('summaryDays').textContent = '—';
  document.getElementById('summaryFee').textContent  = '—';
  document.getElementById('total_price_display').textContent = '—';
  submitBtn.disabled = true;
}

document.getElementById('start_date').addEventListener('change', function() {
  const d = new Date(this.value);
  d.setDate(d.getDate() + 1);
  const minEnd = d.toISOString().split('T')[0];
  document.getElementById('end_date').min = minEnd;
  if (document.getElementById('end_date').value <= this.value) {
    document.getElementById('end_date').value = minEnd;
  }
  updateTotal();
});
document.getElementById('end_date').addEventListener('change', updateTotal);

// Toggle Delivery
function toggleDelivery() {
  const isDel = document.getElementById('deliveryRadio').checked;
  const addrBlock = document.getElementById('deliveryAddressBlock');
  const addrInput = document.getElementById('deliveryAddress');
  addrBlock.style.display = isDel ? 'block' : 'none';
  addrInput.required      = isDel;
  document.getElementById('pickupCard').classList.toggle('pay-selected', !isDel);
  document.getElementById('deliveryCard').classList.toggle('pay-selected',  isDel);
}

// Payment method updates
function updatePaymentDetails(methodKey) {
  const channel = paymentChannels[methodKey];
  if (!channel) return;
  document.getElementById('payMethodBadge').textContent = methodKey.toUpperCase();
  document.getElementById('payChannelName').textContent  = channel.name;
  document.getElementById('payHolderName').textContent   = channel.holder_name;
  document.getElementById('payAccountNo').textContent    = channel.account_no;
  document.getElementById('payInstructions').textContent = channel.instructions;
}

document.querySelectorAll('.pay-method').forEach(r => {
  r.addEventListener('change', function() {
    document.querySelectorAll('.pay-method + .pay-card-opt').forEach(c => c.classList.remove('pay-selected'));
    this.nextElementSibling.classList.add('pay-selected');
    updatePaymentDetails(this.value);
  });
});

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

// Inisialisasi
const checkedMethod = document.querySelector('.pay-method:checked');
if (checkedMethod) {
  updatePaymentDetails(checkedMethod.value);
}
<?php if ($postStart && $postEnd): ?>
updateTotal();
<?php endif; ?>
</script>
</body>
</html>