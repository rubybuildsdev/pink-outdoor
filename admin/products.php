<?php
require_once '../config/config.php';
requireAdmin();

$pageTitle = 'Kelola Gear';
$active    = 'products';

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ---- PROSES POST (Tambah / Edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $cat_id = (int)$_POST['category_id'];
    $name   = trim($_POST['name']);
    $desc   = trim($_POST['description'] ?? '');
    $price  = (float)$_POST['price_per_day'];
    $stock  = max(0, (int)$_POST['stock']);
    $cond   = in_array($_POST['condition'], ['excellent','good','fair']) ? $_POST['condition'] : 'good';
    $status = in_array($_POST['status'], ['available','unavailable']) ? $_POST['status'] : 'available';
    $id     = (int)($_POST['product_id'] ?? 0);

    // Mulai dari gambar lama
    $image = trim($_POST['old_image'] ?? '') ?: 'no-image.png';

    // FIX ROOT CAUSE #3: Cek folder writable dulu
    $gearDir = __DIR__ . '/../uploads/gear/';
    if (!is_dir($gearDir)) {
        mkdir($gearDir, 0777, true);
    }

    // FIX: Wajib cek UPLOAD_ERR_OK sebelum proses file
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allow       = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo       = new finfo(FILEINFO_MIME_TYPE);
        $mime        = $finfo->file($_FILES['image']['tmp_name']);

        if (in_array($ext, $allow) && in_array($mime, $allowedMime) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
            if (!is_writable($gearDir)) {
                setFlash('error', 'Folder uploads/gear/ tidak dapat ditulis. Jalankan fix_permissions.sh di server.');
                header('Location: products.php'); exit;
            }
            $newFilename = 'gear_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $gearDir . $newFilename)) {
                // Hanya ganti $image jika file BENAR-BENAR berhasil dipindahkan
                $image = $newFilename;
            } else {
                setFlash('error', 'Gagal menyimpan foto gear. Periksa permission folder uploads/gear/.');
                header('Location: products.php' . ($id ? '?edit=' . $id : '')); exit;
            }
        } else {
            setFlash('error', 'Format tidak valid (JPG/PNG/WEBP, maks 5MB).');
            header('Location: products.php' . ($id ? '?edit=' . $id : '')); exit;
        }
    }
    // Jika tidak ada file dipilih (UPLOAD_ERR_NO_FILE), $image tetap = old_image → benar

    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE products
            SET category_id=?, name=?, description=?, price_per_day=?,
                stock=?, `condition`=?, status=?, image=?
            WHERE id=?
        ");
        $stmt->bind_param('issdisssi', $cat_id, $name, $desc, $price, $stock, $cond, $status, $image, $id);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Gear berhasil diperbarui!');
    } else {
        $stmt = $conn->prepare("
            INSERT INTO products (category_id, name, description, price_per_day, stock, `condition`, status, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issdisss', $cat_id, $name, $desc, $price, $stock, $cond, $status, $image);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Gear berhasil ditambahkan!');
    }
    header('Location: products.php'); exit;
}

// ---- Delete ----
if (isset($_GET['delete'])) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: products.php'); exit;
    }
    $id = (int)$_GET['delete'];
    // Ambil nama image sebelum dihapus (opsional: hapus file juga)
    $row = $conn->prepare("SELECT image FROM products WHERE id=?");
    $row->bind_param('i', $id); $row->execute();
    $imgRow = $row->get_result()->fetch_assoc(); $row->close();

    $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Hapus file foto jika bukan default
    if ($imgRow && $imgRow['image'] !== 'no-image.png') {
        $imgPath = __DIR__ . '/../uploads/gear/' . $imgRow['image'];
        if (file_exists($imgPath)) @unlink($imgPath);
    }
    setFlash('success', 'Gear berhasil dihapus!');
    header('Location: products.php'); exit;
}

