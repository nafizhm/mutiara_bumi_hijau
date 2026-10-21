-- MySQL / MariaDB. Pilih database aplikasi sebelum menjalankan.
-- Sesuai migration: 2026_10_21_000000_create_change_requests_table
-- Tidak mengubah data pengguna, role, atau transaksi.

CREATE TABLE IF NOT EXISTS `change_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `menu` VARCHAR(255) NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `route_name` VARCHAR(255) NOT NULL,
    `reason` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `status` VARCHAR(255) NOT NULL DEFAULT 'pending',
    `reviewer_id` BIGINT UNSIGNED NULL,
    `review_note` TEXT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `change_requests_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opsional: jalankan blok berikut hanya jika tabel `migrations` Laravel ada,
-- setelah memastikan struktur change_requests sesuai definisi di atas.
-- Ini mencegah php artisan migrate mencoba membuat tabel yang sama.
-- INSERT INTO `migrations` (`migration`, `batch`)
-- SELECT '2026_10_21_000000_create_change_requests_table',
--        COALESCE((SELECT MAX(`batch`) FROM `migrations` AS m), 0) + 1
-- WHERE NOT EXISTS (
--     SELECT 1 FROM `migrations`
--     WHERE `migration` = '2026_10_21_000000_create_change_requests_table'
-- );
