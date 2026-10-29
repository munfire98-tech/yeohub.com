<?php
/** Run once per minute. Visit reminders start at 08:15 KST; account events are sent throughout the day. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/visit_push_inbox_common.php';require_once __DIR__.'/building_push_common.php';date_default_timezone_set('Asia/Seoul');
if(!is_dir(vp_dir()))mkdir(vp_dir(),0700,true);
$connectionsOnly=in_array('--connections-only',$argv??[],true);
$lock=fopen(vp_dir().($connectionsOnly?'/events.lock':'/runner.lock'),'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
$wakeToken=$connectionsOnly?(@file_get_contents(vp_dir().'/fast_wake.php')?:''):'';
$stats=['at'=>date('c'),'sent'=>0,'failed'=>0,'skipped'=>0];
try{
 if(!is_file(vp_dir().'/config.php'))throw new RuntimeException('Run visit_push_setup.php first');
 vp_tx(static function(array &$s){$s['connection_push_since']??=time();$s['request_push_since']??=time();$s['digest_since']??=time();});
 $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul'));$members=mg_members();$system=mg_read(mg_state_file());$snapshot=mg_read(vp_file());$stores=[];$connectionEvents=[];
 foreach($snapshot['subscriptions']??[] as $id=>$sub){
  $actor=$sub['actor'];
  if((!mg_active($members[$actor]??[],'agency')&&!bp_active($actor,$members))||$sub['scope']!==vp_scope($actor,$members)||$sub['updated']<time()-86400*(bp_active($actor,$members)?90:30)){vp_tx(static function(array &$s)use($id){$r=$s['subscriptions'][$id]??null;if($r)vp_pause_device($s,$r['device']);});continue;}
  if(bp_active($actor,$members)){
   if(!pc_active($actor))continue; // Keep device preferences, pause paid work reminders until renewal.
   if(!$connectionsOnly){try{$periods=bp_periods($now);$items=bp_items($actor,$periods);$message=vp_tx(static function(array &$s)use($id,$periods,$now,$items){$current=$s['subscriptions'][$id]??null;return $current?bp_queue($s,$current,$periods,$now,$items):null;});if($message){$result=vp_send($id,$message);if($result['ok'])$stats['sent']++;elseif(in_array($result['reason']??'',['detached','stale','disabled'],true))$stats['skipped']++;else $stats['failed']++;}}catch(Throwable $e){$stats['failed']++;error_log('Building reminder failed');}}
   continue;
  }
  if(!isset($connectionEvents[$actor])){ms_sync_notifications($actor,$members);$system=mg_read(mg_state_file());$connectionEvents[$actor]=array_values(array_filter($system['connection_notifications']??[],static fn($n)=>is_array($n)&&($n['manager']??'')===$actor));}
  foreach($connectionEvents[$actor] as $n){
   $message=vp_tx(static function(array &$s)use($id,$n,$members,$system){$sub=$s['subscriptions'][$id]??null;return $sub?vp_queue_connection($s,$sub,$n,$members,time(),$system):null;});
   if(!$message)continue;$result=vp_send($id,$message);if($result['ok'])$stats['sent']++;elseif(in_array($result['reason']??'',['detached','stale','disabled'],true))$stats['skipped']++;else $stats['failed']++;
  }
  if($connectionsOnly||vd_slot($now,max((int)($snapshot['digest_since']??PHP_INT_MAX),(int)($snapshot['digest_times'][$sub['scope']]['updated']??0)),$snapshot['digest_times'][$sub['scope']]??[])===null)continue;
  if(!isset($stores[$actor])){
   $file=vp_store($actor,$members);$s=mg_read($file);$calendar=rh_sync(rc_months($s));$stores[$actor]=mg_tx($file,static function(array &$s)use($calendar){rc_refresh($s,$calendar);return $s;},true);
  }
  $subscriptions=array_values($snapshot['subscriptions']??[]);
  $message=vp_tx(static function(array &$s)use($id,$actor,$members,$system,$stores,$subscriptions,$now){
   $current=$s['subscriptions'][$id]??null;if(!$current)return null;
   $record=vd_prepare($s,$actor,$current['scope'],$subscriptions,$stores[$actor],$members,$system,$now);
   return $record?vd_queue($s,$current,$record,$stores[$actor],$members,$system,$now->getTimestamp()):null;
  });
  if($message){$result=vp_send($id,$message);if($result['ok'])$stats['sent']++;elseif(in_array($result['reason']??'',['detached','stale','disabled'],true))$stats['skipped']++;else $stats['failed']++;}
 }
 vp_tx(static function(array &$s){foreach($s['messages']??[] as $id=>$m)if(($m['created']??0)<time()-86400*90)unset($s['messages'][$id]);foreach($s['inbox_read']??[] as $key=>$at)if($at<time()-86400*90)unset($s['inbox_read'][$key]);foreach($s['inbox_deleted']??[] as $key=>$at)if($at<time()-86400*90)unset($s['inbox_deleted'][$key]);foreach($s['digests']??[] as $key=>$r)if(($r['created']??0)<time()-86400*90)unset($s['digests'][$key]);foreach($s['test_at']??[] as $device=>$at)if($at<time()-86400)unset($s['test_at'][$device]);});
}catch(Throwable $e){$stats['error']='runner_failed';error_log('Visit push runner failed: '.$e->getMessage());}
finally{mg_tx(vp_dir().($connectionsOnly?'/event_runner_status.php':'/runner_status.php'),static function(array &$s)use($stats){$s=$stats;},true);flock($lock,LOCK_UN);fclose($lock);if($connectionsOnly&&$wakeToken!==(@file_get_contents(vp_dir().'/fast_wake.php')?:'')&&function_exists('exec')){$php=PHP_BINDIR.'/php';if(is_executable($php))exec(escapeshellarg($php).' '.escapeshellarg(__FILE__).' --connections-only > /dev/null 2>&1 &');}}
echo json_encode($stats,JSON_UNESCAPED_UNICODE).PHP_EOL;
