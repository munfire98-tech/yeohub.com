<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||!isset($teamDocument)){http_response_code(404);exit;}
(static function(array $team):void {
 $groups=(array)$team['groups'];$early=array_values(array_filter($groups,static function($g){foreach(['비상연락','초기소화','피난유도'] as $k)if(str_starts_with($g['name'],$k))return true;return false;}));
 $badge=static function($m){if(!empty($m['concurrent']))echo '<span class="dual-badge">겸임</span>';};
 $names=static function($members){$names=[];foreach($members as $m)if($m['name']!=='')$names[]=$m['name'].(!empty($m['concurrent'])?' (겸임)':'');return $names?implode(', ',$names).' ('.count($names).'명)':'-';};
 $heads=static function($title,$m):void { ?><div class="cbox"><div class="cbox__h"><?=h($title)?></div><table class="ctab"><tr><th>소속</th><th>성명</th></tr><tr><td><?=v($m['dept'])?></td><td class="nm"><?=v($m['name'])?></td></tr></table></div><?php };
 $groupRows=static function($items)use($names):void {if(!$items){echo '<tr><td>-</td><td>-</td></tr>';return;}foreach($items as $g):?><tr><td><?=v($g['name'])?></td><td class="ppl"><?=h($names($g['members']))?></td></tr><?php endforeach;};
 $taskBox=static function($title,$task):void {?><div class="cbox"><div class="cbox__h"><?=h($title)?></div><table class="ctab"><tr><td class="task"><?=v($task)?></td></tr></table></div><?php };
 $groupTasks=static function($items):void {if(!$items){echo '<tr><td>-</td></tr>';return;}foreach($items as $g){$tasks=array_values(array_unique(array_filter(array_column($g['members'],'task'),static fn($x)=>$x!=='')));?><tr><td class="task"><b><?=v($g['name'])?></b><?php if($tasks):?><br><?=h(implode(' / ',$tasks))?><?php endif;?></td></tr><?php }};
?>
<div class="fp-team-document">
 <div class="team-roster">
  <header class="team-print-head"><h3>자 위 소 방 대 편 성 표</h3><p>自衛消防隊 編成表</p><div class="team-meta"><span>대상물명 : <?=v($team['site_name'])?></span><span>근무형태 : <?=v($team['work_type'])?> · 편성인원 <?=(int)$team['total']?>명</span></div></header>
  <table class="team-table"><colgroup><col style="width:15%"><col style="width:14%"><col style="width:15%"><col style="width:18%"><col></colgroup><thead><tr><th>직책</th><th>성명</th><th>소속</th><th>연락처</th><th>임무</th></tr></thead><tbody>
  <?php foreach(['cmd'=>'대 장','deputy'=>'부 대 장'] as $key=>$label):$m=$team[$key];?><tr><td class="role-cell"><?=h($label)?><?php if($key==='cmd'):?><br><small>(소방안전관리자)</small><?php endif;?><br><?php $badge($m);?></td><td><?=v($m['name'])?></td><td><?=v($m['dept'])?></td><td><?=v($m['tel'])?></td><td class="task"><?=v($m['task'])?></td></tr><?php endforeach;?>
  <?php foreach($groups as $i=>$g):?><tr class="grp-row"><td colspan="5"><?=h(['①','②','③','④','⑤','⑥','⑦','⑧','⑨'][$i]??'•')?> <?=v($g['name'])?> <small><?=count($g['members'])?>명</small></td></tr>
   <?php foreach($g['members'] as $m):?><tr><td><?=v($g['name'])?><br><?php $badge($m);?></td><td><?=v($m['name'])?></td><td><?=v($m['dept'])?></td><td><?=v($m['tel'])?></td><td class="task"><?=v($m['task'])?></td></tr><?php endforeach;?>
  <?php endforeach;?>
  </tbody></table>
  <p class="team-sign">저장일 : <?=h($team['saved'])?><br>소방안전관리자 : <?=v($team['cmd']['name'])?> (서명 또는 인)</p>
 </div>
 <div class="team-chart-page">
  <h3>조직도 <small>소방계획서 2.3.3 Type-Ⅲ · 상시 근무인원 50명 미만 권장</small></h3>
  <div class="chart">
   <div class="chart__t">1. 자위소방대 및 초기대응체계 조직도</div>
   <div class="crow"><div class="ccol"><?php $heads('자위소방대장',$team['cmd']);?><div class="cstem"></div><?php $heads('부대장',$team['deputy']);?></div><div class="clink"></div><div class="ccol"><div class="cbox"><div class="cbox__h">초기대응체계</div><table class="ctab"><tr><th>조</th><th>인원</th></tr><?php $groupRows($early);?></table></div></div></div>
   <div class="cwide"><div class="cbox"><div class="cbox__h">현장대응팀</div><table class="ctab"><tr><th>소속</th><th>인원</th></tr><?php $groupRows($groups);?></table></div></div>
   <div class="chart__t task-heading">2. 자위소방대 및 초기대응체계 임무</div>
   <div class="crow"><div class="ccol"><?php $taskBox('자위소방대장',$team['cmd']['task']);?><div class="cstem"></div><?php $taskBox('부대장',$team['deputy']['task']);?></div><div class="clink"></div><div class="ccol"><div class="cbox"><div class="cbox__h">초기대응체계</div><table class="ctab"><?php $groupTasks($early);?></table></div></div></div>
   <div class="cwide"><div class="cbox"><div class="cbox__h">현장대응팀</div><table class="ctab"><?php $groupTasks($groups);?></table></div></div>
  </div>
 </div>
</div>
<?php })($teamDocument); ?>
