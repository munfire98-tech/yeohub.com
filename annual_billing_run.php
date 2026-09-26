<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');
require_once __DIR__.'/annual_billing_engine.php';
header('Cache-Control: no-store');header('Content-Type: application/json; charset=utf-8');
try{
 if(PHP_SAPI==='cli'){$execute=in_array('--run',$argv,true);}
 else{
  if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit('{"ok":false}');}
  if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('{"ok":false}');}
  $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
  $token=strncmp($header,'Bearer ',7)===0?substr($header,7):'';$settings=ab_scheduler_settings();
  if($token===''||empty($settings['token_hash'])||!hash_equals($settings['token_hash'],hash('sha256',$token))){http_response_code(403);exit('{"ok":false}');}
  $execute=($_POST['mode']??'dry-run')==='run';
 }
 $r=ab_batch($execute);if(PHP_SAPI!=='cli')unset($r['results']);echo json_encode(['ok'=>true,'result'=>$r],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'설정 또는 처리 확인이 필요합니다. 관리자 결제 화면을 확인하세요.'],JSON_UNESCAPED_UNICODE);if(PHP_SAPI==='cli')exit(1);}
