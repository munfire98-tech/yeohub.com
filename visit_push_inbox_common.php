<?php
declare(strict_types=1);
require_once __DIR__.'/visit_push_common.php';
require_once __DIR__.'/withdraw_manager.php';
require_once __DIR__.'/manager_subscription.php';
require_once __DIR__.'/work_digest_common.php';
/** A logical reminder is shared across the account's devices. */
function vi_key(array $m):string{
 if(($m['kind']??'')==='digest')return vd_key((string)$m['scope'],(string)$m['slot']);
 if(($m['kind']??'')==='connection')return hash('sha256',($m['scope']??'').'|manager|'.substr((string)($m['event']??''),11));
 return hash('sha256',implode('|',[$m['scope']??'',($m['kind']??'')==='test'?'test_'.$m['id']:($m['event']??''),$m['due']??'',(string)($m['offset']??0)]));
}
function vi_entry(array $m,string $actor,string $scope,array $store,array $members,array $system,array $pushState=[]):?array{
 if(($m['actor']??'')!==$actor||($m['scope']??'')!==$scope||!in_array($m['status']??'',['queued','sending','sent','failed'],true))return null;
 $id=(string)($m['id']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$id))return null;
 if(($m['kind']??'')==='building'){require_once __DIR__.'/building_push_common.php';$content=bp_resolve($m,$actor,$members);if(!$content)return null;return ['id'=>$id,'key'=>vi_key($m),'kind'=>'building','name'=>$content['title'],'label'=>'건물 업무 알림','description'=>$content['body'],'due'=>$m['due']??'','original_due'=>'','status'=>$content['items']?'scheduled':'completed','event'=>'','action_url'=>'/visit_push_open.php?message='.$id,'created'=>(int)$m['created'],'delivery'=>$m['status']];}
 if(($m['kind']??'')==='digest')return vd_inbox_entry($m,$actor,$scope,$store,$members,$system,$pushState);
 if(($m['kind']??'')==='test')return ['id'=>$id,'key'=>vi_key($m),'kind'=>'test','name'=>$m['test_schedule']['name']??'테스트 알림','label'=>!empty($m['test_schedule'])?'[테스트] 방문 예정일':'방문일정 알림 테스트','test_schedule'=>$m['test_schedule']??null,'description'=>'실제 업무에 포함되지 않는 가상 일정입니다.','due'=>$m['test_schedule']['date']??'','original_due'=>'','status'=>'test','event'=>!empty($m['test_schedule'])?'test_'.$id:'test','created'=>(int)$m['created'],'delivery'=>$m['status']];
 if(($m['kind']??'')==='connection')return null; // Transport record; canonical manager event is listed once below.
 $kind=(string)($m['kind']??'');if(!isset(vp_types()[$kind]))return null;
 $eventKey=(string)($m['event']??'');$prefix=$kind.'_';if(!str_starts_with($eventKey,$prefix))return null;
 $visitId=substr($eventKey,strlen($prefix));$visit=$store['visits'][$visitId]??null;
 $target=(string)($visit['uid']??$m['snapshot']['uid']??'');
 if($target===''||!vp_allowed($actor,$target,$members,$system))return null;
 $source=$kind==='visit'?$visit:($kind==='report'?($store['reports'][$visitId]??null):($store['followups'][$visitId]??null));
 $due=(string)($kind==='visit'?($source['date']??''):($kind==='report'?($source['deadline']??''):($kind==='work'?($source['work_due']??''):($source['filing_due']??''))));
 $status='scheduled';
 if(!$visit||!$source)$status='deleted';
 elseif(($visit['status']??'')==='cancelled'||($source['status']??'')==='cancelled')$status='cancelled';
 elseif(($source['status']??'')==='completed'||($kind==='work'&&!empty($source['actual_work_date'])))$status='completed';
 elseif(!empty($source['suspended'])||!empty($source['needs_review']))$status='review';
 elseif($due!==($m['due']??''))$status='changed';
 $event=in_array($status,['deleted','cancelled'],true)?'':$eventKey;
 return ['id'=>$id,'key'=>vi_key($m),'kind'=>$kind,'name'=>(string)($visit['name']??$m['snapshot']['name']??'거래처'),'label'=>vp_types()[$kind],'due'=>$due,'original_due'=>(string)($m['due']??''),'offset'=>(int)($m['offset']??0),'status'=>$status,'event'=>$event,'created'=>(int)$m['created'],'delivery'=>$m['status']];
}
function vi_connection(array $n,string $actor,string $scope,array $members,?array $system=null):?array{
 if(($n['manager']??'')!==$actor||($n['manager_created']??'')!==(string)($members[$actor]['created']??'')||$scope!==vp_scope($actor,$members)||!wm_visible($n,$members))return null;
 $id=(string)($n['id']??'');if(!preg_match('/^[a-f0-9]{64}$/D',$id))return null;
 $labels=['local_connected'=>'로컬 파트너 새 연결','connection_requested'=>'새로운 연결 요청','subscription_started'=>'PRO 모드 전환','subscription_renewed'=>'PRO 구독 갱신','renewal_disabled'=>'자동갱신 해지','refund_pending'=>'환불 확인 중','subscription_refunded'=>'해지·환불 완료','subscription_canceled'=>'구독 해지','qr_joined'=>'QR 가입·연결 완료','member_withdrawn'=>'회원 탈퇴','disconnected'=>'연결 해제','request_cancelled'=>'연결 요청 취소'];
 $kind=(string)($n['kind']??'');
 $descriptions=['local_connected'=>'정보 공유 동의 후 로컬 파트너와 자동 연결되었습니다. 수락 절차 없이 새 연결 목록에서 확인할 수 있습니다.','connection_requested'=>'파트너 코드로 연결을 요청했습니다. 요청 내용을 확인하고 승인해 주세요.','subscription_started'=>'PRO 모드로 전환되었습니다. 연결된 건물의 업무 현황을 확인할 수 있습니다.','subscription_renewed'=>'PRO 구독 갱신 결제가 완료되었습니다.','renewal_disabled'=>'자동갱신을 해지했습니다.'.(!empty($n['expires_at'])?' 기존 이용기간은 '.$n['expires_at'].'까지입니다.':''),'refund_pending'=>'환불을 요청하여 처리 결과를 확인 중입니다. 아직 환불 완료 상태는 아닙니다.','subscription_refunded'=>'해지·환불이 완료되어 PRO 이용이 종료되었습니다.','subscription_canceled'=>'PRO 구독이 해지되었습니다.','qr_joined'=>'QR 초대로 가입하고 건물과 자동 연결되었습니다. 구독 여부는 PRO 구독 알림에서 확인하세요.','member_withdrawn'=>'연결된 유저가 탈퇴했습니다. 건물 화면에 접근할 수 없습니다.','disconnected'=>'유저가 파트너 연결을 해제했습니다.','request_cancelled'=>'연결 요청이 취소되었습니다.'];

 $action='';$status='connection';
 if($kind==='connection_requested'){
  $system??=mg_read(mg_state_file());$uid=(string)($n['user']??'');$member=$members[$uid]??[];
  $pending=mg_active($member,'building')&&mg_connection_manager($member,$members)===$actor&&hash_equals((string)($n['link_key']??''),mg_link_key($uid,$member))&&mg_link_status($uid,$member,$system)==='pending';
  if($pending)$action='/manager_addresses.php?request='.rawurlencode($uid).'&key='.rawurlencode(mg_link_key($uid,$member));
  else{$status='completed';$descriptions[$kind]='이미 처리되었거나 취소·변경된 연결 요청입니다.';}
 }
 return ['id'=>'manager_'.$id,'key'=>hash('sha256',$scope.'|manager|'.$id),'kind'=>'connection','name'=>(string)($n['name']??'거래처'),'label'=>(!empty($n['test'])?'테스트 · ':'').($labels[$kind]??'연결 요청 변경'),'due'=>'','original_due'=>'','description'=>($descriptions[$kind]??'연결 요청 상태가 변경되었습니다.').(!empty($n['started_at'])?' 구독 시작일 '.$n['started_at']:'').(!empty($n['test'])?' 테스트 모드 알림입니다.':''),'status'=>$status,'action_url'=>$action,'event'=>'','created'=>strtotime((string)($n['at']??''))?:0,'delivery'=>'sent','read'=>!empty($n['read_at'])];
}
function vi_item(string $id,array $state,string $actor,string $scope,array $store,array $members,array $system):?array{
 if(str_starts_with($id,'manager_')){ $n=$system['connection_notifications'][substr($id,8)]??null;return is_array($n)?vi_connection($n,$actor,$scope,$members,$system):null; }
 $m=$state['messages'][$id]??null;if(($m['kind']??'')==='connection'){if(($m['actor']??'')!==$actor||($m['scope']??'')!==$scope)return null; $n=$system['connection_notifications'][substr((string)($m['event']??''),11)]??null;return is_array($n)?vi_connection($n,$actor,$scope,$members,$system):null;}return is_array($m)?vi_entry($m,$actor,$scope,$store,$members,$system,$state):null;
}
function vi_list(array $state,string $actor,string $scope,array $store,array $members,array $system):array{
 $out=[];
 foreach($state['messages']??[] as $m){
  if(($m['created']??0)<time()-86400*90)continue;
  $r=vi_entry($m,$actor,$scope,$store,$members,$system,$state);if(!$r)continue;
  $key=$r['key'];if(!empty($state['inbox_deleted'][$key]))continue;$r['read']=!empty($state['inbox_read'][$key]);
  if(!isset($out[$key])||$r['created']>$out[$key]['created'])$out[$key]=$r;
 }
 foreach($system['connection_notifications']??[] as $n){
  if(!is_array($n))continue;$r=vi_connection($n,$actor,$scope,$members,$system);if(!$r||$r['created']<time()-86400*90||!empty($state['inbox_deleted'][$r['key']]))continue;
  $r['read']=$r['read']||!empty($state['inbox_read'][$r['key']]);$out[$r['key']]=$r;
 }
 $rows=array_values($out);usort($rows,fn($a,$b)=>$b['created']<=>$a['created']?:strcmp($b['id'],$a['id']));return $rows;
}
function vi_read(array &$state,string $messageId,string $actor,string $scope,array $store,array $members,array $system):void{
 $r=vi_item($messageId,$state,$actor,$scope,$store,$members,$system);
 if(!$r)throw new RuntimeException('알림을 확인할 권한이 없습니다.',403);
 $state['inbox_read'][$r['key']]=time();
}

