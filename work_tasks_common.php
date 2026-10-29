<?php
declare(strict_types=1);
require_once __DIR__.'/visit_push_common.php';
function wt_clients(string $actor,array $members,array $system):array{
 $rows=[];foreach(ma_available($actor,$members,$system) as $id=>$r)$rows[]=['uid'=>'pre_'.$id,'name'=>(string)($r['name']??'사전등록 거래처')];
 foreach($members as $uid=>$member){$uid=(string)$uid;if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!mg_can_view($actor,$uid,$members,$system))continue;$info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');$rows[]=['uid'=>$uid,'name'=>(string)($info['name']??$member['nickname']??$uid)];}
 usort($rows,static fn($a,$b)=>strcmp($a['name'],$b['name']));return $rows;
}
function wt_list(array $store,string $actor,array $members,array $system):array{
 $rows=[];foreach($store['tasks']??[] as $task)if(($task['status']??'')!=='cancelled'&&(($task['uid']??'')===''||vp_allowed($actor,$task['uid'],$members,$system)))$rows[]=$task;
 usort($rows,static fn($a,$b)=>(($a['status']==='completed')<=>($b['status']==='completed'))?:strcmp($a['due'],$b['due'])?:strcmp($a['id'],$b['id']));return $rows;
}
function wt_save(array &$store,array $input,string $actor,array $members,array $system):array{
 $id=$input['id']??'';if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{24}$/D',$id)))throw new RuntimeException('할 일을 확인해 주세요.',400);
 $old=$id!==''?($store['tasks'][$id]??null):null;
 if($id!==''&&(!$old||(($old['uid']??'')!==''&&!vp_allowed($actor,$old['uid'],$members,$system))))throw new RuntimeException('할 일을 확인할 권한이 없습니다.',403);
 if($old&&(int)($input['revision']??0)!==(int)$old['revision'])throw new RuntimeException('다른 화면에서 변경되었습니다. 목록을 다시 열어 주세요.',409);
 $act=$input['act']??'task_save';
 if($act==='task_save'){
  $title=is_string($input['title']??null)?preg_replace('/\s+/u',' ',trim($input['title'])):'';
  $due=is_string($input['due']??null)?$input['due']:'';$uid=is_string($input['uid']??null)?$input['uid']:'';
  if($title===''||mb_strlen($title)>160||!rh_valid_date($due))throw new RuntimeException('할 일 제목과 마감일을 확인해 주세요.',400);
  if($uid!==''&&!vp_allowed($actor,$uid,$members,$system))throw new RuntimeException('선택한 거래처를 확인할 권한이 없습니다.',403);
  if(!$old&&count($store['tasks']??[])>=10000)throw new RuntimeException('등록 가능한 할 일 수를 초과했습니다.',400);
  $name='';if($uid!=='')foreach(wt_clients($actor,$members,$system) as $client)if($client['uid']===$uid){$name=$client['name'];break;}
  $row=['id'=>$id?:bin2hex(random_bytes(12)),'uid'=>$uid,'client_name'=>$uid!==''?$name:'','title'=>$title,'due'=>$due,'status'=>$old['status']??'planned','revision'=>(int)($old['revision']??0)+1,'created'=>$old['created']??date('c'),'updated'=>date('c')];
 }else{
  if(!$old)throw new RuntimeException('변경할 할 일을 선택해 주세요.',400);$row=$old;$row['status']=$act==='task_delete'?'cancelled':(($input['completed']??false)===true?'completed':'planned');$row['revision']++;$row['updated']=date('c');
 }
 $store['tasks'][$row['id']]=$row;return $row;
}
