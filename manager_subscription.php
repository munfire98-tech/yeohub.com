<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
/** Public manager view. Never return billing keys or payment identifiers. */
function ms_subscription(string $uid): array {
    $result=['active'=>false,'started_at'=>'','period_started_at'=>'','expires_at'=>'','test'=>false];
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return $result;
    $d=mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
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
function ms_sync_notifications(string $actor,array $members):void {
    $state=mg_read(mg_state_file());$events=[];
    foreach($members as $uid=>$member){
        $uid=(string)$uid;
        if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!is_array($member)||!mg_can_view($actor,$uid,$members,$state))continue;
        $summary=ms_subscription($uid);if(!$summary['active']||$summary['test'])continue;
        $d=mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');$payment=[];
        foreach((array)($d['history']??[]) as $h){
            if(!is_array($h)||($h['test']??null)!==false||empty($h['ok'])||empty($h['paymentKey'])||!in_array($h['type']??'',['payment','renewal',''],true))continue;
            if(strcmp((string)($h['at']??''),(string)($payment['at']??''))>=0)$payment=$h;
        }
        if(!$payment){$first=$d['manager_first_payment']??[];if(($first['live']??false)===true&&($first['status']??'')==='DONE'&&!empty($first['payment_key']))$payment=['paymentKey'=>$first['payment_key'],'at'=>$first['at']??''];}
        if(!$payment||strtotime((string)($payment['at']??''))===false)continue;
        $id=hash('sha256','subscription:'.mg_link_key($uid,$member).':'.$actor.':'.($members[$actor]['created']??'').':'.$payment['paymentKey']);
        if(isset($state['connection_notifications'][$id]))continue;
        $bi=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
        $events[$id]=['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'user'=>$uid,'name'=>trim((string)($bi['name']??''))?:((string)($member['nickname']??$uid)),'kind'=>($payment['type']??'')==='renewal'?'subscription_renewed':'subscription_started','at'=>date('c',strtotime($payment['at'])),'started_at'=>$summary['started_at'],'period_started_at'=>$summary['period_started_at'],'expires_at'=>$summary['expires_at'],'read_at'=>null];
    }
    if(!$events)return;
    // Recheck connection under member -> state lock before publishing notices.
    mg_member_tx(function(array &$current)use($actor,$events){mg_state_tx(function(array &$s)use($actor,$events,$current){foreach($events as $id=>$event){if(!mg_can_view($actor,$event['user'],$current,$s)||($current[$actor]['created']??'')!==$event['manager_created'])continue;if(!isset($s['connection_notifications'][$id]))$s['connection_notifications'][$id]=$event;}});});
}
