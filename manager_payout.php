<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_payout_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 $uid=mg_uid();$me=mg_members()[$uid]??[];if($uid===''||!mg_active($me,'agency')){http_response_code(403);echo '{"ok":false}';exit;}
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='POST'){
  if(empty($_SESSION['csrf'])||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);echo '{"ok":false}';exit;}
  mp_apply($uid,(string)($_POST['action']??''),$_POST);
 }elseif($method!=='GET'){http_response_code(405);header('Allow: GET, POST');echo '{"ok":false}';exit;}
 echo json_encode(mp_snapshot($uid,$me),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(RuntimeException $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){http_response_code(503);echo '{"ok":false,"error":"잠시 후 다시 시도해 주세요."}';}
