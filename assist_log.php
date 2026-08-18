<?php
/* =============================================================
   assist_log.php — 무엇을 물어보는지 기록
   ─────────────────────────────────────────────────────────────
   특히 kind=miss (답을 못 한 질문)가 중요합니다. 이걸 보고
   assist_flows.php 의 assist_faq() 에 답을 추가해 나가면
   챗봇이 점점 똑똑해집니다.
   기록을 원치 않으시면 아래 LOG_ON 을 false 로 바꾸세요.
   ============================================================= */
declare(strict_types=1);

const LOG_ON   = true;
const LOG_KEEP = 3000;                 // 최근 몇 건까지 보관할지
const LOG_FILE = __DIR__ . '/data/assist_log.json';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!LOG_ON || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => true]); exit;
}

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }

$kind = (string)($_POST['kind'] ?? '');
if (!in_array($kind, ['done', 'faq', 'miss', 'review'], true)) { echo json_encode(['ok' => false]); exit; }

$text = trim((string)($_POST['text'] ?? ''));
if (function_exists('mb_substr')) $text = mb_substr($text, 0, 200, 'UTF-8');
else                              $text = substr($text, 0, 400);

/* 같은 사람이 도배하지 못하게 — 한 세션에서 시간당 60건까지 */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$uid = trim((string)($_SESSION['member_id'] ?? ''));
if ($kind === 'review' && ($uid === '' || empty($_SESSION['is_user']))) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'login']); exit;
}
$now = time();
$hits = $_SESSION['assist_hits'] ?? [];
$hits = array_values(array_filter($hits, fn($t) => $t > $now - 3600));
if (count($hits) >= 60) { echo json_encode(['ok' => false, 'error' => 'rate']); exit; }
$hits[] = $now;
$_SESSION['assist_hits'] = $hits;

$dir = dirname(LOG_FILE);
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$all = [];
if (is_file(LOG_FILE)) {
  $a = json_decode((string)@file_get_contents(LOG_FILE), true);
  if (is_array($a)) $all = $a;
}

$row = [
  'at'   => date('Y-m-d H:i:s'),
  'kind' => $kind,
  'text' => $text,
];
if ($uid !== '') {
  $row['uid'] = $uid;
  $row['nickname'] = trim((string)($_SESSION['nickname'] ?? ''));
}
if ($kind === 'review') {
  $row['id'] = date('YmdHis') . '-' . bin2hex(random_bytes(3));
  $row['status'] = 'pending';
}
$all[] = $row;

if (count($all) > LOG_KEEP) $all = array_slice($all, -LOG_KEEP);

$tmp = LOG_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
if (@file_put_contents($tmp, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
  @rename($tmp, LOG_FILE);
} else {
  @unlink($tmp);
}

echo json_encode(['ok' => true]);
