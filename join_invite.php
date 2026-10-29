<?php
declare(strict_types=1);
require_once __DIR__.'/invite_common.php';iq_session();$error='';$preview=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??'')==='open'){
 header('Content-Type: application/json; charset=utf-8');
 try{iq_csrf();$token=(string)($_POST['token']??'');iq_preview($token);$_SESSION['building_invite']=$token;echo '{"ok":true}';}
 catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}exit;
}
$token=(string)($_SESSION['building_invite']??'');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??'')==='check_userid'){
 header('Content-Type: application/json; charset=utf-8');
 try{
  iq_csrf();iq_preview($token);
  $checks=array_values(array_filter((array)($_SESSION['invite_id_checks']??[]),static fn($t)=>$t>time()-60));
  if(count($checks)>=40)throw new RuntimeException('잠시 후 다시 확인해 주세요.');
  $checks[]=time();$_SESSION['invite_id_checks']=$checks;
  if(!is_string($_POST['userid']??null))throw new RuntimeException('아이디를 입력해 주세요.');
  $candidate=strtolower(trim($_POST['userid']));iq_validate_userid($candidate);
  $available=true;foreach(mg_members() as $id=>$row){if(strcasecmp((string)$id,$candidate)===0){$available=false;break;}}
  echo json_encode(['ok'=>true,'userid'=>$candidate,'available'=>$available],JSON_UNESCAPED_UNICODE);
 }catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}exit;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  iq_csrf();if(mg_uid()!==''||!empty($_SESSION['is_admin'])||!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))throw new RuntimeException('이미 로그인되어 있습니다. 다른 계정 가입은 로그아웃 후 진행해 주세요.');
  $last=(array)($_SESSION['invite_attempts']??[]);$last=array_values(array_filter($last,fn($t)=>$t>time()-600));if(count($last)>=12)throw new RuntimeException('가입 시도가 많습니다. 잠시 후 다시 시도해 주세요.');$last[]=time();$_SESSION['invite_attempts']=$last;
  $member=iq_register($token,$_POST);
  if(!session_regenerate_id(true))throw new RuntimeException('로그인 정보를 만들지 못했습니다. 가입한 아이디로 로그인해 주세요.');
  unset($_SESSION['_manager_edit'],$_SESSION['_imp'],$_SESSION['_mge_actor'],$_SESSION['kakao_id'],$_SESSION['building_invite']);
  $_SESSION['is_user']=true;$_SESSION['is_admin']=false;$_SESSION['ID_OK']=0;$_SESSION['member_id']=$member['userid'];$_SESSION['nickname']=$member['nickname'];$_SESSION['role']='building';$_SESSION['login_type']='member';$_SESSION['csrf']=bin2hex(random_bytes(24));
  alp_login();session_write_close();header('Location: /subscribe_page.php',true,303);exit;
 }catch(Throwable $e){$error=$e->getMessage();}
}
if($token!==''){try{$preview=iq_preview($token);}catch(Throwable $e){if($error==='')$error=$e->getMessage();}}
function iq_h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>건물 연결 · 소방계획서</title><link rel="stylesheet" href="/invite_flow.css?v=20261002-1"><style>.iq-card [hidden]{display:none!important}.iq-id-row{display:flex;align-items:stretch;gap:8px}.iq-id-row input{min-width:0;flex:1;margin:0!important}.iq-id-row button{flex-shrink:0;border:1px solid #d3deeb;border-radius:10px;background:#f0f5fc;color:#315f94;padding:0 12px;font:600 13px system-ui;cursor:pointer}.iq-id-row button:disabled{opacity:.6}#iq-id-status{min-height:20px;margin:8px 0 18px}</style></head><body class="iq-page"><main class="iq-card" data-csrf="<?=iq_h($_SESSION['csrf'])?>"><a class="iq-brand" href="/">소방계획서.com</a><div class="iq-steps">1 가입·연결 <span>→ 2 카드·구독 → 3 이메일 확인</span></div><h1>우리 건물, 바로 시작하세요</h1><p>건물 정보와 연결 업체는 준비되어 있어요.<br>내 계정만 만들면 자동으로 연결됩니다.</p>
<p class="iq-error" role="alert" id="iq-error"><?=iq_h($error)?></p>
<?php if($preview): ?><section class="iq-building"><small>연결할 건물</small><strong><?=iq_h($preview['building'])?></strong><p><?=iq_h($preview['address'])?></p><span>연결 업체 · <?=iq_h($preview['manager'])?></span></section>
<?php if(mg_uid()!==''||!empty($_SESSION['is_admin'])||!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor'])): ?><p>현재 다른 계정으로 로그인되어 있습니다. 이 초대는 신규 가입용입니다. 기존 계정 연결은 파트너에게 요청해 주세요.</p><a class="iq-button" href="/FireDesk.php">내 화면으로</a>
<?php else: ?><form method="post" id="iq-signup"><input type="hidden" name="csrf" value="<?=iq_h($_SESSION['csrf'])?>"><label for="iq-userid">아이디</label><div class="iq-id-row"><input id="iq-userid" aria-describedby="iq-id-status" name="userid" autocomplete="username" autocapitalize="none" spellcheck="false" required pattern="[a-zA-Z][a-zA-Z0-9_]{3,19}" minlength="4" maxlength="20" placeholder="영문·숫자 4~20자" value="<?=iq_h($_POST['userid']??'')?>"><button type="button" id="iq-check-id">중복 확인</button></div><p class="iq-hint" id="iq-id-status" role="status" aria-live="polite">영문으로 시작하는 4~20자 · 숫자, 밑줄 사용 가능</p><label>비밀번호<input name="password" type="password" autocomplete="new-password" minlength="8" maxlength="64" required placeholder="8자 이상"></label><label>비밀번호 확인<input name="password_confirm" type="password" autocomplete="new-password" minlength="8" maxlength="64" required placeholder="비밀번호를 한 번 더 입력해 주세요" aria-describedby="iq-password-match"></label><p class="iq-hint" id="iq-password-match" role="status" aria-live="polite"></p><label>이메일<input type="email" name="email" autocomplete="email" maxlength="254" required placeholder="인증은 구독 후에 할 수 있어요" value="<?=iq_h($_POST['email']??'')?>"></label><p class="iq-hint">이메일 인증 전에는 이메일로 아이디 찾기·비밀번호 재설정을 할 수 없습니다. 아이디와 비밀번호를 기억해 주세요.</p><label class="iq-consent"><input type="checkbox" name="consent" value="yes" required><span><a href="/terms.php" target="_blank" rel="noopener">이용약관</a>·<a href="/privacy.php" target="_blank" rel="noopener">개인정보 처리방침</a>을 확인했으며, 표시된 파트너에게 건물정보와 업무 진행 현황을 공유하는 데 동의합니다.</span></label><button class="iq-button" type="submit">가입하고 구독으로 →</button><p class="iq-hint">가입만으로 결제되지 않습니다. 다음 화면에서 금액을 확인한 뒤 결제를 진행합니다.</p></form><?php endif; ?>
<?php else: ?><p id="iq-loading">파트너가 보여주는 QR을 촬영해 주세요.</p><a href="/index.php?auth=login">이미 가입했다면 로그인</a><?php endif; ?>
</main><script src="/join_invite.js?v=20261015-partner-1-connected-1-inquiry-1" defer></script></body></html>
