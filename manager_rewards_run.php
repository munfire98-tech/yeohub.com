<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');
header('Cache-Control: no-store');header('Content-Type: application/json; charset=utf-8');
// Reuses the annual billing admin's scheduler token, but NEVER charges a card.
if(PHP_SAPI!=='cli'){
 if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit('{"ok":false}');}
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('{"ok":false}');}
 if(!is_file(__DIR__.'/annual_billing_engine.php')){http_response_code(503);exit('{"ok":false,"error":"scheduler_not_installed"}');}
 require_once __DIR__.'/annual_billing_engine.php';
 $settings=ab_scheduler_settings();$header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
 $token=strncmp($header,'Bearer ',7)===0?substr($header,7):'';
 if($token===''||empty($settings['token_hash'])||!hash_equals($settings['token_hash'],hash('sha256',$token))){http_response_code(403);exit('{"ok":false}');}
}
require_once __DIR__.'/manager_common.php';
try{
 $count=0;$failed=0;$started=microtime(true);
 $uids=[];foreach(mg_members() as $uid=>$member)if(is_array($member)&&mg_active($member,'building'))$uids[]=(string)$uid;
 sort($uids,SORT_STRING);
 // Persist cursor across runs so a hosting time limit cannot starve later accounts.
 mg_tx(__DIR__.'/data/manager_rewards_cursor.php',function(array &$cursor)use($uids,&$count,&$failed,$started){
  $after=(string)($cursor['last']??'');$ordered=array_merge(array_values(array_filter($uids,fn($u)=>strcmp($u,$after)>0)),array_values(array_filter($uids,fn($u)=>strcmp($u,$after)<=0)));
  foreach($ordered as $uid){if(microtime(true)-$started>5||$count+$failed>=100)break;
   try{mr_reconcile($uid);$count++;}catch(Throwable $e){$failed++;}
   $cursor['last']=$uid;
  }
  $cursor['updated_at']=date('c');
 },true);
 echo json_encode(['ok'=>$failed===0,'processed'=>$count,'failed'=>$failed],JSON_UNESCAPED_UNICODE).PHP_EOL;
 if(PHP_SAPI==='cli')exit($failed?1:0);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false}';if(PHP_SAPI==='cli')exit(1);}
