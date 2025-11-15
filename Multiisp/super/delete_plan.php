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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    if (!$pdo) {
        throw new Exception('Database connection error');
    }

    $planId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($planId === false || $planId === null) {
        throw new Exception('Invalid plan ID');
    }

    // Check if plan exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Plan not found');
    }

    // Delete plan
    $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
    $success = $stmt->execute([$planId]);

    if (!$success) {
        throw new Exception('Failed to delete plan');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error in delete_plan.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}