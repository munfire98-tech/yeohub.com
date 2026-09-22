<?php
/* =============================================================
   admin_billing.php — 구독자 결제 관리 (관리자 전용)
   ─────────────────────────────────────────────────────────────
   구독자 목록을 보면서 결제일이 된 사람을 직접 결제합니다.
   크론을 쓰지 않고 사람이 확인하며 처리할 때 씁니다.
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
if (!is_admin()) {
  http_response_code(403);
  exit('<meta charset="utf-8"><p style="font-family:sans-serif;padding:40px">관리자만 볼 수 있습니다. <a href="/admin_login.php">로그인</a></p>');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf'];

$PLANS = [
  'monthly' => ['name'=>'월 구독', 'price'=>2900,  'months'=>1],
  'yearly'  => ['name'=>'연 구독', 'price'=>29000, 'months'=>12],
];

$BASE = __DIR__ . '/data/subscribe';
$api  = @include __DIR__ . '/api_keys.php';
$SECRET = is_array($api) ? (string)($api['toss_secret'] ?? '') : '';
$IS_LIVE = is_array($api) ? (bool)($api['toss_live'] ?? false) : false;

/** 다음 결제일 — 말일 처리를 맞춥니다(1/31 → 2/28 → 3/31) */
function ab_next_billing(string $fromYmd, int $months, int $anchorDay = 0): string {
  $ts = strtotime($fromYmd); if ($ts === false) $ts = time();
  $y = (int)date('Y', $ts); $m = (int)date('n', $ts);
  $d = $anchorDay > 0 ? $anchorDay : (int)date('j', $ts);
  $m += $months; $y += intdiv($m - 1, 12); $m = (($m - 1) % 12) + 1;
  $last = (int)date('t', mktime(0,0,0,$m,1,$y));
  if ($d > $last) $d = $last;
  return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/** 토스 결제 승인 */
function ab_charge(string $secret, string $bk, string $ck, int $amount, string $name): array {
  $orderId = 'od_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
  $ch = curl_init('https://api.tosspayments.com/v1/billing/' . rawurlencode($bk));
  curl_setopt_array($ch, [
    CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25,
    CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($secret.':'), 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode(['customerKey'=>$ck,'amount'=>$amount,'orderId'=>$orderId,'orderName'=>$name], JSON_UNESCAPED_UNICODE),
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $body = json_decode((string)$raw, true); if (!is_array($body)) $body = [];
  if ($code >= 200 && $code < 300) return ['ok'=>true,'orderId'=>$orderId,'error'=>''];
  $msg = (string)($body['message'] ?? '알 수 없는 오류');
  $ec  = (string)($body['code'] ?? '');
  return ['ok'=>false,'orderId'=>$orderId,'error'=>($ec!==''?"[$ec] ":'').$msg];
}

/* ── 결제 실행 ───────────────────────────────────────────── */
$flash = ''; $flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $flash = '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'; $flashType = 'err';
  } elseif ($SECRET === '' || strpos($SECRET, '여기에') !== false) {
    $flash = '토스 시크릿 키가 설정되지 않았습니다.'; $flashType = 'err';
  } else {
    $targets = [];
    if (($_POST['act'] ?? '') === 'charge_one') {
      $u = (string)($_POST['uid'] ?? '');
      if (preg_match('/^[A-Za-z0-9_-]+$/', $u)) $targets[] = $u;
    } elseif (($_POST['act'] ?? '') === 'charge_due') {
      foreach (glob($BASE . '/*', GLOB_ONLYDIR) ?: [] as $d) $targets[] = basename($d);
    }

    $done = 0; $fail = 0; $skip = 0; $msgs = [];
    $today = date('Y-m-d');

    foreach ($targets as $uid) {
      $file = $BASE . '/' . $uid . '/subscription.json';
      if (!is_file($file)) { $skip++; continue; }
      $d = json_decode((string)@file_get_contents($file), true);
      if (!is_array($d)) { $skip++; continue; }

      $status = (string)($d['status'] ?? '');
      $bk = trim((string)($d['billing_key'] ?? ''));
      $ck = trim((string)($d['customer_key'] ?? ''));
      $plan = (string)($d['plan'] ?? '');
      $next = trim((string)($d['next_billing'] ?? ''));

      if (!in_array($status, ['active','payment_failed'], true)) { $skip++; continue; }
      if ($bk === '' || $ck === '' || !isset($PLANS[$plan]))     { $skip++; continue; }

      /* '결제일 된 사람 모두'일 때는 날짜를 봅니다. 개별 결제는 관리자 판단을 따릅니다. */
      if (($_POST['act'] ?? '') === 'charge_due' && ($next === '' || $next > $today)) { $skip++; continue; }

      /* 오늘 이미 결제했으면 막습니다(중복 방지) */
      if (substr((string)($d['paid_at'] ?? ''), 0, 10) === $today) {
        $msgs[] = $uid . ' — 오늘 이미 결제됨';
        $skip++; continue;
      }

      $p = $PLANS[$plan];
      $res = ab_charge($SECRET, $bk, $ck, (int)$p['price'], $p['name']);

      $hist = is_array($d['history'] ?? null) ? $d['history'] : [];
      array_unshift($hist, [
        'at'=>date('Y-m-d H:i:s'), 'amount'=>(int)$p['price'], 'name'=>$p['name'],
        'orderId'=>$res['orderId'], 'ok'=>$res['ok'],
        'msg'=>$res['ok'] ? '관리자 수동 결제' : $res['error'],
        'test'=>!$IS_LIVE, 'manual'=>true,
      ]);
      $d['history'] = array_slice($hist, 0, 50);

      if ($res['ok']) {
        $d['status'] = 'active';
        $d['paid_at'] = date('Y-m-d H:i:s');
        $d['next_billing'] = ab_next_billing($next ?: date('Y-m-d'), (int)$p['months'], (int)($d['bill_day'] ?? 0));
        $d['expires_at'] = $d['next_billing'];
        $d['last_error'] = '';
        $done++; $msgs[] = $uid . ' — 결제 완료 (다음 ' . $d['next_billing'] . ')';
      } else {
        $d['status'] = 'payment_failed';
        $d['last_error'] = $res['error'];
        $fail++; $msgs[] = $uid . ' — 실패: ' . $res['error'];
      }

      $tmp = $file . '.tmp';
      if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false) {
        @rename($tmp, $file);
      }
    }

    $flash = "결제 {$done}건 · 실패 {$fail}건 · 건너뜀 {$skip}건" . ($msgs ? ' — ' . implode(' / ', array_slice($msgs, 0, 5)) : '');
    $flashType = $fail > 0 ? 'err' : 'ok';
  }
}

