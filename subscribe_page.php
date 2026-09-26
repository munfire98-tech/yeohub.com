<?php
/* 연간 단일 요금제: 59,000원 / 12개월. 기존 결제 내역은 보존합니다. */
declare(strict_types=1);

date_default_timezone_set('Asia/Seoul');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool {
  return is_admin() || !empty($_SESSION['is_user']);
}

/* 로그인 안 했으면 메인으로 */
if (!is_logged_in()) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/user_key.php';
require_once __DIR__ . '/manager_common.php';
$UID = function_exists('app_user_key') ? app_user_key() : '';
$hasUser = ($UID !== '');

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 요금제 정의 (여기만 고치면 화면·저장값이 함께 바뀝니다) ── */
require_once __DIR__.'/annual_plan.php';
const PLANS = AP_PLANS;

require_once __DIR__.'/toss_billing.php';
if(!$hasUser){http_response_code(403);exit('로그인 계정을 확인해 주세요.');}
if(!empty($_SESSION['_imp'])||defined('MANAGER_VIEW_UID')||(is_admin()&&!empty($_REQUEST['uid']))){http_response_code(403);exit('구독과 카드 등록은 유저 본인 계정에서 진행해 주세요.');}
function sub_file():string{return tb_file();}
function sub_read():array{return tb_read();}
function sub_latest_payment(array $d):array{return ab_latest_payment($d);}
function sub_refund_quote(array $d,?int $now=null):array{return ab_refund_quote($d,$now);}
$flash='';$flashType='ok';
if(!empty($_SESSION['annual_flash'])){[$flash,$flashType]=$_SESSION['annual_flash'];unset($_SESSION['annual_flash']);}
$proPopup=(($_GET['pro_popup']??'')==='1'||($_POST['pro_popup']??'')==='1');
$act=(string)($_POST['act']??'');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  if(!hash_equals($CSRF,(string)($_POST['csrf']??'')))throw new RuntimeException('새로고침 후 다시 시도해 주세요.');
  if(in_array($act,['subscribe','resubscribe'],true)){
   if(($_POST['offer']??'')!==AP_OFFER||($_POST['renewal_consent']??'')!==AB_CONSENT)throw new RuntimeException('연 59,000원 자동결제 안내를 확인하고 동의해 주세요.');
   if(($_POST['plan']??'yearly')!=='yearly')throw new RuntimeException('연간 요금제만 사용할 수 있습니다.');
   ab_renewal($UID,true);$r=tb_charge(AP_PRICE,AP_PLANS['yearly']['name']);if(!$r['ok'])throw new RuntimeException($r['error']);$flash='59,000원 결제가 완료되었습니다. 다음 결제일과 자동갱신 설정을 확인해 주세요.';
  }elseif($act==='renew_off'){ab_renewal($UID,false);$flash='자동갱신을 해제했습니다. 이미 결제한 기간까지 이용할 수 있습니다.';}
  elseif($act==='renew_on'){
   if(($_POST['renewal_consent']??'')!==AB_CONSENT)throw new RuntimeException('자동결제 안내에 동의해 주세요.');
   $d=tb_read();if(ab_end($d)<=time()||($d['status']??'')!=='active')throw new RuntimeException('기간이 만료된 경우 구독 결제로 다시 시작해 주세요.');
   ab_renewal($UID,true);$flash='연간 자동갱신을 설정했습니다. 다음 결제일에 59,000원이 청구됩니다.';
  }elseif($act==='cancel'){$r=ab_refund_user($UID);if(!$r['ok'])throw new RuntimeException($r['error']);$flash='해지·환불 처리가 완료되었습니다.';}
  elseif($act==='reconcile'){$d=tb_read();$r=in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true)?ab_refund_user($UID,null,true):ab_charge_user($UID,'reconcile');$flash=$r['ok']?'기존 결제·환불 결과를 반영했습니다.':$r['error'];$flashType=$r['ok']?'ok':'err';}
  elseif($act==='inquiry'){
   $message=trim((string)($_POST['message']??''));if($message==='')throw new RuntimeException('문의 내용을 입력해 주세요.');
   mg_tx(__DIR__.'/data/subscribe/inquiries.json',function(&$rows)use($UID,$message){$rows[]=['at'=>date('c'),'uid'=>$UID,'message'=>mb_substr($message,0,1000),'status'=>'open'];});$flash='문의가 접수되었습니다.';
  }else throw new RuntimeException('지원하지 않는 요청입니다.');
 }catch(Throwable $e){$flash=$e->getMessage();$flashType='err';}
 $_SESSION['annual_flash']=[$flash,$flashType];header('Location: /subscribe_page.php'.($proPopup?'?embed=1&pro_popup=1':''));exit;
}
$sub=sub_read();$status=ap_status($sub);

