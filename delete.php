<?php
/**
 * delete.php — 관리자 전용 + CSRF 검증
 */
declare(strict_types=1);
/* ── 30일 로그인 유지 ── */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

// 0) 관리자 권한 확인
if (empty($_SESSION['is_admin'])) {
  echo "<script>alert('관리자만 삭제할 수 있습니다.'); history.back();</script>";
  exit;
}

// 0-1) CSRF 토큰 검증
$csrf = $_GET['csrf'] ?? '';
if (!is_string($csrf) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo "<script>alert('잘못된 요청입니다(CSRF).'); history.back();</script>";
  exit;
}

// (선택) 유틸 로드
$util = __DIR__ . '/inc/util.php';
if (is_file($util)) require $util;

// 1) ID 확인 & 정제
$id = $_GET['id'] ?? '';
$id = preg_replace('/[^0-9A-Za-z_\-]/', '', $id);
if ($id === '') {
  echo "<script>alert('잘못된 요청입니다.'); history.back();</script>";
  exit;
}

// 2) 게시물 파일 경로 탐색
$DATA_DIR = __DIR__ . '/data/posts';
$targets = [];
$candidates = [
  "{$DATA_DIR}/{$id}.json",
  "{$DATA_DIR}/{$id}.txt",
  "{$DATA_DIR}/{$id}.dat",
  "{$DATA_DIR}/{$id}.md",
  "{$DATA_DIR}/{$id}"
];
foreach ($candidates as $path) { if (is_file($path)) $targets[] = $path; }
if (empty($targets)) {
  foreach (glob($DATA_DIR . '/' . $id . '.*') as $g) { if (is_file($g)) $targets[] = $g; }
}

// 3) 삭제 수행
if (empty($targets)) {
  echo "<script>alert('삭제할 파일을 찾지 못했습니다.'); history.back();</script>";
  exit;
}
$ok = true;
foreach ($targets as $t) {
  if (!@unlink($t)) { $ok = false; break; }
}

if ($ok) {
  echo "<script>alert('삭제되었습니다.'); location.href='/list.php';</script>";
} else {
  echo "<script>alert('일부 파일 삭제에 실패했습니다.'); history.back();</script>";
}
