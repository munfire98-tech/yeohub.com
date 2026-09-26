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
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||($receipt['live']??false)!==true||($receipt['status']??'')!=='DONE'||(int)($receipt['amount']??0)!==59000||empty($receipt['payment_key'])||empty($receipt['order_id'])||strtotime((string)($receipt['at']??''))===false)return;
    mg_member_tx(function(array &$members)use($uid,$receipt){
        $m=$members[$uid]??[];$manager=mg_connection_manager($m,$members);
        if(!mg_active($m,'building')||$manager==='')return;
        mg_state_tx(function(array &$s)use($uid,$receipt,$members,$m,$manager){
            $pk=(string)$receipt['payment_key'];$id=hash('sha256',$pk);
            if(isset($s['refunds'][$pk]))return;
            if(!isset($s['monthly_rewards'][$id])){
                // A duplicate annual payment must not create overlapping reward years.
                $start=date('Y-m-d',strtotime($receipt['at']));$end=substr(mr_month_at($receipt['at'],12),0,10);
                foreach($s['monthly_rewards']??[] as $existing){
                    if(($existing['user']??'')!==$uid||($existing['user_created']??'')!==(string)($m['created']??''))continue;
                    if($start<substr(mr_month_at($existing['started_at'],12),0,10)&&$end>date('Y-m-d',strtotime($existing['started_at'])))return;
                }

                if(!mg_can_view($manager,$uid,$members,$s))return;
                $link=$s['links'][mg_link_key($uid,$m)]??[];
                // Do not award a subscription purchased before this manager's accepted connection.
                if(strtotime((string)($link['at']??''))>strtotime($receipt['at']))return;
                $s['monthly_rewards'][$id]=['user'=>$uid,'user_created'=>(string)($m['created']??''),'manager'=>$manager,'manager_created'=>(string)($members[$manager]['created']??''),'connection_key'=>mg_link_key($uid,$m),'payment_key'=>$pk,'order_id'=>$receipt['order_id'],'started_at'=>$receipt['at'],'closed_at'=>null];
                // Existing single coin is installment 1, not an additional coin.
                foreach($s['rewards']??[] as $key=>$r){if(($r['payment_key']??'')===$pk){$s['rewards'][$key]['user']=$uid;$s['rewards'][$key]['installment']=1;$s['rewards'][$key]['unit_value']=MR_COIN_VALUE;break;}}
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
                for($i=0;$i<12;$i++){
                    $due=mr_month_at($plan['started_at'],$i);if(strtotime($due)>$now)break;
                    $exists=false;foreach($s['rewards']??[] as $r){if(($r['payment_key']??'')===$pk&&(int)($r['installment']??1)===$i+1){$exists=true;break;}}
                    if($exists)continue;
                    $key='monthly_'.hash('sha256',$pk.':'.$i);
                    $s['rewards'][$key]=['user'=>$uid,'manager'=>$manager,'manager_created'=>$plan['manager_created'],'payment_key'=>$pk,'order_id'=>$plan['order_id'],'installment'=>$i+1,'unit_value'=>MR_COIN_VALUE,'at'=>$due,'posted_at'=>date(DATE_ATOM,$now),'reversed'=>false];
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
