<?php
declare(strict_types=1);
if(defined('MG_EDIT_GUARD_READY'))return;
define('MG_EDIT_GUARD_READY',true);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_edit_bootstrap.php';
$wasActive=session_status()===PHP_SESSION_ACTIVE;
if(!$wasActive)session_start();
$context=$_SESSION['_manager_edit']??null;
// Without an explicit manager editing context, ordinary user sessions are unchanged.
if(!is_array($context)){
    $helpUid=mg_uid();
    if($helpUid!==''&&($_SESSION['role']??'')==='building'&&basename((string)($_SERVER['SCRIPT_FILENAME']??''))==='building_manager.php'){
        ob_start(static function(string $html)use($helpUid):string{
            if(strpos($html,'data-dashboard="1"')!==false)return $html;
            $tag='<script src="/manager_help.js?v=6" data-dashboard="1" data-uid="'.htmlspecialchars($helpUid,ENT_QUOTES,'UTF-8').'"></script>';
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
if(!$valid){unset($_SESSION['_manager_edit']);session_write_close();http_response_code(403);exit('담당 유저 연결이 해제되었거나 권한을 확인하지 못해 편집을 중단했습니다. 매니저 화면에서 다시 열어 주세요.');}
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
$editStore=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).'/mge_sessions_'.hash('sha256',__DIR__);
if(is_link($editStore)||(!is_dir($editStore)&&!mkdir($editStore,0700,true)&&!is_dir($editStore))){http_response_code(503);exit('편집 세션 저장소를 만들지 못했습니다.');}
if(!is_writable($editStore)){http_response_code(503);exit('편집 세션 저장소 쓰기 권한을 확인해 주세요.');}
session_module_name('files');session_save_path($editStore);
session_name('MGEEDITV2');
$editCookie=$_COOKIE['MGEEDITV2']??'';
session_id(is_string($editCookie)&&preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$editCookie)?$editCookie:'');
ini_set('session.use_strict_mode','1');session_start();
if(($_SESSION['_mge_generation']??'')!==$generation||($_SESSION['_mge_actor']??'')!==$actor){
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){session_write_close();http_response_code(409);exit('편집 대상이 변경되었습니다. 건물관리 화면을 다시 열어 주세요.');}
    session_regenerate_id(true);
    $m=$members[$target];
    $_SESSION=['_mge_generation'=>$generation,'_mge_actor'=>$actor,'_imp'=>['kind'=>'manager_edit','uid'=>$target,'nick'=>$m['nickname']??$target],
        'is_user'=>true,'member_id'=>$target,'role'=>'building','nickname'=>$m['nickname']??$target,'csrf'=>bin2hex(random_bytes(24))];
    if(!empty($m['kakao_id']))$_SESSION['kakao_id']=(string)$m['kakao_id'];
}
if(($_SESSION['member_id']??'')!==$target){session_write_close();http_response_code(403);exit('편집 대상이 일치하지 않습니다. 매니저 화면에서 다시 열어 주세요.');}
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    try{mg_tx(__DIR__.'/data/manager_edit_audit.php',function(&$rows)use($actor,$target,$name){$rows[]=['at'=>date('c'),'actor'=>$actor,'target'=>$target,'page'=>$name,'event'=>'write_request'];if(count($rows)>3000)$rows=array_slice($rows,-3000);},true);}
    catch(Throwable $e){session_write_close();http_response_code(503);exit('편집 기록을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.');}
}
$editName=(string)$_SESSION['nickname'];session_write_close();header('Cache-Control: no-store');
// Keep the return bar on the main dashboard only, never inside form popups.
if($name!=='building_manager.php'||!empty($_GET['modal'])||!empty($_GET['embed']))return;
ob_start(static function(string $html)use($editName,$actorCsrf,$target):string{
    $pos=strripos($html,'</body>');if($pos===false)return $html;
    $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $bar='<style>body{padding-top:72px!important}.mge-bar{position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#5b21b6;color:white;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;font:13px/1.5 system-ui}.mge-bar form{margin:0}.mge-bar button{border:0;border-radius:7px;background:white;color:#5b21b6;padding:8px 12px;cursor:pointer;font:600 12px system-ui}.mge-bar small{display:block;opacity:.85}@media print{.mge-bar{display:none}body{padding-top:0!important}}</style>'
    .'<div class="mge-bar"><div><strong>'.$e($editName).' · 건물정보 수정 중</strong><small>저장하면 이 유저에게 반영됩니다.</small></div><form action="/manager_view.php" method="post"><input type="hidden" name="action" value="stop"><input type="hidden" name="csrf" value="'.$e($actorCsrf).'"><button>매니저 화면으로 돌아가기</button></form></div>';
    $panel='<script src="/manager_help.js?v=6" data-manager="1" data-dashboard="1" data-uid="'.$e($target).'"></script>';
    $html=preg_replace('/(<body\b[^>]*>)/i','$1'.$panel,$html,1);
    $pos=strripos($html,'</body>');
    return substr($html,0,$pos).$bar.substr($html,$pos);
});
