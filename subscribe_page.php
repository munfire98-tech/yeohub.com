<?php
/* =============================================================
   subscribe_page.php — 구독 신청 / 관리 페이지 (틀)
   ─────────────────────────────────────────────────────────────
   지금은 "틀"입니다. 실제 결제(PG) 연동 전이라 아래처럼 동작합니다.

     · 플랜 선택 → 신청 내역이 파일로 저장됨 (status: pending)
     · 실제 카드결제·빌링키 발급은 아직 없음
     · PG 연동을 붙일 자리에 ★PG연동 주석을 달아 두었습니다

   실제 결제를 붙일 때 고칠 곳은 두 군데뿐입니다.
     [1] act=subscribe  → 빌링키 발급 + 첫 결제 호출
     [2] act=cancel     → PG 정기결제 해지 호출

   요금: 월 1,900원 / 연 19,000원 (2개월 무료)

   화면은 _header.php / _footer.php 를 그대로 씁니다 (blog.php·service.php 와 동일 구조).
   ============================================================= */
declare(strict_types=1);

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
$UID = function_exists('app_user_key') ? app_user_key() : '';
$hasUser = ($UID !== '');

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 요금제 정의 (여기만 고치면 화면·저장값이 함께 바뀝니다) ── */
const PLANS = [
  'monthly' => ['name'=>'월 구독', 'price'=>2900,  'period'=>'월', 'months'=>1],
  'yearly'  => ['name'=>'연 구독', 'price'=>29000, 'period'=>'년', 'months'=>12],
];

