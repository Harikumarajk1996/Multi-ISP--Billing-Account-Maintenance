-- Billing tables for SilverWave ISP portal

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(64) NOT NULL UNIQUE,
  `subscriber_id` INT NOT NULL,
  `plan_id` INT DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_date` DATE DEFAULT NULL,
  `status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME DEFAULT NULL,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoices_subscriber` (`subscriber_id`),
  INDEX `idx_invoices_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional payments table to record individual payments against invoices
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(64) DEFAULT NULL,
  `transaction_id` VARCHAR(128) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payments_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If you want foreign keys (optional) and your subscriber/plans tables exist:
-- ALTER TABLE `invoices` ADD CONSTRAINT `fk_invoices_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `subscriber`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
-- ALTER TABLE `invoices` ADD CONSTRAINT `fk_invoices_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
