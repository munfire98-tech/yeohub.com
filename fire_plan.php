<?php
declare(strict_types=1);
/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';

date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE){
ini_set('session.cookie_httponly','1');
if(PHP_VERSION_ID>=70300)session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
}

function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_admin():bool{return !empty($_SESSION['is_admin'])||(!empty($_SESSION['ID_OK'])&&$_SESSION['ID_OK']==1);}
if(!is_admin()&&empty($_SESSION['is_user'])){header('Location: /index.php');exit;}
if(!is_admin()&&($_SESSION['role']??'')!=='building'){header('Location: /clients_mini.php');exit;}
require_once __DIR__.'/fire_plan_db.php';
if(fp_user_key()===''){http_response_code(403);exit('다시 로그인해 주세요.');}
$context=[];
if(($_GET['embed']??'')==='1')$context['embed']='1';
if(is_admin()&&isset($_GET['uid'])&&is_string($_GET['uid']))$context['uid']=$_GET['uid'];
$link=function(string $path,array $args=[])use($context):string{$q=array_merge($context,$args);return $path.($q?'?'.http_build_query($q):'');};
$deleteError='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['act']??'')==='delete'){
  fp_csrf_check();
  try{fp_delete_plan((string)($_POST['id']??''));header('Location: '.$link('/fire_plan.php',['deleted'=>'1']));exit;}
  catch(RuntimeException $e){$deleteError=$e->getMessage();}
}
require_once __DIR__.'/manager_fire_plan_help.php';
$isHelpManager=!empty($_SESSION['_mge_actor']);
$thisYear=(int)date('Y');
$focusYear=filter_var($_GET['year']??$thisYear,FILTER_VALIDATE_INT,['options'=>['min_range'=>1901,'max_range'=>2199]]);
if($focusYear===false)$focusYear=$thisYear;
$plans=[];$byYear=[];
foreach(fp_list_plans() as $row){
  $full=fp_load_plan((string)($row['id']??''));if(!$full)continue;
  $full['plan_year']=fp_plan_year($full);$plans[]=$full;$byYear[$full['plan_year']][]=$full;
}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>연도별 소방계획서</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f8fa;color:#2d3848;font:14px/1.6 system-ui,-apple-system,"Apple SD Gothic Neo",sans-serif}a{color:inherit;text-decoration:none}button,input{font:inherit}.plan-page{max-width:1000px;margin:auto;padding:30px 24px 50px}.plan-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:28px}h1{font-size:23px;margin:0 0 6px;letter-spacing:-.04em}.muted{font-size:12px;color:#7b8593}.year-picker{display:flex;align-items:center;gap:6px}.year-picker input{width:84px;padding:7px;border:1px solid #e0e5ec;border-radius:7px;background:#fff}.btn{display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border:1px solid #e0e5ec;border-radius:7px;background:#fff;font-size:12px;color:#596578;cursor:pointer}.years{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.year-card{display:flex;flex-direction:column;gap:20px;min-height:175px;padding:22px;border:1px solid #e1e6ed;border-radius:12px;background:#fff}.year-card:hover{border-color:#9daec8;background:#fcfdff}.year-card.current{border-color:#b9c7dc}.year-label{font-size:11px;color:#8a94a2}.year-title{font-size:24px;font-weight:650;letter-spacing:-.04em}.year-title small{font-size:12px;font-weight:400;margin-left:6px;color:#7a8596}.year-foot{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12px;color:#5e718f}.intro-note{margin:16px 2px 30px;color:#7b8593;font-size:12px}.records{background:#fff;border:1px solid #e1e6ed;border-radius:12px;padding:20px}.records h2{font-size:15px;margin:0 0 8px}.record{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 0;border-top:1px solid #edf0f4}.record-main{min-width:0}.record-main b{font-size:13px}.record-actions{display:flex;gap:6px;flex-wrap:wrap}.record-actions form{margin:0}.empty{padding:25px 0;color:#7b8593;font-size:13px}.status{font-size:10px;margin-left:6px;padding:3px 6px;border-radius:4px;background:#f2f5f9;color:#6b7b93}.help{margin-top:22px;font-size:12px;color:#758192}.help summary{cursor:pointer}.help p{line-height:1.8}a:focus-visible,button:focus-visible,input:focus-visible{outline:2px solid #7e96ba;outline-offset:3px}@media(max-width:640px){.plan-page{padding:22px 16px}.plan-head{align-items:flex-start;flex-direction:column}.years{grid-template-columns:1fr}.year-card{min-height:0;gap:12px;padding:18px}.record{align-items:flex-start;flex-direction:column}.record-actions{width:100%}}
.request-open{background:#fff4df;border-color:#edbc68;color:#975c10;font-weight:750}</style></head><body><main class="plan-page">
<?php if($deleteError!==''): ?><p role="alert" style="padding:12px;border-radius:10px;background:#fff0eb;color:#963c29"><?=h($deleteError)?></p><?php endif;?>
<header class="plan-head"><div><h1>어느 해의 소방계획서를 작성할까요?</h1><div class="muted">연도를 선택하고, 기존 자료를 골라 대화로 작성하세요.</div></div>
<form class="year-picker" method="get">
<?php foreach($context as $k=>$v):?><input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>"><?php endforeach;?>
<input aria-label="계획연도" type="number" name="year" min="1901" max="2199" value="<?=$focusYear?>"><button class="btn">연도 찾기</button></form></header>
<?php require_once __DIR__.'/building_facilities_common.php';bf_render_reference(); ?>
<section class="years" aria-label="작성할 계획연도">
<?php foreach([$focusYear-1,$focusYear,$focusYear+1] as $year):
  $items=$byYear[$year]??[];$latest=$items[0]??null;
?>
<a class="year-card <?=$year===$thisYear?'current':''?>" href="<?=h($link('/fire_plan_chat.php',['year'=>$year]))?>">
<div><div class="year-label"><?=$year===$thisYear?'올해 계획':($year<$thisYear?'지난해 계획':'다가올 계획')?></div><div class="year-title"><?=$year?>년<small>소방계획서</small></div></div>
<div class="year-foot"><span><?=$latest?(($latest['status']??'')==='done'?'작성 완료 · 확인하기':'작성 중 · 이어쓰기'):'새로 작성하기'?></span><span aria-hidden="true">→</span></div>
</a>
<?php endforeach;?>
</section>
<p class="intro-note">기존 계획서가 있으면 이어서 열립니다. 선택한 연도와 실제 작성일은 별도로 보관합니다.</p>
<section class="records"><h2>작성한 계획서</h2>
<?php if(!$plans):?><div class="empty">아직 작성한 계획서가 없어요. 위에서 연도를 선택해 시작해 보세요.</div>
<?php else:foreach($plans as $p): $requests=$isHelpManager?mfp_pending(fp_user_key(),(string)$p['id']):[]; ?>
<div class="record"><div class="record-main"><b><?= (int)$p['plan_year'] ?>년 소방계획서</b><span class="status"><?=($p['status']??'')==='done'?'작성 완료':'작성 중'?></span><div class="muted"><?=h((string)($p['building_name']?:'건물정보 입력 전'))?> · 수정 <?=h(substr((string)($p['updated_at']??''),0,16))?></div></div>
<div class="record-actions">
<?php if($requests && preg_match('/^__fp_[0-9]+_([0-9]+)_([A-Za-z0-9_]+)$/',$requests[0]['field'],$requestMatch)): ?>
<a class="btn request-open" href="<?=h($link('/fire_plan_chat.php',['id'=>$p['id'],'help_code'=>$requestMatch[1],'help_key'=>$requestMatch[2]]))?>">요청 <?=count($requests)?>건 확인 →</a>
<?php endif;?>
<a class="btn" href="<?=h($link('/fire_plan_chat.php',['id'=>$p['id'],'year'=>$p['plan_year']]))?>">대화로 작성</a>
<a class="btn" href="<?=h($link('/fire_plan_edit.php',['id'=>$p['id']]))?>">서식 확인</a>
<a class="btn" target="_blank" rel="noopener" href="<?=h($link('/fire_plan_print.php',['id'=>$p['id']]))?>">인쇄</a>
<form method="post" onsubmit="return confirm('이 계획서와 연결된 작성 도움 요청·완료 내역을 함께 삭제할까요? 되돌릴 수 없습니다.')"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=h((string)$p['id'])?>"><input type="hidden" name="csrf" value="<?=h(fp_csrf())?>"><button class="btn">삭제</button></form>
</div></div>
<?php endforeach;endif;?></section>
<details class="help"><summary>어떤 자료를 활용할 수 있나요?</summary><p>기본정보 · 자위소방대 편성 · 매월 기록 · 자위소방대 교육 · 소방훈련·교육 · 피난계획 중 필요한 자료를 고를 수 있습니다. 자료를 불러오지 않고 직접 답변해도 됩니다.</p></details>
</main>
<script>
/* 문답만 최상위 문서의 모달로 엽니다. 기존 인페이지 작업 공간은 그대로 둡니다. */
document.addEventListener('click',function(event){
  // 공통 업무 팝업 안에서는 별도 문답 팝업을 중첩해서 만들지 않습니다.
  if(window.parent!==window && new URLSearchParams(location.search).get('modal')==='1') return;
  const link=event.target.closest('a[href]');
  if(!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button!==0)return;
  const url=new URL(link.href,location.href);
  if(url.origin!==location.origin || url.pathname!=='/fire_plan_chat.php')return;
  let host;
  try{host=window.top;if(host.location.origin!==location.origin)return;}catch(e){return;}
  event.preventDefault();
  if(host.document.getElementById('firePlanQuestionDialog'))return;
  const doc=host.document, dialog=doc.createElement('dialog'), frame=doc.createElement('iframe');
  const previous=doc.activeElement, oldOverflow=doc.documentElement.style.overflow;
  const style=doc.createElement('style');
  style.textContent='#firePlanQuestionDialog{padding:0;border:1px solid #e1e6ed;border-radius:16px;width:min(760px,calc(100vw - 32px));max-width:none;height:min(820px,calc(100dvh - 48px));max-height:none;background:#fff;box-shadow:0 24px 80px #17243b30;overflow:hidden}#firePlanQuestionDialog::backdrop{background:rgba(24,34,50,.38)}#firePlanQuestionDialog .fp-modal-head{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:1px solid #edf0f4;font:600 14px system-ui;color:#344054}#firePlanQuestionDialog button{border:1px solid #e0e5ec;border-radius:7px;background:white;padding:6px 10px;font:12px system-ui;color:#64748b;cursor:pointer}#firePlanQuestionDialog button:focus-visible{outline:2px solid #667da5;outline-offset:2px}#firePlanQuestionDialog iframe{display:block;width:100%;height:calc(100% - 64px);border:0;background:#fff}@media(max-width:540px){#firePlanQuestionDialog{width:calc(100vw - 16px);height:calc(100dvh - 24px);border-radius:12px}}';
  dialog.id='firePlanQuestionDialog';dialog.setAttribute('aria-label','소방계획서 문답 작성');
  // 상위 페이지의 dialog·전역 margin 초기화와 관계없이 뷰포트 중앙에 배치합니다.
  style.textContent += '#firePlanQuestionDialog{position:fixed!important;inset:50% auto auto 50%!important;margin:0!important;transform:translate(-50%,-50%)!important;box-sizing:border-box}';
  const header=doc.createElement('div');header.className='fp-modal-head';
  const title=doc.createElement('span');title.textContent=url.searchParams.has('year')?url.searchParams.get('year')+'년 소방계획서 작성':'소방계획서 작성';
  const close=doc.createElement('button');close.type='button';close.textContent='닫기 ×';close.setAttribute('aria-label','문답 팝업 닫기');
  header.append(title,close);frame.title='소방계획서 질문과 답변';
  const notice=doc.createElement('div');notice.hidden=true;notice.className='fp-close-notice';notice.setAttribute('role','status');
  style.textContent+='#firePlanQuestionDialog[open]{display:flex;flex-direction:column}#firePlanQuestionDialog .fp-modal-head{flex-shrink:0}#firePlanQuestionDialog iframe{flex:1;min-height:0;height:auto}#firePlanQuestionDialog .fp-close-notice{padding:14px 20px;border-bottom:1px solid #e5e9f0;background:#f8fafc;color:#475569;font:13px/1.6 system-ui}#firePlanQuestionDialog .fp-close-notice[hidden]{display:none}#firePlanQuestionDialog .fp-close-options{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}#firePlanQuestionDialog .fp-close-options .fp-save-close{background:#405c86;color:white;border-color:#405c86}';
  const context=new URLSearchParams(location.search);
  if(context.has('uid')&&!url.searchParams.has('uid'))url.searchParams.set('uid',context.get('uid'));
  url.searchParams.set('embed','1');url.searchParams.set('modal','1');frame.src=url.href;
  let loaded=false;
  const finish=()=>{dialog.close();};
  let closing=false;
  const showCloseChoice=(child,canSave)=>{
    notice.replaceChildren();notice.hidden=false;
    const message=doc.createElement('div');message.textContent='아직 저장하지 않은 답변이 있어요. 어떻게 할까요?';notice.append(message);
    const options=doc.createElement('div');options.className='fp-close-options';notice.append(options);
    const add=(label,action)=>{const b=doc.createElement('button');b.type='button';b.textContent=label;b.onclick=action;options.append(b);return b;};
    if(canSave){
      const save=add('저장하고 닫기',async()=>{
        if(closing)return;closing=true;options.querySelectorAll('button').forEach(b=>b.disabled=true);
        try{if(await child.fpModalSave()){finish();return;}message.textContent='저장하지 못했어요. 답변이나 오류 안내를 확인해 주세요.';}
        catch(e){message.textContent='저장 상태를 확인하지 못했어요. 계속 작성으로 돌아가 확인해 주세요.';}
        finally{closing=false;options.querySelectorAll('button').forEach(b=>b.disabled=false);}
      });save.className='fp-save-close';
    }
    add('계속 작성',()=>{notice.hidden=true;frame.focus();});
    add('저장 없이 닫기',()=>{if(child && typeof child.fpModalDiscard==='function')child.fpModalDiscard();finish();});
    options.querySelector('button').focus();
  };
  const requestClose=()=>{
    if(closing)return;
    if(!loaded){finish();return;}
    try{
      const child=frame.contentWindow;
      if(child.location.origin===location.origin && typeof child.fpModalState==='function'){
        const state=child.fpModalState();
        if(state.busy){notice.hidden=false;notice.textContent='저장 중이에요. 잠시 후 다시 닫아주세요.';return;}
        if(!state.dirty){finish();return;}
        showCloseChoice(child,typeof child.fpModalSave==='function');return;
      }
    }catch(e){}
    // 구버전·오류 화면도 무응답으로 갇히지 않게, 명시적인 닫기 선택을 제공합니다.
    showCloseChoice(null,false);
  };
  const receive=event=>{
    if(event.origin!==location.origin || event.source!==frame.contentWindow)return;
    if(event.data && event.data.type==='fp-modal-year' && Number.isInteger(event.data.year) && event.data.year>=1900 && event.data.year<=2200){title.textContent=event.data.year+'년 소방계획서 작성';return;}
    if(event.data && event.data.type==='fp-modal-request-close'){requestClose();return;}
    if(event.data && event.data.type==='fp-modal-close-ready')finish();
  };
  host.addEventListener('message',receive);
  frame.addEventListener('load',()=>{loaded=true;});
  close.addEventListener('click',requestClose);
  dialog.addEventListener('cancel',e=>{e.preventDefault();requestClose();});
  dialog.addEventListener('close',()=>{
    host.removeEventListener('message',receive);dialog.remove();style.remove();doc.documentElement.style.overflow=oldOverflow;
    if(previous && previous.isConnected)previous.focus();
    window.location.reload();
  },{once:true});
  dialog.append(header,notice,frame);doc.head.append(style);doc.body.append(dialog);
  doc.documentElement.style.overflow='hidden';dialog.showModal();close.focus();
});
</script>


<?php if(is_file(__DIR__.'/admin_quickmemo_widget.php')) require_once __DIR__.'/admin_quickmemo_widget.php'; ?>
<?php if(($_GET['deleted']??'')==='1'): ?><script>
try{if('BroadcastChannel' in window){const channel=new BroadcastChannel('manager-plan-changes');channel.postMessage('plan-deleted');channel.close();}window.managerHelp?.refresh();if(window.parent!==window)window.parent.managerHelp?.refresh();if(window.top!==window.parent)window.top.managerHelp?.refresh();}catch(e){}
</script><?php endif;?>
</body></html>
