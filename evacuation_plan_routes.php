<?php
declare(strict_types=1);

/** Null preserves older plans that were written entirely as free text; [] means no stairs. */
function epl_stairs($input): ?array {
    if($input===null)return null;
    if(!is_array($input)||!array_is_list($input)||count($input)>20)throw new InvalidArgumentException('계단은 0~20개로 설정해 주세요.');
    $out=[];$ids=[];$names=[];
    foreach($input as $row){
        if(!is_array($row))throw new InvalidArgumentException('계단 정보를 확인해 주세요.');
        $id=epl_text($row['id']??'',16);$name=epl_text($row['name']??'',40);
        if(!preg_match('/^stair_([1-9]|1[0-9]|20)$/D',$id)||isset($ids[$id]))throw new InvalidArgumentException('계단 목록을 다시 확인해 주세요.');
        if($name===''||preg_match('/[\r\n\x00-\x1f]/u',$name))throw new InvalidArgumentException('계단 이름을 한 줄로 입력해 주세요.');
        $nameKey=preg_replace('/\s+/u','',$name);
        if(isset($names[$nameKey]))throw new InvalidArgumentException('계단을 구분할 수 있도록 서로 다른 이름을 골라 주세요.');
        $ids[$id]=true;$names[$nameKey]=true;$out[]=['id'=>$id,'name'=>$name];
    }
    return $out;
}
function epl_route_text(string $ref,?array $stairs): ?string {
    if($ref==='pending')return '대체 대피경로 현장 확인 필요';
    $name=['direct_1'=>'출입구','direct_2'=>'다른 출입구'][$ref]??null;
    foreach($stairs??[] as $stair)if($stair['id']===$ref)$name=$stair['name'];
    return $name!==null?$name.' → 건물 밖 → 지정 집결지':null;
}
function epl_route_problem(array $row,?array $stairs): string {
    foreach(['primary_route','alternate_route'] as $field){
        $ref=(string)($row[$field.'_ref']??'');
        if($ref==='pending')return '대체 대피경로 현장 확인';
        if($ref!==''&&epl_route_text($ref,$stairs)===null)return '변경된 계단의 대피경로 다시 선택';
    }
    if(!empty($row['primary_route_ref'])&&($row['primary_route_ref']??'')===($row['alternate_route_ref']??''))return '주 경로와 다른 대체 경로 선택';
    return '';
}
