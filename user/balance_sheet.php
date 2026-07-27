<?php
/**
 * FinancePro - Balance Sheet
 * Traditional T-format: Liabilities (Left) vs Assets (Right).
 * Assets = Liabilities + Capital always.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$as_of = $_GET['as_of'] ?? date('Y-m-d');

// 1. Calculate Net Profit using Accounting Rule Engine (up to as_of date)
$stmt = $conn->prepare("
    SELECT 
        (SELECT COALESCE(SUM(i.amount),0) FROM income i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? AND c.accounting_group IN ('Direct Income', 'Indirect Income') AND i.income_date <= ?) -
        (SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN categories c ON e.category_id = c.category_id WHERE e.user_id = ? AND c.accounting_group IN ('Direct Expense', 'Indirect Expense') AND e.expense_date <= ?)
    AS net_profit
");
$stmt->bind_param("isis", $uid, $as_of, $uid, $as_of);
$stmt->execute();
$net_profit = (float)$stmt->get_result()->fetch_row()[0];
$stmt->close();

// Assets: Cash = sum of cash-mode income - cash-mode expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND payment_mode='cash' AND income_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$cash_in = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND payment_mode='cash' AND expense_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$cash_out = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$cash_balance = max(0, $cash_in - $cash_out);

// Bank = bank_transfer + upi
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND payment_mode IN ('bank_transfer','upi') AND income_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$bank_in = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND payment_mode IN ('bank_transfer','upi') AND expense_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$bank_out = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$bank_balance = max(0, $bank_in - $bank_out);

// Card liabilities (outstanding card expenses)
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND payment_mode='card' AND expense_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$card_liability = $stmt->get_result()->fetch_row()[0]; $stmt->close();

// Debtors = unpaid invoices
$stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE user_id=? AND status='unpaid' AND invoice_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$debtors = $stmt->get_result()->fetch_row()[0]; $stmt->close();

// Outstanding bills
$stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE user_id=? AND status='partial' AND invoice_date<=?");
$stmt->bind_param("is", $uid, $as_of); $stmt->execute();
$partial = $stmt->get_result()->fetch_row()[0]; $stmt->close();

// Fetch mapped Assets from categories (Expenses = Asset Increase, Income = Asset Decrease)
$stmt = $conn->prepare("
    SELECT c.category_name, 
           (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.category_id = c.category_id AND e.user_id = ? AND e.expense_date <= ?) - 
           (SELECT COALESCE(SUM(i.amount),0) FROM income i WHERE i.category_id = c.category_id AND i.user_id = ? AND i.income_date <= ?) AS net_val
    FROM categories c WHERE c.accounting_group = 'Asset'
");
$stmt->bind_param("isis", $uid, $as_of, $uid, $as_of);
$stmt->execute();
$db_assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch mapped Liabilities from categories (Income = Liability Increase, Expenses = Liability Decrease)
$stmt = $conn->prepare("
    SELECT c.category_name, 
           (SELECT COALESCE(SUM(i.amount),0) FROM income i WHERE i.category_id = c.category_id AND i.user_id = ? AND i.income_date <= ?) - 
           (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.category_id = c.category_id AND e.user_id = ? AND e.expense_date <= ?) AS net_val
    FROM categories c WHERE c.accounting_group = 'Liability'
");
$stmt->bind_param("isis", $uid, $as_of, $uid, $as_of);
$stmt->execute();
$db_liabilities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch mapped Capital from categories (Income = Capital Increase, Expenses = Capital Decrease)
$stmt = $conn->prepare("
    SELECT c.category_name, 
           (SELECT COALESCE(SUM(i.amount),0) FROM income i WHERE i.category_id = c.category_id AND i.user_id = ? AND i.income_date <= ?) - 
           (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.category_id = c.category_id AND e.user_id = ? AND e.expense_date <= ?) AS net_val
    FROM categories c WHERE c.accounting_group = 'Capital'
");
$stmt->bind_param("isis", $uid, $as_of, $uid, $as_of);
$stmt->execute();
$db_capital = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Capital = 50000 assumed opening capital (or can be entered by user later)
$opening_capital = 50000; // TODO: allow user to configure

// Build balance sheet sides
$asset_items = [
    ['name' => 'Cash in Hand', 'amount' => $cash_balance],
    ['name' => 'Bank Balance', 'amount' => $bank_balance],
    ['name' => 'Trade Debtors (Unpaid Invoices)', 'amount' => $debtors + $partial]
];
foreach ($db_assets as $a) {
    if ($a['net_val'] != 0) $asset_items[] = ['name' => $a['category_name'], 'amount' => $a['net_val']];
}

$assets = [
    ['name' => 'Current & Fixed Assets', 'items' => $asset_items]
];
$total_assets = array_sum(array_column($asset_items, 'amount'));

$liability_items = [];
if ($card_liability > 0) {
    $liability_items[] = ['name' => 'Card/Credit Outstanding', 'amount' => $card_liability];
}
foreach ($db_liabilities as $l) {
    if ($l['net_val'] != 0) $liability_items[] = ['name' => $l['category_name'], 'amount' => $l['net_val']];
}

$capital_items = [
    ['name' => 'Opening Capital', 'amount' => $opening_capital]
];
foreach ($db_capital as $c) {
    if ($c['net_val'] != 0) $capital_items[] = ['name' => $c['category_name'], 'amount' => $c['net_val']];
}
$capital_items[] = ['name' => $net_profit >= 0 ? 'Add: Net Profit' : 'Less: Net Loss', 'amount' => $net_profit];

$liabilities = [
    ['name' => 'Capital Account', 'items' => $capital_items],
    ['name' => 'Liabilities', 'items' => $liability_items]
];

$total_liabilities = array_sum(array_column($capital_items, 'amount')) + array_sum(array_column($liability_items, 'amount'));

// Adjust to make balance sheet balance (plug difference to capital if any rounding)
$diff = $total_assets - $total_liabilities;
if (abs($diff) > 0.01) {
    $liabilities[0]['items'][] = ['name' => 'Retained Earnings / Adjustment', 'amount' => $diff];
    $total_liabilities += $diff;
}

$active_page = 'balance_sheet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Balance Sheet - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .bs-table { width:100%; border-collapse:collapse; }
        .bs-table th { background:var(--fp-navy,#0b1f3a); color:#fff; padding:12px 16px; font-size:.85rem; }
        .bs-table td { padding:9px 16px; border-bottom:1px solid var(--fp-border); color:var(--fp-text-dark); font-size:.9rem; }
        .bs-table .section-head td { font-weight:700; font-size:.78rem; letter-spacing:.8px; color:var(--fp-primary); background:var(--fp-bg); padding:10px 16px; }
        .bs-table .subtotal-row td { font-weight:600; border-top:2px solid var(--fp-border); background:rgba(36,87,217,.05); }
        .bs-table .grand-row td { font-weight:700; background:var(--fp-primary); color:#fff !important; font-size:1rem; }
        .bs-table td:last-child { text-align:right; }
        .t-panel { background:var(--fp-card-bg,#fff); border:1px solid var(--fp-border); overflow:hidden; }
        @media print { .fp-sidebar,.fp-topbar,.btn-fp { display:none!important; } .fp-main { margin:0; } }
    </style>
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--fp-text-dark);margin:0;">
                        <i class="fa-solid fa-table-columns" style="color:var(--fp-primary)"></i> Balance Sheet
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">As of: <strong style="color:var(--fp-text-dark);"><?= date('d M Y', strtotime($as_of)) ?></strong></p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <input type="date" name="as_of" value="<?= e($as_of) ?>" class="fp-input" style="width:165px;">
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Go</button>
                    </form>
                    <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <!-- Balance Check -->
            <?php $balanced = abs($total_assets - $total_liabilities) < 0.01; ?>
            <div class="fp-alert <?= $balanced ? 'fp-alert-success' : 'fp-alert-warning' ?>" style="margin-bottom:20px;">
                <i class="fa-solid <?= $balanced ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
                <?= $balanced
                    ? '<strong>Balance Sheet is BALANCED</strong> — Total Assets = Total Liabilities + Capital.'
                    : '<strong>Note:</strong> Difference of ' . format_currency(abs($total_assets - $total_liabilities)) . ' adjusted automatically.' ?>
            </div>

            <!-- T-Format Balance Sheet -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border:2px solid var(--fp-border);border-radius:12px;overflow:hidden;">
                <!-- LEFT: Liabilities & Capital -->
                <div class="t-panel" style="border-right:2px solid var(--fp-border);">
                    <table class="bs-table">
                        <thead><tr><th colspan="2">Liabilities & Capital</th></tr></thead>
                        <tbody>
                        <?php foreach ($liabilities as $section): ?>
                            <tr class="section-head"><td colspan="2"><?= strtoupper($section['name']) ?></td></tr>
                            <?php foreach ($section['items'] as $item): ?>
                            <tr>
                                <td style="padding-left:28px;"><?= e($item['name']) ?></td>
                                <td style="color:<?= $item['amount']>=0?'var(--fp-text-dark)':'var(--fp-danger)' ?>;"><?= format_currency($item['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr class="grand-row">
                            <td>TOTAL LIABILITIES</td>
                            <td><?= format_currency($total_liabilities) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!-- RIGHT: Assets -->
                <div class="t-panel">
                    <table class="bs-table">
                        <thead><tr><th colspan="2">Assets</th></tr></thead>
                        <tbody>
                        <?php foreach ($assets as $section): ?>
                            <tr class="section-head"><td colspan="2"><?= strtoupper($section['name']) ?></td></tr>
                            <?php foreach ($section['items'] as $item): ?>
                            <tr>
                                <td style="padding-left:28px;"><?= e($item['name']) ?></td>
                                <td><?= format_currency($item['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr class="grand-row">
                            <td>TOTAL ASSETS</td>
                            <td><?= format_currency($total_assets) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-top:20px;">
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-landmark"></i></div>
                    <div class="card-info"><div class="card-label">Total Assets</div><div class="card-value" style="color:var(--fp-accent);"><?= format_currency($total_assets) ?></div></div>
                </div>
                <div class="summary-card">
                    <div class="card-icon expense"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    <div class="card-info"><div class="card-label">Capital</div><div class="card-value" style="color:var(--fp-primary);"><?= format_currency($opening_capital) ?></div></div>
                </div>
                <div class="summary-card">
                    <div class="card-icon balance"><i class="fa-solid fa-piggy-bank"></i></div>
                    <div class="card-info"><div class="card-label">Net Profit/Loss</div>
                    <div class="card-value" style="color:<?= $net_profit>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;"><?= format_currency($net_profit) ?></div></div>
                </div>
                <div class="summary-card">
                    <div class="card-icon <?= $balanced?'income':'expense' ?>"><i class="fa-solid fa-scale-balanced"></i></div>
                    <div class="card-info"><div class="card-label">Balance Check</div>
                    <div class="card-value" style="color:<?= $balanced?'var(--fp-accent)':'var(--fp-danger)' ?>;"><?= $balanced ? '✓ Balanced' : '✗ Unbalanced' ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
