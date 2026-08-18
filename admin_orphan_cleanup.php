<?php
/* =============================================================
   admin_orphan_cleanup.php — 남아 있는 탈퇴 회원 데이터 정리 (관리자 전용)
   ─────────────────────────────────────────────────────────────
   왜 필요한가
     예전 탈퇴 처리는 train(소방훈련) · jawi(자위소방대 교육·훈련) 폴더를
     지우지 않았습니다. 그래서 같은 아이디로 다시 가입하면 예전 기록이
     그대로 보였습니다. withdraw.php 는 고쳤지만, 이미 남아 있는
     폴더는 이 화면에서 한 번 정리해야 합니다.

   쓰는 법
     1) 관리자로 로그인한 뒤 /admin_orphan_cleanup.php 를 엽니다
     2) 목록을 확인합니다 (members.json 에 없는 회원 폴더만 나옵니다)
     3) 정리할 항목을 고르고 "선택 정리" 를 누릅니다
     4) 지운 자료는 data/_backups/orphans/ 로 옮겨집니다 (즉시 삭제 아님)

   정리가 끝나면 이 파일은 서버에서 지워도 됩니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
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

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!is_admin()) { http_response_code(403); exit('관리자만 사용할 수 있습니다.'); }

$DATA    = __DIR__ . '/data';
$MEMBERS = $DATA . '/members.json';
$BACKUP  = $DATA . '/_backups/orphans';

/* 회원별 데이터가 들어 있는 폴더들 */
const BUCKETS = [
  'building' => '건물 기본정보',
  'worklog'  => '업무수행 기록표',
  'fireplan' => '소방계획서·편성표',
  'train'    => '소방훈련·교육 기록부',
  'jawi'     => '자위소방대 교육·훈련',
];

function oc_members(string $f): array {
  if (!is_file($f)) return [];
  $a = json_decode((string)@file_get_contents($f), true);
  return is_array($a) ? $a : [];
}
function oc_size(string $dir): int {
  $n = 0;
  foreach (glob($dir . '/*') ?: [] as $p) { $n += is_dir($p) ? oc_size($p) : 1; }
  return $n;
}
function oc_move(string $src, string $dst): bool {
  if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0775, true);
  if (@rename($src, $dst)) return true;
  if (!is_dir($dst)) @mkdir($dst, 0775, true);
  foreach (glob($src . '/*') ?: [] as $p) {
    $t = $dst . '/' . basename($p);
    is_dir($p) ? oc_move($p, $t) : @copy($p, $t);
    if (!is_dir($p)) @unlink($p);
  }
  @rmdir($src);
  return !is_dir($src);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf'];

$members = oc_members($MEMBERS);
$msg = '';

/* ── 현재 남아 있는 고아 폴더 찾기 ── */
$orphans = [];   // uid => [bucket => ['path'=>, 'files'=>, 'mtime'=>]]
foreach (BUCKETS as $bucket => $label) {
  $base = $DATA . '/' . $bucket;
  if (!is_dir($base)) continue;
  foreach (scandir($base) ?: [] as $e) {
    if ($e === '.' || $e === '..') continue;
    $dir = $base . '/' . $e;
    if (!is_dir($dir)) continue;

    /* users/m_ 접두어 제거한 이름으로 회원 여부 판단 */
    $uid = $e;
    if (isset($members[$uid])) continue;                 // 살아 있는 회원 → 건너뜀
    if ($uid === 'kakao_guest' || $uid === 'guest') {
      /* 예전 공용 폴더 — 회원 것이 아니므로 정리 대상 */
    } else {
      /* 카카오 회원은 members.json 에 없을 수 있으니, 접두어가 kakao_ 이면
         kakao_roles.json 에 남아 있는지 한 번 더 확인한다 */
      if (strncmp($uid, 'kakao_', 6) === 0) {
        $kr = oc_members($DATA . '/kakao_roles.json');
        $kid = substr($uid, 6);
        if (isset($kr[$kid])) continue;                  // 아직 쓰는 계정
      }
    }

    $orphans[$uid][$bucket] = [
      'path'  => $dir,
      'files' => oc_size($dir),
      'mtime' => @filemtime($dir) ?: 0,
    ];
  }
}
ksort($orphans);

