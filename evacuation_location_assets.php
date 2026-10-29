<?php
// The JavaScript key and domain configuration are identical to building_setup.php.
$eplMapApi=@include __DIR__.'/api_keys.php';
$eplMapKey=is_array($eplMapApi)?(string)($eplMapApi['kakao_js']??''):'';
if($eplMapKey!==''):?>
<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=<?=htmlspecialchars($eplMapKey,ENT_QUOTES,'UTF-8')?>&amp;autoload=false" defer></script>
<?php endif;?>
<script src="/evacuation_location_maps.js?v=20261021-1" defer></script>
