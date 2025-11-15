<?php
// Simple HTTP ping: returns server timestamp — client measures RTT and jitter
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'server_ts' => microtime(true)]);