// ---- Edit: ambil data ----
$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $conn->prepare("SELECT * FROM products WHERE id=?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ---- Filter & Pencarian Daftar ----
$q            = trim($_GET['q'] ?? '');
$catFilter    = (int)($_GET['category'] ?? 0);
$statusFilter = $_GET['status_filter'] ?? '';
$validStatuses = ['available', 'unavailable'];

$whereClauses = ["1=1"];
$params       = [];
$types        = '';

if ($q !== '') {
    $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $likeQ    = '%' . $q . '%';
    $params[] = $likeQ;
    $params[] = $likeQ;
    $types   .= 'ss';
}

if ($catFilter > 0) {
    $whereClauses[] = "p.category_id = ?";
    $params[] = $catFilter;
    $types   .= 'i';
}

if (in_array($statusFilter, $validStatuses)) {
    $whereClauses[] = "p.status = ?";
    $params[] = $statusFilter;
    $types   .= 's';
}

$sqlProducts = "
    SELECT p.*, c.name AS cat_name
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY p.id DESC
";

$stmtP = $conn->prepare($sqlProducts);
if (!empty($params)) {
    $stmtP->bind_param($types, ...$params);
}
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Form Tambah / Edit -->
      <?php if (isset($_GET['action']) || $edit): ?>
      <div class="section-card mb-4">
        <div class="section-title"><?= $edit ? '✏️ Edit Gear' : '➕ Tambah Gear Baru' ?></div>
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <?php if ($edit): ?>
          <input type="hidden" name="product_id" value="<?= (int)$edit['id'] ?>">
          <input type="hidden" name="old_image"   value="<?= htmlspecialchars($edit['image']) ?>">
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama Gear *</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Kategori *</label>
              <select name="category_id" class="form-select" required>
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)($edit['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Harga/Hari (Rp) *</label>
              <input type="number" name="price_per_day" class="form-control" required min="0" step="500"
                     value="<?= htmlspecialchars($edit['price_per_day'] ?? '') ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Deskripsi</label>
              <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-2">
              <label class="form-label">Stok *</label>
              <input type="number" name="stock" class="form-control" required min="0"
                     value="<?= (int)($edit['stock'] ?? 1) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Kondisi</label>
              <select name="condition" class="form-select">
                <?php foreach (['excellent', 'good', 'fair'] as $c): ?>
                <option value="<?= $c ?>" <?= ($edit['condition'] ?? 'good') === $c ? 'selected' : '' ?>>
                  <?= ucfirst($c) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="available"   <?= ($edit['status'] ?? '') === 'available'   ? 'selected' : '' ?>>Tersedia</option>
                <option value="unavailable" <?= ($edit['status'] ?? '') === 'unavailable' ? 'selected' : '' ?>>Tidak Tersedia</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Foto Gear <span class="text-muted">(JPG/PNG/WEBP, maks 2MB)</span></label>
              <input type="file" name="image" class="form-control"
                     accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($edit['image']) && $edit['image'] !== 'no-image.png'): ?>
              <div class="mt-2 d-flex align-items-center gap-2">
                <!-- FIX #3: Path gambar preview saat edit -->
                <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($edit['image']) ?>"
                     height="50" style="border-radius:8px;object-fit:cover;border:1px solid #ddd;"
                     onerror="this.style.display='none'">
                <small class="text-muted">Foto saat ini. Pilih file baru untuk mengganti.</small>
              </div>
              <?php else: ?>
              <small class="text-muted d-block mt-1">Biarkan kosong jika tidak ingin ganti foto.</small>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn-pink">
              <i class="bi bi-check-lg me-1"></i><?= $edit ? 'Update Gear' : 'Simpan Gear' ?>
            </button>
            <a href="products.php" class="btn-pink-outline">Batal</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Tabel Produk -->
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="section-title mb-0">🎒 Daftar Gear (<?= count($products) ?>)</div>
          <a href="products.php?action=add" class="btn-pink">
            <i class="bi bi-plus-lg me-1"></i>Tambah Gear
          </a>
        </div>

        <!-- Filter & Search Bar Admin -->
        <form method="GET" action="products.php" class="p-3 mb-3 rounded-3" style="background:var(--surface-2);border:1px solid var(--border);">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       class="form-control" placeholder="Cari nama atau deskripsi gear...">
              </div>
            </div>
            <div class="col-md-3">
              <select name="category" class="form-select form-select-sm">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="status_filter" class="form-select form-select-sm">
                <option value="">-- Semua Status --</option>
                <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Tersedia</option>
                <option value="unavailable" <?= $statusFilter === 'unavailable' ? 'selected' : '' ?>>Tidak Tersedia</option>
              </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
              <button type="submit" class="btn btn-sm btn-pink flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filter
              </button>
              <?php if ($q !== '' || $catFilter > 0 || $statusFilter !== ''): ?>
              <a href="products.php" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                <i class="bi bi-arrow-counterclockwise"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </form>
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
                  <!-- FIX #3: Gunakan BASE_URL agar path foto benar dari folder admin/ -->
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
                </td>
                <td><span class="badge bg-light text-dark"><?= ucfirst(htmlspecialchars($p['condition'])) ?></span></td>
                <td>
                  <span class="badge-<?= $p['status'] === 'available' ? 'active' : 'cancelled' ?>">
                    <?= $p['status'] === 'available' ? 'Tersedia' : 'Tidak Tersedia' ?>
                  </span>
                </td>
                <td>
                  <a href="products.php?edit=<?= (int)$p['id'] ?>"
                     class="btn btn-sm btn-outline-primary py-0 me-1">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <button onclick="confirmDelete('products.php?delete=<?= (int)$p['id'] ?>&csrf_token=<?= csrfToken() ?>','<?= addslashes(htmlspecialchars($p['name'])) ?>')"
                          class="btn btn-sm btn-outline-danger py-0">
                    <i class="bi bi-trash"></i>
                  </button>
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
