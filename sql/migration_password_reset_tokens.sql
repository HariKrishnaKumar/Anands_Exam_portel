-- Password reset tokens table
-- Used for the "Forgot Password" flow where students request a reset link via email.

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prt_email (email),
    INDEX idx_prt_token (token_hash),
    INDEX idx_prt_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auto-cleanup: delete expired tokens (run via event scheduler or cron)
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1;
