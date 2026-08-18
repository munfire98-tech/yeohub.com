<?php
/* =============================================================
   _imp.php — 대리 보기 중이라는 띠를 화면 위에 붙입니다
   ─────────────────────────────────────────────────────────────
   회원 화면 파일을 하나하나 고치지 않아도 되도록,
   여러 화면이 공통으로 부르는 파일에서 이 파일을 부르게 합니다.

     building_info.php / train_db.php / fire_plan_db.php / user_key.php
     맨 윗줄에 아래 한 줄을 넣으면 됩니다.

       @include_once __DIR__ . '/_imp.php';

   HTML 화면에만 붙고, 사진·JSON 응답에는 붙지 않습니다.
   ============================================================= */
declare(strict_types=1);

if (defined('IMP_BAR_READY')) return;
define('IMP_BAR_READY', 1);

/* 명령줄에서 돌 때는 아무것도 하지 않습니다 */
if (PHP_SAPI === 'cli') return;

ob_start(function (string $html): string {

  /* 세션이 없거나 대리 보기 중이 아니면 그대로 둡니다 */
  if (session_status() !== PHP_SESSION_ACTIVE) return $html;
  $imp = $_SESSION['_imp'] ?? null;
  if (!$imp || empty($imp['uid'])) return $html;

  /* HTML 화면일 때만 붙입니다 (사진·JSON 응답은 건드리지 않습니다) */
  $pos = strripos($html, '</body>');
  if ($pos === false) return $html;

  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $nick  = $e($imp['nick'] ?? $imp['uid']);
  $uid   = $e($imp['uid']);
  $admin = $e($imp['admin']['nickname'] ?? '관리자');

  /* 이 회원의 미처리 검토요청 수 */
  $pending = 0;
  $lf = __DIR__ . '/data/assist_log.json';
  if (is_file($lf)) {
    $rows = json_decode((string)@file_get_contents($lf), true);
    if (is_array($rows)) {
      foreach ($rows as $r) {
        if (($r['kind'] ?? '') !== 'review') continue;
        if ((string)($r['uid'] ?? '') !== (string)$imp['uid']) continue;
        if (($r['status'] ?? 'pending') === 'resolved') continue;
        $pending++;
      }
    }
  }

  $bar = '
<style>
  body{padding-top:46px !important}
  #impbar{position:fixed;top:0;left:0;right:0;z-index:99999;height:46px;
    background:linear-gradient(90deg,#b45309,#d97706);color:#fff;
    display:flex;align-items:center;gap:12px;padding:0 16px;
    font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;font-size:13.5px;
    box-shadow:0 2px 10px rgba(0,0,0,.18)}
  #impbar b{font-weight:800}
  #impbar .who{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  #impbar .uid{opacity:.85;font-size:12px}
  #impbar a{color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.6);
    border-radius:8px;padding:6px 12px;font-weight:700;font-size:12.5px;white-space:nowrap}
  #impbar a:hover{background:rgba(255,255,255,.18)}
  #impbar a.rv{background:#fff;color:#b45309;border-color:#fff}
  @media print{#impbar{display:none}body{padding-top:0 !important}}
  @media(max-width:560px){
    #impbar{font-size:12px;gap:8px;padding:0 10px}
    #impbar .uid{display:none}
  }
</style>
<div id="impbar">
  <span>👤</span>
  <span class="who"><b>' . $nick . '</b>님 계정으로 보는 중
    <span class="uid">(' . $uid . ' · ' . $admin . ')</span></span>';

  if ($pending > 0) {
    $bar .= '<a class="rv" href="/admin_member_review.php?uid=' . rawurlencode((string)$imp['uid']) . '">
      검토요청 ' . $pending . '건</a>';
  }

  $bar .= '<a href="/impersonate.php?stop=1">관리자로 돌아가기</a>
</div>
';

  return substr($html, 0, $pos) . $bar . substr($html, $pos);
});
