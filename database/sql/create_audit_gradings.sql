-- Jalankan script ini di phpMyAdmin atau MySQL client
-- jika tidak bisa menjalankan: php artisan migrate

CREATE TABLE IF NOT EXISTS `audit_gradings` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plan_audit_id`     BIGINT UNSIGNED NOT NULL,
    `id_grading`        VARCHAR(255)    NULL,
    `jenis`             VARCHAR(255)    NULL,
    `area`              VARCHAR(255)    NULL,
    `bbnkb`             VARCHAR(1)      NOT NULL DEFAULT 'N',
    `fraud`             VARCHAR(1)      NOT NULL DEFAULT 'N',
    `jenis_fraud`       JSON            NULL,
    `keterangan_fraud`  TEXT            NULL,
    `details`           JSON            NULL,
    `total_nilai`       DECIMAL(8,2)    NULL,
    `created_by`        VARCHAR(255)    NULL,
    `updated_by`        VARCHAR(255)    NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    INDEX `audit_gradings_plan_audit_id_index` (`plan_audit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
