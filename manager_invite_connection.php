<?php
declare(strict_types=1);
require_once __DIR__.'/manager_addresses_common.php';

/** A consumed QR is ready only after the member commit and current connection agree. */
function iq_connection_status(string $actor,string $hash,array $members,array $state,int $now):array {
 $i=$state['qr_invites'][$hash]??[];
 if(!mg_active($members[$actor]??[],'agency')||($i['manager']??'')!==$actor||($i['manager_created']??'')!==($members[$actor]['created']??null))throw new RuntimeException('초대 정보를 확인할 수 없습니다.');
 $result=['used'=>!empty($i['used_at']),'expired'=>($i['expires']??0)<=$now||!empty($i['revoked_at']),'connected'=>false,'pending'=>false];
 if(!$result['used'])return $result;
 $uid=(string)($i['used_uid']??'');
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return $result;
 $m=$members[$uid]??[];
 if(!$m){
  $claim=$i['claim_member']??[];
  $result['pending']=($claim['userid']??'')===$uid&&($claim['invite_id']??'')===$hash;
  return $result;
 }
 $row=$state['address_book'][(string)($i['draft_id']??'')]??[];
 $key=mg_link_key($uid,$m);
 if(($m['invite_id']??'')!==$hash||!ma_owned($row,$actor,$members)||($row['linked_uid']??'')!==$uid||($row['linked_key']??'')!==$key||!mg_can_view($actor,$uid,$members,$state))return $result;
 $result['connected']=true;
 return $result+['uid'=>$uid,'connection_key'=>$key,'building'=>(string)($row['name']??$m['nickname']??$uid)];
}
