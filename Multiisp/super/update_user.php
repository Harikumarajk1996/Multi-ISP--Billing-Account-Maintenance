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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $mobile = trim($_POST['mobile'] ?? '');
    $plan_id = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
    $subscriber_id = trim($_POST['subscriber_id'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($name==='') throw new Exception('Name required');
    if ($email==='') throw new Exception('Email required');
    // check exists and role
    $stmt = $pdo->prepare('SELECT id FROM subscriber WHERE id = ? LIMIT 1'); $stmt->execute([$id]); if (!$stmt->fetch()) throw new Exception('Subscriber not found');
    // check email uniqueness
    $stmt = $pdo->prepare('SELECT id FROM subscriber WHERE email = ? AND id <> ? LIMIT 1'); $stmt->execute([$email,$id]); if ($stmt->fetch()) throw new Exception('Email already in use');
    // check subscriber_id uniqueness
    if ($subscriber_id !== ''){
        $stmt = $pdo->prepare('SELECT id FROM subscriber WHERE subscriber_id = ? AND id <> ? LIMIT 1'); $stmt->execute([$subscriber_id,$id]); if ($stmt->fetch()) throw new Exception('Subscriber ID already in use');
    }
    if ($password!==''){
        $hash = password_hash($password,PASSWORD_DEFAULT);
        $up = $pdo->prepare('UPDATE subscriber SET name=?,email=?,password=?,subscriber_id=?,mobile=?,plan_id=?,address=? WHERE id=?');
        $up->execute([$name,$email,$hash,$subscriber_id,$mobile,$plan_id,$address,$id]);
    } else {
        $up = $pdo->prepare('UPDATE subscriber SET name=?,email=?,subscriber_id=?,mobile=?,plan_id=?,address=? WHERE id=?');
        $up->execute([$name,$email,$subscriber_id,$mobile,$plan_id,$address,$id]);
    }
    echo json_encode(['success'=>true]);
}catch(Throwable $e){ error_log('update_user: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
