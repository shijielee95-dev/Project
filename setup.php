<?php
/**
 * setup.php
 * ─────────────────────────────────────────────
 * Run this ONCE in your browser:
 *   http://yoursite.com/einvoice/setup.php
 *
 * It will:
 *   1. Create all database tables
 *   2. Create the admin user with a correct
 *      password hash generated on YOUR server
 *   3. Insert a blank company profile
 *
 * DELETE THIS FILE after running it.
 * ─────────────────────────────────────────────
 */
require_once 'config/bootstrap.php';

$errors  = [];
$success = [];
$pdo     = db();

// ── 1. Create tables ──────────────────────────

$tables = [

'users' => "CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'user_sessions' => "CREATE TABLE IF NOT EXISTS user_sessions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(128) NOT NULL UNIQUE,
    expire_at  DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'company_profiles' => "CREATE TABLE IF NOT EXISTS company_profiles (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name      VARCHAR(255) NOT NULL DEFAULT '',
    company_tin       VARCHAR(50)  NOT NULL DEFAULT '',
    id_type           ENUM('NRIC','BRN','ARMY','PASSPORT') DEFAULT 'BRN',
    id_no             VARCHAR(100) NOT NULL DEFAULT '',
    sst_no            VARCHAR(100) DEFAULT '',
    tourism_tax_no    VARCHAR(100) DEFAULT '',
    msic_code         VARCHAR(20)  NOT NULL DEFAULT '',
    business_activity VARCHAR(255) NOT NULL DEFAULT '',
    address_line_0    VARCHAR(255) DEFAULT '',
    address_line_1    VARCHAR(255) DEFAULT '',
    address_line_2    VARCHAR(255) DEFAULT '',
    postal_code       VARCHAR(20)  DEFAULT '',
    city              VARCHAR(100) DEFAULT '',
    state_code        VARCHAR(5)   DEFAULT '',
    country_code      VARCHAR(5)   DEFAULT 'MYS',
    phone             VARCHAR(50)  DEFAULT '',
    company_email     VARCHAR(255) DEFAULT '',
    contact_email     VARCHAR(255) DEFAULT '',
    client_id         VARCHAR(255) DEFAULT '',
    client_secret_1   VARCHAR(255) DEFAULT '',
    client_secret_2   VARCHAR(255) DEFAULT '',
    logo_path         VARCHAR(500) DEFAULT '',
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'customers' => "CREATE TABLE IF NOT EXISTS customers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name  VARCHAR(255) NOT NULL,
    tin            VARCHAR(50)  NOT NULL DEFAULT '',
    id_type        ENUM('NRIC','BRN','ARMY','PASSPORT','NA') DEFAULT 'BRN',
    reg_no         VARCHAR(100) DEFAULT '',
    sst_reg_no     VARCHAR(100) DEFAULT '',
    email          VARCHAR(255) DEFAULT '',
    phone          VARCHAR(50)  DEFAULT '',
    address_line_0 VARCHAR(255) DEFAULT '',
    address_line_1 VARCHAR(255) DEFAULT '',
    city           VARCHAR(100) DEFAULT '',
    postal_code    VARCHAR(20)  DEFAULT '',
    state_code     VARCHAR(5)   DEFAULT '',
    country_code   VARCHAR(5)   DEFAULT 'MYS',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'invoices' => "CREATE TABLE IF NOT EXISTS invoices (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no       VARCHAR(50)   NOT NULL UNIQUE,
    customer_id      INT UNSIGNED  DEFAULT NULL,
    customer_name    VARCHAR(255)  NOT NULL DEFAULT '',
    customer_tin     VARCHAR(50)   DEFAULT '',
    customer_reg_no  VARCHAR(100)  DEFAULT '',
    customer_email   VARCHAR(255)  DEFAULT '',
    customer_phone   VARCHAR(50)   DEFAULT '',
    customer_address TEXT,
    invoice_date     DATE          NOT NULL,
    due_date         DATE          DEFAULT NULL,
    subtotal         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_type         ENUM('none','sst6','sst10','service6') NOT NULL DEFAULT 'none',
    tax_amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency         CHAR(3)       NOT NULL DEFAULT 'MYR',
    notes            TEXT,
    status           ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    pdf_path         VARCHAR(500)  DEFAULT NULL,
    lhdn_invoice_type VARCHAR(5)  DEFAULT '01',
    msic_code        VARCHAR(20)   DEFAULT '',
    payment_method   VARCHAR(5)    DEFAULT '01',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'invoice_items' => "CREATE TABLE IF NOT EXISTS invoice_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id     INT UNSIGNED  NOT NULL,
    description    VARCHAR(500)  NOT NULL,
    quantity       DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    unit_price     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    tax_type       ENUM('none','sst6','sst10','service6') NOT NULL DEFAULT 'none',
    tax_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    classification VARCHAR(20)   DEFAULT '',
    sort_order     SMALLINT      NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'invoice_sequences' => "CREATE TABLE IF NOT EXISTS invoice_sequences (
    prefix   VARCHAR(10)  NOT NULL DEFAULT 'INV',
    year     YEAR         NOT NULL,
    next_no  INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (prefix, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'lhdn_submissions' => "CREATE TABLE IF NOT EXISTS lhdn_submissions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id     INT UNSIGNED  NOT NULL,
    environment    ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    submission_uid VARCHAR(100)  DEFAULT NULL,
    document_uuid  VARCHAR(100)  DEFAULT NULL,
    long_id        VARCHAR(255)  DEFAULT NULL,
    status         ENUM('pending','valid','invalid','cancelled') NOT NULL DEFAULT 'pending',
    error_message  TEXT          DEFAULT NULL,
    raw_request    LONGTEXT      DEFAULT NULL,
    raw_response   LONGTEXT      DEFAULT NULL,
    submitted_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    validated_at   DATETIME      DEFAULT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'log_book' => "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    user_name VARCHAR(255) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) DEFAULT NULL,
    record_id INT UNSIGNED DEFAULT NULL,
    old_value LONGTEXT DEFAULT NULL,
    new_value LONGTEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_table (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $success[] = "Table <strong>{$name}</strong> — OK";
    } catch (PDOException $e) {
        $errors[] = "Table <strong>{$name}</strong>: " . $e->getMessage();
    }
}

// ── 2. Company profile default row ────────────
try {
    $pdo->exec("INSERT IGNORE INTO company_profiles (id) VALUES (1)");
    $success[] = "Company profile row — OK";
} catch (PDOException $e) {
    $errors[] = "Company profile: " . $e->getMessage();
}

// ── 3. Admin user with fresh hash ─────────────
$adminName     = 'Admin';
$adminEmail    = 'admin@company.com';
$adminPassword = 'admin123';
$adminHash     = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$adminEmail]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update password hash on existing user
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
            ->execute([$adminHash, $adminEmail]);
        $success[] = "Admin user password <strong>reset</strong> — OK";
    } else {
        $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)")
            ->execute([$adminName, $adminEmail, $adminHash]);
        $success[] = "Admin user <strong>created</strong> — OK";
    }
} catch (PDOException $e) {
    $errors[] = "Admin user: " . $e->getMessage();
}

