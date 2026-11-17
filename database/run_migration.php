<?php
require_once __DIR__ . '/../config/db.php';

echo "=== Starting Reviews Table Migration ===\n";

// 1. Create table
$createTableSql = "CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `rating` INT(1) NOT NULL DEFAULT 5,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($createTableSql) === TRUE) {
    echo "✔ Table 'reviews' created or already exists successfully.\n";
} else {
    die("❌ Error creating table: " . $conn->error . "\n");
}

// 2. Seed data
$checkCount = $conn->query("SELECT COUNT(*) AS total FROM `reviews`");
$rowCount = $checkCount->fetch_assoc()['total'];

if ($rowCount == 0) {
    echo "Seeding reviews data...\n";
    $seeds = [
        [
            'product_id' => 1,
            'user_id'    => 5,
            'user_name'  => 'Muhammad Ruby',
            'rating'     => 5,
            'comment'    => 'Tenda sangat bersih, frame masih kokoh dan pasak lengkap. Dipakai di Gunung Prau aman dari angin kencang dan tidak rembes pas hujan rintik-rintik. Sangat recommended!',
            'created_at' => '2026-10-15 14:22:00'
        ],
        [
            'product_id' => 2,
            'user_id'    => null,
            'user_name'  => 'Budi Santoso',
            'rating'     => 5,
            'comment'    => 'Muat 4 orang dewasa dengan nyaman plus barang-barang. Setup gampang banget, pasak dan tali webbing lengkap. Kondisi alat bersih dan wangi saat diambil.',
            'created_at' => '2026-10-20 09:15:00'
        ],
        [
            'product_id' => 3,
            'user_id'    => null,
            'user_name'  => 'Dimas Pratama',
            'rating'     => 5,
            'comment'    => 'Backsystem Osprey emang juara, beban 14kg di pundak berasa enteng. Busa strap masih empuk dan raincover bawaan berfungsi dengan baik.',
            'created_at' => '2026-10-28 17:40:00'
        ],
        [
            'product_id' => 4,
            'user_id'    => null,
            'user_name'  => 'Fajar Nugraha',
            'rating'     => 4,
            'comment'    => 'Kapasitas lega banget muat logistik 4 hari ke Semeru. Resleting lancar semua. Sedikit lecet pemakaian wajar tapi fungsinya tetap 100% prima.',
            'created_at' => '2026-11-02 11:05:00'
        ],
        [
            'product_id' => 5,
            'user_id'    => 5,
            'user_name'  => 'Muhammad Ruby',
            'rating'     => 5,
            'comment'    => 'Hangat banget pas camp di Ranu Kumbolo! Bahannya tebal tapi packable. Tidak bau apek sama sekali, benar-benar higienis dan terawat.',
            'created_at' => '2026-11-05 19:30:00'
        ],
        [
            'product_id' => 6,
            'user_id'    => null,
            'user_name'  => 'Siti Rahmawati',
            'rating'     => 5,
            'comment'    => 'Ringan, gampang dilipat masuk ke carrier, dan pas buat camping santai di bukit. Resleting double slider memudahkan ventilasi kalau agak gerah.',
            'created_at' => '2026-11-08 08:50:00'
        ],
        [
            'product_id' => 9,
            'user_id'    => null,
            'user_name'  => 'Rian Hidayat',
            'rating'     => 4,
            'comment'    => 'Cahayanya terang banget buat summit attack jam 2 pagi. Baterai awet sampai turun gunung. Strap kepalanya juga elastis dan nyaman.',
            'created_at' => '2026-11-10 16:10:00'
        ],
        [
            'product_id' => 12,
            'user_id'    => null,
            'user_name'  => 'Andi & Sarah',
            'rating'     => 5,
            'comment'    => 'Sewa paket couple ini hemat banget dibanding sewa satuan. Semua alat berfungsi normal, kompor gas portable apinya biru dan matras empuk!',
            'created_at' => '2026-11-12 13:25:00'
        ],
    ];

    $stmt = $conn->prepare("INSERT INTO `reviews` (`product_id`, `user_id`, `user_name`, `rating`, `comment`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($seeds as $seed) {
        $stmt->bind_param("iisiss", $seed['product_id'], $seed['user_id'], $seed['user_name'], $seed['rating'], $seed['comment'], $seed['created_at']);
        $stmt->execute();
    }
    echo "✔ Seeded " . count($seeds) . " reviews successfully.\n";
} else {
    echo "ℹ Reviews table already has $rowCount records, skipping seed.\n";
}

// 3. Verify
$verify = $conn->query("SELECT r.id, r.product_id, p.name AS product_name, r.user_name, r.rating, r.comment, r.created_at FROM `reviews` r JOIN `products` p ON r.product_id = p.id ORDER BY r.id ASC");
echo "\n--- Verification Query Results ---\n";
while ($row = $verify->fetch_assoc()) {
    echo "[#{$row['id']}] Product: {$row['product_name']} | By: {$row['user_name']} | Rating: {$row['rating']}/5\n";
    echo "     Comment: {$row['comment']}\n";
    echo "     Date: {$row['created_at']}\n\n";
}
echo "Migration finished successfully!\n";
