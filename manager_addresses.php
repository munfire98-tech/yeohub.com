<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_addresses_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
$actor=mg_uid();$members=mg_members();
if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);exit('매니저 계정으로 로그인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
function ma_http(string $url):array {
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>18,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false||$code!==200)throw new RuntimeException('외부 조회에 실패했습니다. 잠시 후 다시 시도해 주세요.');
 $d=json_decode($raw,true);if(!is_array($d))throw new RuntimeException('조회 응답을 확인하지 못했습니다.');return $d;
}
function ma_items(array $d):array {
 $code=(string)($d['response']['header']['resultCode']??'00');if(!in_array($code,['00','0'],true))throw new RuntimeException('건축물대장 조회 권한 또는 응답을 확인해 주세요.');
 $a=$d['response']['body']['items']['item']??[];if(!is_array($a))return [];return isset($a['platPlc'])||isset($a['bldNm'])?[$a]:array_values($a);
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 $act=(string)($_POST['act']??'');$link=$act==='link';
 if(!$link)header('Content-Type: application/json; charset=utf-8');
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 새로고침해 주세요.');
  if($link){
   $target=(string)($_POST['target']??'');$draftId=(string)($_POST['address_id']??'');
   ma_accept($actor,$target,(string)($_POST['request_key']??''),$draftId);
   $notice='연결 요청을 수락했습니다.';
   if($draftId!==''){
    try{require_once __DIR__.'/building_info.php';bi_load_uid($target);$notice='연결했습니다. 매니저의 사전등록 정보로 유저 기본정보를 교체했습니다.';}
    catch(Throwable $e){$notice='연결은 완료됐지만 기본정보 반영을 마치지 못했습니다. 유저 기본정보 화면을 다시 열면 반영을 재시도합니다.';}
   }
   $_SESSION['manager_notice']=['text'=>$notice];header('Location: /clients_mini.php#manager-sidebar',true,303);exit;
  }
  if($act==='new'){
   $id=ma_begin($actor,$members);$result=['id'=>$id];
  }elseif($act==='search'){
   $kw=trim((string)($_POST['keyword']??''));if(mb_strlen($kw)<2||mb_strlen($kw)>150)throw new RuntimeException('검색할 주소를 2자 이상 입력해 주세요.');
   $api=require __DIR__.'/api_keys.php';$d=ma_http($api['juso_url'].'?'.http_build_query(['confmKey'=>$api['juso'],'currentPage'=>1,'countPerPage'=>15,'keyword'=>$kw,'resultType'=>'json']));
   if((string)($d['results']['common']['errorCode']??'')!=='0')throw new RuntimeException('주소를 조회하지 못했습니다. 도로명과 건물번호로 검색해 주세요.');
   $rows=[];$_SESSION['manager_address_search']=[];
   foreach($d['results']['juso']??[] as $a){$adm=(string)($a['admCd']??'');if(!preg_match('/^\d{10}$/D',$adm))continue;
    $token=bin2hex(random_bytes(16));$address=['name'=>(string)($a['bdNm']??''),'address'=>(string)$a['roadAddr'],'jibun'=>(string)($a['jibunAddr']??''),'address_key'=>(string)($a['bdMgtSn']??'')?:hash('sha256',$a['roadAddr']),'codes'=>['sigunguCd'=>substr($adm,0,5),'bjdongCd'=>substr($adm,5,5),'platGbCd'=>($a['mtYn']??'0')==='1'?'1':'0','bun'=>str_pad((string)(int)($a['lnbrMnnm']??0),4,'0',STR_PAD_LEFT),'ji'=>str_pad((string)(int)($a['lnbrSlno']??0),4,'0',STR_PAD_LEFT)]];
    $_SESSION['manager_address_search'][$token]=['at'=>time(),'actor'=>$actor,'address'=>$address];$rows[]=['token'=>$token,'name'=>$address['name'],'address'=>$address['address'],'jibun'=>$address['jibun']];
   }$result=['results'=>$rows];
  }elseif($act==='save'){
   $candidate=$_SESSION['manager_address_search'][(string)($_POST['token']??'')]??[];
   if(($candidate['actor']??'')!==$actor||($candidate['at']??0)<time()-1800)throw new RuntimeException('주소를 다시 검색해 주세요.');
   $result=['id'=>ma_save($actor,$candidate['address'])];
  }elseif($act==='lookup'||$act==='delete'){
   $id=(string)($_POST['id']??'');$state=mg_read(mg_state_file());$row=$state['address_book'][$id]??[];
   if(!ma_owned($row,$actor,$members))throw new RuntimeException('등록된 주소를 확인할 수 없습니다.');
   if($act==='delete'){
    mg_member_tx(function(array &$current)use($id,$actor){mg_state_tx(function(array &$s)use($current,$id,$actor){$r=$s['address_book'][$id]??[];if(!ma_owned($r,$actor,$current)||ma_linked($r,$actor,$current,$s))throw new RuntimeException('연결된 주소는 삭제할 수 없습니다.');$s['address_book'][$id]['deleted_at']=date('c');});});$result=[];
   }else{
    $api=require __DIR__.'/api_keys.php';$key=(string)$api['hub'];if(strpos($key,'%')!==false)$key=urldecode($key);
    $query=$row['codes']+['serviceKey'=>$key,'numOfRows'=>100,'pageNo'=>1,'_type'=>'json'];
    $d=ma_http(rtrim($api['hub_base'],'/').'/getBrTitleInfo?'.http_build_query($query));$raw=ma_items($d);$items=[];
    foreach($raw as $v){if(!is_array($v))continue;$item=[];foreach(['bldNm','dongNm','platPlc','newPlatPlc','mainPurpsCdNm','strctCdNm','grndFlrCnt','ugrndFlrCnt','archArea','totArea','useAprDay'] as $k)$item[$k]=(string)($v[$k]??'');$items[]=$item;}
    $result=['items'=>$items,'address'=>$row['address'],'total'=>(int)($d['response']['body']['totalCount']??count($items))];
   }
  }else{throw new RuntimeException('지원하지 않는 요청입니다.');}
  echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 }catch(Throwable $e){if($link){$_SESSION['manager_notice']=['text'=>$e->getMessage(),'error'=>true];header('Location: /clients_mini.php#manager-sidebar',true,303);}else{http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'처리하지 못했습니다. 잠시 후 다시 시도해 주세요.'],JSON_UNESCAPED_UNICODE);}}exit;
}
if(isset($_GET['requests'])){
 $state=mg_read(mg_state_file());$pending=[];
 foreach($members as $uid=>$member){if(!is_array($member)||!mg_active($member,'building')||mg_connection_manager($member,$members)!==$actor||mg_link_status((string)$uid,$member,$state)!=='pending')continue;$pending[(string)$uid]=$member;}
 ?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>새로운 연결 요청</title><link rel="stylesheet" href="/manager_addresses.css?v=8"><body><main class="ma-page"><header><span class="ma-kicker">신규 유저 연결</span><h1>새로운 연결 요청 <small><?=count($pending)?>건</small></h1><p>요청자를 확인하고 사전 등록한 거래처와 연결해 주세요.</p></header>
 <?php if(!$pending): ?><p class="ma-note">대기 중인 연결 요청이 없습니다.</p><?php endif; ?>
 <?php foreach($pending as $uid=>$m):$key=mg_link_key($uid,$m);$bi=preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)?mg_read(__DIR__.'/data/building/'.$uid.'/info.json'):[]; ?>
 <article class="ma-address"><div><strong><?=mg_e($m['nickname']??$uid)?></strong><p><?=mg_e($uid)?></p><p><?=mg_e($bi['address']??'')?:'주소 미입력 · 연결 전에 유저에게 확인해 주세요.'?></p><small>연결 대기</small></div><div class="ma-actions"><a class="ma-link" href="/manager_addresses.php?request=<?=rawurlencode($uid)?>&amp;key=<?=rawurlencode($key)?>">연결 검토 →</a><form action="/manager_portal.php" method="post" target="_top"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="target" value="<?=mg_e($uid)?>"><input type="hidden" name="request_key" value="<?=mg_e($key)?>"><input type="hidden" name="return_to" value="clients"><button class="ma-quiet" name="decision" value="rejected">거절</button></form></div></article>
 <?php endforeach; ?></main></body></html><?php exit;
}
$request=(string)($_GET['request']??'');
if($request!==''){
 $state=mg_read(mg_state_file());$member=$members[$request]??[];$key=(string)($_GET['key']??'');
 if(!mg_active($member,'building')||mg_connection_manager($member,$members)!==$actor||$key===''||!hash_equals(mg_link_key($request,$member),$key)||mg_link_status($request,$member,$state)!=='pending'){http_response_code(409);exit('이미 처리되었거나 만료된 요청입니다. 창을 닫고 새로고침해 주세요.');}
 $choices=ma_available($actor,$members,$state);
 $bi=preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$request)?mg_read(__DIR__.'/data/building/'.$request.'/info.json'):[];
 ?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>연결 요청 검토</title><link rel="stylesheet" href="/manager_addresses.css?v=8"><body>
 <main class="ma-page ma-match"><header><span class="ma-kicker">연결 요청 검토</span><h1>어느 거래처와 연결할까요?</h1><p>요청한 유저와 사전 등록한 건물이 같은 곳인지 확인해 주세요.</p></header>
 <section class="ma-person"><span class="ma-avatar" aria-hidden="true">↗</span><div><strong><?=mg_e($member['nickname']??$request)?></strong><small><?=mg_e($request)?></small><p><?=mg_e($bi['address']??'')?:'유저가 입력한 주소가 없습니다. 연결 전 주소를 확인해 주세요.'?></p></div></section>
 <form action="/manager_addresses.php" method="post" target="_top" id="ma-link-form">
 <input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="act" value="link"><input type="hidden" name="target" value="<?=mg_e($request)?>"><input type="hidden" name="request_key" value="<?=mg_e($key)?>">
 <fieldset class="ma-choice-set"><legend>연결할 거래처 선택</legend>
 <input type="search" id="ma-filter" placeholder="건물명 또는 주소로 찾기" aria-label="사전 등록 거래처 검색">
 <div class="ma-choice-list">
 <?php foreach($choices as $id=>$row):$ready=trim((string)($row['address']??''))!==''; ?>
 <label class="ma-choice" data-choice-search="<?=mg_e($row['name'].' '.$row['address'])?>"><input type="radio" name="address_id" value="<?=mg_e($id)?>" required <?=$ready?'':'disabled'?>><span><strong><?=mg_e($row['name']?:'작성 중인 거래처')?></strong><small><?=mg_e($row['address']?:'주소 작성 후 연결할 수 있습니다.')?></small><em><?=$ready?'저장된 기본정보 반영':'작성 중'?></em></span></label>
 <?php endforeach; ?>
 </div><p class="ma-note" id="ma-match-empty" <?=count($choices)?'hidden':''?>>사전 등록된 거래처가 없습니다.</p>
 <label class="ma-choice ma-choice-none"><input type="radio" name="address_id" value="" required><span><strong>사전 등록 없이 연결</strong><small>기존 기본정보를 유지하며 연결합니다.</small></span></label>
 </fieldset>
 <div class="ma-decision"><p id="ma-selection-note" role="status">거래처를 선택하거나 사전 등록 없이 연결해 주세요.</p><small>사전등록을 선택하면 유저 기본정보를 사전등록 내용으로 교체합니다. 사전등록의 빈 항목도 그대로 적용되며, 기존 정보는 백업합니다.</small><button type="submit" id="ma-connect" disabled>선택 후 연결하기</button></div>
 </form></main><script src="/manager_addresses.js?v=8" defer></script></body></html><?php exit;
}
$state=mg_read(mg_state_file());$selected=(string)($_GET['id']??'');$rows=[];
if($selected!==''){header('Location: /manager_draft_view.php?id='.rawurlencode($selected),true,302);exit;}
foreach($state['address_book']??[] as $id=>$row)if(ma_owned($row,$actor,$members))$rows[]=['id'=>$id,'name'=>$row['name'],'address'=>$row['address'],'linked'=>ma_linked($row,$actor,$members,$state)];
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>기본정보 사전 등록</title>
<link rel="stylesheet" href="/manager_addresses.css?v=8"><body><main class="ma-page" data-csrf="<?=mg_e($_SESSION['csrf'])?>" data-selected="<?=mg_e($selected)?>" data-create="<?=isset($_GET['new'])?'1':'0'?>">
<header><span class="ma-kicker">거래처 사전 등록</span><h1>유저가 오기 전, 기본정보부터</h1><p>유저와 같은 문답으로 건축물대장을 불러오고 기본정보를 작성하세요.<br>연결 요청이 오면 거래처를 선택해 작성한 정보를 이어갈 수 있습니다.</p></header>
<button type="button" id="ma-new" style="margin-top:16px">＋ 기본정보 사전 등록</button>
<p id="ma-status" role="status"></p><div id="ma-results"></div><section><input type="search" id="ma-saved-search" placeholder="건물명 · 주소 검색" aria-label="등록 거래처 검색"><h2>사전 등록 거래처 <small id="ma-count"></small></h2><div id="ma-saved"></div></section>
</main><script type="application/json" id="ma-data"><?=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="/manager_addresses.js?v=8" defer></script></body></html>
