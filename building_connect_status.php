<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$uid=mg_uid();$editing=!empty($_SESSION['_mge_actor']);session_write_close();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);exit;}
try{
 $members=mg_members();$me=$members[$uid]??[];
 if($uid===''||$editing||!mg_active($me,'building')){http_response_code(403);echo '{"ok":false}';exit;}
 $accepted=mg_connection_manager($me,$members)!==''&&mg_link_status($uid,$me,mg_read(mg_state_file()))==='accepted';
 echo json_encode(['ok'=>true,'accepted'=>$accepted]);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false}';}