function vi_delete(array &$state,string $id,string $actor,string $scope,array $store,array $members,array $system):void{
 $r=vi_item($id,$state,$actor,$scope,$store,$members,$system);
 if(!$r)throw new RuntimeException('알림을 삭제할 권한이 없습니다.',403);
 $state['inbox_deleted'][$r['key']]=time();$state['inbox_read'][$r['key']]=time();
}
function vi_revision(array $items):string{return hash('sha256',json_encode(array_map(static fn($r)=>[$r['key'],$r['read'],$r['due'],$r['status'],$r['revision']??''],$items)));}

function vi_group(array $item):string{return ($item['kind']??'')==='connection'?'connection':'visit';}
function vi_groups(array $items):array{
 $groups=['all'=>['total'=>count($items),'unread'=>0],'visit'=>['total'=>0,'unread'=>0],'connection'=>['total'=>0,'unread'=>0]];
 foreach($items as $r){$g=vi_group($r);$groups[$g]['total']++;if(!$r['read']){$groups[$g]['unread']++;$groups['all']['unread']++;}}
 return $groups;
}
function vi_selected_group($value):string{
 if(!is_string($value)||!in_array($value,['all','visit','connection'],true))throw new RuntimeException('알림 분류를 확인해 주세요.',400);
 return $value;
}

/** Called only after vp_resolve verifies the account and current access. */
function vi_open_destination(array $message,array $content):string {
 if(($message['kind']??'')==='building')return (string)$content['action_url'];
 if(in_array($message['kind']??'',['connection','digest'],true))return '/partner_center.php?notification_item='.rawurlencode((string)$message['id']);
 $key=(string)($content['event']['key']??'');return '/partner_center.php?visit_notice='.rawurlencode($key!==''?$key:'test');
}
