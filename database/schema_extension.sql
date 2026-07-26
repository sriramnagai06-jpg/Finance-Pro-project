-- =====================================================================
-- FinancePro - Schema Extension Part 10 & Part 3 Update
-- Creates cash_receipts, cash_payments, online_receipts, online_payments,
-- payment_modes, transaction_history, and dashboard_summary tables.
-- =====================================================================

-- 1. Payment Modes Lookup Table
CREATE TABLE IF NOT EXISTS payment_modes (
    mode_id INT AUTO_INCREMENT PRIMARY KEY,
    mode_name VARCHAR(50) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO payment_modes (mode_name) VALUES ('Cash'), ('UPI'), ('Bank'), ('Card'), ('Wallet');

-- 2. Cash Receipts Table
CREATE TABLE IF NOT EXISTS cash_receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    receipt_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT DEFAULT NULL,
    received_from VARCHAR(150) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank','Card','Wallet') NOT NULL DEFAULT 'Cash',
    amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    status ENUM('Completed','Pending','Cancelled') NOT NULL DEFAULT 'Completed',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cash_rec_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Cash Payments Table
CREATE TABLE IF NOT EXISTS cash_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payment_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT DEFAULT NULL,
    paid_to VARCHAR(150) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank','Card','Wallet') NOT NULL DEFAULT 'Cash',
    amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    status ENUM('Completed','Pending','Cancelled') NOT NULL DEFAULT 'Completed',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cash_pay_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Online Receipts Table
CREATE TABLE IF NOT EXISTS online_receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    receipt_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    upi_id VARCHAR(100) DEFAULT NULL,
    received_from VARCHAR(150) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank','Card','Wallet') NOT NULL DEFAULT 'Bank',
    amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    status ENUM('Completed','Pending','Cancelled') NOT NULL DEFAULT 'Completed',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_online_rec_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Online Payments Table
CREATE TABLE IF NOT EXISTS online_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payment_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    upi_id VARCHAR(100) DEFAULT NULL,
    paid_to VARCHAR(150) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank','Card','Wallet') NOT NULL DEFAULT 'Bank',
    amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    status ENUM('Completed','Pending','Cancelled') NOT NULL DEFAULT 'Completed',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_online_pay_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Unified Transaction History Table
CREATE TABLE IF NOT EXISTS transaction_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_type ENUM('Cash Receipt','Cash Payment','Online Receipt','Online Payment','Income','Expense') NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    txn_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    description TEXT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank','Card','Wallet') NOT NULL DEFAULT 'Cash',
    status ENUM('Completed','Pending','Cancelled') NOT NULL DEFAULT 'Completed',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Dashboard Summary Cache Table
CREATE TABLE IF NOT EXISTS dashboard_summary (
    summary_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_income DECIMAL(12,2) DEFAULT 0.00,
    total_expense DECIMAL(12,2) DEFAULT 0.00,
    cash_balance DECIMAL(12,2) DEFAULT 0.00,
    online_balance DECIMAL(12,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_summary_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
