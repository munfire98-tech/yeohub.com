<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/partner_company_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')throw new RuntimeException('같은 사이트에서 요청해 주세요.',403);
 $uid=mg_uid();$members=mg_members();
 if($uid===''||!mg_active($members[$uid]??[],'agency')||!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK'])||!empty($_SESSION['_manager_edit'])||!empty($_SESSION['demo'])||session_name()==='PARTNERDEMO')throw new RuntimeException('본인 파트너 계정으로 로그인해 주세요.',403);
 $_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];$method=$_SERVER['REQUEST_METHOD']??'GET';
 if(!in_array($method,['GET','POST'],true))throw new RuntimeException('지원하지 않는 요청입니다.',405);
 if($method==='POST'){
  $input=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($input)||!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf']))throw new RuntimeException('새로고침 후 다시 저장해 주세요.',403);
  $action=$input['action']??'';if(!in_array($action,['save','defer'],true))throw new RuntimeException('지원하지 않는 요청입니다.',400);
  $value=$action==='save'?pci_validate($input):null;$created=(string)$members[$uid]['created'];
  mg_member_tx(static function(array &$current)use($uid,$created,$action,$value){
   if(!mg_active($current[$uid]??[],'agency')||($current[$uid]['created']??'')!==$created)throw new RuntimeException('계정이 변경되었습니다. 다시 로그인해 주세요.',403);
   mg_tx(pci_file($uid,$current),static function(array &$s)use($action,$value){if($action==='save')$s=$value;elseif(empty($s['saved_at']))$s['deferred_at']=date('c');},true);
  });
 }
 $profile=pci_read($uid,$members);
 echo json_encode(['ok'=>true,'csrf'=>$csrf,'scope'=>hash('sha256',$uid.'|'.$members[$uid]['created']),'profile'=>$profile,'needs_prompt'=>empty($profile['saved_at'])&&empty($profile['deferred_at'])],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$code=$e->getCode();http_response_code(in_array($code,[400,403,405],true)?$code:500);echo json_encode(['ok'=>false,'error'=>$code?$e->getMessage():'업체정보를 저장하지 못했습니다. 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}
