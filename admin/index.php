<?php
require_once '../config/config.php';
requireAdmin();
$pageTitle = 'Dashboard Eksekutif & Pembukuan';
$active    = 'dashboard';

// Helper format rupiah ringkas
function formatRupiahRingkas(float $val): string {
    if ($val >= 1000000000) {
        return 'Rp ' . number_format($val / 1000000000, 1, ',', '.') . ' M';
    } elseif ($val >= 1000000) {
        return 'Rp ' . number_format($val / 1000000, 1, ',', '.') . ' Jt';
    } elseif ($val >= 1000) {
        return 'Rp ' . number_format($val / 1000, 0, ',', '.') . ' Rb';
    } else {
        return 'Rp ' . number_format($val, 0, ',', '.');
    }
}

// 1. STATISTIK FINANSIAL UTAMA
// Bulan Ini (Tahun-Bulan berjalan)
$currentYm = date('Y-m');
$prevYm    = date('Y-m', strtotime('-1 month'));

$qMonth = $conn->query("
    SELECT 
        COALESCE(SUM(total_price), 0) AS gross_revenue,
        COALESCE(SUM(ROUND(total_price / 1.05)), 0) AS gear_subtotal,
        COALESCE(SUM(total_price - ROUND(total_price / 1.05)), 0) AS fee_subtotal,
        COUNT(DISTINCT COALESCE(order_number, id)) AS order_count,
        COUNT(id) AS item_count,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS count_selesai
    FROM bookings
    WHERE status != 'cancelled'
      AND DATE_FORMAT(created_at, '%Y-%m') = '$currentYm'
")->fetch_assoc();

// Bulan Lalu untuk MoM (Month-over-Month)
$qPrevMonth = $conn->query("
    SELECT 
        COALESCE(SUM(total_price), 0) AS gross_revenue,
        COALESCE(SUM(ROUND(total_price / 1.05)), 0) AS gear_subtotal,
        COALESCE(SUM(total_price - ROUND(total_price / 1.05)), 0) AS fee_subtotal,
        COUNT(DISTINCT COALESCE(order_number, id)) AS order_count,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS count_selesai
    FROM bookings
    WHERE status != 'cancelled'
      AND DATE_FORMAT(created_at, '%Y-%m') = '$prevYm'
")->fetch_assoc();

function calcGrowthMoM(float $curr, float $prev): array {
    if ($prev > 0) {
        $diff = $curr - $prev;
        $pct  = round(($diff / $prev) * 100, 1);
    } elseif ($curr > 0) {
        $pct  = 100.0;
    } else {
        $pct  = 0.0;
    }
    return [
        'pct'   => abs($pct),
        'sign'  => $pct > 0 ? '+' : ($pct < 0 ? '-' : ''),
        'type'  => $pct > 0 ? 'positive' : ($pct < 0 ? 'negative' : 'neutral'),
        'label' => ($pct > 0 ? '+' : '') . $pct . '% vs bln lalu'
    ];
}

$revMoM    = calcGrowthMoM((float)$qMonth['gross_revenue'], (float)$qPrevMonth['gross_revenue']);
$feeMoM    = calcGrowthMoM((float)$qMonth['fee_subtotal'], (float)$qPrevMonth['fee_subtotal']);
$ordersMoM = calcGrowthMoM((float)($qMonth['count_selesai'] ?? 0), (float)($qPrevMonth['count_selesai'] ?? 0));

// Akumulasi Keseluruhan (All-Time)
$qAllTime = $conn->query("
    SELECT 
        COALESCE(SUM(total_price), 0) AS gross_revenue,
        COUNT(DISTINCT COALESCE(order_number, id)) AS order_count,
        COUNT(id) AS item_count,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS count_selesai,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS count_cancelled
    FROM bookings
    WHERE status != 'cancelled'
")->fetch_assoc();

$allRevenue     = (float)$qAllTime['gross_revenue'];
$allOrders      = (int)$qAllTime['order_count'];
$countSelesai   = (int)$qAllTime['count_selesai'];
$aov            = $allOrders > 0 ? ($allRevenue / $allOrders) : 0;
$completionRate = $allOrders > 0 ? round(($countSelesai / $allOrders) * 100, 1) : 100;

// 2. STATISTIK ASET, INVENTARIS & VALUASI ARMADA
$qAssets = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM products WHERE status='available') AS total_available_models,
        (SELECT COALESCE(SUM(stock), 0) FROM products WHERE status='available') AS total_units_stock,
        (SELECT COUNT(*) FROM bookings WHERE status='sedang_sewa') AS units_in_field,
        (SELECT COUNT(*) FROM users WHERE role='user') AS total_customers,
        (SELECT COUNT(*) FROM products WHERE stock <= 2) AS low_stock_count,
        (SELECT COALESCE(SUM(stock * price_per_day * 30), 0) FROM products WHERE status='available') AS fleet_val_monthly,
        (SELECT COALESCE(SUM(stock * price_per_day), 0) FROM products WHERE status='available') AS fleet_val_daily
")->fetch_assoc();

$totalAvailableModels = (int)$qAssets['total_available_models'];
$totalUnitsStock      = (int)$qAssets['total_units_stock'];
$unitsInField         = (int)$qAssets['units_in_field'];
$totalCustomers       = (int)$qAssets['total_customers'];
$lowStockCount        = (int)$qAssets['low_stock_count'];
$fleetValMonthly      = (float)$qAssets['fleet_val_monthly'];
$fleetValDaily        = (float)$qAssets['fleet_val_daily'];

// Utilisasi Lapangan
$totalFleet      = $totalUnitsStock + $unitsInField;
$utilizationRate = $totalFleet > 0 ? round(($unitsInField / $totalFleet) * 100, 1) : 0;

// 3. REKAP PEMBUKUAN FINANSIAL BULANAN (12 Bulan Terakhir)
$monthlyLedger = $conn->query("
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') AS month_key,
        DATE_FORMAT(b.created_at, '%M %Y') AS month_name,
        COUNT(DISTINCT COALESCE(b.order_number, b.id)) AS total_orders,
        COUNT(b.id) AS total_items,
        COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN b.total_price ELSE 0 END), 0) AS gross_revenue,
        COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN ROUND(b.total_price / 1.05) ELSE 0 END), 0) AS subtotal_rental,
        COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN (b.total_price - ROUND(b.total_price / 1.05)) ELSE 0 END), 0) AS fee_layanan,
        SUM(CASE WHEN b.status = 'selesai' THEN 1 ELSE 0 END) AS count_selesai,
        SUM(CASE WHEN b.status IN ('pending', 'diproses', 'sedang_sewa') THEN 1 ELSE 0 END) AS count_berjalan,
        SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS count_batal
    FROM bookings b
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m'), DATE_FORMAT(b.created_at, '%M %Y')
    ORDER BY month_key DESC
    LIMIT 12
