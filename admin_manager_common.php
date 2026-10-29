<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

require_once __DIR__.'/manager_payout_common.php';
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/manager_subscription.php';

function am_is_admin():bool {
 return empty($_SESSION['_imp']) && empty($_SESSION['_manager_edit'])
  && (!empty($_SESSION['is_admin']) || ($_SESSION['ID_OK']??null)==1);
}
function am_require_admin():void {
 if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
 header('Cache-Control: private, no-store');header('X-Frame-Options: SAMEORIGIN');
 header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');
 if(!am_is_admin()){http_response_code(403);exit('사이트 관리자 계정으로 로그인해 주세요.');}
}
/** Counts belong to the current account generation; no synchronization or writes on a view. */
function am_manager_rows(array $members,array $state,?callable $subscription=null):array {
 $subscription=$subscription??'ms_subscription';$stats=[];
 foreach($members as $uid=>$m){
  if(!is_array($m)||($m['role']??'')!=='agency'||!sa_valid_uid((string)$uid))continue;
  $balance=mp_balance($state,(string)$uid,$m);$balance['held']=max(0,$balance['earned']-$balance['paid']);
  $stats[(string)$uid]=['uid'=>(string)$uid,'name'=>trim((string)($m['nickname']??''))?:(string)$uid,
   'code'=>(string)($m['manager_code']??''),'created'=>(string)($m['created']??''),'status'=>(string)($m['status']??'active'),
   'preregistered'=>0,'unlinked'=>0,'clients'=>0,'connected_only'=>0,'pro'=>0,'pro_live'=>0,'pro_test'=>0,'requests'=>0,
   'pending_count'=>0,'pending_amount'=>0,'balance'=>$balance,'starter'=>mr_starter_summary($state,(string)$uid),
   'account'=>!empty($state['payout_accounts'][mp_account_key((string)$uid,$m)])];
 }
 foreach($state['address_book']??[] as $r){
  if(!is_array($r))continue;$uid=(string)($r['manager']??'');
  if(!isset($stats[$uid])||!ma_owned($r,$uid,$members))continue;
  $stats[$uid]['preregistered']++;
  if(!ma_linked($r,$uid,$members,$state))$stats[$uid]['unlinked']++;
 }
 foreach($members as $id=>$m){
  if(!is_array($m)||!mg_active($m,'building')||!sa_valid_uid((string)$id))continue;
  $uid=mg_connection_manager($m,$members);if(!isset($stats[$uid]))continue;
  $status=mg_link_status((string)$id,$m,$state);
  if($status==='pending'){$stats[$uid]['requests']++;continue;}
  if($status!=='accepted')continue;
  $stats[$uid]['clients']++;$sub=$subscription((string)$id);
  if(!empty($sub['active'])){$stats[$uid]['pro']++;$stats[$uid][!empty($sub['test'])?'pro_test':'pro_live']++;}
  else $stats[$uid]['connected_only']++;
 }
 foreach($state['payouts']??[] as $r){
  if(!is_array($r))continue;$uid=(string)($r['manager']??'');
  if(!isset($stats[$uid])||!mp_owned($r,$uid,$members[$uid])||($r['status']??'')!=='pending')continue;
  $stats[$uid]['pending_count']++;$stats[$uid]['pending_amount']+=(int)($r['amount']??0);
 }
 uasort($stats,static fn($a,$b)=>strcmp($b['created'],$a['created'])?:strcmp($a['uid'],$b['uid']));
 return $stats;
}
function am_money(int $mileage):string {return number_format($mileage).'마일리지';}
function am_status(string $status):string {return ['active'=>'활성','pending'=>'승인 대기','blocked'=>'차단','suspended'=>'정지','deleted'=>'탈퇴'][$status]??$status;}
function am_payout_label(string $status):string {return ['pending'=>'처리 대기','paid'=>'지급 완료','rejected'=>'반려'][$status]??'상태 확인 필요';}
function am_json(array $data):string {return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);}
