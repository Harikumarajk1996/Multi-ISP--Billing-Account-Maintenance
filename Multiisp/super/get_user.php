<?php
session_start();
$dbConfigPath = __DIR__ . '/config/db.php'; if (!file_exists($dbConfigPath)) $dbConfigPath = __DIR__ . '/../config/db.php'; require_once $dbConfigPath;
$pdo = $pdo ?? null; if (empty($pdo) || !($pdo instanceof PDO)){
    if (!empty($dbCfg) && is_array($dbCfg)){
        try{ $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$dbCfg['host']??'127.0.0.1',$dbCfg['dbname']??'isp_portal'); $pdo=new PDO($dsn,$dbCfg['user']??'root',$dbCfg['pass']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){ $pdo=null; }
    }
}
if ($pdo===null){ try{ $pdo=new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){$pdo=null;}}
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0){ echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }
try{
    $stmt = $pdo->prepare('SELECT id,name,email,subscriber_id,mobile,plan_id,address,created_at FROM subscriber WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }
    echo json_encode(['success'=>true,'data'=>$row]);
}catch(Throwable $e){ error_log('get_user: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>'Server error']); }