/* ── 목록 읽기 ───────────────────────────────────────────── */
$today = date('Y-m-d');
$rows = []; $dueCount = 0;
foreach (glob($BASE . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
  $uid = basename($dir);
  $f = $dir . '/subscription.json';
  if (!is_file($f)) continue;
  $d = json_decode((string)@file_get_contents($f), true);
  if (!is_array($d)) continue;

  $next = trim((string)($d['next_billing'] ?? ''));
  $status = (string)($d['status'] ?? 'none');
  $hasCard = trim((string)($d['billing_key'] ?? '')) !== '';
  $due = $hasCard && in_array($status, ['active','payment_failed'], true)
      && $next !== '' && $next <= $today
      && substr((string)($d['paid_at'] ?? ''), 0, 10) !== $today;
  if ($due) $dueCount++;

  $rows[] = [
    'uid'=>$uid, 'status'=>$status, 'card'=>$hasCard,
    'plan'=>(string)($d['plan_name'] ?? ($d['plan'] ?? '')),
    'price'=>(int)($d['price'] ?? 0),
    'next'=>$next, 'paid'=>substr((string)($d['paid_at'] ?? ''), 0, 16),
    'due'=>$due, 'err'=>(string)($d['last_error'] ?? ''),
    'hist'=>is_array($d['history'] ?? null) ? count($d['history']) : 0,
  ];
}
usort($rows, function($a,$b){
  if ($a['due'] !== $b['due']) return $b['due'] <=> $a['due'];   // 결제일 된 사람 먼저
  return strcmp($a['next'] ?: '9999', $b['next'] ?: '9999');
});

$LABEL = [
  'active'=>'이용 중', 'payment_failed'=>'결제 실패', 'canceled'=>'해지',
  'pending'=>'신청 접수', 'expired'=>'만료', 'none'=>'미구독',
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>구독 결제 관리</title>
<style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
    --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);padding:24px 20px 70px;line-height:1.6;
    font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif}
  .wrap{max-width:960px;margin:0 auto}
  h1{font-size:21px;font-weight:800;margin-bottom:5px}
  .sub{font-size:13px;color:var(--mut);margin-bottom:20px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:13px;
    padding:18px 20px;margin-bottom:14px}
  .flash{padding:12px 15px;border-radius:10px;margin-bottom:16px;font-size:13px;line-height:1.7}
  .flash.ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
  .flash.err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
  .top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .top__n{font-size:14px;font-weight:700}
  .top__n b{color:#b45309}
  .btn{padding:10px 18px;border-radius:10px;border:1px solid var(--bd2);background:#fff;
    color:var(--fg);font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
    text-decoration:none;display:inline-flex;align-items:center;gap:6px}
  .btn:hover{border-color:var(--brand);color:var(--brand2)}
  .btn--pri{background:var(--brand);border-color:var(--brand);color:#fff;margin-left:auto}
  .btn--pri:hover{filter:brightness(1.08);color:#fff}
  .btn--pri:disabled{background:#e8edf5;border-color:#e8edf5;color:#8a94a6;cursor:default;filter:none}
  .btn--sm{padding:7px 13px;font-size:12px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle}
  th{font-size:11.5px;color:var(--mut);font-weight:700}
  tr.due{background:#fffbeb}
  code{font-family:ui-monospace,Consolas,monospace;font-size:12px}
  .tag{display:inline-block;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:999px}
  .t-on{background:#f0fdf4;color:#15803d}
  .t-no{background:#fef2f2;color:#b91c1c}
  .t-gray{background:#f1f5f9;color:#8a94a6}
  .t-due{background:#fff7ed;color:#b45309}
  .err{font-size:11.5px;color:#b91c1c;margin-top:3px}
  .empty{text-align:center;color:var(--mut);padding:40px 20px;font-size:13.5px}
  .note{font-size:12px;color:var(--mut);line-height:1.8;margin-top:14px}
  .testbar{background:#fffbeb;border:1px solid #f6d8a8;color:#92400e;
    border-radius:10px;padding:11px 14px;font-size:12.5px;margin-bottom:16px}
</style>
</head>
<body>
<div class="wrap">
  <h1>구독 결제 관리</h1>
  <div class="sub">결제일이 된 구독자를 확인하고 직접 결제합니다.</div>

  <?php if (!$IS_LIVE): ?>
    <div class="testbar"><b>테스트 키를 쓰고 있습니다.</b> 실제로 돈이 빠져나가지 않습니다.</div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="flash <?=h($flashType)?>"><?=h($flash)?></div>
  <?php endif; ?>

  <div class="card">
    <div class="top">
      <div class="top__n">
        구독자 <?=count($rows)?>명 ·
        오늘 결제 대상 <b><?=$dueCount?>명</b>
      </div>
      <form method="post" style="margin-left:auto"
            onsubmit="return confirm('결제일이 된 <?=$dueCount?>명에게 결제를 진행합니다. 계속할까요?')">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="act" value="charge_due">
        <button class="btn btn--pri" type="submit" <?= $dueCount ? '' : 'disabled' ?>>
          💳 결제일 된 <?=$dueCount?>명 결제하기
        </button>
      </form>
    </div>

    <?php if (!$rows): ?>
      <div class="empty">아직 구독자가 없습니다.</div>
    <?php else: ?>
      <div style="overflow-x:auto">
      <table>
        <tr>
          <th>회원</th><th>상태</th><th>요금제</th>
          <th>다음 결제</th><th>최근 결제</th><th style="text-align:right">개별</th>
        </tr>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $r['due'] ? 'due' : '' ?>">
            <td>
              <code><?=h($r['uid'])?></code>
              <?php if (!$r['card']): ?><br><span class="tag t-gray">카드 없음</span><?php endif; ?>
            </td>
            <td>
              <span class="tag <?= $r['status']==='active' ? 't-on' : ($r['status']==='payment_failed' ? 't-no' : 't-gray') ?>">
                <?=h($LABEL[$r['status']] ?? $r['status'])?>
              </span>
              <?php if ($r['err']): ?><div class="err"><?=h(mb_substr($r['err'],0,40))?></div><?php endif; ?>
            </td>
            <td><?=h($r['plan'] ?: '-')?><?php if ($r['price']): ?><br>
              <span style="font-size:11.5px;color:var(--mut)"><?=number_format($r['price'])?>원</span><?php endif; ?></td>
            <td>
              <?=h($r['next'] ?: '-')?>
              <?php if ($r['due']): ?><br><span class="tag t-due">결제 대상</span><?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--mut2)"><?=h($r['paid'] ?: '-')?></td>
            <td style="text-align:right">
              <?php if ($r['card']): ?>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('<?=h($r['uid'])?> 님에게 <?=number_format($r['price'])?>원을 결제합니다. 계속할까요?')">
                  <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                  <input type="hidden" name="act" value="charge_one">
                  <input type="hidden" name="uid" value="<?=h($r['uid'])?>">
                  <button class="btn btn--sm" type="submit">결제</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
    <?php endif; ?>

    <div class="note">
      <b>결제 대상</b>은 카드가 등록돼 있고, 다음 결제일이 오늘이거나 지났으며,
      오늘 아직 결제하지 않은 회원입니다.<br>
      같은 사람에게 하루 두 번 결제되지 않도록 막아 두었습니다.
      개별 <b>결제</b> 버튼은 날짜와 무관하게 즉시 청구하므로 신중히 눌러주세요.
    </div>
  </div>
</div>
</body>
</html>
