<?php
// /save.php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/inc/util.php';

if (($_POST['website'] ?? '') !== '') { // honeypot
  http_response_code(400);
  exit('봇 의심으로 차단되었습니다.');
}

$title = trim($_POST['title'] ?? '');
$region = trim($_POST['region'] ?? '');
$distance = floatval($_POST['distance_km'] ?? 0);
$diff = $_POST['difficulty'] ?? 'moderate';
$tags = trim($_POST['tags'] ?? '');
$body = trim($_POST['body'] ?? '');

if ($title === '' || $region === ''){
  http_response_code(400);
  exit('필수 항목 누락');
}

$id = make_id();
$now = round(microtime(true) * 1000);
$row = [
  'id' => $id,
  'title' => $title,
  'region' => $region,
  'distance_km' => $distance,
  'difficulty' => $diff,
  'tags' => $tags,
  'body' => $body,
  'likes' => 0,
  'created_at' => date('Y-m-d H:i:s'),
  'created_ts' => $now,
  'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '')
];

$dir = __DIR__ . '/data/posts';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$path = $dir . '/' . $id . '.json';

file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
header('Location: view.php?id='.$id);
