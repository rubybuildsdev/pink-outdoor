<?php
require_once '../config/config.php';
requireLogin();

$uid = getUserId();

$stmtUser = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmtUser->bind_param('i', $uid);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$error = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name    = trim($_POST['name']    ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$name) {
            $error = 'Nama lengkap tidak boleh kosong.';
        } elseif ($phone && !preg_match('/^[0-9+\s-]{9,20}$/', $phone)) {
            $error = 'Format nomor WhatsApp tidak valid. Masukkan minimal 10 digit angka.';
        } else {
            $s = $conn->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?");
            $s->bind_param('sssi', $name, $phone, $address, $uid);
            $s->execute();
            $s->close();
            $_SESSION['name'] = $name;
            setFlash('success', 'Data profil & alamat berhasil diperbarui!');
            header('Location: profile.php');
            exit;
        }
    }

    if ($action === 'password') {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($old, $user['password'])) {
            $error = 'Password lama yang Anda masukkan tidak sesuai.';
        } elseif (strlen($new) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($new !== $conf) {
            $error = 'Konfirmasi password baru tidak cocok.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s->bind_param('si', $hash, $uid);
            $s->execute();
            $s->close();
            setFlash('success', 'Password akun Anda berhasil diperbarui!');
            header('Location: profile.php');
            exit;
        }
    }

    // Hapus akun
    if ($action === 'delete_account') {
        $confirm = $_POST['confirm_delete'] ?? '';
        if ($confirm !== 'HAPUS') {
            $error = 'Ketik kata HAPUS untuk konfirmasi penghapusan akun.';
        } else {
            $s = $conn->prepare("DELETE FROM users WHERE id=?");
            $s->bind_param('i', $uid);
            $s->execute();
            $s->close();
            session_destroy();
            header('Location: ' . BASE_URL . 'register.php?msg=Akun+berhasil+dihapus');
            exit;
        }
    }
}

$stmtStats = $conn->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) AS selesai, SUM(CASE WHEN status IN ('diproses','sedang_sewa') THEN 1 ELSE 0 END) AS aktif, COALESCE(SUM(total_price),0) AS total_spent FROM bookings WHERE user_id=?");
$stmtStats->bind_param('i', $uid);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Saya — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include '../config/partials/navbar_user.php'; ?>

