<?php
declare(strict_types=1);
/** The selected manager draft replaces basic information once per accepted connection. */
function md_handoff_has_value($value):bool {
 if(is_array($value)){foreach($value as $k=>$v){if(in_array((string)$k,['type','updated'],true))continue;if(md_handoff_has_value($v))return true;}return false;}
 return trim((string)$value)!=='';
}
/** Resolve only the draft explicitly attached to the CURRENT accepted request. */
function md_handoff_current(string $uid,array $members,array &$state):?array {
 $m=$members[$uid]??[];if(!mg_active($m,'building'))return null;
 $actor=mg_connection_manager($m,$members);
 if(!mg_can_view($actor,$uid,$members,$state))return null;
 $requestKey=mg_link_key($uid,$m);$matches=[];
 foreach($state['address_book']??[] as $id=>$row){
  if(!is_array($row)||!empty($row['deleted_at']))continue;
  if(($row['manager']??'')===$actor&&($row['manager_created']??'')===(string)($members[$actor]['created']??'')&&($row['linked_uid']??'')===$uid&&($row['linked_key']??'')===$requestKey)$matches[(string)$id]=$row;
 }
 // Never guess a building or reuse an old snapshot on a connection without a draft.
 if(count($matches)!==1)return null;
 $id=(string)array_key_first($matches);$row=$matches[$id];
 if(!is_array($row['basic_info']??null)||!$row['basic_info'])return null;
 $key=$uid.':'.hash('sha256',(string)($m['created']??''));$old=$state['draft_handoffs'][$key]??[];
 if(($old['id']??'')===$id&&($old['request_key']??'')===$requestKey&&($old['manager']??'')===$actor&&($old['manager_created']??'')===(string)($members[$actor]['created']??'')&&is_array($old['info']??null))return $old;
 // Repair older account-scoped snapshots from the explicit current mapping.
 $handoff=['id'=>$id,'info'=>$row['basic_info'],'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'request_key'=>$requestKey,'at'=>date('c')];
 $state['draft_handoffs'][$key]=$handoff;return $handoff;
}
function md_handoff_load(string $uid,array $loaded,bool $persist=true):array {
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return $loaded;
 require_once __DIR__.'/manager_common.php';
 if($persist){
  $handoff=mg_member_tx(function(array &$members)use($uid){return mg_state_tx(function(array &$state)use($uid,$members){return md_handoff_current($uid,$members,$state);});});
 }else{$members=mg_members();$state=mg_read(mg_state_file());$handoff=md_handoff_current($uid,$members,$state);}
 if(!$handoff)return $loaded;
 $apply=function(array &$stored)use($loaded,$handoff,$uid,$persist){
  $current=$stored?:$loaded;$marker=$current['_manager_draft_import']??[];
  $differentDraft=isset($marker['id'])&&(string)$marker['id']!==(string)$handoff['id'];
  $differentRequest=isset($marker['request_key'])&&(string)$marker['request_key']!==(string)$handoff['request_key'];
  if($marker&&!$differentDraft&&!$differentRequest){
   // Completed imports and later user saves/resets must never be replayed.
   if(($marker['result']??'')!=='existing_preserved'||!empty($marker['user_saved_after_handoff']))return $current;
   $hasInfo=false;foreach(bi_blank() as $field=>$_)if($field!=='updated'&&md_handoff_has_value($current[$field]??'')){$hasInfo=true;break;}
   if(!$hasInfo)return $current; // Old reset records are deliberately left blank.
  }
  // Save the original record before replacing it; backups are guarded PHP data.
  if($persist&&$current){
   $backupKey=hash('sha256',(string)$handoff['id'].':'.(string)$handoff['request_key']);
   mg_tx(__DIR__.'/data/building/'.$uid.'/manager_draft_backups.php',function(array &$backups)use($backupKey,$current,$handoff){
    if(!isset($backups[$backupKey]))$backups[$backupKey]=['at'=>date('c'),'draft_id'=>$handoff['id'],'request_key'=>$handoff['request_key'],'info'=>$current];
   },true);
  }
  // Empty fields also come from the draft, so old and new building data cannot mix.
  $replacement=array_merge(bi_blank(),array_intersect_key($handoff['info'],bi_blank()));
  $current=array_merge($current,$replacement);
  $current['updated']=date('Y-m-d H:i:s');
  $current['_manager_draft_import']=['id'=>$handoff['id'],'at'=>date('c'),'result'=>'replaced','request_key'=>$handoff['request_key'],'version'=>4];
  $stored=$current;return $stored;
 };
 if(!$persist){$preview=$loaded;return $apply($preview);}
 return mg_tx(__DIR__.'/data/building/'.$uid.'/info.json',$apply);
}
