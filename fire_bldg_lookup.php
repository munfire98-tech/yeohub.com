<?php
/* fire_bldg_lookup.php — 주소 자동완성 → 소방계획서 대상물 개요 자동 채움
 *
 * 완성된 흐름:
 *   [브라우저] 주소 타이핑 → juso 검색 → 후보 목록 표시 → 사용자가 클릭
 *        ↓ (선택한 주소의 코드가 확정됨)
 *   [서버]   건축HUB 총괄표제부 조회(없으면 표제부) → 대상물 개요 항목 채움
 *
 * 확정된 설정:
 *   - juso 검색 API : addrLinkApi.do
 *   - 건축물대장     : apis.data.go.kr/1613000/BldRgstHubService (http, _v2 없음)
 *   - 키는 Decoding 키 사용
 *
 * 이 파일은 단독 실행 가능. 나중에 소방계획서 도구에 통합할 땐
 *   [1] fillFromBuilding() 가 넘겨주는 JSON을 도구의 대상물 입력칸에 매핑하면 됨.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

/* ─── 키 설정 ─────────────────────────────────────────────
 * JUSO_KEY : business.juso.go.kr 검색 API confmKey (아직 코드에 안 박았으면 화면 입력칸 사용)
 * HUB_KEY  : 공공데이터포털 Decoding 인증키
 */
const JUSO_KEY = 'U01TX0FVVEgyMDI2MDgwNzE4MzU1MzExOTkzNjg=';   // juso 검색 API confmKey
const HUB_KEY  = 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==';

const JUSO_URL = 'https://business.juso.go.kr/addrlink/addrLinkApi.do';
const HUB_BASE = 'http://apis.data.go.kr/1613000/BldRgstHubService';   // ★ 확정된 정답 경로

/* ───────────── AJAX 라우팅 (브라우저 fetch 로 호출) ───────────── */
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function http_get(string $url): array {
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $b=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['body'=>(string)$b,'code'=>$c];
}
function jusoKey(): string { $k=trim($_POST['juso_key']??$_GET['juso_key']??''); return $k!==''?$k:JUSO_KEY; }
function hubKey(): string {
  $k=trim($_POST['hub_key']??$_GET['hub_key']??''); $k=$k!==''?$k:HUB_KEY;
  if(strpos($k,'%')!==false)$k=urldecode($k); return $k;
}

/* [action=search] 주소 검색 → 후보 목록 반환 */
if ($action==='search') {
  header('Content-Type: application/json; charset=utf-8');
  $kw=trim($_POST['keyword']??'');
  $key=jusoKey();
  if($key===''){ echo json_encode(['error'=>'juso 키가 설정되지 않았습니다.']); exit; }
  if($kw===''){ echo json_encode(['results'=>[]]); exit; }
  $url=JUSO_URL.'?'.http_build_query([
    'confmKey'=>$key,'currentPage'=>1,'countPerPage'=>10,'keyword'=>$kw,'resultType'=>'json']);
  $res=http_get($url); $j=json_decode($res['body'],true);
  $common=$j['results']['common']??[];
  if(($common['errorCode']??'')!=='0'){
    echo json_encode(['error'=>'검색 오류: '.($common['errorMessage']??'?')]); exit;
  }
  $out=[];
  foreach(($j['results']['juso']??[]) as $a){
    $adm=(string)($a['admCd']??'');
    $out[]=[
      'roadAddr'=>$a['roadAddr']??'', 'jibunAddr'=>$a['jibunAddr']??'',
      'zipNo'=>$a['zipNo']??'',
      'sigunguCd'=>substr($adm,0,5), 'bjdongCd'=>substr($adm,5,5),
      'platGbCd'=>(($a['mtYn']??'0')==='1')?'1':'0',
      'bun'=>str_pad((string)($a['lnbrMnnm']??'0'),4,'0',STR_PAD_LEFT),
      'ji'=>str_pad((string)($a['lnbrSlno']??'0'),4,'0',STR_PAD_LEFT),
    ];
  }
  echo json_encode(['results'=>$out], JSON_UNESCAPED_UNICODE); exit;
}

