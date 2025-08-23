<?php
// Simple script to save map drafts with basic rate limiting
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit = 5; // requests per minute per IP
$window = 60; // seconds
$rateFile = sys_get_temp_dir() . '/draft_rate_' . md5($ip) . '.json';

// Read rate limit file
$rateData = ['time' => time(), 'count' => 0];
if (file_exists($rateFile)) {
    $json = file_get_contents($rateFile);
    if ($json !== false) {
        $rateData = json_decode($json, true) ?: $rateData;
    }
}

if ($rateData['time'] > time() - $window) {
    if ($rateData['count'] >= $limit) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    $rateData['count']++;
} else {
    $rateData = ['time' => time(), 'count' => 1];
}
file_put_contents($rateFile, json_encode($rateData));

$body = file_get_contents('php://input');
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'No data provided']);
    exit;
}

$draftDir = __DIR__ . '/drafts';
if (!is_dir($draftDir)) {
    mkdir($draftDir, 0777, true);
}

$id = bin2hex(random_bytes(8));
$filePath = $draftDir . '/' . $id . '.json';
file_put_contents($filePath, $body);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$url = $scheme . '://' . $host . $base . '/save/' . $id;
echo json_encode(['url' => $url]);
