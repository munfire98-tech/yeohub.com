<?php
declare(strict_types=1);
// Public fictional example: no account session, credentials, or member records are read.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: strict-origin-when-cross-origin');
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
if(isset($_GET['asset'])){
 $assets=['calendar'=>'manager_visit_calendar.js','map'=>'manager_map.js'];
 if(!is_string($_GET['asset'])||!isset($assets[$_GET['asset']])){http_response_code(404);exit;}
 header('Content-Type: text/javascript; charset=utf-8');
 $code=(string)file_get_contents(__DIR__.'/'.$assets[$_GET['asset']]);
 $code=str_replace("location.pathname.startsWith('/partner_demo_app.php/')","location.pathname==='/partner_center_example.php'",$code);
 $code=str_replace('demoRows=[];demoDays={};form.hidden=true;',"demoRows=(window.partnerExampleVisits||[]).map(row=>({...row}));demoSequence=100;demoDays={};form.hidden=true;",$code);
 $code=str_replace("if(act==='save_day'){", "if(act==='followup_preview'){const p=window.partnerExampleReportPayload(month);return reply({preview:Object.values(p.followups)[0]});}if(act.startsWith('report_')||act.startsWith('followup_'))return reply({ok:false,error:'예시 일정입니다. 실제 접수·공사 기록은 가입 후 관리할 수 있습니다.'});if(act==='save_day'){",$code);
 $code=str_replace(['manager-visit-map-view','manager-visit-guide-hidden-v1:','manager-map-view'],['public-example-visit-map-view','public-example-visit-guide:','public-example-map-view'],$code);
 $code=str_replace("visits:demoRows.filter(r=>r.date.startsWith(m)),days:","visits:demoRows.filter(r=>r.date.startsWith(m)),...window.partnerExampleReportPayload(m),days:",$code);
 echo $code;exit;
}

if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site'){http_response_code(403);exit;}
$key=$_COOKIE['partner_center_preview']??'';
if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key)){$key=bin2hex(random_bytes(16));setcookie('partner_center_preview',$key,['expires'=>time()+86400,'path'=>'/partner_center_example.php','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);}
$_COOKIE=[];$_GET=[];
define('PDA_PREFIX','/firedesk_example.php/');require_once __DIR__.'/partner_demo_runtime.php';
$manifest=json_decode(file_get_contents(__DIR__.'/partner_demo_sources.json'),true);
$manifest['sources']=array_values(array_unique(array_merge($manifest['sources'],['partner_center.php','manager_sidebar.php','manager_payout_common.php'])));
$runtime=pda_runtime(hash('sha256',"partner-public-real-v1\0".$key.'\0'.date('Y-m-d')),$manifest);
ini_set('session.use_cookies','0');ini_set('session.use_strict_mode','0');session_name('PARTNERPUBLICEXAMPLE');session_save_path($runtime.'/sessions');session_id(hash('sha256',$runtime));session_start();
$_SESSION=['is_user'=>true,'member_id'=>'demo_building','role'=>'building','nickname'=>'예시 파트너','csrf'=>bin2hex(random_bytes(24))];
if(ini_set('open_basedir',$runtime)===false){http_response_code(503);exit;}ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',$runtime.'/errors.log');date_default_timezone_set('Asia/Seoul');
define('MANAGER_FULL_DASHBOARD',true);define('PARTNER_DEMO_RUNTIME',true);
require $runtime.'/partner_demo_seed.php';pda_seed($runtime);
$_SESSION['member_id']='demo_partner';$_SESSION['role']='agency';
pda_store($runtime,'data/users/m_demo_partner/settings.json',['company'=>'예시소방안전','updated'=>date('Y-m-d H:i:s')]);
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com https://cdn.jsdelivr.net; img-src 'self' data: blob: https://tile.openstreetmap.org; connect-src https://tile.openstreetmap.org; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; frame-src 'self'; form-action 'none'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
ob_start(static function(string $out):string{
 $out=preg_replace('~<script\b[^>]*src=["\'][^"\']*(?:partner_demo|manager_navigation|visit_push|visit_badge|visit_inbox|work_notifications|partner_basemap|manager_invite|dapi\.kakao)[^"\']*["\'][^>]*>\s*</script>~i','',$out);
 $out=preg_replace('~src="/manager_visit_calendar\.js[^"\']*"~','src="/partner_center_example.php?asset=calendar"',$out);
 $out=preg_replace('~src="/manager_map\.js[^"\']*"~','src="/partner_center_example.php?asset=map"',$out);
 $out=preg_replace('~(<head\b[^>]*>)~i','$1<link rel="stylesheet" href="/partner_center_example.css?v=3"><script src="/partner_center_example.js?v=3"></script>',$out,1);
 $out=preg_replace('~<body\b~i','<body data-tour-result-page',$out,1);
 return $out;
});
$_SERVER['SCRIPT_FILENAME']=$runtime.'/partner_center.php';$_SERVER['SCRIPT_NAME']='/partner_center_example.php';$_SERVER['PHP_SELF']='/partner_center_example.php';chdir($runtime);require $runtime.'/partner_center.php';
