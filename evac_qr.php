<?php
/**
 * evac_qr.php — 피난 시뮬 공유 QR (관리자용)
 *
 *   관리자가 도면 하나를 골라 공유를 켜고, 그 열람 주소의 QR 을 만든다.
 *   여러 사람이 QR 을 찍으면 각자 폰에서 대피 시뮬을 본다.
 *
 *     /evac_qr.php?id=모델ID
 */
declare(strict_types=1);

/* ── 세션 설정: login.php / admin_members.php 와 동일해야
   관리자 로그인이 인식된다. 쿠키 도메인을 .tworix.com 으로 맞춘다. ── */
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
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/evac_common.php';

$id    = evac_clean_id((string)($_GET['id'] ?? ''));
$model = $id !== '' ? evac_load_model($id) : null;
if (!$model) { http_response_code(404); exit('없는 도면입니다.'); }

/* 접근 권한: 관리자이거나, 이 도면을 배정받은 회원이어야 한다. */
$isAdmin = evac_is_admin();
$myUid   = (string)($_SESSION['member_id'] ?? '');
$allowed = $isAdmin;
if (!$allowed && $myUid !== '') {
    $assign = evac_load_assign();
    $mine = $assign[$myUid] ?? [];
    $allowed = is_array($mine) && in_array($id, $mine, true);
}
if (!$allowed) {
    http_response_code(403);
    exit('이 도면에 접근할 권한이 없습니다.');
}

$name   = (string)($model['name'] ?? '피난 시뮬레이션');
$shared = !empty($model['share']);

/* 공개 열람 주소 (절대경로) */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$viewUrl = $scheme . '://' . $host . '/evac_view.php?id=' . rawurlencode($id);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$CSRF = (string)$_SESSION['csrf'];
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>공유 QR · <?=h($name)?></title>
<style>
  :root{--ink:#14181f;--mut:#6b7280;--line:#e5e7eb;--accent:#1d4ed8;--ok:#15803d}
  *{box-sizing:border-box}
  body{margin:0;background:#f5f6f8;color:var(--ink);
       font-family:-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo','Malgun Gothic',sans-serif}
  .wrap{max-width:440px;margin:0 auto;padding:26px 18px 60px}
  .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;text-align:center}
  h1{font-size:1.15rem;margin:0 0 4px}
  .sub{color:var(--mut);font-size:.86rem;margin:0 0 18px}
  .qr{width:240px;height:240px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;
      border:1px solid var(--line);border-radius:12px;background:#fff}
  .qr canvas,.qr img{width:220px;height:220px}
  .url{font-size:.78rem;color:var(--mut);word-break:break-all;font-family:ui-monospace,monospace;
       background:#f5f6f8;border-radius:8px;padding:9px 11px;margin:0 0 16px}
  .row{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
  button,.btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:9px;
       padding:10px 15px;font-size:.9rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none}
  button.pri{background:var(--accent);border-color:var(--accent);color:#fff}
  .status{display:inline-flex;align-items:center;gap:6px;font-size:.82rem;margin:0 0 16px;
          padding:5px 11px;border-radius:20px}
  .status.on{background:#f0fdf4;color:var(--ok)}
  .status.off{background:#fef2f2;color:#b91c1c}
  .status .dot{width:7px;height:7px;border-radius:50%;background:currentColor}
  .note{font-size:.78rem;color:var(--mut);margin:16px 0 0;line-height:1.6}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1><?=h($name)?></h1>
    <p class="sub">피난 시뮬레이션 공유 QR</p>

    <div class="status <?=$shared?'on':'off'?>" id="status">
      <span class="dot"></span>
      <span id="statusText"><?=$shared?'공유 중 — 누구나 열람 가능':'공유 꺼짐'?></span>
    </div>

    <div class="qr" id="qrBox"><span style="color:#9aa2b1;font-size:.85rem">QR 생성 중…</span></div>
    <div class="url"><?=h($viewUrl)?></div>

    <div class="row">
      <?php if ($isAdmin): ?>
        <button id="toggleBtn" class="<?=$shared?'':'pri'?>"><?=$shared?'공유 끄기':'공유 켜고 QR 만들기'?></button>
      <?php endif; ?>
      <button id="dlBtn" class="<?=$isAdmin?'':'pri'?>">QR 저장</button>
      <a class="btn" href="<?=h($viewUrl)?>" target="_blank" rel="noopener">미리보기</a>
    </div>

    <p class="note">
      QR 을 인쇄하거나 화면에 띄워 여러 사람이 각자 스마트폰으로 스캔하면,
      로그인 없이 대피 시뮬레이션을 볼 수 있습니다.
      공유를 끄면 QR 주소는 더 이상 열리지 않습니다.
    </p>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  const VIEW_URL = <?=json_encode($viewUrl)?>;
  const MODEL_ID = <?=json_encode($id)?>;
  const CSRF     = <?=json_encode($CSRF)?>;
  let shared     = <?=$shared?'true':'false'?>;

  const qrBox = document.getElementById('qrBox');
  let qr = null;
  function drawQR(){
    qrBox.innerHTML = '';
    qr = new QRCode(qrBox, {
      text: VIEW_URL, width: 220, height: 220,
      colorDark: '#14181f', colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }
  drawQR();

  const status = document.getElementById('status');
  const statusText = document.getElementById('statusText');
  const toggleBtn = document.getElementById('toggleBtn');
  const IS_ADMIN = <?=$isAdmin?'true':'false'?>;

  function paint(){
    if(!toggleBtn) return;
    status.className = 'status ' + (shared ? 'on' : 'off');
    statusText.textContent = shared ? '공유 중 — 누구나 열람 가능' : '공유 꺼짐';
    toggleBtn.textContent = shared ? '공유 끄기' : '공유 켜고 QR 만들기';
    toggleBtn.className = shared ? '' : 'pri';
  }

  if (toggleBtn) toggleBtn.addEventListener('click', async ()=>{
    toggleBtn.disabled = true;
    try{
      const body = new URLSearchParams({ act:'share', id:MODEL_ID, on: shared?'0':'1', csrf:CSRF });
      const r = await fetch('/evac_library_api.php', { method:'POST', body });
      const j = await r.json();
      if(!j.ok){ alert(j.error || '실패했습니다.'); }
      else { shared = j.share; paint(); }
    }catch(e){ alert('통신 오류가 발생했습니다.'); }
    toggleBtn.disabled = false;
  });

  document.getElementById('dlBtn').addEventListener('click', ()=>{
    const cv = qrBox.querySelector('canvas');
    const img = qrBox.querySelector('img');
    let url;
    if(cv) url = cv.toDataURL('image/png');
    else if(img) url = img.src;
    else return;
    const a = document.createElement('a');
    a.href = url; a.download = 'evac_qr_' + MODEL_ID + '.png';
    document.body.appendChild(a); a.click(); a.remove();
  });
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
