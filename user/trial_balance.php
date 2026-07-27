<?php
/**
 * FinancePro - Trial Balance
 * Dynamic trial balance: total debits = total credits across all accounts.
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$as_of = $_GET['as_of'] ?? date('Y-m-d');

// Aggregate debit/credit per account
$stmt = $conn->prepare("
    SELECT a.account_id, a.account_name, a.account_type,
           COALESCE(SUM(ji.debit),0) AS total_debit,
           COALESCE(SUM(ji.credit),0) AS total_credit
    FROM accounts a
    LEFT JOIN journal_items ji ON ji.account_id = a.account_id
    LEFT JOIN journal_entries je ON je.entry_id = ji.entry_id AND je.user_id = ? AND je.entry_date <= ?
    WHERE a.user_id = ?
    GROUP BY a.account_id
    HAVING total_debit > 0 OR total_credit > 0
    ORDER BY FIELD(a.account_type,'Asset','Liability','Equity','Revenue','Expense'), a.account_name
");
$stmt->bind_param("isi", $uid, $as_of, $uid);
$stmt->execute();
$tb_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grand_debit  = array_sum(array_column($tb_rows, 'total_debit'));
$grand_credit = array_sum(array_column($tb_rows, 'total_credit'));
$balanced     = abs($grand_debit - $grand_credit) < 0.01;

$active_page = 'trial_balance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Trial Balance - <?= e(SITE_NAME) ?></title>
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
                        <i class="fa-solid fa-scale-balanced" style="color:var(--fp-primary)"></i> Trial Balance
                    </h1>
                    <p style="color:var(--fp-text-muted);margin:4px 0 0;">As of: <strong style="color:var(--fp-text-dark);"><?= date('d M Y', strtotime($as_of)) ?></strong></p>
                </div>
                <div style="display:flex;gap:8px;">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <input type="date" name="as_of" value="<?= e($as_of) ?>" class="fp-input" style="width:160px;">
                        <button type="submit" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-filter"></i> Go</button>
                    </form>
                    <button onclick="window.print()" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <!-- Balance Status -->
            <div class="fp-alert <?= $balanced ? 'fp-alert-success' : 'fp-alert-danger' ?>" style="margin-bottom:20px;">
                <i class="fa-solid <?= $balanced ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $balanced ? 'Trial Balance is <strong>BALANCED</strong> — Total Debits equal Total Credits.' : 'Trial Balance is <strong>UNBALANCED</strong> — Discrepancy: ' . format_currency(abs($grand_debit - $grand_credit)) ?>
            </div>

            <div class="table-card">
                <?php if (empty($tb_rows)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <h3>No Data</h3>
                    <p>Add income or expenses to generate a trial balance.</p>
                </div>
                <?php else: ?>
                <table class="fp-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th style="text-align:right;">Debit (Dr.)</th>
                            <th style="text-align:right;">Credit (Cr.)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $current_type = '';
                    foreach ($tb_rows as $row):
                        if ($row['account_type'] !== $current_type):
                            $current_type = $row['account_type'];
                    ?>
                        <tr>
                            <td colspan="4" style="background:var(--fp-bg);font-weight:700;font-size:.8rem;letter-spacing:1px;color:var(--fp-primary);padding:10px 16px;">
                                <?= strtoupper($current_type) ?> ACCOUNTS
                            </td>
                        </tr>
                    <?php endif; ?>
                        <tr>
                            <td style="color:var(--fp-text-dark);padding-left:28px;"><?= e($row['account_name']) ?></td>
                            <td><span class="badge-fp" style="background:var(--fp-primary);color:#fff;font-size:.68rem;padding:2px 7px;border-radius:20px;"><?= e($row['account_type']) ?></span></td>
                            <td style="text-align:right;color:var(--fp-primary);font-weight:600;">
                                <?= $row['total_debit']>0 ? format_currency($row['total_debit']) : '-' ?>
                            </td>
                            <td style="text-align:right;color:var(--fp-accent);font-weight:600;">
                                <?= $row['total_credit']>0 ? format_currency($row['total_credit']) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--fp-primary);color:#fff;font-weight:700;font-size:1rem;">
                            <td colspan="2" style="color:#fff;">GRAND TOTAL</td>
                            <td style="text-align:right;color:#fff;"><?= format_currency($grand_debit) ?></td>
                            <td style="text-align:right;color:#fff;"><?= format_currency($grand_credit) ?></td>
                        </tr>
                        <?php if (!$balanced): ?>
                        <tr style="background:var(--fp-danger);color:#fff;">
                            <td colspan="2" style="color:#fff;">DIFFERENCE</td>
                            <td colspan="2" style="text-align:right;color:#fff;"><?= format_currency(abs($grand_debit - $grand_credit)) ?></td>
                        </tr>
                        <?php endif; ?>
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
