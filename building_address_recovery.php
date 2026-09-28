<?php
// Read-only helpers for a user-requested road-address retry.
function bar_road_key(string $address): string {
  $address = preg_replace('/\([^)]*\)/u', '', trim($address));
  $address = preg_replace('/\s+/u', ' ', $address);
  $aliases=['경기'=>'경기도','서울'=>'서울특별시','인천'=>'인천광역시','부산'=>'부산광역시','대구'=>'대구광역시','대전'=>'대전광역시','광주'=>'광주광역시','울산'=>'울산광역시','세종'=>'세종특별자치시','충북'=>'충청북도','충남'=>'충청남도','전북'=>'전북특별자치도','전라북도'=>'전북특별자치도','전남'=>'전라남도','경북'=>'경상북도','경남'=>'경상남도','강원'=>'강원특별자치도','강원도'=>'강원특별자치도','제주'=>'제주특별자치도','제주도'=>'제주특별자치도'];
  $parts=explode(' ',trim($address));$parts[0]=$aliases[$parts[0]]??$parts[0];
  return implode(' ',$parts);
}
function bar_road_code(array $api,string $road): ?array {
  if(trim($road)==='')return null;
  $r=bldg_http_get($api['juso_url'].'?'.http_build_query(['confmKey'=>$api['juso'],'currentPage'=>1,'countPerPage'=>100,'keyword'=>$road,'resultType'=>'json']));
  $j=json_decode($r['body'],true);
  if((string)($j['results']['common']['errorCode']??'')!=='0')return null;
  $matches=[];
  foreach($j['results']['juso']??[] as $a){
    if(bar_road_key((string)($a['roadAddrPart1']??$a['roadAddr']??''))!==bar_road_key($road))continue;
    $adm=(string)($a['admCd']??'');
    if(strlen($adm)!==10 || (int)($a['lnbrMnnm']??0)===0)continue;
    $c=['sigunguCd'=>substr($adm,0,5),'bjdongCd'=>substr($adm,5,5),'platGbCd'=>(($a['mtYn']??'0')==='1'?'1':'0'),'bun'=>str_pad((string)(int)$a['lnbrMnnm'],4,'0',STR_PAD_LEFT),'ji'=>str_pad((string)(int)($a['lnbrSlno']??0),4,'0',STR_PAD_LEFT),'roadAddr'=>$a['roadAddr']??$road];
    $matches[implode('|',array_slice($c,0,5))]=$c;
  }
  return count($matches)===1?reset($matches):null;
}
function bar_matching_items(array $items,string $road): array {
  return array_values(array_filter($items,static function($item)use($road){
    return is_array($item) && bar_road_key((string)($item['newPlatPlc']??''))===bar_road_key($road);
  }));
}
