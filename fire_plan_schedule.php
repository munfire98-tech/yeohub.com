<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';


/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
require_once __DIR__.'/manager_edit_guard.php';
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_admin():bool{return !empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);}
if(!is_admin()&&empty($_SESSION['is_user'])){header('Location: /index.php');exit;}
if(!is_admin()&&($_SESSION['role']??'')!=='building'){header('Location: /partner_center.php');exit;}
require_once __DIR__.'/fire_plan_schedule_service.php';
if(fp_user_key()===''||!empty($_SESSION['_mge_help_review'])){http_response_code(403);exit('건물관리 화면에서 일정을 열어 주세요.');}
header('Cache-Control: private, no-store');
$context=[];foreach(['modal','embed'] as $key)if(($_GET[$key]??'')==='1')$context[$key]='1';
if(is_admin()&&is_string($_GET['uid']??null))$context['uid']=$_GET['uid'];
$link=static function(string $path,array $args=[])use($context):string{return $path.'?'.http_build_query(array_merge($context,$args));};
$id=is_string($_GET['id']??null)?$_GET['id']:'';$plan=fp_load_plan($id);
if(!$plan){http_response_code(404);exit('계획서를 찾을 수 없습니다. 계획서 목록에서 다시 열어 주세요.');}
$year=fp_plan_year($plan);$error='';$events=fpsc_events($plan);$posted=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    fp_csrf_check();
    try{
        if(($_POST['act']??'')==='save'){
            $raw=$_POST['events']??'';
            if(!is_string($raw)||strlen($raw)>200000)throw new RuntimeException('입력 내용을 다시 확인해 주세요.');
            $posted=json_decode($raw,true);$valid=fpsc_validate($posted,$year);
            fpsc_save($id,$valid,(string)($_POST['revision']??''));
            header('Location: '.$link('/fire_plan_schedule.php',['id'=>$id,'saved'=>'1']),true,303);exit;
        }
        if(($_POST['act']??'')==='start'){
            $r=fpsc_start_record($id,(string)($_POST['event']??''),(string)($_POST['actual']??''),(string)($_POST['revision']??''));
            header('Location: '.$link($r['page'],['id'=>$r['id'],'year'=>$year]),true,303);exit;
        }
        throw new RuntimeException('지원하지 않는 요청입니다.');
    }catch(RuntimeException $e){$error=$e->getMessage();}
}
$revision=(string)($_POST['revision']??fpo_hash($plan));
// Never replace a stale revision after an error: the user must reload to review concurrent edits.
if(isset($valid))$events=$valid;
if(!$events){$old=(array)($plan['sections']['11']['data']??[]);foreach(fpsc_kinds() as $kind=>$title){$n=$kind==='fire'?1:2;
 $events[]=['id'=>bin2hex(random_bytes(8)),'kind'=>$kind,'precision'=>'month','date'=>'','same_as'=>'','who'=>$kind==='jawi'?'자위소방대 및 초기대응체계 대원':($old['t'.$n.'_who']??'건물 관계인 및 근무자'),'place'=>'','content'=>$kind==='jawi'?'역할별 임무, 화재 신고·초기소화·피난유도 교육':($old['t'.$n.'_how']??($kind==='fire'?'화재 신고·초기소화·피난유도 훈련':'소화기·소화전 사용법 및 대피요령 교육'))];
}}
$boot=['kind'=>array_key_exists((string)($_GET['kind']??''),fpsc_kinds())?$_GET['kind']:'fire','year'=>$year,'events'=>$events,'references'=>fpsc_references($year-1),'saved'=>count(fpsc_events($plan))>0];
$recordEvent=null;foreach(fpsc_events($plan) as $e)if($e['id']===($_GET['record']??''))$recordEvent=$e;
$name=(string)($plan['sections']['1']['data']['name']??$plan['building_name']??'우리 건물');
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$year?>년 교육·훈련 계획</title><link rel="stylesheet" href="/fire_plan_schedule.css?v=20261013-1"><script src="/fire_plan_schedule.js?v=20261013-work-years-1" defer></script></head>
<body class="fpsc-body"><main class="fpsc-shell">
<header class="fpsc-top"><a href="<?=h($link('/fire_plan.php',['id'=>$id]))?>">‹ 소방계획서</a><span><?=h($name)?></span><b><?=$year?>년</b></header>
<?php if($error):?><div class="fpsc-error" role="alert"><?=h($error)?> <a href="<?=h($link('/fire_plan_schedule.php',['id'=>$id]))?>">새로고침</a></div><?php endif;?>
<?php if(isset($_GET['saved'])):?><div class="fpsc-success" role="status">✓ <?=$year?>년 계획서에 예정 일정을 반영했어요.</div><?php endif;?>
<?php if($recordEvent):$existing=fpsc_record($plan,$recordEvent);?>
<section class="fpsc-record"><span class="fpsc-eyebrow">예정 일정 → 실시기록</span><h1><?=h(fpsc_kinds()[$recordEvent['kind']])?> 기록하기</h1><p><?=h(fpsc_label($recordEvent))?> · <?=h($recordEvent['place']?:'장소 미정')?></p>
<?php if($existing):?><p>이 일정으로 시작한 기록이 있어요. 이어서 작성할 수 있습니다.</p><?php else:?><p>실제로 진행한 날짜를 확인해 주세요.<br>예정한 장소·내용을 가져오고 참석 인원과 결과는 직접 확인합니다.</p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(fp_csrf())?>"><input type="hidden" name="revision" value="<?=h(fpo_hash($plan))?>"><input type="hidden" name="act" value="start"><input type="hidden" name="event" value="<?=h($recordEvent['id'])?>">
<?php if(!$existing):?><label>실제 실시일<input type="date" name="actual" min="<?=$year?>-01-01" max="<?=h(min($year.'-12-31',date('Y-m-d')))?>" value="<?=h($recordEvent['precision']==='date'&&$recordEvent['date']<=date('Y-m-d')?$recordEvent['date']:'')?>" required></label><?php endif;?>
<button class="fpsc-primary" <?=$year>(int)date('Y')&&!$existing?'disabled':''?>><?=$existing?'작성 중인 기록 이어서 열기':'실시기록 작성 시작 →'?></button>
<?php if($year>(int)date('Y')&&!$existing):?><p>실시기록은 <?=$year?>년 교육·훈련을 진행한 뒤 작성해 주세요.</p><?php endif;?>
<a class="fpsc-text" href="<?=h($link('/fire_plan_schedule.php',['id'=>$id]))?>">일정으로 돌아가기</a></form></section>
<?php else:?>
<div class="fpsc-heading"><span class="fpsc-eyebrow">ANNUAL TRAINING PLAN</span><h1><?=$year?>년 교육·훈련은 언제 할까요?</h1><p>우선 월만 정해도 괜찮아요. 정확한 날짜는 나중에 바꿀 수 있어요.</p></div>
<form method="post" id="fpsc-form"><input type="hidden" name="csrf" value="<?=h(fp_csrf())?>"><input type="hidden" name="revision" value="<?=h($revision)?>"><input type="hidden" name="act" value="save"><input type="hidden" name="events" id="fpsc-events">
<div class="fpsc-layout"><section class="fpsc-editor"><nav class="fpsc-tabs" aria-label="교육·훈련 종류"><?php foreach(fpsc_kinds() as $kind=>$title):?><button type="button" data-kind="<?=h($kind)?>"><span></span><?=h($title)?></button><?php endforeach;?></nav><div id="fpsc-stage"></div><div class="fpsc-footer"><div><strong id="fpsc-progress"></strong><small>날짜·대상·내용을 확인한 뒤 저장해 주세요.</small></div><div class="fpsc-footer-actions"><button type="button" class="fpsc-secondary" id="fpsc-next">다음 일정 →</button><button class="fpsc-primary" type="submit" id="fpsc-save">계획서에 일정 반영</button></div></div><p class="fpsc-error" id="fpsc-client-error" role="alert" hidden></p>
</section>
<aside class="fpsc-summary"><div class="fpsc-summary-head"><span class="fpsc-calendar" aria-hidden="true">▦</span><div><small><?=$year?> ANNUAL PLAN</small><h2>우리 건물의 연간 일정</h2></div></div><div id="fpsc-preview" aria-live="polite"></div><div class="fpsc-note"><strong>계획서에 함께 반영돼요</strong><p>소방훈련·교육 계획과 인쇄본에 표시됩니다. 실제 실시 여부는 기록부에서 따로 관리해요.</p></div></aside></div>
</form>
<?php if(fpsc_events($plan)):?><section class="fpsc-saved-records"><h2>실시한 뒤 기록으로 남기기</h2><p>예정일을 저장해도 교육·훈련을 실시한 것으로 처리되지 않습니다.</p><?php foreach(fpsc_events($plan) as $e):$r=fpsc_record($plan,$e);?><a href="<?=h($link('/fire_plan_schedule.php',['id'=>$id,'record'=>$e['id']]))?>"><span><b><?=h(fpsc_kinds()[$e['kind']])?></b><small><?=h(fpsc_label($e))?></small></span><span><?=$r?'기록 이어서 보기':($year>(int)date('Y')?'실시 후 기록 안내':'실시기록 작성')?> →</span></a><?php endforeach;?></section><?php endif;?>
<a class="fpsc-return" href="<?=h($link('/fire_plan.php',['id'=>$id]))?>">소방계획서로 돌아가기 →</a>
<script type="application/json" id="fpsc-boot"><?=json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<?php endif;?></main></body></html>