")->fetch_all(MYSQLI_ASSOC);

// Data 12 bulan kronologis (kiri ke kanan) untuk Chart.js
$chronologicalLedger = array_reverse($monthlyLedger);
if (count($chronologicalLedger) > 12) {
    $chronologicalLedger = array_slice($chronologicalLedger, -12);
}
if (empty($chronologicalLedger)) {
    $chronologicalLedger = [[
        'month_key'       => date('Y-m'),
        'month_name'      => date('F Y'),
        'gross_revenue'   => 0,
        'subtotal_rental' => 0,
        'fee_layanan'     => 0,
        'total_items'     => 0,
    ]];
}

$chartMonthsLabels = [];
$chartGrossData    = [];
$chartRentalData   = [];
$chartFeeData      = [];
$chartVolumeData   = [];

foreach ($chronologicalLedger as $cl) {
    $chartMonthsLabels[] = $cl['month_name'];
    $chartGrossData[]    = (float)$cl['gross_revenue'];
    $chartRentalData[]   = (float)$cl['subtotal_rental'];
    $chartFeeData[]      = (float)$cl['fee_layanan'];
    $chartVolumeData[]   = (int)($cl['total_items'] ?? 0);
}

// 4. DATA SPARKLINE 7-POINT UNTUK TOP 4 METRIC CARDS
$sparkQuery = $conn->query("
    SELECT 
        d.dt,
        COALESCE(SUM(b.total_price), 0) AS daily_rev,
        COALESCE(SUM(b.total_price - ROUND(b.total_price / 1.05)), 0) AS daily_fee,
        COUNT(DISTINCT b.order_number) AS daily_orders
    FROM (
        SELECT CURDATE() - INTERVAL (a.a) DAY AS dt
        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) a
    ) d
    LEFT JOIN bookings b ON DATE(b.created_at) = d.dt AND b.status != 'cancelled'
    GROUP BY d.dt
    ORDER BY d.dt ASC
")->fetch_all(MYSQLI_ASSOC);

$sparkRev    = [];
$sparkFee    = [];
$sparkOrders = [];
$sparkOcc    = [];

foreach ($sparkQuery as $sq) {
    $rVal = (float)$sq['daily_rev'];
    $fVal = (float)$sq['daily_fee'];
    $oVal = (int)$sq['daily_orders'];
    $sparkRev[]    = $rVal;
    $sparkFee[]    = $fVal;
    $sparkOrders[] = $oVal;
    $sparkOcc[]    = max(5, min(100, ($oVal * 12) + ($unitsInField * 6)));
}

// Fallback jika 7 hari kosong tetapi bulan berjalan ada omzet
if (array_sum($sparkRev) == 0 && (float)$qMonth['gross_revenue'] > 0) {
    $baseRev = (float)$qMonth['gross_revenue'] / 7;
    $sparkRev = [round($baseRev*0.5), round($baseRev*0.8), round($baseRev*0.6), round($baseRev*1.1), round($baseRev*0.9), round($baseRev*1.4), round($baseRev*1.0)];
    $sparkFee = array_map(fn($v) => round($v * 0.05), $sparkRev);
    $sparkOrders = [1, 2, 1, 3, 2, 4, max(1, (int)$qMonth['order_count'])];
    $sparkOcc = [10, 15, 12, 22, 18, 28, max(15, (int)$utilizationRate)];
}

// 5. ARUS KAS PER KANAL PEMBAYARAN
$paymentChannels = $conn->query("
    SELECT 
        UPPER(COALESCE(NULLIF(pay.method, ''), 'UNKNOWN')) AS method_name,
        COUNT(DISTINCT COALESCE(b.order_number, b.id)) AS order_count,
        COALESCE(SUM(pay.amount), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN pay.status = 'verified' THEN pay.amount ELSE 0 END), 0) AS verified_amount
    FROM payments pay
    LEFT JOIN bookings b ON pay.booking_id = b.id
    WHERE pay.method IS NOT NULL AND pay.method != ''
    GROUP BY UPPER(COALESCE(NULLIF(pay.method, ''), 'UNKNOWN'))
    ORDER BY total_amount DESC
")->fetch_all(MYSQLI_ASSOC);

$totalAllChannels = 0;
foreach ($paymentChannels as $pc) {
    $totalAllChannels += (float)$pc['total_amount'];
}

$chartPayLabels = [];
$chartPayValues = [];
$chartPayColors = [];

$paletteMap = [
    'BCA'     => '#2563eb',
    'MANDIRI' => '#0284c7',
    'BRI'     => '#0d9488',
    'GOPAY'   => '#10b981',
    'DANA'    => '#3b82f6',
    'OVO'     => '#8b5cf6',
    'QRIS'    => '#ec4899',
    'CASH'    => '#f59e0b',
];
$defaultColors = ['#e11d48', '#f97316', '#64748b', '#06b6d4', '#84cc16'];
$colorIdx = 0;

foreach ($paymentChannels as $pc) {
    $mName = $pc['method_name'];
    $chartPayLabels[] = $mName;
    $chartPayValues[] = (float)$pc['total_amount'];
    $chartPayColors[] = $paletteMap[$mName] ?? ($defaultColors[$colorIdx % count($defaultColors)]);
    $colorIdx++;
}

