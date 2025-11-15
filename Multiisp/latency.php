<?php
header('Content-Type: application/json; charset=utf-8');

// target host (change if you want local gateway or ISP host)
$target = isset($_GET['host']) && preg_match('/^[0-9A-Za-z\.\-]+$/', $_GET['host']) ? $_GET['host'] : '8.8.8.8';
$attempts = 4;

// choose ping command depending on platform
if (stripos(PHP_OS, 'WIN') === 0) {
    $cmd = sprintf('ping -n %d %s', $attempts, escapeshellarg($target));
} else {
    $cmd = sprintf('ping -c %d %s', $attempts, escapeshellarg($target));
}

exec($cmd, $output, $rc);

// parse times like "time=12ms" or "time=12.3 ms"
$times = [];
foreach ($output as $line) {
    if (preg_match('/time[=<]?\s*([0-9]+(?:\.[0-9]+)?)\s*ms/i', $line, $m)) {
        $times[] = (float)$m[1];
    }
}

if (count($times) === 0) {
    echo json_encode(['ok' => false, 'error' => 'no_response', 'raw' => array_slice($output, -6)]);
    exit;
}

$avg = array_sum($times) / count($times);
$min = min($times);
$max = max($times);

// simple jitter estimate = standard deviation of samples
$sumSq = 0.0;
foreach ($times as $t) $sumSq += ($t - $avg) * ($t - $avg);
$std = sqrt($sumSq / count($times));

echo json_encode([
    'ok' => true,
    'host' => $target,
    'samples' => count($times),
    'latency_ms' => round($avg, 1),
    'min_ms' => round($min, 1),
    'max_ms' => round($max, 1),
    'jitter_ms' => round($std, 1),
]);