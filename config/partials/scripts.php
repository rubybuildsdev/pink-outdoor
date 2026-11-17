<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Sidebar toggle (Desktop Minimize & Mobile Offcanvas) ───────────────────
const sidebar  = document.getElementById('sidebar');
const toggle   = document.getElementById('sidebarToggle');
const overlay  = document.getElementById('sidebarOverlay');

function toggleSidebar() {
  if (!sidebar) return;
  if (window.innerWidth >= 992) {
    const isCollapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
  } else {
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
  }
}

if (toggle && sidebar) {
  toggle.addEventListener('click', toggleSidebar);
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }

  // Keyboard shortcut: Ctrl + B or Alt + S
  document.addEventListener('keydown', (e) => {
    // Abaikan jika user sedang mengetik di input / textarea
    const tag = e.target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

    if ((e.ctrlKey && e.key.toLowerCase() === 'b') || (e.altKey && e.key.toLowerCase() === 's')) {
      e.preventDefault();
      toggleSidebar();
    }
  });
}

// ── confirmDelete helper ─────────────────────────────────
function confirmDelete(url, name) {
  Swal.fire({
    title: 'Hapus ' + name + '?',
    text: 'Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e6067a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    borderRadius: '12px',
  }).then(r => { if (r.isConfirmed) window.location.href = url; });
}

function confirmAction(url, msg) {
  Swal.fire({
    title: msg || 'Konfirmasi',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#e6067a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  }).then(r => { if (r.isConfirmed) window.location.href = url; });
}

// ── Flash via SweetAlert ─────────────────────────────────
<?php $flash = getFlash(); if ($flash): ?>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    icon: '<?= $flash['type'] === 'success' ? 'success' : 'error' ?>',
    title: '<?= addslashes(htmlspecialchars($flash['msg'])) ?>',
    timer: 2800, showConfirmButton: false,
    toast: true, position: 'top-end',
    timerProgressBar: true,
  });
});
<?php endif; ?>
</script>
</body>
</html>
