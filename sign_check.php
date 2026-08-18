<?php
/** sign_check.php — 서명 저장 진단 (확인 후 삭제) */
declare(strict_types=1);
if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!(is_admin() || !empty($_SESSION['is_user']))) { exit('로그인 후 이용하세요.'); }

$uid  = $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest'));
$uid  = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uid);
$BASE = __DIR__ . '/data/worklog/' . $uid;

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:system-ui,'Malgun Gothic',sans-serif;max-width:800px;margin:26px auto;padding:0 16px;line-height:1.75;color:#1a2436}
.b{background:#f8fafc;border:1px solid #dde3ec;border-radius:9px;padding:11px 13px;margin:8px 0;font-size:13px;word-break:break-all}
.ok{color:#059669;font-weight:700}.bad{color:#dc2626;font-weight:700}.wn{color:#b45309;font-weight:700}
code{background:#f2f4f8;padding:2px 6px;border-radius:4px}h3{margin-top:22px}</style>";
echo "<h2>서명 저장 진단</h2>";

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  echo "<h3>■ 전송 결과</h3>";
  $s = (string)($_POST['tsign'] ?? '');
  echo "<div class='b'>";
  echo "POST 총 크기: <b>" . number_format((int)($_SERVER['CONTENT_LENGTH'] ?? 0)) . "</b> bytes<br>";
  echo "받은 서명 길이: <b>" . number_format(strlen($s)) . "</b><br>";
  echo "형식: " . (preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $s)
       ? "<span class='ok'>정상</span>" : "<span class='bad'>깨짐/없음</span>");
  echo "</div>";
  if (strlen($s) > 100) {
    echo "<p class='ok'>서명이 서버까지 전달됩니다.</p>";
    echo "<img src='" . h($s) . "' style='border:1px solid #ddd;border-radius:6px;max-height:60px'><br>";
    if (!is_dir($BASE)) @mkdir($BASE, 0775, true);
    $tf = $BASE . '/_t.json';
    $r  = @file_put_contents($tf, json_encode(['data'=>$s]));
    if ($r !== false) { echo "<p class='ok'>파일 저장 성공 (" . number_format((int)$r) . " bytes)</p>"; @unlink($tf); }
    else { echo "<p class='bad'>파일 저장 실패 - 폴더 권한 문제</p>"; }
  } else {
    echo "<p class='bad'>서명이 서버에 도착하지 않았습니다.</p>";
    echo "<p>post_max_size = <code>" . h(ini_get('post_max_size')) . "</code></p>";
  }
}

echo "<h3>1. 서버 설정</h3><div class='b'>";
echo "post_max_size: <b>" . h(ini_get('post_max_size')) . "</b><br>";
echo "memory_limit: " . h(ini_get('memory_limit')) . "<br>";
echo "PHP: " . PHP_VERSION . "</div>";

echo "<h3>2. 저장 폴더</h3><div class='b'>" . h($BASE) . "</div>";
if (!is_dir($BASE)) {
  echo "<p class='wn'>폴더 없음 - 생성 시도</p>";
  echo @mkdir($BASE, 0775, true) ? "<p class='ok'>생성됨</p>" : "<p class='bad'>생성 실패</p>";
}
if (is_dir($BASE)) {
  echo is_writable($BASE) ? "<p class='ok'>쓰기 가능</p>" : "<p class='bad'>쓰기 불가 (권한 755/775 확인)</p>";
}

echo "<h3>3. 저장된 파일</h3>";
$sf = $BASE . '/signature.json';
if (is_file($sf)) {
  $d = json_decode((string)@file_get_contents($sf), true);
  $len = strlen((string)($d['data'] ?? ''));
  echo "<p class='ok'>signature.json 있음 (" . number_format($len) . "자)</p>";
  if ($len > 0) echo "<img src='" . h($d['data']) . "' style='border:1px solid #ddd;border-radius:6px;max-height:60px'>";
} else {
  echo "<p class='wn'>signature.json 없음</p>";
}
$n = 0;
foreach (glob($BASE . '/m*.json') ?: [] as $f) {
  $d = json_decode((string)@file_get_contents($f), true);
  if (!is_array($d)) continue;
  $n++;
  $sl = strlen((string)($d['sign'] ?? ''));
  echo "<div class='b'><b>" . h(basename($f)) . "</b> - 서명 " .
       ($sl ? "<span class='ok'>있음(" . number_format($sl) . ")</span>" : "<span class='bad'>없음</span>") .
       " / 항목 " . (isset($d['sobang']) ? "<span class='ok'>있음</span>" : "<span class='bad'>없음</span>") . "</div>";
}
if (!$n) echo "<p class='wn'>월별 기록 파일 없음</p>";
?>
<h3>4. 직접 테스트</h3>
<form method="post">
  <div style="border:1px dashed #cbd5e1;border-radius:10px;background:#fbfcfe;position:relative">
    <canvas id="pad" style="display:block;width:100%;height:150px;touch-action:none"></canvas>
    <div id="hint" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#b9c2d2;font-size:13px;pointer-events:none">여기에 서명</div>
  </div>
  <input type="hidden" name="tsign" id="ts">
  <div style="margin-top:10px;display:flex;gap:8px">
    <button type="button" id="c" style="padding:9px 16px;border:1px solid #cfd7e3;border-radius:8px;background:#fff;cursor:pointer;font-family:inherit">지우기</button>
    <button type="submit" id="s" style="padding:9px 18px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;font-family:inherit">전송 테스트</button>
  </div>
</form>
<script>
(function(){
  var p=document.getElementById('pad'),hi=document.getElementById('hint'),x,dr=false,dt=false;
  function su(){var r=p.getBoundingClientRect(),d=Math.min(devicePixelRatio||1,2);
    p.width=Math.round(r.width*d);p.height=Math.round(r.height*d);
    x=p.getContext('2d');x.scale(d,d);x.lineWidth=2.2;x.lineCap='round';x.lineJoin='round';x.strokeStyle='#111';}
  su();
  function po(e){var r=p.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return{x:t.clientX-r.left,y:t.clientY-r.top};}
  p.addEventListener('pointerdown',function(e){e.preventDefault();dr=true;dt=true;hi.style.display='none';
    var q=po(e);x.beginPath();x.moveTo(q.x,q.y);});
  p.addEventListener('pointermove',function(e){if(!dr)return;e.preventDefault();var q=po(e);x.lineTo(q.x,q.y);x.stroke();});
  addEventListener('pointerup',function(){dr=false;});
  document.getElementById('c').onclick=function(){x.clearRect(0,0,p.width,p.height);dt=false;hi.style.display='flex';};
  document.getElementById('s').onclick=function(e){
    if(!dt){alert('서명을 그려주세요');e.preventDefault();return false;}
    document.getElementById('ts').value=p.toDataURL('image/png');
  };
})();
</script>
<hr style="margin:24px 0;border:0;border-top:1px solid #e5e7eb">
<p style="color:#b45309;font-size:13px">확인 후 sign_check.php를 삭제하세요.</p>
