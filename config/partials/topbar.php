<?php
// Topbar: Clean White Surface, search input, notification bell, user dropdown
?>
<div class="topbar" id="mainTopbar">
  <!-- Left: Toggle + Page Title -->
  <div class="d-flex align-items-center gap-3">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" title="Toggle Sidebar (Ctrl+B)">
      <i class="bi bi-list"></i>
    </button>
    <div>
      <div class="page-title"><?= $pageTitle ?? '' ?></div>
    </div>
  </div>

  <!-- Right: Search + Notifications + User Info -->
  <div class="d-flex align-items-center gap-2">

    <!-- Clean Search Input (hidden on mobile) -->
    <div class="topbar-search d-none d-lg-flex align-items-center">
      <div style="position:relative;">
        <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.88rem;pointer-events:none;"></i>
        <input
          type="text"
          class="form-control"
          placeholder="Cari…"
          style="padding-left:2.25rem;padding-right:1rem;height:38px;border-radius:9999px;border:1px solid #e2e8f0;background:#f8fafc;font-size:0.84rem;width:200px;transition:border-color 0.15s,box-shadow 0.15s;"
          onfocus="this.style.borderColor='#e11d48';this.style.boxShadow='0 0 0 3px rgba(225,29,72,0.08)';this.style.background='#fff';"
          onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none';this.style.background='#f8fafc';"
        />
      </div>
    </div>

    <!-- Notification Bell -->
    <div class="dropdown">
      <button
        class="btn"
        type="button"
        id="notifDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        style="width:38px;height:38px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:1.1rem;padding:0;transition:all 0.15s;position:relative;"
        onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1';"
        onmouseout="this.style.background='#fff';this.style.borderColor='#e2e8f0';"
        title="Notifikasi"
      >
        <i class="bi bi-bell"></i>
        <?php
        // Quick count: unread notifications (pending bookings + pending payments)
        $notifCount = 0;
        if (isset($conn)) {
            $nr = $conn->query("SELECT (SELECT COUNT(*) FROM bookings WHERE status='pending') + (SELECT COUNT(*) FROM payments WHERE status='pending') AS cnt");
            if ($nr) {
                $notifCount = (int)($nr->fetch_assoc()['cnt'] ?? 0);
            }
        }
        ?>
        <?php if ($notifCount > 0): ?>
        <span style="position:absolute;top:-4px;right:-4px;background:#e11d48;color:#fff;font-size:0.6rem;font-weight:800;min-width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;line-height:1;">
          <?= $notifCount > 9 ? '9+' : $notifCount ?>
        </span>
        <?php endif; ?>
      </button>
      <div
        class="dropdown-menu dropdown-menu-end shadow-sm py-0"
        aria-labelledby="notifDropdown"
        style="min-width:300px;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-top:6px;"
      >
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:800;font-size:0.9rem;color:#0f172a;">Notifikasi</span>
          <?php if ($notifCount > 0): ?>
          <span style="background:#fff1f2;color:#e11d48;border:1px solid #ffe4e6;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:700;"><?= $notifCount ?> Baru</span>
          <?php endif; ?>
        </div>
        <div style="max-height:260px;overflow-y:auto;">
          <?php
          $notifBookings = [];
          if (isset($conn)) {
              $nb = $conn->query("SELECT b.id, u.name AS uname, p.name AS pname FROM bookings b JOIN users u ON b.user_id=u.id JOIN products p ON b.product_id=p.id WHERE b.status='pending' ORDER BY b.created_at DESC LIMIT 4");
              if ($nb) $notifBookings = $nb->fetch_all(MYSQLI_ASSOC);
          }
          ?>
          <?php if (empty($notifBookings)): ?>
          <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:0.84rem;">
            <i class="bi bi-check2-circle" style="font-size:1.5rem;display:block;margin-bottom:0.35rem;"></i>
            Semua beres! Tidak ada notifikasi baru.
          </div>
          <?php else: ?>
          <?php foreach ($notifBookings as $nb): ?>
          <div style="padding:0.85rem 1.25rem;border-bottom:1px solid #f8fafc;display:flex;gap:0.75rem;align-items:flex-start;">
            <div style="width:34px;height:34px;border-radius:50%;background:#fff1f2;color:#e11d48;display:flex;align-items:center;justify-content:center;font-size:0.95rem;flex-shrink:0;">
              <i class="bi bi-calendar-check"></i>
            </div>
            <div>
              <div style="font-size:0.82rem;font-weight:700;color:#0f172a;line-height:1.3;">
                Booking baru dari <?= htmlspecialchars($nb['uname']) ?>
              </div>
              <div style="font-size:0.76rem;color:#64748b;margin-top:2px;">
                <?= htmlspecialchars($nb['pname']) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="padding:0.75rem 1.25rem;border-top:1px solid #f1f5f9;">
          <?php
          $bookingsPage = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false ? 'bookings.php' : '../admin/bookings.php';
          ?>
          <a href="<?= defined('BASE_URL') ? BASE_URL . 'admin/bookings.php' : 'bookings.php' ?>" style="display:block;text-align:center;color:#e11d48;font-size:0.82rem;font-weight:700;text-decoration:none;">
            Lihat Semua Booking <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- User Info + Dropdown -->
    <div class="dropdown">
      <button
        class="btn"
        type="button"
        id="userDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        style="display:flex;align-items:center;gap:0.6rem;background:transparent;border:none;padding:4px 8px;border-radius:10px;transition:background 0.15s;"
        onmouseover="this.style.background='#f8fafc';"
        onmouseout="this.style.background='transparent';"
      >
        <div class="d-none d-sm-block" style="text-align:right;">
          <div class="user-name"><?= htmlspecialchars(getUserName()) ?></div>
          <div class="user-role"><?= getRole() ?></div>
        </div>
        <div class="avatar"><?= strtoupper(substr(getUserName(), 0, 1)) ?></div>
        <i class="bi bi-chevron-down d-none d-md-block" style="font-size:0.72rem;color:#94a3b8;"></i>
      </button>
      <div
        class="dropdown-menu dropdown-menu-end shadow-sm py-0"
        aria-labelledby="userDropdown"
        style="min-width:220px;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-top:6px;"
      >
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;">
          <div style="font-weight:800;font-size:0.9rem;color:#0f172a;"><?= htmlspecialchars(getUserName()) ?></div>
          <div style="font-size:0.76rem;color:#64748b;margin-top:2px;text-transform:capitalize;"><?= getRole() ?></div>
        </div>
        <?php if (getRole() === 'admin'): ?>
        <a href="<?= BASE_URL ?>admin/index.php" class="dropdown-item d-flex align-items-center gap-2" style="padding:0.7rem 1.25rem;font-size:0.86rem;font-weight:600;color:#334155;">
          <i class="bi bi-speedometer2" style="color:#64748b;width:18px;text-align:center;"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>admin/staff.php" class="dropdown-item d-flex align-items-center gap-2" style="padding:0.7rem 1.25rem;font-size:0.86rem;font-weight:600;color:#334155;">
          <i class="bi bi-people" style="color:#64748b;width:18px;text-align:center;"></i> Kelola Tim
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>staff/index.php" class="dropdown-item d-flex align-items-center gap-2" style="padding:0.7rem 1.25rem;font-size:0.86rem;font-weight:600;color:#334155;">
          <i class="bi bi-speedometer2" style="color:#64748b;width:18px;text-align:center;"></i> Dashboard
        </a>
        <?php endif; ?>
        <div style="border-top:1px solid #f1f5f9;margin-top:4px;"></div>
        <a href="<?= BASE_URL ?>logout.php" class="dropdown-item d-flex align-items-center gap-2" style="padding:0.7rem 1.25rem;font-size:0.86rem;font-weight:600;color:#dc2626;">
          <i class="bi bi-box-arrow-right" style="width:18px;text-align:center;"></i> Logout
        </a>
      </div>
    </div>

  </div>
</div>
