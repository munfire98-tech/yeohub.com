<?php
/**
 * withdraw.php — 회원 탈퇴 (본인 요청)
 *
 *  · 확인 문구를 직접 입력해야 진행된다.
 *  · 개인정보와 업무 데이터는 서비스에서 즉시 제거하고,
 *    data/_backups/withdrawn/{아이디}_{일시}/ 로 옮겨 1년간 보관한다.
 *  · 카카오 회원은 재로그인 시 완전 신규 가입 흐름을 타도록
 *    data/kakao_killed.json 에 기록한다. (index.php 가 처리)
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

require_once __DIR__ . '/telegram_config.php';

/* 로그인한 회원만 */
$isMember = !empty($_SESSION['is_user']) || !empty($_SESSION['member_id']);
if (!$isMember) { header('Location: /index.php'); exit; }

$DATA     = __DIR__ . '/data';
$BACKUP   = $DATA . '/_backups/withdrawn';
$MEMBERS  = $DATA . '/members.json';
$KROLES   = $DATA . '/kakao_roles.json';
$KILLED   = $DATA . '/kakao_killed.json';
$ASSIGN   = $DATA . '/evac_assign.json';

$uid      = (string)($_SESSION['member_id'] ?? '');
$kakaoId  = (string)($_SESSION['kakao_id'] ?? '');
$nick     = (string)($_SESSION['nickname'] ?? '');
$isKakao  = ($_SESSION['login_type'] ?? '') === 'kakao' || $kakaoId !== '';

