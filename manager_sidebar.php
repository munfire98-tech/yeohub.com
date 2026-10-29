<?php
declare(strict_types=1);
require_once __DIR__.'/manager_ui.php';
require_once __DIR__.'/manager_payout_common.php';
require_once __DIR__.'/manager_addresses_common.php';
function ms_data(): array {
    $uid=mg_uid();$members=mg_members();$me=$members[$uid]??[];
    if($uid===''||!mg_active($me,'agency'))return ['available'=>false];
    if(empty($me['manager_code'])){
        mg_member_tx(function(array &$m)use($uid){
            if(!mg_active($m[$uid]??[],'agency'))throw new RuntimeException('파트너 정보를 확인해 주세요.');
            if(empty($m[$uid]['manager_code']))$m[$uid]['manager_code']=mg_code($m);
        });$members=mg_members();$me=$members[$uid];
    }
    mp_migrate_rate();mr_sync_manager($uid);
    $rows=[];
    foreach($members as $id=>$member){
        if(!is_array($member)||!mg_active($member,'building'))continue;
        if(mg_connection_manager($member,$members)===$uid)$rows[(string)$id]=$member;

    }
    $state=mg_read(mg_state_file());$ledger=[];$balance=0;$pending=0;$accepted=0;
    foreach($state['rewards']??[] as $id=>$r){
        if(($r['manager']??'')!==$uid||($r['manager_created']??'')!==(string)($me['created']??''))continue;
        $ledger[(string)$id]=$r;
    }
    foreach($rows as $id=>&$row){
        $row['_status']=mg_link_status($id,$row,$state);$row['_building']='';$row['_address']='';
        if($row['_status']==='pending')$pending++;
        if(in_array($row['_status'],['accepted','pending'],true)){
            if($row['_status']==='accepted')$accepted++;
            if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id))continue;
            $bi=mg_read(__DIR__.'/data/building/'.$id.'/info.json');
            $row['_building']=trim((string)($bi['name']??''));$row['_address']=trim((string)($bi['address']??''));
        }
        if($row['_status']==='accepted')foreach($state['address_book']??[] as $a){
            if(ma_owned($a,$uid,$members)&&($a['linked_uid']??'')===$id&&ma_linked($a,$uid,$members,$state)){
                if($row['_address']==='')$row['_address']=$a['address'];
                if($row['_building']==='')$row['_building']=$a['name'];break;
            }
        }
    }unset($row);
    uasort($rows,static function($a,$b){$r=['pending'=>0,'accepted'=>1,'rejected'=>2,'revoked'=>3];return ($r[$a['_status']]??4)<=>($r[$b['_status']]??4);});
    $balance=mp_balance($state,$uid,$me)['available'];
    return ['available'=>true,'me'=>$me,'rows'=>$rows,'ledger'=>$ledger,'balance'=>$balance,'pending'=>$pending,'accepted'=>$accepted,'starter'=>mr_starter_summary($state,$uid),'preregistered'=>ma_available($uid,$members,$state)];
}
function ms_action(string $uid,array $m,string $action,string $label): void {
    if($action==='accepted'){
      $url='/manager_addresses.php?request='.rawurlencode($uid).'&key='.rawurlencode(mg_link_key($uid,$m));
      ?><a class="ms-button ms-button--primary" href="<?=mg_e($url)?>" data-address-open>연결 검토 →</a><?php return;
    }
    ?><form action="/manager_portal.php" method="post"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="target" value="<?=mg_e($uid)?>"><input type="hidden" name="request_key" value="<?=mg_e(mg_link_key($uid,$m))?>"><input type="hidden" name="return_to" value="clients"><button class="ms-button" name="decision" value="<?=mg_e($action)?>"><?=mg_e($label)?></button></form><?php
}
function ms_render(array $data): void {
    if(empty($data['available'])){echo '<div class="ms-empty">파트너 계정으로 로그인하면 담당 유저를 확인할 수 있습니다.</div>';return;}
    $labels=['pending'=>'수락 대기','accepted'=>'연결됨','rejected'=>'거절됨','revoked'=>'연결 해제'];
    ?><div class="ms-content" id="ms-users" role="tabpanel" aria-labelledby="ms-tab-users">
    <div class="ms-search"><label class="ms-sr" for="ms-search">담당 유저 검색</label><input id="ms-search" type="search" placeholder="이름 · 건물 · 주소 검색" autocomplete="off"></div>

    <div class="ms-totals"><div><span>담당 유저</span><b><?=$data['accepted']?><small>명</small></b></div></div>
    <div class="ms-user-list"><?php if(!$data['accepted']): ?><div class="ms-empty">아직 연결된 담당 유저가 없습니다.<br>새 연결 요청은 워크스페이스 위 카드에서 확인하세요.</div><?php endif; ?>
    <?php foreach($data['rows'] as $uid=>$m):if($m['_status']!=='accepted')continue;$status=$m['_status'];$name=$m['_building']?:($m['nickname']??$uid); ?>
      <article class="ms-user <?=$status==='pending'?'ms-user--pending':''?>" data-ms-uid="<?=mg_e((string)$uid)?>" data-ms-status="<?=mg_e($status)?>" data-ms-name="<?=mg_e($m['_address'].' '.$name.' '.($m['nickname']??'').' '.$uid)?>"><div class="ms-user-head"><strong><?=mg_e($name)?></strong><span class="ms-badge ms-badge--<?=mg_e($status)?>"><?=mg_e($labels[$status]??$status)?></span></div><p><?=mg_e($m['nickname']??$uid)?> · <?=mg_e($uid)?></p>
      <?php if($m['_address']!==''): ?><p class="ms-address"><?=mg_e($m['_address'])?></p><?php endif; ?>
      <?php if($status==='accepted'): ?><p class="ms-approval-date" data-ms-approval>사용승인일 확인 중</p><?php endif; ?>
      <div class="ms-actions"><?php if($status==='pending'){ms_action($uid,$m,'accepted','수락');ms_action($uid,$m,'rejected','거절');}elseif($status==='accepted'){ ?><a class="ms-button ms-button--primary" href="/manager_view.php?uid=<?=rawurlencode($uid)?>">유저 화면</a><button type="button" class="ms-button" data-ms-map="<?=mg_e($uid)?>">지도</button><details class="ms-more"><summary aria-label="연결 관리">···</summary><?php ms_action($uid,$m,'revoked','연결 해제'); ?></details><?php } ?></div></article>
    <?php endforeach; ?><p class="ms-empty" data-ms-no-results hidden>조건에 맞는 담당 유저가 없습니다.</p></div>
</div>
    <div class="ms-content" id="ms-months" role="tabpanel" aria-labelledby="ms-tab-months" hidden>
      <div class="ms-filterbar" id="manager-approval-panel" aria-label="사용승인월 필터"><div class="ms-filterline"><strong>사용승인월</strong><div data-approval-months class="ma-months"></div></div><span data-approval-status role="status" class="ms-filter-status">사용승인일 확인 중</span></div>
      <div data-month-results></div>
    </div>
    <div class="ms-content" id="ms-help" role="tabpanel" aria-labelledby="ms-tab-help" hidden>
      <div class="ms-notification-head"><strong>작성 문의</strong><p>담당 유저가 요청한 작성 문의을 확인하세요.</p></div>
      <p class="ms-empty" data-help-empty>작성 문의을 확인하고 있습니다.</p>
    </div>
    <div class="ms-content" id="ms-notifications" role="tabpanel" aria-labelledby="ms-tab-notifications" hidden>
      <div class="ms-notification-head"><strong>알림</strong><p>구독 시작과 연결 해제 등 변경 사항을 확인하세요.</p></div>
      <p class="ms-empty" data-notices-empty hidden>새로운 알림이 없습니다.</p>
      <div id="manager-disconnect-notices" aria-live="polite"></div>
    </div>
    <?php
}
function ms_render_wallet(array $data):void {if(empty($data['available']))return;$starter=$data['starter']??['reserved'=>0,'limit'=>5,'rewarded'=>0,'remaining'=>5]; ?>
    <dialog id="manager-wallet-dialog" aria-labelledby="manager-wallet-title"><div class="wallet-dialog-head"><h2 id="manager-wallet-title">파이어 마일리지</h2><button type="button" data-wallet-close aria-label="닫기">×</button></div><div class="wallet-dialog-body"><section class="ms-help-reward" aria-labelledby="ms-help-reward-title"><span class="ms-help-reward-label">파이어 마일리지</span><h3 id="ms-help-reward-title">도움 기록이 보상으로 이어집니다</h3><p>PRO 구독 인원들이 업무수행을 모두 완료하면<br>마일리지가 쌓입니다.</p><div class="ms-help-example"><div><span>PRO 50명 · 월 예상 적립</span><strong>PRO 구독자 50명 모두 기록 완료</strong></div><b><?=number_format(50 * MR_MONTHLY_MILEAGE)?><small>마일리지 / 월</small></b><span>50명 × <?=number_format(MR_MONTHLY_MILEAGE)?>마일리지</span></div><small>전체 완료를 기다리지 않고, 조건을 충족한 사용자별로 적립됩니다.<br>실결제 구독·소방시설 현황 저장·실제 월 기록 기준이며, 첫 5명 혜택은 아래 별도 기준을 적용합니다. 예상 금액은 적립 잔액이 아닙니다.</small></section><div class="ms-wallet"><span><?=mg_icon('coin')?> 파이어 마일리지</span><strong><?=number_format($data['balance'])?><small>마일리지</small></strong><p>출금 가능한 마일리지</p></div><section class="ms-starter" data-starter-progress aria-label="첫 시작 리워드 현황"><div><span>첫 구독자 5명 혜택</span><strong><?=$starter['reserved']?> / 5명</strong></div><p>첫 기록 완료 시 <b>10,000마일리지</b></p><div class="ms-starter-dots" aria-hidden="true"><?php for($i=0;$i<5;$i++):?><span data-starter-dot class="<?=$i<$starter['reserved']?'is-used':''?>"><?=$i+1?></span><?php endfor;?></div><small data-starter-note>혜택 배정 <?=$starter['reserved']?>명 · 첫 적립 이력 <?=$starter['rewarded']?>명 · 남은 자리 <?=$starter['remaining']?>명</small><small>시설 현황 저장 + 유저의 실제 월 기록이 필요합니다.</small></section><section id="manager-payout" aria-label="계좌 및 출금"><h3>출금 신청</h3><p>1마일리지 = 1원 · 관리자 확인 후 직접 송금</p><p data-payout-balance>출금 가능 잔액 확인 중</p><p data-payout-deficit></p><p data-account-saved></p><details><summary>정산 계좌 등록 · 변경</summary><form data-account-form><input type="hidden" name="action" value="account"><label>은행명<input name="bank" maxlength="40" required autocomplete="off"></label><label>예금주<input name="holder" maxlength="60" required autocomplete="off"></label><label>계좌번호<input name="number" inputmode="numeric" maxlength="30" required autocomplete="off"></label><p>계좌번호와 예금주를 정확히 입력해 주세요. 계좌 실명 검증은 제공하지 않습니다. 변경된 계좌는 다음 신청부터 적용됩니다.</p><button>계좌 저장</button></form></details><form data-payout-form><input type="hidden" name="action" value="request"><label>신청 마일리지<input name="mileage" type="number" inputmode="numeric" min="1" step="1" placeholder="예: 5000" required><input type="hidden" name="unit" value="mileage-v1"></label><button type="button" class="payout-all" data-payout-all>전액 입력</button><p>신청 금액 <strong data-payout-amount>0원</strong></p><button type="submit" disabled>출금 신청</button></form><p data-payout-status role="status"></p><h3>사용자별 도움 기록</h3><div data-reward-plans></div><h3>출금 내역</h3><div data-payout-history></div></section><div class="ms-ledger-title">도움으로 쌓인 마일리지</div>
    <?php if(!$data['ledger']): ?><div class="ms-empty">아직 적립 내역이 없습니다.</div><?php endif; ?>
    <?php foreach($data['ledger'] as $uid=>$r): ?><div class="ms-ledger-row"><div><strong><?=mg_e($r['user']??$uid)?></strong><small><?=mg_e(substr($r['at'],0,10))?> · <?=mg_e(isset($r['worklog_month'])?$r['worklog_month'].' · '.mr_reward_label($r):'기존 적립')?></small></div><span><?=empty($r['reversed'])?'+'.number_format(mr_mileage($r)).'마일리지':'회수됨'?></span></div><?php endforeach; ?>
    <p class="ms-policy">첫 시작 혜택은 <?=mg_e((new DateTimeImmutable(MR_STARTER_START))->format("Y.m.d H:i"))?> 이후 첫 실결제 구독자 5명까지 배정됩니다. 소방시설 현황 확인·저장 후 유저가 첫 월 업무기록을 저장하면 10,000마일리지, 이후 작성 월마다 1,000마일리지씩 최대 11회 적립됩니다. 첫 구독월을 포함한 12개월 내 최대 21,000마일리지이며 미작성 월은 이월하지 않습니다. 6번째 유저·기존 결제·다음 구독부터는 작성 월마다 1,500마일리지입니다. 테스트 결제·빈 기록·과거 월 소급 작성은 제외되며 같은 월 반복 저장·재구독으로 중복 지급하지 않습니다. 환불·탈퇴·연결 해제로 혜택 자리가 다시 생기지 않으며, 전액 환불 시 해당 결제의 적립분을 회수합니다. 1마일리지 = 1원이며 출금은 관리자 확인 후 송금합니다.</p></div></dialog><?php
}

function ms_render_code(array $data):void {if(empty($data['available']))return; ?>
    <div class="ms-code"><span>내 파트너 코드</span><div><code data-ms-code><?=mg_e($data['me']['manager_code'])?></code><button type="button" data-ms-copy aria-label="파트너 코드 복사"><?=mg_icon('copy')?><span>복사</span></button></div><small data-ms-copy-note role="status">건물관리자에게 이 코드를 전달하세요.</small></div>
<?php }
