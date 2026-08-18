<?php
/* fire_bldg_lookup2.php — 이름·주소 통합검색 → 소방계획서 대상물 개요 자동 채움
 *
 * 흐름:
 *   [브라우저] "○○빌딩" 또는 "분당구 불정로 6" 입력
 *        ↓ 카카오 키워드 장소검색 → 후보(장소명+주소+좌표) 목록
 *   [사용자]  후보 클릭
 *        ↓ 그 도로명주소를 juso로 넘겨 코드 변환 (sigunguCd·bjdongCd·bun·ji)
 *   [서버]   건축HUB 총괄표제부(없으면 표제부) 조회 → 대상물 개요 채움
 *
 * 확정 설정:
 *   - 카카오 장소검색 : https://dapi.kakao.com/v2/local/search/keyword.json (헤더 KakaoAK)
 *   - juso 주소→코드  : business.juso.go.kr/addrlink/addrLinkApi.do
 *   - 건축물대장       : http://apis.data.go.kr/1613000/BldRgstHubService (_v2 없음)
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

/* ─── 키 설정 ───────────────────────────────────────── */
const KAKAO_KEY = 'aea180f8a9ccf7395bccfb6dfbede9c6';                       // 카카오 REST API 키
const JUSO_KEY  = 'U01TX0FVVEgyMDI2MDgwNzE4MzU1MzExOTkzNjg=';               // juso confmKey
const HUB_KEY   = 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==';

const KAKAO_URL = 'https://dapi.kakao.com/v2/local/search/keyword.json';
const JUSO_URL  = 'https://business.juso.go.kr/addrlink/addrLinkApi.do';
const HUB_BASE  = 'http://apis.data.go.kr/1613000/BldRgstHubService';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* 공통 GET (헤더 지정 가능) */
function http_get(string $url, array $headers=[]): array {
  $ch=curl_init($url);
  $h=array_merge(['Accept: application/json'],$headers);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_HTTPHEADER=>$h]);
  $b=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['body'=>(string)$b,'code'=>$c];
}
function hubKey(): string { $k=HUB_KEY; if(strpos($k,'%')!==false)$k=urldecode($k); return $k; }

/* juso 로 도로명주소 → 코드 변환 (내부 헬퍼) */
function juso_to_code(string $roadAddr): ?array {
  if($roadAddr==='') return null;
  $url=JUSO_URL.'?'.http_build_query([
    'confmKey'=>JUSO_KEY,'currentPage'=>1,'countPerPage'=>1,'keyword'=>$roadAddr,'resultType'=>'json']);
  $res=http_get($url); $j=json_decode($res['body'],true);
  if(($j['results']['common']['errorCode']??'')!=='0') return null;
  $a=$j['results']['juso'][0]??null; if(!$a) return null;
  $adm=(string)($a['admCd']??'');
  return [
    'roadAddr'=>$a['roadAddr']??$roadAddr, 'jibunAddr'=>$a['jibunAddr']??'',
    'sigunguCd'=>substr($adm,0,5),'bjdongCd'=>substr($adm,5,5),
    'platGbCd'=>(($a['mtYn']??'0')==='1')?'1':'0',
    'bun'=>str_pad((string)($a['lnbrMnnm']??'0'),4,'0',STR_PAD_LEFT),
    'ji'=>str_pad((string)($a['lnbrSlno']??'0'),4,'0',STR_PAD_LEFT),
  ];
}

/* [action=search] 카카오 키워드 장소검색 → 후보 목록 */
if ($action==='search') {
  header('Content-Type: application/json; charset=utf-8');
  $kw=trim($_POST['keyword']??'');
  if($kw===''){ echo json_encode(['results'=>[]]); exit; }
  $url=KAKAO_URL.'?'.http_build_query(['query'=>$kw,'size'=>10]);
  $res=http_get($url,['Authorization: KakaoAK '.KAKAO_KEY]);
  $j=json_decode($res['body'],true);
  if($res['code']===401||$res['code']===403){
    echo json_encode(['error'=>'카카오 인증 실패 — 앱의 카카오맵 사용설정 ON, REST 키 확인']); exit;
  }
  if(!isset($j['documents'])){
    echo json_encode(['error'=>'카카오 응답 오류('.$res['code'].')']); exit;
  }
  $out=[];
  foreach($j['documents'] as $d){
    $out[]=[
      'place'=>$d['place_name']??'',
      'road'=>$d['road_address_name']??'',
      'jibun'=>$d['address_name']??'',
      'category'=>$d['category_group_name']??'',
    ];
  }
  echo json_encode(['results'=>$out], JSON_UNESCAPED_UNICODE); exit;
}

