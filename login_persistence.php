<?php
declare(strict_types=1);
defined('ALP_DEPLOYED_AT') || define('ALP_DEPLOYED_AT', 1792893048);
/* Device-specific persistent login. No passwords or bearer tokens stored in plaintext. */
if(!function_exists('alp_session_start')){
function alp_root():string{return defined('ALP_TEST_ROOT')?ALP_TEST_ROOT:__DIR__;}
function alp_read(string $file):array{$v=@file_get_contents($file);if($v===false)return [];$v=preg_replace('/^<\?php exit; \?>\n/','',$v);$d=json_decode($v,true);return is_array($d)?$d:[];}
function alp_tx(callable $fn){
 $dir=alp_root().'/data/login_devices';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Login store unavailable');
 $lock=fopen($dir.'/tokens.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Login store busy');
 try{$file=$dir.'/tokens.php';$s=alp_read($file);$now=time();foreach($s as $id=>$r)if(!is_array($r)||($r['expires']??0)<=$now)unset($s[$id]);$result=$fn($s);$tmp=tempnam($dir,'.alp-');try{if(file_put_contents($tmp,"<?php exit; ?>\n".json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)throw new RuntimeException('Login store write failed');chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('Login store commit failed');}finally{if(is_file($tmp))unlink($tmp);}return $result;}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function alp_ttl(string $role):int{return ($role==='building'?90:30)*86400;}
function alp_admin_hash(string $name,string $source):string{
 $file=alp_root().($source==='legacy'?'/login.php':'/index.php');$code=@file_get_contents($file)?:'';
 if($source==='legacy'){$ok=preg_match('/\$ADMIN_ID\s*=\s*\x27([^\x27]+)\x27/',$code,$u)&&preg_match('/\$ADMIN_HASH\s*=\s*\x27([^\x27]+)\x27/',$code,$h);}
 else{$ok=preg_match('/const\s+ADMIN_USER\s*=\s*\x27([^\x27]+)\x27/',$code,$u)&&preg_match('/const\s+ADMIN_HASH\s*=\s*\x27([^\x27]+)\x27/',$code,$h);}
 if($name==='@existing-admin'){$credentials=$ok?[[$u[1],$h[1]]]:[];foreach(alp_read(alp_root().'/data/admins.json') as $a)$credentials[]=[(string)($a['username']??''),(string)($a['hash']??'')];return hash('sha256',json_encode($credentials));}
 if($ok&&hash_equals($u[1],$name))return $h[1];
 if($source!=='legacy')foreach(alp_read(alp_root().'/data/admins.json') as $a)if(($a['username']??'')===$name)return (string)($a['hash']??'');
 return '';
}
function alp_identity(array $session):?array{
 if(!empty($session['_imp'])||!empty($session['_mge_actor'])||!empty($session['demo']))return null;
 if(!empty($session['is_admin'])||!empty($session['ID_OK'])){$id=(string)($session['_alp_admin']??'@existing-admin');$source=(string)($session['_alp_admin_source']??'index');$hash=alp_admin_hash($id,$source);return $hash!==''?['type'=>'admin','id'=>$id,'source'=>$source,'role'=>'admin','fingerprint'=>hash('sha256',$hash),'nickname'=>'관리자']:null;}
 if(empty($session['is_user']))return null;
 $id=(string)($session['member_id']??'');if($id===''&&!empty($session['kakao_id']))$id='kakao_'.preg_replace('/[^0-9A-Za-z]/','',(string)$session['kakao_id']);
 $member=alp_read(alp_root().'/data/members.json')[$id]??null;
 if(!is_array($member)||($member['status']??'active')!=='active')return null;
 return ['type'=>'member','id'=>$id,'role'=>(string)($member['role']??''),'fingerprint'=>hash('sha256',(string)($member['created']??'').'|'.(string)($member['pw_hash']??'')),'nickname'=>(string)($member['nickname']??$id),'kakao_id'=>(string)($member['kakao_id']??''),'password_changed_at'=>strtotime((string)($member['password_changed_at']??''))?:0];
}
function alp_cookie(?string $value,int $expires=0):void{
 if(PHP_SAPI==='cli'||headers_sent())return;
 setcookie('sobang_login',$value??'',['expires'=>$value===null?time()-3600:$expires,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 if($value===null)unset($_COOKIE['sobang_login']);else $_COOKIE['sobang_login']=$value;
}
function alp_parts():?array{$v=$_COOKIE['sobang_login']??'';if(!is_string($v)||!preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D',$v,$m))return null;return [$m[1],$m[2]];}
function alp_forget_current(bool $optout=true):void{
 $parts=alp_parts();if($parts)alp_tx(static function(array &$s)use($parts){$r=$s[$parts[0]]??null;if($r&&hash_equals((string)$r['validator'],hash('sha256',$parts[1])))unset($s[$parts[0]]);});
 alp_cookie(null);if($optout&&session_status()===PHP_SESSION_ACTIVE)$_SESSION['_alp_optout']=true;
}
function alp_issue(?array $identity=null):void{
 $identity??=alp_identity($_SESSION);if(!$identity||session_name()!=='PHPSESSID'||!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))return;
 alp_forget_current(false);$selector=bin2hex(random_bytes(16));$validator=bin2hex(random_bytes(32));$now=time();$expires=$now+alp_ttl($identity['role']);
 alp_tx(static function(array &$s)use($selector,$validator,$identity,$now,$expires){
  $same=[];foreach($s as $id=>$r)if(($r['identity']['type']??'')===$identity['type']&&($r['identity']['id']??'')===$identity['id'])$same[$id]=$r['seen']??0;
  asort($same);while(count($same)>=20){$old=array_key_first($same);unset($same[$old],$s[$old]);}
  $s[$selector]=['validator'=>hash('sha256',$validator),'identity'=>$identity,'seen'=>$now,'expires'=>$expires];
 });
 $_SESSION['_alp_identity']=$identity;$_SESSION['_alp_seen']=$now;unset($_SESSION['_alp_optout']);alp_cookie($selector.'.'.$validator,$expires);alp_session_cookie($identity['role']);
}
function alp_session_cookie(string $role):void{
 if(PHP_SAPI==='cli'||headers_sent()||!ini_get('session.use_cookies'))return;
 setcookie(session_name(),session_id(),['expires'=>time()+alp_ttl($role),'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
}
function alp_authenticated(array $identity):array{
 if($identity['type']==='admin')return ['is_admin'=>true,'ID_OK'=>1,'login_type'=>'admin','_alp_admin'=>$identity['id'],'_alp_admin_source'=>$identity['source']??'index'];
 $s=['is_user'=>true,'is_admin'=>false,'ID_OK'=>0,'member_id'=>$identity['id'],'role'=>$identity['role'],'nickname'=>$identity['nickname'],'login_type'=>empty($identity['kakao_id'])?'member':'kakao'];if(!empty($identity['kakao_id']))$s['kakao_id']=$identity['kakao_id'];return $s;
}
function alp_match(array $a,array $b):bool{return $a['type']===$b['type']&&$a['id']===$b['id']&&hash_equals($a['fingerprint'],$b['fingerprint']);}
function alp_validate_token(?array $expected=null):?array{
 $parts=alp_parts();if(!$parts)return null;$now=time();
 return alp_tx(static function(array &$s)use($parts,$now,$expected){
  $r=$s[$parts[0]]??null;if(!$r||($r['expires']??0)<=$now||!hash_equals((string)$r['validator'],hash('sha256',$parts[1])))return null;
  $identity=alp_identity(alp_authenticated($r['identity']));
  if(!$identity||!alp_match($identity,$r['identity'])||($expected&&!alp_match($identity,$expected))){unset($s[$parts[0]]);return null;}
  $expires=$now+alp_ttl($identity['role']);$s[$parts[0]]['seen']=$now;$s[$parts[0]]['expires']=$expires;$s[$parts[0]]['identity']=$identity;
  return ['identity'=>$identity,'expires'=>$expires];
 });
}
function alp_login(?string $admin=null,string $source='index'):void{
 if($admin!==null){$_SESSION['_alp_admin']=$admin;$_SESSION['_alp_admin_source']=$source;}
 unset($_SESSION['_alp_identity'],$_SESSION['_alp_optout']);try{alp_issue();}catch(Throwable $e){error_log('Persistent login issue failed');}
}
function alp_password_changed():void{
 $identity=alp_identity($_SESSION);if($identity)alp_tx(static function(array &$s)use($identity){foreach($s as $id=>$r)if(($r['identity']['type']??'')===$identity['type']&&($r['identity']['id']??'')===$identity['id'])unset($s[$id]);});
 alp_forget_current();if($identity){$_SESSION['_alp_identity']=$identity;$_SESSION['_alp_seen']=time();}
}
function alp_session_start(array $options=[]):bool{
 if(session_status()===PHP_SESSION_ACTIVE)return true;
 $demo=session_name()!=='PHPSESSID'||str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/partner_demo')||!empty($GLOBALS['ALP_DISABLE']);
 if($demo&&PHP_SAPI!=='cli')ini_set('session.gc_maxlifetime',(string)(90*86400));
 if($demo||PHP_SAPI==='cli')return session_start($options);
 ini_set('session.gc_maxlifetime',(string)(90*86400));ini_set('session.cookie_httponly','1');ini_set('session.use_strict_mode','1');
 session_set_cookie_params(['lifetime'=>30*86400,'path'=>'/','domain'=>'','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
 if(!session_start($options))return false;
 try{
  if(!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))return true;
  if(!empty($_SESSION['is_user'])&&!empty($_SESSION['kakao_id'])&&empty($_SESSION['role'])&&!alp_identity($_SESSION))return true;
  $logged=!empty($_SESSION['is_user'])||!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);$identity=alp_identity($_SESSION);$old=$_SESSION['_alp_identity']??null;
  if($logged&&(!$identity||($old&&!alp_match($old,$identity))||(!empty($_SESSION['_alp_seen'])&&$_SESSION['_alp_seen']<=time()-alp_ttl($identity['role']))||(!$old&&($identity['password_changed_at']??0)>ALP_DEPLOYED_AT))){alp_forget_current();$_SESSION=[];session_regenerate_id(true);$identity=null;$logged=false;}
  if($identity){
   if($old&&!empty($_SESSION['_alp_seen'])&&$_SESSION['_alp_seen']>time()-300)return true;
   $_SESSION['_alp_seen']=time();$_SESSION['_alp_identity']=$identity;
   if(empty($_SESSION['_alp_optout'])){$valid=alp_validate_token($identity);if($valid)alp_cookie((string)$_COOKIE['sobang_login'],$valid['expires']);else alp_issue($identity);}
   alp_session_cookie($identity['role']);return true;
  }
  $valid=alp_validate_token();if($valid){session_regenerate_id(true);$_SESSION=alp_authenticated($valid['identity'])+['_alp_identity'=>$valid['identity'],'_alp_seen'=>time(),'csrf'=>bin2hex(random_bytes(24))];alp_cookie((string)$_COOKIE['sobang_login'],$valid['expires']);alp_session_cookie($valid['identity']['role']);}
  elseif(alp_parts())alp_cookie(null);
 }catch(Throwable $e){error_log('Persistent login unavailable: '.get_class($e));}
 return true;
}

}