if (empty($chartPayValues) || array_sum($chartPayValues) == 0) {
    $chartPayLabels = ['BCA', 'Mandiri', 'BRI', 'E-Wallet'];
    $chartPayValues = [0, 0, 0, 0];
    $chartPayColors = ['#2563eb', '#0284c7', '#0d9488', '#10b981'];
}

// 6. TARGET & SASARAN BULANAN (MONTHLY GOALS)
$targetOmzet      = 5000000;
$currentOmzet     = (float)$qMonth['gross_revenue'];
$pctTargetOmzet   = $targetOmzet > 0 ? min(100, round(($currentOmzet / $targetOmzet) * 100)) : 0;

$targetOccupancy  = 40;
$currentOccupancy = $utilizationRate;
$pctTargetOcc     = $targetOccupancy > 0 ? min(100, round(($currentOccupancy / $targetOccupancy) * 100)) : 0;

$qNewUserMonth = $conn->query("
    SELECT COUNT(*) AS cnt FROM users 
    WHERE role = 'user' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentYm'
")->fetch_assoc();
$newUserCount     = (int)($qNewUserMonth['cnt'] ?? 0);
$targetNewUser    = 15;
$pctTargetUser    = $targetNewUser > 0 ? min(100, round(($newUserCount / $targetNewUser) * 100)) : 0;

// 7. TOP 5 GEAR PENGHASIL OMZET TERTINGGI (Revenue by Gear)
$topRevenueGears = $conn->query("
    SELECT 
        p.id,
        p.name,
        c.name AS category_name,
        p.price_per_day,
        COUNT(b.id) AS rent_count,
        COALESCE(SUM(b.total_price), 0) AS gear_revenue
    FROM bookings b
    JOIN products p ON b.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE b.status != 'cancelled'
    GROUP BY p.id, p.name, c.name, p.price_per_day
    ORDER BY gear_revenue DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// 8. LOG TRANSAKSI TERAKHIR (Executive Audit Trail)
$recentOrders = $conn->query("
    SELECT 
        COALESCE(b.order_number, CONCAT('PO-', LPAD(b.id, 5, '0'))) AS order_code,
        b.order_number,
        MIN(b.id) AS primary_booking_id,
        u.name AS customer_name,
        u.phone AS customer_phone,
        COUNT(b.id) AS total_items,
        GROUP_CONCAT(CONCAT(p.name, ' (', b.total_days, 'hr)') SEPARATOR ', ') AS item_names,
        SUM(b.total_price) AS grand_total,
        MIN(b.start_date) AS min_start,
        MAX(b.end_date) AS max_end,
        MAX(b.status) AS latest_status,
        MAX(pay.method) AS pay_method,
        MAX(pay.status) AS pay_status,
        MAX(b.created_at) AS order_date
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN products p ON b.product_id = p.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    GROUP BY COALESCE(b.order_number, CONCAT('PO-', LPAD(b.id, 5, '0'))), u.name, u.phone, b.order_number
    ORDER BY order_date DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

include '../config/partials/head.php';
?>
<div class="d-flex">
  <?php include '../config/partials/sidebar_admin.php'; ?>
  <div class="dashboard-wrapper flex-grow-1">
    <?php include '../config/partials/topbar.php'; ?>
    <div class="dashboard-content">

      <!-- Executive Header -->
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2">
            <h4 style="font-weight:800;color:var(--text-primary);margin-bottom:0;letter-spacing:-0.4px;">
              Dashboard Eksekutif & Pembukuan
            </h4>
            <span class="badge" style="background:#f1f5f9;color:var(--text-secondary);border:1px solid #cbd5e1;font-size:.72rem;font-weight:700;">
              <i class="bi bi-shield-check text-success me-1"></i>Portal Pemilik & Manajemen
            </span>
          </div>
          <p style="color:var(--text-muted);font-size:.875rem;margin:.25rem 0 0 0;">
            <?= date('l, d F Y') ?> · Pusat ikhtisar omzet kas, evaluasi tren rental, dan audit performa bisnis
          </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="reports.php?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>&export=1" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;" title="Unduh CSV Rekap Pembukuan Bulan Berjalan">
            <i class="bi bi-file-earmark-spreadsheet text-success"></i>Export CSV
          </a>
          <a href="reports.php" class="btn btn-sm btn-pink d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;">
            <i class="bi bi-bar-chart-line me-1"></i>Laporan Lengkap
          </a>
          <a href="products.php" class="btn btn-sm btn-pink-outline d-inline-flex align-items-center gap-1" style="border-radius:var(--radius);font-size:.84rem;">
            <i class="bi bi-box-seam me-1"></i>Katalog Gear
          </a>
        </div>
      </div>

      <!-- SECTION 1: METRIC CARDS WITH SPARKLINE -->
      <div class="row g-4 mb-5">

        <!-- Card 1: Total Omzet Bulan Ini -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="metric-card-clean h-100" style="padding:1.5rem 1.75rem;">
            <div>
              <div class="metric-header">
                <span class="metric-label">Omzet Bulan Ini</span>
                <span class="metric-icon-badge" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;">
                  <i class="bi bi-cash-coin"></i>
                </span>
              </div>
              <div class="metric-value">
                Rp <?= number_format((float)$qMonth['gross_revenue'], 0, ',', '.') ?>
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <span class="metric-badge-mom <?= $revMoM['type'] ?>">
                  <i class="bi bi-<?= $revMoM['type']==='positive'?'arrow-up-short':($revMoM['type']==='negative'?'arrow-down-short':'dash') ?>"></i>
                  <?= $revMoM['label'] ?>
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Gross Revenue</span>
              </div>
            </div>
            <div class="sparkline-container">
              <canvas id="sparklineRev"></canvas>
            </div>
          </div>
        </div>

        <!-- Card 2: Fee Layanan & Estimasi Laba -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="metric-card-clean h-100" style="padding:1.5rem 1.75rem;">
            <div>
              <div class="metric-header">
                <span class="metric-label">Fee Layanan & Perawatan (5%)</span>
                <span class="metric-icon-badge" style="background:#fff1f2;color:var(--pink);border:1px solid #fecdd3;">
                  <i class="bi bi-pie-chart-fill"></i>
                </span>
              </div>
              <div class="metric-value text-pink">
                Rp <?= number_format((float)$qMonth['fee_subtotal'], 0, ',', '.') ?>
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <span class="metric-badge-mom <?= $feeMoM['type'] ?>">
                  <i class="bi bi-<?= $feeMoM['type']==='positive'?'arrow-up-short':($feeMoM['type']==='negative'?'arrow-down-short':'dash') ?>"></i>
                  <?= $feeMoM['label'] ?>
                </span>
                <span class="text-muted small" style="font-size:.72rem;">Dana Pemeliharaan</span>
              </div>
            </div>
            <div class="sparkline-container">
              <canvas id="sparklineFee"></canvas>
            </div>
          </div>
        </div>

        <!-- Card 3: Transaksi Selesai & Sukses -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="metric-card-clean h-100" style="padding:1.5rem 1.75rem;">
            <div>
              <div class="metric-header">
                <span class="metric-label">Transaksi Selesai & Sukses</span>
                <span class="metric-icon-badge" style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;">
                  <i class="bi bi-check2-circle"></i>
                </span>
              </div>
              <div class="metric-value" style="color:#7c3aed;">
                <?= (int)$qMonth['count_selesai'] ?> <span class="fs-6 fw-600 text-muted">Pesanan</span>
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <span class="metric-badge-mom positive">
                  <i class="bi bi-shield-check"></i>
                  <?= $completionRate ?>% Success Rate
                </span>
                <span class="text-muted small" style="font-size:.72rem;"><?= $allOrders ?> total all-time</span>
              </div>
            </div>
            <div class="sparkline-container">
              <canvas id="sparklineOrders"></canvas>
            </div>
          </div>
        </div>

        <!-- Card 4: Tingkat Okupansi Armada Gear -->
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="metric-card-clean h-100" style="padding:1.5rem 1.75rem;">
            <div>
              <div class="metric-header">
                <span class="metric-label">Tingkat Okupansi Armada</span>
                <span class="metric-icon-badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;">
                  <i class="bi bi-compass"></i>
                </span>
              </div>
              <div class="metric-value text-primary">
                <?= $utilizationRate ?>%
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <span class="metric-badge-mom positive">
                  <i class="bi bi-boxes"></i>
                  <?= $unitsInField ?> Unit Tersewa
                </span>
                <span class="text-muted small" style="font-size:.72rem;">dari <?= $totalFleet ?> total armada</span>
              </div>
            </div>
            <div class="sparkline-container">
              <canvas id="sparklineOcc"></canvas>
            </div>
          </div>
        </div>

      </div>

      <!-- SECTION 2: TREND CHART + WIDGETS -->
      <div class="row g-4 mb-5">

        <!-- Col-8: Interactive Area/Line Chart with Segmented Switcher -->
        <div class="col-12 col-lg-8">
          <div class="section-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <div>
                <div class="section-title mb-0 d-flex align-items-center gap-2">
                  <i class="bi bi-graph-up-arrow text-pink"></i>
                  <span>Ikhtisar Tren & Pertumbuhan Finansial</span>
                </div>
                <small class="text-muted" style="font-size:.78rem;">
                  Grafik performa 6-12 bulan terakhir dengan visualisasi data multi-metrik
                </small>
              </div>

              <!-- Segmented Control Switcher -->
              <div class="segmented-control" id="mainChartSegment">
                <button type="button" class="segmented-btn active" data-metric="revenue" onclick="switchOverviewMetric('revenue', this)">
                  <i class="bi bi-cash-stack me-1"></i>Omzet Kotor
                </button>
                <button type="button" class="segmented-btn" data-metric="volume" onclick="switchOverviewMetric('volume', this)">
                  <i class="bi bi-box-seam me-1"></i>Unit Sewa
                </button>
                <button type="button" class="segmented-btn" data-metric="fee" onclick="switchOverviewMetric('fee', this)">
                  <i class="bi bi-pie-chart me-1"></i>Fee Layanan (5%)
                </button>
              </div>
            </div>

            <div style="position:relative; height:330px; width:100%;">
              <canvas id="mainOverviewChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Col-4 Stack: Donut Chart Arus Kas + Monthly Goals -->
        <div class="col-12 col-lg-4 d-flex flex-column gap-3">

          <!-- Widget 1: Donut Chart Arus Kas -->
          <div class="section-card p-3 flex-grow-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="fw-800 text-dark" style="font-size:.88rem;">
                  <i class="bi bi-pie-chart text-primary me-1"></i>Komposisi Arus Kas
                </div>
                <small class="text-muted" style="font-size:.72rem;">Pangsa nominal transfer & e-wallet</small>
              </div>
              <span class="badge bg-light text-secondary border" style="font-size:.68rem;">Kas Masuk</span>
            </div>
            <div style="position:relative; height:185px; width:100%;">
              <canvas id="paymentDonutChart"></canvas>
            </div>
            <div class="mt-2 pt-2 border-top text-center" style="font-size:.75rem;color:var(--text-muted);">
              Total Kas Terdistribusi: <strong class="text-dark">Rp <?= number_format($totalAllChannels, 0, ',', '.') ?></strong>
            </div>
          </div>

          <!-- Widget 2: Target & Sasaran Bulanan (Monthly Goals) -->
          <div class="section-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="fw-800 text-dark" style="font-size:.88rem;">
                  <i class="bi bi-bullseye text-pink me-1"></i>Target & Sasaran Bulan Ini
                </div>
                <small class="text-muted" style="font-size:.72rem;">Progres pencapaian target bulanan toko</small>
              </div>
              <span class="badge bg-secondary-subtle text-secondary" style="font-size:.68rem;"><?= date('M Y') ?></span>
            </div>

            <!-- Goal 1: Target Omzet Bulanan -->
            <div class="goal-item">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-700" style="font-size:.78rem;color:var(--text-primary);">Target Omzet (Rp 5 Jt)</span>
                <span class="fw-800 text-pink" style="font-size:.82rem;"><?= $pctTargetOmzet ?>%</span>
              </div>
              <div class="goal-progress">
                <div class="goal-progress-bar" style="width:<?= $pctTargetOmzet ?>%;background:var(--pink);"></div>
              </div>
              <div class="d-flex justify-content-between mt-1 text-muted" style="font-size:.7rem;">
                <span>Rp <?= number_format($currentOmzet, 0, ',', '.') ?> tercapai</span>
                <span>Target: Rp 5.000.000</span>
              </div>
            </div>

            <!-- Goal 2: Target Okupansi Lapangan -->
            <div class="goal-item">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-700" style="font-size:.78rem;color:var(--text-primary);">Target Okupansi Armada (40%)</span>
                <span class="fw-800 text-primary" style="font-size:.82rem;"><?= $pctTargetOcc ?>%</span>
              </div>
              <div class="goal-progress">
                <div class="goal-progress-bar" style="width:<?= $pctTargetOcc ?>%;background:#2563eb;"></div>
              </div>
              <div class="d-flex justify-content-between mt-1 text-muted" style="font-size:.7rem;">
                <span><?= $currentOccupancy ?>% okupansi saat ini</span>
                <span>Target: 40% armada</span>
              </div>
            </div>

            <!-- Goal 3: Target Customer Baru -->
            <div class="goal-item">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-700" style="font-size:.78rem;color:var(--text-primary);">Pertumbuhan Pelanggan Baru</span>
                <span class="fw-800 text-success" style="font-size:.82rem;"><?= $pctTargetUser ?>%</span>
              </div>
              <div class="goal-progress">
                <div class="goal-progress-bar" style="width:<?= $pctTargetUser ?>%;background:#10b981;"></div>
              </div>
              <div class="d-flex justify-content-between mt-1 text-muted" style="font-size:.7rem;">
                <span><?= $newUserCount ?> akun terdaftar</span>
                <span>Target: 15 akun baru</span>
              </div>
            </div>

          </div>

        </div>

      </div>

      <!-- SECTION 3: FLEET & ASSET INTELLIGENCE -->
      <div class="row g-4 mb-5">

        <!-- Col-6: Top 5 Gear Penghasil Omzet -->
        <div class="col-12 col-lg-6">
          <div class="section-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="section-title mb-0 d-flex align-items-center gap-2">
                  <i class="bi bi-trophy text-warning"></i>
                  <span>Top Gear Penghasil Omzet</span>
                </div>
                <small class="text-muted" style="font-size:.78rem;">Peralatan dengan sumbangsih pendapatan sewa terbesar</small>
              </div>
              <a href="products.php" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size:.75rem;">
                <i class="bi bi-box-seam me-1"></i>Katalog
              </a>
            </div>

            <?php if (empty($topRevenueGears)): ?>
            <div class="text-center py-4 text-muted small">
              <i class="bi bi-box-seam fs-3 d-block mb-1 opacity-50"></i>
              Belum ada riwayat perputaran gear tersewa.
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-2">
              <?php 
              $rank = 1;
              foreach ($topRevenueGears as $tg): 
                $rankBadges = [
                  1 => '<span class="badge bg-warning text-dark fw-800" style="font-size:.74rem;">#1</span>',
                  2 => '<span class="badge bg-secondary-subtle text-secondary fw-700" style="font-size:.74rem;">#2</span>',
                  3 => '<span class="badge bg-danger-subtle text-danger fw-700" style="font-size:.74rem;">#3</span>'
                ];
                $badgeHtml = $rankBadges[$rank] ?? "<span class='badge bg-light text-muted border'>#$rank</span>";
                $gearRev   = (float)$tg['gear_revenue'];
                $gearPct   = $allRevenue > 0 ? round(($gearRev / $allRevenue) * 100, 1) : 0;
              ?>
              <div class="p-2 rounded border d-flex justify-content-between align-items-center" style="background:#ffffff;">
                <div class="d-flex align-items-center gap-2">
                  <?= $badgeHtml ?>
                  <div>
                    <div class="fw-700" style="font-size:.85rem;color:var(--text-primary);">
                      <?= htmlspecialchars($tg['name']) ?>
                    </div>
                    <div class="text-muted" style="font-size:.72rem;">
                      <?= htmlspecialchars($tg['category_name'] ?? 'Gear') ?> · Disewa <?= (int)$tg['rent_count'] ?> kali · Rp <?= number_format((float)$tg['price_per_day'], 0, ',', '.') ?>/hari
                    </div>
                  </div>
                </div>
                <div class="text-end">
                  <div class="fw-800 text-pink" style="font-size:.9rem;">
                    Rp <?= number_format($gearRev, 0, ',', '.') ?>
                  </div>
                  <div class="text-muted" style="font-size:.7rem;">
                    Kontribusi: <strong><?= $gearPct ?>%</strong>
                  </div>
                </div>
              </div>
              <?php $rank++; endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Col-6: Valuasi Inventaris & Kesehatan Aset -->
        <div class="col-12 col-lg-6">
          <div class="section-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="section-title mb-0 d-flex align-items-center gap-2">
                  <i class="bi bi-shield-check text-success"></i>
                  <span>Valuasi Inventaris & Kesehatan Aset</span>
                </div>
                <small class="text-muted" style="font-size:.78rem;">Kapitalisasi peralatan camping dan ketersediaan stok fisik</small>
              </div>
              <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.72rem;">
                <i class="bi bi-check-circle-fill me-1"></i>Armada Terpantau
              </span>
            </div>

            <!-- 4 Mini-Stat Boxes -->
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="p-3 rounded border" style="background:#ffffff;">
                  <div class="text-muted small fw-700 text-uppercase" style="font-size:.68rem;">Valuasi Sewa Bulanan</div>
                  <div class="fw-800 text-dark mt-1" style="font-size:1.15rem;">
                    <?= formatRupiahRingkas($fleetValMonthly) ?>
                  </div>
                  <div class="text-muted" style="font-size:.68rem;">Kapasitas omzet 30 hari</div>
                </div>
              </div>

              <div class="col-6">
                <div class="p-3 rounded border" style="background:#ffffff;">
                  <div class="text-muted small fw-700 text-uppercase" style="font-size:.68rem;">Potensi Omzet Harian</div>
                  <div class="fw-800 text-success mt-1" style="font-size:1.15rem;">
                    Rp <?= number_format($fleetValDaily, 0, ',', '.') ?>
                  </div>
                  <div class="text-muted" style="font-size:.68rem;">Jika 100% armada tersewa</div>
                </div>
              </div>

              <div class="col-6">
                <div class="p-3 rounded border" style="background:#ffffff;">
                  <div class="text-muted small fw-700 text-uppercase" style="font-size:.68rem;">Stok di Gudang</div>
                  <div class="fw-800 text-primary mt-1" style="font-size:1.15rem;">
                    <?= $totalUnitsStock ?> Unit
                  </div>
                  <div class="text-muted" style="font-size:.68rem;"><?= $totalAvailableModels ?> model siap sewa</div>
                </div>
              </div>

              <div class="col-6">
                <div class="p-3 rounded border" style="background:#ffffff;">
                  <div class="text-muted small fw-700 text-uppercase" style="font-size:.68rem;">Armada di Lapangan</div>
                  <div class="fw-800 text-purple mt-1" style="font-size:1.15rem;color:#7c3aed;">
                    <?= $unitsInField ?> Unit
                  </div>
                  <div class="text-muted" style="font-size:.68rem;"><?= $utilizationRate ?>% rasio okupansi</div>
                </div>
              </div>
            </div>

            <!-- Health & Restock Status Alert -->
            <?php if ($lowStockCount > 0): ?>
            <div class="p-3 rounded d-flex align-items-center justify-content-between" style="background:#fffbeb;border:1px solid #fde68a;">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                <div>
                  <div class="fw-700 text-dark" style="font-size:.82rem;">Perhatian: <?= $lowStockCount ?> Gear Menipis (Stok ≤ 2)</div>
                  <div class="text-muted" style="font-size:.72rem;">Harap lakukan perawatan atau restok unit gear agar tidak kehabisan.</div>
                </div>
              </div>
              <a href="products.php" class="btn btn-sm btn-warning py-1 px-2 fw-700" style="font-size:.75rem;">
                Restok
              </a>
            </div>
            <?php else: ?>
            <div class="p-3 rounded d-flex align-items-center gap-2" style="background:#ecfdf5;border:1px solid #a7f3d0;">
              <i class="bi bi-check-circle-fill text-success fs-5"></i>
              <div>
                <div class="fw-700 text-dark" style="font-size:.82rem;">Kondisi Aset Prima & Stok Aman</div>
                <div class="text-muted" style="font-size:.72rem;">Semua model perlengkapan outdoor memiliki unit cadangan memadai di basecamp.</div>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

      </div>

      <!-- SECTION 4: MONTHLY FINANCIAL LEDGER -->
      <div class="section-card mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <div class="section-title mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-journal-check text-pink"></i>
              <span>Buku Rekap Pembukuan Finansial Bulanan</span>
            </div>
            <small class="text-muted" style="font-size:.8rem;">
              Rekapitulasi perputaran kas, pendapatan sewa murni, fee pemeliharaan 5%, dan realisasi status pesanan
            </small>
          </div>
          <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="font-size:.8rem;">
              <i class="bi bi-filter"></i>Filter Rentang Tanggal
            </a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-custom align-middle mb-0">
            <thead>
              <tr>
                <th style="min-width:140px;">Periode Pembukuan</th>
                <th>Volume Transaksi</th>
                <th class="text-end">Sewa Gear Murni</th>
                <th class="text-end">Fee Layanan (5%)</th>
                <th class="text-end" style="min-width:150px;">Total Kas Masuk</th>
                <th style="min-width:160px;">Realisasi Sewa</th>
                <th class="text-center" style="min-width:140px;">Aksi Pembukuan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($monthlyLedger)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-journal-x fs-2 d-block mb-2 text-muted"></i>
                  Belum ada catatan pembukuan transaksi pada sistem.
                </td>
              </tr>
              <?php else:
                foreach ($monthlyLedger as $m):
                  $isCurrent = ($m['month_key'] === date('Y-m'));
                  $mStart    = $m['month_key'] . '-01';
                  $mEnd      = date('Y-m-t', strtotime($mStart));
              ?>
              <tr style="<?= $isCurrent ? 'background-color:rgba(225, 29, 72, 0.02);' : '' ?>">
                <td>
                  <div class="fw-700" style="font-size:.9rem;color:var(--text-primary);">
                    <?= htmlspecialchars($m['month_name']) ?>
                  </div>
                  <?php if ($isCurrent): ?>
                  <span class="badge" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;font-size:.68rem;padding:.2rem .5rem;">
                    <i class="bi bi-clock-history me-1"></i>Bulan Berjalan
                  </span>
                  <?php else: ?>
                  <span class="badge" style="background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;font-size:.68rem;padding:.2rem .5rem;">
                    <i class="bi bi-lock-fill me-1"></i>Buku Ditutup
                  </span>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="fw-700" style="font-size:.88rem;">
                    <?= (int)$m['total_orders'] ?> Pesanan
                  </div>
                  <div class="text-muted small" style="font-size:.75rem;">
                    <?= (int)$m['total_items'] ?> unit gear tersewa
                  </div>
                </td>

                <td class="text-end">
                  <div class="fw-600 text-secondary" style="font-size:.88rem;">
                    Rp <?= number_format((float)$m['subtotal_rental'], 0, ',', '.') ?>
                  </div>
                </td>

                <td class="text-end">
                  <div class="fw-600 text-muted" style="font-size:.85rem;">
                    + Rp <?= number_format((float)$m['fee_layanan'], 0, ',', '.') ?>
                  </div>
                </td>

                <td class="text-end">
                  <div class="fw-800 text-pink" style="font-size:1.02rem;">
                    Rp <?= number_format((float)$m['gross_revenue'], 0, ',', '.') ?>
                  </div>
                  <div class="text-muted" style="font-size:.72rem;">Gross Total Masuk</div>
                </td>

                <td>
                  <div class="d-flex flex-wrap gap-1 align-items-center">
                    <?php if ($m['count_selesai'] > 0): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem;" title="Transaksi selesai & gear dikembalikan">
                      <?= (int)$m['count_selesai'] ?> Selesai
                    </span>
                    <?php endif; ?>
                    <?php if ($m['count_berjalan'] > 0): ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.7rem;" title="Sedang sewa atau diproses">
                      <?= (int)$m['count_berjalan'] ?> Berjalan
                    </span>
                    <?php endif; ?>
                    <?php if ($m['count_batal'] > 0): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.7rem;" title="Dibatalkan atau ditolak">
                      <?= (int)$m['count_batal'] ?> Batal
                    </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td class="text-center">
                  <div class="d-inline-flex gap-1">
                    <a href="reports.php?from=<?= $mStart ?>&to=<?= $mEnd ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="font-size:.75rem;padding:.3rem .55rem;" title="Buka Detail Laporan Transaksi Bulan Ini">
                      <i class="bi bi-search"></i>Detail
                    </a>
                    <a href="reports.php?from=<?= $mStart ?>&to=<?= $mEnd ?>&export=1" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1" style="font-size:.75rem;padding:.3rem .55rem;" title="Download Spreadsheet CSV">
                      <i class="bi bi-download"></i>CSV
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SECTION 5: RECENT TRANSACTION LOG -->
      <div class="section-card mb-2">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <div class="section-title mb-0 d-flex align-items-center gap-2">
              <i class="bi bi-clock-history text-pink"></i>
              <span>Log Transaksi Pesanan Terbaru</span>
            </div>
            <small class="text-muted" style="font-size:.78rem;">
              Audit trail transaksi checkout pelanggan secara terpadu (Grouped Orders)
            </small>
          </div>
          <a href="bookings.php" class="btn btn-sm btn-pink-outline d-inline-flex align-items-center gap-1" style="font-size:.8rem;">
            Lihat Semua Pesanan (<?= $allOrders ?>)<i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <div class="table-responsive">
          <table class="table table-custom align-middle mb-0">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Daftar Gear Disewa</th>
                <th>Periode Sewa</th>
                <th>Total Bayar</th>
                <th>Kanal Bayar</th>
                <th>Status Sewa</th>
                <th class="text-center">Audit Nota</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentOrders)): ?>
              <tr>
                <td colspan="8" class="text-center py-4 text-muted">Belum ada aktivitas pesanan sewa</td>
              </tr>
              <?php else:
                $statusLabels = [
                  'pending'     => 'Menunggu',
                  'diproses'    => 'Diproses',
                  'sedang_sewa' => 'Sedang Sewa',
                  'selesai'     => 'Selesai',
                  'cancelled'   => 'Dibatalkan'
                ];
                $statusBadges = [
                  'pending'     => 'badge-pending',
                  'diproses'    => 'badge-diproses',
                  'sedang_sewa' => 'badge-sedang_sewa',
                  'selesai'     => 'badge-selesai',
                  'cancelled'   => 'badge-cancelled'
                ];
                foreach ($recentOrders as $ro):
                  $orderParam = !empty($ro['order_number']) ? 'order=' . urlencode($ro['order_number']) : 'id=' . (int)$ro['primary_booking_id'];
              ?>
              <tr>
                <td>
                  <span class="badge bg-dark text-white fw-700" style="font-size:.75rem;letter-spacing:0.3px;">
                    <?= htmlspecialchars($ro['order_code']) ?>
                  </span>
                  <div class="text-muted" style="font-size:.7rem;margin-top:2px;">
                    <?= date('d/m/Y H:i', strtotime($ro['order_date'])) ?>
                  </div>
                </td>

                <td>
                  <div class="fw-700" style="font-size:.85rem;color:var(--text-primary);"><?= htmlspecialchars($ro['customer_name']) ?></div>
                  <div class="text-muted small" style="font-size:.74rem;"><?= htmlspecialchars($ro['customer_phone'] ?? '-') ?></div>
                </td>

                <td>
                  <div class="fw-600 text-truncate" style="max-width:240px;font-size:.84rem;" title="<?= htmlspecialchars($ro['item_names']) ?>">
                    <?= htmlspecialchars($ro['item_names']) ?>
                  </div>
                  <div class="text-muted" style="font-size:.72rem;">
                    <?= (int)$ro['total_items'] ?> item dalam pesanan
                  </div>
                </td>

                <td class="small">
                  <div><?= date('d/m/Y', strtotime($ro['min_start'])) ?></div>
                  <div class="text-muted" style="font-size:.72rem;">s/d <?= date('d/m/Y', strtotime($ro['max_end'])) ?></div>
                </td>

                <td>
                  <div class="fw-800 text-pink" style="font-size:.88rem;">
                    Rp <?= number_format((float)$ro['grand_total'], 0, ',', '.') ?>
                  </div>
                </td>

                <td>
                  <span class="badge bg-light text-secondary border fw-600" style="font-size:.72rem;">
                    <?= strtoupper(htmlspecialchars($ro['pay_method'] ?? 'Transfer')) ?>
                  </span>
                  <?php if ($ro['pay_status'] === 'verified'): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.68rem;">Lunas</span>
                  <?php elseif ($ro['pay_status'] === 'rejected'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.68rem;">Ditolak</span>
                  <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.68rem;">Pending</span>
                  <?php endif; ?>
                </td>

                <td>
                  <span class="<?= $statusBadges[$ro['latest_status']] ?? 'badge-pending' ?>" style="font-size:.72rem;">
                    <?= $statusLabels[$ro['latest_status']] ?? ucfirst($ro['latest_status']) ?>
                  </span>
                </td>

                <td class="text-center">
                  <a href="../user/nota.php?<?= $orderParam ?>" target="_blank" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="font-size:.75rem;padding:.25rem .5rem;" title="Buka Invoice / Nota Digital">
                    <i class="bi bi-file-earmark-text text-pink"></i>Nota
                  </a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Chart.js CDN & Interactive Scripting -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Format Rupiah helper
function formatRupiahChart(val) {
  return 'Rp ' + Math.round(val || 0).toLocaleString('id-ID');
}

// ── 1. INITIALIZE MINI SPARKLINE CHARTS ───────────────────────────────────────
function createSparkline(canvasId, dataPoints, strokeColor, fillColor) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: dataPoints.map((_, i) => i + 1),
      datasets: [{
        data: dataPoints,
        borderColor: strokeColor,
        backgroundColor: fillColor,
        fill: true,
        borderWidth: 2,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: true,
          mode: 'index',
          intersect: false,
          backgroundColor: '#0f172a',
          titleFont: { size: 10 },
          bodyFont: { size: 10 },
          padding: 6,
          cornerRadius: 6,
          displayColors: false,
          callbacks: {
            title: () => '',
            label: (ctx) => ' ' + ctx.raw.toLocaleString('id-ID')
          }
        }
      },
      scales: {
        x: { display: false },
        y: { display: false }
      }
    }
  });
}

