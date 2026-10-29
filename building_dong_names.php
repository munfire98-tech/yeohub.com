<?php
declare(strict_types=1);

/** Display names never replace registry names, which existing record IDs depend on. */
function bdn_rows(array $building): array {
    $rows=$building['bd_dong_list']??[];
    if(is_string($rows))$rows=json_decode($rows,true);
    if(!is_array($rows))return [];
    $aliases=is_array($building['bd_dong_names']??null)?$building['bd_dong_names']:[];
    $pick=trim((string)($building['bd_dong_pick']??''));
    $address=preg_replace('/\s+/u','',trim((string)($building['address']??'')));
    $seen=[];$fingerprints=[];$facSeen=[];$skippedPrimary=false;$out=[];
    foreach($rows as $i=>$row){
        if(!is_array($row))continue;
        $original=trim((string)($row['dong']??''))?:'동명 미상 '.($i+1);
        $n=($seen[$original]??0)+1;$seen[$original]=$n;
        $evacId='dong_'.substr(hash('sha256',$original.'#'.$n),0,20);
        // Bind aliases to the address and source row, not its current display order.
        $identity=[$address,(string)($row['dong']??'')];
        foreach(['floor_a','floor_b','area','arch_area','struct','height','use'] as $field)$identity[]=(string)($row[$field]??'');
        $fingerprint=hash('sha256',json_encode($identity,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $occurrence=($fingerprints[$fingerprint]??0)+1;$fingerprints[$fingerprint]=$occurrence;
        $key='name_'.substr(hash('sha256',$fingerprint.'#'.$occurrence),0,24);
        $alias=is_string($aliases[$key]??null)?trim($aliases[$key]):'';
        $primary=$pick!==''&&$original===$pick&&$n===1;
        // Facilities historically skip the primary row before numbering duplicates.
        if(!$skippedPrimary&&$pick!==''&&$original===$pick){$facilityId='base';$skippedPrimary=true;}
        else{$fn=($facSeen[$original]??0)+1;$facSeen[$original]=$fn;$facilityId='dong_'.substr(hash('sha256',$original.'#'.$fn),0,20);}
        $out[]=['row'=>$row,'key'=>$key,'original'=>$original,'default'=>$original.($n>1?' ('.$n.')':''),'name'=>$alias!==''?$alias:$original.($n>1?' ('.$n.')':''),'alias'=>$alias,'evac_id'=>$evacId,'facility_id'=>$facilityId,'primary'=>$primary];
    }
    return $out;
}

/** Validate aliases and discard names belonging to a removed/replaced registry row. */
function bdn_clean_names(array $building): array {
    $names=$building['bd_dong_names']??[];
    if(!is_array($names))throw new InvalidArgumentException('동 이름을 확인해 주세요.');
    $clean=[];
    foreach(bdn_rows($building) as $entry){
        $value=$names[$entry['key']]??'';
        if(!is_string($value))throw new InvalidArgumentException('동 이름을 확인해 주세요.');
        $value=trim($value);
        if(!preg_match('//u',$value)||mb_strlen($value,'UTF-8')>60||preg_match('/[\x00-\x1f\x7f]/u',$value))throw new InvalidArgumentException('동 이름은 한 줄, 60자 이내로 입력해 주세요.');
        if($value!=='')$clean[$entry['key']]=$value;
    }
    return $clean;
}


/** Alias-only revision; unrelated basic-info edits do not prevent a name change. */
function bdn_revision(array $bi):string {
 return hash('sha256',json_encode([$bi['address']??'',$bi['bd_dong_list']??[],$bi['bd_dong_pick']??'',$bi['bd_dong_names']??[]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function bdn_save_alias(string $key,string $name,string $revision):array {
 require_once __DIR__.'/building_info.php';require_once __DIR__.'/manager_common.php';
 if(bi_user_key()===''||defined('MANAGER_VIEW_UID'))throw new RuntimeException('편집 가능한 건물 화면에서 이름을 수정해 주세요.');
 $write=static function()use($key,$name,$revision):array {
 $result=[];
 mg_tx(bi_file(),static function(array &$stored)use($key,$name,$revision,&$result){
  $bi=array_merge(bi_blank(),$stored);
  if(!hash_equals(bdn_revision($bi),$revision))throw new RuntimeException('건물정보가 변경되었습니다. 입력한 시설을 저장한 뒤 새로고침해 주세요.');
  $found=false;foreach(bdn_rows($bi) as $row)if($row['key']===$key)$found=true;
  if(!$found)throw new RuntimeException('수정할 동을 확인해 주세요.');
  $bi['bd_dong_names'][$key]=$name;$clean=bdn_clean_names($bi);
  $stored['bd_dong_names']=$clean;$stored['updated']=date('Y-m-d H:i:s');$result=array_merge(bi_blank(),$stored);return true;
 });
 return $result;
 };
 if(!empty($_SESSION['_mge_actor'])){require_once __DIR__.'/manager_facility_help.php';$result=mfh_save(bi_user_key(),false,$write);}else $result=$write();
 require_once __DIR__.'/fire_plan_auto_sync.php';fpa_queue();return $result;
}
