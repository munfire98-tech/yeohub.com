<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_draft_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
try{
 if(!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))throw new RuntimeException('매니저 본인 화면에서 확인해 주세요.');
 define('MD_ACTOR',mg_uid());define('MD_ID',(string)($_GET['id']??''));
 md_entry(MD_ACTOR,MD_ID);$d=md_load();
}catch(Throwable $e){http_response_code(403);exit('사전 등록 정보를 확인할 수 없습니다. 연결된 거래처는 담당 유저 화면에서 확인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$chat='/manager_draft.php?id='.rawurlencode(MD_ID).'&modal=1';
$groups=[
 '건물 기본정보'=>['name'=>'건물명','address'=>'주소','use'=>'용도','grade'=>'관리 등급','rep'=>'대표자','tel'=>'건물 연락처'],
 '건물 규모'=>['floor_b'=>'지하층','floor_a'=>'지상층','area_t'=>'연면적 (㎡)','area_f'=>'바닥면적 (㎡)','bd_area_arch'=>'건축면적 (㎡)','dongsu'=>'동수'],
 '건축물대장'=>['bd_struct'=>'구조','bd_struct_etc'=>'기타 구조','bd_height'=>'높이 (m)','bd_area_plat'=>'대지면적 (㎡)','bd_bcrat'=>'건폐율 (%)','bd_vlrat'=>'용적률 (%)','bd_area_vl'=>'용적률 산정 연면적 (㎡)','bd_main_bld'=>'주건축물 수','bd_atch_bld'=>'부속건축물 수','bd_hhld'=>'세대수','bd_family'=>'가구수','bd_ho'=>'호수','bd_park'=>'주차대수','bd_elev'=>'승용승강기 수','bd_use_main'=>'주용도','bd_use_etc'=>'기타 용도','bd_pms_day'=>'허가일','bd_stcns_day'=>'착공일','bd_use_apr'=>'사용승인일','bd_seismic'=>'내진설계 적용','bd_seismic_ablty'=>'내진능력','bd_energy'=>'에너지효율등급','bd_road_addr'=>'도로명 대지 위치','bd_dongs'=>'동별 현황','bd_dong_pick'=>'대표 기준동','bd_looked'=>'대장 조회일'],
 '근무인원'=>['wd_day'=>'평일 주간','wd_night'=>'평일 야간','hd_day'=>'휴일 주간','hd_night'=>'휴일 야간'],
 '기록 참고사항'=>['note_sobang'=>'소방시설','note_pinan'=>'피난·방화시설','note_hwagi'=>'화기 취급','note_etc'=>'기타 사항']
];
function dv_value($v):string {return is_scalar($v)?trim((string)$v):'';}

function dv_editor(string $key,string $label,$value):string {
 $value=dv_value($value);$attrs=' data-dv-key="'.mg_e($key).'" aria-label="'.mg_e($label).'"';
 if(in_array($key,['bd_dongs','bd_dong_pick','bd_looked'],true))return '<span>'.mg_e($value?:'—').'</span>';
 if($key==='grade'){$html='<select'.$attrs.'><option value="">선택</option>';foreach(['특급','1급','2급','3급'] as $v)$html.='<option'.($v===$value?' selected':'').'>'.mg_e($v).'</option>';return $html.'</select>';}
 if(strpos($key,'note_')===0)return '<textarea'.$attrs.' rows="3" maxlength="5000">'.mg_e($value).'</textarea>';
 return '<input type="text"'.$attrs.' maxlength="1000" value="'.mg_e($value).'">';
}
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>사전 등록 기본정보</title><link rel="stylesheet" href="/manager_addresses.css?v=7"><style>
.dv-section input,.dv-section select,.dv-section textarea{box-sizing:border-box;width:100%;border:1px solid #d7e1ec;border-radius:8px;padding:10px 11px;background:#fff;color:#29435c;font:14px/1.5 system-ui}.dv-section input:focus,.dv-section select:focus,.dv-section textarea:focus{outline:2px solid #aac9ef;border-color:#658ec4}.dv-section dd{min-width:0}.dv-section dt{padding-top:10px}.dv-section details>summary{cursor:pointer;font-weight:700;color:#354f69;padding:8px 0}.dv-savebar{position:sticky;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;background:#fffffff2;border:1px solid #dce5ef;border-radius:12px;box-shadow:0 -5px 20px #243c5510;backdrop-filter:blur(6px)}.dv-savebar button{background:#285ed0;color:white;border:0;border-radius:9px;padding:11px 20px;font-weight:700;cursor:pointer}.dv-savebar button:disabled{opacity:.5}.dv-savebar [role=status]{font-size:12px;color:#5d7289}.dv-manager{margin-top:16px;border-top:1px solid #edf1f6;padding-top:14px}.dv-page{padding-bottom:22px}

.dv-heading{display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,300px);align-items:start;gap:24px}.dv-heading-copy{min-width:0}.dv-heading-copy h1{overflow-wrap:anywhere}.dv-contact-card{padding:17px;background:#fff;border:1px solid #dce7e4;border-radius:13px}.dv-contact-title{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:13px}.dv-contact-title strong{font-size:14px;color:#254d43}.dv-contact-title>span{font-size:10px;color:#84958f}.dv-contact-row{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);gap:9px;margin-top:10px}.dv-contact-row[hidden]{display:none}.dv-contact-row label{display:block;min-width:0}.dv-contact-row label>span:first-child{display:block;font-size:10px;color:#70867d;margin-bottom:5px}.dv-contact-card input{box-sizing:border-box;width:100%;min-width:0;padding:9px 10px;background:#f8fbfa;border:1px solid #dbe5e1;border-radius:7px;color:#29453b;font:13px/1.5 system-ui}.dv-contact-card input:focus{outline:2px solid #a6cfc2;border-color:#568d7b}.dv-contact-add{margin-top:12px;border:0;background:none;padding:3px 0;color:#497666;font:12px system-ui;cursor:pointer}.dv-contact-add[hidden]{display:none}@media(max-width:620px){.dv-heading{grid-template-columns:1fr;gap:18px}.dv-contact-card{width:100%;box-sizing:border-box}}

/* Use readable, stable input sizing on phones and touch devices; preserve pinch zoom. */
@media (max-width:768px), (hover:none) and (pointer:coarse){
 input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),textarea,select{font-size:16px!important;box-sizing:border-box;min-width:0;max-width:100%}
 .inrow input{min-width:0;flex:1 1 160px}
 .inrow,.ma-searchline,.dv-contact-row,.dv-section dd{min-width:0}
}
</style><body><main class="ma-page dv-page">
<header class="dv-heading"><div class="dv-heading-copy"><span class="ma-kicker">사전등록 · 저장된 기본정보</span><h1><?=mg_e($d['name']?:'등록된 건물 정보')?></h1><p><?=mg_e($d['address']?:'주소를 아직 입력하지 않았습니다.')?></p><?php if($d['updated']): ?><small class="ma-note">최근 저장 <?=mg_e($d['updated'])?></small><?php endif; ?></div><aside class="dv-contact-card" aria-label="소방안전관리자 연락처">
<div class="dv-contact-title"><strong>소방안전관리자</strong><span>이름 · 전화번호</span></div>
<?php for($i=0;$i<4;$i++):$m=$d['mgrs'][$i]??[]; ?><div class="dv-contact-row" data-contact-row="<?=$i?>" <?=$i>0&&empty($m['name'])&&empty($m['tel'])?'hidden':''?>><label><span>관리자 <?=$i+1?></span><span data-contact-slot="<?=$i?>-name"></span></label><label><span>전화번호</span><span data-contact-slot="<?=$i?>-tel"></span></label></div><?php endfor; ?>
<button type="button" class="dv-contact-add">＋ 관리자 추가</button>
</aside></header>
<div class="dv-toolbar"><a class="ma-link" href="<?=mg_e($chat)?>">검색·문답으로 보완</a><form method="post" action="<?=mg_e($chat)?>" onsubmit="return confirm('저장된 기본정보를 초기화하고 첫 질문부터 다시 작성합니다. 계속할까요?')"><input type="hidden" name="act" value="reset"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><button type="submit" class="ma-secondary">처음부터 다시</button></form><a href="/manager_addresses.php" class="dv-back">목록</a></div>
<p class="ma-note">아래 항목을 바로 수정한 뒤 저장하세요. 동별 현황·기준동 변경과 지도 설정은 검색·문답으로 보완할 수 있습니다. 처음부터 다시는 저장된 내용을 초기화합니다.</p>
<?php foreach($groups as $title=>$fields): ?>
<section class="dv-section"><details <?=in_array($title,['건물 기본정보','건물 규모'],true)?'open':''?>><summary><?=mg_e($title)?></summary><dl><?php foreach($fields as $key=>$label):$value=dv_value($d[$key]??''); ?><div><dt><?=mg_e($label)?></dt><dd class="<?=$value===''?'dv-missing':''?>"><?=dv_editor($key,$label,$value)?></dd></div><?php endforeach; ?></dl><?php if(!array_filter(array_intersect_key($d,$fields),fn($v)=>dv_value($v)!=='')): ?><p class="ma-note">아직 저장된 정보가 없습니다.</p><?php endif; ?></details></section>
<?php if($title==='건물 기본정보'): ?>
<section class="dv-section"><details><summary>소방안전관리자 상세 · 구분·선임일·자격</summary><?php for($i=0;$i<4;$i++):$m=$d['mgrs'][$i]??[]; ?><div class="dv-manager"><strong>관리자 <?=$i+1?></strong><dl><?php foreach(['name'=>'이름','type'=>'구분','tel'=>'연락처','appt'=>'선임일','qual'=>'자격'] as $k=>$label): ?><div><dt><?=mg_e($label)?></dt><dd><?php if($k==='type'): ?><select data-dv-manager="<?=$i?>" data-field="type" aria-label="관리자 <?=$i+1?> 구분"><?php foreach([''=>'선택','주'=>'주','보조'=>'보조'] as $v=>$caption): ?><option value="<?=mg_e($v)?>" <?=($m[$k]??'')===$v?'selected':''?>><?=mg_e($caption)?></option><?php endforeach; ?></select><?php else: ?><input type="text" maxlength="200" data-dv-manager="<?=$i?>" data-field="<?=mg_e($k)?>" aria-label="관리자 <?=$i+1?> <?=mg_e($label)?>" value="<?=mg_e($m[$k]??'')?>"><?php endif; ?></dd></div><?php endforeach; ?></dl></div><?php endfor; ?></details></section>
<?php endif; ?>
<?php endforeach; ?>

<section class="dv-section"><h2>집결지 · 소방차 진입로</h2><dl><?php foreach(['assembly_kind'=>'집결지 이름','assembly_lat'=>'집결지 위도','assembly_lng'=>'집결지 경도','fire_engine_route_note'=>'진입로 참고사항','bd_lat'=>'건물 위도','bd_lng'=>'건물 경도'] as $k=>$label): ?><div><dt><?=mg_e($label)?></dt><dd><?=mg_e(dv_value($d[$k]??'')?:'—')?></dd></div><?php endforeach; ?><div><dt>소방차 진입로</dt><dd><?=count((array)json_decode((string)($d['fire_engine_route']??''),true))>=2?'경로 저장됨':'—'?></dd></div></dl></section>
<div class="dv-savebar"><span id="dv-status" role="status">항목을 수정한 뒤 저장해 주세요.</span><button type="button" id="dv-save">변경사항 저장</button></div>
<script>
(()=>{
 // Move the existing inputs, so header edits and saved manager data have one source.
 document.querySelectorAll('[data-contact-slot]').forEach(slot=>{const [index,key]=slot.dataset.contactSlot.split('-');const el=document.querySelector('[data-dv-manager="'+index+'"][data-field="'+key+'"]');if(!el)return;const oldRow=el.closest('dd')?.parentElement;if(key==='tel'){el.type='tel';el.autocomplete='off';}slot.append(el);oldRow?.remove();});
 const addContact=document.querySelector('.dv-contact-add');
 function updateContactAdd(){addContact.hidden=!document.querySelector('.dv-contact-row[hidden]');}
 addContact.onclick=()=>{const row=document.querySelector('.dv-contact-row[hidden]');if(row){row.hidden=false;row.querySelector('input')?.focus();}updateContactAdd();};updateContactAdd();
 const inputs=[...document.querySelectorAll('[data-dv-key],[data-dv-manager]')],button=document.querySelector('#dv-save'),status=document.querySelector('#dv-status');
 let busy=false,baseline=new Map(inputs.map(el=>[el,el.value]));
 const dirty=()=>inputs.some(el=>el.value!==baseline.get(el));
 window.managerDraftCanLeave=()=>busy?(alert('저장이 끝난 뒤 닫아 주세요.'),false):!dirty()||confirm('저장하지 않은 변경사항을 버리고 닫을까요?');
 inputs.forEach(el=>el.addEventListener('input',()=>{status.textContent=dirty()?'저장하지 않은 변경사항이 있습니다.':'변경사항이 없습니다.';}));
 document.querySelectorAll('.dv-toolbar a,.dv-toolbar form').forEach(el=>el.addEventListener(el.tagName==='FORM'?'submit':'click',e=>{if(!window.managerDraftCanLeave())e.preventDefault();}));
 window.addEventListener('beforeunload',e=>{if(dirty()||busy){e.preventDefault();e.returnValue='';}});
 button.onclick=async()=>{
  if(busy)return;if(!dirty()){status.textContent='변경사항이 없습니다.';return;}
  const patch={},snapshot=new Map(inputs.map(el=>[el,el.value]));
  for(const el of inputs){if(!el.reportValidity())return;if(el.dataset.dvKey&&el.value!==baseline.get(el))patch[el.dataset.dvKey]=el.value;}
  const mgrInputs=inputs.filter(el=>el.hasAttribute('data-dv-manager'));
  if(mgrInputs.some(el=>el.value!==baseline.get(el))){patch.mgrs=Array.from({length:4},()=>({}));mgrInputs.forEach(el=>patch.mgrs[+el.dataset.dvManager][el.dataset.field]=el.value);}
  if(Object.hasOwn(patch,'address')){patch.bd_lat='';patch.bd_lng='';}
  busy=true;button.disabled=true;status.textContent='저장 중…';
  try{const r=await fetch(<?=json_encode($chat)?>,{method:'POST',credentials:'same-origin',body:new URLSearchParams({act:'save_step',csrf:<?=json_encode($_SESSION['csrf'])?>,patch:JSON.stringify(patch)})});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'저장하지 못했습니다.');baseline=snapshot;status.textContent=dirty()?'저장됐습니다. 추가 변경사항도 저장해 주세요.':'저장했습니다.';if(parent!==window)parent.postMessage({type:'manager-addresses-changed'},location.origin);if(Object.hasOwn(patch,'name'))document.querySelector('header h1').textContent=patch.name||'등록된 건물 정보';if(Object.hasOwn(patch,'address'))document.querySelector('header p').textContent=patch.address||'주소 미입력';}
  catch(e){status.textContent=e instanceof SyntaxError?'응답을 확인하지 못했습니다. 입력 내용은 유지됩니다.':e.message;}
  finally{busy=false;button.disabled=false;}
 };
})();
</script></main></body></html>
