<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/manager_buildings_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');echo '{"ok":false}';exit;}
try{
 $actor=mg_uid();$members=mg_members();
 if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);echo '{"ok":false}';exit;}
 mr_sync_manager($actor);
 echo json_encode(mb_manager_rows($actor,$members,mg_read(mg_state_file())),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false,"error":"연결된 건물 정보를 불러오지 못했습니다."}';}