/* [action=building] 도로명주소 → juso 코드변환 → 건축HUB 조회 → 대상물 개요 */
if ($action==='building') {
  header('Content-Type: application/json; charset=utf-8');
  $road = trim($_POST['road']??'');
  $jibun= trim($_POST['jibun']??'');
  // 도로명주소 우선, 없으면 지번주소로 juso 조회
  $code = juso_to_code($road!=='' ? $road : $jibun);
  if(!$code){ echo json_encode(['error'=>'이 장소의 주소를 코드로 변환하지 못했습니다(juso 미검색).']); exit; }

  $p=['sigunguCd'=>$code['sigunguCd'],'bjdongCd'=>$code['bjdongCd'],
      'platGbCd'=>$code['platGbCd'],'bun'=>$code['bun'],'ji'=>$code['ji']];
  $key=hubKey(); $item=null; $usedOp='';
  foreach(['getBrRecapTitleInfo'=>'총괄표제부','getBrTitleInfo'=>'표제부'] as $op=>$nm){
    $url=HUB_BASE.'/'.$op.'?'.http_build_query(array_merge($p,[
      'serviceKey'=>$key,'numOfRows'=>30,'pageNo'=>1,'_type'=>'json']));
    $res=http_get($url); $j=json_decode($res['body'],true);
    if(stripos($res['body'],'SERVICE KEY IS NOT REGISTERED')!==false){
      echo json_encode(['error'=>'건축물대장 키 미등록/승인대기']); exit;
    }
    $items=$j['response']['body']['items']['item']??[];
    if(isset($items['bldNm'])||isset($items['platPlc'])) $items=[$items];
    if(is_array($items)&&$items){
      usort($items, fn($a,$b)=>((float)($b['totArea']??0))<=>((float)($a['totArea']??0)));
      $item=$items[0]; $usedOp=$nm; break;
    }
  }
  if(!$item){ echo json_encode(['error'=>'해당 지번의 건축물대장 데이터를 찾지 못했습니다.','code'=>$code]); exit; }

  $g=(int)($item['grndFlrCnt']??0); $u=(int)($item['ugrndFlrCnt']??0);
  $flr = ($g||$u) ? ('지상 '.$g.'층 / 지하 '.$u.'층') : '';
  $useApr=$item['useAprDay']??'';
  if(preg_match('/^\d{8}$/',$useApr)) $useApr=substr($useApr,0,4).'.'.substr($useApr,4,2).'.'.substr($useApr,6,2);

  $overview=[
    '대상물명'   => $item['bldNm']       ?? '',
    '소재지'     => $item['platPlc']     ?? '',
    '도로명주소' => $item['newPlatPlc']  ?? $code['roadAddr'],
    '주용도'     => trim(($item['mainPurpsCdNm']??'').' '.($item['etcPurps']??'')),
    '구조'       => $item['strctCdNm']   ?? '',
    '대지면적'   => ($item['platArea']??'')!=='' ? ($item['platArea'].' ㎡') : '',
    '건축면적'   => ($item['archArea']??'')!=='' ? ($item['archArea'].' ㎡') : '',
    '연면적'     => ($item['totArea']??'') !=='' ? ($item['totArea'].' ㎡')  : '',
    '층수'       => $flr,
    '높이'       => ($item['heit']??'')!=='' && ($item['heit']??'0')!=='0' ? ($item['heit'].' m') : '',
    '세대/호수'  => trim(((($item['hhldCnt']??'0')!=='0')?($item['hhldCnt'].'세대 '):'').((($item['hoCnt']??'0')!=='0')?($item['hoCnt'].'호'):'')),
    '총주차대수' => (($item['totPkngCnt']??'0')!=='0') ? ($item['totPkngCnt'].' 대') : '',
    '사용승인일' => $useApr,
  ];
  echo json_encode(['op'=>$usedOp,'overview'=>$overview,'addr'=>$code,'raw'=>$item], JSON_UNESCAPED_UNICODE); exit;
}

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>대상물 개요 자동 조회 (이름·주소 검색)</title>
<style>
  :root{--navy:#3a5572;--red:#c0392b;--ink:#1f2430;--mut:#6b7280;--bg:#eef1f6;
    --card:#fff;--brd:#e3e7ee;--ok:#16a34a;--radius:12px}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Pretendard','Malgun Gothic',sans-serif;background:var(--bg);color:var(--ink);
    font-size:14px;line-height:1.6;padding:22px 16px 90px}
  .wrap{max-width:760px;margin:0 auto}
  h1{font-size:20px;font-weight:800;margin-bottom:4px}
  .sub{color:var(--mut);font-size:13px;margin-bottom:18px}
  .card{background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
    padding:18px;margin-bottom:16px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
  label{display:block;font-size:12px;font-weight:700;color:var(--mut);margin:0 0 5px}
  input[type=text]{width:100%;border:1px solid #d6dce6;border-radius:9px;padding:11px 13px;
    font-size:14px;font-family:inherit;outline:none}
  input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(58,85,114,.12)}
  .searchbox{position:relative}
  .cand{position:absolute;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid var(--brd);
    border-radius:10px;box-shadow:0 8px 24px rgba(16,24,40,.12);z-index:20;max-height:340px;overflow:auto;display:none}
  .cand.show{display:block}
  .cand div{padding:10px 13px;cursor:pointer;border-bottom:1px solid #f0f2f6}
  .cand div:last-child{border-bottom:none}
  .cand div:hover{background:#f3f6fb}
  .cand .place{font-weight:700;color:#2b3446;font-size:13.5px}
  .cand .place .cat{font-size:11px;color:var(--navy);background:#eef2fa;border-radius:5px;padding:1px 6px;margin-left:6px;font-weight:600}
  .cand .addr{font-size:11.5px;color:var(--mut);margin-top:2px}
  .cand .empty{color:var(--mut);cursor:default}
  .hint{font-size:11.5px;color:var(--mut);margin-top:7px}
  .status{font-size:13px;font-weight:700;margin:8px 0 0}
  .status.load{color:var(--navy)}.status.err{color:var(--red)}
  .overview{display:none}.overview.show{display:block}
  .ov-head{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;margin-bottom:4px}
  .tag{font-size:11px;font-weight:800;background:#eefaf1;color:#137a3a;border:1px solid #bfe6cb;border-radius:999px;padding:3px 10px}
  .selname{font-size:12.5px;color:var(--mut);margin-bottom:12px}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th,td{border:1px solid #dbe0e8;padding:9px 12px;text-align:left;vertical-align:top}
  th{background:#f3f6fa;width:34%;font-weight:700;color:#37455e;white-space:nowrap}
  td:empty::after{content:'—';color:#c2c8d2}
  .copy{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
  .copy button{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
  .copy button.ghost{background:#eef2fa;color:var(--navy);border:1px solid #dbe3f0}
  details{margin-top:12px}summary{cursor:pointer;font-size:12px;color:var(--navy);font-weight:700}
  pre{background:#0f172a;color:#d1e0f5;border-radius:9px;padding:12px;overflow:auto;font-size:11px;margin-top:8px;max-height:300px}
</style></head><body>
<div class="wrap">
  <h1>🏢 대상물 개요 자동 조회</h1>
  <div class="sub"><b>건물 이름</b>이든 <b>주소</b>든 아무거나 입력하세요. 후보를 클릭하면 소방계획서 <b>대상물 개요</b>가 자동으로 채워집니다.</div>

  <div class="card">
    <label>건물명 또는 주소 검색</label>
    <div class="searchbox">
      <input type="text" id="q" autocomplete="off" placeholder="예: 롯데월드타워  /  성남시청  /  분당구 불정로 6  (2글자 이상)">
      <div class="cand" id="cand"></div>
    </div>
    <div class="hint">카카오 장소검색으로 이름·주소를 함께 찾습니다. 목록에서 정확한 건물을 클릭하세요.</div>
    <div class="status" id="status"></div>
  </div>

  <div class="card overview" id="ovcard">
    <div class="ov-head">📋 대상물 개요 <span class="tag" id="optag"></span></div>
    <div class="selname" id="selname"></div>
    <table id="ovtable"></table>
    <div class="copy">
      <button onclick="copyTSV()">📋 표 복사 (엑셀/한글용)</button>
      <button class="ghost" onclick="copyJSON()">JSON 복사</button>
    </div>
    <details><summary>건축물대장 원본 필드 / 변환된 주소코드 보기</summary><pre id="rawbox"></pre></details>
    <div class="hint" style="margin-top:10px">※ 자동 조회값은 참고용입니다. 소방계획서 작성 전 대장 원본과 대조해 확인하세요.</div>
  </div>
</div>

<script>
const $=s=>document.querySelector(s);
const q=$('#q'), cand=$('#cand'), statusEl=$('#status');
let timer=null, lastOverview=null, lastPlace='';

q.addEventListener('input',()=>{
  clearTimeout(timer);
  const kw=q.value.trim();
  if(kw.length<2){ hideCand(); return; }
  timer=setTimeout(()=>doSearch(kw),300);
});

async function doSearch(kw){
  setStatus('검색 중…','load');
  try{
    const fd=new FormData(); fd.append('keyword',kw);
    const r=await fetch('?action=search',{method:'POST',body:fd});
    const j=await r.json();
    if(j.error){ setStatus(j.error,'err'); hideCand(); return; }
    renderCand(j.results||[]); setStatus('');
  }catch(e){ setStatus('검색 실패: '+e.message,'err'); }
}

function renderCand(list){
  cand.innerHTML='';
  if(!list.length){ cand.innerHTML='<div class="empty">검색 결과가 없습니다. 다르게 입력해 보세요.</div>'; cand.classList.add('show'); return; }
  list.forEach(a=>{
    const d=document.createElement('div');
    const addr=a.road||a.jibun||'';
    const cat=a.category?`<span class="cat">${a.category}</span>`:'';
    d.innerHTML=`<div class="place">${a.place}${cat}</div><div class="addr">${addr}</div>`;
    d.onclick=()=>selectPlace(a);
    cand.appendChild(d);
  });
  cand.classList.add('show');
}
function hideCand(){ cand.classList.remove('show'); }
document.addEventListener('click',e=>{ if(!e.target.closest('.searchbox')) hideCand(); });

async function selectPlace(a){
  q.value=a.place;
  lastPlace=a.place+(a.road?(' · '+a.road):'');
  hideCand();
  setStatus('건축물대장 조회 중…','load');
  $('#ovcard').classList.remove('show');
  try{
    const fd=new FormData();
    fd.append('road',a.road||''); fd.append('jibun',a.jibun||'');
    const r=await fetch('?action=building',{method:'POST',body:fd});
    const j=await r.json();
    if(j.error){ setStatus('❌ '+j.error,'err'); return; }
    setStatus(''); showOverview(j);
  }catch(e){ setStatus('조회 실패: '+e.message,'err'); }
}

function showOverview(j){
  const ov=j.overview||{}; lastOverview=ov;
  $('#optag').textContent=j.op||'';
  $('#selname').textContent='검색: '+lastPlace;
  const t=$('#ovtable'); t.innerHTML='';
  for(const [k,v] of Object.entries(ov)){
    const tr=document.createElement('tr');
    tr.innerHTML=`<th>${k}</th><td>${v||''}</td>`;
    t.appendChild(tr);
  }
  $('#rawbox').textContent=JSON.stringify({주소코드:j.addr, 대장원본:j.raw},null,2);
  $('#ovcard').classList.add('show');
}
function setStatus(m,c){ statusEl.textContent=m||''; statusEl.className='status'+(c?(' '+c):''); }
function copyTSV(){ if(!lastOverview)return;
  const tsv=Object.entries(lastOverview).map(([k,v])=>k+'\t'+(v||'')).join('\n');
  navigator.clipboard.writeText(tsv).then(()=>toast('표 복사됨')); }
function copyJSON(){ if(!lastOverview)return;
  navigator.clipboard.writeText(JSON.stringify(lastOverview,null,2)).then(()=>toast('JSON 복사됨')); }
function toast(m){ setStatus('✔ '+m,'load'); setTimeout(()=>setStatus(''),1500); }
</script>
</body></html>
