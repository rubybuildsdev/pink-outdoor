<?php
// FIX #5: Staff dapat akses Kelola Gear — lihat daftar gear, update stok, dan update foto.
// Staff TIDAK dapat tambah/hapus gear (itu kewenangan admin).
require_once '../config/config.php';
requireStaff();

$pageTitle = 'Kelola Gear';
$active    = 'products';

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ---- Update data gear oleh staff (tanpa tambah/hapus) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    requireCsrf();
    $id    = (int)$_POST['product_id'];
    $stock = max(0, (int)$_POST['stock']);
    $cond  = in_array($_POST['condition'], ['excellent','good','fair']) ? $_POST['condition'] : 'good';
    $stat  = in_array($_POST['status'], ['available','unavailable'])   ? $_POST['status']    : 'available';

    // Gambar lama sebagai default
    $image = trim($_POST['old_image'] ?? '') ?: 'no-image.png';

    // Upload foto (opsional, sama logikanya dengan admin)
    $gearDir = __DIR__ . '/../uploads/gear/';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allow       = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo       = new finfo(FILEINFO_MIME_TYPE);
        $mime        = $finfo->file($_FILES['image']['tmp_name']);

        if (in_array($ext, $allow) && in_array($mime, $allowedMime) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            if (is_writable($gearDir)) {
                $newFilename = 'gear_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $gearDir . $newFilename)) {
                    $image = $newFilename;
                }
            } else {
                setFlash('error', 'Folder uploads/gear/ tidak dapat ditulis. Hubungi admin server.');
                header('Location: products.php?edit=' . $id); exit;
            }
        } else {
            setFlash('error', 'Format tidak valid (JPG/PNG/WEBP, maks 5MB).');
            header('Location: products.php?edit=' . $id); exit;
        }
    }

    $stmt = $conn->prepare("UPDATE products SET stock=?, `condition`=?, status=?, image=? WHERE id=?");
    $stmt->bind_param('isssi', $stock, $cond, $stat, $image, $id);
    $stmt->execute();
    $stmt->close();

    setFlash('success', 'Data gear berhasil diperbarui!');
    header('Location: products.php'); exit;
}

// ---- Edit: ambil data ----
$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $conn->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id=?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ---- Daftar gear ----
$products = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p JOIN categories c ON p.category_id=c.id
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Info role staff -->
      <div class="alert alert-info border-0 mb-4 d-flex align-items-center gap-2" style="border-radius:var(--radius);font-size:.87rem;">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <span>Staff dapat <strong>mengupdate stok, kondisi, status, dan foto</strong> gear. Untuk menambah atau menghapus gear, hubungi Admin.</span>
      </div>

      <!-- Form Edit (muncul saat klik tombol Edit) -->
      <?php if ($edit): ?>
      <div class="section-card mb-4">
        <div class="section-title">✏️ Edit Gear: <?= htmlspecialchars($edit['name']) ?></div>
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="product_id" value="<?= (int)$edit['id'] ?>">
          <input type="hidden" name="old_image"   value="<?= htmlspecialchars($edit['image']) ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nama Gear <span class="text-muted">(read-only)</span></label>
              <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($edit['name']) ?>" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">Kategori <span class="text-muted">(read-only)</span></label>
              <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($edit['cat_name']) ?>" disabled>
            </div>
            <div class="col-md-2">
              <label class="form-label">Stok *</label>
              <input type="number" name="stock" class="form-control" min="0" required
                     value="<?= (int)$edit['stock'] ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Kondisi</label>
              <select name="condition" class="form-select">
                <?php foreach (['excellent', 'good', 'fair'] as $c): ?>
                <option value="<?= $c ?>" <?= $edit['condition'] === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="available"   <?= $edit['status'] === 'available'   ? 'selected' : '' ?>>Tersedia</option>
                <option value="unavailable" <?= $edit['status'] === 'unavailable' ? 'selected' : '' ?>>Tidak Tersedia</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Foto Gear <span class="text-muted">(JPG/PNG/WEBP, maks 5MB)</span></label>
              <input type="file" name="image" class="form-control"
                     accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($edit['image']) && $edit['image'] !== 'no-image.png'): ?>
              <div class="mt-2 d-flex align-items-center gap-2">
                <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($edit['image']) ?>"
                     height="50" style="border-radius:8px;object-fit:cover;border:1px solid #ddd;"
                     onerror="this.style.display='none'">
                <small class="text-muted">Foto saat ini. Pilih file baru untuk mengganti.</small>
              </div>
              <?php else: ?>
              <small class="text-muted d-block mt-1">Belum ada foto. Upload foto gear di sini.</small>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-pink">
              <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
            </button>
            <a href="products.php" class="btn-pink-outline">Batal</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Tabel Gear -->
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="section-title mb-0">🎒 Daftar Gear (<?= count($products) ?>)</div>
        </div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead>
              <tr><th>#</th><th>Foto</th><th>Nama</th><th>Kategori</th><th>Harga/Hari</th><th>Stok</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
              <tr>
                <td class="text-muted small"><?= (int)$p['id'] ?></td>
                <td>
                  <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($p['image']) ?>"
                       width="52" height="40"
                       style="object-fit:cover;border-radius:6px;border:1px solid #eee;"
                       onerror="this.onerror=null;this.src='<?= BASE_URL ?>assets/img/no-image.svg'">
                </td>
                <td class="fw-600"><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['cat_name']) ?></td>
                <td>Rp <?= number_format($p['price_per_day'], 0, ',', '.') ?></td>
                <td>
                  <span class="fw-700 <?= (int)$p['stock'] === 0 ? 'text-danger' : 'text-success' ?>">
                    <?= (int)$p['stock'] ?>
                  </span>
                  <?= (int)$p['stock'] === 0 ? '<span class="badge-cancelled ms-1">Habis</span>' : '' ?>
                </td>
                <td><span class="badge bg-light text-dark"><?= ucfirst(htmlspecialchars($p['condition'])) ?></span></td>
                <td>
                  <span class="badge-<?= $p['status'] === 'available' ? 'active' : 'cancelled' ?>">
                    <?= $p['status'] === 'available' ? 'Tersedia' : 'Tidak Tersedia' ?>
                  </span>
                </td>
                <td>
                  <a href="products.php?edit=<?= (int)$p['id'] ?>"
                     class="btn btn-sm btn-outline-primary py-0">
                    <i class="bi bi-pencil"></i> Edit
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($products)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada gear</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
