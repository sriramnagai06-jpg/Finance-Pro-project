<?php
/**
 * FinancePro - Settings (Module 12)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];
$settings = get_user_settings($conn, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request.';
    } else {
        $currency_symbol = clean_input($_POST['currency_symbol'] ?? 'Rs.');
        $currency_pos    = clean_input($_POST['currency_position'] ?? 'prefix');
        $theme           = clean_input($_POST['theme'] ?? 'light');
        $large_exp       = (float)($_POST['large_expense_threshold'] ?? 5000);
        
        $cgst = (float)($_POST['default_cgst'] ?? 9.0);
        $sgst = (float)($_POST['default_sgst'] ?? 9.0);
        $utgst = (float)($_POST['default_utgst'] ?? 9.0);
        $igst = (float)($_POST['default_igst'] ?? 18.0);
        
        $c_name    = clean_input($_POST['company_name'] ?? '');
        $c_address = clean_input($_POST['company_address'] ?? '');
        $c_gstin   = clean_input($_POST['company_gstin'] ?? '');
        $c_phone   = clean_input($_POST['company_phone'] ?? '');
        $c_email   = clean_input($_POST['company_email'] ?? '');

        // Handle logo upload
        $logo_path = $settings['invoice_logo'];
        if (!empty($_FILES['invoice_logo']['name'])) {
            $allowed = ['image/jpeg', 'image/png'];
            if (!in_array($_FILES['invoice_logo']['type'], $allowed)) {
                $errors[] = 'Only JPG/PNG images allowed for logo.';
            } elseif ($_FILES['invoice_logo']['size'] > 1024 * 1024) {
                $errors[] = 'Logo must be under 1MB.';
            } else {
                $upload_dir = __DIR__.'/../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['invoice_logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_'.$uid.'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['invoice_logo']['tmp_name'], $upload_dir.$filename)) {
                    $logo_path = 'uploads/logos/'.$filename;
                }
            }
        }

        if (empty($errors)) {
            // Check if settings exist
            $check = $conn->query("SELECT user_id FROM user_settings WHERE user_id=$uid")->num_rows;
            
            if ($check) {
                $stmt = $conn->prepare("UPDATE user_settings SET 
                    currency_symbol=?, currency_position=?, theme=?, large_expense_threshold=?,
                    default_cgst=?, default_sgst=?, default_utgst=?, default_igst=?,
                    company_name=?, company_address=?, company_gstin=?, company_phone=?, company_email=?, invoice_logo=?
                    WHERE user_id=?");
                $stmt->bind_param('sssiddddssssssi', 
                    $currency_symbol, $currency_pos, $theme, $large_exp,
                    $cgst, $sgst, $utgst, $igst,
                    $c_name, $c_address, $c_gstin, $c_phone, $c_email, $logo_path, $uid);
            } else {
                $stmt = $conn->prepare("INSERT INTO user_settings 
                    (user_id, currency_symbol, currency_position, theme, large_expense_threshold,
                    default_cgst, default_sgst, default_utgst, default_igst, company_name, company_address, company_gstin, company_phone, company_email, invoice_logo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssiddddssssss', 
                    $uid, $currency_symbol, $currency_pos, $theme, $large_exp,
                    $cgst, $sgst, $utgst, $igst,
                    $c_name, $c_address, $c_gstin, $c_phone, $c_email, $logo_path);
            }
            
            if ($stmt->execute()) {
                audit_log($conn, $uid, 'update', 'user_settings', $uid, 'Updated user settings');
                set_flash('success', 'Settings saved successfully!');
                header('Location: settings.php'); exit;
            } else {
                $errors[] = 'Failed to save settings.';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
$active_page = 'settings';
$page_title = 'Settings';
$page_subtitle = 'Preferences, Tax, and Company Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Settings - <?=e(SITE_NAME)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/style-dashboard.css">`r`n    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
<div class="fp-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="fp-main">
        <?php include '../includes/header.php'; ?>
        <div class="fp-content">
            <?php include '../includes/alerts.php'; ?>
            <?php if(!empty($errors)): ?><div class="fp-alert fp-alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?=implode(' &bull; ',array_map('e',$errors))?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
                    <!-- General Settings -->
                    <div class="form-card">
                        <div class="form-section-title"><i class="fa-solid fa-sliders" style="color:var(--fp-primary)"></i> General Settings</div>
                        
                        <div class="fp-form-group">
                            <label class="fp-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="fp-input" value="<?=e($settings['currency_symbol'])?>" placeholder="e.g. Rs. or ₹ or $">
                        </div>
                        
                        <div class="fp-form-group">
                            <label class="fp-label">Currency Position</label>
                            <select name="currency_position" class="fp-select">
                                <option value="prefix" <?=$settings['currency_position']==='prefix'?'selected':''?>>Prefix (Rs. 500)</option>
                                <option value="suffix" <?=$settings['currency_position']==='suffix'?'selected':''?>>Suffix (500 Rs.)</option>
                            </select>
                        </div>
                        
                        <div class="fp-form-group">
                            <label class="fp-label">Theme</label>
                            <select name="theme" class="fp-select">
                                <option value="light" <?=$settings['theme']==='light'?'selected':''?>>Light Mode</option>
                                <option value="dark" <?=$settings['theme']==='dark'?'selected':''?>>Dark Mode</option>
                            </select>
                        </div>

                        <div class="fp-form-group">
                            <label class="fp-label">Large Expense Threshold</label>
                            <input type="number" name="large_expense_threshold" class="fp-input" value="<?=e($settings['large_expense_threshold'])?>" min="1" step="0.01">
                            <small style="color:var(--fp-text-muted); font-size:0.75rem;">Get notified when an expense exceeds this amount.</small>
                        </div>
                    </div>

                    <!-- Tax Settings -->
                    <div class="form-card">
                        <div class="form-section-title"><i class="fa-solid fa-percent" style="color:var(--fp-warning)"></i> Default Tax Rates (Invoices)</div>
                        
                        <div class="fp-form-group">
                            <label class="fp-label">Default CGST (%)</label>
                            <input type="number" name="default_cgst" class="fp-input" value="<?=e($settings['default_cgst'])?>" min="0" step="0.01">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Default SGST (%)</label>
                            <input type="number" name="default_sgst" class="fp-input" value="<?=e($settings['default_sgst'])?>" min="0" step="0.01">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Default UTGST (%)</label>
                            <input type="number" name="default_utgst" class="fp-input" value="<?=e($settings['default_utgst']??9)?>" min="0" step="0.01">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Default IGST (%)</label>
                            <input type="number" name="default_igst" class="fp-input" value="<?=e($settings['default_igst'])?>" min="0" step="0.01">
                        </div>
                        <div style="margin-top:14px; font-size:0.8rem; color:var(--fp-text-muted);">
                            <i class="fa-solid fa-circle-info"></i> These rates will be pre-filled when creating a new invoice.
                        </div>
                    </div>
                </div>

                <!-- Company Details -->
                <div class="form-card" style="margin-bottom:24px;">
                    <div class="form-section-title"><i class="fa-solid fa-building" style="color:var(--fp-accent)"></i> Company Details (For Invoices)</div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div class="fp-form-group">
                            <label class="fp-label">Company Name</label>
                            <input type="text" name="company_name" class="fp-input" value="<?=e($settings['company_name'])?>">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">GSTIN</label>
                            <input type="text" name="company_gstin" class="fp-input" value="<?=e($settings['company_gstin'])?>">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Company Email</label>
                            <input type="email" name="company_email" class="fp-input" value="<?=e($settings['company_email'])?>">
                        </div>
                        <div class="fp-form-group">
                            <label class="fp-label">Company Phone</label>
                            <input type="text" name="company_phone" class="fp-input" value="<?=e($settings['company_phone'])?>">
                        </div>
                        <div class="fp-form-group" style="grid-column: 1 / -1;">
                            <label class="fp-label">Company Address</label>
                            <textarea name="company_address" class="fp-textarea" rows="2"><?=e($settings['company_address'] ?? '')?></textarea>
                        </div>
                        <div class="fp-form-group" style="grid-column: 1 / -1;">
                            <label class="fp-label">Invoice Logo</label>
                            <input type="file" name="invoice_logo" class="fp-input" accept="image/jpeg, image/png">
                            <?php if($settings['invoice_logo']): ?>
                            <div style="margin-top:10px;">
                                <img src="../<?=e($settings['invoice_logo'])?>" alt="Logo" style="max-height:60px; border-radius:4px; border:1px solid var(--fp-border);">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-fp btn-fp-primary btn-fp-lg"><i class="fa-solid fa-floppy-disk"></i> Save All Settings</button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
