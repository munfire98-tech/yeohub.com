<?php
declare(strict_types=1);
require_once __DIR__.'/admin_manager_common.php';
require_once __DIR__.'/manager_buildings_common.php';
am_require_admin();
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit('열람 화면에서는 저장할 수 없습니다.');}
session_write_close();
$uid=$_GET['uid']??'';
if(!is_string($uid)||!sa_valid_uid($uid)){http_response_code(400);exit('파트너를 선택해 주세요.');}
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul'));
$month=$_GET['month']??$now->format('Y-m');
if(!is_string($month)||!preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/D',$month)){$month=$now->format('Y-m');}
$tab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],['map','visits','rewards','requests'],true)?$_GET['tab']:'map';
$error='';$buildings=[];$visits=[];$days=[];$rewards=[];$requests=[];$managers=[];$me=[];$summary=[];
try{
 $members=mg_members();$me=$members[$uid]??[];
 if(!mg_active($me,'agency')){http_response_code(404);exit('현재 이용 가능한 파트너가 아닙니다. 파트너 관리에서 상태를 확인해 주세요.');}
 $state=mg_read(mg_state_file());$summaries=am_manager_rows($members,$state);$summary=$summaries[$uid];
 foreach($summaries as $id=>$row)if($row['status']==='active')$managers[$id]=$row;
 $buildings=mb_manager_rows($uid,$members,$state)['buildings'];
 $calendar=mg_read(__DIR__.'/data/manager_visits/'.hash('sha256',$uid.'|'.(string)($me['created']??'')).'.php');
 foreach($calendar['visits']??[] as $v){
  if(!is_array($v)||substr((string)($v['date']??''),0,7)!==$month||($v['status']??'')==='cancelled')continue;
  $visits[]=array_intersect_key($v,array_flip(['id','uid','name','address','date','time','status','visited_date','memo']));
 }
 foreach($calendar['days']??[] as $date=>$d)if(is_array($d)&&substr((string)$date,0,7)===$month)$days[(string)$date]=array_intersect_key($d,array_flip(['holiday','memo']));
 foreach($state['rewards']??[] as $r)if(is_array($r)&&mp_owned($r,$uid,$me))$rewards[]=['at'=>(string)($r['at']??''),'user'=>(string)($r['user']??''),'month'=>(string)($r['worklog_month']??''),'mileage'=>mr_mileage($r),'reason'=>mr_reward_label($r),'reversed'=>!empty($r['reversed'])];
 usort($rewards,static fn($a,$b)=>strcmp($b['at'],$a['at']));$rewardTotal=count($rewards);$rewards=array_slice($rewards,0,100);
 foreach($members as $id=>$member){
  if(!is_array($member)||!mg_active($member,'building')||mg_connection_manager($member,$members)!==$uid||mg_link_status((string)$id,$member,$state)!=='pending')continue;
  $requests[]=['name'=>(string)($member['nickname']??$id),'uid'=>(string)$id,'at'=>(string)($member['manager_requested_at']??$member['referral_at']??'')];
 }
}catch(Throwable $e){http_response_code(503);$error='파트너 현황을 불러오지 못했습니다. 새로고침해 주세요.';}
$name=trim((string)($me['nickname']??''))?:$uid;
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=mg_e($name)?> · 파트너 화면 | 관리자</title><link rel="stylesheet" href="/admin_managers.css?v=20261003-1"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous" defer></script></head>
<body><nav class="am-nav"><a class="am-brand" href="/admin_manager_payouts.php">← 파트너 관리</a><span class="am-view-label">관리자 열람</span></nav>
<main class="am-page am-view"><header class="am-heading"><div><p class="am-eyebrow">파트너 화면</p><h1><?=mg_e((string)preg_replace('/(?:\s*(?:매니저|파트너))+$/u','',$name))?> <span class="am-name-suffix">파트너</span></h1><p><?=mg_e($uid)?> · <?=mg_e($me['manager_code']??'코드 미발급')?> · 관리자로 로그인한 상태에서 확인하고 있습니다.</p></div><form method="get" class="am-switch"><label for="manager-switch">다른 파트너</label><select name="uid" id="manager-switch"><?php foreach($managers as $id=>$m):?><option value="<?=mg_e($id)?>" <?=$id===$uid?'selected':''?>><?=mg_e($m['name'])?></option><?php endforeach;?></select><button class="am-button" type="submit">보기</button></form></header>
<?php if($error):?><p class="am-notice am-notice--error" role="alert"><?=mg_e($error)?></p><?php else:?>
<div class="am-view-summary"><span>사전등록 <b><?=$summary['preregistered']?>곳</b></span><span>파트너 연결 <b><?=$summary['clients']?>명</b></span><span>PRO 이용 <b><?=$summary['pro']?>명</b></span><span>파이어 마일리지 <b><?=am_money($summary['balance']['held'])?></b></span><a href="/admin_manager_payouts.php?manager=<?=rawurlencode($uid)?>#payout-section">출금 대기 <b><?=$summary['pending_count']?>건</b></a></div>
<div class="am-tabs" role="tablist" aria-label="파트너 화면 구성">
<?php foreach(['map'=>'지도 · 건물','visits'=>'방문일정','rewards'=>'마일리지 내역','requests'=>'연결 요청 '.$summary['requests']] as $id=>$label):?><button type="button" role="tab" id="tab-<?=$id?>" aria-controls="panel-<?=$id?>" aria-selected="<?=$tab===$id?'true':'false'?>" tabindex="<?=$tab===$id?'0':'-1'?>" data-tab="<?=$id?>"><?=mg_e($label)?></button><?php endforeach;?></div>
<section id="panel-map" role="tabpanel" aria-labelledby="tab-map" <?=$tab!=='map'?'hidden':''?>>
<div class="am-map-layout"><div class="am-map-card"><div class="am-section-head"><h2>담당 건물</h2><span class="am-muted" id="am-location-count"></span></div><div class="am-map-stage"><div id="am-map" aria-label="파트너 담당 건물 지도"></div><div class="am-map-filters" aria-label="건물 분류"><button type="button" data-kind="all" aria-pressed="true">전체 <b></b></button><button type="button" data-kind="pre" aria-pressed="false"><i class="am-pin am-pin--pre">P</i><span>사전등록</span> <b></b></button><button type="button" data-kind="connected" aria-pressed="false"><i class="am-pin am-pin--connected">C</i><span>파트너 연결</span> <b></b></button><button type="button" data-kind="pro" aria-pressed="false"><i class="am-pin am-pin--pro">S</i><span>PRO 이용</span> <b></b></button></div></div></div>
<aside class="am-workspace"><div class="am-section-head"><h2>내 워크스페이스</h2><span class="am-badge">열람</span></div><input id="am-building-search" type="search" placeholder="건물명 · 주소 검색" aria-label="건물 검색"><p class="am-muted" id="am-building-count" role="status"></p><div id="am-building-list"></div></aside></div><p class="am-footnote">파트너의 현재 지도와 같은 건물 자료를 표시합니다. 예시 건물은 집계하지 않으며 위치가 없는 건물도 목록에서 확인할 수 있습니다.</p></section>
<section id="panel-visits" class="am-panel" role="tabpanel" aria-labelledby="tab-visits" <?=$tab!=='visits'?'hidden':''?>><div class="am-section-head"><div><h2>방문일정</h2><p>날짜를 누르면 등록된 거래처와 메모를 확인할 수 있습니다.</p></div><form method="get" class="am-month-form"><input type="hidden" name="uid" value="<?=mg_e($uid)?>"><input type="hidden" name="tab" value="visits"><label for="am-month" class="am-sr">조회할 월</label><input type="month" id="am-month" name="month" value="<?=mg_e($month)?>" min="2000-01" max="2100-12"><button class="am-button" type="submit">조회</button></form></div><div class="am-weekdays" aria-hidden="true"><?php foreach(['일','월','화','수','목','금','토'] as $day):?><span><?=$day?></span><?php endforeach;?></div><div id="am-calendar" class="am-calendar"></div></section>
<section id="panel-rewards" class="am-panel" role="tabpanel" aria-labelledby="tab-rewards" <?=$tab!=='rewards'?'hidden':''?>><div class="am-section-head"><div><h2>파이어 마일리지 내역</h2><p>보유 <?=am_money($summary['balance']['held'])?> · 출금 가능 <?=am_money($summary['balance']['available'])?> · 신청 중 <?=number_format($summary['pending_amount'])?>원</p></div></div>
<div class="am-simple-table"><table><thead><tr><th>적립일</th><th>유저</th><th>업무기록 월</th><th>상태</th></tr></thead><tbody><?php foreach($rewards as $r):?><tr><td><?=mg_e(substr($r['at'],0,10))?></td><td><?=mg_e($r['user'])?></td><td><?=mg_e($r['month']?:'기존 적립')?><small><?=mg_e($r['reason'])?></small></td><td><?=$r['reversed']?'회수됨':'+'.number_format($r['mileage']).'마일리지'?></td></tr><?php endforeach;?></tbody></table></div><?php if(!$rewards):?><p class="am-empty">아직 적립 내역이 없습니다.</p><?php endif;?><p class="am-footnote">저장된 최근 <?=count($rewards)?>건 / 전체 <?=$rewardTotal?>건 · 첫 시작 혜택은 최초 10,000마일리지, 이후 월 기록마다 1,000마일리지입니다. 일반 월 기록은 1,500마일리지이며 테스트 결제는 제외됩니다.</p></section>
<section id="panel-requests" class="am-panel" role="tabpanel" aria-labelledby="tab-requests" <?=$tab!=='requests'?'hidden':''?>><div class="am-section-head"><h2>수락 대기 중인 연결 요청</h2></div><?php if(!$requests):?><p class="am-empty">대기 중인 연결 요청이 없습니다.</p><?php endif;?><?php foreach($requests as $r):?><article class="am-request"><div><strong><?=mg_e($r['name'])?></strong><p><?=mg_e($r['uid'])?> · <?=mg_e(substr($r['at'],0,10))?></p></div><span class="am-badge am-badge--pending">수락 대기</span></article><?php endforeach;?></section>
<p class="am-footnote">열람 화면에서 확인한 내용은 저장·수정되지 않습니다. 출금 처리는 관리자 파트너 관리 화면에서 진행하세요.</p>
<dialog id="am-detail" class="am-dialog"><div class="am-dialog-head"><h2 id="am-detail-title"></h2><button type="button" class="am-button" id="am-detail-close" aria-label="상세정보 닫기">닫기 ×</button></div><div id="am-detail-body"></div></dialog>
<script type="application/json" id="am-view-data"><?=am_json(['buildings'=>$buildings,'visits'=>$visits,'days'=>$days,'month'=>$month])?></script><script src="/admin_manager_view.js?v=20261015-partner-1" defer></script>
<?php endif;?></main></body></html>
