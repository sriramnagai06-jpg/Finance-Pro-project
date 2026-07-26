<?php
// payment_helper.php - Shared functions for handling payments and receipts

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * Insert a payment record into the payments table.
 *
 * @param int $user_id User ID making the payment
 * @param string $type 'cash' or 'online'
 * @param float $amount Payment amount
 * @param string $status Payment status (default 'completed')
 * @return int|false Inserted payment ID or false on failure
 */
function insert_payment(int $user_id, string $type, float $amount, string $status = 'completed') {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO payments (user_id, type, amount, status) VALUES (?, ?, ?, ?)');
    if (!$stmt) return false;
    $stmt->bind_param('isds', $user_id, $type, $amount, $status);
    if ($stmt->execute()) {
        $payment_id = $stmt->insert_id;
        $stmt->close();
        return $payment_id;
    }
    $stmt->close();
    return false;
}

/**
 * Generate an HTML receipt for a payment.
 *
 * @param int $payment_id Payment record ID
 * @return string HTML receipt markup
 */
function render_receipt(int $payment_id): string {
    global $conn;
    $stmt = $conn->prepare('SELECT p.id, p.type, p.amount, p.status, p.created_at, u.full_name FROM payments p JOIN users u ON p.user_id = u.user_id WHERE p.id = ?');
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) return '<p>Receipt not found.</p>';
    $html = '<div class="receipt" style="max-width:400px;margin:auto;padding:20px;border:1px solid #ccc;border-radius:8px;background:#f9f9f9;">
        <h2 style="text-align:center;">Payment Receipt</h2>
        <p><strong>Payment ID:</strong> ' . e($row['id']) . '</p>
        <p><strong>Name:</strong> ' . e($row['full_name']) . '</p>
        <p><strong>Type:</strong> ' . ucfirst(e($row['type'])) . '</p>
        <p><strong>Amount:</strong> ' . e(CURRENCY) . ' ' . number_format($row['amount'], 2) . '</p>
        <p><strong>Status:</strong> ' . ucfirst(e($row['status'])) . '</p>
        <p><strong>Date:</strong> ' . e(date('Y-m-d H:i', strtotime($row['created_at']))) . '</p>
        <button onclick="window.print()" style="margin-top:15px;width:100%;" class="btn btn-primary">Print Receipt</button>
    </div>';
    return $html;
}
?>
