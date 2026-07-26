<?php
/**
 * FinancePro - CSV Export (Phase 7 Bonus)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();

$uid = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'income';

if ($type !== 'income' && $type !== 'expenses' && $type !== 'gst') {
    die("Invalid export type.");
}

$period = $_GET['period'] ?? 'monthly';
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

if ($type === 'gst') {
    if ($period === 'monthly') {
        $query = "SELECT invoice_number, invoice_date, customer_name, subtotal, cgst_amount, sgst_amount, utgst_amount, igst_amount, tax_amount, grand_total, status FROM invoices WHERE user_id = ? AND MONTH(invoice_date)=? AND YEAR(invoice_date)=? ORDER BY invoice_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iii', $uid, $month, $year);
    } else {
        $query = "SELECT invoice_number, invoice_date, customer_name, subtotal, cgst_amount, sgst_amount, utgst_amount, igst_amount, tax_amount, grand_total, status FROM invoices WHERE user_id = ? AND YEAR(invoice_date)=? ORDER BY invoice_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $uid, $year);
    }
} else {
    $date_column = $type === 'income' ? 'income_date' : 'expense_date';
    $query = "
        SELECT t.{$date_column} as date, c.category_name as category, t.amount, t.description 
        FROM {$type} t 
        JOIN categories c ON t.category_id = c.category_id 
        WHERE t.user_id = ? 
        ORDER BY t.{$date_column} DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $uid);
}

$stmt->execute();
$result = $stmt->get_result();

$filename = "FinancePro_{$type}_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

if ($type === 'gst') {
    fputcsv($output, ['Invoice Number', 'Date', 'Customer Name', 'Subtotal (Rs)', 'CGST (Rs)', 'SGST (Rs)', 'UTGST (Rs)', 'IGST (Rs)', 'Total Tax (Rs)', 'Grand Total (Rs)', 'Status']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['invoice_number'],
            $row['invoice_date'],
            $row['customer_name'],
            $row['subtotal'],
            $row['cgst_amount'],
            $row['sgst_amount'],
            $row['utgst_amount'],
            $row['igst_amount'],
            $row['tax_amount'],
            $row['grand_total'],
            $row['status']
        ]);
    }
} else {
    fputcsv($output, ['Date', 'Category', 'Amount (Rs)', 'Description']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['date'],
            $row['category'],
            $row['amount'],
            $row['description']
        ]);
    }
}

fclose($output);
$stmt->close();
exit;
