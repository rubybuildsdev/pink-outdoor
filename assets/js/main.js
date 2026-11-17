// ============================================
// PINK OUTDOOR — Main JS
// ============================================

// Sidebar toggle (mobile)
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      if (window.innerWidth >= 992) {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed ? '1' : '0');
      } else {
        sidebar.classList.toggle('open');
      }
    });
    document.addEventListener('click', (e) => {
      if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // Auto-hide flash alert
  const flash = document.getElementById('flashAlert');
  if (flash) setTimeout(() => flash.style.opacity = '0', 3500);

  // Booking: hitung total harga otomatis
  const startDate = document.getElementById('start_date');
  const endDate   = document.getElementById('end_date');
  const priceDay  = document.getElementById('price_per_day');
  const totalBox  = document.getElementById('total_price_display');
  const totalHidden = document.getElementById('total_price');
  const daysBox   = document.getElementById('total_days');

  function calcTotal() {
    if (!startDate || !endDate || !priceDay) return;
    const s = new Date(startDate.value);
    const e = new Date(endDate.value);
    if (isNaN(s) || isNaN(e) || e <= s) {
      if (totalBox) totalBox.textContent = '-';
      if (daysBox)  daysBox.value = '';
      return;
    }
    const days  = Math.ceil((e - s) / (1000 * 60 * 60 * 24));
    const price = parseFloat(priceDay.value) || 0;
    const total = days * price;
    if (totalBox)    totalBox.textContent = 'Rp ' + total.toLocaleString('id-ID');
    if (totalHidden) totalHidden.value    = total;
    if (daysBox)     daysBox.value        = days;
  }

  if (startDate) startDate.addEventListener('change', calcTotal);
  if (endDate)   endDate.addEventListener('change', calcTotal);

  // Set min date untuk start_date = hari ini
  if (startDate) startDate.min = new Date().toISOString().split('T')[0];
  if (startDate) {
    startDate.addEventListener('change', () => {
      if (endDate) endDate.min = startDate.value;
    });
  }
});

// Konfirmasi delete via SweetAlert
function confirmDelete(url, itemName) {
  Swal.fire({
    title: 'Hapus ' + (itemName || 'item') + '?',
    text: 'Data tidak bisa dikembalikan setelah dihapus.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e91e8c',
    cancelButtonColor:  '#6c757d',
    confirmButtonText:  'Ya, Hapus!',
    cancelButtonText:   'Batal'
  }).then((result) => {
    if (result.isConfirmed) window.location.href = url;
  });
}

// Konfirmasi aksi status booking
function confirmAction(url, msg) {
  Swal.fire({
    title: 'Konfirmasi',
    text: msg || 'Lanjutkan aksi ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#e91e8c',
    cancelButtonColor:  '#6c757d',
    confirmButtonText:  'Ya',
    cancelButtonText:   'Batal'
  }).then((result) => {
    if (result.isConfirmed) window.location.href = url;
  });
}
