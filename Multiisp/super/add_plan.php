<?php
session_start();
// Load DB config from parent config directory
$dbConfigPath = __DIR__ . '/config/db.php';
if (!file_exists($dbConfigPath)) {
    $dbConfigPath = __DIR__ . '/../config/db.php';
}
require_once $dbConfigPath;

// Ensure $pdo is available (db.php may provide $pdo or $dbCfg)
$pdo = $pdo ?? null;
$dbErr = null;
if (empty($pdo) || !($pdo instanceof PDO)) {
    if (!empty($dbCfg) && is_array($dbCfg)) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbCfg['host'] ?? '127.0.0.1', $dbCfg['dbname'] ?? 'isp_portal');
            $pdo = new PDO($dsn, $dbCfg['user'] ?? 'root', $dbCfg['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $pdo = null; $dbErr = $e->getMessage();
        }
    }
}
if ($pdo === null) {
    try {
        $dsn = 'mysql:host=127.0.0.1;dbname=isp_portal;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        $pdo = null; $dbErr = $e->getMessage();
    }
}

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    if (!$pdo) {
        throw new Exception('Database connection error');
    }

    // Validate inputs
    $requiredFields = ['isp_id', 'name', 'speed', 'price', 'planmode'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and validate inputs
    $ispId = filter_var($_POST['isp_id'], FILTER_VALIDATE_INT);
    $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
    $speed = trim(filter_var($_POST['speed'], FILTER_SANITIZE_STRING));
    $price = filter_var($_POST['price'], FILTER_VALIDATE_INT);
    $planmode = filter_var($_POST['planmode'], FILTER_VALIDATE_INT);

    // Validate numeric ids and price explicitly (allow price = 0)
    if ($ispId === false || $ispId === null) {
        throw new Exception('Invalid isp id');
    }
    if ($price === false || $price === null || !is_int($price) || $price < 0) {
        throw new Exception('Invalid price');
    }
    if (!in_array($planmode, [1, 2], true)) {
        throw new Exception('Invalid plan mode');
    }

    // Check if ISP exists in `isps` table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM isps WHERE id = ? AND status = 'active'");
    $stmt->execute([$ispId]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Invalid ISP selected');
    }

    // Insert new plan
    $stmt = $pdo->prepare("INSERT INTO plans (isp_id, name, speed, price, planmode) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$ispId, $name, $speed, $price, $planmode]);

    if (!$success) {
        throw new Exception('Failed to add plan');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error in add_plan.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}