<?php
/* endpoint_probe.php — 건축물대장 API 엔드포인트 자동 탐색
 *
 * 문제: NO_OPENAPI_SERVICE_ERROR = 서비스 경로(엔드포인트)가 틀림.
 * 해결: 알려진 후보 경로들을 한꺼번에 호출해서 어느 게 통과하는지 찾아냄.
 *       (키·주소는 이미 검증됨: juso로 나온 서울시청 코드를 그대로 사용)
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

const HUB_KEY = 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==';

$hubIn = trim($_POST['hub_key'] ?? '');
$hubKey = $hubIn !== '' ? $hubIn : HUB_KEY;
if (strpos($hubKey,'%')!==false) $hubKey = urldecode($hubKey);

/* 테스트 좌표: 검증된 지번들 (juso가 확인해준 서울시청 + 사용가이드의 판교/강남) */
$sigunguCd = trim($_POST['sigunguCd'] ?? '41135');
$bjdongCd  = trim($_POST['bjdongCd']  ?? '11000');
$bun       = trim($_POST['bun'] ?? '542');
$ji        = trim($_POST['ji']  ?? '0');

/* 시도할 후보 엔드포인트들 (기관코드 · 서비스명 조합) */
$CANDIDATES = [
  'apis.data.go.kr/1613000/BldRgstHubService/getBrRecapTitleInfo',
  'apis.data.go.kr/1613000/BldRgstHubService_v2/getBrRecapTitleInfo',
  'apis.data.go.kr/1613000/BldRgstService_v2/getBrRecapTitleInfo',
  'apis.data.go.kr/1611000/BldRgstService_v2/getBrRecapTitleInfo',
  'apis.data.go.kr/1611000/BldRgstService/getBrRecapTitleInfo',
  'apis.data.go.kr/1613000/ArchPmsHubService/getApBasisOulnInfo',
];

