<?php
/**
 * FinancePro - Legacy Online Payment Redirect
 */
require_once '../config.php';
header('Location: ' . BASE_URL . 'user/online_payments.php');
exit;
