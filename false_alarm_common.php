<?php
declare(strict_types=1);
function fa_authorize(string $actor,string $target,array $members,array $state): bool {
 if (!mg_active($members[$target]??[],'building')) return false;
 return $actor===$target || mg_can_view($actor,$target,$members,$state);
}
function fa_record(array &$records,array $input,string $actor,string $name): array {
 $id=$input['id']??'';
 if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('기록 번호가 올바르지 않습니다.');
 $fields=[];
 foreach(['occurred_at'=>16,'location'=>120,'cause'=>1000,'action'=>2000,'status'=>20] as $key=>$max){
  $value=$input[$key]??'';
  if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value,'UTF-8')>$max)throw new InvalidArgumentException('입력 내용을 확인해 주세요.');
  $fields[$key]=trim($value);
 }
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$fields['occurred_at'],new DateTimeZone('Asia/Seoul'));
 if(!$dt||$dt->format('Y-m-d\TH:i')!==$fields['occurred_at'])throw new InvalidArgumentException('발생일시를 확인해 주세요.');
 if($fields['location']===''||$fields['action']==='')throw new InvalidArgumentException('발생 위치와 조치 내용을 입력해 주세요.');
 if(!in_array($fields['status'],['확인 중','조치 중','조치 완료'],true))throw new InvalidArgumentException('처리 상태를 확인해 주세요.');
 $old=$records[$id]??null;$revision=$input['revision']??0;
 if(!is_int($revision)||$revision<0)throw new InvalidArgumentException('기록 버전이 올바르지 않습니다.');
 if($old){
  if($revision===0&&$old['created_by']===$actor&&array_intersect_key($old,$fields)===$fields)return $old;
  if($revision!==($old['revision']??0))throw new DomainException('다른 화면에서 수정되었습니다. 목록을 새로고침한 뒤 다시 수정해 주세요.');
 }elseif($revision!==0)throw new DomainException('기록을 찾을 수 없습니다.');
 $now=date('c');
 $record=$fields+['id'=>$id,'revision'=>($old['revision']??0)+1,'created_at'=>$old['created_at']??$now,'created_by'=>$old['created_by']??$actor,'created_name'=>$old['created_name']??$name,'updated_at'=>$now,'updated_by'=>$actor,'updated_name'=>$name];
 $records[$id]=$record;return $record;
}
