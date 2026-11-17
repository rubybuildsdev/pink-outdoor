-- ============================================
-- PINK OUTDOOR GEAR RENT - Database Schema
-- Import via phpMyAdmin atau: mysql -u root < pink_outdoor.sql
-- ============================================

CREATE DATABASE IF NOT EXISTS pink_outdoor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pink_outdoor;

-- ============================================
-- TABLE: users (user, staff, admin dalam 1 tabel via kolom role)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL UNIQUE,
  `phone`      VARCHAR(20) DEFAULT NULL,
  `address`    TEXT DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','staff','user') NOT NULL DEFAULT 'user',
  `avatar`     VARCHAR(255) DEFAULT 'default.png',
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: categories
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `icon`       VARCHAR(50) DEFAULT 'bi-box',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: products (gear outdoor & paket)
-- ============================================
CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `category_id`   INT(11) NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `price_per_day` DECIMAL(10,2) NOT NULL,
  `stock`         INT(11) NOT NULL DEFAULT 1,
  `condition`     ENUM('excellent','good','fair') NOT NULL DEFAULT 'good',
  `image`         VARCHAR(255) DEFAULT 'no-image.png',
  `status`        ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: bookings
-- ============================================
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `order_number`     VARCHAR(50) DEFAULT NULL,
  `user_id`          INT(11) NOT NULL,
  `product_id`       INT(11) NOT NULL,
  `start_date`       DATE NOT NULL,
  `end_date`         DATE NOT NULL,
  `total_days`       INT(11) NOT NULL DEFAULT 1,
  `total_price`      DECIMAL(10,2) NOT NULL,
  `status`           ENUM('pending','diproses','sedang_sewa','selesai','cancelled') NOT NULL DEFAULT 'pending',
  `notes`            TEXT DEFAULT NULL,
  `pickup_method`    ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  `delivery_address` TEXT DEFAULT NULL,
  `handled_by`       INT(11) DEFAULT NULL COMMENT 'staff/admin user_id',
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_number` (`order_number`),
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: payments
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id`     INT(11) NOT NULL,
  `user_id`        INT(11) NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `method`         ENUM('bca','bri','mandiri','gopay','dana','ovo','cash') NOT NULL DEFAULT 'bca',
  `proof_image`    VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by`    INT(11) DEFAULT NULL,
  `verified_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: activity_log (audit trail)
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) DEFAULT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `module`     VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE: reviews (ulasan / review produk)
-- ============================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `user_id`    INT(11) DEFAULT NULL,
  `user_name`  VARCHAR(100) NOT NULL,
  `rating`     INT(1) NOT NULL DEFAULT 5,
  `comment`    TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default accounts (Password: Password123!)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('Super Admin',  'admin@pinkoutdoor.id',  '081234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('Staff Satu',   'staff@pinkoutdoor.id',  '081234567891', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active'),
