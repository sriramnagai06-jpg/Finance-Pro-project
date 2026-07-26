-- =====================================================================
-- FinancePro - Personal Finance & Expense Management System
-- Database: financepro
-- Engine: MySQL 8 / MariaDB (XAMPP)
--
-- This is a SINGLE consolidated schema. It replaces the old
-- database/migrations/*.sql + patch_*.php chain (financepro.sql +
-- erp_upgrade.sql + v2_optimize.sql + patch_gst_module.sql +
-- patch_columns.sql + patch_accounting_groups.php), which contained
-- two tables (notifications, audit_log) defined twice with different
-- column names. Only run THIS file. Do not run anything from an old
-- /database/migrations folder against this schema.
-- =====================================================================

-- =====================================================================
-- TABLE: users
-- =====================================================================
CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    phone           VARCHAR(15)   DEFAULT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    profile_picture VARCHAR(255)  DEFAULT 'assets/images/default-avatar.png',
    role            ENUM('admin','user') NOT NULL DEFAULT 'user',
    status          ENUM('active','blocked') NOT NULL DEFAULT 'active',
    reset_token     VARCHAR(255)  DEFAULT NULL,
    reset_token_expiry DATETIME  DEFAULT NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: categories
-- category_type = income/expense (drives the Income/Expense pages)
-- accounting_group = which side of the books it belongs on
-- (drives Dashboard, Trading A/c, P&L, Balance Sheet)
-- =====================================================================
CREATE TABLE categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(50)  NOT NULL,
    category_type   ENUM('income','expense') NOT NULL,
    accounting_group ENUM('Direct Income','Indirect Income','Direct Expense','Indirect Expense','Asset','Liability','Capital') NOT NULL DEFAULT 'Indirect Expense',
    icon_class      VARCHAR(50)  DEFAULT 'fa-solid fa-tag',
    is_default      TINYINT(1)   NOT NULL DEFAULT 0,
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_category_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY uniq_category (category_name, category_type)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: income
-- =====================================================================
CREATE TABLE income (
    income_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    category_id     INT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    income_date     DATE NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    payment_mode    ENUM('cash','bank_transfer','upi','card','other') DEFAULT 'cash',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_income_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_income_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    INDEX idx_income_user_date (user_id, income_date)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: expenses
-- =====================================================================
CREATE TABLE expenses (
    expense_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    category_id     INT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
    expense_date    DATE NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    payment_mode    ENUM('cash','bank_transfer','upi','card','other') DEFAULT 'cash',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    INDEX idx_expenses_user_date (user_id, expense_date)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: budget
-- =====================================================================
CREATE TABLE budget (
    budget_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    category_id     INT NOT NULL,
    budget_amount   DECIMAL(12,2) NOT NULL CHECK (budget_amount >= 0),
    budget_month    TINYINT NOT NULL CHECK (budget_month BETWEEN 1 AND 12),
    budget_year     SMALLINT NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_budget (user_id, category_id, budget_month, budget_year)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: invoices (GST columns included directly, no ALTER patch needed)
-- =====================================================================
CREATE TABLE invoices (
    invoice_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    invoice_number  VARCHAR(30) NOT NULL UNIQUE,
    customer_name   VARCHAR(100) NOT NULL,
    customer_email  VARCHAR(150) DEFAULT NULL,
    customer_phone  VARCHAR(15)  DEFAULT NULL,
    customer_address VARCHAR(255) DEFAULT NULL,
    invoice_date    DATE NOT NULL,
    due_date        DATE DEFAULT NULL,
    gst_type        ENUM('intra_state','union_territory','inter_state') DEFAULT 'intra_state',
    cgst_percent    DECIMAL(5,2) DEFAULT 0,
    sgst_percent    DECIMAL(5,2) DEFAULT 0,
    utgst_percent   DECIMAL(5,2) DEFAULT 0,
    igst_percent    DECIMAL(5,2) DEFAULT 0,
    cgst_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    sgst_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    utgst_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    igst_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total     DECIMAL(12,2) NOT NULL DEFAULT 0,
    status          ENUM('paid','unpaid','partial','cancelled') NOT NULL DEFAULT 'unpaid',
    company_logo    VARCHAR(255) DEFAULT NULL,
    notes           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_invoices_user_date (user_id, invoice_date)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: invoice_items
-- =====================================================================
CREATE TABLE invoice_items (
    item_id         INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT NOT NULL,
    product_name    VARCHAR(150) NOT NULL,
    quantity        INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    unit_price      DECIMAL(12,2) NOT NULL CHECK (unit_price >= 0),
    line_total      DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: reports (history log - reserved for future use)
-- =====================================================================
CREATE TABLE reports (
    report_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    report_type     ENUM('daily','weekly','monthly','yearly','profit_loss','savings') NOT NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    total_income    DECIMAL(12,2) DEFAULT 0,
    total_expense   DECIMAL(12,2) DEFAULT 0,
    net_savings     DECIMAL(12,2) DEFAULT 0,
    generated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: user_settings
-- (This replaces the old base-schema "settings" table, which the app
-- code never actually reads from - only this table is used.)
-- =====================================================================
CREATE TABLE user_settings (
    user_id                  INT PRIMARY KEY,
    currency_symbol          VARCHAR(10) DEFAULT 'Rs.',
    currency_position        ENUM('prefix','suffix') DEFAULT 'prefix',
    theme                    ENUM('light','dark') DEFAULT 'light',
    large_expense_threshold  DECIMAL(10,2) DEFAULT 5000.00,
    default_cgst             DECIMAL(5,2) DEFAULT 9.00,
    default_sgst             DECIMAL(5,2) DEFAULT 9.00,
    default_utgst            DECIMAL(5,2) DEFAULT 9.00,
    default_igst              DECIMAL(5,2) DEFAULT 18.00,
    company_name              VARCHAR(255) DEFAULT '',
    company_address           TEXT,
    company_gstin             VARCHAR(50) DEFAULT '',
    company_phone             VARCHAR(50) DEFAULT '',
    company_email             VARCHAR(100) DEFAULT '',
    invoice_logo              VARCHAR(255) DEFAULT NULL,
    updated_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: notifications  (single definition - matches user/notifications.php)
-- =====================================================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    type            VARCHAR(50) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: audit_log  (single definition - matches includes/functions.php
-- and admin/audit_log.php, which both use action_type)
-- =====================================================================
CREATE TABLE audit_log (
    log_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,
    action_type     VARCHAR(100) NOT NULL,
    table_name      VARCHAR(50) DEFAULT NULL,
    record_id       INT DEFAULT NULL,
    description     TEXT,
    ip_address      VARCHAR(45) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user_date (user_id, created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: financial_goals
-- =====================================================================
CREATE TABLE financial_goals (
    goal_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    goal_name       VARCHAR(100) NOT NULL,
    target_amount   DECIMAL(12,2) NOT NULL,
    saved_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    deadline        DATE NOT NULL,
    priority        ENUM('Low','Medium','High') DEFAULT 'Medium',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: recurring_entries (reserved for future automation - not yet
-- wired into any page, kept for forward compatibility)
-- =====================================================================
CREATE TABLE recurring_entries (
    recurring_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    type            ENUM('income','expense') NOT NULL,
    category_id     INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    description     VARCHAR(255),
    frequency       ENUM('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
    next_run_date   DATE NOT NULL,
    status          ENUM('active','paused') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recurring_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_recurring_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: accounts (Chart of Accounts - one set per user)
-- =====================================================================
CREATE TABLE accounts (
    account_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    account_name    VARCHAR(100) NOT NULL,
    account_type    ENUM('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
    is_system       TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_acc_user_name (user_id, account_name)
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: journal_entries
-- =====================================================================
CREATE TABLE journal_entries (
    entry_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    entry_date      DATE NOT NULL,
    description     VARCHAR(255) NOT NULL,
    reference_type  VARCHAR(50) DEFAULT NULL,
    reference_id    INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- TABLE: journal_items (double-entry debit/credit lines)
-- =====================================================================
CREATE TABLE journal_items (
    item_id         INT AUTO_INCREMENT PRIMARY KEY,
    entry_id        INT NOT NULL,
    account_id      INT NOT NULL,
    debit           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credit          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_ji_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id) ON DELETE CASCADE,
    CONSTRAINT fk_ji_account FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SEED: users
-- Admin: admin@financepro.com / Admin@123
-- User:  demo@financepro.com  / Demo@123
-- =====================================================================
INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES
('System Administrator', 'admin@financepro.com', '9999999999', '$2y$12$S9FOrHnDUxI00NWpQ7JEkuyrynlQAgO8AvScesWJ97WtzYF2NE/su', 'admin', 'active'),
('Demo User', 'demo@financepro.com', '9876543210', '$2y$12$zLR/C48PzbzKFydfat0Q3uijvYteV417FrZ7Z6CmT9xG6ijrA.0.K', 'user', 'active');

-- =====================================================================
-- SEED: categories (default income/expense + full chart-of-accounts set)
-- =====================================================================
INSERT INTO categories (category_name, category_type, accounting_group, icon_class, is_default) VALUES
-- Everyday income categories
('Salary', 'income', 'Indirect Income', 'fa-solid fa-money-check-dollar', 1),
('Freelancing', 'income', 'Indirect Income', 'fa-solid fa-laptop-code', 1),
('Business', 'income', 'Direct Income', 'fa-solid fa-briefcase', 1),
('Investment', 'income', 'Indirect Income', 'fa-solid fa-chart-line', 1),
('Bonus', 'income', 'Indirect Income', 'fa-solid fa-gift', 1),
('Others', 'income', 'Indirect Income', 'fa-solid fa-circle-plus', 1),
-- Everyday expense categories
('Food', 'expense', 'Indirect Expense', 'fa-solid fa-utensils', 1),
('Rent', 'expense', 'Indirect Expense', 'fa-solid fa-house', 1),
('Shopping', 'expense', 'Indirect Expense', 'fa-solid fa-bag-shopping', 1),
('Fuel', 'expense', 'Indirect Expense', 'fa-solid fa-gas-pump', 1),
('Electricity', 'expense', 'Indirect Expense', 'fa-solid fa-bolt', 1),
('Entertainment', 'expense', 'Indirect Expense', 'fa-solid fa-film', 1),
('Education', 'expense', 'Indirect Expense', 'fa-solid fa-graduation-cap', 1),
('Medical', 'expense', 'Indirect Expense', 'fa-solid fa-briefcase-medical', 1),
('Household', 'expense', 'Indirect Expense', 'fa-solid fa-circle-minus', 1),
-- Trading Account (Direct Expenses / Direct Income)
('Opening Stock', 'expense', 'Direct Expense', 'fa-solid fa-box-open', 1),
('Purchases', 'expense', 'Direct Expense', 'fa-solid fa-cart-shopping', 1),
('Carriage Inward', 'expense', 'Direct Expense', 'fa-solid fa-truck', 1),
('Freight', 'expense', 'Direct Expense', 'fa-solid fa-ship', 1),
('Factory Wages', 'expense', 'Direct Expense', 'fa-solid fa-hard-hat', 1),
('Manufacturing Expenses', 'expense', 'Direct Expense', 'fa-solid fa-industry', 1),
('Sales', 'income', 'Direct Income', 'fa-solid fa-cash-register', 1),
('Closing Stock', 'income', 'Direct Income', 'fa-solid fa-box', 1),
-- Indirect (P&L Account)
('Telephone', 'expense', 'Indirect Expense', 'fa-solid fa-phone', 1),
('Advertisement', 'expense', 'Indirect Expense', 'fa-solid fa-ad', 1),
('Insurance', 'expense', 'Indirect Expense', 'fa-solid fa-shield-halved', 1),
('Depreciation', 'expense', 'Indirect Expense', 'fa-solid fa-arrow-trend-down', 1),
('Office Expenses', 'expense', 'Indirect Expense', 'fa-solid fa-building', 1),
('Bank Charges', 'expense', 'Indirect Expense', 'fa-solid fa-building-columns', 1),
('Interest', 'expense', 'Indirect Expense', 'fa-solid fa-percent', 1),
('Commission', 'expense', 'Indirect Expense', 'fa-solid fa-handshake', 1),
('Discount Received', 'income', 'Indirect Income', 'fa-solid fa-tags', 1),
('Miscellaneous Income', 'income', 'Indirect Income', 'fa-solid fa-coins', 1),
-- Balance Sheet items (Assets / Liabilities / Capital)
('Inventory', 'expense', 'Asset', 'fa-solid fa-boxes-stacked', 1),
('Debtors', 'expense', 'Asset', 'fa-solid fa-users', 1),
('Fixed Assets', 'expense', 'Asset', 'fa-solid fa-building', 1),
('Creditors', 'income', 'Liability', 'fa-solid fa-users-slash', 1),
('Loans', 'income', 'Liability', 'fa-solid fa-hand-holding-dollar', 1),
('Capital Contribution', 'income', 'Capital', 'fa-solid fa-seedling', 1),
('Drawings', 'expense', 'Capital', 'fa-solid fa-hand-holding', 1);

-- =====================================================================
-- SEED: chart of accounts for every existing user
-- (System asset accounts + one account per category, mirroring what
-- the double-entry triggers below expect to find)
-- =====================================================================
INSERT INTO accounts (user_id, account_name, account_type, is_system)
SELECT user_id, 'Cash', 'Asset', 1 FROM users;

INSERT INTO accounts (user_id, account_name, account_type, is_system)
SELECT user_id, 'Bank', 'Asset', 1 FROM users;

INSERT INTO accounts (user_id, account_name, account_type, is_system)
SELECT user_id, 'Card', 'Liability', 1 FROM users;

INSERT INTO accounts (user_id, account_name, account_type, is_system)
SELECT user_id, 'Other Asset', 'Asset', 1 FROM users;

INSERT INTO accounts (user_id, account_name, account_type, is_system)
SELECT u.user_id, c.category_name,
    CASE WHEN c.category_type = 'income' THEN 'Revenue' ELSE 'Expense' END,
    0
FROM users u CROSS JOIN categories c;

-- =====================================================================
-- TRIGGERS: auto-post a double-entry journal entry whenever an
-- income/expense row is added or removed
-- =====================================================================


-- =====================================================================
-- SEED: sample income/expenses/budget for the demo user (user_id = 2)
-- Inserted AFTER accounts + triggers exist, so the journal/ledger/
-- trial balance pages have real data to show immediately.
-- Category IDs below match the categories block exactly:
-- Salary=1, Freelancing=2, Business=3, Investment=4, Bonus=5, Others(inc)=6,
-- Food=7, Rent=8, Shopping=9, Fuel=10, Electricity=11, Entertainment=12,
-- Education=13, Medical=14, Household=15
-- =====================================================================
INSERT INTO income (user_id, category_id, amount, income_date, description, payment_mode) VALUES
(2, 1, 45000.00, '2026-06-01', 'June Salary', 'bank_transfer'),
(2, 2, 12000.00, '2026-06-10', 'Freelance web project', 'upi'),
(2, 4, 3000.00, '2026-06-15', 'Mutual fund dividend', 'bank_transfer');

INSERT INTO expenses (user_id, category_id, amount, expense_date, description, payment_mode) VALUES
(2, 8, 12000.00, '2026-06-02', 'Monthly rent', 'bank_transfer'),
(2, 7, 4500.00, '2026-06-05', 'Groceries', 'upi'),
(2, 10, 1200.00, '2026-06-08', 'Petrol', 'cash'),
(2, 11, 2200.00, '2026-06-12', 'Electricity bill', 'upi'),
(2, 12, 1800.00, '2026-06-18', 'Movie & dinner', 'card');

INSERT INTO budget (user_id, category_id, budget_amount, budget_month, budget_year) VALUES
(2, 8, 12000.00, 6, 2026),
(2, 7, 6000.00, 6, 2026),
(2, 10, 2000.00, 6, 2026);

INSERT INTO user_settings (user_id, currency_symbol, theme) VALUES (2, 'Rs.', 'light');
