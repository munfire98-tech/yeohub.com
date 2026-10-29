<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
if(session_status()!==PHP_SESSION_ACTIVE) alp_session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function epl_admin():bool{return !empty($_SESSION['is_admin'])||($_SESSION['ID_OK']??0)==1;}
if(!epl_admin()&&empty($_SESSION['is_user'])){header('Location: /index.php');exit;}
if(!epl_admin()&&($_SESSION['role']??'agency')!=='building'){header('Location: /partner_center.php');exit;}
require_once __DIR__.'/building_info.php';
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/evacuation_plan_list_common.php';
$building=bi_load();$key=app_user_key();$bi=bi_file();$file=$bi===''?'':dirname($bi).'/evacuation_plan.json';
$readOnly=defined('MANAGER_VIEW_UID');
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));
$csrf=(string)$_SESSION['csrf'];
$context=[];
if(epl_admin()&&isset($_GET['uid'])&&$key!=='')$context['uid']=$key;
foreach(['modal','embed']as$param)if(($_GET[$param]??'')==='1')$context[$param]='1';
$returnUrl='';$returnCandidate=$_GET['return']??'';
if(is_string($returnCandidate)&&str_starts_with($returnCandidate,'/fire_plan_edit.php?')&&!str_contains($returnCandidate,'\\')&&!preg_match('/[\r\n]/',$returnCandidate)&&parse_url($returnCandidate,PHP_URL_PATH)==='/fire_plan_edit.php'){
    parse_str((string)parse_url($returnCandidate,PHP_URL_QUERY),$returnParts);
    $safeReturn=['id'=>(string)($returnParts['id']??''),'s'=>'5'];
    if(isset($context['uid']))$safeReturn['uid']=$context['uid'];
    $returnUrl='/fire_plan_edit.php?'.http_build_query($safeReturn);$context['return']=$returnUrl;
}
$url=static function(array $query=[])use($context):string{$q=array_merge($context,$query);return '/evacuation_plan.php'.($q?'?'.http_build_query($q):'');};
function epl_public_rows(array $store):array{
    $out=[];foreach($store['_epl_plans']as$id=>$row){unset($row['history'],$row['last_request']);$out[$id]=$row;}return $out;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    header('Content-Type: application/json; charset=utf-8');
    try{
        if($readOnly||$file===''||$key===''){http_response_code(403);throw new LogicException('이 화면에서는 피난계획을 저장할 수 없습니다.');}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>600000){http_response_code(413);throw new LogicException('입력 내용을 줄인 뒤 다시 저장해 주세요.');}
        $input=json_decode((string)file_get_contents('php://input'),true);
        if(!is_array($input))throw new InvalidArgumentException('저장 요청을 확인해 주세요.');
        if(!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf'])){http_response_code(403);throw new LogicException('로그인 시간이 만료되었습니다. 입력 내용을 복사해 두고 다시 로그인해 주세요.');}
        $saved=epl_save($file,$building,$input);
        unset($_SESSION['evacuation_plan_cache'][$key]);
        $store=epl_read($file,$building);
        echo json_encode(['ok'=>true,'id'=>$saved['id'],'plans'=>epl_public_rows($store)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(DomainException $e){http_response_code(409);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }catch(LogicException $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }catch(Throwable $e){http_response_code(500);error_log('Evacuation plan save failed: '.$e->getMessage());echo json_encode(['ok'=>false,'error'=>'저장하지 못했습니다. 입력 내용은 화면에 남아 있습니다. 잠시 후 다시 저장해 주세요.'],JSON_UNESCAPED_UNICODE);}
    exit;
}
$error='';$store=['_epl_plans'=>[]];
try{if($file==='')throw new RuntimeException('건물 정보를 확인할 수 없습니다. 다시 로그인해 주세요.');$store=epl_read($file,$building);}catch(Throwable $e){$error='저장된 피난계획을 읽지 못했습니다. 기존 자료를 보호하기 위해 작성을 잠시 중단합니다. 관리자에게 문의해 주세요.';http_response_code(500);}
$printing=($_GET['print']??'')==='1';
if($printing){
    $wanted=(string)($_GET['id']??'');
    $rows=array_filter($store['_epl_plans'],static fn($r)=>empty($r['deleted_at'])&&!empty($r['published'])&&($wanted!==''?$r['id']===$wanted:empty($r['archived'])));
    if(!$rows&&!$error)http_response_code(404);
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>피난계획 인쇄</title><link rel="stylesheet" href="/evacuation_plan.css?v=20261015-floor-area-1"><link rel="stylesheet" href="/evacuation_plan_document.css?v=20261021-kakao-1"><?php require __DIR__.'/evacuation_location_assets.php'; ?></head><body class="epl-print"><div class="epl-print-tools"><a class="btn" href="<?=h($url())?>">← 계획 목록</a><button class="btn primary" onclick="window.eplPrintLocations ? window.eplPrintLocations() : window.print()">인쇄 · PDF 저장</button></div>
    <?php if(!$rows):?><main class="epl-document"><h1>인쇄할 사용 계획이 없습니다</h1><p><?=h($error?:'작성 중인 계획은 마지막 확인을 거쳐 사용 계획으로 저장해 주세요.')?></p></main><?php endif;?>
    <?php define('EPL_DOCUMENT_AUTHORIZED',true);require __DIR__.'/evacuation_plan_document.php'; ?></body></html><?php exit;
}
require_once __DIR__.'/evacuation_plan_prefill.php';
$rosters=[];
if(preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$key)){
    $rosterFile=__DIR__.'/data/fireplan/'.$key.'/_jawi.json';
    if(is_file($rosterFile)){
        $decoded=json_decode((string)file_get_contents($rosterFile),true);
        if(is_array($decoded))$rosters=$decoded;
    }
}
$savedContext=epl_saved_context($building,$rosters);
$prefill=array_map(static fn($field)=>$field['value'],$savedContext['fields']);
require_once __DIR__.'/building_floor_common.php';$floorReference=[];foreach(bfl_entries($building) as $entry)$floorReference[$entry['evac_id']]=bfl_effective($building,$entry);
$locationHtml=epl_location_html(epl_location_snapshot($building));$locationByPlan=[];
foreach($store['_epl_plans'] as $locationId=>$locationRow){if(!empty($locationRow['published'])){$savedHtml=epl_location_html(epl_plan_locations($locationRow['published'],$building));if($savedHtml!==$locationHtml)$locationByPlan[$locationId]=$savedHtml;}}
$boot=['locationHtml'=>$locationHtml,'locationByPlan'=>$locationByPlan,'floorReference'=>$floorReference,'csrf'=>$csrf,'endpoint'=>$url(),'printUrl'=>$url(['print'=>'1']),'plans'=>epl_public_rows($store),'scopes'=>array_values(epl_scopes($building)),'building'=>['name'=>$building['name']??'내 건물','address'=>$building['address']??''],'prefill'=>$prefill,'savedContext'=>$savedContext['fields'],'staff'=>$savedContext['staff'],'readOnly'=>$readOnly,'returnUrl'=>$returnUrl,'error'=>$error];
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>피난계획</title><link rel="stylesheet" href="/evacuation_plan.css?v=20261015-floor-area-1"><link rel="stylesheet" href="/evacuation_plan_document.css?v=20261021-kakao-1"><?php require __DIR__.'/evacuation_location_assets.php'; ?></head><body>
<main class="epl-app" id="eplApp"><noscript>피난계획을 사용하려면 브라우저의 JavaScript를 켜 주세요.</noscript></main>
<div class="epl-toast" id="eplToast" role="status" aria-live="polite"></div>
<script type="application/json" id="eplBoot"><?=json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<script src="/evacuation_floor_groups.js?v=20261007-card-viewport-1" defer></script>
<script src="/evacuation_plan.js?v=20261021-locations-1" defer></script></body></html>
