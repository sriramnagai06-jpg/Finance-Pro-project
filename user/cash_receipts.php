<?php
/**
 * FinancePro - Cash Receipts Module (Part 6 & Part 3 Compliant)
 * CRUD, Filters (Search, Date, Category, Amount), Pagination, Export Excel/PDF, Print
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$uid = $_SESSION['user_id'];
$errors = [];

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if (verify_csrf_token($_GET['csrf_token'] ?? null)) {
        $stmt = $conn->prepare("DELETE FROM cash_receipts WHERE receipt_id=? AND user_id=?");
        $stmt->bind_param('ii', $del_id, $uid);
        if ($stmt->execute()) {
            set_flash('success', 'Cash Receipt deleted successfully.');
        } else {
            set_flash('danger', 'Failed to delete Cash Receipt.');
        }
        $stmt->close();
    }
    header('Location: cash_receipts.php');
    exit;
}

// Handle Create / Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = clean_input($_POST['action'] ?? 'create');
        $receipt_date = clean_input($_POST['receipt_date'] ?? '');
        $txn_id = clean_input($_POST['transaction_id'] ?? '');
        $particulars = clean_input($_POST['particulars'] ?? '');
        $category = clean_input($_POST['category'] ?? 'General');
        $received_from = clean_input($_POST['received_from'] ?? '');
        $payment_mode = clean_input($_POST['payment_mode'] ?? 'Cash');
        $amount = floatval($_POST['amount'] ?? 0);
        $status = clean_input($_POST['status'] ?? 'Completed');

        if ($receipt_date === '' || $particulars === '' || $received_from === '' || $amount <= 0) {
            $errors[] = 'Please fill in all required fields with valid values.';
        }

        if (empty($errors)) {
            if ($txn_id === '') {
                $txn_id = 'CRV-' . date('Ymd') . '-' . rand(100, 999);
            }

            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO cash_receipts (user_id, transaction_id, receipt_date, category, description, received_from, payment_mode, amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('issssssdsi', $uid, $txn_id, $receipt_date, $category, $particulars, $received_from, $payment_mode, $amount, $status, $uid);
                if ($stmt->execute()) {
                    set_flash('success', 'Cash Receipt created successfully.');
                    header('Location: cash_receipts.php');
                    exit;
                } else {
                    $errors[] = 'Failed to save Cash Receipt.';
                }
                $stmt->close();
            } elseif ($action === 'edit' && isset($_POST['receipt_id'])) {
                $receipt_id = intval($_POST['receipt_id']);
                $stmt = $conn->prepare("UPDATE cash_receipts SET transaction_id=?, receipt_date=?, category=?, description=?, received_from=?, payment_mode=?, amount=?, status=? WHERE receipt_id=? AND user_id=?");
                $stmt->bind_param('ssssssdsii', $txn_id, $receipt_date, $category, $particulars, $received_from, $payment_mode, $amount, $status, $receipt_id, $uid);
                if ($stmt->execute()) {
                    set_flash('success', 'Cash Receipt updated successfully.');
                    header('Location: cash_receipts.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update Cash Receipt.';
                }
                $stmt->close();
            }
        }
    }
}

// Filters & Search Setup
$search = clean_input($_GET['search'] ?? '');
$category_filter = clean_input($_GET['category'] ?? '');
$date_filter = clean_input($_GET['date_filter'] ?? '');
$min_amount = floatval($_GET['min_amount'] ?? 0);
$max_amount = floatval($_GET['max_amount'] ?? 0);
$export = clean_input($_GET['export'] ?? '');

$where_clauses = ["user_id = ?"];
$params = [$uid];
$types = 'i';

if ($search !== '') {
    $where_clauses[] = "(description LIKE ? OR transaction_id LIKE ? OR received_from LIKE ?)";
    $st = "%$search%";
    $params[] = $st; $params[] = $st; $params[] = $st;
    $types .= 'sss';
}
if ($category_filter !== '') {
    $where_clauses[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}
if ($date_filter !== '') {
    $where_clauses[] = "receipt_date = ?";
    $params[] = $date_filter;
    $types .= 's';
}
if ($min_amount > 0) {
    $where_clauses[] = "amount >= ?";
    $params[] = $min_amount;
    $types .= 'd';
}
if ($max_amount > 0) {
    $where_clauses[] = "amount <= ?";
    $params[] = $max_amount;
    $types .= 'd';
}

$where_sql = implode(' AND ', $where_clauses);

// Excel Export
if ($export === 'excel') {
    $stmt = $conn->prepare("SELECT * FROM cash_receipts WHERE $where_sql ORDER BY receipt_date DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cash_receipts_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Transaction ID', 'Category', 'Particulars', 'Received From', 'Mode', 'Amount', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['receipt_date'], $r['transaction_id'], $r['category'], $r['description'], $r['received_from'], $r['payment_mode'], $r['amount'], $r['status']]);
    }
    fclose($out);
    exit;
}

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Count
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM cash_receipts WHERE $where_sql");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0] ?? 0;
$count_stmt->close();
$total_pages = max(1, ceil($total_rows / $limit));

// Fetch
$query_sql = "SELECT * FROM cash_receipts WHERE $where_sql ORDER BY receipt_date DESC LIMIT ? OFFSET ?";
$fetch_params = $params;
$fetch_params[] = $limit;
$fetch_params[] = $offset;
$fetch_types = $types . 'ii';

$stmt = $conn->prepare($query_sql);
$stmt->bind_param($fetch_types, ...$fetch_params);
$stmt->execute();
$receipts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'cash_receipts';
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Receipts - <?= e(SITE_NAME) ?></title>
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
                    <h1 class="page-title mb-1"><i class="fa-solid fa-money-bill-wave"></i> Cash Receipts</h1>
                    <p class="text-muted mb-0">Record and manage cash receipt vouchers with advanced filters</p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fa-solid fa-print"></i> Print</button>
                    <a href="cash_receipts.php?export=excel&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
                    <button class="btn btn-fp-primary" data-bs-toggle="modal" data-bs-target="#createReceiptModal">
                        <i class="fa-solid fa-plus"></i> New Cash Receipt
                    </button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filter-bar">
                <form method="GET" action="cash_receipts.php" class="d-flex gap-2 w-100 flex-wrap align-items-center">
                    <input type="text" name="search" class="form-control" style="max-width:200px;" placeholder="Search Txn ID, name..." value="<?= e($search) ?>">
                    <input type="date" name="date_filter" class="form-control" style="max-width:160px;" value="<?= e($date_filter) ?>">
                    <input type="number" name="min_amount" class="form-control" style="max-width:130px;" placeholder="Min Amount" value="<?= $min_amount > 0 ? $min_amount : '' ?>">
                    <input type="number" name="max_amount" class="form-control" style="max-width:130px;" placeholder="Max Amount" value="<?= $max_amount > 0 ? $max_amount : '' ?>">
                    <button class="btn btn-fp-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a href="cash_receipts.php" class="btn btn-outline-secondary">Reset</a>
                </form>
            </div>

            <!-- Table -->
            <div class="card card-glass rounded-card p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Particulars</th>
                                <th>Category</th>
                                <th>Received From</th>
                                <th>Mode</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($receipts)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">No cash receipts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($receipts as $r): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($r['receipt_date'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= e($r['transaction_id']) ?></span></td>
                                        <td class="fw-semibold"><?= e($r['description']) ?></td>
                                        <td><?= e($r['category']) ?></td>
                                        <td><?= e($r['received_from']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= e($r['payment_mode']) ?></span></td>
                                        <td class="fw-bold text-success"><?= format_currency($r['amount']) ?></td>
                                        <td><span class="badge bg-success"><?= e($r['status']) ?></span></td>
                                        <td>
                                            <a href="cash_receipts.php?action=delete&id=<?= $r['receipt_id'] ?>&csrf_token=<?= e($csrf_token) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?')"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="d-flex justify-content-center mt-3">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="cash_receipts.php?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<!-- Modal: Create Cash Receipt -->
<div class="modal fade" id="createReceiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="cash_receipts.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-money-bill-wave"></i> New Cash Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Receipt Date</label>
                        <input type="date" class="form-control" name="receipt_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction ID <small class="text-muted">(Optional)</small></label>
                        <input type="text" class="form-control" name="transaction_id" placeholder="Auto-generated if empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Received From</label>
                        <input type="text" class="form-control" name="received_from" required placeholder="Person / Client name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" required value="General Income">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Mode</label>
                        <select class="form-select" name="payment_mode">
                            <option value="Cash" selected>Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Bank">Bank</option>
                            <option value="Card">Card</option>
                            <option value="Wallet">Wallet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Particulars / Description</label>
                        <input type="text" class="form-control" name="particulars" required placeholder="Reason for receipt">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (<?= e(CURRENCY) ?>)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required placeholder="Amount received">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-fp-primary">Save Cash Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
