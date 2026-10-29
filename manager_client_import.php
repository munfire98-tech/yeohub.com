<?php
declare(strict_types=1);
require_once __DIR__.'/login_persistence.php';if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/manager_client_import_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
$actor=mg_uid();$members=mg_members();$admin=ci_admin();
if(!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor'])||defined('PARTNER_DEMO_RUNTIME')||(!$admin&&!mg_active($members[$actor]??[],'agency'))){http_response_code(403);exit('업체 계정으로 로그인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
function ca_access(array $r,string $actor,array $members,bool $admin):bool{return ($r['mode']??'')==='auto'&&($admin||ci_owned($r,$actor,$members));}
function ca_public(array $r):array {
 $rows=[];foreach($r['rows'] as $row){$candidates=[];foreach($row['candidates']??[] as $c)$candidates[]=['key'=>$c['key'],'name'=>$c['name'],'address'=>$c['address']];$rows[]=['id'=>$row['id'],'name'=>$row['name'],'address'=>$row['address'],'address_id'=>$row['address_id'],'result'=>$row['result'],'status'=>$row['lookup_status']??'pending','kind'=>ca_review_kind($row),'needs_enrichment'=>!empty($row['resolved'])&&in_array($row['resolved']['registry_status']??'',['found','multiple'],true)&&(int)($row['resolved']['registry_version']??0)<2,'retryable'=>isset($row['resolved'])?(($row['resolved']['floor_status']??'')==='unavailable'||($row['resolved']['registry_retryable']??false)):($row['retryable']??true),'resolved_address'=>$row['resolved']['address']??'','note'=>$row['resolved']['note']??'','candidates'=>$candidates];}
 return ['revision'=>$r['revision'],'id'=>$r['id'],'rows'=>$rows,'mode'=>$r['mode'],'ai_used'=>!empty($r['ai_used'])];
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 header('Content-Type: application/json; charset=utf-8');
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 새로고침해 주세요.');
  $action=(string)($_POST['action']??'');$id=(string)($_POST['id']??'');$rowId=(string)($_POST['row']??'');
  if($action==='prepare'){
   if($admin)throw new RuntimeException('업체 계정에서 목록을 시작해 주세요.');
   $token=(string)($_POST['token']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('요청을 다시 시작해 주세요.');
   // A repeated click returns its existing job without another AI/API charge.
   foreach(mg_read(ci_file()) as $r)if(ca_access($r,$actor,$members,false)&&($r['token']??'')===$token){echo json_encode(['ok'=>true,'job'=>ca_public($r)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
   $last=(int)($_SESSION['client_import_prepare_at']??0);if($last>time()-10)throw new RuntimeException('목록을 처리 중입니다. 잠시 후 다시 시도해 주세요.');$_SESSION['client_import_prepare_at']=time();
   $file=ci_attachment($_FILES['list_file']??[]);$text=trim((string)($_POST['text']??''));$aiUsed=false;
   if($text===''&&$file){if($file['ext']==='xlsx')$rows=ca_xlsx(base64_decode($file['data'],true));elseif($file['ext']==='xls')throw new RuntimeException('구형 XLS는 XLSX로 저장하거나 거래처명·주소를 복사해서 붙여넣어 주세요.');else $text=ci_decode(base64_decode($file['data'],true));}
   if(!isset($rows)){
    try{$rows=ca_parse_text($text);}catch(RuntimeException $e){if(!ca_ai_available()||strlen($text)>30000)throw $e;ca_ai_budget($actor,(string)($members[$actor]['created']??''));$rows=ca_ai_parse($text);$aiUsed=true;}
   }
   $newId=mg_member_tx(function(array &$m)use($actor,$rows,$token,$aiUsed){
    if(!mg_active($m[$actor]??[],'agency'))throw new RuntimeException('업체 계정 상태를 확인해 주세요.');
    return mg_tx(ci_file(),function(array &$store)use($actor,$m,$rows,$token,$aiUsed){$open=0;foreach($store as $r)if(ci_owned($r,$actor,$m)){if(($r['token']??'')===$token)return $r['id'];if(ci_status($r)!=='등록 완료')$open++;}if($open>=10)throw new RuntimeException('진행 중인 목록이 10개입니다. 기존 목록에서 이어서 진행해 주세요.');$new=bin2hex(random_bytes(12));$store[$new]=['id'=>$new,'token'=>$token,'mode'=>'auto','manager'=>$actor,'manager_created'=>(string)($m[$actor]['created']??''),'created_at'=>date('c'),'revision'=>1,'rows'=>$rows,'ai_used'=>$aiUsed,'attachment_name'=>'','note'=>'자동 주소 조회·등록'];return $new;},true);
   });$r=mg_read(ci_file())[$newId];$result=['job'=>ca_public($r)];
  }else{
   if(!preg_match('/^[a-f0-9]{24}$/D',$id))throw new RuntimeException('등록 목록을 확인해 주세요.');$r=mg_read(ci_file())[$id]??[];if(!ca_access($r,$actor,$members,$admin))throw new RuntimeException('이 목록을 처리할 수 없습니다.');
   if($action==='fill'){
    $index=array_search($rowId,array_column($r['rows'],'id'),true);if($index===false)throw new RuntimeException('목록 항목을 찾지 못했습니다.');$row=$r['rows'][$index];$addressId=(string)$row['address_id'];$entry=mg_read(mg_state_file())['address_book'][$addressId]??[];
    if($addressId===''||!ma_owned($entry,$r['manager'],$members)||($entry['intake_id']??'')!==$id||($entry['intake_row']??'')!==$rowId||ma_linked($entry,$r['manager'],$members,mg_read(mg_state_file())))throw new RuntimeException('연결되었거나 변경된 건물은 해당 기본정보 화면에서 조회해 주세요.');
    $before=hash('sha256',json_encode($entry));$old=$row['resolved']??[];if(!$old||mdd_key((string)($entry['address']??''))!==mdd_key((string)($old['address']??'')))throw new RuntimeException('주소가 변경되었습니다. 기본정보에서 주소를 다시 조회해 주세요.');$candidate=array_intersect_key($old,array_flip(['key','name','address','road','jibun','codes']));session_write_close();set_time_limit(90);$api=require __DIR__.'/api_keys.php';$resolved=ca_enrich($candidate,$api);
    if(!in_array($resolved['registry_status'],['found','multiple'],true)||($resolved['floor_status']??'')!=='ready')throw new RuntimeException('층별 정보를 아직 확인하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    mg_member_tx(function(array &$m)use($actor,$admin,$id,$rowId,$addressId,$before,$resolved){mg_tx(ci_file(),function(array &$store)use($m,$actor,$admin,$id,$rowId,$addressId,$before,$resolved){$r=&$store[$id];if(!is_array($r)||!ca_access($r,$actor,$m,$admin)||!ci_owned($r,$r['manager'],$m))throw new RuntimeException('계정 상태가 변경되었습니다.');mg_state_tx(function(array &$s)use(&$r,$m,$rowId,$addressId,$before,$resolved){$entry=$s['address_book'][$addressId]??[];if(!ma_owned($entry,$r['manager'],$m)||ma_linked($entry,$r['manager'],$m,$s)||!hash_equals($before,hash('sha256',json_encode($entry))))throw new RuntimeException('건물정보가 변경되었습니다. 다시 확인해 주세요.');$s['address_book'][$addressId]['basic_info']=ca_merge_details((array)($entry['basic_info']??[]),$resolved['patch']);$s['address_book'][$addressId]['registry_details_version']=2;foreach($r['rows'] as &$v)if($v['id']===$rowId){$v['resolved']=$resolved;break;}unset($v);});$r['revision']++;$r['updated_at']=date('c');},true);});$result=['job'=>ca_public(mg_read(ci_file())[$id])];
   }elseif($action==='lookup'||$action==='choose'||$action==='retry'||$action==='enrich'){
    $index=array_search($rowId,array_column($r['rows'],'id'),true);if($index===false)throw new RuntimeException('목록 항목을 찾지 못했습니다.');$row=$r['rows'][$index];if($row['address_id']!=='')throw new RuntimeException('이미 등록한 항목입니다.');
    $name=ci_text((string)($_POST['name']??$row['name']),150);$address=ci_text((string)($_POST['address']??$row['address']));$signature=hash('sha256',json_encode($row));
    // Release PHP session lock during official API calls; persist only if the row is unchanged.
    session_write_close();set_time_limit(90);$api=require __DIR__.'/api_keys.php';
    if(in_array($action,['retry','enrich'],true)&&!empty($row['resolved'])){$chosen=array_intersect_key($row['resolved'],array_flip(['key','name','address','road','jibun','codes']));$address=$row['address'];$name=$row['name'];$search=['candidates'=>$row['candidates']??[],'match'=>$chosen];}
    elseif($action==='choose'){$chosen=null;foreach($row['candidates']??[] as $c)if(hash_equals($c['key'],(string)($_POST['candidate']??'')))$chosen=$c;if(!$chosen)throw new RuntimeException('주소를 다시 조회해 주세요.');$address=$row['address'];$name=$row['name'];$search=['candidates'=>$row['candidates'],'match'=>$chosen];}
    else{try{$search=ca_search($address,$api);}catch(RuntimeException $e){$search=['candidates'=>[],'match'=>null,'error'=>$e->getMessage(),'retryable'=>$e instanceof CaTemporaryException];}}
    $resolved=$search['match']?ca_enrich($search['match'],$api):null;
    mg_member_tx(function(array &$m)use($actor,$admin,$id,$rowId,$name,$address,$signature,$search,$resolved){mg_tx(ci_file(),function(array &$store)use($m,$actor,$admin,$id,$rowId,$name,$address,$signature,$search,$resolved){$r=&$store[$id];if(!is_array($r)||!ca_access($r,$actor,$m,$admin)||!ci_owned($r,$r['manager'],$m))throw new RuntimeException('계정 또는 목록 상태가 변경되었습니다.');foreach($r['rows'] as &$row)if($row['id']===$rowId){if(!hash_equals($signature,hash('sha256',json_encode($row))))throw new RuntimeException('항목이 변경되었습니다. 목록을 새로고침해 주세요.');$row['name']=$name;$row['address']=$address;$row['candidates']=$search['candidates'];$row['retryable']=$search['retryable']??false;unset($row['resolved']);$row['lookup_status']=$resolved?'ready':(isset($search['error'])?'error':(empty($search['candidates'])?'not_found':'choose'));$row['result']=$search['error']??($resolved?'주소 확인 완료':(empty($search['candidates'])?'주소를 찾지 못했습니다. 주소를 수정해 주세요.':'여러 주소가 검색됐습니다. 실제 거래처 주소를 골라 주세요.'));if($resolved){$row['resolved']=$resolved;if($row['name']==='')$row['name']=$resolved['name']?:$resolved['address'];}break;}$r['revision']++;$r['updated_at']=date('c');},true);});
    $result=['job'=>ca_public(mg_read(ci_file())[$id])];
   }elseif($action==='register'){
    $selected=json_decode((string)($_POST['selected']??'[]'),true);if(!is_array($selected)||!$selected||count($selected)>600)throw new RuntimeException('등록 가능한 항목을 선택해 주세요.');
    mg_member_tx(function(array &$m)use($actor,$admin,$id,$selected){mg_tx(ci_file(),function(array &$store)use($m,$actor,$admin,$id,$selected){$r=&$store[$id];if(!is_array($r)||!ca_access($r,$actor,$m,$admin))throw new RuntimeException('목록을 처리할 수 없습니다.');if((int)($_POST['revision']??0)!==$r['revision'])throw new RuntimeException('다른 창에서 변경되었습니다. 이 목록을 새로 열어 주세요.');mg_state_tx(function(array &$s)use(&$r,$m,$selected){ca_register($r,$s,$m,$selected);});$r['revision']++;$r['updated_at']=date('c');},true);});$result=['job'=>ca_public(mg_read(ci_file())[$id])];
   }else throw new RuntimeException('지원하지 않는 요청입니다.');
  }
  echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 }catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}exit;
}
$id=(string)($_GET['id']??'');$job=null;$history=[];foreach(mg_read(ci_file()) as $r)if(ca_access($r,$actor,$members,$admin)){$history[]=$r;if($r['id']===$id)$job=ca_public($r);}if($id!==''&&!$job){http_response_code(404);exit('등록 목록을 찾을 수 없습니다.');}
usort($history,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>거래처 한 번에 등록 · 소방계획서.com</title><link rel="stylesheet" href="/manager_client_intake.css?v=20261028-1"><link rel="stylesheet" href="/manager_client_import.css?v=20261028-review-groups-1"><script src="/manager_client_import.js?v=20261029-floor-details-1" defer></script></head><body><main class="auto-page" data-csrf="<?=mg_e($csrf)?>" data-token="<?=bin2hex(random_bytes(16))?>">
<header><span class="eyebrow">파트너센터 · 거래처 가져오기</span><h1>거래처, 한 번에 등록하세요.</h1><p>주소 목록만 넣으면 조회하고 정리합니다.<br>확인된 거래처는 한 번에 등록하고, 애매한 주소만 골라주세요.</p><a href="/partner_center.php" target="_top">← 파트너센터</a></header>
<div class="import-steps"><span>1 목록 넣기</span><i>→</i><span>2 자동 조회</span><i>→</i><span>3 한 번에 등록</span></div>
<p id="auto-status" class="notice" role="status" hidden></p>
<?php if(!$admin&&!$job):?><section id="import-input"><form id="import-form"><h2>쓰시던 목록을 그대로 넣어주세요</h2><label for="import-text" class="sr-only">거래처명·주소 목록</label><textarea id="import-text" name="text" rows="5" placeholder="한빛빌딩    경기도 파주시 금빛로 10&#10;새봄요양원    경기도 김포시 김포대로 20&#10;&#10;주소만 한 줄에 하나씩 넣어도 됩니다."></textarea><div class="import-actions"><label class="upload" for="import-file">엑셀·CSV 파일 선택<input type="file" id="import-file" name="list_file" accept=".xlsx,.csv,.txt"></label><span id="import-filename" class="muted">최대 600개 · 5MB</span><button type="submit">자동 조회 시작 →</button></div><p class="muted">엑셀은 첫 번째 시트에서 거래처명·주소 열을 읽습니다. 연락처 등 개인정보는 제외해 주세요.<?=ca_ai_available()?' 형식이 섞인 텍스트는 OpenAI로 정리합니다.':' 정형 목록은 AI 비용 없이 바로 처리합니다.'?></p></form></section><?php endif;?>
<section id="import-review" <?= $job?'':'hidden'?>><div class="section-title"><h2>조회 결과</h2><button type="button" id="lookup-all" class="secondary">지연 항목 다시 조회</button></div><div id="import-counts" class="import-counts"></div><p class="muted">조회 지연은 최대 3회 자동 재시도합니다. 확인이 필요한 항목만 이유별로 모았습니다. 정확히 확인된 거래처부터 등록하세요.</p><div id="import-rows"></div><div class="import-footer"><a href="/manager_client_import.php">새 목록 넣기</a><button type="button" id="register-all">등록 가능한 거래처 모두 등록</button></div></section>
<details class="history"><summary>이전 등록 목록<?=count($history)?' · '.count($history).'건':''?></summary><?php foreach(array_slice($history,0,20) as $r):?><a class="request" href="?id=<?=mg_e($r['id'])?>"><span><?=mg_e(substr($r['created_at'],0,16))?> · <?=count($r['rows'])?>개</span><span class="badge"><?=mg_e(ci_status($r))?> →</span></a><?php endforeach;?></details><p class="import-help"><a href="/manager_client_intake.php">파일 정리가 어려우면 관리자에게 등록 요청 →</a></p>
<script type="application/json" id="import-data"><?=json_encode($job,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
</main></body></html>
