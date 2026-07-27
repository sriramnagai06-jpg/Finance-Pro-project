<?php
/**
 * FinancePro - GST Calculator (Module 8) — built before invoices as invoices depend on it
 */
require_once '../config.php';
require_once '../includes/functions.php';
require_login();
$active_page = 'gst'; $page_title = 'GST Calculator'; $page_subtitle = 'Calculate CGST, SGST / UTGST & IGST instantly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>GST Calculator - <?=e(SITE_NAME)?></title>
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
            <div style="max-width:760px; margin:0 auto;">
                <!-- Mode Toggle -->
                <div style="display:flex; gap:10px; margin-bottom:24px;">
                    <button class="btn-fp btn-fp-primary" id="btn-exclusive" onclick="setMode('exclusive')">Exclusive (Add GST)</button>
                    <button class="btn-fp btn-fp-outline" id="btn-inclusive" onclick="setMode('inclusive')">Inclusive (Extract GST)</button>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">
                    <!-- Input Form -->
                    <div class="form-card">
                        <div class="form-section-title"><i class="fa-solid fa-calculator" style="color:var(--fp-primary)"></i> Enter Details</div>

                        <div class="fp-form-group">
                            <label class="fp-label">Amount (Rs.)</label>
                            <input type="number" id="amount" class="fp-input" min="0" step="0.01" placeholder="0.00" oninput="calculate()">
                        </div>

                        <div style="background:var(--fp-bg); border-radius:10px; padding:16px; margin-bottom:16px;">
                            <p style="font-size:0.78rem; font-weight:700; color:var(--fp-text-muted); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">GST Type</p>
                            <div style="display:flex; gap:10px; margin-bottom:12px;">
                                <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer;">
                                    <input type="radio" name="gst_type" value="intra_state" checked onchange="calculate()"> Intra-State (CGST + SGST)
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer;">
                                    <input type="radio" name="gst_type" value="union_territory" onchange="calculate()"> Union Territory (CGST + UTGST)
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer;">
                                    <input type="radio" name="gst_type" value="inter_state" onchange="calculate()"> Inter-State (IGST)
                                </label>
                            </div>
                            <div id="cgst-sgst-fields">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div class="fp-form-group" style="margin:0">
                                        <label class="fp-label">CGST %</label>
                                        <input type="number" id="cgst" class="fp-input" value="9" min="0" max="50" step="0.01" oninput="calculate()">
                                    </div>
                                    <div class="fp-form-group" style="margin:0" id="sgst-group">
                                        <label class="fp-label">SGST %</label>
                                        <input type="number" id="sgst" class="fp-input" value="9" min="0" max="50" step="0.01" oninput="calculate()">
                                    </div>
                                    <div class="fp-form-group" style="margin:0; display:none;" id="utgst-group">
                                        <label class="fp-label">UTGST %</label>
                                        <input type="number" id="utgst" class="fp-input" value="9" min="0" max="50" step="0.01" oninput="calculate()">
                                    </div>
                                </div>
                            </div>
                            <div id="igst-fields" style="display:none;">
                                <div class="fp-form-group" style="margin:0">
                                    <label class="fp-label">IGST %</label>
                                    <input type="number" id="igst" class="fp-input" value="18" min="0" max="50" step="0.01" oninput="calculate()">
                                </div>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div style="margin-bottom:16px;">
                            <p style="font-size:0.78rem; font-weight:700; color:var(--fp-text-muted); margin-bottom:8px;">Quick Presets</p>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <?php foreach([5,12,18,28] as $rate): ?>
                                <button class="btn-fp btn-fp-outline btn-fp-sm" onclick="setPreset(<?=$rate?>)"><?=$rate?>%</button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button class="btn-fp btn-fp-outline btn-fp-sm" onclick="resetCalc()" style="width:100%"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                    </div>

                    <!-- Result Box -->
                    <div>
                        <div class="gst-result-box" id="result-box">
                            <h3 style="font-size:1rem; font-weight:700; margin-bottom:20px; opacity:0.8;">Tax Breakdown</h3>
                            <div class="gst-result-row"><span class="label">Base Amount</span><span class="value" id="r-base">Rs. 0.00</span></div>
                            <div class="gst-result-row" id="r-cgst-row"><span class="label" id="r-cgst-label">CGST (9%)</span><span class="value" id="r-cgst">Rs. 0.00</span></div>
                            <div class="gst-result-row" id="r-sgst-row"><span class="label" id="r-sgst-label">SGST (9%)</span><span class="value" id="r-sgst">Rs. 0.00</span></div>
                            <div class="gst-result-row" id="r-utgst-row" style="display:none"><span class="label" id="r-utgst-label">UTGST (9%)</span><span class="value" id="r-utgst">Rs. 0.00</span></div>
                            <div class="gst-result-row" id="r-igst-row" style="display:none"><span class="label" id="r-igst-label">IGST (18%)</span><span class="value" id="r-igst">Rs. 0.00</span></div>
                            <div class="gst-result-row"><span class="label">Total Tax</span><span class="value" id="r-tax" style="color:var(--fp-warning)">Rs. 0.00</span></div>
                            <div class="gst-result-row total"><span class="label">Grand Total</span><span class="value" id="r-total" style="color:var(--fp-accent)">Rs. 0.00</span></div>
                        </div>

                        <!-- Common GST Rates Reference -->
                        <div class="form-card" style="margin-top:20px;">
                            <div class="form-section-title"><i class="fa-solid fa-circle-info" style="color:var(--fp-primary)"></i> Common GST Slabs</div>
                            <table class="fp-table" style="font-size:0.8rem;">
                                <thead><tr><th>Rate</th><th>Items</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>0%</strong></td><td>Fresh vegetables, milk, eggs, education</td></tr>
                                    <tr><td><strong>5%</strong></td><td>Sugar, tea, edible oil, medicine</td></tr>
                                    <tr><td><strong>12%</strong></td><td>Butter, cheese, mobile phones, computers</td></tr>
                                    <tr><td><strong>18%</strong></td><td>Hair dryers, AC, TV, restaurants</td></tr>
                                    <tr><td><strong>28%</strong></td><td>Luxury cars, tobacco, cement, aerated drinks</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let mode = 'exclusive'; // exclusive = add GST on top; inclusive = extract from amount

