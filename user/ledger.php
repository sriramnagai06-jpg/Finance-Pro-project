<?php
/**
 * FinancePro - General Ledger
 * Account-wise grouping of all debit/credit movements with running balance.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$sel_account = (int)($_GET['account_id'] ?? 0);

// Get all user accounts that have transactions
$accounts_stmt = $conn->prepare("
    SELECT DISTINCT a.account_id, a.account_name, a.account_type
    FROM accounts a
    INNER JOIN journal_items ji ON ji.account_id = a.account_id
    INNER JOIN journal_entries je ON je.entry_id = ji.entry_id AND je.user_id = ?
    WHERE a.user_id = ?
    ORDER BY a.account_type, a.account_name
");
$accounts_stmt->bind_param("ii", $uid, $uid);
$accounts_stmt->execute();
$accounts = $accounts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$accounts_stmt->close();

// Get ledger for selected account or all accounts
$ledger_data = [];
$target_accounts = $sel_account > 0
    ? array_filter($accounts, fn($a) => $a['account_id'] == $sel_account)
    : $accounts;

foreach ($target_accounts as $acc) {
    $stmt = $conn->prepare("
        SELECT je.entry_date, je.description, je.reference_type,
               ji.debit, ji.credit
        FROM journal_items ji
        JOIN journal_entries je ON je.entry_id = ji.entry_id
        WHERE ji.account_id = ? AND je.user_id = ? AND je.entry_date BETWEEN ? AND ?
        ORDER BY je.entry_date ASC, je.entry_id ASC
    ");
    $stmt->bind_param("iiss", $acc['account_id'], $uid, $from, $to);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($items)) {
        $running = 0;
        $total_dr = 0; $total_cr = 0;
        foreach ($items as &$item) {
            $running += $item['debit'] - $item['credit'];
            $total_dr += $item['debit'];
            $total_cr += $item['credit'];
            $item['balance'] = $running;
        }
        unset($item);
        $ledger_data[] = [
            'account'    => $acc,
            'items'      => $items,
            'total_dr'   => $total_dr,
            'total_cr'   => $total_cr,
            'balance'    => $running
        ];
    }
}

$active_page = 'ledger'; $page_title = 'General Ledger';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>General Ledger - <?= e(SITE_NAME) ?></title>
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

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--fp-text-dark);margin:0;">
                        <i class="fa-solid fa-book-open" style="color:var(--fp-primary)"></i> General Ledger
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">Account-wise transaction summary with running balance</p>
                </div>
                <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            </div>

            <!-- Filter -->
            <form method="GET" style="display:flex;gap:10px;margin-bottom:24px;align-items:center;flex-wrap:wrap;">
                <select name="account_id" class="fp-select" style="width:200px;">
                    <option value="0">All Accounts</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['account_id'] ?>" <?= $sel_account == $acc['account_id'] ? 'selected':'' ?>>
                        <?= e($acc['account_name']) ?> (<?= e($acc['account_type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="from" value="<?= e($from) ?>" class="fp-input" style="width:160px;">
                <span style="color:var(--fp-text-muted);">to</span>
                <input type="date" name="to" value="<?= e($to) ?>" class="fp-input" style="width:160px;">
                <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            </form>

            <?php if (empty($ledger_data)): ?>
            <div class="table-card"><div class="empty-state">
                <i class="fa-solid fa-book-open"></i>
                <h3>No Ledger Entries</h3>
                <p>Add income or expenses to populate the ledger.</p>
            </div></div>
            <?php else: ?>
            <?php foreach ($ledger_data as $ld): ?>
            <div class="table-card" style="margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                    <div>
                        <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--fp-text-dark);">
                            <?= e($ld['account']['account_name']) ?> Account
                        </h3>
                        <span class="badge-fp" style="background:var(--fp-primary);color:#fff;font-size:.7rem;padding:2px 8px;border-radius:20px;">
                            <?= e($ld['account']['account_type']) ?>
                        </span>
                    </div>
                    <div style="font-size:.85rem;color:var(--fp-text-muted);">
                        Closing Balance:
                        <strong style="color:<?= $ld['balance']>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;">
                            <?= format_currency(abs($ld['balance'])) ?> <?= $ld['balance']>=0?'Dr.':'Cr.' ?>
                        </strong>
                    </div>
                </div>
                <table class="fp-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th>Type</th>
                            <th style="text-align:right;">Debit</th>
                            <th style="text-align:right;">Credit</th>
                            <th style="text-align:right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ld['items'] as $item): ?>
                        <tr>
                            <td style="white-space:nowrap;font-size:.85rem;color:var(--fp-text-muted);">
                                <?= e(date('d M Y', strtotime($item['entry_date']))) ?>
                            </td>
                            <td style="color:var(--fp-text-dark);"><?= e($item['description']) ?></td>
                            <td>
                                <span class="badge-fp <?= $item['reference_type']=='income'?'badge-income':'badge-expense' ?>" style="font-size:.7rem;">
                                    <?= ucfirst($item['reference_type']) ?>
                                </span>
                            </td>
                            <td style="text-align:right;color:var(--fp-primary);font-weight:600;">
                                <?= $item['debit']>0 ? format_currency($item['debit']) : '-' ?>
                            </td>
                            <td style="text-align:right;color:var(--fp-accent);font-weight:600;">
                                <?= $item['credit']>0 ? format_currency($item['credit']) : '-' ?>
                            </td>
                            <td style="text-align:right;font-weight:600;color:<?= $item['balance']>=0?'var(--fp-text-dark)':'var(--fp-danger)' ?>;">
                                <?= format_currency(abs($item['balance'])) ?> <?= $item['balance']>=0?'Dr.':'Cr.' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--fp-bg);font-weight:700;">
                            <td colspan="3" style="color:var(--fp-text-dark);">TOTAL</td>
                            <td style="text-align:right;color:var(--fp-primary);"><?= format_currency($ld['total_dr']) ?></td>
                            <td style="text-align:right;color:var(--fp-accent);"><?= format_currency($ld['total_cr']) ?></td>
                            <td style="text-align:right;color:<?= $ld['balance']>=0?'var(--fp-accent)':'var(--fp-danger)' ?>;">
                                <?= format_currency(abs($ld['balance'])) ?> <?= $ld['balance']>=0?'Dr.':'Cr.' ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
