<?php
/**
 * FinancePro - Budget Planner (Module 5)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];

$sel_month = (int)($_GET['month'] ?? date('n'));
$sel_year  = (int)($_GET['year']  ?? date('Y'));

// Handle Save Budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { $errors[] = 'Invalid request.'; }
    else {
        $cat_id = (int)$_POST['category_id'];
        $amount = (float)$_POST['budget_amount'];
        $month  = (int)$_POST['budget_month'];
        $year   = (int)$_POST['budget_year'];
        if ($amount <= 0) $errors[] = 'Budget amount must be greater than 0.';
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO budget (user_id, category_id, budget_amount, budget_month, budget_year)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE budget_amount=VALUES(budget_amount)");
            $stmt->bind_param('iidii', $uid, $cat_id, $amount, $month, $year);
            if ($stmt->execute()) { set_flash('success','Budget saved!'); header("Location: budget.php?month=$sel_month&year=$sel_year"); exit; }
            $stmt->close();
        }
    }
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM budget WHERE budget_id=? AND user_id=?");
    $del_id = (int)$_GET['delete'];
    $stmt->bind_param('ii', $del_id, $uid); $stmt->execute(); $stmt->close();
    set_flash('success','Budget removed.'); header("Location: budget.php?month=$sel_month&year=$sel_year"); exit;
}

// Fetch budgets with spending
$budgets_stmt = $conn->prepare("
    SELECT b.budget_id, b.budget_amount, b.category_id, c.category_name, c.icon_class,
           COALESCE(SUM(e.amount),0) as spent
    FROM budget b
    JOIN categories c ON b.category_id=c.category_id
    LEFT JOIN expenses e ON e.category_id=b.category_id AND e.user_id=b.user_id
        AND MONTH(e.expense_date)=b.budget_month AND YEAR(e.expense_date)=b.budget_year
    WHERE b.user_id=? AND b.budget_month=? AND b.budget_year=?
    GROUP BY b.budget_id ORDER BY c.category_name");
$budgets_stmt->bind_param('iii', $uid, $sel_month, $sel_year);
$budgets_stmt->execute();
$budgets = $budgets_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $budgets_stmt->close();

$cats = $conn->query("SELECT category_id, category_name FROM categories WHERE category_type='expense' ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$csrf_token = generate_csrf_token();
$active_page = 'budget'; $page_title = 'Budget Planner'; $page_subtitle = 'Set and track monthly spending limits';
$month_names = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Budget Planner - <?=e(SITE_NAME)?></title>
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
            <?php if(!empty($errors)): ?><div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?=implode(' &bull; ', array_map('e',$errors))?></div><?php endif; ?>

            <!-- Period Selector -->
            <form method="GET" style="display:flex; gap:10px; margin-bottom:24px; align-items:center;">
                <select name="month" class="fp-select" style="width:160px;">
                    <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$sel_month===$m?'selected':''?>><?=$month_names[$m]?></option><?php endfor; ?>
                </select>
                <select name="year" class="fp-select" style="width:120px;">
                    <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$sel_year===$y?'selected':''?>><?=$y?></option><?php endfor; ?>
                </select>
                <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-calendar"></i> View</button>
            </form>

            <div style="display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start;">
                <!-- Budget Items -->
                <div>
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:16px; color:var(--fp-text-muted);">
                        Budget for <?=$month_names[$sel_month]?> <?=$sel_year?>
                    </h2>
                    <?php if(empty($budgets)): ?>
                    <div class="table-card"><div class="empty-state"><i class="fa-solid fa-bullseye"></i><h3>No budgets set</h3><p>Add budget limits for expense categories using the form.</p></div></div>
                    <?php else: ?>
                    <?php
                    $total_budget = array_sum(array_column($budgets,'budget_amount'));
                    $total_spent  = array_sum(array_column($budgets,'spent'));
                    ?>
                    <!-- Summary Bar -->
                    <div class="summary-card" style="margin-bottom:20px;">
                        <div class="card-icon balance"><i class="fa-solid fa-bullseye"></i></div>
                        <div class="card-info">
                            <div class="card-label">Overall Budget Usage</div>
                            <div style="font-size:1.1rem;font-weight:700;margin:6px 0;"><?=format_currency($total_spent)?> / <?=format_currency($total_budget)?></div>
                            <?php $ov_pct = $total_budget>0?min(100,round($total_spent/$total_budget*100)):0; ?>
                            <div class="fp-progress" style="height:10px; width:100%;">
                                <div class="fp-progress-bar <?=$ov_pct>=100?'danger':($ov_pct>=75?'warning':'')?>" style="width:<?=$ov_pct?>%"></div>
                            </div>
                            <div style="font-size:0.75rem; color:var(--fp-text-muted); margin-top:4px;"><?=$ov_pct?>% used</div>
                        </div>
                    </div>

                    <?php foreach($budgets as $b):
                        $pct = $b['budget_amount']>0 ? min(100, round($b['spent']/$b['budget_amount']*100)) : 0;
                        $remaining = $b['budget_amount'] - $b['spent'];
                        $bar_class = $pct>=100?'danger':($pct>=75?'warning':'');
                    ?>
                    <div class="budget-item">
                        <div class="budget-item-header">
                            <div class="budget-cat">
                                <i class="<?=e($b['icon_class'])?>"></i> <?=e($b['category_name'])?>
                                <?php if($pct>=100): ?><span class="badge-fp badge-expense" style="font-size:0.65rem;">Over Budget!</span><?php endif; ?>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <div class="budget-amounts">Spent: <strong><?=format_currency($b['spent'])?></strong></div>
                                <a href="?delete=<?=$b['budget_id']?>&month=<?=$sel_month?>&year=<?=$sel_year?>" onclick="return confirm('Remove this budget?')" class="btn-fp btn-fp-outline btn-fp-sm" style="padding:3px 8px;"><i class="fa-solid fa-trash" style="color:var(--fp-danger)"></i></a>
                            </div>
                        </div>
                        <div class="fp-progress"><div class="fp-progress-bar <?=$bar_class?>" style="width:<?=$pct?>%"></div></div>
                        <div class="budget-footer">
                            <span>Budget: <?=format_currency($b['budget_amount'])?></span>
                            <span style="color:<?=$remaining<0?'var(--fp-danger)':'var(--fp-accent)'?>; font-weight:600;">
                                <?=$remaining>=0?'Remaining: '.format_currency($remaining):'Over by: '.format_currency(abs($remaining))?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Budget Form -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fa-solid fa-plus" style="color:var(--fp-primary)"></i> Set Budget</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                        <input type="hidden" name="action" value="save">
                        <div class="fp-form-group"><label class="fp-label">Category</label>
                            <select name="category_id" class="fp-select" required><option value="">Select category</option>
                                <?php foreach($cats as $c): ?><option value="<?=$c['category_id']?>"><?=e($c['category_name'])?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fp-form-group"><label class="fp-label">Budget Amount (Rs.)</label>
                            <input type="number" name="budget_amount" class="fp-input" min="1" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="fp-form-group"><label class="fp-label">Month</label>
                            <select name="budget_month" class="fp-select">
                                <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$sel_month===$m?'selected':''?>><?=$month_names[$m]?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="fp-form-group"><label class="fp-label">Year</label>
                            <select name="budget_year" class="fp-select">
                                <?php for($y=date('Y');$y>=date('Y')-2;$y--): ?><option value="<?=$y?>" <?=$sel_year===$y?'selected':''?>><?=$y?></option><?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-fp btn-fp-primary" style="width:100%"><i class="fa-solid fa-floppy-disk"></i> Save Budget</button>
                    </form>
                    <div style="margin-top:14px; font-size:0.78rem; color:var(--fp-text-muted);">
                        <i class="fa-solid fa-info-circle"></i> If a budget for that category/month already exists, it will be updated.
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
