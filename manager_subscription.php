<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
/** Public manager view. Never return billing keys or payment identifiers. */
function ms_subscription(string $uid): array {
    $result=['active'=>false,'started_at'=>'','period_started_at'=>'','expires_at'=>'','test'=>false];
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return $result;
    $d=sa_read($uid);
    if(!pc_active($uid))return $result;
    $latest=[];
    foreach((array)($d['history']??[]) as $h){
        if(!is_array($h)||empty($h['ok'])||!in_array($h['type']??'',['payment','renewal',''],true))continue;
        if(strcmp((string)($h['at']??''),(string)($latest['at']??''))>=0)$latest=$h;
    }
    $result['active']=true;
    $result['test']=($latest['test']??false)===true;
    foreach(['started_at'=>$d['started_at']??$d['manager_first_payment']['at']??$d['paid_at']??'', 'period_started_at'=>$d['paid_at']??$latest['at']??'', 'expires_at'=>$d['expires_at']??$d['next_billing']??$d['next_at']??''] as $key=>$value){
        $ts=strtotime((string)$value);$result[$key]=$ts===false?'':date('Y-m-d',$ts);
    }
    return $result;
}
/** Create an acknowledgement once per paid period and current accepted connection. */
function ms_sync_notifications(string $actor,array $members,string $onlyUid=''):void {
    $state=mg_read(mg_state_file());$events=[];
    foreach($members as $uid=>$member){
        $uid=(string)$uid;if($onlyUid!==''&&$uid!==$onlyUid)continue;
        if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!is_array($member))continue;
        $request=ms_request_event($uid,$member,$actor,$members,$state);
        if($request&&!isset($state['connection_notifications'][$request['id']]))$events[$request['id']]=$request;
        if(!mg_can_view($actor,$uid,$members,$state))continue;
        $localEvent=ms_local_join_event($uid,$member,$actor,$members,$state);if($localEvent&&!isset($state['connection_notifications'][$localEvent['id']]))$events[$localEvent['id']]=$localEvent;
        $qrEvent=ms_qr_join_event($uid,$member,$actor,$members[$actor]??[],$state);
        if($qrEvent&&!isset($state['connection_notifications'][$qrEvent['id']]))$events[$qrEvent['id']]=$qrEvent;
        $summary=ms_subscription($uid); // A completed payment remains an event after cancellation/refund.
        $d=sa_read($uid);$payment=[];
        foreach(ms_lifecycle_events($d) as $token=>$event){
            $id=hash('sha256','lifecycle:'.mg_link_key($uid,$member).':'.$actor.':'.($members[$actor]['created']??'').':'.$token);
            if(isset($state['connection_notifications'][$id]))continue;
            $bi=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
            $events[$id]=['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'user'=>$uid,'link_key'=>mg_link_key($uid,$member),'name'=>trim((string)($bi['name']??''))?:((string)($member['nickname']??$uid)),'kind'=>$event['kind'],'at'=>$event['at'],'expires_at'=>$event['expires_at'],'test'=>$event['test'],'read_at'=>null];
        }

        foreach((array)($d['history']??[]) as $h){
            if(!is_array($h)||!is_bool($h['test']??null)||empty($h['ok'])||empty($h['paymentKey'])||!in_array($h['type']??'',['payment','renewal',''],true))continue;
            if(strcmp((string)($h['at']??''),(string)($payment['at']??''))>=0)$payment=$h;
        }
        if(!$payment){$first=$d['manager_first_payment']??[];if(($first['live']??false)===true&&($first['status']??'')==='DONE'&&!empty($first['payment_key']))$payment=['paymentKey'=>$first['payment_key'],'at'=>$first['at']??'','test'=>false];}
        if(!$payment||strtotime((string)($payment['at']??''))===false)continue;
        $id=hash('sha256','subscription:'.mg_link_key($uid,$member).':'.$actor.':'.($members[$actor]['created']??'').':'.$payment['paymentKey']);
        if(isset($state['connection_notifications'][$id]))continue;
        $bi=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
        // An already-PRO user may connect later. Notify when both conditions become true,
        // while retaining the existing payment+connection event ID and renewal semantics.
        $noticeAt=strtotime($payment['at']);
        if($summary['active']&&($payment['type']??'')!=='renewal')$noticeAt=max($noticeAt,strtotime((string)($state['links'][mg_link_key($uid,$member)]['at']??''))?:0);
        $events[$id]=['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'user'=>$uid,'link_key'=>mg_link_key($uid,$member),'name'=>trim((string)($bi['name']??''))?:((string)($member['nickname']??$uid)),'kind'=>($payment['type']??'')==='renewal'?'subscription_renewed':'subscription_started','at'=>date('c',$noticeAt),'started_at'=>$summary['started_at'],'period_started_at'=>$summary['period_started_at'],'expires_at'=>$summary['expires_at'],'test'=>($payment['test']??false)===true,'read_at'=>null];
    }
    if(!$events)return;
    // Recheck connection under member -> state lock before publishing notices.
    mg_member_tx(function(array &$current)use($actor,$events){mg_state_tx(function(array &$s)use($actor,$events,$current){foreach($events as $id=>$event){if(!(mg_can_view($actor,$event['user'],$current,$s)||(($event['kind']??'')==='connection_requested'&&ms_request_event($event['user'],$current[$event['user']]??[],$actor,$current,$s)!==null))||mg_link_key($event['user'],$current[$event['user']]??[])!==$event['link_key']||($current[$actor]['created']??'')!==$event['manager_created'])continue;if(!isset($s['connection_notifications'][$id]))$s['connection_notifications'][$id]=$event;}});});
    try{require_once __DIR__.'/visit_push_common.php';vp_wake();}catch(Throwable $e){error_log('Account push wake deferred.');}
}

/** Called only after payment entitlement has been committed and the billing lock released. */
function ms_notify_paid_user(string $uid):void {
 $members=mg_members();$member=$members[$uid]??[];
 if(!mg_active($member,'building'))return;
 $actor=mg_connection_manager($member,$members);if($actor==='')return;
 ms_sync_notifications($actor,$members,$uid);
}

/** Backfill existing confirmed states; pending refunds are never called completed. */
function ms_lifecycle_events(array $d):array {
 $events=is_array($d['manager_subscription_events']??null)?$d['manager_subscription_events']:[];
 $add=static function(string $kind,string $token,string $at)use(&$events,$d):void {
  if($token===''||$at===''||strtotime($at)===false)return;
  $events[$kind.':'.$token]??=['kind'=>$kind,'at'=>$at,'expires_at'=>$d['expires_at']??$d['next_billing']??'','test'=>($d['billing_mode']??$d['refund_attempt']['mode']??'')==='test'];
 };
 if(($d['auto_renew']??null)===false&&in_array($d['status']??'',['active','payment_failed'],true)&&!empty($d['renewal_changed_at']))$add('renewal_disabled',$d['renewal_changed_at'],$d['renewal_changed_at']);
 $attempt=$d['refund_attempt']??[];
 if(($d['refund']['status']??'')==='done'&&($attempt['state']??'')==='done')$add('subscription_refunded',(string)($attempt['id']??''),(string)($d['refund']['refunded_at']??$d['canceled_at']??''));
 elseif(($d['status']??'')==='refund_pending'&&in_array($attempt['state']??'',['prepared','unknown'],true))$add('refund_pending',(string)($attempt['id']??''),(string)($attempt['created_at']??''));
 if(($d['status']??'')==='canceled')$add('subscription_canceled',(string)($d['canceled_at']??''),(string)($d['canceled_at']??''));
 $out=[];
 foreach($events as $token=>$event){
  if(!is_array($event)||!in_array($event['kind']??'',['renewal_disabled','subscription_refunded','refund_pending','subscription_canceled'],true)||strtotime((string)($event['at']??''))===false)continue;
  $end=strtotime((string)($event['expires_at']??''));$event['expires_at']=$end===false?'':date('Y-m-d',$end);$event['test']=!empty($event['test']);$out[$token]=$event;
 }
 return $out;
}

/** Reconcile only committed QR accounts; retries and refreshes use one stable event ID. */
function ms_qr_join_event(string $uid,array $member,string $actor,array $manager,array $state):?array {
 if(($member['joined_via']??'')!=='manager_qr')return null;
 $key=mg_link_key($uid,$member);$link=$state['links'][$key]??[];
 if(($link['status']??'')!=='accepted'||($link['source']??'')!=='qr_invite')return null;
 $id=hash('sha256','qr_join:'.$key.':'.$actor.':'.($manager['created']??''));
 return ['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($manager['created']??''),'user'=>$uid,'link_key'=>$key,'name'=>(string)($member['nickname']??$uid),'kind'=>'qr_joined','at'=>(string)($link['at']??$member['created']??''),'read_at'=>null];
}

/** Stable event per committed pending request; no private building data before acceptance. */
function ms_request_event(string $uid,array $member,string $actor,array $members,array $state):?array{
 if(!mg_active($member,'building')||!mg_active($members[$actor]??[],'agency')||mg_connection_manager($member,$members)!==$actor||mg_link_status($uid,$member,$state)!=='pending')return null;
 $at=(string)($member['manager_requested_at']??$member['manager_consent_at']??$member['referral_at']??'');if(strtotime($at)===false)return null;
 $key=mg_link_key($uid,$member);$id=hash('sha256','connection_request:'.$key.':'.$actor.':'.($members[$actor]['created']??''));
 return ['id'=>$id,'manager'=>$actor,'manager_created'=>(string)$members[$actor]['created'],'user'=>$uid,'user_created'=>(string)($member['created']??''),'link_key'=>$key,'name'=>(string)($member['nickname']??$uid),'kind'=>'connection_requested','at'=>$at,'read_at'=>null];
}

/** A new local connection is informational; no acceptance action is offered. */
function ms_local_join_event(string $uid,array $member,string $actor,array $members,array $state):?array {
 $key=mg_link_key($uid,$member);$link=$state['links'][$key]??[];
 if(!mg_is_local_partner($actor,$members)||!mg_can_view($actor,$uid,$members,$state)||($link['source']??'')!=='local_auto')return null;
 $id=hash('sha256','local_join:'.$key.':'.$actor.':'.($members[$actor]['created']??''));
 return ['id'=>$id,'manager'=>$actor,'manager_created'=>(string)$members[$actor]['created'],'user'=>$uid,'user_created'=>(string)($member['created']??''),'link_key'=>$key,'name'=>(string)($member['nickname']??$uid),'kind'=>'local_connected','at'=>(string)$link['at'],'read_at'=>null];
}
