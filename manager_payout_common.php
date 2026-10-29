<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
// Every current balance is an integer mileage amount; 1 mileage = 1 KRW.
const MP_RATE=1;
function mp_migrate_rate():void { /* Legacy callers remain safe; history is read without rewriting. */ }
function mp_payout_mileage(array $row):int {
 if(isset($row['mileage']))return max(0,(int)$row['mileage']);
 if(isset($row['amount']))return max(0,(int)$row['amount']);
 return max(0,(int)($row['coins']??0))*1500;
}
function mp_owned(array $r,string $uid,array $me):bool{return ($r['manager']??'')===$uid&&($r['manager_created']??'')===(string)($me['created']??'');}
function mp_balance(array $s,string $uid,array $me):array{
 $earned=0;$pending=0;$paid=0;
 foreach($s['rewards']??[] as $r)if(mp_owned($r,$uid,$me)&&empty($r['reversed']))$earned+=mr_mileage($r);
 foreach($s['payouts']??[] as $r)if(mp_owned($r,$uid,$me)){if($r['status']==='pending')$pending+=mp_payout_mileage($r);if($r['status']==='paid')$paid+=mp_payout_mileage($r);}
 return ['earned'=>$earned,'pending'=>$pending,'paid'=>$paid,'available'=>max(0,$earned-$pending-$paid),'deficit'=>max(0,$pending+$paid-$earned)];
}
function mp_account_key(string $uid,array $me):string{return hash('sha256',$uid.':'.($me['created']??''));}
function mp_mask(string $s):string{return str_repeat('•',max(0,strlen($s)-4)).substr($s,-4);}
function mp_snapshot(string $uid,array $me):array{
 mr_sync_manager($uid);
 $s=mg_read(mg_state_file());$a=$s['payout_accounts'][mp_account_key($uid,$me)]??null;$rows=[];
 foreach($s['payouts']??[] as $r)if(mp_owned($r,$uid,$me)){
  $r['account']['number']=mp_mask((string)($r['account']['number']??''));$r['mileage']=mp_payout_mileage($r);
  unset($r['manager_created'],$r['coins']);$rows[]=$r;
 }
 usort($rows,fn($a,$b)=>strcmp($b['at'],$a['at']));if($a)$a['number']=mp_mask($a['number']);
 $planMembers=mg_members();$plans=[];$currentMonth=mr_calendar_month('@'.time());
 foreach($s['monthly_rewards']??[] as $plan){
  if(!mp_owned($plan,$uid,$me))continue;
  $user=(string)$plan['user'];$identity=$user.'|'.($plan['user_created']??'');
  if(!isset($plans[$identity]))$plans[$identity]=['user'=>$user,'user_created'=>$plan['user_created']??'','issued'=>0,'mileage'=>0,'stopped'=>true,'current_month'=>$currentMonth,'current_issued'=>false,'first_issued'=>false,'reward_policy'=>'monthly-v1'];
  $sub=sa_read($user);
  if(empty($plan['closed_at'])&&($sub['status']??'')==='active'&&(mr_timestamp((string)($sub['expires_at']??$sub['next_billing']??''))?:0)>time()&&mg_can_view($uid,$user,$planMembers,$s)&&($plan['payment_key']??'')===($sub['last_payment_key']??'')){
   $plans[$identity]['stopped']=false;$plans[$identity]['reward_policy']=$plan['reward_policy']??'monthly-v1';
   $plans[$identity]['starter_slot']=$plan['starter_slot']??null;
   $plans[$identity]['facilities_ready']=mr_facilities_ready($user,$planMembers[$user]??[]);
  }
 }
 foreach($plans as &$display){
  foreach($s['rewards']??[] as $reward){
   if(!mp_owned($reward,$uid,$me)||($reward['user']??'')!==$display['user']||isset($reward['user_created'])&&$reward['user_created']!==$display['user_created']||!empty($reward['reversed']))continue;
   $display['issued']++;$display['mileage']+=mr_mileage($reward);
   if(($reward['reason']??'')==='starter_first')$display['first_issued']=true;
   if(($reward['worklog_month']??mr_calendar_month((string)($reward['at']??'')))===$currentMonth)$display['current_issued']=true;
  }
  unset($display['user_created']);
 }unset($display);
 return ['ok'=>true,'unit'=>'mileage-v1','reward_plans'=>array_values($plans),'starter'=>mr_starter_summary($s,$uid),'rate'=>MP_RATE,'balance'=>mp_balance($s,$uid,$me),'account'=>$a,'requests'=>$rows];
}
function mp_apply(string $uid,string $action,array $input):void{
 mp_migrate_rate();mr_sync_manager($uid);
 mg_member_tx(function(array &$members)use($uid,$action,$input){$me=$members[$uid]??[];if(!mg_active($me,'agency'))throw new RuntimeException('파트너 계정에서 이용해 주세요.');
 mg_state_tx(function(array &$s)use($uid,$me,$action,$input){$key=mp_account_key($uid,$me);
 if($action==='account'){
  $bank=trim((string)($input['bank']??''));$holder=trim((string)($input['holder']??''));$number=preg_replace('/[\s-]/','',(string)($input['number']??''));
  if($bank===''||mb_strlen($bank)>40||$holder===''||mb_strlen($holder)>60||!preg_match('/^[0-9]{6,20}$/D',$number)||preg_match('/[\x00-\x1f<>]/u',$bank.$holder))throw new RuntimeException('은행명·예금주·계좌번호를 확인해 주세요.');
  $s['payout_accounts'][$key]=['bank'=>$bank,'holder'=>$holder,'number'=>$number,'updated'=>date('c')];return;
 }
 if($action!=='request')throw new RuntimeException('지원하지 않는 요청입니다.');
 if(($input['unit']??'')!=='mileage-v1')throw new RuntimeException('마일리지 표시가 변경되었습니다. 화면을 새로고침한 후 다시 신청해 주세요.');
 $mileage=filter_var($input['mileage']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>150000000]]);
 $token=(string)($input['token']??'');if(!preg_match('/^[a-f0-9]{32,64}$/D',$token))throw new RuntimeException('새로고침 후 다시 신청해 주세요.');
 $id=hash('sha256',$key.':'.$token);if(isset($s['payouts'][$id]))return;
 $a=$s['payout_accounts'][$key]??null;if(!$a)throw new RuntimeException('먼저 계좌정보를 저장해 주세요.');
 $b=mp_balance($s,$uid,$me);if(!$mileage||$mileage>$b['available'])throw new RuntimeException('출금 가능한 마일리지가 부족합니다.');
 $s['payouts'][$id]=['id'=>$id,'manager'=>$uid,'manager_created'=>(string)($me['created']??''),'name'=>(string)($me['nickname']??$uid),'mileage'=>$mileage,'unit'=>'mileage-v1','amount'=>$mileage,'account'=>$a,'status'=>'pending','at'=>date('c'),'processed_at'=>null,'note'=>''];
 });});
}
function mp_process(string $admin,string $id,string $decision,string $note):void{
 mp_migrate_rate();
 $existing=mg_read(mg_state_file())['payouts'][$id]??[];if(!empty($existing['manager']))mr_sync_manager((string)$existing['manager']);
 if(!in_array($decision,['paid','rejected'],true)||mb_strlen($note)>200)throw new RuntimeException('처리 내용을 확인해 주세요.');
 mg_member_tx(function(array &$members)use($admin,$id,$decision,$note){mg_state_tx(function(array &$s)use($members,$admin,$id,$decision,$note){
  $r=$s['payouts'][$id]??null;if(!$r||$r['status']!=='pending')throw new RuntimeException('이미 처리되었거나 없는 신청입니다.');
  if($decision==='paid'){
   $me=$members[$r['manager']]??[];if(!mg_active($me,'agency')||!mp_owned($r,$r['manager'],$me))throw new RuntimeException('파트너 계정 상태를 확인해 주세요.');
   if(mp_balance($s,$r['manager'],$me)['deficit']>0)throw new RuntimeException('환불 등으로 마일리지가 부족해졌습니다. 송금하지 말고 신청을 검토해 주세요.');
  }
  if($decision==='rejected'&&trim($note)==='')throw new RuntimeException('반려 사유를 입력해 주세요.');
  $s['payouts'][$id]['status']=$decision;$s['payouts'][$id]['processed_at']=date('c');$s['payouts'][$id]['processed_by']=$admin;$s['payouts'][$id]['note']=$note;
 });});
}
