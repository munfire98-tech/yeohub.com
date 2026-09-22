<?php
/* =============================================================
   map_check.php — 카카오 지도가 왜 안 뜨는지 확인하는 진단 페이지
   ─────────────────────────────────────────────────────────────
   building_setup_chat.php 와 같은 폴더에 올리고 브라우저로 여세요.
   확인이 끝나면 이 파일은 지우시면 됩니다.
   ============================================================= */
declare(strict_types=1);

$API = @require __DIR__ . '/api_keys.php';
$restKey = is_array($API) ? (string)($API['kakao'] ?? '') : '';
$jsKey   = is_array($API) ? (string)($API['kakao_js'] ?? '') : '';

function mask(string $k): string {
  if ($k === '') return '(비어 있음)';
  if (strlen($k) <= 8) return $k;
  return substr($k, 0, 6) . str_repeat('*', max(0, strlen($k) - 10)) . substr($k, -4);
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . $host;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>지도 진단</title>
<style>
  body{font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;background:#f5f7fb;color:#1a2436;
    margin:0;padding:24px;line-height:1.6}
  .wrap{max-width:760px;margin:0 auto}
  h1{font-size:20px;margin:0 0 4px}
  .sub{color:#7a8699;font-size:13px;margin-bottom:20px}
  .card{background:#fff;border:1px solid #e3e8f0;border-radius:12px;padding:18px 20px;margin-bottom:14px}
  .card h2{font-size:14px;margin:0 0 12px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  td{padding:7px 4px;border-bottom:1px solid #eef2f7;vertical-align:top}
  td:first-child{color:#7a8699;width:150px}
  code{background:#f1f5f9;padding:2px 6px;border-radius:5px;font-size:12px;word-break:break-all}
  .ok{color:#15803d;font-weight:700}
  .bad{color:#dc2626;font-weight:700}
  .warn{color:#b45309;font-weight:700}
  #result{font-size:14px;font-weight:700;padding:12px 14px;border-radius:9px;margin-top:10px}
  #map{width:100%;height:300px;background:#eef2f7;border-radius:10px;margin-top:10px}
  .hint{font-size:12.5px;color:#56627a;background:#f8fafc;border-radius:8px;padding:11px 13px;margin-top:12px;line-height:1.7}
</style>
</head>
<body>
<div class="wrap">
  <h1>카카오 지도 진단</h1>
  <div class="sub">지도가 왜 안 뜨는지 단계별로 확인합니다.</div>

  <div class="card">
    <h2>1. 서버에서 읽은 값</h2>
    <table>
      <tr><td>api_keys.php</td>
          <td><?= is_array($API) ? '<span class="ok">읽기 성공</span>' : '<span class="bad">읽기 실패 — 파일이 없거나 형식 오류</span>' ?></td></tr>
      <tr><td>REST 키</td><td><code><?=htmlspecialchars(mask($restKey))?></code></td></tr>
      <tr><td>JavaScript 키</td>
          <td><code><?=htmlspecialchars(mask($jsKey))?></code>
          <?php if ($jsKey === ''): ?>
            <br><span class="bad">→ 비어 있습니다. 이게 원인입니다.</span>
          <?php elseif ($jsKey === $restKey): ?>
            <br><span class="bad">→ REST 키와 같은 값입니다! JavaScript 키를 따로 복사해 넣으세요.</span>
          <?php else: ?>
            <br><span class="ok">→ 값이 들어 있고 REST 키와 다릅니다 (정상)</span>
          <?php endif; ?>
          </td></tr>
      <tr><td>이 사이트 주소</td><td><code><?=htmlspecialchars($origin)?></code>
          <br><span class="warn">→ 카카오 개발자센터 &gt; 플랫폼 &gt; Web 에 이 주소가 등록돼 있어야 합니다.</span></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>2. 브라우저에서 SDK 불러오기</h2>
    <div id="result">확인 중…</div>
    <div id="map"></div>
    <div class="hint" id="hint"></div>
  </div>
</div>

<script>
var JS_KEY = <?=json_encode($jsKey)?>;
var box = document.getElementById('result');
var hint = document.getElementById('hint');

function show(cls, msg, tip){
  box.className = '';
  box.style.background = cls === 'ok' ? '#eefaf1' : (cls === 'bad' ? '#fdeceb' : '#fffbeb');
  box.style.color      = cls === 'ok' ? '#15803d' : (cls === 'bad' ? '#b91c1c' : '#b45309');
  box.textContent = msg;
  hint.innerHTML = tip || '';
}

if (!JS_KEY) {
  show('bad', '✕ JavaScript 키가 비어 있습니다.',
    '<b>해결:</b> 카카오 개발자센터 → 내 애플리케이션 → 앱 키 → <b>JavaScript 키</b>를 복사해서 ' +
    'api_keys.php 의 <code>\'kakao_js\' => \'\'</code> 안에 넣으세요.');
} else {
  var sc = document.createElement('script');
  sc.src = 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' + encodeURIComponent(JS_KEY) + '&autoload=false';

  sc.onerror = function(){
    show('bad', '✕ SDK 파일을 아예 불러오지 못했습니다.',
      '<b>가능한 원인:</b><br>' +
      '· 인터넷/방화벽이 dapi.kakao.com 을 막고 있음<br>' +
      '· 키 형식이 잘못됨<br>' +
      '<b>확인:</b> F12 → Network 탭에서 <code>sdk.js</code> 의 상태 코드를 보세요.');
  };

  sc.onload = function(){
    if (typeof kakao === 'undefined' || !kakao.maps) {
      show('bad', '✕ SDK 는 받았지만 kakao.maps 가 없습니다.',
        '키가 유효하지 않을 가능성이 높습니다. JavaScript 키가 맞는지 확인하세요.');
      return;
    }
    kakao.maps.load(function(){
      try {
        var map = new kakao.maps.Map(document.getElementById('map'), {
          center: new kakao.maps.LatLng(37.5665, 126.9780), level: 5
        });
        new kakao.maps.Marker({ map: map, position: new kakao.maps.LatLng(37.5665, 126.9780) });
        show('ok', '✓ 지도가 정상적으로 떴습니다!',
          '아래에 서울시청 지도가 보이면 키·도메인 설정이 모두 정상입니다.<br>' +
          '이 경우 building_setup_chat.php 쪽 문제이니 알려주세요.');
      } catch (e) {
        show('bad', '✕ 지도 생성 중 오류: ' + e.message, '');
      }
    });
  };

  document.head.appendChild(sc);

  // 8초 안에 아무 반응 없으면 대개 도메인 차단입니다.
  setTimeout(function(){
    if (box.textContent === '확인 중…') {
      show('bad', '✕ 응답이 없습니다 (도메인 미등록 가능성 높음).',
        '<b>해결:</b> 카카오 개발자센터 → 내 애플리케이션 → <b>플랫폼 → Web</b> 에서 ' +
        '<code><?=htmlspecialchars($origin)?></code> 을 사이트 도메인으로 등록하세요.<br>' +
        '한글 도메인이면 퓨니코드(<code>https://xn--...</code>) 형태로도 함께 등록해야 할 수 있습니다.');
    }
  }, 8000);
}
</script>
</body>
</html>
