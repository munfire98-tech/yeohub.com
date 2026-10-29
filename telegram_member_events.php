<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/telegram_config.php';
function tme_source(string $uid,array $member):string {
 if(($member['joined_via']??'')==='manager_qr')return '파트너 QR 가입';
 return str_starts_with($uid,'kakao_')||($member['login_type']??'')==='kakao'?'카카오 가입':'일반(자체) 가입';
}
function tme_message(string $title,string $uid,array $member,array $members,array $extra=[]):string {
 $manager=mg_connection_manager($member,$members);
 $lines=[$title,'아이디: '.$uid,'건물·이름: '.($member['nickname']??$uid),'가입 경로: '.tme_source($uid,$member)];
 if($manager!=='')$lines[]='연결 업체: '.($members[$manager]['nickname']??$manager).' ('.$manager.')';
 foreach($extra as $key=>$value)$lines[]=$key.': '.$value;
 $lines[]='알림 시각: '.(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s');
 return implode("\n",array_map(static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$lines));
}
/** Independent event locks do not hold member, manager-state or payment locks during HTTP. */
function tme_send(string $key,string $message,?callable $sender=null):void {
 try{
  $file=__DIR__.'/data/telegram_events/'.hash('sha256',$key).'.php';
  mg_tx($file,function(array &$event)use($message,$sender){
   if(!empty($event['sent_at']))return;
   $event['attempted_at']=date('c');$event['attempts']=(int)($event['attempts']??0)+1;
   try{$ok=$sender?$sender($message):send_telegram($message);}catch(Throwable $e){$ok=false;}
   if($ok)$event['sent_at']=date('c');
   else error_log('Telegram member event delivery failed; event was not marked sent');
  },true);
 }catch(Throwable $e){error_log('Telegram member event could not be recorded or delivered');}
}
function tme_signup(string $uid):void {
 try{$members=mg_members();$m=$members[$uid]??[];if(!$m)return;
  tme_send('signup:'.$uid.':'.($m['created']??''),tme_message('새 회원 가입',$uid,$m,$members));
 }catch(Throwable $e){error_log('Telegram signup notification failed');}
}
function tme_withdraw(string $uid,array $record):void {
 try{tme_send('withdraw:'.$uid.':'.($record['created']??''),tme_message('회원 탈퇴',$uid,$record,mg_members()));}
 catch(Throwable $e){error_log('Telegram withdrawal notification failed');}
}
function tme_payment(string $uid,string $orderId):void {
 try{
  if($orderId==='')return;
  $members=mg_members();$m=$members[$uid]??[];if(!$m)return;
  $sub=sa_read($uid);
  foreach($sub['history']??[] as $r){
   if(($r['orderId']??'')!==$orderId||empty($r['ok'])||!in_array($r['type']??'',['payment','renewal'],true))continue;
   $title=(!empty($r['test'])?'[테스트] ':'').(($r['type']??'')==='renewal'?'PRO 구독 갱신 완료':'PRO 구독 결제 완료');
   tme_send('payment:'.$orderId,tme_message($title,$uid,$m,$members,['금액'=>number_format((int)($r['amount']??0)).'원','결제 시각'=>$r['at']??'']));return;
  }
 }catch(Throwable $e){error_log('Telegram subscription notification failed');}
}
