<?php
declare(strict_types=1);
require_once __DIR__.'/invite_common.php';iq_session();header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/manager_invite_connection.php';
try {
 $actor=mg_uid();$members=mg_members();if(!mg_active($members[$actor]??[],'agency')||!empty($_SESSION['_mge_actor']))throw new RuntimeException('파트너 본인 계정으로 로그인해 주세요.');
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){echo json_encode(['ok'=>true,'csrf'=>$_SESSION['csrf']]);exit;}
 iq_csrf();$act=(string)($_POST['action']??'');
 if($act==='create'){
  if(time()-(int)($_SESSION['invite_created_at']??0)<3)throw new RuntimeException('잠시 후 다시 생성해 주세요.');
  $result=iq_create($actor,(string)($_POST['id']??''));$_SESSION['invite_created_at']=time();$result['svg']=iq_svg($result['url']);
 }elseif(in_array($act,['status','revoke'],true)){
  $hash=iq_hash((string)($_POST['token']??''));
  $result=mg_member_tx(function(array &$freshMembers)use($hash,$actor,$act){return mg_state_tx(function(array &$s)use($hash,$actor,$freshMembers,$act){
   $status=iq_connection_status($actor,$hash,$freshMembers,$s,time());
   if($act==='revoke'&&!$status['used']){$s['qr_invites'][$hash]['revoked_at']=time();$status['expired']=true;}
   return $status;
  });});
 }else throw new RuntimeException('지원하지 않는 요청입니다.');
 echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'처리하지 못했습니다. 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}
