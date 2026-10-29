<?php
declare(strict_types=1);
require_once __DIR__.'/invite_common.php';iq_session();
$embedded=($_GET['embed']??'')==='1';
if($embedded){header('X-Frame-Options: SAMEORIGIN');header("Content-Security-Policy: frame-ancestors 'self'");}
$uid=mg_uid();
if($uid===''||!empty($_SESSION['_mge_actor'])){header('Location: /index.php?auth=login');exit;}
function ie_h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function ie_send_wait(array $hits,int $now):int {
 $hits=array_values(array_filter($hits,static fn($t)=>is_numeric($t)&&(int)$t>$now-3600));
 sort($hits,SORT_NUMERIC);
 if(!$hits)return 0;
 $until=(int)end($hits)+60;
 if(count($hits)>=5)$until=max($until,(int)$hits[count($hits)-5]+3600);
 return max(0,$until-$now);
}
$note='';$error='';
try{
 $members=mg_members();$me=$members[$uid]??[];if(!mg_active($me,'building'))throw new RuntimeException('건물관리자 계정에서 이메일을 확인해 주세요.');
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  iq_csrf();$action=(string)($_POST['action']??'');
  if($action==='send'){
   $email=strtolower(trim((string)($_POST['email']??'')));if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('이메일 주소를 확인해 주세요.');
   $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$nonce=bin2hex(random_bytes(16));
   mg_member_tx(function(array &$all)use($uid,$email,$code,$nonce){
    $m=$all[$uid]??[];if(!mg_active($m,'building'))throw new RuntimeException('계정 상태를 확인해 주세요.');
    if(!empty($m['email_ok'])&&empty($m['email_verification_required']))throw new RuntimeException('이미 이메일 인증을 완료했습니다.');
    $hits=array_values(array_filter((array)($m['email_verify_sends']??[]),fn($t)=>$t>time()-3600));
    if(ie_send_wait($hits,time())>0)throw new RuntimeException('재발송 대기 중입니다. 아래에 표시된 시간이 지나면 다시 받을 수 있습니다.');
    foreach($all as $other=>$row)if((string)$other!==$uid&&strcasecmp((string)($row['email']??''),$email)===0)throw new RuntimeException('이미 사용 중인 이메일입니다. 다른 주소를 입력해 주세요.');
    $hits[]=time();$all[$uid]['email_verify_sends']=$hits;$all[$uid]['pending_email']=$email;
    $all[$uid]['email_challenge']=['hash'=>hash('sha256',$nonce.':'.$code),'nonce'=>$nonce,'email'=>$email,'expires'=>time()+600,'tries'=>0];
   });
   require_once __DIR__.'/mail_config.php';
   if(!send_mail($email,'[소방계획서.com] 이메일 확인','이메일 인증번호: '.$code."\n10분 안에 입력해 주세요. 본인이 요청하지 않았다면 무시해 주세요."))throw new RuntimeException('메일을 보내지 못했습니다. 잠시 후 다시 시도해 주세요.');
   $note='인증번호를 보냈습니다. 10분 안에 입력해 주세요.';
  }elseif($action==='verify'){
   $code=trim((string)($_POST['code']??''));
   $result=mg_member_tx(function(array &$all)use($uid,$code){
    $m=$all[$uid]??[];$c=$m['email_challenge']??[];
    if(!mg_active($m,'building')||!$c||($c['expires']??0)<=time()||($c['tries']??0)>=5||($c['email']??'')!==($m['pending_email']??''))return '인증번호가 만료되었습니다. 다시 받아 주세요.';
    $all[$uid]['email_challenge']['tries']++;
    if(!preg_match('/^\d{6}$/D',$code)||!hash_equals($c['hash'],hash('sha256',$c['nonce'].':'.$code)))return '인증번호가 일치하지 않습니다.';
    foreach($all as $other=>$row)if((string)$other!==$uid&&strcasecmp((string)($row['email']??''),$c['email'])===0)return '이미 사용 중인 이메일입니다. 다른 주소로 인증해 주세요.';
    $all[$uid]['email']=$c['email'];$all[$uid]['email_ok']=true;$all[$uid]['email_verified_at']=date('c');$all[$uid]['email_verification_required']=false;unset($all[$uid]['pending_email'],$all[$uid]['email_challenge']);return '';
   });if($result!=='')throw new RuntimeException($result);$note='이메일 인증을 완료했습니다.';
  }else throw new RuntimeException('지원하지 않는 요청입니다.');
 }
 $me=mg_members()[$uid]??[];
}catch(Throwable $e){$error=$e->getMessage();$me=mg_members()[$uid]??[];}
$done=!empty($me['email_ok'])&&empty($me['email_verification_required']);
$challenge=$me['email_challenge']??[];
$codeStep=!$done&&!empty($challenge)&&($challenge['expires']??0)>time()&&($challenge['tries']??0)<5&&!(($_POST['action']??'')==='send'&&$error!=='');
$sendWait=ie_send_wait((array)($me['email_verify_sends']??[]),time());
$emailValue=(string)($me['pending_email']??$me['email']??'');
?>
<!doctype html>
<html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>이메일 인증 · 소방계획서.com</title><link rel="stylesheet" href="/invite_email.css?v=20261002-popup"></head>
<body class="email-page <?=$embedded?'email-embedded':''?>">
<header class="email-header"><a href="/FireDesk.php" class="email-brand">소방계획서<span>.com</span></a><a class="email-back" href="/FireDesk.php">← 건물관리</a></header>
<main class="email-main"><section class="email-card" aria-labelledby="email-title">
<div class="email-icon <?=$done?'is-done':''?>" aria-hidden="true"><?php if($done): ?><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg><?php else: ?><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><?php endif; ?></div>
<?php if(!$done): ?><div class="email-progress" aria-label="<?=$codeStep?'2단계 인증번호 확인':'1단계 이메일 확인'?>"><span class="is-active">1 이메일 확인</span><i aria-hidden="true"></i><span class="<?=$codeStep?'is-active':''?>">2 인증 완료</span></div><?php endif; ?>
<h1 id="email-title"><?=$done?'이메일 인증 완료':($codeStep?'인증번호를 입력해 주세요':'이메일을 확인해 주세요')?></h1>
<p class="email-lead"><?=$done?'계정 복구에 사용할 이메일이 확인되었습니다.':($codeStep?'이메일로 받은 숫자 6자리를 입력하면 완료됩니다.':'아이디 찾기와 비밀번호 재설정에 사용할 주소입니다.')?></p>
<?php if($error!==''): ?><div class="email-alert" role="alert"><?=ie_h($error)?></div><?php endif; ?>
<?php if($done): ?>
<div class="email-address"><span>인증된 이메일</span><strong><?=ie_h($me['email']??'')?></strong><small>인증 완료 ✓</small></div>
<?php if($embedded): ?><button type="button" class="email-primary" data-email-close>완료 · 닫기</button><?php else: ?><a class="email-primary" href="/FireDesk.php">건물관리로 돌아가기</a><?php endif; ?>
<?php else: ?>
<section id="email-entry" <?=$codeStep?'hidden':''?>>
<form method="post" data-email-form><input type="hidden" name="csrf" value="<?=ie_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="send">
<label for="email-address-input">이메일 주소</label><input id="email-address-input" type="email" name="email" maxlength="254" required autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="example@email.com" value="<?=ie_h($emailValue)?>" aria-describedby="email-edit-hint">
<p class="email-hint" id="email-edit-hint">잘못 입력한 주소라면 여기에서 수정해 주세요.</p><button class="email-primary" type="submit" data-resend-label="인증번호 받기" <?=$sendWait>0?'disabled':''?>><?=$sendWait>0?'다시 받기까지 '.$sendWait.'초':'인증번호 받기'?></button></form>
</section>
<section id="email-code-step" <?=$codeStep?'':'hidden'?>>
<div class="email-address"><span>인증번호를 보낸 주소</span><strong><?=ie_h($emailValue)?></strong><button type="button" class="email-text" id="email-edit">주소 수정</button></div>
<form method="post" data-email-form><input type="hidden" name="csrf" value="<?=ie_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="verify">
<label for="email-code">인증번호</label><input id="email-code" class="email-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="000000" aria-describedby="email-code-hint">
<p class="email-hint" id="email-code-hint">발송 후 10분 이내에 입력해 주세요.</p><button class="email-primary" type="submit">인증 완료하기</button></form>
<details class="email-help"><summary>인증번호가 오지 않았나요?</summary><p>스팸메일함과 이메일 주소를 확인해 주세요. 재발송은 1분 간격으로 가능합니다.</p>
<form method="post" data-email-form><input type="hidden" name="csrf" value="<?=ie_h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="send"><input type="hidden" name="email" value="<?=ie_h($emailValue)?>"><button class="email-secondary" type="submit" data-resend-label="인증번호 다시 받기" <?=$sendWait>0?'disabled':''?>><?=$sendWait>0?'다시 받기까지 '.$sendWait.'초':'인증번호 다시 받기'?></button></form></details>
</section>
<p class="email-hint" id="email-resend-status" style="text-align:center;font-variant-numeric:tabular-nums;min-height:20px;margin:16px 0 0"></p>
<?php if($embedded): ?><button type="button" class="email-later" data-email-close>나중에 하기</button><?php else: ?><a class="email-later" href="/FireDesk.php">나중에 하기</a><?php endif; ?>
<?php endif; ?>
</section><p class="email-footnote">소방안전 기록관리 · 소방계획서.com</p></main>
<script>
(()=>{
 const readyAt=Date.now()+<?=json_encode($sendWait)?>*1000;
 let submitting=false;
 function updateWait(){
  const seconds=Math.max(0,Math.ceil((readyAt-Date.now())/1000));
  const time=seconds>=60?Math.floor(seconds/60)+'분 '+String(seconds%60).padStart(2,'0')+'초':seconds+'초';
  document.querySelectorAll('[data-resend-label]').forEach(button=>{
   if(submitting)return;
   button.disabled=seconds>0;
   button.textContent=seconds>0?'다시 받기까지 '+time:button.dataset.resendLabel;
  });
  const status=document.getElementById('email-resend-status');
  if(status)status.textContent=seconds>0?'재발송 가능까지 '+time:'인증번호를 받을 수 있습니다.';
 }
 updateWait();setInterval(updateWait,1000);
 document.addEventListener('visibilitychange',updateWait);
 document.querySelectorAll('[data-email-close]').forEach(button=>button.addEventListener('click',()=>{
  if(window.parent!==window)window.parent.postMessage({type:'email-verification-close'},location.origin);
 }));
 document.getElementById('email-edit')?.addEventListener('click',()=>{
  document.getElementById('email-code-step').hidden=true;document.getElementById('email-entry').hidden=false;
  document.getElementById('email-title').textContent='이메일을 확인해 주세요';
  document.querySelector('.email-lead').textContent='아이디 찾기와 비밀번호 재설정에 사용할 주소입니다.';
  const progress=document.querySelector('.email-progress');progress.setAttribute('aria-label','1단계 이메일 확인');progress.lastElementChild.classList.remove('is-active');
  document.getElementById('email-address-input').focus();
 });
 document.querySelectorAll('[data-email-form]').forEach(form=>form.addEventListener('submit',e=>{
  if(submitting||(form.elements.action.value==='send'&&Date.now()<readyAt)){e.preventDefault();return;}
  submitting=true;
  const button=form.querySelector('button[type="submit"]');button.disabled=true;button.dataset.original=button.innerHTML;button.textContent='잠시만 기다려 주세요…';
 }));
 window.addEventListener('pageshow',()=>{submitting=false;document.querySelectorAll('button[data-original]').forEach(button=>{button.disabled=false;button.innerHTML=button.dataset.original;});updateWait();});
})();
</script></body></html>
