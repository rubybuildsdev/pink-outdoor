<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Kategori Gear';
$active    = 'categories';

// Tambah / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name']);
    $slug = strtolower(str_replace(' ', '-', $name));
    $icon = trim($_POST['icon'] ?? 'bi-box');
    $id   = (int)($_POST['cat_id'] ?? 0);

    if ($id > 0) {
        // BUG FIX: execute_query() diganti prepare/bind_param/execute
        $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, icon = ? WHERE id = ?");
        $stmt->bind_param('sssi', $name, $slug, $icon, $id);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Kategori diperbarui!');
    } else {
        // BUG FIX: execute_query() diganti prepare/bind_param/execute
        $stmt = $conn->prepare("INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $slug, $icon);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Kategori ditambahkan!');
    }
    header('Location: categories.php'); exit;
}

if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: categories.php'); exit;
    }
    $id = (int)$_GET['delete'];
    // BUG FIX: execute_query() diganti prepare/bind_param/execute
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Kategori dihapus!');
    header('Location: categories.php'); exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$categories = $conn->query("
    SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS total
    FROM categories c ORDER BY c.name
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">
      <div class="row g-4">

        <!-- Form -->
        <div class="col-lg-4">
          <div class="section-card">
            <div class="section-title"><?= $edit ? '✏️ Edit Kategori' : '➕ Tambah Kategori' ?></div>
            <form method="POST">
              <?= csrfField() ?>
              <?php if ($edit): ?>
              <input type="hidden" name="cat_id" value="<?= (int)$edit['id'] ?>">
              <?php endif; ?>
              <div class="mb-3">
                <label class="form-label">Nama Kategori *</label>
                <input type="text" name="name" class="form-control" required
                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Icon (Bootstrap Icons class)</label>
                <input type="text" name="icon" class="form-control"
                       value="<?= htmlspecialchars($edit['icon'] ?? 'bi-box') ?>"
                       placeholder="bi-box">
                <small class="text-muted">Contoh: bi-house-door, bi-bag, bi-tools</small>
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn-pink"><?= $edit ? 'Update' : 'Simpan' ?></button>
                <?php if ($edit): ?>
                <a href="categories.php" class="btn-pink-outline">Batal</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>

        <!-- Tabel -->
        <div class="col-lg-8">
          <div class="section-card">
            <div class="section-title">📂 Daftar Kategori</div>
            <div class="table-responsive">
              <table class="table table-custom table-hover mb-0">
                <thead>
                  <tr><th>#</th><th>Icon</th><th>Nama</th><th>Jumlah Gear</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($categories as $c): ?>
                  <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><i class="bi <?= htmlspecialchars($c['icon']) ?> fs-5 text-pink"></i></td>
                    <td class="fw-600"><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= (int)$c['total'] ?> gear</td>
                    <td>
                      <a href="categories.php?edit=<?= (int)$c['id'] ?>"
                         class="btn btn-sm btn-outline-primary py-0 me-1">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <button onclick="confirmDelete('categories.php?delete=<?= (int)$c['id'] ?>&csrf_token=<?= csrfToken() ?>','<?= addslashes(htmlspecialchars($c['name'])) ?>')"
                              class="btn btn-sm btn-outline-danger py-0">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
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
