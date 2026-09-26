<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
/** Invoked only after the plan endpoint validates ownership, CSRF, schema and answers. */
function mfp_save(string $uid,string $plan,string $code,array $patch,callable $save):bool {
 $actor=(string)($_SESSION['_mge_actor']??'');
 if($actor===''&&mg_uid()!==$uid)return $save();
 return mg_member_tx(function(array &$members)use($uid,$actor,$plan,$code,$patch,$save){
  if(!mg_active($members[$uid]??[],'building'))throw new RuntimeException('유저 계정을 확인해 주세요.');
  return mg_state_tx(function(array &$state)use($members,$uid,$actor,$plan,$code,$patch,$save){
   if($actor!==''&&(!mg_active($members[$actor]??[],'agency')||!mg_can_view($actor,$uid,$members,$state)))throw new RuntimeException('담당 유저 연결을 확인해 주세요.');
   return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$uid,$actor,$plan,$code,$patch,$save){
    if(!$save())return false;
    $fields=[];foreach(array_keys($patch) as $key)$fields[]='__fp_'.$plan.'_'.$code.'_'.$key;
    foreach($rows as &$row){
     if(($row['uid']??'')!==$uid||($row['status']??'')!=='pending'||!in_array($row['field']??'',$fields,true)
       ||($row['user_created']??null)!==($members[$uid]['created']??null)||($row['link_key']??'')!==mg_link_key($uid,$members[$uid]))continue;
     if($actor!==''&&(($row['manager']??'')!==$actor||($row['manager_created']??null)!==($members[$actor]['created']??null)))continue;
     $row['status']='resolved';$row['resolved_at']=date('c');$row['resolved_by']=$actor?:$uid;$row['resolved_kind']=$actor!==''?'manager':'user';
     $row['reply']=($actor!==''?'담당 매니저가':'유저가').' 요청한 소방계획서 답변을 저장했습니다.';
    }unset($row);return true;
   },true);
  });
 });
}

function mfp_pending(string $uid,string $plan):array {
 $members=mg_members();$state=mg_read(mg_state_file());$actor=(string)($_SESSION['_mge_actor']??'');
 if($actor!==''? !mg_can_view($actor,$uid,$members,$state) : mg_uid()!==$uid)return [];
 if(!mg_active($members[$uid]??[],'building'))return [];
 $prefix='__fp_'.$plan.'_';$out=[];
 foreach(mg_read(__DIR__.'/data/manager_help_requests.php') as $r){
  if(!is_array($r)||($r['uid']??'')!==$uid||($r['status']??'')!=='pending'||strpos((string)($r['field']??''),$prefix)!==0
   ||($r['user_created']??null)!==($members[$uid]['created']??null)||($r['link_key']??'')!==mg_link_key($uid,$members[$uid]))continue;
  if($actor!==''&&(($r['manager']??'')!==$actor||($r['manager_created']??null)!==($members[$actor]['created']??null)))continue;
  $out[]=['id'=>$r['id']??'','field'=>$r['field'],'text'=>$r['text']??'','created_at'=>$r['created_at']??''];
 }
 usort($out,static fn($a,$b)=>strcmp($a['created_at'],$b['created_at']));return $out;
}
