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
<!doctype html>
<html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>담당 유저 건물관리</title>
<style>
*{box-sizing:border-box}body{margin:0;color:#243248;background:#f5f7fb;font:14px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a,button{-webkit-tap-highlight-color:transparent}a{color:inherit}svg{display:block;flex-shrink:0}.page{width:min(100% - 40px,640px);margin:0 auto;padding:36px 0 48px}.back{display:inline-flex;align-items:center;gap:7px;color:#68778c;font-size:13px;text-decoration:none;margin-bottom:28px}.back:hover{color:#315fa4}.card{background:#fff;border:1px solid #e3e9f1;border-radius:24px;box-shadow:0 12px 44px #203b6008;overflow:hidden}.card-top{padding:30px 32px 0;display:flex;align-items:center;justify-content:space-between;gap:12px}.eyebrow{font-size:11px;letter-spacing:.1em;font-weight:750;color:#7d8ca2}.badge{display:inline-flex;align-items:center;gap:6px;color:#288066;background:#eff8f3;border:1px solid #dcefe4;border-radius:30px;padding:4px 10px;font-size:11px;font-weight:650}.dot{width:5px;height:5px;background:#38a57d;border-radius:50%}.intro{padding:28px 32px 25px;display:flex;gap:16px;align-items:flex-start}.avatar{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;flex-shrink:0;background:#edf3ff;color:#5278b6;border:1px solid #e1eafd}.intro-copy{min-width:0;flex:1}.user{font-size:13px;color:#708199;margin:0 0 3px;overflow-wrap:anywhere}h1{font-size:28px;letter-spacing:-1px;line-height:1.35;margin:0;font-weight:750}.lead{margin:12px 0 0;color:#748197;font-size:13px;line-height:1.8}.body{padding:0 32px 32px}.features{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 26px}.feature{padding:17px;background:#f8fafd;border:1px solid #eaf0f6;border-radius:12px}.feature svg{color:#728aad;margin-bottom:10px}.feature strong{display:block;font-size:13px;margin-bottom:3px}.feature span{display:block;color:#8a96a7;font-size:12px}.open{width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 19px;border:1px solid #416cba;border-radius:12px;background:#456fb9;color:white;line-height:1.5;font-family:inherit;font-size:14px;font-weight:650;cursor:pointer;box-shadow:0 4px 10px #456fb919;transition:background .15s,box-shadow .15s}.open:hover{background:#365fa8;box-shadow:0 5px 14px #456fb92b}.open:focus-visible,.back:focus-visible{outline:3px solid #9bb8eb;outline-offset:4px}.foot{display:flex;gap:9px;align-items:flex-start;padding:19px 32px;background:#fbfcfe;border-top:1px solid #edf1f6}.foot svg{color:#8b9ab0;margin-top:3px}.foot p{margin:0;font-size:12px;line-height:1.8;color:#8793a5}.foot strong{color:#62738c;font-weight:600}.error{padding:13px 15px;border:1px solid #f1d4cc;border-radius:10px;background:#fff6f2;color:#a34d39;font-size:13px;margin:0 0 17px;overflow-wrap:anywhere}.bottom{margin:19px 0 0;text-align:center;font-size:12px;color:#96a1b2}@media(min-height:760px){.page{padding-top:72px}}@media(max-width:480px){.page{width:calc(100% - 28px);padding-top:22px}.back{margin-bottom:18px}.card{border-radius:18px}.card-top{padding:22px 22px 0}.intro{padding:23px 22px;gap:12px}.avatar{width:46px;height:46px;border-radius:13px}h1{font-size:24px}.body{padding:0 22px 24px}.feature{padding:13px}.foot{padding:17px 22px}.features{gap:8px}.lead{font-size:12px}}
</style></head><body><main class="page">
<a class="back" href="/clients_mini.php#manager-sidebar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg>매니저 화면으로</a>
<section class="card" aria-labelledby="page-title">
<div class="card-top"><span class="eyebrow">담당 유저 관리</span><span class="badge"><span class="dot" aria-hidden="true"></span>연결된 유저</span></div>
<div class="intro"><span class="avatar" aria-hidden="true"><svg width="29" height="29" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M10 21v-5h4v5"/></svg></span><div class="intro-copy"><p class="user"><?=$e($members[$target]['nickname']??$target)?>님과 함께하는</p><h1 id="page-title">건물 안전관리</h1><p class="lead">유저와 같은 화면에서 필요한 업무를 확인하고,<br>건물정보와 업무 기록을 작성할 수 있습니다.</p></div></div>
<div class="body"><div class="features"><div class="feature"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><strong>건물정보 관리</strong><span>기본정보 확인·수정</span></div><div class="feature"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="m8 12 3 3 5-6"/></svg><strong>업무 기록 작성</strong><span>진행 현황 확인·저장</span></div></div>
<?php if($error!==''): ?><p class="error" role="alert"><?=$e($error)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=$e($_SESSION['csrf'])?>"><input type="hidden" name="uid" value="<?=$e($target)?>"><input type="hidden" name="action" value="edit"><button class="open" type="submit"><span>건물관리 화면 열기</span><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button></form></div>
<div class="foot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-11v2"/></svg><p><strong>현재 수정 중인 유저는 건물관리 화면 상단에 표시됩니다.</strong><br>다른 유저로 전환하면 이전에 열어 둔 서식은 새로 열어 주세요.</p></div>
</section><p class="bottom">담당 유저의 업무를 함께 확인하고 기록하세요.</p>
</main></body></html>
