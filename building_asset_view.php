<?php
// Internal template, included only after building_setup.php authentication and manager editing guard.
if(!defined('BUILDING_ASSET_VIEW_READY')){http_response_code(404);exit;}
require_once __DIR__.'/building_location_rules.php';
$isRoute=($_GET['asset_view']??'')==='route';
$title=$isRoute?'소방차 진입로':'비상 집결지';
$points=[];
if($isRoute){$raw=json_decode((string)($d['fire_engine_route']??''),true);foreach(is_array($raw)?$raw:[] as $p)if(is_array($p)&&bl_point_valid($p['lat']??null,$p['lng']??null))$points[]=['lat'=>(float)$p['lat'],'lng'=>(float)$p['lng']];}
elseif(bl_point_valid($d['assembly_lat']??null,$d['assembly_lng']??null))$points[]=['lat'=>(float)$d['assembly_lat'],'lng'=>(float)$d['assembly_lng']];
$available=count($points)>=($isRoute?2:1);
$payload=['route'=>$isRoute,'points'=>$available?$points:[]];
$editUrl=$url('/building_setup.php').($adminQuery===''?'?':'&').'modal=1&embed=1';
header('Cache-Control: no-store');
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7f9;color:#223646;font:14px/1.6 system-ui,-apple-system,"Apple SD Gothic Neo",sans-serif}.asset-wrap{max-width:1000px;margin:auto;padding:24px}.asset-top{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}.asset-top h1{margin:4px 0;font-size:24px;letter-spacing:-.7px}.eyebrow{font-size:11px;letter-spacing:1.2px;color:#6b8091;font-weight:750}.saved-pill{flex-shrink:0;font-size:12px;padding:6px 12px;border-radius:20px;background:#e6f5ee;color:#19765b}.building{color:#64778a;margin:0}.map-card{overflow:hidden;border:1px solid #dce5eb;border-radius:16px;background:white;box-shadow:0 8px 30px #24384b08}#assetMap{height:clamp(280px,55vh,500px);background:#eaf0f3}.map-fallback{padding:30px;text-align:center;color:#607489}.map-caption{padding:12px 18px;border-top:1px solid #e5edf1;color:#65798a;font-size:12px}.asset-info{padding:20px}.asset-info h2{font-size:17px;margin:0 0 8px}.asset-info p{white-space:pre-wrap;margin:0;color:#526a7d;overflow-wrap:anywhere}.asset-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.asset-actions a,.asset-actions button{padding:9px 15px;border:1px solid #d3dfe7;border-radius:9px;background:white;color:#355267;text-decoration:none;font:600 13px system-ui;cursor:pointer}.coords{font-size:11px;color:#81909b;margin-top:10px}@media(max-width:500px){.asset-wrap{padding:16px}.asset-top h1{font-size:21px}.asset-top{align-items:flex-start}}@media print{@page{size:A4;margin:12mm}body{background:white}.asset-wrap{padding:0}.asset-actions{display:none}#assetMap{height:140mm}.map-card{box-shadow:none;break-inside:avoid}.saved-pill{border:1px solid #aac9bb}}
</style></head><body><main class="asset-wrap">
<header class="asset-top"><div><div class="eyebrow">SAFETY ASSETS</div><h1><?=h($title)?></h1><p class="building"><?=h((string)($d['name']??''))?></p></div><span class="saved-pill"><?=$available?'저장된 위치':'위치 미지정'?></span></header>
<section class="map-card"><div id="assetMap"><p class="map-fallback"><?=$available?'저장된 지도를 불러오는 중입니다.':'저장된 위치가 없습니다. 기본정보에서 먼저 지정해 주세요.'?></p></div>
<div class="map-caption"><?=$isRoute?'초록점은 진입 시작 · 붉은 선은 저장된 소방차 진입 경로입니다.':'지도 핀은 저장된 비상 집결지입니다.'?></div>
<div class="asset-info"><h2><?=h($isRoute?'진입로 특이사항':((string)($d['assembly_kind']??'')?:'지도에 지정한 집결지'))?></h2><p><?=h($isRoute?((string)($d['fire_engine_route_note']??'')?:'등록된 특이사항이 없습니다.'):(string)($d['address']??''))?></p>
<?php if($available): ?><div class="coords"><?=$isRoute?count($points).'개 지점으로 저장됨':'위도 '.h((string)$points[0]['lat']).' · 경도 '.h((string)$points[0]['lng'])?></div><?php endif; ?></div></section>
<div class="asset-actions"><a href="<?=h($editUrl)?>">기본정보에서 수정</a><button type="button" onclick="buildingInfoRequestClose()">닫기</button></div>
</main>
<script>
window.buildingInfoDirty=false;
window.buildingInfoRequestClose=function(){if(window.parent!==window)window.parent.postMessage({type:'building-info-close'},location.origin);else location.href=<?=json_encode($url('/building_manager.php'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;};
window.buildingInfoPrint=function(){window.focus();window.print();};
document.addEventListener('keydown',function(e){if(e.key==='Escape')buildingInfoRequestClose();});
const savedAsset=<?=json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?>;
</script>
<?php if($available&&$KAKAO_JS!==''): ?>
<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=<?=h($KAKAO_JS)?>&autoload=false"></script>
<script src="/building_asset_view.js?v=1"></script>
<?php elseif($available): ?><script>document.querySelector('.map-fallback').textContent='지도 키 설정을 확인해 주세요. 위치 정보는 저장되어 있습니다.';</script><?php endif; ?>
</body></html>
