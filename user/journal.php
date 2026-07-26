<?php
/**
 * FinancePro - Journal Book
 * Shows all double-entry journal records auto-generated from income/expense transactions.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Fetch journal entries with their items and accounts
$stmt = $conn->prepare("
    SELECT je.entry_id, je.entry_date, je.description, je.reference_type, je.reference_id,
           ji.debit, ji.credit, a.account_name, a.account_type
    FROM journal_entries je
    JOIN journal_items ji ON je.entry_id = ji.entry_id
    JOIN accounts a ON ji.account_id = a.account_id
    WHERE je.user_id = ? AND je.entry_date BETWEEN ? AND ?
    ORDER BY je.entry_date ASC, je.entry_id ASC, ji.debit DESC
");
$stmt->bind_param("iss", $uid, $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group by entry_id
$entries = [];
foreach ($rows as $row) {
    $eid = $row['entry_id'];
    if (!isset($entries[$eid])) {
        $entries[$eid] = [
            'entry_date'     => $row['entry_date'],
            'description'    => $row['description'],
            'reference_type' => $row['reference_type'],
            'items'          => []
        ];
    }
    $entries[$eid]['items'][] = $row;
}

$total_debit  = 0;
$total_credit = 0;
foreach ($rows as $r) { $total_debit += $r['debit']; $total_credit += $r['credit']; }

$active_page = 'journal'; $page_title = 'Journal Book';
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Journal Book - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>

            <!-- Page Header -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--fp-text-dark);margin:0;">
                        <i class="fa-solid fa-book" style="color:var(--fp-primary)"></i> Journal Book
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">Double-entry accounting records — auto-generated from every transaction</p>
                </div>
                <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>

            <!-- Filter -->
            <form method="GET" style="display:flex;gap:10px;margin-bottom:24px;align-items:center;flex-wrap:wrap;">
                <div class="fp-form-group" style="margin:0;">
                    <input type="date" name="from" value="<?= e($from) ?>" class="fp-input" style="width:160px;">
                </div>
                <span style="color:var(--fp-text-muted);">to</span>
                <div class="fp-form-group" style="margin:0;">
                    <input type="date" name="to" value="<?= e($to) ?>" class="fp-input" style="width:160px;">
                </div>
                <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            </form>

            <!-- Summary Cards -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
                <div class="summary-card">
                    <div class="card-icon income"><i class="fa-solid fa-arrow-down"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Debit</div>
                        <div class="card-value" style="color:var(--fp-primary)"><?= format_currency($total_debit) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon expense"><i class="fa-solid fa-arrow-up"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Credit</div>
                        <div class="card-value" style="color:var(--fp-accent)"><?= format_currency($total_credit) ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon balance"><i class="fa-solid fa-scale-balanced"></i></div>
                    <div class="card-info">
                        <div class="card-label">Balanced?</div>
                        <div class="card-value" style="color:<?= abs($total_debit-$total_credit)<0.01?'var(--fp-accent)':'var(--fp-danger)' ?>">
                            <?= abs($total_debit - $total_credit) < 0.01 ? '✓ YES' : '✗ NO' ?>
                        </div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="card-icon balance"><i class="fa-solid fa-list"></i></div>
                    <div class="card-info">
                        <div class="card-label">Total Entries</div>
                        <div class="card-value"><?= count($entries) ?></div>
                    </div>
                </div>
            </div>

            <!-- Journal Table -->
            <div class="table-card">
                <?php if (empty($entries)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-book"></i>
                    <h3>No Journal Entries</h3>
                    <p>Journal entries are automatically created when you add income or expenses.</p>
                </div>
                <?php else: ?>
                <table class="fp-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th>L/F</th>
                            <th style="text-align:right;">Debit (Dr.)</th>
                            <th style="text-align:right;">Credit (Cr.)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $eid => $entry): ?>
                        <?php $first = true; foreach ($entry['items'] as $item): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--fp-text-muted);font-size:.85rem;">
                                <?= $first ? e(date('d M Y', strtotime($entry['entry_date']))) : '' ?>
                            </td>
                            <td>
                                <?php if ($item['debit'] > 0): ?>
                                    <strong style="color:var(--fp-text-dark);"><?= e($item['account_name']) ?> A/c</strong> ...... Dr.
                                <?php else: ?>
                                    &nbsp;&nbsp;&nbsp;&nbsp;To <em style="color:var(--fp-text-muted);"><?= e($item['account_name']) ?> A/c</em>
                                    <?php if ($first): ?>
                                        <div style="font-size:0.78rem;color:var(--fp-text-muted);margin-top:2px;">
                                            (<?= e($entry['description']) ?>)
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--fp-text-muted);font-size:.8rem;"><?= $eid ?></td>
                            <td style="text-align:right;font-weight:600;color:var(--fp-primary);">
                                <?= $item['debit'] > 0 ? format_currency($item['debit']) : '' ?>
                            </td>
                            <td style="text-align:right;font-weight:600;color:var(--fp-accent);">
                                <?= $item['credit'] > 0 ? format_currency($item['credit']) : '' ?>
                            </td>
                        </tr>
                        <?php $first = false; endforeach; ?>
                        <tr style="border-top:2px dashed var(--fp-border);">
                            <td colspan="5" style="padding:4px 0;"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--fp-bg);font-weight:700;">
                            <td colspan="3" style="text-align:right;color:var(--fp-text-dark);">TOTAL</td>
                            <td style="text-align:right;color:var(--fp-primary);"><?= format_currency($total_debit) ?></td>
                            <td style="text-align:right;color:var(--fp-accent);"><?= format_currency($total_credit) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
