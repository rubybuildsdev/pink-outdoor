<?php
require_once 'config/config.php';
$pageTitle = 'Katalog Gear';

$catId      = (int)($_GET['cat'] ?? 0);
$q          = trim($_GET['q'] ?? '');
$sort       = $_GET['sort'] ?? 'latest';
$validSorts = ['latest', 'price_low', 'price_high', 'name_asc'];
if (!in_array($sort, $validSorts)) $sort = 'latest';

$categories = $conn->query("SELECT * FROM categories WHERE slug != 'paket-camping' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Bangun query dinamis menggunakan prepared statements
$whereClauses = ["p.status = 'available'"];
$params       = [];
$types        = '';

if ($catId > 0) {
    $whereClauses[] = "p.category_id = ?";
    $params[] = $catId;
    $types   .= 'i';
} else {
    // Sembunyikan kategori paket camping jika melihat semua gear
    $whereClauses[] = "c.slug != 'paket-camping'";
}

if ($q !== '') {
    $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $likeQ    = '%' . $q . '%';
    $params[] = $likeQ;
    $params[] = $likeQ;
    $types   .= 'ss';
}

$orderBy = match($sort) {
    'price_low'  => "p.price_per_day ASC",
    'price_high' => "p.price_per_day DESC",
    'name_asc'   => "p.name ASC",
    default      => "p.id DESC",
};

$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
        (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE product_id = p.id) AS avg_rating,
        (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) AS review_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $whereClauses) . "
        ORDER BY " . $orderBy;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flash = getFlash();
$baseUrl = BASE_URL;

