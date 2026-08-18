<?php
/* =============================================================
   train_photo.php — 훈련·교육 사진 내려주기
   ─────────────────────────────────────────────────────────────
   data/train/ 아래 사진은 웹에서 직접 열 수 없게 막아두므로,
   이 파일이 권한을 확인한 뒤 대신 전달합니다.

   · 회원 본인 → 자기 사진만
   · 관리자    → 회원 서류 열람용으로 ?uid= 를 붙여 다른 회원 사진도
   ============================================================= */
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function tp_is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!tp_is_admin() && empty($_SESSION['is_user'])) { http_response_code(403); exit; }

/* 회원 폴더 이름 — train_db.php 와 같은 규칙.
   train_db.php 를 부르지 않아도 되도록 여기서 직접 구합니다. */
function tp_own_key(): string {
  if (function_exists('app_user_key')) {                 // user_key.php 를 쓰는 경우
    $k = app_user_key();
    if ($k !== '') return $k;
  }
  $k = $_SESSION['member_id'] ?? '';
  if (trim((string)$k) === '') {
    $kk = trim((string)($_SESSION['kakao_id'] ?? ''));
    $k = $kk !== '' ? 'kakao_' . $kk : '';
  }
  return preg_replace('/[^A-Za-z0-9_-]/', '', (string)$k);
}

$file = basename((string)($_GET['f'] ?? ''));
if ($file === '' || $file === '.' || $file === '..') { http_response_code(404); exit; }

if (tp_is_admin() && isset($_GET['uid'])) {
  $uid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_GET['uid']);
} else {
  $uid = tp_own_key();
}
if ($uid === '') { http_response_code(403); exit; }

$path = __DIR__ . '/data/train/' . $uid . '/photos/' . $file;

/* 폴더 밖으로 빠져나가려는 주소는 막습니다 */
$real = @realpath($path);
$base = @realpath(__DIR__ . '/data/train');
if ($real === false || $base === false || strpos($real, $base) !== 0) { http_response_code(404); exit; }

$info = @getimagesize($real);
if (!$info) { http_response_code(404); exit; }

$mime = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png',
         IMAGETYPE_GIF  => 'image/gif',  IMAGETYPE_WEBP => 'image/webp'][$info[2]] ?? '';
if ($mime === '') { http_response_code(404); exit; }

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($real));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($real);
