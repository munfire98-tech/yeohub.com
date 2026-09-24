<?php
declare(strict_types=1);
/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
header('Cache-Control: no-store');
require_once __DIR__.'/building_info.php';require_once __DIR__.'/building_facilities_common.php';
$admin=!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);
if(!$admin&&(empty($_SESSION['is_user'])||($_SESSION['role']??'')!=='building')){http_response_code(403);exit('건물관리자 계정으로 열어 주세요.');}
if(bi_user_key()===''){http_response_code(403);exit('건물 계정을 선택해 주세요.');}
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$d=bf_load();$error='';$saved=false;$resetDone=false;$bi=bi_load();$options=bf_dong_options($bi);$scopes=bf_scopes($d,$options);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('새로고침 후 다시 시도해 주세요.');}
 try{
  if(($_POST['multi_scope_form']??'')!=='1'||($_POST['form_end']??'')!=='1')throw new RuntimeException('화면을 새로고침한 후 다시 저장해 주세요.');
  require_once __DIR__.'/manager_facility_help.php';
  if(($_POST['action']??'save')==='reset'){
   $d=mfh_save(bi_user_key(),false,static function()use($options):array{return bf_reset((string)($_POST['revision']??''),$options);},true);
   $scopes=bf_scopes($d,$options);$saved=true;$resetDone=true;
  }else{
  $included=array_merge(['base'],is_array($_POST['included']??null)?$_POST['included']:[]);$inputs=[];
  foreach($included as $sid){if(!is_string($sid)||!isset($scopes[$sid]))throw new RuntimeException('동 목록을 다시 확인해 주세요.');
   $posted=is_array($_POST['installed'][$sid]??null)?$_POST['installed'][$sid]:[];
   foreach(bf_catalog() as $g)foreach($g[1] as $name){$id=bf_id($name);$inputs[$sid][$id]=['status'=>isset($posted[$id])?'yes':'no'];}
  }
  foreach($scopes as $sid=>&$scope){$scope['included']=in_array($sid,$included,true);if(isset($inputs[$sid]))$scope['items']=$inputs[$sid];}unset($scope);
  require_once __DIR__.'/manager_facility_help.php';
  $d=mfh_save(bi_user_key(),true,static function()use($inputs,$included,$options):array{return bf_save_multi($inputs,$included,(string)($_POST['revision']??''),$options);});
  $scopes=bf_scopes($d,$options);$saved=true;
  }
 }catch(RuntimeException $e){$error=$e->getMessage();}
}
$c=bf_counts(['scopes'=>$scopes]);
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>소방시설 현황</title><style>
*{box-sizing:border-box}body{margin:0;background:#f7f8fb;color:#202d42;font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:1040px;margin:auto;padding:32px 28px 130px}.eyebrow{font-size:11px;letter-spacing:.14em;font-weight:750;color:#667a9a;margin:0 0 8px}h1{font-size:28px;letter-spacing:-1px;margin:0 0 8px}.intro{margin:0;color:#718096}.heading{display:flex;justify-content:space-between;align-items:center;gap:20px}.total{white-space:nowrap;color:#4663b4;background:#eef2fc;border-radius:100px;padding:10px 16px;font-size:13px}.total b{font-size:20px;margin-right:3px}.toolbar{margin:24px 0 26px;display:flex;gap:12px;align-items:center;justify-content:space-between}.search{width:320px;max-width:100%;background:white;border:1px solid #e0e5ed;border-radius:12px;padding:12px 14px;font:inherit;color:inherit}.toggle{display:flex;align-items:center;gap:7px;color:#62728a;cursor:pointer;white-space:nowrap;font-size:13px}input[type=checkbox]{accent-color:#496ddd}section{margin:0 0 26px}.group-title{display:flex;align-items:center;gap:10px;margin:0 0 11px}h2{font-size:15px;margin:0;font-weight:750}.group-count{font-size:12px;color:#8390a4}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.item{position:relative;display:flex;align-items:center;gap:11px;min-height:65px;padding:14px;background:white;border:1px solid #e2e7ef;border-radius:12px;cursor:pointer;transition:background .15s,border-color .15s,box-shadow .15s}.item:hover{border-color:#aebde2;background:#fafbff}.item:has(input:checked){border-color:#90a9ee;background:#eef3ff;box-shadow:0 0 0 1px #496ddd0a}.item:has(input:checked) .name{color:#304f9d;font-weight:650}.item input{position:absolute;opacity:0;width:22px;height:22px;margin:0}.check{display:grid;place-items:center;flex:0 0 22px;height:22px;border:1.5px solid #ccd5e3;border-radius:7px;background:white;color:transparent}.check svg{width:14px;height:14px}.item input:checked+.check{background:#496ddd;border-color:#496ddd;color:white}.item input:focus-visible+.check{outline:3px solid #a6baf4;outline-offset:3px}.name{font-size:13px;line-height:1.5;word-break:keep-all;overflow-wrap:anywhere}.savebar{position:fixed;bottom:0;left:0;right:0;background:#ffffffed;backdrop-filter:blur(12px);border-top:1px solid #e2e7ef;padding:16px 28px calc(16px + env(safe-area-inset-bottom));z-index:10}.save-inner{max-width:984px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:18px}.save-note{font-size:12px;color:#7a879b;margin:2px 0 0}#save-status{font-size:13px;font-weight:600}button{background:#496ddd;color:white;border:0;border-radius:11px;padding:13px 28px;font-family:inherit;font-size:14px;font-weight:600;line-height:1.5;cursor:pointer;white-space:nowrap}button:hover{background:#3b5cc4}button:focus-visible,.search:focus-visible{outline:3px solid #a6baf4;outline-offset:3px}.notice{padding:12px 16px;background:#e8f4ed;color:#286047;border-radius:10px;margin-top:18px}.error{background:#fff0e8;color:#993b22}#facility-empty{text-align:center;color:#78869a;padding:30px}[hidden]{display:none!important}@media(max-width:760px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}main{padding:24px 18px 140px}h1{font-size:24px}.total{padding:8px 12px}.savebar{padding-left:18px;padding-right:18px}}@media(max-width:440px){.grid{grid-template-columns:1fr 1fr;gap:7px}.item{padding:11px 9px;gap:8px;min-height:70px}.name{font-size:12px}.heading{align-items:start;gap:10px}.total{font-size:11px}.total b{font-size:17px}.toolbar{gap:10px}.search{width:62%;font-size:13px}.toggle{font-size:12px}.save-inner{gap:10px}button{padding:12px 18px}.save-note{font-size:11px}}

.facility-help{margin-top:20px;padding:18px;background:#eef5fb;border:1px solid #d8e6f2;border-radius:14px}.facility-help strong{display:block;margin-bottom:5px}.facility-help p{font-size:12px;color:#597185;margin:0 0 12px}.facility-help button{background:white;color:#225d89;border:1px solid #b9d2e6;padding:10px 14px;font-size:13px;white-space:normal;text-align:left}.facility-help button:disabled{opacity:.65;cursor:wait}.facility-confirm{display:flex;align-items:flex-start;gap:8px;padding:12px 14px;background:#f6faf8;border:1px solid #dcebe3;border-radius:10px;font-size:12px;margin:16px 0}.facility-confirm input{margin-top:4px;flex-shrink:0}

.scope-picker{background:#fff;border:1px solid #dce5ed;border-radius:16px;padding:18px;margin-bottom:18px}.scope-picker p{font-size:12px;color:#718096;margin:7px 0}.scope-choices{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.scope-choices label{background:#f1f5fa;border-radius:9px;padding:9px 12px;cursor:pointer}.scope-tabs{display:flex;overflow-x:auto;gap:8px;margin:0 0 22px;padding-bottom:6px}.scope-tabs button{background:#e8edf5;color:#52647c;padding:10px 18px}.scope-tabs button[aria-pressed=true]{background:#244f77;color:white}.scope-heading{font-size:20px;margin:0 0 18px}.print-scope-state{display:none}.print-action{background:white;color:#244f77;border:1px solid #dce5ed;padding:9px 14px;margin-top:12px}
@media print{@page{size:A4;margin:14mm}body{background:white;color:#172638}main{max-width:none;padding:0}.facility-help,.toolbar,.scope-picker,.scope-tabs,.savebar,.facility-confirm,.notice,.print-action,.total,.eyebrow,#facility-empty{display:none!important}.scope-panel,.scope-panel[hidden]{display:block!important;break-before:page}.scope-panel[data-scope=base]{break-before:auto}.scope-panel[data-included="0"]{display:none!important}.scope-panel .facility-group,.scope-panel .facility-group[hidden]{display:block!important;break-inside:avoid}.scope-panel .item,.scope-panel .item[hidden]{display:none!important}.scope-panel .item:has(input:checked){display:flex!important;background:white;border:1px solid #ccd5e3;box-shadow:none;min-height:0;padding:8px}.scope-panel .facility-group:not(:has(input:checked)){display:none!important}.grid{grid-template-columns:repeat(2,1fr)}.print-scope-state{display:block}.group-count{display:none}.heading{margin-bottom:20px}.intro{font-size:12px}.check{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
.save-actions{display:flex;gap:8px}.reset-action{background:white;color:#95615e;border:1px solid #e3d7d6;padding:12px 16px}.reset-action:hover{background:#fff0ed}@media(max-width:600px){.save-inner{flex-wrap:wrap}.save-actions{width:100%;justify-content:flex-end}main{padding-bottom:185px}}
</style></head><body><main>
<p class="eyebrow">STEP 02 · FIRE FACILITIES</p><div class="heading"><div><h1>소방시설 현황</h1><p class="intro"><?=h($bi['name']??'')?> · 동을 선택하고 설치된 시설을 체크해 주세요.</p></div><span class="total">선택 <b id="selected-total"><?=$c['present']?></b>건</span></div>
<?php if($saved):?><div role="status" class="notice"><?=$resetDone?'현황을 초기화했습니다. 설치된 시설을 다시 체크해 주세요.':'소방시설 현황을 저장했습니다.'?></div><?php endif;?><?php if($error):?><div role="alert" class="notice error"><?=h($error)?></div><?php endif;?>
<div class="facility-help" id="facility-help-card">
<strong>어떤 소방시설이 있는지 잘 모르겠나요?</strong>
<p>설비를 잘 모르겠다면 저장하기 전에 담당 매니저에게 확인을 요청하세요.</p>
<button type="button" id="facility-help-request" disabled>담당 매니저 확인 중…</button>
<p id="facility-help-feedback" role="status" style="margin:10px 0 0"></p>
</div>
<button type="button" class="print-action" onclick="buildingInfoPrint()">인쇄 / PDF</button><div class="toolbar"><input class="search" id="facility-search" type="search" placeholder="시설 이름 검색" aria-label="시설 이름 검색"><label class="toggle"><input type="checkbox" id="selected-only">체크한 시설만</label></div>
<form method="post" id="facility-form"><input type="hidden" name="checklist_form" value="1"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="revision" value="<?=h($error?(string)($_POST['revision']??''):($d['revision']??''))?>">
<input type="hidden" name="multi_scope_form" value="1">
<div class="scope-picker"><strong>시설현황에 포함할 동</strong><p>기존 기록은 기본동에 유지됩니다. 추가 동은 해당 동의 설비를 따로 확인해 주세요.</p><div class="scope-choices">
<?php foreach($scopes as $sid=>$scope):?><label><input type="checkbox" class="scope-include" name="included[]" value="<?=h($sid)?>" <?=!empty($scope['included'])?'checked':''?> <?=$sid==='base'?'disabled':''?>> <?=h($scope['label'])?></label><?php endforeach;?></div>
<?php if(count($scopes)===1):?><p>추가 동은 기본정보의 건물 조회에서 동별 정보를 등록하면 표시됩니다.</p><?php endif;?>
<p>포함을 해제해도 저장한 동별 기록은 보관됩니다.</p></div>
<div class="scope-tabs" aria-label="작성할 동 선택"><?php foreach($scopes as $sid=>$scope):?><button type="button" data-scope-tab="<?=h($sid)?>" aria-pressed="false"><?=h($scope['label'])?></button><?php endforeach;?></div>
<?php foreach($scopes as $sid=>$scope):$one=bf_counts(['items'=>$scope['items']??[]]);?>
<div class="scope-panel" data-scope="<?=h($sid)?>" data-unrecorded="<?=$one['confirmed']===0?'1':'0'?>" data-included="<?=!empty($scope['included'])?'1':'0'?>">
<h2 class="scope-heading"><?=h($scope['label'])?></h2><p class="print-scope-state">선택한 시설 <?=$one['present']?>종</p>
<?php foreach(bf_catalog() as $group):?><section class="facility-group"><div class="group-title"><h2><?=h($group[0])?></h2><span class="group-count"></span></div><div class="grid"><?php foreach($group[1] as $name):$id=bf_id($name);$v=$scope['items'][$id]??[];?><label class="item" data-name="<?=h($name)?>"><input type="checkbox" name="installed[<?=h($sid)?>][<?=$id?>]" value="1" <?=($v['status']??'unknown')==='yes'?'checked':''?>><span class="check" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none"><path d="m3 8 3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span class="name"><?=h($name)?></span></label><?php endforeach;?></div></section><?php endforeach;?>
</div>
<?php endforeach;?>
<input type="hidden" name="form_end" value="1"></form><p id="facility-empty" hidden>조건에 맞는 시설이 없습니다.</p><script src="/manager_help.js?v=7" data-facilities="1" data-uid="<?=h(bi_user_key())?>" data-manager="<?=!empty($_SESSION['_mge_actor'])?'1':'0'?>"></script>
<script>
(function(){
 const button=document.getElementById('facility-help-request'),feedback=document.getElementById('facility-help-feedback');
 function label(s){if(s.mode==='local')return '잘 모르겠어요 · 로컬매니저 연결하고 요청하기';return '잘 모르겠어요 · '+(s.manager_name||'담당 매니저')+'에게 요청하기'+(s.mode==='pending'?' (연결 수락 대기)':'');}
 window.managerHelp.ready.then(function(s){
  if(s&&s.mode==='manager'){button.hidden=true;document.querySelector('#facility-help-card strong').textContent='소방시설 설치 여부를 확인해 주세요';document.querySelector('#facility-help-card p').textContent='설치된 설비를 체크하고 저장하면 작성 도움 요청도 함께 완료됩니다.';return;}
  button.disabled=false;button.textContent=s?label(s):'담당 매니저 확인하고 요청하기';
 });
 button.onclick=async function(){button.disabled=true;try{const s=await window.managerHelp.request('__facilities','소방시설 현황: 설치된 소방시설의 종류와 설치 여부를 확인해 주세요.');if(s){feedback.textContent=s.mode==='pending'?'연결 수락 후 매니저가 시설 확인 요청을 볼 수 있습니다.':'담당 매니저에게 소방시설 현황 확인을 요청했습니다.';button.textContent='요청 접수 완료';}else button.disabled=false;}catch(e){feedback.textContent=e.message;button.disabled=false;}};
 <?php if($saved): ?>try{if(window.parent!==window)window.parent.managerHelp?.refresh();}catch(e){}<?php endif; ?>
})();
</script></main>
<div class="savebar"><div class="save-inner"><div><span id="save-status" role="status">설치된 시설을 모두 체크했나요?</span><p class="save-note">체크하지 않은 시설은 ‘없음’으로 저장되며, 작성 도움 요청도 완료됩니다.</p></div><div class="save-actions"><button type="submit" form="facility-form" name="action" value="reset" class="reset-action">현황 초기화</button><button type="submit" form="facility-form" name="action" value="save">저장하기</button></div></div></div>
<script src="/building_facilities_scopes.js?v=4"></script></body></html>
