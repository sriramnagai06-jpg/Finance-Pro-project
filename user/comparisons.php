<?php
/**
 * FinancePro - Advanced Period Comparison Engine
 * Compare ANY two accounting periods independently:
 *   - Month vs Month (any two months from any year)
 *   - Year vs Year (any two years)
 *   - Custom Date Range vs Custom Date Range
 *
 * Supports: Income, Expenses, Gross Profit, Net Profit, Savings,
 *           Budget Utilization, Category Breakdown (Income & Expenses),
 *           Trading Account, P&L, Balance Sheet, Fund Flow, Cash Flow
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

/* ── Month names ──────────────────────────────────────────────── */
$MN = ['','January','February','March','April','May','June',
       'July','August','September','October','November','December'];
$MN_S = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

/* ── Mode: month | year | custom ─────────────────────────────── */
$mode = $_GET['mode'] ?? 'month';

/* ── Period A ─────────────────────────────────────────────────── */
switch ($mode) {
    case 'year':
        $a_year  = (int)($_GET['a_year']  ?? date('Y'));
        $b_year  = (int)($_GET['b_year']  ?? date('Y') - 1);
        $fy_mode = ($_GET['fy_mode'] ?? 'calendar') === 'fy'; // Indian Financial Year Apr-Mar
        if ($fy_mode) {
            // FY: April 1 of a_year to March 31 of a_year+1
            $a_from = "$a_year-04-01"; $a_to = ($a_year+1)."-03-31";
            $b_from = "$b_year-04-01"; $b_to = ($b_year+1)."-03-31";
            $label_a = "FY $a_year–".($a_year+1);
            $label_b = "FY $b_year–".($b_year+1);
        } else {
            $a_from  = "$a_year-01-01"; $a_to = "$a_year-12-31";
            $b_from  = "$b_year-01-01"; $b_to = "$b_year-12-31";
            $label_a = "CY $a_year"; $label_b = "CY $b_year";
        }
        break;

    case 'custom':
        $a_from  = $_GET['a_from'] ?? date('Y-01-01');
        $a_to    = $_GET['a_to']   ?? date('Y-m-d');
        $b_from  = $_GET['b_from'] ?? date('Y-01-01', strtotime('-1 year'));
        $b_to    = $_GET['b_to']   ?? date('Y-12-31', strtotime('-1 year'));
        // Sanitise dates
        foreach (['a_from','a_to','b_from','b_to'] as $k) {
            $$k = preg_match('/^\d{4}-\d{2}-\d{2}$/', $$k) ? $$k : date('Y-m-d');
        }
        $label_a = date('d M Y', strtotime($a_from)).' – '.date('d M Y', strtotime($a_to));
        $label_b = date('d M Y', strtotime($b_from)).' – '.date('d M Y', strtotime($b_to));
        break;

    default: // month
        $a_month = (int)($_GET['a_month'] ?? date('n'));
        $a_year  = (int)($_GET['a_year']  ?? date('Y'));
        $b_month = (int)($_GET['b_month'] ?? ($a_month == 1 ? 12 : $a_month - 1));
        $b_year  = (int)($_GET['b_year']  ?? ($a_month == 1 ? date('Y') - 1 : date('Y')));
        $a_from  = "$a_year-" . sprintf('%02d', $a_month) . "-01";
        $a_to    = date('Y-m-t', strtotime($a_from));
        $b_from  = "$b_year-" . sprintf('%02d', $b_month) . "-01";
        $b_to    = date('Y-m-t', strtotime($b_from));
        $label_a = $MN[$a_month] . ' ' . $a_year;
        $label_b = $MN[$b_month] . ' ' . $b_year;
}

