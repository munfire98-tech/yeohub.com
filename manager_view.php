<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_edit_bootstrap.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');
// Recover an old-version editing session once, if one is still open.
if(($_SESSION['_imp']['kind']??'')==='manager_edit'&&isset($_SESSION['_imp']['admin'])){$_SESSION=$_SESSION['_imp']['admin'];session_regenerate_id(true);}
function mge_page(string $message): void {http_response_code(403);exit(htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit;}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
if($method==='POST'&&(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])))mge_page('요청이 만료되었습니다. 매니저 화면에서 다시 열어 주세요.');
if($method==='POST'&&($_POST['action']??'')==='stop'){
    unset($_SESSION['_manager_edit']);header('Location: /clients_mini.php#manager-sidebar',true,303);exit;
}
$target=is_string($_POST['uid']??$_GET['uid']??null)?($_POST['uid']??$_GET['uid']):'';$actor=mg_uid();
if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$target))mge_page('유저 정보를 확인해 주세요.');
try{$members=mg_members();if(!mg_can_view($actor,$target,$members,mg_read(mg_state_file())))mge_page('수락된 담당 유저만 수정할 수 있습니다.');}
catch(Throwable $e){http_response_code(503);exit('연결 정보를 확인하지 못했습니다.');}
$error='';
if($method==='POST'){
    if(($_POST['action']??'')!=='edit')mge_page('잘못된 요청입니다.');
    try{
        mge_prepare();
        // Recheck after installing application hooks, before creating edit context.
        $members=mg_members();if(!mg_can_view($actor,$target,$members,mg_read(mg_state_file())))throw new RuntimeException('담당 유저 연결이 변경되었습니다.');
        $_SESSION['_manager_edit']=['uid'=>$target,'actor_created'=>$members[$actor]['created']??'',
            'target_created'=>$members[$target]['created']??'','link_key'=>mg_link_key($target,$members[$target]),'generation'=>bin2hex(random_bytes(24))];
        header('Location: /building_manager.php',true,303);exit;
    }catch(Throwable $e){$error='편집 화면 연결 실패: '.$e->getMessage();}
}
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>담당 유저 건물 수정</title><style>body{background:#f5f3ff;color:#302347;font:15px/1.7 system-ui;margin:0;padding:60px 20px}.edit-card{max-width:470px;margin:auto;padding:30px;background:white;border:1px solid #e8e0f5;border-radius:20px}h1{font-size:23px}button{background:#6d28d9;color:white;border:0;border-radius:10px;padding:13px 20px;font:600 15px system-ui;cursor:pointer}small,p{color:#786b8a}a{color:#6d28d9}</style></head><body><main class="edit-card"><small>담당 유저 관리</small><h1><?=$e($members[$target]['nickname']??$target)?>님의 건물관리</h1><p>기본정보와 업무 기록을 작성하고 저장할 수 있습니다.</p><p>수정 중인 유저는 건물관리 화면 상단에 표시됩니다. 다른 유저를 열면 편집 대상이 바뀌므로 이전에 열어 둔 서식은 새로 열어 주세요.</p>
<?php if($error!==''): ?><p role="alert"><?=$e($error)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="uid" value="<?=$e($target)?>"><input type="hidden" name="action" value="edit"><button>수정 화면 열기</button></form><p><a href="/clients_mini.php#manager-sidebar">매니저 화면으로</a></p></main></body></html>
