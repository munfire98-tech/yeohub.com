<?php
/**
 * signup.php - index.php 로그인/회원가입 팝업의 서버 처리 전용
 *
 * GET  ?check_uid=... : 아이디 사용 가능 여부
 * POST mode=signup    : 회원가입
 * POST mode=login     : 일반회원 로그인
 */
declare(strict_types=1);

$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
if (PHP_VERSION_ID >= 70300) {
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

const USER_ID_PATTERN = '/^[a-z][a-z0-9_]{3,19}$/';
const USER_PASSWORD_MIN = 8;
const USER_PASSWORD_MAX_BYTES = 64;

$MEMBERS_FILE = __DIR__ . '/data/members.json';

function normalize_role(string $role): string {
  return $role === 'building' ? 'building' : 'agency';
}

/* 유형별 업무페이지 주소.
   ※ 로그인·가입 직후에는 더 이상 여기로 보내지 않고 메인(index.php)에 머무릅니다.
      사용자가 상단 아이콘으로 직접 들어가는 방식입니다.
      다른 곳에서 쓸 수 있어 함수는 남겨둡니다. */
function role_landing(string $role): string {
  return $role === 'building' ? '/building_manager.php' : '/clients_mini.php';
}

function normalize_userid(string $userid): string {
  return strtolower(trim($userid));
}

function normalize_email(string $email): string {
  return strtolower(trim($email));
}

function text_length(string $value): int {
  return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function load_members(string $file, ?bool &$valid = null): array {
  $valid = true;
  if (!is_file($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false) {
    $valid = false;
    return [];
  }
  if (trim($raw) === '') return [];
  $members = json_decode($raw, true);
  if (!is_array($members)) {
    $valid = false;
    return [];
  }
  return $members;
}

function write_members_unlocked(string $file, array $members): bool {
  $dir = dirname($file);
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;

  $json = json_encode($members, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;

  $tmp = @tempnam($dir, '.members_');
  if ($tmp === false) return false;
  $ok = @file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $file);
  if (!$ok && is_file($tmp)) @unlink($tmp);
  return $ok;
}

/** 잠금 안에서 members.json을 다시 읽고 검사한 뒤 한 번에 저장합니다. */
function members_transaction(string $file, callable $callback): array {
  $dir = dirname($file);
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    return ['ok' => false, 'error' => '회원 저장 폴더를 사용할 수 없습니다.'];
  }

  $lock = @fopen($file . '.lock', 'c+');
  if ($lock === false || !@flock($lock, LOCK_EX)) {
    if (is_resource($lock)) @fclose($lock);
    return ['ok' => false, 'error' => '회원 정보를 잠시 사용할 수 없습니다.'];
  }

  try {
    $valid = true;
    $members = load_members($file, $valid);
    if (!$valid) return ['ok' => false, 'error' => '회원 정보 파일을 읽을 수 없습니다. 관리자에게 문의해 주세요.'];
    $result = $callback($members);
    if (!is_array($result)) $result = ['ok' => false];
    if (!empty($result['save']) && !write_members_unlocked($file, $members)) {
      $result = ['ok' => false, 'error' => '회원 정보 저장에 실패했습니다.'];
    }
    return $result;
  } finally {
    @flock($lock, LOCK_UN);
    @fclose($lock);
  }
}

function find_member_key_ci(array $members, string $userid): ?string {
  foreach ($members as $key => $member) {
    $stored = (string)($member['userid'] ?? $key);
    if (strcasecmp($stored, $userid) === 0 || strcasecmp((string)$key, $userid) === 0) {
      return (string)$key;
    }
  }
  return null;
}

function member_email_taken(array $members, string $email): bool {
  if ($email === '') return false;
  foreach ($members as $member) {
    if (strcasecmp((string)($member['email'] ?? ''), $email) === 0) return true;
  }
  return false;
}

function userid_is_reserved(string $userid): bool {
  $userid = normalize_userid($userid);
  if ($userid === 'mhg1234' || strncmp($userid, 'kakao_', 6) === 0) return true;
  return preg_match('/^(admin|administrator|root|support|system|tworix)(_|$)/', $userid) === 1;
}

function password_is_common(string $password, string $userid, string $email): bool {
  $value = strtolower(trim($password));
  $local = strtolower((string)strtok($email, '@'));
  $blocked = [
    'password', 'password1', 'password1234', 'password123!',
    'qwerty12', 'qwerty123456', 'qwer1234', 'qwer12345678',
    '12345678', '123456789012', '11111111', '111111111111',
    'abcd1234', 'tworix12', 'tworix123456',
  ];
  return in_array($value, $blocked, true)
      || $value === strtolower($userid)
      || ($local !== '' && $value === $local);
}

function json_out(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* 아이디 중복확인 API */
if (isset($_GET['check_uid'])) {
  $now = time();
  $hits = array_values(array_filter((array)($_SESSION['uidchk'] ?? []), static fn($t) => (int)$t > $now - 60));
  if (count($hits) >= 30) {
    json_out(['ok' => false, 'code' => 'rate_limited', 'msg' => '확인 요청이 많습니다. 잠시 후 다시 시도해 주세요.'], 429);
  }
  $hits[] = $now;
  $_SESSION['uidchk'] = $hits;

  $userid = normalize_userid((string)$_GET['check_uid']);
  if (!preg_match(USER_ID_PATTERN, $userid)) {
    json_out(['ok' => false, 'code' => 'invalid', 'msg' => '영문으로 시작하는 영문·숫자·밑줄 4~20자로 입력해 주세요.'], 422);
  }

  $valid = true;
  $members = load_members($MEMBERS_FILE, $valid);
  if (!$valid) {
    json_out(['ok' => false, 'code' => 'storage_error', 'msg' => '회원 정보를 확인할 수 없습니다. 잠시 후 다시 시도해 주세요.'], 503);
  }
  $taken = userid_is_reserved($userid) || find_member_key_ci($members, $userid) !== null;
  json_out(['ok' => true, 'taken' => $taken, 'value' => $userid]);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = (string)$_SESSION['csrf'];
$error = '';
$mode = (string)($_POST['mode'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $error = '잘못된 요청입니다. 새로고침 후 다시 시도하세요.';
  } elseif ($mode === 'signup') {
    $uidRaw = trim((string)($_POST['userid'] ?? ''));
    $uid = normalize_userid($uidRaw);
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $nickname = trim((string)($_POST['nickname'] ?? ''));
    $roleRaw = trim((string)($_POST['role'] ?? ''));
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $email = normalize_email($emailRaw);
    $phone = preg_replace('/[^0-9]/', '', trim((string)($_POST['phone'] ?? '')));

    if (!preg_match(USER_ID_PATTERN, $uid)) {
      $error = '아이디는 영문으로 시작하는 영문·숫자·밑줄 4~20자로 입력하세요.';
    } elseif (userid_is_reserved($uid)) {
      $error = '사용할 수 없는 아이디입니다. 다른 아이디를 입력해 주세요.';
    } elseif (text_length($password) < USER_PASSWORD_MIN) {
      $error = '비밀번호는 8자 이상이어야 합니다.';
    } elseif (strlen($password) > USER_PASSWORD_MAX_BYTES) {
      $error = '비밀번호가 너무 깁니다. 64바이트 이내로 입력해 주세요.';
    } elseif ($password !== $password2) {
      $error = '비밀번호 확인이 일치하지 않습니다.';
    } elseif (password_is_common($password, $uid, $email)) {
      $error = '추측하기 쉬운 비밀번호입니다. 더 긴 문장이나 서로 관련 없는 단어를 사용해 주세요.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = '올바른 이메일 주소를 입력하세요.';
    } elseif (empty($_SESSION['email_verified'][$email]) && empty($_SESSION['email_verified'][$emailRaw])) {
      $error = '이메일 인증을 완료해 주세요.';
    } elseif ($phone !== '' && !preg_match('/^01[016789][0-9]{7,8}$/', $phone)) {
      $error = '휴대폰 번호 형식이 올바르지 않습니다. (예: 01012345678)';
    } elseif ($nickname !== '' && (text_length($nickname) < 2 || text_length($nickname) > 20)) {
      $error = '닉네임은 2~20자로 입력해 주세요.';
    } elseif (!in_array($roleRaw, ['building', 'agency'], true)) {
      $error = '사용자 유형을 선택해 주세요.';
    } else {
      $role = normalize_role($roleRaw);
      if ($nickname === '') $nickname = $uid;
      $created = date('Y-m-d H:i:s');
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);

      if ($passwordHash === false) {
        $error = '비밀번호 처리에 실패했습니다. 잠시 후 다시 시도하세요.';
      } else {
        $result = members_transaction($MEMBERS_FILE, static function (array &$members) use (
          $uid, $nickname, $role, $email, $phone, $created, $passwordHash
        ): array {
          if (find_member_key_ci($members, $uid) !== null) {
            return ['ok' => false, 'error' => '이미 사용 중인 아이디입니다.'];
          }
          if (member_email_taken($members, $email)) {
            return ['ok' => false, 'error' => '이미 가입된 이메일입니다.'];
          }
          $members[$uid] = [
            'userid' => $uid,
            'nickname' => $nickname,
            'role' => $role,
            'email' => $email,
            'email_ok' => true,
            'phone' => $phone,
            'phone_ok' => false,
            'joined_via' => 'signup',
            'status' => 'active',
            'created' => $created,
            'last_login' => $created,
            'pw_hash' => $passwordHash,
          ];
          return ['ok' => true, 'save' => true];
        });

        if (empty($result['ok'])) {
          $error = (string)($result['error'] ?? '저장에 실패했습니다. 잠시 후 다시 시도하세요.');
        } else {
          unset($_SESSION['email_verified'][$email], $_SESSION['email_verified'][$emailRaw]);

          require_once __DIR__ . '/telegram_config.php';
          $roleKo = $role === 'building' ? '건물 소방안전관리자' : '소방 대행업체';
          $tg = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          @send_telegram(
            "<b>TWORIX 새 회원 가입</b>\n\n"
            . '아이디: <code>' . $tg($uid) . "</code>\n"
            . '닉네임: ' . $tg($nickname) . "\n"
            . '유형: ' . $tg($roleKo) . "\n"
            . '이메일: ' . $tg($email) . "\n"
            . ($phone !== '' ? '연락처: ' . $tg($phone) . "\n" : '')
            . '가입시각: ' . $created
          );

          session_regenerate_id(true);
          $_SESSION['is_admin'] = false;
          $_SESSION['ID_OK'] = 0;
          $_SESSION['is_user'] = true;
          $_SESSION['member_id'] = $uid;
          $_SESSION['nickname'] = $nickname;
          $_SESSION['role'] = $role;
          $_SESSION['login_type'] = 'member';
          header('Location: /index.php');
          exit;
        }
      }
    }
  } elseif ($mode === 'login') {
    $userid = normalize_userid((string)($_POST['userid'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $now = time();
    $failures = array_values(array_filter((array)($_SESSION['login_failures'] ?? []), static fn($t) => (int)$t > $now - 600));

    if (count($failures) >= 10) {
      $error = '로그인 시도가 많습니다. 잠시 후 다시 시도해 주세요.';
    } else {
      $result = members_transaction($MEMBERS_FILE, static function (array &$members) use ($userid, $password): array {
        $memberKey = find_member_key_ci($members, $userid);
        if ($memberKey === null || !password_verify($password, (string)($members[$memberKey]['pw_hash'] ?? ''))) {
          return ['ok' => false, 'code' => 'invalid'];
        }
        if (($members[$memberKey]['status'] ?? 'active') === 'banned') {
          return ['ok' => false, 'code' => 'banned'];
        }
        $members[$memberKey]['last_login'] = date('Y-m-d H:i:s');
        return [
          'ok' => true,
          'save' => true,
          'uid' => $memberKey,
          'nickname' => (string)($members[$memberKey]['nickname'] ?? $memberKey),
          'role' => normalize_role((string)($members[$memberKey]['role'] ?? '')),
        ];
      });

      if (empty($result['ok'])) {
        if (($result['code'] ?? '') === 'banned') {
          $error = '이용이 제한된 계정입니다. 관리자에게 문의해 주세요.';
        } else {
          $failures[] = $now;
          $_SESSION['login_failures'] = $failures;
          $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
      } else {
        unset($_SESSION['login_failures']);
        $uid = (string)$result['uid'];
        $role = (string)$result['role'];
        session_regenerate_id(true);
        $_SESSION['is_admin'] = false;
        $_SESSION['ID_OK'] = 0;
        $_SESSION['is_user'] = true;
        $_SESSION['member_id'] = $uid;
        $_SESSION['nickname'] = (string)$result['nickname'];
        $_SESSION['role'] = $role;
        $_SESSION['login_type'] = 'member';
        header('Location: /index.php');
        exit;
      }
    }
  } else {
    $error = '지원하지 않는 요청입니다.';
  }

  $backTab = $mode === 'login' ? 'login' : 'signup';

  /* 오류로 되돌아갈 때, 입력해 둔 내용을 세션에 실어 보냅니다.
   * 예전에는 오류 문구만 넘겨서 팝업이 완전히 빈 채로 다시 떴고,
   * 휴대폰 형식 하나 틀렸다고 아이디·이메일·닉네임까지 다시 적어야 했습니다.
   * 비밀번호는 보안상 일부러 담지 않습니다. */
  if ($backTab === 'signup') {
    $_SESSION['signup_old'] = [
      'userid'   => (string)($_POST['userid'] ?? ''),
      'nickname' => (string)($_POST['nickname'] ?? ''),
      'email'    => (string)($_POST['email'] ?? ''),
      'phone'    => (string)($_POST['phone'] ?? ''),
      'role'     => (string)($_POST['role'] ?? ''),
    ];
  } else {
    $_SESSION['login_old'] = ['userid' => (string)($_POST['userid'] ?? '')];
  }

  header('Location: /index.php?auth=' . $backTab . '&err=' . rawurlencode($error));
  exit;
}

/* 직접 화면은 제공하지 않고 index.php 팝업만 사용합니다. */
header('Location: /index.php?auth=signup');
exit;
