<?php
/**
 * FinancePro - Fund Flow Statement
 * Sources and Applications of funds for a financial period.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);

$date_from = $month ? "$year-" . sprintf('%02d', $month) . "-01" : "$year-01-01";
$date_to   = $month ? date('Y-m-t', strtotime($date_from)) : "$year-12-31";

// Sources of Funds = Income categorised
$stmt = $conn->prepare("
    SELECT c.category_name, c.category_type, COALESCE(SUM(i.amount),0) AS total
    FROM income i JOIN categories c ON i.category_id=c.category_id
    WHERE i.user_id=? AND i.income_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$sources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Applications of Funds = Expenses categorised
$stmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e JOIN categories c ON e.category_id=c.category_id
    WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?
    GROUP BY c.category_id ORDER BY total DESC
");
$stmt->bind_param("iss", $uid, $date_from, $date_to);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_sources      = array_sum(array_column($sources, 'total'));
$total_applications = array_sum(array_column($applications, 'total'));
$net_fund_flow      = $total_sources - $total_applications;

$active_page = 'fund_flow';
$mn = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Fund Flow Statement - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .ff-table { width:100%; border-collapse:collapse; }
        .ff-table th { background:var(--fp-navy,#0b1f3a); color:#fff; padding:12px 16px; font-size:.85rem; }
        .ff-table td { padding:10px 16px; border-bottom:1px solid var(--fp-border); color:var(--fp-text-dark); font-size:.9rem; }
        .ff-table .section-head td { font-weight:700; font-size:.78rem; letter-spacing:.8px; color:var(--fp-primary); background:var(--fp-bg); }
        .ff-table .total-row td { font-weight:700; border-top:2px solid var(--fp-border); }
        .ff-table .net-row td { font-weight:700; font-size:1rem; background:var(--fp-primary); color:#fff !important; }
        .ff-table td:last-child { text-align:right; font-weight:600; }
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
                        <i class="fa-solid fa-water" style="color:var(--fp-primary)"></i> Fund Flow Statement
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

            <!-- Summary Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-plus-circle"></i></div>
                    <div class="card-info"><div class="card-label">Total Sources</div><div class="card-value" style="color:var(--fp-accent);"><?= format_currency($total_sources) ?></div></div>
                </div>
                <div class="summary-card">
                    <div class="card-icon expense"><i class="fa-solid fa-minus-circle"></i></div>
                    <div class="card-info"><div class="card-label">Total Applications</div><div class="card-value" style="color:var(--fp-danger);"><?= format_currency($total_applications) ?></div></div>
                </div>
                <div class="summary-card">
                    <div class="card-icon <?= $net_fund_flow>=0?'income':'expense' ?>"><i class="fa-solid fa-water"></i></div>
                    <div class="card-info">
                        <div class="card-label"><?= $net_fund_flow>=0?'Net Fund Inflow':'Net Fund Outflow' ?></div>
                        <div class="card-value" style="color:<?= $net_fund_flow>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;"><?= format_currency(abs($net_fund_flow)) ?></div>
                    </div>
                </div>
            </div>

            <!-- T-Format Fund Flow -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border:2px solid var(--fp-border);border-radius:12px;overflow:hidden;">
                <!-- LEFT: Sources of Funds -->
                <div class="t-panel" style="border-right:2px solid var(--fp-border);">
                    <table class="ff-table">
                        <thead><tr><th colspan="2">Sources of Funds (Inflows)</th></tr></thead>
                        <tbody>
                            <tr class="section-head"><td colspan="2">INCOME SOURCES</td></tr>
                            <?php foreach ($sources as $s): ?>
                            <tr>
                                <td style="padding-left:24px;"><?= e($s['category_name']) ?></td>
                                <td><?= format_currency($s['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sources)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;padding-left:24px;">No income sources</td></tr>
                            <?php endif; ?>
                            <tr class="total-row"><td><strong>TOTAL SOURCES</strong></td><td><?= format_currency($total_sources) ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- RIGHT: Applications of Funds -->
                <div class="t-panel">
                    <table class="ff-table">
                        <thead><tr><th colspan="2">Applications of Funds (Outflows)</th></tr></thead>
                        <tbody>
                            <tr class="section-head"><td colspan="2">EXPENSE APPLICATIONS</td></tr>
                            <?php foreach ($applications as $a): ?>
                            <tr>
                                <td style="padding-left:24px;"><?= e($a['category_name']) ?></td>
                                <td><?= format_currency($a['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($applications)): ?>
                            <tr><td colspan="2" style="color:var(--fp-text-muted);font-style:italic;padding-left:24px;">No expenses</td></tr>
                            <?php endif; ?>
                            <tr class="total-row"><td><strong>TOTAL APPLICATIONS</strong></td><td><?= format_currency($total_applications) ?></td></tr>
                            <?php if ($net_fund_flow >= 0): ?>
                            <tr class="net-row">
                                <td>NET INCREASE IN WORKING CAPITAL</td>
                                <td><?= format_currency($net_fund_flow) ?></td>
                            </tr>
                            <?php else: ?>
                            <tr class="net-row" style="background:var(--fp-danger)!important;">
                                <td>NET DECREASE IN WORKING CAPITAL</td>
                                <td><?= format_currency(abs($net_fund_flow)) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
