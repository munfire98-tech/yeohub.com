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
      $body = "안녕하세요, 소방계획서.com입니다.\n\n요청하신 아이디 안내입니다.\n\n"
            . "아이디: " . implode(', ', $found) . "\n\n"
            . "본인이 요청하지 않았다면 이 메일을 무시하세요.";
      send_mail($email, '[소방계획서.com] 아이디 찾기 안내', $body);
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
      $link = 'https://xn--989ay50awvdzmk18f.com/find_account.php?mode=reset&token=' . $token;
      $body = "안녕하세요, 소방계획서.com입니다.\n\n비밀번호 재설정 링크입니다. (30분간 유효)\n\n"
            . $link . "\n\n본인이 요청하지 않았다면 이 메일을 무시하세요.";
      send_mail($email, '[소방계획서.com] 비밀번호 재설정 안내', $body);
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
<title>아이디·비밀번호 찾기 — 소방계획서.com</title>
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

/* Account recovery layout */
html{display:block;min-height:100%;padding:0;background:#f5f7fb}
body{display:flex;min-height:100dvh;align-items:center;justify-content:center;padding:40px 20px;background:radial-gradient(ellipse at 50% 0,#eaf1ff 0,transparent 55%),#f5f7fb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Apple SD Gothic Neo",sans-serif}
.wrap{max-width:460px}.brand{display:flex;justify-content:center;align-items:center;gap:9px;margin-bottom:25px;font-size:21px;letter-spacing:-.8px;color:#253b59}.brand-mark{display:grid;place-items:center;width:34px;height:38px;color:#4d73b5}.brand svg{width:30px;height:30px}.card{padding:34px;border:1px solid #e1e8f2;border-radius:23px;box-shadow:0 14px 48px #29466f09}.recovery-head{margin-bottom:26px}.eyebrow{color:#8091a8;font-size:10px;font-weight:750;letter-spacing:.12em;margin-bottom:8px}.recovery-head h1{font-size:25px;letter-spacing:-.9px;line-height:1.4;margin-bottom:9px}.recovery-head p,.reset-desc{font-size:13px;color:#8490a1;line-height:1.8}.tabs{background:#f0f3f8;border:0;border-radius:11px;padding:4px;margin-bottom:25px}.tab{font-size:13px;min-height:40px}.tab.active{color:#3a609e}.field{gap:8px;margin-bottom:18px}.field label{font-size:12px;color:#506079}.inp{min-height:48px;padding:12px 14px;background:#fff;border-color:#dce3ed;border-radius:10px;min-width:0;width:100%;font-size:16px}.inp::placeholder{color:#a0aaba;font-size:13px}.inp:focus{border-color:#6487c3;box-shadow:0 0 0 3px #6487c315}button{background:#456fb6;border-radius:10px;min-height:48px;font-size:14px}button:hover{background:#365c9e}a:focus-visible,button:focus-visible{outline:3px solid #a3bce6;outline-offset:3px}.back{margin-top:24px;padding-top:20px;border-top:1px solid #edf0f5;font-size:12px;color:#8390a1}.done{padding:5px 0 0}.done .ic{display:grid;place-items:center;width:76px;height:76px;border-radius:24px;margin:0 auto 22px;background:#edf3ff;color:#5179b9}.done .ic svg{width:35px;height:35px}.done .ic.success{background:#eef8f3;color:#3c9475}.done-title{font-size:24px;letter-spacing:-.8px;margin-bottom:12px}.done-desc{font-size:13px;color:#7b889c;line-height:1.85;margin-bottom:20px}.sent-email{display:block;margin:18px 0;background:#f7f9fc;border:1px solid #e7edf5;border-radius:11px;padding:13px 12px;color:#405b82;font-size:14px;overflow-wrap:anywhere}.sent-email small{display:block;font-size:10px;color:#8997aa;font-weight:500;margin-bottom:3px}.done-hint{text-align:left;background:#f8fafc;border-color:#e9eef4;padding:15px;margin:0 0 22px;font-size:12px;color:#8490a1}.done-hint strong{display:block;color:#5f728d;font-size:12px;margin-bottom:5px}.btn-link{border-color:#dce4ef;color:#59718f;font-size:13px}.btn-link.primary{background:#456fb6;border-color:#456fb6;color:#fff}.reset-title{font-size:24px;letter-spacing:-.8px;margin-bottom:8px}.reset-desc{margin-bottom:24px}.footer-note{text-align:center;margin-top:20px;font-size:11px;color:#9aa7b9}.msg{overflow-wrap:anywhere}.msg.err{background:#fff6f2;border-color:#f3dfd5;color:#a26446}
@media(max-width:480px){html{padding:0}body{padding:28px 16px;align-items:flex-start}.wrap{margin:auto}.card{padding:27px 22px;border-radius:19px}.brand{font-size:20px;margin-bottom:20px}.tab{font-size:12.5px}.done-title{font-size:22px}}
@media(prefers-reduced-motion:reduce){.spinner{animation:none}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4 5 8 8 9 3-1 8-5 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg></span>소방계획서.com</div>
  <div class="card">

    <?php if ($mode === 'done'): ?>
      <div class="done">
        <div class="ic success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4 5 8 8 9 3-1 8-5 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg></div><h1 class="done-title">비밀번호 변경 완료</h1><p class="done-desc"><?=h($msg)?></p><a class="btn-link primary" href="/index.php">메인에서 로그인하기</a>
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
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></div>
        <h1 class="done-title">메일함을 확인해 주세요</h1>
        <?php if ($masked !== ''): ?>
          <div class="sent-email"><small>안내받을 이메일</small><?=h($masked)?></div>
        <?php endif; ?>
        <p class="done-desc">
          입력하신 이메일로 가입된 계정이 있다면 <?=h($what)?> 보내드렸습니다.<br>
          메일함(스팸함 포함)을 확인해 주세요.
        </p>
        <p class="done-hint"><strong>메일이 보이지 않나요?</strong>
          스팸메일함도 확인해 주세요. 5분 내로 메일이 오지 않으면, <?=h($recheck)?> 맞는지 확인하시거나 문의해 주세요.
        </p>
        <a class="btn-link" href="?mode=<?=h($sentKind)?>">다시 입력하기</a>
      </div>

    <?php elseif ($mode === 'reset' && $resetValid): ?>
      <h1 class="reset-title">새 비밀번호 설정</h1><p class="reset-desc">다른 곳에서 사용하지 않는 비밀번호로 설정해 주세요.</p>
      <?php if ($msg): ?><div role="alert" class="msg <?=h($msgType)?>"><?=nl2br(h($msg))?></div><?php endif; ?>
      <form method="post" class="js-submit-form">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="action" value="do_reset">
        <input type="hidden" name="token" value="<?=h($resetToken)?>">
        <div class="field"><label for="new-password">새 비밀번호</label>
          <input class="inp" id="new-password" autocomplete="new-password" type="password" name="password" required placeholder="8자 이상"></div>
        <div class="field"><label for="confirm-password">새 비밀번호 확인</label>
          <input class="inp" id="confirm-password" autocomplete="new-password" type="password" name="password2" required placeholder="다시 입력"></div>
        <button type="submit" data-loading="변경 중…">비밀번호 변경</button>
      </form>

    <?php else: ?>
      <div class="recovery-head"><p class="eyebrow">ACCOUNT RECOVERY</p><h1>계정을 찾으시나요?</h1><p>가입 정보를 확인하고 다시 시작하세요.</p></div>
      <nav class="tabs" aria-label="계정 찾기 방법">
        <a class="tab <?= $mode==='id'?'active':'' ?>" href="?mode=id">아이디 찾기</a>
        <a class="tab <?= $mode==='pw'?'active':'' ?>" href="?mode=pw">비밀번호 재설정</a>
      </nav>
      <?php if ($msg): ?><div role="alert" class="msg <?=h($msgType)?>"><?=nl2br(h($msg))?></div><?php endif; ?>

      <?php if ($mode === 'pw'): ?>
        <form method="post" class="js-submit-form">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="request_reset">
          <div class="field"><label for="recover-userid">아이디</label>
            <input class="inp" id="recover-userid" autocomplete="username" name="userid" required placeholder="가입한 아이디"></div>
          <div class="field"><label for="recover-email">가입 이메일</label>
            <input class="inp" id="recover-email" autocomplete="email" type="email" name="email" required placeholder="가입 시 등록한 이메일"></div>
          <button type="submit" data-loading="메일 보내는 중…">재설정 링크 받기</button>
        </form>
      <?php else: ?>
        <form method="post" class="js-submit-form">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="action" value="find_id">
          <div class="field"><label for="recover-email">가입 이메일</label>
            <input class="inp" id="recover-email" autocomplete="email" type="email" name="email" required placeholder="가입 시 등록한 이메일"></div>
          <button type="submit" data-loading="메일 보내는 중…">아이디 메일로 받기</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <a class="back" href="/index.php">← 메인으로 돌아가기</a>
  </div>
  <p class="footer-note">함께 관리하고 기록하는 소방안전관리</p>
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
