<?php
/**
 * FinancePro - Expense Management (Module 4)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { $errors[] = 'Invalid request.'; }
    else {
        $amount       = (float)($_POST['amount'] ?? 0);
        $category_id  = (int)($_POST['category_id'] ?? 0);
        $expense_date = clean_input($_POST['expense_date'] ?? '');
        $description  = clean_input($_POST['description'] ?? '');
        $payment_mode = clean_input($_POST['payment_mode'] ?? 'cash');

        if ($amount <= 0)     $errors[] = 'Amount must be greater than 0.';
        if (!$category_id)    $errors[] = 'Please select a category.';
        if (!$expense_date)   $errors[] = 'Please select a date.';

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, category_id, amount, expense_date, description, payment_mode) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('iidsss', $uid, $category_id, $amount, $expense_date, $description, $payment_mode);
            if ($stmt->execute()) {
                $expense_id = $stmt->insert_id;
                
                // --- Start Trigger Migration ---
                $cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
                $cat_stmt->bind_param('i', $category_id);
                $cat_stmt->execute();
                $cat_res = $cat_stmt->get_result();
                $cat_row = $cat_res->fetch_assoc();
                $cat_name = $cat_row ? $cat_row['category_name'] : 'Expense';
                $cat_stmt->close();
                
                $exp_stmt = $conn->prepare("SELECT account_id FROM accounts WHERE user_id = ? AND account_name = ? LIMIT 1");
                $exp_stmt->bind_param('is', $uid, $cat_name);
                $exp_stmt->execute();
                $exp_res = $exp_stmt->get_result();
                $exp_row = $exp_res->fetch_assoc();
                $exp_account_id = $exp_row ? $exp_row['account_id'] : null;
                $exp_stmt->close();
                
                $asset_name = 'Bank';
                if ($payment_mode === 'cash') $asset_name = 'Cash';
                elseif ($payment_mode === 'card') $asset_name = 'Card';
                
                $asset_stmt = $conn->prepare("SELECT account_id FROM accounts WHERE user_id = ? AND account_name = ? LIMIT 1");
                $asset_stmt->bind_param('is', $uid, $asset_name);
                $asset_stmt->execute();
                $asset_res = $asset_stmt->get_result();
                $asset_row = $asset_res->fetch_assoc();
                $asset_account_id = $asset_row ? $asset_row['account_id'] : null;
                $asset_stmt->close();
                
                if (!$asset_account_id) {
                    $asset_type = 'Asset';
                    $asset_stmt2 = $conn->prepare("SELECT account_id FROM accounts WHERE user_id = ? AND account_type = ? LIMIT 1");
                    $asset_stmt2->bind_param('is', $uid, $asset_type);
                    $asset_stmt2->execute();
                    $asset_res2 = $asset_stmt2->get_result();
                    $asset_row2 = $asset_res2->fetch_assoc();
                    $asset_account_id = $asset_row2 ? $asset_row2['account_id'] : null;
                    $asset_stmt2->close();
                }
                
                if ($exp_account_id && $asset_account_id) {
                    $desc = "Expense: " . ($description ? $description : $cat_name);
                    $ref_type = 'expense';
                    $je_stmt = $conn->prepare("INSERT INTO journal_entries (user_id, entry_date, description, reference_type, reference_id) VALUES (?,?,?,?,?)");
                    $je_stmt->bind_param('isssi', $uid, $expense_date, $desc, $ref_type, $expense_id);
                    if ($je_stmt->execute()) {
                        $entry_id = $je_stmt->insert_id;
                        
                        $ji_stmt = $conn->prepare("INSERT INTO journal_items (entry_id, account_id, debit, credit) VALUES (?,?,?,0)");
                        $ji_stmt->bind_param('iid', $entry_id, $exp_account_id, $amount);
                        $ji_stmt->execute();
                        $ji_stmt->close();
                        
                        $ji_stmt2 = $conn->prepare("INSERT INTO journal_items (entry_id, account_id, debit, credit) VALUES (?,?,0,?)");
                        $ji_stmt2->bind_param('iid', $entry_id, $asset_account_id, $amount);
                        $ji_stmt2->execute();
                        $ji_stmt2->close();
                    }
                    $je_stmt->close();
                }
                // --- End Trigger Migration ---

                audit_log($conn, $uid, 'insert', 'expenses', $expense_id, "Added expense: {$cat_name} ({$amount})");
                
                // Notifications Check
                $settings = get_user_settings($conn, $uid);
                if ($amount >= $settings['large_expense_threshold']) {
                    add_notification($conn, $uid, 'large_expense', 'Large Expense Alert', "You just added an expense of " . format_currency_custom($amount, $settings) . " which exceeds your alert threshold.");
                }

                // Budget Check
                $m = (int)date('n', strtotime($expense_date));
                $y = (int)date('Y', strtotime($expense_date));
                $b_stmt = $conn->prepare("
                    SELECT b.budget_amount, COALESCE(SUM(e.amount),0) as spent, c.category_name
                    FROM budget b
                    JOIN categories c ON b.category_id = c.category_id
                    LEFT JOIN expenses e ON e.category_id = b.category_id AND e.user_id = b.user_id AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=?
                    WHERE b.user_id=? AND b.category_id=? AND b.budget_month=? AND b.budget_year=?
                    GROUP BY b.budget_id
                ");
                $b_stmt->bind_param('iiiiii', $m, $y, $uid, $category_id, $m, $y);
                $b_stmt->execute();
                $b_res = $b_stmt->get_result()->fetch_assoc();
                $b_stmt->close();

                if ($b_res && $b_res['budget_amount'] > 0) {
                    $pct = ($b_res['spent'] / $b_res['budget_amount']) * 100;
                    if ($pct >= 100) {
                        add_notification($conn, $uid, 'budget_exceeded', 'Budget Exceeded', "You have exceeded your budget for " . $b_res['category_name'] . " this month.");
                    } elseif ($pct >= 80) {
                        // Using 'system' type for warning to distinguish from exceeded
                        add_notification($conn, $uid, 'system', 'Budget Warning', "You have used " . round($pct) . "% of your budget for " . $b_res['category_name'] . ".");
                    }
                }

                set_flash('success', 'Expense added successfully!'); header('Location: expenses.php'); exit; 
            }
            else { $errors[] = 'Failed to save. Try again.'; }
            $stmt->close();
        }
    }
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del = (int)$_GET['delete'];
    
    // --- Start Trigger Migration ---
    $del_je_stmt = $conn->prepare("DELETE FROM journal_entries WHERE reference_type = 'expense' AND reference_id = ?");
    $del_je_stmt->bind_param('i', $del);
    $del_je_stmt->execute();
    $del_je_stmt->close();
    // --- End Trigger Migration ---
    
    $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_id=? AND user_id=?");
    $stmt->bind_param('ii', $del, $uid); 
    if ($stmt->execute()) {
        audit_log($conn, $uid, 'delete', 'expenses', $del, "Deleted expense ID: $del");
    }
    $stmt->close();
    set_flash('success', 'Expense deleted.'); header('Location: expenses.php'); exit;
}

// Filters & Pagination
$filter_month = (int)($_GET['month'] ?? date('n'));
$filter_year  = (int)($_GET['year']  ?? date('Y'));
$filter_cat   = (int)($_GET['cat']   ?? 0);
$search       = clean_input($_GET['search'] ?? '');
$page_num     = max(1,(int)($_GET['page'] ?? 1));
$per_page     = 10; $offset = ($page_num-1)*$per_page;

$where = "WHERE e.user_id=?"; $params=[$uid]; $types='i';
if ($filter_month){ $where.=" AND MONTH(e.expense_date)=?"; $params[]=$filter_month; $types.='i'; }
if ($filter_year) { $where.=" AND YEAR(e.expense_date)=?";  $params[]=$filter_year;  $types.='i'; }
if ($filter_cat)  { $where.=" AND e.category_id=?";         $params[]=$filter_cat;   $types.='i'; }
if ($search)      { $like="%$search%"; $where.=" AND (e.description LIKE ? OR c.category_name LIKE ?)"; $params[]=$like; $params[]=$like; $types.='ss'; }

$total_rows = $conn->prepare("SELECT COUNT(*) FROM expenses e JOIN categories c ON e.category_id=c.category_id $where");
$total_rows->bind_param($types, ...$params); $total_rows->execute();
$total = $total_rows->get_result()->fetch_row()[0]; $total_rows->close();
$total_pages = ceil($total / $per_page);

$list = $conn->prepare("SELECT e.*, c.category_name, c.icon_class FROM expenses e JOIN categories c ON e.category_id=c.category_id $where ORDER BY e.expense_date DESC LIMIT ? OFFSET ?");
$p2 = $params; $p2[] = $per_page; $p2[] = $offset;
$list->bind_param($types.'ii', ...$p2); $list->execute();
$expense_list = $list->get_result()->fetch_all(MYSQLI_ASSOC); $list->close();

$sum = $conn->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN categories c ON e.category_id=c.category_id $where");
$sum->bind_param($types, ...$params); $sum->execute();
$filtered_total = $sum->get_result()->fetch_row()[0]; $sum->close();

$cats = $conn->query("SELECT category_id, category_name FROM categories WHERE category_type='expense' ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$csrf_token = generate_csrf_token();
$active_page = 'expenses'; $page_title = 'Expenses'; $page_subtitle = 'Track your spending';
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - <?= e(SITE_NAME) ?></title>
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
            <?php if (!empty($errors)): ?><div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= implode(' &bull; ', array_map('e', $errors)) ?></div><?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 320px; gap:24px; align-items:start;">
                <!-- List -->
                <div class="table-card">
                    <div class="table-card-header">
                        <div><div class="tc-title">Expense Records</div><div class="tc-sub">Total: <strong style="color:var(--fp-danger)"><?= format_currency($filtered_total) ?></strong></div></div>
                    </div>
                    <div style="padding:16px 20px; border-bottom:1px solid var(--fp-border);">
                        <form method="GET" class="filters-bar">
                            <input type="text" name="search" class="fp-input" placeholder="Search..." value="<?= e($search) ?>">
                            <select name="month" class="fp-select">
                                <option value="">All Months</option>
                                <?php foreach($months as $mi=>$mn): ?><option value="<?=$mi+1?>" <?=$filter_month===$mi+1?'selected':''?>><?=$mn?></option><?php endforeach; ?>
                            </select>
                            <select name="year" class="fp-select">
                                <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$filter_year===$y?'selected':''?>><?=$y?></option><?php endfor; ?>
                            </select>
                            <select name="cat" class="fp-select">
                                <option value="">All Categories</option>
                                <?php foreach($cats as $c): ?><option value="<?=$c['category_id']?>" <?=$filter_cat===$c['category_id']?'selected':''?>><?=e($c['category_name'])?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                            <a href="expenses.php" class="btn-fp btn-fp-outline btn-fp-sm">Reset</a>
                        </form>
                    </div>
                    <?php if (empty($expense_list)): ?>
                    <div class="empty-state"><i class="fa-solid fa-receipt"></i><h3>No expense records</h3><p>Add your first expense using the form.</p></div>
                    <?php else: ?>
                    <table class="fp-table">
                        <thead><tr><th>Category</th><th>Description</th><th>Date</th><th>Mode</th><th>Amount</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach($expense_list as $row): ?>
                        <tr>
                            <td><i class="<?=e($row['icon_class'])?>" style="color:var(--fp-danger);margin-right:6px;"></i><?=e($row['category_name'])?></td>
                            <td style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($row['description']?:'—')?></td>
                            <td style="font-size:0.82rem;"><?=format_date($row['expense_date'])?></td>
                            <td style="font-size:0.78rem;text-transform:capitalize;"><?=e(str_replace('_',' ',$row['payment_mode']))?></td>
                            <td style="font-weight:700;color:var(--fp-danger);">-<?=format_currency($row['amount'])?></td>
                            <td><a href="?delete=<?=$row['expense_id']?>" class="btn-fp btn-fp-danger btn-fp-sm" onclick="return confirm('Delete this expense?')"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if($total_pages>1): ?>
                    <div class="fp-pagination">
                        <?php for($p=1;$p<=$total_pages;$p++): ?>
                        <?php if($p===$page_num): ?><span class="current"><?=$p?></span>
                        <?php else: ?><a href="?page=<?=$p?>&month=<?=$filter_month?>&year=<?=$filter_year?>&cat=<?=$filter_cat?>&search=<?=urlencode($search)?>"><?=$p?></a><?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Add Form -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fa-solid fa-plus" style="color:var(--fp-danger)"></i> Add Expense</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                        <input type="hidden" name="action" value="add">
                        <div class="fp-form-group"><label class="fp-label">Amount (Rs.)</label><input type="number" name="amount" class="fp-input" min="0.01" step="0.01" placeholder="0.00" required></div>
                        <div class="fp-form-group"><label class="fp-label">Category</label>
                            <select name="category_id" class="fp-select" required><option value="">Select category</option>
                                <?php foreach($cats as $c): ?><option value="<?=$c['category_id']?>"><?=e($c['category_name'])?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fp-form-group"><label class="fp-label">Date</label><input type="date" name="expense_date" class="fp-input" value="<?=date('Y-m-d')?>" required></div>
                        <div class="fp-form-group"><label class="fp-label">Payment Mode</label>
                            <select name="payment_mode" class="fp-select">
                                <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option><option value="card">Card</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div class="fp-form-group"><label class="fp-label">Description (optional)</label><textarea name="description" class="fp-textarea" placeholder="What was this for?"></textarea></div>
                        <button type="submit" class="btn-fp btn-fp-danger" style="width:100%"><i class="fa-solid fa-plus"></i> Add Expense</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
