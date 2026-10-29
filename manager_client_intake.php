<?php
declare(strict_types=1);
require_once __DIR__.'/login_persistence.php';
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
require_once __DIR__.'/manager_client_intake_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
$actor=mg_uid();$members=mg_members();$admin=ci_admin();
if(!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor'])||defined('PARTNER_DEMO_RUNTIME')||(!$admin&&!mg_active($members[$actor]??[],'agency'))){http_response_code(403);exit('업체 또는 관리자 계정으로 로그인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$id=(string)($_GET['id']??'');if($id!==''&&!preg_match('/^[a-f0-9]{24}$/D',$id)){http_response_code(404);exit('접수 내역을 찾을 수 없습니다.');}
$all=mg_read(ci_file());$request=$all[$id]??null;
if($id!==''&&(!$request||(!$admin&&!ci_owned($request,$actor,$members)))){http_response_code(404);exit('접수 내역을 찾을 수 없습니다.');}
if(isset($_GET['download'])&&$request){
 $file=mg_read(__DIR__.'/data/client_intakes/attachments/'.$id.'.php');$attachment=$file['attachment']??[];
 if(!$attachment){http_response_code(404);exit('첨부 파일이 없습니다.');}
 $bytes=base64_decode((string)$attachment['data'],true);if($bytes===false){http_response_code(500);exit('첨부 파일을 확인해 주세요.');}
 header('Content-Type: application/octet-stream');header("Content-Security-Policy: sandbox");header("Content-Disposition: attachment; filename=client-list.".$attachment['ext']."; filename*=UTF-8''".rawurlencode($attachment['name']));header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}
if(($request['mode']??'')==='auto'){header('Location: /manager_client_import.php?id='.$id,true,303);exit;}
$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')try{
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 새로고침해 주세요.');
 $action=(string)($_POST['action']??'');
 if($action==='submit'){
  if($admin)throw new RuntimeException('업체 계정에서 목록을 보내 주세요.');
  $token=(string)($_POST['token']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$token))throw new RuntimeException('요청을 다시 시작해 주세요.');
  $attachment=ci_attachment($_FILES['list_file']??[]);$text=trim((string)($_POST['list_text']??''));
  if($text===''&&in_array($attachment['ext']??'',['csv','txt'],true))$text=ci_decode(base64_decode($attachment['data'],true));
  $rows=ci_parse($text);if(!$rows&&!$attachment)throw new RuntimeException('거래처 주소 목록 또는 파일을 보내 주세요.');
  $note=ci_text((string)($_POST['note']??''),1000);
  $newId=mg_member_tx(function(array &$m)use($actor,$rows,$attachment,$token,$note){
   if(!mg_active($m[$actor]??[],'agency'))throw new RuntimeException('업체 계정 상태를 확인해 주세요.');
   return mg_tx(ci_file(),function(array &$store)use($m,$actor,$rows,$attachment,$token,$note){
    foreach($store as $r)if(ci_owned($r,$actor,$m)&&($r['token']??'')===$token)return $r['id'];
    $open=0;foreach($store as $r)if(ci_owned($r,$actor,$m)&&ci_status($r)!=='등록 완료')$open++;
    if($open>=10)throw new RuntimeException('처리 중인 목록이 10개입니다. 기존 접수 내역을 먼저 확인해 주세요.');
    $newId=bin2hex(random_bytes(12));
    if($attachment)mg_tx(__DIR__.'/data/client_intakes/attachments/'.$newId.'.php',function(array &$f)use($attachment){$f=['attachment'=>$attachment];},true);
    $store[$newId]=['id'=>$newId,'token'=>$token,'manager'=>$actor,'manager_created'=>(string)($m[$actor]['created']??''),'created_at'=>date('c'),'revision'=>1,'note'=>$note,'rows'=>$rows,'attachment_name'=>$attachment['name']??''];return $newId;
   },true);
  });
  header('Location: /manager_client_intake.php?id='.$newId.'&sent=1',true,303);exit;
 }
 if(!$admin||!$request||!in_array($action,['parse','register'],true))throw new RuntimeException('관리자만 등록할 수 있습니다.');
 $parsed=$action==='parse'?ci_parse((string)($_POST['review_text']??'')):[];
 if($action==='parse'&&!$parsed)throw new RuntimeException('거래처명·주소 목록을 입력해 주세요.');
 $selectedRaw=isset($_POST['selected_payload'])?json_decode((string)$_POST['selected_payload'],true,512,JSON_THROW_ON_ERROR):($_POST['selected']??[]);
 $selected=array_values(array_filter((array)$selectedRaw,'is_string'));
 $rowEdits=isset($_POST['rows_payload'])?json_decode((string)$_POST['rows_payload'],true,512,JSON_THROW_ON_ERROR):(array)($_POST['rows']??[]);
 if(!is_array($rowEdits)||count($selected)>600||count($rowEdits)>600)throw new RuntimeException('목록은 최대 600개까지 처리할 수 있습니다.');
 if($action==='register'&&!$selected)throw new RuntimeException('등록할 항목을 선택해 주세요.');
 mg_member_tx(function(array &$m)use($id,$action,$parsed,$selected,$rowEdits){
  if(!ci_admin())throw new RuntimeException('관리자 권한이 필요합니다.');
  mg_tx(ci_file(),function(array &$store)use($id,$action,$parsed,$selected,$m,$rowEdits){
   if(!isset($store[$id]))throw new RuntimeException('접수 내역이 없습니다.');$r=&$store[$id];
   if((int)($_POST['revision']??0)!==$r['revision'])throw new RuntimeException('다른 창에서 변경되었습니다. 새로고침 후 다시 확인해 주세요.');
   if(!ci_owned($r,$r['manager'],$m))throw new RuntimeException('업체 계정 상태가 변경되었습니다.');
   if($action==='parse'){
    foreach($r['rows'] as $row)if($row['address_id']!=='')throw new RuntimeException('등록이 시작된 목록은 교체할 수 없습니다. 새 목록으로 접수해 주세요.');
    $r['rows']=$parsed;
   }else{mg_state_tx(function(array &$s)use(&$r,$m,$selected,$rowEdits){ci_register_rows($r,$s,$m,$selected,$rowEdits);});}
   $r['revision']++;$r['updated_at']=date('c');
  },true);
 });
 header('Location: /manager_client_intake.php?id='.$id.'&saved=1',true,303);exit;
}catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'처리하지 못했습니다. 입력 내용과 파일을 확인해 주세요.';}
$visible=array_filter($all,static fn($r)=>$admin||ci_owned($r,$actor,$members));uasort($visible,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
$token=preg_match('/^[a-f0-9]{32}$/D',(string)($_POST['token']??''))?$_POST['token']:bin2hex(random_bytes(16));
function ci_hidden(string $action,?array $r=null):void{?><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="<?=mg_e($action)?>"><?php if($r):?><input type="hidden" name="revision" value="<?= (int)$r['revision']?>"><?php endif;}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>거래처 목록 <?= $admin?'접수 관리':'보내기'?> · 소방계획서.com</title><link rel="stylesheet" href="/manager_client_intake.css?v=20261028-1"></head><body><main>
<header><span class="eyebrow"><?= $admin?'관리자 · 초기 등록 지원':'파트너센터 · 초기 등록 지원'?></span><h1>거래처 목록 <?= $admin?'접수 관리':'보내기'?></h1><p>쓰시던 거래처 주소 목록을 보내주세요.<br>관리자가 확인한 뒤, 보낸 업체의 사전등록 거래처로 등록해 드립니다.</p><a target="_top" href="<?= $admin?'/admin_members.php':'/partner_center.php'?>">← <?= $admin?'회원 관리':'파트너센터'?></a></header>
<?php if($error):?><p class="notice error" role="alert"><?=mg_e($error)?></p><?php endif;?>
<?php if(isset($_GET['sent'])):?><p class="notice" role="status">목록을 접수했습니다. 아래에서 등록 결과를 확인할 수 있습니다.</p><?php elseif(isset($_GET['saved'])):?><p class="notice" role="status">처리 결과를 저장했습니다. 각 항목의 등록 결과를 확인해 주세요.</p><?php endif;?>
<?php if($request): $owner=$members[$request['manager']]??[];?>
<section><div class="section-title"><h2>접수 내역</h2><?php if(($request['mode']??'')==='auto'):?><a href="/manager_client_import.php?id=<?=mg_e($id)?>">자동 조회·등록 화면 열기 →</a><?php endif;?><span class="badge"><?=mg_e(ci_status($request))?></span></div><p class="muted"><?=mg_e(substr($request['created_at'],0,16))?> · <?=count($request['rows'])?>개</p>
<?php if($admin):?><p><b>등록 대상 업체</b> <?=mg_e((string)($owner['company']??$owner['name']??$owner['nick']??$request['manager']))?> <small>(<?=mg_e($request['manager'])?>)</small></p><?php endif;?>
<?php if($request['note']!==''):?><p class="note"><?=nl2br(mg_e($request['note']))?></p><?php endif;?>
<?php if($request['attachment_name']!==''):?><a class="file" href="?id=<?=mg_e($id)?>&amp;download=1">첨부 파일 다운로드 · <?=mg_e($request['attachment_name'])?></a><?php endif;?>
<?php if($admin):?><p class="muted">거래처명·주소를 확인하고 선택한 항목을 등록하세요. 건축물대장 조회와 지도 위치 확인은 등록 후 기본정보에서 진행합니다. 동일 업체의 같은 이름·주소는 중복 등록하지 않습니다.</p><?php endif;?>
<?php if($request['rows']):?><form method="post"><?php ci_hidden('register',$request);?><div class="table-wrap"><table><thead><tr><?php if($admin):?><th>선택</th><?php endif;?><th>거래처명</th><th>주소</th><th>등록 결과</th></tr></thead><tbody>
<?php foreach($request['rows'] as $row):$done=$row['address_id']!=='';?><tr><?php if($admin):?><td><?php if(!$done):?><input type="checkbox" name="selected[]" value="<?=mg_e($row['id'])?>" checked aria-label="<?=mg_e($row['name']?:$row['address'])?> 등록 선택"><?php endif;?></td><?php endif;?><td><?php if($admin&&!$done):?><input name="rows[<?=mg_e($row['id'])?>][name]" value="<?=mg_e($row['name'])?>" maxlength="150" aria-label="거래처명"><?php else:?><?=mg_e($row['name']?:'확인 필요')?><?php endif;?></td><td><?php if($admin&&!$done):?><input class="address" name="rows[<?=mg_e($row['id'])?>][address]" value="<?=mg_e($row['address'])?>" maxlength="500" aria-label="주소"><?php else:?><?=mg_e($row['address'])?><?php endif;?></td><td><span class="<?= $done?'done':'muted'?>"><?=mg_e($row['result']?:'확인 대기')?></span><?php if($done&&!$admin):?> <a href="/manager_draft.php?id=<?=mg_e($row['address_id'])?>">기본정보 →</a><?php endif;?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($admin&&ci_status($request)!=='등록 완료'):?><button type="submit">선택한 거래처 등록</button><?php endif;?></form><?php else:?><p class="empty">파일을 접수했습니다. 관리자가 목록을 확인한 뒤 등록합니다.</p><?php endif;?>
<?php if($admin&&ci_status($request)==='접수됨'):?><details><summary>첨부 자료에서 거래처명·주소 붙여넣기</summary><p class="muted">엑셀을 내려받아 거래처명·주소 두 열을 복사하세요. 아직 등록하지 않은 목록만 교체할 수 있습니다.</p><form method="post"><?php ci_hidden('parse',$request);?><textarea name="review_text" rows="6" required placeholder="거래처명&#9;주소"></textarea><button type="submit" class="secondary">검토 목록 만들기</button></form></details><?php endif;?></section><a href="/manager_client_intake.php">← 전체 접수 내역</a>
<?php else:?>
<?php if(!$admin):?><section><h2>주소 목록을 보내주세요</h2><form method="post" enctype="multipart/form-data"><?php ci_hidden('submit');?><input type="hidden" name="token" value="<?=mg_e($token)?>"><input type="hidden" name="MAX_FILE_SIZE" value="5242880"><label for="list-text">목록 붙여넣기</label><p class="muted">엑셀의 거래처명·주소 두 열을 복사하거나, 주소만 한 줄에 하나씩 넣어도 됩니다.</p><textarea id="list-text" name="list_text" rows="7" placeholder="한빛빌딩&#9;경기도 파주시 금빛로 10&#10;새봄요양원&#9;경기도 김포시 김포대로 20"><?=mg_e((string)($_POST['list_text']??''))?></textarea><label for="list-file">또는 파일 첨부</label><input id="list-file" type="file" name="list_file" accept=".xlsx,.xls,.csv,.txt"><p class="muted">엑셀·CSV·TXT / 최대 5MB · 목록은 600개까지<br>거래처명과 주소만 보내주세요. 연락처 등 개인정보는 제외해 주세요. 엑셀은 관리자가 확인하며, CSV·TXT는 두 열을 자동으로 읽습니다.</p><label for="note">전달사항 <span class="muted">선택</span></label><textarea id="note" name="note" rows="2" maxlength="1000"><?=mg_e((string)($_POST['note']??''))?></textarea><button type="submit">목록 보내기 →</button><p class="muted">등록된 정보는 기존 ‘건물 사전등록’에서 이어서 관리할 수 있습니다.</p></form></section><?php endif;?>
<section><div class="section-title"><h2><?= $admin?'접수된 목록':'보낸 목록'?></h2><span class="badge"><?=count($visible)?>건</span></div><?php if(!$visible):?><p class="empty">아직 접수된 목록이 없습니다.</p><?php endif;?><?php foreach($visible as $r):$done=count(array_filter($r['rows'],static fn($v)=>$v['address_id']!==''));?><a class="request" href="?id=<?=mg_e($r['id'])?>"><span><b><?=mg_e($r['attachment_name']?:'거래처 주소 목록')?></b><small><?=mg_e(substr($r['created_at'],0,16))?><?= $admin?' · '.mg_e($r['manager']):''?> · 등록 <?=$done?> / <?=count($r['rows'])?>개</small></span><span class="badge"><?=mg_e(ci_status($r))?> ›</span></a><?php endforeach;?></section>
<?php endif;?></main><script>
document.addEventListener('submit',function(e){const form=e.target;if(form.elements.action?.value!=='register')return;const rows={},selected=[];for(const input of form.querySelectorAll('input[name^="rows["],input[name="selected[]"]')){if(input.name==='selected[]'){if(input.checked)selected.push(input.value);}else{const match=input.name.match(/^rows\[([^\]]+)\]\[(name|address)\]$/);if(match){rows[match[1]]??={};rows[match[1]][match[2]]=input.value;}}input.disabled=true;}for(const [name,value] of Object.entries({rows_payload:rows,selected_payload:selected})){let input=form.querySelector('input[name="'+name+'"]');if(!input){input=document.createElement('input');input.type='hidden';input.name=name;form.append(input);}input.value=JSON.stringify(value);}});
</script></body></html>
