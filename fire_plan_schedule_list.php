<?php
// Embedded by authenticated education/training record lists; no records are created on GET.
if(!isset($fpscFolder)||!in_array($fpscFolder,['train','jawi'],true))return;
require_once __DIR__.'/fire_plan_schedule_service.php';
$fpscRows=[];$fpscYear=(int)substr(fpsc_today(),0,4);
foreach(fp_list_plans() as $fpscIndex){
 $fpscPlan=fp_load_plan((string)($fpscIndex['id']??''));if(!$fpscPlan)continue;
 if(isset($recordYear)){if(fp_plan_year($fpscPlan)!==$recordYear)continue;}elseif(fp_plan_year($fpscPlan)<$fpscYear||fp_plan_year($fpscPlan)>$fpscYear+1)continue;
 foreach(fpsc_events($fpscPlan) as $fpscEvent){
  if(($fpscEvent['kind']==='jawi')!==($fpscFolder==='jawi'))continue;
  $fpscArgs=['id'=>$fpscPlan['id'],'record'=>$fpscEvent['id']];foreach(['modal','embed'] as $fpscKey)if(($_GET[$fpscKey]??'')==='1')$fpscArgs[$fpscKey]='1';
  if(is_admin()&&is_string($_GET['uid']??null))$fpscArgs['uid']=$_GET['uid'];
  $fpscRows[]=['event'=>$fpscEvent,'href'=>'/fire_plan_schedule.php?'.http_build_query($fpscArgs),'exists'=>fpsc_record($fpscPlan,$fpscEvent)!==null];
 }
}
usort($fpscRows,static fn($a,$b)=>strcmp($a['event']['date'],$b['event']['date']));
if($fpscRows):?>
<section class="card" style="margin-bottom:18px;padding:20px;border-color:#cdddf4;background:#f6f9ff"><h2 style="font-size:17px;margin:0 0 8px">소방계획서에 정해 둔 일정</h2><p style="font-size:13px;color:#617795;margin:0 0 14px">예정 일정입니다. 실제로 실시한 뒤 날짜와 결과를 확인해 기록하세요.</p>
<?php foreach($fpscRows as $fpscRow):?><a href="<?=h($fpscRow['href'])?>" style="display:flex;gap:12px;justify-content:space-between;align-items:center;padding:12px 0;border-top:1px solid #dce6f4;color:#315580;text-decoration:none;font-size:14px"><span><b><?=h(fpsc_kinds()[$fpscRow['event']['kind']])?></b><small style="display:block;color:#7589a3"><?=h(fpsc_label($fpscRow['event']))?></small></span><span><?=$fpscRow['exists']?'기록 이어서 보기':'실시기록 작성'?> →</span></a><?php endforeach;?></section>
<?php endif;?>
