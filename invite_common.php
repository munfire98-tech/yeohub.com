<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

require_once __DIR__.'/manager_addresses_common.php';
const IQ_TTL=1800;
const IQ_ORIGIN='https://www.xn--989ay50awvdzmk18f.com';
function iq_session():void {
 date_default_timezone_set('Asia/Seoul');
 if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['lifetime'=>2592000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);alp_session_start();}
 if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
 header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
}
function iq_csrf():void {if(!is_string($_POST['csrf']??null)||!hash_equals((string)$_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('화면을 새로고침하고 다시 시도해 주세요.');}
function iq_hash(string $token):string {if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('초대 링크를 확인해 주세요.');return hash('sha256',$token);}
function iq_row(array $members,array $state,string $hash,int $now):array {
 $i=$state['qr_invites'][$hash]??[];$actor=(string)($i['manager']??'');$id=(string)($i['draft_id']??'');$r=$state['address_book'][$id]??[];
 if(!$i||($i['expires']??0)<=$now||!empty($i['revoked_at'])||!ma_owned($r,$actor,$members)||($i['manager_created']??'')!==($members[$actor]['created']??null))throw new RuntimeException('초대가 만료되었거나 취소되었습니다. 파트너에게 새 QR을 요청해 주세요.');
 return [$i,$r];
}
function iq_preview(string $token):array {
 $hash=iq_hash($token);$members=mg_members();$s=mg_read(mg_state_file());[$i,$r]=iq_row($members,$s,$hash,time());
 if(!empty($i['used_at'])||ma_linked($r,$i['manager'],$members,$s))throw new RuntimeException('이미 연결이 완료된 초대입니다. 가입한 아이디로 로그인해 주세요.');
 return ['building'=>(string)($r['name']??''),'address'=>(string)($r['address']??''),'manager'=>(string)($members[$i['manager']]['nickname']??'연결 업체'),'expires'=>$i['expires']];
}
function iq_create(string $actor,string $id):array {
 $token=bin2hex(random_bytes(32));$hash=iq_hash($token);$now=time();
 return mg_member_tx(function(array &$members)use($actor,$id,$token,$hash,$now){return mg_state_tx(function(array &$s)use($members,$actor,$id,$token,$hash,$now){
  $r=$s['address_book'][$id]??[];
  if(!preg_match('/^[a-f0-9]{24}$/D',$id)||!ma_owned($r,$actor,$members)||ma_linked($r,$actor,$members,$s))throw new RuntimeException('본인의 연결되지 않은 사전등록 건물에서만 QR을 만들 수 있습니다.');
  if(trim((string)($r['name']??''))===''||trim((string)($r['address']??''))==='')throw new RuntimeException('건물명과 주소를 먼저 저장해 주세요.');
  foreach($s['qr_invites']??[] as $k=>$i){
   if(($i['expires']??0)<$now-86400&&empty($i['claim_member']))unset($s['qr_invites'][$k]);
   elseif(($i['draft_id']??'')===$id&&empty($i['used_at']))$s['qr_invites'][$k]['revoked_at']=$now;
   elseif(($i['draft_id']??'')===$id&&!empty($i['claim_member']))throw new RuntimeException('가입 연결을 마무리하는 중입니다. 잠시 후 다시 확인해 주세요.');
  }
  $s['qr_invites'][$hash]=['manager'=>$actor,'manager_created'=>$members[$actor]['created']??'','draft_id'=>$id,'created_at'=>$now,'expires'=>$now+IQ_TTL];
  return ['token'=>$token,'url'=>IQ_ORIGIN.'/join_invite.php#'.$token,'expires'=>$now+IQ_TTL,'building'=>$r['name'],'manager'=>$members[$actor]['nickname']??'연결 업체'];
 });});
}
function iq_validate_userid(string $uid):void {
 if(!preg_match('/^[a-z][a-z0-9_]{3,19}$/D',$uid)||($uid==='mhg1234'||str_starts_with($uid,'kakao_')||preg_match('/^(admin|administrator|root|support|system|tworix)(_|$)/',$uid)))throw new RuntimeException('아이디는 영문으로 시작하는 영문·숫자·밑줄 4~20자로 입력해 주세요.');
}
function iq_input(array $in):array {
 $uid=strtolower(trim((string)($in['userid']??'')));$pw=(string)($in['password']??'');$email=strtolower(trim((string)($in['email']??'')));
 iq_validate_userid($uid);
 if(strlen($pw)<8||strlen($pw)>64)throw new RuntimeException('비밀번호는 8~64바이트로 입력해 주세요.');
 if(!is_string($in['password_confirm']??null)||!hash_equals($pw,$in['password_confirm']))throw new RuntimeException('비밀번호 확인이 일치하지 않습니다. 다시 입력해 주세요.');
 if(iq_password_common($pw,$uid,$email))throw new RuntimeException('쉽게 추측할 수 있는 비밀번호입니다. 다른 비밀번호를 사용해 주세요.');
 if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('이메일 주소를 확인해 주세요.');
 if(($in['consent']??'')!=='yes')throw new RuntimeException('이용약관·개인정보 처리 및 연결 업체와 정보 공유에 동의해 주세요.');
 return ['uid'=>$uid,'password'=>$pw,'email'=>$email];
}
/** Member -> state lock order. A guarded recovery snapshot handles a failed member-file commit. */
function iq_register_state(array &$members,array &$s,string $hash,array $input,int $now):array {
 [$invite,$r]=iq_row($members,$s,$hash,$now);$uid=$input['uid'];$actor=$invite['manager'];$id=$invite['draft_id'];
 if(!empty($invite['used_at'])){
  $candidate=$invite['claim_member']??null;
  if(!is_array($candidate)||($candidate['userid']??'')!==$uid||!password_verify($input['password'],$candidate['pw_hash']??'')||($candidate['pending_email']??'')!==$input['email'])throw new RuntimeException('이미 사용한 초대입니다. 가입한 아이디로 로그인해 주세요.');
  if(isset($members[$uid])&&(($members[$uid]['invite_id']??'')!==$hash||($members[$uid]['created']??'')!==$candidate['created']))throw new RuntimeException('가입 정보를 확인할 수 없습니다. 관리자에게 문의해 주세요.');
  if(($r['linked_uid']??'')!==$uid||($r['linked_key']??'')!==mg_link_key($uid,$candidate)||($s['links'][mg_link_key($uid,$candidate)]['status']??'')!=='accepted')throw new RuntimeException('연결 상태가 변경되었습니다. 파트너에게 확인해 주세요.');
  if(!isset($members[$uid]))$members[$uid]=$candidate;
  return $members[$uid];
 }
 if(ma_linked($r,$actor,$members,$s))throw new RuntimeException('이미 다른 유저와 연결된 건물입니다.');
 foreach($members as $key=>$m)if(strcasecmp((string)$key,$uid)===0)throw new RuntimeException('이미 사용 중인 아이디입니다. 다른 아이디를 입력해 주세요.');
 // Verified addresses remain exclusive; unverified pending addresses never reserve ownership.
 foreach($members as $m)if(strcasecmp((string)($m['email']??''),$input['email'])===0)throw new RuntimeException('이미 가입된 이메일입니다. 기존 계정은 파트너에게 연결을 요청해 주세요.');
 $date=date('Y-m-d H:i:s',$now);$member=['userid'=>$uid,'nickname'=>(string)$r['name'],'role'=>'building','email'=>'','pending_email'=>$input['email'],'email_ok'=>false,'email_verification_required'=>true,'phone'=>'','phone_ok'=>false,'joined_via'=>'manager_qr','status'=>'active','created'=>$date,'last_login'=>$date,'pw_hash'=>password_hash($input['password'],PASSWORD_DEFAULT),'invite_id'=>$hash,'referrer_uid'=>$actor,'referrer_created'=>$members[$actor]['created']??'','referral_at'=>date('c',$now),'referral_reward_eligible'=>true,'assigned_manager_uid'=>$actor,'assigned_manager_created'=>$members[$actor]['created']??'','manager_request_id'=>bin2hex(random_bytes(16)),'manager_consent_at'=>date('c',$now),'terms_accepted_at'=>date('c',$now)];
 $request=mg_link_key($uid,$member);
 $s['links'][$request]=['status'=>'accepted','at'=>date('c',$now),'actor'=>$actor,'source'=>'qr_invite'];
 $s['address_book'][$id]['linked_uid']=$uid;$s['address_book'][$id]['linked_key']=$request;$s['address_book'][$id]['linked_at']=date('c',$now);
 $info=(array)($r['basic_info']??[]);$info['name']=$r['name'];$info['address']=$r['address'];
 $s['draft_handoffs'][$uid.':'.hash('sha256',$date)]=['id'=>$id,'info'=>$info,'manager'=>$actor,'manager_created'=>$members[$actor]['created']??'','request_key'=>$request,'at'=>date('c',$now)];
 $s['qr_invites'][$hash]['used_at']=$now;$s['qr_invites'][$hash]['used_uid']=$uid;$s['qr_invites'][$hash]['claim_member']=$member;
 $members[$uid]=$member;return $member;
}
function iq_register(string $token,array $in):array {
 $input=iq_input($in);$hash=iq_hash($token);
 $member=mg_member_tx(function(array &$members)use($hash,$input){return mg_state_tx(function(array &$s)use(&$members,$hash,$input){return iq_register_state($members,$s,$hash,$input,time());});});
 // Failure here is harmless: an idempotent retry still checks the same credentials.
 try{mg_state_tx(function(array &$s)use($hash){unset($s['qr_invites'][$hash]['claim_member']);});}catch(Throwable $e){error_log('QR invite: finalization pending');}
 require_once __DIR__.'/telegram_member_events.php';
 tme_signup((string)$member['userid']);
 require_once __DIR__.'/visit_push_common.php';vp_notify_member_change((string)$member['userid']);
 return $member;
}
function iq_svg(string $url):string {
 require_once __DIR__.'/invite_vendor/qrcode.php';$q=new QRcode($url,'M');$b=$q->getBarcodeArray();$n=(int)$b['num_rows'];$path='';
 for($y=0;$y<$n;$y++)for($x=0;$x<$n;$x++)if($b['bcode'][$y][$x])$path.='M'.($x+4).' '.($y+4).'h1v1h-1z';
 return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($n+8).' '.($n+8).'" role="img" aria-label="건물 연결 QR 코드" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><path fill="#142c44" d="'.$path.'"/></svg>';
}

function iq_password_common(string $password, string $userid, string $email): bool {
  $value = strtolower(trim($password));
  $local = strtolower((string)strtok($email, '@'));
  $blocked = [
    'password', 'password1', 'password1234', 'password123!',
    'qwerty12', 'qwerty123456', 'qwer1234', 'qwer12345678',
    '12345678', '123456789012', '11111111', '111111111111',
    'abcd1234', 'tworix12', 'tworix123456',
  ];
  return in_array($value, $blocked, true)
      || $value === strtolower($userid)
      || ($local !== '' && $value === $local);
}

