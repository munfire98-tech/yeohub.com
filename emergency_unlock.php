<?php
/** Temporary, password-protected recovery. No session, database or app bootstrap. */
declare(strict_types=1);
function er_files():array{return [__DIR__.'/data/members.json.lock',__DIR__.'/data/manager_system.php.lock',__DIR__.'/data/manager_help_requests.php.lock'];}
function er_owners(array $files,string $proc='/proc'):array {
 if(!function_exists('posix_geteuid')||!function_exists('posix_getpid'))throw new RuntimeException('호스팅에서 프로세스 확인 기능을 사용할 수 없습니다.');
 $uid=posix_geteuid();if($uid===0)throw new RuntimeException('관리자 권한 프로세스에서는 실행하지 않습니다.');
 $raw=@file_get_contents($proc.'/locks');if($raw===false)throw new RuntimeException('호스팅에서 /proc/locks 접근을 막았습니다. 카페24에 잠금 프로세스 종료를 요청해야 합니다.');
 $targets=[];foreach($files as $file){$real=realpath($file);if(!$real)continue;$st=@stat($real);if(!$st||$st['uid']!==$uid)continue;$targets[$real]=$st;}
 $found=[];
 foreach(explode("\n",$raw) as $line){
  if(!preg_match('/^\d+:\s+FLOCK\s+ADVISORY\s+WRITE\s+(\d+)\s+\S+:(\d+)\s+0\s+EOF\s*$/',$line,$match))continue;
  $pid=(int)$match[1];if($pid<=1||$pid===posix_getpid())continue;
  $status=@file_get_contents($proc.'/'.$pid.'/status');
  if($status===false||!preg_match('/^Uid:\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m',$status,$ids))continue;
  if((int)$ids[1]!==$uid||(int)$ids[2]!==$uid||(int)$ids[3]!==$uid||(int)$ids[4]!==$uid)continue;
  $exe=@readlink($proc.'/'.$pid.'/exe');$name=basename((string)$exe);
  if(!preg_match('/^(?:php(?:[0-9.]+)?(?:-fpm|-cgi)?|php-fpm(?:[0-9.]+)?|lsphp(?:[0-9.]+)?|httpd|apache2)$/D',$name))continue;
  foreach(@glob($proc.'/'.$pid.'/fd/*')?:[] as $fd){
   $path=@readlink($fd);if(!is_string($path)||!isset($targets[$path]))continue;
   clearstatcache(true,$fd);$st=@stat($fd);$target=$targets[$path];
   if(!$st||$st['ino']!==$target['ino']||$st['dev']!==$target['dev']||(string)$st['ino']!==$match[2])continue;
   $found[$pid]=['pid'=>$pid,'exe'=>$name,'lock'=>basename($path)];break;
  }
 }
 return $found;
}
function er_rollback_ok():bool {
 foreach(['building_facilities_common.php'=>'16b464764dc8eb20648a737a7c03158c6aabdb89fbb1a7b44b75c5d6a266c125','manager_facility_help.php'=>'77f850b81ab04c6dbd5a26ba164570311f2a4f3986c71969957ee58f9b5941bb'] as $file=>$hash){if(!is_file(__DIR__.'/'.$file)||!hash_equals($hash,(string)hash_file('sha256',__DIR__.'/'.$file)))return false;}
 return true;
}
function er_e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if(defined('ER_LOCAL_TEST'))return;
header('Cache-Control: no-store, private');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$password=is_string($_POST['password']??null)?$_POST['password']:'';
$authorized=$password!==''&&hash_equals('7fbf3334591ea593fe584b1f349edf79dce9bce4b006498ace726c6ea865b987',hash('sha256',$password));
$message='';$error='';$owners=[];$ready=false;
if($authorized){
 try{
  if(!er_rollback_ok())throw new RuntimeException('먼저 ZIP 안의 복원 파일 두 개를 이 PHP와 같은 폴더에 덮어쓰세요.');
  $ready=true;
  if(($_POST['action']??'')==='stop'){
   if(!function_exists('posix_kill'))throw new RuntimeException('호스팅에서 프로세스 종료 기능을 막았습니다. 카페24에 종료를 요청해야 합니다.');
   $pid=filter_var($_POST['pid']??'',FILTER_VALIDATE_INT);$current=er_owners(er_files());
   if(!$pid||!isset($current[$pid]))throw new RuntimeException('선택한 프로세스가 이미 종료됐거나 현재 사이트의 잠금을 확인할 수 없습니다. 종료하지 않았습니다.');
   if(!posix_kill($pid,15))throw new RuntimeException('종료 요청 권한이 없습니다. 카페24에 종료를 요청해야 합니다.');
   $message='프로세스 '.$pid.'에 종료 신호를 보냈습니다. 아래 확인 버튼을 다시 눌러 잠금이 해제됐는지 확인하세요.';
  }
  $owners=er_owners(er_files());
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'진단을 완료하지 못했습니다. 카페24에 확인을 요청해 주세요.';}
}elseif($password!==''){http_response_code(403);$error='복구 비밀번호가 맞지 않습니다.';}
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>사이트 잠금 긴급 복구</title><style>body{font:15px/1.7 system-ui;background:#f4f6f5;color:#253d31;margin:0}main{max-width:650px;margin:35px auto;padding:24px;background:white;border:1px solid #dfe6e1;border-radius:14px}input,button{font:inherit;padding:10px;border:1px solid #aabdb0;border-radius:7px}button{background:#28583f;color:white;cursor:pointer}input{max-width:90%}article{border-top:1px solid #dde6df;padding:18px 0}.error{color:#a33223}.note{color:#6b776e;font-size:13px}</style><main><h1>사이트 잠금 긴급 복구</h1><p>데이터와 잠금 파일을 삭제하지 않습니다. 이 사이트의 지정된 잠금 파일을 점유하고 있는 같은 계정의 PHP/웹 프로세스만 확인합니다.</p>
<?php if($error): ?><p class="error"><?=er_e($error)?></p><?php endif; ?><?php if($message): ?><p><?=er_e($message)?></p><?php endif; ?>
<?php if(!$authorized): ?><form method="post"><label>복구 비밀번호<br><input type="password" name="password" required autocomplete="off"></label> <button type="submit">잠금 상태 확인</button></form>
<?php else: ?>
<?php foreach($owners as $o): ?><article><strong>잠금을 점유한 프로세스 <?=er_e($o['pid'])?></strong><p><?=er_e($o['exe'])?> · <?=er_e($o['lock'])?></p><p class="note">이 프로세스의 진행 중인 요청이 중단됩니다. 해당 파일을 정상 작업이 사용 중인 경우에도 표시될 수 있으므로 사이트 이용을 멈춘 상태에서 실행하세요.</p><form method="post"><input type="hidden" name="password" value="<?=er_e($password)?>"><input type="hidden" name="action" value="stop"><input type="hidden" name="pid" value="<?=er_e($o['pid'])?>"><button type="submit">이 프로세스 종료 요청</button></form></article><?php endforeach; ?>
<?php if(!$error&&!$owners): ?><p>이 실행 환경에서 확인 가능한 잠금 점유 프로세스가 없습니다. 종료한 뒤라면 시크릿 창에서 로그인을 확인하세요. 여전히 안 되면 카페24의 확인이 필요합니다.</p><?php endif; ?>
<form method="post"><input type="hidden" name="password" value="<?=er_e($password)?>"><button type="submit">잠금 상태 다시 확인</button></form>
<?php endif; ?><p class="note">복구 후 이 긴급 복구 PHP 파일을 서버에서 삭제하세요. 이 페이지는 사이트 로그인 세션을 사용하지 않습니다.</p></main></html>
