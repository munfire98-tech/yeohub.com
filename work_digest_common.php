<?php
declare(strict_types=1);
require_once __DIR__.'/visit_push_common.php';
/** Two account-wide daily slots; device-specific type preferences stay separate. */
function vd_times(array $input=[]):array{
 $out=['am'=>'08:15','pm'=>'17:00'];
 foreach($out as $part=>$default){$v=$input[$part]??$default;if(is_string($v)&&preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$v)&&($part==='am'?$v<'12:00':$v>='12:00'))$out[$part]=$v;}
 return $out;
}
function vd_save_times(array &$state,string $scope,array $input,int $now):void{
 $times=vd_times($input);
 foreach(['am','pm'] as $part)if(!isset($input[$part])||$input[$part]!==$times[$part])throw new RuntimeException('오전과 오후의 올바른 수신 시간을 선택해 주세요.',400);
 if($times!==vd_times($state['digest_times'][$scope]??[]))$state['digest_times'][$scope]=$times+['updated'=>$now];
}
/** Explain account-level daily deduplication without exposing device records. */
function vd_timing_status(array $state,string $scope,DateTimeImmutable $now):array{
 $now=$now->setTimezone(new DateTimeZone('Asia/Seoul'));$day=$now->format('Y-m-d');$times=vd_times($state['digest_times'][$scope]??[]);$out=[];
 foreach($times as $part=>$clock){
  $slot=$day.'_'.$part;$record=$state['digests'][vd_key($scope,$slot)]??null;$sent=0;
  foreach($state['messages']??[] as $m)if(($m['scope']??'')===$scope&&($m['kind']??'')==='digest'&&($m['slot']??'')===$slot&&($m['status']??'')==='sent')$sent=max($sent,(int)($m['last_attempt']??$m['created']??0));
  [$h,$min]=array_map('intval',explode(':',$clock));$next=$now->setTime($h,$min);
  if($record||$next<=$now)$next=$next->modify('+1 day');
  $out[$part]=['sent_at'=>$sent,'processed'=>$record!==null,'empty'=>$record!==null&&empty($record['refs']),'next'=>$next->format('Y-m-d H:i')];
 }
 return $out;
}
function vd_slot(DateTimeImmutable $now,int $since=0,array $times=[]):?array{
 $now=$now->setTimezone(new DateTimeZone('Asia/Seoul'));$times=vd_times($times);$clock=$now->format('H:i');if($clock<$times['am'])return null;
 $part=$clock<$times['pm']?'am':'pm';[$hour,$minute]=array_map('intval',explode(':',$times[$part]));$start=$now->setTime($hour,$minute);
 if($start->getTimestamp()<$since)return null;
 $day=$now->format('Y-m-d');$friday=$part==='pm'&&$now->format('N')==='5';
 return ['id'=>$day.'_'.$part,'day'=>$day,'part'=>$part,'friday'=>$friday,'start'=>$start->getTimestamp(),'tomorrow'=>$now->modify('+1 day')->format('Y-m-d'),'urgent_end'=>$now->modify('+3 days')->format('Y-m-d'),'weekend_end'=>$now->modify('+2 days')->format('Y-m-d'),'week_start'=>$now->modify('next monday')->format('Y-m-d'),'week_end'=>$now->modify('next monday +6 days')->format('Y-m-d')];
}
function vd_parse_slot(string $id):?array{
 if(!preg_match('/^(20\d{2}-\d{2}-\d{2})_(am|pm)$/D',$id,$m)||!rh_valid_date($m[1]))return null;
 return vd_slot(new DateTimeImmutable($m[1].($m[2]==='am'?' 08:15':' 17:00'),new DateTimeZone('Asia/Seoul')));
}
function vd_key(string $scope,string $slot):string{return hash('sha256',$scope.'|digest|'.$slot);}
function vd_events(array $store):array{
 $events=vp_events($store);
 foreach($store['tasks']??[] as $id=>$task){
  if(!is_array($task)||($task['status']??'')!=='planned'||!rh_valid_date((string)($task['due']??'')))continue;
  $key='todo_'.$id;$events[$key]=['key'=>$key,'uid'=>(string)($task['uid']??''),'name'=>(string)($task['client_name']??''),'title'=>(string)$task['title'],'kind'=>'todo','due'=>$task['due'],'pending'=>true];
 }
 return $events;
}
function vd_allowed(array $event,string $actor,array $members,array $system):bool{
 return ($event['kind']??'')==='todo'&&($event['uid']??'')===''?mg_active($members[$actor]??[],'agency'):vp_allowed($actor,(string)($event['uid']??''),$members,$system);
}
function vd_bucket(array $event,array $slot):?string{
 $due=$event['due'];$today=$slot['day'];
 if($slot['part']==='am'){
  if($due<$today)return 'overdue';if($due===$today)return 'today';
  if($event['kind']!=='visit'&&$due<=$slot['urgent_end'])return 'soon';return null;
 }
 if(!$slot['friday'])return $due===$slot['tomorrow']?'tomorrow':null;
 if($due<=$today)return 'carry';
 if($due<=$slot['weekend_end'])return 'weekend';
 return $due>=$slot['week_start']&&$due<=$slot['week_end']?'nextweek':null;
}
function vd_rows(array $record,array $store,string $actor,array $members,array $system,?array $preferences=null):array{
 $slot=vd_parse_slot((string)$record['slot']);if(!$slot)return [];$reference=max($slot['day'],(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d'));$referenceEnd=(new DateTimeImmutable($reference))->modify('+3 days')->format('Y-m-d');$all=vd_events($store);$rows=[];
 foreach($record['refs']??[] as $key=>$ref){
  $e=$all[$key]??null;if(!$e||empty($e['pending'])||!vd_allowed($e,$actor,$members,$system)||($preferences!==null&&empty($preferences[$e['kind']])))continue;
  $bucket=vd_bucket($e,$slot);if(!$bucket)continue;
  $e['bucket']=$bucket;$e['label']=vp_types()[$e['kind']];$e['display']=$e['kind']==='todo'?($e['name']!==''?$e['name'].' · ':'').$e['title']:$e['name'].' · '.$e['label'];
  $e['urgent']=$e['due']<=$reference?'critical':($e['kind']!=='visit'&&$e['due']<=$referenceEnd?'soon':'normal');$e['urgency_label']=$e['due']<$reference?($e['kind']==='visit'?'방문 확인 필요':'기한 초과'):($e['due']===$reference?($e['kind']==='visit'?'오늘 방문':'오늘 마감'):($e['urgent']==='soon'?'마감 임박':'업무 보기'));$rows[]=$e;
 }
 $ranks=['overdue'=>0,'today'=>1,'carry'=>2,'soon'=>3,'tomorrow'=>4,'weekend'=>4,'nextweek'=>5];$kinds=['filing'=>0,'report'=>1,'work'=>2,'todo'=>3,'visit'=>4];
 usort($rows,static fn($a,$b)=>($ranks[$a['bucket']]<=>$ranks[$b['bucket']])?:strcmp($a['due'],$b['due'])?:($kinds[$a['kind']]<=>$kinds[$b['kind']])?:strcmp($a['key'],$b['key']));return $rows;
}
function vd_visible(array $record,string $actor,string $scope,array $members,array $system):bool{
 if(($record['actor']??'')!==$actor||($record['scope']??'')!==$scope||!mg_active($members[$actor]??[],'agency'))return false;
 foreach($record['refs']??[] as $ref)if(vd_allowed($ref,$actor,$members,$system))return true;return false;
}
function vd_counts(array $rows):string{
 $counts=[];foreach($rows as $r)$counts[$r['kind']]=($counts[$r['kind']]??0)+1;
 $labels=['visit'=>'방문','report'=>'보고서 접수','work'=>'공사 완료기한','filing'=>'이행완료보고','todo'=>'할 일'];$parts=[];
 foreach($labels as $kind=>$label)if(!empty($counts[$kind]))$parts[]=$label.' '.$counts[$kind].'건';return implode(' · ',$parts);
}
function vd_text(array $record,array $rows):array{
 $slot=vd_parse_slot($record['slot']);$day=str_replace('-','/',substr($slot['day'],5));$title=$slot['part']==='am'?'오늘 업무 안내 · '.$day:($slot['friday']?'다음 주 업무 안내 · '.str_replace('-','/',substr($slot['week_start'],5)).'~'.str_replace('-','/',substr($slot['week_end'],5)):'내일 업무 안내 · '.str_replace('-','/',substr($slot['tomorrow'],5)));
 $labels=['overdue'=>'기한 초과·지난 일정','today'=>'오늘','soon'=>'3일 내 마감','tomorrow'=>'내일','carry'=>'이번 주에서 넘어온 미완료','weekend'=>'이번 주말','nextweek'=>'다음 주'];$lines=[];
 foreach($labels as $key=>$label){$bucket=array_values(array_filter($rows,static fn($r)=>$r['bucket']===$key));
  if(in_array($key,['overdue','carry'],true)){$visits=array_values(array_filter($bucket,static fn($r)=>$r['kind']==='visit'));if($visits)$lines[]=($key==='carry'?'이번 주 방문 여부 확인':'방문 여부 확인').': '.count($visits).'곳';$bucket=array_values(array_filter($bucket,static fn($r)=>$r['kind']!=='visit'));}
  if($bucket)$lines[]=$label.': '.vd_counts($bucket);
 }
 if($rows&&$slot['part']==='am')$lines[]='우선 확인: '.$rows[0]['display'].' ('.$rows[0]['due'].')';
 return ['title'=>$title,'body'=>$lines?implode("\n",$lines):'모든 업무가 완료되었거나 일정이 변경되었습니다.','slot'=>$slot,'rows'=>$rows];
}
function vd_prepare(array &$state,string $actor,string $scope,array $subscriptions,array $store,array $members,array $system,DateTimeImmutable $now):?array{
 $times=$state['digest_times'][$scope]??[];$slot=vd_slot($now,max((int)($state['digest_since']??PHP_INT_MAX),(int)($times['updated']??0)),$times);if(!$slot)return null;$key=vd_key($scope,$slot['id']);
 if(isset($state['digests'][$key]))return $state['digests'][$key];
 $prefs=[];$recipients=[];
 foreach($subscriptions as $sub){if(($sub['actor']??'')!==$actor||($sub['scope']??'')!==$scope||($sub['updated']??0)<$now->getTimestamp()-86400*30)continue;$recipients[]=$sub['id'];foreach(vp_preferences($sub['prefs']??[]) as $kind=>$enabled)if($enabled)$prefs[$kind]=true;}
 $refs=[];foreach(vd_events($store) as $keyEvent=>$e)if(!empty($e['pending'])&&!empty($prefs[$e['kind']])&&vd_allowed($e,$actor,$members,$system)&&vd_bucket($e,$slot)!==null)$refs[$keyEvent]=['uid'=>$e['uid'],'kind'=>$e['kind']];
 // Even an empty slot is recorded so later edits don't create an off-time notification.
 return $state['digests'][$key]=['actor'=>$actor,'scope'=>$scope,'slot'=>$slot['id'],'scheduled_start'=>$slot['start'],'created'=>$now->getTimestamp(),'refs'=>$refs,'recipients'=>$recipients];
}
function vd_queue(array &$state,array $sub,array $record,array $store,array $members,array $system,int $now):?string{
 if($sub['actor']!==$record['actor']||$sub['scope']!==$record['scope']||!in_array($sub['id'],$record['recipients'],true))return null;
 $prefs=vp_preferences($sub['prefs']??[]);if(!vd_rows($record,$store,$sub['actor'],$members,$system,$prefs))return null;
 $key=vd_key($sub['scope'],$record['slot']);if(!empty($state['inbox_read'][$key])||!empty($state['inbox_deleted'][$key]))return null;
 $dedup=hash('sha256',$sub['id'].'|'.$key);
 foreach($state['messages']??[] as $id=>$m)if(($m['dedup']??'')===$dedup){
  if(($m['status']??'')==='failed'&&in_array((int)($m['http_status']??0),[429,500,502,503,504],true)&&($m['attempts']??0)<3&&($m['last_attempt']??0)<$now-300){$state['messages'][$id]['status']='sending';return $id;}return null;
 }
 $id=bin2hex(random_bytes(24));$state['messages'][$id]=['id'=>$id,'dedup'=>$dedup,'subscription_id'=>$sub['id'],'actor'=>$sub['actor'],'scope'=>$sub['scope'],'device'=>$sub['device'],'kind'=>'digest','event'=>'digest_'.$record['slot'],'slot'=>$record['slot'],'types'=>$prefs,'created'=>$now,'status'=>'sending','attempts'=>0];return $id;
}
function vd_resolve(array $message,string $actor,array $members,bool $receiving=false):?array{
 $state=mg_read(vp_file());$scope=vp_scope($actor,$members);$slot=(string)($message['slot']??'');$key=vd_key($scope,$slot);$record=$state['digests'][$key]??null;$system=mg_read(mg_state_file());
 if(!$record||!vd_visible($record,$actor,$scope,$members,$system))return null;
 $prefs=null;
 if($receiving){
  $parsed=vd_parse_slot($slot);$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul'));
  if(!$parsed||$parsed['day']!==$now->format('Y-m-d')||$now->getTimestamp()<(int)($record['scheduled_start']??$parsed['start'])||!empty($state['inbox_read'][$key])||!empty($state['inbox_deleted'][$key]))return null;
  $sub=$state['subscriptions'][$message['subscription_id']]??null;if(!$sub||$sub['scope']!==$scope||$sub['device']!==$message['device'])return null;
  $prefs=vp_preferences($sub['prefs']??[]);foreach($prefs as $kind=>&$enabled)$enabled=$enabled&&!empty($message['types'][$kind]);unset($enabled);
 }
 $rows=vd_rows($record,mg_read(vp_store($actor,$members)),$actor,$members,$system,$prefs);if($receiving&&!$rows)return null;
 $text=vd_text($record,$rows);return ['title'=>$text['title'],'body'=>$text['body'],'url'=>'/visit_push_open.php?message='.$message['id']];
}
function vd_inbox_entry(array $message,string $actor,string $scope,array $store,array $members,array $system,array $state):?array{
 $slot=(string)($message['slot']??'');$record=$state['digests'][vd_key($scope,$slot)]??null;if(!$record||!vd_visible($record,$actor,$scope,$members,$system))return null;
 $rows=vd_rows($record,$store,$actor,$members,$system);$text=vd_text($record,$rows);
 return ['id'=>$message['id'],'key'=>vd_key($scope,$slot),'kind'=>'digest','name'=>'업무 요약','label'=>$text['title'],'description'=>$text['body'],'due'=>$text['slot']['day'],'original_due'=>$text['slot']['day'],'status'=>$rows?'scheduled':'completed','event'=>'digest_'.$slot,'digest_slot'=>$slot,'created'=>(int)$message['created'],'delivery'=>$message['status'],'revision'=>hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE))];
}
