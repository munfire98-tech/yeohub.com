<?php
/**
 * settings.php — 사용자 설정
 *
 *  내 정보 / 건물·거래처 / 이용 / 계정(로그아웃·탈퇴)을 한 곳에 모은다.
 *  회원 탈퇴는 맨 아래 '계정' 항목에서 withdraw.php 로 연결된다.
 */
declare(strict_types=1);

$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseDomain = preg_match('/([^.]+\.[^.]+)$/', $host, $m) ? $m[1] : $host;
$cookieDomain = ($host === 'localhost' || $host === '') ? '' : ('.' . $baseDomain);
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL, 'path' => '/', 'domain' => $cookieDomain,
    'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
  ]);
}
session_start();

$isMember = !empty($_SESSION['is_user']) || !empty($_SESSION['member_id']);
if (!$isMember) { header('Location: /index.php'); exit; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$DATA    = __DIR__ . '/data';
$MEMBERS = $DATA . '/members.json';

$uid     = (string)($_SESSION['member_id'] ?? '');
$kakaoId = (string)($_SESSION['kakao_id'] ?? '');
$nick    = (string)($_SESSION['nickname'] ?? '');
$role    = (string)($_SESSION['role'] ?? '');
$isKakao = ($_SESSION['login_type'] ?? '') === 'kakao' || $kakaoId !== '';

if ($uid === '') { header('Location: /index.php'); exit; }

function st_read(string $f): array {
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function st_write(string $f, array $a): bool {
  $d = dirname($f);
  if (!is_dir($d)) @mkdir($d, 0775, true);
  $t = $f . '.tmp';
  if (@file_put_contents($t, json_encode($a, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($t, $f);
}

$members = st_read($MEMBERS);
$me      = $members[$uid] ?? [];

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$msg = ''; $msgType = '';

/* ── 내 정보 저장 ── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'profile') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $msg = '잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.'; $msgType = 'err';
  } else {
    $newNick  = trim((string)($_POST['nickname'] ?? ''));
    $newEmail = trim((string)($_POST['email'] ?? ''));
    $newPhone = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));

    if ($newNick === '') {
      $msg = '닉네임을 입력해 주세요.'; $msgType = 'err';
    } elseif ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $msg = '이메일 형식이 올바르지 않습니다.'; $msgType = 'err';
    } elseif ($newPhone !== '' && (strlen($newPhone) < 9 || strlen($newPhone) > 11)) {
      $msg = '휴대폰 번호를 다시 확인해 주세요.'; $msgType = 'err';
    } else {
      $prevEmail = (string)($me['email'] ?? '');
      $prevPhone = (string)($me['phone'] ?? '');

      $me['nickname'] = mb_check_encoding($newNick, 'UTF-8')
                        ? (function_exists('mb_substr') ? mb_substr($newNick, 0, 40) : substr($newNick, 0, 120))
                        : $newNick;
      $me['email'] = $newEmail;
      $me['phone'] = $newPhone;
      /* 값이 바뀌면 인증 상태는 초기화한다 */
      if ($newEmail !== $prevEmail) $me['email_ok'] = false;
      if ($newPhone !== $prevPhone) $me['phone_ok'] = false;

      $members[$uid] = $me;
      if (st_write($MEMBERS, $members)) {
        $_SESSION['nickname'] = $me['nickname'];
        $nick = $me['nickname'];
        $msg = '저장했습니다.'; $msgType = 'ok';
      } else {
        $msg = '저장에 실패했습니다. 잠시 후 다시 시도해 주세요.'; $msgType = 'err';
      }
    }
  }
}

$email    = (string)($me['email'] ?? '');
$phone    = (string)($me['phone'] ?? '');
$emailOk  = !empty($me['email_ok']);
$phoneOk  = !empty($me['phone_ok']);
$joined   = (string)($me['created'] ?? '');
$roleKo   = $role === 'agency' ? '소방 대행업체' : ($role === 'building' ? '건물 소방안전관리자' : '미설정');
$homeUrl  = $role === 'agency' ? '/clients_mini.php' : '/building_manager.php';

/* 건물명 (건물관리자만) */
$bldgName = '';
if ($role !== 'agency') {
  $bi = st_read($DATA . '/building/' . preg_replace('/[^A-Za-z0-9_]/', '_', $uid) . '/info.json');
  $bldgName = (string)($bi['name'] ?? $bi['building_name'] ?? '');
}
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>설정 · TWORIX</title>
<style>
  :root{ --ink:#111827; --mut:#6b7280; --line:#e5e7eb; --bg:#f5f6f8;
         --card:#fff; --brand:#1d4ed8; --danger:#dc2626; --ok:#15803d; }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-size:14px;line-height:1.6;
       font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}
  .wrap{max-width:640px;margin:0 auto;padding:20px 16px 60px}
  .top{display:flex;align-items:center;gap:10px;margin-bottom:18px}
  .top h1{font-size:1.35rem;margin:0;font-weight:700}
  .top .back{margin-left:auto;font-size:.85rem;color:var(--brand);text-decoration:none;
             border:1px solid #c7d2fe;background:#fff;border-radius:8px;padding:7px 12px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:13px;
        padding:18px 18px 16px;margin-bottom:14px}
  .card h2{font-size:.83rem;margin:0 0 14px;color:var(--mut);font-weight:700;
           letter-spacing:.04em;text-transform:uppercase}
  .row{display:flex;align-items:center;gap:12px;padding:11px 0;border-top:1px solid #f1f3f6}
  .row:first-of-type{border-top:0}
  .row .k{width:96px;flex:none;color:var(--mut);font-size:.87rem}
  .row .v{flex:1;min-width:0;word-break:break-all}
  .row .v.dim{color:var(--mut)}
  .go{margin-left:auto;flex:none;font-size:.85rem;color:var(--brand);
      text-decoration:none;border:1px solid #dbeafe;background:#f8fafc;
      border-radius:8px;padding:6px 11px;white-space:nowrap}
  .badge{display:inline-block;font-size:.72rem;font-weight:600;border-radius:20px;
         padding:1px 8px;margin-left:6px}
  .badge.ok{background:#f0fdf4;color:var(--ok)}
  .badge.no{background:#f8fafc;color:var(--mut)}
  label.f{display:block;font-size:.83rem;color:var(--mut);margin:0 0 5px}
  input[type=text],input[type=email]{width:100%;padding:10px 11px;border:1px solid var(--line);
    border-radius:9px;font-size:.94rem;font-family:inherit}
  input:focus{outline:none;border-color:var(--brand)}
  .fld{margin-bottom:13px}
  .hint{font-size:.76rem;color:var(--mut);margin-top:4px}
  .btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;
       padding:10px 16px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:inherit}
  .btn.pri{background:var(--brand);border-color:var(--brand);color:#fff}
  .flash{border-radius:9px;padding:10px 13px;font-size:.86rem;margin-bottom:14px}
  .flash.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:var(--ok)}
  .flash.err{background:#fef2f2;border:1px solid #fecaca;color:var(--danger)}
  .acct a{display:block;padding:11px 0;border-top:1px solid #f1f3f6;
          text-decoration:none;color:var(--ink)}
  .acct a:first-of-type{border-top:0}
  .acct a.quit{color:var(--mut);font-size:.86rem}
  .acct a.quit:hover{color:var(--danger)}
  @media(max-width:520px){
    .row{flex-wrap:wrap}
    .row .k{width:100%;margin-bottom:-4px}
    .go{margin-left:0}
  }
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <h1>설정</h1>
    <a class="back" href="<?=h($homeUrl)?>">← 돌아가기</a>
  </div>

  <?php if ($msg !== ''): ?>
    <div class="flash <?=h($msgType)?>"><?=h($msg)?></div>
  <?php endif; ?>

  <!-- 내 정보 -->
  <div class="card">
    <h2>내 정보</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="profile">

      <div class="fld">
        <label class="f" for="nickname">닉네임</label>
        <input type="text" id="nickname" name="nickname" value="<?=h($nick)?>" required>
      </div>

      <div class="fld">
        <label class="f" for="email">이메일
          <?php if ($email !== ''): ?>
            <span class="badge <?=$emailOk ? 'ok' : 'no'?>"><?=$emailOk ? '인증됨' : '미인증'?></span>
          <?php endif; ?>
        </label>
        <input type="email" id="email" name="email" value="<?=h($email)?>" placeholder="example@domain.com">
      </div>

      <div class="fld">
        <label class="f" for="phone">휴대폰
          <?php if ($phone !== ''): ?>
            <span class="badge <?=$phoneOk ? 'ok' : 'no'?>"><?=$phoneOk ? '인증됨' : '미인증'?></span>
          <?php endif; ?>
        </label>
        <input type="text" id="phone" name="phone" value="<?=h($phone)?>"
               placeholder="01012345678" inputmode="numeric">
        <p class="hint">이메일이나 휴대폰을 바꾸면 인증 상태가 초기화됩니다.</p>
      </div>

      <button type="submit" class="btn pri">저장</button>
    </form>
  </div>

  <!-- 계정 정보 -->
  <div class="card">
    <h2>계정</h2>
    <div class="row">
      <span class="k">아이디</span>
      <span class="v dim"><?=h($uid)?></span>
    </div>
    <div class="row">
      <span class="k">가입 경로</span>
      <span class="v"><?=$isKakao ? '카카오 로그인' : '자체 가입'?></span>
    </div>
    <div class="row">
      <span class="k">사용자 유형</span>
      <span class="v"><?=h($roleKo)?></span>
    </div>
    <?php if ($joined !== ''): ?>
    <div class="row">
      <span class="k">가입일</span>
      <span class="v dim"><?=h(substr($joined, 0, 10))?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- 업무 -->
  <div class="card">
    <h2><?=$role === 'agency' ? '거래처' : '건물'?></h2>
    <?php if ($role === 'agency'): ?>
      <div class="row">
        <span class="k">거래처 관리</span>
        <span class="v dim">등록된 거래처와 일정을 관리합니다.</span>
        <a class="go" href="/clients_mini.php">관리 →</a>
      </div>
    <?php else: ?>
      <div class="row">
        <span class="k">건물 정보</span>
        <span class="v <?=$bldgName === '' ? 'dim' : ''?>">
          <?=h($bldgName !== '' ? $bldgName : '아직 등록하지 않았습니다')?>
        </span>
        <a class="go" href="/building_info.php">관리 →</a>
      </div>
      <div class="row">
        <span class="k">업무일지</span>
        <span class="v dim">점검·교육 기록을 남깁니다.</span>
        <a class="go" href="/work_log.php">이동 →</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- 이용 -->
  <div class="card">
    <h2>이용</h2>
    <div class="row">
      <span class="k">구독 · 결제</span>
      <span class="v dim">요금제와 결제 내역을 확인합니다.</span>
      <a class="go" href="/clients_mini.php?view=subscribe">이동 →</a>
    </div>
    <div class="row">
      <span class="k">도움말</span>
      <span class="v dim">자주 묻는 질문</span>
      <a class="go" href="/faq.php">이동 →</a>
    </div>
  </div>

  <!-- 로그아웃 · 탈퇴 -->
  <div class="card acct">
    <h2>계정 관리</h2>
    <a href="/logout.php">로그아웃</a>
    <a href="/withdraw.php" class="quit">회원 탈퇴</a>
  </div>

</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
