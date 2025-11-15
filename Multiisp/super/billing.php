<?php
session_start();
// load db config
$configPath = __DIR__ . '/config/db.php'; if (!file_exists($configPath)) $configPath = __DIR__ . '/../config/db.php'; if (file_exists($configPath)) require_once $configPath;
$pdo = $pdo ?? null; $dbErr = null;
if (empty($pdo) || !($pdo instanceof PDO)) {
    if (!empty($dbCfg) && is_array($dbCfg)) {
        try { $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbCfg['host'] ?? '127.0.0.1', $dbCfg['dbname'] ?? 'isp_portal');
            $pdo = new PDO($dsn, $dbCfg['user'] ?? 'root', $dbCfg['pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        } catch (Throwable $e) { $pdo = null; $dbErr = $e->getMessage(); }
    }
}
if ($pdo === null) {
  try { $pdo = new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } catch (Throwable $e) { $pdo = null; $dbErr = $e->getMessage(); }
}
// admin check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') { header('Location: /index.php'); exit; }

$invoices = [];
$subscribers = [];
$plans = [];
if ($pdo) {
    try {
  // include subscriber contact details for richer invoice previews
  $stmt = $pdo->query("SELECT inv.*, s.name AS subscriber_name, s.email AS subscriber_email, s.mobile AS subscriber_mobile, s.address AS subscriber_address, s.subscriber_id AS subscriber_code, p.name AS plan_name, p.price AS plan_price FROM invoices inv LEFT JOIN subscriber s ON inv.subscriber_id = s.id LEFT JOIN plans p ON inv.plan_id = p.id ORDER BY inv.created_at DESC");
        $invoices = $stmt->fetchAll();
        $sstmt = $pdo->query("SELECT id,name FROM subscriber ORDER BY name ASC"); $subscribers = $sstmt->fetchAll();
  $pstmt = $pdo->query("SELECT id,name,price FROM plans ORDER BY name ASC"); $plans = $pstmt->fetchAll();
    } catch (Throwable $e) { $dbErr = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Billing — SilverWave</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{font-family:Inter,system-ui,Segoe UI,Arial;color:#0f172a;background:#f3f6f9}
    .container{max-width:1200px;margin:28px auto;padding:20px}
    /* Invoice summary tweaks */
    .invoice-summary dt{font-weight:600;color:#333}
    .invoice-summary dd{margin-left:0}
    .invoice-summary dd span{display:inline-block;min-width:120px}
    .modal-xl .modal-content{border-radius:8px}
    /* make left card controls compact */
    .form-control.bg-light{background:#fbfbfb}
  </style>
</head>
<body>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Billing / Invoices</h3>
    <div>
      <button class="btn btn-outline-secondary me-2" onclick="location.href='dashboard_admin.php'">Back to Dashboard</button>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">Create Invoice</button>
    </div>
  </div>
  <?php if (!empty($dbErr)): ?><div class="alert alert-warning">DB error: <?= htmlspecialchars($dbErr) ?></div><?php endif; ?>
  <div class="d-flex mb-3 gap-2">
    <input id="invoiceSearch" class="form-control form-control-sm" placeholder="Search invoice, subscriber or plan..." />
    <select id="statusFilter" class="form-select form-select-sm" style="width:160px">
      <option value="">All status</option>
      <option value="pending">Pending</option>
      <option value="paid">Paid</option>
    </select>
    <button id="clearFilters" class="btn btn-sm btn-outline-secondary">Clear</button>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th>#</th><th>Invoice #</th><th>Subscriber</th><th>Plan</th><th>Amount</th><th>Due</th><th>Status</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($invoices as $i => $inv): ?>
          <?php
            $invId = (int)($inv['id'] ?? 0);
            $invNum = htmlspecialchars($inv['invoice_number'] ?? '');
            $subName = htmlspecialchars($inv['subscriber_name'] ?? '—');
            $planName = htmlspecialchars($inv['plan_name'] ?? '—');
            $status = htmlspecialchars(strtolower($inv['status'] ?? 'pending'));
            $amount = number_format($inv['amount'] ?? 0,2);
          ?>
          <tr data-invoice="<?= $invNum ?>" data-subscriber="<?= $subName ?>" data-plan="<?= $planName ?>" data-status="<?= $status ?>" data-created="<?= htmlspecialchars($inv['created_at'] ?? '') ?>">
            <td><?= $i+1 ?></td>
            <td><?= $invNum ?></td>
            <td><?= $subName ?></td>
            <td><?= $planName ?></td>
            <td>₹<?= $amount ?></td>
            <td><?= htmlspecialchars($inv['due_date'] ?? '') ?></td>
            <td><?= htmlspecialchars(ucfirst($status)) ?></td>
            <td><?= htmlspecialchars($inv['created_at'] ?? '') ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-secondary me-1" onclick="downloadInvoice(<?= $invId ?>)">Download</button>
              <?php if (($inv['status'] ?? '') !== 'paid'): ?>
                <button class="btn btn-sm btn-success me-1" onclick="markPaid(<?= $invId ?>)">Mark Paid</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $invId ?>)">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Create Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="addInvoiceForm">
            <div class="row">
              <div class="col-md-7">
                <div class="card border-0 bg-white shadow-sm mb-3 h-100">
                  <div class="card-body d-flex flex-column">
                    <div class="mb-3">
                      <label class="form-label small text-muted">Subscriber</label>
                      <div class="form-floating">
                        <select id="subscriberSelect" name="subscriber_id" class="form-select" required>
                          <option value="">-- select --</option>
                          <?php foreach ($subscribers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <label for="subscriberSelect">Subscriber</label>
                      </div>
                      <div class="mt-1">
                        <span id="subscriberStatusBadge" class="badge bg-secondary">Status: —</span>
                        <small id="rechargeNotice" class="text-muted ms-2"></small>
                      </div>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label small text-muted">Plan (optional)</label>
                      <div class="input-group">
                        <select name="plan_id" id="planSelect" class="form-select">
                          <option value="" data-price="">-- none --</option>
                          <?php foreach ($plans as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-price="<?= htmlspecialchars($p['price'] ?? 0) ?>"><?= htmlspecialchars($p['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <span class="input-group-text">₹</span>
                      </div>
                    </div>

                    <div class="row g-2">
                      <div class="col-md-4">
                        <div class="form-floating">
                          <select id="billingDays" name="billing_days" class="form-select">
                            <option value="30" selected>30</option>
                            <option value="60">60</option>
                            <option value="90">90</option>
                            <option value="120">120</option>
                            <option value="180">180</option>
                            <option value="360">360</option>
                          </select>
                          <label for="billingDays">Days</label>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-floating">
                          <input id="startDate" name="start_date" type="date" class="form-control">
                          <label for="startDate">From</label>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-floating">
                          <input id="dueDate" name="due_date" type="date" class="form-control" readonly>
                          <label for="dueDate">Due</label>
                        </div>
                      </div>
                    </div>

                    <div class="row g-2 align-items-end mt-3">
                      <div class="col-md-6">
                        <label class="form-label small text-muted">Subtotal (₹)</label>
                        <div class="form-control bg-light text-end" id="subtotalDisplay">0.00</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label small text-muted">GST (18%)</label>
                        <div class="form-control bg-light text-end" id="gstDisplay">0.00</div>
                      </div>
                    </div>

                    <div class="mt-3">
                      <label class="form-label small text-muted">Total (₹)</label>
                      <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input id="amountInput" name="amount" class="form-control form-control-lg fw-bold text-end" required>
                      </div>
                    </div>

                    <input type="hidden" id="subtotalInput" name="subtotal">
                    <input type="hidden" id="gstInput" name="gst">

                    <div class="mb-3 mt-3"><label class="form-label small text-muted">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="card shadow-sm h-100">
                  <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                      <div>
                        <h5 class="mb-0" style="letter-spacing:0.5px">Invoice summary</h5>
                        <small class="text-muted">Preview of the invoice that will be created</small>
                      </div>
                      <div class="text-end">
                        <strong id="summaryTotalTop" style="font-size:1.05rem;color:#d35400">₹<span id="summaryTotalTopVal">0.00</span></strong>
                      </div>
                    </div>
                    <dl class="row invoice-summary" style="margin:0">
                      <dt class="col-6">Invoice #</dt><dd class="col-6 text-end"><span id="summaryInvoice">—</span></dd>
                      <dt class="col-6">Subscriber</dt><dd class="col-6 text-end"><span id="summarySubscriber">—</span></dd>
                      <dt class="col-6">Plan</dt><dd class="col-6 text-end"><span id="summaryPlan">—</span></dd>
                      <dt class="col-6">Per-day</dt><dd class="col-6 text-end">₹<span id="summaryPerDay">0.00</span></dd>
                      <dt class="col-6">Period</dt><dd class="col-6 text-end"><span id="summaryPeriod">30 days</span></dd>
                      <dt class="col-6">From</dt><dd class="col-6 text-end"><span id="summaryFrom">—</span></dd>
                      <dt class="col-6">Due</dt><dd class="col-6 text-end"><span id="summaryDue">—</span></dd>
                      <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">₹<span id="summarySubtotal">0.00</span></dd>
                      <dt class="col-6">GST (18%)</dt><dd class="col-6 text-end">₹<span id="summaryGst">0.00</span></dd>
                      <dt class="col-6" style="font-weight:700;font-size:1rem">Total</dt><dd class="col-6 text-end h5" style="font-weight:700;font-size:1.15rem;color:#111">₹<span id="summaryTotal">0.00</span></dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="createInvoiceBtn" class="btn btn-primary">Create Invoice</button>
        </div>
      </div>
    </div>
  </div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Delete this invoice?<input type="hidden" id="deleteInvoiceId"></div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button id="confirmDeleteInvoiceBtn" class="btn btn-danger">Delete</button></div>
    </div>
  </div>
</div>

    <!-- Invoice Preview Modal -->
    <div class="modal fade" id="invoicePreviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Invoice Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="invoicePreviewBody" style="background:#fff">
            <!-- preview HTML will be injected here -->
          </div>
          <div class="modal-footer d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center" style="gap:8px">
              <label class="mb-0 small text-muted">Align:</label>
              <select id="pdfAlign" class="form-select form-select-sm" style="width:110px">
                <option value="center">Center</option>
                <option value="left">Left</option>
                <option value="right">Right</option>
              </select>
              <label class="mb-0 small text-muted ms-2">Top margin (px):</label>
              <input id="pdfTopMargin" type="number" class="form-control form-control-sm" value="20" style="width:80px" />
            </div>
            <div>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="button" id="downloadPreviewBtn" class="btn btn-primary">Download PDF</button>
            </div>
          </div>
        </div>
      </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- html2canvas and jsPDF for client-side PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// embed invoices data for PDF generation
var invoicesData = <?= json_encode($invoices ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

// company header info (used in preview/PDF)
const COMPANY_NAME = 'Udhayam Cable Network - Singadivakkam';
const COMPANY_MOBILE = '9444981440';
const COMPANY_ADDRESS = 'No:68, Mettu Street, Singadivakkam village & post, Kancheepuram, Kancheepuram, Tamil Nadu - 631561';
const COMPANY_GST = 'xxxx-xxxx-xxxx';

// helper to find invoice by id
function findInvoice(id){ id = parseInt(id,10); for (let i=0;i<invoicesData.length;i++){ if (parseInt(invoicesData[i].id,10)===id) return invoicesData[i]; } return null; }

// build a printable invoice node and download as PDF
// build a printable invoice preview and open preview modal; PDF saved from modal
async function downloadInvoice(id){
  const inv = findInvoice(id);
  if (!inv) { alert('Invoice not found'); return; }
  const total = parseFloat(inv.amount || 0);
  const subtotal = Math.round((total / 1.18) * 100)/100;
  const gst = Math.round((total - subtotal) * 100)/100;

  const previewWrap = document.createElement('div');
  // center content and constrain width for A4-friendly rendering
  previewWrap.style.width = '100%'; previewWrap.style.padding = '18px'; previewWrap.style.background = '#fff'; previewWrap.style.fontFamily = 'Helvetica, Arial, sans-serif';
  previewWrap.id = 'invoicePreviewContent';
  previewWrap.innerHTML = `
    <div style="max-width:820px;margin:0 auto;color:#222">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
        <div style="flex:1">
          <h1 style="margin:0;font-size:22px;color:#d35400;font-weight:600;letter-spacing:0.2px">${COMPANY_NAME}</h1>
          <div style="font-size:12px;color:#6b6b6b;margin-top:6px;line-height:1.3">${COMPANY_ADDRESS.replace(/\n/g,'<br/>')}<br/>Mobile: ${COMPANY_MOBILE}</div>
        </div>
        <div style="text-align:right;margin-left:20px;min-width:220px;font-size:12px;color:#222">
          <div><strong>Date:</strong> ${inv.created_at || ''}</div>
          <div><strong>Invoice #:</strong> ${inv.invoice_number || ''}</div>
          <div><strong>Due:</strong> ${inv.due_date || ''}</div>
          <div style="margin-top:6px;font-size:11px;color:#666"><strong>GST:</strong> ${COMPANY_GST}</div>
        </div>
      </div>
      <hr style="border:none;border-top:1px solid #e9e9e9;margin:12px 0 16px 0;" />

      <div style="display:flex;justify-content:space-between;gap:20px;margin-bottom:12px">
        <div style="flex:1;background:transparent;padding:0">
          <div style="font-size:13px;color:#444;font-weight:600;margin-bottom:6px">Bill To</div>
          <div style="font-size:14px;color:#111;font-weight:600">${inv.subscriber_name || ''}</div>
          <div style="font-size:12px;color:#666;margin-top:6px">Subscriber ID: ${inv.subscriber_code || ''}</div>
          <div style="font-size:12px;color:#666;margin-top:6px">${(inv.subscriber_address || '').replace(/\n/g,'<br/>')}</div>
          <div style="font-size:12px;color:#666;margin-top:6px">Mobile: ${inv.subscriber_mobile || ''}</div>
          <div style="font-size:12px;color:#666">Email: ${inv.subscriber_email || ''}</div>
        </div>
        <div style="width:300px;flex-shrink:0">
          <div style="font-size:13px;color:#444;font-weight:600;margin-bottom:6px;text-align:right">Summary</div>
          <div style="font-size:13px;color:#111;text-align:right">Plan: ${inv.plan_name || '--'}</div>
          <div style="font-size:12px;color:#666;margin-top:8px;text-align:right">Period: ${inv.start_date || '—'} to ${inv.due_date || '—'}</div>
          <div style="font-size:12px;color:#666;margin-top:6px;text-align:right">Activation: ${inv.start_date || '—'}</div>
          <div style="font-size:12px;color:#666;margin-top:2px;text-align:right">Expiry: ${inv.due_date || '—'}</div>
        </div>
      </div>

      <div style="border:1px solid #eee;border-radius:4px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
          <thead style="background:#f5f5f5;color:#333">
            <tr>
              <th style="text-align:left;padding:12px 16px;border-right:1px solid #eee;font-weight:600">Description</th>
              <th style="text-align:right;padding:12px 16px;font-weight:600">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding:14px 16px;border-top:1px solid #fff">Service: ${inv.plan_name || 'Service'}</td>
              <td style="padding:14px 16px;border-top:1px solid #fff;text-align:right">₹${subtotal.toFixed(2)}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td style="padding:10px 16px;border-top:1px solid #eee;text-align:right;color:#333">Subtotal</td>
              <td style="padding:10px 16px;border-top:1px solid #eee;text-align:right">₹${subtotal.toFixed(2)}</td>
            </tr>
            <tr>
              <td style="padding:10px 16px;text-align:right;color:#333">GST (18%)</td>
              <td style="padding:10px 16px;text-align:right">₹${gst.toFixed(2)}</td>
            </tr>
            <tr>
              <td style="padding:12px 16px;text-align:right;font-weight:700;color:#111;background:#fafafa">Total</td>
              <td style="padding:12px 16px;text-align:right;font-weight:700;color:#111;background:#fafafa">₹${total.toFixed(2)}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div style="margin-top:18px;font-size:11px;color:#666">Thank you for your business!</div>
    </div>
  `;

  // inject into preview modal body
  const body = document.getElementById('invoicePreviewBody');
  if (!body) { alert('Preview modal not found'); return; }
  body.innerHTML = '';
  body.appendChild(previewWrap);

  // store current preview id
  window.__previewingInvoiceId = id;

  // show modal
  const m = new bootstrap.Modal(document.getElementById('invoicePreviewModal'));
  m.show();
}

// generate PDF from the content currently in preview modal
async function downloadPreviewPDF(){
  const content = document.getElementById('invoicePreviewContent');
  if (!content) { alert('No preview available'); return; }
  try{
  const canvas = await html2canvas(content, {scale:2, backgroundColor:'#ffffff'});
  const imgData = canvas.toDataURL('image/png');
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({unit:'px', format:'a4'});
  const pdfWidth = pdf.internal.pageSize.getWidth();
  const pdfHeight = pdf.internal.pageSize.getHeight();
  const imgWidth = canvas.width; const imgHeight = canvas.height;
  // compute scale to fit width or height (leave room for margins)
  const topMarginInput = document.getElementById('pdfTopMargin');
  const topMargin = topMarginInput ? parseInt(topMarginInput.value||'20',10) : 20;
  const availableHeight = pdfHeight - (topMargin + 20);
  const ratio = Math.min((pdfWidth - 40) / imgWidth, availableHeight / imgHeight);
  const w = imgWidth * ratio; const h = imgHeight * ratio;
  // compute X based on alignment control
  const alignSelect = document.getElementById('pdfAlign');
  const align = alignSelect ? (alignSelect.value || 'center') : 'center';
  let x = 20;
  if (align === 'center') x = Math.max(20, (pdfWidth - w) / 2);
  else if (align === 'right') x = Math.max(20, pdfWidth - w - 20);
  const y = topMargin;
  pdf.addImage(imgData, 'PNG', x, y, w, h);
    const inv = findInvoice(window.__previewingInvoiceId);
    const filename = (inv && inv.invoice_number ? inv.invoice_number : ('invoice-'+window.__previewingInvoiceId)) + '.pdf';
    pdf.save(filename.replace(/[^a-zA-Z0-9-_.]/g,''));
  }catch(err){ console.error(err); alert('Failed to generate PDF'); }
}

// Submit handler for add invoice form (re-usable)
async function submitAddInvoice(e){
  if (e && e.preventDefault) e.preventDefault();
  const form = document.getElementById('addInvoiceForm');
  const fd = new FormData(form);
  try{
    const resp = await fetch('add_invoice.php',{method:'POST',body:fd});
    const data = await resp.json();
    if (data.success) location.reload();
    else alert(data.error || 'Error creating invoice');
  }catch(err){ console.error(err); alert('Error creating invoice'); }
}
document.getElementById('addInvoiceForm').addEventListener('submit', submitAddInvoice);
document.getElementById('createInvoiceBtn').addEventListener('click', function(){ document.getElementById('addInvoiceForm').dispatchEvent(new Event('submit',{cancelable:true})); });

function markPaid(id){ if (!confirm('Mark invoice as paid?')) return; const body=new URLSearchParams(); body.append('id', id); fetch('mark_paid.php',{method:'POST',body:body}).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); else alert(d.error||'Error'); }).catch(e=>{alert('Error');}); }
function confirmDelete(id){ document.getElementById('deleteInvoiceId').value = id; new bootstrap.Modal(document.getElementById('deleteInvoiceModal')).show(); }
document.getElementById('confirmDeleteInvoiceBtn').addEventListener('click', function(){ const id=document.getElementById('deleteInvoiceId').value; const btn=this; btn.disabled=true; btn.textContent='Deleting...'; const body=new URLSearchParams(); body.append('id',id); fetch('delete_invoice.php',{method:'POST',body:body}).then(r=>r.json()).then(d=>{ btn.disabled=false; btn.textContent='Delete'; if (d.success) location.reload(); else alert(d.error||'Error'); }).catch(e=>{ btn.disabled=false; btn.textContent='Delete'; alert('Error'); }); });

// Auto-calc amount and due date based on plan price and selected days + update summary
function parsePrice(v){ const n = parseFloat(v); return isNaN(n)?0:n; }
function formatMoney(n){ return (Math.round(n*100)/100).toFixed(2); }
// ask server for the next invoice number for a given date; falls back to a local format if request fails
async function generateInvoiceNumber(dateStr){
  try{
    const d = dateStr || (new Date()).toISOString().slice(0,10);
    const resp = await fetch('get_next_invoice_no.php?date='+encodeURIComponent(d));
    const json = await resp.json();
    if (json && json.success && json.invoice_number) return json.invoice_number;
  }catch(e){ console.error('get_next_invoice_no failed',e); }
  // fallback local generate (non-sequential)
  const t = new Date(); const d = t.getFullYear().toString() + ('0'+(t.getMonth()+1)).slice(-2) + ('0'+t.getDate()).slice(-2); const r = Math.floor(1000 + Math.random()*9000);
  return 'INV' + d + ('0000'+r).slice(-4);
}
function updateSummaryFields({perDay, subtotal, gst, amount, days, from, due, planName, subscriberName, invoiceNumber}){
  const sPer = document.getElementById('summaryPerDay'); if (sPer) sPer.textContent = formatMoney(perDay);
  const sSubtotal = document.getElementById('summarySubtotal'); if (sSubtotal) sSubtotal.textContent = formatMoney(subtotal || 0);
  const sGst = document.getElementById('summaryGst'); if (sGst) sGst.textContent = formatMoney(gst || 0);
  const sTotal = document.getElementById('summaryTotal'); if (sTotal) sTotal.textContent = formatMoney(amount || 0);
  const sTop = document.getElementById('summaryTotalTopVal'); if (sTop) sTop.textContent = formatMoney(amount || 0);
  const sPeriod = document.getElementById('summaryPeriod'); if (sPeriod) sPeriod.textContent = (days||30) + ' days';
  const sFrom = document.getElementById('summaryFrom'); if (sFrom) sFrom.textContent = from || '—';
  const sDue = document.getElementById('summaryDue'); if (sDue) sDue.textContent = due || '—';
  const sPlan = document.getElementById('summaryPlan'); if (sPlan) sPlan.textContent = planName || '—';
  const sSub = document.getElementById('summarySubscriber'); if (sSub) sSub.textContent = subscriberName || '—';
  const sInv = document.getElementById('summaryInvoice'); if (sInv) sInv.textContent = invoiceNumber || '—';
}

async function computeAmountAndDue(){
  const planSelect = document.getElementById('planSelect');
  const planOpt = planSelect.options[planSelect.selectedIndex];
  const price = parsePrice(planOpt.dataset.price || planOpt.getAttribute('data-price'));
  const days = parseInt(document.getElementById('billingDays').value || '30',10);
  const start = document.getElementById('startDate').value;
  // compute per-day, subtotal, gst and total
  const perDay = price>0 ? price/30.0 : 0;
  const subtotal = perDay>0 ? Math.round(perDay * days * 100)/100 : parseFloat(document.getElementById('amountInput').value || 0);
  const gst = Math.round(subtotal * 0.18 * 100)/100;
  const total = Math.round((subtotal + gst) * 100)/100;
  // set displays and hidden inputs
  const subtotalEl = document.getElementById('subtotalDisplay'); if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);
  const gstEl = document.getElementById('gstDisplay'); if (gstEl) gstEl.textContent = formatMoney(gst);
  const subtotalInput = document.getElementById('subtotalInput'); if (subtotalInput) subtotalInput.value = subtotal;
  const gstInput = document.getElementById('gstInput'); if (gstInput) gstInput.value = gst;
  document.getElementById('amountInput').value = formatMoney(total);
  // compute due date from start + days
  let fromDate = start ? new Date(start) : new Date();
  fromDate.setHours(0,0,0,0);
  const dueDateObj = new Date(fromDate.getTime() + (days * 24 * 60 * 60 * 1000));
  const y = dueDateObj.getFullYear(); const m = ('0'+(dueDateObj.getMonth()+1)).slice(-2); const d = ('0'+dueDateObj.getDate()).slice(-2);
  const dueStr = y + '-' + m + '-' + d;
  document.getElementById('dueDate').value = dueStr;

  // update summary block
  const subscriberSelect = document.getElementById('subscriberSelect');
  const subscriberName = subscriberSelect && subscriberSelect.options[subscriberSelect.selectedIndex] ? subscriberSelect.options[subscriberSelect.selectedIndex].text : '—';
  const planName = planOpt ? planOpt.text : '—';
  // invoice preview: prefer existing preview value, otherwise request next invoice no from server
  const previewEl = document.getElementById('summaryInvoice');
  let invoiceNumber = (previewEl && previewEl.textContent && previewEl.textContent !== '—') ? previewEl.textContent : await generateInvoiceNumber(document.getElementById('startDate').value);
  updateSummaryFields({perDay, subtotal, gst, amount: total, days, from: (start||new Date().toISOString().slice(0,10)), due: dueStr, planName, subscriberName, invoiceNumber});
}

// init startDate default today and invoice preview
(async function(){
  const sd = document.getElementById('startDate');
  if (sd && !sd.value){ const t = new Date(); sd.value = t.toISOString().slice(0,10); }
  // set initial invoice number preview (ask server)
  const invPreview = document.getElementById('summaryInvoice');
  if (invPreview && (!invPreview.textContent || invPreview.textContent==='—')){
    invPreview.textContent = await generateInvoiceNumber(document.getElementById('startDate').value);
  }
  await computeAmountAndDue();
})();

// Listen to changes to keep summary live
document.getElementById('planSelect').addEventListener('change', ()=>computeAmountAndDue());
document.getElementById('billingDays').addEventListener('change', ()=>computeAmountAndDue());
document.getElementById('startDate').addEventListener('change', ()=>computeAmountAndDue());
document.getElementById('subscriberSelect').addEventListener('change', ()=>{ computeAmountAndDue(); checkSubscriberRechargeEligibility(); });
document.getElementById('amountInput').addEventListener('input', ()=>computeAmountAndDue());

// Find latest due date for a subscriber from the loaded invoicesData
function getLatestDueForSubscriber(subId){
  if (!subId) return null;
  let last = null;
  for (let i=0;i<invoicesData.length;i++){
    const inv = invoicesData[i];
    if (!inv) continue;
    if (String(inv.subscriber_id) === String(subId) && inv.due_date){
      const d = new Date(inv.due_date);
      if (!last || d > new Date(last)) last = inv.due_date;
    }
  }
  return last;
}

// Check if recharge is allowed for the selected subscriber
function checkSubscriberRechargeEligibility(){
  const sel = document.getElementById('subscriberSelect'); if (!sel) return;
  const sid = sel.value;
  const badge = document.getElementById('subscriberStatusBadge');
  const notice = document.getElementById('rechargeNotice');
  const createBtn = document.getElementById('createInvoiceBtn');
  if (!sid){ if (badge) { badge.textContent='Status: —'; badge.className='badge bg-secondary'; } if (notice) notice.textContent=''; if (createBtn) createBtn.disabled = false; return; }
  const lastDue = getLatestDueForSubscriber(sid);
  const today = new Date(); today.setHours(0,0,0,0);
  if (!lastDue){ if (badge) { badge.textContent='Status: Inactive'; badge.className='badge bg-danger'; } if (notice) notice.textContent='No prior invoice — recharge allowed'; if (createBtn) createBtn.disabled = false; return; }
  const due = new Date(lastDue); due.setHours(0,0,0,0);
  const diffDays = Math.ceil((due - today) / (24*60*60*1000));
  if (diffDays >= 0){
    // not yet expired
    if (diffDays <= 3){
      if (badge) { badge.textContent='Status: Active'; badge.className='badge bg-success'; }
      if (notice) notice.textContent='Recharge allowed (within 3 days before expiry).';
      if (createBtn) createBtn.disabled = false;
    } else {
      if (badge) { badge.textContent='Status: Active'; badge.className='badge bg-success'; }
      if (notice) notice.textContent='Recharge allowed only within 3 days before expiry.';
      if (createBtn) createBtn.disabled = true;
    }
  } else {
    // already expired
    if (badge) { badge.textContent='Status: Inactive'; badge.className='badge bg-danger'; }
    if (notice) notice.textContent='Subscriber expired — recharge allowed.';
    if (createBtn) createBtn.disabled = false;
  }
}

// Run initial check on load (after preview/inits)
document.addEventListener('DOMContentLoaded', function(){ try{ checkSubscriberRechargeEligibility(); }catch(e){/*ignore*/} });

// ------- Search & filter for invoices table -------
function filterInvoices(){
  const q = (document.getElementById('invoiceSearch').value||'').toLowerCase().trim();
  const status = (document.getElementById('statusFilter').value||'').toLowerCase();
  const rows = document.querySelectorAll('table tbody tr');
  rows.forEach(r=>{
    const invoice = (r.dataset.invoice||'').toLowerCase();
    const sub = (r.dataset.subscriber||'').toLowerCase();
    const plan = (r.dataset.plan||'').toLowerCase();
    const st = (r.dataset.status||'').toLowerCase();
    const matchesQ = !q || invoice.includes(q) || sub.includes(q) || plan.includes(q);
    const matchesStatus = !status || st===status;
    r.style.display = (matchesQ && matchesStatus) ? '' : 'none';
  });
}

let filterTimer = null;
document.getElementById('invoiceSearch').addEventListener('input', function(){ clearTimeout(filterTimer); filterTimer=setTimeout(filterInvoices,250); });
document.getElementById('statusFilter').addEventListener('change', filterInvoices);
document.getElementById('clearFilters').addEventListener('click', function(){ document.getElementById('invoiceSearch').value=''; document.getElementById('statusFilter').value=''; filterInvoices(); });

// Wire preview modal download button
document.getElementById('downloadPreviewBtn').addEventListener('click', function(){ downloadPreviewPDF(); });

// Clear preview on modal hidden to release memory
document.getElementById('invoicePreviewModal').addEventListener('hidden.bs.modal', function(){ const b=document.getElementById('invoicePreviewBody'); if (b) b.innerHTML=''; window.__previewingInvoiceId = null; });
</script>