<?php
/**
 * FinancePro - Profit & Loss Account
 * Indirect income/expenses leading to Net Profit / Net Loss.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);

$date_from = $month ? "$year-" . sprintf('%02d', $month) . "-01" : "$year-01-01";
$date_to   = $month ? date('Y-m-t', strtotime($date_from)) : "$year-12-31";

// 1. Calculate Gross Profit from Trading Account (Direct Income - Direct Expense)
$stmt = $conn->prepare("
    SELECT 
        (SELECT COALESCE(SUM(i.amount),0) FROM income i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? AND c.accounting_group = 'Direct Income' AND i.income_date BETWEEN ? AND ?) -
        (SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN categories c ON e.category_id = c.category_id WHERE e.user_id = ? AND c.accounting_group = 'Direct Expense' AND e.expense_date BETWEEN ? AND ?)
    AS gross_profit
");
$stmt->bind_param("ississ", $uid, $date_from, $date_to, $uid, $date_from, $date_to);
$stmt->execute();
$gross_profit = (float)$stmt->get_result()->fetch_row()[0];
$stmt->close();

// 2. Fetch Indirect Income (P&L Credit Side)
$stmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(i.amount),0) AS total
    FROM income i JOIN categories c ON i.category_id = c.category_id
    WHERE i.user_id = ? AND c.accounting_group = 'Indirect Income' AND i.income_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$indirect_income_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Fetch Indirect Expenses (P&L Debit Side)
$stmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e JOIN categories c ON e.category_id = c.category_id
    WHERE e.user_id = ? AND c.accounting_group = 'Indirect Expense' AND e.expense_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$indirect_expense_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_indirect_income  = array_sum(array_column($indirect_income_rows, 'total'));
$total_indirect_expense = array_sum(array_column($indirect_expense_rows, 'total'));

$net_profit = $gross_profit + $total_indirect_income - $total_indirect_expense;

$total_pl_income  = ($gross_profit >= 0 ? $gross_profit : 0) + $total_indirect_income;
$total_pl_expense = ($gross_profit < 0 ? abs($gross_profit) : 0) + $total_indirect_expense;

$active_page = 'pl';
$mn = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Profit & Loss - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .pl-table { width:100%; border-collapse:collapse; }
        .pl-table th { background:var(--fp-navy,#0b1f3a); color:#fff; padding:12px 16px; font-size:.85rem; }
        .pl-table td { padding:9px 16px; border-bottom:1px solid var(--fp-border); color:var(--fp-text-dark); font-size:.9rem; }
        .pl-table .section-head td { font-weight:700; font-size:.78rem; letter-spacing:.8px; color:var(--fp-primary); background:var(--fp-bg); padding:10px 16px; }
        .pl-table .subtotal-row td { font-weight:600; background:rgba(36,87,217,.07); }
        .pl-table .profit-row td { font-weight:700; background:var(--fp-accent); color:#fff !important; }
        .pl-table .loss-row td { font-weight:700; background:var(--fp-danger); color:#fff !important; }
        .pl-table .grand-row td { font-weight:700; background:var(--fp-primary); color:#fff !important; font-size:1rem; }
        .pl-table td:last-child { text-align:right; }
        .t-panel { background:var(--fp-card-bg,#fff); border:1px solid var(--fp-border); border-radius:12px; overflow:hidden; }
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
                        <i class="fa-solid fa-arrow-trend-up" style="color:var(--fp-primary)"></i> Profit & Loss Account
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">
                        Period: <strong style="color:var(--fp-text-dark);"><?= $month ? $mn[$month].' '.$year : 'Full Year '.$year ?></strong>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select name="year" class="fp-select" style="width:100px;">
                            <?php for ($y = date('Y'); $y >= date('Y')-4; $y--): ?>
                            <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="month" class="fp-select" style="width:130px;">
                            <option value="0" <?= $month==0?'selected':'' ?>>Full Year</option>
                            <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= $mn[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Go</button>
                    </form>
                    <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <!-- T-Format P&L -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border:2px solid var(--fp-border);border-radius:12px;overflow:hidden;">
                <!-- LEFT: Expenses / Losses -->
                <div class="t-panel" style="border-radius:0;border:none;border-right:2px solid var(--fp-border);">
                    <table class="pl-table">
                        <thead><tr><th colspan="2">Dr. — Expenses & Losses</th></tr></thead>
                        <tbody>
                            <?php if ($gross_profit < 0): ?>
                            <tr><td style="font-weight:700;">To Gross Loss b/d</td><td class="text-danger"><?= format_currency(abs($gross_profit)) ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($indirect_expense_rows as $r): ?>
                            <tr><td>To <?= e($r['category_name']) ?></td><td><?= format_currency($r['total']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($indirect_expense_rows)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;">No indirect expenses</td></tr>
                            <?php endif; ?>

                            <?php if ($net_profit > 0): ?>
                            <tr class="profit-row">
                                <td>To Net Profit (transferred to Capital A/c)</td>
                                <td><?= format_currency($net_profit) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php $grand_total = max(abs($gross_profit > 0 ? $gross_profit : 0) + $total_indirect_income, abs($gross_profit < 0 ? $gross_profit : 0) + $total_indirect_expense); ?>
                            <tr class="grand-row">
                                <td>TOTAL</td>
                                <td><?= format_currency($grand_total) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- RIGHT: Income / Gains -->
                <div class="t-panel" style="border-radius:0;border:none;">
                    <table class="pl-table">
                        <thead><tr><th colspan="2">Cr. — Income & Gains</th></tr></thead>
                        <tbody>
                            <?php if ($gross_profit > 0): ?>
                            <tr><td style="font-weight:700;color:var(--fp-accent);">By Gross Profit b/d</td><td style="font-weight:700;color:var(--fp-accent);"><?= format_currency($gross_profit) ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($indirect_income_rows as $r): ?>
                            <tr><td>By <?= e($r['category_name']) ?></td><td><?= format_currency($r['total']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($indirect_income_rows)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;">No indirect income</td></tr>
                            <?php endif; ?>

                            <?php if ($net_profit < 0): ?>
                            <tr class="loss-row">
                                <td>By Net Loss (transferred to Capital A/c)</td>
                                <td><?= format_currency(abs($net_profit)) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="grand-row">
                                <td>TOTAL</td>
                                <td><?= format_currency($grand_total) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- KPI Summary Row -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:20px;">
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Revenue (Cr.)</div>
                        <div class="card-value" style="color:var(--fp-accent);"><?= format_currency($total_pl_income) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon expense"><i class="fa-solid fa-receipt"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Expenses (Dr.)</div>
                        <div class="card-value" style="color:var(--fp-danger);"><?= format_currency($total_pl_expense) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon balance"><i class="fa-solid fa-store"></i></div>
                    <div class="card-info">
                        <div class="card-label">Gross Profit</div>
                        <div class="card-value" style="color:<?= $gross_profit>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;"><?= format_currency($gross_profit) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon <?= $net_profit>=0?'income':'expense' ?>">
                        <i class="fa-solid <?= $net_profit>=0?'fa-arrow-trend-up':'fa-arrow-trend-down' ?>"></i>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
                        <div class="card-value" style="color:<?= $net_profit>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;">
                            <?= format_currency(abs($net_profit)) ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--fp-text-muted);">
                            Margin: <?= $total_pl_income>0?round($net_profit/$total_pl_income*100,1):0 ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
