<?php
require_once 'config/config.php';
redirectIfLoggedIn();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? $password;

    if (!$name || !$email || !$password) {
        $error = 'Nama lengkap, email, dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal harus 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'Alamat email ini sudah terdaftar. Silakan langsung login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $name, $email, $hash);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                // Catat aktivitas registrasi
                $act = "Pendaftaran akun baru";
                $mod = 'auth';
                $l = $conn->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, ?, ?)");
                $l->bind_param('iss', $newId, $act, $mod);
                $l->execute();
                $l->close();

                // Auto-login langsung agar pengguna tidak perlu repot mengetik ulang di login
                $_SESSION['user_id'] = $newId;
                $_SESSION['name']    = $name;
                $_SESSION['email']   = $email;
                $_SESSION['role']    = 'user';
                setFlash('success', 'Selamat datang di Pink Outdoor, ' . htmlspecialchars($name) . '! Akun kamu siap digunakan.');
                header('Location: index.php');
                exit;
            } else {
                $error = 'Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi.';
            }
            $stmt->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Akun Baru — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .auth-minimal-wrapper {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      padding: 2.5rem 1rem;
    }
    .auth-minimal-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
    }
    .auth-minimal-card .form-control {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      height: 48px;
      padding: 0.75rem 1rem;
      font-size: 0.92rem;
      color: #0f172a;
      transition: all 0.2s ease;
    }
    .auth-minimal-card .form-control:focus {
      border-color: var(--pink);
      box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
      outline: none;
    }
    .auth-minimal-card .form-control::placeholder {
      color: #94a3b8;
      font-size: 0.88rem;
    }
  </style>
</head>
<body>

<div class="auth-minimal-wrapper">
  <div class="auth-minimal-card">
    
    <!-- Brand Header -->
    <div class="text-center mb-4">
      <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-2">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--pink);border-radius:7px;color:#fff;font-size:.85rem;">
          <i class="bi bi-triangle-fill" style="transform:scale(0.85);"></i>
        </span>
        <span style="font-weight:900;letter-spacing:-0.5px;font-size:1.25rem;color:#0f172a;">
          PINK<span style="color:var(--pink);">OUTDOOR</span>
        </span>
      </a>
      <h1 class="fw-800 text-dark mb-1" style="font-size:1.35rem;letter-spacing:-0.4px;">Buat Akun</h1>
      <p class="text-muted small mb-0">Mulai sewa perlengkapan camping resmi di Garut</p>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2 px-3 border-0 rounded-3" style="background:#fef2f2;color:#991b1b;font-size:.84rem;">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0 text-danger"></i>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <!-- Simplified Registration Form -->
    <form method="POST" novalidate>
      <?= csrfField() ?>

      <!-- 1. Nama Lengkap -->
      <div class="mb-3">
        <label class="form-label text-secondary fw-600 small mb-1" for="nameInput">Nama Lengkap</label>
        <input type="text" id="nameInput" name="name" class="form-control"
               placeholder="Nama lengkap kamu"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus autocomplete="name">
      </div>

      <!-- 2. Email -->
      <div class="mb-3">
        <label class="form-label text-secondary fw-600 small mb-1" for="emailInput">Alamat Email</label>
        <input type="email" id="emailInput" name="email" class="form-control"
               placeholder="nama@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>

      <!-- 3. Password dengan Toggle Eye -->
      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label text-secondary fw-600 small mb-0" for="passInput">Password</label>
          <span class="text-muted" style="font-size:.72rem;">Min. 6 karakter</span>
        </div>
        <div class="position-relative">
          <input type="password" id="passInput" name="password" class="form-control pe-5"
                 placeholder="Buat password akun kamu" required minlength="6" autocomplete="new-password">
          <button type="button" id="togglePassBtn" class="btn text-muted position-absolute end-0 top-50 translate-middle-y border-0 p-2 me-1"
                  aria-label="Tampilkan / sembunyikan password" tabindex="-1" style="background:transparent;cursor:pointer;">
            <i class="bi bi-eye" id="eyeIcon" style="font-size:1.05rem;"></i>
          </button>
        </div>
      </div>

      <!-- Submit CTA Button -->
      <button type="submit" class="btn-pink w-100 py-2.5 fw-700" style="border-radius:12px;height:48px;font-size:.92rem;box-shadow:0 4px 14px rgba(225,29,72,0.25);">
        Buat Akun Sekarang
      </button>
    </form>

    <!-- Footer Links -->
    <div class="text-center mt-4 pt-2 border-top" style="border-color:#f1f5f9!important;">
      <span class="text-muted small">Sudah punya akun?</span>
      <a href="login.php" class="fw-700 small text-decoration-none ms-1" style="color:var(--pink);">
        Masuk di sini
      </a>
    </div>

    <div class="text-center mt-3">
      <a href="index.php" class="text-muted text-decoration-none" style="font-size:.78rem;">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
      </a>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Toggle password visibility
  const toggleBtn = document.getElementById('togglePassBtn');
  const passInput = document.getElementById('passInput');
  const eyeIcon   = document.getElementById('eyeIcon');
  if (toggleBtn && passInput && eyeIcon) {
    toggleBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const isPassword = passInput.getAttribute('type') === 'password';
      passInput.setAttribute('type', isPassword ? 'text' : 'password');
      eyeIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      passInput.focus();
    });
  }
</script>
</body>
</html>