function http_get(string $url): array {
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $b=curl_exec($ch); $e=curl_error($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['body'=>(string)$b,'err'=>$e,'code'=>$c];
}

/* 응답을 짧게 진단 */
function diagnose(string $body): array {
  // 성공: item 존재 / 정상 헤더
  $j = json_decode($body, true);
  if ($j !== null) {
    $rc = $j['response']['header']['resultCode'] ?? null;
    $items = $j['response']['body']['items']['item'] ?? null;
    if ($items) return ['ok','정상 — 데이터 수신 ✔'];
    if ($rc==='00'||$rc==='0') return ['warn','경로 정상, 다만 이 지번엔 데이터 없음'];
    if ($rc!==null) return ['warn','응답코드 '.$rc.' — '.($j['response']['header']['resultMsg']??'')];
  }
  // XML/텍스트 에러들
  if (stripos($body,'NO_OPENAPI_SERVICE')!==false) return ['no','경로 없음 (서비스명 틀림)'];
  if (stripos($body,'SERVICE_KEY_IS_NOT_REGISTERED')!==false||stripos($body,'SERVICE KEY IS NOT REGISTERED')!==false)
    return ['no','키 미등록 (이 서비스에 신청 안 됨/승인대기)'];
  if (stripos($body,'LIMITED_NUMBER_OF_SERVICE')!==false) return ['warn','일일 호출한도 초과'];
  if (stripos($body,'<item>')!==false||stripos($body,'"item"')!==false) return ['ok','정상 — 데이터 수신 ✔'];
  if (stripos($body,'resultCode>00')!==false) return ['warn','경로 정상, 데이터 없음'];
  return ['no','알 수 없는 응답'];
}

$run=($_SERVER['REQUEST_METHOD']==='POST');
$rows=[];
if($run){
  $p=['sigunguCd'=>$sigunguCd,'bjdongCd'=>$bjdongCd,'platGbCd'=>'0',
      'bun'=>str_pad($bun?:'0',4,'0',STR_PAD_LEFT),'ji'=>str_pad($ji?:'0',4,'0',STR_PAD_LEFT)];
  foreach($CANDIDATES as $ep){
    $url='http://'.$ep.'?'.http_build_query(array_merge($p,[
      'serviceKey'=>$hubKey,'numOfRows'=>5,'pageNo'=>1,'_type'=>'json']));
    $res=http_get($url);
    [$stat,$msg]=diagnose($res['body']);
    $rows[]=['ep'=>$ep,'http'=>$res['code'],'stat'=>$stat,'msg'=>$msg,'body'=>$res['body']];
  }
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function snip($b){ $b=trim((string)$b); return mb_substr($b,0,600); }
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>엔드포인트 자동 탐색</title>
<style>
  :root{--navy:#3a5572;--red:#c0392b;--ok:#16a34a;--warn:#b8860b;--ink:#1f2430;--mut:#6b7280;
    --bg:#eef1f6;--card:#fff;--brd:#e3e7ee}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Pretendard','Malgun Gothic',sans-serif;background:var(--bg);color:var(--ink);
    font-size:14px;line-height:1.6;padding:22px 16px 80px}
  .wrap{max-width:900px;margin:0 auto}
  h1{font-size:19px;font-weight:800;margin-bottom:4px}
  .sub{color:var(--mut);font-size:13px;margin-bottom:16px}
  .card{background:var(--card);border:1px solid var(--brd);border-radius:12px;padding:16px;margin-bottom:14px}
  label{display:block;font-size:12px;font-weight:700;color:var(--mut);margin:0 0 5px}
  input[type=text]{width:100%;border:1px solid #d6dce6;border-radius:9px;padding:9px 11px;
    font-size:13px;font-family:inherit;outline:none;margin-bottom:10px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
  @media(max-width:560px){.grid{grid-template-columns:1fr 1fr}}
  .btn{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:11px 22px;
    font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:6px}
  .row{border:1px solid var(--brd);border-radius:10px;padding:12px 14px;margin-bottom:10px}
  .row.ok{border-color:#bfe6cb;background:#f2fbf5}
  .row.warn{border-color:#ecdca6;background:#fdfaf0}
  .row.no{border-color:#ecccc6;background:#fdf4f2}
  .ep{font-family:ui-monospace,Consolas,monospace;font-size:12px;word-break:break-all;color:#2b3446}
  .verdict{display:inline-block;font-size:12px;font-weight:800;border-radius:999px;padding:2px 10px;margin-left:6px}
  .verdict.ok{background:#dcf5e5;color:#137a3a}
  .verdict.warn{background:#f6ecc9;color:#8a6d1a}
  .verdict.no{background:#f6d9d2;color:#a83224}
  .msg{font-size:12.5px;color:#444;margin-top:5px}
  details{margin-top:6px}summary{cursor:pointer;font-size:11.5px;color:var(--navy)}
  pre{background:#0f172a;color:#d1e0f5;border-radius:8px;padding:10px;overflow:auto;font-size:11px;
    margin-top:6px;max-height:220px}
  .win{background:#eefaf1;border:1px solid #bfe6cb;border-radius:10px;padding:13px 15px;margin-bottom:14px}
  .win b{color:#137a3a}
</style></head><body>
<div class="wrap">
  <h1>🎯 엔드포인트 자동 탐색</h1>
  <div class="sub">키·주소는 이미 정상입니다. 문제는 API 경로뿐이라, 후보 경로들을 한꺼번에 호출해 정답을 찾습니다.</div>

  <form method="post" class="card">
    <label>건축물대장 키 (비우면 코드값 사용)</label>
    <input type="text" name="hub_key" value="<?=h($hubIn)?>" placeholder="Bgl2NmDm...==">
    <label>테스트 지번 (기본: 판교 백현동 542 — 사용가이드 검증 지번)</label>
    <div class="grid">
      <input type="text" name="sigunguCd" value="<?=h($sigunguCd)?>" placeholder="sigunguCd">
      <input type="text" name="bjdongCd"  value="<?=h($bjdongCd)?>"  placeholder="bjdongCd">
      <input type="text" name="bun" value="<?=h($bun)?>" placeholder="bun">
      <input type="text" name="ji"  value="<?=h($ji)?>"  placeholder="ji">
    </div>
    <button class="btn" type="submit">🔍 후보 경로 전부 테스트</button>
  </form>

<?php if($run): ?>
  <?php
    $winner=null;
    foreach($rows as $r){ if($r['stat']==='ok'||$r['stat']==='warn'){ $winner=$r; break; } }
  ?>
  <?php if($winner): ?>
    <div class="win">✅ <b>정답 경로를 찾았습니다:</b><br>
      <span class="ep"><?=h($winner['ep'])?></span><br>
      <span class="msg"><?=h($winner['msg'])?> — 이 경로를 실제 코드에 쓰면 됩니다.</span></div>
  <?php else: ?>
    <div class="win" style="background:#fdf4f2;border-color:#ecccc6">❌ <b style="color:#a83224">아직 통과한 경로가 없습니다.</b>
      아래 각 응답을 보고 원인을 확인하세요(키 미등록이면 승인 대기, 전부 경로없음이면 서비스 상세페이지에서 정확한 URL 확인 필요).</div>
  <?php endif; ?>

  <?php foreach($rows as $r): ?>
    <div class="row <?=h($r['stat'])?>">
      <span class="ep"><?=h($r['ep'])?></span>
      <span class="verdict <?=h($r['stat'])?>"><?= $r['stat']==='ok'?'통과':($r['stat']==='warn'?'경로OK':'실패') ?></span>
      <span style="font-size:11px;color:#888">HTTP <?=h((string)$r['http'])?></span>
      <div class="msg"><?=h($r['msg'])?></div>
      <details><summary>응답 일부</summary><pre><?=h(snip($r['body']))?></pre></details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>
</body></html>
