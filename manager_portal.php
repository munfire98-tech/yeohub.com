<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

ini_set('session.cookie_httponly','1');
alp_session_start();
require_once __DIR__.'/manager_ui.php';
require_once __DIR__.'/manager_payout_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');
$actor=mg_uid();
if($actor===''){http_response_code(403);exit('본인 계정으로 로그인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $localSetupStart=false;
    $returnTo=in_array($_POST['return_to']??'', ['building','clients'],true)?$_POST['return_to']:'portal';
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new RuntimeException('요청이 만료되었습니다. 새로고침 후 다시 시도해 주세요.');
        if(($_POST['action']??'')==='register_code'){
            $code=is_string($_POST['manager_code']??null)?trim($_POST['manager_code']):'';
            mg_request_manager($actor,$code,($_POST['manager_consent']??'')==='1');
            unset($_SESSION['manager_code_old']);
            $localSetupStart=strtoupper($code)==='FM-61A8AD50E2';
            $_SESSION['manager_notice']=['text'=>$localSetupStart?'로컬 파트너 연결이 완료되었습니다. 기본정보에서 건물등록을 시작해 주세요.':'연결 요청을 보냈습니다. 파트너가 수락하면 연결이 완료됩니다.'];
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
    $clientsReturn='/partner_center.php';$returnQuery=[];
    if(is_string($_POST['return_month']??null)&&preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$_POST['return_month']))$returnQuery['m']=$_POST['return_month'];
    if(in_array($_POST['return_type']??'', ['visit','inspect','as','report','submit'],true))$returnQuery['type']=$_POST['return_type'];
    if($returnQuery)$clientsReturn.='?'.http_build_query($returnQuery);
    if($returnTo==='clients'){header('Location: '.$clientsReturn.'#manager-sidebar',true,303);exit;}
    header('Location: '.($returnTo==='building'?($localSetupStart?'/FireDesk.php?setup_start=1#local-building-start':'/FireDesk.php#manager-connect'):'/manager_portal.php'),true,303);exit;
}
$error='';$manager=false;$rows=[];$ledger=[];$balance=0;$pending=0;$accepted=0;
try{
    $members=mg_members();$me=$members[$actor]??[];$manager=mg_active($me,'agency');
    if(!$manager&&!mg_active($me,'building'))throw new RuntimeException('사용 가능한 회원 계정이 아닙니다.');
    if($manager&&empty($me['manager_code'])){
        mg_member_tx(function(array &$all)use($actor){
            if(!mg_active($all[$actor]??[],'agency'))throw new RuntimeException('파트너 권한이 없습니다.');
            if(empty($all[$actor]['manager_code']))$all[$actor]['manager_code']=mg_code($all);
        });
        $members=mg_members();$me=$members[$actor];
    }
    if($manager){
        mr_sync_manager($actor);
        foreach($members as $id=>$m){
            if(!is_array($m)||!mg_active($m,'building'))continue;
            if(mg_connection_manager($m,$members)===$actor)$rows[(string)$id]=$m;

        }
    }else{mg_reconcile($actor);}
    $state=mg_read(mg_state_file());
    foreach($state['rewards']??[] as $uid=>$r)if(($r['manager']??'')===$actor&&($r['manager_created']??'')===(string)($me['created']??'')){$ledger[$uid]=$r;}
    $balance=mp_balance($state,$actor,$me)['available'];
    foreach($rows as $uid=>$m){$s=mg_link_status($uid,$m,$state);if($s==='pending')$pending++;if($s==='accepted')$accepted++;}
}catch(Throwable $e){http_response_code(503);$error=$e->getMessage();}
$labels=['pending'=>'수락 대기','accepted'=>'연결됨','rejected'=>'거절됨','revoked'=>'연결 해제'];
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$manager?'담당 유저 · 파이어 마일리지':'연결 업체 연결'?> | TWORIX</title><link rel="stylesheet" href="/manager.css?v=3"></head>
<body class="mp-body"><div class="mp-page"><nav class="mp-nav" aria-label="주 메뉴"><a class="mp-brand" href="/index.php"><span><?=mg_icon('shield')?></span>TWORIX</a><a class="mp-back" href="<?=$manager?'/partner_center.php':'/FireDesk.php'?>"><?=mg_icon('back')?> 업무 화면으로</a></nav><main class="mp-main">
<header class="mp-heading"><div><p class="mp-eyebrow"><?=$manager?'PARTNER WORKSPACE':'BUILDING CARE'?></p><h1><?=$manager?'담당 유저 · 파이어 마일리지':'연결 업체 연결'?></h1><p><?=$manager?'함께 관리하는 건물과 새로운 연결 요청을 확인하세요.':'가입은 끝났으니, 이제 연결 업체와 연결해 보세요.'?></p></div></header>
<?php if($error!==''): ?><div class="mc-notice mc-notice--error" role="alert"><?=mg_e($error)?></div>
<?php elseif(!$manager): ?>
<div class="mp-user-layout"><div><?php mg_connect_card(); ?></div><aside class="mp-aside"><h2>연결하면 함께 할 수 있어요</h2>
<div class="mp-benefit"><span><?=mg_icon('users')?></span><div><h3>우리 건물의 연결 업체</h3><p>코드로 연결된 업체가 요청을 확인하고<br>우리 건물의 관리 현황을 살펴봅니다.</p></div></div>
<div class="mp-benefit"><span><?=mg_icon('check')?></span><div><h3>놓친 업무도 한눈에</h3><p>기본정보부터 교육·훈련 기록까지,<br>어떤 항목이 부족한지 함께 확인합니다.</p></div></div>
<div class="mp-benefit"><span><?=mg_icon('lock')?></span><div><h3>내가 선택하는 정보 공유</h3><p>파트너가 수락한 뒤에만 공유되며,<br>원할 때 언제든 연결을 해제할 수 있습니다.</p></div></div>
<div class="mp-help"><strong>파트너 코드가 없으신가요?</strong>연결 업체에게 고유 코드를 요청해 주세요.<br>코드 없이도 기존 건물관리 기능은 이용할 수 있습니다.</div></aside></div>
<?php else: mg_notice(); ?>
<section class="mp-stats" aria-label="파트너 요약"><div class="mp-stat mp-stat--code"><div class="mp-stat__label"><?=mg_icon('link')?> 나의 파트너 코드</div><div class="mp-code-line"><code id="my-manager-code"><?=mg_e($me['manager_code'])?></code><button type="button" class="mp-copy" aria-label="파트너 코드 복사" data-copy-code><?=mg_icon('copy')?></button></div><p class="mp-stat__note" id="copy-result" role="status">건물관리자에게 이 코드를 전달해 주세요.</p></div>
<div class="mp-stat mp-stat--coin"><div class="mp-stat__label"><?=mg_icon('coin')?> 파이어 마일리지</div><div class="mp-stat__number"><?=number_format($balance)?><small>마일리지</small></div><p class="mp-stat__note">월별 구독 리워드</p></div>
<div class="mp-stat"><div class="mp-stat__label"><?=mg_icon('users')?> 함께 관리하는 유저</div><div class="mp-stat__number"><?=$accepted?><small>명</small></div><p class="mp-stat__note">새로운 요청 <?=$pending?>건</p></div></section>
<section class="mp-panel"><div class="mp-panel__head"><h2>연결 요청 · 담당 유저<span class="mp-count"><?=count($rows)?></span></h2><p>요청을 수락하면 유저 현황을 조회할 수 있어요.</p></div>
<?php if(!$rows): ?><div class="mp-empty"><span><?=mg_icon('users')?></span><h3>새로운 연결을 기다리고 있어요</h3><p>건물관리자가 내 코드를 등록하면 여기에 표시됩니다.</p></div>
<?php else: ?><ul class="mp-rows"><?php foreach($rows as $uid=>$m):$s=mg_link_status($uid,$m,$state); ?><li class="mp-row"><span class="mc-avatar"><?=mg_icon('users')?></span><div class="mp-row__info"><h3><?=mg_e($m['nickname']??$uid)?></h3><p><?=mg_e($uid)?> · <?=mg_e(substr($m['manager_requested_at']??$m['referral_at']??'',0,10))?> 요청</p></div><span class="mc-status mc-status--<?=mg_e($s)?>"><i></i><?=mg_e($labels[$s]??$s)?></span><div class="mp-row__actions">
<?php if($s==='pending'){mg_action_form($uid,$m,'accepted','수락하기','portal','primary');mg_action_form($uid,$m,'rejected','거절');}elseif($s==='accepted'){ ?><a class="mc-button mc-button--primary" href="/manager_view.php?uid=<?=rawurlencode($uid)?>">유저 현황 <?=mg_icon('arrow')?></a><?php mg_action_form($uid,$m,'revoked','연결 해제','portal','text');} ?>
</div></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="mp-panel"><div class="mp-panel__head"><h2>파이어 마일리지 내역</h2><p>1마일리지 = 1원 · 첫 시작 혜택과 월 기록 리워드</p></div><?php if(!$ledger): ?><div class="mp-empty"><span><?=mg_icon('coin')?></span><h3>첫 번째 파이어 마일리지를 기다리고 있어요</h3><p>실결제 구독과 월 업무수행기록 조건을 충족하면 마일리지가 적립됩니다.</p></div><?php else: ?><div class="mp-scroll"><table class="mp-ledger"><thead><tr><th>유저</th><th>적립일</th><th>적립 내역</th></tr></thead><tbody><?php foreach($ledger as $uid=>$r): ?><tr><td><?=mg_e($r['user']??$uid)?></td><td><?=mg_e(substr($r['at'],0,10))?></td><td><?=empty($r['reversed'])?'+'.number_format(mr_mileage($r)).'마일리지':'전액 환불 · 회수됨'?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<p class="mp-footer-note">첫 시작 혜택은 적용 이후 첫 실결제 구독자 5명까지 배정됩니다. 소방시설 현황 확인·저장 후 유저가 첫 월 업무기록을 저장하면 10,000마일리지, 이후 작성 월마다 1,000마일리지씩 최대 11회 적립됩니다. 첫 구독월을 포함한 12개월 내 최대 21,000마일리지이며 미작성 월은 이월하지 않습니다. 6번째 유저·기존 결제·다음 구독부터는 작성 월마다 1,500마일리지입니다. 테스트 결제·빈 기록·과거 월 소급 작성은 제외되며 같은 월 반복 저장·재구독으로 중복 지급하지 않습니다. 환불·탈퇴·연결 해제로 혜택 자리가 다시 생기지 않으며, 전액 환불 시 해당 결제의 적립분을 회수합니다. 1마일리지 = 1원이며 출금은 관리자 확인 후 송금합니다.</p>
<?php endif; ?></main></div><script src="/manager.js?v=2" defer></script><?php require_once __DIR__.'/manager_quickmemo.php'; ?></body></html>
