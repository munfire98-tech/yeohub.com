<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
// Called only by the authenticated, CSRF-checked save_step handler after manager_edit_guard.
function mh_save_requested_answer(string $actor,string $uid,string $id,array $patch,callable $save): bool {
 return mg_member_tx(function(array &$members)use($actor,$uid,$id,$patch,$save){
  return mg_state_tx(function(array &$state)use($members,$actor,$uid,$id,$patch,$save){
   if(!mg_active($members[$actor]??[],'agency')||!mg_can_view($actor,$uid,$members,$state))throw new RuntimeException('담당 매니저 연결을 확인해 주세요.');
   return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$actor,$uid,$id,$patch,$save){
    $r=$rows[$id]??[];
    if(($r['uid']??'')!==$uid||($r['manager']??'')!==$actor||($r['link_key']??'')!==mg_link_key($uid,$members[$uid])
      ||($r['user_created']??null)!==($members[$uid]['created']??null)||($r['manager_created']??null)!==($members[$actor]['created']??null))throw new RuntimeException('요청이 변경되었거나 삭제되었습니다. 다시 열어 주세요.');
    $field=(string)($r['field']??'');
    $groups=['__floors'=>['floor_a','floor_b'],'__areas'=>['area_t','area_f'],'__mgr'=>['mgrs'],'__assembly'=>['assembly_kind'],'__search'=>['name'],'__staff'=>['wd_day']];
    $keys=$groups[$field]??[$field];$answered=false;
    foreach($keys as $key){
     if(!array_key_exists($key,$patch))continue;
     if($key==='mgrs'){foreach((array)$patch[$key] as $m)if(is_array($m)&&trim((string)($m['name']??''))!=='')$answered=true;}
     elseif(is_scalar($patch[$key])&&(trim((string)$patch[$key])!==''||$key==='fire_engine_route_note'))$answered=true;
    }
    if($field==='__assembly'){require_once __DIR__.'/building_location_rules.php';$answered=trim((string)($patch['assembly_kind']??''))!==''&&bl_point_valid($patch['assembly_lat']??null,$patch['assembly_lng']??null);}
    if(!$answered)throw new RuntimeException('요청한 항목에 답변을 입력해 주세요.');
    if(($r['status']??'')==='resolved')return true; // idempotent retry; do not overwrite a later answer
    if(!$save())throw new RuntimeException('답변을 저장하지 못했습니다. 다시 시도해 주세요.');
    $rows[$id]['status']='resolved';$rows[$id]['resolved_at']=date('c');$rows[$id]['resolved_by']=$actor;
    $rows[$id]['reply']='담당 매니저가 요청한 항목을 확인하고 기본정보에 저장했습니다.';
    return true;
   },true);
  });
 });
}

/** Match a submitted field against the value actually persisted, never an unrelated field. */
function mh_own_answered(string $field,array $patch,array $stored,string $confirmed): bool {
 $groups=['__floors'=>['floor_a','floor_b'],'__areas'=>['area_t','area_f'],'__mgr'=>['mgrs'],'__assembly'=>['assembly_kind','assembly_lat','assembly_lng'],'__search'=>['name'],'__staff'=>['wd_day']];
 $keys=$groups[$field]??[$field];
 if(!array_intersect($keys,array_keys($patch)))return false;
 if($field==='__assembly'){
  require_once __DIR__.'/building_location_rules.php';
  return trim((string)($stored['assembly_kind']??''))!==''&&bl_point_valid($stored['assembly_lat']??null,$stored['assembly_lng']??null);
 }
 if($field==='fire_engine_route'){
  require_once __DIR__.'/building_location_rules.php';
  return trim((string)($stored[$field]??''))!==''&&bl_location_error($stored,[$field=>true])==='';
 }
 if($field==='__mgr'){
  foreach((array)($stored['mgrs']??[]) as $m)if(is_array($m)&&trim((string)($m['name']??''))!=='')return true;
  return false;
 }
 foreach($keys as $key){
  if(!array_key_exists($key,$patch)||!isset($stored[$key])||!is_scalar($stored[$key]))continue;
  if(trim((string)$stored[$key])!=='')return true;
  // A blank note counts only when the user explicitly chose "no special notes" in that question.
  if($key==='fire_engine_route_note'&&$confirmed===$field)return true;
 }
 return false;
}
function mh_save_own_answers(string $uid,array $patch,callable $save,callable $load,string $confirmed=''): bool {
 if($uid===''||mg_uid()!==$uid)throw new RuntimeException('본인 계정에서 저장해 주세요.');
 return mg_member_tx(function(array &$members)use($uid,$patch,$save,$load,$confirmed){
  if(!mg_active($members[$uid]??[],'building'))throw new RuntimeException('회원 상태를 확인해 주세요.');
  return mg_state_tx(function(array &$state)use($members,$uid,$patch,$save,$load,$confirmed){
   return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$state,$uid,$patch,$save,$load,$confirmed){
    if(!$save())return false;
    $stored=$load();
    foreach($rows as &$r){
     if(($r['uid']??'')!==$uid||($r['status']??'')!=='pending'||($r['user_created']??null)!==($members[$uid]['created']??null)
       ||($r['link_key']??'')!==mg_link_key($uid,$members[$uid])||!in_array(mg_link_status($uid,$members[$uid],$state),['pending','accepted'],true))continue;
     if(!mh_own_answered((string)($r['field']??''),$patch,$stored,$confirmed))continue;
     $r['status']='resolved';$r['resolved_at']=date('c');$r['resolved_by']=$uid;$r['resolved_kind']='user';
     $r['reply']='유저가 요청한 항목을 직접 작성하고 저장했습니다.';
    }unset($r);
    return true;
   },true);
  });
 });
}
