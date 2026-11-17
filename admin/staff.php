<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Kelola Staff';
$active    = 'staff';

// Tambah staff
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        setFlash('error', 'Email sudah terdaftar!');
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'staff')");
        $stmt->bind_param('ssss', $name, $email, $phone, $password);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Staff berhasil ditambahkan!');
    }
    $chk->close();
    header('Location: staff.php'); exit;
}

// Toggle status staff
if (isset($_GET['toggle'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: staff.php'); exit;
    }
    $id = (int)$_GET['toggle'];
    // BUG FIX: execute_query() hanya ada di PHP 8.2+, menyebabkan Fatal Error di PHP < 8.2.
    // Ganti dengan prepare() → bind_param() → execute().
    $stmt = $conn->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role = 'staff'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Status staff diperbarui!');
    header('Location: staff.php'); exit;
}

// Hapus staff
if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: staff.php'); exit;
    }
    $id = (int)$_GET['delete'];
    // BUG FIX: sama — execute_query() diganti.
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Staff dihapus!');
    header('Location: staff.php'); exit;
}

$staffList = $conn->query("SELECT * FROM users WHERE role = 'staff' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">
      <div class="row g-4">

        <!-- Form Tambah Staff -->
        <div class="col-lg-4">
          <div class="section-card">
            <div class="section-title">➕ Tambah Staff Baru</div>
            <form method="POST">
              <?= csrfField() ?>
              <div class="mb-3">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">No. HP</label>
                <input type="tel" name="phone" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required minlength="6">
                <small class="text-muted">Min. 6 karakter</small>
              </div>
              <button type="submit" class="btn-pink w-100">
                <i class="bi bi-person-plus me-1"></i>Tambah Staff
              </button>
            </form>
          </div>
        </div>

        <!-- Daftar Staff -->
        <div class="col-lg-8">
          <div class="section-card">
            <div class="section-title">👮 Daftar Staff (<?= count($staffList) ?>)</div>
            <div class="table-responsive">
              <table class="table table-custom table-hover mb-0">
                <thead>
                  <tr><th>#</th><th>Nama</th><th>Email</th><th>No HP</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($staffList as $s): ?>
                  <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td class="fw-600"><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                    <td><span class="badge-<?= $s['status'] === 'active' ? 'active' : 'cancelled' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td class="small text-muted"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                    <td>
                      <a href="staff.php?toggle=<?= (int)$s['id'] ?>&csrf_token=<?= csrfToken() ?>"
                         class="btn btn-sm btn-outline-warning py-0 me-1"
                         onclick="return confirm('Ubah status staff?')">
                        <?= $s['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </a>
                      <button onclick="confirmDelete('staff.php?delete=<?= (int)$s['id'] ?>&csrf_token=<?= csrfToken() ?>','<?= addslashes(htmlspecialchars($s['name'])) ?>')"
                              class="btn btn-sm btn-outline-danger py-0">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($staffList)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">Belum ada staff</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
