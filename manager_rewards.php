<?php
declare(strict_types=1);
/** Server-only monthly reward ledger. All amounts are KRW. */
const MR_COIN_VALUE = 1500;
function mr_month_at(string $start,int $month):string {
    $base=new DateTimeImmutable($start,new DateTimeZone('Asia/Seoul'));
    $target=$base->modify('first day of this month')->modify('+'.$month.' months');
    return $target->setDate((int)$target->format('Y'),(int)$target->format('m'),min((int)$base->format('d'),(int)$target->format('t')))->format(DATE_ATOM);
}
function mr_award(string $uid,array $receipt):void {
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||($receipt['live']??false)!==true||($receipt['status']??'')!=='DONE'||!in_array((int)($receipt['amount']??0),[59000,69000],true)||empty($receipt['payment_key'])||empty($receipt['order_id'])||mr_timestamp((string)($receipt['at']??''))===false)return;
    mg_member_tx(function(array &$members)use($uid,$receipt){
        $m=$members[$uid]??[];$manager=mg_connection_manager($m,$members);
        if(!mg_active($m,'building')||$manager==='')return;
        mg_state_tx(function(array &$s)use($uid,$receipt,$members,$m,$manager){
            $pk=(string)$receipt['payment_key'];$id=hash('sha256',$pk);
            if(isset($s['refunds'][$pk]))return;
            if(!isset($s['monthly_rewards'][$id])){
                if(!mg_can_view($manager,$uid,$members,$s))return;
                $link=$s['links'][mg_link_key($uid,$m)]??[];
                // Do not award a subscription purchased before this manager's accepted connection.
                if(mr_timestamp((string)($link['at']??''))>mr_timestamp($receipt['at']))return;
                $s['monthly_rewards'][$id]=['user'=>$uid,'user_created'=>(string)($m['created']??''),'manager'=>$manager,'manager_created'=>(string)($members[$manager]['created']??''),'connection_key'=>mg_link_key($uid,$m),'payment_key'=>$pk,'order_id'=>$receipt['order_id'],'started_at'=>$receipt['at'],'closed_at'=>null];

            }
        });
    });
    mr_accrue($uid);
}
function mr_accrue(string $uid,?int $now=null):void {
    $now=$now??time();
    mg_member_tx(function(array &$members)use($uid,$now){
        $m=$members[$uid]??[];$sub=mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
        mg_state_tx(function(array &$s)use($uid,$now,$m,$members,$sub){
            foreach($s['monthly_rewards']??[] as $id=>$plan){
                if(($plan['user']??'')!==$uid||!empty($plan['closed_at']))continue;
                $manager=$plan['manager'];$pk=$plan['payment_key'];
                if(isset($s['refunds'][$pk])){$s['monthly_rewards'][$id]['closed_at']=date(DATE_ATOM,$now);continue;}
                if(!mg_active($m,'building')||($m['created']??'')!==$plan['user_created']||($members[$manager]['created']??'')!==$plan['manager_created']||!mg_can_view($manager,$uid,$members,$s)||mg_link_key($uid,$m)!==$plan['connection_key']){$s['monthly_rewards'][$id]['closed_at']=date(DATE_ATOM,$now);continue;}
                // Any refunded/cancelled subscription stops further rewards; renewal-off alone does not.
                if(in_array($sub['status']??'',['refunded','canceled'],true)){$s['monthly_rewards'][$id]['closed_at']=date(DATE_ATOM,$now);continue;}
                $workUid=preg_replace('/[^A-Za-z0-9_]/','_',$uid);
                foreach(glob(__DIR__.'/data/worklog/'.$workUid.'/m????-??.json')?:[] as $file){
                    $record=mg_read($file);
                    $event=$record['_reward_submissions'][hash('sha256',$pk)]??[];
                    $month=substr(basename($file),1,7);
                    if(!mr_valid_submission($event,$plan,$month,$now))continue;
                    $exists=false;
                    foreach($s['rewards']??[] as $reward){
                        if(($reward['user']??'')!==$uid)continue;
                        if(isset($reward['user_created'])&&$reward['user_created']!==$plan['user_created'])continue;
                        $rewardMonth=(string)($reward['worklog_month']??mr_calendar_month((string)($reward['at']??'')));
                        if($rewardMonth===$month){$exists=true;break;}
                    }
                    if($exists)continue;
                    $key='worklog_'.hash('sha256',$uid.'|'.$plan['user_created'].'|'.$month);
                    $s['rewards'][$key]=['user'=>$uid,'user_created'=>$plan['user_created'],'manager'=>$manager,'manager_created'=>$plan['manager_created'],'payment_key'=>$pk,'order_id'=>$plan['order_id'],'unit_value'=>MR_COIN_VALUE,'worklog_month'=>$month,'reason'=>'monthly_worklog','at'=>$event['at'],'posted_at'=>date(DATE_ATOM,$now),'reversed'=>false];
                }
            }
        });
    });
}
function mr_reconcile(string $uid):void {
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return;
    $d=mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
    foreach($d['manager_full_refunds']??[] as $pk)mg_reverse($uid,(string)$pk);
    foreach((array)($d['history']??[]) as $h){
        if(!is_array($h)||($h['test']??null)!==false||empty($h['ok'])||!in_array($h['type']??'',['payment','renewal',''],true))continue;
        mr_award($uid,['live'=>true,'status'=>'DONE','amount'=>$h['amount']??0,'payment_key'=>$h['paymentKey']??'','order_id'=>$h['orderId']??'','at'=>$h['at']??'']);
    }
    if(!empty($d['manager_first_payment']))mr_award($uid,$d['manager_first_payment']);
    mr_accrue($uid);
}
function mr_sync_manager(string $actor):void {
    $members=mg_members();$state=mg_read(mg_state_file());$uids=[];
    foreach($members as $uid=>$m)if(is_array($m)&&mg_active($m,'building')&&mg_connection_manager($m,$members)===$actor)$uids[(string)$uid]=true;
    foreach($state['monthly_rewards']??[] as $r)if(($r['manager']??'')===$actor)$uids[$r['user']]=true;
    foreach(array_keys($uids) as $uid)mr_reconcile($uid);
}

