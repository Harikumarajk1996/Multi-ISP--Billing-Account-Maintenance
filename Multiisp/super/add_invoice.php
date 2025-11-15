<?php
session_start();
// add invoice endpoint
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
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'error'=>'Invalid method']); exit; }
try{
    $subscriber_id = isset($_POST['subscriber_id']) ? (int)$_POST['subscriber_id'] : 0;
    if ($subscriber_id<=0) throw new Exception('Subscriber required');
    // Enforce recharge window: allow invoice creation only if there's no prior invoice,
    // or if the latest invoice due_date is within 3 days from today, or already expired.
    $checkStmt = $pdo->prepare('SELECT due_date FROM invoices WHERE subscriber_id = ? AND due_date IS NOT NULL ORDER BY due_date DESC LIMIT 1');
    $checkStmt->execute([$subscriber_id]);
    $last = $checkStmt->fetch();
    if ($last && !empty($last['due_date'])) {
        $dueDate = $last['due_date'];
        $today = date('Y-m-d');
        $diff = (strtotime($dueDate) - strtotime($today)) / 86400; // days until due
        if ($diff > 3) {
            echo json_encode(['success'=>false,'error'=>'Recharge not allowed yet. Can only recharge within 3 days before expiry.']);
            exit;
        }
    }
    $plan_id = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
    $amount = isset($_POST['amount']) ? floatval(str_replace(',','',$_POST['amount'])) : 0;
    if ($amount<=0) throw new Exception('Amount must be > 0');
    $start_date = trim($_POST['start_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // generate invoice number in format INVYYYYMMDD0001 (4-digit sequence per day)
    $datePart = date('Ymd');
    $prefix = 'INV' . $datePart;

    // Try to reserve a sequence and insert atomically. On duplicate key, retry a few times.
    $tries = 0; $maxTries = 5; $inserted = false;
    while (!$inserted && $tries < $maxTries) {
        $tries++;
        try {
            $pdo->beginTransaction();
            // find last invoice for today
            $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1 FOR UPDATE");
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

            // include start_date if available; assumes invoices table has a `start_date` DATE column
            $ins = $pdo->prepare('INSERT INTO invoices (invoice_number,subscriber_id,plan_id,amount,start_date,due_date,notes,status,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $ins->execute([$invoice_number,$subscriber_id,$plan_id,$amount,$start_date,$due_date,$notes,'pending']);
            $pdo->commit();
            $inserted = true;
            echo json_encode(['success'=>true,'invoice_number'=>$invoice_number]);
            break;
        } catch (PDOException $e) {
            $pdo->rollBack();
            // duplicate key - retry with incremented sequence
            if ($e->getCode() == 23000) {
                // continue loop to try next sequence
                continue;
            }
            throw $e;
        }
    }
    if (!$inserted) throw new Exception('Could not generate unique invoice number after multiple attempts');
}catch(Throwable $e){ error_log('add_invoice: '.$e->getMessage()); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
