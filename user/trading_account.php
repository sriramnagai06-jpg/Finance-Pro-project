<?php
/**
 * FinancePro - Trading Account
 * Direct income (Sales/Revenue) vs direct expenses (Purchases/COGS).
 * Calculates Gross Profit / Gross Loss automatically.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0); // 0 = full year

$date_from = $month ? "$year-" . sprintf('%02d', $month) . "-01" : "$year-01-01";
$date_to   = $month ? date('Y-m-t', strtotime($date_from))       : "$year-12-31";

// Direct Income (Sales/Revenue/Closing Stock)
$stmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(i.amount),0) AS total
    FROM income i
    JOIN categories c ON i.category_id = c.category_id
    WHERE i.user_id = ? AND c.accounting_group = 'Direct Income' 
      AND i.income_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$sales_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Direct Expenses (Purchases/Wages/Freight/Opening Stock)
$stmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e
    JOIN categories c ON e.category_id = c.category_id
    WHERE e.user_id = ? AND c.accounting_group = 'Direct Expense' 
      AND e.expense_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$direct_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_sales   = array_sum(array_column($sales_rows, 'total'));
$total_direct  = array_sum(array_column($direct_rows, 'total'));
$gross_profit  = $total_sales - $total_direct;


$active_page = 'trading';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Trading Account - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .acc-table { width:100%; border-collapse:collapse; }
        .acc-table th { background:var(--fp-navy,#0b1f3a); color:#fff; padding:10px 16px; font-size:.85rem; }
        .acc-table td { padding:9px 16px; border-bottom:1px solid var(--fp-border); color:var(--fp-text-dark); font-size:.9rem; }
        .acc-table .section-head td { font-weight:700; font-size:.8rem; letter-spacing:.5px; color:var(--fp-primary); background:var(--fp-bg); }
        .acc-table .total-row td { font-weight:700; border-top:2px solid var(--fp-border); }
        .acc-table .gross-row td { font-weight:700; background:var(--fp-primary); color:#fff !important; }
        .acc-table td:last-child { text-align:right; font-weight:600; }
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
                        <i class="fa-solid fa-store" style="color:var(--fp-primary)"></i> Trading Account
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">
                        For the period: <strong style="color:var(--fp-text-dark);"><?= date('d M Y', strtotime($date_from)) ?> — <?= date('d M Y', strtotime($date_to)) ?></strong>
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
                            <?php $mn=['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            for ($m=1;$m<=12;$m++): ?>
                            <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= $mn[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Go</button>
                    </form>
                    <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <!-- T-Format Trading Account -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border:2px solid var(--fp-border);border-radius:12px;overflow:hidden;">
                <!-- LEFT: Dr. Side (Expenses/Purchases) -->
                <div class="t-panel" style="border-radius:0;border:none;border-right:2px solid var(--fp-border);">
                    <table class="acc-table">
                        <thead><tr><th colspan="2">Dr. (Debit Side — Expenses)</th></tr></thead>
                        <tbody>
                            <tr class="section-head"><td colspan="2">DIRECT EXPENSES</td></tr>
                            <?php foreach ($direct_rows as $dr): ?>
                            <tr><td><?= e($dr['category_name']) ?></td><td><?= format_currency($dr['total']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($direct_rows)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;">No direct expenses</td></tr>
                            <?php endif; ?>
                            <?php if ($gross_profit > 0): ?>
                            <tr class="gross-row">
                                <td>Gross Profit c/d</td>
                                <td><?= format_currency($gross_profit) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td><?= format_currency(max($total_sales, $total_direct)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- RIGHT: Cr. Side (Sales/Revenue) -->
                <div class="t-panel" style="border-radius:0;border:none;">
                    <table class="acc-table">
                        <thead><tr><th colspan="2">Cr. (Credit Side — Income)</th></tr></thead>
                        <tbody>
                            <tr class="section-head"><td colspan="2">SALES / REVENUE</td></tr>
                            <?php foreach ($sales_rows as $sr): ?>
                            <tr><td><?= e($sr['category_name']) ?></td><td><?= format_currency($sr['total']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($sales_rows)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;">No income records</td></tr>
                            <?php endif; ?>
                            <?php if ($gross_profit < 0): ?>
                            <tr class="gross-row">
                                <td>Gross Loss c/d</td>
                                <td><?= format_currency(abs($gross_profit)) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td><?= format_currency(max($total_sales, $total_direct)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gross Profit Summary -->
            <div class="summary-card" style="margin-top:20px;">
                <div class="card-icon <?= $gross_profit>=0?'income':'expense' ?>">
                    <i class="fa-solid <?= $gross_profit>=0?'fa-arrow-trend-up':'fa-arrow-trend-down' ?>"></i>
                </div>
                <div class="card-info">
                    <div class="card-label"><?= $gross_profit >= 0 ? 'Gross Profit' : 'Gross Loss' ?></div>
                    <div class="card-value" style="color:<?= $gross_profit>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;">
                        <?= format_currency(abs($gross_profit)) ?>
                    </div>
                    <div style="font-size:.78rem;color:var(--fp-text-muted);">Gross Margin: <?= $total_sales>0?round($gross_profit/$total_sales*100,1):0 ?>%</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
