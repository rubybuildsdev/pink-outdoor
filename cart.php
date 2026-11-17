<?php
/**
 * cart.php — Central cart handler
 * Semua aksi "Tambah ke Keranjang" dari halaman manapun POST ke sini.
 * Setelah selesai, redirect kembali ke halaman asal atau ke keranjang.
 */
require_once 'config/config.php';

$action   = $_POST['action']   ?? '';
$redirect = $_POST['redirect'] ?? 'keranjang.php';

// Sanitasi redirect — hanya boleh ke halaman internal
$allowedRedirects = ['keranjang.php','katalog.php','index.php','paket.php'];
$redirectBase = strtok(strtok($redirect, '?'), '#');
if (!in_array($redirectBase, $allowedRedirects)) {
    $redirect = 'keranjang.php';
}

// Inisialisasi cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Definisi resmi paket (mencegah client-side price tampering)
$packageDefinitions = [
    'solo'   => ['name' => 'Paket Solo Hiker',     'price' => 75000,  'icon' => '⛰️'],
    'couple' => ['name' => 'Paket Couple Camp',    'price' => 120000, 'icon' => '💑'],
    'group'  => ['name' => 'Paket Group Explorer', 'price' => 220000, 'icon' => '👥'],
    'family' => ['name' => 'Paket Family Weekend', 'price' => 180000, 'icon' => '👨‍👩‍👧‍👦'],
];

// ── TAMBAH PRODUK KE KERANJANG ──────────────────────────────────────────────
if ($action === 'add_product') {
    $pid = (int)($_POST['product_id'] ?? 0);
    if ($pid > 0) {
        $s = $conn->prepare("SELECT id,name,image,price_per_day,stock FROM products WHERE id=? AND status='available' AND stock>0");
        $s->bind_param('i',$pid); $s->execute();
        $p = $s->get_result()->fetch_assoc(); $s->close();
        if ($p) {
            $key = 'p_'.$pid;
            if (!isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key] = [
                    'type'       => 'product',
                    'product_id' => $pid,
                    'name'       => $p['name'],
                    'image'      => $p['image'] ?: 'no-image.png',
                    'price'      => (float)$p['price_per_day'],
                    'days'       => 1,
                    'start'      => date('Y-m-d'),
                    'end'        => date('Y-m-d', strtotime('+1 day')),
                    'qty'        => 1,
                ];
            } else {
                $_SESSION['cart'][$key]['qty']++;
            }
            setFlash('success', htmlspecialchars($p['name']).' ditambahkan ke keranjang!');
        } else {
            setFlash('error', 'Gear tidak tersedia atau stok habis.');
        }
    }
    header('Location: '.$redirect); exit;
}

// ── TAMBAH PAKET KE KERANJANG ────────────────────────────────────────────────
if ($action === 'add_paket') {
    $paketId = $_POST['paket_id'] ?? '';
    if (isset($packageDefinitions[$paketId])) {
        $pkgInfo    = $packageDefinitions[$paketId];
        $pkgName    = $pkgInfo['name'];
        $pkgPrice   = (float)$pkgInfo['price'];
        $pkgIcon    = $pkgInfo['icon'];

        // Cari atau buatkan product_id di database agar valid saat di-checkout & berelasi
        $stmtFind = $conn->prepare("SELECT id, price_per_day, stock FROM products WHERE name = ? LIMIT 1");
        $stmtFind->bind_param('s', $pkgName);
        $stmtFind->execute();
        $existing = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($existing) {
            $productId = (int)$existing['id'];
            $pkgPrice  = (float)$existing['price_per_day'];
        } else {
            // Ambil / buat kategori 'Paket Camping'
            $cStmt = $conn->prepare("SELECT id FROM categories WHERE slug='paket-camping' LIMIT 1");
            $cStmt->execute();
            $catRow = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
            if ($catRow) {
                $catId = (int)$catRow['id'];
            } else {
                $cIns = $conn->prepare("INSERT INTO categories (name, slug, icon) VALUES ('Paket Camping', 'paket-camping', 'bi-boxes')");
                $cIns->execute();
                $catId = $conn->insert_id;
                $cIns->close();
            }

            // Insert produk paket ke tabel products
            $desc = 'Paket sewa camping lengkap ' . $pkgName;
            $insP = $conn->prepare("INSERT INTO products (category_id, name, description, price_per_day, stock, `condition`, status) VALUES (?, ?, ?, ?, 10, 'excellent', 'available')");
            $insP->bind_param('issd', $catId, $pkgName, $desc, $pkgPrice);
            $insP->execute();
            $productId = $conn->insert_id;
            $insP->close();
        }

        $key = 'paket_'.$paketId;
        if (!isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key] = [
                'type'       => 'paket',
                'product_id' => $productId,
                'name'       => $pkgName,
                'image'      => 'no-image.png',
                'icon'       => $pkgIcon,
                'price'      => $pkgPrice,
                'days'       => 1,
                'start'      => date('Y-m-d'),
                'end'        => date('Y-m-d', strtotime('+1 day')),
                'qty'        => 1,
            ];
        } else {
            $_SESSION['cart'][$key]['qty']++;
        }
        setFlash('success', htmlspecialchars($pkgName).' ditambahkan ke keranjang!');
    } else {
        setFlash('error', 'Paket sewa tidak valid.');
    }
    header('Location: '.$redirect); exit;
}

