<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
/** Run after the facilities page login, manager guard and CSRF checks. */
function mfh_save(string $uid,bool $confirmed,callable $save,bool $reset=false):array{
 $owner=mg_uid();$editor=(string)($_SESSION['_mge_actor']??'');
 // Site-admin editing retains its normal save behavior but cannot resolve as a user/manager.
 if($owner!==$uid&&$editor==='')return $save();
 return mg_member_tx(function(array &$members)use($uid,$owner,$editor,$confirmed,$save,$reset){
  if(!mg_active($members[$uid]??[],'building'))throw new RuntimeException('건물 계정을 확인해 주세요.');
  return mg_state_tx(function(array &$state)use($members,$uid,$owner,$editor,$confirmed,$save,$reset){
   if($editor!==''&&!mg_can_view($editor,$uid,$members,$state))throw new RuntimeException('담당 유저 연결을 확인해 주세요.');
   return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$uid,$owner,$editor,$confirmed,$save,$reset){
    $data=$save(); // revision conflicts / failed saves throw, leaving requests unchanged.
    if($reset){
     foreach($rows as $id=>$r){if(is_array($r)&&($r['uid']??'')===$uid&&($r['field']??'')==='__facilities'&&($r['user_created']??null)===($members[$uid]['created']??null))unset($rows[$id]);}
     return $data;
    }
    if(!$confirmed||!bf_complete($data))return $data;
    foreach($rows as &$r){
     if(($r['uid']??'')!==$uid||($r['field']??'')!=='__facilities'||($r['status']??'')!=='pending'
       ||($r['user_created']??null)!==($members[$uid]['created']??null)||($r['link_key']??'')!==mg_link_key($uid,$members[$uid]))continue;
     if($editor!==''&&(($r['manager']??'')!==$editor||($r['manager_created']??null)!==($members[$editor]['created']??null)))continue;
     $r['status']='resolved';$r['resolved_at']=date('c');$r['resolved_by']=$editor!==''?$editor:$owner;
     $r['resolved_kind']=$editor!==''?'manager':'user';
     $r['reply']=($editor!==''?'담당 매니저가':'유저가').' 소방시설 설치 여부 확인을 마치고 현황을 저장했습니다.';
    }unset($r);return $data;
   },true);
  });
 });
}
