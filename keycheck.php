<?php
/* keycheck.php — juso 키 + 건축물대장 키, 둘 다 살아있는지 한 번에 점검
 *
 * 목적: "두 키가 작동하나?"만 명확히 확인. 주소 하나 넣으면
 *   [1단계] juso 검색 API 로 코드 변환 → 통과/실패
 *   [2단계] 건축HUB 로 건물조회        → 통과/실패
 *   를 큼직한 판정으로 보여줌.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

/* ── 키 두 개 (여기 넣거나 화면에서 입력) ───────────────────────── */
const JUSO_KEY = '';   // juso confmKey (예: devU01TX0FVVEg...=)  ← 여기에 넣으세요
const HUB_KEY  = 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==';

$jusoIn = trim($_POST['juso_key'] ?? '');
$hubIn  = trim($_POST['hub_key']  ?? '');
$jusoKey = $jusoIn !== '' ? $jusoIn : JUSO_KEY;
$hubKey  = $hubIn  !== '' ? $hubIn  : HUB_KEY;
if (strpos($hubKey, '%') !== false) $hubKey = urldecode($hubKey);   // 인코딩 키 방어
$addr = trim($_POST['addr'] ?? '');

function http_get(string $url): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>['Accept: application/json'],
  ]);
  $b=curl_exec($ch); $e=curl_error($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['body'=>(string)$b,'err'=>$e,'code'=>$c];
}

/* 1단계: juso 검색 API */
function check_juso(string $key, string $addr): array {
  $out = ['pass'=>false,'msg'=>'','raw'=>null,'parsed'=>null];
  if ($key==='') { $out['msg']='juso 키가 비어 있습니다.'; return $out; }
  $url='https://business.juso.go.kr/addrlink/addrLinkApi.do?'.http_build_query([
    'confmKey'=>$key,'currentPage'=>1,'countPerPage'=>5,'keyword'=>$addr,'resultType'=>'json',
  ]);
  $res=http_get($url); $out['raw']=$res;
  $j=json_decode($res['body'],true);
  if($j===null){ $out['msg']='응답을 JSON으로 읽지 못했습니다(키/네트워크 확인).'; return $out; }
  $common=$j['results']['common']??[];
  $ec=(string)($common['errorCode']??'?');
  if($ec!=='0'){ $out['msg']='juso 오류코드 '.$ec.' — '.($common['errorMessage']??''); return $out; }
  $list=$j['results']['juso']??[];
  if(!$list){ $out['msg']='주소 검색 결과가 없습니다(주소를 더 정확히 입력).'; return $out; }
  $a=$list[0]; $adm=(string)($a['admCd']??'');
  $out['pass']=true;
  $out['parsed']=[
    'roadAddr'=>$a['roadAddr']??'', 'jibunAddr'=>$a['jibunAddr']??'',
    'sigunguCd'=>substr($adm,0,5), 'bjdongCd'=>substr($adm,5,5),
    'platGbCd'=>(($a['mtYn']??'0')==='1')?'1':'0',
    'bun'=>str_pad((string)($a['lnbrMnnm']??'0'),4,'0',STR_PAD_LEFT),
    'ji'=>str_pad((string)($a['lnbrSlno']??'0'),4,'0',STR_PAD_LEFT),
    'candCount'=>count($list),
  ];
  return $out;
}

/* 2단계: 건축HUB (총괄표제부 → 없으면 표제부) */
function check_hub(string $key, array $p): array {
  $out=['pass'=>false,'msg'=>'','raw'=>null,'item'=>null,'op'=>''];
  if($key===''){ $out['msg']='건축물대장 키가 비어 있습니다.'; return $out; }
  foreach(['getBrRecapTitleInfo'=>'총괄표제부','getBrTitleInfo'=>'표제부'] as $op=>$nm){
    $url='https://apis.data.go.kr/1613000/BldRgstService_v2/'.$op.'?'.http_build_query([
      'serviceKey'=>$key,'sigunguCd'=>$p['sigunguCd'],'bjdongCd'=>$p['bjdongCd'],
      'platGbCd'=>$p['platGbCd'],'bun'=>$p['bun'],'ji'=>$p['ji'],
      'numOfRows'=>10,'pageNo'=>1,'_type'=>'json',
    ]);
    $res=http_get($url);
    if($out['raw']===null) $out['raw']=$res;   // 첫 호출 원시응답 보관
    $j=json_decode($res['body'],true);
    $hdr=$j['response']['header']??[];
    $rc=(string)($hdr['resultCode']??'');
    if(stripos($res['body'],'SERVICE KEY IS NOT REGISTERED')!==false){
      $out['msg']='키가 아직 등록/승인되지 않았습니다(신청 직후면 1~2시간 대기).'; $out['raw']=$res; return $out;
    }
    $items=$j['response']['body']['items']['item']??[];
    if(isset($items['bldNm'])||isset($items['platPlc'])) $items=[$items];
    if(is_array($items)&&$items){
      $out['pass']=true; $out['item']=$items[0]; $out['op']=$nm; $out['raw']=$res; return $out;
    }
    if($rc!==''&&$rc!=='00'&&$rc!=='0'){
      $out['msg']='HUB 응답코드 '.$rc.' — '.($hdr['resultMsg']??''); $out['raw']=$res;
    }
  }
  if($out['msg']==='') $out['msg']='키는 정상 응답했지만 이 지번에 건물 데이터가 없습니다(다른 주소로 시도).';
  return $out;
}

