<?php
session_start();
// reports page - date/week/month reports and CSV/PDF export
$dbConfigPath = __DIR__ . '/config/db.php'; if (!file_exists($dbConfigPath)) $dbConfigPath = __DIR__ . '/../config/db.php'; require_once $dbConfigPath;
$pdo = $pdo ?? null; if (empty($pdo) || !($pdo instanceof PDO)) {
    if (!empty($dbCfg) && is_array($dbCfg)){
        try{ $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$dbCfg['host']??'127.0.0.1',$dbCfg['dbname']??'isp_portal'); $pdo=new PDO($dsn,$dbCfg['user']??'root',$dbCfg['pass']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){ $pdo=null; }
    }
}
if ($pdo===null){ try{ $pdo=new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){$pdo=null;} }
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '')!=='admin') { echo "Unauthorized"; exit; }

// parse inputs
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$group = $_GET['group'] ?? 'none'; // none, day, week, month

$errors = [];
$results = [];
try{
    $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
    if ($group === 'none'){
        $sql = "SELECT inv.*, s.name AS subscriber_name, p.name AS plan_name FROM invoices inv LEFT JOIN subscriber s ON inv.subscriber_id = s.id LEFT JOIN plans p ON inv.plan_id = p.id WHERE inv.created_at BETWEEN ? AND ? ORDER BY inv.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
  }elseif($group === 'day'){
    $sql = "SELECT DATE(inv.created_at) AS period, COUNT(*) AS count_items, SUM(inv.amount) AS total_amount, SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END) AS paid_amount FROM invoices inv WHERE inv.created_at BETWEEN ? AND ? GROUP BY DATE(inv.created_at) ORDER BY DATE(inv.created_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    }elseif($group === 'week'){
    $sql = "SELECT YEAR(inv.created_at) AS yr, WEEK(inv.created_at,1) AS wk, CONCAT(YEAR(inv.created_at),'-W',LPAD(WEEK(inv.created_at,1),2,'0')) AS period, COUNT(*) AS count_items, SUM(inv.amount) AS total_amount, SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END) AS paid_amount FROM invoices inv WHERE inv.created_at BETWEEN ? AND ? GROUP BY YEAR(inv.created_at),WEEK(inv.created_at,1) ORDER BY YEAR(inv.created_at),WEEK(inv.created_at,1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    }elseif($group === 'month'){
    $sql = "SELECT DATE_FORMAT(inv.created_at,'%Y-%m') AS period, COUNT(*) AS count_items, SUM(inv.amount) AS total_amount, SUM(CASE WHEN inv.status = 'paid' THEN inv.amount ELSE 0 END) AS paid_amount FROM invoices inv WHERE inv.created_at BETWEEN ? AND ? GROUP BY DATE_FORMAT(inv.created_at,'%Y-%m') ORDER BY DATE_FORMAT(inv.created_at,'%Y-%m')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    }
}catch(Throwable $e){ $errors[] = $e->getMessage(); }

// Compute the report's range total (sum of invoice amounts in the current result set)
$rangeTotal = 0.0;
$rangePaidTotal = 0.0;
try {
  if (!empty($results)) {
    if ($group === 'none') {
      foreach ($results as $r) {
        $amt = isset($r['amount']) ? (float)$r['amount'] : 0.0;
        $rangeTotal += $amt;
        if (isset($r['status']) && strtolower($r['status']) === 'paid') $rangePaidTotal += $amt;
      }
    } else {
      // grouped results include aggregated columns
      foreach ($results as $r) {
        $rangeTotal += isset($r['total_amount']) ? (float)$r['total_amount'] : 0.0;
        $rangePaidTotal += isset($r['paid_amount']) ? (float)$r['paid_amount'] : 0.0;
      }
    }
  }
} catch (Throwable $e) {
  $errors[] = 'Range total calc error: ' . $e->getMessage();
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reports — ISP Portal</title>
</head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  .report-wrap{max-width:1100px;margin:20px auto}
  .table-fixed thead th{position:sticky;top:0;background:#fff}
  .stat-card{border-radius:8px;background:#fff;border:1px solid #eee;padding:12px}
  .preset-btns .btn{margin-right:6px}
</style>
</head>
<body>
<div class="container report-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Reports</h3>
    <div><a href="dashboard_admin.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a></div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n',$errors)); ?></div>
  <?php endif; ?>

  <form id="reportForm" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
      <label class="form-label small">From</label>
      <input type="date" id="from" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small">To</label>
      <input type="date" id="to" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small">Group By</label>
      <select name="group" id="group" class="form-select">
        <option value="none" <?php echo $group==='none'?'selected':''; ?>>Detailed</option>
        <option value="day" <?php echo $group==='day'?'selected':''; ?>>Day</option>
        <option value="week" <?php echo $group==='week'?'selected':''; ?>>Week</option>
        <option value="month" <?php echo $group==='month'?'selected':''; ?>>Month</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary">Run</button>
    </div>
    <div class="col-auto ms-auto d-flex" style="gap:8px">
      <button id="csvBtn" type="button" class="btn btn-outline-secondary">Download CSV</button>
      <button id="pdfBtn" type="button" class="btn btn-outline-secondary">Download PDF</button>
    </div>
  </form>

  <div class="d-flex align-items-start gap-3 mb-3">
    <div class="preset-btns">
      <button type="button" id="presetToday" class="btn btn-sm btn-light">Today</button>
      <button type="button" id="presetWeek" class="btn btn-sm btn-light">This Week</button>
      <button type="button" id="presetMonth" class="btn btn-sm btn-light">This Month</button>
      <button type="button" id="presetYear" class="btn btn-sm btn-light">This Year</button>
    </div>
    <div class="ms-auto d-flex" style="gap:12px">
      <div class="stat-card text-center">
        <div class="small text-muted">Total Items</div>
        <div id="statCount" style="font-size:18px;font-weight:700"><?php echo ($group==='none'?count($results):array_sum(array_column($results,'count_items'))); ?></div>
      </div>
      <div class="stat-card text-center">
        <div class="small text-muted">Range Total</div>
        <div id="statRangeTotal" style="font-size:18px;font-weight:700">₹<?php echo number_format((float)$rangeTotal,2); ?></div>
      </div>
      <div class="stat-card text-center">
        <div class="small text-muted">Range Paid Total</div>
        <div id="statRangePaid" style="font-size:18px;font-weight:700">₹<?php echo number_format((float)$rangePaidTotal,2); ?></div>
      </div>
    </div>
  </div>

  <div id="reportArea">
    <?php if ($group === 'none'): ?>
      <div class="card mb-3">
        <div class="card-body">
          <canvas id="reportChart" height="120"></canvas>
        </div>
      </div>
      <div class="table-responsive card">
        <div class="card-body p-0">
          <table class="table table-sm table-striped table-fixed mb-0" id="reportTable">
          <thead><tr><th>#</th><th>Invoice #</th><th>Subscriber</th><th>Plan</th><th class="text-end">Amount</th><th>Due</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($results as $i => $row): ?>
              <tr>
                <td><?php echo $i+1; ?></td>
                <td><?php echo htmlspecialchars($row['invoice_number'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['subscriber_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['plan_name'] ?? ''); ?></td>
                <td class="text-end"><?php echo number_format($row['amount'] ?? 0,2); ?></td>
                <td><?php echo htmlspecialchars($row['due_date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
  </table>
        </div>
      </div>
    <?php else: ?>
      <div class="card mb-3">
        <div class="card-body">
          <canvas id="reportChart" height="120"></canvas>
        </div>
      </div>
      <div class="table-responsive card">
        <div class="card-body p-0">
          <table class="table table-sm table-striped mb-0" id="reportTable">
          <thead><tr><th>Period</th><th class="text-end">Count</th><th class="text-end">Total Amount</th><th class="text-end">Paid Amount</th></tr></thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['period'] ?? ($r['yr'].'-W'.$r['wk'] ?? '')); ?></td>
                <td class="text-end"><?php echo number_format($r['count_items'] ?? 0); ?></td>
                <td class="text-end"><?php echo number_format($r['total_amount'] ?? 0,2); ?></td>
                <td class="text-end"><?php echo number_format($r['paid_amount'] ?? 0,2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
  </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// helper to download table as CSV
function tableToCSV(separator = ','){
  const tbl = document.getElementById('reportTable');
  if(!tbl) return '';
  const rows = Array.from(tbl.querySelectorAll('tr'));
  return rows.map(r => Array.from(r.querySelectorAll('th,td')).map(cell => '"'+(cell.innerText.replace(/"/g,'""'))+'"').join(separator)).join('\n');
}
document.getElementById('csvBtn').addEventListener('click', function(){
  const csv = tableToCSV();
  const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  const url = URL.createObjectURL(blob);
  a.href = url; a.download = 'report-<?php echo date('YmdHis'); ?>.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
});

// PDF download of report area
async function downloadReportPDF(){
  const node = document.getElementById('reportArea');
  if(!node) return alert('Nothing to export');
  try{
    const canvas = await html2canvas(node, {scale:2, backgroundColor:'#ffffff'});
    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({unit:'px', format:'a4'});
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();
    const imgWidth = canvas.width; const imgHeight = canvas.height;
    const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
    const w = imgWidth * ratio; const h = imgHeight * ratio;
    const x = Math.max(20, (pdfWidth - w)/2);
    const y = 20;
    pdf.addImage(imgData, 'PNG', x, y, w, h);
    pdf.save('report-<?php echo date('YmdHis'); ?>.pdf');
  }catch(err){ console.error(err); alert('Failed to generate PDF'); }
}

document.getElementById('pdfBtn').addEventListener('click', downloadReportPDF);

// build chart from results
const resultsData = <?php echo json_encode($results, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const groupMode = '<?php echo $group; ?>';
function buildChart(){
  const ctx = document.getElementById('reportChart');
  if(!ctx) return;
  // prepare labels and data
  const labels = [];
  const data = [];
  if(groupMode === 'none'){
    const map = {};
    resultsData.forEach(r=>{
      const d = (r.created_at||'').slice(0,10);
      if(!map[d]) map[d]=0; map[d]+= parseFloat(r.amount||0);
    });
    const sortedKeys = Object.keys(map).sort();
    sortedKeys.forEach(k=>{ labels.push(k); data.push(map[k]); });
  }else{
    resultsData.forEach(r=>{ labels.push(r.period); data.push(parseFloat(r.total_amount||0)); });
  }
  // destroy existing chart instance if present
  if(window.__reportChart) window.__reportChart.destroy();
  window.__reportChart = new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ label: 'Total Amount', data: data, backgroundColor: '#4f46e5' }] },
    options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
  });
}
buildChart();

// quick presets
function setRange(from,to){ document.getElementById('from').value = from; document.getElementById('to').value = to; document.getElementById('reportForm').submit(); }
document.getElementById('presetToday').addEventListener('click', ()=>{ const t=new Date(); const s=t.toISOString().slice(0,10); setRange(s,s); });
document.getElementById('presetWeek').addEventListener('click', ()=>{ const now=new Date(); const day = now.getDay(); const diff = (day === 0 ? -6 : 1 - day); const first = new Date(now); first.setDate(now.getDate() + diff); const last = new Date(first); last.setDate(first.getDate()+6); setRange(first.toISOString().slice(0,10), last.toISOString().slice(0,10)); });
document.getElementById('presetMonth').addEventListener('click', ()=>{ const d=new Date(); const start=new Date(d.getFullYear(),d.getMonth(),1); const end=new Date(d.getFullYear(),d.getMonth()+1,0); setRange(start.toISOString().slice(0,10), end.toISOString().slice(0,10)); });
document.getElementById('presetYear').addEventListener('click', ()=>{ const d=new Date(); const start=new Date(d.getFullYear(),0,1); const end=new Date(d.getFullYear(),11,31); setRange(start.toISOString().slice(0,10), end.toISOString().slice(0,10)); });
</script>
</body>
</html>
