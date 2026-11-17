<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Kelola User';
$active    = 'users';

// Toggle status user
if (isset($_GET['toggle'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: users.php'); exit;
    }
    $id = (int)$_GET['toggle'];
    // BUG FIX: execute_query() hanya ada di PHP 8.2+, menyebabkan Fatal Error di PHP < 8.2.
    // Ganti dengan prepare() → bind_param() → execute() yang kompatibel sejak PHP 7.4.
    $stmt = $conn->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role = 'user'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Status user diperbarui!');
    header('Location: users.php'); exit;
}

// Hapus user
if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: users.php'); exit;
    }
    $id = (int)$_GET['delete'];
    // BUG FIX: sama — execute_query() diganti bind_param/execute.
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'User dihapus!');
    header('Location: users.php'); exit;
}

$users = $conn->query("
    SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) AS total_booking
    FROM users u WHERE u.role = 'user' ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">
      <div class="section-card">
        <div class="section-title">👥 Daftar User (<?= count($users) ?>)</div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead>
              <tr><th>#</th><th>Nama</th><th>Email</th><th>No HP</th><th>Total Booking</th><th>Status</th><th>Daftar</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td class="fw-600"><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                <td><span class="badge-confirmed"><?= (int)$u['total_booking'] ?> booking</span></td>
                <td><span class="badge-<?= $u['status'] === 'active' ? 'active' : 'cancelled' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <a href="users.php?toggle=<?= (int)$u['id'] ?>&csrf_token=<?= csrfToken() ?>"
                     class="btn btn-sm btn-outline-warning py-0 me-1"
                     onclick="return confirm('Ubah status user ini?')">
                    <?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </a>
                  <button onclick="confirmDelete('users.php?delete=<?= (int)$u['id'] ?>&csrf_token=<?= csrfToken() ?>','<?= addslashes(htmlspecialchars($u['name'])) ?>')"
                          class="btn btn-sm btn-outline-danger py-0">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($users)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada user</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
