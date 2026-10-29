<?php
declare(strict_types=1);
/** One plan per receipt, two derived calendar events. No duplicate source of dates. */
function cf_options(array $input,array $old=[]):array {
 $period=(string)($input['work_period']??$old['work_period']??'10');
 if(!in_array($period,['10','20','custom'],true))throw new RuntimeException('공사기간을 선택해 주세요.');
 $include=(string)($input['include_received']??(isset($old['include_received'])?($old['include_received']?'1':'0'):'1'));
 $business=(string)($input['work_business_only']??(isset($old['work_business_only'])?($old['work_business_only']?'1':'0'):'0'));
 if(!in_array($include,['0','1'],true)||!in_array($business,['0','1'],true))throw new RuntimeException('공사기간 계산 옵션을 확인해 주세요.');
 $custom=trim((string)($input['custom_work_due']??$old['custom_work_due']??''));
 if($period==='custom'&&!rh_valid_date($custom))throw new RuntimeException('공사 완료기한을 직접 선택해 주세요.');
 return ['work_period'=>$period,'include_received'=>$include==='1','work_business_only'=>$business==='1','custom_work_due'=>$period==='custom'?$custom:''];
}
function cf_calculate(array $report,array $options,array $calendar,string $actualWork=''):array {
 $received=(string)($report['received_date']??'');
 if(($report['status']??'')!=='completed'||!rh_valid_date($received))throw new RuntimeException('보고서 접수 완료와 실제 접수일을 먼저 저장해 주세요.');
 if($options['work_period']==='custom'){
  if($options['custom_work_due']<$received)throw new RuntimeException('공사 완료기한은 접수일보다 빠를 수 없습니다.');
  $work=['date'=>$options['custom_work_due'],'ready'=>true,'stale'=>false,'hash'=>'manual'];
 }else $work=rh_count_days($received,(int)$options['work_period'],$options['include_received'],$options['work_business_only'],$calendar);
 if($actualWork!==''&&(!rh_valid_date($actualWork)||$actualWork<$received||$actualWork>date('Y-m-d')))throw new RuntimeException('실제 공사 완료일은 접수일부터 오늘 사이로 입력해 주세요.');
 $filing=($work['ready']||$actualWork!=='')?rh_count_days($actualWork!==''?$actualWork:$work['date'],10,false,true,$calendar):['date'=>'','ready'=>false,'stale'=>true,'hash'=>''];
 return ['received_date'=>$received,'work_due'=>$work['date'],'filing_due'=>$filing['date'],'filing_basis'=>$actualWork!==''?$actualWork:$work['date'],'filing_basis_type'=>$actualWork!==''?'actual':'planned','calendar_ready'=>$work['ready']&&$filing['ready'],'calendar_stale'=>$work['stale']||$filing['stale'],'calendar_hash'=>hash('sha256',$work['hash'].'|'.$filing['hash'])];
}
function cf_months(array $s):array {
 $months=[];foreach($s['followups']??[] as $id=>$r){if(($r['status']??'')!=='planned')continue;
  $parent=$s['reports'][$id]??[];
  foreach([$parent['received_date']??'',$r['work_due']??'',$r['custom_work_due']??'',$r['actual_work_date']??''] as $d)if(rh_valid_date($d))$months=array_merge($months,rh_months($d));
 }return array_values(array_unique($months));
}
function cf_preview(array $s,array $input,array $calendar):array {
 $id=(string)($input['visit_id']??'');$parent=$s['reports'][$id]??null;
 if(!$parent)throw new RuntimeException('연결할 보고서 접수 일정을 찾지 못했습니다.');
 $old=$s['followups'][$id]??[];$options=cf_options($input,$old);$actual=trim((string)($input['actual_work_date']??$old['actual_work_date']??''));
 return ['visit_id'=>$id,'name'=>$parent['name'],'uid'=>$parent['uid'],'report_revision'=>$parent['revision'],'revision'=>$old['revision']??0,'actual_work_date'=>$actual,'status'=>$old['status']??'planned','filed_date'=>$old['filed_date']??'','memo'=>$old['memo']??'']+$options+cf_calculate($parent,$options,$calendar,$actual);
}
function cf_upsert(array &$s,array $input,array $calendar,bool $create):array {
 $id=(string)($input['visit_id']??'');$old=$s['followups'][$id]??null;
 if($create&&$old){$requested=cf_options($input);foreach(['work_period','include_received','work_business_only','custom_work_due'] as $key)if($requested[$key]!==$old[$key])throw new RuntimeException('이미 등록된 공사·보고 일정이 있습니다. 기존 일정에서 수정해 주세요.');return $old;} // Retry-safe unique pair.
 if(!$create&&(!$old||(int)($input['revision']??0)!==(int)$old['revision']))throw new RuntimeException('공사·이행완료보고 일정이 변경되었습니다. 다시 열어 주세요.');
 $p=cf_preview($s,$input,$calendar);
 if((int)($input['report_revision']??0)!==$p['report_revision'])throw new RuntimeException('접수일이 변경되었습니다. 날짜를 다시 계산해 주세요.');
 if(!$p['calendar_ready'])throw new RuntimeException('공식 공휴일 자료 확인 후 두 기한을 계산할 수 있습니다. 잠시 후 다시 시도해 주세요.');
 foreach(['work_due','filing_due'] as $key)if(($input['expected_'.$key]??'')!==$p[$key])throw new RuntimeException('공휴일 또는 계산 날짜가 변경되었습니다. 다시 계산해 확인해 주세요.');
 $status=(string)($input['status']??'planned');$filed=trim((string)($input['filed_date']??''));$memo=trim((string)($input['memo']??''));
 if(!in_array($status,['planned','completed','cancelled'],true)||mb_strlen($memo)>2000)throw new RuntimeException('일정 상태와 메모를 확인해 주세요.');
 if($status==='completed'&&($p['actual_work_date']===''||!rh_valid_date($filed)||$filed<$p['actual_work_date']||$filed>date('Y-m-d')))throw new RuntimeException('실제 공사 완료일과 그 이후부터 오늘 사이의 보고일을 입력해 주세요.');
 if($old&&($old['work_due']!==$p['work_due']||$old['filing_due']!==$p['filing_due']))cf_changes($s,$old,$p,'일정 설정 변경');
 $r=array_replace($p,['id'=>'followup_'.$id,'address'=>$s['reports'][$id]['address'],'status'=>$status,'filed_date'=>$status==='completed'?$filed:'','memo'=>$memo,'review_received_date'=>$p['received_date'],'needs_review'=>false,'review_reason'=>'','suspended'=>false,'revision'=>(int)($old['revision']??0)+1,'created_at'=>$old['created_at']??date('c'),'updated_at'=>date('c')]);
 $s['followups'][$id]=$r;return $r;
}
function cf_changes(array &$s,array $old,array $next,string $reason):void {
 foreach(['work_due'=>'공사 완료기한','filing_due'=>'이행완료보고 마감'] as $key=>$label)if(($old[$key]??'')!==($next[$key]??''))rc_notice($s,['id'=>$old['id'],'name'=>$old['name'].' · '.$label],(string)($old[$key]??''),(string)($next[$key]??''),$reason);
}
function cf_refresh(array &$s,array $calendar):void {
 foreach($s['followups']??[] as $id=>$old){
  if(($old['status']??'')!=='planned')continue;$r=$old;$parent=$s['reports'][$id]??[];
  if(($parent['status']??'')!=='completed'||empty($parent['received_date'])){$r['suspended']=true;$r['needs_review']=true;$r['review_reason']='접수 완료 상태 확인 필요';}
  else{
   $r['suspended']=false;
   try{$calc=cf_calculate($parent,cf_options([],$r),$calendar,(string)($r['actual_work_date']??''));
    $r['calendar_ready']=$calc['calendar_ready'];$r['calendar_stale']=$calc['calendar_stale'];
    if(!empty($r['actual_work_date']))$calc['work_due']=$r['work_due'];
    if($calc['calendar_ready']){cf_changes($s,$r,$calc,$r['received_date']!==$calc['received_date']?'접수일 변경':'공휴일 자료 변경');$r=array_replace($r,$calc);}
    $r['needs_review']=($r['work_period']==='custom'&&($r['review_received_date']??'')!==$parent['received_date'])||!$calc['calendar_ready'];
    $r['review_reason']=!$calc['calendar_ready']?'공식 공휴일 자료 확인 대기':($r['needs_review']?'접수일 변경 · 지정한 공사 완료기한 재확인 필요':'');
   }catch(RuntimeException $e){$r['needs_review']=true;$r['suspended']=true;$r['review_reason']='접수일과 실제 공사 완료일 순서 확인 필요';}
  }
  if($r!==$old){$r['revision']++;$r['updated_at']=date('c');$s['followups'][$id]=$r;}
 }
}
function cf_payload(array $s,string $month):array {
 $events=[];$linked=[];
 foreach($s['followups']??[] as $id=>$r){
  $parent=$s['reports'][$id]??[];$relevant=substr((string)($parent['date']??''),0,7)===$month||substr((string)($parent['received_date']??''),0,7)===$month||substr((string)($s['visits'][$id]['date']??''),0,7)===$month;
  if($r['status']!=='cancelled'&&empty($r['suspended']))foreach(['work'=>'work_due','filing'=>'filing_due'] as $kind=>$field){
   if(empty($r[$field])||substr($r[$field],0,7)!==$month)continue;$relevant=true;
   $events[]=['id'=>$kind.'_'.$id,'visit_id'=>$id,'uid'=>$r['uid'],'name'=>$r['name'],'kind'=>$kind,'date'=>$r[$field],'status'=>$kind==='work'?(!empty($r['actual_work_date'])?'completed':'planned'):$r['status'],'calendar_ready'=>$r['calendar_ready'],'needs_review'=>$r['needs_review']];
  }
  if($relevant)$linked[$id]=$r;
 }
 return ['followup_events'=>$events,'followups'=>(object)$linked];
}
