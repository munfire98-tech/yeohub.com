<?php
declare(strict_types=1);
require_once __DIR__.'/building_floor_common.php';
require_once __DIR__.'/manager_common.php';
function bmu_types():array{return ['음식점·제과점','단란·유흥주점','노래연습장','게임·PC방','영화·비디오 상영','학원','목욕장','산후조리원','고시원','실내 체육·오락시설','방탈출·키즈·만화카페','기타'];}
function bmu_locations(array $bi):array{$out=[];foreach(bfl_entries($bi) as $e){$floors=[];foreach(bfl_effective($bi,$e) as $f)$floors[$f['key']]=$f['label'];$out[$e['key']]=['name'=>$e['name'],'floors'=>$floors];}return $out;}
function bmu_file():string{$uid=bi_user_key();if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid))throw new RuntimeException('건물 계정을 확인해 주세요.');return __DIR__.'/data/building/'.$uid.'/multi_use.php';}
function bmu_load():array{return mg_read(bmu_file());}
function bmu_revision(array $data):string{return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function bmu_text($value,int $max=200):string{if(!is_string($value)||mb_strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/u',$value))throw new InvalidArgumentException('입력 내용을 확인해 주세요. 한 줄로 입력할 수 있습니다.');return trim($value);}
function bmu_clean(array $input,array $bi):array{
 $state=$input['state']??'';if(!in_array($state,['unknown','none','present'],true))throw new InvalidArgumentException('입점 현황을 선택해 주세요.');
 $raw=$input['rows']??[];if(!is_array($raw)||count($raw)>100)throw new InvalidArgumentException('업소는 100개까지 등록할 수 있습니다.');$rows=[];$locations=bmu_locations($bi);$ids=[];
 foreach($raw as $r){if(!is_array($r))throw new InvalidArgumentException('업소 정보를 확인해 주세요.');$clean=[];
  foreach(['name','type','dong'] as $k)$clean[$k]=bmu_text($r[$k]??'',in_array($k,['note','facilities'],true)?500:200);
  if($clean['name']===''&&$clean['type']==='')continue;
  if($state!=='present')throw new InvalidArgumentException('등록된 업소가 있습니다. 입점 현황을 확인해 주세요.');
  if($clean['name']===''||!in_array($clean['type'],bmu_types(),true))throw new InvalidArgumentException('업소명과 업종을 입력해 주세요.');
  if(!isset($locations[$clean['dong']]))throw new InvalidArgumentException('기본정보의 동 목록이 변경되었습니다. 새로고침 후 위치를 선택해 주세요.');
  $floors=$r['floors']??[];if(!is_array($floors)||!$floors)throw new InvalidArgumentException('영업하는 층을 선택해 주세요.');
  foreach($floors as $f)if(!is_string($f)||!isset($locations[$clean['dong']]['floors'][$f]))throw new InvalidArgumentException('기본정보의 층 목록을 다시 확인해 주세요.');
  $clean['floors']=array_values(array_unique($floors));
  $id=$r['id']??'';if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{24}$/D',$id)))throw new InvalidArgumentException('업소 식별값을 확인해 주세요.');$clean['id']=$id?:bin2hex(random_bytes(12));if(isset($ids[$clean['id']]))throw new InvalidArgumentException('동일 업소가 중복되었습니다.');$ids[$clean['id']]=true;$rows[]=$clean;
 }
 if($state==='present'&&!$rows)throw new InvalidArgumentException('업소를 한 개 이상 입력해 주세요.');return ['state'=>$state,'rows'=>$rows];
}
function bmu_save(array $input,string $revision,string $buildingRevision):array{
 if(defined('MANAGER_VIEW_UID'))throw new RuntimeException('열람 화면에서는 저장할 수 없습니다.');
 require_once __DIR__.'/manager_facility_help.php';
 $result=mfh_save(bi_user_key(),false,static function()use($input,$revision,$buildingRevision):array{
  // Lock basic info before this inventory, so renamed/removed floors cannot race a save.
  $basic=__DIR__.'/data/building/'.bi_user_key().'/info.json';
  return mg_tx($basic,static function(array &$bi)use($input,$revision,$buildingRevision):array{
   if(!hash_equals($buildingRevision,bmu_revision(bmu_locations($bi))))throw new RuntimeException('동·층 정보가 변경되었습니다. 새로고침 후 다시 저장해 주세요.');
   $clean=bmu_clean($input,$bi);
   return mg_tx(bmu_file(),static function(array &$stored)use($clean,$revision):array{if(!hash_equals(bmu_revision($stored),$revision))throw new RuntimeException('다른 화면에서 수정되었습니다. 새로고침 후 다시 저장해 주세요.');$stored=$clean+['updated_at'=>date('c')];return $stored;},true);
  });
 });
 require_once __DIR__.'/fire_plan_auto_sync.php';fpa_queue();return $result;
}
function bmu_snapshot(array $bi,array $data):array{
 $locations=bmu_locations($bi);foreach($data['rows']??[] as $i=>$r){$loc=$locations[$r['dong']]??null;$labels=[];$stale=!$loc;foreach($r['floors']??[] as $f){$labels[]=$loc['floors'][$f]??bfl_label($f);if(!isset($loc['floors'][$f]))$stale=true;}$data['rows'][$i]['location']=($loc['name']??'동 정보 확인 필요').' · '.implode(', ',$labels);$data['rows'][$i]['location_stale']=$stale;}return $data;
}
