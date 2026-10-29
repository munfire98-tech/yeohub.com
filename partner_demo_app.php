<?php
/** Authenticated, isolated runtime for the real building screens. */
declare(strict_types=1);
require_once __DIR__.'/partner_demo_runtime.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$actor=(string)($_SESSION['member_id']??'');
if($actor===''&&!empty($_SESSION['kakao_id']))$actor='kakao_'.$_SESSION['kakao_id'];
$members=json_decode((string)file_get_contents(__DIR__.'/data/members.json'),true);$member=$members[$actor]??[];
if(empty($_SESSION['is_user'])||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$actor)||($member['role']??'')!=='agency'||($member['status']??'active')!=='active')pda_unavailable('파트너로 로그인한 후 예시 둘러보기를 열어 주세요.',403);
date_default_timezone_set('Asia/Seoul');
$loginSession=session_id();$scope=hash('sha256',$actor."\0".($member['created']??'')."\0".date('Y-m-d')."\0walkthrough-v2");
unset($members,$member,$actor);session_write_close();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD','POST'],true)||!empty($_FILES)||(int)($_SERVER['CONTENT_LENGTH']??0)>1200000)pda_unavailable('체험에서 지원하지 않는 요청입니다.',405);
if(in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'',['cross-site'],true))pda_unavailable('같은 사이트에서 체험을 열어 주세요.');
$route=ltrim((string)($_SERVER['PATH_INFO']??''),'/');if($route===''||$route==='building_manager.php')$route='FireDesk.php';
if(!preg_match('/^[A-Za-z0-9_-]+\.(?:php|js|css)$/D',$route))pda_unavailable();
$manifest=json_decode((string)file_get_contents(__DIR__.'/partner_demo_sources.json'),true,512,JSON_THROW_ON_ERROR);
$static=preg_match('/\.(js|css)$/D',$route,$ext);
if($static){
    if($method!=='GET'&&$method!=='HEAD')pda_unavailable();
    $file=__DIR__.'/'.$route;if(!is_file($file)||is_link($file))pda_unavailable('체험 파일을 찾지 못했습니다.',404);
    header('Content-Type: '.($ext[1]==='js'?'text/javascript':'text/css').'; charset=utf-8');echo pda_rewrite((string)file_get_contents($file));exit;
}
if(!in_array($route,$manifest['routes'],true)){
    if(str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')||$method==='POST'){
        http_response_code(403);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'체험에서는 결제·공유·외부 요청을 실행하지 않습니다.'],JSON_UNESCAPED_UNICODE);exit;
    }
    pda_unavailable();
}
try{$runtime=pda_runtime($scope,$manifest);}catch(Throwable $e){error_log('Partner demo preparation failed: '.$e->getMessage());pda_unavailable('체험 화면을 준비하지 못했습니다. 잠시 후 다시 열어 주세요.',503);}
$prereg=in_array($route,['manager_draft.php','partner_walkthrough.php'],true);
$demoIdentity=($route==='manager_help.php'||$prereg)?'demo_partner':'demo_building';
// Never reuse or replace the partner's production session or cookie.
ini_set('session.use_cookies','0');ini_set('session.use_strict_mode','0');session_name('PARTNERDEMO');session_save_path($runtime.'/sessions');session_id(hash('sha256',$loginSession."\0".$scope."\0".$demoIdentity));session_start();
if(($_SESSION['member_id']??'')!==$demoIdentity)$_SESSION=['is_user'=>true,'member_id'=>$demoIdentity,'role'=>$demoIdentity==='demo_partner'?'agency':'building','nickname'=>$demoIdentity==='demo_partner'?'체험 파트너':'소방계획서.com 빌딩','_mge_actor'=>'demo_partner','csrf'=>bin2hex(random_bytes(24))];
if($prereg)unset($_SESSION['_mge_actor'],$_SESSION['_imp']);
foreach(['fp_csrf','jw_csrf','tr_csrf'] as $key)if(empty($_SESSION[$key]))$_SESSION[$key]=$_SESSION['csrf'];
if($method==='POST'){
    $input=$_POST;
    if(str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json'))$input=json_decode((string)file_get_contents('php://input'),true)?:[];
    $token=$input['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'';
    $valid=false;foreach(['csrf','fp_csrf','jw_csrf','tr_csrf'] as $key)if(is_string($token)&&$token!==''&&hash_equals((string)($_SESSION[$key]??''),$token))$valid=true;
    if(!$valid)pda_unavailable('체험 요청이 만료되었습니다. 예시 둘러보기를 다시 열어 주세요.');
}
// All actual screen code and data stay in this private directory. Even a crafted
// record path cannot reach production data. No live credentials are copied.
if(ini_set('open_basedir',$runtime)===false)pda_unavailable('체험 저장소 격리를 준비하지 못했습니다.',503);
ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',$runtime.'/errors.log');
date_default_timezone_set('Asia/Seoul');
define('MANAGER_FULL_DASHBOARD',true);
define('PARTNER_DEMO_RUNTIME',true);
require $runtime.'/partner_demo_seed.php';
$demoSession=$_SESSION;
try{$_SESSION['member_id']='demo_building';$_SESSION['role']='building';pda_seed($runtime);}catch(Throwable $e){error_log('Demo seed failed: '.$e->getMessage());pda_unavailable('체험 자료를 준비하지 못했습니다. 잠시 후 다시 열어 주세요.',503);}finally{$_SESSION=$demoSession;unset($demoSession);}
if($route==='manager_draft.php'&&empty($_GET['id'])){
    require_once $runtime.'/manager_addresses_common.php';
    $id=ma_begin('demo_partner',mg_members());session_write_close();
    header('Location: '.PDA_PREFIX.'manager_draft.php?id='.$id.(!empty($_GET['autoplay'])?'&autoplay=1':'').(!empty($_GET['pick'])?'&pick=1':''));exit;
}
$host=(string)($_SERVER['HTTP_HOST']??'');if(!preg_match('/^[A-Za-z0-9.:-]+$/D',$host))pda_unavailable();
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'?'https':'http';$origin=$scheme.'://'.$host;
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline' ".$origin.PDA_PREFIX." https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'unsafe-inline' 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com; img-src 'self' data: blob: https://tile.openstreetmap.org; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src ".$origin.PDA_PREFIX."; frame-src ".$origin.PDA_PREFIX."; form-action ".$origin.PDA_PREFIX."; media-src 'self' blob:; base-uri 'self'; object-src 'none'");
header_register_callback(static function():void{
    foreach(headers_list() as $header)if(stripos($header,'Location:')===0){$url=trim(substr($header,9));if(str_starts_with($url,PDA_PREFIX))return;$parts=parse_url($url);$path=$parts['path']??'';header('Location: '.PDA_PREFIX.basename($path).(isset($parts['query'])?'?'.$parts['query']:''),true);}
});
ob_start(static function(string $out):string{
    $out=pda_rewrite($out);
    if(stripos($out,'<head')!==false){$base=htmlspecialchars((string)($_SERVER['REQUEST_URI']??PDA_PREFIX.'FireDesk.php'),ENT_QUOTES,'UTF-8');$out=preg_replace_callback('/(<head\b[^>]*>)/i',static fn($m)=>$m[1].'<base href="'.$base.'"><script src="'.PDA_PREFIX.'partner_demo_client.js?v=1"></script>',$out,1);}
    return $out;
});
$_SERVER['SCRIPT_FILENAME']=$runtime.'/'.$route;$_SERVER['SCRIPT_NAME']=PDA_PREFIX.$route;$_SERVER['PHP_SELF']=PDA_PREFIX.$route;
chdir($runtime);session_write_close();require $runtime.'/'.$route;
