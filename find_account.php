<?php
// find_account.php — 아이디 찾기 / 비밀번호 재설정
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$MEMBERS_FILE = __DIR__ . '/data/members.json';
$RESET_FILE   = __DIR__ . '/data/pw_resets.json';   // 재설정 토큰 저장

function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function save_json(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp=$f.'.tmp'; file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  return @rename($tmp,$f);
}

/* 메일 발송: mail_config.php의 send_mail() 사용 (카페24 SMTP: info@tworix.com) */
require_once __DIR__ . '/mail_config.php';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$members = load_json($MEMBERS_FILE);
$msg = ''; $msgType = '';   // ok | err
$sentEmail = '';            // 발송 완료 화면에 표시할 이메일
$sentKind  = '';            // id | pw
$mode = $_GET['mode'] ?? 'id';   // id | pw | reset | sent

/* ── 재설정 링크로 들어온 경우 (mode=reset&token=...) ── */
$resetToken = $_GET['token'] ?? '';
$resetValid = false; $resetUid = '';
if ($mode === 'reset' && $resetToken !== '') {
  $resets = load_json($RESET_FILE);
  foreach ($resets as $uid => $r) {
    if (hash_equals($r['token'] ?? '', $resetToken) && ($r['expires'] ?? 0) > time()) {
      $resetValid = true; $resetUid = $uid; break;
    }
  }
  if (!$resetValid) { $msg = '링크가 만료되었거나 올바르지 않습니다. 다시 요청해 주세요.'; $msgType='err'; $mode='pw'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }
  $action = $_POST['action'] ?? '';

  /* 아이디 찾기: 이메일로 아이디 발송 */
  if ($action === 'find_id') {
    $email = trim($_POST['email'] ?? '');
    $found = [];
    foreach ($members as $uid => $m) {
      if (strcasecmp($m['email'] ?? '', $email) === 0) $found[] = $uid;
    }
    // 이메일 존재 여부를 화면에서 구분해주지 않음 (계정 캐기 방지)
    if ($found && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $body = "안녕하세요, TWORIX입니다.\n\n요청하신 아이디 안내입니다.\n\n"
            . "아이디: " . implode(', ', $found) . "\n\n"
            . "본인이 요청하지 않았다면 이 메일을 무시하세요.";
      send_mail($email, '[TWORIX] 아이디 찾기 안내', $body);
    }
    $sentEmail = $email;
    $sentKind  = 'id';
    $mode = 'sent';
  }

  /* 비밀번호 재설정 요청: 아이디+이메일 → 재설정 링크 발송 */
  elseif ($action === 'request_reset') {
    $uid   = trim($_POST['userid'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $m = $members[$uid] ?? null;
    if ($m && strcasecmp($m['email'] ?? '', $email) === 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $token = bin2hex(random_bytes(24));
      $resets = load_json($RESET_FILE);
      $resets[$uid] = ['token' => $token, 'expires' => time() + 1800];  // 30분 유효
      save_json($RESET_FILE, $resets);
      $link = 'https://www.tworix.com/find_account.php?mode=reset&token=' . $token;
      $body = "안녕하세요, TWORIX입니다.\n\n비밀번호 재설정 링크입니다. (30분간 유효)\n\n"
            . $link . "\n\n본인이 요청하지 않았다면 이 메일을 무시하세요.";
      send_mail($email, '[TWORIX] 비밀번호 재설정 안내', $body);
    }
    $sentEmail = $email;
    $sentKind  = 'pw';
    $mode = 'sent';
  }

  /* 새 비밀번호 설정 */
  elseif ($action === 'do_reset') {
    $token = $_POST['token'] ?? '';
    $pw    = (string)($_POST['password'] ?? '');
    $pw2   = (string)($_POST['password2'] ?? '');
    $resets = load_json($RESET_FILE);
    $uid = '';
    foreach ($resets as $u => $r) {
      if (hash_equals($r['token'] ?? '', $token) && ($r['expires'] ?? 0) > time()) { $uid = $u; break; }
    }
    if ($uid === '') {
      $msg = '링크가 만료되었거나 올바르지 않습니다. 다시 요청해 주세요.'; $msgType='err'; $mode='pw';
    } elseif (strlen($pw) < 8) {
      $msg = '비밀번호는 8자 이상이어야 합니다.'; $msgType='err'; $mode='reset'; $resetValid=true; $resetToken=$token;
    } elseif ($pw !== $pw2) {
      $msg = '비밀번호 확인이 일치하지 않습니다.'; $msgType='err'; $mode='reset'; $resetValid=true; $resetToken=$token;
    } else {
      $members[$uid]['pw_hash'] = password_hash($pw, PASSWORD_DEFAULT);
      save_json($MEMBERS_FILE, $members);
      unset($resets[$uid]); save_json($RESET_FILE, $resets);   // 토큰 폐기
      $msg = '비밀번호가 변경되었습니다. 새 비밀번호로 로그인해 주세요.'; $msgType='ok'; $mode='done';
    }
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>아이디·비밀번호 찾기 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg);color:var(--fg);
  font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6;
  display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{width:100%;max-width:420px}
.brand{text-align:center;font-weight:800;font-size:24px;letter-spacing:.5px;margin-bottom:20px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:18px;padding:26px;box-shadow:0 20px 50px rgba(20,40,80,.08)}
.tabs{display:flex;gap:4px;margin-bottom:20px;background:#eef2f8;border:1px solid var(--bd);border-radius:10px;padding:4px}
.tab{flex:1;display:flex;align-items:center;justify-content:center;min-height:38px;
  padding:8px 6px;border-radius:8px;font-size:13.5px;font-weight:600;color:var(--mut2);
  cursor:pointer;text-decoration:none;white-space:nowrap;letter-spacing:-.2px;transition:.12s}
.tab:hover{color:var(--fg)}
.tab.active{background:#fff;color:var(--brand2);box-shadow:0 1px 4px rgba(20,40,80,.08)}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.field label{font-size:12px;color:var(--mut2);font-weight:700}
.inp{padding:12px 13px;border:1px solid var(--bd2);border-radius:10px;font-size:14px;font-family:inherit;background:#f8fafc}
.inp:focus{outline:none;border-color:var(--brand);background:#fff}
button{width:100%;background:var(--brand);color:#fff;border:0;border-radius:11px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
button:hover{background:var(--brand2)}
.msg{border-radius:9px;padding:11px 13px;font-size:13px;margin-bottom:16px;line-height:1.7;text-align:left}
.msg.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}
.msg.err{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c}
.back{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--mut2);text-decoration:none}
.back:hover{color:var(--brand2)}
.done{text-align:center;padding:8px 0}
.done .ic{font-size:48px;margin-bottom:12px;line-height:1}
.done-title{font-size:19px;font-weight:800;margin-bottom:12px}
.done-desc{font-size:14px;color:var(--mut2);line-height:1.7;margin-bottom:12px}
.done-hint{font-size:12.5px;color:var(--mut);line-height:1.7;
  background:#f8fafc;border:1px solid var(--bd);border-radius:9px;padding:10px 12px;margin-bottom:18px}
.sent-email{display:inline-block;background:#f0f5ff;border:1px solid #c7dbff;color:var(--brand2);
  border-radius:8px;padding:7px 14px;font-size:14px;font-weight:700;margin-bottom:16px}
.btn-link{display:block;width:100%;background:#fff;border:1px solid var(--bd2);color:var(--fg);
  border-radius:11px;padding:12px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;transition:.12s}
.btn-link:hover{border-color:var(--brand);color:var(--brand2)}
/* 버튼 로딩 상태 */
button:disabled{opacity:.72;cursor:progress}
.spinner{display:inline-block;width:14px;height:14px;margin-right:7px;vertical-align:-2px;
  border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;
  animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
/* 좁은 화면에서 탭 글자가 삐져나오지 않게 */
@media(max-width:400px){
  html,body{padding:16px}
  .card{padding:22px 18px}
  .tab{font-size:12.5px;padding:8px 4px;letter-spacing:-.4px}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">TWORIX</div>
  <div class="card">

    <?php if ($mode === 'done'): ?>
      <div class="done">
        <div class="ic">✅</div>
        <p><?=h($msg)?></p>
      </div>

    <?php elseif ($mode === 'sent'): ?>
      <?php
        // 이메일 일부 가리기 (abc***@gmail.com)
        $masked = $sentEmail;
        if ($sentEmail !== '' && strpos($sentEmail, '@') !== false) {
          [$loc, $dom] = explode('@', $sentEmail, 2);
          $keep = max(1, min(3, strlen($loc) - 1));
          $masked = substr($loc, 0, $keep) . str_repeat('*', max(2, strlen($loc) - $keep)) . '@' . $dom;
        }
        $what = ($sentKind === 'pw') ? '비밀번호 재설정 링크를' : '아이디를';
        $recheck = ($sentKind === 'pw') ? '아이디와 이메일이' : '가입할 때 쓴 이메일이';
      ?>
      <div class="done">
        <div class="ic">📬</div>
        <h3 class="done-title">메일을 보냈습니다</h3>
        <?php if ($masked !== ''): ?>
          <div class="sent-email"><?=h($masked)?></div>
        <?php endif; ?>
        <p class="done-desc">
          입력하신 이메일로 가입된 계정이 있다면 <?=h($what)?> 보내드렸습니다.<br>
          메일함(스팸함 포함)을 확인해 주세요.
        </p>
        <p class="done-hint">
          5분 내로 메일이 오지 않으면, <?=h($recheck)?> 맞는지 확인하시거나 문의해 주세요.
        </p>
        <a class="btn-link" href="?mode=<?=h($sentKind)?>">다시 입력하기</a>
      </div>

    <?php elseif ($mode === 'reset' && $resetValid): ?>
      <h3 style="font-size:17px;margin-bottom:16px">새 비밀번호 설정</h3>
      <?php if ($msg): ?><div class="msg <?=h($msgType)?>"><?=nl2br(h($msg))?></div><?php endif; ?>
      <form method="post" class="js-submit-form">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="action" value="do_reset">
        <input type="hidden" name="token" value="<?=h($resetToken)?>">
        <div class="field"><label>새 비밀번호</label>
          <input class="inp" type="password" name="password" required placeholder="8자 이상"></div>
        <div class="field"><label>새 비밀번호 확인</label>
          <input class="inp" type="password" name="password2" required placeholder="다시 입력"></div>
        <button type="submit" data-loading="변경 중…">비밀번호 변경</button>
      </form>

    <?php else: ?>
      <div class="tabs">
        <a class="tab <?= $mode==='id'?'active':'' ?>" href="?mode=id">아이디 찾기</a>
        <a class="tab <?= $mode==='pw'?'active':'' ?>" href="?mode=pw">비밀번호 재설정</a>
      </div>
      <?php if ($msg): ?><div class="msg <?=h($msgType)?>"><?=nl2br(h($msg))?></div><?php endif; ?>

      <?php if ($mode === 'pw'): ?>
        <form method="post" class="js-submit-form">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="request_reset">
          <div class="field"><label>아이디</label>
            <input class="inp" name="userid" required placeholder="가입한 아이디"></div>
          <div class="field"><label>가입 이메일</label>
            <input class="inp" type="email" name="email" required placeholder="가입 시 등록한 이메일"></div>
          <button type="submit" data-loading="메일 보내는 중…">재설정 링크 받기</button>
        </form>
      <?php else: ?>
        <form method="post" class="js-submit-form">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="find_id">
          <div class="field"><label>가입 이메일</label>
            <input class="inp" type="email" name="email" required placeholder="가입 시 등록한 이메일"></div>
          <button type="submit" data-loading="메일 보내는 중…">아이디 메일로 받기</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <a class="back" href="/index.php">← 메인으로 돌아가기</a>
  </div>
</div>

<script>
  /* 폼 제출 시 버튼을 즉시 로딩 상태로 (메일 발송에 몇 초 걸림 · 중복 제출 방지) */
  document.querySelectorAll('.js-submit-form').forEach(form => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type=submit]');
      if (!btn || btn.disabled) return;
      const label = btn.dataset.loading || '처리 중…';
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>' + label;
    });
  });
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