/* ══════════════════════════════════════════════════════════════
   DATA ENGINE — fetches all metrics for ONE period
══════════════════════════════════════════════════════════════ */
function fetch_period_data(mysqli $conn, int $uid, string $from, string $to): array {
    // Total income (Direct & Indirect)
    $s = $conn->prepare("SELECT COALESCE(SUM(i.amount),0) FROM income i JOIN categories c ON i.category_id=c.category_id WHERE i.user_id=? AND c.accounting_group IN ('Direct Income','Indirect Income') AND i.income_date BETWEEN ? AND ?");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $inc = (float)$s->get_result()->fetch_row()[0]; $s->close();

    // Total expense (Direct & Indirect)
    $s = $conn->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN categories c ON e.category_id=c.category_id WHERE e.user_id=? AND c.accounting_group IN ('Direct Expense','Indirect Expense') AND e.expense_date BETWEEN ? AND ?");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $exp = (float)$s->get_result()->fetch_row()[0]; $s->close();

    // Direct expenses (Trading A/c Dr.)
    $s = $conn->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN categories c ON e.category_id=c.category_id WHERE e.user_id=? AND c.accounting_group = 'Direct Expense' AND e.expense_date BETWEEN ? AND ?");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $direct_exp = (float)$s->get_result()->fetch_row()[0]; $s->close();
    
    // Direct income (Trading A/c Cr.)
    $s = $conn->prepare("SELECT COALESCE(SUM(i.amount),0) FROM income i JOIN categories c ON i.category_id=c.category_id WHERE i.user_id=? AND c.accounting_group = 'Direct Income' AND i.income_date BETWEEN ? AND ?");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $direct_inc = (float)$s->get_result()->fetch_row()[0]; $s->close();

    $gross_profit = $direct_inc - $direct_exp;
    $net_profit   = $inc - $exp;
    $savings      = $inc - $exp;

    // Budget
    $a_month = (int)date('n', strtotime($from)); $a_year = (int)date('Y', strtotime($from));
    $b_month = (int)date('n', strtotime($to));   $b_year = (int)date('Y', strtotime($to));
    $s = $conn->prepare("SELECT COALESCE(SUM(budget_amount),0) FROM budget
        WHERE user_id=? AND (budget_year * 100 + budget_month) BETWEEN ? AND ?");
    $period_from_int = $a_year * 100 + $a_month;
    $period_to_int   = $b_year * 100 + $b_month;
    $s->bind_param("iii", $uid, $period_from_int, $period_to_int); $s->execute();
    $budget = (float)$s->get_result()->fetch_row()[0]; $s->close();
    $budget_util = $budget > 0 ? round($exp / $budget * 100, 1) : 0;

    // Category-wise expenses
    $s = $conn->prepare("SELECT c.category_name, c.icon_class, COALESCE(SUM(e.amount),0) AS total, c.accounting_group
        FROM expenses e JOIN categories c ON e.category_id=c.category_id
        WHERE e.user_id=? AND c.accounting_group IN ('Direct Expense','Indirect Expense') AND e.expense_date BETWEEN ? AND ?
        GROUP BY c.category_id ORDER BY total DESC");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $cat_expenses = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

    // Category-wise income
    $s = $conn->prepare("SELECT c.category_name, c.icon_class, COALESCE(SUM(i.amount),0) AS total, c.accounting_group
        FROM income i JOIN categories c ON i.category_id=c.category_id
        WHERE i.user_id=? AND c.accounting_group IN ('Direct Income','Indirect Income') AND i.income_date BETWEEN ? AND ?
        GROUP BY c.category_id ORDER BY total DESC");
    $s->bind_param("iss", $uid, $from, $to); $s->execute();
    $cat_income = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

    // Cash flow: operating = net_profit, investing = 0, financing = 0 (simplified)
    $cash_in  = $inc;
    $cash_out = $exp;
    $net_cash = $cash_in - $cash_out;

    return compact('inc','exp','gross_profit','net_profit','savings',
                   'direct_exp','budget','budget_util',
                   'cat_expenses','cat_income','net_cash','cash_in','cash_out');
}

$a = fetch_period_data($conn, $uid, $a_from, $a_to);
$b = fetch_period_data($conn, $uid, $b_from, $b_to);

/* ── Helper: Growth % and direction ──────────────────────────── */
function diff_pct(float $new, float $old): float {
    if ($old == 0) return $new > 0 ? 100.0 : 0.0;
    return round(($new - $old) / abs($old) * 100, 1);
}
function diff_abs(float $a, float $b): float { return $a - $b; }

/* ── KPI Comparison Array ─────────────────────────────────────── */
$kpis = [
    ['label'=>'Income',          'icon'=>'fa-money-bill-trend-up',  'a'=>$a['inc'],         'b'=>$b['inc'],         'color'=>'#10b981', 'good_up'=>true],
    ['label'=>'Expenses',        'icon'=>'fa-receipt',              'a'=>$a['exp'],         'b'=>$b['exp'],         'color'=>'#ef4444', 'good_up'=>false],
    ['label'=>'Gross Profit',    'icon'=>'fa-store',                'a'=>$a['gross_profit'],'b'=>$b['gross_profit'],'color'=>'#3b82f6', 'good_up'=>true],
    ['label'=>'Net Profit',      'icon'=>'fa-arrow-trend-up',       'a'=>$a['net_profit'],  'b'=>$b['net_profit'],  'color'=>'#8b5cf6', 'good_up'=>true],
    ['label'=>'Savings',         'icon'=>'fa-piggy-bank',           'a'=>$a['savings'],     'b'=>$b['savings'],     'color'=>'#06b6d4', 'good_up'=>true],
    ['label'=>'Budget',          'icon'=>'fa-bullseye',             'a'=>$a['budget'],      'b'=>$b['budget'],      'color'=>'#f59e0b', 'good_up'=>true],
    ['label'=>'Budget Util %',   'icon'=>'fa-percent',              'a'=>$a['budget_util'], 'b'=>$b['budget_util'], 'color'=>'#f97316', 'good_up'=>false, 'is_pct'=>true],
    ['label'=>'Net Cash Flow',   'icon'=>'fa-water',                'a'=>$a['net_cash'],    'b'=>$b['net_cash'],    'color'=>'#14b8a6', 'good_up'=>true],
];

/* ── AI Insights (Rule-Based) ─────────────────────────────────── */
$insights = [];
$pct_inc  = diff_pct($a['inc'],         $b['inc']);
$pct_exp  = diff_pct($a['exp'],         $b['exp']);
$pct_prof = diff_pct($a['net_profit'],  $b['net_profit']);
$pct_sav  = diff_pct($a['savings'],     $b['savings']);

if ($pct_inc > 10)
    $insights[] = ['type'=>'success','icon'=>'fa-chart-line',
        'msg'=>"<strong>Income up {$pct_inc}%</strong> in Period A vs Period B — ".format_currency(abs(diff_abs($a['inc'],$b['inc'])))." more revenue."];
elseif ($pct_inc < -10)
    $insights[] = ['type'=>'danger','icon'=>'fa-chart-line',
        'msg'=>"<strong>Income dropped {$pct_inc}%</strong> in Period A vs Period B. Revenue declined by ".format_currency(abs(diff_abs($a['inc'],$b['inc'])))."."];

if ($pct_exp > 15)
    $insights[] = ['type'=>'warning','icon'=>'fa-triangle-exclamation',
        'msg'=>"<strong>Expenses rose {$pct_exp}%</strong> in Period A. Spending increased by ".format_currency(abs(diff_abs($a['exp'],$b['exp'])))."."];
elseif ($pct_exp < -10)
    $insights[] = ['type'=>'success','icon'=>'fa-circle-check',
        'msg'=>"<strong>Expenses reduced by {$pct_exp}%</strong> in Period A — excellent cost control! Saved ".format_currency(abs(diff_abs($a['exp'],$b['exp'])))."."];

if ($a['net_profit'] > 0 && $b['net_profit'] <= 0)
    $insights[] = ['type'=>'success','icon'=>'fa-trophy',
        'msg'=>"<strong>Turnaround achieved!</strong> Period A is profitable (".format_currency($a['net_profit']).") while Period B was at a loss."];
elseif ($a['net_profit'] < 0 && $b['net_profit'] > 0)
    $insights[] = ['type'=>'danger','icon'=>'fa-exclamation-circle',
        'msg'=>"<strong>Profitability declined</strong> — Period A shows a loss of ".format_currency(abs($a['net_profit']))." versus a profit in Period B."];
elseif ($pct_prof > 20)
    $insights[] = ['type'=>'success','icon'=>'fa-arrow-trend-up',
        'msg'=>"<strong>Net Profit up {$pct_prof}%</strong> in Period A. Strong improvement of ".format_currency(abs(diff_abs($a['net_profit'],$b['net_profit'])))."."];

$a_sav_rate = $a['inc'] > 0 ? round($a['savings']/$a['inc']*100,1) : 0;
$b_sav_rate = $b['inc'] > 0 ? round($b['savings']/$b['inc']*100,1) : 0;
if ($a_sav_rate > $b_sav_rate + 5)
    $insights[] = ['type'=>'success','icon'=>'fa-piggy-bank',
        'msg'=>"Savings rate improved from <strong>{$b_sav_rate}%</strong> to <strong>{$a_sav_rate}%</strong> — great financial discipline!"];
elseif ($a_sav_rate < $b_sav_rate - 5)
    $insights[] = ['type'=>'warning','icon'=>'fa-piggy-bank',
        'msg'=>"Savings rate dropped from <strong>{$b_sav_rate}%</strong> to <strong>{$a_sav_rate}%</strong>. Consider reducing discretionary spending."];

if ($a['budget_util'] >= 100 && $a['budget'] > 0)
    $insights[] = ['type'=>'danger','icon'=>'fa-bullseye',
        'msg'=>"<strong>Budget exceeded</strong> in Period A ({$a['budget_util']}% utilization). Strict budget controls needed."];
elseif ($a['budget_util'] >= 80 && $a['budget'] > 0)
    $insights[] = ['type'=>'warning','icon'=>'fa-bullseye',
        'msg'=>"Budget utilization at <strong>{$a['budget_util']}%</strong> in Period A — approaching limit."];

// Top expense category comparison
$a_top_cat = !empty($a['cat_expenses']) ? $a['cat_expenses'][0] : null;
$b_top_cat = !empty($b['cat_expenses']) ? $b['cat_expenses'][0] : null;
if ($a_top_cat && $b_top_cat && $a_top_cat['category_name'] !== $b_top_cat['category_name'])
    $insights[] = ['type'=>'info','icon'=>'fa-chart-pie',
        'msg'=>"Top expense shifted from <strong>{$b_top_cat['category_name']}</strong> (Period B) to <strong>{$a_top_cat['category_name']}</strong> (Period A)."];

if (empty($insights))
    $insights[] = ['type'=>'info','icon'=>'fa-circle-info',
        'msg'=>"Both periods show similar financial patterns. No significant changes detected."];

/* ── Build aligned category data for comparison charts ────────── */
function align_categories(array $a_cats, array $b_cats): array {
    $all_names = array_unique(array_merge(
        array_column($a_cats, 'category_name'),
        array_column($b_cats, 'category_name')
    ));
    $a_map = array_column($a_cats, 'total', 'category_name');
    $b_map = array_column($b_cats, 'total', 'category_name');
    $result = [];
    foreach ($all_names as $n) {
        $result[] = [
            'name' => $n,
            'a'    => (float)($a_map[$n] ?? 0),
            'b'    => (float)($b_map[$n] ?? 0),
        ];
    }
    usort($result, fn($x,$y) => ($y['a']+$y['b']) <=> ($x['a']+$x['b']));
    return array_slice($result, 0, 10);
}

$cat_exp_aligned = align_categories($a['cat_expenses'], $b['cat_expenses']);
$cat_inc_aligned = align_categories($a['cat_income'],   $b['cat_income']);

$active_page = 'comparisons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Period Comparison - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
    /* ── Period Selector Pills ───────────────────────── */
    .mode-pills { display:flex; gap:6px; flex-wrap:wrap; }
    .mode-pill  {
        padding:6px 18px; border-radius:30px; font-size:.82rem; font-weight:600;
        border:2px solid var(--fp-border); color:var(--fp-text-muted);
        cursor:pointer; text-decoration:none; transition:.2s;
    }
    .mode-pill.active,
    .mode-pill:hover { background:var(--fp-primary); color:#fff; border-color:var(--fp-primary); }

    /* ── Period Selection Panels ─────────────────────── */
    .period-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .period-panel {
        background:var(--fp-card-bg,#fff); border:2px solid var(--fp-border);
        border-radius:14px; padding:20px;
    }
    .period-panel.panel-a { border-left:4px solid var(--fp-primary); }
    .period-panel.panel-b { border-left:4px solid #f59e0b; }
    .period-label {
        font-size:.72rem; font-weight:800; letter-spacing:1px;
        text-transform:uppercase; margin-bottom:12px;
    }
    .panel-a .period-label { color:var(--fp-primary); }
    .panel-b .period-label { color:#f59e0b; }

    /* ── KPI Comparison Cards ────────────────────────── */
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:16px; margin-bottom:24px; }
    .kpi-card {
        background:var(--fp-card-bg,#fff); border:1px solid var(--fp-border);
        border-radius:14px; padding:18px; position:relative; overflow:hidden;
        transition:box-shadow .2s;
    }
    .kpi-card:hover { box-shadow:var(--fp-shadow-hover,0 8px 30px rgba(15,35,75,.13)); }
    .kpi-card::before {
        content:''; position:absolute; top:0; left:0; width:4px; height:100%;
        background:var(--kpi-color, var(--fp-primary));
    }
    .kpi-header { display:flex; align-items:center; gap:8px; margin-bottom:12px; }
    .kpi-icon   {
        width:32px; height:32px; border-radius:8px; display:flex; align-items:center;
        justify-content:center; font-size:.85rem; flex-shrink:0;
    }
    .kpi-metric-label { font-size:.72rem; font-weight:700; letter-spacing:.5px;
        text-transform:uppercase; color:var(--fp-text-muted); }
    .kpi-row    { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:8px; }
    .kpi-val    { font-size:1rem; font-weight:700; color:var(--fp-text-dark); }
    .kpi-period { font-size:.68rem; color:var(--fp-text-muted); margin-bottom:2px; }
    .kpi-divider{ border:none; border-top:1px dashed var(--fp-border); margin:10px 0; }
    .trend-badge {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 10px; border-radius:20px; font-size:.76rem; font-weight:700;
    }
    .trend-up   { background:rgba(16,185,129,.12); color:#10b981; }
    .trend-down { background:rgba(239,68,68,.12);  color:#ef4444; }
    .trend-flat { background:rgba(107,118,136,.1); color:#6b7688; }
    .diff-row   { display:flex; justify-content:space-between; font-size:.78rem; color:var(--fp-text-muted); }

    /* ── Accounting T-tables ──────────────────────────── */
    .comp-section-title {
        font-size:.82rem; font-weight:800; letter-spacing:.8px; text-transform:uppercase;
        color:var(--fp-primary); background:var(--fp-bg); padding:10px 16px;
    }
    .ct { width:100%; border-collapse:collapse; }
    .ct th { padding:10px 14px; font-size:.8rem; color:#fff; }
    .ct th.a-col { background:var(--fp-primary); }
    .ct th.b-col { background:#d97706; }
    .ct th.diff-col { background:#334155; }
    .ct td { padding:9px 14px; border-bottom:1px solid var(--fp-border); font-size:.88rem; color:var(--fp-text-dark); }
    .ct td.num { text-align:right; font-weight:600; }
    .ct td.diff { text-align:right; font-weight:700; }
    .ct tr.subtotal td { font-weight:700; background:rgba(36,87,217,.06); }
    .ct tr.profit-row td { background:var(--fp-accent); color:#fff!important; font-weight:700; }
    .ct tr.loss-row td   { background:var(--fp-danger); color:#fff!important; font-weight:700; }
    .ct .section-head td { font-weight:700; font-size:.76rem; letter-spacing:.6px; color:var(--fp-primary); background:var(--fp-bg); }

    /* ── Chart Containers ─────────────────────────────── */
    .chart-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .chart-grid-1 { margin-bottom:24px; }

    /* ── Tabs ─────────────────────────────────────────── */
    .comp-tabs { display:flex; gap:0; border-bottom:2px solid var(--fp-border); margin-bottom:20px; flex-wrap:wrap; }
    .comp-tab  {
        padding:10px 18px; font-size:.82rem; font-weight:600; color:var(--fp-text-muted);
        cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:.15s;
    }
    .comp-tab.active,
    .comp-tab:hover { color:var(--fp-primary); border-bottom-color:var(--fp-primary); }
    .comp-pane { display:none; }
    .comp-pane.active { display:block; }

    /* ── Responsive ───────────────────────────────────── */
    @media (max-width:768px) {
        .period-grid  { grid-template-columns:1fr; }
        .chart-grid-2 { grid-template-columns:1fr; }
        .kpi-grid     { grid-template-columns:1fr 1fr; }
    }
    @media print {
        .fp-sidebar,.fp-topbar,.period-grid,.mode-pills,.comp-tabs { display:none!important; }
        .fp-main { margin:0!important; }
        .comp-pane { display:block!important; }
    }

    /* Dark mode extras for this page */
    body.dark-theme .period-panel { background:var(--fp-card-bg); }
    body.dark-theme .kpi-card     { background:var(--fp-card-bg); }
    body.dark-theme .ct th.a-col  { background:#1d4ed8; }
    body.dark-theme .ct th.b-col  { background:#b45309; }
    body.dark-theme .ct th.diff-col{ background:#1e293b; }
    </style>
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <!-- ══ Page Header ══ -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:800;color:var(--fp-text-dark);margin:0;">
                        <i class="fa-solid fa-chart-bar" style="color:var(--fp-primary)"></i> Period Comparison
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;font-size:.88rem;">
                        Compare any two accounting periods — Month, Year, or Custom Range
                    </p>
                </div>
                <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm">
                    <i class="fa-solid fa-print"></i> Print Report
                </button>
            </div>

            <!-- ══ Mode Pills ══ -->
            <div class="mode-pills" style="margin-bottom:20px;">
                <a href="?mode=month<?= $mode==='month' ? '&a_month='.($a_month??date('n')).'&a_year='.($a_year??date('Y')).'&b_month='.($b_month??date('n')-1).'&b_year='.($b_year??date('Y')) : '' ?>"
                   class="mode-pill <?= $mode==='month'?'active':'' ?>">
                    <i class="fa-regular fa-calendar"></i> Month vs Month
                </a>
                <a href="?mode=year<?= $mode==='year' ? '&a_year='.($a_year??date('Y')).'&b_year='.($b_year??date('Y')-1) : '' ?>"
                   class="mode-pill <?= $mode==='year'?'active':'' ?>">
                    <i class="fa-solid fa-calendar-days"></i> Year vs Year
                </a>
                <a href="?mode=custom<?= $mode==='custom' ? '&a_from='.$a_from.'&a_to='.$a_to.'&b_from='.$b_from.'&b_to='.$b_to : '' ?>"
                   class="mode-pill <?= $mode==='custom'?'active':'' ?>">
                    <i class="fa-solid fa-sliders"></i> Custom Range
                </a>
            </div>

            <!-- ══ Period Selectors ══ -->
            <form method="GET" id="periodForm">
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <div class="period-grid">
                    <!-- Period A -->
                    <div class="period-panel panel-a">
                        <div class="period-label"><i class="fa-solid fa-circle"></i> Period A (Current)</div>
                        <?php if ($mode === 'month'): ?>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:110px;">
                                <label class="fp-label">Month</label>
                                <select name="a_month" class="fp-select">
                                    <?php for ($m=1;$m<=12;$m++): ?>
                                    <option value="<?= $m ?>" <?= ($a_month??0)==$m?'selected':'' ?>><?= $MN[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:90px;">
                                <label class="fp-label">Year</label>
                                <select name="a_year" class="fp-select">
                                    <?php for ($y=date('Y');$y>=date('Y')-6;$y--): ?>
                                    <option value="<?= $y ?>" <?= ($a_year??0)==$y?'selected':'' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <?php elseif ($mode === 'year'): ?>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:90px;">
                                <label class="fp-label">Year</label>
                                <select name="a_year" class="fp-select">
                                    <?php for ($y=date('Y')+1;$y>=date('Y')-9;$y--): ?>
                                    <option value="<?= $y ?>" <?= ($a_year??0)==$y?'selected':'' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:120px;">
                                <label class="fp-label">Year Type</label>
                                <select name="fy_mode" class="fp-select">
                                    <option value="calendar" <?= (($_GET['fy_mode']??'calendar')==='calendar')?'selected':'' ?>>Calendar (Jan–Dec)</option>
                                    <option value="fy" <?= (($_GET['fy_mode']??'')==='fy')?'selected':'' ?>>Indian FY (Apr–Mar)</option>
                                </select>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="fp-form-group" style="margin:0;flex:1;">
                                <label class="fp-label">From</label>
                                <input type="date" name="a_from" class="fp-input" value="<?= e($a_from) ?>">
                            </div>
                            <div class="fp-form-group" style="margin:0;flex:1;">
                                <label class="fp-label">To</label>
                                <input type="date" name="a_to" class="fp-input" value="<?= e($a_to) ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Period B -->
                    <div class="period-panel panel-b">
                        <div class="period-label" style="color:#d97706;"><i class="fa-solid fa-circle"></i> Period B (Comparison)</div>
                        <?php if ($mode === 'month'): ?>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:110px;">
                                <label class="fp-label">Month</label>
                                <select name="b_month" class="fp-select">
                                    <?php for ($m=1;$m<=12;$m++): ?>
                                    <option value="<?= $m ?>" <?= ($b_month??0)==$m?'selected':'' ?>><?= $MN[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="fp-form-group" style="margin:0;flex:1;min-width:90px;">
                                <label class="fp-label">Year</label>
                                <select name="b_year" class="fp-select">
                                    <?php for ($y=date('Y');$y>=date('Y')-6;$y--): ?>
                                    <option value="<?= $y ?>" <?= ($b_year??0)==$y?'selected':'' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <?php elseif ($mode === 'year'): ?>
                        <div class="fp-form-group" style="margin:0;">
                            <label class="fp-label">Comparison Year</label>
                            <select name="b_year" class="fp-select">
                                <?php for ($y=date('Y')+1;$y>=date('Y')-9;$y--): ?>
                                <option value="<?= $y ?>" <?= ($b_year??0)==$y?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="fp-form-group" style="margin:0;flex:1;">
                                <label class="fp-label">From</label>
                                <input type="date" name="b_from" class="fp-input" value="<?= e($b_from) ?>">
                            </div>
                            <div class="fp-form-group" style="margin:0;flex:1;">
                                <label class="fp-label">To</label>
                                <input type="date" name="b_to" class="fp-input" value="<?= e($b_to) ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn-fp btn-fp-primary" style="margin-bottom:28px;">
                    <i class="fa-solid fa-rotate"></i> Compare Periods
                </button>
            </form>

            <!-- ══ Active Comparison Header ══ -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap;">
                <div style="background:var(--fp-primary);color:#fff;padding:8px 18px;border-radius:24px;font-weight:700;font-size:.88rem;">
                    <i class="fa-solid fa-circle" style="font-size:.5rem;vertical-align:middle;margin-right:4px;"></i> A: <?= e($label_a) ?>
                </div>
                <div style="font-size:1.2rem;color:var(--fp-text-muted);font-weight:700;">vs</div>
                <div style="background:#d97706;color:#fff;padding:8px 18px;border-radius:24px;font-weight:700;font-size:.88rem;">
                    <i class="fa-solid fa-circle" style="font-size:.5rem;vertical-align:middle;margin-right:4px;"></i> B: <?= e($label_b) ?>
                </div>
            </div>

            <!-- ══ KPI Cards ══ -->
            <div class="kpi-grid">
            <?php foreach ($kpis as $k):
                $pct  = isset($k['is_pct'])
                    ? round($k['a'] - $k['b'], 1)
                    : diff_pct($k['a'], $k['b']);
                $diff_v = diff_abs($k['a'], $k['b']);
                $is_good = $k['good_up'] ? $pct >= 0 : $pct <= 0;
                $trend_class = $pct > 0.1 ? 'trend-up' : ($pct < -0.1 ? 'trend-down' : 'trend-flat');
                $arrow = $pct > 0.1 ? '↑' : ($pct < -0.1 ? '↓' : '→');
            ?>
            <div class="kpi-card" style="--kpi-color:<?= $k['color'] ?>">
                <div class="kpi-header">
                    <div class="kpi-icon" style="background:<?= $k['color'] ?>22;">
                        <i class="fa-solid <?= $k['icon'] ?>" style="color:<?= $k['color'] ?>"></i>
                    </div>
                    <span class="kpi-metric-label"><?= e($k['label']) ?></span>
                </div>
                <div class="kpi-row">
                    <div>
                        <div class="kpi-period">Period A — <?= e($label_a) ?></div>
                        <div class="kpi-val" style="color:<?= $k['color'] ?>">
                            <?= isset($k['is_pct']) ? $k['a'].'%' : format_currency($k['a']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="kpi-period">Period B — <?= e($label_b) ?></div>
                        <div class="kpi-val" style="color:var(--fp-text-muted);font-size:.9rem;">
                            <?= isset($k['is_pct']) ? $k['b'].'%' : format_currency($k['b']) ?>
                        </div>
                    </div>
                </div>
                <hr class="kpi-divider">
                <div class="diff-row">
                    <span>
                        <span class="trend-badge <?= $is_good ? 'trend-up' : 'trend-down' ?>">
                            <?= $arrow ?> <?= abs($pct) ?>%
                        </span>
                    </span>
                    <span style="color:<?= $diff_v >= 0 ? 'var(--fp-accent)' : 'var(--fp-danger)' ?>;font-weight:600;">
                        <?= $diff_v >= 0 ? '+' : '' ?><?= isset($k['is_pct']) ? round($diff_v,1).'pp' : format_currency($diff_v) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- ══ Charts Row ══ -->
            <div class="chart-grid-2">
                <div class="chart-card">
                    <h3 style="font-size:.92rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">
                        Financial Overview Comparison
                    </h3>
                    <canvas id="overviewChart" height="220"></canvas>
                </div>
                <div class="chart-card">
                    <h3 style="font-size:.92rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">
                        Income Distribution
                    </h3>
                    <canvas id="incomeDonut" height="220"></canvas>
                </div>
            </div>
            <div class="chart-grid-2">
                <div class="chart-card">
                    <h3 style="font-size:.92rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">
                        Category-wise Expenses
                    </h3>
                    <canvas id="catExpChart" height="250"></canvas>
                </div>
                <div class="chart-card">
                    <h3 style="font-size:.92rem;font-weight:700;margin-bottom:16px;color:var(--fp-text-dark);">
                        Category-wise Income
                    </h3>
                    <canvas id="catIncChart" height="250"></canvas>
                </div>
            </div>

            <!-- ══ Tabbed Accounting Statements ══ -->
            <div class="table-card" style="margin-bottom:24px;">
                <div class="comp-tabs">
                    <div class="comp-tab active" onclick="showTab('pl')"><i class="fa-solid fa-arrow-trend-up"></i> P&amp;L</div>
                    <div class="comp-tab" onclick="showTab('trading')"><i class="fa-solid fa-store"></i> Trading</div>
                    <div class="comp-tab" onclick="showTab('balance')"><i class="fa-solid fa-table-columns"></i> Balance Sheet</div>
                    <div class="comp-tab" onclick="showTab('cashflow')"><i class="fa-solid fa-water"></i> Cash Flow</div>
                    <div class="comp-tab" onclick="showTab('catexp')"><i class="fa-solid fa-receipt"></i> Expense Categories</div>
                    <div class="comp-tab" onclick="showTab('catinc')"><i class="fa-solid fa-money-bill-trend-up"></i> Income Categories</div>
                </div>

                <!-- P&L Comparison -->
                <div id="pane-pl" class="comp-pane active">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">
                        Profit & Loss Account — Side-by-Side
                    </h4>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Particulars</th>
                            <th class="a-col" style="text-align:right;">Period A<br><small><?= e($label_a) ?></small></th>
                            <th class="b-col" style="text-align:right;">Period B<br><small><?= e($label_b) ?></small></th>
                            <th class="diff-col" style="text-align:right;">Difference</th>
                            <th class="diff-col" style="text-align:right;">Change %</th>
                        </tr></thead>
                        <tbody>
                        <?php
                        $a_indirect_inc = array_filter($a['cat_income'], fn($r)=>$r['accounting_group']==='Indirect Income');
                        $b_indirect_inc = array_filter($b['cat_income'], fn($r)=>$r['accounting_group']==='Indirect Income');
                        $a_indirect_exp = array_filter($a['cat_expenses'], fn($r)=>$r['accounting_group']==='Indirect Expense');
                        $b_indirect_exp = array_filter($b['cat_expenses'], fn($r)=>$r['accounting_group']==='Indirect Expense');
                        
                        // Gross Profit b/d
                        $gp_a=$a['gross_profit']; $gp_b=$b['gross_profit'];
                        $d=diff_abs($gp_a,$gp_b); $p=diff_pct($gp_a,$gp_b);
                        ?>
                        <tr>
                            <td style="font-weight:700;">Gross Profit / (Loss) b/d</td>
                            <td class="num" style="font-weight:700;color:<?= $gp_a>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= format_currency($gp_a) ?></td>
                            <td class="num" style="font-weight:700;color:<?= $gp_b>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= format_currency($gp_b) ?></td>
                            <td class="diff"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                            <td class="diff"><?= $p ?>%</td>
                        </tr>
                        
                        <tr class="section-head"><td colspan="5">INDIRECT INCOME (Cr.)</td></tr>
                        <?php
                        foreach ($a_indirect_inc as $r) {
                            $b_val = 0;
                            foreach ($b_indirect_inc as $br) {
                                if ($br['category_name'] === $r['category_name']) { $b_val = $br['total']; break; }
                            }
                            echo "<tr><td style='padding-left:24px;'>".e($r['category_name'])."</td>
                                 <td class='num'>".format_currency($r['total'])."</td>
                                 <td class='num'>".format_currency($b_val)."</td>";
                            $d = $r['total'] - $b_val; $p = diff_pct($r['total'],$b_val);
                            echo "<td class='diff' style='color:".($d>=0?'var(--fp-accent)':'var(--fp-danger)')."'>".($d>=0?'+':'').format_currency($d)."</td>
                                 <td class='diff' style='color:".($p>=0?'var(--fp-accent)':'var(--fp-danger)')."'>".$p."%</td></tr>";
                        }
                        ?>
                        <tr class="section-head"><td colspan="5">INDIRECT EXPENSES (Dr.)</td></tr>
                        <?php
                        foreach ($a_indirect_exp as $r) {
                            $b_val = 0;
                            foreach ($b_indirect_exp as $br) {
                                if ($br['category_name'] === $r['category_name']) { $b_val = $br['total']; break; }
                            }
                            echo "<tr><td style='padding-left:24px;'>".e($r['category_name'])."</td>
                                 <td class='num'>".format_currency($r['total'])."</td>
                                 <td class='num'>".format_currency($b_val)."</td>";
                            $d = $r['total'] - $b_val; $p = diff_pct($r['total'],$b_val);
                            echo "<td class='diff' style='color:".($d<=0?'var(--fp-accent)':'var(--fp-danger)')."'>".($d>=0?'+':'').format_currency($d)."</td>
                                 <td class='diff' style='color:".($p<=0?'var(--fp-accent)':'var(--fp-danger)')."'>".$p."%</td></tr>";
                        }
                        ?>
                        <?php
                        $np_a = $a['net_profit']; $np_b = $b['net_profit'];
                        $d = diff_abs($np_a,$np_b); $p = diff_pct($np_a,$np_b);
                        $cls = ($np_a >= 0) ? 'profit-row' : 'loss-row';
                        ?>
                        <tr class="<?= $cls ?>">
                            <td><strong><?= $np_a >= 0 ? 'NET PROFIT' : 'NET LOSS' ?></strong></td>
                            <td class="num"><?= format_currency(abs($np_a)) ?></td>
                            <td class="num"><?= format_currency(abs($np_b)) ?></td>
                            <td class="diff"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                            <td class="diff"><?= $p ?>%</td>
                        </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Trading Account Comparison -->
                <div id="pane-trading" class="comp-pane">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">Trading Account — Side-by-Side</h4>
                    <?php
                    // Build direct rows
                    $a_direct = array_filter($a['cat_expenses'], fn($r)=>$r['accounting_group']==='Direct Expense');
                    $b_direct = array_filter($b['cat_expenses'], fn($r)=>$r['accounting_group']==='Direct Expense');
                    $a_direct_inc = array_filter($a['cat_income'], fn($r)=>$r['accounting_group']==='Direct Income');
                    $b_direct_inc = array_filter($b['cat_income'], fn($r)=>$r['accounting_group']==='Direct Income');
                    ?>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Particulars</th>
                            <th class="a-col" style="text-align:right;"><?= e($label_a) ?></th>
                            <th class="b-col" style="text-align:right;"><?= e($label_b) ?></th>
                            <th class="diff-col" style="text-align:right;">Diff</th>
                        </tr></thead>
                        <tbody>
                        <tr class="section-head"><td colspan="4">DIRECT EXPENSES (Debit Side)</td></tr>
                        <?php foreach ($a_direct as $r):
                            $bv = 0; foreach($b_direct as $br) if($br['category_name']===$r['category_name']) $bv=$br['total'];
                            $d=diff_abs($r['total'],$bv); ?>
                        <tr><td style="padding-left:24px;"><?= e($r['category_name']) ?></td>
                            <td class="num"><?= format_currency($r['total']) ?></td>
                            <td class="num"><?= format_currency($bv) ?></td>
                            <td class="diff" style="color:<?= $d<=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="subtotal"><td><strong>Total Direct Exp.</strong></td>
                            <td class="num"><?= format_currency($a['direct_exp']) ?></td>
                            <td class="num"><?= format_currency($b['direct_exp']) ?></td>
                            <?php $d=diff_abs($a['direct_exp'],$b['direct_exp']); ?>
                            <td class="diff" style="color:<?= $d<=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        <tr class="section-head"><td colspan="4">SALES / REVENUE (Credit Side)</td></tr>
                        <tr class="section-head"><td colspan="4">SALES / REVENUE (Credit Side)</td></tr>
                        <?php foreach ($a_direct_inc as $r):
                            $bv = 0; foreach($b_direct_inc as $br) if($br['category_name']===$r['category_name']) $bv=$br['total'];
                            $d=diff_abs($r['total'],$bv); ?>
                        <tr><td style="padding-left:24px;"><?= e($r['category_name']) ?></td>
                            <td class="num"><?= format_currency($r['total']) ?></td>
                            <td class="num"><?= format_currency($bv) ?></td>
                            <td class="diff" style="color:<?= $d>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        <?php endforeach; ?>
                        $gp_a=$a['gross_profit']; $gp_b=$b['gross_profit'];
                        $d=diff_abs($gp_a,$gp_b);
                        $cls=$gp_a>=0?'profit-row':'loss-row';
                        ?>
                        <tr class="<?= $cls ?>">
                            <td><strong><?= $gp_a>=0?'GROSS PROFIT':'GROSS LOSS' ?></strong></td>
                            <td class="num"><?= format_currency(abs($gp_a)) ?></td>
                            <td class="num"><?= format_currency(abs($gp_b)) ?></td>
                            <td class="diff"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                        </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Balance Sheet Comparison -->
                <div id="pane-balance" class="comp-pane">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">Balance Sheet — Key Items Comparison</h4>
                    <?php
                    $opening_capital = 50000;
                    $bs_a = ['capital'=>$opening_capital,'net_profit'=>$a['net_profit'],
                             'total_liab'=>$opening_capital+$a['net_profit'],
                             'total_assets'=>$opening_capital+$a['net_profit']];
                    $bs_b = ['capital'=>$opening_capital,'net_profit'=>$b['net_profit'],
                             'total_liab'=>$opening_capital+$b['net_profit'],
                             'total_assets'=>$opening_capital+$b['net_profit']];
                    $bs_items = [
                        ['label'=>'Opening Capital',     'a'=>$bs_a['capital'],      'b'=>$bs_b['capital']],
                        ['label'=>'Net Profit / (Loss)', 'a'=>$bs_a['net_profit'],   'b'=>$bs_b['net_profit']],
                        ['label'=>'Total Capital Employed','a'=>$bs_a['total_liab'], 'b'=>$bs_b['total_liab']],
                        ['label'=>'---'],
                        ['label'=>'Cash Flow Surplus',   'a'=>$a['net_cash'],        'b'=>$b['net_cash']],
                        ['label'=>'Total Assets (Est.)','a'=>$bs_a['total_assets'], 'b'=>$bs_b['total_assets']],
                    ];
                    ?>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Item</th>
                            <th class="a-col" style="text-align:right;"><?= e($label_a) ?></th>
                            <th class="b-col" style="text-align:right;"><?= e($label_b) ?></th>
                            <th class="diff-col" style="text-align:right;">Difference</th>
                            <th class="diff-col" style="text-align:right;">Change %</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($bs_items as $item):
                            if (isset($item['label']) && $item['label'] === '---'): ?>
                            <tr class="section-head"><td colspan="5">ASSETS</td></tr>
                            <?php continue; endif;
                            $d = diff_abs($item['a'], $item['b']);
                            $p = diff_pct($item['a'], $item['b']);
                        ?>
                        <tr>
                            <td style="padding-left:24px;"><?= e($item['label']) ?></td>
                            <td class="num"><?= format_currency($item['a']) ?></td>
                            <td class="num"><?= format_currency($item['b']) ?></td>
                            <td class="diff" style="color:<?= $d>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                            <td class="diff" style="color:<?= $p>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= $p ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <p style="font-size:.75rem;color:var(--fp-text-muted);margin-top:8px;">
                        <i class="fa-solid fa-info-circle"></i> Opening capital assumed Rs. 50,000. For full balance sheet with fixed assets, configure in Settings.
                    </p>
                </div>

                <!-- Cash Flow Comparison -->
                <div id="pane-cashflow" class="comp-pane">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">Cash Flow Statement — Comparison</h4>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Particulars</th>
                            <th class="a-col" style="text-align:right;"><?= e($label_a) ?></th>
                            <th class="b-col" style="text-align:right;"><?= e($label_b) ?></th>
                            <th class="diff-col" style="text-align:right;">Difference</th>
                        </tr></thead>
                        <tbody>
                        <tr class="section-head"><td colspan="4">OPERATING ACTIVITIES</td></tr>
                        <tr><td style="padding-left:24px;">Cash Inflows (Income)</td>
                            <td class="num"><?= format_currency($a['cash_in']) ?></td>
                            <td class="num"><?= format_currency($b['cash_in']) ?></td>
                            <?php $d=diff_abs($a['cash_in'],$b['cash_in']); ?>
                            <td class="diff" style="color:<?= $d>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        <tr><td style="padding-left:24px;">Cash Outflows (Expenses)</td>
                            <td class="num">(<?= format_currency($a['cash_out']) ?>)</td>
                            <td class="num">(<?= format_currency($b['cash_out']) ?>)</td>
                            <?php $d=diff_abs($a['cash_out'],$b['cash_out']); ?>
                            <td class="diff" style="color:<?= $d<=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        <?php
                        $nc_a=$a['net_cash']; $nc_b=$b['net_cash'];
                        $d=diff_abs($nc_a,$nc_b);
                        $cls = $nc_a>=0?'profit-row':'loss-row';
                        ?>
                        <tr class="<?= $cls ?>">
                            <td><strong>Net Operating Cash Flow</strong></td>
                            <td class="num"><?= format_currency($nc_a) ?></td>
                            <td class="num"><?= format_currency($nc_b) ?></td>
                            <td class="diff"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                        </tr>
                        <tr class="section-head"><td colspan="4">SUMMARY</td></tr>
                        <tr><td style="padding-left:24px;">Opening Cash (Est.)</td>
                            <td class="num">Rs. 0.00</td><td class="num">Rs. 0.00</td><td class="diff">—</td></tr>
                        <tr class="subtotal"><td><strong>Closing Cash Balance</strong></td>
                            <td class="num"><?= format_currency($nc_a) ?></td>
                            <td class="num"><?= format_currency($nc_b) ?></td>
                            <td class="diff" style="color:<?= $d>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td></tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Category-wise Expenses -->
                <div id="pane-catexp" class="comp-pane">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">Category-wise Expense Comparison</h4>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Category</th>
                            <th class="a-col" style="text-align:right;"><?= e($label_a) ?></th>
                            <th class="b-col" style="text-align:right;"><?= e($label_b) ?></th>
                            <th class="diff-col" style="text-align:right;">Diff (Abs)</th>
                            <th class="diff-col" style="text-align:right;">Change %</th>
                            <th class="diff-col" style="text-align:right;">Trend</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($cat_exp_aligned as $c):
                            $d = diff_abs($c['a'],$c['b']); $p = diff_pct($c['a'],$c['b']);
                            $arrow = $p > 0.1 ? '↑' : ($p < -0.1 ? '↓' : '→');
                            $arrow_color = $p <= 0 ? 'var(--fp-accent)' : 'var(--fp-danger)';
                        ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td class="num" style="color:var(--fp-danger);"><?= format_currency($c['a']) ?></td>
                            <td class="num" style="color:var(--fp-text-muted);"><?= format_currency($c['b']) ?></td>
                            <td class="diff" style="color:<?= $d<=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                            <td class="diff" style="color:<?= $p<=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= $p ?>%</td>
                            <td class="diff" style="color:<?= $arrow_color ?>;font-size:1.1rem;"><?= $arrow ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cat_exp_aligned)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--fp-text-muted);padding:20px;">No expense data for selected periods.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Category-wise Income -->
                <div id="pane-catinc" class="comp-pane">
                    <h4 style="font-size:.92rem;font-weight:700;color:var(--fp-text-dark);margin-bottom:12px;">Category-wise Income Comparison</h4>
                    <div style="overflow-x:auto;">
                    <table class="ct">
                        <thead><tr>
                            <th style="background:#1e293b;">Category</th>
                            <th class="a-col" style="text-align:right;"><?= e($label_a) ?></th>
                            <th class="b-col" style="text-align:right;"><?= e($label_b) ?></th>
                            <th class="diff-col" style="text-align:right;">Diff (Abs)</th>
                            <th class="diff-col" style="text-align:right;">Change %</th>
                            <th class="diff-col" style="text-align:right;">Trend</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($cat_inc_aligned as $c):
                            $d = diff_abs($c['a'],$c['b']); $p = diff_pct($c['a'],$c['b']);
                            $arrow = $p > 0.1 ? '↑' : ($p < -0.1 ? '↓' : '→');
                            $arrow_color = $p >= 0 ? 'var(--fp-accent)' : 'var(--fp-danger)';
                        ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td class="num" style="color:var(--fp-accent);"><?= format_currency($c['a']) ?></td>
                            <td class="num" style="color:var(--fp-text-muted);"><?= format_currency($c['b']) ?></td>
                            <td class="diff" style="color:<?= $d>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= ($d>=0?'+':'').format_currency($d) ?></td>
                            <td class="diff" style="color:<?= $p>=0?'var(--fp-accent)':'var(--fp-danger)' ?>"><?= $p ?>%</td>
                            <td class="diff" style="color:<?= $arrow_color ?>;font-size:1.1rem;"><?= $arrow ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cat_inc_aligned)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--fp-text-muted);padding:20px;">No income data for selected periods.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- ══ AI Finance Insights ══ -->
            <div class="table-card" style="margin-bottom:24px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:38px;height:38px;background:linear-gradient(135deg,#7c3aed,#2457d9);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-brain" style="color:#fff;font-size:.95rem;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:.95rem;font-weight:700;color:var(--fp-text-dark);">
                            AI Finance Insights — <?= e($label_a) ?> vs <?= e($label_b) ?>
                        </h3>
                        <p style="margin:0;font-size:.75rem;color:var(--fp-text-muted);">Rule-based analysis of the selected periods</p>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($insights as $ins): ?>
                    <div class="fp-alert fp-alert-<?= e($ins['type']) ?>" style="margin:0;display:flex;align-items:flex-start;gap:10px;">
                        <i class="fa-solid <?= e($ins['icon']) ?>" style="margin-top:2px;flex-shrink:0;"></i>
                        <span><?= $ins['msg'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- /fp-content -->
    </div><!-- /fp-main -->
</div><!-- /fp-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
/* ── Tab switching ─────────────────────────────────────── */
function showTab(id) {
    document.querySelectorAll('.comp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.comp-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('pane-' + id).classList.add('active');
    event.currentTarget.classList.add('active');
}

/* ── Chart.js config ───────────────────────────────────── */
const darkMode  = document.body.classList.contains('dark-theme');
const gridColor = darkMode ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)';
const textColor = darkMode ? '#94a3b8' : '#6b7688';
Chart.defaults.color = textColor;
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.font = { size: 11 };

const colorA = '#2457d9';
const colorB = '#d97706';

/* ── Overview Bar Chart ────────────────────────────────── */
new Chart(document.getElementById('overviewChart'), {
    type: 'bar',
    data: {
        labels: ['Income', 'Expenses', 'Gross Profit', 'Net Profit', 'Savings', 'Net Cash Flow'],
        datasets: [
            {
                label: '<?= addslashes($label_a) ?>',
                data: [<?= $a['inc'] ?>, <?= $a['exp'] ?>, <?= $a['gross_profit'] ?>,
                       <?= $a['net_profit'] ?>, <?= $a['savings'] ?>, <?= $a['net_cash'] ?>],
                backgroundColor: colorA + 'CC',
                borderColor: colorA,
                borderWidth: 1,
                borderRadius: 6
            },
            {
                label: '<?= addslashes($label_b) ?>',
                data: [<?= $b['inc'] ?>, <?= $b['exp'] ?>, <?= $b['gross_profit'] ?>,
                       <?= $b['net_profit'] ?>, <?= $b['savings'] ?>, <?= $b['net_cash'] ?>],
                backgroundColor: colorB + 'CC',
                borderColor: colorB,
                borderWidth: 1,
                borderRadius: 6
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { grid: { color: gridColor }, ticks: { callback: v => 'Rs.' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});

/* ── Income Donut ──────────────────────────────────────── */
new Chart(document.getElementById('incomeDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Period A Income', 'Period B Income'],
        datasets: [{
            data: [<?= $a['inc'] ?>, <?= $b['inc'] ?>],
            backgroundColor: [colorA, colorB],
            borderWidth: 2,
            borderColor: darkMode ? '#1e293b' : '#fff'
        }]
    },
    options: {
        responsive: true,
        cutout: '68%',
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ' Rs.' + ctx.parsed.toLocaleString() } }
        }
    }
});

/* ── Category Expense Grouped Bar ─────────────────────── */
const catExpData = <?= json_encode($cat_exp_aligned) ?>;
new Chart(document.getElementById('catExpChart'), {
    type: 'bar',
    data: {
        labels: catExpData.map(r => r.name),
        datasets: [
            { label: '<?= addslashes($label_a) ?>', data: catExpData.map(r=>r.a), backgroundColor: colorA+'BB', borderRadius:5 },
            { label: '<?= addslashes($label_b) ?>', data: catExpData.map(r=>r.b), backgroundColor: colorB+'BB', borderRadius:5 }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { callback: v => 'Rs.'+v.toLocaleString() } },
            y: { grid: { display: false } }
        }
    }
});

/* ── Category Income Grouped Bar ──────────────────────── */
const catIncData = <?= json_encode($cat_inc_aligned) ?>;
new Chart(document.getElementById('catIncChart'), {
    type: 'bar',
    data: {
        labels: catIncData.map(r => r.name),
        datasets: [
            { label: '<?= addslashes($label_a) ?>', data: catIncData.map(r=>r.a), backgroundColor: '#10b981BB', borderRadius:5 },
            { label: '<?= addslashes($label_b) ?>', data: catIncData.map(r=>r.b), backgroundColor: '#06b6d4BB', borderRadius:5 }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { callback: v => 'Rs.'+v.toLocaleString() } },
            y: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>
