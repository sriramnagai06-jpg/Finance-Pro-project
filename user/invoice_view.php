<?php
/**
 * FinancePro - Invoice View / Print
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

$inv_id = (int)($_GET['id'] ?? 0);
if (!$inv_id) { header('Location: invoices.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM invoices WHERE invoice_id=? AND user_id=?");
$stmt->bind_param('ii', $inv_id, $uid); $stmt->execute();
$inv = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$inv) { set_flash('danger','Invoice not found.'); header('Location: invoices.php'); exit; }

$items_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
$items_stmt->bind_param('i', $inv_id); $items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $items_stmt->close();

$user_stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE user_id=?");
$user_stmt->bind_param('i', $uid); $user_stmt->execute();
$user_info = $user_stmt->get_result()->fetch_assoc(); $user_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?=e($inv['invoice_number'])?> - FinancePro</title>
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
        <header class="fp-topbar no-print">
            <div class="topbar-title">
                <button class="sidebar-toggle-btn d-lg-none" onclick="toggleSidebar(event)" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-title-text">
                    <h1><?=e($inv['invoice_number'])?></h1>
                    <p>Invoice Details</p>
                </div>
            </div>
            <div class="topbar-actions">
                <a href="invoices.php" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <button class="btn-fp btn-fp-primary btn-fp-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / PDF</button>
            </div>
        </header>
        <div class="fp-content">
            <div class="invoice-print">
                <!-- Header -->
                <div class="invoice-header">
                    <div>
                        <div style="font-size:1.8rem; font-weight:800; color:var(--fp-primary);"><i class="fa-solid fa-chart-pie"></i> FinancePro</div>
                        <div style="font-size:0.85rem; color:var(--fp-text-muted); margin-top:4px;"><?=e($user_info['full_name'])?></div>
                        <div style="font-size:0.82rem; color:var(--fp-text-muted);"><?=e($user_info['email'])?> | <?=e($user_info['phone']??'')?></div>
                    </div>
                    <div class="invoice-meta">
                        <strong><?=e($inv['invoice_number'])?></strong>
                        <div>Date: <?=format_date($inv['invoice_date'])?></div>
                        <?php if($inv['due_date']): ?><div>Due: <?=format_date($inv['due_date'])?></div><?php endif; ?>
                        <div style="margin-top:6px;"><span class="badge-fp badge-<?=$inv['status']?>"><?=ucfirst($inv['status'])?></span></div>
                    </div>
                </div>

                <!-- Parties -->
                <div class="invoice-parties">
                    <div>
                        <div class="invoice-party-label">Bill To</div>
                        <div class="invoice-party-name"><?=e($inv['customer_name'])?></div>
                        <?php if($inv['customer_email']): ?><div style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($inv['customer_email'])?></div><?php endif; ?>
                        <?php if($inv['customer_phone']): ?><div style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($inv['customer_phone'])?></div><?php endif; ?>
                        <?php if($inv['customer_address']): ?><div style="font-size:0.82rem;color:var(--fp-text-muted);"><?=e($inv['customer_address'])?></div><?php endif; ?>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="invoice-items-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Product / Service</th><th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Unit Price</th><th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $i=>$item): ?>
                        <tr>
                            <td style="color:var(--fp-text-muted);"><?=$i+1?></td>
                            <td><?=e($item['product_name'])?></td>
                            <td style="text-align:center;"><?=$item['quantity']?></td>
                            <td style="text-align:right;"><?=format_currency($item['unit_price'])?></td>
                            <td style="text-align:right; font-weight:600;"><?=format_currency($item['line_total'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="invoice-totals">
                    <div class="invoice-total-row"><span>Subtotal</span><span><?=format_currency($inv['subtotal'])?></span></div>
                    <?php if($inv['cgst_percent']>0): ?><div class="invoice-total-row"><span>CGST (<?=$inv['cgst_percent']?>%)</span><span><?=format_currency($inv['cgst_amount'])?></span></div><?php endif; ?>
                    <?php if($inv['sgst_percent']>0): ?><div class="invoice-total-row"><span>SGST (<?=$inv['sgst_percent']?>%)</span><span><?=format_currency($inv['sgst_amount'])?></span></div><?php endif; ?>
                    <?php if($inv['utgst_percent']>0): ?><div class="invoice-total-row"><span>UTGST (<?=$inv['utgst_percent']?>%)</span><span><?=format_currency($inv['utgst_amount'])?></span></div><?php endif; ?>
                    <?php if($inv['igst_percent']>0): ?><div class="invoice-total-row"><span>IGST (<?=$inv['igst_percent']?>%)</span><span><?=format_currency($inv['igst_amount'])?></span></div><?php endif; ?>
                    <?php if($inv['tax_amount']>0): ?><div class="invoice-total-row"><span>Total Tax</span><span><?=format_currency($inv['tax_amount'])?></span></div><?php endif; ?>
                    <div class="invoice-total-row grand"><span>Grand Total</span><span style="color:var(--fp-primary);"><?=format_currency($inv['grand_total'])?></span></div>
                </div>

                <?php if($inv['notes']): ?>
                <div style="margin-top:28px; padding-top:16px; border-top:1px solid var(--fp-border); font-size:0.85rem; color:var(--fp-text-muted);">
                    <strong>Notes:</strong> <?=e($inv['notes'])?>
                </div>
                <?php endif; ?>

                <div style="margin-top:36px; text-align:center; font-size:0.78rem; color:var(--fp-text-muted);">
                    Thank you for your business! — Generated by FinancePro
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
