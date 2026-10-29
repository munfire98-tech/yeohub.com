<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

// A short-lived, request-scoped editor. Never changes the dashboard edit target.
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_jawi_help.php';
require_once __DIR__.'/manager_train_help.php';
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function mhr_fail(string $message,int $code=400):void{http_response_code($code);echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
$actor=mg_uid();
if($actor==='')mhr_fail('로그인 후 다시 열어 주세요.',401);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')mhr_fail('허용되지 않은 요청입니다.',405);
if(!is_string($_POST['csrf']??null)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf']))mhr_fail('새로고침 후 다시 열어 주세요.',403);
$action=$_POST['action']??'';
foreach(($_SESSION['_manager_help_review']??[]) as $key=>$c)if(($c['expires']??0)<time())unset($_SESSION['_manager_help_review'][$key]);
if($action==='close'){
    $token=is_string($_POST['token']??null)?$_POST['token']:'';
    unset($_SESSION['_manager_help_review'][$token]);
    echo '{"ok":true}';exit;
}
if($action!=='open')mhr_fail('지원하지 않는 요청입니다.');
$uid=is_string($_POST['uid']??null)?$_POST['uid']:'';
$id=is_string($_POST['id']??null)?$_POST['id']:'';
if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!preg_match('/^[a-f0-9]{32}$/D',$id))mhr_fail('요청 정보를 확인해 주세요.');
try{
    $members=mg_members();$state=mg_read(mg_state_file());
    if(!mg_can_view($actor,$uid,$members,$state))mhr_fail('현재 담당하는 유저의 요청만 답변할 수 있습니다.',403);
    $row=mg_read(__DIR__.'/data/manager_help_requests.php')[$id]??[];
    if(($row['uid']??'')!==$uid||($row['manager']??'')!==$actor
        ||($row['user_created']??null)!==($members[$uid]['created']??null)
        ||($row['manager_created']??null)!==($members[$actor]['created']??null)
        ||($row['link_key']??'')!==mg_link_key($uid,$members[$uid]))mhr_fail('이 요청에 답변할 권한이 없습니다.',403);
    if(($row['status']??'')!=='pending')mhr_fail('이미 처리되었거나 삭제된 요청입니다. 목록을 새로고침해 주세요.',409);
    $field=(string)$row['field'];$query=[];
    if($field==='__facilities'){$page='building_facilities.php';$query=['embed'=>'1'];}
    elseif(strpos($field,'__jw_')===0){
        $q=mjh_parse($field);if(!$q||!mjh_valid($uid,$field))mhr_fail('교육·훈련 기록이 삭제되었거나 변경되었습니다.',409);
        $page='jawi_chat.php';$query=['id'=>$q['id'],'help_step'=>$q['step']];
    }elseif(strpos($field,'__tr_')===0){
        $q=mth_parse($field);if(!$q||!mth_valid($uid,$field))mhr_fail('훈련·교육 기록이 삭제되었거나 변경되었습니다.',409);
        $page='train_chat.php';$query=['id'=>$q['id'],'help_step'=>$q['step']];
    }elseif(preg_match('/^__fp_([0-9]+)_([0-9]+)_([A-Za-z0-9_]+)$/D',$field,$q)){
        require_once __DIR__.'/fire_plan_db.php';
        if(!is_file(__DIR__.'/data/fireplan/'.$uid.'/'.$q[1].'.json')||!isset(fp_chat_schema()[$q[2]][$q[3]]))mhr_fail('소방계획서가 삭제되었거나 변경되었습니다.',409);
        $page='fire_plan_chat.php';$query=['id'=>$q[1],'help_code'=>$q[2],'help_key'=>$q[3]];
    }else{
        if(!in_array($field,['__search','name','address','__assembly','fire_engine_route','fire_engine_route_note','use','__floors','__areas','grade','__mgr','rep','tel'],true))mhr_fail('이 요청은 현재 질문 화면에서 열 수 없습니다.');
        $page='building_setup_chat.php';$query=['help_field'=>$field];
    }
    if(count($_SESSION['_manager_help_review']??[])>=16)mhr_fail('열려 있는 요청 창을 닫고 다시 시도해 주세요.',409);
    $token=bin2hex(random_bytes(24));
    $_SESSION['_manager_help_review'][$token]=['uid'=>$uid,'actor_created'=>$members[$actor]['created'],
        'target_created'=>$members[$uid]['created'],'link_key'=>mg_link_key($uid,$members[$uid]),
        'generation'=>$token,'request_id'=>$id,'field'=>$field,'page'=>$page,'query'=>$query,'expires'=>time()+1800];
    $query+=['help_review'=>$token,'help_request'=>$id];
    echo json_encode(['ok'=>true,'token'=>$token,'url'=>'/'.$page.'?'.http_build_query($query)],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_THROW_ON_ERROR);
}catch(Throwable $e){error_log('Manager help review: '.$e->getMessage());mhr_fail('요청을 열지 못했습니다. 잠시 후 다시 시도해 주세요.',503);}