/* 상태 표시용 */
$STATUS_LABEL = [
  'none'           => ['구독 중이 아닙니다', 'muted'],
  'pending'        => ['신청 접수됨 · 결제 준비 중', 'wait'],
  'active'         => ['구독 중', 'ok'],
  'canceled'       => ['해지됨', 'muted'],
  'refund_pending' => ['해지 접수 · 환불 확인 중', 'wait'],
  'refunded'       => ['해지 · 환불 완료', 'muted'],
  'expired'        => ['기간 만료', 'wait'],
  'payment_failed' => ['결제 실패 · 확인이 필요합니다', 'err'],
];
[$statusText, $statusTone] = $STATUS_LABEL[$status] ?? $STATUS_LABEL['none'];
$refundQuote = in_array($status, ['active','payment_failed'], true) ? sub_refund_quote($sub) : [];

$yearly = PLANS['yearly'];

$PAGE_TITLE = '구독';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;                          // 이 페이지는 이미 위에서 로그인 필수 처리했으므로 항상 true
$ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
$ACCOUNT_IS_ADMIN = is_admin();
require __DIR__ . '/_header.php';
?>
<script>
if (window.self !== window.top) document.documentElement.classList.add('subscription-embedded');
</script>
<style>
.subscription-embedded .nav,.subscription-embedded .site-header,
.subscription-embedded .account-nav,.subscription-embedded .page-head,
.subscription-embedded footer{display:none!important}
.subscription-embedded body{padding-top:0!important;margin-top:0!important}
.subscription-embedded main.wrap{max-width:960px;margin:0 auto;padding:16px}
.subscription-payment-note{display:none}
.subscription-embedded .subscription-payment-note{display:block;margin-bottom:12px;font-size:12px;color:var(--mut)}
/* 구독 페이지 전용 — service.php/blog.php 와 같은 방식: 기존 .wrap/.card/.btn 위에 최소한만 더합니다 */
.sub-state{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.sub-badge{font-size:12px;font-weight:800;border-radius:999px;padding:5px 13px}
.sub-badge.muted{background:var(--bg2);color:var(--mut)}
.sub-badge.wait{background:#fffbeb;color:#b45309}
.sub-badge.ok{background:#eefaf1;color:#15803d}
.sub-badge.err{background:#fdeceb;color:var(--danger)}
.sub-meta{font-size:12.5px;color:var(--mut)}
.refund-box{margin-top:14px;padding:13px 14px;border:1px solid #ddd6fe;background:#faf8ff;border-radius:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}

/* 해지 신청 취소 — 아직 되돌릴 수 있음을 알려줍니다 */
.sub-revoke{margin-top:10px;padding:14px 16px;border-radius:10px;
  background:#f0fdf4;border:1px solid #bbf7d0;
  display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.sub-revoke__tx{flex:1;min-width:200px;font-size:12.5px;color:#15803d;line-height:1.7}

/* 다시 구독하기 */
.sub-again{margin-top:14px;padding:15px 17px;border-radius:10px;
  background:var(--bg2);border:1px solid var(--bd);
  display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.sub-again__tx{flex:1;min-width:200px}
.sub-again__tx b{display:block;font-size:13.5px;font-weight:700;color:var(--fg)}
.sub-again__tx span{display:block;font-size:12px;color:var(--mut2);margin-top:3px;line-height:1.7}
@media(max-width:560px){
  .sub-revoke .btn,.sub-again .btn{width:100%;justify-content:center}
}
.refund-box__tx{flex:1;min-width:220px}.refund-box__tx b{display:block;font-size:13px;color:#5b21b6}.refund-box__tx span{display:block;font-size:11.5px;color:var(--mut2);margin-top:3px;line-height:1.6}
.refund-box__amount{font-size:18px;font-weight:900;color:#6d28d9;white-space:nowrap}
.plan-change{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:15px;border:1px solid var(--bd);border-radius:11px;background:var(--bg2)}
.plan-change__flow{display:flex;align-items:center;gap:9px;flex:1;min-width:230px}.plan-change__plan b{display:block;font-size:14px}.plan-change__plan small{display:block;font-size:11.5px;color:var(--mut)}
.plan-change__arrow{color:var(--brand);font-size:18px;font-weight:800}.plan-change__note{font-size:11.5px;color:var(--mut2);margin-top:10px;line-height:1.7}

.sub-notice{background:#fffbeb;border:1px solid #f6d8a8;border-radius:10px;
  padding:12px 14px;font-size:12.5px;color:#b45309;line-height:1.75;margin-bottom:16px}
.sub-flash{border-radius:9px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:16px}
.sub-flash.ok{background:#eefaf1;border:1px solid #bfe6cb;color:#15803d}
.sub-flash.err{background:#fdeceb;border:1px solid #eebfb8;color:var(--danger)}

.sub-sec-t{font-size:15px;font-weight:800;margin-bottom:12px}

.sub-plans{max-width:620px;margin-inline:auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:14px}
.sub-plan{position:relative;display:block;background:var(--card2);border:1px solid var(--bd);
  border-radius:12px;padding:18px;cursor:pointer;transition:.14s}
.sub-plan:hover{border-color:var(--brand)}
.sub-plan.sel{border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.sub-plan__badge{position:absolute;top:-10px;right:14px;background:#16a34a;color:#fff;
  font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px}
.sub-plan__name{font-size:13px;font-weight:700;color:var(--mut2)}
.sub-plan__price{display:flex;align-items:baseline;gap:5px;flex-wrap:wrap;margin-top:4px}
.sub-plan__num{font-size:28px;font-weight:900;letter-spacing:-.02em}
.sub-plan__unit{font-size:13px;color:var(--mut)}
.sub-plan__was{font-size:12.5px;color:var(--mut);text-decoration:line-through}
.sub-plan__sub{font-size:12.5px;color:var(--mut2);line-height:1.65;min-height:34px;margin:8px 0}
.sub-plan__list{list-style:none;display:grid;gap:6px;font-size:12.5px;padding:0}
.sub-plan__list li{display:flex;gap:6px;align-items:flex-start;line-height:1.55}
.sub-plan__list li::before{content:'✓';font-weight:800;color:var(--brand);flex-shrink:0}

.sub-empty{color:var(--mut);font-size:12.5px;padding:14px;text-align:center}
table.sub-table{width:100%;border-collapse:collapse;font-size:12.5px}
table.sub-table th,table.sub-table td{border:1px solid var(--bd);padding:7px 10px;text-align:left}
table.sub-table th{background:var(--bg2);color:var(--mut);font-weight:700;white-space:nowrap}

.sub-faq{display:grid;gap:9px}
.sub-faq__q{font-size:13.5px;font-weight:700;margin-bottom:3px}
.sub-faq__a{font-size:12.5px;color:var(--mut2);line-height:1.75}

.sub-textarea{width:100%;border:1px solid var(--bd2);border-radius:9px;padding:10px 12px;
  font-size:13.5px;font-family:inherit;resize:vertical;color:var(--fg);background:var(--bg2)}

/* ── 준비 중 안내 ── */
.sub-notice__t{display:flex;align-items:center;gap:9px;font-size:14px;font-weight:700;
  color:#92400e;margin-bottom:7px;flex-wrap:wrap}
.sub-notice__badge{font-size:11px;font-weight:800;background:#b45309;color:#fff;
  padding:3px 10px;border-radius:999px;letter-spacing:.02em}
.sub-notice__d{font-size:12.5px;color:#92400e;line-height:1.8;margin:0}
.sub-notice__d b{font-weight:700}

/* ── 카드 등록 ── */
.tb-lead{font-size:13px;color:var(--mut2);line-height:1.75;margin-bottom:14px}
.tb-card{display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--bg2);border-radius:11px;padding:14px 16px}
.tb-card__ic{font-size:22px}
.tb-card__tx{flex:1;min-width:0}
.tb-card__tx b{display:block;font-size:14px;font-weight:700}
.tb-card__tx small{display:block;font-size:11.5px;color:var(--mut);margin-top:2px}
.tb-msg{font-size:12.5px;color:var(--mut2);background:var(--bg2);border-radius:10px;
  padding:13px 15px;line-height:1.8}
.tb-msg code{background:#fff;border:1px solid var(--bd);border-radius:5px;
  padding:1px 6px;font-size:11.5px}
.tb-test{margin-top:12px;font-size:12px;color:#92400e;background:#fffbeb;
  border:1px solid #f6d8a8;border-radius:9px;padding:10px 13px;line-height:1.7}

/* ── 결제 신뢰 안내 ── */
.trust{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;
  margin-bottom:16px}
.trust__item{display:flex;gap:11px;align-items:flex-start;background:var(--card);
  border:1px solid var(--bd);border-radius:12px;padding:14px 15px}
.trust__ico{flex:0 0 34px;width:34px;height:34px;border-radius:9px;background:#eef4ff;
  color:var(--brand2);display:flex;align-items:center;justify-content:center}
.trust__ico svg{width:18px;height:18px}
.trust__item b{display:block;font-size:13px;font-weight:700;color:var(--fg);margin-bottom:3px}
.trust__item span{display:block;font-size:11.5px;color:var(--mut2);line-height:1.6}

/* ── 판매자 정보 · 환불 규정 ── */
.seller{background:var(--bg2);border-radius:12px;padding:18px 20px;margin-top:6px}
.seller__t{font-size:12px;font-weight:800;color:var(--mut2);letter-spacing:.04em;
  text-transform:uppercase;margin-bottom:12px}
.seller__grid{display:flex;flex-wrap:wrap;gap:7px 24px;font-size:12.5px;color:var(--mut);
  line-height:1.85;margin-bottom:12px}
.seller__grid b{font-weight:600;color:var(--mut2);margin-right:6px}
.seller__policy{font-size:12px;color:var(--mut);line-height:1.85;margin:0;
  padding-top:12px;border-top:1px solid var(--bd)}
.seller__policy b{color:var(--mut2);font-weight:700}
/* ── 간결한 접이식 관리 메뉴 ── */
details.sub-fold{padding:0;overflow:hidden}
.sub-fold>summary{list-style:none;display:flex;align-items:center;gap:12px;padding:16px 18px;cursor:pointer;user-select:none}
.sub-fold>summary::-webkit-details-marker{display:none}
.sub-fold>summary::after{content:'›';margin-left:auto;color:var(--mut);font-size:22px;line-height:1;transition:transform .16s}
.sub-fold[open]>summary::after{transform:rotate(90deg)}
.sub-fold__title{font-size:14px;font-weight:800;color:var(--fg)}
.sub-fold__hint{font-size:11.5px;color:var(--mut);margin-left:auto}
.sub-fold>summary::after{margin-left:0}.sub-fold__body{padding:0 18px 18px;border-top:1px solid var(--bd);padding-top:16px}
.sub-fold .sub-sec-t{display:none}.sub-fold .trust{margin:0 18px 18px}
@media(max-width:560px){.sub-fold__hint{display:none}}
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 구독</div>
    <h1>구독</h1>
    <p>매년 59,000원 자동결제로 12개월씩 이용하세요.</p>
  </div>
</header>

<main class="wrap">
  <p class="subscription-payment-note">카드 등록과 구독 결제를 이 팝업에서 이어서 진행하세요. 카드사 인증만 별도 보안창에서 열립니다.</p>
  <?php if ($flash): ?>
    <div class="sub-flash <?=h($flashType)?>"><?=h($flash)?></div>
  <?php endif; ?>

  <?php if (!$hasUser): ?>
    <div class="sub-flash err">로그인 정보를 확인할 수 없어 구독 정보를 불러오지 못했습니다. 다시 로그인해 주세요.</div>
  <?php endif; ?>

  <?php require_once __DIR__ . '/toss_billing.php'; if (!tb_ready()): ?>
  <!-- 결제키가 아직 설정되지 않은 경우에만 표시 -->
  <div class="sub-notice">
    <div class="sub-notice__t">
      <span class="sub-notice__badge">준비 중</span>
      결제 시스템을 연동하고 있습니다
    </div>
    <p class="sub-notice__d">
      결제 시스템을 준비하고 있습니다.
      준비가 완료되면 카드 등록과 구독 결제를 이용할 수 있습니다.
      문의는 아래 문의하기를 이용해 주세요.
    </p>
  </div>
  <?php endif; ?>

  <!-- 카드 등록 (토스페이먼츠 자동결제) -->
  <?php
    $tbData  = tb_read();
    $tbCard  = $tbData['card'] ?? [];
    $hasCard = trim((string)($tbData['billing_key'] ?? '')) !== '';
  ?>
  <details class="card sub-fold" <?=!$hasCard?'open':''?>>
    <summary><span class="sub-fold__title">결제카드 관리</span><span class="sub-fold__hint"><?=$hasCard?'등록된 카드 확인·변경':'카드 등록 필요'?></span></summary>
    <div class="sub-fold__body">
    <div class="sub-sec-t">결제 카드</div>

    <?php if (!tb_ready()): ?>
      <div class="tb-msg">
        결제 키가 아직 설정되지 않았습니다.
        <code>api_keys.php</code> 의 <code>toss_client</code> · <code>toss_secret</code> 에
        토스페이먼츠 키를 넣어주세요.
      </div>

    <?php elseif ($hasCard): ?>
      <div class="tb-card">
        <span class="tb-card__ic">💳</span>
        <div class="tb-card__tx">
          <b><?=h(trim(($tbCard['company'] ?? '') . ' ' . ($tbCard['number'] ?? '')) ?: '등록된 카드')?></b>
          <small>등록일 <?=h(substr((string)($tbData['card_registered_at'] ?? ''), 0, 16))?></small>
        </div>
        <button class="btn btn--ghost" type="button" onclick="registerCard()">카드 바꾸기</button>
      </div>

    <?php else: ?>
      <p class="tb-lead">
        카드 등록만으로는 결제되지 않습니다. 아래에서 자동결제에 동의하고 구독을 시작하면 첫 59,000원이 결제됩니다.
        전체 카드번호 대신 토스에서 발급한 결제용 키와 마스킹된 카드정보만 저장합니다.
      </p>
      <button class="btn btn--primary" type="button" onclick="registerCard()">💳 카드 등록하기</button>
    <?php endif; ?>

    <?php if (!tb_is_live() && tb_ready()): ?>
      <div class="tb-test">
        <b>테스트 모드</b> · 실제로 결제되지 않습니다.
        카드번호는 앞 6~8자리만 맞으면 나머지는 아무 값이나 넣으셔도 됩니다.
      </div>
    <?php endif; ?>
    </div>

  <?php if (tb_ready()): ?>
  <script>
    /* 카드 등록창을 띄웁니다.
       성공하면 successUrl 로 authKey·customerKey 가 붙어 돌아오고,
       거기서 빌링키를 발급받아 저장합니다. */
    let cardAuthWindow=null;
    function registerCard(){
      if(cardAuthWindow&&!cardAuthWindow.closed){cardAuthWindow.focus();return;}
      const host=window.top;
      const width=Math.min(1000,screen.availWidth),height=Math.min(820,screen.availHeight);
      const left=Math.round(host.screenX+(host.outerWidth-width)/2),top=Math.round(host.screenY+(host.outerHeight-height)/2);
      cardAuthWindow=window.open('/pro_card_auth.php','sobangProCardAuth',`popup,width=${width},height=${height},left=${left},top=${top}`);
      if(!cardAuthWindow){alert('카드 인증창이 차단되었습니다. 이 사이트의 팝업을 허용하고 다시 눌러 주세요.');return;}
      cardAuthWindow.focus();
    }
    window.addEventListener('message',function(e){
      if(e.origin!==location.origin||!cardAuthWindow||e.source!==cardAuthWindow||e.data?.type!=='pro-card-return')return;
      location.reload();
    });
  </script>
  <?php endif; ?>

  <!-- 결제 신뢰 안내 -->
  <div class="trust">
    <div class="trust__item">
      <span class="trust__ico">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5.5c0 4.2-2.9 8.1-7 9.5-4.1-1.4-7-5.3-7-9.5V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12.2l1.8 1.8 3.4-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <b>토스페이먼츠 결제</b>
        <span>결제는 토스페이먼츠 시스템에서 처리됩니다</span>
      </div>
    </div>
    <div class="trust__item">
      <span class="trust__ico">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </span>
      <div>
        <b>카드번호를 저장하지 않습니다</b>
        <span>결제사가 발급한 결제키만 보관합니다</span>
      </div>
    </div>
    <div class="trust__item">
      <span class="trust__ico">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 8h14M5 12h14M5 16h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </span>
      <div>
        <b>해지해도 자료는 그대로</b>
        <span>입력하신 건물 정보와 기록은 삭제되지 않습니다</span>
      </div>
    </div>
    <div class="trust__item">
      <span class="trust__ico">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <b>언제든 해지</b>
        <span>자동갱신 해제 후에도 남은 이용기간은 유지됩니다</span>
      </div>
    </div>
  </div>
  </details>

  <!-- 현재 상태 -->
  <div class="card">
    <div class="sub-sec-t">현재 상태</div>
    <?php if(!empty($sub['billing_notice'])): ?><p role="status" class="tb-msg"><?=h($sub['billing_notice'])?></p><?php endif;?>
    <?php if(in_array($sub['charge_attempt']['state']??'',['prepared','unknown'],true)): ?><form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="reconcile"><button class="btn btn--ghost">기존 결제 결과 확인</button></form><?php endif;?>
    <?php if($status==='active'||($sub['auto_renew']??false)): ?>
    <div class="refund-box"><div class="refund-box__tx"><b><?=ab_auto($sub)?'자동갱신 켜짐':'자동갱신 꺼짐'?></b><span><?=ab_auto($sub)?'다음 결제일 '.h($sub['next_billing']??'').' · 59,000원':'이미 결제한 기간까지 이용할 수 있으며 다음 결제는 진행하지 않습니다.'?></span></div></div>
    <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="<?=ab_auto($sub)?'renew_off':'renew_on'?>">
    <?php if(!ab_auto($sub)): ?><label style="display:block;margin:12px 0"><input type="checkbox" name="renewal_consent" value="<?=h(AB_CONSENT)?>" required> 다음 결제일부터 매년 59,000원 자동결제에 동의합니다. 갱신 결제 실패 시 총 3회 시도합니다.</label><?php endif;?>
    <button class="btn btn--ghost"><?=ab_auto($sub)?'자동갱신 해제 · 남은 기간 유지':'연간 자동갱신 설정'?></button></form>
    <?php endif;?>

    <div class="sub-state">
      <span class="sub-badge <?=h($statusTone)?>"><?=h($statusText)?></span>
      <?php if (!empty($sub['plan_name'])): ?>
        <span class="sub-meta">
          <?=h($sub['plan_name'])?> · <?=number_format((int)($sub['price'] ?? 0))?>원
          <?php if (!empty($sub['requested_at'])): ?> · 신청 <?=h($sub['requested_at'])?><?php endif; ?>
          <?php if (!empty($sub['expires_at'])): ?> · 이용 만료 <?=h($sub['expires_at'])?><?php endif; ?>
        </span>
      <?php endif; ?>
      <?php if (in_array($status, ['active','payment_failed'], true) && !empty($refundQuote['ok'])): ?>
        <form method="post" style="margin-left:auto">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="act" value="cancel">
          <button class="btn btn--ghost" type="submit"
            onclick="return confirm('구독을 즉시 해지하고 <?=number_format((int)$refundQuote['amount'])?>원을 환불합니다.\n환불 후에는 즉시 이용이 종료됩니다. 계속할까요?')">해지·환불하기</button>
        </form>
      <?php endif; ?>
    </div>
    <?php if (in_array($status, ['active','payment_failed'], true)): ?>
      <div class="refund-box">
        <div class="refund-box__tx">
          <b>지금 해지할 경우 예상 환불액</b>
          <?php if (!empty($refundQuote['ok'])): ?>
            <span><?=h($refundQuote['reason'])?> · 전체 <?=$refundQuote['total_days']?>일 중 잔여 <?=$refundQuote['remaining_days']?>일 · 환불 완료 시 즉시 이용 종료</span>
          <?php else: ?><span><?=h((string)($refundQuote['reason'] ?? '환불 정보를 확인할 수 없습니다.'))?></span><?php endif; ?>
        </div>
        <strong class="refund-box__amount"><?=!empty($refundQuote['ok'])?number_format((int)$refundQuote['amount']).'원':'확인 필요'?></strong>
      </div>
    <?php elseif ($status === 'refund_pending'): ?>
      <div class="refund-box">
        <div class="refund-box__tx">
          <b>환불 확인 중</b>
          <span>관리자가 결제 식별정보를 확인한 뒤 환불을 완료합니다.
            확인 중에는 중복 환불을 보내지 않습니다.</span>
        </div>
        <strong class="refund-box__amount"><?=number_format((int)($sub['refund']['amount'] ?? 0))?>원</strong>
      </div>
      <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="reconcile"><button class="btn btn--ghost">환불 결과 다시 확인</button></form>

    <?php elseif (in_array($status, ['canceled','refunded','expired'], true)): ?>
      <?php
        require_once __DIR__ . '/toss_billing.php';
        $reCard = trim((string)(tb_read()['billing_key'] ?? '')) !== '';
        $rePlan = PLANS['yearly']['name'];
        $rePrice = AP_PRICE;
      ?>
      <div class="sub-again">
        <div class="sub-again__tx">
          <b>다시 이용하시겠어요?</b>
          <span>
            <?php if ($reCard): ?>
              등록해 두신 카드로 바로 시작할 수 있습니다.
              <?php if ($rePlan): ?><?=h($rePlan)?> <?=number_format($rePrice)?>원이 결제됩니다.<?php endif; ?>
            <?php else: ?>
              먼저 아래에서 결제 카드를 등록해 주세요.
            <?php endif; ?>
          </span>
        </div>
        <?php if ($reCard): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input type="hidden" name="act" value="resubscribe"><input type="hidden" name="offer" value="<?=h(AP_OFFER)?>"><label style="display:flex;gap:9px;align-items:flex-start;margin:15px 0;font-size:13px;line-height:1.7"><input type="checkbox" name="renewal_consent" value="<?=h(AB_CONSENT)?>" required style="margin-top:5px"><span>오늘 59,000원 결제 후, 자동갱신을 해제하기 전까지 매년 59,000원이 등록 카드로 결제되는 것에 동의합니다. 갱신 결제 실패 시 총 3회까지 시도하며, 이 화면에서 자동갱신을 해제할 수 있습니다.</span></label>
            <button class="btn btn--primary" type="submit"
              onclick="return confirm('<?=h($rePlan ?: '구독')?> <?=number_format($rePrice)?>원을 결제하고 다시 시작합니다.\n계속할까요?')">
              다시 구독하기
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- 플랜 선택 -->
  <?php if (in_array($status, ['none','canceled','expired','refunded','payment_failed'], true)): ?>
  <div class="card">
    <div class="sub-sec-t">요금제 선택</div>
    <form method="post" id="planForm">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="act" value="subscribe"><input type="hidden" name="offer" value="<?=h(AP_OFFER)?>">
      <input type="hidden" name="plan" id="planInput" value="yearly">

      <div class="sub-plans">
        <!-- 연 -->
        <label class="sub-plan sel" data-plan="yearly" onclick="pickPlan('yearly')">
          <span class="sub-plan__badge">12개월 이용</span>
          <div class="sub-plan__name"><?=h($yearly['name'])?></div>
          <div class="sub-plan__price">
            <span class="sub-plan__num"><?=number_format($yearly['price'])?></span>
            <span class="sub-plan__unit">원 / <?=h($yearly['period'])?></span>
            
          </div>
          <div class="sub-plan__sub">매년 59,000원 자동결제 · 12개월 이용</div>
          <ul class="sub-plan__list">
            <li>모든 기능 사용</li>
            <li>연간 단일 요금제</li>
            <li>1년간 요금 변동 없음</li>
          </ul>
        </label>
      </div>

      <label style="display:flex;gap:9px;align-items:flex-start;margin:15px 0;font-size:13px;line-height:1.7"><input type="checkbox" name="renewal_consent" value="<?=h(AB_CONSENT)?>" required style="margin-top:5px"><span>오늘 59,000원 결제 후, 자동갱신을 해제하기 전까지 매년 59,000원이 등록 카드로 결제되는 것에 동의합니다. 갱신 결제 실패 시 총 3회까지 시도하며, 이 화면에서 자동갱신을 해제할 수 있습니다.</span></label>
      <button class="btn btn--primary" type="submit" style="width:100%;justify-content:center"
        <?= $hasUser && $hasCard ? '' : 'disabled' ?>><?=$hasCard?'연 59,000원 결제하기':'카드 등록 후 결제할 수 있습니다'?></button>
    </form>
  </div>
  <?php endif; ?>

  <!-- 결제 내역 -->
  <details class="card sub-fold">
    <summary><span class="sub-fold__title">신청 · 결제 내역</span><span class="sub-fold__hint"><?=count((array)($sub['history'] ?? []))?>건</span></summary>
    <div class="sub-fold__body">
    <div class="sub-sec-t">신청 · 결제 내역</div>
    <?php $hist = (array)($sub['history'] ?? []); if (!$hist): ?>
      <div class="sub-empty">아직 내역이 없습니다.</div>
    <?php else: ?>
      <table class="sub-table">
        <tr><th>일시</th><th>구분</th><th>금액</th><th>비고</th></tr>
        <?php foreach (array_reverse($hist) as $row): ?>
          <tr>
            <td><?=h((string)($row['at'] ?? ''))?></td>
            <td><?=h((string)($row['type'] ?? ''))?></td>
            <td><?= isset($row['amount']) ? number_format((int)$row['amount']).'원' : '-' ?></td>
            <td><?=h((string)($row['memo'] ?? ''))?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    </div>
  </details>

  <!-- 안내 -->
  <details class="card sub-fold">
    <summary><span class="sub-fold__title">이용 안내</span><span class="sub-fold__hint">결제·환불·자료 보관 안내</span></summary>
    <div class="sub-fold__body">
    <div class="sub-sec-t">이용 안내</div>
    <div class="sub-faq">
      <div>
        <div class="sub-faq__q">결제는 어떻게 이루어지나요?</div>
        <div class="sub-faq__a">처음 59,000원을 결제한 뒤 매년 같은 결제일에 59,000원이 자동 청구됩니다. 자동갱신을 해제하면 다음 청구가 중단되고 남은 기간까지 이용할 수 있습니다.
          결제는 토스페이먼츠 시스템에서 처리되며, 카드번호는 저희 서버에 저장되지 않습니다.
          결제사가 발급한 결제키만 보관합니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">환불이 되나요?</div>
        <div class="sub-faq__a">결제 후 7일 이내에는 전액 환불하며, 이후에는 실제 결제금액을 기준으로 남은 기간을 일할 계산하여 환불합니다.
          환불이 완료되면 서비스 이용이 즉시 종료됩니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">해지하면 자료가 사라지나요?</div>
        <div class="sub-faq__a">아니요. 입력하신 건물 정보와 기록은 삭제되지 않습니다.
          다시 구독하시면 이어서 사용하실 수 있습니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">요금제를 바꿀 수 있나요?</div>
        <div class="sub-faq__a">연간 단일 요금제만 제공합니다. 기존 결제의 남은 이용기간은 유지되며 새 결제에는 연 59,000원이 적용됩니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">세금계산서 발행이 되나요?</div>
        <div class="sub-faq__a">필요하시면 아래로 문의해 주세요. 사업자 정보를 확인한 뒤 안내해 드리겠습니다.</div>
      </div>
    </div>
    </div>
  </details>

  <!-- 문의 -->
  <details class="card sub-fold">
    <summary><span class="sub-fold__title">문의하기</span><span class="sub-fold__hint">구독·결제 문의 남기기</span></summary>
    <div class="sub-fold__body">
    <div class="sub-sec-t">문의하기</div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="act" value="inquiry">
      <textarea class="sub-textarea" name="message" rows="3"
        placeholder="구독·결제에 대해 궁금한 점을 남겨주세요. 확인 후 안내해 드리겠습니다."></textarea>
      <button class="btn btn--ghost" type="submit" style="margin-top:9px" <?= $hasUser ? '' : 'disabled' ?>>
        문의 보내기
      </button>
    </form>
    </div>
  </details>

  <!-- 판매자 정보 · 환불 규정 -->
  <details class="seller sub-fold">
    <summary><span class="sub-fold__title">판매자 정보 · 환불 규정</span><span class="sub-fold__hint">사업자 정보와 약관 확인</span></summary>
    <div class="sub-fold__body">
    <div class="seller__t">판매자 정보</div>
    <div class="seller__grid">
      <span><b>상호</b>YEOHUB</span>
      <span><b>대표</b>문현권</span>
      <span><b>사업자등록번호</b>751-38-01677</span>
      <span><b>소재지</b>경기도 파주시 운정중앙로</span>
      <span><b>이메일</b>YEOHUB@YEOHUB.com</span>
      <span><b>결제대행</b>토스페이먼츠</span>
    </div>
    <p class="seller__policy">
      <b>환불 규정</b> ·
      결제 후 7일 이내에는 전액 환불합니다.
      이후에는 실제 결제금액에서 전체 이용기간 대비 남은 기간을 일할 계산하여 환불하며,
      환불 완료 시 서비스 이용이 즉시 종료됩니다.
    </p>
    </div>
  </details>
</main>

<script>
function pickPlan(p){
  document.getElementById('planInput').value = p;
  document.querySelectorAll('.sub-plan').forEach(function(el){
    el.classList.toggle('sel', el.dataset.plan === p);
  });
}
</script>

<script>
 document.querySelectorAll('form[method="post"]').forEach(form=>{
  const context=document.createElement('input');context.type='hidden';context.name='pro_popup';context.value=<?=json_encode($proPopup?'1':'0')?>;form.append(context);
  form.addEventListener('submit',event=>{
   if(form.dataset.submitting==='1'){event.preventDefault();return;}
   form.dataset.submitting='1';
   form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>{button.setAttribute('aria-disabled','true');button.style.pointerEvents='none';});
  });
 });
 <?php if($flash!==''&&$flashType==='ok'): ?>
 if(window.parent!==window)window.parent.postMessage({type:'pro-subscription-updated'},location.origin);
 <?php endif; ?>
</script>
<?php require __DIR__ . '/_footer.php'; ?>
