<?php
declare(strict_types=1);

// Payments before this release retain their existing reward policy.
const MR_STARTER_START = '2026-10-05T18:48:50+09:00';
const MR_STARTER_VERSION = 'first-five-v1';
const MR_STARTER_LIMIT = 5;
const MR_STARTER_FIRST = 10000;
const MR_STARTER_MONTHLY = 1000;
const MR_MONTHLY_MILEAGE = 1500;

/** Old ledger rows represented one 1,500 KRW unit. Do not rewrite historical records. */
function mr_mileage(array $reward):int {
    return isset($reward['mileage']) ? max(0,(int)$reward['mileage']) : 1500;
}
function mr_starter_manager_key(string $uid):string {return hash('sha256','manager|'.$uid);}
function mr_starter_keys(string $uid,array $member,array $state):array {
    $keys=['user:'.hash('sha256',$uid)];$info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
    foreach($state['address_book']??[] as $id=>$row){
        if(($row['linked_uid']??'')!==$uid||($row['linked_key']??'')!==mg_link_key($uid,$member))continue;
        $keys[]='draft:'.hash('sha256',(string)$id);
        if(empty($info['address']))$info['address']=$row['address']??'';
        if(empty($info['name']))$info['name']=$row['name']??'';
    }
    // Include the name: separate schools or tenants can share one address.
    $address=preg_replace('/\s+/u','',mb_strtolower(trim((string)($info['address']??''))));
    $name=preg_replace('/\s+/u','',mb_strtolower(trim((string)($info['name']??$member['nickname']??''))));
    if($address!==''&&$name!=='')$keys[]='building:'.hash('sha256',$address.'|'.$name);
    return array_values(array_unique($keys));
}
/** Called under the shared state lock when a verified payment is first registered. */
function mr_starter_reserve(array &$state,string $id,string $uid,array $member,string $manager,array $receipt,array $subscription):array {
    $normal=['reward_policy'=>'monthly-v1'];
    $paid=mr_timestamp((string)$receipt['at']);
    if(!$paid||$paid<mr_timestamp(MR_STARTER_START))return $normal;
    $payment=null;
    foreach($subscription['history']??[] as $h){
        if(empty($h['ok'])||($h['test']??null)!==false||!in_array($h['type']??'',['payment','renewal'],true))continue;
        if(($h['paymentKey']??'')===$receipt['payment_key'])$payment=$h;
        else return $normal; // A renewal or later purchase is never another welcome reward.
    }
    if(!$payment||($payment['type']??'')!=='payment')return $normal;
    foreach($state['monthly_rewards']??[] as $plan)if(($plan['user']??'')===$uid)return $normal;
    foreach($state['rewards']??[] as $reward)if(($reward['user']??'')===$uid)return $normal;
    $keys=mr_starter_keys($uid,$member,$state);
    foreach($keys as $key)if(isset($state['starter_claims'][$key]))return $normal;
    $managerKey=mr_starter_manager_key($manager);$slots=$state['starter_slots'][$managerKey]??[];
    if(count($slots)>=MR_STARTER_LIMIT)return $normal;
    $slot=count($slots)+1;
    $state['starter_slots'][$managerKey][$id]=['manager'=>$manager,'user'=>$uid,'payment_key'=>$receipt['payment_key'],'slot'=>$slot,'at'=>$receipt['at']];
    foreach($keys as $key)$state['starter_claims'][$key]=$id;
    // Claims and slots are permanent even after refunds, unlinking or account withdrawal.
    return ['reward_policy'=>MR_STARTER_VERSION,'starter_slot'=>$slot,'starter_keys'=>$keys];
}
function mr_facilities_ready(string $uid,array $member):bool {
    if(!sa_valid_uid($uid))return false;
    require_once __DIR__.'/building_facilities_common.php';
    $stored=mg_read(__DIR__.'/data/building/'.$uid.'/facilities.php');
    $updated=mr_timestamp((string)($stored['updated']??''));$created=mr_timestamp((string)($member['created']??''));
    if(!$updated||!$created||$updated<$created||empty($stored['revision']))return false;
    $aligned=bf_align($stored,bf_source($uid));$counts=bf_counts($aligned);
    return $counts['total']>0&&bf_complete($aligned);
}
/** null means no payable installment. Missing months are never made up later. */
function mr_installment(array $state,array $plan,array $event,string $month):?array {
    if(($plan['reward_policy']??'')!==MR_STARTER_VERSION)return ['mileage'=>MR_MONTHLY_MILEAGE,'reason'=>'monthly_worklog'];
    $start=mr_calendar_month((string)$plan['started_at']);
    $offset=((int)substr($month,0,4)-(int)substr($start,0,4))*12+(int)substr($month,5,2)-(int)substr($start,5,2);
    if($offset<0||$offset>=12)return null;
    $count=0;$total=0;
    foreach($state['rewards']??[] as $r)if(($r['payment_key']??'')===$plan['payment_key']&&($r['reward_policy']??'')===MR_STARTER_VERSION){$count++;$total+=mr_mileage($r);}
    if($count>=12||$total>=21000)return null;
    if($count===0){
        if(empty($event['facilities_confirmed']))return null;
        return ['mileage'=>MR_STARTER_FIRST,'reason'=>'starter_first'];
    }
    return ['mileage'=>MR_STARTER_MONTHLY,'reason'=>'starter_monthly'];
}
function mr_starter_summary(array $state,string $manager):array {
    $slots=$state['starter_slots'][mr_starter_manager_key($manager)]??[];$rewarded=0;
    foreach($slots as $slot){foreach($state['rewards']??[] as $r){
        if(($r['payment_key']??'')===($slot['payment_key']??'')&&($r['reason']??'')==='starter_first'){$rewarded++;break;}
    }}
    return ['limit'=>MR_STARTER_LIMIT,'reserved'=>count($slots),'remaining'=>max(0,MR_STARTER_LIMIT-count($slots)),'rewarded'=>$rewarded];
}
function mr_reward_label(array $r):string {
    return ['starter_first'=>'첫 시작 리워드','starter_monthly'=>'첫 시작 혜택 · 월 기록','monthly_worklog'=>'월 업무기록'][$r['reason']??'']??'기존 적립';
}
