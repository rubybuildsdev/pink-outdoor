<?php
require_once '../config/config.php';
requireStaff();
$pageTitle = 'Update Stok Gear';
$active    = 'stock';

// Update stok
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id    = (int)$_POST['product_id'];
    $stock = max(0, (int)$_POST['stock']);
    // BUG FIX: execute_query() hanya ada di PHP 8.2+, menyebabkan Fatal Error (HTTP 500).
    // Ganti dengan prepare() → bind_param() → execute() yang kompatibel sejak PHP 7.4.
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $stock, $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', 'Stok berhasil diperbarui!');
    header('Location: stock.php'); exit;
}

$products = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p JOIN categories c ON p.category_id = c.id
    ORDER BY c.name, p.name
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_staff.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">
      <div class="section-card">
        <div class="section-title">📦 Update Stok Gear</div>
        <div class="table-responsive">
          <table class="table table-custom table-hover mb-0">
            <thead>
              <tr><th>Foto</th><th>Nama Gear</th><th>Kategori</th><th>Kondisi</th><th>Stok Sekarang</th><th>Update Stok</th></tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
              <tr>
                <td>
                  <img src="<?= BASE_URL ?>uploads/gear/<?= htmlspecialchars($p['image']) ?>"
                       width="50" height="38"
                       style="object-fit:cover;border-radius:6px;"
                       onerror="this.onerror=null;this.src='../assets/img/no-image.svg'">
                </td>
                <td class="fw-600"><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['cat_name']) ?></td>
                <td>
                  <span class="badge bg-light text-dark"><?= ucfirst(htmlspecialchars($p['condition'])) ?></span>
                </td>
                <td>
                  <span class="fw-700 <?= (int)$p['stock'] === 0 ? 'text-danger' : 'text-success' ?> fs-5">
                    <?= (int)$p['stock'] ?>
                  </span>
                  <?= (int)$p['stock'] === 0 ? '<span class="badge-cancelled ms-1">Habis</span>' : '' ?>
                </td>
                <td>
                  <form method="POST" class="d-flex gap-2 align-items-center">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <input type="number" name="stock" class="form-control form-control-sm"
                           style="width:80px;" value="<?= (int)$p['stock'] ?>" min="0" required>
                    <button type="submit" class="btn btn-sm btn-pink py-1 px-3"
                            onclick="return confirm('Update stok <?= addslashes(htmlspecialchars($p['name'])) ?>?')">
                      Simpan
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($products)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada produk</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../config/partials/scripts.php'; ?>
