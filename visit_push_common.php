<?php
declare(strict_types=1);
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/manager_visit_store.php';
function vp_dir():string{return __DIR__.'/data/visit_push';}
function vp_file():string{return vp_dir().'/state.php';}
function vp_tx(callable $fn){return mg_tx(vp_file(),$fn,true);}
function vp_scope(string $uid,array $members):string{return hash('sha256',$uid.'|'.(string)($members[$uid]['created']??''));}
function vp_actor(array $members):string{
 $uid=mg_uid();return $uid!==''&&empty($_SESSION['_mge_actor'])&&empty($_SESSION['is_admin'])&&empty($_SESSION['ID_OK'])&&empty($_SESSION['demo'])&&session_name()!=='PARTNERDEMO'&&(mg_active($members[$uid]??[],'agency')||mg_active($members[$uid]??[],'building'))?$uid:'';
}
function vp_device(bool $create=false):string{
 $v=(string)($_COOKIE['visit_push_device']??'');
 if(!preg_match('/^[a-f0-9]{64}$/D',$v)){if(!$create)return '';$v=bin2hex(random_bytes(32));setcookie('visit_push_device',$v,['expires'=>time()+86400*365,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);$_COOKIE['visit_push_device']=$v;}
 return hash('sha256',$v);
}
/** Keep prior consent apart from active delivery; never deliver while signed out. */
function vp_pause_device(array &$s,string $device):void{
 foreach($s['subscriptions']??[] as $id=>$r)if($r['device']===$device){
  $r['paused_at']=time();$s['paused_subscriptions'][hash('sha256',$r['scope'].'|'.$id)]=$r;unset($s['subscriptions'][$id]);
 }
}
function vp_resume_device(array &$s,string $actor,string $scope,string $device,int $now):void{
 foreach($s['subscriptions']??[] as $r)if($r['device']===$device&&$r['scope']!==$scope){vp_pause_device($s,$device);break;}
 foreach($s['subscriptions']??[] as $id=>$r)if($r['device']===$device&&$r['scope']===$scope){
  if(($r['updated']??0)<$now-3600)$s['subscriptions'][$id]['updated']=$now;return;
 }
 $candidates=[];foreach($s['paused_subscriptions']??[] as $key=>$r)if(($r['actor']??'')===$actor&&($r['scope']??'')===$scope&&($r['device']??'')===$device)$candidates[$key]=$r;
 if(!$candidates)return;uasort($candidates,static fn($a,$b)=>($b['updated']??0)<=>($a['updated']??0));
 $key=array_key_first($candidates);$r=$candidates[$key];unset($r['paused_at']);$r['updated']=$now;$s['subscriptions'][$r['id']]=$r;unset($s['paused_subscriptions'][$key]);
}
function vp_remove_device(array &$s,string $scope,string $device,?string $id=null):void{
 $found=false;foreach(['subscriptions','paused_subscriptions'] as $bucket)foreach($s[$bucket]??[] as $key=>$r)if(($r['scope']??'')===$scope&&($r['device']??'')===$device&&($id===null||($r['id']??'')===$id)){unset($s[$bucket][$key]);$found=true;}
 if($id!==null&&!$found)throw new RuntimeException('해당 기기 연결을 확인할 수 없습니다.',403);
}
function vp_detach():void{
 $device=vp_device();if($device===''||!is_file(vp_file()))return;
 vp_tx(static function(array &$s)use($device){vp_pause_device($s,$device);});
}
function vp_observe():void{
 if(!is_file(vp_file())||vp_device()==='')return;
 $members=mg_members();$actor=vp_actor($members);$scope=$actor!==''?vp_scope($actor,$members):'';$device=vp_device();
 vp_tx(static function(array &$s)use($actor,$scope,$device){if($actor==='')vp_pause_device($s,$device);else vp_resume_device($s,$actor,$scope,$device,time());});
}
function vp_device_label(string $ua,bool $standalone):string{
 $os=preg_match('/iPhone|iPod/i',$ua)?'iPhone':(preg_match('/iPad/i',$ua)?'iPad':(stripos($ua,'Android')!==false?'Android':(stripos($ua,'Windows')!==false?'Windows':(stripos($ua,'Macintosh')!==false?'Mac':'기기'))));
 $browser=preg_match('/Edg\//',$ua)?'Edge':(preg_match('/SamsungBrowser/',$ua)?'삼성 인터넷':(preg_match('/Chrome|CriOS/',$ua)?'Chrome':(stripos($ua,'Firefox')!==false?'Firefox':(stripos($ua,'Safari')!==false?'Safari':'브라우저'))));
 return $os.' · '.($standalone?'홈 화면 앱':$browser);
}
function vp_device_list(array $s,string $scope,string $device):array{
 $list=[];foreach(['subscriptions'=>'active','paused_subscriptions'=>'paused'] as $bucket=>$status)foreach($s[$bucket]??[] as $r)if(($r['scope']??'')===$scope)$list[]=['id'=>$r['id'],'label'=>$r['label']??'기존 연결 기기','current'=>($r['device']??'')===$device,'status'=>$status,'updated'=>(int)($r['updated']??0)];
 usort($list,static fn($a,$b)=>($b['current']<=>$a['current'])?:($b['updated']<=>$a['updated']));return $list;
}
function vp_config():array{return mg_read(vp_dir().'/config.php');}
function vp_types():array{return ['visit'=>'방문 예정','report'=>'보고서 접수 마감','work'=>'공사 완료기한','filing'=>'이행완료보고 제출 마감','connection'=>'가입·구독·연결','todo'=>'할 일'];}
function vp_preferences(array $input):array{$out=[];foreach(vp_types()+['building_monthly'=>'월별 업무수행기록','building_annual'=>'차기년도 소방계획서'] as $k=>$v)$out[$k]=($input[$k]??true)===true;return $out;}
function vp_validate_subscription(array $in):array{
 $url=$in['endpoint']??'';if(!is_string($url)||strlen($url)>2048)throw new RuntimeException('유효하지 않은 푸시 주소입니다.',400);
 $p=parse_url($url);$host=strtolower((string)($p['host']??''));$allowed=false;
 foreach(['fcm.googleapis.com','push.services.mozilla.com','push.apple.com','notify.windows.com'] as $domain)if($host===$domain||str_ends_with($host,'.'.$domain))$allowed=true;
 if(!$allowed||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||($p['port']??443)!==443)throw new RuntimeException('지원하지 않는 푸시 서비스 주소입니다.',400);
 $keys=$in['keys']??[];foreach(['p256dh'=>65,'auth'=>16] as $key=>$length){$value=$keys[$key]??'';if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]+={0,2}$/D',$value))throw new RuntimeException('푸시 구독키를 확인해 주세요.',400);$raw=base64_decode(strtr($value,'-_','+/'),true);if($raw===false||strlen($raw)!==$length||($key==='p256dh'&&ord($raw[0])!==4))throw new RuntimeException('푸시 구독키 길이를 확인해 주세요.',400);}
 return ['endpoint'=>$url,'keys'=>['p256dh'=>$keys['p256dh'],'auth'=>$keys['auth']],'contentEncoding'=>'aes128gcm'];
}
function vp_subscribe(array &$s,string $actor,string $scope,string $device,array $subscription,array $prefs,int $now,string $label='기기'):array{
 $sub=vp_validate_subscription($subscription);$id=hash('sha256',$sub['endpoint']);$old=$s['subscriptions'][$id]??null;
 if($old&&$old['device']!==$device)throw new RuntimeException('기기 연결을 새로 만들어 주세요.',409);
 foreach($s['subscriptions']??[] as $key=>$r)if($r['device']===$device&&$key!==$id)unset($s['subscriptions'][$key]);
 if(!$old&&(count($s['subscriptions']??[])>=20000||count(array_filter($s['subscriptions']??[],fn($r)=>$r['scope']===$scope))>=128))throw new RuntimeException('등록 가능한 알림 기기 수를 초과했습니다.',400);
 vp_remove_device($s,$scope,$device);
 $s['subscriptions'][$id]=['label'=>$label,'id'=>$id,'actor'=>$actor,'scope'=>$scope,'device'=>$device,'subscription'=>$sub,'prefs'=>vp_preferences($prefs),'updated'=>$now,'created'=>$old['created']??$now];return $s['subscriptions'][$id];
}
function vp_store(string $actor,array $members):string{return __DIR__.'/data/manager_visits/'.vp_scope($actor,$members).'.php';}
function vp_allowed(string $actor,string $target,array $members,array $system):bool{
 if(!mg_active($members[$actor]??[],'agency'))return false;
 if(str_starts_with($target,'pre_')){$id=substr($target,4);return isset(ma_available($actor,$members,$system)[$id]);}
 return mg_can_view($actor,$target,$members,$system);
}
/** Only saved visit-planner records. Never standalone building/other-mode tasks. */
function vp_events(array $s):array{
 $out=[];
 foreach($s['visits']??[] as $id=>$v){
  if(($v['status']??'')==='cancelled')continue;
  $add=static function(string $kind,string $due,bool $pending,bool $ready=true)use(&$out,$id,$v):void{if(!rh_valid_date($due))return;$key=$kind.'_'.$id;$out[$key]=['key'=>$key,'visit_id'=>$id,'uid'=>$v['uid'],'name'=>$v['name'],'kind'=>$kind,'due'=>$due,'pending'=>$pending&&$ready];};
  $add('visit',$v['date'],$v['status']==='planned');
  $r=$s['reports'][$id]??[];
  if($r&&($r['status']??'')!=='cancelled')$add('report',(string)($r['deadline']??''),$r['status']==='planned',!empty($r['calendar_ready']));
  $f=$s['followups'][$id]??[];
  if($f&&($f['status']??'')!=='cancelled'&&empty($f['suspended'])&&($r['status']??'')==='completed'){
   $add('work',(string)($f['work_due']??''),$f['status']==='planned'&&empty($f['actual_work_date']),!empty($f['calendar_ready']));
   $add('filing',(string)($f['filing_due']??''),$f['status']==='planned',!empty($f['calendar_ready']));
  }
 }
 return $out;
}
function vp_reminder(array $e,DateTimeImmutable $now):?int{
 if(empty($e['pending'])||(int)$now->format('H')<9)return null;
 $today=$now->format('Y-m-d');if($e['due']===$today)return 0;if($e['due']===$now->modify('+1 day')->format('Y-m-d'))return 1;return null;
}
function vp_resolve(array $message,string $actor,array $members,bool $receiving=false):?array{
 if(($message['actor']??'')!==$actor||($message['scope']??'')!==vp_scope($actor,$members))return null;
 if(($message['kind']??'')==='building'){require_once __DIR__.'/building_push_common.php';return bp_resolve($message,$actor,$members,$receiving);}
 if(($message['kind']??'')==='digest'){require_once __DIR__.'/work_digest_common.php';return vd_resolve($message,$actor,$members,$receiving);}
 if(($message['kind']??'')==='connection'){
  require_once __DIR__.'/visit_push_inbox_common.php';
  $id=substr((string)($message['event']??''),11);$state=mg_read(mg_state_file());$n=$state['connection_notifications'][$id]??null;
  $r=is_array($n)?vi_connection($n,$actor,vp_scope($actor,$members),$members,$state):null;
  if(!$r||($receiving&&($n['kind']??'')==='connection_requested'&&$r['status']==='completed'))return null;
  if($receiving){$push=mg_read(vp_file());if($r['read']||!empty($push['inbox_read'][$r['key']])||!empty($push['inbox_deleted'][$r['key']]))return null;}
  return ['action_url'=>$r['action_url']??'','title'=>'파트너센터 · '.$r['label'],'body'=>$r['name'].' · '.$r['description'],'url'=>'/visit_push_open.php?message='.$message['id']];
 }
 if(($message['kind']??'')==='test')return ['title'=>!empty($message['test_schedule'])?'[테스트] 오늘 방문 일정':'방문일정 알림 테스트','body'=>!empty($message['test_schedule'])?'소방계획서.com 빌딩 · 오전 10시 방문 예정. 눌러서 가상 일정 상세를 확인하세요.':'이 기기에서 방문일정 알림을 받을 준비가 되었습니다.','url'=>'/visit_push_open.php?message='.$message['id'],'event'=>['key'=>!empty($message['test_schedule'])?'test_'.$message['id']:'test']];
 $e=vp_events(mg_read(vp_store($actor,$members)))[$message['event']]??null;
 if(!$e||!vp_allowed($actor,$e['uid'],$members,mg_read(mg_state_file())))return null;
 if($receiving&&(!$e['pending']||$e['due']!==$message['due']||empty($message['remind_day'])||$message['remind_day']!==(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d')))return null;
 $label=vp_types()[$e['kind']];$when=($message['offset']??0)===1?'내일':'오늘';
 return ['title'=>'방문일정 알림','body'=>$e['name'].' · '.$label.'이 '.$when.'입니다.','url'=>'/visit_push_open.php?message='.$message['id'],'event'=>$e];
}
function vp_webpush(){
 require_once __DIR__.'/push_vendor/vendor/autoload.php';$c=vp_config();
 if(empty($c['publicKey'])||empty($c['privateKey']))throw new RuntimeException('푸시 발송키가 준비되지 않았습니다.');
 return new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>$c['subject'],'publicKey'=>$c['publicKey'],'privateKey'=>$c['privateKey']]],['TTL'=>3600,'urgency'=>'normal'],10,['allow_redirects'=>false]);
}
function vp_send(string $subId,string $messageId):array{
 $state=mg_read(vp_file());$sub=$state['subscriptions'][$subId]??null;$m=$state['messages'][$messageId]??null;
 if(!$sub||!$m||$sub['scope']!==$m['scope']||$sub['device']!==$m['device'])return ['ok'=>false,'reason'=>'detached'];
 $members=mg_members();if((!mg_active($members[$sub['actor']]??[],'agency')&&!mg_active($members[$sub['actor']]??[],'building'))||$sub['scope']!==vp_scope($sub['actor'],$members)||!vp_resolve($m,$sub['actor'],$members,true))return ['ok'=>false,'reason'=>'stale'];
 if(!in_array($m['kind'],['test','digest','building'],true)&&empty(vp_preferences($sub['prefs']??[])[$m['kind']]))return ['ok'=>false,'reason'=>'disabled'];
 try{$report=vp_webpush()->sendOneNotification(Minishlink\WebPush\Subscription::create($sub['subscription']),json_encode(['message'=>$messageId]),['TTL'=>3600,'urgency'=>'high']);$ok=$report->isSuccess();$status=$report->getResponse()?->getStatusCode();$expired=$report->isSubscriptionExpired();}
 catch(Throwable $e){$ok=false;$status=0;$expired=false;}
 vp_tx(static function(array &$s)use($subId,$messageId,$ok,$status,$expired){vp_record_result($s,$subId,$messageId,$ok,(int)$status,$expired,time());});
 return ['ok'=>$ok,'status'=>$status,'expired'=>$expired];
}
function vp_queue(array &$state,array $sub,array $event,DateTimeImmutable $now):?string{
 $offset=vp_reminder($event,$now);if($offset===null||empty($sub['prefs'][$event['kind']]))return null;
 $dedup=hash('sha256',implode('|',[$sub['id'],$sub['scope'],$event['key'],$event['due'],(string)$offset]));
 foreach($state['messages']??[] as $id=>$m)if(($m['dedup']??'')===$dedup){
  if(($m['status']??'')==='failed'&&in_array((int)($m['http_status']??0),[429,500,502,503,504],true)&&($m['attempts']??0)<3&&($m['last_attempt']??0)<$now->getTimestamp()-300){$state['messages'][$id]['status']='sending';return $id;}return null;
 }
 $id=bin2hex(random_bytes(24));$state['messages'][$id]=['id'=>$id,'dedup'=>$dedup,'subscription_id'=>$sub['id'],'actor'=>$sub['actor'],'scope'=>$sub['scope'],'device'=>$sub['device'],'kind'=>$event['kind'],'event'=>$event['key'],'snapshot'=>['uid'=>$event['uid'],'name'=>$event['name']],'due'=>$event['due'],'offset'=>$offset,'remind_day'=>$now->format('Y-m-d'),'created'=>$now->getTimestamp(),'status'=>'sending','attempts'=>0];return $id;
}

function vp_record_result(array &$s,string $subId,string $messageId,bool $ok,int $status,bool $expired,int $at):void{if(isset($s['messages'][$messageId])){$m=&$s['messages'][$messageId];$m['status']=$ok?'sent':'failed';$m['last_attempt']=$at;$m['attempts']=(int)($m['attempts']??0)+1;$m['http_status']=$status;}if($expired)unset($s['subscriptions'][$subId]);}

/** New account events only; no historical replay when adding this push category. */
function vp_queue_connection(array &$state,array $sub,array $notice,array $members,int $now,?array $system=null):?string{
 $since=(int)($state['connection_push_since']??$now);
 if(($notice['kind']??'')==='connection_requested')$since=max($since,(int)($state['request_push_since']??$now));
 $r=vi_connection($notice,$sub['actor'],$sub['scope'],$members,$system);
 if(!$r||(($notice['kind']??'')==='connection_requested'&&$r['status']==='completed')||$r['created']<$since||$r['created']<$now-86400||$r['created']>$now||$r['read']||!empty($state['inbox_read'][$r['key']])||!empty($state['inbox_deleted'][$r['key']])||empty(vp_preferences($sub['prefs']??[])['connection']))return null;
 $dedup=hash('sha256',$sub['id'].'|'.$sub['scope'].'|'.$r['id']);
 foreach($state['messages']??[] as $id=>$m)if(($m['dedup']??'')===$dedup){
  if(($m['status']??'')==='failed'&&in_array((int)($m['http_status']??0),[429,500,502,503,504],true)&&($m['attempts']??0)<3&&($m['last_attempt']??0)<$now-300){$state['messages'][$id]['status']='sending';return $id;}return null;
 }
 $id=bin2hex(random_bytes(24));$state['messages'][$id]=['id'=>$id,'dedup'=>$dedup,'subscription_id'=>$sub['id'],'actor'=>$sub['actor'],'scope'=>$sub['scope'],'device'=>$sub['device'],'kind'=>'connection','event'=>'connection_'.$notice['id'],'created'=>$now,'status'=>'sending','attempts'=>0];return $id;
}

/** Wake the CLI sender after the request has committed and released all locks. */
function vp_wake():void{
 static $queued=false;
 if($queued||PHP_SAPI==='cli'||!function_exists('exec')||!is_file(vp_dir().'/config.php')||empty(mg_read(vp_file())['subscriptions']))return;
 $php=PHP_BINDIR.'/php';if(!is_executable($php))return;$queued=true;
 register_shutdown_function(static function()use($php){
  try{
   if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
   if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
   // Fixed local paths only; no account, endpoint, or request input enters the shell.
   file_put_contents(vp_dir().'/fast_wake.php','<?php exit; ?>'.bin2hex(random_bytes(16)),LOCK_EX);
   $cmd=escapeshellarg($php).' '.escapeshellarg(__DIR__.'/visit_push_run.php').' --connections-only > /dev/null 2>&1 &';
   exec($cmd);
  }catch(Throwable $e){error_log('Immediate push wake failed; scheduled runner will retry.');}
 });
}
function vp_notify_member_change(string $uid):void{
 try{require_once __DIR__.'/manager_subscription.php';ms_notify_paid_user($uid);}catch(Throwable $e){error_log('Immediate member notification deferred to scheduled reconciliation.');}
}
