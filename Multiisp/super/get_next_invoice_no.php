<?php
session_start();
// returns next invoice number for given date (non-consuming)
$dbConfigPath = __DIR__ . '/config/db.php'; if (!file_exists($dbConfigPath)) $dbConfigPath = __DIR__ . '/../config/db.php'; require_once $dbConfigPath;
$pdo = $pdo ?? null; $dbErr = null;
if (empty($pdo) || !($pdo instanceof PDO)) {
    if (!empty($dbCfg) && is_array($dbCfg)){
        try{ $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$dbCfg['host']??'127.0.0.1',$dbCfg['dbname']??'isp_portal'); $pdo=new PDO($dsn,$dbCfg['user']??'root',$dbCfg['pass']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){ $pdo=null; $dbErr=$e->getMessage(); }
    }
}
if ($pdo===null){ try{ $pdo=new PDO('mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }catch(Throwable $e){$pdo=null;$dbErr=$e->getMessage();}}
header('Content-Type: application/json');
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '')!=='admin') { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }
try{
    $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $datePart = date('Ymd', strtotime($date));
    $prefix = 'INV' . $datePart;
    $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $row = $stmt->fetch();
    if ($row && !empty($row['invoice_number'])) {
        $last = $row['invoice_number'];
        $lastSeq = (int)substr($last, -4);
        $nextSeq = $lastSeq + 1;
    } else {
        $nextSeq = 1;
    }
    $invoice_number = sprintf('INV%s%04d', $datePart, $nextSeq);
    echo json_encode(['success'=>true,'invoice_number'=>$invoice_number]);
}catch(Throwable $e){ error_log('get_next_invoice_no: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