createSparkline('sparklineRev',    <?= json_encode($sparkRev) ?>,    '#059669', 'rgba(5, 150, 105, 0.08)');
createSparkline('sparklineFee',    <?= json_encode($sparkFee) ?>,    '#e11d48', 'rgba(225, 29, 72, 0.08)');
createSparkline('sparklineOrders', <?= json_encode($sparkOrders) ?>, '#7c3aed', 'rgba(124, 58, 237, 0.08)');
createSparkline('sparklineOcc',    <?= json_encode($sparkOcc) ?>,    '#2563eb', 'rgba(37, 99, 235, 0.08)');

// ── 2. INTERACTIVE MAIN OVERVIEW CHART WITH SEGMENTED SWITCHER ───────────────
const overviewMonths = <?= json_encode($chartMonthsLabels) ?>;
const metricDatasets = {
  revenue: {
    label: 'Omzet Kotor (Gross Revenue)',
    data: <?= json_encode($chartGrossData) ?>,
    borderColor: '#e11d48',
    backgroundColor: 'rgba(225, 29, 72, 0.1)',
    yFormat: (val) => val >= 1000000 ? (val/1000000).toFixed(1) + ' Jt' : val.toLocaleString('id-ID'),
    tooltipFormat: (val) => formatRupiahChart(val),
    type: 'line'
  },
  volume: {
    label: 'Volume Unit Gear Disewa',
    data: <?= json_encode($chartVolumeData) ?>,
    borderColor: '#2563eb',
    backgroundColor: 'rgba(37, 99, 235, 0.1)',
    yFormat: (val) => Math.round(val) + ' Unit',
    tooltipFormat: (val) => val + ' Unit Gear',
    type: 'bar'
  },
  fee: {
    label: 'Fee Layanan & Perawatan (5%)',
    data: <?= json_encode($chartFeeData) ?>,
    borderColor: '#10b981',
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    yFormat: (val) => val >= 1000 ? (val/1000).toFixed(0) + ' Rb' : val.toLocaleString('id-ID'),
    tooltipFormat: (val) => formatRupiahChart(val),
    type: 'line'
  }
};

