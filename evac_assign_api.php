<?php
/**
 * evac_assign_api.php — 시뮬레이션 모델 ↔ 회원 배정 API (관리자 전용)
 *
 *   GET  ?act=list&uid=회원아이디        → 해당 회원에 배정된 모델 목록
 *   POST act=assign   uid, model_id, name, map, scenario(json), csrf
 *        → 모델을 서버에 스냅샷 저장하고 회원에 연결
 *   POST act=unassign uid, model_id, csrf
 *        → 연결 해제 (다른 회원이 안 쓰면 모델 파일도 삭제)
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
function request_text($value, int $max): string {
  $value = trim((string)$value);
  return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

$act = (string)($_REQUEST['act'] ?? '');

/* ═══ 회원용: 시뮬레이션 배정 요청 ═══
   배정을 아직 못 받은 회원이 '요청' 버튼을 누르면 여기로 온다.
   관리자 권한 검사보다 앞에 있어야 한다. */
if ($act === 'request' || $act === 'request_state') {
  $uid = evac_current_uid();
  if ($uid === '') out(['ok' => false, 'error' => '로그인이 필요합니다.']);

  $reqs = evac_load_requests();

  if ($act === 'request_state') {
    out(['ok' => true, 'requested' => isset($reqs[$uid]), 'request' => $reqs[$uid] ?? null]);
  }

  /* 쓰기 — POST 만 */
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') out(['ok' => false, 'error' => 'POST로 요청하세요.']);
  if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    out(['ok' => false, 'error' => '잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.']);
  }
  if (evac_models_for($uid)) out(['ok' => false, 'error' => '이미 시뮬레이션이 배정되어 있습니다.']);

  $buildingName = request_text($_POST['building_name'] ?? '', 120);
  $address = request_text($_POST['address'] ?? '', 200);
  $managerName = request_text($_POST['manager_name'] ?? '', 60);
  $managerPhone = request_text($_POST['manager_phone'] ?? '', 30);
  $phoneDigits = preg_replace('/[^0-9]/', '', $managerPhone);

  if ($address === '') out(['ok' => false, 'error' => '건물 주소를 확인해 주세요.']);
  if ($managerName === '') out(['ok' => false, 'error' => '소방안전관리자 이름을 확인해 주세요.']);
  if (strlen($phoneDigits) < 8 || strlen($phoneDigits) > 11) {
    out(['ok' => false, 'error' => '연락 가능한 전화번호를 확인해 주세요.']);
  }

  $now = date('Y-m-d H:i:s');
  $request = [
    'at' => $now,
    'building_name' => $buildingName,
    'address' => $address,
    'manager_name' => $managerName,
    'manager_phone' => $managerPhone,
  ];
  if (!evac_store_request($uid, $request)) out(['ok' => false, 'error' => '요청을 저장하지 못했습니다.']);

  /* 텔레그램 알림 (설정돼 있으면) */
  $tg = __DIR__ . '/telegram_config.php';
  if (is_file($tg)) {
    @include_once $tg;
    if (function_exists('send_telegram')) {
      $safe = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      @send_telegram(
        "<b>피난 시뮬레이션 배정 요청</b>\n"
        . '회원: <code>' . $safe($uid) . "</code>\n"
        . '건물: ' . $safe($buildingName !== '' ? $buildingName : '-') . "\n"
        . '주소: ' . $safe($address) . "\n"
        . '안전관리자: ' . $safe($managerName) . "\n"
        . '연락처: ' . $safe($managerPhone)
      );
    }
  }
  out(['ok' => true, 'requested' => true, 'request' => $request, 'updated' => isset($reqs[$uid])]);
}

if (!evac_is_admin()) out(['ok' => false, 'error' => '관리자 권한이 필요합니다.']);

/* ── 조회 ── */
if ($act === 'list') {
  $uid = trim((string)($_GET['uid'] ?? ''));
  if ($uid === '') out(['ok' => false, 'error' => 'uid가 없습니다.']);
  out(['ok' => true, 'uid' => $uid, 'models' => evac_models_for($uid)]);
}

/* 이하 쓰기 작업 — POST + CSRF */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') out(['ok' => false, 'error' => 'POST로 요청하세요.']);
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
  out(['ok' => false, 'error' => '잘못된 요청입니다(CSRF).']);
}

$uid = trim((string)($_POST['uid'] ?? ''));
$mid = evac_clean_id((string)($_POST['model_id'] ?? ''));
if ($uid === '' || $mid === '') out(['ok' => false, 'error' => 'uid / model_id가 없습니다.']);

/* ── 배정 ── */
if ($act === 'assign') {
  /* 보관함에 이미 있는 도면만 연결한다.
     도면 본문은 복사하지 않으므로, 관리자가 수정하면 즉시 반영된다. */
  if (!evac_load_model($mid)) {
    out(['ok' => false, 'error' => '보관함에 없는 도면입니다. 시뮬레이터에서 먼저 저장하세요.']);
  }

  $assign = evac_load_assign();
  $list = $assign[$uid] ?? [];
  if (!in_array($mid, $list, true)) $list[] = $mid;
  $assign[$uid] = array_values($list);
  if (!evac_save_assign($assign)) out(['ok' => false, 'error' => '배정 저장에 실패했습니다.']);

  /* 배정되면 대기 중이던 요청을 지운다 */
  evac_remove_request($uid);

  /* 배정하면 공유도 함께 켠다 → 회원이 QR 을 받아 바로 쓸 수 있다 */
  $m = evac_load_model($mid);
  if ($m && empty($m['share'])) { $m['share'] = true; evac_save_model($m); }

  out(['ok' => true, 'models' => evac_models_for($uid), 'shared' => true]);
}

/* ── 해제 ── */
if ($act === 'unassign') {
  $assign = evac_load_assign();
  $assign[$uid] = array_values(array_filter($assign[$uid] ?? [], fn($x) => $x !== $mid));
  if (!$assign[$uid]) unset($assign[$uid]);
  if (!evac_save_assign($assign)) out(['ok' => false, 'error' => '저장에 실패했습니다.']);

  /* 도면 파일은 보관함 자산이므로 지우지 않는다.
     (삭제는 보관함 화면에서만 가능) */

  /* 아무에게도 배정돼 있지 않으면 공유를 끈다 → QR 주소가 더는 열리지 않는다 */
  $stillUsed = false;
  foreach ($assign as $ids) {
    if (is_array($ids) && in_array($mid, $ids, true)) { $stillUsed = true; break; }
  }
  if (!$stillUsed) {
    $m = evac_load_model($mid);
    if ($m && !empty($m['share'])) { $m['share'] = false; evac_save_model($m); }
  }

  out(['ok' => true, 'models' => evac_models_for($uid)]);
}

out(['ok' => false, 'error' => '알 수 없는 요청입니다.']);