/* [action=building] 코드 → 건축물대장 조회 → 대상물 개요 반환 */
if ($action==='building') {
  header('Content-Type: application/json; charset=utf-8');
  $key=hubKey();
  $p=[
    'sigunguCd'=>trim($_POST['sigunguCd']??''), 'bjdongCd'=>trim($_POST['bjdongCd']??''),
    'platGbCd'=>trim($_POST['platGbCd']??'0'),
    'bun'=>str_pad(trim($_POST['bun']??'0'),4,'0',STR_PAD_LEFT),
    'ji'=>str_pad(trim($_POST['ji']??'0'),4,'0',STR_PAD_LEFT),
  ];
  // 총괄표제부 우선, 없으면 표제부
  $item=null; $usedOp='';
  foreach(['getBrRecapTitleInfo'=>'총괄표제부','getBrTitleInfo'=>'표제부'] as $op=>$nm){
    $url=HUB_BASE.'/'.$op.'?'.http_build_query(array_merge($p,[
      'serviceKey'=>$key,'numOfRows'=>20,'pageNo'=>1,'_type'=>'json']));
    $res=http_get($url); $j=json_decode($res['body'],true);
    if(stripos($res['body'],'SERVICE KEY IS NOT REGISTERED')!==false){
      echo json_encode(['error'=>'건축물대장 키 미등록/승인대기']); exit;
    }
    $items=$j['response']['body']['items']['item']??[];
    if(isset($items['bldNm'])||isset($items['platPlc'])) $items=[$items];
    if(is_array($items)&&$items){
      // 표제부는 여러 동이 나올 수 있음 → 주건축물/연면적 최대인 것 우선
      usort($items, fn($a,$b)=>((float)($b['totArea']??0))<=>((float)($a['totArea']??0)));
      $item=$items[0]; $usedOp=$nm; break;
    }
  }
  if(!$item){ echo json_encode(['error'=>'해당 지번의 건축물대장 데이터를 찾지 못했습니다.']); exit; }

  /* ── 소방계획서 대상물 개요 매핑 ───────────────────────────
   * 건축물대장 필드 → 소방계획서 항목
   */
  $g=(int)($item['grndFlrCnt']??0);   // 지상층
  $u=(int)($item['ugrndFlrCnt']??0);  // 지하층
  $flr = ($g||$u) ? ('지상 '.$g.'층 / 지하 '.$u.'층') : '';
  $useApr = $item['useAprDay']??'';
  if(preg_match('/^\d{8}$/',$useApr)) $useApr=substr($useApr,0,4).'.'.substr($useApr,4,2).'.'.substr($useApr,6,2);

  $overview=[
    '대상물명'   => $item['bldNm']       ?? '',
    '소재지'     => $item['platPlc']     ?? '',
    '도로명주소' => $item['newPlatPlc']  ?? '',
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
  echo json_encode(['op'=>$usedOp,'overview'=>$overview,'raw'=>$item], JSON_UNESCAPED_UNICODE); exit;
}

/* juso 키가 코드에 박혔는지(화면에서 입력칸 노출 여부 결정) */
$needJusoInput = (JUSO_KEY==='');
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>대상물 개요 자동 조회</title>
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
    border-radius:10px;box-shadow:0 8px 24px rgba(16,24,40,.12);z-index:20;max-height:320px;overflow:auto;display:none}
  .cand.show{display:block}
  .cand div{padding:10px 13px;cursor:pointer;border-bottom:1px solid #f0f2f6;font-size:13px}
  .cand div:last-child{border-bottom:none}
  .cand div:hover{background:#f3f6fb}
  .cand .road{font-weight:700;color:#2b3446}
  .cand .jibun{font-size:11.5px;color:var(--mut);margin-top:1px}
  .cand .empty{color:var(--mut);cursor:default}
  .hint{font-size:11.5px;color:var(--mut);margin-top:7px}
  .status{font-size:13px;font-weight:700;margin:6px 0 0}
  .status.load{color:var(--navy)}.status.err{color:var(--red)}
  .overview{display:none}
  .overview.show{display:block}
  .ov-head{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;margin-bottom:12px}
  .tag{font-size:11px;font-weight:800;background:#eefaf1;color:#137a3a;border:1px solid #bfe6cb;
    border-radius:999px;padding:3px 10px}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th,td{border:1px solid #dbe0e8;padding:9px 12px;text-align:left;vertical-align:top}
  th{background:#f3f6fa;width:34%;font-weight:700;color:#37455e;white-space:nowrap}
  td:empty::after{content:'—';color:#c2c8d2}
  .copy{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
  .copy button{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:9px 16px;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
  .copy button.ghost{background:#eef2fa;color:var(--navy);border:1px solid #dbe3f0}
  details{margin-top:12px}summary{cursor:pointer;font-size:12px;color:var(--navy);font-weight:700}
  pre{background:#0f172a;color:#d1e0f5;border-radius:9px;padding:12px;overflow:auto;font-size:11px;margin-top:8px;max-height:300px}
  .keyrow{margin-bottom:12px}
</style></head><body>
<div class="wrap">
  <h1>🏢 대상물 개요 자동 조회</h1>
  <div class="sub">주소를 입력하면 후보가 뜹니다. 클릭하면 건축물대장에서 소방계획서 <b>대상물 개요</b>를 자동으로 채웁니다.</div>

  <div class="card">
    <?php if($needJusoInput): ?>
    <div class="keyrow">
      <label>juso 인증키 (confmKey) — 코드에 아직 안 넣었으면 여기에</label>
      <input type="text" id="jusoKey" placeholder="devU01TX0FVVEg...=">
      <div class="hint">이 키는 이 페이지 안에서만 쓰입니다. 실제 배포 땐 코드 상단 JUSO_KEY 에 넣어두세요.</div>
    </div>
    <?php endif; ?>

    <label>주소 검색</label>
    <div class="searchbox">
      <input type="text" id="addr" autocomplete="off" placeholder="예: 분당구 불정로 6  /  종로구 세종대로 175  (2글자 이상 입력)">
      <div class="cand" id="cand"></div>
    </div>
    <div class="hint">도로명 일부만 입력해도 됩니다. 목록에서 정확한 건물을 클릭하세요.</div>
    <div class="status" id="status"></div>
  </div>

  <div class="card overview" id="ovcard">
    <div class="ov-head">📋 대상물 개요 <span class="tag" id="optag"></span></div>
    <table id="ovtable"></table>
    <div class="copy">
      <button onclick="copyTSV()">📋 표 복사 (엑셀/한글 붙여넣기용)</button>
      <button class="ghost" onclick="copyJSON()">JSON 복사</button>
    </div>
    <details><summary>건축물대장 원본 필드 보기</summary><pre id="rawbox"></pre></details>
    <div class="hint" style="margin-top:10px">※ 자동 조회값은 참고용입니다. 실제 소방계획서 작성 전 대장 원본과 대조해 확인하세요.</div>
  </div>
</div>

<script>
const $ = s => document.querySelector(s);
const addr = $('#addr'), cand = $('#cand'), statusEl = $('#status');
let timer=null, lastOverview=null;

function jusoKeyVal(){ const el=$('#jusoKey'); return el?el.value.trim():''; }

addr.addEventListener('input', ()=>{
  clearTimeout(timer);
  const kw = addr.value.trim();
  if(kw.length<2){ hideCand(); return; }
  timer = setTimeout(()=>doSearch(kw), 300);   // 디바운스
});

async function doSearch(kw){
  setStatus('검색 중…','load');
  try{
    const fd=new FormData();
    fd.append('keyword',kw);
    if(jusoKeyVal()) fd.append('juso_key',jusoKeyVal());
    const r=await fetch('?action=search',{method:'POST',body:fd});
    const j=await r.json();
    if(j.error){ setStatus(j.error,'err'); hideCand(); return; }
    renderCand(j.results||[]);
    setStatus('');
  }catch(e){ setStatus('검색 실패: '+e.message,'err'); }
}

function renderCand(list){
  cand.innerHTML='';
  if(!list.length){ cand.innerHTML='<div class="empty">검색 결과가 없습니다. 주소를 더 정확히 입력해 보세요.</div>'; cand.classList.add('show'); return; }
  list.forEach(a=>{
    const d=document.createElement('div');
    d.innerHTML=`<div class="road">${a.roadAddr}</div><div class="jibun">${a.jibunAddr} · ${a.zipNo}</div>`;
    d.onclick=()=>selectAddr(a);
    cand.appendChild(d);
  });
  cand.classList.add('show');
}
function hideCand(){ cand.classList.remove('show'); }
document.addEventListener('click',e=>{ if(!e.target.closest('.searchbox')) hideCand(); });

async function selectAddr(a){
  addr.value=a.roadAddr;
  hideCand();
  setStatus('건축물대장 조회 중…','load');
  $('#ovcard').classList.remove('show');
  try{
    const fd=new FormData();
    Object.entries(a).forEach(([k,v])=>fd.append(k,v));
    const r=await fetch('?action=building',{method:'POST',body:fd});
    const j=await r.json();
    if(j.error){ setStatus('❌ '+j.error,'err'); return; }
    setStatus('');
    showOverview(j);
  }catch(e){ setStatus('조회 실패: '+e.message,'err'); }
}

function showOverview(j){
  const ov=j.overview||{};
  lastOverview=ov;
  $('#optag').textContent=j.op||'';
  const t=$('#ovtable'); t.innerHTML='';
  for(const [k,v] of Object.entries(ov)){
    const tr=document.createElement('tr');
    tr.innerHTML=`<th>${k}</th><td>${v||''}</td>`;
    t.appendChild(tr);
  }
  $('#rawbox').textContent=JSON.stringify(j.raw,null,2);
  $('#ovcard').classList.add('show');
}

function setStatus(msg,cls){ statusEl.textContent=msg||''; statusEl.className='status'+(cls?(' '+cls):''); }

function copyTSV(){
  if(!lastOverview) return;
  const tsv=Object.entries(lastOverview).map(([k,v])=>k+'\t'+(v||'')).join('\n');
  navigator.clipboard.writeText(tsv).then(()=>toast('표를 복사했습니다'));
}
function copyJSON(){
  if(!lastOverview) return;
  navigator.clipboard.writeText(JSON.stringify(lastOverview,null,2)).then(()=>toast('JSON을 복사했습니다'));
}
function toast(m){ setStatus('✔ '+m,'load'); setTimeout(()=>setStatus(''),1500); }
</script>
</body></html>