function setMode(m) {
    mode = m;
    document.getElementById('btn-exclusive').className = m==='exclusive' ? 'btn-fp btn-fp-primary' : 'btn-fp btn-fp-outline';
    document.getElementById('btn-inclusive').className = m==='inclusive' ? 'btn-fp btn-fp-primary' : 'btn-fp btn-fp-outline';
    calculate();
}

function setPreset(r) {
    const type = document.querySelector('input[name="gst_type"]:checked').value;
    if (type === 'intra_state') {
        document.getElementById('cgst').value = r/2;
        document.getElementById('sgst').value = r/2;
    } else if (type === 'union_territory') {
        document.getElementById('cgst').value = r/2;
        document.getElementById('utgst').value = r/2;
    } else {
        document.getElementById('igst').value = r;
    }
    calculate();
}

function resetCalc() {
    document.getElementById('amount').value = '';
    document.getElementById('cgst').value = 9;
    document.getElementById('sgst').value = 9;
    document.getElementById('utgst').value = 9;
    document.getElementById('igst').value = 18;
    calculate();
}

function fmt(n) { return 'Rs. ' + n.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function calculate() {
    const amt = parseFloat(document.getElementById('amount').value) || 0;
    const gst_type = document.querySelector('input[name="gst_type"]:checked').value;
    const cgst_r = parseFloat(document.getElementById('cgst').value) || 0;
    const sgst_r = parseFloat(document.getElementById('sgst').value) || 0;
    const utgst_r = parseFloat(document.getElementById('utgst').value) || 0;
    const igst_r = parseFloat(document.getElementById('igst').value) || 0;

    // Show/hide fields
    document.getElementById('cgst-sgst-fields').style.display = (gst_type === 'intra_state' || gst_type === 'union_territory') ? '' : 'none';
    document.getElementById('sgst-group').style.display = gst_type === 'intra_state' ? '' : 'none';
    document.getElementById('utgst-group').style.display = gst_type === 'union_territory' ? '' : 'none';
    document.getElementById('igst-fields').style.display = gst_type === 'inter_state' ? '' : 'none';

    document.getElementById('r-cgst-row').style.display = (gst_type === 'intra_state' || gst_type === 'union_territory') ? '' : 'none';
    document.getElementById('r-sgst-row').style.display = gst_type === 'intra_state' ? '' : 'none';
    document.getElementById('r-utgst-row').style.display = gst_type === 'union_territory' ? '' : 'none';
    document.getElementById('r-igst-row').style.display = gst_type === 'inter_state' ? '' : 'none';

    let base, cgst_amt = 0, sgst_amt = 0, utgst_amt = 0, igst_amt = 0, total_tax = 0, grand = 0;
    
    let total_rate = 0;
    if (gst_type === 'intra_state') total_rate = cgst_r + sgst_r;
    else if (gst_type === 'union_territory') total_rate = cgst_r + utgst_r;
    else if (gst_type === 'inter_state') total_rate = igst_r;

    if (mode === 'exclusive') {
        base = amt;
        if (gst_type === 'intra_state') {
            cgst_amt = base * cgst_r / 100;
            sgst_amt = base * sgst_r / 100;
        } else if (gst_type === 'union_territory') {
            cgst_amt = base * cgst_r / 100;
            utgst_amt = base * utgst_r / 100;
        } else {
            igst_amt = base * igst_r / 100;
        }
        total_tax = cgst_amt + sgst_amt + utgst_amt + igst_amt;
        grand = base + total_tax;
    } else {
        grand = amt;
        base = grand / (1 + total_rate/100);
        total_tax = grand - base;
        if (gst_type === 'intra_state') {
            cgst_amt = base * cgst_r / 100;
            sgst_amt = base * sgst_r / 100;
        } else if (gst_type === 'union_territory') {
            cgst_amt = base * cgst_r / 100;
            utgst_amt = base * utgst_r / 100;
        } else {
            igst_amt = total_tax;
        }
    }

    document.getElementById('r-base').textContent = fmt(base);
    document.getElementById('r-cgst').textContent = fmt(cgst_amt);
    document.getElementById('r-sgst').textContent = fmt(sgst_amt);
    document.getElementById('r-utgst').textContent = fmt(utgst_amt);
    document.getElementById('r-igst').textContent = fmt(igst_amt);
    document.getElementById('r-tax').textContent  = fmt(total_tax);
    document.getElementById('r-total').textContent = fmt(grand);

    document.getElementById('r-cgst-label').textContent = `CGST (${cgst_r}%)`;
    document.getElementById('r-sgst-label').textContent = `SGST (${sgst_r}%)`;
    document.getElementById('r-utgst-label').textContent = `UTGST (${utgst_r}%)`;
    document.getElementById('r-igst-label').textContent = `IGST (${igst_r}%)`;
}

document.querySelectorAll('input[name="gst_type"]').forEach(r => r.addEventListener('change', () => {
    calculate();
}));
</script>
<script src="../assets/js/app.js"></script>`r`n</body>
</html>
