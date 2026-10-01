<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
require_once __DIR__.'/false_alarm_common.php';
date_default_timezone_set('Asia/Seoul');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 $method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');throw new InvalidArgumentException('지원하지 않는 요청입니다.');}
 $actor=mg_uid();if($actor===''){http_response_code(401);throw new InvalidArgumentException('본인 계정으로 로그인해 주세요.');}
 $target=is_string($_GET['uid']??null)?$_GET['uid']:$actor;
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$target)){http_response_code(400);throw new InvalidArgumentException('건물 정보를 확인해 주세요.');}
 if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
 $input=[];
 if($method==='POST'){
  if((int)($_SERVER['CONTENT_LENGTH']??0)>20000){http_response_code(413);throw new InvalidArgumentException('입력 내용이 너무 깁니다.');}
  $raw=file_get_contents('php://input',false,null,0,20001);
  if(strlen($raw)>20000)throw new InvalidArgumentException('입력 내용이 너무 깁니다.');
  $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
  if(!is_array($input)||!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf'])){http_response_code(403);throw new InvalidArgumentException('화면을 다시 열고 저장해 주세요.');}
 }
 // Same lock ordering as manager connection changes; recheck permissions before reading/writing.
 $result=mg_member_tx(function(array &$members)use($actor,$target,$method,$input){
  return mg_state_tx(function(array &$state)use($members,$actor,$target,$method,$input){
   if(!fa_authorize($actor,$target,$members,$state)){http_response_code(403);throw new InvalidArgumentException('수락된 담당 건물만 기록할 수 있습니다.');}
   if($actor===$target&&!pc_active($target)){http_response_code(403);throw new InvalidArgumentException('PRO 구독 후 이용할 수 있습니다.');}
   $key=hash('sha256',$target.'|'.(string)($members[$target]['created']??''));
   $path=__DIR__.'/data/false_alarm/'.$key.'.json';
   if($method==='POST')mg_tx($path,function(array &$rows)use($input,$actor,$members){fa_record($rows,$input,$actor,(string)($members[$actor]['nickname']??$actor));});
   $rows=array_values(mg_read($path));usort($rows,static fn($a,$b)=>strcmp($b['occurred_at'],$a['occurred_at'])?:strcmp($b['created_at'],$a['created_at']));
   return ['name'=>(string)($members[$target]['nickname']??$target),'rows'=>$rows,'count'=>count($rows)];
  });
 });
 echo json_encode(['ok'=>true,'csrf'=>$csrf]+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 if($e instanceof DomainException)http_response_code(409);
 elseif(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException||$e instanceof JsonException?400:503);
 $safe=$e instanceof InvalidArgumentException||$e instanceof DomainException?$e->getMessage():'기록을 불러오거나 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.';
 echo json_encode(['ok'=>false,'error'=>$safe],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
