<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_admin():bool{return !empty($_SESSION['is_admin'])||(!empty($_SESSION['ID_OK'])&&$_SESSION['ID_OK']==1);}
if(!is_admin()&&empty($_SESSION['is_user'])){header('Location: /index.php');exit;}
if(!is_admin()&&($_SESSION['role']??'')!=='building'){header('Location: /partner_center.php');exit;}
require_once __DIR__.'/fire_plan_overview_common.php';
require_once __DIR__.'/manager_fire_plan_help.php';
require_once __DIR__.'/fire_plan_schedule_common.php';
if(fp_user_key()===''){http_response_code(403);exit('다시 로그인해 주세요.');}
header('Cache-Control: private, no-store');
$context=[];foreach(['embed','modal'] as $k)if(($_GET[$k]??'')==='1')$context[$k]='1';
if(is_admin()&&is_string($_GET['uid']??null))$context['uid']=$_GET['uid'];
$returnToBook=($_POST['return_book']??'')==='1';
$workspace=is_string($_GET['workspace']??null)&&in_array($_GET['workspace'],['sources','review'],true)?$_GET['workspace']:'';
$link=static function(string $path,array $args=[])use($context,$workspace):string{
 if($workspace!==''&&$path==='/fire_plan.php'&&!array_key_exists('workspace',$args))$args['workspace']=$workspace;
 if($workspace!==''&&$path==='/fire_plan_chat.php')$args['workflow']='1';
 $q=array_merge($context,$args);return $path.($q?'?'.http_build_query($q):'');
};
$currentYear=(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y');
$previousYear=$currentYear-1;
$nextYear=$currentYear+1;
$year=filter_var($_GET['year']??$currentYear,FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
if($year===false)$year=$currentYear;
$error='';$plans=[];
foreach(fp_list_plans() as $row){$p=fp_load_plan((string)($row['id']??''));if($p)$plans[]=$p;}
$id=is_string($_GET['id']??null)?$_GET['id']:'';
$plan=$id!==''?fp_load_plan($id):null;
if($id!==''&&!$plan){http_response_code(404);exit('계획서를 찾을 수 없습니다. 목록에서 다시 열어 주세요.');}
if(!$plan)foreach($plans as $p)if(fp_plan_year($p)===$year){$plan=$p;$id=(string)$p['id'];break;}
if($plan)$year=fp_plan_year($plan);
if($plan&&empty($_GET['id'])&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
 $args=['id'=>$id,'year'=>$year];if(in_array($_GET['schedule_kind']??'',['fire','jawi'],true))$args['schedule_kind']=$_GET['schedule_kind'];if(isset($_GET['deleted']))$args['deleted']='1';
 header('Location: '.$link('/fire_plan.php',$args));exit;
}
if($plan&&in_array($_GET['schedule_kind']??'',['fire','jawi'],true)&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'){header('Location: '.$link('/fire_plan_schedule.php',['id'=>$id,'year'=>$year,'kind'=>$_GET['schedule_kind']]));exit;}
$bi=bi_load();$evac=epc_load();$sources=[];
try{$sources=fpo_sources($bi,$evac,$year);}catch(Throwable $e){$error='기존 자료를 읽지 못했습니다. 잠시 후 새로고침해 주세요. 저장된 계획서는 그대로 유지됩니다.';error_log('Fire plan sources: '.$e->getMessage());}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 fp_csrf_check();
 try{
  $act=$_POST['act']??'';
  if($act==='create'){
   foreach($plans as $p)if(fp_plan_year($p)===$year){header('Location: '.($returnToBook?$link('/fire_plan_book.php',['year'=>$year]):$link('/fire_plan.php',['id'=>$p['id']])));exit;}
   $id=fp_create_plan('business',$year);if(!fp_load_plan($id))throw new RuntimeException('계획서를 만들지 못했습니다. 다시 시도해 주세요.');
   // Starting a plan includes the saved source records; later refreshes remain explicit.
   if($sources){
    $selected=array_keys(array_filter($sources['groups'],static fn($g)=>!empty($g['available'])));
    if($selected){$created=fp_load_plan($id);fpo_save_import($id,fpo_sources($bi,$evac,$year,$selected),$selected,fpo_hash($created));}
   }
   if($returnToBook){header('Location: '.$link('/fire_plan_book.php',['year'=>$year]));exit;}
   header('Location: '.($year>$currentYear&&$workspace===''?$link('/fire_plan_schedule.php',['id'=>$id,'year'=>$year,'kind'=>in_array($_GET['schedule_kind']??'',['fire','jawi'],true)?$_GET['schedule_kind']:'fire']):$link('/fire_plan.php',['id'=>$id,'created'=>'1']+($workspace!==''?['workspace'=>'sources']:[])).'#sources'));exit;
  }
  if(!$plan||!hash_equals(fpo_hash($plan),(string)($_POST['revision']??'')))throw new RuntimeException('계획서가 변경되었습니다. 새로고침 후 다시 확인해 주세요.');
  if($act==='import'){
   if(!$sources)throw new RuntimeException('자료를 읽지 못해 반영하지 않았습니다. 새로고침해 주세요.');
   if(!hash_equals($sources['fingerprint'],(string)($_POST['source_revision']??'')))throw new RuntimeException('기존 자료가 변경되었습니다. 새로고침 후 반영할 내용을 다시 확인해 주세요.');
   $selected=$_POST['sources']??[];if(!is_array($selected))throw new RuntimeException('반영할 자료를 선택해 주세요.');
   foreach($selected as $key)if(!is_string($key)||!isset($sources['groups'][$key])||!$sources['groups'][$key]['available'])throw new RuntimeException('반영할 자료를 다시 확인해 주세요.');
   $selected=array_values(array_unique($selected));
   $chosen=fpo_sources($bi,$evac,$year,$selected);
   fpo_save_import($id,$chosen,$selected,(string)$_POST['revision']);
   if($returnToBook){header('Location: '.$link('/fire_plan_book.php',['year'=>$year,'saved'=>'1']));exit;}
   if($workspace==='sources'){$afterImport=fpo_status(fp_load_plan($id)??[]);header('Location: '.$link('/fire_plan_chat.php',['id'=>$id,'year'=>$year,'section'=>(string)($afterImport['next']['code']??'1')]));exit;}
   header('Location: '.$link('/fire_plan.php',['id'=>$id,'saved'=>'1']));exit;
  }
  if($act==='review'){
   if(empty($_POST['confirmed'])||!fpo_status($plan)['complete'])throw new RuntimeException('남은 항목을 작성하고 내용 확인에 체크해 주세요.');
   $expected=(string)$_POST['revision'];
   $ok=mfp_save(fp_user_key(),$id,'0',[],static function()use($id,$expected):bool {
    $current=fp_load_plan($id);
    if(!$current||!hash_equals(fpo_hash($current),$expected))throw new RuntimeException('계획서가 변경되었습니다. 새로고침 후 다시 확인해 주세요.');
    if(!fpo_status($current)['complete'])throw new RuntimeException('남은 항목을 먼저 확인해 주세요.');
    $current['final_review']=['hash'=>fpo_content_hash($current),'at'=>date('Y-m-d H:i:s')];$current['status']='done';$current['updated_at']=date('Y-m-d H:i:s');
    if(!fp_write_json(fp_plan_file($id),$current))return false;fp_touch_index($id,$current);return true;
   });
   if(!$ok)throw new RuntimeException('확인 상태를 저장하지 못했습니다. 다시 시도해 주세요.');
   header('Location: '.($returnToBook?$link('/fire_plan_book.php',['year'=>$year,'reviewed'=>'1']):$link('/fire_plan.php',['id'=>$id,'reviewed'=>'1'])));exit;
  }
  if($act==='delete'){fp_delete_plan($id);header('Location: '.$link('/fire_plan.php',['year'=>$year,'deleted'=>'1']));exit;}
  throw new RuntimeException('지원하지 않는 요청입니다.');
 }catch(RuntimeException $e){$error=$e->getMessage();if($returnToBook){$_SESSION['fp_book_error'][fp_user_key().':'.$year]=$error;header('Location: '.$link('/fire_plan_book.php',['year'=>$year]));exit;}}
}
$yearPlans=[];
foreach($plans as $savedPlan){$savedYear=fp_plan_year($savedPlan);if(!isset($yearPlans[$savedYear]))$yearPlans[$savedYear]=$savedPlan;}
if($plan)$yearPlans[$year]=$plan;
$yearStatus=static function(int $target)use($yearPlans,$currentYear):string {
 if(!isset($yearPlans[$target]))return $target<$currentYear?'보관본 없음':'작성 전';
 $progress=fpo_status($yearPlans[$target]);
 return $progress['reviewed']?'최종 확인 완료':($progress['complete']?'내용 확인 필요':$progress['percent'].'% 작성');
};
$yearLabel=$year===$currentYear?'올해 계획':($year===$nextYear?'차기년도 준비':($plan?'보관된 계획':'지난 연도 계획'));
$workingYears=[$previousYear=>'지난해 보관',$currentYear=>'올해 계획',$nextYear=>'차기 계획'];
$navigationYears=array_unique(array_merge(array_keys($yearPlans),array_keys($workingYears)));sort($navigationYears,SORT_NUMERIC);
$previousTarget=null;$nextTarget=null;
foreach($navigationYears as $target){if($target<$year)$previousTarget=$target;elseif($target>$year&&$nextTarget===null)$nextTarget=$target;}
$yearLinkLabel=static fn(int $target):string=>$target.'년 '.($target===$currentYear?'올해 계획':($target===$nextYear?'차기년도 계획':'지난 계획')).' 보기';
$state=fpo_status($plan??[]);$name=(string)($plan['sections']['1']['data']['name']??$bi['name']??'내 건물');
if(trim($name)==='')$name='내 건물';
$address=(string)($plan['sections']['1']['data']['addr']??$bi['address']??'');
$groups=$sources['groups']??[];$savedGroups=(array)($plan['source_bundle']['groups']??[]);
$selection=!empty($plan['source_selection_set'])?(array)($plan['source_selection']??[]):array_keys(array_filter($groups,static fn($g)=>$g['available']));
$changed=[];foreach($savedGroups as $key=>$g)if(isset($groups[$key])&&($g['fingerprint']??'')!==$groups[$key]['fingerprint'])$changed[]=$g['title'];
$helpRequests=$plan&&!empty($_SESSION['_mge_actor'])?mfp_pending(fp_user_key(),$id):[];
$short=['1'=>'건물 기본현황','2'=>'소방시설 현황','3'=>'자체점검 계획','4'=>'점검·정비 계획','5'=>'피난계획','6'=>'방화구획·방염 관리','7'=>'권원별 안전관리','8'=>'공동관리 협의','9'=>'자위소방대 조직·임무','10'=>'화기작업 안전관리','11'=>'소방훈련·교육 계획','12'=>'위험물 관리','13'=>'업무수행 기록 관리','14'=>'화재 초기대응','15'=>'추가 요청사항'];
$schema=fp_chat_schema();
$chatArgs=['id'=>$id,'year'=>$year];if($state['next'])$chatArgs['section']=$state['next']['code'];
$hidden=static function()use($plan,$id){echo '<input type="hidden" name="csrf" value="'.h(fp_csrf()).'"><input type="hidden" name="revision" value="'.h(fpo_hash($plan??[])).'">';};
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($name)?> · 소방계획서</title><link rel="stylesheet" href="/fire_plan_overview.css?v=20261010-years-archive-3"><link rel="stylesheet" href="/fire_plan_share.css?v=20261010-1"><script src="/fire_plan_share.js?v=20261015-partner-1" defer></script><?php if($workspace!==''):?><link rel="stylesheet" href="/fire_plan_workspace.css?v=20261013-1"><?php endif;?></head>
<body class="<?=$workspace!==''?'fp-workspace':''?>" data-workspace="<?=h($workspace)?>"><main class="fp-home">
<?php if($workspace!==''):?>
<header class="fp-workspace-head"><p><?=$year?>년 · 소방계획서 완성</p><h1><?=h($name)?></h1><nav aria-label="계획서 완성 단계">
<a class="<?=$workspace==='sources'?'is-active':''?>" href="<?=h($link('/fire_plan.php',['year'=>$year]+($plan?['id'=>$id]:[])+['workspace'=>'sources']))?>" <?=$workspace==='sources'?'aria-current="step"':''?>><span>1</span>자료 불러오기</a>
<a href="<?=h($plan&&!empty($plan['source_selection_set'])?$link('/fire_plan_chat.php',$chatArgs):$link('/fire_plan.php',['year'=>$year]+($plan?['id'=>$id]:[])+['workspace'=>'sources']))?>"><span>2</span>내용 작성</a>
<a class="<?=$workspace==='review'?'is-active':''?>" href="<?=h($link('/fire_plan.php',['year'=>$year]+($plan?['id'=>$id]:[])+['workspace'=>$plan?'review':'sources']))?>" <?=$workspace==='review'?'aria-current="step"':''?>><span>3</span>최종 확인</a>
</nav></header>
<?php if($workspace==='sources'&&$year<$currentYear):?><p class="fp-workspace-year-note">현재 저장된 건물 현황을 가져옵니다. <?=$year?>년 당시의 내용과 같은지 확인해 주세요.</p><?php endif;?>
<?php if(!$plan):?><section class="fp-workspace-start"><h2><?=$year?>년 계획서를 시작해요</h2><p>계획서를 만든 뒤 불러올 자료를 선택해 주세요. 직접 작성한 업무 기록은 그대로 유지됩니다.</p><form method="post"><?php $hidden();?><input type="hidden" name="act" value="create"><button class="fp-button primary">계획서 만들고 자료 선택 <span>→</span></button></form></section><?php endif;?>
<?php endif;?>
<header class="fp-top"><a href="<?=h($link('/fire_plan.php',['year'=>$currentYear]))?>" class="fp-brand"><span class="fp-logo" aria-hidden="true">▤</span> 우리 건물 소방계획서</a><span class="fp-cycle-label">지난 계획 보관부터 차기 계획 준비까지</span></header>
<nav class="fp-year-switch" aria-label="소방계획서 연도 선택">
 <?php if($previousTarget!==null):?><a class="fp-year-arrow" href="<?=h($link('/fire_plan.php',['year'=>$previousTarget]))?>" aria-label="<?=h($yearLinkLabel($previousTarget))?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></a><?php else:?><span class="fp-year-arrow is-disabled" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m14 6-6 6 6 6"/></svg></span><?php endif;?>
 <div class="fp-year-tabs"><?php foreach($workingYears as $targetYear=>$caption):?><a class="fp-year-tab <?=$year===$targetYear?'is-selected':''?>" href="<?=h($link('/fire_plan.php',['year'=>$targetYear]))?>" <?=$year===$targetYear?'aria-current="page"':''?>><span class="fp-year-caption"><?=h($caption)?></span><strong><?=$targetYear?><span>년</span></strong><small><?=h($yearStatus($targetYear))?></small></a><?php endforeach;?></div>
 <?php if($nextTarget!==null):?><a class="fp-year-arrow" href="<?=h($link('/fire_plan.php',['year'=>$nextTarget]))?>" aria-label="<?=h($yearLinkLabel($nextTarget))?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></a><?php else:?><span class="fp-year-arrow is-disabled" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m10 6 6 6-6 6"/></svg></span><?php endif;?>
</nav>
<div class="fp-year-guidance"><?php if($year===$nextYear):?><p><strong><?=$nextYear?>년 계획을 미리 준비하고 있어요.</strong> 올해 <?=$currentYear?>년 12월 31일까지 작성·검토해 주세요.</p><?php elseif($year===$currentYear):?><p>올해 계획을 관리하고, <strong><?=$nextYear?>년 계획도 올해 12월 31일까지</strong> 준비해 주세요.</p><?php elseif($plan):?><p><strong><?=$year?>년 보관된 계획서</strong>를 보고 있어요. 연도가 바뀌어도 저장한 내용은 유지됩니다.</p><?php else:?><p><strong><?=$year?>년 저장된 계획서가 없습니다.</strong> 지난 계획을 추가할 때는 해당 연도의 실제 자료를 확인해 작성해 주세요.</p><?php endif;?>
 <details><summary>작성 기준</summary><p>공개 소방계획서 작성서식은 차기년도 계획을 매년 12월 31일까지 작성·검토하고 대표자·소유자의 최종 승인을 받도록 안내합니다. 화면의 ‘최종 확인 완료’는 작성 내용의 확인 상태이며, 관계인의 승인을 대신하지 않습니다.</p><p><a href="https://gfsa.gwd.go.kr/egf/bp/board/article/download?fileSeq=1212208" target="_blank" rel="noopener noreferrer">소방계획서 공개 서식 · 문서작성 및 기록관리 ↗</a></p></details>
</div>

<?php if($error):?><p class="fp-alert" role="alert"><?=h($error)?></p><?php endif;?>
<?php if(isset($_GET['saved'])):?><p class="fp-notice" role="status">✓ 선택한 자료가 반영되었습니다. 직접 수정한 답변은 유지했습니다.</p><?php elseif(isset($_GET['reviewed'])):?><p class="fp-notice" role="status">✓ 최종 확인을 저장했습니다. 완성본을 미리 보고 인쇄해 보세요.</p><?php elseif(isset($_GET['deleted'])):?><p class="fp-notice" role="status">선택한 계획서가 삭제되었습니다.</p><?php endif;?>
<?php if($plan):$scheduled=fpsc_events($plan);?>
<section class="fp-overview-schedule" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;padding:20px 24px;margin:18px 0;border:1px solid #cbdcf5;border-radius:16px;background:#edf4ff">
<div><strong style="display:block;color:#285fbb;font-size:17px"><?=$year?>년 교육·훈련 예정 일정</strong><p style="margin:5px 0 0;color:#627c9f;font-size:14px"><?=$scheduled?'✓ '.count($scheduled).'개 일정 반영됨 · 계획서와 인쇄본에서 확인할 수 있어요.':'소방훈련·소방교육·자위소방대 교육, 예정 월부터 간편하게 정해 보세요.'?></p></div>
<a class="fp-button primary" href="<?=h($link('/fire_plan_schedule.php',['id'=>$id,'year'=>$year,'kind'=>in_array($_GET['schedule_kind']??'',['fire','jawi'],true)?$_GET['schedule_kind']:'fire']))?>"><?=$scheduled?'예정 일정 확인·수정':'예정 일정 정하기'?> →</a></section>
<?php endif;?>
<section class="fp-hero">
 <div class="fp-hero-copy"><p class="fp-eyebrow"><?=$year?>년 · <?=h($yearLabel)?></p><span class="fp-status <?=$state['reviewed']?'is-ready':''?>"><?=$state['reviewed']?'✓ 최종 확인 완료':($plan?'작성 중 · 자동 저장':'기존 자료로 간편하게 시작')?></span><h1><?=h($name)?><br><span>소방계획서</span></h1><p class="fp-address"><?=h($address)?></p>
 <p class="fp-lead"><?=$state['reviewed']?'차곡차곡 작성한 우리 건물의 안전관리 계획이 한 권에 담겼어요.':($plan?'이미 작성한 자료를 모으고, 남은 내용만 확인하면 돼요.':'기본정보부터 피난계획까지, 작성해 둔 자료를 한 권으로 모아보세요.')?></p>
 <div class="fp-actions"><?php if(!$plan):?><form method="post"><?php $hidden();?><input type="hidden" name="act" value="create"><button class="fp-button primary"><?=$year?>년 계획서 만들기 <span>→</span></button></form><?php else:?><a class="fp-button primary" href="<?=h($link($state['reviewed']?'/fire_plan_print.php':'/fire_plan_chat.php',$state['reviewed']?['id'=>$id]:$chatArgs))?>"><?=$state['reviewed']?'완성본 미리보기':($state['complete']?'작성한 답변 확인':'남은 질문 이어서 작성')?> <span>→</span></a><a class="fp-button" href="<?=h($link($state['reviewed']?'/fire_plan_chat.php':'/fire_plan_print.php',['id'=>$id]))?>"><?=$state['reviewed']?'내용 다시 확인':'문서 미리보기'?></a><?php endif;?></div>
 </div>
 <div class="fp-document" aria-label="작성 현황"><div class="fp-paper"><div class="fp-paper-top"><span>소방계획서.com</span><span><?=$year?></span></div><p>FIRE SAFETY PLAN</p><h2><span class="fp-paper-year"><?=$year?>년 </span>소방계획서</h2><strong><?=h($name)?></strong><div class="fp-paper-lines"><i></i><i></i><i></i></div><div class="fp-progress"><span><?=$state['reviewed']?'최종 확인 완료':'항목 채움 현황'?></span><b><?=$state['percent']?>%</b></div><div class="fp-track" role="progressbar" aria-label="항목 채움 현황" aria-valuenow="<?=$state['percent']?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?=$state['percent']?>%"></i></div><small><?=$state['filled']?> / <?=$state['total']?>개 질문 · 해당 없는 항목 제외</small></div></div>
</section>
<div class="fp-metrics"><div><span>반영한 자료</span><b><?=count($savedGroups)?><small>종</small></b></div><div><span>내용이 채워진 항목</span><b><?=$state['done']?><small> / <?=15-$state['skipped']?>개</small></b></div><div><span>추가로 확인할 질문</span><b><?=$state['total']-$state['filled']?><small>개</small></b></div></div>
<?php if($plan):?>
<section class="fp-panel" id="sources"><div class="fp-section-head"><div><p class="fp-eyebrow">01 · 자료 모으기</p><h2>작성해 둔 자료를 반영해요</h2><p>선택한 자료는 계획서와 동별 상세표에 함께 저장됩니다.</p></div><?php if(!empty($plan['source_bundle']['imported_at'])):?><small>최근 반영 <?=h($plan['source_bundle']['imported_at'])?></small><?php endif;?></div>
<?php if($changed):?><p class="fp-alert">새로운 내용이 있어요 · <?=h(implode(', ',$changed))?><br><small>아래에서 자료를 다시 반영하면 불러온 값이 갱신됩니다. 직접 수정한 답변은 유지됩니다.</small></p><?php endif;?>
<form method="post" class="fp-source-form"><?php $hidden();?><input type="hidden" name="act" value="import"><input type="hidden" name="source_revision" value="<?=h($sources['fingerprint']??'')?>"><div class="fp-source-grid">
<?php foreach($groups as $key=>$g):$included=isset($savedGroups[$key]);$different=$included&&$savedGroups[$key]['fingerprint']!==$g['fingerprint'];?><label class="fp-source <?=$g['available']?'':'is-empty'?>"><input type="checkbox" name="sources[]" value="<?=h($key)?>" <?=in_array($key,$selection,true)&&$g['available']?'checked':''?> <?=$g['available']?'':'disabled'?>><span class="fp-source-content"><strong><?=h($g['title'])?></strong><small><?=h($g['detail'])?></small><em class="<?=$different?'needs-update':''?>"><?=$different?'새 내용 있음':($included?'✓ 반영됨':($g['available']?'반영 가능':'저장된 자료 없음'))?></em></span></label><?php endforeach;?></div>
<div class="fp-source-foot"><p>현재 저장된 기본정보·시설·편성·피난계획과<br><strong><?=$year?>년</strong> 교육·훈련·월별 기록을 가져옵니다.</p><button class="fp-button primary" <?=$sources?'':'disabled'?>><?= $workspace==='sources'?'반영하고 내용 작성':'선택한 자료 반영하기' ?> <span><?=$workspace==='sources'?'→':'↻'?></span></button></div></form>
<details class="fp-detail"><summary>어떤 내용이 반영되나요?</summary><p>건물·관리자 정보, 동별 현황, 시설 종류와 수신기 위치, 대원별 임무, 사용 중인 동별 피난경로, 교육·훈련 실시기록, 월별 기록 현황을 가져옵니다. 비화재보 기록이 있으면 같은 연도의 발생·조치 내역도 선택해 첨부할 수 있습니다. 점검 시기와 앞으로의 훈련계획 등 결정이 필요한 내용은 문답에서 확인합니다.</p><p>예전 연도의 계획서를 작성할 때는 현재 건물 현황이 그 당시와 같은지 확인해 주세요. 선택을 해제하고 다시 반영하면 해당 자료에서 자동으로 가져온 값은 제외됩니다. 직접 수정한 답변은 유지됩니다. 자료 반영만으로 전체 계획서가 완료되지는 않습니다.</p></details></section>
<?php if($helpRequests && preg_match('/^__fp_[0-9]+_([0-9]+)_([A-Za-z0-9_]+)$/',$helpRequests[0]['field'],$helpMatch)):?><a class="fp-alert fp-help-link" href="<?=h($link('/fire_plan_chat.php',['id'=>$id,'help_code'=>$helpMatch[1],'help_key'=>$helpMatch[2]]))?>">작성 문의 <?=count($helpRequests)?>건 · 요청한 질문에 답변하기 →</a><?php endif;?>
<section class="fp-panel" id="contents"><div class="fp-section-head"><div><p class="fp-eyebrow">02 · 내용 확인하기</p><h2>우리 건물의 계획이 채워지고 있어요</h2><p>항목을 누르면 남은 질문부터 이어서 작성할 수 있어요.</p></div><span class="fp-count"><?=$state['done']?> / <?=15-$state['skipped']?>개 항목</span></div>
<div class="fp-section-grid"><?php foreach($state['sections'] as $code=>$st):?><a class="fp-section <?=$st['skip']?'is-skip':($st['done']?'is-done':'')?>" href="<?=h($link('/fire_plan_chat.php',['id'=>$id,'section'=>$code]))?>"><span class="fp-section-number"><?=$st['done']?'✓':str_pad((string)$code,2,'0',STR_PAD_LEFT)?></span><span><strong><?=h($short[$code])?></strong><small><?=$st['skip']?'해당 없음':($st['done']?'내용이 모두 채워졌어요':'남은 질문 '.count($st['missing']).'개')?><?=$st['source']?' · 자료 반영':''?></small></span><span class="fp-section-arrow">→</span></a><?php endforeach;?></div>
</section>
<section class="fp-finish <?=$state['reviewed']?'is-ready':''?>" id="review"><span class="fp-finish-icon" aria-hidden="true"><?=$state['reviewed']?'✓':'▤'?></span><div><p class="fp-eyebrow">03 · 한 권으로 완성하기</p><h2><?=$state['reviewed']?'우리 건물의 소방계획서를 확인했어요':($state['complete']?'마지막으로 완성본을 확인해 주세요':'미리 보면서 마무리해 보세요')?></h2><p><?=$state['complete']?'실제 건물의 시설·담당자·대응방법과 맞는지 확인한 뒤 최종 확인을 저장하세요.':'작성한 내용은 지금도 문서로 확인할 수 있어요. 남은 질문을 채우면 최종 확인이 열립니다.'?></p><div class="fp-actions"><a class="fp-button" href="<?=h($link('/fire_plan_print.php',['id'=>$id]))?>">완성본 미리보기 · 인쇄</a><button type="button" class="fp-button fps-open-button" data-fps-open data-fps-year="<?=$year?>" data-fps-endpoint="<?=h($link('/fire_plan_share_api.php'))?>" aria-haspopup="dialog"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h3v3h-3zM21 14v7h-6M3 12h6M12 3v6M12 12h3M12 18v3"/></svg> 계획 공유 · QR</button><a class="fp-text-link" href="<?=h($link('/fire_plan_edit.php',['id'=>$id]))?>">표로 직접 수정 →</a></div>
<?php if($workspace==='review'&&!$state['complete']):?><a class="fp-button primary fp-workspace-continue" href="<?=h(!empty($plan['source_selection_set'])?$link('/fire_plan_chat.php',$chatArgs):$link('/fire_plan.php',['id'=>$id,'workspace'=>'sources']))?>"><?=!empty($plan['source_selection_set'])?'남은 '.($state['total']-$state['filled']).'개 질문 이어서 작성':'기존 자료부터 불러오기'?> →</a><?php endif;?>
<?php if($state['complete']&&!$state['reviewed']):?><form method="post" class="fp-confirm"><?php $hidden();?><input type="hidden" name="act" value="review"><label><input type="checkbox" name="confirmed" value="1" required> 건물 현황과 계획서 내용을 확인했습니다.</label><button class="fp-button primary">최종 확인 저장</button></form><?php elseif($state['reviewed']):?><p class="fp-reviewed-at">최종 확인 <?=h($plan['final_review']['at']??'')?></p><?php endif;?></div></section>
<?php endif;?>
<section class="fp-library"><div class="fp-section-head"><div><h2>보관 중인 계획서</h2><p>지난 연도 계획서는 자동으로 삭제되지 않습니다. 더 오래된 계획도 여기에서 확인할 수 있어요.</p></div></div><?php if(!$plans):?><p class="fp-library-empty">첫 계획서를 만들면 여기에 차곡차곡 보관돼요.</p><?php endif;?><div class="fp-library-list"><?php foreach($plans as $p):$s=fpo_status($p);?><a href="<?=h($link('/fire_plan.php',['id'=>$p['id']]))?>" class="<?=($p['id']===$id)?'is-current':''?>"><span>▤</span><strong><?=fp_plan_year($p)?>년 소방계획서<small><?=h($p['building_name']?:'건물정보 입력 전')?></small></strong><em><?=$s['reviewed']?'최종 확인 완료':$s['percent'].'% 작성'?></em><span>→</span></a><?php endforeach;?></div></section>
<?php if($plan):?><details class="fp-danger"><summary>계획서 관리</summary><form method="post" onsubmit="return confirm('이 계획서와 연결된 작성 문의·완료 내역을 삭제할까요? 되돌릴 수 없습니다.')"><?php $hidden();?><input type="hidden" name="act" value="delete"><p>기본정보와 원본 교육·훈련 기록은 유지됩니다.</p><button class="fp-button">이 계획서 삭제</button></form></details><?php endif;?>
<footer class="fp-footer">소방계획서.com <span>우리 건물의 안전관리를 한 권에</span></footer>
</main><script src="/fire_plan_overview.js?v=20261008-1"></script><?php if(is_file(__DIR__.'/admin_quickmemo_widget.php'))require_once __DIR__.'/admin_quickmemo_widget.php'; ?></body></html>
