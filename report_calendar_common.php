<?php
declare(strict_types=1);
require_once __DIR__.'/report_holidays.php';
require_once __DIR__.'/report_followup_common.php';
function rc_basis(array $visit):string {return !empty($visit['inspection_end'])?$visit['inspection_end']:$visit['date'];}
function rc_months(array $s,string $month=''):array {
 $months=$month!==''?[$month]:rh_months(date('Y-m-d'));
 foreach($s['reports']??[] as $r){if(($r['status']??'')!=='planned')continue;$v=$s['visits'][$r['visit_id']]??null;if($v)$months=array_merge($months,rh_months(rc_basis($v)));}
 return array_values(array_unique(array_merge($months,cf_months($s))));
}
function rc_notice(array &$s,array $r,string $old,string $new,string $reason):void {
 $seq=(int)($s['report_notice_seq']??0)+1;$s['report_notice_seq']=$seq;
 $s['report_notices'][]=['seq'=>$seq,'report_id'=>$r['id'],'name'=>$r['name'],'before'=>$old,'after'=>$new,'reason'=>$reason,'at'=>date('c')];
 if(count($s['report_notices'])>200)$s['report_notices']=array_slice($s['report_notices'],-200);
}
function rc_refresh(array &$s,array $calendar):void {
 foreach($s['reports']??[] as $key=>$r){
  if(($r['status']??'')!=='planned')continue;$v=$s['visits'][$r['visit_id']]??null;if(!$v)continue;
  if($v['status']==='cancelled'){$r['status']='cancelled';$r['cancel_reason']='visit';}
  else{
   $basis=rc_basis($v);$oldBasis=(string)($r['basis']??'');$calc=rh_deadline($basis,$calendar);$old=(string)($r['pending_deadline']??$r['deadline']??'');
   $r['calendar_stale']=$calc['stale'];$r['calendar_ready']=$calc['ready'];
   if($calc['ready']){
    $r['deadline']=$calc['deadline'];$r['calendar_hash']=$calc['hash'];$r['basis']=$basis;$r['basis_type']=empty($v['inspection_end'])?'planned':'actual';
    if($old!==$r['deadline']){rc_notice($s,$r,$old,$r['deadline'],$oldBasis!==$basis?'점검일 변경':'공휴일 자료 변경');if(($r['date_mode']??'auto')==='auto')$r['date']=$r['deadline'];}
   }
   if(!$calc['ready']&&$oldBasis!==$basis){$r['pending_deadline']=$old;$r['deadline']='';$r['basis']=$basis;$r['basis_type']=empty($v['inspection_end'])?'planned':'actual';}
   if($calc['ready'])unset($r['pending_deadline']);
   $r['basis_before']=$basis;$r['needs_reschedule']=!empty($r['deadline'])&&$r['date']>$r['deadline'];
  }
  if($r!==$s['reports'][$key]){$r['revision']++;$r['updated_at']=date('c');$s['reports'][$key]=$r;}
 }
 cf_refresh($s,$calendar);
}
function rc_proposals(array $s,array $visits,array $calendar):array {
 $out=[];foreach($visits as $v){if(!$v||in_array($v['status'],['cancelled','missed'],true)||isset($s['reports'][$v['id']]))continue;
  if((int)($v['approval_month']??0)!==(int)substr($v['date'],5,2))continue;
  $calc=rh_deadline(rc_basis($v),$calendar);$out[]=['visit_id'=>$v['id'],'name'=>$v['name'],'date'=>$v['date'],'basis'=>rc_basis($v),'basis_type'=>empty($v['inspection_end'])?'planned':'actual']+$calc;
 }return $out;
}
function rc_create(array &$s,array $input,array $calendar):array {
 $id=(string)($input['visit_id']??'');$v=$s['visits'][$id]??null;
 if(!$v||in_array($v['status'],['cancelled','missed'],true))throw new RuntimeException('연결할 방문 일정을 확인해 주세요.');
 if(isset($s['reports'][$id]))return $s['reports'][$id]; // Unique linked report, including retry after lost response.
 $calc=rh_deadline(rc_basis($v),$calendar);$date=trim((string)($input['date']??''));
 if($date===''){$date=$calc['deadline'];$mode='auto';}else{$mode=($input['date_mode']??'manual')==='auto'&&$date===$calc['deadline']?'auto':'manual';}
 if(!rh_valid_date($date))throw new RuntimeException('공휴일 조회가 준비되지 않았습니다. 접수 예정일을 직접 선택해 주세요.');
 $r=['id'=>'report_'.$id,'visit_id'=>$id,'uid'=>$v['uid'],'name'=>$v['name'],'address'=>$v['address'],'date'=>$date,'date_mode'=>$mode,'deadline'=>$calc['deadline'],'basis'=>rc_basis($v),'basis_before'=>rc_basis($v),'basis_type'=>empty($v['inspection_end'])?'planned':'actual','status'=>'planned','received_date'=>'','memo'=>'','calendar_hash'=>$calc['hash'],'calendar_ready'=>$calc['ready'],'calendar_stale'=>$calc['stale'],'needs_reschedule'=>$calc['ready']&&$date>$calc['deadline'],'revision'=>1,'created_at'=>date('c'),'updated_at'=>date('c')];
 $s['reports'][$id]=$r;return $r;
}
function rc_save(array &$s,array $input):array {
 $id=(string)($input['visit_id']??'');$old=$s['reports'][$id]??null;
 if(!$old)throw new RuntimeException('보고서 접수 일정을 찾지 못했습니다.');
 if((int)($input['revision']??0)!==$old['revision'])throw new RuntimeException('보고서 일정이 변경되었습니다. 다시 열어 확인해 주세요.');
 $r=$old;$r['date']=(string)($input['date']??'');$r['status']=(string)($input['status']??'planned');$r['received_date']=(string)($input['received_date']??'');$r['memo']=trim((string)($input['memo']??''));
 if(!rh_valid_date($r['date'])||!in_array($r['status'],['planned','completed','cancelled'],true)||mb_strlen($r['memo'])>2000)throw new RuntimeException('접수 일정 입력 내용을 확인해 주세요.');
 $inspectionEnd=(string)($s['visits'][$id]['inspection_end']??'');
 if($r['status']==='completed'&&(!rh_valid_date($r['received_date'])||$r['received_date']>date('Y-m-d')||($inspectionEnd!==''&&$r['received_date']<$inspectionEnd)))throw new RuntimeException('실제 접수완료일은 오늘까지 입력해 주세요. 실제 점검 종료일이 기록되어 있다면 그 날짜 이후여야 합니다.');
 if($r['status']==='completed'&&!empty($s['followups'][$id]['actual_work_date'])&&$r['received_date']>$s['followups'][$id]['actual_work_date'])throw new RuntimeException('접수일은 기록된 실제 공사 완료일보다 늦을 수 없습니다.');
 if($r['status']!=='completed')$r['received_date']='';
 if(($s['visits'][$id]['status']??'cancelled')==='cancelled'&&$r['status']!=='cancelled'&&$old['status']!=='completed')throw new RuntimeException('취소된 방문 일정의 접수를 다시 등록할 수 없습니다.');
 if(($input['date_mode']??'')==='auto'){
  if(empty($old['calendar_ready'])||empty($old['deadline'])){
   if($r['status']!=='completed')throw new RuntimeException('공휴일 자료 확인 후 자동 날짜로 바꿀 수 있습니다.');
   $r['date']=$old['date']; // Saving a real receipt does not require a new forecast.
  }else $r['date']=$old['deadline'];
  $r['date_mode']='auto';
 }else{$r['date_mode']=($input['date_mode']??'')==='manual'||$r['date']!==$old['date']?'manual':($old['date_mode']??'manual');}
 $r['needs_reschedule']=$r['status']==='planned'&&!empty($r['deadline'])&&$r['date']>$r['deadline'];
 $r['revision']++;$r['updated_at']=date('c');$s['reports'][$id]=$r;return $r;
}
function rc_payload(array $s,string $month,array $calendar):array {
 $reports=[];$overdue=0;
 foreach($s['reports']??[] as $r){if($r['status']==='planned'&&!empty($r['deadline'])&&$r['deadline']<date('Y-m-d'))$overdue++;
  if(substr($r['date'],0,7)===$month||($r['status']==='completed'&&substr($r['received_date'],0,7)===$month))$reports[]=$r;
 }
 $linked=[];foreach($s['reports']??[] as $key=>$r)if(substr((string)($s['visits'][$key]['date']??''),0,7)===$month)$linked[$key]=$r;
 $holidays=$calendar['months'][$month]['days']??[];$state=$calendar['months'][$month]??[];
 return ['reports'=>$reports,'linked_reports'=>(object)$linked,'report_notices'=>array_values(array_filter($s['report_notices']??[],fn($n)=>$n['seq']>(int)($s['report_notices_seen']??0))),'report_overdue'=>$overdue,'holidays'=>(object)$holidays,'holiday_checked_at'=>$state['checked_at']??0,'holiday_stale'=>empty($state['checked_at'])||time()-(int)$state['checked_at']>21600||!empty($state['error'])]+cf_payload($s,$month);
}

/** Read-only review across all months. The planned receipt date, not legal deadline, triggers this. */
function rc_review_receipts(array $store,string $today,callable $allowed):array {
 $items=[];
 foreach($store['reports']??[] as $id=>$r){
  if(!is_array($r)||($r['status']??'')!=='planned'||!rh_valid_date((string)($r['date']??''))||$r['date']>=$today)continue;
  $visit=$store['visits'][$id]??null;
  if(!is_array($visit)||in_array($visit['status']??'',['cancelled','missed'],true)||($visit['uid']??'')!==($r['uid']??'')||!$allowed((string)$r['uid']))continue;
  $items[]=$r;
 }
 usort($items,static fn($a,$b)=>strcmp($a['date'],$b['date'])?:strcmp($a['visit_id'],$b['visit_id']));
 return ['items'=>$items,'total'=>count($items)];
}
