<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/manager_visit_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 $actor=mg_uid();$members=mg_members();
 if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);throw new RuntimeException('파트너 계정으로 로그인해 주세요.');}
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new RuntimeException('지원하지 않는 요청입니다.');}
 if($method==='POST'&&(!is_string($_POST['csrf']??null)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf']))){http_response_code(403);throw new RuntimeException('요청이 만료되었습니다. 새로고침해 주세요.');}
 session_write_close();
 $file=__DIR__.'/data/manager_visits/'.hash('sha256',$actor.'|'.(string)($members[$actor]['created']??'')).'.php';
 $send=static function(array $data):void {echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);};
 if($method==='GET'){
  if(isset($_GET['review_visits'])||isset($_GET['review_reports'])){
   $system=mg_read(mg_state_file());$drafts=array_fill_keys(array_column(ma_map_rows($actor,$members,$system),'uid'),true);
   $allowed=static fn(string $uid):bool=>isset($drafts[$uid])||(isset($members[$uid])&&mg_can_view($actor,$uid,$members,$system));
   $send(isset($_GET['review_reports'])?rc_review_receipts(mg_read($file),date('Y-m-d'),$allowed):mvc_review_visits(mg_read($file),date('Y-m-d'),$allowed));exit;
  }

  $month=(string)($_GET['month']??'');if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$month))throw new RuntimeException('조회할 월을 확인해 주세요.');
  $data=mg_read($file);$calendar=rh_sync(rc_months($data,$month));
  $data=mg_tx($file,static function(array &$s)use($calendar){rc_refresh($s,$calendar);return $s;},true);
  $rows=array_values(array_filter($data['visits']??[],fn($r)=>substr($r['date'],0,7)===$month||($r['status']==='completed'&&substr($r['visited_date'],0,7)===$month)));
  $send(['visits'=>$rows,'days'=>(object)array_filter($data['days']??[],fn($key)=>substr((string)$key,0,7)===$month,ARRAY_FILTER_USE_KEY)]+rc_payload($data,$month,$calendar));exit;
 }
 $act=(string)($_POST['act']??'save');
 if($act==='save_day'){$send(['day'=>mg_tx($file,fn(array &$s)=>mvc_save_day($s,$_POST),true)]);exit;}
 if($act==='report_ack'){
  $seq=max(0,(int)($_POST['seq']??0));mg_tx($file,static function(array &$s)use($seq){$s['report_notices_seen']=max((int)($s['report_notices_seen']??0),min($seq,(int)($s['report_notice_seq']??0)));},true);$send([]);exit;
 }
 if(in_array($act,['followup_preview','followup_create','followup_save'],true)){
  $id=(string)($_POST['visit_id']??'');$snapshot=mg_read($file);$parent=$snapshot['reports'][$id]??null;if(!$parent)throw new RuntimeException('연결할 접수 일정을 찾지 못했습니다.');
  $months=rc_months($snapshot);if(rh_valid_date((string)($parent['received_date']??'')))$months=array_merge($months,rh_months($parent['received_date']));
  foreach(['custom_work_due','actual_work_date'] as $key)if(rh_valid_date((string)($_POST[$key]??'')))$months=array_merge($months,rh_months($_POST[$key]));
  $calendar=rh_sync(array_values(array_unique($months)));
  $result=mg_tx($file,static function(array &$s)use($calendar,$act){rc_refresh($s,$calendar);if($act==='followup_preview')return ['preview'=>cf_preview($s,$_POST,$calendar)];return ['followup'=>cf_upsert($s,$_POST,$calendar,$act==='followup_create')];},true);$send($result);exit;
 }
 if(in_array($act,['report_preview','report_create','report_save'],true)){
  $id=(string)($_POST['visit_id']??'');$s=mg_read($file);$visit=$s['visits'][$id]??null;if(!$visit)throw new RuntimeException('방문 일정을 찾지 못했습니다.');
  $months=array_merge(rh_months(rc_basis($visit)),cf_months($s));
  if(rh_valid_date((string)($_POST['received_date']??'')))$months=array_merge($months,rh_months($_POST['received_date']));
  $calendar=rh_sync(array_values(array_unique($months)));
  $result=mg_tx($file,static function(array &$s)use($calendar,$act,$id){
   rc_refresh($s,$calendar);$v=$s['visits'][$id]??null;if(!$v)throw new RuntimeException('방문 일정을 찾지 못했습니다.');
   if($act==='report_preview')return ['proposal'=>['visit_id'=>$id,'name'=>$v['name'],'date'=>$v['date'],'basis'=>rc_basis($v),'basis_type'=>empty($v['inspection_end'])?'planned':'actual']+rh_deadline(rc_basis($v),$calendar)];
   $before=$s['reports'][$id]??[];
   if($act==='report_create')$r=rc_create($s,$_POST,$calendar);else $r=rc_save($s,$_POST);
   rc_refresh($s,$calendar);$saved=$s['reports'][$id];$follow=$s['followups'][$id]??null;
   $receiptChanged=($before['status']??'')!=='completed'||($before['received_date']??'')!==$saved['received_date'];
   $offer=$saved['status']==='completed'&&(!$follow||($follow['status']==='planned'&&$receiptChanged));
   return ['report'=>$saved,'followup'=>$follow,'followup_offer'=>$offer?$id:null,'followup_updated'=>(bool)($follow&&$receiptChanged&&$follow['status']==='planned')];
  },true);$send($result);exit;
 }
 if($act==='undo_batch'){
  $calendar=rh_snapshot();$result=mg_tx($file,static function(array &$s)use($calendar){$r=mvc_undo_batch($s,(string)($_POST['batch_token']??''));rc_refresh($s,$calendar);return $r;},true);$send($result);exit;
 }
 if(!in_array($act,['save','batch','confirm_visits',''],true))throw new RuntimeException('지원하지 않는 일정 작업입니다.');
 $state=mg_read(mg_state_file());$drafts=[];
 foreach(ma_map_rows($actor,$members,$state) as $r)$drafts[$r['uid']]=['name'=>$r['name'],'address'=>$r['address'],'approval_month'=>$r['approval_month']];
 $resolve=function(string $uid)use($drafts,$actor,$members,$state):?array {
  if(isset($drafts[$uid]))return $drafts[$uid];
  if(preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)&&isset($members[$uid])&&mg_can_view($actor,$uid,$members,$state)){
   $info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');$raw=preg_replace('/[.\/-]/','',(string)($info['bd_use_apr']??''));$approval=0;
   if(preg_match('/^\d{8}$/D',$raw)&&checkdate((int)substr($raw,4,2),(int)substr($raw,6,2),(int)substr($raw,0,4)))$approval=(int)substr($raw,4,2);
   return ['name'=>trim((string)($info['name']??''))?:((string)($members[$uid]['nickname']??$uid)),'address'=>(string)($info['address']??''),'approval_month'=>$approval];
  }return null;
 };
 if($act==='confirm_visits'){
  $refs=json_decode((string)($_POST['visits']??''),true);if(!is_array($refs))throw new RuntimeException('방문 일정을 선택해 주세요.');
  $calendar=rh_snapshot();$result=mg_tx($file,static function(array &$s)use($refs,$resolve,$calendar){$r=mvc_confirm_visits($s,$refs,(string)($_POST['status']??''),(string)($_POST['visited_date']??''),$resolve);rc_refresh($s,$calendar);return $r;},true);$send($result);exit;
 }
 $date=(string)($_POST['inspection_end']??'');if($date==='')$date=(string)($_POST['date']??'');
 $calendar=rh_valid_date($date)?rh_sync(rh_months($date)):rh_snapshot();
 if($act==='batch'){
  $uids=json_decode((string)($_POST['uids']??''),true);$targets=[];
  if(!is_array($uids)||count($uids)>50)throw new RuntimeException('거래처를 1~50곳 선택해 주세요.');
  foreach($uids as $uid){if(!is_string($uid))throw new RuntimeException('거래처를 확인해 주세요.');$target=$resolve($uid);if($target)$targets[$uid]=$target;}
  $result=mg_tx($file,static function(array &$s)use($targets,$calendar){$r=mvc_batch($s,$_POST,$targets);rc_refresh($s,$calendar);$r['proposals']=rc_proposals($s,array_map(fn($id)=>$s['visits'][$id]??null,$r['ids']),$calendar);return $r;},true);$send($result);exit;
 }
 $target=$resolve((string)($_POST['uid']??''));
 $result=mg_tx($file,static function(array &$s)use($target,$calendar){$row=mvc_save($s,$_POST,$target);rc_refresh($s,$calendar);return ['visit'=>$row,'proposals'=>rc_proposals($s,[$row],$calendar)];},true);$send($result);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'일정을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}