/* ── 저장 위치 ── */
function sub_file(): string {
  $k = function_exists('app_user_key') ? app_user_key() : '';
  if ($k === '') return '';
  $dir = __DIR__ . '/data/subscribe/' . $k;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/subscription.json';
}
function sub_read(): array {
  $f = sub_file();
  if ($f === '' || !is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function sub_write(array $d): bool {
  $f = sub_file();
  if ($f === '') return false;
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

/* 현재 구독 상태
   status: none | pending | active | canceled | expired | payment_failed */
$sub = sub_read();
$status = (string)($sub['status'] ?? 'none');

/* ── 액션 처리 ────────────────────────────────────────────── */
$flash = ''; $flashType = 'ok';
$act = $_POST['act'] ?? '';

if ($act !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $flash = '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'; $flashType = 'err';
  } elseif (!$hasUser) {
    $flash = '로그인 정보를 확인할 수 없습니다. 다시 로그인해 주세요.'; $flashType = 'err';
  } else {

    /* [1] 구독 신청 ------------------------------------------------
       ★PG연동 자리
       실제로는 여기서:
         1. PG사 결제창 호출 → 카드 등록
         2. 빌링키(billing_key) 발급받아 저장
         3. 첫 결제 즉시 실행
         4. 성공 시 status='active', 실패 시 'payment_failed'
       지금은 신청 기록만 남기고 status='pending' 으로 둡니다. */
    if ($act === 'subscribe') {
      $plan = (string)($_POST['plan'] ?? '');
      if (!isset(PLANS[$plan])) {
        $flash = '요금제를 다시 선택해 주세요.'; $flashType = 'err';
      } else {
        $p = PLANS[$plan];
        $now = date('Y-m-d H:i:s');
        $sub = [
          'plan'        => $plan,
          'plan_name'   => $p['name'],
          'price'       => $p['price'],
          'status'      => 'pending',        // ★PG연동 후 'active' 로
          'requested_at'=> $now,
          'started_at'  => '',               // ★첫 결제 성공 시각
          'expires_at'  => '',               // ★결제일 + months
          'billing_key' => '',               // ★PG 빌링키 (카드번호는 저장하지 않음)
          'next_billing'=> '',               // ★다음 청구 예정일
          'history'     => array_merge((array)($sub['history'] ?? []), [[
            'at'=>$now, 'type'=>'request', 'plan'=>$plan, 'amount'=>$p['price'],
            'memo'=>'구독 신청 (결제 연동 전)',
          ]]),
        ];
        $ok = sub_write($sub);
        $flash = $ok
          ? $p['name'].' 신청이 접수되었습니다. 결제 연동 후 정식으로 시작됩니다.'
          : '저장에 실패했습니다. 잠시 후 다시 시도해 주세요.';
        $flashType = $ok ? 'ok' : 'err';
        $status = $sub['status'];
      }
    }

    /* [2] 구독 해지 ------------------------------------------------
       ★PG연동 자리 — 실제로는 PG 정기결제 해지 API 호출 후 상태 변경.
       해지해도 만료일까지는 사용하게 두는 방식을 권장합니다. */
    if ($act === 'cancel') {
      if ($sub) {
        $now = date('Y-m-d H:i:s');
        $sub['status'] = 'canceled';
        $sub['canceled_at'] = $now;
        $sub['history'][] = ['at'=>$now, 'type'=>'cancel', 'memo'=>'사용자 해지 요청'];
        sub_write($sub);
        $flash = '구독이 해지되었습니다. 입력하신 자료는 삭제되지 않습니다.';
        $status = 'canceled';
      }
    }

    /* [3] 문의 남기기 (결제 전 단계에서 유용) */
    if ($act === 'inquiry') {
      $msg = trim((string)($_POST['message'] ?? ''));
      if ($msg === '') {
        $flash = '문의 내용을 입력해 주세요.'; $flashType = 'err';
      } else {
        $f = __DIR__ . '/data/subscribe/inquiries.json';
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
        $list = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
        $list[] = ['at'=>date('Y-m-d H:i:s'), 'uid'=>$UID,
                   'message'=>mb_substr($msg, 0, 1000), 'status'=>'open'];
        @file_put_contents($f, json_encode($list, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
        $flash = '문의가 접수되었습니다. 확인 후 안내해 드리겠습니다.';
      }
    }
  }
  $sub = sub_read();
  $status = (string)($sub['status'] ?? 'none');
}

/* 상태 표시용 */
$STATUS_LABEL = [
  'none'           => ['구독 중이 아닙니다', 'muted'],
  'pending'        => ['신청 접수됨 · 결제 준비 중', 'wait'],
  'active'         => ['구독 중', 'ok'],
  'canceled'       => ['해지됨', 'muted'],
  'expired'        => ['기간 만료', 'wait'],
  'payment_failed' => ['결제 실패 · 확인이 필요합니다', 'err'],
];
[$statusText, $statusTone] = $STATUS_LABEL[$status] ?? $STATUS_LABEL['none'];

$monthly = PLANS['monthly']; $yearly = PLANS['yearly'];
$yearCompare = $monthly['price'] * 12;          // 22,800
$yearSave    = $yearCompare - $yearly['price']; // 3,800

$PAGE_TITLE = '구독';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;                          // 이 페이지는 이미 위에서 로그인 필수 처리했으므로 항상 true
$ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
$ACCOUNT_IS_ADMIN = is_admin();
require __DIR__ . '/_header.php';
?>
<style>
/* 구독 페이지 전용 — service.php/blog.php 와 같은 방식: 기존 .wrap/.card/.btn 위에 최소한만 더합니다 */
.sub-state{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.sub-badge{font-size:12px;font-weight:800;border-radius:999px;padding:5px 13px}
.sub-badge.muted{background:var(--bg2);color:var(--mut)}
.sub-badge.wait{background:#fffbeb;color:#b45309}
.sub-badge.ok{background:#eefaf1;color:#15803d}
.sub-badge.err{background:#fdeceb;color:var(--danger)}
.sub-meta{font-size:12.5px;color:var(--mut)}

.sub-notice{background:#fffbeb;border:1px solid #f6d8a8;border-radius:10px;
  padding:12px 14px;font-size:12.5px;color:#b45309;line-height:1.75;margin-bottom:16px}
.sub-flash{border-radius:9px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:16px}
.sub-flash.ok{background:#eefaf1;border:1px solid #bfe6cb;color:#15803d}
.sub-flash.err{background:#fdeceb;border:1px solid #eebfb8;color:var(--danger)}

.sub-sec-t{font-size:15px;font-weight:800;margin-bottom:12px}

.sub-plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:14px}
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
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> PRO 구독</div>
    <h1>PRO 구독</h1>
    <p>모든 기능을 제한 없이 사용합니다. 월 구독과 연 구독 중에 선택하세요.</p>
  </div>
</header>

<main class="wrap">
  <?php if ($flash): ?>
    <div class="sub-flash <?=h($flashType)?>"><?=h($flash)?></div>
  <?php endif; ?>

  <?php if (!$hasUser): ?>
    <div class="sub-flash err">로그인 정보를 확인할 수 없어 구독 정보를 불러오지 못했습니다. 다시 로그인해 주세요.</div>
  <?php endif; ?>

  <!-- ★ 결제 연동 전 안내 — PG 연동 후 이 블록을 지우세요 -->
  <div class="sub-notice">
    <b>현재는 준비 중입니다.</b><br>
    결제 시스템 연동 전이라 지금 신청하시면 <b>사전 신청</b>으로 접수됩니다.
    실제 결제는 이루어지지 않으며, 준비가 끝나면 안내해 드리겠습니다.
  </div>

  <!-- 현재 상태 -->
  <div class="card">
    <div class="sub-sec-t">현재 상태</div>
    <div class="sub-state">
      <span class="sub-badge <?=h($statusTone)?>"><?=h($statusText)?></span>
      <?php if (!empty($sub['plan_name'])): ?>
        <span class="sub-meta">
          <?=h($sub['plan_name'])?> · <?=number_format((int)($sub['price'] ?? 0))?>원
          <?php if (!empty($sub['requested_at'])): ?> · 신청 <?=h($sub['requested_at'])?><?php endif; ?>
          <?php if (!empty($sub['expires_at'])): ?> · 이용 만료 <?=h($sub['expires_at'])?><?php endif; ?>
        </span>
      <?php endif; ?>
      <?php if (in_array($status, ['pending','active'], true)): ?>
        <form method="post" style="margin-left:auto">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="act" value="cancel">
          <button class="btn btn--ghost" type="submit"
            onclick="return confirm('구독을 해지할까요?\n입력하신 자료는 삭제되지 않습니다.')">해지하기</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- 플랜 선택 -->
  <?php if (!in_array($status, ['active'], true)): ?>
  <div class="card">
    <div class="sub-sec-t">요금제 선택</div>
    <form method="post" id="planForm">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="act" value="subscribe">
      <input type="hidden" name="plan" id="planInput" value="yearly">

      <div class="sub-plans">
        <!-- 월 -->
        <label class="sub-plan" data-plan="monthly" onclick="pickPlan('monthly')">
          <div class="sub-plan__name"><?=h($monthly['name'])?></div>
          <div class="sub-plan__price">
            <span class="sub-plan__num"><?=number_format($monthly['price'])?></span>
            <span class="sub-plan__unit">원 / <?=h($monthly['period'])?></span>
          </div>
          <div class="sub-plan__sub">부담 없이 시작해 보고 싶을 때. 언제든 해지할 수 있습니다.</div>
          <ul class="sub-plan__list">
            <li>모든 기능 사용</li>
            <li>거래처 200곳까지</li>
            <li>건축물대장 자동 조회</li>
          </ul>
        </label>

        <!-- 연 -->
        <label class="sub-plan sel" data-plan="yearly" onclick="pickPlan('yearly')">
          <span class="sub-plan__badge">2개월 무료</span>
          <div class="sub-plan__name"><?=h($yearly['name'])?></div>
          <div class="sub-plan__price">
            <span class="sub-plan__num"><?=number_format($yearly['price'])?></span>
            <span class="sub-plan__unit">원 / <?=h($yearly['period'])?></span>
            <span class="sub-plan__was"><?=number_format($yearCompare)?>원</span>
          </div>
          <div class="sub-plan__sub">
            월 구독으로 1년이면 <?=number_format($yearCompare)?>원 —
            <b><?=number_format($yearSave)?>원 더 저렴</b>합니다.
          </div>
          <ul class="sub-plan__list">
            <li>월 구독의 모든 기능</li>
            <li>2개월분 무료</li>
            <li>1년간 요금 변동 없음</li>
          </ul>
        </label>
      </div>

      <button class="btn btn--primary" type="submit" style="width:100%;justify-content:center"
        <?= $hasUser ? '' : 'disabled' ?>>선택한 요금제로 신청하기</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- 결제 내역 -->
  <div class="card">
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

  <!-- 안내 -->
  <div class="card">
    <div class="sub-sec-t">이용 안내</div>
    <div class="sub-faq">
      <div>
        <div class="sub-faq__q">결제는 어떻게 이루어지나요?</div>
        <div class="sub-faq__a">등록하신 카드로 선택한 주기(월 또는 연)마다 자동으로 청구됩니다.
          카드번호는 저장하지 않으며, 결제사가 발급한 결제키만 보관합니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">해지하면 자료가 사라지나요?</div>
        <div class="sub-faq__a">아니요. 입력하신 건물 정보와 기록은 삭제되지 않습니다.
          다시 구독하시면 이어서 사용하실 수 있습니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">요금제를 바꿀 수 있나요?</div>
        <div class="sub-faq__a">월 구독과 연 구독은 서로 변경하실 수 있으며, 변경 시점 이후 기간부터 적용됩니다.</div>
      </div>
      <div>
        <div class="sub-faq__q">세금계산서 발행이 되나요?</div>
        <div class="sub-faq__a">필요하시면 아래로 문의해 주세요. 사업자 정보를 확인한 뒤 안내해 드리겠습니다.</div>
      </div>
    </div>
  </div>

  <!-- 문의 -->
  <div class="card">
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
  <?php require __DIR__ . '/memo_widget.php'; ?>
</main>

<script>
function pickPlan(p){
  document.getElementById('planInput').value = p;
  document.querySelectorAll('.sub-plan').forEach(function(el){
    el.classList.toggle('sel', el.dataset.plan === p);
  });
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
