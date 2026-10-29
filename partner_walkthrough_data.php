<?php
declare(strict_types=1);
/** Reserved fictional search result. Never query a public registry for this name. */
function pwt_matches(string $keyword):bool {
    $key=mb_strtolower(preg_replace('/\s+/u','',trim($keyword))??'','UTF-8');
    return $key!==''&&(str_contains($key,'소방계획서')||str_contains($key,'sobang.com'));
}
function pwt_result():array {
    return ['demo'=>true,'place'=>'소방계획서.com 빌딩','road'=>'체험용 가상 건물 · 실제 거래처로 등록되지 않아요','jibun'=>'','category'=>'사전등록 연습','lat'=>'37.7591','lng'=>'126.7794'];
}
function pwt_lookup():array {
    $floors=[];
    foreach(['B1','1F','2F','3F','4F','5F'] as $i=>$key)$floors[]=['key'=>$key,'label'=>$i===0?'지하 1층':'지상 '.$i.'층','area'=>'400','use'=>'업무시설','structure'=>'철근콘크리트구조','source'=>'registry','excluded'=>false,'exclusion_known'=>true];
    $dong=['dong'=>'본관','floor_a'=>'5','floor_b'=>'1','area'=>'2400','arch_area'=>'400','use'=>'업무시설','managed'=>true,'floor_info'=>['status'=>'ready','rows'=>$floors]];
    return ['ok'=>true,'demo'=>true,'dongCnt'=>1,'dongList'=>[$dong], 'patch'=>[
        'name'=>'소방계획서.com 빌딩','address'=>'경기도 파주시 · 체험용 가상 주소',
        'use'=>'업무시설(사무실)','floor_a'=>'5','floor_b'=>'1','area_t'=>'2400',
        'area_f'=>'400','bd_area_arch'=>'400','bd_struct'=>'철근콘크리트구조',
        'bd_lat'=>'37.7591','bd_lng'=>'126.7794','dongsu'=>'1','bd_dong_pick'=>'본관',
        'bd_use_apr'=>'20180515','bd_dong_list'=>[$dong]
    ]];
}
