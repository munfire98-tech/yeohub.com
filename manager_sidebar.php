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
            if(!mg_active($m[$uid]??[],'agency'))throw new RuntimeException('매니저 정보를 확인해 주세요.');
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
        $ledger[(string)$id]=$r;if(empty($r['reversed']))$balance++;
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
    return ['available'=>true,'me'=>$me,'rows'=>$rows,'ledger'=>$ledger,'balance'=>$balance,'pending'=>$pending,'accepted'=>$accepted,'preregistered'=>ma_available($uid,$members,$state)];
}
function ms_action(string $uid,array $m,string $action,string $label): void {
    if($action==='accepted'){
      $url='/manager_addresses.php?request='.rawurlencode($uid).'&key='.rawurlencode(mg_link_key($uid,$m));
      ?><a class="ms-button ms-button--primary" href="<?=mg_e($url)?>" data-address-open>연결 검토 →</a><?php return;
    }
    ?><form action="/manager_portal.php" method="post"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="target" value="<?=mg_e($uid)?>"><input type="hidden" name="request_key" value="<?=mg_e(mg_link_key($uid,$m))?>"><input type="hidden" name="return_to" value="clients"><button class="ms-button" name="decision" value="<?=mg_e($action)?>"><?=mg_e($label)?></button></form><?php
}
function ms_render(array $data): void {
    if(empty($data['available'])){echo '<div class="ms-empty">매니저 계정으로 로그인하면 담당 유저를 확인할 수 있습니다.</div>';return;}
    $labels=['pending'=>'수락 대기','accepted'=>'연결됨','rejected'=>'거절됨','revoked'=>'연결 해제'];
    ?><div class="ms-content" id="ms-users" role="tabpanel" aria-labelledby="ms-tab-users">
    <div class="ms-search"><label class="ms-sr" for="ms-search">담당 유저 검색</label><input id="ms-search" type="search" placeholder="이름 · 건물 · 주소 검색" autocomplete="off"></div>

    <div class="ms-totals"><div><span>담당 유저</span><b><?=$data['accepted']?><small>명</small></b></div></div>
    <div class="ms-user-list"><?php if(!$data['accepted']): ?><div class="ms-empty">아직 연결된 담당 유저가 없습니다.<br>새 연결 요청은 워크스페이스 위 카드에서 확인하세요.</div><?php endif; ?>
    <?php foreach($data['rows'] as $uid=>$m):if($m['_status']!=='accepted')continue;$status=$m['_status'];$name=$m['_building']?:($m['nickname']??$uid); ?>
      <article class="ms-user <?=$status==='pending'?'ms-user--pending':''?>" data-ms-uid="<?=mg_e((string)$uid)?>" data-ms-status="<?=mg_e($status)?>" data-ms-name="<?=mg_e($m['_address'].' '.$name.' '.($m['nickname']??'').' '.$uid)?>"><div class="ms-user-head"><strong><?=mg_e($name)?></strong><span class="ms-badge ms-badge--<?=mg_e($status)?>"><?=mg_e($labels[$status]??$status)?></span></div><p><?=mg_e($m['nickname']??$uid)?> · <?=mg_e($uid)?></p>
      <?php if($m['_address']!==''): ?><p class="ms-address"><?=mg_e($m['_address'])?></p><?php endif; ?>
      <?php if($status==='accepted'): ?><p class="ms-approval-date" data-ms-approval>사용승인일 확인 중</p><?php endif; ?>
      <div class="ms-actions"><?php if($status==='pending'){ms_action($uid,$m,'accepted','수락');ms_action($uid,$m,'rejected','거절');}elseif($status==='accepted'){ ?><a class="ms-button ms-button--primary" href="/manager_view.php?uid=<?=rawurlencode($uid)?>" target="_blank" rel="noopener">유저 화면</a><button type="button" class="ms-button" data-ms-map="<?=mg_e($uid)?>">지도</button><details class="ms-more"><summary aria-label="연결 관리">···</summary><?php ms_action($uid,$m,'revoked','연결 해제'); ?></details><?php } ?></div></article>
    <?php endforeach; ?><p class="ms-empty" data-ms-no-results hidden>조건에 맞는 담당 유저가 없습니다.</p></div>
</div>
    <div class="ms-content" id="ms-months" role="tabpanel" aria-labelledby="ms-tab-months" hidden>
      <div class="ms-filterbar" id="manager-approval-panel" aria-label="사용승인월 필터"><div class="ms-filterline"><strong>사용승인월</strong><div data-approval-months class="ma-months"></div></div><span data-approval-status role="status" class="ms-filter-status">사용승인일 확인 중</span></div>
      <div data-month-results></div>
    </div>
    <div class="ms-content" id="ms-help" role="tabpanel" aria-labelledby="ms-tab-help" hidden>
      <div class="ms-notification-head"><strong>작성 요청</strong><p>담당 유저가 요청한 작성 도움을 확인하세요.</p></div>
      <p class="ms-empty" data-help-empty>작성 요청을 확인하고 있습니다.</p>
    </div>
    <div class="ms-content" id="ms-notifications" role="tabpanel" aria-labelledby="ms-tab-notifications" hidden>
      <div class="ms-notification-head"><strong>알림</strong><p>구독 시작과 연결 해제 등 변경 사항을 확인하세요.</p></div>
      <p class="ms-empty" data-notices-empty hidden>새로운 알림이 없습니다.</p>
      <div id="manager-disconnect-notices" aria-live="polite"></div>
    </div>
    <?php
}
function ms_render_wallet(array $data):void {if(empty($data['available']))return; ?>
    <dialog id="manager-wallet-dialog" aria-labelledby="manager-wallet-title"><div class="wallet-dialog-head"><h2 id="manager-wallet-title">파이어코인</h2><button type="button" data-wallet-close aria-label="닫기">×</button></div><div class="wallet-dialog-body"><div class="ms-wallet"><span><?=mg_icon('coin')?> 파이어코인</span><strong><?=$data['balance']?><small>개</small></strong><p>출금 가능한 코인</p></div><section id="manager-payout" aria-label="계좌 및 출금"><h3>출금 신청</h3><p>1코인 = 1,500원 · 관리자 확인 후 직접 송금</p><p data-payout-balance>출금 가능 잔액 확인 중</p><p data-payout-deficit></p><p data-account-saved></p><details><summary>정산 계좌 등록 · 변경</summary><form data-account-form><input type="hidden" name="action" value="account"><label>은행명<input name="bank" maxlength="40" required autocomplete="off"></label><label>예금주<input name="holder" maxlength="60" required autocomplete="off"></label><label>계좌번호<input name="number" inputmode="numeric" maxlength="30" required autocomplete="off"></label><p>계좌번호와 예금주를 정확히 입력해 주세요. 계좌 실명 검증은 제공하지 않습니다. 변경된 계좌는 다음 신청부터 적용됩니다.</p><button>계좌 저장</button></form></details><form data-payout-form><input type="hidden" name="action" value="request"><label>신청 코인 수<input name="coins" type="number" min="1" step="1" value="1" required></label><p>신청 금액 <strong data-payout-amount>1,500원</strong></p><button disabled>출금 신청</button></form><p data-payout-status role="status"></p><h3>월별 리워드</h3><div data-reward-plans></div><h3>출금 내역</h3><div data-payout-history></div></section><div class="ms-ledger-title">적립 내역</div>
    <?php if(!$data['ledger']): ?><div class="ms-empty">아직 적립 내역이 없습니다.</div><?php endif; ?>
    <?php foreach($data['ledger'] as $uid=>$r): ?><div class="ms-ledger-row"><div><strong><?=mg_e($r['user']??$uid)?></strong><small><?=mg_e(substr($r['at'],0,10))?> · <?=(int)($r['installment']??1)?>/12회</small></div><span><?=empty($r['reversed'])?'+1':'회수됨'?></span></div><?php endforeach; ?>
    <p class="ms-policy">연간 59,000원 실결제 시 1개, 이후 매월 1개씩 총 12개가 적립됩니다. 1개는 1,500원이며 테스트 결제는 제외됩니다. 연결 해제·구독 환불 시 이후 적립이 중단되고, 전액 환불 시 해당 결제의 적립분이 회수됩니다.</p></div></dialog><?php
}

function ms_render_code(array $data):void {if(empty($data['available']))return; ?>
    <div class="ms-code"><span>내 매니저 코드</span><div><code data-ms-code><?=mg_e($data['me']['manager_code'])?></code><button type="button" data-ms-copy aria-label="매니저 코드 복사"><?=mg_icon('copy')?><span>복사</span></button></div><small data-ms-copy-note role="status">건물관리자에게 이 코드를 전달하세요.</small></div>
<?php }