// Bentuk URL redirect dengan filter aktif
$activeParams = [];
if ($catId > 0) $activeParams['cat'] = $catId;
if ($q !== '')  $activeParams['q'] = $q;
if ($sort !== 'latest') $activeParams['sort'] = $sort;
$redirectPage = 'katalog.php' . (!empty($activeParams) ? '?' . http_build_query($activeParams) : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Katalog Gear — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body style="background:#f8fafc;color:var(--text-primary);">
<?php include 'config/partials/navbar.php'; ?>

<!-- Clean White Page Header -->
<div class="py-5" style="background:#ffffff;border-bottom:1px solid #f1f5f9;">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background:#fff1f2;border:1px solid #ffe4e6;font-size:.76rem;font-weight:800;color:var(--pink);">
          <i class="bi bi-grid-3x3-gap-fill me-1"></i> KOLEKSI ALAT OUTDOOR GARUT
        </div>
        <h1 class="fw-800 text-dark mb-2" style="font-size:clamp(2rem, 3.8vw, 2.8rem);letter-spacing:-0.8px;">
          Katalog Perlengkapan Outdoor
        </h1>
        <p class="text-secondary mb-0" style="font-size:1rem;line-height:1.65;max-width:580px;">
          Tenda double-layer waterproof, carrier ergonomis, sleeping bag wangi terawat, nesting kompor, dan perlengkapan esensial siap pakai untuk petualanganmu.
        </p>
      </div>
      <div class="col-lg-5">
        <!-- Form Pencarian Langsung -->
        <form method="GET" action="katalog.php" class="d-flex gap-2">
          <?php if ($catId > 0): ?>
          <input type="hidden" name="cat" value="<?= $catId ?>">
          <?php endif; ?>
          <?php if ($sort !== 'latest'): ?>
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <?php endif; ?>
          <div class="input-group" style="box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;border-color:#e2e8f0;">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   class="form-control border-start-0 ps-0"
                   placeholder="Cari nama alat (tenda, carrier, matras...)"
                   style="border-radius:0 10px 10px 0;border-color:#e2e8f0;font-size:0.9rem;">
          </div>
          <button type="submit" class="btn-pink px-4" style="border-radius:10px;">Cari</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="container py-4">
  <!-- Bar Filter Kategori & Sortir (Sleek & Airy Bar) -->
  <div class="p-3 bg-white rounded-3 border d-flex flex-wrap justify-content-between align-items-center gap-3 my-3" 
       style="border-color:#e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
    <!-- Filter Kategori Pills -->
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <a href="katalog.php<?= $q !== '' ? '?q='.urlencode($q) : '' ?>"
         class="btn btn-sm <?= $catId===0 ? 'btn-pink' : 'btn-outline-secondary' ?>"
         style="border-radius:999px;font-size:.82rem;font-weight:700;padding:.4rem 1rem;<?= $catId!==0 ? 'background:#ffffff;border-color:#e2e8f0;color:#334155;' : '' ?>">
        <i class="bi bi-grid-fill me-1"></i> Semua Alat
      </a>
      <?php foreach ($categories as $cat): ?>
      <?php
        $catParams = ['cat' => $cat['id']];
        if ($q !== '') $catParams['q'] = $q;
        if ($sort !== 'latest') $catParams['sort'] = $sort;
        $isActive = ($catId === (int)$cat['id']);
      ?>
      <a href="katalog.php?<?= http_build_query($catParams) ?>"
         class="btn btn-sm <?= $isActive ? 'btn-pink' : 'btn-outline-secondary' ?>"
         style="border-radius:999px;font-size:.82rem;font-weight:600;padding:.4rem 1rem;<?= !$isActive ? 'background:#ffffff;border-color:#e2e8f0;color:#334155;' : '' ?>">
        <i class="bi <?= htmlspecialchars($cat['icon']) ?> me-1"></i>
        <?= htmlspecialchars($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Dropdown Sortir -->
    <div class="d-flex align-items-center gap-2 ms-auto">
      <label class="text-muted small fw-700 mb-0 d-none d-sm-inline">Urutkan:</label>
      <form method="GET" action="katalog.php" class="m-0" id="sortForm">
        <?php if ($catId > 0): ?>
        <input type="hidden" name="cat" value="<?= $catId ?>">
        <?php endif; ?>
        <?php if ($q !== ''): ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
        <?php endif; ?>
        <select name="sort" class="form-select form-select-sm"
                style="border-radius:8px;border-color:#e2e8f0;cursor:pointer;min-height:36px;font-size:.84rem;background-color:#ffffff;"
                onchange="document.getElementById('sortForm').submit()">
          <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Terbaru</option>
          <option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Tarif: Terendah</option>
          <option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Tarif: Tertinggi</option>
          <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Nama: A - Z</option>
        </select>
      </form>
    </div>
  </div>

  <!-- Indikator Pencarian / Filter Aktif -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 px-1">
    <div class="text-secondary small">
      Menampilkan <strong><?= count($products) ?></strong> perlengkapan
      <?php if ($q !== ''): ?>
        untuk pencarian "<strong><?= htmlspecialchars($q) ?></strong>"
      <?php endif; ?>
      <?php if ($catId > 0): ?>
        <?php 
          $activeCat = array_filter($categories, fn($c) => (int)$c['id'] === $catId);
          $catObj = reset($activeCat);
        ?>
        di kategori <strong><?= htmlspecialchars($catObj['name'] ?? '') ?></strong>
      <?php endif; ?>
    </div>

    <?php if ($q !== '' || $catId > 0 || $sort !== 'latest'): ?>
    <a href="katalog.php" class="btn btn-sm btn-link text-decoration-none text-pink fw-700 p-0">
      <i class="bi bi-x-circle me-1"></i>Reset Semua Filter
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($products)): ?>
  <div class="p-5 text-center bg-white rounded-4 border my-4" style="border-color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
    <div style="font-size:3.5rem;">🎒</div>
    <h5 class="fw-800 text-dark mt-3 mb-1">Gear Tidak Ditemukan</h5>
    <p class="text-secondary small mb-3">Tidak ada gear yang cocok dengan kata kunci atau filter yang dipilih.</p>
    <a href="katalog.php" class="btn-pink px-4 py-2" style="border-radius:var(--radius-pill);">
      Lihat Semua Gear
    </a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <?php foreach ($products as $p): ?>
    <div class="col-sm-6 col-lg-3">
      <?php include 'config/partials/product_card.php'; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include 'config/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
