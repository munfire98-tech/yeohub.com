<?php
/* =============================================================
   card_check.php — 카드 등록이 왜 안 보이는지 확인하는 진단 페이지
   ─────────────────────────────────────────────────────────────
   두 컴퓨터에서 각각 열어보고, 아래 값을 비교하세요.
   확인이 끝나면 이 파일은 지우시면 됩니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) date_default_timezone_set('Asia/Seoul');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) {
  exit('<meta charset="utf-8"><p style="font-family:sans-serif;padding:40px">로그인 후 열어주세요.</p>');
}

require_once __DIR__ . '/user_key.php';

$key   = app_user_key();
$dir   = __DIR__ . '/data/subscribe/' . $key;
$file  = $dir . '/subscription.json';
$data  = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];

/* data/subscribe 아래에 어떤 폴더들이 있는지 (계정이 갈렸는지 확인용) */
$allDirs = [];
foreach (glob(__DIR__ . '/data/subscribe/*', GLOB_ONLYDIR) ?: [] as $d) {
  $f = $d . '/subscription.json';
  $j = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  $allDirs[] = [
    'name' => basename($d),
    'card' => trim((string)($j['billing_key'] ?? '')) !== '',
    'stat' => (string)($j['status'] ?? '-'),
    'at'   => (string)($j['card_registered_at'] ?? ''),
  ];
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>카드 등록 진단</title>
<style>
  body{font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;background:#f5f7fb;
    color:#1a2436;padding:24px 20px 60px;line-height:1.65;margin:0}
  .wrap{max-width:720px;margin:0 auto}
  h1{font-size:20px;margin:0 0 6px}
  .sub{font-size:13px;color:#7a8699;margin-bottom:20px}
  .card{background:#fff;border:1px solid #e3e8f0;border-radius:12px;padding:18px 20px;margin-bottom:14px}
  .card h2{font-size:14px;margin:0 0 12px}
  .big{font-size:22px;font-weight:800;font-family:ui-monospace,monospace;
    background:#eef4ff;color:#1d4ed8;border-radius:9px;padding:12px 14px;
    word-break:break-all;margin-bottom:8px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  td{padding:7px 5px;border-bottom:1px solid #eef2f7;vertical-align:top}
  td:first-child{color:#7a8699;width:130px}
  code{background:#f1f5f9;padding:2px 6px;border-radius:5px;font-size:12px;word-break:break-all}
  .ok{color:#15803d;font-weight:700}
  .no{color:#dc2626;font-weight:700}
  .hint{font-size:12.5px;color:#56627a;background:#fffbeb;border:1px solid #f6d8a8;
    border-radius:9px;padding:12px 14px;line-height:1.8;color:#92400e}
  .row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #eef2f7}
  .row:last-child{border-bottom:0}
  .row b{font-family:ui-monospace,monospace;font-size:13px}
  .tag{font-size:11px;font-weight:800;padding:2px 8px;border-radius:999px}
  .tag--y{background:#f0fdf4;color:#15803d}
  .tag--n{background:#f1f5f9;color:#8a94a6}
  .me{margin-left:auto;font-size:11px;color:#2563eb;font-weight:800}
</style>
</head>
<body>
<div class="wrap">
  <h1>카드 등록 진단</h1>
  <div class="sub">두 컴퓨터에서 각각 열어보고 아래 <b>내 회원키</b>가 같은지 비교하세요.</div>

  <div class="card">
    <h2>1. 지금 내 회원키</h2>
    <div class="big"><?=h($key !== '' ? $key : '(비어 있음)')?></div>
    <div class="sub" style="margin:0">
      이 값이 두 컴퓨터에서 <b>다르면</b> 서로 다른 계정으로 로그인한 것입니다.
    </div>
  </div>

  <div class="card">
    <h2>2. 내 계정의 카드 상태</h2>
    <table>
      <tr><td>세션 아이디</td><td><code><?=h($_SESSION['member_id'] ?? '(없음)')?></code></td></tr>
      <tr><td>닉네임</td><td><code><?=h($_SESSION['nickname'] ?? '(없음)')?></code></td></tr>
      <tr><td>저장 파일</td><td><code>data/subscribe/<?=h($key)?>/subscription.json</code></td></tr>
      <tr><td>파일 존재</td>
          <td><?= is_file($file) ? '<span class="ok">있음</span>' : '<span class="no">없음</span>' ?></td></tr>
      <tr><td>카드 등록</td>
          <td><?= trim((string)($data['billing_key'] ?? '')) !== ''
                 ? '<span class="ok">등록됨</span>' : '<span class="no">등록 안 됨</span>' ?></td></tr>
      <tr><td>등록 시각</td><td><code><?=h($data['card_registered_at'] ?? '-')?></code></td></tr>
      <tr><td>구독 상태</td><td><code><?=h($data['status'] ?? '-')?></code></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>3. 서버에 저장된 전체 계정 (<?=count($allDirs)?>개)</h2>
    <?php if (!$allDirs): ?>
      <div class="sub" style="margin:0">아직 저장된 것이 없습니다.</div>
    <?php else: ?>
      <?php foreach ($allDirs as $d): ?>
        <div class="row">
          <b><?=h($d['name'])?></b>
          <span class="tag <?= $d['card'] ? 'tag--y' : 'tag--n' ?>">
            <?= $d['card'] ? '카드 있음' : '카드 없음' ?>
          </span>
          <span style="font-size:11.5px;color:#7a8699"><?=h($d['stat'])?></span>
          <?php if ($d['name'] === $key): ?><span class="me">← 지금 나</span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="hint">
    <b>폴더가 2개 이상이고 각각 다른 아이디라면</b> — 두 컴퓨터에서 서로 다른 계정으로
    로그인하신 것입니다. 카드를 등록한 그 계정으로 로그인하시면 그대로 보입니다.<br><br>
    <b>폴더가 1개인데 "카드 없음"이라면</b> — 저장이 실패한 것이니 알려주세요.
  </div>
</div>
</body>
</html>
