<?php
/**
 * FinancePro - Savings Analytics
 * Monthly savings trends, growth, averages, and charts.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$year = (int)($_GET['year'] ?? date('Y'));

// Monthly data for selected year
$months_data = [];
for ($m = 1; $m <= 12; $m++) {
    $from = "$year-" . sprintf('%02d', $m) . "-01";
    $to   = date('Y-m-t', strtotime($from));

    $si = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id=? AND income_date BETWEEN ? AND ?");
    $si->bind_param("iss", $uid, $from, $to); $si->execute();
    $inc = (float)$si->get_result()->fetch_row()[0]; $si->close();

    $se = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?");
    $se->bind_param("iss", $uid, $from, $to); $se->execute();
    $exp = (float)$se->get_result()->fetch_row()[0]; $se->close();

    $months_data[$m] = ['income' => $inc, 'expense' => $exp, 'savings' => $inc - $exp];
}

$mn = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// KPIs
$all_savings    = array_column($months_data, 'savings');
$all_income     = array_column($months_data, 'income');
$active_months  = array_filter($all_income, fn($v) => $v > 0);
$total_savings  = array_sum($all_savings);
$avg_savings    = count($active_months) > 0 ? $total_savings / count($active_months) : 0;
$max_savings    = max($all_savings);
$min_savings    = $active_months ? min(array_values(array_filter($all_savings, fn($v, $m) => $all_income[$m] > 0, ARRAY_FILTER_USE_BOTH))) : 0;
$max_month      = array_search($max_savings, $all_savings);
$total_income_y = array_sum($all_income);
$savings_rate   = $total_income_y > 0 ? round($total_savings / $total_income_y * 100, 1) : 0;

$active_page = 'savings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Savings Analytics - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
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
                        <i class="fa-solid fa-piggy-bank" style="color:var(--fp-accent)"></i> Savings Analytics
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">Track your monthly savings growth and patterns</p>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <select name="year" class="fp-select" style="width:110px;">
                        <?php for ($y = date('Y'); $y >= date('Y')-4; $y--): ?>
                        <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Go</button>
                </form>
            </div>

            <!-- KPI Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-piggy-bank"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Savings</div>
                        <div class="card-value" style="color:<?= $total_savings>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;"><?= format_currency($total_savings) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon balance"><i class="fa-solid fa-chart-bar"></i></div>
                    <div class="card-info">
                        <div class="card-label">Avg Monthly Savings</div>
                        <div class="card-value" style="color:var(--fp-primary);"><?= format_currency($avg_savings) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-trophy"></i></div>
                    <div class="card-info">
                        <div class="card-label">Best Month</div>
                        <div class="card-value" style="color:var(--fp-accent);"><?= $mn[$max_month] ?></div>
                        <div style="font-size:.72rem;color:var(--fp-text-muted);"><?= format_currency($max_savings) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon expense"><i class="fa-solid fa-percent"></i></div>
                    <div class="card-info">
                        <div class="card-label">Savings Rate</div>
                        <div class="card-value" style="color:<?= $savings_rate>=20?'var(--fp-accent)':($savings_rate>=10?'var(--fp-warning)':'var(--fp-danger)') ?>;"><?= $savings_rate ?>%</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                <div class="chart-card">
                    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">Monthly Savings — <?= $year ?></h3>
                    <canvas id="savingsChart" height="200"></canvas>
                </div>
                <div class="chart-card">
                    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">Income vs Expense vs Savings</h3>
                    <canvas id="compareChart" height="200"></canvas>
                </div>
            </div>

            <!-- Monthly Table -->
            <div class="table-card">
                <h3 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">Monthly Breakdown — <?= $year ?></h3>
                <table class="fp-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th style="text-align:right;">Income</th>
                            <th style="text-align:right;">Expenses</th>
                            <th style="text-align:right;">Savings</th>
                            <th style="text-align:right;">Savings Rate</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($months_data as $m => $md):
                        $sr = $md['income'] > 0 ? round($md['savings']/$md['income']*100,1) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:600;color:var(--fp-text-dark);"><?= $mn[$m] ?> <?= $year ?></td>
                        <td style="text-align:right;color:var(--fp-accent);"><?= format_currency($md['income']) ?></td>
                        <td style="text-align:right;color:var(--fp-danger);"><?= format_currency($md['expense']) ?></td>
                        <td style="text-align:right;font-weight:700;color:<?= $md['savings']>=0?'var(--fp-primary)':'var(--fp-danger)' ?>;"><?= format_currency($md['savings']) ?></td>
                        <td style="text-align:right;color:var(--fp-text-muted);"><?= $sr ?>%</td>
                        <td style="width:140px;">
                            <div class="fp-progress" style="height:6px;">
                                <div class="fp-progress-bar <?= $sr<0?'danger':'' ?>" style="width:<?= min(100,abs($sr)) ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700;background:var(--fp-bg);">
                            <td style="color:var(--fp-text-dark);">TOTAL / <?= $year ?></td>
                            <td style="text-align:right;color:var(--fp-accent);"><?= format_currency(array_sum($all_income)) ?></td>
                            <td style="text-align:right;color:var(--fp-danger);"><?= format_currency(array_sum(array_column($months_data,'expense'))) ?></td>
                            <td style="text-align:right;color:<?= $total_savings>=0?'var(--fp-primary)':'var(--fp-danger)' ?>;"><?= format_currency($total_savings) ?></td>
                            <td style="text-align:right;color:var(--fp-text-muted);"><?= $savings_rate ?>%</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
const labels = <?= json_encode(array_values($mn)) ?>.slice(1);
const savingsData = <?= json_encode(array_column($months_data, 'savings')) ?>;
const incomeData  = <?= json_encode(array_column($months_data, 'income')) ?>;
const expData     = <?= json_encode(array_column($months_data, 'expense')) ?>;

const darkMode = document.body.classList.contains('dark-theme');
const gridColor = darkMode ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
const textColor = darkMode ? '#94a3b8' : '#6b7688';

Chart.defaults.color = textColor;

new Chart(document.getElementById('savingsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Savings',
            data: savingsData,
            backgroundColor: savingsData.map(v => v >= 0 ? 'rgba(23,185,120,.7)' : 'rgba(229,72,77,.7)'),
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('compareChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label: 'Income', data: incomeData, borderColor: '#17b978', backgroundColor: 'rgba(23,185,120,.1)', tension: .4, fill: true },
            { label: 'Expense', data: expData, borderColor: '#e5484d', backgroundColor: 'rgba(229,72,77,.08)', tension: .4, fill: true },
            { label: 'Savings', data: savingsData, borderColor: '#2457d9', backgroundColor: 'rgba(36,87,217,.08)', tension: .4, fill: false }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
            y: { grid: { color: gridColor } },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>
