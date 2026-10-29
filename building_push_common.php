<?php
declare(strict_types=1);
require_once __DIR__.'/pro_collaboration.php';
function bp_active(string $uid,array $members):bool{return preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)&&mg_active($members[$uid]??[],'building');}
function bp_periods(DateTimeImmutable $now):array {
 $now=$now->setTimezone(new DateTimeZone('Asia/Seoul'));if($now->format('d')!=='01')return [];
 $p=['building_monthly'=>$now->format('Y-m')];if($now->format('m')==='12')$p['building_annual']=(string)((int)$now->format('Y')+1);return $p;
}
/** Pure completion rules, matching saved monthly records and the plan's final-review snapshot. */
function bp_pending(array $periods,array $monthly,array $plans):array {
 $out=[];
 if(isset($periods['building_monthly'])&&empty($monthly[$periods['building_monthly']]))$out['building_monthly']=$periods['building_monthly'];
 if(isset($periods['building_annual'])){
  $year=$periods['building_annual'];$reviewed=false;
  $latest=null;foreach($plans as $p){if((string)($p['plan_year']??substr((string)($p['plan_date']??$p['created_at']??''),0,4))!==$year)continue;if($latest===null||[(string)($p['updated_at']??''),(string)($p['id']??'')]>[(string)($latest['updated_at']??''),(string)($latest['id']??'')])$latest=$p;}
  if($latest!==null){$hash=hash('sha256',json_encode([$latest['sections']??[],$latest['source_bundle']??[],$latest['plan_year']??0,$latest['plan_date']??''],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));$reviewed=!empty($latest['final_review']['hash'])&&hash_equals($hash,(string)$latest['final_review']['hash']);}
  if(!$reviewed)$out['building_annual']=$year;
 }
 return $out;
}
function bp_items(string $uid,array $periods):array {
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return [];
 $monthly=[];foreach($periods as $kind=>$period)if($kind==='building_monthly'&&preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',(string)$period))$monthly[$period]=mg_read(__DIR__.'/data/worklog/'.preg_replace('/[^A-Za-z0-9_]/','_',$uid).'/m'.$period.'.json');
 $plans=[];if(isset($periods['building_annual']))foreach(mg_read(__DIR__.'/data/fireplan/'.$uid.'/_index.json') as $r){$id=(string)($r['id']??'');if(preg_match('/^[0-9]{1,64}$/D',$id))$plans[]=mg_read(__DIR__.'/data/fireplan/'.$uid.'/'.$id.'.json');}
 return bp_pending($periods,$monthly,$plans);
}
function bp_body(array $items):string {
 $parts=[];if(isset($items['building_monthly']))$parts[]=(int)substr($items['building_monthly'],5).'월 소방안전관리 업무수행기록을 작성해 주세요.';
 if(isset($items['building_annual']))$parts[]=$items['building_annual'].'년도 소방계획서를 작성하고 최종 확인해 주세요.';
 return implode(' ',$parts);
}
function bp_url(array $items):string {
 if(count($items)!==1)return '/FireDesk.php?building_notice=1';
 return isset($items['building_monthly'])?'/work_log.php?year='.substr($items['building_monthly'],0,4):'/fire_plan_book.php?year='.$items['building_annual'];
}
function bp_resolve(array $m,string $actor,array $members,bool $receiving=false):?array {
 if($receiving&&!pc_active($actor))return null;
 if(!bp_active($actor,$members)||($m['actor']??'')!==$actor||($m['scope']??'')!==vp_scope($actor,$members))return null;
 $test=!empty($m['building_test']);$items=$test?['building_monthly'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m')]:bp_items($actor,(array)($m['periods']??[]));
 if($receiving&&!$test){$s=mg_read(vp_file());$sub=$s['subscriptions'][$m['subscription_id']]??null;if(!$sub||$sub['scope']!==$m['scope']||$sub['device']!==$m['device'])return null;
  $prefs=vp_preferences($sub['prefs']??[]);$items=array_filter($items,static fn($v,$k)=>!empty($prefs[$k]),ARRAY_FILTER_USE_BOTH);
  if(($m['due']??'')!==(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d'))return null;
 }
 if($receiving&&!$items)return null;
 $action=$test?'/FireDesk.php?building_notice=test':bp_url($items);if(!$test&&$items){$system=mg_read(mg_state_file());$member=$members[$actor];if(!pc_active($actor)||mg_connection_manager($member,$members)===''||mg_link_status($actor,$member,$system)!=='accepted')$action='/FireDesk.php?building_notice=1';}
 return ['title'=>$test?'[테스트] 건물 업무 알림':'소방안전관리 업무 안내','body'=>$test?'이번 달 업무수행기록 작성 안내입니다. 눌러서 건물 업무를 확인해 주세요.':($items?bp_body($items):'안내한 업무가 작성·확인되었습니다.'),'url'=>'/visit_push_open.php?message='.$m['id'],'action_url'=>$action,'items'=>$items];
}
function bp_queue(array &$s,array $sub,array $periods,DateTimeImmutable $now,array $items):?string {
 $now=$now->setTimezone(new DateTimeZone('Asia/Seoul'));if(!$periods||!$items)return null;$scope=$sub['scope'];$clock=vd_times($s['digest_times'][$scope]??[])['am'];$at=new DateTimeImmutable($now->format('Y-m-d').' '.$clock,new DateTimeZone('Asia/Seoul'));
 if($now<$at||$at->getTimestamp()<(int)($s['building_since'][$scope]??PHP_INT_MAX)||$at->getTimestamp()<(int)($s['digest_times'][$scope]['updated']??0))return null;
 $prefs=vp_preferences($sub['prefs']??[]);if(!array_filter($items,static fn($v,$k)=>!empty($prefs[$k]),ARRAY_FILTER_USE_BOTH))return null;
 $accountKinds=[];foreach($s['subscriptions']??[$sub] as $deviceSub)if(($deviceSub['scope']??'')===$scope){foreach(vp_preferences($deviceSub['prefs']??[]) as $kind=>$enabled)if($enabled)$accountKinds[$kind]=true;}$periods=array_intersect_key($periods,$accountKinds);
 $dedup=hash('sha256',$scope.'|'.$sub['id'].'|building|'.$now->format('Y-m-d'));
 foreach($s['messages']??[] as $id=>$m)if(($m['dedup']??'')===$dedup){if(($m['status']??'')==='failed'&&in_array((int)($m['http_status']??0),[429,500,502,503,504],true)&&($m['attempts']??0)<3&&($m['last_attempt']??$m['created'])<$now->getTimestamp()-300){$s['messages'][$id]['status']='sending';return $id;}return null;}
 $id=bin2hex(random_bytes(24));$s['messages'][$id]=['id'=>$id,'actor'=>$sub['actor'],'scope'=>$scope,'subscription_id'=>$sub['id'],'device'=>$sub['device'],'kind'=>'building','periods'=>$periods,'event'=>'building_'.$now->format('Y-m-d'),'due'=>$now->format('Y-m-d'),'created'=>$now->getTimestamp(),'dedup'=>$dedup,'status'=>'sending','attempts'=>0];return $id;
}
