<?php
/**
 * evac_library_api.php — 도면 보관함 (관리자 전용)
 *
 *   GET  ?act=list                      → 보관함 전체 목록 (도면 본문 제외)
 *   GET  ?act=get&id=모델ID             → 도면 1개 전문
 *   POST act=save   id, name, map, scenario(json), csrf
 *        → 신규 저장 또는 덮어쓰기 (id 비우면 새로 발급)
 *   POST act=delete id, csrf
 *        → 삭제 (배정된 회원이 있으면 거부)
 *
 * 저장 위치: data/evac_models/<id>.json  (evac_assign_api.php와 공용)
 */
declare(strict_types=1);

/* admin_members.php와 동일한 세션 설정 (같은 세션을 봐야 함) */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseDomain = preg_match('/([^.]+\.[^.]+)$/', $host, $m) ? $m[1] : $host;
$cookieDomain = ($host === 'localhost') ? '' : ('.' . $baseDomain);
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL, 'path' => '/', 'domain' => $cookieDomain,
    'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
  ]);
}
session_start();

require_once __DIR__ . '/evac_common.php';

header('Content-Type: application/json; charset=utf-8');
function out(array $j): void { echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

/* 보관함은 관리자만 다룬다 */
if (!evac_is_admin()) out(['ok' => false, 'error' => '관리자 권한이 필요합니다.']);

$act = (string)($_REQUEST['act'] ?? '');

/* 문자열 자르기 (mbstring 없어도 동작) */
function lib_cut(string $s, int $n): string {
  return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n * 3);
}

/* ── 보관함 목록 (도면 본문은 빼고 요약만) ── */
if ($act === 'list') {
  $out = [];
  $assign = evac_load_assign();

  foreach (glob(EVAC_MODEL_DIR . '/*.json') ?: [] as $path) {
    $m = json_decode((string)@file_get_contents($path), true);
    if (!is_array($m) || empty($m['id'])) continue;

    /* 이 도면을 배정받은 회원 수 */
    $used = 0;
    foreach ($assign as $ids) {
      if (is_array($ids) && in_array($m['id'], $ids, true)) $used++;
    }

    $out[] = [
      'id'       => (string)$m['id'],
      'name'     => (string)($m['name'] ?? '이름 없는 건물'),
      'updated'  => (int)($m['updated'] ?? 0),
      'scenario' => is_array($m['scenario'] ?? null) ? $m['scenario'] : [],
      'used'     => $used,
      'bytes'    => strlen((string)($m['map'] ?? '')),
    ];
  }

  /* 최근 수정순 */
  usort($out, fn($a, $b) => $b['updated'] <=> $a['updated']);
  out(['ok' => true, 'models' => $out]);
}

/* ── 도면 1개 전문 ── */
if ($act === 'get') {
  $id = evac_clean_id((string)($_GET['id'] ?? ''));
  if ($id === '') out(['ok' => false, 'error' => 'id가 없습니다.']);
  $m = evac_load_model($id);
  if (!$m) out(['ok' => false, 'error' => '없는 도면입니다.']);
  out(['ok' => true, 'model' => $m]);
}

/* 이하 쓰기 작업 — POST + CSRF */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  out(['ok' => false, 'error' => 'POST로 요청하세요.']);
}
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
  out(['ok' => false, 'error' => '잘못된 요청입니다(CSRF).']);
}

/* ── 저장 (신규 / 덮어쓰기) ── */
if ($act === 'save') {
  $id = evac_clean_id((string)($_POST['id'] ?? ''));
  if ($id === '') {
    /* 새 도면: 충돌하지 않는 ID 발급 */
    do { $id = 'm' . base_convert((string)random_int(100000, 999999), 10, 36) . dechex(time() % 65536); }
    while (file_exists(evac_model_path($id)));
  }

  $name = trim((string)($_POST['name'] ?? '')) ?: '이름 없는 건물';
  $map  = (string)($_POST['map'] ?? '');
  if ($map === '') out(['ok' => false, 'error' => '도면 데이터가 비어 있습니다.']);
  if (strlen($map) > 2 * 1024 * 1024) out(['ok' => false, 'error' => '도면이 너무 큽니다(2MB 초과).']);

  $scenario = json_decode((string)($_POST['scenario'] ?? ''), true);
  if (!is_array($scenario)) $scenario = [];
  $scenario = [
    'people' => max(1,   min(300, (int)($scenario['people'] ?? 60))),
    'spread' => max(0.2, min(3.0, (float)($scenario['spread'] ?? 1.0))),
    'speed'  => max(0.5, min(2.0, (float)($scenario['speed']  ?? 1.0))),
  ];

  /* 만든 시각은 최초 저장 때만 기록 */
  $prev    = evac_load_model($id);
  $created = ($prev && !empty($prev['created'])) ? (int)$prev['created'] : time() * 1000;

  $ok = evac_save_model([
    'id'       => $id,
    'name'     => lib_cut($name, 120),
    'map'      => $map,
    'scenario' => $scenario,
    'created'  => $created,
    'updated'  => time() * 1000,
    'author'   => (string)($_SESSION['nickname'] ?? 'admin'),
  ]);
  if (!$ok) out(['ok' => false, 'error' => '저장에 실패했습니다. data 폴더 쓰기 권한을 확인하세요.']);

  out(['ok' => true, 'id' => $id, 'saved' => date('H:i')]);
}

/* ── 삭제 ── */
if ($act === 'delete') {
  $id = evac_clean_id((string)($_POST['id'] ?? ''));
  if ($id === '') out(['ok' => false, 'error' => 'id가 없습니다.']);

  /* 배정 중인 도면은 지우지 않는다 (회원 화면이 깨지므로) */
  $assign = evac_load_assign();
  $users  = [];
  foreach ($assign as $uid => $ids) {
    if (is_array($ids) && in_array($id, $ids, true)) $users[] = (string)$uid;
  }
  if ($users) {
    out(['ok' => false,
         'error' => '이 도면을 배정받은 회원이 ' . count($users) . '명 있습니다. 먼저 배정을 해제하세요.',
         'users' => $users]);
  }

  $p = evac_model_path($id);
  if (file_exists($p) && !@unlink($p)) out(['ok' => false, 'error' => '삭제에 실패했습니다.']);
  out(['ok' => true]);
}

/* ── 공유 켜기/끄기 ── (QR 공개 열람용)
   share=true 인 모델만 evac_view.php 로 로그인 없이 열람된다. */
if ($act === 'share') {
  $id = evac_clean_id((string)($_POST['id'] ?? ''));
  if ($id === '') out(['ok' => false, 'error' => 'id가 없습니다.']);
  $m = evac_load_model($id);
  if (!$m) out(['ok' => false, 'error' => '없는 도면입니다.']);
  $on = !empty($_POST['on']) && $_POST['on'] !== '0' && $_POST['on'] !== 'false';
  $m['share'] = $on;
  if (!evac_save_model($m)) out(['ok' => false, 'error' => '저장에 실패했습니다.']);
  out(['ok' => true, 'share' => $on, 'id' => $id]);
}

out(['ok' => false, 'error' => '알 수 없는 요청입니다.']);
