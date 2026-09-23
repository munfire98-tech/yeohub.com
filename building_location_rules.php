<?php
declare(strict_types=1);
function bl_point_valid($lat,$lng): bool {
 return is_scalar($lat)&&is_scalar($lng)&&trim((string)$lat)!==''&&trim((string)$lng)!==''&&is_numeric($lat)&&is_numeric($lng)
 &&is_finite((float)$lat)&&is_finite((float)$lng)&&abs((float)$lat)<=90&&abs((float)$lng)<=180;
}
function bl_location_error(array $data,array $changed): string {
 if(array_intersect(['assembly_lat','assembly_lng','assembly_kind'],array_keys($changed))){
  $lat=$data['assembly_lat']??'';$lng=$data['assembly_lng']??'';$name=trim((string)($data['assembly_kind']??''));
  if($name!==''||trim((string)$lat)!==''||trim((string)$lng)!==''){
   if(!bl_point_valid($lat,$lng))return '지도에서 집결지 위치를 먼저 찍어 주세요.';
   if($name==='')return '위치를 찍은 뒤 집결지 이름을 입력해 주세요.';
  }
 }
 if(array_key_exists('fire_engine_route',$changed)&&trim((string)($data['fire_engine_route']??''))!==''){
  $points=json_decode((string)$data['fire_engine_route'],true);
  if(!is_array($points)||count($points)<2)return '소방차 진입로는 지도에서 두 지점 이상 찍어 주세요.';
  foreach($points as $point)if(!is_array($point)||!bl_point_valid($point['lat']??null,$point['lng']??null))return '소방차 진입로 좌표를 다시 확인해 주세요.';
 }
 return '';
}
