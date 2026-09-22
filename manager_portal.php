<?php
declare(strict_types=1);
ini_set('session.cookie_httponly','1');
session_start();
require_once __DIR__.'/manager_ui.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');
$actor=mg_uid();
if($actor===''){http_response_code(403);exit('본인 계정으로 로그인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $returnTo=in_array($_POST['return_to']??'', ['building','clients'],true)?$_POST['return_to']:'portal';
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.');
        if(($_POST['action']??'')==='register_code'){
            $code=is_string($_POST['manager_code']??null)?trim($_POST['manager_code']):'';
            mg_request_manager($actor,$code,($_POST['manager_consent']??'')==='1');
            unset($_SESSION['manager_code_old']);
            $_SESSION['manager_notice']=['text'=>'연결 요청을 보냈습니다. 매니저가 수락하면 연결이 완료됩니다.'];
        }else{
            $key=is_string($_POST['request_key']??null)?$_POST['request_key']:'';
            if($key==='')throw new RuntimeException('요청 정보를 확인하지 못했습니다. 새로고침 후 다시 시도해 주세요.');
            $decision=(string)($_POST['decision']??'');
            mg_decide($actor,(string)($_POST['target']??''),$decision,$key);
            $_SESSION['manager_notice']=['text'=>['accepted'=>'연결 요청을 수락했습니다. 유저 현황을 확인할 수 있습니다.','rejected'=>'연결 요청을 거절했습니다.','revoked'=>'연결 요청 또는 연결을 해제했습니다.'][$decision]??'처리되었습니다.'];
        }
    }catch(Throwable $e){
        $_SESSION['manager_notice']=['text'=>$e->getMessage(),'error'=>true];
        if(($_POST['action']??'')==='register_code')$_SESSION['manager_code_old']=is_string($_POST['manager_code']??null)?substr($_POST['manager_code'],0,13):'';
    }
    $clientsReturn='/clients_mini.php';$returnQuery=[];
    if(is_string($_POST['return_month']??null)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$_POST['return_month']))$returnQuery['m']=$_POST['return_month'];
    if(in_array($_POST['return_type']??'', ['visit','inspect','as','report','submit'],true))$returnQuery['type']=$_POST['return_type'];
    if($returnQuery)$clientsReturn.='?'.http_build_query($returnQuery);
    if($returnTo==='clients'){header('Location: '.$clientsReturn.'#manager-sidebar',true,303);exit;}
    header('Location: '.($returnTo==='building'?'/building_manager.php#manager-connect':'/manager_portal.php'),true,303);exit;
}
$error='';$manager=false;$rows=[];$ledger=[];$balance=0;$pending=0;$accepted=0;
try{
    $members=mg_members();$me=$members[$actor]??[];$manager=mg_active($me,'agency');
    if(!$manager&&!mg_active($me,'building'))throw new RuntimeException('사용 가능한 회원 계정이 아닙니다.');
    if($manager&&empty($me['manager_code'])){
        mg_member_tx(function(array &$all)use($actor){
            if(!mg_active($all[$actor]??[],'agency'))throw new RuntimeException('매니저 권한이 없습니다.');
            if(empty($all[$actor]['manager_code']))$all[$actor]['manager_code']=mg_code($all);
        });
        $members=mg_members();$me=$members[$actor];
    }
    if($manager){
        foreach($members as $id=>$m){
            if(!is_array($m)||!mg_active($m,'building'))continue;
            if(mg_connection_manager($m,$members)===$actor)$rows[(string)$id]=$m;
            if(mg_referrer($m,$members)===$actor)mg_reconcile((string)$id);
        }
    }else{mg_reconcile($actor);}
    $state=mg_read(mg_state_file());
    foreach($state['rewards']??[] as $uid=>$r)if(($r['manager']??'')===$actor&&($r['manager_created']??'')===(string)($me['created']??'')){$ledger[$uid]=$r;if(empty($r['reversed']))$balance++;}
    foreach($rows as $uid=>$m){$s=mg_link_status($uid,$m,$state);if($s==='pending')$pending++;if($s==='accepted')$accepted++;}
}catch(Throwable $e){http_response_code(503);$error=$e->getMessage();}
$labels=['pending'=>'수락 대기','accepted'=>'연결됨','rejected'=>'거절됨','revoked'=>'연결 해제'];
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$manager?'담당 유저 · 파이어코인':'담당 매니저 연결'?> | TWORIX</title><link rel="stylesheet" href="/manager.css?v=3"></head>
<body class="mp-body"><div class="mp-page"><nav class="mp-nav" aria-label="주 메뉴"><a class="mp-brand" href="/index.php"><span><?=mg_icon('shield')?></span>TWORIX</a><a class="mp-back" href="<?=$manager?'/clients_mini.php':'/building_manager.php'?>"><?=mg_icon('back')?> 업무 화면으로</a></nav><main class="mp-main">
<header class="mp-heading"><div><p class="mp-eyebrow"><?=$manager?'PARTNER WORKSPACE':'BUILDING CARE'?></p><h1><?=$manager?'담당 유저 · 파이어코인':'담당 매니저 연결'?></h1><p><?=$manager?'함께 관리하는 건물과 새로운 연결 요청을 확인하세요.':'가입은 끝났으니, 이제 담당 매니저와 연결해 보세요.'?></p></div></header>
<?php if($error!==''): ?><div class="mc-notice mc-notice--error" role="alert"><?=mg_e($error)?></div>
<?php elseif(!$manager): ?>
<div class="mp-user-layout"><div><?php mg_connect_card(); ?></div><aside class="mp-aside"><h2>연결하면 함께 할 수 있어요</h2>
<div class="mp-benefit"><span><?=mg_icon('users')?></span><div><h3>우리 건물의 담당 매니저</h3><p>코드로 연결된 매니저가 요청을 확인하고<br>우리 건물의 관리 현황을 살펴봅니다.</p></div></div>
<div class="mp-benefit"><span><?=mg_icon('check')?></span><div><h3>놓친 업무도 한눈에</h3><p>기본정보부터 교육·훈련 기록까지,<br>어떤 항목이 부족한지 함께 확인합니다.</p></div></div>
<div class="mp-benefit"><span><?=mg_icon('lock')?></span><div><h3>내가 선택하는 정보 공유</h3><p>매니저가 수락한 뒤에만 공유되며,<br>원할 때 언제든 연결을 해제할 수 있습니다.</p></div></div>
<div class="mp-help"><strong>매니저 코드가 없으신가요?</strong>담당 매니저에게 고유 코드를 요청해 주세요.<br>코드 없이도 기존 건물관리 기능은 이용할 수 있습니다.</div></aside></div>
<?php else: mg_notice(); ?>
<section class="mp-stats" aria-label="매니저 요약"><div class="mp-stat mp-stat--code"><div class="mp-stat__label"><?=mg_icon('link')?> 나의 매니저 코드</div><div class="mp-code-line"><code id="my-manager-code"><?=mg_e($me['manager_code'])?></code><button type="button" class="mp-copy" aria-label="매니저 코드 복사" data-copy-code><?=mg_icon('copy')?></button></div><p class="mp-stat__note" id="copy-result" role="status">건물관리자에게 이 코드를 전달해 주세요.</p></div>
<div class="mp-stat mp-stat--coin"><div class="mp-stat__label"><?=mg_icon('coin')?> 파이어코인</div><div class="mp-stat__number"><?=$balance?><small>개</small></div><p class="mp-stat__note">최초 결제 보상</p></div>
<div class="mp-stat"><div class="mp-stat__label"><?=mg_icon('users')?> 함께 관리하는 유저</div><div class="mp-stat__number"><?=$accepted?><small>명</small></div><p class="mp-stat__note">새로운 요청 <?=$pending?>건</p></div></section>
<section class="mp-panel"><div class="mp-panel__head"><h2>연결 요청 · 담당 유저<span class="mp-count"><?=count($rows)?></span></h2><p>요청을 수락하면 유저 현황을 조회할 수 있어요.</p></div>
<?php if(!$rows): ?><div class="mp-empty"><span><?=mg_icon('users')?></span><h3>새로운 연결을 기다리고 있어요</h3><p>건물관리자가 내 코드를 등록하면 여기에 표시됩니다.</p></div>
<?php else: ?><ul class="mp-rows"><?php foreach($rows as $uid=>$m):$s=mg_link_status($uid,$m,$state); ?><li class="mp-row"><span class="mc-avatar"><?=mg_icon('users')?></span><div class="mp-row__info"><h3><?=mg_e($m['nickname']??$uid)?></h3><p><?=mg_e($uid)?> · <?=mg_e(substr($m['manager_requested_at']??$m['referral_at']??'',0,10))?> 요청</p></div><span class="mc-status mc-status--<?=mg_e($s)?>"><i></i><?=mg_e($labels[$s]??$s)?></span><div class="mp-row__actions">
<?php if($s==='pending'){mg_action_form($uid,$m,'accepted','수락하기','portal','primary');mg_action_form($uid,$m,'rejected','거절');}elseif($s==='accepted'){ ?><a class="mc-button mc-button--primary" href="/manager_view.php?uid=<?=rawurlencode($uid)?>">유저 현황 <?=mg_icon('arrow')?></a><?php mg_action_form($uid,$m,'revoked','연결 해제','portal','text');} ?>
</div></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="mp-panel"><div class="mp-panel__head"><h2>파이어코인 내역</h2><p>최초 정상 결제에 유저당 1개</p></div><?php if(!$ledger): ?><div class="mp-empty"><span><?=mg_icon('coin')?></span><h3>첫 번째 파이어코인을 기다리고 있어요</h3><p>코드를 등록한 유저가 처음 결제하면 코인이 적립됩니다.</p></div><?php else: ?><div class="mp-scroll"><table class="mp-ledger"><thead><tr><th>유저</th><th>최초 결제일</th><th>적립 내역</th></tr></thead><tbody><?php foreach($ledger as $uid=>$r): ?><tr><td><?=mg_e($uid)?></td><td><?=mg_e(substr($r['at'],0,10))?></td><td><?=empty($r['reversed'])?'+1 코인':'전액 환불 · 회수됨'?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<p class="mp-footer-note">코드 등록 전 이미 결제한 유저에게는 보상이 소급 적용되지 않습니다. 최초 등록된 추천 매니저에게 1회 지급되며, 해당 결제의 전액 환불 시 회수됩니다.</p>
<?php endif; ?></main></div><script src="/manager.js?v=2" defer></script></body></html>
