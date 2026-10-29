<?php
declare(strict_types=1);
require_once __DIR__.'/building_location_rules.php';
function epl_location_snapshot(array $building):array {
 $point=static fn($lat,$lng)=>bl_point_valid($lat,$lng)?['lat'=>(float)$lat,'lng'=>(float)$lng]:null;
 $route=$building['fire_engine_route']??[];if(is_string($route))$route=json_decode($route,true);
 $points=[];foreach(is_array($route)?$route:[] as $p){if(!is_array($p))continue;$v=$point($p['lat']??null,$p['lng']??null);if($v)$points[]=$v;}
 return ['assembly_name'=>is_scalar($building['assembly_kind']??null)?trim((string)$building['assembly_kind']):'',
 'assembly'=>$point($building['assembly_lat']??null,$building['assembly_lng']??null),
 'building'=>$point($building['bd_lat']??null,$building['bd_lng']??null),'route'=>count($points)>=2?$points:[],
 'route_note'=>is_scalar($building['fire_engine_route_note']??null)?trim((string)$building['fire_engine_route_note']):''];
}
function epl_plan_locations(array $plan,array $building):array {
 if(!is_array($plan['_building_locations']??null))return epl_location_snapshot($building);
 $s=$plan['_building_locations'];
 return epl_location_snapshot(['assembly_kind'=>$s['assembly_name']??'','assembly_lat'=>$s['assembly']['lat']??null,'assembly_lng'=>$s['assembly']['lng']??null,
 'bd_lat'=>$s['building']['lat']??null,'bd_lng'=>$s['building']['lng']??null,'fire_engine_route'=>$s['route']??[],'fire_engine_route_note'=>$s['route_note']??'']);
}
/** Same Kakao ROADMAP and saved overlays as basic information, with separate maps. */
function epl_location_html(array $s):string {
 $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 if(empty($s['assembly_name'])&&empty($s['assembly'])&&empty($s['route'])&&empty($s['route_note']))return '';
 $map=static function(string $kind,array $data)use($escape):void { ?>
 <div class="epl-location-map epl-kakao-map" data-epl-location-map="<?=$kind?>" data-location="<?=$escape(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))?>" role="img" aria-label="<?=$kind==='assembly'?'집결지 지도':'소방차 진입로 지도'?>"><p class="epl-map-status" role="status">기본정보와 같은 카카오 지도를 불러오는 중입니다.</p></div>
 <?php };
 ob_start(); ?>
 <section class="epl-location-reference"><h2>집결지</h2>
 <p class="epl-location-value"><?=$escape($s['assembly_name']?:'집결지 이름 미입력')?></p>
 <?php if($s['assembly']):$map('assembly',['assembly'=>$s['assembly']]);?>
 <p class="epl-location-legend">기본정보에 지정한 집결지 · <?=$escape($s['assembly']['lat'])?>, <?=$escape($s['assembly']['lng'])?></p>
 <?php else:?><p class="epl-location-note">기본정보에서 집결지 지도 위치를 지정해 주세요.</p><?php endif;?>
 </section>
 <section class="epl-location-reference"><h2>소방차 진입로</h2>
 <?php if($s['route']):$map('route',['route'=>$s['route'],'building'=>$s['building']]);?>
 <p class="epl-location-legend">큰도로 → 건물 입구 · 초록점: 진입 시작 · 저장 경로 <?=count($s['route'])?>개 지점</p>
 <?php else:?><p class="epl-location-note">기본정보에서 소방차 진입로를 지정해 주세요.</p><?php endif;?>
 <?php if($s['route_note']!==''):?><dl class="epl-review-grid"><div class="wide"><dt>소방차 진입 시 특이사항</dt><dd><?=$escape($s['route_note'])?></dd></div></dl><?php endif;?>
 <p class="epl-location-note">기본정보에 저장된 차량 접근 경로입니다. 재실자의 피난경로와 구분합니다.</p>
 </section>
 <?php return (string)ob_get_clean();
}
