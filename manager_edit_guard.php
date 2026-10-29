<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

if(defined('MG_EDIT_GUARD_READY'))return;
define('MG_EDIT_GUARD_READY',true);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_edit_bootstrap.php';
require_once __DIR__.'/building_service_access.php';
$wasActive=session_status()===PHP_SESSION_ACTIVE;
if(!$wasActive)alp_session_start();
$reviewToken=is_string($_GET['help_review']??null)?$_GET['help_review']:'';
$isHelpReview=array_key_exists('help_review',$_GET);
$context=$isHelpReview?($_SESSION['_manager_help_review'][$reviewToken]??null):($_SESSION['_manager_edit']??null);
if($isHelpReview){
    $reviewPage=basename((string)($_SERVER['SCRIPT_FILENAME']??''));
    $photoRequest=is_array($context)&&($context['page']??'')==='train_chat.php'&&$reviewPage==='train_photo.php'&&in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true);
    $reviewValid=preg_match('/^[a-f0-9]{48}$/D',$reviewToken)&&is_array($context)&&($context['expires']??0)>=time()
        &&(($context['page']??'')===$reviewPage||$photoRequest)
        &&($_GET['help_request']??'')===($context['request_id']??'')&&!isset($_GET['new'])&&!isset($_GET['reset']);
    if($reviewValid&&!$photoRequest)foreach($context['query'] as $key=>$value)if(($_GET[$key]??null)!==$value)$reviewValid=false;
    if($photoRequest){
        $record=mg_read(__DIR__.'/data/train/'.($context['uid']??'').'/'.($context['query']['id']??'').'.json');
        $reviewValid=$reviewValid&&is_string($_GET['f']??null)&&in_array($_GET['f'],(array)($record['data']['photos']??[]),true);
    }
    if(!$reviewValid){session_write_close();http_response_code(403);exit('작성 요청 창이 만료되었거나 요청이 변경되었습니다. 팝업에서 해당 질문을 다시 열어 주세요.');}
    if(($_POST['act']??'')==='reset'||($_POST['action']??'')==='reset'){session_write_close();http_response_code(403);exit('요청 창에서는 전체 기록을 초기화할 수 없습니다.');}
}
// Without an explicit manager editing context, ordinary user sessions are unchanged.
if(!is_array($context)){
    bsa_enforce();
    $helpUid=mg_uid();
    if($helpUid!==''&&($_SESSION['role']??'')==='building'&&basename((string)($_SERVER['SCRIPT_FILENAME']??''))==='FireDesk.php'){
        ob_start(static function(string $html)use($helpUid):string{
            if(strpos($html,'data-dashboard="1"')!==false)return $html;
            $tag='<script src="/manager_help.js?v=20261015-partner-1-inquiry-1" data-dashboard="1" data-uid="'.htmlspecialchars($helpUid,ENT_QUOTES,'UTF-8').'"></script>';
            $pos=strripos($html,'</body>');return $pos===false?$html:substr($html,0,$pos).$tag.substr($html,$pos);
        });
    }
    if(!$wasActive)session_write_close();return;
}
$actor=mg_uid();$target=(string)($context['uid']??'');
try{
    $members=mg_members();
    $valid=preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$target)
        && ($context['actor_created']??null)===($members[$actor]['created']??null)
        && ($context['target_created']??null)===($members[$target]['created']??null)
        && ($context['link_key']??'')===mg_link_key($target,$members[$target]??[])
        && mg_can_view($actor,$target,$members,mg_read(mg_state_file()));
}catch(Throwable $e){$valid=false;}
if(!$valid){if($isHelpReview)unset($_SESSION['_manager_help_review'][$reviewToken]);else unset($_SESSION['_manager_edit']);session_write_close();http_response_code(403);exit('담당 유저 연결이 해제되었거나 권한을 확인하지 못해 편집을 중단했습니다. 파트너 화면에서 다시 열어 주세요.');}
$script=realpath((string)($_SERVER['SCRIPT_FILENAME']??''));$name=$script?basename($script):'';
if(!$script||dirname($script)!==realpath(__DIR__)||!in_array($name,mge_routes(),true)){
    if(!$wasActive)session_write_close();return;
}
$actorCsrf=(string)($_SESSION['csrf']??'');$generation=(string)$context['generation'];
session_write_close();
// The manager's normal session NEVER becomes the user's session. Unpatched routes
// therefore retain manager identity, including account, payment and client pages.
// Separate cookie names alone do not isolate PHP session files. Keep editing
// sessions in a private, separate storage path so their IDs cannot be replayed
// as the normal login cookie on routes without an editing hook.
// Use the configured private session directory, not the OS temporary directory.
$sessionBase=(string)session_save_path();
if(strpos($sessionBase,';')!==false)$sessionBase=substr($sessionBase,strrpos($sessionBase,';')+1);
$sessionBase=realpath($sessionBase!==''?$sessionBase:sys_get_temp_dir());
if($sessionBase===false||!is_dir($sessionBase)||!is_writable($sessionBase)){http_response_code(503);exit('로그인 세션 저장 경로를 확인해 주세요.');}
$editStore=$sessionBase.($isHelpReview?'/mge_help_sessions_':'/mge_sessions_').hash('sha256',__DIR__);
if(is_link($editStore)||(!is_dir($editStore)&&!@mkdir($editStore,0700)&&!is_dir($editStore))){http_response_code(503);exit('편집 세션 저장소를 만들지 못했습니다.');}
if(!is_writable($editStore)){http_response_code(503);exit('편집 세션 저장소 쓰기 권한을 확인해 주세요.');}
session_module_name('files');
if(session_save_path($editStore)===false||session_save_path()!==$editStore){http_response_code(503);exit('편집 세션 경로를 변경할 수 없습니다. PHP 서버 설정을 확인해 주세요.');}
session_name($isHelpReview?'MGEHELPV1':'MGEEDITV2');
$editCookie=$_COOKIE[session_name()]??'';
session_id(is_string($editCookie)&&preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$editCookie)?$editCookie:'');
ini_set('session.use_strict_mode','1');
if(!alp_session_start()){http_response_code(503);exit('편집 세션을 시작하지 못했습니다. 파트너 화면에서 다시 열어 주세요.');}
if(($_SESSION['_mge_generation']??'')!==$generation||($_SESSION['_mge_actor']??'')!==$actor){
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){session_write_close();http_response_code(409);exit('편집 대상이 변경되었습니다. 건물관리 화면을 다시 열어 주세요.');}
    session_regenerate_id(true);
    $m=$members[$target];
    $_SESSION=['_mge_generation'=>$generation,'_mge_actor'=>$actor,'_imp'=>['kind'=>'manager_edit','uid'=>$target,'nick'=>$m['nickname']??$target],
        'is_user'=>true,'member_id'=>$target,'role'=>'building','nickname'=>$m['nickname']??$target,'csrf'=>bin2hex(random_bytes(24))];
    if($isHelpReview)$_SESSION['_mge_help_review']=['id'=>$context['request_id'],'token'=>$reviewToken];
    if(!empty($m['kakao_id']))$_SESSION['kakao_id']=(string)$m['kakao_id'];
}
if(($_SESSION['member_id']??'')!==$target){session_write_close();http_response_code(403);exit('편집 대상이 일치하지 않습니다. 파트너 화면에서 다시 열어 주세요.');}
bsa_enforce();
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    try{mg_tx(__DIR__.'/data/manager_edit_audit.php',function(&$rows)use($actor,$target,$name){$rows[]=['at'=>date('c'),'actor'=>$actor,'target'=>$target,'page'=>$name,'event'=>'write_request'];if(count($rows)>3000)$rows=array_slice($rows,-3000);},true);}
    catch(Throwable $e){session_write_close();http_response_code(503);exit('편집 기록을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.');}
}
$editName=(string)$_SESSION['nickname'];session_write_close();header('Cache-Control: no-store');
// Render only the requested question inside the manager's request dialog.
if($isHelpReview){
    header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: same-origin');
    if($name==='train_photo.php')return;
    ob_start(static function(string $html):string{
        if(stripos($html,'<head')===false)return $html;
        $tags='<link rel="stylesheet" href="/manager_help_question.css?v=20261007-1"><script src="/manager_help_question.js?v=20261007-1"></script>';
        return preg_replace('/(<head\b[^>]*>)/i','$1'.$tags,$html,1);
    });
    return;
}
// Keep the return bar on the main dashboard only, never inside form popups.
if($name!=='FireDesk.php'||!empty($_GET['modal'])||!empty($_GET['embed']))return;
ob_start(static function(string $html)use($editName,$actorCsrf,$target,$actor):string{
    $pos=strripos($html,'</body>');if($pos===false)return $html;
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $bar='<style>body{padding-top:72px!important}.mge-bar{position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#5b21b6;color:white;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;font:13px/1.5 system-ui}.mge-bar form{margin:0}.mge-bar button{border:0;border-radius:7px;background:white;color:#5b21b6;padding:8px 12px;cursor:pointer;font:600 12px system-ui}.mge-bar small{display:block;opacity:.85}@media print{.mge-bar{display:none}body{padding-top:0!important}}</style>'
    .'<div class="mge-bar"><div><strong>'.$e($editName).' · 건물정보 수정 중</strong><small>저장하면 이 유저에게 반영됩니다.</small></div><form action="/manager_view.php" method="post"><input type="hidden" name="action" value="stop"><input type="hidden" name="csrf" value="'.$e($actorCsrf).'"><button>파트너 화면으로 돌아가기</button></form></div>';
    $memo='<details open style="position:fixed;right:18px;bottom:18px;z-index:10000;width:min(330px,calc(100vw - 36px));border:1px solid #efd68b;border-radius:12px;background:#fffbeb;box-shadow:0 10px 30px #182d4922" class="mge-private-memo"><summary style="cursor:pointer;padding:10px 14px;color:#805917;font:600 13px system-ui">연결 업체 전용 메모</summary><iframe title="연결 업체 전용 메모" src="/manager_private_memo.php?uid='.rawurlencode($target).'" style="width:100%;height:255px;border:0"></iframe></details><style>@media print{.mge-private-memo{display:none!important}}</style>';
    $memoStateKey=json_encode('mge-memo-open-v1:'.hash('sha256',$actor."\0".$target));
    $memo.='<script>(function(){var panel=document.querySelector(".mge-private-memo");if(!panel)return;var key='.$memoStateKey.';try{var saved=localStorage.getItem(key);if(saved!==null)panel.open=saved==="1";}catch(e){}panel.addEventListener("toggle",function(){try{localStorage.setItem(key,panel.open?"1":"0");}catch(e){}});})();</script>';
    $bar.=$memo;
    $panel='<script src="/manager_help.js?v=20261015-partner-1-inquiry-1" data-manager="1" data-dashboard="1" data-uid="'.$e($target).'"></script>';
    $html=preg_replace('/(<body\b[^>]*>)/i','$1'.$panel,$html,1);
    $pos=strripos($html,'</body>');
    return substr($html,0,$pos).$bar.substr($html,$pos);
});
