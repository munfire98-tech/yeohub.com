<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/manager_visit_store.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 $actor=mg_uid();$members=mg_members();
 if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);throw new RuntimeException('매니저 계정으로 로그인해 주세요.');}
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new RuntimeException('지원하지 않는 요청입니다.');}
 if($method==='POST'&&(!is_string($_POST['csrf']??null)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf']))){http_response_code(403);throw new RuntimeException('요청이 만료되었습니다. 새로고침해 주세요.');}
 session_write_close();
 $file=__DIR__.'/data/manager_visits/'.hash('sha256',$actor.'|'.(string)($members[$actor]['created']??'')).'.php';
 if($method==='GET'){
  $month=(string)($_GET['month']??'');if(!preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/D',$month))throw new RuntimeException('조회할 월을 확인해 주세요.');
  $data=mg_read($file);$rows=array_values(array_filter($data['visits']??[],fn($r)=>substr($r['date'],0,7)===$month||($r['status']==='completed'&&substr($r['visited_date'],0,7)===$month)));
  echo json_encode(['ok'=>true,'visits'=>$rows],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
 }
 if(($_POST['act']??'')==='undo_batch'){
  $result=mg_tx($file,fn(array &$s)=>mvc_undo_batch($s,(string)($_POST['batch_token']??'')),true);
  echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
 }
 $state=mg_read(mg_state_file());$drafts=[];
 foreach(ma_map_rows($actor,$members,$state) as $r)$drafts[$r['uid']]=['name'=>$r['name'],'address'=>$r['address']];
 $resolve=function(string $uid)use($drafts,$actor,$members,$state):?array {
  if(isset($drafts[$uid]))return $drafts[$uid];
  if(preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)&&isset($members[$uid])&&mg_can_view($actor,$uid,$members,$state)){
   $info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');return ['name'=>trim((string)($info['name']??''))?:((string)($members[$uid]['nickname']??$uid)),'address'=>(string)($info['address']??'')];
  }return null;
 };
 if(($_POST['act']??'')==='batch'){
  $uids=json_decode((string)($_POST['uids']??''),true);$targets=[];
  if(!is_array($uids)||count($uids)>50)throw new RuntimeException('거래처를 1~50곳 선택해 주세요.');
  foreach($uids as $uid){if(!is_string($uid))throw new RuntimeException('거래처를 확인해 주세요.');$target=$resolve($uid);if($target)$targets[$uid]=$target;}
  $result=mg_tx($file,fn(array &$s)=>mvc_batch($s,$_POST,$targets),true);
  echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
 }
 $target=$resolve((string)($_POST['uid']??''));
 $row=mg_tx($file,fn(array &$s)=>mvc_save($s,$_POST,$target),true);
 echo json_encode(['ok'=>true,'visit'=>$row],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'일정을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}
