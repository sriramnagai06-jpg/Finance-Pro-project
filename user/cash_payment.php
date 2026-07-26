<?php
/**
 * FinancePro - Legacy Cash Payment Redirect
 */
require_once '../config.php';
header('Location: ' . BASE_URL . 'user/cash_payments.php');
exit;
