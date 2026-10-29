<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
date_default_timezone_set('Asia/Seoul');
require_once __DIR__.'/fire_plan_share_common.php';
fps_headers();header('Content-Type: application/json; charset=utf-8');
function fps_reply(array $body,int $code=200):never {http_response_code($code);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
$admin=!empty($_SESSION['is_admin'])||(!empty($_SESSION['ID_OK'])&&$_SESSION['ID_OK']==1);
if(!$admin&&empty($_SESSION['is_user']))fps_reply(['ok'=>false,'error'=>'다시 로그인해 주세요.'],401);
if(!empty($_SESSION['_mge_help_review'])||(!$admin&&($_SESSION['role']??'')!=='building'))fps_reply(['ok'=>false,'error'=>'건물관리 화면에서 열어 주세요.'],403);
$uid=fp_user_key();$actor=(string)($_SESSION['_mge_actor']??'');
if(!fps_uid($uid))fps_reply(['ok'=>false,'error'=>'공유할 건물을 확인해 주세요.'],403);
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['csrf'];$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))fps_reply(['ok'=>false,'error'=>'지원하지 않는 요청입니다.'],405);
if($method==='POST'&&(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf'])))fps_reply(['ok'=>false,'error'=>'창을 닫고 다시 열어 주세요.'],403);
session_write_close();
try{
    // Follow the existing member -> manager state -> feature ledger lock order.
    $body=mg_member_tx(static function(array &$members)use($uid,$actor,$admin,$method,$csrf):array {
        $member=$members[$uid]??[];$created=(string)($member['created']??'');
        if(!mg_active($member,'building')||$created==='')throw new RuntimeException('사용 가능한 건물 계정이 아닙니다.');
        return mg_state_tx(static function(array &$state)use($members,$member,$created,$uid,$actor,$admin,$method,$csrf):array {
            if($actor!==''&&!mg_can_view($actor,$uid,$members,$state))throw new RuntimeException('연결 업체 연결을 확인해 주세요.');
            $canCreate=$admin||(mg_connection_manager($member,$members)!==''&&mg_link_status($uid,$member,$state)==='accepted'&&pc_active($uid));
            $plans=fps_plans($uid);
            return mg_tx(fps_store(),static function(array &$shares)use($uid,$created,$plans,$method,$csrf,$canCreate):array {
                if($method==='POST'){
                    $year=filter_var($_POST['year']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
                    $action=$_POST['action']??'';
                    if($year===false||!in_array($action,['create','revoke'],true))throw new RuntimeException('연도와 요청을 확인해 주세요.');
                    $key=fps_key($uid,$created,$year);$row=$shares[$key]??[];
                    if($action==='create'){
                        if(!$canCreate)throw new RuntimeException('파트너 연결과 PRO 이용 상태를 확인해 주세요. 기존 공유는 중단할 수 있습니다.');
                        if(!isset($plans[$year]))throw new RuntimeException('이 연도의 계획서를 먼저 작성·저장해 주세요.');
                        if(!fps_active($row,$uid,$created,$year))$shares[$key]=['uid'=>$uid,'account_created'=>$created,'year'=>$year,'token'=>bin2hex(random_bytes(32)),'created_at'=>date('c'),'revoked_at'=>null];
                    }elseif(fps_active($row,$uid,$created,$year)){$shares[$key]['revoked_at']=date('c');}
                }
                $years=[];$current=(int)date('Y');$all=array_unique(array_merge([$current-1,$current,$current+1],array_keys($plans)));
                // Keep deleted-plan links visible so owners can still revoke them.
                foreach($shares as $r)if(is_array($r)&&($r['uid']??'')===$uid&&($r['account_created']??'')===$created)$all[]=(int)$r['year'];
                $all=array_unique($all);sort($all,SORT_NUMERIC);
                foreach($all as $year){
                    $p=$plans[$year]??null;$row=$shares[fps_key($uid,$created,$year)]??[];$active=fps_active($row,$uid,$created,$year);
                    $status=$p?fpo_status($p):null;$url=$active?fps_url($row['token']):'';
                    $years[]=['year'=>$year,'available'=>(bool)$p,'name'=>(string)($p['building_name']??''),'updated_at'=>(string)($p['updated_at']??''),'reviewed'=>(bool)($status['reviewed']??false),'percent'=>(int)($status['percent']??0),'active'=>$active,'url'=>$url,'qr'=>$active?fps_qr($url):''];
                }
                return ['ok'=>true,'csrf'=>$csrf,'can_create'=>$canCreate,'current_year'=>$current,'years'=>$years];
            },true);
        });
    });
    fps_reply($body);
}catch(RuntimeException $e){fps_reply(['ok'=>false,'error'=>$e->getMessage()],400);}
catch(Throwable $e){error_log('Fire plan sharing failed: '.get_class($e));fps_reply(['ok'=>false,'error'=>'공유 정보를 확인하지 못했습니다. 잠시 후 다시 시도해 주세요.'],503);}
