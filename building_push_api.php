<?php
declare(strict_types=1);
if(!defined('BUILDING_PUSH_API_AUTHORIZED')){http_response_code(404);exit;}
require_once __DIR__.'/building_push_common.php';
$notificationPro=pc_active($actor);
if(!$notificationPro&&(($method==='GET'&&!isset($_GET['inbox'])&&!isset($_GET['message']))||($method==='POST'&&in_array((string)($input['act']??''),['subscribe','preferences','test'],true))))throw new RuntimeException('업무알림은 PRO 구독 후 이용할 수 있습니다.',403);
if($method==='GET'){
 if(isset($_GET['message'])){$id=(string)$_GET['message'];$m=$state['messages'][$id]??null;if(!$m||!$current||($m['device']??'')!==$device||($m['subscription_id']??'')!==$current['id']||($m['created']??0)<time()-3600||($m['kind']??'')!=='building'||!empty($state['inbox_deleted'][vi_key($m)]))throw new RuntimeException('현재 계정·기기의 알림이 아닙니다.',403);$content=vp_resolve($m,$actor,$members,true);if(!$content)throw new RuntimeException('업무가 완료되었거나 알림 접근 권한이 없습니다.',403);$reply(['notification'=>$content,'tag'=>$m['dedup']]);exit;}
 if(isset($_GET['inbox'])){$items=vi_list($state,$actor,$scope,[],$members,[]);$items=array_values(array_filter($items,static fn($r)=>$r['kind']==='building'));$unread=count(array_filter($items,static fn($r)=>!$r['read']));$reply(['csrf'=>$csrf,'items'=>array_slice($items,0,50),'unread'=>$unread,'total'=>count($items)]);exit;}
 if(array_intersect(array_keys($_GET),['tasks','event','digest','inbox_item']))throw new RuntimeException('이 계정에서 지원하지 않는 업무입니다.',403);
 $c=vp_config();$reply(['csrf'=>$csrf,'publicKey'=>$c['publicKey']??'','ready'=>!empty($c['publicKey']),'enabled'=>$current!==null,'preferences'=>vp_preferences($current['prefs']??[]),'times'=>vd_times($state['digest_times'][$scope]??[]),'timing'=>[],'device_count'=>count(array_filter($state['subscriptions']??[],static fn($r)=>$r['scope']===$scope)),'devices'=>vp_device_list($state,$scope,$device)]);exit;
}
$act=(string)($input['act']??'');
if(in_array($act,['inbox_read','inbox_delete','inbox_read_all'],true)){vp_tx(static function(array &$s)use($act,$input,$actor,$scope,$members){if($act==='inbox_read_all'){foreach(vi_list($s,$actor,$scope,[],$members,[]) as $r)$s['inbox_read'][$r['key']]=time();}elseif($act==='inbox_delete')vi_delete($s,(string)($input['id']??''),$actor,$scope,[],$members,[]);else vi_read($s,(string)($input['id']??''),$actor,$scope,[],$members,[]);});$reply([]);exit;}
 if($act==='subscribe'){
  if(empty(vp_config()['publicKey']))throw new RuntimeException('서버의 푸시 발송키를 먼저 설정해 주세요.',503);
  $label=vp_device_label((string)($_SERVER['HTTP_USER_AGENT']??''),($input['standalone']??false)===true);
  $row=vp_tx(static function(array &$s)use($actor,$scope,$device,$input,$label){if(isset($input['times']))vd_save_times($s,$scope,(array)$input['times'],time());$s['building_since'][$scope]??=time();return vp_subscribe($s,$actor,$scope,$device,(array)($input['subscription']??[]),(array)($input['preferences']??[]),time(),$label);});$reply(['enabled'=>true]);exit;
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
 $id=bin2hex(random_bytes(24));vp_tx(static function(array &$s)use($id,$current,$actor,$scope,$device){if(($s['test_at'][$device]??0)>time()-60)throw new RuntimeException('테스트 알림은 1분 뒤 다시 보낼 수 있습니다.',429);if(($s['subscriptions'][$current['id']]['scope']??'')!==$scope)throw new RuntimeException('기기 연결이 변경되었습니다.',409);$s['test_at'][$device]=time();$s['messages'][$id]=['id'=>$id,'actor'=>$actor,'scope'=>$scope,'device'=>$device,'subscription_id'=>$current['id'],'kind'=>'building','building_test'=>true,'periods'=>[],'event'=>'building_test_'.$id,'due'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d'),'created'=>time(),'dedup'=>'building_test_'.$id,'status'=>'queued'];});$result=vp_send($current['id'],$id);if(!$result['ok'])throw new RuntimeException('푸시 발송을 완료하지 못했습니다. 기기 연결을 확인해 주세요.',502);$reply(['accepted'=>true]);exit;
}
throw new RuntimeException('지원하지 않는 작업입니다.',400);
