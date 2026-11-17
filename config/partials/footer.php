<footer class="site-footer mt-auto">
  <div class="container py-5">
    <div class="row g-4 g-lg-5">

      <!-- Brand & Summary -->
      <div class="col-lg-4 col-md-6">
        <div class="footer-brand-title mb-2">
          PINK<span style="color:var(--pink);">OUTDOOR</span> <small class="text-muted fw-normal" style="font-size:.78rem;">GARUT</small>
        </div>
        <p style="color:#94a3b8;font-size:.875rem;line-height:1.7;margin-bottom:1.25rem;">
          Pusat penyewaan perlengkapan camping dan pendakian gunung resmi di Kabupaten Garut. Tenda waterproof, sleeping bag higienis, dan peralatan gunung terawat siap pakai.
        </p>
        <div class="d-flex gap-2">
          <a href="https://wa.me/6281234567890" class="btn btn-sm btn-outline-light px-3 py-1 fw-700" style="border-color:#334155;background:#1e293b;border-radius:var(--radius);font-size:.8rem;" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp CS
          </a>
          <a href="https://maps.google.com" target="_blank" rel="noopener" class="btn btn-sm btn-outline-light px-3 py-1" style="border-color:#334155;background:#1e293b;border-radius:var(--radius);font-size:.8rem;">
            <i class="bi bi-geo-alt me-1 text-pink"></i>Google Maps
          </a>
        </div>
      </div>

      <!-- Basecamp Lokasi & Jam Buka -->
      <div class="col-lg-3 col-md-6">
        <div class="footer-heading">Basecamp & Jam Layanan</div>
        <ul class="list-unstyled" style="font-size:.86rem;color:#cbd5e1;line-height:1.75;">
          <li class="mb-2">
            <i class="bi bi-geo-alt-fill me-2 text-pink"></i>
            Jl. Patriot No. 12, Tarogong Kidul, Garut (Dekat Simpang Lima)
          </li>
          <li class="mb-2">
            <i class="bi bi-clock-fill me-2 text-pink"></i>
            Buka Setiap Hari: <strong>07.00 – 21.00 WIB</strong>
          </li>
          <li>
            <i class="bi bi-truck me-2 text-pink"></i>
            Drop Point: Stasiun Garut & Jalur Papandayan / Guntur
          </li>
        </ul>
      </div>

      <!-- Navigasi Cepat -->
      <div class="col-lg-2 col-md-6">
        <div class="footer-heading">Menu Sewa</div>
        <ul class="list-unstyled" style="font-size:.86rem;line-height:2;">
          <li><a href="<?= BASE_URL ?>katalog.php"><i class="bi bi-chevron-right me-1 text-pink" style="font-size:.7rem;"></i>Katalog Gear</a></li>
          <li><a href="<?= BASE_URL ?>paket.php"><i class="bi bi-chevron-right me-1 text-pink" style="font-size:.7rem;"></i>Paket Camping</a></li>
          <li><a href="<?= BASE_URL ?>cara-sewa.php"><i class="bi bi-chevron-right me-1 text-pink" style="font-size:.7rem;"></i>Alur & Cara Sewa</a></li>
          <li><a href="<?= BASE_URL ?>syarat.php"><i class="bi bi-chevron-right me-1 text-pink" style="font-size:.7rem;"></i>Syarat Jaminan</a></li>
          <li><a href="<?= BASE_URL ?>faq.php"><i class="bi bi-chevron-right me-1 text-pink" style="font-size:.7rem;"></i>Tanya Jawab (FAQ)</a></li>
        </ul>
      </div>

      <!-- Metode Pembayaran & Keamanan -->
      <div class="col-lg-3 col-md-6">
        <div class="footer-heading">Metode Pembayaran Resmi</div>
        <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin-bottom:.85rem;">
          Mendukung pembayaran transfer bank, e-wallet, dan QRIS otomatis:
        </p>
        <div class="d-flex flex-wrap gap-1 mb-3">
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">BCA</span>
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">BRI</span>
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">MANDIRI</span>
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">QRIS</span>
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">GOPAY</span>
          <span class="badge bg-light text-dark fw-700 px-2 py-1" style="font-size:.7rem;">DANA</span>
        </div>
        <div class="p-2 rounded" style="background:#1e293b;border:1px solid #334155;font-size:.76rem;color:#cbd5e1;">
          <i class="bi bi-shield-check text-pink me-1"></i>Transaksi resmi tercatat dengan invoice digital dan bukti sewa sah.
        </div>
      </div>

    </div>
  </div>

  <div class="footer-bottom">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        © <?= date('Y') ?> <strong class="text-white">PINK OUTDOOR GEAR RENT</strong>. Hak cipta dilindungi.
      </div>
      <div>
        Basecamp Resmi Pendakian Garut, Jawa Barat
      </div>
    </div>
  </div>
</footer>

<!-- Floating WhatsApp Widget -->
<a href="https://wa.me/6281234567890?text=Halo%20Pink%20Outdoor,%20saya%20ingin%20tanya%20seputar%20sewa%20gear%20camping" 
   class="floating-wa-btn" 
   target="_blank" 
   rel="noopener" 
   aria-label="Chat WhatsApp Pink Outdoor"
   title="Konsultasi Sewa via WhatsApp">
  <i class="bi bi-whatsapp"></i>
  <span class="floating-wa-text">Chat CS</span>
</a>
