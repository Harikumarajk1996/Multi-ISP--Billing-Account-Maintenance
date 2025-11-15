<?php
session_start();
// load db config
$dbConfigPath = __DIR__ . '/config/db.php'; if (!file_exists($dbConfigPath)) $dbConfigPath = __DIR__ . '/../config/db.php'; require_once $dbConfigPath;
$pdo = $pdo ?? null; $dbErr = null;
if (empty($pdo) || !($pdo instanceof PDO)) {
    if (!empty($dbCfg) && is_array($dbCfg)) {
        try { $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$dbCfg['host']??'127.0.0.1',$dbCfg['dbname']??'isp_portal'); $pdo = new PDO($dsn,$dbCfg['user']??'root',$dbCfg['pass']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } catch (Throwable $e){ $pdo=null;$dbErr=$e->getMessage(); }
    }
}
if ($pdo===null){ try{ $pdo=new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){$pdo=null;$dbErr=$e->getMessage();}}
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || $_SESSION['user']['role']!=='admin') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'error'=>'Invalid method']); exit; }
try{
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $mobile = trim($_POST['mobile'] ?? '');
    $plan_id = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
    $subscriber_id = trim($_POST['subscriber_id'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name==='') throw new Exception('Name required');
    if ($email==='') throw new Exception('Email required');
    if ($password==='') throw new Exception('Password required');
    // basic uniqueness check for email
    $stmt = $pdo->prepare('SELECT id FROM subscriber WHERE email = ? LIMIT 1'); $stmt->execute([$email]);
    if ($stmt->fetch()) throw new Exception('Email already in use');
    // subscriber_id uniqueness
    if ($subscriber_id !== ''){
        $stmt = $pdo->prepare('SELECT id FROM subscriber WHERE subscriber_id = ? LIMIT 1'); $stmt->execute([$subscriber_id]);
        if ($stmt->fetch()) throw new Exception('Subscriber ID already in use');
    } else {
        // generate a simple subscriber id: SUB + timestamp + random
        $subscriber_id = 'SUB'.time().substr(bin2hex(random_bytes(3)),0,6);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // insert into existing `subscriber` table (no role column assumed)
    $ins = $pdo->prepare('INSERT INTO subscriber (name,email,password,subscriber_id,mobile,plan_id,address,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $ins->execute([$name,$email,$hash,$subscriber_id,$mobile,$plan_id,$address]);
    echo json_encode(['success'=>true]);
}catch(Throwable $e){ error_log('add_user: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