('User Demo',    'user@pinkoutdoor.id',   '081234567892', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user',  'active');

-- Default categories
INSERT INTO `categories` (`name`, `slug`, `icon`) VALUES
('Tenda',         'tenda',         'bi-house-door'),
('Carrier',       'carrier',       'bi-bag'),
('Sleeping Bag',  'sleeping-bag',  'bi-moon-stars'),
('Matras',        'matras',        'bi-layers'),
('Aksesoris',     'aksesoris',     'bi-tools'),
('Paket Camping', 'paket-camping', 'bi-boxes');

-- Default products (contoh data)
INSERT INTO `products` (`category_id`, `name`, `description`, `price_per_day`, `stock`, `condition`) VALUES
(1, 'Tenda Dome 2P',        'Tenda dome kapasitas 2 orang, anti air, cocok untuk camping gunung.', 50000,  3, 'excellent'),
(1, 'Tenda Dome 4P',        'Tenda dome kapasitas 4 orang, fiberglass pole, mudah dipasang.',      80000,  2, 'good'),
(2, 'Carrier 45L Osprey',   'Carrier 45 liter merk Osprey, nyaman untuk perjalanan 2-3 hari.',     40000,  5, 'good'),
(2, 'Carrier 60L Deuter',   'Carrier 60 liter kapasitas besar untuk ekspedisi panjang.',            55000,  3, 'excellent'),
(3, 'Sleeping Bag -5°C',    'Sleeping bag tahan suhu -5 derajat celcius, cocok untuk gunung.',     25000, 10, 'good'),
(3, 'Sleeping Bag 0°C',     'Sleeping bag untuk suhu 0 derajat, ringan dan kompak.',               20000,  8, 'excellent'),
(4, 'Matras Foam',          'Matras busa ringan, mudah digulung, tebal 3cm.',                      10000, 15, 'good'),
(4, 'Matras Inflatable',    'Matras tiup premium, nyaman dan hangat untuk tidur.',                  20000,  6, 'good'),
(5, 'Headlamp LED',         'Headlamp LED 200 lumen, tahan air, baterai 20 jam.',                  15000, 20, 'excellent'),
(5, 'Trekking Pole (pair)', 'Trekking pole aluminum sepasang, adjustable.',                         20000, 10, 'good'),
(6, 'Paket Solo Hiker',     'Paket solo: Carrier 45L, Sleeping Bag -5°C, Matras Foam, Headlamp LED, Rain Cover.', 75000, 10, 'excellent'),
(6, 'Paket Couple Camp',    'Paket couple: Tenda 2P, 2x Carrier 45L, 2x Sleeping Bag, 2x Matras, Kompor, 2x Headlamp.', 120000, 10, 'excellent'),
(6, 'Paket Group Explorer', 'Paket grup: 2x Tenda 4P, 4x Carrier 60L, 4x Sleeping Bag, 4x Matras, Kompor, 4x Headlamp, 2x Pole.', 220000, 10, 'excellent'),
(6, 'Paket Family Weekend', 'Paket family: Tenda 4P, 2x Carrier 45L, 4x Sleeping Bag, 4x Matras, Kompor, 4x Headlamp.', 180000, 10, 'excellent');

-- Default reviews (ulasan pelanggan)
INSERT INTO `reviews` (`product_id`, `user_id`, `user_name`, `rating`, `comment`, `created_at`) VALUES
(1, 3, 'Muhammad Ruby', 5, 'Tenda sangat bersih, frame masih kokoh dan pasak lengkap. Dipakai di Gunung Prau aman dari angin kencang dan tidak rembes pas hujan rintik-rintik. Sangat recommended!', '2026-10-15 14:22:00'),
(2, NULL, 'Budi Santoso', 5, 'Muat 4 orang dewasa dengan nyaman plus barang-barang. Setup gampang banget, pasak dan tali webbing lengkap. Kondisi alat bersih dan wangi saat diambil.', '2026-10-20 09:15:00'),
(3, NULL, 'Dimas Pratama', 5, 'Backsystem Osprey emang juara, beban 14kg di pundak berasa enteng. Busa strap masih empuk dan raincover bawaan berfungsi dengan baik.', '2026-10-28 17:40:00'),
(4, NULL, 'Fajar Nugraha', 4, 'Kapasitas lega banget muat logistik 4 hari ke Semeru. Resleting lancar semua. Sedikit lecet pemakaian wajar tapi fungsinya tetap 100% prima.', '2026-11-02 11:05:00'),
(5, 3, 'Muhammad Ruby', 5, 'Hangat banget pas camp di Ranu Kumbolo! Bahannya tebal tapi packable. Tidak bau apek sama sekali, benar-benar higienis dan terawat.', '2026-11-05 19:30:00'),
(6, NULL, 'Siti Rahmawati', 5, 'Ringan, gampang dilipat masuk ke carrier, dan pas buat camping santai di bukit. Resleting double slider memudahkan ventilasi kalau agak gerah.', '2026-11-08 08:50:00'),
(9, NULL, 'Rian Hidayat', 4, 'Cahayanya terang banget buat summit attack jam 2 pagi. Baterai awet sampai turun gunung. Strap kepalanya juga elastis dan nyaman.', '2026-11-10 16:10:00'),
(12, NULL, 'Andi & Sarah', 5, 'Sewa paket couple ini hemat banget dibanding sewa satuan. Semua alat berfungsi normal, kompor gas portable apinya biru dan matras empuk!', '2026-11-12 13:25:00');

