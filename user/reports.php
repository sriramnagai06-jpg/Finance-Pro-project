<?php
/**
 * FinancePro - Multi-Report Generator (Part 7 Compliant)
 * Generates reports for Cash, Online, Receipt, Payment, Daily, Weekly, Monthly, Yearly, Profit, Expense, Income, Cash Flow.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$uid = $_SESSION['user_id'];

$report_type = clean_input($_GET['report'] ?? 'cash_report');
$period      = clean_input($_GET['period'] ?? 'monthly');
$sel_year    = (int)($_GET['year'] ?? date('Y'));
$sel_month   = (int)($_GET['month'] ?? date('n'));
$export      = clean_input($_GET['export'] ?? '');

$rows = [];
$title = "Financial Report";

// Handle Date Constraints
if ($period === 'daily') {
    $date_clause = "WHERE user_id=$uid AND DATE(created_at) = CURDATE()";
} elseif ($period === 'weekly') {
    $date_clause = "WHERE user_id=$uid AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($period === 'yearly') {
    $date_clause = "WHERE user_id=$uid AND YEAR(created_at) = $sel_year";
} else { // monthly
    $date_clause = "WHERE user_id=$uid AND MONTH(created_at) = $sel_month AND YEAR(created_at) = $sel_year";
}

// Module Query Dispatch
if ($report_type === 'cash_report') {
    $title = "Cash Report";
    $sql = "SELECT 'Cash Receipt' as type, transaction_id, receipt_date as r_date, category, description, amount FROM cash_receipts $date_clause UNION ALL SELECT 'Cash Payment' as type, transaction_id, payment_date as r_date, category, description, amount FROM cash_payments $date_clause ORDER BY r_date DESC";
} elseif ($report_type === 'online_report') {
    $title = "Online Report";
    $sql = "SELECT 'Online Receipt' as type, transaction_id, receipt_date as r_date, category, description, amount FROM online_receipts $date_clause UNION ALL SELECT 'Online Payment' as type, transaction_id, payment_date as r_date, category, description, amount FROM online_payments $date_clause ORDER BY r_date DESC";
} elseif ($report_type === 'receipt_report') {
    $title = "Receipt Report";
    $sql = "SELECT 'Cash Receipt' as type, transaction_id, receipt_date as r_date, category, description, amount FROM cash_receipts $date_clause UNION ALL SELECT 'Online Receipt' as type, transaction_id, receipt_date as r_date, category, description, amount FROM online_receipts $date_clause ORDER BY r_date DESC";
} elseif ($report_type === 'payment_report') {
    $title = "Payment Report";
    $sql = "SELECT 'Cash Payment' as type, transaction_id, payment_date as r_date, category, description, amount FROM cash_payments $date_clause UNION ALL SELECT 'Online Payment' as type, transaction_id, payment_date as r_date, category, description, amount FROM online_payments $date_clause ORDER BY r_date DESC";
} elseif ($report_type === 'income_report') {
    $title = "Income Report";
    $sql = "SELECT 'Income' as type, CONCAT('INC-', income_id) as transaction_id, income_date as r_date, 'Income' as category, description, amount FROM income $date_clause ORDER BY r_date DESC";
} elseif ($report_type === 'expense_report') {
    $title = "Expense Report";
    $sql = "SELECT 'Expense' as type, CONCAT('EXP-', expense_id) as transaction_id, expense_date as r_date, 'Expense' as category, description, amount FROM expenses $date_clause ORDER BY r_date DESC";
} else {
    $title = "Cash Flow Report";
    $sql = "SELECT 'Receipt' as type, transaction_id, receipt_date as r_date, category, description, amount FROM cash_receipts $date_clause UNION ALL SELECT 'Payment' as type, transaction_id, payment_date as r_date, category, description, -amount as amount FROM cash_payments $date_clause ORDER BY r_date DESC";
}

$res = $conn->query($sql);
if ($res) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}

$total_amount = 0;
foreach ($rows as $r) {
    $total_amount += $r['amount'];
}

// Excel Export
if ($export === 'excel') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Type', 'Transaction ID', 'Date', 'Category', 'Description', 'Amount']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['type'], $r['transaction_id'], $r['r_date'], $r['category'], $r['description'], $r['amount']]);
    }
    fputcsv($out, ['', '', '', '', 'TOTAL:', $total_amount]);
    fclose($out);
    exit;
}

$active_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?= e(SITE_NAME) ?></title>
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

            <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                <div>
                    <h1 class="page-title mb-1"><i class="fa-solid fa-chart-pie"></i> Reports Center</h1>
                    <p class="text-muted mb-0">Generate, view, print and export financial reports</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" action="reports.php" class="d-flex gap-2 w-100 flex-wrap align-items-center">
                    <select name="report" class="form-select" style="width:200px;" onchange="this.form.submit()">
                        <option value="cash_report" <?= $report_type==='cash_report'?'selected':'' ?>>Cash Report</option>
                        <option value="online_report" <?= $report_type==='online_report'?'selected':'' ?>>Online Report</option>
                        <option value="receipt_report" <?= $report_type==='receipt_report'?'selected':'' ?>>Receipt Report</option>
                        <option value="payment_report" <?= $report_type==='payment_report'?'selected':'' ?>>Payment Report</option>
                        <option value="income_report" <?= $report_type==='income_report'?'selected':'' ?>>Income Report</option>
                        <option value="expense_report" <?= $report_type==='expense_report'?'selected':'' ?>>Expense Report</option>
                        <option value="cash_flow" <?= $report_type==='cash_flow'?'selected':'' ?>>Cash Flow Report</option>
                    </select>

                    <select name="period" class="form-select" style="width:140px;" onchange="this.form.submit()">
                        <option value="daily" <?= $period==='daily'?'selected':'' ?>>Daily</option>
                        <option value="weekly" <?= $period==='weekly'?'selected':'' ?>>Weekly</option>
                        <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Monthly</option>
                        <option value="yearly" <?= $period==='yearly'?'selected':'' ?>>Yearly</option>
                    </select>

                    <button type="submit" class="btn btn-fp-primary"><i class="fa-solid fa-filter"></i> Apply</button>

                    <div class="ms-auto d-flex gap-2">
                        <button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="fa-solid fa-print"></i> Print / PDF</button>
                        <a href="reports.php?report=<?= e($report_type) ?>&period=<?= e($period) ?>&export=excel" class="btn btn-outline-success"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
                    </div>
                </form>
            </div>

            <!-- Report Card -->
            <div class="card card-glass rounded-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0"><?= e($title) ?></h4>
                    <span class="badge bg-primary fs-6"><?= ucfirst(e($period)) ?></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No report records found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= e($r['type']) ?></span></td>
                                        <td><span class="badge bg-info text-dark"><?= e($r['transaction_id']) ?></span></td>
                                        <td><?= date('d M Y', strtotime($r['r_date'])) ?></td>
                                        <td><?= e($r['category']) ?></td>
                                        <td><?= e($r['description'] ?: '—') ?></td>
                                        <td class="fw-bold <?= $r['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= format_currency($r['amount']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="5" class="text-end">TOTAL:</th>
                                <th class="fw-bold fs-5 text-primary"><?= format_currency($total_amount) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
