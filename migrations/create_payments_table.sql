CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('cash','online') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    paid_to VARCHAR(255) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL COMMENT 'upi, credit_card, debit_card, net_banking',
    transaction_ref VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
