<?php
/**
 * FinancePro - Master Dashboard (Parts 2, 4, 5, 8 Compliant)
 * 12 Top Cards, Cash vs Online Comparisons, 6 Chart.js Graphs, Widgets & Activity Timeline.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
$month = date('n'); 
$year = date('Y');

// Helper query function for sums
function get_val($conn, $sql, $types = '', ...$params) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $val = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();
        return (float)$val;
    }
    return 0.0;
}

// ---- Part 2: Top Cards Metrics ----
$total_income  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=?", 'i', $uid);
$total_expense = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=?", 'i', $uid);

$cash_rec_total = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_receipts WHERE user_id=?", 'i', $uid);
$cash_pay_total = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_payments WHERE user_id=?", 'i', $uid);
$cash_balance   = $cash_rec_total - $cash_pay_total;

$online_rec_total = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=?", 'i', $uid);
$online_pay_total = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_payments WHERE user_id=?", 'i', $uid);
$online_balance   = $online_rec_total - $online_pay_total;

$today_income  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND income_date=?", 'is', $uid, $today);
$today_expense = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date=?", 'is', $uid, $today);

$month_income  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND MONTH(income_date)=? AND YEAR(income_date)=?", 'iii', $uid, $month, $year);
$month_expense = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?", 'iii', $uid, $month, $year);

$profit  = $total_income - $total_expense;
$savings = $month_income > 0 ? max(0, $month_income - $month_expense) : 0;

// ---- Part 8: Widgets Metrics ----
$today_collection = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_receipts WHERE user_id=? AND receipt_date=?", 'is', $uid, $today)
                  + get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND receipt_date=?", 'is', $uid, $today);

$today_payment    = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM cash_payments WHERE user_id=? AND payment_date=?", 'is', $uid, $today)
                  + get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_payments WHERE user_id=? AND payment_date=?", 'is', $uid, $today);

$upi_balance  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='UPI'", 'i', $uid)
              - get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_payments WHERE user_id=? AND payment_mode='UPI'", 'i', $uid);

$bank_balance = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='Bank'", 'i', $uid)
              - get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_payments WHERE user_id=? AND payment_mode='Bank'", 'i', $uid);

$highest_expense = get_val($conn, "SELECT COALESCE(MAX(amount),0) FROM expenses WHERE user_id=?", 'i', $uid);
$highest_income  = get_val($conn, "SELECT COALESCE(MAX(amount),0) FROM income WHERE user_id=?", 'i', $uid);

$total_txns = (int)get_val($conn, "SELECT COUNT(*) FROM income WHERE user_id=?", 'i', $uid)
            + (int)get_val($conn, "SELECT COUNT(*) FROM expenses WHERE user_id=?", 'i', $uid)
            + (int)get_val($conn, "SELECT COUNT(*) FROM cash_receipts WHERE user_id=?", 'i', $uid)
            + (int)get_val($conn, "SELECT COUNT(*) FROM cash_payments WHERE user_id=?", 'i', $uid)
            + (int)get_val($conn, "SELECT COUNT(*) FROM online_receipts WHERE user_id=?", 'i', $uid)
            + (int)get_val($conn, "SELECT COUNT(*) FROM online_payments WHERE user_id=?", 'i', $uid);

// ---- Part 5: Chart.js Data Generation ----
$m_labels = []; $m_inc = []; $m_exp = []; $m_profit = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('n', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $m_labels[] = date('M', strtotime("-$i months"));

    $inc_val = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND MONTH(income_date)=? AND YEAR(income_date)=?", 'iii', $uid, $m, $y);
    $exp_val = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?", 'iii', $uid, $m, $y);

    $m_inc[] = $inc_val;
    $m_exp[] = $exp_val;
    $m_profit[] = $inc_val - $exp_val;
}

// Payment Mode Distribution
$mode_upi   = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='UPI'", 'i', $uid);
$mode_bank  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='Bank'", 'i', $uid);
$mode_cash  = $cash_rec_total;
$mode_card  = get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='Card'", 'i', $uid);
$mode_wallet= get_val($conn, "SELECT COALESCE(SUM(amount),0) FROM online_receipts WHERE user_id=? AND payment_mode='Wallet'", 'i', $uid);

// ---- Unified Recent Activity Timeline (Latest 10) ----
$timeline_sql = "
    (SELECT 'Cash Receipt' as module, transaction_id, receipt_date as txn_date, description, amount, 'Completed' as status FROM cash_receipts WHERE user_id=?)
    UNION ALL
    (SELECT 'Cash Payment' as module, transaction_id, payment_date, description, amount, 'Completed' as status FROM cash_payments WHERE user_id=?)
    UNION ALL
    (SELECT 'Online Receipt' as module, transaction_id, receipt_date, description, amount, 'Completed' as status FROM online_receipts WHERE user_id=?)
    UNION ALL
    (SELECT 'Online Payment' as module, transaction_id, payment_date, description, amount, 'Completed' as status FROM online_payments WHERE user_id=?)
    ORDER BY txn_date DESC LIMIT 10";
$t_stmt = $conn->prepare($timeline_sql);
$t_stmt->bind_param('iiii', $uid, $uid, $uid, $uid);
$t_stmt->execute();
$timeline_rows = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$t_stmt->close();

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Dashboard - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/executive-style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <main class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <!-- Title Header -->
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                <div>
                    <h1 class="page-title mb-1"><i class="fa-solid fa-chart-line"></i> Master Financial Dashboard</h1>
                    <p class="text-muted mb-0">Unified accounting overview with Cash vs Online comparisons</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-bar">
                <a href="income.php" class="quick-action-btn"><i class="fa-solid fa-plus"></i> Add Income</a>
                <a href="expenses.php" class="quick-action-btn"><i class="fa-solid fa-minus"></i> Add Expense</a>
                <a href="cash_receipts.php" class="quick-action-btn"><i class="fa-solid fa-money-bill-wave"></i> Cash Receipt</a>
                <a href="cash_payments.php" class="quick-action-btn"><i class="fa-solid fa-wallet"></i> Cash Payment</a>
                <a href="online_receipts.php" class="quick-action-btn"><i class="fa-solid fa-building-columns"></i> Online Receipt</a>
                <a href="online_payments.php" class="quick-action-btn"><i class="fa-solid fa-credit-card"></i> Online Payment</a>
                <a href="invoices.php" class="quick-action-btn"><i class="fa-solid fa-file-invoice"></i> Create Invoice</a>
                <a href="reports.php" class="quick-action-btn" style="background: linear-gradient(135deg, #17b978 0%, #0d9c5f 100%);"><i class="fa-solid fa-download"></i> Reports</a>
            </div>

            <!-- PART 2: Top Cards Grid (10 Essential Metrics) -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card card-color-income p-3">
                        <small class="text-muted fw-semibold">Total Income</small>
                        <h3 class="fw-bold text-success mb-0"><?= format_currency($total_income) ?></h3>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card card-color-expense p-3">
                        <small class="text-muted fw-semibold">Total Expense</small>
                        <h3 class="fw-bold text-danger mb-0"><?= format_currency($total_expense) ?></h3>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card card-color-cash p-3">
                        <small class="text-muted fw-semibold">Cash Balance</small>
                        <h3 class="fw-bold text-warning mb-0"><?= format_currency($cash_balance) ?></h3>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card card-color-bank p-3">
                        <small class="text-muted fw-semibold">Online Balance</small>
                        <h3 class="fw-bold text-info mb-0"><?= format_currency($online_balance) ?></h3>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card p-3">
                        <small class="text-muted fw-semibold">Today's Income</small>
                        <h4 class="fw-bold text-success mb-0"><?= format_currency($today_income) ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card p-3">
                        <small class="text-muted fw-semibold">Today's Expense</small>
                        <h4 class="fw-bold text-danger mb-0"><?= format_currency($today_expense) ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card p-3">
                        <small class="text-muted fw-semibold">This Month Income</small>
                        <h4 class="fw-bold text-success mb-0"><?= format_currency($month_income) ?></h4>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-glass rounded-card p-3">
                        <small class="text-muted fw-semibold">This Month Expense</small>
                        <h4 class="fw-bold text-danger mb-0"><?= format_currency($month_expense) ?></h4>
                    </div>
                </div>
            </div>

            <!-- PART 4: Dashboard Comparison Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-4">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-money-bill-wave text-success"></i> Cash Account Overview</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Cash Received:</span> <strong class="text-success"><?= format_currency($cash_rec_total) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Cash Paid:</span> <strong class="text-danger"><?= format_currency($cash_pay_total) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fs-5 fw-bold">
                            <span>Cash Remaining:</span> <span class="text-primary"><?= format_currency($cash_balance) ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-4">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-building-columns text-info"></i> Online Account Overview</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Online Received:</span> <strong class="text-success"><?= format_currency($online_rec_total) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Online Paid:</span> <strong class="text-danger"><?= format_currency($online_pay_total) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fs-5 fw-bold">
                            <span>Online Remaining:</span> <span class="text-info"><?= format_currency($online_balance) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PART 5: Graphs & Charts Grid -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-3 h-100">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-scale-balanced"></i> Cash vs Online Balance</h6>
                        <div style="height:250px;">
                            <canvas id="cashVsOnlineChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-3 h-100">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-chart-column"></i> Monthly Income vs Expense</h6>
                        <div style="height:250px;">
                            <canvas id="incExpChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-3 h-100">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-pie-chart"></i> Payment Mode Distribution</h6>
                        <div style="height:250px; display:flex; justify-content:center;">
                            <canvas id="modeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-glass rounded-card p-3 h-100">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-arrow-trend-up"></i> Monthly Profit Trend</h6>
                        <div style="height:250px;">
                            <canvas id="profitTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PART 8: Widgets & Recent Activity Timeline -->
            <div class="card card-glass rounded-card p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity Timeline</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($timeline_rows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No recent activity logged.</td></tr>
                            <?php else: ?>
                                <?php foreach ($timeline_rows as $row): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= e($row['module']) ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?= e($row['transaction_id']) ?></span></td>
                                        <td><?= date('d M Y', strtotime($row['txn_date'])) ?></td>
                                        <td><?= e($row['description'] ?: '—') ?></td>
                                        <td class="fw-bold"><?= format_currency($row['amount']) ?></td>
                                        <td><span class="badge bg-success"><?= e($row['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

// 1. Cash vs Online
new Chart(document.getElementById('cashVsOnlineChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Cash', 'Online'],
        datasets: [{ label: 'Remaining Balance', data: [<?= $cash_balance ?>, <?= $online_balance ?>], backgroundColor: ['#f2a93b', '#3498db'], borderRadius: 6 }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// 2. Income vs Expense
new Chart(document.getElementById('incExpChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($m_labels) ?>,
        datasets: [
            { label: 'Income', data: <?= json_encode($m_inc) ?>, backgroundColor: '#17b978', borderRadius: 6 },
            { label: 'Expense', data: <?= json_encode($m_exp) ?>, backgroundColor: '#e5484d', borderRadius: 6 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// 3. Payment Mode Distribution
new Chart(document.getElementById('modeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Cash', 'UPI', 'Bank', 'Card', 'Wallet'],
        datasets: [{ data: [<?= $mode_cash ?>, <?= $mode_upi ?>, <?= $mode_bank ?>, <?= $mode_card ?>, <?= $mode_wallet ?>], backgroundColor: ['#f2a93b', '#17b978', '#3498db', '#9b59b6', '#34495e'] }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// 4. Monthly Profit Trend
new Chart(document.getElementById('profitTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($m_labels) ?>,
        datasets: [{ label: 'Profit', data: <?= json_encode($m_profit) ?>, borderColor: '#17b978', backgroundColor: 'rgba(23,185,120,0.1)', fill: true, tension: 0.3 }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
