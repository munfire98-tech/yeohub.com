<?php
declare(strict_types=1);
// Read only; uses normal login identity even while a manager has an editing cookie.
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function be_reply(int $status,array $data):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')be_reply(405,['error'=>'조회만 가능한 요청입니다.']);
$actor=mg_uid();$admin=!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);
if($actor===''&&!$admin)be_reply(401,['error'=>'로그인 후 다시 열어 주세요.']);
$uid=$_GET['uid']??'';
if(!is_string($uid)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))be_reply(400,['error'=>'건물 정보를 확인해 주세요.']);
try{
 $members=mg_members();$member=$members[$uid]??[];
 if(!mg_active($member,'building')||(!$admin&&$actor!==$uid&&!mg_can_view($actor,$uid,$members,mg_read(mg_state_file()))))be_reply(403,['error'=>'담당 건물만 열람할 수 있습니다.']);
 session_write_close();
 // The key is bound once, after authorization. Avoid loading page/migration side effects.
 define('BE_FACILITY_UID',$uid);
 require_once __DIR__.'/building_facilities_common.php';
 be_reply(200,bf_hydrant_education(bf_load()));
}catch(Throwable $e){be_reply(503,['error'=>'소방시설 현황을 확인하지 못했습니다. 잠시 후 다시 시도해 주세요.']);}
function bi_user_key():string{return BE_FACILITY_UID;}