$run=($_SERVER['REQUEST_METHOD']==='POST' && $addr!=='');
$r1=$r2=null;
if($run){
  $r1=check_juso($jusoKey,$addr);
  if($r1['pass']) $r2=check_hub($hubKey,$r1['parsed']);
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function pretty($b){$j=json_decode((string)$b,true);return $j!==null?json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):(string)$b;}
$LAB=['bldNm'=>'건물명','platPlc'=>'대지위치','newPlatPlc'=>'도로명주소','mainPurpsCdNm'=>'주용도',
  'totArea'=>'연면적(㎡)','archArea'=>'건축면적(㎡)','grndFlrCnt'=>'지상층','ugrndFlrCnt'=>'지하층',
  'useAprDay'=>'사용승인일'];
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>두 키 작동 점검</title>
<style>
  :root{--navy:#3a5572;--red:#c0392b;--ink:#1f2430;--mut:#6b7280;--bg:#eef1f6;
    --card:#fff;--brd:#e3e7ee;--ok:#16a34a;--radius:12px}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Pretendard','Malgun Gothic',sans-serif;background:var(--bg);color:var(--ink);
    font-size:14px;line-height:1.6;padding:22px 16px 80px}
  .wrap{max-width:820px;margin:0 auto}
  h1{font-size:20px;font-weight:800;margin-bottom:4px}
  .sub{color:var(--mut);font-size:13px;margin-bottom:18px}
  .card{background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
    padding:18px;margin-bottom:16px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
  label{display:block;font-size:12px;font-weight:700;color:var(--mut);margin:0 0 5px}
  input[type=text]{width:100%;border:1px solid #d6dce6;border-radius:9px;padding:10px 12px;
    font-size:14px;font-family:inherit;outline:none;margin-bottom:12px}
  input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(58,85,114,.12)}
  .btn{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:11px 22px;
    font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn:hover{background:#2f465e}
  .verdict{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:11px;margin-bottom:6px}
  .verdict.ok{background:#eefaf1;border:1px solid #bfe6cb}
  .verdict.no{background:#fdf1ef;border:1px solid #eebfb8}
  .vico{font-size:26px;line-height:1}
  .vtxt b{font-size:15px;font-weight:800}
  .verdict.ok .vtxt b{color:#137a3a}.verdict.no .vtxt b{color:var(--red)}
  .vtxt div{font-size:12.5px;color:var(--mut)}
  table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
  th,td{border:1px solid #dbe0e8;padding:7px 10px;text-align:left}
  th{background:#f3f6fa;width:38%;font-weight:700;color:#37455e;white-space:nowrap}
  details{margin-top:10px}summary{cursor:pointer;font-size:12.5px;color:var(--navy);font-weight:700}
  pre{background:#0f172a;color:#d1e0f5;border-radius:9px;padding:12px;overflow:auto;font-size:11.5px;
    line-height:1.5;margin-top:8px;max-height:300px}
  .kv{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:7px;margin-top:8px}
  .kv div{background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px;padding:7px 9px}
  .kv b{display:block;font-size:10.5px;color:var(--mut)}.kv span{font-size:13.5px;font-weight:700;color:#2b3446}
  .note{font-size:12.5px;color:var(--mut);line-height:1.7;background:#f8fafc;border:1px dashed #ccd6e4;
    border-radius:9px;padding:11px 13px;margin-top:8px}
  .ex{display:flex;flex-wrap:wrap;gap:7px;margin:-4px 0 12px}
  .ex button{background:#eef2fa;border:1px solid #dbe3f0;color:var(--navy);border-radius:999px;
    padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
</style></head><body>
<div class="wrap">
  <h1>🔑 두 키 작동 점검</h1>
  <div class="sub">주소 하나로 juso 키와 건축물대장 키가 모두 살아있는지 확인합니다.</div>

  <form method="post" class="card">
    <label>juso 인증키 (confmKey) — 방금 발급받은 것</label>
    <input type="text" name="juso_key" value="<?=h($jusoIn)?>" placeholder="devU01TX0FVVEg...=">
    <label>건축물대장 인증키 (Decoding) — 비우면 코드에 넣어둔 키 사용</label>
    <input type="text" name="hub_key" value="<?=h($hubIn)?>" placeholder="Bgl2NmDm...==">
    <label>조회할 주소</label>
    <div class="ex">
      <button type="button" onclick="setA('경기도 성남시 분당구 불정로 6')">분당구 불정로 6</button>
      <button type="button" onclick="setA('서울특별시 종로구 세종대로 175')">세종대로 175</button>
      <button type="button" onclick="setA('서울특별시 중구 세종대로 110')">서울시청</button>
    </div>
    <input type="text" id="addr" name="addr" value="<?=h($addr)?>" placeholder="도로명주소 또는 지번주소">
    <button class="btn" type="submit">✅ 두 키 점검</button>
  </form>

<?php if($run): ?>
  <!-- 1단계 -->
  <div class="card">
    <div class="verdict <?=$r1['pass']?'ok':'no'?>">
      <span class="vico"><?=$r1['pass']?'✅':'❌'?></span>
      <div class="vtxt"><b><?=$r1['pass']?'juso 키 정상':'juso 키 확인 필요'?></b>
        <div><?=$r1['pass']?'주소 → 코드 변환 성공':h($r1['msg'])?></div></div>
    </div>
    <?php if($r1['pass']): $p=$r1['parsed']; ?>
      <div class="kv">
        <div><b>도로명주소</b><span><?=h($p['roadAddr'])?></span></div>
        <div><b>sigunguCd</b><span><?=h($p['sigunguCd'])?></span></div>
        <div><b>bjdongCd</b><span><?=h($p['bjdongCd'])?></span></div>
        <div><b>bun / ji</b><span><?=h($p['bun'])?>/<?=h($p['ji'])?></span></div>
      </div>
      <?php if($p['candCount']>1): ?><div class="note">후보 <?=$p['candCount']?>개 중 첫 번째 사용. 실제 도구에선 사용자가 고르게 할 예정.</div><?php endif; ?>
    <?php endif; ?>
    <?php if($r1['raw']): ?><details><summary>juso 원시 응답 (HTTP <?=h((string)$r1['raw']['code'])?>)</summary><pre><?=h(pretty($r1['raw']['body']))?></pre></details><?php endif; ?>
  </div>

  <!-- 2단계 -->
  <?php if($r2): ?>
  <div class="card">
    <div class="verdict <?=$r2['pass']?'ok':'no'?>">
      <span class="vico"><?=$r2['pass']?'✅':'❌'?></span>
      <div class="vtxt"><b><?=$r2['pass']?'건축물대장 키 정상':'건축물대장 확인 필요'?></b>
        <div><?=$r2['pass']?('건물 조회 성공 · '.h($r2['op'])):h($r2['msg'])?></div></div>
    </div>
    <?php if($r2['pass'] && $r2['item']): ?>
      <table>
        <?php foreach($LAB as $k=>$l): if(isset($r2['item'][$k])&&$r2['item'][$k]!==''): ?>
          <tr><th><?=h($l)?></th><td><?=h($r2['item'][$k])?></td></tr>
        <?php endif; endforeach; ?>
      </table>
    <?php endif; ?>
    <?php if($r2['raw']): ?><details><summary>HUB 원시 응답 (HTTP <?=h((string)$r2['raw']['code'])?>)</summary><pre><?=h(pretty($r2['raw']['body']))?></pre></details><?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- 종합 -->
  <div class="card">
    <?php $both=$r1['pass'] && $r2 && $r2['pass']; ?>
    <div class="verdict <?=$both?'ok':'no'?>">
      <span class="vico"><?=$both?'🎉':'⏳'?></span>
      <div class="vtxt">
        <b><?=$both?'두 키 모두 정상 — 연동 준비 완료':'아직 다 확인되지 않음'?></b>
        <div><?=$both?'이제 실제 도구에 자동완성 방식으로 붙이면 됩니다.':'위 실패 항목의 안내 메시지를 확인하세요.'?></div>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>
<script>
function setA(v){document.getElementById('addr').value=v;}
</script>
</body></html>