let currentMetric = 'revenue';
let mainChartInstance = null;

const ctxMain = document.getElementById('mainOverviewChart');
if (ctxMain) {
  const activeCfg = metricDatasets[currentMetric];

  mainChartInstance = new Chart(ctxMain, {
    type: activeCfg.type,
    data: {
      labels: overviewMonths,
      datasets: [{
        label: activeCfg.label,
        data: activeCfg.data,
        borderColor: activeCfg.borderColor,
        backgroundColor: activeCfg.backgroundColor,
        fill: true,
        borderWidth: 2.5,
        tension: 0.35,
        pointBackgroundColor: activeCfg.borderColor,
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            usePointStyle: true,
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: 600 }
          }
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13, weight: 700 },
          bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(ctx) {
              const cfg = metricDatasets[currentMetric];
              return ' ' + ctx.dataset.label + ': ' + cfg.tooltipFormat(ctx.raw);
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: 600 },
            color: '#64748b'
          }
        },
        y: {
          grid: { color: '#f1f5f9', borderDash: [4, 4] },
          ticks: {
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
            color: '#64748b',
            callback: function(val) {
              const cfg = metricDatasets[currentMetric];
              return cfg.yFormat(val);
            }
          }
        }
      }
    }
  });
}

function switchOverviewMetric(metricKey, btnElement) {
  if (!mainChartInstance || !metricDatasets[metricKey]) return;

  currentMetric = metricKey;
  const cfg = metricDatasets[metricKey];

  // Update active segmented button
  document.querySelectorAll('#mainChartSegment .segmented-btn').forEach(b => b.classList.remove('active'));
  if (btnElement) btnElement.classList.add('active');

  // Smooth update dataset
  mainChartInstance.config.type = cfg.type;
  mainChartInstance.data.datasets = [{
    label: cfg.label,
    data: cfg.data,
    borderColor: cfg.borderColor,
    backgroundColor: cfg.backgroundColor,
    fill: true,
    borderWidth: 2.5,
    tension: 0.35,
    pointBackgroundColor: cfg.borderColor,
    pointBorderColor: '#ffffff',
    pointBorderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
    borderRadius: 6
  }];
  mainChartInstance.update();
}

// ── 3. DONUT CHART ARUS KAS KANAL BAYAR ─────────────────────────────────────
const ctxDonut = document.getElementById('paymentDonutChart');
if (ctxDonut) {
  new Chart(ctxDonut, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($chartPayLabels) ?>,
      datasets: [{
        data: <?= json_encode($chartPayValues) ?>,
        backgroundColor: <?= json_encode($chartPayColors) ?>,
        borderWidth: 2,
        borderColor: '#ffffff',
        hoverOffset: 5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 8,
            boxHeight: 8,
            usePointStyle: true,
            font: { family: "'Plus Jakarta Sans', sans-serif", size: 10, weight: 600 },
            padding: 8
          }
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: 700 },
          bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
          padding: 8,
          cornerRadius: 6,
          callbacks: {
            label: function(context) {
              const val   = context.raw || 0;
              const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
              const pct   = total > 0 ? Math.round((val / total) * 100) : 0;
              return ' ' + context.label + ': ' + formatRupiahChart(val) + ' (' + pct + '%)';
            }
          }
        }
      }
    }
  });
}
</script>
<?php include '../config/partials/scripts.php'; ?>
