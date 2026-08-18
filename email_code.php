<?php
/**
 * email_code.php — 이메일 인증번호 발송 / 확인 API
 *   POST action=send   { email }        → 6자리 코드 메일 발송
 *   POST action=verify { email, code }  → 코드 확인 → 세션에 인증 완료 표시
 *
 * 인증 결과는 $_SESSION['email_verified'][이메일] = true 로 저장되며,
 * signup.php가 가입 시 이 값을 확인한다.
 */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

require_once __DIR__ . '/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
  out(['ok' => false, 'msg' => '잘못된 요청입니다. 새로고침 후 다시 시도하세요.']);
}

$action = $_POST['action'] ?? '';
$email  = strtolower(trim((string)($_POST['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  out(['ok' => false, 'msg' => '올바른 이메일 주소를 입력하세요.']);
}

/* ── 인증번호 발송 ── */
if ($action === 'send') {
  // 이미 가입된 이메일인지 확인
  $mf = __DIR__ . '/data/members.json';
  if (file_exists($mf)) {
    $raw = @file_get_contents($mf);
    $members = json_decode($raw ?: '', true);
    if (is_array($members)) {
      foreach ($members as $m) {
        if (strcasecmp($m['email'] ?? '', $email) === 0) {
          out(['ok' => false, 'msg' => '이미 가입된 이메일입니다.']);
        }
      }
    }
  }

  // 재발송 제한 (60초에 1번)
  $last = $_SESSION['email_code_sent_at'][$email] ?? 0;
  if (time() - $last < 60) {
    $wait = 60 - (time() - $last);
    out(['ok' => false, 'msg' => "잠시 후 다시 시도하세요. ({$wait}초)"]);
  }

  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $_SESSION['email_codes'][$email] = [
    'code'    => $code,
    'expires' => time() + 300,   // 5분 유효
    'tries'   => 0,
  ];
  $_SESSION['email_code_sent_at'][$email] = time();

  $body = "안녕하세요, TWORIX입니다.\n\n"
        . "회원가입 인증번호는 다음과 같습니다.\n\n"
        . "        {$code}\n\n"
        . "인증번호는 5분간 유효합니다.\n"
        . "본인이 요청하지 않았다면 이 메일을 무시하세요.";

  if (send_mail($email, '[TWORIX] 회원가입 인증번호', $body)) {
    out(['ok' => true, 'msg' => '인증번호를 보냈습니다. 메일함(스팸함 포함)을 확인해 주세요.']);
  }
  out(['ok' => false, 'msg' => '메일 발송에 실패했습니다. 잠시 후 다시 시도하세요.']);
}

/* ── 인증번호 확인 ── */
if ($action === 'verify') {
  $code = preg_replace('/[^0-9]/', '', trim($_POST['code'] ?? ''));
  $rec  = $_SESSION['email_codes'][$email] ?? null;

  if (!$rec) {
    out(['ok' => false, 'msg' => '먼저 인증번호를 받아주세요.']);
  }
  if (($rec['expires'] ?? 0) < time()) {
    unset($_SESSION['email_codes'][$email]);
    out(['ok' => false, 'msg' => '인증번호가 만료되었습니다. 다시 받아주세요.']);
  }
  if (($rec['tries'] ?? 0) >= 5) {
    unset($_SESSION['email_codes'][$email]);
    out(['ok' => false, 'msg' => '시도 횟수를 초과했습니다. 인증번호를 다시 받아주세요.']);
  }

  $_SESSION['email_codes'][$email]['tries'] = ($rec['tries'] ?? 0) + 1;

  if (!hash_equals((string)$rec['code'], $code)) {
    $left = 5 - $_SESSION['email_codes'][$email]['tries'];
    out(['ok' => false, 'msg' => "인증번호가 일치하지 않습니다. (남은 시도 {$left}회)"]);
  }

  // 인증 성공
  unset($_SESSION['email_codes'][$email]);
  $_SESSION['email_verified'][$email] = true;
  out(['ok' => true, 'msg' => '이메일 인증이 완료되었습니다.']);
}

out(['ok' => false, 'msg' => '알 수 없는 요청입니다.']);