/* ── 정리 실행 ── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $msg = '잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.';
  } else {
    $picked = (array)($_POST['uid'] ?? []);
    $stamp  = date('Ymd_His');
    $movedN = 0; $movedUsers = 0;
    foreach ($picked as $uid) {
      $uid = (string)$uid;
      if (!isset($orphans[$uid])) continue;
      $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid);
      foreach ($orphans[$uid] as $bucket => $info) {
        if (oc_move($info['path'], $BACKUP . '/' . $safe . '_' . $stamp . '/' . $bucket)) {
          $movedN += $info['files'];
        }
      }
      @file_put_contents(
        $BACKUP . '/' . $safe . '_' . $stamp . '/_meta.json',
        json_encode([
          'uid' => $uid, 'reason' => 'orphan cleanup',
          'moved_at' => date('Y-m-d H:i:s'),
          'by' => $_SESSION['nickname'] ?? 'admin',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
      );
      $movedUsers++;
      unset($orphans[$uid]);
    }
    $msg = $movedUsers > 0
      ? "{$movedUsers}명분 · 파일 {$movedN}개를 백업으로 옮겼습니다. (data/_backups/orphans/)"
      : '선택한 항목이 없습니다.';
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>탈퇴 잔여 데이터 정리 — TWORIX</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#f5f7fb;color:#1a2436;font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;
  line-height:1.6;padding:28px 18px 70px}
.wrap{max-width:920px;margin:0 auto}
h1{font-size:21px;font-weight:800;margin-bottom:6px}
.lead{font-size:13.5px;color:#56627a;margin-bottom:18px;line-height:1.7}
.card{background:#fff;border:1px solid #e3e8f0;border-radius:13px;padding:18px 20px;margin-bottom:14px}
.msg{background:#eef4ff;border:1px solid #c7dbff;color:#1d4ed8;border-radius:10px;
  padding:12px 14px;font-size:13.5px;font-weight:600;margin-bottom:14px}
.warn{background:#fff7ed;border:1px solid #f6d8a8;color:#92400e;border-radius:10px;
  padding:12px 14px;font-size:13px;margin-bottom:16px;line-height:1.65}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{padding:10px 9px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}
th{font-size:12px;color:#7a8699;font-weight:700;background:#fafbfd}
.uid{font-weight:800}
.tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:4px}
.tag{font-size:11px;font-weight:700;background:#eef2f7;color:#56627a;border-radius:999px;padding:2px 9px}
.tag--hot{background:#fef2f2;color:#dc2626}
.empty{text-align:center;color:#7a8699;padding:34px 12px;font-size:14px;line-height:1.8}
.bar{position:sticky;bottom:0;background:#fff;border-top:1px solid #e3e8f0;
  padding:13px 16px;display:flex;gap:9px;align-items:center;flex-wrap:wrap;
  margin:0 -20px -18px;border-radius:0 0 13px 13px}
button{padding:10px 17px;border-radius:9px;border:1px solid #d4dbe6;background:#fff;
  font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;color:#56627a}
.go{background:#dc2626;border-color:#dc2626;color:#fff}
.go:hover{filter:brightness(1.08)}
a.back{font-size:13px;color:#1d4ed8;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <h1>탈퇴 잔여 데이터 정리</h1>
  <p class="lead">
    <b>members.json 에 없는</b> 회원 폴더만 모았습니다. 예전 탈퇴 처리에서
    소방훈련·자위소방대 기록이 지워지지 않아, 같은 아이디로 다시 가입하면
    옛 기록이 보이던 문제를 정리합니다.
  </p>

  <?php if ($msg !== ''): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>

  <div class="warn">
    지운 자료는 곧바로 삭제되지 않고 <b>data/_backups/orphans/</b> 로 옮겨집니다.
    며칠 지켜본 뒤 문제가 없으면 그 폴더를 직접 지우세요.
    <b>지금 쓰고 있는 회원은 목록에 나오지 않습니다.</b>
  </div>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

    <?php if (!$orphans): ?>
      <div class="empty">✓ 남아 있는 탈퇴 회원 데이터가 없습니다.<br>정리할 것이 없습니다.</div>
    <?php else: ?>
      <table>
        <tr>
          <th style="width:38px"><input type="checkbox" onclick="
            document.querySelectorAll('input[name=\'uid[]\']').forEach(c=>c.checked=this.checked)"></th>
          <th>아이디</th>
          <th>남아 있는 자료</th>
          <th style="width:120px">마지막 변경</th>
        </tr>
        <?php foreach ($orphans as $uid => $buckets): ?>
        <tr>
          <td><input type="checkbox" name="uid[]" value="<?=h($uid)?>"></td>
          <td>
            <span class="uid"><?=h($uid)?></span>
            <?php if ($uid === 'kakao_guest' || $uid === 'guest'): ?>
              <div style="font-size:11.5px;color:#dc2626;margin-top:2px">예전 공용 폴더 — 정리 권장</div>
            <?php endif; ?>
          </td>
          <td>
            <div class="tags">
              <?php foreach ($buckets as $b => $info): ?>
                <span class="tag <?= in_array($b, ['train','jawi'], true) ? 'tag--hot' : '' ?>">
                  <?=h(BUCKETS[$b])?> <?=(int)$info['files']?>
                </span>
              <?php endforeach; ?>
            </div>
          </td>
          <td style="font-size:12px;color:#7a8699">
            <?php
              $mt = 0; foreach ($buckets as $i) { $mt = max($mt, (int)$i['mtime']); }
              echo $mt ? h(date('Y-m-d', $mt)) : '-';
            ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>

      <div class="bar">
        <button type="submit" class="go"
          onclick="return confirm('선택한 회원의 남은 자료를 백업으로 옮깁니다. 계속할까요?')">
          선택 정리
        </button>
        <span style="font-size:12.5px;color:#7a8699">
          모두 <?=count($orphans)?>명분이 남아 있습니다
        </span>
      </div>
    <?php endif; ?>
  </form>

  <a class="back" href="/admin_members.php">← 회원 목록으로</a>
</div>
</body>
</html>