/** Calendar months always use Korea time, independent of the PHP host timezone. */
function mr_calendar_month(string $at):string {
    if(trim($at)==='')return '';
    try{return (new DateTimeImmutable($at,new DateTimeZone('Asia/Seoul')))->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m');}catch(Throwable $e){return '';}
}
function mr_worklog_complete(array $record,string $month):bool {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)($record['date']??''),new DateTimeZone('Asia/Seoul'));
    if(!$date||$date->format('Y-m-d')!==($record['date']??'')||$date->format('Y-m')!==$month||trim((string)($record['performer']??''))==='')return false;
    foreach(['sobang','pinan','hwagi','etc'] as $key){
        if(trim((string)($record[$key]['note']??''))!==''&&in_array($record[$key]['result']??'',['양호','불량'],true))return true;
    }
    return false;
}
/** Only the authenticated user-save path calls this; metadata is never copied from POST. */
function mr_worklog_stamp(string $uid,string $month,array $record,array $previous,?int $now=null):array {
    $events=(array)($previous['_reward_submissions']??[]);$now=$now??time();
    if($month!==mr_calendar_month('@'.$now)||!mr_worklog_complete($record,$month))return $events;
    $members=mg_members();$member=$members[$uid]??[];$manager=mg_connection_manager($member,$members);$state=mg_read(mg_state_file());
    if(!mg_active($member,'building')||$manager===''||!mg_can_view($manager,$uid,$members,$state))return $events;
    $sub=mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
    $end=mr_timestamp((string)($sub['expires_at']??$sub['next_billing']??''));
    if(($sub['status']??'')!=='active'||!$end||$end<=$now||in_array($sub['refund_attempt']['state']??'',['prepared','unknown','done'],true))return $events;
    $pk=(string)($sub['last_payment_key']??'');$payment=[];
    foreach((array)($sub['history']??[]) as $h){if(($h['paymentKey']??'')===$pk&&$pk!==''&&($h['test']??null)===false&&!empty($h['ok'])&&in_array($h['type']??'',['payment','renewal'],true))$payment=$h;}
    if(!$payment||isset($state['refunds'][$pk]))return $events;
    $paid=mr_timestamp((string)($payment['at']??''));if(!$paid||$paid>$now)return $events;
    $id=hash('sha256',$pk);
    if(!isset($events[$id])||($events[$id]['user_created']??'')!==($member['created']??'')){
        $events[$id]=['month'=>$month,'at'=>(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Asia/Seoul'))->format(DATE_ATOM),'user_created'=>(string)($member['created']??''),'payment_key'=>$pk,'subscription_end'=>$end,'connection_key'=>mg_link_key($uid,$member),'manager'=>$manager];
    }
    return $events;
}
function mr_valid_submission(array $event,array $plan,string $month,int $now):bool {
    if(!$event||($event['month']??'')!==$month||($event['payment_key']??'')!==$plan['payment_key']||($event['user_created']??'')!==$plan['user_created']||($event['connection_key']??'')!==$plan['connection_key']||($event['manager']??'')!==$plan['manager'])return false;
    $at=mr_timestamp((string)($event['at']??''));$start=mr_timestamp($plan['started_at']);
    return $at!==false&&$start!==false&&$at>=$start&&$at<=(int)$now&&$at<(int)($event['subscription_end']??0)&&mr_calendar_month((string)$event['at'])===$month;
}

function mr_timestamp(string $at) {
    if(trim($at)==='')return false;
    try{return (new DateTimeImmutable($at,new DateTimeZone('Asia/Seoul')))->getTimestamp();}catch(Throwable $e){return false;}
}
