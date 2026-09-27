<?php
declare(strict_types=1);
require_once __DIR__.'/manager_addresses_common.php';

// No member/state writes here: payment locks must not enter the manager write chain.
function ap_promo_file():string{return __DIR__.'/data/subscribe/_promotion_claims.php';}
function ap_has_paid(array $sub,string $mode):bool {
 foreach((array)($sub['history']??[]) as $r){
  if(!is_array($r)||!in_array($r['type']??'',['payment','renewal'],true)||empty($r['ok']))continue;
  if($mode==='live'&&($r['test']??false)===true)continue;
  return true;
 }
 return !empty($sub['manager_first_payment']['live'])||!empty($sub['promotion_used'][$mode]);
}
/** Repeat subscriptions qualify only while the enrollment promotion is open. */
function ap_promotion_open(?int $now=null):bool {
 $config=require __DIR__.'/promotion_config.php';$day=(string)($config['ends_on']??'');
 if($day==='')return true;
 $end=DateTimeImmutable::createFromFormat('!Y-m-d',$day,new DateTimeZone('Asia/Seoul'));
 if(!$end||$end->format('Y-m-d')!==$day)throw new RuntimeException('프로모션 종료일 설정을 확인해 주세요.');
 return ($now??time())<=$end->setTime(23,59,59)->getTimestamp();
}
function ap_claim_available(array $claim,string $uid,bool $repeat):bool {
 return !$claim||($repeat&&($claim['uid']??'')===$uid&&($claim['state']??'')==='used');
}
/** Local Korean calendar cutoff; existing retained prices do not use this cutoff. */
function ap_enrolled_in_window(array $member,array $row):bool {
 $config=require __DIR__.'/promotion_config.php';$day=(string)($config['ends_on']??'');
 if($day==='')return true;
 $tz=new DateTimeZone('Asia/Seoul');$end=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);
 if(!$end||$end->format('Y-m-d')!==$day)throw new RuntimeException('프로모션 종료일 설정을 확인해 주세요.');
 $limit=$end->setTime(23,59,59)->getTimestamp();
 foreach([$member['created']??'',$row['linked_at']??''] as $stamp){
  if(!is_string($stamp)||trim($stamp)==='')return false;
  try{$t=(new DateTimeImmutable($stamp,$tz))->getTimestamp();}catch(Throwable $e){return false;}
  if($t>$limit)return false;
 }
 return true;
}
/** An enabled scheduled renewal/retry continues the subscription; expiry with renewal off does not. */
function ap_price_retained(array $sub,string $mode,?int $now=null):bool {
 $lock=$sub['price_lock']??[];$now=$now??time();
 if(($lock['code']??'')!==AP_PROMOTION||($lock['mode']??'')!==$mode||($lock['state']??'')!=='active'||(int)($lock['amount']??0)!==AP_PROMO_PRICE)return false;
 if(!in_array($sub['status']??'',['active','payment_failed'],true)||in_array($sub['refund_attempt']['state']??'',['prepared','unknown','done'],true))return false;
 $end=ab_end($sub);if($end>$now)return true;
 // A pending scheduled collection is not a user cancellation. Old consents never authorize it.
 return $end>0&&$end+366*86400>$now&&ab_auto($sub)&&(int)($sub['renewal_consent']['price']??0)===AP_PROMO_PRICE&&(int)($sub['retry_count']??0)<3;
}
function ap_quote(string $uid,array $sub,string $kind='manual'):array {
 $q=['amount'=>AP_PRICE,'renewal_amount'=>AP_PRICE,'promotion'=>'','source'=>'','retain_price'=>false,'customer_type'=>''];
 $mode=ab_mode();$repeat=ap_promotion_open();
 if(ap_price_retained($sub,$mode)){
  $q['amount']=$q['renewal_amount']=AP_PROMO_PRICE;$q['retain_price']=true;$q['source']=(string)($sub['price_lock']['source']??'');$q['customer_type']=(string)($sub['price_lock']['customer_type']??'preregistered');
 }elseif($kind!=='renewal'&&($repeat||!ap_has_paid($sub,$mode))){
  $members=mg_members();$member=$members[$uid]??[];$actor=mg_connection_manager($member,$members);
  $state=mg_read(mg_state_file());$claims=ab_read_file(ap_promo_file());
  if($actor!==''&&mg_can_view($actor,$uid,$members,$state)){
   foreach($state['address_book']??[] as $id=>$row){
    if(!is_array($row)||($row['linked_uid']??'')!==$uid||!ma_owned($row,$actor,$members)||!ma_linked($row,$actor,$members,$state)||trim((string)($row['address']??''))==='')continue;
    if(!ap_enrolled_in_window($member,$row))continue;
    $source=hash('sha256',$actor.'|'.($row['manager_created']??'').'|'.$id);
    $uk=$mode.':user:'.hash('sha256',$uid);$sk=$mode.':source:'.$source;
    if(!ap_claim_available((array)($claims[$uk]??[]),$uid,$repeat)||!ap_claim_available((array)($claims[$sk]??[]),$uid,$repeat))continue;
    $q['amount']=$q['renewal_amount']=AP_PROMO_PRICE;$q['retain_price']=true;$q['promotion']=AP_PROMOTION;$q['source']=$source;$q['customer_type']='preregistered';break;
   }
   // A local manager's accepted connection is a separate qualifying route.
   $config=require __DIR__.'/promotion_config.php';
   $localCode=trim((string)($config['local_manager_code']??''));
   $connectedAt=(string)($state['links'][mg_link_key($uid,$member)]['at']??'');
   if($q['promotion']===''&&$localCode!==''&&hash_equals($localCode,(string)($members[$actor]['manager_code']??''))&&ap_enrolled_in_window($member,['linked_at'=>$connectedAt])){
    $source=hash('sha256','local|'.$actor.'|'.($members[$actor]['created']??'').'|'.$uid);
    $uk=$mode.':user:'.hash('sha256',$uid);$sk=$mode.':source:'.$source;
    if(ap_claim_available((array)($claims[$uk]??[]),$uid,$repeat)&&ap_claim_available((array)($claims[$sk]??[]),$uid,$repeat)){
     $q['amount']=$q['renewal_amount']=AP_PROMO_PRICE;$q['retain_price']=true;$q['promotion']=AP_PROMOTION;$q['source']=$source;$q['customer_type']='local_manager';
    }
   }
  }
 }
 $q['id']=hash('sha256',AP_OFFER.'|'.$mode.'|'.$uid.'|'.json_encode($q));return $q;
}
function ap_reserve(string $uid,array $attempt):void {
 if(empty($attempt['promotion']))return;
 mg_tx(ap_promo_file(),function(&$rows)use($uid,$attempt){
  $keys=[$attempt['mode'].':user:'.hash('sha256',$uid),$attempt['mode'].':source:'.$attempt['promotion_source']];
  foreach($keys as $key)if(!ap_claim_available((array)($rows[$key]??[]),$uid,ap_promotion_open())&&(($rows[$key]['order_id']??'')!==$attempt['order_id']||($rows[$key]['uid']??'')!==$uid))throw new RuntimeException('프로모션 사용 상태가 변경되었습니다. 새로고침 후 금액을 확인해 주세요.');
  foreach($keys as $key)$rows[$key]=['uid'=>$uid,'order_id'=>$attempt['order_id'],'state'=>'reserved','at'=>date('c')];
 },true);
}
function ap_finish(string $uid,array $attempt,bool $paid):void {
 if(empty($attempt['promotion']))return;
 mg_tx(ap_promo_file(),function(&$rows)use($uid,$attempt,$paid){
  foreach([$attempt['mode'].':user:'.hash('sha256',$uid),$attempt['mode'].':source:'.$attempt['promotion_source']] as $key){
   if((($rows[$key]['order_id']??'')!==$attempt['order_id']||($rows[$key]['uid']??'')!==$uid))throw new RuntimeException('프로모션 결제 기록 확인이 필요합니다.');
   if($paid){$rows[$key]['state']='used';$rows[$key]['paid_at']=date('c');}
   elseif(($rows[$key]['state']??'')==='reserved')unset($rows[$key]);
  }
 },true);
}
