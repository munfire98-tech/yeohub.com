<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/withdraw_manager.php';
require_once __DIR__.'/manager_subscription.php';
require_once __DIR__.'/visit_push_inbox_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try{
 $actor=mg_uid();$members=mg_members();$me=$members[$actor]??[];
 if($actor===''||!mg_active($me,'agency')){http_response_code(403);echo '{"ok":false}';exit;}
 $owns=static fn(array $n):bool=>($n['manager']??'')===$actor&&($n['manager_created']??'')===(string)($me['created']??'');
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='POST'){
  if(!is_string($_POST['csrf']??null)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);echo '{"ok":false}';exit;}
  $id=is_string($_POST['id']??null)?$_POST['id']:'';
  $found=mg_state_tx(function(array &$state)use($id,$owns){$n=$state['connection_notifications'][$id]??null;if(!is_array($n)||!$owns($n))return false;if(empty($n['read_at']))$state['connection_notifications'][$id]['read_at']=date('c');return true;});
  if(!$found){http_response_code(404);echo '{"ok":false}';exit;}
 }elseif($method!=='GET'){http_response_code(405);header('Allow: GET, POST');echo '{"ok":false}';exit;}
 ms_sync_notifications($actor,$members);
 $push=mg_read(vp_file());$scope=vp_scope($actor,$members);
 $state=mg_read(mg_state_file());$rows=[];$unread=0;
 foreach($state['connection_notifications']??[] as $n){if(!is_array($n)||!$owns($n)||!wm_visible($n,$members)||!empty($n['read_at']))continue;$shared=vi_connection($n,$actor,$scope,$members);if(!$shared||!empty($push['inbox_read'][$shared['key']])||!empty($push['inbox_deleted'][$shared['key']]))continue;$read=false;$unread++;$rows[]=['id'=>$n['id'],'name'=>$n['name'],'kind'=>$n['kind'],'at'=>$n['at'],'read'=>$read,'test'=>!empty($n['test']),'started_at'=>$n['started_at']??'','period_started_at'=>$n['period_started_at']??'','expires_at'=>$n['expires_at']??''];}
 usort($rows,static fn($a,$b)=>($a['read']<=>$b['read'])?:strcmp($b['at'],$a['at']));
 echo json_encode(['ok'=>true,'unread'=>$unread,'notifications'=>$rows],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false,"error":"알림을 불러오지 못했습니다."}';}
