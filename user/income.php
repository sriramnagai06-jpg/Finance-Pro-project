<?php
/**
 * FinancePro - Income Management (Module 3)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = []; $success = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { $errors[] = 'Invalid request.'; }
    else {
        $amount      = (float)($_POST['amount'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $income_date = clean_input($_POST['income_date'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $payment_mode= clean_input($_POST['payment_mode'] ?? 'cash');

        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        if ($category_id <= 0) $errors[] = 'Please select a category.';
        if (!$income_date) $errors[] = 'Please select a date.';

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO income (user_id, category_id, amount, income_date, description, payment_mode) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('iidsss', $uid, $category_id, $amount, $income_date, $description, $payment_mode);
            if ($stmt->execute()) {
                $income_id = $stmt->insert_id;
                
                // --- Start Trigger Migration ---
                $cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
                $cat_stmt->bind_param('i', $category_id);
                $cat_stmt->execute();
                $cat_res = $cat_stmt->get_result();
                $cat_row = $cat_res->fetch_assoc();
                $cat_name = $cat_row ? $cat_row['category_name'] : 'Income';
                $cat_stmt->close();
                
                $rev_stmt = $conn->prepare("SELECT account_id FROM accounts WHERE user_id = ? AND account_name = ? LIMIT 1");
                $rev_stmt->bind_param('is', $uid, $cat_name);
                $rev_stmt->execute();
                $rev_res = $rev_stmt->get_result();
                $rev_row = $rev_res->fetch_assoc();
                $rev_account_id = $rev_row ? $rev_row['account_id'] : null;
                $rev_stmt->close();
                
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
                
                if ($rev_account_id && $asset_account_id) {
                    $desc = "Income: " . ($description ? $description : $cat_name);
                    $ref_type = 'income';
                    $je_stmt = $conn->prepare("INSERT INTO journal_entries (user_id, entry_date, description, reference_type, reference_id) VALUES (?,?,?,?,?)");
                    $je_stmt->bind_param('isssi', $uid, $income_date, $desc, $ref_type, $income_id);
                    if ($je_stmt->execute()) {
                        $entry_id = $je_stmt->insert_id;
                        
                        $ji_stmt = $conn->prepare("INSERT INTO journal_items (entry_id, account_id, debit, credit) VALUES (?,?,?,0)");
                        $ji_stmt->bind_param('iid', $entry_id, $asset_account_id, $amount);
                        $ji_stmt->execute();
                        $ji_stmt->close();
                        
                        $ji_stmt2 = $conn->prepare("INSERT INTO journal_items (entry_id, account_id, debit, credit) VALUES (?,?,0,?)");
                        $ji_stmt2->bind_param('iid', $entry_id, $rev_account_id, $amount);
                        $ji_stmt2->execute();
                        $ji_stmt2->close();
                    }
                    $je_stmt->close();
                }
                // --- End Trigger Migration ---

                set_flash('success', 'Income added successfully!'); header('Location: income.php'); exit; 
            }
            else { $errors[] = 'Failed to add income. Try again.'; }
            $stmt->close();
        }
    }
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];

    // --- Start Trigger Migration ---
    $del_je_stmt = $conn->prepare("DELETE FROM journal_entries WHERE reference_type = 'income' AND reference_id = ?");
    $del_je_stmt->bind_param('i', $del_id);
    $del_je_stmt->execute();
    $del_je_stmt->close();
    // --- End Trigger Migration ---

    $stmt = $conn->prepare("DELETE FROM income WHERE income_id=? AND user_id=?");
    $stmt->bind_param('ii', $del_id, $uid); $stmt->execute(); $stmt->close();
    set_flash('success', 'Income deleted.'); header('Location: income.php'); exit;
}

// Filters
$filter_month = (int)($_GET['month'] ?? date('n'));
$filter_year  = (int)($_GET['year']  ?? date('Y'));
$filter_cat   = (int)($_GET['cat']   ?? 0);
$search       = clean_input($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 10;
$offset       = ($page - 1) * $per_page;

$where = "WHERE i.user_id=?";
$params = [$uid]; $types = 'i';
if ($filter_month) { $where .= " AND MONTH(i.income_date)=?"; $params[] = $filter_month; $types .= 'i'; }
if ($filter_year)  { $where .= " AND YEAR(i.income_date)=?";  $params[] = $filter_year;  $types .= 'i'; }
if ($filter_cat)   { $where .= " AND i.category_id=?";        $params[] = $filter_cat;   $types .= 'i'; }
if ($search)       { $where .= " AND (i.description LIKE ? OR c.category_name LIKE ?)";
                     $like = "%$search%"; $params[] = $like; $params[] = $like; $types .= 'ss'; }

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM income i JOIN categories c ON i.category_id=c.category_id $where");
$count_stmt->bind_param($types, ...$params); $count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0]; $count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

$list_stmt = $conn->prepare("SELECT i.*, c.category_name, c.icon_class FROM income i JOIN categories c ON i.category_id=c.category_id $where ORDER BY i.income_date DESC LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= 'ii';
$list_stmt->bind_param($types, ...$params); $list_stmt->execute();
$income_list = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $list_stmt->close();

// Total for filtered period
$sum_stmt = $conn->prepare("SELECT COALESCE(SUM(i.amount),0) FROM income i JOIN categories c ON i.category_id=c.category_id $where");
array_pop($params); array_pop($params); $types = substr($types, 0, -2);
$sum_stmt->bind_param($types, ...$params); $sum_stmt->execute();
$filtered_total = $sum_stmt->get_result()->fetch_row()[0]; $sum_stmt->close();

// Categories for dropdown
$cats = $conn->query("SELECT category_id, category_name FROM categories WHERE category_type='income' ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

$csrf_token  = generate_csrf_token();
$active_page = 'income'; $page_title = 'Income'; $page_subtitle = 'Track your earnings';
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">`r`n    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>
            <?php if (!empty($errors)): ?>
                <div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= implode(' &bull; ', array_map('e', $errors)) ?></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 320px; gap:24px; align-items:start;">

                <!-- Left: List -->
                <div>
                    <!-- Filters -->
                    <div class="table-card" style="margin-bottom:20px;">
                        <div class="table-card-header">
                            <div><div class="tc-title">Income Records</div><div class="tc-sub">Total: <strong style="color:var(--fp-accent)"><?= format_currency($filtered_total) ?></strong></div></div>
                        </div>
                        <div style="padding:16px 20px; border-bottom:1px solid var(--fp-border);">
                            <form method="GET" class="filters-bar">
                                <input type="text" name="search" class="fp-input" placeholder="Search..." value="<?= e($search) ?>">
                                <select name="month" class="fp-select">
                                    <option value="">All Months</option>
                                    <?php foreach ($months as $mi=>$mn): ?>
                                    <option value="<?= $mi+1 ?>" <?= $filter_month===$mi+1?'selected':'' ?>><?= $mn ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="year" class="fp-select">
                                    <?php for ($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filter_year===$y?'selected':'' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="cat" class="fp-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['category_id'] ?>" <?= $filter_cat===$c['category_id']?'selected':'' ?>><?= e($c['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                                <a href="income.php" class="btn-fp btn-fp-outline btn-fp-sm">Reset</a>
                            </form>
                        </div>

                        <?php if (empty($income_list)): ?>
                        <div class="empty-state"><i class="fa-solid fa-money-bill-trend-up"></i><h3>No income records</h3><p>Add your first income entry using the form.</p></div>
                        <?php else: ?>
                        <table class="fp-table">
                            <thead><tr><th>Category</th><th>Description</th><th>Date</th><th>Mode</th><th>Amount</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($income_list as $row): ?>
                            <tr>
                                <td><i class="<?= e($row['icon_class']) ?>" style="color:var(--fp-accent); margin-right:6px;"></i><?= e($row['category_name']) ?></td>
                                <td style="font-size:0.82rem; color:var(--fp-text-muted);"><?= e($row['description'] ?: '—') ?></td>
                                <td style="font-size:0.82rem;"><?= format_date($row['income_date']) ?></td>
                                <td><span style="font-size:0.78rem; text-transform:capitalize;"><?= e(str_replace('_',' ',$row['payment_mode'])) ?></span></td>
                                <td style="font-weight:700; color:var(--fp-accent);">+<?= format_currency($row['amount']) ?></td>
                                <td>
                                    <a href="?delete=<?= $row['income_id'] ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>" class="btn-fp btn-fp-danger btn-fp-sm" onclick="return confirm('Delete this income record?')"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_pages > 1): ?>
                        <div class="fp-pagination">
                            <?php for ($p=1;$p<=$total_pages;$p++): ?>
                            <?php if ($p===$page): ?><span class="current"><?= $p ?></span>
                            <?php else: ?><a href="?page=<?= $p ?>&month=<?= $filter_month ?>&year=<?= $filter_year ?>&cat=<?= $filter_cat ?>&search=<?= urlencode($search) ?>"><?= $p ?></a><?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Add Form -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fa-solid fa-plus" style="color:var(--fp-accent)"></i> Add Income</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="fp-form-group">
                            <label class="fp-label">Amount (Rs.)</label>
                            <input type="number" name="amount" class="fp-input" min="0.01" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Category</label>
                            <select name="category_id" class="fp-select" required>
                                <option value="">Select category</option>
                                <?php foreach ($cats as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Date</label>
                            <input type="date" name="income_date" class="fp-input" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Payment Mode</label>
                            <select name="payment_mode" class="fp-select">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Description (optional)</label>
                            <textarea name="description" class="fp-textarea" placeholder="Notes about this income..."></textarea>
                        </div>
                        <button type="submit" class="btn-fp btn-fp-success" style="width:100%"><i class="fa-solid fa-plus"></i> Add Income</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
