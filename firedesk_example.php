<?php
/** Authenticated, isolated runtime for the real building screens. */
declare(strict_types=1);
// Public preview owns a separate cookie and session; it never opens the login session.
define('PDA_PREFIX','/firedesk_example.php/');
require_once __DIR__.'/partner_demo_runtime.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD'],true)){header('Allow: GET, HEAD');pda_unavailable('예시 화면에서는 저장하지 않습니다. 실제 건물 등록 후 작성할 수 있어요.',405);}
if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')pda_unavailable();
$previewKey=$_COOKIE['firedesk_preview']??'';
if(!is_string($previewKey)||!preg_match('/^[a-f0-9]{32}$/D',$previewKey)){
    $previewKey=bin2hex(random_bytes(16));
    setcookie('firedesk_preview',$previewKey,['expires'=>time()+86400,'path'=>'/firedesk_example.php','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
}
$loginSession=$previewKey;$scope=hash('sha256',"public-preview-v1\0".$previewKey."\0".gmdate('Y-m-d'));
$allowedQuery=['from_book','uid','work_page','plan_year','setup_locations','asset_view','print','workspace','stay','plan_token','year','y','month','m','id','modal','embed','dong','tab','view','print_view','dashboard_revision','v','section','page','preview'];
foreach($_GET as $key=>$value)if(!in_array($key,$allowedQuery,true)||!is_string($value)||!preg_match('/^[A-Za-z0-9_-]{0,100}$/D',$value))pda_unavailable('예시 화면에서 지원하지 않는 요청입니다.');
$route=ltrim((string)($_SERVER['PATH_INFO']??''),'/');if($route===''||$route==='building_manager.php')$route='FireDesk.php';
if(!preg_match('/^[A-Za-z0-9_-]+\.(?:php|js|css)$/D',$route))pda_unavailable();
$manifest=json_decode((string)file_get_contents(__DIR__.'/partner_demo_sources.json'),true,512,JSON_THROW_ON_ERROR);
$manifest['routes']=['manager_help.php','FireDesk.php','building_setup.php','building_setup_chat.php','building_facilities.php','building_multi_use.php','building_asset_view.php','fire_plan.php','fire_plan_book.php','fire_plan_edit.php','fire_plan_chat.php','fire_plan_jawi.php','fire_plan_print.php','fire_plan_schedule.php','jawi.php','jawi_edit.php','jawi_chat.php','jawi_print.php','train.php','train_edit.php','train_chat.php','train_print.php','work_log.php','work_log_form.php','work_log_print.php','evacuation_plan.php','evacuation_plan_chat.php','fire_manager_board.php'];
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
ini_set('session.use_cookies','0');ini_set('session.use_strict_mode','0');session_name('FIREDESKPREVIEW');session_save_path($runtime.'/sessions');session_id(hash('sha256',$loginSession."\0".$scope."\0".$demoIdentity));session_start();
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
// Complete the fictional example's access route, without touching any member data.
if(!is_file($runtime.'/.public-example-ready')){
    $exampleInfo=json_decode((string)file_get_contents($runtime.'/data/building/demo_building/info.json'),true,512,JSON_THROW_ON_ERROR);
    $exampleInfo['fire_engine_route']=json_encode([['lat'=>37.75965,'lng'=>126.7799],['lat'=>37.75945,'lng'=>126.77965],['lat'=>37.7591,'lng'=>126.7794]]);
    $exampleInfo['fire_engine_route_note']='체험용 경로 · 정문과 후문, 출입구 2개';
    pda_store($runtime,'data/building/demo_building/info.json',$exampleInfo);
    file_put_contents($runtime.'/.public-example-ready','1',LOCK_EX);
}
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
    if(stripos($out,'<head')!==false){$base=htmlspecialchars((string)($_SERVER['REQUEST_URI']??PDA_PREFIX.'FireDesk.php'),ENT_QUOTES,'UTF-8');$out=preg_replace_callback('/(<head\b[^>]*>)/i',static fn($m)=>$m[1].'<base href="'.$base.'"><script src="'.PDA_PREFIX.'home_firedesk_client.js?v=1"></script>',$out,1);}
    if(($_GET['preview']??'')==='1'&&str_contains($out,'<body')){
        $notice='<style>.fp-example-notice{position:sticky;top:0;z-index:90;padding:12px 20px;text-align:center;background:#eff6ff;border-bottom:1px solid #d5e3fa;color:#294a78;font:600 13px/1.6 system-ui}.fp-example-notice small{font-weight:400;margin-left:8px}@media print{.fp-example-notice{display:none}}</style><aside class="fp-example-notice" role="note">예시 소방계획서<small>가상 건물 자료로 구성한 출력 미리보기입니다.</small></aside>';
        $out=preg_replace_callback('/(<body\b[^>]*>)/i',static fn($m)=>$m[1].$notice,$out,1);
    }
    return $out;
});
$_SERVER['SCRIPT_FILENAME']=$runtime.'/'.$route;$_SERVER['SCRIPT_NAME']=PDA_PREFIX.$route;$_SERVER['PHP_SELF']=PDA_PREFIX.$route;
chdir($runtime);session_write_close();require $runtime.'/'.$route;
