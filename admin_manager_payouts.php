<?php
declare(strict_types=1);
require_once __DIR__.'/admin_manager_common.php';
am_require_admin();
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$csrf=$_SESSION['csrf'];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('새로고침 후 다시 시도해 주세요.');
  foreach(['id','decision','note','sent'] as $key)if(isset($_POST[$key])&&!is_string($_POST[$key]))throw new RuntimeException('요청 형식을 확인해 주세요.');
  if(($_POST['decision']??'')==='paid'&&($_POST['sent']??'')!=='1')throw new RuntimeException('실제 송금 완료 여부를 확인해 주세요.');
  mp_process((string)($_SESSION['member_id']??'site-admin'),(string)($_POST['id']??''),(string)($_POST['decision']??''),trim((string)($_POST['note']??'')));
  $_SESSION['payout_admin_notice']='출금 신청을 처리했습니다.';
 }catch(Throwable $e){$_SESSION['payout_admin_notice']=$e instanceof RuntimeException?$e->getMessage():'처리하지 못했습니다. 잠시 후 다시 시도해 주세요.';}
 header('Location: /admin_manager_payouts.php#payout-section',true,303);exit;
}
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);exit;}
$notice=(string)($_SESSION['payout_admin_notice']??'');unset($_SESSION['payout_admin_notice']);session_write_close();
$error='';$managerRows=[];$members=[];$state=[];$rows=[];
$summary=array_fill_keys(['preregistered','unlinked','clients','pro','pro_live','pro_test','held','available','deficit'],0);
$totals=['pending'=>0,'paid'=>0,'rejected'=>0];$pendingAmount=0;
try{
 $members=mg_members();$state=mg_read(mg_state_file());$managerRows=am_manager_rows($members,$state);
 foreach($managerRows as $m){foreach(['preregistered','unlinked','clients','pro','pro_live','pro_test'] as $k)$summary[$k]+=$m[$k];foreach(['held','available','deficit'] as $k)$summary[$k]+=$m['balance'][$k];}
 $rows=array_values(array_filter($state['payouts']??[],'is_array'));
 usort($rows,static fn($a,$b)=>(($a['status']??'')!=='pending')<=>(($b['status']??'')!=='pending')?:strcmp((string)($b['at']??''),(string)($a['at']??'')));
 foreach($rows as $r){$s=$r['status']??'';if(isset($totals[$s]))$totals[$s]++;if($s==='pending')$pendingAmount+=(int)($r['amount']??0);}
}catch(Throwable $e){http_response_code(503);$error='현황을 불러오지 못했습니다. 새로고침해 주세요. 데이터가 확인되기 전에는 출금 처리를 진행하지 마세요.';}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>파트너 관리 | 소방계획서.com</title><link rel="stylesheet" href="/admin_managers.css?v=20261003-1"></head>
<body><nav class="am-nav"><a class="am-brand" href="/index.php">소방계획서.com <span>관리자</span></a><div><a href="/admin_members.php">회원 관리</a><a href="/admin_accounts.php">관리자 계정</a></div></nav>
<main class="am-page"><header class="am-heading"><div><p class="am-eyebrow">관리자 워크스페이스</p><h1>파트너 관리</h1><p>파트너별 건물 운영 현황과 파이어 마일리지, 출금 신청을 한곳에서 확인하세요.</p></div><a class="am-button" href="/admin_manager_payouts.php">새로고침 ↻</a></header>
<?php if($notice!==''):?><p class="am-notice" role="status"><?=mg_e($notice)?></p><?php endif;?>
<?php if($error!==''):?><p class="am-notice am-notice--error" role="alert"><?=mg_e($error)?></p><?php else:?>
<div class="am-stats">
 <article><span>등록 파트너</span><strong><?=number_format(count($managerRows))?><small>명</small></strong><p>현재 회원 목록 기준</p></article>
 <article><span>사전등록 건물</span><strong><?=number_format($summary['preregistered'])?><small>곳</small></strong><p>연결 전 <?=number_format($summary['unlinked'])?>곳 · 삭제 제외</p></article>
 <article><span>파트너 연결</span><strong><?=number_format($summary['clients'])?><small>명</small></strong><p>수락 완료 · PRO 이용 포함</p></article>
 <article><span>PRO 이용</span><strong><?=number_format($summary['pro'])?><small>명</small></strong><p>실결제 <?=$summary['pro_live']?>명 · 테스트 <?=$summary['pro_test']?>명</p></article>
 <article><span>보유 파이어 마일리지</span><strong class="am-money"><?=am_money($summary['held'])?></strong><p>출금 신청 중 포함 · 지급 완료 제외</p></article>
 <article class="am-stat--accent"><span>출금 처리 대기</span><strong><?=$totals['pending']?><small>건</small></strong><p><?=number_format($pendingAmount)?>원 신청 중</p></article>
