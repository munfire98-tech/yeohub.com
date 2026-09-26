<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
const MP_RATE=1500;
function mp_migrate_rate():void {
 mg_state_tx(function(array &$s){
  if(($s['coin_rate_version']??'')==='monthly1500-v1')return;
  foreach($s['payouts']??[] as $id=>$r){
   if(($r['status']??'')==='pending'&&(int)($r['amount']??0)!==(int)$r['coins']*MP_RATE){
    $s['payouts'][$id]['status']='rejected';$s['payouts'][$id]['processed_at']=date('c');
    $s['payouts'][$id]['processed_by']='rate-migration';$s['payouts'][$id]['note']='1코인 1,500원 기준으로 변경되었습니다. 새 기준으로 다시 신청해 주세요.';
   }
  }
  $s['coin_rate_version']='monthly1500-v1';
 });
}
function mp_owned(array $r,string $uid,array $me):bool{return ($r['manager']??'')===$uid&&($r['manager_created']??'')===(string)($me['created']??'');}
function mp_balance(array $s,string $uid,array $me):array{
 $earned=0;$pending=0;$paid=0;
 foreach($s['rewards']??[] as $r)if(mp_owned($r,$uid,$me)&&empty($r['reversed']))$earned++;
 foreach($s['payouts']??[] as $r)if(mp_owned($r,$uid,$me)){if($r['status']==='pending')$pending+=(int)$r['coins'];if($r['status']==='paid')$paid+=(int)$r['coins'];}
 return ['earned'=>$earned,'pending'=>$pending,'paid'=>$paid,'available'=>max(0,$earned-$pending-$paid),'deficit'=>max(0,$pending+$paid-$earned)];
}
function mp_account_key(string $uid,array $me):string{return hash('sha256',$uid.':'.($me['created']??''));}
function mp_mask(string $s):string{return str_repeat('•',max(0,strlen($s)-4)).substr($s,-4);}
function mp_snapshot(string $uid,array $me):array{
 mp_migrate_rate();mr_sync_manager($uid);
 $s=mg_read(mg_state_file());$a=$s['payout_accounts'][mp_account_key($uid,$me)]??null;$rows=[];
 foreach($s['payouts']??[] as $r)if(mp_owned($r,$uid,$me)){$r['account']['number']=mp_mask($r['account']['number']);unset($r['manager_created']);$rows[]=$r;}
 usort($rows,fn($a,$b)=>strcmp($b['at'],$a['at']));if($a)$a['number']=mp_mask($a['number']);
 $plans=[];
 foreach($s['monthly_rewards']??[] as $plan){
  if(!mp_owned($plan,$uid,$me))continue;$paidMonths=0;
  foreach($s['rewards']??[] as $reward)if(($reward['payment_key']??'')===$plan['payment_key']&&empty($reward['reversed']))$paidMonths++;
  $plans[]=['user'=>$plan['user'],'started_at'=>substr($plan['started_at'],0,10),'issued'=>$paidMonths,'total'=>12,'stopped'=>!empty($plan['closed_at']),'next_at'=>empty($plan['closed_at'])&&$paidMonths<12?substr(mr_month_at($plan['started_at'],$paidMonths),0,10):''];
 }
 return ['ok'=>true,'reward_plans'=>$plans,'rate'=>MP_RATE,'balance'=>mp_balance($s,$uid,$me),'account'=>$a,'requests'=>$rows];
}
function mp_apply(string $uid,string $action,array $input):void{
 mp_migrate_rate();mr_sync_manager($uid);
 mg_member_tx(function(array &$members)use($uid,$action,$input){$me=$members[$uid]??[];if(!mg_active($me,'agency'))throw new RuntimeException('매니저 계정에서 이용해 주세요.');
 mg_state_tx(function(array &$s)use($uid,$me,$action,$input){$key=mp_account_key($uid,$me);
 if($action==='account'){
  $bank=trim((string)($input['bank']??''));$holder=trim((string)($input['holder']??''));$number=preg_replace('/[\s-]/','',(string)($input['number']??''));
  if($bank===''||mb_strlen($bank)>40||$holder===''||mb_strlen($holder)>60||!preg_match('/^[0-9]{6,20}$/D',$number)||preg_match('/[\x00-\x1f<>]/u',$bank.$holder))throw new RuntimeException('은행명·예금주·계좌번호를 확인해 주세요.');
  $s['payout_accounts'][$key]=['bank'=>$bank,'holder'=>$holder,'number'=>$number,'updated'=>date('c')];return;
 }
 if($action!=='request')throw new RuntimeException('지원하지 않는 요청입니다.');
 $coins=filter_var($input['coins']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
 $token=(string)($input['token']??'');if(!preg_match('/^[a-f0-9]{32,64}$/D',$token))throw new RuntimeException('새로고침 후 다시 신청해 주세요.');
 $id=hash('sha256',$key.':'.$token);if(isset($s['payouts'][$id]))return;
 $a=$s['payout_accounts'][$key]??null;if(!$a)throw new RuntimeException('먼저 계좌정보를 저장해 주세요.');
 $b=mp_balance($s,$uid,$me);if(!$coins||$coins>$b['available'])throw new RuntimeException('출금 가능한 코인이 부족합니다.');
 $s['payouts'][$id]=['id'=>$id,'manager'=>$uid,'manager_created'=>(string)($me['created']??''),'name'=>(string)($me['nickname']??$uid),'coins'=>$coins,'amount'=>$coins*MP_RATE,'account'=>$a,'status'=>'pending','at'=>date('c'),'processed_at'=>null,'note'=>''];
 });});
}
function mp_process(string $admin,string $id,string $decision,string $note):void{
 mp_migrate_rate();
 $existing=mg_read(mg_state_file())['payouts'][$id]??[];if(!empty($existing['manager']))mr_sync_manager((string)$existing['manager']);
 if(!in_array($decision,['paid','rejected'],true)||mb_strlen($note)>200)throw new RuntimeException('처리 내용을 확인해 주세요.');
 mg_member_tx(function(array &$members)use($admin,$id,$decision,$note){mg_state_tx(function(array &$s)use($members,$admin,$id,$decision,$note){
  $r=$s['payouts'][$id]??null;if(!$r||$r['status']!=='pending')throw new RuntimeException('이미 처리되었거나 없는 신청입니다.');
  if($decision==='paid'){
   $me=$members[$r['manager']]??[];if(!mg_active($me,'agency')||!mp_owned($r,$r['manager'],$me))throw new RuntimeException('매니저 계정 상태를 확인해 주세요.');
   if(mp_balance($s,$r['manager'],$me)['deficit']>0)throw new RuntimeException('환불 등으로 코인이 부족해졌습니다. 송금하지 말고 신청을 검토해 주세요.');
  }
  if($decision==='rejected'&&trim($note)==='')throw new RuntimeException('반려 사유를 입력해 주세요.');
  $s['payouts'][$id]['status']=$decision;$s['payouts'][$id]['processed_at']=date('c');$s['payouts'][$id]['processed_by']=$admin;$s['payouts'][$id]['note']=$note;
 });});
}
