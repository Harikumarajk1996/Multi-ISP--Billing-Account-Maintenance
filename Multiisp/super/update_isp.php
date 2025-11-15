<?php
session_start();
$dbConfigPath = __DIR__ . '/config/db.php'; if (!file_exists($dbConfigPath)) $dbConfigPath = __DIR__ . '/../config/db.php'; require_once $dbConfigPath;
$pdo = $pdo ?? null; $dbErr = null;
if (empty($pdo) || !($pdo instanceof PDO)){
    if (!empty($dbCfg) && is_array($dbCfg)){
        try{ $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$dbCfg['host']??'127.0.0.1',$dbCfg['dbname']??'isp_portal'); $pdo=new PDO($dsn,$dbCfg['user']??'root',$dbCfg['pass']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){ $pdo=null;$dbErr=$e->getMessage(); }
    }
}
if ($pdo===null){ try{ $pdo=new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){$pdo=null;$dbErr=$e->getMessage();}}
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'error'=>'Invalid method']); exit; }
try{
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id<=0) throw new Exception('Invalid id');
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contact = trim($_POST['contact_email'] ?? '');
    $status = in_array($_POST['status'] ?? 'active',['active','inactive']) ? $_POST['status'] : 'active';
    if ($name==='') throw new Exception('Name required');
    if ($slug==='') { $slug = strtolower(preg_replace('/[^a-z0-9]+/','-', $name)); }
    $stmt = $pdo->prepare('UPDATE isps SET name=?,slug=?,description=?,contact_email=?,status=? WHERE id=?');
    $stmt->execute([$name,$slug,$description,$contact,$status,$id]);
    echo json_encode(['success'=>true]);
}catch(Throwable $e){ error_log('update_isp: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
