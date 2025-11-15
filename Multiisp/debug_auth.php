<?php
error_reporting(E_ALL); ini_set('display_errors',1);
$dbHost = '127.0.0.1';
$dbName = 'isp_portal';
$dbUser = 'root';
$dbPass = ''; // adjust if needed
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (Exception $e) {
    echo "DB connect error: " . htmlspecialchars($e->getMessage());
    exit;
}
$email = $_GET['email'] ?? '';
$pw = $_GET['pw'] ?? '';
if (!$email) { echo "usage: ?email=...&pw=..."; exit; }
$stmt = $pdo->prepare('SELECT id,email,password_hash,roll,name FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();
header('Content-Type: text/plain; charset=utf-8');
if (!$row) { echo "User not found\n"; var_dump($row); exit; }
echo "Row:\n"; print_r($row);
$stored = $row['password_hash'] ?? '';
echo "\nStored value length: " . strlen($stored) . "\n";
echo "Password_get_info: "; print_r(password_get_info($stored));
$match = false;
if (!empty($stored) && !empty(password_get_info($stored)['algo'])) {
    $match = password_verify($pw, $stored);
    echo "password_verify => " . ($match ? 'OK' : 'FAIL') . "\n";
} else {
    $match = hash_equals((string)$stored, (string)$pw);
    echo "plaintext compare => " . ($match ? 'OK' : 'FAIL') . "\n";
}