-- ============================================================================
-- Intelligent Integrated Financial Management System - Travel & Tour Agencies
-- Full database schema. Import this into a fresh MySQL database via
-- phpMyAdmin or `mysql -u root travelcore_fms < schema.sql`.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS travelcore_fms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE travelcore_fms;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Auth / RBAC
-- ----------------------------------------------------------------------------

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_module_action (module_key, action)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT DEFAULT NULL,
    details TEXT,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_audit_module (module, created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- General Ledger (core)
-- ----------------------------------------------------------------------------

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE coa_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_name VARCHAR(150) NOT NULL,
    account_type VARCHAR(20) NOT NULL,          -- Asset, Liability, Equity, Revenue, Expense
    parent_id INT DEFAULT NULL,
    normal_balance VARCHAR(10) NOT NULL,        -- Debit, Credit
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES coa_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_no VARCHAR(30) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    source_module VARCHAR(30) NOT NULL DEFAULT 'manual',
    source_id INT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, Posted, Void
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    posted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    KEY idx_je_date (entry_date),
    KEY idx_je_source (source_module, source_id)
) ENGINE=InnoDB;

CREATE TABLE journal_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    department_id INT DEFAULT NULL,
    memo VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    KEY idx_jl_account (account_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Cash Management (schema needed early: AP/AR/DV/CR all reference cash_accounts)
-- ----------------------------------------------------------------------------

CREATE TABLE cash_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(150) NOT NULL,
    account_type VARCHAR(30) NOT NULL,          -- Cash on Hand, Bank, Petty Cash
    bank_name VARCHAR(100) DEFAULT NULL,
    account_no VARCHAR(50) DEFAULT NULL,
    gl_account_id INT NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gl_account_id) REFERENCES coa_accounts(id)
) ENGINE=InnoDB;

CREATE TABLE cash_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_account_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    type VARCHAR(20) NOT NULL,                  -- Deposit, Withdrawal, TransferIn, TransferOut, Adjustment
    amount DECIMAL(14,2) NOT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    source_module VARCHAR(30) DEFAULT NULL,
    source_id INT DEFAULT NULL,
    cash_flow_category VARCHAR(20) NOT NULL DEFAULT 'Operating', -- Operating, Investing, Financing
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    KEY idx_ct_date (transaction_date)
) ENGINE=InnoDB;

