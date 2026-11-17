# 🌸 Pink Outdoor Gear Rent

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2%20|%208.3%20|%208.4%20|%208.5+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%20|%208.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.2-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![SweetAlert2](https://img.shields.io/badge/SweetAlert2-v11-E95420?style=flat)](https://sweetalert2.github.io/)
[![Google OAuth 2.0](https://img.shields.io/badge/Google%20OAuth-2.0-4285F4?style=flat&logo=google&logoColor=white)](https://developers.google.com/identity/protocols/oauth2)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**Pink Outdoor Gear Rent** adalah aplikasi web manajemen dan penyewaan alat outdoor & perlengkapan camping modern berbasis **PHP Native (Procedural & Clean Code)** dan **MySQL/MariaDB**.

Dibangun dengan arsitektur multi-role profesional (Admin, Staff, User), sistem keamanan terintegrasi (CSRF Token, Prepared Statements, Enkripsi Bcrypt, Sanitasi MIME-Type), katalog interaktif dengan sistem ulasan & rating bintang, nota/invoice digital, serta integrasi Google OAuth 2.0.

---

## 📑 Daftar Isi

- [Fitur Utama](#-fitur-utama)
  - [Modul Administrator](#-modul-administrator-admin)
  - [Modul Staff Operasional](#-modul-staff-operasional-staff)
  - [Modul Pelanggan / User](#-modul-pelanggan--user-user)
- [Alur Status Booking (Lifecycle)](#-alur-status-booking-lifecycle)
- [Skema & Arsitektur Database](#-skema--arsitektur-database)
- [Struktur Direktori Proyek](#-struktur-direktori-proyek)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Panduan Instalasi di XAMPP](#-panduan-instalasi-di-xampp)
- [Akun Bawaan (Default Accounts)](#-akun-bawaan-default-accounts)
- [Konfigurasi Lingkungan (.env) & Google OAuth](#-konfigurasi-lingkungan-env--google-oauth)
- [Implementasi Keamanan](#-implementasi-keamanan)
- [Tech Stack](#-tech-stack)
- [Lisensi](#-lisensi)

---

## ✨ Fitur Utama

### 👑 Modul Administrator (`/admin`)
- **Dashboard Statistik & Analitik**: Pemantauan *real-time* omset/pendapatan, total pesanan, jumlah produk aktif, pengguna terdaftar, verifikasi pembayaran tertunda, dan peringatan dini stok menipis.
- **Manajemen Gear / Produk**: CRUD lengkap alat sewa, upload foto dengan hash acak unik & validasi MIME byte asli, pengaturan kondisi barang (*excellent, good, fair*), serta status ketersediaan.
- **Manajemen Kategori**: Pengelompokan alat (Tenda, Carrier, Sleeping Bag, Matras, Aksesoris, Paket Camping) dengan slug URL ramah SEO dan ikon dinamis.
- **Manajemen Booking**: Tinjau seluruh pesanan sewa, filter berdasarkan status operasional, dan detail lengkap penyewa.
- **Verifikasi Pembayaran**: Pratinjau bukti transfer interaktif, aksi verifikasi (*auto update* status booking menjadi diproses), atau penolakan dengan alasan.
- **Kelola Pengguna & Staff**: Monitoring seluruh akun pengguna, aktivasi/penonaktifan akun, dan penambahan akun staf operasional baru.
- **Laporan & Ekspor CSV**: Filter performa bisnis berdasarkan rentang tanggal kalender, daftar gear terlaris (*Top Rated & Most Rented*), serta ekspor data rekapitulasi ke format spreadsheet `.csv`.

### 👮 Modul Staff Operasional (`/staff`)
- **Dashboard Harian Petugas**: Ringkasan tugas piket (pesanan menunggu konfirmasi, gear sedang disiapkan, status masa sewa aktif, dan verifikasi bukti bayar).
- **Operasional Booking & Serah Terima**: Konfirmasi pesanan dan serah terima perlengkapan ke pelanggan (mengubah status ke `sedang_sewa`).
- **Pengembalian Gear (Returns) & Auto-Restock**: Proses *check-in* pengembalian alat saat masa sewa usai secara instan dengan **otomatisasi pemulihan jumlah stok barang**.
- **Quick Stock Update**: Pembaruan cepat jumlah unit stok dan pengecekan kondisi fisik barang pasca-pengembalian.
- **Verifikasi Pembayaran Kasir**: Validasi pembayaran langsung maupun bukti transfer pelanggan.
- **Riwayat Transaksi**: Arsip pencatatan riwayat rental yang telah selesai atau dibatalkan.

### 👤 Modul Pelanggan / User (`/user`)
- **Autentikasi Fleksibel & Single Sign-On (SSO)**:
  - Masuk/Daftar manual dengan proteksi password hashing Bcrypt.
  - **Login Instan via Google OAuth 2.0** dengan integrasi cURL aman & penukaran token otomatis.
- **Katalog Lengkap & Paket Camping Hemat**:
  - Filter kategori, pencarian kata kunci, pengurutan harga, serta tampilan badge kondisi barang.
  - Halaman khusus paket camping hemat (*Solo Hiker, Couple Camp, Group Explorer, Family Weekend*).
- **Halaman Detail Produk (`detail.php`)**:
  - Galeri pratinjau gear interaktif.
  - Informasi spesifikasi teknis lengkap, ketersediaan unit stok, dan harga sewa per hari.
  - **Sistem Ulasan & Rating Bintang**: Ulasan nyata dari penyewa dengan agregat skor rating (bintang 1–5).
- **Keranjang Belanja (Multi-Item Cart)**:
  - Tambah banyak item sekaligus serta paket hemat ke dalam keranjang belanja.
  - Hitung subtotal dan estimasi biaya secara otomatis.
- **Checkout Terpadu**:
  - Pemilihan tanggal mulai sewa dan durasi hari dengan kalkulasi total biaya dinamis.
  - Pilihan metode penerimaan: **Ambil di Basecamp** atau **Diantar ke Alamat (Delivery)**.
  - Berbagai pilihan pembayaran: Transfer Bank (BCA, BRI, Mandiri) dan E-Wallet (GoPay, DANA, OVO).
- **Manajemen Pesanan, Nota Digital & Upload Bukti Bayar**:
  - Daftar riwayat booking pribadi dengan status pelacakan langsung (*live status badge*).
  - Form upload bukti transfer yang aman.
  - **Nota / Invoice Digital (`/user/nota.php`)**: Tampilan invoice resmi pesanan lengkap dengan nomor invoice unik, rincian biaya, dan tombol cetak (*printer-friendly*).
- **Profil & Keamanan Akun**: Ubah nama, nomor telepon, alamat utama, ganti kata sandi dengan validasi konfirmasi, serta penghapusan akun mandiri.

---

## 🔄 Alur Status Booking (Lifecycle)

Setiap transaksi penyewaan mengikuti alur siklus status yang terstandarisasi dan terkontrol:

```text
[ User Checkout ]
       │
       ▼
 ┌───────────┐      Upload Bukti Bayar       ┌────────────┐
 │  pending  │ ───────────────────────────>  │  diproses  │
 └───────────┘                               └────────────┘
       │                                            │
       │ (Dibatalkan / Kadaluarsa)                  │ (Barang Diserahkan ke User)
       ▼                                            ▼
 ┌───────────┐                               ┌─────────────┐
 │ cancelled │                               │ sedang_sewa │
 └───────────┘                               └─────────────┘
  (Stok Pulih)                                      │
                                                    │ (Barang Dikembalikan)
                                                    ▼
                                             ┌─────────────┐
                                             │   selesai   │
                                             └─────────────┘
                                            (Auto Re-Stock)
```

| Status | Penjelasan | Hak Akses & Aksi |
| :--- | :--- | :--- |
| `pending` | Pesanan baru berhasil dibuat, menunggu pembayaran & upload bukti dari user. | User mengunggah struk/bukti transfer. |
| `diproses` | Bukti pembayaran berhasil diverifikasi oleh Admin/Staff. Gear disiapkan di basecamp. | Staff menyiapkan alat & menyerahkan ke penyewa / kurir. |
| `sedang_sewa` | Gear telah diterima pelanggan dan masa petualangan sewa sedang berjalan. | Pelanggan memakai alat dan mengembalikan tepat waktu. |
| `selesai` | Gear dikembalikan dalam kondisi baik. **Stok produk otomatis bertambah kembali**. | Staff mencatat pengembalian & transaksi tuntas. |
| `cancelled` | Pesanan dibatalkan (oleh penyewa sebelum sewa atau bukti bayar ditolak). **Stok otomatis dipulihkan**. | Transaksi batal. |

---

## 🗄️ Skema & Arsitektur Database

Database `pink_outdoor` dirancang secara relasional dengan *Foreign Key Constraints* dan *Cascade Rules*:

| Nama Tabel | Deskripsi | Kunci Utama / Relasi |
| :--- | :--- | :--- |
| `users` | Menyimpan data akun pengguna, staf, dan admin dengan kolom pembeda `role`. | `id` (Primary Key), `email` (Unique) |
| `categories` | Kategori alat camping (Tenda, Carrier, Sleeping Bag, dll). | `id` (Primary Key), `slug` (Unique) |
| `products` | Katalog gear outdoor, spesifikasi, harga, stok, kondisi, dan gambar. | `id` (PK), `category_id` -> `categories(id)` |
| `bookings` | Rekam transaksi sewa, nomor order, durasi hari, metode pickup, dan status. | `id` (PK), `user_id` -> `users`, `product_id` -> `products` |
| `payments` | Bukti pembayaran, nominal, saluran transfer (bank/ewallet), status verifikasi. | `id` (PK), `booking_id` -> `bookings`, `user_id` -> `users` |
| `reviews` | Ulasan pelanggan, skor rating (1–5 bintang), dan komentar ulasan produk. | `id` (PK), `product_id` -> `products(id)` |
| `activity_log` | Catatan jejak audit aktivitas sistem (*audit trail*) untuk pengawasan admin. | `id` (Primary Key) |

---

## 📁 Struktur Direktori Proyek

```text
pink-outdoor/
│
├── admin/                        # Area Panel Administrator
│   ├── bookings.php              # Kontrol & pemantauan status pesanan sewa
│   ├── categories.php            # CRUD kategori gear & ikon
│   ├── index.php                 # Dashboard analitik utama & ringkasan metrik
│   ├── payments.php              # Verifikasi bukti transfer pembayaran
│   ├── products.php              # CRUD gear outdoor & upload foto berkas
│   ├── reports.php               # Laporan pendapatan, top gear & ekspor CSV
│   ├── staff.php                 # Kelola data staf operasional
│   └── users.php                 # Manajemen pengguna terdaftar
│
├── staff/                        # Area Operasional Staff Lapangan
│   ├── bookings.php              # Konfirmasi & serah terima sewa gear
│   ├── history.php               # Arsip riwayat pesanan selesai / batal
│   ├── index.php                 # Dashboard tugas harian staf
│   ├── payments.php              # Verifikasi bukti bayar kasir
│   ├── products.php              # Cek ketersediaan & kondisi fisik gear
│   ├── returns.php               # Pengembalian gear & auto-restock unit
│   └── stock.php                 # Pembaruan cepat jumlah stok barang
│
├── user/                         # Area Khusus Pelanggan / Penyewa
│   ├── booking.php               # Form sewa satuan langsung dari katalog
│   ├── bookings.php              # Riwayat pesanan saya & form upload bukti
│   ├── checkout.php              # Form checkout terpadu keranjang belanja
│   ├── nota.php                  # Cetak nota / faktur digital booking
│   └── profile.php               # Pengaturan profil, alamat & ganti sandi
│
├── auth/                         # Modul Autentikasi Eksternal & OAuth
│   ├── google-login.php          # Inisiasi OAuth Google + state anti-CSRF
│   ├── google-callback.php       # Pertukaran auth-code & auto-register user
│   └── google-config.php         # Konfigurasi client Google OAuth
│
├── config/                       # Konfigurasi Inti Aplikasi
│   ├── config.php                # Konstanta global, channels pembayaran & auto-URL
│   ├── db.php                    # Koneksi database MySQLi & set charset
│   ├── session.php               # Auth guards, role checker & token CSRF
│   └── partials/                 # Komponen antarmuka modular reusable
│       ├── head.php              # Meta tag & tag head HTML
│       ├── navbar.php            # Navigasi utama pengunjung
│       ├── navbar_user.php       # Navigasi terautentikasi pengguna
│       ├── sidebar_admin.php     # Menu samping panel admin
│       ├── sidebar_staff.php     # Menu samping panel staff
│       ├── topbar.php            # Bar navigasi atas admin & staff
│       ├── product_card.php      # Kartu komponen gear & badge rating
│       ├── scripts.php           # Pustaka skrip JS & SweetAlert2 init
│       └── footer.php            # Bagian footer situs & info kontak
│
├── assets/                       # Berkas Aset Statis
│   ├── css/style.css             # Tema custom stylesheet Pink Outdoor
│   ├── js/main.js                # Logika antarmuka klien & validasi form
│   └── img/                      # Aset grafis (logo, avatar, placeholder)
│
├── uploads/                      # Direktori Berkas Publik
│   ├── gear/                     # Foto produk gear outdoor
│   ├── payments/                 # Bukti transfer pembayaran penyewa
│   └── .htaccess                 # Proteksi blokir eksekusi skrip PHP
│
├── database/                     # Berkas Database & Migrasi
│   ├── pink_outdoor.sql          # Skema database komprehensif & data inisial
│   └── run_migration.php         # Skrip CLI migrasi database mandiri
│
├── .env.example                  # Contoh berkas konfigurasi variabel lingkungan
├── .gitignore                    # Berkas pengecualian Git
├── cart.php                      # Controller logika keranjang belanja
├── keranjang.php                 # Halaman antarmuka keranjang sewa
├── katalog.php                   # Katalog pencarian, filter & list gear
├── detail.php                    # Halaman detail produk, spesifikasi & review
├── paket.php                     # Halaman paket camping hemat terpadu
├── cara-sewa.php                 # Panduan prosedur peminjaman alat
├── faq.php                       # Pertanyaan umum yang sering diajukan
├── syarat.php                    # Syarat, ketentuan & kebijakan rental
├── tentang.php                   # Profil & kontak basecamp Pink Outdoor
├── login.php                     # Halaman masuk sistem (User, Staff, Admin)
├── register.php                  # Halaman pendaftaran member baru
├── logout.php                    # Penghancuran sesi login aman
└── README.md                     # Dokumentasi teknis proyek
```

---

## 💻 Persyaratan Sistem

- **Web Server**: Apache / Nginx (XAMPP, Laragon, atau Linux Stack)
- **PHP**: Versi `7.4` atau `8.0+` (Kompatibel penuh hingga `PHP 8.4` & `8.5`)
- **Ekstensi PHP Wajib**:
  - `mysqli` (Koneksi database)
  - `curl` (Koneksi integrasi Google OAuth)
  - `fileinfo` (Validasi tipe berkas unggahan)
  - `json` & `session` (Manajemen sesi & payload)
  - `openssl` (Enkripsi token anti-CSRF)
- **Database Server**: MySQL `5.7+` atau MariaDB `10.4+`
- **Web Browser**: Google Chrome, Mozilla Firefox, Microsoft Edge, Safari

---

## 🚀 Panduan Instalasi di XAMPP

### 1. Salin Proyek ke Direktori Web Server
Letakkan folder proyek di dalam direktori `htdocs` server XAMPP Anda:
```bash
# Path standar Windows XAMPP:
C:\xampp\htdocs\pink-outdoor
```

### 2. Buat & Import Database
1. Buka aplikasi **XAMPP Control Panel** dan nyalakan modul **Apache** serta **MySQL**.
2. Buka peramban dan kunjungi **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Buat database baru bernama: `pink_outdoor` dengan collation `utf8mb4_unicode_ci`.
4. Pilih database `pink_outdoor`, lalu buka tab **Import**.
5. Pilih berkas SQL:
   ```
   C:\xampp\htdocs\pink-outdoor\database\pink_outdoor.sql
   ```
6. Klik tombol **Import** (atau **Go**) pada bagian bawah.

> 💡 **Alternatif Migrasi Cepat via Terminal**:
> ```bash
> php database/run_migration.php
> ```

### 3. Konfigurasi Variabel Lingkungan (`.env`)
Salin berkas `.env.example` menjadi `.env`:
```bash
copy .env.example .env
```
Sesuaikan konfigurasi koneksi database Anda di `.env`:
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=pink_outdoor

# Opsional: Kredensial Google OAuth 2.0
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

### 4. Akses Aplikasi di Browser
Buka peramban dan akses alamat:
```
http://localhost/pink-outdoor/
```

---

## 🔑 Akun Bawaan (Default Accounts)

Database awal telah menyediakan 3 akun siap pakai untuk pengujian:

| Peran (Role) | Email | Kata Sandi | Dashboard Akses |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@pinkoutdoor.id` | `Password123!` | [admin/index.php](http://localhost/pink-outdoor/admin/index.php) |
| **Staff Operasional** | `staff@pinkoutdoor.id` | `Password123!` | [staff/index.php](http://localhost/pink-outdoor/staff/index.php) |
| **Pelanggan (User)** | `user@pinkoutdoor.id` | `Password123!` | [index.php](http://localhost/pink-outdoor/index.php) |

> ⚠️ **PERINGATAN**: Demi keamanan saat dideploy ke server produksi, segera ubah kata sandi akun default di atas melalui halaman profil!

---

## ⚙️ Konfigurasi Lingkungan (.env) & Google OAuth

Aplikasi mendukung pemuatan environment otomatis tanpa dependensi pustaka luar:

```env
# Database Credentials
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=pink_outdoor

# Google OAuth 2.0
GOOGLE_CLIENT_ID=masukkan_google_client_id_anda
GOOGLE_CLIENT_SECRET=masukkan_google_client_secret_anda
```

### Langkah Setup Google OAuth 2.0:
1. Kunjungi [Google Cloud Console](https://console.cloud.google.com/).
2. Buat proyek baru atau pilih proyek yang sudah ada.
3. Masuk ke **APIs & Services** > **Credentials**.
4. Klik **Create Credentials** > **OAuth Client ID**.
5. Pilih Application Type: **Web application**.
6. Tambahkan **Authorized Redirect URIs**:
   ```
   http://localhost/pink-outdoor/auth/google-callback.php
   ```
7. Salin **Client ID** dan **Client Secret**, kemudian tempelkan ke dalam berkas `.env`.

---

## 🛡️ Implementasi Keamanan

1. **CSRF Protection (Cross-Site Request Forgery)**:
   - Dilengkapi sistem token acak bersandi per sesi (`csrfToken()`, `csrfField()`, `verifyCsrfToken()`).
   - Melindungi setiap submit form sensitif (verifikasi/tolak pembayaran, kelola kategori/produk/stok, pemesanan, dan ganti password).
2. **SQL Injection Prevention**:
   - Seluruh query interaksi data pengguna menggunakan mekanisme **Prepared Statements & Parameter Binding** (`$conn->prepare()`, `bind_param()`).
3. **Hashing Kata Sandi Kuat**:
   - Penyimpanan password menggunakan algoritma hashing standar industri `password_hash(PASSWORD_DEFAULT)` (Bcrypt) dan diverifikasi melalui `password_verify()`.
4. **Validasi & Isolasi Berkas Upload**:
   - Verifikasi format file berbasis byte signature riil menggunakan pustaka PHP `finfo(FILEINFO_MIME_TYPE)` (bukan hanya ekstensi file).
   - Penamaan berkas acak yang aman berbasis *hash token*.
   - Konfigurasi `.htaccess` pada folder `uploads/` yang memblokir eksekusi skrip berbahaya (`.php`, `.phtml`, dll).
5. **Mitigasi OAuth State Tampering**:
   - Menggunakan validasi token `state` terenkripsi pada alur otentikasi Google guna mencegah serangan OAuth Login CSRF.

---

## 🛠️ Tech Stack

| Komponen | Pustaka / Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend Core** | PHP 7.4 - 8.5+ | PHP Native Procedural Bersih & Cepat |
| **Database** | MySQL / MariaDB | Relational Database dengan FK Cascading |
| **Frontend Framework** | Bootstrap 5.3.2 | Desain Responsif & Modern Mobile-First |
| **Icons** | Bootstrap Icons 1.11.3 | Ikonografi Vektor Elegan |
| **Alert & Dialog** | SweetAlert2 v11 | Pop-up Notifikasi & Konfirmasi Interaktif |
| **Single Sign-On** | Google OAuth 2.0 | Login Cepat Pelanggan dengan Akun Google |
| **Assets Storage** | Native Uploads + `.htaccess` Guard | Penyimpanan Berkas Foto & Bukti Bayar Terisolasi |

---

## 📄 Lisensi

Proyek ini dirilis di bawah lisensi [MIT License](LICENSE). Anda bebas menggunakan, memodifikasi, dan mengembangkan aplikasi ini untuk keperluan pembelajaran maupun komersial.

---

<p align="center">
  Dibuat dengan ❤️ untuk kemudahan petualangan alam bebas bersama <b>Pink Outdoor Gear Rent</b>.
</p>
