<?php
/**
 * FinancePro - Invoice List + Create (Module 7)
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];
$errors = [];

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->prepare("DELETE FROM invoices WHERE invoice_id=? AND user_id=?")->execute() || null;
    $d=$conn->prepare("DELETE FROM invoices WHERE invoice_id=? AND user_id=?");
    $d->bind_param('ii',(int)$_GET['delete'],$uid); $d->execute(); $d->close();
    set_flash('success','Invoice deleted.'); header('Location: invoices.php'); exit;
}

// Handle Create
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')===('create')) {
    if (!verify_csrf_token($_POST['csrf_token']??null)) { $errors[]='Invalid request.'; }
    else {
        $customer_name    = clean_input($_POST['customer_name']??'');
        $customer_email   = clean_input($_POST['customer_email']??'');
        $customer_phone   = clean_input($_POST['customer_phone']??'');
        $customer_address = clean_input($_POST['customer_address']??'');
        $invoice_date     = clean_input($_POST['invoice_date']??'');
        $due_date         = clean_input($_POST['due_date']??'');
        $gst_type         = in_array($_POST['gst_type']??'',['intra_state','union_territory','inter_state'])?$_POST['gst_type']:'intra_state';
        $cgst_pct         = (float)($_POST['cgst_percent']??0);
        $sgst_pct         = (float)($_POST['sgst_percent']??0);
        $utgst_pct        = (float)($_POST['utgst_percent']??0);
        $igst_pct         = (float)($_POST['igst_percent']??0);
        $notes            = clean_input($_POST['notes']??'');
        $status           = in_array($_POST['status']??'',['paid','unpaid','partial'])?$_POST['status']:'unpaid';

        $products = $_POST['product_name']  ?? [];
        $qtys     = $_POST['quantity']       ?? [];
        $prices   = $_POST['unit_price']     ?? [];

        if (!$customer_name) $errors[]='Customer name required.';
        if (!$invoice_date)  $errors[]='Invoice date required.';
        if (empty($products)||count(array_filter($products))==0) $errors[]='Add at least one item.';

        if (empty($errors)) {
            $subtotal = 0;
            $items = [];
            foreach ($products as $i=>$prod) {
                if (!$prod) continue;
                $qty   = max(1,(int)($qtys[$i]??1));
                $price = (float)($prices[$i]??0);
                $line  = $qty * $price;
                $subtotal += $line;
                $items[] = ['name'=>$prod,'qty'=>$qty,'price'=>$price,'line'=>$line];
            }
            $cgst_amt = 0; $sgst_amt = 0; $utgst_amt = 0; $igst_amt = 0;
            if ($gst_type === 'intra_state') {
                $cgst_amt = $subtotal * $cgst_pct / 100;
                $sgst_amt = $subtotal * $sgst_pct / 100;
                $utgst_pct = 0; $igst_pct = 0;
            } elseif ($gst_type === 'union_territory') {
                $cgst_amt = $subtotal * $cgst_pct / 100;
                $utgst_amt = $subtotal * $utgst_pct / 100;
                $sgst_pct = 0; $igst_pct = 0;
            } else {
                $igst_amt = $subtotal * $igst_pct / 100;
                $cgst_pct = 0; $sgst_pct = 0; $utgst_pct = 0;
            }
            $tax_amt = $cgst_amt + $sgst_amt + $utgst_amt + $igst_amt;
            $grand     = $subtotal + $tax_amt;
            $inv_num   = generate_invoice_number($conn);

            $stmt = $conn->prepare("INSERT INTO invoices (user_id,invoice_number,customer_name,customer_email,customer_phone,customer_address,invoice_date,due_date,gst_type,cgst_percent,sgst_percent,utgst_percent,igst_percent,cgst_amount,sgst_amount,utgst_amount,igst_amount,subtotal,tax_amount,grand_total,status,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssssssssddddddddddss',$uid,$inv_num,$customer_name,$customer_email,$customer_phone,$customer_address,$invoice_date,$due_date,$gst_type,$cgst_pct,$sgst_pct,$utgst_pct,$igst_pct,$cgst_amt,$sgst_amt,$utgst_amt,$igst_amt,$subtotal,$tax_amt,$grand,$status,$notes);
            $stmt->execute();
            $inv_id = $stmt->insert_id; $stmt->close();

            foreach ($items as $item) {
                $is = $conn->prepare("INSERT INTO invoice_items (invoice_id,product_name,quantity,unit_price,line_total) VALUES (?,?,?,?,?)");
                $is->bind_param('isids',$inv_id,$item['name'],$item['qty'],$item['price'],$item['line']);
                $is->execute(); $is->close();
            }

            // Audit and Notify
            audit_log($conn, $uid, 'create', 'invoices', $inv_id, "Created invoice $inv_num");
            add_notification($conn, $uid, 'system', 'Invoice Generated', "Invoice $inv_num has been successfully generated for $customer_name.");

            set_flash('success',"Invoice $inv_num created!"); header("Location: invoice_view.php?id=$inv_id"); exit;
        }
    }
}

// Fetch invoices
$inv_list = $conn->prepare("SELECT * FROM invoices WHERE user_id=? ORDER BY invoice_date DESC");
$inv_list->bind_param('i',$uid); $inv_list->execute();
$invoices = $inv_list->get_result()->fetch_all(MYSQLI_ASSOC); $inv_list->close();

$csrf_token = generate_csrf_token();
$active_page = 'invoices'; $page_title = 'Invoices'; $page_subtitle = 'Create and manage invoices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Invoices - <?=e(SITE_NAME)?></title>
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

            <!-- Invoice List -->
            <div class="table-card" style="margin-bottom:28px;">
                <div class="table-card-header">
                    <div><div class="tc-title">All Invoices</div><div class="tc-sub"><?=count($invoices)?> invoice(s)</div></div>
                    <a href="#createForm" class="btn-fp btn-fp-primary btn-fp-sm"><i class="fa-solid fa-plus"></i> New Invoice</a>
                </div>
                <?php if(empty($invoices)): ?>
                <div class="empty-state"><i class="fa-solid fa-file-invoice"></i><h3>No invoices yet</h3><p>Create your first invoice below.</p></div>
                <?php else: ?>
                <table class="fp-table">
                    <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($invoices as $inv): ?>
                    <tr>
                        <td style="font-weight:700;"><?=e($inv['invoice_number'])?></td>
                        <td><?=e($inv['customer_name'])?></td>
                        <td style="font-size:0.82rem;"><?=format_date($inv['invoice_date'])?></td>
                        <td style="font-weight:700;"><?=format_currency($inv['grand_total'])?></td>
                        <td><span class="badge-fp badge-<?=$inv['status']?>"><?=ucfirst($inv['status'])?></span></td>
                        <td style="display:flex;gap:6px;">
                            <a href="invoice_view.php?id=<?=$inv['invoice_id']?>" class="btn-fp btn-fp-outline btn-fp-sm"><i class="fa-solid fa-eye"></i></a>
                            <a href="?delete=<?=$inv['invoice_id']?>" class="btn-fp btn-fp-danger btn-fp-sm" onclick="return confirm('Delete invoice?')"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Create Invoice Form -->
            <div class="form-card" id="createForm">
                <div class="form-section-title"><i class="fa-solid fa-file-invoice" style="color:var(--fp-primary)"></i> Create New Invoice</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                    <input type="hidden" name="action" value="create">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div><div class="fp-form-group"><label class="fp-label">Customer Name *</label><input type="text" name="customer_name" class="fp-input" required placeholder="Customer / Company Name"></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Customer Email</label><input type="email" name="customer_email" class="fp-input" placeholder="email@example.com"></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Customer Phone</label><input type="text" name="customer_phone" class="fp-input" placeholder="10-digit mobile"></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Customer Address</label><input type="text" name="customer_address" class="fp-input" placeholder="Address"></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Invoice Date *</label><input type="date" name="invoice_date" class="fp-input" value="<?=date('Y-m-d')?>" required></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Due Date</label><input type="date" name="due_date" class="fp-input"></div></div>
                        <div><div class="fp-form-group"><label class="fp-label">Status</label>
                            <select name="status" class="fp-select"><option value="unpaid">Unpaid</option><option value="paid">Paid</option><option value="partial">Partial</option></select>
                        </div></div>
                    </div>

                    <!-- Items -->
                    <div class="form-section-title" style="margin-top:4px;">Line Items</div>
                    <table class="fp-table" id="itemsTable">
                        <thead><tr><th>Product / Service</th><th>Qty</th><th>Unit Price</th><th>Line Total</th><th></th></tr></thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td><input type="text" name="product_name[]" class="fp-input" placeholder="Item description" required></td>
                                <td><input type="number" name="quantity[]" class="fp-input qty" min="1" value="1" style="width:70px;" oninput="calcLine(this)"></td>
                                <td><input type="number" name="unit_price[]" class="fp-input price" min="0" step="0.01" value="0" style="width:120px;" oninput="calcLine(this)"></td>
                                <td class="line-total" style="font-weight:600;">Rs. 0.00</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn-fp btn-fp-outline btn-fp-sm" style="margin:10px 0;" onclick="addRow()"><i class="fa-solid fa-plus"></i> Add Row</button>

                    <!-- GST Type -->
                    <div class="fp-form-group">
                        <label class="fp-label">GST Type</label>
                        <select name="gst_type" id="gst_type" class="fp-select" onchange="calcTotals()">
                            <option value="intra_state">Intra-State (CGST + SGST)</option>
                            <option value="union_territory">Union Territory (CGST + UTGST)</option>
                            <option value="inter_state">Inter-State (IGST)</option>
                        </select>
                    </div>

                    <!-- GST Rates -->
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px; margin:20px 0;">
                        <div class="fp-form-group" id="grp-cgst"><label class="fp-label">CGST %</label><input type="number" name="cgst_percent" id="cgst_pct" class="fp-input" value="0" min="0" max="50" step="0.01" oninput="calcTotals()"></div>
                        <div class="fp-form-group" id="grp-sgst"><label class="fp-label">SGST %</label><input type="number" name="sgst_percent" id="sgst_pct" class="fp-input" value="0" min="0" max="50" step="0.01" oninput="calcTotals()"></div>
                        <div class="fp-form-group" id="grp-utgst" style="display:none;"><label class="fp-label">UTGST %</label><input type="number" name="utgst_percent" id="utgst_pct" class="fp-input" value="0" min="0" max="50" step="0.01" oninput="calcTotals()"></div>
                        <div class="fp-form-group" id="grp-igst" style="display:none;"><label class="fp-label">IGST %</label><input type="number" name="igst_percent" id="igst_pct" class="fp-input" value="0" min="0" max="50" step="0.01" oninput="calcTotals()"></div>
                    </div>

                    <!-- Totals Preview -->
                    <div style="max-width:320px; margin-left:auto; margin-bottom:20px;" class="gst-result-box">
                        <div class="gst-result-row"><span class="label">Subtotal</span><span class="value" id="prev-sub">Rs. 0.00</span></div>
                        <div class="gst-result-row" id="row-cgst"><span class="label">CGST</span><span class="value" id="prev-cgst">Rs. 0.00</span></div>
                        <div class="gst-result-row" id="row-sgst"><span class="label">SGST</span><span class="value" id="prev-sgst">Rs. 0.00</span></div>
                        <div class="gst-result-row" id="row-utgst" style="display:none;"><span class="label">UTGST</span><span class="value" id="prev-utgst">Rs. 0.00</span></div>
                        <div class="gst-result-row" id="row-igst" style="display:none;"><span class="label">IGST</span><span class="value" id="prev-igst">Rs. 0.00</span></div>
                        <div class="gst-result-row total"><span class="label">Grand Total</span><span class="value" id="prev-grand" style="color:var(--fp-accent)">Rs. 0.00</span></div>
                    </div>

                    <div class="fp-form-group"><label class="fp-label">Notes</label><textarea name="notes" class="fp-textarea" placeholder="Optional notes for the customer"></textarea></div>
                    <button type="submit" class="btn-fp btn-fp-primary" style="min-width:180px;"><i class="fa-solid fa-file-invoice"></i> Create Invoice</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fmt(n){return 'Rs. '+n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
function calcLine(el){
    const row=el.closest('tr');
    const qty=parseFloat(row.querySelector('.qty').value)||0;
    const price=parseFloat(row.querySelector('.price').value)||0;
    row.querySelector('.line-total').textContent=fmt(qty*price);
    calcTotals();
}
function calcTotals(){
    let sub=0;
    document.querySelectorAll('#itemsBody tr').forEach(r=>{
        const q=parseFloat(r.querySelector('.qty').value)||0;
        const p=parseFloat(r.querySelector('.price').value)||0;
        sub+=q*p;
    });
    const type=document.getElementById('gst_type').value;
    
    // Toggle groups
    document.getElementById('grp-cgst').style.display = (type==='intra_state'||type==='union_territory')?'':'none';
    document.getElementById('grp-sgst').style.display = type==='intra_state'?'':'none';
    document.getElementById('grp-utgst').style.display = type==='union_territory'?'':'none';
    document.getElementById('grp-igst').style.display = type==='inter_state'?'':'none';
    
    document.getElementById('row-cgst').style.display = (type==='intra_state'||type==='union_territory')?'':'none';
    document.getElementById('row-sgst').style.display = type==='intra_state'?'':'none';
    document.getElementById('row-utgst').style.display = type==='union_territory'?'':'none';
    document.getElementById('row-igst').style.display = type==='inter_state'?'':'none';

    const cgst=parseFloat(document.getElementById('cgst_pct').value)||0;
    const sgst=parseFloat(document.getElementById('sgst_pct').value)||0;
    const utgst=parseFloat(document.getElementById('utgst_pct').value)||0;
    const igst=parseFloat(document.getElementById('igst_pct').value)||0;
    
    let cgst_amt=0, sgst_amt=0, utgst_amt=0, igst_amt=0;
    
    if (type==='intra_state') {
        cgst_amt = sub*cgst/100;
        sgst_amt = sub*sgst/100;
    } else if (type==='union_territory') {
        cgst_amt = sub*cgst/100;
        utgst_amt = sub*utgst/100;
    } else {
        igst_amt = sub*igst/100;
    }

    const total_tax=cgst_amt+sgst_amt+utgst_amt+igst_amt;
    document.getElementById('prev-sub').textContent=fmt(sub);
    document.getElementById('prev-cgst').textContent=fmt(cgst_amt);
    document.getElementById('prev-sgst').textContent=fmt(sgst_amt);
    document.getElementById('prev-utgst').textContent=fmt(utgst_amt);
    document.getElementById('prev-igst').textContent=fmt(igst_amt);
    document.getElementById('prev-grand').textContent=fmt(sub+total_tax);
}
function addRow(){
    const tbody=document.getElementById('itemsBody');
    const row=document.createElement('tr');
    row.innerHTML=`<td><input type="text" name="product_name[]" class="fp-input" placeholder="Item description" required></td>
        <td><input type="number" name="quantity[]" class="fp-input qty" min="1" value="1" style="width:70px;" oninput="calcLine(this)"></td>
        <td><input type="number" name="unit_price[]" class="fp-input price" min="0" step="0.01" value="0" style="width:120px;" oninput="calcLine(this)"></td>
        <td class="line-total" style="font-weight:600;">Rs. 0.00</td>
        <td><button type="button" class="btn-fp btn-fp-danger btn-fp-sm" onclick="this.closest('tr').remove();calcTotals();"><i class="fa-solid fa-xmark"></i></button></td>`;
    tbody.appendChild(row);
}
</script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