CREATE TABLE cash_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(30) NOT NULL UNIQUE,
    from_cash_account_id INT NOT NULL,
    to_cash_account_id INT NOT NULL,
    transfer_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (to_cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE bank_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_account_id INT NOT NULL,
    statement_date DATE NOT NULL,
    statement_balance DECIMAL(14,2) NOT NULL,
    book_balance DECIMAL(14,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'InProgress', -- InProgress, Completed
    reconciled_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (reconciled_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE bank_reconciliation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reconciliation_id INT NOT NULL,
    cash_transaction_id INT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    item_type VARCHAR(30) NOT NULL,             -- OutstandingCheck, DepositInTransit, BankCharge, Interest, Error
    cleared TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliations(id) ON DELETE CASCADE,
    FOREIGN KEY (cash_transaction_id) REFERENCES cash_transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tax Management (schema needed early: AP/AR lines reference tax_types)
-- ----------------------------------------------------------------------------

CREATE TABLE tax_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    rate_percent DECIMAL(6,3) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tax_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_type_id INT NOT NULL,
    source_module VARCHAR(10) NOT NULL,         -- AP, AR
    source_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    taxable_amount DECIMAL(14,2) NOT NULL,
    tax_amount DECIMAL(14,2) NOT NULL,
    direction VARCHAR(15) NOT NULL,             -- Input, Output, Withholding
    status VARCHAR(20) NOT NULL DEFAULT 'Pending', -- Pending, Remitted
    remittance_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id)
) ENGINE=InnoDB;

CREATE TABLE tax_remittances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remittance_no VARCHAR(30) NOT NULL UNIQUE,
    tax_type_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    remittance_date DATE DEFAULT NULL,
    reference_no VARCHAR(100) DEFAULT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, Remitted
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

ALTER TABLE tax_transactions ADD FOREIGN KEY (remittance_id) REFERENCES tax_remittances(id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- Accounts Payable
-- ----------------------------------------------------------------------------

CREATE TABLE ap_vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    tax_id VARCHAR(30) DEFAULT NULL,
    payment_terms_days INT NOT NULL DEFAULT 30,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ap_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_no VARCHAR(30) NOT NULL UNIQUE,
    vendor_id INT NOT NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, Open, PartiallyPaid, Paid, Overdue, Void
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES ap_vendors(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    KEY idx_bill_due (due_date),
    KEY idx_bill_status (status)
) ENGINE=InnoDB;

CREATE TABLE ap_bill_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    account_id INT NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_type_id INT DEFAULT NULL,
    FOREIGN KEY (bill_id) REFERENCES ap_bills(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id)
) ENGINE=InnoDB;

CREATE TABLE ap_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_no VARCHAR(30) NOT NULL UNIQUE,
    vendor_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Bank Transfer',
    reference_no VARCHAR(100) DEFAULT NULL,
    cash_account_id INT NOT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES ap_vendors(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE ap_payment_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    bill_id INT NOT NULL,
    amount_applied DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES ap_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES ap_bills(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Accounts Receivable (mirrors AP)
-- ----------------------------------------------------------------------------

CREATE TABLE ar_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    tax_id VARCHAR(30) DEFAULT NULL,
    credit_terms_days INT NOT NULL DEFAULT 30,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ar_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_received DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, Open, PartiallyPaid, Paid, Overdue, Void
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES ar_customers(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    KEY idx_inv_due (due_date),
    KEY idx_inv_status (status)
) ENGINE=InnoDB;

CREATE TABLE ar_invoice_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    account_id INT NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_type_id INT DEFAULT NULL,
    FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (tax_type_id) REFERENCES tax_types(id)
) ENGINE=InnoDB;

CREATE TABLE ar_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    receipt_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Bank Transfer',
    reference_no VARCHAR(100) DEFAULT NULL,
    cash_account_id INT NOT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES ar_customers(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE ar_receipt_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    invoice_id INT NOT NULL,
    amount_applied DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (receipt_id) REFERENCES ar_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Disbursement Management (approval wrapper around AP payment / cash outflow)
-- ----------------------------------------------------------------------------

CREATE TABLE disbursement_vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dv_no VARCHAR(30) NOT NULL UNIQUE,
    dv_date DATE NOT NULL,
    payee_type VARCHAR(20) NOT NULL,             -- Vendor, Employee, Other
    vendor_id INT DEFAULT NULL,
    payee_name VARCHAR(150) NOT NULL,
    particulars VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    cash_account_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, PendingApproval, Approved, Paid, Rejected, Void
    requested_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    ap_payment_id INT DEFAULT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES ap_vendors(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (ap_payment_id) REFERENCES ap_payments(id) ON DELETE SET NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE disbursement_voucher_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dv_id INT NOT NULL,
    account_id INT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    ap_bill_id INT DEFAULT NULL,
    FOREIGN KEY (dv_id) REFERENCES disbursement_vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (ap_bill_id) REFERENCES ap_bills(id)
) ENGINE=InnoDB;

CREATE TABLE dv_approval_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dv_id INT NOT NULL,
    action VARCHAR(30) NOT NULL,
    actor_id INT NOT NULL,
    comments VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dv_id) REFERENCES disbursement_vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Collection Management (approval wrapper around AR receipt / cash inflow)
-- ----------------------------------------------------------------------------

CREATE TABLE collection_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cr_no VARCHAR(30) NOT NULL UNIQUE,
    cr_date DATE NOT NULL,
    payer_type VARCHAR(20) NOT NULL,             -- Customer, Other
    customer_id INT DEFAULT NULL,
    payer_name VARCHAR(150) NOT NULL,
    particulars VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    cash_account_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, PendingApproval, Approved, Deposited, Rejected, Void
    received_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    ar_receipt_id INT DEFAULT NULL,
    journal_entry_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES ar_customers(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (ar_receipt_id) REFERENCES ar_receipts(id) ON DELETE SET NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE collection_receipt_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cr_id INT NOT NULL,
    account_id INT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    ar_invoice_id INT DEFAULT NULL,
    FOREIGN KEY (cr_id) REFERENCES collection_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (ar_invoice_id) REFERENCES ar_invoices(id)
) ENGINE=InnoDB;

CREATE TABLE cr_approval_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cr_id INT NOT NULL,
    action VARCHAR(30) NOT NULL,
    actor_id INT NOT NULL,
    comments VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cr_id) REFERENCES collection_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Budget Management
-- ----------------------------------------------------------------------------

CREATE TABLE budget_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,                   -- e.g. FY2026
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Open',  -- Open, Closed
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_period_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Draft', -- Draft, Approved
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_period_id) REFERENCES budget_periods(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE budget_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    account_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES coa_accounts(id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE budget_line_monthly (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_line_id INT NOT NULL,
    month TINYINT NOT NULL,                       -- 1-12
    budgeted_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (budget_line_id) REFERENCES budget_lines(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_line_month (budget_line_id, month)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- AI Financial Assistant audit trail
-- ----------------------------------------------------------------------------

CREATE TABLE ai_assistant_query_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    question_text VARCHAR(500) NOT NULL,
    matched_intent VARCHAR(50) DEFAULT NULL,
    answer_text TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Reference / seed data: roles, permissions, default role-permission mapping
-- ============================================================================

INSERT INTO roles (name, description) VALUES
('Admin', 'Full system access including user management and settings'),
('Accountant', 'Creates and edits transactional records; cannot approve'),
('Approver', 'Approves/rejects transactions and views reports'),
('Auditor', 'Read-only access to all modules and the audit log');

INSERT INTO permissions (module_key, action, description) VALUES
('dashboard','view','View dashboard'),
('gl','view','View general ledger'), ('gl','create','Create GL records'), ('gl','post','Approve/post journal entries'),
('ap','view','View accounts payable'), ('ap','create','Create AP records'), ('ap','approve','Approve AP bills/payments'),
('ar','view','View accounts receivable'), ('ar','create','Create AR records'), ('ar','approve','Approve AR invoices/receipts'),
('disbursement','view','View disbursement vouchers'), ('disbursement','create','Create disbursement vouchers'), ('disbursement','approve','Approve/pay disbursement vouchers'),
('collection','view','View collection receipts'), ('collection','create','Create collection receipts'), ('collection','approve','Approve/deposit collection receipts'),
('budget','view','View budgets'), ('budget','create','Create budgets'), ('budget','approve','Approve budgets'),
('cash','view','View cash management'), ('cash','create','Create cash transactions/transfers'), ('cash','approve','Approve reconciliations'),
('tax','view','View tax management'), ('tax','create','Create tax records'), ('tax','approve','Approve tax remittances'),
('reports','view','View financial reports'),
('users','view','View users'), ('users','create','Create/edit users'),
('settings','view','View system settings'), ('settings','create','Edit system settings'),
('audit','view','View audit log'),
('assistant','view','Use AI financial assistant');

-- Admin: granted everything implicitly in code (has_permission short-circuits for Admin role).

-- Accountant: create-level access across transactional modules, no approvals
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE name='Accountant'), id FROM permissions
WHERE (module_key, action) IN (
 ('dashboard','view'),
 ('gl','view'),('gl','create'),
 ('ap','view'),('ap','create'),
 ('ar','view'),('ar','create'),
 ('disbursement','view'),('disbursement','create'),
 ('collection','view'),('collection','create'),
 ('budget','view'),('budget','create'),
 ('cash','view'),('cash','create'),
 ('tax','view'),('tax','create'),
 ('reports','view'),
 ('assistant','view')
);

-- Approver: approvals + view across modules + reports
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE name='Approver'), id FROM permissions
WHERE (module_key, action) IN (
 ('dashboard','view'),
 ('gl','view'),('gl','post'),
 ('ap','view'),('ap','approve'),
 ('ar','view'),('ar','approve'),
 ('disbursement','view'),('disbursement','approve'),
 ('collection','view'),('collection','approve'),
 ('budget','view'),('budget','approve'),
 ('cash','view'),('cash','approve'),
 ('tax','view'),('tax','approve'),
 ('reports','view'),
 ('assistant','view')
);

-- Auditor: view-only everywhere + audit log
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE name='Auditor'), id FROM permissions
WHERE action = 'view';

-- Default decision-support thresholds (editable later by Admin)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('cash_runway_days_threshold', '60', 'Minimum healthy cash runway in days before a Critical alert fires'),
('ap_overdue_pct_threshold', '30', 'AP 60+/90+ bucket as % of total AP that triggers a Warning'),
('ar_aging_pct_threshold', '20', 'AR 90+ bucket as % of total AR that triggers a Warning'),
('budget_warning_pct', '90', 'Budget utilization % that triggers a Warning'),
('budget_critical_pct', '100', 'Budget utilization % that triggers a Critical alert'),
('upcoming_payable_days', '30', 'Look-ahead window (days) for the insufficient-cash-for-payables rule');
