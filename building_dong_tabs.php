<?php
// Included only by the authenticated basic-information page.
if(!isset($d)||!is_array($d))return;
$dtRows=$d['bd_dong_list']??[];
if(is_string($dtRows))$dtRows=json_decode($dtRows,true);
$dtRows=is_array($dtRows)?array_values(array_filter($dtRows,'is_array')):[];
$dtPick=(string)($d['bd_dong_pick']??'');$dtPrimary=null;
foreach($dtRows as $i=>$row)if(($row['dong']??'')===$dtPick){$dtPrimary=$i;break;}
if($dtPrimary!==null){$row=$dtRows[$dtPrimary];unset($dtRows[$dtPrimary]);array_unshift($dtRows,$row);}
if(!$dtRows)return;
$dtEsc=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<style>
.dong-info{margin:18px 0;background:#fff;border:1px solid #dbe6ee;border-radius:14px;padding:18px}.dong-info h3{font-size:16px;color:#28495c;margin:0 0 7px}.dong-info .dt-help{font-size:12px;color:#708597;line-height:1.7;margin:0 0 14px}.dt-tabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px}.dt-tabs button{border:1px solid #dce6ed;border-radius:9px;background:#f6f9fb;padding:9px 13px;color:#61788c;font:600 13px system-ui;cursor:pointer}.dt-tabs button[aria-selected="true"]{color:#176f63;background:#eaf7f1;border-color:#94cdbb}.dt-tabs button:focus-visible{outline:3px solid #479eb9;outline-offset:2px}.dt-panel[hidden]{display:none}.dt-panel h4{margin:0 0 12px;font-size:15px;color:#27475b}.dt-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 20px;margin:0}.dt-fields>div{padding:11px 0;border-bottom:1px solid #eef2f5;min-width:0}.dt-fields dt{font-size:11px;color:#768b99;margin-bottom:6px}.dt-fields dd{font-size:14px;color:#2c475a;margin:0;overflow-wrap:anywhere}@media(max-width:600px){.dt-fields{grid-template-columns:repeat(2,minmax(0,1fr))}.dong-info{padding:14px}}@media print{.dt-tabs,.dong-info .dt-help{display:none!important}.dong-info{border:0;padding:0}.dt-panel,.dt-panel[hidden]{display:block!important;break-inside:avoid;page-break-inside:avoid;margin:0 0 7mm}.dt-panel h4{border-bottom:2px solid #476b78;padding-bottom:2mm}.dt-fields{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
<section class="dong-info" aria-label="관리 대상 동별 기본정보">
<h3>관리 대상 동별 기본정보</h3><p class="dt-help">동을 선택하면 해당 동의 저장된 상세 현황을 볼 수 있습니다. 값이 없는 항목은 미입력으로 표시합니다. 주소와 관리자 연락처는 공통정보를 사용합니다.</p>
<div class="dt-tabs" role="tablist" aria-label="관리할 동 선택">
<?php foreach($dtRows as $i=>$g):$primary=$i===0&&$dtPrimary!==null; ?>
<button type="button" role="tab" id="dt-tab-<?=$i?>" aria-controls="dt-panel-<?=$i?>" aria-selected="<?=$i===0?'true':'false'?>" tabindex="<?=$i===0?'0':'-1'?>"><?=$primary?'기준동 · ':''?><?=$dtEsc($g['dong']??'동명 미상')?></button>
<?php endforeach; ?>
</div>
<?php foreach($dtRows as $i=>$g):
 $primary=$i===0&&$dtPrimary!==null;
 if($primary)foreach(['floor_a'=>'floor_a','floor_b'=>'floor_b','arch_area'=>'bd_area_arch','area'=>'area_t','struct'=>'bd_struct','height'=>'bd_height','use'=>'bd_use_main'] as $to=>$from)if(array_key_exists($from,$d))$g[$to]=$d[$from];
 $number=static function($v,$unit)use($dtEsc){return is_numeric($v)?$dtEsc(rtrim(rtrim(number_format((float)$v,2,'.',','),'0'),'.')).$unit:'미입력';};
 $fields=['용도'=>trim((string)($g['use']??''))?:'미입력','층수'=>'지상 '.($g['floor_a']??'—').'층 / 지하 '.($g['floor_b']??'—').'층','건축면적'=>$number($g['arch_area']??'','㎡'),'연면적'=>$number($g['area']??'','㎡'),'구조'=>trim((string)($g['struct']??''))?:'미입력','높이'=>$number($g['height']??'','m')];
 $detail=is_array($g['patch']??null)?$g['patch']:[];
 $detailLabels=['bd_use_etc'=>'상세 용도','bd_struct_etc'=>'상세 구조','bd_area_plat'=>'대지면적','bd_area_vl'=>'용적률 산정 연면적','bd_bcrat'=>'건폐율','bd_vlrat'=>'용적률','bd_pms_day'=>'허가일','bd_stcns_day'=>'착공일','bd_use_apr'=>'사용승인일','bd_park'=>'총주차대수','bd_elev'=>'승용승강기','bd_hhld'=>'세대수','bd_family'=>'가구수','bd_ho'=>'호수','bd_main_bld'=>'주건축물수','bd_atch_bld'=>'부속건축물수','bd_seismic'=>'내진설계','bd_seismic_ablty'=>'내진능력','bd_energy'=>'에너지효율등급'];
 foreach($detailLabels as $key=>$label){
   $value=$primary&&array_key_exists($key,$d)?$d[$key]:($detail[$key]??'');
   $value=is_scalar($value)?trim((string)$value):'';
   if(in_array($key,['bd_area_plat','bd_area_vl'],true))$value=$number($value,'㎡');
   elseif(in_array($key,['bd_bcrat','bd_vlrat'],true))$value=$number($value,'%');
   elseif($value==='')$value='미입력';
   $fields[$label]=$value;
 }

?>
<div class="dt-panel" role="tabpanel" tabindex="0" id="dt-panel-<?=$i?>" aria-labelledby="dt-tab-<?=$i?>" <?=$i===0?'':'hidden'?>>
<h4><?=$primary?'기준동 · ':''?><?=$dtEsc($g['dong']??'동명 미상')?></h4><dl class="dt-fields">
<?php foreach($fields as $label=>$value): ?><div><dt><?=$dtEsc($label)?></dt><dd><?=$dtEsc($value)?></dd></div><?php endforeach; ?>
</dl></div>
<?php endforeach; ?>
</section>
<script>
(()=>{const root=document.querySelector('.dong-info');if(!root)return;const tabs=[...root.querySelectorAll('[role="tab"]')],panels=[...root.querySelectorAll('[role="tabpanel"]')];function select(i,focus){tabs.forEach((t,n)=>{t.setAttribute('aria-selected',n===i?'true':'false');t.tabIndex=n===i?0:-1;panels[n].hidden=n!==i;});if(focus)tabs[i].focus();}tabs.forEach((t,i)=>{t.addEventListener('click',()=>select(i,false));t.addEventListener('keydown',e=>{let n=i;if(e.key==='ArrowRight')n=(i+1)%tabs.length;else if(e.key==='ArrowLeft')n=(i+tabs.length-1)%tabs.length;else if(e.key==='Home')n=0;else if(e.key==='End')n=tabs.length-1;else return;e.preventDefault();select(n,true);});});})();
</script>
