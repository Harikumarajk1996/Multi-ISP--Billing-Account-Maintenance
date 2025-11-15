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
// fetch isps
$isps = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id,name,slug,description,contact_email,status,created_at FROM isps ORDER BY name ASC");
        $isps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $dbErr = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Operators — SilverWave</title>
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
        <div style="color:#6b7280;font-size:13px">Operators (ISPs)</div>
      </div>
    </div>
    <div>
      <a href="dashboard_admin.php" class="btn btn-outline-secondary">Back to Dashboard</a>
      <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addIspModal">Add ISP</button>
    </div>
  </div>

  <?php if (!empty($dbErr)): ?>
    <div class="alert alert-warning">Database error: <?= htmlspecialchars($dbErr) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr><th class="text-center">ID</th><th>Name</th><th>Slug</th><th>Contact</th><th>Status</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
        <?php $count = count($isps); $pad = max(2, strlen((string)$count)); $i = 0; foreach ($isps as $isp): $i++; ?>
          <tr data-id="<?= (int)$isp['id'] ?>">
            <td class="text-center"><?= htmlspecialchars(str_pad((string)$i, $pad, '0', STR_PAD_LEFT)) ?></td>
            <td><?= htmlspecialchars($isp['name']) ?></td>
            <td><?= htmlspecialchars($isp['slug']) ?></td>
            <td><?= htmlspecialchars($isp['contact_email']) ?></td>
            <td><?= htmlspecialchars($isp['status']) ?></td>
            <td><?= htmlspecialchars($isp['created_at']) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary me-1" onclick="openEdit(<?= (int)$isp['id'] ?>)">Edit</button>
              <button class="btn btn-sm btn-danger" onclick="openDelete(<?= (int)$isp['id'] ?>, '<?= htmlspecialchars(addslashes($isp['name'])) ?>')">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add ISP Modal -->
<div class="modal fade" id="addIspModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add ISP</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="addIspForm">
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Slug</label><input name="slug" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"></textarea></div>
          <div class="mb-3"><label class="form-label">Contact Email</label><input name="contact_email" type="email" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-control"><option value="active">active</option><option value="inactive">inactive</option></select></div>
          <div class="text-end"><button type="submit" class="btn btn-primary">Add ISP</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit ISP Modal (populated dynamically) -->
<div class="modal fade" id="editIspModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit ISP</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="editIspForm">
          <input type="hidden" name="id">
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Slug</label><input name="slug" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"></textarea></div>
          <div class="mb-3"><label class="form-label">Contact Email</label><input name="contact_email" type="email" class="form-control"></div>
          <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-control"><option value="active">active</option><option value="inactive">inactive</option></select></div>
          <div class="text-end"><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteIspModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p id="deleteIspMessage">Are you sure you want to delete this ISP?</p>
        <input type="hidden" id="deleteIspId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDeleteIspBtn" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEdit(id){
  fetch('get_isp.php?id='+id).then(r=>r.json()).then(data=>{
    if (!data.success) return alert(data.error||'Failed');
    const row = data.data || data.isp || null;
    if (!row) return alert('Invalid response');
    const f = document.getElementById('editIspForm');
    f.elements['id'].value = row.id;
    f.elements['name'].value = row.name || '';
    f.elements['slug'].value = row.slug || '';
    f.elements['description'].value = row.description || '';
    f.elements['contact_email'].value = row.contact_email || '';
    f.elements['status'].value = row.status || 'active';
    new bootstrap.Modal(document.getElementById('editIspModal')).show();
  }).catch(e=>{console.error(e); alert('Error fetching ISP');});
}
function openDelete(id,name){
  // show confirm modal and set values
  const msg = document.getElementById('deleteIspMessage');
  const hid = document.getElementById('deleteIspId');
  msg.textContent = `Delete ${name}?`;
  hid.value = id;
  const delModal = new bootstrap.Modal(document.getElementById('deleteIspModal'));
  delModal.show();
}

document.getElementById('confirmDeleteIspBtn').addEventListener('click', function(){
  const id = document.getElementById('deleteIspId').value;
  if (!id) return;
  const btn = this; btn.disabled = true; btn.textContent = 'Deleting...';
  const body = new URLSearchParams(); body.append('id', id);
  fetch('delete_isp.php',{method:'POST',body:body}).then(r=>r.json()).then(data=>{
    btn.disabled = false; btn.textContent = 'Delete';
    if (data.success) location.reload(); else { alert(data.error||'Error'); }
  }).catch(e=>{ console.error(e); btn.disabled=false; btn.textContent='Delete'; alert('Error deleting ISP'); });
});

// Add ISP
document.getElementById('addIspForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fetch('add_isp.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{ if (data.success) { location.reload(); } else alert(data.error||'Error'); }).catch(e=>{alert('Error');});
});
// Edit ISP
document.getElementById('editIspForm').addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fetch('update_isp.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{ if (data.success) { location.reload(); } else alert(data.error||'Error'); }).catch(e=>{alert('Error');});
});
</script>
</body>
</html>
