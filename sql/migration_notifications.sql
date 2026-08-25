-- ============================================================
-- Migration: admin_notifications (bell icon feed)
-- Logs operational events admins should see:
--   student_account — registration / OTP / verification issues
--   guest_link      — invalid / expired link or QR login attempts
--   qr              — QR / guest link generation failures
--   test            — test submission / running failures
--   system          — PHP errors, exceptions, misc failures
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB;