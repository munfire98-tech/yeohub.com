<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

alp_session_start();require_once __DIR__.'/visit_push_inbox_common.php';header('Cache-Control: no-store');
$id=(string)($_GET['message']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$id)){http_response_code(404);exit('알림을 찾을 수 없습니다.');}
$members=mg_members();$actor=vp_actor($members);
if($actor===''){$_SESSION['visit_push_return']=$id;header('Location: /index.php');exit;}
unset($_SESSION['visit_push_return']);
$m=mg_read(vp_file())['messages'][$id]??null;$content=$m?vp_resolve($m,$actor,$members):null;
if(!$content){http_response_code(403);exit('일정이 삭제되었거나 현재 계정의 조회 권한이 없습니다.');}
unset($_SESSION['visit_push_return']);
vp_tx(static function(array &$s)use($id,$actor,$members){vi_read($s,$id,$actor,vp_scope($actor,$members),mg_read(vp_store($actor,$members)),$members,mg_read(mg_state_file()));});
header('Location: '.vi_open_destination($m,$content));exit;
