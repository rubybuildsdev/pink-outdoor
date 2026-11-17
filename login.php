<?php
require_once 'config/config.php';
redirectIfLoggedIn();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1");
        $stmt->bind_param('s',$email); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $uid=$user['id']; $act="Login sebagai {$user['role']}"; $mod='auth';
            $l=$conn->prepare("INSERT INTO activity_log (user_id,action,module) VALUES(?,?,?)");
            $l->bind_param('iss',$uid,$act,$mod); $l->execute(); $l->close();
            switch ($user['role']) {
                case 'admin': header('Location: admin/index.php'); break;
                case 'staff': header('Location: staff/index.php'); break;
                default:      header('Location: index.php'); break;
            }
            exit;
        } else { $error = 'Email atau password salah, atau akun tidak aktif.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Logo -->
    <div class="text-center mb-4">
      <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-2">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:var(--pink);border-radius:7px;color:#fff;font-size:.85rem;">
          <i class="bi bi-triangle-fill" style="transform:scale(0.85);"></i>
        </span>
        <span style="font-weight:900;letter-spacing:-0.5px;font-size:1.25rem;color:#0f172a;">
          PINK<span style="color:var(--pink);">OUTDOOR</span>
        </span>
      </a>
      <h1 class="fw-800 text-dark mb-1" style="font-size:1.35rem;letter-spacing:-0.4px;">Selamat Datang</h1>
      <div style="font-size:.82rem;color:var(--text-muted);">Masuk ke akun rental perlengkapanmu</div>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;">
      <i class="bi bi-lock-fill flex-shrink-0"></i>
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label text-secondary fw-600 small mb-1" for="loginEmail">Alamat Email</label>
        <input type="email" id="loginEmail" name="email" class="form-control"
               placeholder="nama@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required autofocus
               style="border-radius:12px; height:48px; border-color:#e2e8f0; font-size:.92rem; padding: 0.75rem 1rem;">
      </div>
      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label text-secondary fw-600 small mb-0" for="passInput">Password</label>
        </div>
        <div class="position-relative">
          <input type="password" id="passInput" name="password" class="form-control pe-5"
                 placeholder="••••••••" autocomplete="current-password" required
                 style="border-radius:12px; height:48px; border-color:#e2e8f0; font-size:.92rem; padding: 0.75rem 1rem;">
          <button type="button" id="togglePassBtn" class="btn text-muted position-absolute end-0 top-50 translate-middle-y border-0 p-2 me-1"
                  aria-label="Tampilkan / sembunyikan password" tabindex="-1" style="background:transparent;cursor:pointer;">
            <i class="bi bi-eye" id="eyeIcon" style="font-size:1.05rem;"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-pink w-100 mb-3 fw-700" style="min-height:48px; border-radius:12px; font-size:.92rem; box-shadow:0 4px 14px rgba(225,29,72,0.25);">
        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
      </button>
    </form>

    <div style="position:relative;text-align:center;margin-bottom:1rem;">
      <hr style="border-color:var(--border);">
      <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--surface);padding:0 .75rem;font-size:.78rem;color:var(--text-muted);">atau</span>
    </div>

    <a href="auth/google-login.php"
       class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 mb-4"
       style="border-radius:var(--radius);min-height:44px;font-size:.875rem;border-color:var(--border);">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#4285F4" d="M47.5 24.5c0-1.6-.1-3.2-.4-4.7H24v9h13.1c-.6 3-2.3 5.5-4.9 7.2v6h7.9c4.6-4.3 7.4-10.5 7.4-17.5z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.9-6c-2.1 1.4-4.8 2.3-8 2.3-6.2 0-11.4-4.2-13.3-9.8H2.5v6.2C6.5 42.7 14.8 48 24 48z"/><path fill="#FBBC05" d="M10.7 28.7C10.2 27.3 10 25.7 10 24s.2-3.3.7-4.7v-6.2H2.5C.9 16.6 0 20.2 0 24s.9 7.4 2.5 10.9l8.2-6.2z"/><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.9 2.2 30.5 0 24 0 14.8 0 6.5 5.3 2.5 13.1l8.2 6.2C12.6 13.7 17.8 9.5 24 9.5z"/></svg>
      Login dengan Google
    </a>

    <p class="text-center mb-3" style="font-size:.85rem;color:var(--text-muted);">
      Belum punya akun?
      <a href="register.php" style="color:var(--pink);font-weight:700;">Daftar sekarang</a>
    </p>

    <div class="text-center">
      <a href="index.php" class="text-muted text-decoration-none" style="font-size:.78rem;">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
      </a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePassBtn').addEventListener('click', function() {
  const inp = document.getElementById('passInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
});
</script>
</body>
</html>
