<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
function mg_icon(string $name): string {
    $paths = [
      'shield'=>'<path d="M12 3 4 6v6c0 4 5 8 8 9 3-1 8-5 8-9V6z"/><path d="m8 12 3 3 5-6"/>',
      'link'=>'<path d="m10 13 4-4M8 15l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0M16 9l1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0" transform="translate(0 -1) scale(.95)"/>',
      'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>',
      'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/>',
      'coin'=>'<circle cx="12" cy="12" r="9"/><path d="M15 7h-6v10m0-5h5"/>',
      'copy'=>'<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/>',
      'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/>',
      'back'=>'<path d="M19 12H5m5-5-5 5 5 5"/>',
    ];
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['shield']).'</svg>';
}
function mg_notice(): void {
    $notice = $_SESSION['manager_notice'] ?? null;
    unset($_SESSION['manager_notice']);
    if (is_array($notice)) echo '<div class="mc-notice '.(!empty($notice['error'])?'mc-notice--error':'').'" role="'.(!empty($notice['error'])?'alert':'status').'">'.mg_e($notice['text'] ?? '').'</div>';
}
function mg_action_form(string $target,array $m,string $decision,string $label,string $returnTo='portal',string $style='secondary'): void {
    ?><form method="post" action="/manager_portal.php" class="mc-inline"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="target" value="<?=mg_e($target)?>"><input type="hidden" name="request_key" value="<?=mg_e(mg_link_key($target,$m))?>"><input type="hidden" name="return_to" value="<?=mg_e($returnTo)?>"><button class="mc-button mc-button--<?=mg_e($style)?>" name="decision" value="<?=mg_e($decision)?>"><?=mg_e($label)?></button></form><?php
}
/** Uses the existing consent, CSRF and manager acceptance flow. */
function mg_local_connect_option(string $returnTo): void {
    static $styled=false;
    if(!$styled){$styled=true; ?>
    <style>
    #manager-connect .mc-local-choice{margin-top:18px;border-top:1px solid #e6ebf2;padding-top:12px}
    #manager-connect .mc-local-choice>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;padding:8px 0;color:#34445b;font-size:13px;font-weight:700;line-height:1.5}
    #manager-connect .mc-local-choice>summary::-webkit-details-marker{display:none}
    #manager-connect .mc-local-choice>summary::marker{content:''}
    #manager-connect .mc-local-choice__link{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 13px;min-height:40px;border:1px solid #b8c9e7;border-radius:9px;background:#eaf1ff;color:#315a9d;font-size:12px;font-weight:750;white-space:nowrap;box-shadow:0 2px 4px #234b8710;transition:background .18s,border-color .18s,box-shadow .18s}
    #manager-connect .mc-local-choice__link svg{width:14px;height:14px;transition:transform .18s}
    #manager-connect .mc-local-choice[open] .mc-local-choice__link svg{transform:rotate(90deg)}
    #manager-connect .mc-local-choice>summary:hover .mc-local-choice__link{color:#244d8c;background:#dfeaff;border-color:#8eaad5;box-shadow:0 3px 7px #234b8718}
    #manager-connect .mc-local-choice[open] .mc-local-choice__link{background:#dfeaff;border-color:#8eaad5}
    #manager-connect .mc-local-choice>summary:active .mc-local-choice__link{box-shadow:none}
    #manager-connect .mc-local-choice>summary:focus-visible{outline:2px solid #7496cb;outline-offset:4px;border-radius:5px}
    #manager-connect .mc-local{margin:8px 0 0;padding:14px 15px;border:1px solid #e5eaf2;border-radius:10px;background:#f8fafc}
    #manager-connect .mc-local__head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
    #manager-connect .mc-local__head>span{display:flex;color:#6980a0}
    #manager-connect .mc-local__head svg{width:17px;height:17px}
    #manager-connect .mc-local__head strong{font-size:13px;font-weight:700;color:#354962}
    #manager-connect .mc-local p{margin:0 0 10px;color:#7b8798;font-size:12px;line-height:1.65}
    #manager-connect .mc-local .mc-consent{margin:10px 0 12px}
    #manager-connect .mc-local .mc-button{width:100%;justify-content:center;background:#fff;color:#435f87;border:1px solid #ccd7e5;box-shadow:none}
    #manager-connect .mc-local .mc-button:hover{background:#eef3fa;border-color:#95accb}
    @media(max-width:380px){#manager-connect .mc-local-choice>summary{gap:10px;flex-wrap:wrap}#manager-connect .mc-local-choice__link{width:100%;justify-content:space-between}#manager-connect .mc-local{padding:12px}}
    </style>
    <?php } ?>
    <details class="mc-local-choice">
      <summary><span>담당 매니저가 없으신가요?</span><span class="mc-local-choice__link">로컬매니저 연결 <?=mg_icon('arrow')?></span></summary>
    <div class="mc-local">
      <div class="mc-local__head"><span><?=mg_icon('users')?></span><strong>로컬매니저에게 연결 요청</strong></div>
      <p>연결을 요청하면 로컬매니저가 확인합니다.<br>수락 후 건물정보와 업무 현황이 공유됩니다.</p>
      <form method="post" action="/manager_portal.php">
        <input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>">
        <input type="hidden" name="action" value="register_code">
        <input type="hidden" name="return_to" value="<?=mg_e($returnTo)?>">
        <input type="hidden" name="manager_code" value="FM-61A8AD50E2">
        <label class="mc-consent"><input type="checkbox" name="manager_consent" value="1" required><span>수락한 로컬매니저에게 건물정보와 업무 현황을 공유하는 데 동의합니다.</span></label>
        <button class="mc-button mc-button--primary" type="submit">로컬매니저 연결 요청 <?=mg_icon('arrow')?></button>
      </form>
    </div>
    </details>
    <?php
}
function mg_connect_card(bool $compact=false,string $returnTo='portal'): void {
    $uid = mg_uid();
    if ($uid === '') return;
    try {
        $members=mg_members();$me=$members[$uid]??[];
        if(!mg_active($me,'building'))return;
        $state=mg_read(mg_state_file());$ref=mg_connection_manager($me,$members);
        $status=$ref!==''?mg_link_status($uid,$me,$state):'none';
        $isPending=$status==='pending';$isAccepted=$status==='accepted';
        $old=$_SESSION['manager_code_old']??'';unset($_SESSION['manager_code_old']);
        $formOpen=!$isPending&&!$isAccepted;
        $nickname=(string)($members[$ref]['nickname']??$ref);
    }catch(Throwable $e){echo '<div class="mc-notice mc-notice--error" role="alert">연결 정보를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.</div>';return;}
    if ($compact) {
        mg_compact_card($uid,$me,$status,$nickname,(string)$old,$returnTo);
        return;
    }
    ?>
    <section class="mc-connect<?=$compact?' mc-connect--compact':''?>" id="manager-connect" aria-labelledby="manager-connect-title">
      <div class="mc-connect__top"><span class="mc-kicker"><?=mg_icon('shield')?> 담당 매니저</span><span class="mc-status mc-status--<?=mg_e($status)?>"><i></i><?=$isAccepted?(pc_active($uid)?'PRO 이용 중':'담당 매니저 연결됨'):($isPending?'수락 대기':'연결 전')?></span></div>
      <?php mg_notice(); ?>
      <?php if($formOpen): ?>
      <div class="mc-intro"><div><h2 id="manager-connect-title">담당 매니저와 연결하세요</h2><p>담당 매니저와 연결하고,<br class="mc-mobile-break"> 부족한 업무를 함께 확인하세요.</p></div><span class="mc-connect__symbol"><?=mg_icon('link')?></span></div>
      <?php if(in_array($status,['rejected','revoked'],true)): ?><p class="mc-context"><?=$status==='rejected'?'이전 요청이 수락되지 않았습니다. 코드를 확인한 뒤 다시 요청할 수 있습니다.':'연결이 해제되었습니다. 코드를 등록하면 새 요청을 보냅니다.'?></p><?php endif; ?>
      <form method="post" action="/manager_portal.php" class="mc-code-form" data-manager-form>
        <input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="register_code"><input type="hidden" name="return_to" value="<?=mg_e($returnTo)?>">
        <label class="mc-label" for="manager-code-input">매니저 고유 코드</label>
        <div class="mc-code-row"><div class="mc-input-wrap"><span><?=mg_icon('link')?></span><input id="manager-code-input" name="manager_code" type="text" maxlength="13" minlength="13" pattern="[Ff][Mm]-[0-9A-Fa-f]{10}" placeholder="FM-XXXXXXXXXX" value="<?=mg_e($old)?>" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="manager-code-help" required></div><button class="mc-button mc-button--primary" type="submit">연결 요청 <?=mg_icon('arrow')?></button></div>
        <p class="mc-field-help" id="manager-code-help">매니저에게 전달받은 13자리 코드를 입력하세요.</p>
        <label class="mc-consent"><input type="checkbox" name="manager_consent" value="1" required><span>매니저가 요청을 수락하면 <strong>건물정보와 업무 진행 현황</strong>을 조회하는 데 동의합니다.</span></label>
      </form>
          <?php mg_local_connect_option($returnTo); ?>
      <?php else: ?>
      <div class="mc-person"><span class="mc-avatar"><?=mg_icon('users')?></span><div><h2 id="manager-connect-title"><?=mg_e($nickname)?><span> 매니저</span></h2><p><?=$isPending?'연결 요청을 보냈습니다. 매니저의 수락을 기다리고 있어요.':'이 건물의 정보와 업무 진행 현황을 함께 확인하고 있어요.'?></p></div></div>
      <?php if($isAccepted&&!pc_active($uid)): ?><p class="mc-context">PRO로 소방안전 업무 기록을 한곳에서 작성·관리하세요. <a href="/subscribe_page.php">PRO 이용 안내 →</a></p><?php endif;?>
      <div class="mc-connection-detail"><span><?=mg_icon($isPending?'clock':'check')?> <?=$isPending?'수락 후에 건물정보가 공유됩니다.':'승인된 매니저만 현황을 조회할 수 있습니다.'?></span><?php mg_action_form($uid,$me,'revoked',$isPending?'요청 취소':'연결 해제',$returnTo,'text'); ?></div>
      <?php endif; ?>
      <ol class="mc-steps" aria-label="매니저 연결 순서"><li class="<?=$status!=='none'&&($isPending||$isAccepted)?'is-done':'is-now'?>"><span><?=$isPending||$isAccepted?mg_icon('check'):'01'?></span>코드 등록</li><li class="<?=$isAccepted?'is-done':($isPending?'is-now':'')?>"><span><?=$isAccepted?mg_icon('check'):'02'?></span>매니저 수락</li><li class="<?=$isAccepted?'is-now':''?>"><span>03</span>함께 관리</li></ol>
      <div class="mc-privacy"><?=mg_icon('lock')?> 연결은 언제든 해제할 수 있습니다.</div>
    </section>
    <?php
}

/** Compact dashboard control; the dedicated connection page keeps its full form. */
function mg_compact_card(string $uid,array $me,string $status,string $nickname,string $old,string $returnTo): void {
    $pending=$status==='pending';$accepted=$status==='accepted';$formOpen=!$pending&&!$accepted;
    $notice=$_SESSION['manager_notice']??null;
    $expand=is_array($notice)&&!empty($notice['error']);
    ?>
    <style>
    /* Compact manager details only; subscription and connection actions stay distinct. */
    #manager-connect.mc-mini .mc-collab-invite{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 20px;margin:0 0 15px;border:1px solid #dce8e6;border-radius:12px;background:linear-gradient(120deg,#f3f9f8,#fbfdfd);box-sizing:border-box}
    #manager-connect.mc-mini .mc-collab-invite__copy{min-width:0}
    #manager-connect.mc-mini .mc-collab-invite__eyebrow{display:block;margin:0 0 7px;color:#26786e;font-size:10px;line-height:1.3;font-weight:750;letter-spacing:.06em}
    #manager-connect.mc-mini .mc-collab-invite h3{margin:0 0 6px;color:#263e3a;font-family:inherit;font-size:15px;font-weight:700;line-height:1.5;letter-spacing:-.035em;word-break:keep-all;overflow-wrap:anywhere}
    #manager-connect.mc-mini .mc-collab-invite p{margin:0;color:#6b7e79;font-size:12px;line-height:1.75;word-break:keep-all;overflow-wrap:anywhere}
    #manager-connect.mc-mini .mc-collab-invite__link{display:inline-flex;align-items:center;justify-content:center;gap:12px;flex-shrink:0;min-height:42px;padding:10px 15px;border:1px solid #203e38;border-radius:9px;background:#203e38;color:#fff;font-size:12px;font-weight:650;line-height:1.5;text-decoration:none;white-space:nowrap;box-shadow:0 2px 4px #203e3810;transition:background .15s,border-color .15s}
    #manager-connect.mc-mini .mc-collab-invite__link:hover{background:#2c5148;border-color:#2c5148;color:#fff}
    #manager-connect.mc-mini .mc-collab-invite__link:focus-visible{outline:3px solid #70b8aa;outline-offset:3px}
    #manager-connect.mc-mini .mc-collab-invite + .mc-mini__manage{padding-top:13px;border-top:1px solid #edf1ef;align-items:center}
    #manager-connect.mc-mini .mc-collab-invite + .mc-mini__manage p{font-size:11px;color:#7d8a85;line-height:1.6}
    #manager-connect.mc-mini .mc-collab-invite + .mc-mini__manage .mc-button{font-size:11px;min-height:32px;padding:5px 0 5px 12px;color:#87918d;white-space:nowrap}
    #manager-connect.mc-mini .mc-collab-invite + .mc-mini__manage .mc-button:hover{color:#b54747}
    @media(max-width:560px){
      #manager-connect.mc-mini .mc-collab-invite{align-items:stretch;flex-direction:column;gap:14px;padding:16px}
      #manager-connect.mc-mini .mc-collab-invite h3{font-size:14px}
      #manager-connect.mc-mini .mc-collab-invite__link{justify-content:space-between;min-height:42px}
      #manager-connect.mc-mini .mc-collab-invite + .mc-mini__manage{flex-direction:row;align-items:flex-start;gap:12px}
      #manager-connect.mc-mini .mc-collab-invite__break{display:none}
    }
    @media(prefers-reduced-motion:reduce){#manager-connect.mc-mini .mc-collab-invite__link{transition:none}}
    </style>
    <section class="mc-connect mc-mini" id="manager-connect" aria-labelledby="manager-connect-title">
      <?php if(is_array($notice)&&empty($notice['error'])) mg_notice(); ?>
      <details class="mc-mini__details"<?=$expand?' open':''?>>
        <summary class="mc-mini__summary">
          <span class="mc-mini__icon"><?=mg_icon($accepted?'users':'link')?></span>
          <span class="mc-mini__copy"><span class="mc-mini__title" id="manager-connect-title">담당 매니저</span><span class="mc-mini__subtitle"><?=mg_e($formOpen?($status==='rejected'?'이전 요청이 거절되었습니다.':($status==='revoked'?'연결이 해제되었습니다.':'전달받은 매니저 코드를 입력하세요.')):$nickname.' 매니저')?></span></span>
          <?php if(!$formOpen): ?><span class="mc-status mc-status--<?=mg_e($status)?>"><i></i><?=$accepted?(pc_active($uid)?'PRO 이용 중':'담당 매니저 연결됨'):'수락 대기'?></span><?php endif; ?>
          <span class="mc-mini__toggle"><span><?=$formOpen?'매니저 연결':'관리'?></span><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></span>
        </summary>
        <div class="mc-mini__body">
          <?php if($expand) mg_notice(); ?>
          <?php if($formOpen): ?>
          <form method="post" action="/manager_portal.php" class="mc-code-form" data-manager-form>
            <input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="register_code"><input type="hidden" name="return_to" value="<?=mg_e($returnTo)?>">
            <label class="mc-label" for="manager-code-input">매니저 고유 코드</label>
            <div class="mc-code-row"><div class="mc-input-wrap"><input id="manager-code-input" name="manager_code" type="text" maxlength="13" minlength="13" pattern="[Ff][Mm]-[0-9A-Fa-f]{10}" placeholder="FM-XXXXXXXXXX" value="<?=mg_e($old)?>" autocomplete="off" autocapitalize="characters" spellcheck="false" required></div><button class="mc-button mc-button--primary" type="submit">연결 요청</button></div>
            <label class="mc-consent"><input type="checkbox" name="manager_consent" value="1" required><span>수락한 매니저에게 건물정보와 업무 현황을 공유하는 데 동의합니다.</span></label>
          </form>
          <?php mg_local_connect_option($returnTo); ?>
          <?php else: ?>
          <?php if($accepted&&!pc_active($uid)): ?>
          <div class="mc-collab-invite">
            <div class="mc-collab-invite__copy">
              <span class="mc-collab-invite__eyebrow">PRO 서비스</span>
              <h3>소방안전 업무 기록을 체계적으로</h3>
              <p>기본정보부터 소방계획서, 매월 업무 기록까지 작성·관리하고,<br class="mc-collab-invite__break"> 필요한 항목은 담당 매니저에게 작성 도움을 요청하세요.</p>
            </div>
            <a class="mc-collab-invite__link" href="/subscribe_page.php">PRO 이용 안내<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg></a>
          </div>
          <?php endif;?>
          <div class="mc-mini__manage"><p><?=$pending?'매니저가 수락하면 건물정보와 업무 현황이 공유됩니다.':'담당 매니저가 건물정보와 업무 현황을 조회할 수 있습니다.'?></p><?php mg_action_form($uid,$me,'revoked',$pending?'요청 취소':'연결 해제',$returnTo,'text'); ?></div>
          <?php endif; ?>
        </div>
      </details>
    </section>
    <?php
}