if ($uid === '') { header('Location: /index.php'); exit; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function wd_read(string $f): array {
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function wd_write(string $f, array $a): bool {
  $d = dirname($f);
  if (!is_dir($d)) @mkdir($d, 0775, true);
  $t = $f . '.tmp';
  if (@file_put_contents($t, json_encode($a, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($t, $f);
}
/** 폴더를 통째로 옮긴다. rename 이 실패하면(다른 파티션 등) 복사 후 삭제 */
function wd_move(string $src, string $dst): bool {
  if (!is_dir($src)) return false;
  if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0775, true);
  if (@rename($src, $dst)) return true;
  @mkdir($dst, 0775, true);
  foreach (scandir($src) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $s = $src . '/' . $f; $d = $dst . '/' . $f;
    is_dir($s) ? wd_move($s, $d) : @copy($s, $d);
  }
  /* 원본 제거 */
  $it = @scandir($src) ?: [];
  foreach ($it as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $src . '/' . $f;
    if (!is_dir($p)) @unlink($p);
  }
  @rmdir($src);
  return true;
}

/* 회원 1명의 개인 데이터 폴더 (admin_members.php 와 동일하게 유지할 것)
 *
 * ⚠ 폴더 이름 규칙이 서식마다 조금씩 다릅니다.
 *   · jawi / building        : app_user_key() → 영문·숫자·_·- 외에는 _ 로 바꿈
 *   · worklog                : [^A-Za-z0-9_] 을 _ 로 바꿈
 *   · fireplan               : 세션 키를 가공 없이 그대로 사용
 * 그래서 아이디를 가공한 이름도 함께 지웁니다. 하나라도 남으면 같은 아이디로
 * 다시 가입했을 때 예전 기록이 그대로 보입니다. */
function wd_dirs(string $DATA, string $uid): array {
  $keys = array_values(array_unique(array_filter([
    $uid,
    preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid),   // app_user_key() 규칙
    preg_replace('/[^A-Za-z0-9_]/',   '_', $uid),   // work_log.php 규칙
  ])));

  $buckets = ['users', 'worklog', 'fireplan', 'building', 'train', 'jawi'];

  $out = [];
  foreach ($buckets as $b) {
    foreach ($keys as $i => $k) {
      $path  = $b === 'users' ? $DATA . '/users/m_' . $k : $DATA . '/' . $b . '/' . $k;
      $label = $i === 0 ? $b : $b . '_' . ($i + 1);   // 백업 폴더에서 겹치지 않게
      $out[$label] = $path;
    }
  }
  return $out;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$error = '';
$CONFIRM_WORD = '탈퇴합니다';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $error = '잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.';
  } elseif (trim((string)($_POST['confirm'] ?? '')) !== $CONFIRM_WORD) {
    $error = '확인 문구가 일치하지 않습니다.';
  } elseif (empty($_POST['agree'])) {
    $error = '안내 사항에 동의해 주세요.';
  } else {
    $stamp = date('Ymd_His');
    $dest  = $BACKUP . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid) . '_' . $stamp;
    if (!is_dir($dest)) @mkdir($dest, 0775, true);

    /* 1) 회원 레코드를 백업에 남기고 members.json 에서 제거 */
    $members = wd_read($MEMBERS);
    $record  = $members[$uid] ?? [];
    if (isset($members[$uid])) { unset($members[$uid]); wd_write($MEMBERS, $members); }

    /* 2) 개인 데이터 폴더 이동 */
    $moved = [];
    foreach (wd_dirs($DATA, $uid) as $name => $dir) {
      if (is_dir($dir)) { wd_move($dir, $dest . '/' . $name); $moved[] = $name; }
    }

    /* 3) 피난 시뮬레이션 배정 해제 */
    $assign = wd_read($ASSIGN);
    $hadAssign = isset($assign[$uid]) ? $assign[$uid] : [];
    if (isset($assign[$uid])) { unset($assign[$uid]); wd_write($ASSIGN, $assign); }

    /* 3-2) 배정 신청 기록도 지운다 (남으면 재가입 후 "요청 완료"로 보인다) */
    $REQS = $DATA . '/evac_requests.json';
    $reqs = wd_read($REQS);
    if (isset($reqs[$uid])) { unset($reqs[$uid]); wd_write($REQS, $reqs); }

    /* 4) 카카오: 유형 제거 + 재로그인 시 신규 가입 처리 */
    if ($isKakao && $kakaoId !== '') {
      $kr = wd_read($KROLES);
      if (isset($kr[$kakaoId])) { unset($kr[$kakaoId]); wd_write($KROLES, $kr); }
      $kk = wd_read($KILLED);
      $kk[$kakaoId] = time();
      wd_write($KILLED, $kk);
    }

    /* 5) 백업 메타 (복구·보관기간 관리용) */
    wd_write($dest . '/_meta.json', [
      'uid'        => $uid,
      'kakao_id'   => $kakaoId,
      'nickname'   => $nick,
      'member'     => $record,
      'assign'     => $hadAssign,
      'moved'      => $moved,
      'withdrawn'  => date('Y-m-d H:i:s'),
      'purge_after'=> date('Y-m-d', strtotime('+1 year')),
      'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    /* 6) 관리자 알림 */
    @send_telegram(
        "🔴 <b>회원 탈퇴</b>\n"
      . "아이디: " . h($uid) . "\n"
      . "닉네임: " . h($nick !== '' ? $nick : '-') . "\n"
      . "가입경로: " . ($isKakao ? '카카오' : '자체가입') . "\n"
      . "정리한 데이터: " . (count($moved) ? implode(', ', $moved) : '없음') . "\n"
      . "백업 보관: 1년 (" . date('Y-m-d', strtotime('+1 year')) . " 이후 정리)\n"
      . "시각: " . date('Y-m-d H:i:s')
    );

    /* 7) 세션 종료 */
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();

    header('Location: /index.php?withdrawn=1'); exit;
  }
}
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>회원 탈퇴 · TWORIX</title>
<style>
  :root{ --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --bg:#f8fafc;
         --danger:#dc2626; --danger-bg:#fef2f2; --brand:#1d4ed8; }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
       font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;
       line-height:1.6;padding:24px 16px}
  .wrap{max-width:520px;margin:0 auto;background:#fff;border:1px solid var(--line);
        border-radius:14px;padding:26px 22px}
  h1{font-size:1.25rem;margin:0 0 6px}
  .sub{color:var(--muted);font-size:.88rem;margin:0 0 18px}
  .who{background:var(--bg);border:1px solid var(--line);border-radius:10px;
       padding:11px 13px;font-size:.88rem;margin-bottom:18px}
  .who b{font-weight:600}
  .box{border:1px solid #fecaca;background:var(--danger-bg);border-radius:10px;
       padding:13px 15px;margin-bottom:18px}
  .box h2{font-size:.92rem;margin:0 0 8px;color:var(--danger)}
  .box ul{margin:0;padding-left:18px;font-size:.85rem;color:#7f1d1d}
  .box li{margin:3px 0}
  label.f{display:block;font-size:.86rem;font-weight:600;margin:0 0 6px}
  input[type=text]{width:100%;padding:11px 12px;border:1px solid var(--line);
                   border-radius:9px;font-size:.95rem}
  input[type=text]:focus{outline:none;border-color:var(--brand)}
  .chk{display:flex;gap:9px;align-items:flex-start;margin:16px 0 20px;font-size:.86rem}
  .chk input{margin-top:3px;flex:none}
  .err{background:#fef2f2;border:1px solid #fecaca;color:var(--danger);
       border-radius:9px;padding:10px 12px;font-size:.86rem;margin-bottom:16px}
  .btns{display:flex;gap:9px}
  button,.cancel{flex:1;padding:12px;border-radius:9px;font-size:.94rem;
                 font-weight:600;cursor:pointer;text-align:center;text-decoration:none;
                 border:1px solid var(--line);background:#fff;color:var(--ink)}
  button.go{background:var(--danger);border-color:var(--danger);color:#fff}
  button.go:disabled{background:#fca5a5;border-color:#fca5a5;cursor:not-allowed}
  .note{font-size:.78rem;color:var(--muted);margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <h1>회원 탈퇴</h1>
  <p class="sub">탈퇴하시면 계정과 저장된 자료를 더 이상 이용하실 수 없습니다.</p>

  <div class="who">
    <b><?=h($nick !== '' ? $nick : $uid)?></b> 님
    · <?=$isKakao ? '카카오 로그인' : '자체 가입'?>
    <br><span style="color:var(--muted)"><?=h($uid)?></span>
  </div>

  <div class="box">
    <h2>탈퇴하면 이렇게 됩니다</h2>
    <ul>
      <li>건물 기본정보, 업무일지, 소방계획서, 교육·훈련 기록이 삭제됩니다.</li>
      <li>배정받은 피난 시뮬레이션 도면을 볼 수 없습니다.</li>
      <li>같은 계정으로 다시 가입하셔도 이전 자료는 이어지지 않습니다.</li>
      <li>자료는 오기재·착오에 대비해 <b>1년간</b> 별도 보관 후 완전 삭제되며,
          이 기간에는 관리자에게 문의해 복구를 요청하실 수 있습니다.</li>
    </ul>
  </div>

  <?php if ($error !== ''): ?>
    <div class="err"><?=h($error)?></div>
  <?php endif; ?>

  <form method="post" id="f">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

    <label class="f" for="confirm">확인을 위해 <b><?=h($CONFIRM_WORD)?></b> 를 입력해 주세요</label>
    <input type="text" id="confirm" name="confirm" autocomplete="off"
           placeholder="<?=h($CONFIRM_WORD)?>" required>

    <label class="chk">
      <input type="checkbox" name="agree" id="agree" value="1">
      <span>위 안내 사항을 모두 확인했으며, 탈퇴에 동의합니다.</span>
    </label>

    <div class="btns">
      <a class="cancel" href="/index.php">취소</a>
      <button type="submit" class="go" id="go" disabled>탈퇴하기</button>
    </div>
  </form>

  <p class="note">
    문의가 필요하시면 탈퇴 전에 관리자에게 연락해 주세요.
    탈퇴 처리 후에는 화면에서 자료를 확인하실 수 없습니다.
  </p>
</div>

<script>
  var cf = document.getElementById('confirm'),
      ag = document.getElementById('agree'),
      go = document.getElementById('go'),
      WORD = <?=json_encode($CONFIRM_WORD, JSON_UNESCAPED_UNICODE)?>;
  function sync(){ go.disabled = !(cf.value.trim() === WORD && ag.checked); }
  cf.addEventListener('input', sync);
  ag.addEventListener('change', sync);
  document.getElementById('f').addEventListener('submit', function(e){
    if (!confirm('정말 탈퇴하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) e.preventDefault();
  });
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
