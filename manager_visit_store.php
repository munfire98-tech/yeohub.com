<?php
declare(strict_types=1);
function mvc_date(string $v):bool {return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4));}
function mvc_save(array &$store,array $input,?array $target):array {
 $id=(string)($input['id']??'');$old=$id!==''?($store['visits'][$id]??null):null;
 if($id!==''&&!is_array($old))throw new RuntimeException('일정을 찾지 못했습니다. 새로고침해 주세요.');
 if($old&&(int)($input['revision']??0)!==(int)$old['revision'])throw new RuntimeException('다른 화면에서 변경된 일정입니다. 달력을 다시 열어 주세요.');
 $uid=trim((string)($input['uid']??''));
 if(!$target&&(!$old||$old['uid']!==$uid))throw new RuntimeException('현재 담당하는 거래처를 선택해 주세요.');
 $date=(string)($input['date']??'');$time=(string)($input['time']??'');$status=(string)($input['status']??'planned');$actual=(string)($input['visited_date']??'');$memo=trim((string)($input['memo']??''));
 if(!mvc_date($date)||substr($date,0,4)<'2000'||substr($date,0,4)>'2100')throw new RuntimeException('방문 예정일을 확인해 주세요.');
 if($time!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time))throw new RuntimeException('시간을 확인해 주세요.');
 if(!in_array($status,['planned','completed','cancelled'],true))throw new RuntimeException('방문 상태를 확인해 주세요.');
 if($status==='completed'&&(!mvc_date($actual)||$actual>date('Y-m-d')||substr($actual,0,4)<'2000'))throw new RuntimeException('실제 방문일은 오늘까지의 날짜로 입력해 주세요.');
 if(mb_strlen($memo)>2000)throw new RuntimeException('메모는 2,000자 이내로 입력해 주세요.');
 if(!$old&&count($store['visits']??[])>=10000)throw new RuntimeException('저장 가능한 일정 수를 초과했습니다.');
 if(!$id)$id=bin2hex(random_bytes(12));
 $row=['id'=>$id,'uid'=>$uid,'name'=>$target['name']??$old['name'],'address'=>$target['address']??$old['address'],'date'=>$date,'time'=>$time,'status'=>$status,'visited_date'=>$status==='completed'?$actual:'','memo'=>$memo,'revision'=>(int)($old['revision']??0)+1,'created_at'=>$old['created_at']??date('c'),'updated_at'=>date('c')];
 $store['visits'][$id]=$row;return $row;
}

function mvc_batch(array &$store,array $input,array $targets):array {
 $token=(string)($input['batch_token']??'');
 if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('등록 요청을 다시 시작해 주세요.');
 $uids=json_decode((string)($input['uids']??''),true);
 if(!is_array($uids)||!array_is_list($uids)||!count($uids)||count($uids)>50)throw new RuntimeException('거래처를 1~50곳 선택해 주세요.');
 foreach($uids as $uid)if(!is_string($uid)||!isset($targets[$uid]))throw new RuntimeException('현재 담당하지 않는 거래처가 포함되어 있습니다. 목록을 새로고침해 주세요.');
 $uids=array_values(array_unique($uids));
 $fingerprint=hash('sha256',json_encode([$uids,$input['date']??'',$input['memo']??'']));
 if(isset($store['batches'][$token])){
  if($store['batches'][$token]['fingerprint']!==$fingerprint)throw new RuntimeException('등록 요청 내용이 변경되었습니다. 다시 시도해 주세요.');
  if(!empty($store['batches'][$token]['undone']))throw new RuntimeException('이미 실행 취소된 등록 요청입니다. 다시 등록해 주세요.');
  return ['created'=>$store['batches'][$token]['created'],'skipped'=>$store['batches'][$token]['skipped'],'undo_token'=>isset($store['batches'][$token]['ids'])?$token:null];
 }
 $copy=$store;$created=0;$skipped=0;$ids=[];
 foreach($uids as $uid){
  $exists=false;foreach($copy['visits']??[] as $r)if($r['uid']===$uid&&$r['date']===($input['date']??'')&&$r['status']!=='cancelled'){$exists=true;break;}
  if($exists){$skipped++;continue;}
  $row=mvc_save($copy,['uid'=>$uid,'date'=>$input['date']??'','status'=>'planned','memo'=>$input['memo']??''],$targets[$uid]);$ids[]=$row['id'];$created++;
 }
 $copy['batches'][$token]=['fingerprint'=>$fingerprint,'created'=>$created,'skipped'=>$skipped,'ids'=>$ids];
 if(count($copy['batches'])>500)$copy['batches']=array_slice($copy['batches'],-500,null,true);
 $store=$copy;return ['created'=>$created,'skipped'=>$skipped,'undo_token'=>$created?$token:null];
}

function mvc_undo_batch(array &$store,string $token):array {
 if(!preg_match('/^[a-f0-9]{32}$/D',$token)||!isset($store['batches'][$token]['ids']))throw new RuntimeException('실행 취소할 등록을 찾지 못했습니다.');
 $batch=$store['batches'][$token];
 if(!empty($batch['undone']))return ['undone'=>count($batch['ids'])];
 foreach($batch['ids'] as $id){$r=$store['visits'][$id]??null;if(!$r||$r['revision']!==1||$r['status']!=='planned')throw new RuntimeException('등록 후 수정된 일정이 있어 일괄 실행 취소할 수 없습니다. 날짜를 눌러 개별 해제해 주세요.');}
 foreach($batch['ids'] as $id){$store['visits'][$id]['status']='cancelled';$store['visits'][$id]['revision']++;$store['visits'][$id]['updated_at']=date('c');}
 $store['batches'][$token]['undone']=true;return ['undone'=>count($batch['ids'])];
}

// Per-manager calendar notes, independent of individual building visits.
function mvc_save_day(array &$store,array $input):array {
 $date=$input['date']??null;$memo=$input['memo']??null;$holiday=$input['holiday']??null;
 if(!is_string($date)||!mvc_date($date)||substr($date,0,4)<'2000'||substr($date,0,4)>'2100')throw new RuntimeException('날짜를 확인해 주세요.');
 if(!is_string($memo)||mb_strlen($memo)>2000)throw new RuntimeException('날짜 메모는 2,000자 이내로 입력해 주세요.');
 if(!in_array($holiday,['0','1'],true))throw new RuntimeException('휴일 표시를 확인해 주세요.');
 $old=$store['days'][$date]??[];
 if(!isset($input['revision'])||!is_scalar($input['revision'])||(string)(int)$input['revision']!==(string)$input['revision']||(int)$input['revision']!==(int)($old['revision']??0))throw new RuntimeException('다른 화면에서 날짜 설정이 변경되었습니다. 메모를 복사해 둔 뒤 날짜 창을 다시 열어 확인해 주세요.');
 $row=['holiday'=>$holiday==='1','memo'=>trim($memo),'revision'=>(int)($old['revision']??0)+1,'updated_at'=>date('c')];
 // Keep empty revisions too, so a stale tab cannot restore cleared notes.
 $store['days'][$date]=$row;return $row;
}