<div class="container py-4" style="max-width:920px;">
  <!-- User Profile Header Card -->
  <div class="section-card mb-4 p-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center justify-content-center fw-800 text-white flex-shrink-0"
             style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,var(--pink),#be123c);font-size:1.8rem;box-shadow:0 4px 14px rgba(225,29,72,.25);">
          <?= strtoupper(substr($user['name'],0,1)) ?>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2">
            <h4 class="fw-800 mb-0" style="color:var(--text-primary);"><?= htmlspecialchars($user['name']) ?></h4>
            <span class="badge" style="background:var(--pink-subtle);color:var(--pink);border:1px solid rgba(225,29,72,.2);font-size:.7rem;">
              <i class="bi bi-patch-check-fill me-1"></i>Member Terdaftar
            </span>
          </div>
          <div class="text-muted small mt-1">
            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
            <?php if (!empty($user['phone'])): ?>
            <span class="mx-2">•</span><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($user['phone']) ?>
            <?php endif; ?>
          </div>
          <div class="text-muted" style="font-size:.74rem;margin-top:2px;">
            Bergabung sejak <?= date('d M Y', strtotime($user['created_at'])) ?>
          </div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="bookings.php" class="btn btn-outline-secondary btn-sm py-2 px-3 fw-600">
          <i class="bi bi-calendar2-check me-1 text-pink"></i>Lihat Booking
        </a>
      </div>
    </div>
  </div>

  <!-- Stats Grid -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card stat-pink">
        <div class="stat-num"><?= (int)$stats['total'] ?></div>
        <div class="stat-label">Total Booking</div>
        <i class="bi bi-calendar-check stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-blue">
        <div class="stat-num"><?= (int)$stats['aktif'] ?></div>
        <div class="stat-label">Sewa Aktif</div>
        <i class="bi bi-hourglass-split stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-green">
        <div class="stat-num"><?= (int)$stats['selesai'] ?></div>
        <div class="stat-label">Selesai</div>
        <i class="bi bi-check-circle stat-icon"></i>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card stat-purple">
        <div class="stat-num" style="font-size:1.6rem;">Rp <?= number_format((float)$stats['total_spent']/1000, 0, ',', '.') ?>K</div>
        <div class="stat-label">Total Rental</div>
        <i class="bi bi-wallet2 stat-icon"></i>
      </div>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:var(--radius);">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div><?= htmlspecialchars($error) ?></div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Edit Profil & Alamat -->
    <div class="col-lg-6">
      <div class="section-card mb-4">
        <div class="section-title"><i class="bi bi-person me-2 text-pink"></i>Data Pribadi & Kontak</div>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="profile">

          <div class="mb-3">
            <label class="form-label small fw-700">Nama Lengkap *</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($user['name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-700">Alamat Email <small class="text-muted">(terkunci)</small></label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--surface-2);">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-700">Nomor WhatsApp Aktif</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-whatsapp text-success"></i></span>
              <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
            </div>
            <small class="text-muted" style="font-size:.72rem;">Nomor ini digunakan saat konfirmasi dan serah-terima gear.</small>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-700">Alamat Default Pengiriman Gear</label>
            <textarea name="address" class="form-control" rows="3" placeholder="Nama jalan, perumahan, RT/RW, dan patokan"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn-pink w-100 py-2 fw-700">
            <i class="bi bi-check2-circle me-1"></i>Simpan Perubahan Profil
          </button>
        </form>
      </div>
    </div>

    <!-- Kolom Kanan: Keamanan Password & Pengaturan Akun -->
    <div class="col-lg-6">
      <!-- Ganti Password -->
      <div class="section-card mb-4">
        <div class="section-title"><i class="bi bi-shield-lock me-2 text-pink"></i>Keamanan & Password</div>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="password">

          <div class="mb-3">
            <label class="form-label small fw-700">Password Lama *</label>
            <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-700">Password Baru *</label>
            <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-700">Konfirmasi Password Baru *</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password baru" required>
          </div>
          <button type="submit" class="btn-pink-outline w-100 py-2 fw-700">
            <i class="bi bi-key me-1"></i>Perbarui Password
          </button>
        </form>
      </div>

      <!-- Sesi & Logout -->
      <div class="section-card mb-4">
        <div class="section-title"><i class="bi bi-box-arrow-right me-2 text-pink"></i>Keluar dari Akun</div>
        <p class="text-muted small mb-3">Keluar dari akun Anda pada perangkat ini jika Anda menggunakan komputer umum.</p>
        <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-600" onclick="confirmLogout()">
          <i class="bi bi-box-arrow-right me-2 text-danger"></i>Logout
        </button>
      </div>

      <!-- Zona Bahaya: Hapus Akun -->
      <div class="section-card" style="border:1px dashed #ef4444;background:#fffaf0;">
        <div class="fw-800 text-danger mb-1" style="font-size:.9rem;"><i class="bi bi-exclamation-triangle me-1"></i>Hapus Akun Permanen</div>
        <p class="text-muted small mb-3" style="font-size:.78rem;">
          Tindakan ini permanen. Seluruh riwayat rental dan data akun Anda akan dihapus dari sistem.
        </p>
        <button class="btn btn-outline-danger btn-sm w-100 py-2 fw-700" data-bs-toggle="collapse" data-bs-target="#deleteForm">
          <i class="bi bi-trash me-1"></i>Tampilkan Form Penghapusan
        </button>
        <div class="collapse mt-3" id="deleteForm">
          <form method="POST" onsubmit="return confirm('Apakah Anda benar-benar yakin ingin menghapus akun ini secara permanen?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_account">
            <div class="mb-2">
              <label class="form-label small fw-700 text-danger" style="font-size:.76rem;">Ketik HAPUS untuk konfirmasi:</label>
              <input type="text" name="confirm_delete" class="form-control form-control-sm border-danger" placeholder="HAPUS" required>
            </div>
            <button type="submit" class="btn btn-danger btn-sm w-100 py-2 fw-700">
              <i class="bi bi-trash-fill me-1"></i>Konfirmasi Hapus Akun Saya
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmLogout() {
  Swal.fire({
    title: 'Keluar dari akun?',
    text: 'Sesi login Anda akan diakhiri.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Ya, Keluar',
    cancelButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      window.location.href = '<?= BASE_URL ?>logout.php';
    }
  });
}

<?php if ($flash): ?>
Swal.fire({
  icon: '<?= $flash['type'] ?>',
  title: '<?= addslashes(htmlspecialchars($flash['msg'])) ?>',
  timer: 2800,
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timerProgressBar: true
});
<?php endif; ?>
</script>
</body>
</html>