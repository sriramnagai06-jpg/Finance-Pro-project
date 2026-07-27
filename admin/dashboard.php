<?php
/**
 * FinancePro - Admin Dashboard (Module 10)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_admin();

// Stats
$total_users    = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$active_users   = $conn->query("SELECT COUNT(*) FROM users WHERE role='user' AND status='active'")->fetch_row()[0];
$blocked_users  = $conn->query("SELECT COUNT(*) FROM users WHERE status='blocked'")->fetch_row()[0];
$total_income   = $conn->query("SELECT COALESCE(SUM(amount),0) FROM income")->fetch_row()[0];
$total_expense  = $conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetch_row()[0];
$total_invoices = $conn->query("SELECT COUNT(*) FROM invoices")->fetch_row()[0];

// Recent users
$recent_users = $conn->query("SELECT user_id, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Monthly income/expense for chart (last 6 months)
$m_labels=[]; $m_inc=[]; $m_exp=[];
for($i=5;$i>=0;$i--) {
    $m=date('n',strtotime("-$i months")); $y=date('Y',strtotime("-$i months"));
    $m_labels[]=date('M',strtotime("-$i months"));
    $m_inc[] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM income WHERE MONTH(income_date)=$m AND YEAR(income_date)=$y")->fetch_row()[0];
    $m_exp[] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE MONTH(expense_date)=$m AND YEAR(expense_date)=$y")->fetch_row()[0];
}

$active_page='admin'; $page_title='Admin Dashboard'; $page_subtitle='System Overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Admin - <?=e(SITE_NAME)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <!-- Admin Stats -->
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:28px;">
                <div class="summary-card"><div class="card-icon balance"><i class="fa-solid fa-users"></i></div>
                    <div class="card-info"><div class="card-label">Total Users</div><div class="card-value balance"><?=$total_users?></div><div class="card-sub"><?=$active_users?> active, <?=$blocked_users?> blocked</div></div></div>
                <div class="summary-card"><div class="card-icon income"><i class="fa-solid fa-arrow-trend-up"></i></div>
                    <div class="card-info"><div class="card-label">Platform Income</div><div class="card-value income"><?=format_currency($total_income)?></div><div class="card-sub">All users combined</div></div></div>
                <div class="summary-card"><div class="card-icon expense"><i class="fa-solid fa-arrow-trend-down"></i></div>
                    <div class="card-info"><div class="card-label">Platform Expenses</div><div class="card-value expense"><?=format_currency($total_expense)?></div><div class="card-sub">All users combined</div></div></div>
            </div>

            <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:24px;">
                <!-- Trend Chart -->
                <div class="chart-card">
                    <div class="chart-header"><div><div class="chart-title">Platform Monthly Activity</div><div class="chart-subtitle">Income & expenses across all users (last 6 months)</div></div></div>
                    <div style="height:260px;"><canvas id="adminTrendChart"></canvas></div>
                </div>

                <!-- Quick Links -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fa-solid fa-bolt" style="color:var(--fp-warning)"></i> Quick Actions</div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="users.php" class="btn-fp btn-fp-primary"><i class="fa-solid fa-users"></i> Manage Users</a>
                        <a href="../user/reports.php" class="btn-fp btn-fp-outline"><i class="fa-solid fa-chart-column"></i> View Reports</a>
                        <a href="../user/invoices.php" class="btn-fp btn-fp-outline"><i class="fa-solid fa-file-invoice"></i> Invoices</a>
                        <a href="../user/dashboard.php" class="btn-fp btn-fp-outline"><i class="fa-solid fa-gauge-high"></i> User Dashboard</a>
                    </div>
                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--fp-border);">
                        <div style="font-size:0.78rem; color:var(--fp-text-muted);"><strong>Total Invoices:</strong> <?=$total_invoices?></div>
                        <div style="font-size:0.78rem; color:var(--fp-text-muted); margin-top:4px;"><strong>Net Platform Balance:</strong> <span style="color:<?=($total_income-$total_expense)>=0?'var(--fp-accent)':'var(--fp-danger)'?>"><?=format_currency($total_income-$total_expense)?></span></div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="table-card" style="margin-top:24px;">
                <div class="table-card-header">
                    <div><div class="tc-title">All Users</div><div class="tc-sub">Manage user accounts</div></div>
                    <a href="users.php" class="btn-fp btn-fp-primary btn-fp-sm">Manage All</a>
                </div>
                <table class="fp-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach($recent_users as $u): ?>
                    <tr>
                        <td style="font-weight:600;"><?=e($u['full_name'])?></td>
                        <td style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($u['email'])?></td>
                        <td><span class="badge-fp badge-<?=$u['role']==='admin'?'partial':'income'?>"><?=ucfirst($u['role'])?></span></td>
                        <td><span class="badge-fp" style="<?=$u['status']==='active'?'background:rgba(23,185,120,0.12);color:#0d8a5a;':'background:rgba(229,72,77,0.12);color:#c93a3f;'?>"><?=ucfirst($u['status'])?></span></td>
                        <td style="font-size:0.82rem;color:var(--fp-text-muted);"><?=format_date($u['created_at'])?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('adminTrendChart').getContext('2d'),{
    type:'bar',
    data:{
        labels:<?=json_encode($m_labels)?>,
        datasets:[
            {label:'Income',data:<?=json_encode($m_inc)?>,backgroundColor:'rgba(23,185,120,0.85)',borderRadius:6,borderSkipped:false},
            {label:'Expenses',data:<?=json_encode($m_exp)?>,backgroundColor:'rgba(229,72,77,0.85)',borderRadius:6,borderSkipped:false}
        ]
    },
    options:{
        responsive:true,maintainAspectRatio:false,
        scales:{x:{grid:{display:false}},y:{grid:{color:'#f0f4fc'},ticks:{callback:v=>'Rs.'+v.toLocaleString('en-IN')}}},
        plugins:{legend:{position:'top',labels:{usePointStyle:true,pointStyle:'circle',padding:16}}}
    }
});
</script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