// ── HAPUS ITEM ────────────────────────────────────────────────────────────────
if ($action === 'remove') {
    $key = $_POST['key'] ?? '';
    if ($key && isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
        setFlash('success', 'Item dihapus dari keranjang.');
    }
    header('Location: keranjang.php'); exit;
}

// ── UPDATE DURASI / QTY ─────────────────────────────────────────────────────────────
if ($action === 'update_qty') {
    $key   = $_POST['key'] ?? '';
    $delta = (int)($_POST['delta'] ?? 0);
    $directQty = isset($_POST['qty']) ? (int)$_POST['qty'] : null;

    if ($key && isset($_SESSION['cart'][$key])) {
        $currQty = (int)($_SESSION['cart'][$key]['qty'] ?? 1);
        $newQty  = ($directQty !== null) ? $directQty : ($currQty + $delta);

        if ($newQty <= 0) {
            $removedName = $_SESSION['cart'][$key]['name'] ?? 'Item';
            unset($_SESSION['cart'][$key]);
            setFlash('info', htmlspecialchars($removedName) . ' dihapus dari keranjang.');
        } else {
            // Cek ketersediaan stok produk dari database jika tipe produk
            if (($_SESSION['cart'][$key]['type'] ?? '') === 'product') {
                $pid = (int)($_SESSION['cart'][$key]['product_id'] ?? 0);
                $chk = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
                $chk->bind_param('i', $pid);
                $chk->execute();
                $pRow = $chk->get_result()->fetch_assoc();
                $chk->close();

                $availableStock = $pRow ? (int)$pRow['stock'] : 0;
                if ($newQty > $availableStock) {
                    $_SESSION['cart'][$key]['qty'] = max(1, $availableStock);
                    setFlash('error', 'Jumlah ' . htmlspecialchars($_SESSION['cart'][$key]['name']) . ' melebihi stok yang tersedia (Maks: ' . $availableStock . ').');
                    header('Location: keranjang.php');
                    exit;
                }
            }

            $_SESSION['cart'][$key]['qty'] = $newQty;
            setFlash('success', 'Jumlah ' . htmlspecialchars($_SESSION['cart'][$key]['name']) . ' diperbarui (' . $newQty . ' unit).');
        }
    }
    header('Location: keranjang.php'); exit;
}

if ($action === 'update') {
    foreach ($_POST['days'] ?? [] as $key => $days) {
        $days = max(1,(int)$days);
        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['days'] = $days;
            $start = $_SESSION['cart'][$key]['start'] ?? date('Y-m-d');
            $_SESSION['cart'][$key]['end'] = date('Y-m-d', strtotime($start.' +'.$days.' days'));
        }
    }
    setFlash('success','Keranjang diperbarui.');
    header('Location: keranjang.php'); exit;
}

// ── KOSONGKAN ─────────────────────────────────────────────────────────────────
if ($action === 'clear') {
    $_SESSION['cart'] = [];
    setFlash('success','Keranjang dikosongkan.');
    header('Location: keranjang.php'); exit;
}

// Fallback
header('Location: keranjang.php'); exit;
