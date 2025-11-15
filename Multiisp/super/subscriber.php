<?php
session_start();
// DB connect (try local config then parent)
$configPath = __DIR__ . '/config/db.php';
if (!file_exists($configPath)) $configPath = __DIR__ . '/../config/db.php';
if (file_exists($configPath)) require_once $configPath;
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
// fetch subscribers (users role)
$subs = [];
$plans = [];
if ($pdo) {
  try {
  $stmt = $pdo->prepare("SELECT id,name,email,subscriber_id,mobile,address,plan_id,created_at FROM subscriber ORDER BY name ASC");
  $stmt->execute();
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // fetch plans for dropdown
    $pstmt = $pdo->query("SELECT id,name FROM plans ORDER BY name ASC");
    $plans = $pstmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $dbErr = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Subscribers — SilverWave</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{font-family:Inter,system-ui,Segoe UI,Arial;color:#0f172a;background:#f3f6f9}
.container{max-width:1100px;margin:28px auto;padding:20px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,#e6f7ff,#e9d8ff);display:flex;align-items:center;justify-content:center;font-weight:800;color:#07203a}
.table thead th{font-weight:700}
.table td.text-center{font-family:monospace; width:90px}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div style="display:flex;gap:12px;align-items:center">
      <div class="logo">SW</div>
      <div>
        <div style="font-weight:700">SilverWave</div>
        <div style="color:#6b7280;font-size:13px">Subscribers (Customers)</div>
      </div>
    </div>
    <div>
      <a href="dashboard_admin.php" class="btn btn-outline-secondary">Back to Dashboard</a>
      <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addSubModal">Add Subscriber</button>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3">
    <input id="subscriberSearch" class="form-control" placeholder="Search by name, email or subscriber ID" />
    <select id="subscriberPlanFilter" class="form-control" style="max-width:220px">
      <option value="">All plans</option>
      <?php foreach ($plans as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button id="clearFiltersBtn" class="btn btn-outline-secondary">Clear</button>
  </div>

  <?php if (!empty($dbErr)): ?>
    <div class="alert alert-warning">Database error: <?= htmlspecialchars($dbErr) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
  <tr><th class="text-center">ID</th><th>Subscriber ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Plan</th><th>Address</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
        <?php $count = count($subs); $pad = max(2, strlen((string)$count)); $i = 0; foreach ($subs as $sub): $i++; ?>
          <tr data-id="<?= (int)$sub['id'] ?>" data-subscriber-id="<?= htmlspecialchars($sub['subscriber_id'] ?? '') ?>" data-name="<?= htmlspecialchars($sub['name']) ?>" data-email="<?= htmlspecialchars($sub['email']) ?>" data-mobile="<?= htmlspecialchars($sub['mobile'] ?? '') ?>" data-plan-id="<?= (int)($sub['plan_id'] ?? 0) ?>" data-address="<?= htmlspecialchars($sub['address'] ?? '') ?>">
            <td class="text-center"><?= htmlspecialchars(str_pad((string)$i, $pad, '0', STR_PAD_LEFT)) ?></td>
            <td><?= htmlspecialchars($sub['subscriber_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($sub['name']) ?></td>
            <td><?= htmlspecialchars($sub['email']) ?></td>
            <td><?= htmlspecialchars($sub['mobile'] ?? '') ?></td>
      <td><?php // find plan name
                  $planName = '';
                  foreach ($plans as $pp) { if ($pp['id']==($sub['plan_id'] ?? null)) { $planName = $pp['name']; break; } }
                  echo htmlspecialchars($planName);
        ?></td>
      <td><?= htmlspecialchars(mb_strlen($sub['address'] ?? '')>80 ? mb_substr($sub['address'] ?? '',0,80).'...' : ($sub['address'] ?? '')) ?></td>
      <td><?= htmlspecialchars($sub['created_at']) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary me-1" onclick="openEdit(<?= (int)$sub['id'] ?>)">Edit</button>
              <button class="btn btn-sm btn-danger" onclick="openDelete(<?= (int)$sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Subscriber Modal -->
<div class="modal fade" id="addSubModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Subscriber</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="addSubForm">
          <div class="mb-3"><label class="form-label">Subscriber ID (optional)</label><input name="subscriber_id" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Mobile</label><input name="mobile" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Plan</label>
            <select name="plan_id" class="form-control">
              <option value="">-- none --</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control"></textarea></div>
          <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
          <div class="text-end"><button type="submit" class="btn btn-primary">Add Subscriber</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Subscriber Modal -->
<div class="modal fade" id="editSubModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Subscriber</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="editSubForm">
          <input type="hidden" name="id">
          <div class="mb-3"><label class="form-label">Subscriber ID</label><input name="subscriber_id" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Mobile</label><input name="mobile" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Plan</label>
            <select name="plan_id" class="form-control">
              <option value="">-- none --</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control"></textarea></div>
          <div class="mb-3"><label class="form-label">Password (leave blank to keep)</label><input name="password" type="password" class="form-control"></div>
          <div class="text-end"><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSubModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p id="deleteSubMessage">Are you sure you want to delete this subscriber?</p>
        <input type="hidden" id="deleteSubId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteSubBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(id){
  fetch('get_user.php?id='+id).then(r=>r.json()).then(data=>{
    if (!data.success) return alert(data.error||'Failed');
    const row = data.data || null;
    if (!row) return alert('Invalid response');
    const f = document.getElementById('editSubForm');
    f.elements['id'].value = row.id;
    f.elements['subscriber_id'].value = row.subscriber_id || '';
    f.elements['name'].value = row.name || '';
    f.elements['email'].value = row.email || '';
    f.elements['mobile'].value = row.mobile || '';
    f.elements['plan_id'].value = row.plan_id || '';
    f.elements['address'].value = row.address || '';
    f.elements['password'].value = '';
    new bootstrap.Modal(document.getElementById('editSubModal')).show();
  }).catch(e=>{console.error(e); alert('Error fetching subscriber');});
}
function openDelete(id,name){
  const msg = document.getElementById('deleteSubMessage');
  const hid = document.getElementById('deleteSubId');
  msg.textContent = `Delete ${name}?`;
  hid.value = id;
  const delModal = new bootstrap.Modal(document.getElementById('deleteSubModal'));
  delModal.show();
}

document.getElementById('confirmDeleteSubBtn').addEventListener('click', function(){
  const id = document.getElementById('deleteSubId').value;
  if (!id) return;
  const btn = this; btn.disabled = true; btn.textContent = 'Deleting...';
  const body = new URLSearchParams(); body.append('id', id);
  fetch('delete_user.php',{method:'POST',body:body}).then(r=>r.json()).then(data=>{
    btn.disabled = false; btn.textContent = 'Delete';
    if (data.success) location.reload(); else { alert(data.error||'Error'); }
  }).catch(e=>{ console.error(e); btn.disabled=false; btn.textContent='Delete'; alert('Error deleting subscriber'); });
});

// Add subscriber
document.getElementById('addSubForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fetch('add_user.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{ if (data.success) { location.reload(); } else alert(data.error||'Error'); }).catch(e=>{alert('Error');});
});
// Edit subscriber
document.getElementById('editSubForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fetch('update_user.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{ if (data.success) { location.reload(); } else alert(data.error||'Error'); }).catch(e=>{alert('Error');});
});

// Search & filter (client-side)
function debounce(fn, wait){ let t; return function(...args){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args), wait); }; }
function filterSubscribers(){
  const q = (document.getElementById('subscriberSearch').value || '').trim().toLowerCase();
  const plan = document.getElementById('subscriberPlanFilter').value;
  const rows = document.querySelectorAll('table tbody tr[data-id]');
  rows.forEach(r=>{
    const name = (r.dataset.name||'').toLowerCase();
    const email = (r.dataset.email||'').toLowerCase();
    const sid = (r.dataset.subscriberId||'').toLowerCase();
    const mobile = (r.dataset.mobile||'').toLowerCase();
    const planId = String(r.dataset.planId||'');
    let visible = true;
    if (q){ visible = (name.indexOf(q)!==-1)||(email.indexOf(q)!==-1)||(sid.indexOf(q)!==-1)||(mobile.indexOf(q)!==-1); }
    if (visible && plan){ visible = (planId === plan); }
    r.style.display = visible ? '' : 'none';
  });
}
const debouncedFilter = debounce(filterSubscribers, 200);
document.getElementById('subscriberSearch').addEventListener('input', debouncedFilter);
document.getElementById('subscriberPlanFilter').addEventListener('change', filterSubscribers);
document.getElementById('clearFiltersBtn').addEventListener('click', function(){ document.getElementById('subscriberSearch').value=''; document.getElementById('subscriberPlanFilter').value=''; filterSubscribers(); });
</script>
</body>
</html>