</div>
<section class="am-panel" aria-labelledby="manager-title"><div class="am-section-head"><div><h2 id="manager-title">파트너별 현황</h2><p>파트너 화면 보기에서 해당 파트너의 지도·건물·방문일정을 열람합니다.</p></div><span id="manager-count" class="am-muted" role="status"></span></div>
<div class="am-filters"><input id="manager-search" type="search" aria-label="파트너 검색" placeholder="파트너 이름 · 아이디 · 코드 검색"><select id="manager-filter" aria-label="파트너 필터"><option value="all">전체 파트너</option><option value="pending">출금 신청 중</option><option value="pro">PRO 이용 유저 있음</option><option value="balance">출금 가능 마일리지 있음</option><option value="deficit">정산 확인 필요</option></select><select id="manager-sort" aria-label="파트너 정렬"><option value="order">최근 가입순</option><option value="preregistered">사전등록 많은 순</option><option value="clients">연결 유저 많은 순</option><option value="pro">PRO 이용 많은 순</option><option value="held">보유 마일리지 많은 순</option></select></div>
<div class="am-table-wrap"><table class="am-manager-table"><thead><tr><th>파트너</th><th>사전등록</th><th>파트너 연결</th><th>PRO 이용</th><th>파이어 마일리지</th><th>출금 신청</th><th>관리</th></tr></thead><tbody id="manager-table">
<?php $i=0;foreach($managerRows as $m):$b=$m['balance'];?><tr data-manager-row data-order="<?=$i++?>" data-search="<?=mg_e($m['uid'].' '.$m['name'].' '.$m['code'])?>" data-preregistered="<?=$m['preregistered']?>" data-clients="<?=$m['clients']?>" data-pro="<?=$m['pro']?>" data-held="<?=$b['held']?>" data-pending="<?=$m['pending_count']?>" data-available="<?=$b['available']?>" data-deficit="<?=$b['deficit']?>">
<td data-label="파트너"><strong class="am-manager-name"><?=mg_e($m['name'])?></strong><small><?=mg_e($m['uid'])?> · <?=mg_e(am_status($m['status']))?></small><code><?=mg_e($m['code']?:'코드 미발급')?></code><small>가입 <?=mg_e(substr($m['created'],0,10)?:'미등록')?></small></td>
<td data-label="사전등록"><b><?=$m['preregistered']?>곳</b><small>연결 전 <?=$m['unlinked']?>곳</small></td>
<td data-label="파트너 연결"><b><?=$m['clients']?>명</b><small>미구독 <?=$m['connected_only']?>명</small><?php if($m['requests']):?><small>수락 대기 <?=$m['requests']?>명</small><?php endif;?></td>
<td data-label="PRO 이용"><b><?=$m['pro']?>명</b><small>실결제 <?=$m['pro_live']?> · 테스트 <?=$m['pro_test']?></small></td>
<td data-label="파이어 마일리지"><b class="am-teal"><?=am_money($b['held'])?></b><small>출금 가능 <?=am_money($b['available'])?></small><small>첫 혜택 배정 <?=$m['starter']['reserved']?>/5명 · 첫 적립 이력 <?=$m['starter']['rewarded']?>명</small><small>지급 완료 <?=am_money($b['paid'])?></small><?php if($b['deficit']):?><small class="am-warning">정산 확인 <?=am_money($b['deficit'])?></small><?php endif;?></td>
<td data-label="출금 신청"><?php if($m['pending_count']):?><span class="am-badge am-badge--pending"><?=$m['pending_count']?>건 대기</span><small><?=number_format($m['pending_amount'])?>원</small><?php else:?><span class="am-muted">대기 없음</span><?php endif;?><small>계좌 <?=$m['account']?'등록됨':'미등록'?></small></td>
<td data-label="관리"><div class="am-row-actions"><?php if($m['status']==='active'):?><a class="am-button am-button--primary" href="/admin_manager_view.php?uid=<?=rawurlencode($m['uid'])?>">파트너 화면 보기 ↗</a><?php else:?><span class="am-muted">비활성 계정</span><?php endif;?><button class="am-button" type="button" data-manager-payouts="<?=mg_e($m['uid'])?>">출금 내역</button></div></td>
</tr><?php endforeach;?></tbody></table></div><p class="am-empty" id="manager-empty" hidden>조건에 맞는 파트너가 없습니다.</p>
<p class="am-footnote">사전등록은 연결 완료된 건물을 포함합니다. 파트너 연결은 PRO 이용을 포함한 수락 완료 인원입니다. 테스트 구독은 따로 표시하며 마일리지 지급 대상이 아닙니다. 화면 조회는 저장된 현황만 읽습니다.</p></section>
<section class="am-panel" id="payout-section" aria-labelledby="payout-title"><div class="am-section-head"><div><h2 id="payout-title">출금 신청</h2><p>실제 송금 후 지급 완료로 처리하세요. 이 화면에서 은행 송금은 실행되지 않습니다.</p></div><span class="am-muted">대기 <?=$totals['pending']?> · 지급 <?=$totals['paid']?> · 반려 <?=$totals['rejected']?></span></div>
<div class="am-filters"><input id="payout-search" type="search" aria-label="출금 신청 검색" placeholder="파트너 이름 · 아이디 · 신청번호 검색"><select id="payout-filter" aria-label="출금 상태"><option value="pending">처리 대기</option><option value="all">전체 신청</option><option value="paid">지급 완료</option><option value="rejected">반려</option></select><button type="button" class="am-button" id="payout-reset">전체 파트너</button></div><p id="payout-result" class="am-muted" role="status"></p>
<div class="am-payout-grid">
<?php foreach($rows as $r):$uid=(string)($r['manager']??'');$me=$members[$uid]??[];$pending=($r['status']??'')==='pending';$owned=mp_owned($r,$uid,$me);$blocked=!mg_active($me,'agency')||!$owned||mp_balance($state,$uid,$me)['deficit']>0;?>
<article class="am-payout" data-payout-row data-manager="<?=mg_e($uid)?>" data-status="<?=mg_e($r['status']??'')?>" data-search="<?=mg_e(($r['name']??'').' '.$uid.' '.($r['id']??'').' '.($me['nickname']??''))?>"><header><div><h3><?=mg_e($r['name']??$uid)?></h3><small><?=mg_e($uid)?><?=$owned?'':' · 이전 계정 내역'?></small></div><span class="am-badge <?=$pending?'am-badge--pending':''?>"><?=mg_e(am_payout_label($r['status']??''))?></span></header>
<strong class="am-payout-amount"><?=number_format((int)($r['amount']??0))?>원</strong><p class="am-muted">파이어 마일리지 출금 · 신청 <?=mg_e($r['at']??'')?></p>
<details class="am-account"><summary>송금 계좌 확인</summary><p><?=mg_e($r['account']['bank']??'')?> · <?=mg_e($pending?($r['account']['number']??''):mp_mask($r['account']['number']??''))?><br>예금주 <?=mg_e($r['account']['holder']??'')?></p></details>
<?php if($pending):?><?php if($blocked):?><p class="am-warning">회원 상태 또는 정산 부족을 확인해 주세요. 지급 완료 처리가 제한됩니다.</p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=mg_e($csrf)?>"><input type="hidden" name="id" value="<?=mg_e($r['id']??'')?>"><label class="am-field">처리 메모 / 반려 사유<input name="note" maxlength="200" placeholder="이체 확인번호 또는 반려 사유"></label><label class="am-check"><input type="checkbox" name="sent" value="1"> 계좌와 금액을 확인하고 실제 송금을 완료했습니다.</label><div class="am-row-actions am-row-actions--inline"><button class="am-button am-button--primary" name="decision" value="paid" <?=$blocked?'disabled':''?>>지급 완료 처리</button><button class="am-button" name="decision" value="rejected">반려 · 마일리지 반환</button></div></form>
<?php else:?><p class="am-muted">처리 <?=mg_e($r['processed_at']??'')?></p><p class="am-note"><?=mg_e($r['note']??'')?></p><?php endif;?></article>
<?php endforeach;?></div><p id="payout-empty" class="am-empty" hidden>해당 조건의 출금 신청이 없습니다.</p></section>
<?php endif;?></main><script src="/admin_managers.js?v=20261003-1" defer></script></body></html>
