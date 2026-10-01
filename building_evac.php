<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/building_evac_common.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');
$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
try{
 if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true))throw new RuntimeException('지원하지 않는 요청입니다.',405);
 $uid=$_GET['uid']??mg_uid();if(!is_string($uid))throw new RuntimeException('건물을 확인해 주세요.',400);
 $member=bes_authorize($uid);$path=bes_path($uid,$member);
 if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
 $csrf=(string)$_SESSION['csrf'];$actor=mg_uid()?:'admin';
 require_once __DIR__.'/building_evac_library.php';
 $doc=$_GET['doc']??'';
 if(!is_string($doc)||($doc!==''&&!preg_match('/^[a-f0-9]{24}$/D',$doc)))throw new RuntimeException('도면을 확인해 주세요.',400);
 if($doc===''){bes_library_page($path,$uid,$csrf,$actor,$post);exit;}
 $libraryPath=$path;
 $library=mg_read($libraryPath);$entry=$library['documents'][$doc]??null;
 if(!$entry)throw new RuntimeException('도면이 삭제되었거나 사용할 수 없습니다.',409);
 $path=bes_document_path($libraryPath,$doc);

 if($post){
  if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 창을 다시 열어 주세요.',403);
  session_write_close();$saved=mg_tx($libraryPath,function(array &$library)use($doc,$path,$actor){
   if(!isset($library['documents'][$doc]))throw new RuntimeException('삭제된 도면입니다. 보관함을 다시 열어 주세요.',409);
   return mg_tx($path,fn(array &$s)=>bes_save($s,$_POST,$actor),true);
  },true);
  header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'revision'=>$saved['revision'],'saved'=>date('H:i')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
 }
 $saved=mg_read($path);$info=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
 $map=(string)($saved['map']??'');$scenario=$saved['scenario']??[];
 if($map===''&&!empty($entry['legacy'])){
  require_once __DIR__.'/evac_common.php';$assigned=evac_models_for($uid);
  if($assigned){$source=evac_load_model($assigned[0]['id']);$map=(string)($source['map']??'');$scenario=$source['scenario']??[];}
 }
 $EVAC_BUILDING_OPTIONS=bes_building_options($info);
 $EVAC_HOST=true;$EVAC_EMBED=false;$EVAC_AUTO=false;$EVAC_NO_SESSION=true;
 $EVAC_MAP=$map;$EVAC_NAME=(string)$entry['name'];
 $EVAC_SCENARIO=json_encode($scenario,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 $EVAC_SAVE_URL='/building_evac.php?uid='.rawurlencode($uid).'&doc='.rawurlencode($doc);
 $EVAC_HOST_REVISION=(int)($saved['revision']??0);
 session_write_close();include __DIR__.'/fire_evac_sim.php';
}catch(Throwable $e){
 $code=$e instanceof RuntimeException&&in_array($e->getCode(),[400,401,403,405,409],true)?$e->getCode():503;
 http_response_code($code);$message=$code===503?'시뮬레이션 정보를 읽지 못했습니다. 잠시 후 다시 열어 주세요.':$e->getMessage();
 if($post){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);}
 else{header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font:16px/1.8 system-ui;background:#f4f7fb;padding:32px;color:#34465e"><h2>피난 시뮬레이션</h2><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p></body></html>';}
}
