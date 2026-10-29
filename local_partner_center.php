<?php
declare(strict_types=1);
if(!defined('LOCAL_PARTNER_CENTER_AUTHORIZED')){http_response_code(404);exit;}
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$localActor=mg_uid();$localMembers=mg_members();if(!mg_is_local_partner($localActor,$localMembers)){http_response_code(403);exit;}
mg_local_reconcile_pending();
function lc_rows(string $actor,array $members,array $state):array {
 $scope=hash('sha256',$actor.'|'.$members[$actor]['created']);$rows=[];
 foreach($members as $uid=>$member){$uid=(string)$uid;if(!is_array($member)||!mg_can_view($actor,$uid,$members,$state))continue;$key=mg_link_key($uid,$member);$link=$state['links'][$key]??[];if(($link['source']??'')!=='local_auto')continue;
  $info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');$rows[]=['key'=>$key,'uid'=>$uid,'name'=>trim((string)($info['name']??''))?:((string)($member['nickname']??$uid)).'님의 건물','address'=>(string)($info['address']??''),'connected_at'=>(string)($link['at']??''),'new'=>empty($state['local_connection_seen'][$scope][$key])];
 }usort($rows,static fn($a,$b)=>strcmp($b['connected_at'],$a['connected_at']));return $rows;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||isset($_GET['local_feed'])){
 header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 try{if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){if(($_POST['action']??'')!=='local_seen'||!is_string($_POST['csrf']??null)||!hash_equals($CSRF,$_POST['csrf']))throw new RuntimeException('요청을 확인해 주세요.');
  mg_member_tx(static function(array &$members)use($localActor){mg_state_tx(static function(array &$s)use($members,$localActor){$scope=hash('sha256',$localActor.'|'.$members[$localActor]['created']);$key=(string)($_POST['key']??'');foreach(lc_rows($localActor,$members,$s) as $r)if(hash_equals($r['key'],$key)){$s['local_connection_seen'][$scope][$key]=time();return;}throw new RuntimeException('현재 연결된 건물을 확인해 주세요.');});});}
  $rows=lc_rows($localActor,mg_members(),mg_read(mg_state_file()));echo json_encode(['ok'=>true,'rows'=>$rows,'csrf'=>$CSRF],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
 }catch(Throwable $e){http_response_code(403);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}exit;
}
$PAGE_TITLE='로컬 파트너 업무';$NAV_MODE='account';$IS_LOGGED_IN=true;$ACCOUNT_NICK='로컬 파트너';$ACCOUNT_IS_ADMIN=false;$ACCOUNT_UNREAD=0;require __DIR__.'/_header.php';
?>
<link rel="stylesheet" href="/local_partner_center.css?v=1">
<main class="lc-workspace" id="manager-sidebar"><header><div><span>LOCAL PARTNER · 0918</span><h1>로컬 파트너 업무</h1><p>자동 연결된 건물을 확인하고, 작성 문의에 답변하세요.</p></div><button type="button" class="lc-notify" onclick="window.visitPushOpen?.(this)">알림 설정</button></header>
<nav class="lc-tabs" role="tablist" aria-label="로컬 파트너 업무"><button type="button" id="lc-new-tab" role="tab" aria-selected="true" aria-controls="lc-new-panel">새 연결 <b data-lc-new>0</b></button><button type="button" id="lc-help-tab" role="tab" aria-selected="false" aria-controls="ms-help">작성 문의 <b data-lc-help>0</b></button></nav>
<section id="lc-new-panel" role="tabpanel" aria-labelledby="lc-new-tab"><div class="lc-toolbar"><strong>수락 절차 없이 연결되었습니다.</strong><label><input type="checkbox" data-lc-all>확인한 연결도 보기</label></div><div class="lc-list" role="status">연결된 건물을 확인하고 있습니다.</div></section>
<section id="ms-help" role="tabpanel" aria-labelledby="lc-help-tab" hidden><p class="lc-help-note">새 작성 문의가 있으면 이곳에 표시됩니다. 문의한 항목을 열어 확인하고 답변을 저장해 주세요.</p></section>
</main>
<script src="/manager_help.js?v=20261026-local-auto-1" data-manager="1" defer></script>
<link rel="stylesheet" href="/visit_push.css?v=20261023-date-map-1"><script src="/visit_push.js?v=20261026-building-1" defer></script><script src="/local_partner_center.js?v=1" defer></script>
<?php require __DIR__.'/_footer.php'; ?>