$allGood = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — e-Invoice Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 w-full max-w-lg p-8">

    <!-- Icon -->
    <div class="w-14 h-14 rounded-2xl <?= $allGood ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center mx-auto mb-5">
        <?php if ($allGood): ?>
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <?php else: ?>
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <?php endif; ?>
    </div>

    <h1 class="text-xl font-semibold text-slate-800 text-center mb-1">
        <?= $allGood ? 'Setup Complete' : 'Setup Completed with Errors' ?>
    </h1>
    <p class="text-sm text-slate-400 text-center mb-6">
        e-Invoice Portal — Database Setup
    </p>

    <!-- Results -->
    <div class="space-y-2 mb-6">
        <?php foreach ($success as $msg): ?>
        <div class="flex items-start gap-2.5 text-sm text-green-700 bg-green-50 rounded-lg px-3 py-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
            <span><?= $msg ?></span>
        </div>
        <?php endforeach; ?>
        <?php foreach ($errors as $msg): ?>
        <div class="flex items-start gap-2.5 text-sm text-red-700 bg-red-50 rounded-lg px-3 py-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            <span><?= $msg ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($allGood): ?>
    <!-- Login credentials -->
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Default Login Credentials</p>
        <div class="space-y-1.5 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Email</span>
                <code class="text-slate-800 font-medium">admin@company.com</code>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Password</span>
                <code class="text-slate-800 font-medium">admin123</code>
            </div>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-6">
        <p class="text-xs text-amber-700 flex items-start gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span><strong>Important:</strong> Delete this file (<code>setup.php</code>) from your server immediately after logging in. Change your password on first login.</span>
        </p>
    </div>

    <a href="login.php"
       class="block w-full text-center py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition-colors">
        Go to Login
    </a>
    <?php else: ?>
    <p class="text-sm text-slate-500 text-center">
        Fix the errors above and refresh this page to try again.<br>
        Check your <code class="text-slate-700">config/db.php</code> settings.
    </p>
    <?php endif; ?>

</div>

</body>
</html>
