<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/visit_push_inbox_common.php';
require_once __DIR__.'/work_tasks_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')throw new RuntimeException('같은 사이트에서 요청해 주세요.',403);
 $members=mg_members();$actor=vp_actor($members);if($actor==='')throw new RuntimeException('알림을 받을 계정으로 로그인해 주세요.',401);
 vp_observe();$device=vp_device(true);$scope=vp_scope($actor,$members);
 $_SESSION['visit_push_csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['visit_push_csrf'];
 $method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true))throw new RuntimeException('지원하지 않는 요청입니다.',405);
 $input=$method==='POST'?json_decode((string)file_get_contents('php://input'),true):[];
 if($method==='POST'&&(!is_array($input)||!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf'])))throw new RuntimeException('요청이 만료되었습니다.',403);
 session_write_close();$state=mg_read(vp_file());$current=null;foreach($state['subscriptions']??[] as $r)if($r['device']===$device&&$r['scope']===$scope)$current=$r;
 $reply=static function(array $data){echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);};
 if(mg_active($members[$actor]??[],'building')){define('BUILDING_PUSH_API_AUTHORIZED',true);require __DIR__.'/building_push_api.php';exit;}
 if($method==='GET'){
  if(isset($_GET['inbox_item'])){
   $id=is_string($_GET['inbox_item'])?$_GET['inbox_item']:'';
   $item=vi_item($id,$state,$actor,$scope,mg_read(vp_store($actor,$members)),$members,mg_read(mg_state_file()));
   if(!$item||!empty($state['inbox_deleted'][$item['key']]))throw new RuntimeException('삭제되었거나 조회 권한이 없는 알림입니다.',403);
   $reply(['csrf'=>$csrf,'item'=>$item]);exit;
  }
  if(isset($_GET['tasks'])){$reply(['csrf'=>$csrf,'tasks'=>wt_list(mg_read(vp_store($actor,$members)),$actor,$members,mg_read(mg_state_file())),'clients'=>wt_clients($actor,$members,mg_read(mg_state_file()))]);exit;}
  if(isset($_GET['digest'])){
   $slot=is_string($_GET['digest'])?$_GET['digest']:'';$record=$state['digests'][vd_key($scope,$slot)]??null;$system=mg_read(mg_state_file());
   if(!$record||!vd_visible($record,$actor,$scope,$members,$system))throw new RuntimeException('이 업무 요약을 조회할 권한이 없습니다.',403);
   $rows=vd_rows($record,mg_read(vp_store($actor,$members)),$actor,$members,$system);$reply(['digest'=>vd_text($record,$rows)]);exit;
  }

  if(isset($_GET['inbox'])){
   // Reconcile billing/connection events at most once per account per minute.
   $sync=false;if(($state['inbox_sync_at'][$scope]??0)<=time()-60)$sync=vp_tx(static function(array &$s)use($scope){if(($s['inbox_sync_at'][$scope]??0)>time()-60)return false;$s['inbox_sync_at'][$scope]=time();return true;});
   if($sync)ms_sync_notifications($actor,$members);
   $state=mg_read(vp_file());
   $items=vi_list($state,$actor,$scope,mg_read(vp_store($actor,$members)),$members,mg_read(mg_state_file()));
   $group=vi_selected_group($_GET['group']??'all');$groups=vi_groups($items);$revision=vi_revision($items);
   $filtered=$group==='all'?$items:array_values(array_filter($items,static fn($r)=>vi_group($r)===$group));
   $offset=max(0,min(100000,(int)($_GET['offset']??0)));$count=$groups['all']['unread'];
   if(isset($_GET['counts'])){$reply(['csrf'=>$csrf,'total'=>count($filtered),'unread'=>$count,'groups'=>$groups,'revision'=>$revision]);exit;}
   $reply(['csrf'=>$csrf,'items'=>array_slice($filtered,$offset,50),'total'=>count($filtered),'unread'=>$count,'groups'=>$groups,'revision'=>$revision,'next'=>$offset+50<count($filtered)?$offset+50:null]);exit;
  }

  if(isset($_GET['message'])){
   $id=(string)$_GET['message'];$m=$state['messages'][$id]??null;
   if(!$m||!$current||$m['device']!==$device||$m['subscription_id']!==$current['id']||($m['created']??0)<time()-3600)throw new RuntimeException('만료되었거나 다른 계정의 알림입니다.',403);
   if(!empty($state['inbox_deleted'][vi_key($m)]))throw new RuntimeException('삭제한 알림입니다.',403);
   if(!in_array($m['kind'],['test','digest'],true)&&empty(vp_preferences($current['prefs']??[])[$m['kind']]))throw new RuntimeException('이 종류의 알림이 꺼져 있습니다.',403);
   $content=vp_resolve($m,$actor,$members,true);if(!$content)throw new RuntimeException('일정이 변경되었거나 접근 권한이 없습니다.',403);
   $reply(['notification'=>$content,'tag'=>$m['dedup']]);exit;
  }
  if(isset($_GET['event'])){
   $key=(string)$_GET['event'];
   if(preg_match('/^test_([a-f0-9]{48})$/D',$key,$match)){
    $item=vi_item($match[1],$state,$actor,$scope,[], $members,mg_read(mg_state_file()));
    if(!$item||($item['kind']??'')!=='test'||empty($item['test_schedule'])||!empty($state['inbox_deleted'][$item['key']]))throw new RuntimeException('테스트 일정이 삭제되었거나 조회 권한이 없습니다.',403);
    $reply(['test_event'=>$item]);exit;
   }
   $store=mg_read(vp_store($actor,$members));$e=vp_events($store)[$key]??null;
   if(!$e||!vp_allowed($actor,$e['uid'],$members,mg_read(mg_state_file())))throw new RuntimeException('일정을 조회할 수 없습니다.',403);
   $id=$e['visit_id'];$reply(['event'=>$e,'visit'=>$store['visits'][$id]??null,'report'=>$store['reports'][$id]??null,'followup'=>$store['followups'][$id]??null]);exit;
  }
  $config=vp_config();$reply(['csrf'=>$csrf,'publicKey'=>$config['publicKey']??'','ready'=>!empty($config['publicKey']),'enabled'=>$current!==null,'timing'=>vd_timing_status($state,$scope,new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul'))),'times'=>vd_times($state['digest_times'][$scope]??[]),'preferences'=>vp_preferences($current['prefs']??[]),'device_count'=>count(array_filter($state['subscriptions']??[],fn($r)=>$r['scope']===$scope)),'devices'=>vp_device_list($state,$scope,$device)]);exit;
 }
 $act=(string)($input['act']??'');
 if(in_array($act,['task_save','task_status','task_delete'],true)){
  $system=mg_read(mg_state_file());$row=mg_tx(vp_store($actor,$members),static fn(array &$s)=>wt_save($s,$input,$actor,$members,$system),true);$reply(['task'=>$row]);exit;
 }

 if(in_array($act,['inbox_read','inbox_read_all','inbox_delete'],true)){
  $group=vi_selected_group($input['group']??'all');
  $store=mg_read(vp_store($actor,$members));$system=mg_read(mg_state_file());
  vp_tx(static function(array &$s)use($input,$act,$actor,$scope,$store,$members,$system,$group){
   if($act==='inbox_delete')vi_delete($s,(string)($input['id']??''),$actor,$scope,$store,$members,$system);
   elseif($act==='inbox_read')vi_read($s,(string)($input['id']??''),$actor,$scope,$store,$members,$system);
   else foreach(vi_list($s,$actor,$scope,$store,$members,$system) as $r)if($group==='all'||vi_group($r)===$group)$s['inbox_read'][$r['key']]=time();
  });$reply([]);exit;
 }

 if($act==='subscribe'){
  if(empty(vp_config()['publicKey']))throw new RuntimeException('서버의 푸시 발송키를 먼저 설정해 주세요.',503);
  $label=vp_device_label((string)($_SERVER['HTTP_USER_AGENT']??''),($input['standalone']??false)===true);
  $row=vp_tx(static function(array &$s)use($actor,$scope,$device,$input,$label){if(isset($input['times']))vd_save_times($s,$scope,(array)$input['times'],time());return vp_subscribe($s,$actor,$scope,$device,(array)($input['subscription']??[]),(array)($input['preferences']??[]),time(),$label);});$reply(['enabled'=>true]);exit;
 }
 if($act==='disable'){vp_tx(static function(array &$s)use($scope,$device){vp_remove_device($s,$scope,$device);});$reply(['enabled'=>false]);exit;}
 if($act==='device_remove'){
  $id=is_string($input['id']??null)?$input['id']:'';if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new RuntimeException('기기를 확인해 주세요.',400);
  vp_tx(static function(array &$s)use($scope,$id){$target=null;foreach(['subscriptions','paused_subscriptions'] as $bucket)foreach($s[$bucket]??[] as $r)if(($r['scope']??'')===$scope&&($r['id']??'')===$id){$target=$r['device'];break 2;}if($target===null)throw new RuntimeException('해당 기기 연결을 확인할 수 없습니다.',403);vp_remove_device($s,$scope,$target,$id);});$reply([]);exit;
 }
 if($act==='device_label'){
  if($current){$label=vp_device_label((string)($_SERVER['HTTP_USER_AGENT']??''),($input['standalone']??false)===true);vp_tx(static function(array &$s)use($current,$scope,$device,$label){$id=$current['id'];if(($s['subscriptions'][$id]['scope']??'')===$scope&&($s['subscriptions'][$id]['device']??'')===$device)$s['subscriptions'][$id]['label']=$label;});}$reply([]);exit;
 }
 if($act==='preferences'){
  if(!$current)throw new RuntimeException('이 기기에서 알림 받기를 먼저 켜 주세요.',400);
  vp_tx(static function(array &$s)use($current,$input,$scope,$device){$id=$current['id'];if(($s['subscriptions'][$id]['scope']??'')!==$scope||($s['subscriptions'][$id]['device']??'')!==$device)throw new RuntimeException('기기 연결이 변경되었습니다.',409);if(isset($input['times']))vd_save_times($s,$scope,(array)$input['times'],time());$s['subscriptions'][$id]['prefs']=vp_preferences((array)($input['preferences']??[]));$s['subscriptions'][$id]['updated']=time();});$reply([]);exit;
 }
 if($act==='test'){
  if(!$current)throw new RuntimeException('알림 받기를 먼저 켜 주세요.',400);
  $id=bin2hex(random_bytes(24));vp_tx(static function(array &$s)use($id,$current,$actor,$scope,$device){
   if(($s['test_at'][$device]??0)>time()-60)throw new RuntimeException('테스트 알림은 1분 뒤 다시 보낼 수 있습니다.',429);
   if(($s['subscriptions'][$current['id']]['scope']??'')!==$scope)throw new RuntimeException('기기 연결이 변경되었습니다.',409);
   $s['test_at'][$device]=time();$s['messages'][$id]=['id'=>$id,'actor'=>$actor,'scope'=>$scope,'device'=>$device,'subscription_id'=>$current['id'],'kind'=>'test','test_schedule'=>['name'=>'[테스트] 소방계획서.com 빌딩','date'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d'),'time'=>'10:00'],'created'=>time(),'dedup'=>'test_'.$id,'status'=>'queued'];
  });$result=vp_send($current['id'],$id);if(!$result['ok'])throw new RuntimeException('푸시 서비스가 발송을 수락하지 않았습니다. 알림을 다시 연결하거나 서버 발송 기록을 확인해 주세요.',502);$reply(['accepted'=>true]);exit;
 }
 throw new RuntimeException('지원하지 않는 작업입니다.',400);
}catch(Throwable $e){$code=$e->getCode();http_response_code(in_array($code,[400,401,403,405,409,429,502,503],true)?$code:500);echo json_encode(['ok'=>false,'error'=>$code?$e->getMessage():'알림 설정을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}
