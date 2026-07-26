<?php
/**
 * FinancePro - Flash Alert Partial
 * Location: /FinancePro/includes/alerts.php
 * Usage: include after config.php + functions.php, near top of <body>
 */
$flash = get_flash();
if ($flash):
    $iconMap = [
        'success' => 'fa-circle-check',
        'danger'  => 'fa-circle-exclamation',
        'warning' => 'fa-triangle-exclamation',
        'info'    => 'fa-circle-info',
    ];
    $icon = $iconMap[$flash['type']] ?? 'fa-circle-info';
?>
<div class="alert alert-<?= e($flash['type']) ?> alert-fp d-flex align-items-center gap-2" data-autohide role="alert">
    <i class="fa-solid <?= $icon ?>"></i>
    <span><?= e($flash['message']) ?></span>
</div>
<?php endif; ?>
