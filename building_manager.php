<?php
// building_manager.php — 건물 소방안전관리자 전용 페이지
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

// 로그인 안 했으면 메인으로
if (!is_logged_in()) { header('Location: /index.php'); exit; }

// 유형이 건물 관리자가 아니면 자기 유형 페이지로 돌려보냄 (관리자는 통과)
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') {
  header('Location: /clients_mini.php'); exit;
}

require_once __DIR__ . '/building_info.php';
$bi     = bi_load();
$biName = trim((string)$bi['name']);
$hasBi  = $biName !== '';

/* 회원을 특정할 수 있는가 (못하면 남의 데이터를 보여주지 않는다) */
$hasUser = !function_exists('app_has_user_key') || app_has_user_key();
$viewUid = function_exists('app_user_key') ? app_user_key() : '';
$adminView = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '' && $viewUid !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($viewUid)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};

/* 기본정보가 얼마나 채워졌는지 — 이름만 적어도 "완료"로 보이던 문제 보완 */
$biProg = function_exists('bi_progress')
  ? bi_progress()
  : ['filled' => $hasBi ? 1 : 0, 'total' => 1, 'percent' => $hasBi ? 100 : 0, 'missing' => []];
$biDone = $biProg['filled'] >= $biProg['total'];

$nick = $_SESSION['nickname'] ?? '사용자';

/* 알림 미확인 개수 — notifications.php 가 아직 없어도 안전하게 0으로 둡니다 */
$unreadCount = 0;
if (function_exists('app_user_key')) {
  $__nUid = app_user_key();
  if ($__nUid !== '') {
    $__nFile = __DIR__ . '/data/notifications/' . $__nUid . '.json';
    if (is_file($__nFile)) {
      $__nList = json_decode((string)@file_get_contents($__nFile), true);
      if (is_array($__nList)) {
        foreach ($__nList as $__n) { if (empty($__n['read'])) $unreadCount++; }
      }
    }
  }
}
$targetNick = $nick;
if ($adminView) {
  $membersFileForName = __DIR__ . '/data/members.json';
  $membersForName = is_file($membersFileForName) ? json_decode((string)@file_get_contents($membersFileForName), true) : [];
  if (is_array($membersForName) && isset($membersForName[$viewUid])) {
    $targetNick = trim((string)($membersForName[$viewUid]['nickname'] ?? '')) ?: $viewUid;
  } else {
    $targetNick = $viewUid;
  }
}

/* ── 건물 기본정보 검토요청 상태
 * building_setup_chat.php 에서 보낸 "건물 기본정보:" 요청만 집계한다.
 * 미확인 요청은 개수로 보여준다.
 * ※ '관리자 확인완료'는 더 이상 여기에 배지로 띄우지 않는다.
 *    관리자가 처리하는 순간 회원에게 알림(notifications)으로 보내므로,
 *    진행 현황에는 남기지 않는다. (아래 $reviewResolvedRecent 는 계산만 남겨 둠) */
$reviewPending = 0;
$reviewResolvedRecent = false;
$adminReviewRows = [];


$reviewResolvedUntil = strtotime('-7 days');
$reviewFile = __DIR__ . '/data/assist_log.json';
$memberUid = $viewUid;
$memberCreatedAt = 0;
if ($memberUid !== '') {
  $membersFile = __DIR__ . '/data/members.json';
  $membersRows = is_file($membersFile) ? json_decode((string)@file_get_contents($membersFile), true) : [];
  if (is_array($membersRows) && isset($membersRows[$memberUid])) {
    $memberCreatedAt = strtotime((string)($membersRows[$memberUid]['created'] ?? '')) ?: 0;
  }
}
if ($memberUid !== '' && is_file($reviewFile)) {
  $reviewRows = json_decode((string)@file_get_contents($reviewFile), true);
  if (is_array($reviewRows)) {
    foreach ($reviewRows as $reviewRow) {
      if (($reviewRow['kind'] ?? '') !== 'review') continue;
      if ((string)($reviewRow['uid'] ?? '') !== $memberUid) continue;

      $requestedAt = strtotime((string)($reviewRow['at'] ?? '')) ?: 0;
      if ($memberCreatedAt > 0 && $requestedAt > 0 && $requestedAt < $memberCreatedAt) continue;

      if ($adminView && ($reviewRow['status'] ?? 'pending') !== 'resolved') {
        $adminReviewRows[] = $reviewRow;
      }

      if (strpos((string)($reviewRow['text'] ?? ''), '건물 기본정보:') !== 0) continue;

      if (($reviewRow['status'] ?? 'pending') === 'resolved') {
        $resolvedAt = strtotime((string)($reviewRow['resolved_at'] ?? ''));
        if ($resolvedAt !== false && $resolvedAt >= $reviewResolvedUntil) {
          $reviewResolvedRecent = true;
        }
      } else {
        $reviewPending++;
      }
    }
  }
}

/* ── 관리자가 배정한 피난 시뮬레이션 ── */
require_once __DIR__ . '/evac_common.php';
$evacUid    = $adminView ? $viewUid : evac_current_uid();
$evacModels = evac_models_for($evacUid);

/* 배정이 없는 회원은 관리자에게 배정을 요청할 수 있다.
   요청 목록: data/evac_requests.json = { uid: {at: "..."} } */
$evacRequested = false;
if (!$evacModels && $evacUid !== '') {
  $rf = __DIR__ . '/data/evac_requests.json';
  if (is_file($rf)) {
    $ra = json_decode((string)@file_get_contents($rf), true);
    $evacRequested = is_array($ra) && isset($ra[$evacUid]);
  }
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf'];

/* ── 배정 신청 팝업에 미리 채워 넣을 값 ──
 * 기본정보(building_info.php)에 이미 적어둔 내용을 그대로 가져옵니다.
 * 소방안전관리자는 '주' 담당을 우선으로, 없으면 첫 번째 사람을 씁니다. */
$reqMgrName = '';
$reqMgrTel  = '';
if (!empty($bi['mgrs']) && is_array($bi['mgrs'])) {
  $pick = null;
  foreach ($bi['mgrs'] as $m) {
    if (!is_array($m)) continue;
    if (trim((string)($m['name'] ?? '')) === '') continue;
    if ($pick === null) $pick = $m;
    if (strpos((string)($m['type'] ?? ''), '주') === 0) { $pick = $m; break; }
  }
  if ($pick) {
    $reqMgrName = trim((string)($pick['name'] ?? ''));
    $reqMgrTel  = trim((string)($pick['tel'] ?? ''));
  }
}
/* 관리자 전화가 비어 있으면 건물 대표번호로 대신 채워줍니다 */
if ($reqMgrTel === '') $reqMgrTel = trim((string)($bi['tel'] ?? ''));

$reqPrefill = [
  'building_name' => $biName,
  'address'       => trim((string)($bi['address'] ?? '')),
  'manager_name'  => $reqMgrName,
  'manager_phone' => $reqMgrTel,
];
/* 팝업에서 바로 보낼 수 있는 상태인가 (비어 있으면 팝업에서 직접 채웁니다) */
$reqReady = $reqPrefill['address'] !== '' && $reqPrefill['manager_name'] !== '' && $reqPrefill['manager_phone'] !== '';

/* ── 업무 진행 상태 자동 감지 ──
 * 각 서식의 실제 저장 파일을 읽어 완료 여부를 판단한다. */
if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
$mon = date('n') . '월';

$rawKey = $viewUid !== '' ? $viewUid : (string)($_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest')));
$keySafe = !preg_match('#[/\\\\]|\.\.#', $rawKey);          // 경로 문자 방어
$wlKey   = preg_replace('/[^A-Za-z0-9_]/', '_', $rawKey);   // work_log.php와 같은 규칙

/* 구독(Pro) 상태 — 우측 패널의 안내를 무엇으로 보여줄지 정하는 데 씁니다.
   ★ 저장 위치는 subscribe_page.php / toss_billing.php 와 똑같이
     app_user_key() 기준이어야 합니다(다르면 파일을 못 찾습니다). */
$proStatus = 'none';
/* 요금제·다음 결제일 등 자세한 내용은 subscribe_page.php 에서 보여줍니다.
   여기서는 '이용 중' 여부만 쓰지만, 필요해질 때를 위해 읽어는 둡니다. */
$proPlan = ''; $proNext = ''; $proPrice = 0;
if (!function_exists('app_user_key') && is_file(__DIR__ . '/user_key.php')) {
  require_once __DIR__ . '/user_key.php';
}
$__subKey = function_exists('app_user_key') ? app_user_key() : '';
if ($__subKey !== '') {
  $subFile = __DIR__ . '/data/subscribe/' . $__subKey . '/subscription.json';
  if (is_file($subFile)) {
    $subData = json_decode((string)@file_get_contents($subFile), true);
    if (is_array($subData)) {
      $proStatus = (string)($subData['status'] ?? 'none');
      $proPlan   = (string)($subData['plan_name'] ?? '');
      $proNext   = (string)($subData['next_billing'] ?? '');
      $proPrice  = (int)($subData['price'] ?? 0);
    }
  }
}
$isPro = ($proStatus === 'active');

/* Pro 기능 링크 — 구독 중이면 원래 페이지로, 아니면 구독 페이지로 보냅니다.
   카드는 구독 여부와 상관없이 똑같이 보여줍니다(있는 줄 알아야 구독하니까요). */
$proLink = function (string $path) use ($isPro, $url): string {
  return $isPro ? $url($path) : $url('/subscribe_page.php');
};

/* ① 이번 달 업무수행 기록표: data/worklog/{uid}/mYYYY-MM.json 존재 여부 */
$doneWorkLog = is_file(__DIR__ . '/data/worklog/' . $wlKey . '/m' . date('Y-m') . '.json');

/* ② 자위소방대 교육·훈련을 올해 실시했는가
 *    ★ 예전에는 편성표(_jawi.json)가 있으면 교육까지 한 것으로 처리했는데,
 *      편성표는 '명단'이고 교육은 '실시 기록'이라 서로 다른 것입니다.
 *      명단만 만들어도 '교육 완료'로 표시되던 문제가 있어 분리했습니다.
 *      편성표 존재 여부는 아래 $hasRoster 가 따로 판단합니다. */
$doneJawi = false;

$jawiStatus = null;   // 올해 자위소방대 교육·훈련 기록의 진행 상태

/* ②-1 자위소방대 편성표(명단)를 만들어 두었는가
 *     교육·훈련 기록부가 이 명단을 불러 쓰므로, 편성표가 먼저입니다.
 *     저장 위치는 fire_plan_jawi.php 와 같은 규칙(app_user_key)을 씁니다. */
$hasRoster   = false;
$rosterCount = 0;
$rosterName  = '';
if ($viewUid !== '') {
  $rf2 = __DIR__ . '/data/fireplan/' . $viewUid . '/_jawi.json';
  if (is_file($rf2)) {
    $ra2 = json_decode((string)@file_get_contents($rf2), true);
    if (is_array($ra2) && $ra2) {
      $rosterCount = count($ra2);
      $hasRoster   = true;
      $last = end($ra2);
      if (is_array($last)) $rosterName = trim((string)($last['site'] ?? $last['siteName'] ?? ''));
    }
  }
}

/* ②-2 자위소방대 교육·훈련 — 새 기록부(jawi_db.php)가 있으면 그쪽을 먼저 봅니다 */
$jdb = __DIR__ . '/jawi_db.php';
if (is_file($jdb)) {
  require_once $jdb;
  if (function_exists('jw_done_this_year')) {
    if (jw_done_this_year()) $doneJawi = true;
    if (function_exists('jw_year_status')) $jawiStatus = jw_year_status();
  }
}

/* ③ 올해 소방훈련·교육: train_db.php의 tr_list()로 올해 실시 기록 확인
 *    (이미 선언된 함수와 이름이 겹치면 치명적 오류가 나므로, 겹치지 않을 때만 불러온다) */
$doneTrain   = false;
$trainStatus = null;   // 올해 훈련 기록의 진행 상태
$tdb = __DIR__ . '/train_db.php';
if (is_file($tdb)) {
  $tsrc = (string)@file_get_contents($tdb);
  $safe = true;
  if (preg_match_all('/^\s*function\s+([A-Za-z_]\w*)/mi', $tsrc, $tm)) {
    foreach ($tm[1] as $fn) { if (function_exists($fn)) { $safe = false; break; } }
  }
  if ($safe) {
    require_once $tdb;
    if (function_exists('tr_list')) {
      try {
        /* 날짜만 적힌 빈 기록이 완료로 잡히지 않도록,
           훈련 일시·종류·참석 인원이 모두 채워진 것만 완료로 봅니다. */
        if (function_exists('tr_done_this_year')) {
          $doneTrain = tr_done_this_year();
          /* 몇 %인지, 무엇이 남았는지도 같이 가져옵니다 */
          if (function_exists('tr_year_status')) $trainStatus = tr_year_status();
        } else {
          foreach (tr_list() as $r) {
            $d = (string)($r['train_date'] ?? '');
            if (strncmp($d, date('Y'), 4) === 0) { $doneTrain = true; break; }
          }
        }
      } catch (Throwable $e) { /* 감지 실패 시 미진행으로 표시 */ }
    }
  }
}
?>
<?php
$PAGE_TITLE = '건물 소방안전관리';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;              // 이 페이지는 이미 위에서 로그인 필수 처리했으므로 항상 true
$ACCOUNT_NICK = $nick;
$ACCOUNT_IS_ADMIN = is_admin();
$ACCOUNT_UNREAD = $unreadCount;    // 위에서 이미 계산한 값을 그대로 재사용
require __DIR__ . '/_header.php';
?>
<style>
/* building_manager.php 전용 -- _header.php 가 :root.nav.wrap.card.page-head 기본값을 이미 제공합니다.
   여기서는 이 페이지만의 것(진행 패널.평가 배너.팝업 등)만 추가.보완합니다. */
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:14px}
.badge span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.page-head h1{font-size:clamp(24px,3.5vw,34px);font-weight:700;letter-spacing:-.5px;margin-bottom:8px}
.page-head p{color:var(--mut2);font-size:15px}
.wrap{max-width:1120px;margin:0 auto;padding:22px 24px 40px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:12px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:15px 17px;display:flex;flex-direction:column;gap:6px;min-height:104px}
.card h3{font-size:16px;font-weight:700;line-height:1.4}
.card--link{color:inherit;transition:.15s}
.card--link h3{color:var(--brand2)}
.card--link:hover{border-color:var(--brand);box-shadow:0 8px 20px rgba(37,99,235,.08);transform:translateY(-1px)}
.card__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card .badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;
  background:#ecfdf5;color:#047857;white-space:nowrap;border:0;margin:0}
.card .badge--plan{background:#fff7ed;color:#b45309}
.card .badge--setup{background:#eef2ff;color:var(--brand2)}
.card__arrow{margin-left:auto;color:var(--mut);font-size:15px;flex-shrink:0}
.card__sub{color:var(--mut2);font-size:12.5px;line-height:1.55;margin-top:auto}
.card--soon{opacity:.7}
.card--soon h3{color:var(--fg)}
.tag-soon{font-size:11px;padding:3px 9px;border-radius:999px;background:#f1f5f9;color:var(--mut2)}
.card--setup{border-color:#c7dbff;background:linear-gradient(180deg,#f8fbff,#fff)}
.setup-badge{font-size:11px;padding:3px 9px;border-radius:999px;font-weight:700}
.setup-badge--ok{background:#ecfdf5;color:#047857}
.setup-badge--need{background:#fff7ed;color:#b45309}
.setup-badge--review{background:#fef3c7;color:#b45309}
.setup-badge--admin{background:#e0f2fe;color:#0369a1}
@media(max-width:680px){.page-head__inner{padding:36px 20px 28px}}
/* -- 배정된 피난 시뮬레이션 -- */
.evac-strip{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px}
.evac-strip__label{font-size:12px;font-weight:700;color:var(--mut2);
  display:inline-flex;align-items:center;gap:6px}
.evac-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 15px;border-radius:999px;
  border:1px solid #b6d0f5;background:#fff;color:var(--brand2);font-size:13px;font-weight:700;
  box-shadow:0 2px 8px rgba(37,99,235,.08);transition:.15s}
.evac-chip:hover{border-color:var(--brand);box-shadow:0 6px 16px rgba(37,99,235,.15);transform:translateY(-1px)}
.evac-chip .ic{font-size:14px}
.evac-chip .go{color:var(--mut);font-weight:400}
.evac-qr-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;
  border-radius:999px;border:1px solid #b6d0f5;background:#fff;cursor:pointer;font-size:15px;
  box-shadow:0 2px 8px rgba(37,99,235,.08);transition:.15s;flex-shrink:0}
.evac-qr-btn:hover{border-color:var(--brand);transform:translateY(-1px)}
.qrov{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;
  align-items:center;justify-content:center;z-index:100;padding:20px}
.qrov.on{display:flex}
.qrbox{background:#fff;border-radius:16px;padding:24px;max-width:320px;width:100%;text-align:center}
.qrbox h4{font-size:15px;font-weight:800;margin:0 0 4px}
.qrbox .qsub{font-size:12px;color:var(--mut2);margin:0 0 14px}
.qrbox .qimg{width:220px;height:220px;margin:0 auto 12px;display:flex;align-items:center;
  justify-content:center;border:1px solid var(--bd);border-radius:12px}
.qrbox .qurl{font-size:11px;color:var(--mut2);word-break:break-all;background:var(--bg);
  border-radius:8px;padding:8px 10px;margin-bottom:14px;font-family:ui-monospace,monospace}
.qrbox .qclose{width:100%;padding:10px;border:0;border-radius:10px;background:var(--brand);
  color:#fff;font-size:13px;font-weight:700;cursor:pointer}

/* -- 시뮬레이션 배정 요청 -- */
.evac-req{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;
  padding:13px 16px;border-radius:12px;background:#fff;border:1px dashed #b6d0f5}
.evac-req__t{font-size:13px;font-weight:700;color:var(--brand2)}
.evac-req__d{font-size:12px;color:var(--mut2)}
.evac-req__btn{margin-left:auto;padding:9px 16px;border:0;border-radius:10px;background:var(--brand);
  color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.evac-req__btn:hover{filter:brightness(1.08)}
.evac-req__btn:disabled{background:#e8edf5;color:#8a94a6;cursor:default}
.evac-req--done{border-style:solid;border-color:#b7e2c6;background:#f6fdf8}
.evac-req--done .evac-req__t{color:#15803d}
.evac-req--wait{background:#fff7ed;border-color:#f6d8a8}
.evac-req--wait .evac-req__t{color:#92400e}
/* 자동알림 서비스 구독 배너 (배정 시뮬레이션 아래) */
.subs{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;
  padding:13px 16px;border-radius:12px;background:#f7f5ff;border:1px solid #ddd6fe}
.subs__t{font-size:13px;font-weight:700;color:#5b3fd1}
.subs__d{font-size:12px;color:var(--mut2)}
.subs__btn{margin-left:auto;padding:9px 16px;border:0;border-radius:10px;background:#6d4aff;
  color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;
  display:inline-flex;align-items:center;text-decoration:none}
.subs__btn:hover{filter:brightness(1.08)}

/* ── Pro 기능 카드: 미구독은 어둡게, 구독 중은 밝게 ── */
/* ── Pro 기능 카드 (우측 패널 · 진행 현황 아래) ────────────
   폭이 좁으므로 세로로 쌓습니다. 글자는 읽기 편한 크기를 유지합니다. */
.prolock{margin-top:12px;background:#fff;border:1px solid var(--bd);color:var(--fg);
  border-radius:16px;padding:16px 17px;box-shadow:0 4px 16px rgba(15,30,60,.06)}
.prolock__hd{display:block;padding-bottom:13px;margin-bottom:13px;
  border-bottom:1px solid var(--bd)}
.prolock__badge{display:inline-block;font-size:11px;font-weight:900;letter-spacing:.05em;
  padding:4px 10px;border-radius:7px;background:#fff7ed;color:#b45309;
  border:1px solid #f6d8a8;margin-bottom:9px}
.prolock__ttx b{display:block;font-size:15px;font-weight:800;color:var(--fg);line-height:1.45}
.prolock__ttx small{display:block;font-size:12.5px;color:var(--mut2);margin-top:4px;line-height:1.6}
.prolock__btn{display:block;text-align:center;margin-top:11px;
  background:var(--brand);color:#fff;border-radius:10px;
  padding:11px;font-size:14px;font-weight:800;text-decoration:none;transition:.14s}
.prolock__btn:hover{filter:brightness(1.08);color:#fff}

.prolock__list{display:flex;flex-direction:column;gap:0;
  border:1px solid var(--bd);border-radius:11px;overflow:hidden}
.prolock__i{display:flex;align-items:center;gap:11px;text-decoration:none;
  background:#fff;padding:13px 14px;min-height:60px;transition:.14s}
.prolock__i+.prolock__i{border-top:1px solid var(--bd)}
.prolock__i:hover{background:#f4f8ff}
.prolock__ic{width:34px;height:34px;flex-shrink:0;display:inline-flex;
  align-items:center;justify-content:center;border-radius:10px;
  background:#eef4ff;font-size:17px;line-height:1}
.prolock__i > div{flex:1;min-width:0}
.prolock__i b{display:block;font-size:13.5px;font-weight:700;color:var(--fg);line-height:1.4}
.prolock__i small{display:block;font-size:11.5px;color:var(--mut2);margin-top:2px;line-height:1.5}
.prolock__go{flex-shrink:0;font-size:15px;color:var(--mut);font-weight:700}
.prolock__i:hover .prolock__go{color:var(--brand2)}

/* 구독 중 — 초록 계열 강조만 다릅니다 */
.prolock--on{border-color:#bbf7d0;box-shadow:0 4px 16px rgba(21,128,61,.08)}
.prolock--on .prolock__badge{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.prolock--on .prolock__btn{background:#ecfdf5;color:#047857;border:1px solid #bbf7d0}
.prolock--on .prolock__btn:hover{background:#dcfce7;border-color:#86efac;color:#047857;filter:none}
.prolock--on .prolock__ic{background:#f0fdf4}
@media(max-width:760px){.prolock{margin-top:14px}}
.prolock--on .prolock__go{color:var(--mut);transition:transform .14s,color .14s}
.prolock--on .prolock__i:hover .prolock__go{color:var(--brand2);transform:translateX(2px)}

@media(max-width:680px){
  .prolock--on .prolock__list{grid-template-columns:1fr}
  .prolock--on .prolock__i+.prolock__i{border-left:0;border-top:1px solid var(--bd)}
}

@media(max-width:560px){
  .prolock__hd{gap:9px}
  .prolock__btn{width:100%;text-align:center;order:3}
}

/* 전체 인쇄 카드 (자동알림 배너 아래) — 같은 형태에 색만 구분합니다 */
.prt{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;
  padding:13px 16px;border-radius:12px;background:#f0f9ff;border:1px solid #bae6fd}
.prt__t{font-size:13px;font-weight:700;color:#0369a1}
.prt__d{font-size:12px;color:var(--mut2)}
.prt__btn{margin-left:auto;padding:9px 16px;border:0;border-radius:10px;background:#0284c7;
  color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;
  display:inline-flex;align-items:center;text-decoration:none}
.prt__btn:hover{filter:brightness(1.08);color:#fff}
@media(max-width:560px){.prt__btn{margin-left:0;width:100%;justify-content:center}}
.evac-req__btn--go{margin-left:auto;display:inline-flex;align-items:center;text-decoration:none;
  background:#b45309;color:#fff;font-weight:700;font-size:13px;padding:9px 16px;border-radius:10px}
.evac-req__btn--go:hover{filter:brightness(1.08);color:#fff}
@media(max-width:560px){.evac-req__btn,.evac-req__btn--go{margin-left:0;width:100%;justify-content:center}}

/* -- 배정 신청 팝업 -- */
.rqov{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;
  align-items:center;justify-content:center;z-index:200;padding:18px}
.rqov.on{display:flex}
.rqbox{background:#fff;border-radius:16px;padding:22px;max-width:420px;width:100%;
  max-height:90vh;overflow:auto}
.rqbox h4{font-size:16px;font-weight:800;margin:0 0 4px}
.rqbox .rsub{font-size:12.5px;color:var(--mut2);margin:0 0 16px;line-height:1.55}
.rqf{margin-bottom:12px}
.rqf label{display:block;font-size:12px;font-weight:700;color:var(--mut2);margin-bottom:5px}
.rqf label .req{color:#dc2626;margin-left:2px}
.rqf input{width:100%;padding:10px 12px;border:1px solid var(--bd2);border-radius:9px;
  font-size:14px;font-family:inherit;color:var(--fg);background:#fff}
.rqf input:focus{outline:0;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.rqf__hint{font-size:11px;color:var(--mut);margin-top:4px}
.rqerr{display:none;font-size:12.5px;font-weight:600;color:#b91c1c;background:#fef2f2;
  border:1px solid #fecaca;border-radius:9px;padding:9px 11px;margin-bottom:12px}
.rqerr.on{display:block}
.rqnote{font-size:11.5px;color:var(--mut);background:var(--bg);border-radius:9px;
  padding:9px 11px;margin:2px 0 14px;line-height:1.55}
.rqbtns{display:flex;gap:8px}
.rqbtns button{flex:1;padding:11px;border-radius:10px;font-size:13.5px;font-weight:700;
  cursor:pointer;font-family:inherit;border:1px solid var(--bd2);background:#fff;color:var(--mut2)}
.rqbtns .rqgo{border:0;background:var(--brand);color:#fff}
.rqbtns .rqgo:hover{filter:brightness(1.08)}
.rqbtns .rqgo:disabled{background:#e8edf5;color:#8a94a6;cursor:default;filter:none}

/* -- 진행 현황 (헤더 우측 패널) -- */
/* 헤더를 낮춰 본문이 한 화면에 들어오게 합니다 */
.page-head__inner{display:flex;gap:24px;align-items:flex-start;padding:24px 24px 20px!important}
.page-head h1{font-size:26px!important;margin-bottom:5px!important}
.page-head p{font-size:14px!important}
.page-head__label{margin-bottom:9px!important}
.ph-left{flex:1;min-width:0}
/* Pro 배너와 진행 현황을 한 덩어리로 묶어 세로로 쌓습니다 */
.rightcol{width:290px;flex-shrink:0;display:flex;flex-direction:column}
@media(max-width:760px){.rightcol{width:100%}}

/* ── Pro 구독 안내 (진행 현황 위) ── */
.pro{width:236px;flex-shrink:0;border-radius:14px;padding:15px 16px;margin-bottom:12px;
  background:#111827;color:#e2e8f0;border:1px solid #293548;
  box-shadow:0 6px 18px rgba(15,23,42,.18)}
.pro__row{display:flex;align-items:center;gap:8px;margin-bottom:7px}
.pro__badge{font-size:10px;font-weight:900;letter-spacing:.06em;padding:3px 8px;border-radius:6px;
  background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#3b2500}
.pro__badge--on{background:#ecfdf5;color:#047857;border:1px solid #bbf7d0}
.pro__badge--wait{background:#475569;color:#cbd5e1}
.pro__t{font-size:13.5px;font-weight:800;color:#fff}
.pro__d{font-size:11.5px;color:#94a3b8;line-height:1.7;margin-bottom:11px}
.pro__btn{display:block;text-align:center;background:#fff;color:#0f172a;border-radius:9px;
  padding:9px;font-size:12.5px;font-weight:800;text-decoration:none;transition:.14s}
.pro__btn:hover{background:#f1f5f9;color:#0f172a}
.pro__price{font-size:10.5px;color:#64748b;text-align:center;margin-top:7px}
.pro__link{display:inline-block;font-size:11.5px;color:#93c5fd;text-decoration:none;font-weight:700}
.pro__link:hover{color:#bfdbfe}
.pro--on{background:#fff;color:var(--fg);border-color:var(--bd);
  box-shadow:0 4px 14px rgba(15,30,60,.06)}
/* 구독 중 — 요금제·다음 결제일까지 보여줍니다 */
.pro__dot{width:7px;height:7px;border-radius:50%;background:#34d399;margin-left:auto;
  box-shadow:0 0 0 3px rgba(52,211,153,.22);animation:proPulse 2.4s ease-in-out infinite}
@keyframes proPulse{0%,100%{opacity:1}50%{opacity:.45}}
@media(prefers-reduced-motion:reduce){.pro__dot{animation:none}}
.pro--on .pro__t{color:var(--fg)}
.pro--on .pro__d{color:var(--mut2)}
.pro--wait .pro__d{color:#94a3b8}

/* 구독 관리 버튼 */
.pro__btn--on{background:#fff;color:var(--brand2);
  border:1px solid #c7dbff;cursor:pointer}
.pro__btn--on:hover{background:#f8fbff;border-color:var(--brand);color:var(--brand2)}
.pro__btn--wait{background:rgba(255,255,255,.10);color:#cbd5e1;
  border:1px solid rgba(255,255,255,.16)}
.pro__btn--wait:hover{background:rgba(255,255,255,.16);color:#fff}
.pro--on .pro__price{color:var(--mut)}
.pro--wait{background:#111827}
@media(max-width:760px){.pro{width:100%}}

/* ── 진행 현황 ──────────────────────────────────────────────
   나이가 있으신 분들도 편하게 보실 수 있도록
   글자·번호·누르는 영역을 넉넉히 잡았습니다. */
.prog{width:290px;flex-shrink:0;background:#fff;border:1px solid var(--bd);border-radius:16px;
  padding:20px 20px;box-shadow:0 4px 16px rgba(15,30,60,.06)}
.prog__t{font-size:15px;font-weight:800;color:var(--fg);letter-spacing:0;margin-bottom:14px;
  padding-bottom:12px;border-bottom:1px solid var(--bd)}
.pstep{display:flex;align-items:center;gap:12px;padding:14px 6px;font-size:15px;font-weight:700;
  color:var(--mut2);text-decoration:none;border-radius:10px;transition:.12s;min-height:56px}
a.pstep:hover{background:#f2f6fd}
.pstep .no{width:30px;height:30px;border-radius:50%;background:#e8edf5;color:var(--mut2);
  display:inline-flex;align-items:center;justify-content:center;font-size:14px;
  font-weight:800;flex-shrink:0}
.pstep__label{min-width:0;flex:1;line-height:1.45}
.pstep--done{color:#15803d}
.pstep--done .no{background:#22c55e;color:#fff}
.pstep--now{color:var(--brand2)}
.pstep--now .no{background:var(--brand);color:#fff;box-shadow:0 0 0 4px rgba(37,99,235,.16)}
.pstep+.pstep{border-top:1px dashed var(--bd)}
.ptag{margin-left:auto;font-size:12px;font-weight:800;padding:4px 11px;border-radius:999px;flex-shrink:0;white-space:nowrap}
.ptag--done{background:#f0fdf4;color:#15803d}
.ptag--no{background:#fef2f2;color:#dc2626}
.ptag--wait{background:#eef1f6;color:#8a94a6}
.ptag--part{background:#eff6ff;color:#1d4ed8}
.ptag--always{background:#faf5ff;color:#7e22ce}
.ptag--review{background:#fef3c7;color:#b45309}
.ptag--admin{background:#e0f2fe;color:#0369a1}
.prog__hint{font-size:13px;color:var(--mut2);margin-top:14px;text-align:center;line-height:1.6}
.prog__start{font-size:13.5px;font-weight:800;color:var(--brand2);text-align:center;
  margin:2px 0 6px;animation:startNudge 1.8s ease-in-out infinite}
@keyframes startNudge{0%,100%{transform:translateY(0)}50%{transform:translateY(2px)}}
.pstep--first{background:#eff6ff;border-radius:10px;padding-left:8px;padding-right:8px;
  box-shadow:0 0 0 2px rgba(37,99,235,.18)}
.pstep--first+.pstep{border-top:0}
.pstep--lock{opacity:.45}
.pstep--lock .no{background:#eef1f6;color:#8a94a6}
@media(prefers-reduced-motion:reduce){.prog__start{animation:none}}

@media(max-width:760px){
  .page-head__inner{flex-direction:column}
  .prog{width:100%}
}

/* -- 지금 할 일 배너 -- */
.todo{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:15px 18px;border-radius:14px;
  margin-bottom:6px;font-size:14px;font-weight:600;line-height:1.55}
.todo--need{background:#fff7ed;border:1px solid #f6d8a8;color:#92400e}
.todo--ok{background:#eef4ff;border:1px solid #c7dbff;color:var(--brand2)}
.todo .btn2{margin-left:auto;padding:9px 16px;border-radius:10px;background:var(--brand);color:#fff;
  font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;transition:.15s}
.todo .btn2:hover{filter:brightness(1.08)}
@media(max-width:560px){.todo .btn2{margin-left:0;width:100%;text-align:center}}

/* -- 주기별 섹션 -- */
.sec{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:18px 0 9px}
.sec__chip{font-size:11px;font-weight:800;padding:4px 11px;border-radius:999px;letter-spacing:.02em}
.chip-first{background:#eef2ff;color:var(--brand2)}
.chip-month{background:#f0fdf4;color:#15803d}
.chip-year{background:#fefce8;color:#a16207}
.chip-always{background:#faf5ff;color:#7e22ce}
.sec__t{font-size:15px;font-weight:800}
.sec__d{font-size:12.5px;color:var(--mut2)}
.sec--dim .sec__lock{font-size:12px;color:#b45309;font-weight:700}
.grid--dim{opacity:.5}
.due{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;
  padding:3px 9px;border-radius:999px}
.due--need{background:#fff7ed;color:#b45309}
.due--ok{background:#f0fdf4;color:#15803d}
.due--wait{background:#eef1f6;color:#8a94a6}
.badge--roster{background:#fff1f2;color:#be123c}
.card--roster{border-color:#fbcfe8}
.card--next{box-shadow:0 0 0 2px rgba(190,18,60,.16);background:linear-gradient(180deg,#fff,#fff6f8)}
.card--wait{opacity:.62}
.ptag--part{background:#eff6ff;color:#1d4ed8}
.psub{display:flex;gap:12px;flex-wrap:wrap;padding:2px 0 8px 42px;font-size:13px;color:var(--mut2);line-height:1.6}
.psub--why{display:block;padding:0 4px 10px 42px;line-height:1.65}
.psub__i.is-ok{color:#15803d;font-weight:700}
.cardprog{margin-top:11px;padding-top:11px;border-top:1px dashed var(--bd)}
.cardprog__bar{height:5px;background:#eef2f7;border-radius:3px;overflow:hidden}
.cardprog__bar i{display:block;height:100%;background:var(--brand);
  border-radius:3px;transition:width .4s cubic-bezier(.2,.7,.3,1)}
.cardprog__t{font-size:12px;color:var(--mut2);margin-top:6px;line-height:1.5}
.cardprog__t b{color:var(--brand2);font-weight:800;margin-right:3px}
.cardprog__t--ok{color:#15803d}
.cardprog__t--ok b{color:#15803d}
/* ── 첫 방문 안내 팝업 ── */
.gdov{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;
  align-items:center;justify-content:center;z-index:300;padding:20px}
.gdov.on{display:flex}
.gdbox{background:#fff;border-radius:16px;padding:26px 24px 20px;max-width:400px;width:100%;
  box-shadow:0 20px 50px rgba(15,30,60,.25);text-align:center}
.gdbox__ico{font-size:34px;margin-bottom:12px}
.gdbox h4{font-size:17px;font-weight:800;margin:0 0 8px;line-height:1.45}
.gdbox p{font-size:13.5px;color:var(--mut2);margin:0 0 18px;line-height:1.7}
.gdbox__hi{display:inline-block;background:#eff6ff;color:var(--brand2);
  font-weight:700;padding:2px 8px;border-radius:6px}
.gdbox__btn{width:100%;padding:12px;border:0;border-radius:11px;background:var(--brand);
  color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.gdbox__btn:hover{filter:brightness(1.08)}
.gdbox__again{display:flex;align-items:center;justify-content:center;gap:7px;
  margin-top:14px;font-size:12.5px;color:var(--mut);cursor:pointer}
.gdbox__again input{cursor:pointer}

/* 안내가 가리키는 진행 현황 패널을 잠깐 강조합니다 */
.prog.gd-hi{box-shadow:0 0 0 4px rgba(37,99,235,.35),0 4px 14px rgba(15,30,60,.05);
  border-color:var(--brand);transition:box-shadow .3s,border-color .3s}

/* ── 한 화면 업무 관제판 ────────────────────────────────── */
.dashboard-shell{max-width:1120px;margin:0 auto;padding:18px 24px 40px;
  display:grid;grid-template-columns:minmax(0,1fr) 290px;column-gap:24px;align-items:start}
.dashboard-shell .page-head,.dashboard-shell .page-head__inner{display:contents}
.dashboard-shell .ph-left{grid-column:1;grid-row:1;padding:2px 0 14px}
.dashboard-shell .rightcol{grid-column:2;grid-row:1 / span 2;width:290px}
.dashboard-shell .wrap{grid-column:1;grid-row:2;max-width:none;width:100%;margin:0;padding:0;
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}

.dashboard-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px}
.dashboard-head__copy{min-width:0}
.dashboard-head .badge{margin-bottom:8px}
.dashboard-head h1{margin:0 0 4px!important}
.dashboard-head p{margin:0}
.plan-link{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;
  min-height:36px;padding:8px 13px;border:1px solid var(--bd2);border-radius:8px;
  background:#fff;color:var(--mut2);font-size:12px;font-weight:800;text-decoration:none}
.plan-link:hover{border-color:var(--brand);color:var(--brand2)}
.plan-link--on{border-color:#bbf7d0;background:#f0fdf4;color:#047857}
.plan-link--wait{background:#f8fafc;color:#64748b}

/* 현재 해야 할 일 하나만 강하게 보여줍니다. */
.mission{grid-column:1 / -1;display:flex;align-items:center;gap:18px;min-height:78px;
  padding:14px 16px;border-radius:12px;background:#111827;color:#fff}
.mission__copy{flex:1;min-width:0}
.mission__label{display:block;margin-bottom:5px;color:#93c5fd;font-size:10px;
  font-weight:900;letter-spacing:0}
.mission__text{display:block;font-size:14px;line-height:1.45}
.mission__text b{color:#fff}
.mission__btn{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;
  min-height:38px;padding:9px 14px;border-radius:8px;background:#fff;color:#111827;
  font-size:12.5px;font-weight:800;text-decoration:none;white-space:nowrap}
.mission__btn:hover{background:#eff6ff;color:#1d4ed8}
.mission--error{background:#7f1d1d}

.ops-head{grid-column:1 / -1;display:flex;align-items:baseline;gap:9px;margin-top:5px;padding:3px 1px}
.ops-head b{font-size:14px;color:var(--fg)}
.ops-head span{font-size:11.5px;color:var(--mut2)}
.dashboard-shell .wrap>.section,.dashboard-shell .wrap>.todo{grid-column:1 / -1}
.dashboard-shell .sec{display:none}
.dashboard-shell .grid{display:contents}
.dashboard-shell .grid--dim>.card{opacity:.5}

/* 실제 업무 여섯 개를 동일한 크기의 3 x 2 카드로 정리합니다. */
.dashboard-shell .card{min-height:110px;padding:12px 13px;gap:5px;border-radius:10px;
  background:#fff;box-shadow:none}
.dashboard-shell .card--setup,.dashboard-shell .card--next{background:#fff}
.dashboard-shell .card h3{font-size:14px;line-height:1.35}
.dashboard-shell .card__top{gap:6px}
.dashboard-shell .card__sub{font-size:11.5px;line-height:1.4;
  display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.dashboard-shell .card .badge,.dashboard-shell .setup-badge,
.dashboard-shell .due{font-size:10px;padding:3px 7px}
.dashboard-shell .cardprog{margin-top:7px;padding-top:7px}
.dashboard-shell .cardprog__t{font-size:10.5px;margin-top:4px;line-height:1.35;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* PRO 기능은 별도 카드 대신 가로 실행 도구로 둡니다. */
.protools{grid-column:1 / -1;margin-top:5px;border:1px solid var(--bd);
  border-radius:10px;background:#fff;overflow:hidden}
.protools__head{display:flex;align-items:center;justify-content:space-between;gap:12px;
  padding:9px 12px;border-bottom:1px solid var(--bd)}
.protools__head b{font-size:11px;color:var(--fg)}
.protools__head span{font-size:10.5px;color:var(--mut2)}
.protools__list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}
.protool{display:flex;align-items:center;gap:8px;min-width:0;padding:10px 12px;
  color:var(--fg);text-decoration:none;transition:background .14s}
.protool+.protool{border-left:1px solid var(--bd)}
.protool:hover{background:#f8fbff}
.protool__ic{font-size:15px;flex-shrink:0}
.protool b{font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.protool__go{margin-left:auto;color:var(--mut);font-size:12px}

@media(max-width:900px){
  .dashboard-shell{grid-template-columns:1fr;padding:18px 20px 40px}
  .dashboard-shell .ph-left{grid-column:1;grid-row:1}
  .dashboard-shell .wrap{grid-column:1;grid-row:2;grid-template-columns:repeat(2,minmax(0,1fr))}
  .dashboard-shell .rightcol{grid-column:1;grid-row:3;width:100%;margin-top:14px}
  .dashboard-shell .prog{width:100%}
}
@media(max-width:620px){
  .dashboard-head{align-items:flex-start}
  .dashboard-head p{display:none}
  .dashboard-shell .wrap{grid-template-columns:1fr}
  .mission{align-items:flex-start;flex-direction:column;gap:11px}
  .mission__btn{width:100%}
  .protools__head{align-items:flex-start;flex-direction:column;gap:2px}
  .protools__list{grid-template-columns:1fr}
  .protool+.protool{border-left:0;border-top:1px solid var(--bd)}
}

/* ── 화면 위계·클릭 유도 보완 ───────────────────────────── */
.dashboard-shell{column-gap:28px}
.dashboard-shell .wrap{gap:12px}
.dashboard-shell .card{
  position:relative;min-height:136px;padding:17px 18px 15px;border-radius:14px;
  border-color:#e2e8f0;box-shadow:0 1px 2px rgba(15,23,42,.03);
  transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease
}
.dashboard-shell .card--link:hover{
  transform:translateY(-2px);border-color:#a9c4f5;
  box-shadow:0 10px 26px rgba(30,64,175,.09)
}
.dashboard-shell .card h3{font-size:15px;letter-spacing:-.18px;margin-top:2px}
.dashboard-shell .card__sub{font-size:12px;line-height:1.55;-webkit-line-clamp:2}
.dashboard-shell .card__top{min-height:25px}
.dashboard-shell .card__arrow{
  margin-left:auto;display:inline-flex;align-items:center;justify-content:center;
  min-height:27px;padding:4px 9px;border-radius:7px;background:#eff6ff;
  color:#1d4ed8;font-size:0;font-weight:800
}
.dashboard-shell .card__arrow::after{content:'열기';font-size:10.5px}
.dashboard-shell .card--link:hover .card__arrow{background:#2563eb;color:#fff}
.dashboard-shell .card--next{
  border-color:#f1b8ca;box-shadow:0 0 0 3px rgba(190,18,60,.07)
}
.dashboard-shell .card--wait{opacity:.72;background:#f8fafc}
.dashboard-shell .due--ok::first-letter{font-size:0}

.mission{border:1px solid #253249;box-shadow:0 8px 22px rgba(15,23,42,.10)}
.mission__label{letter-spacing:.08em}
.mission__btn{padding-left:18px;padding-right:18px}

.prog{border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(15,30,60,.06)}
.prog__start{animation:none;text-align:left;margin:5px 7px 3px;font-size:11.5px;color:#1d4ed8}
.pstep--first{box-shadow:none;border:1px solid #bfdbfe;background:#eff6ff}

.protools{margin-top:7px;border-radius:12px;background:#fbfcfe}
.protools__head{padding:10px 13px}
.protool{padding:11px 13px;color:var(--mut2)}
.protool:hover{background:#fff;color:var(--fg)}
.protool__go{font-size:0}
.protool__go::after{content:'열기';font-size:10px;font-weight:800;color:#64748b}

@media(max-width:620px){
  .dashboard-shell .card{min-height:122px;padding:15px 16px}
  .dashboard-shell .card__arrow{min-height:26px}
}

/* ── Notion형 좌측 업무 사이드바 ─────────────────────────── */
.dashboard-shell{
  max-width:1280px;grid-template-columns:272px minmax(0,1fr);
  column-gap:34px;padding:20px 28px 56px
}
.dashboard-shell .ph-left{grid-column:2;grid-row:1;padding:5px 0 18px}
.dashboard-shell .rightcol{
  grid-column:1;grid-row:1 / span 2;width:272px;min-height:calc(100vh - 96px);
  padding:15px 12px;border:1px solid #e5e7eb;border-radius:16px;
  background:#f7f8fa
}
.dashboard-shell .wrap{
  grid-column:2;grid-row:2;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px
}
.dashboard-shell .prog{
  position:sticky;top:76px;width:100%;padding:5px;background:transparent;
  border:0;border-radius:0;box-shadow:none
}
.dashboard-shell .prog__t{
  margin:0 6px 9px;padding:7px 8px 12px;font-size:12px;letter-spacing:.02em;
  color:#64748b;border-bottom:1px solid #e5e7eb
}
.dashboard-shell .pstep{
  min-height:48px;padding:9px 9px;gap:10px;border:0!important;border-radius:8px;
  font-size:13px;font-weight:650
}
.dashboard-shell .pstep+.pstep{margin-top:2px}
.dashboard-shell .pstep:hover{background:#eceef2}
.dashboard-shell .pstep .no{width:26px;height:26px;font-size:11px;background:#e5e7eb}
.dashboard-shell .pstep--done .no{background:#dcfce7;color:#15803d}
.dashboard-shell .pstep--now,.dashboard-shell .pstep--first{
  background:#e8eefc;border:0!important;box-shadow:none;color:#1d4ed8
}
.dashboard-shell .pstep--now .no{background:#2563eb;color:#fff;box-shadow:none}
.dashboard-shell .ptag{font-size:10px;padding:3px 7px}
.dashboard-shell .psub{padding:3px 8px 7px 44px;gap:6px;font-size:10.5px;flex-direction:column}
.dashboard-shell .prog__start{
  margin:7px 7px 4px;padding:0;color:#2563eb;font-size:10.5px;font-weight:800
}
.dashboard-shell .prog__hint{
  margin:12px 7px 3px;padding-top:12px;border-top:1px solid #e5e7eb;
  text-align:left;font-size:10.5px;line-height:1.5
}
.dashboard-head{min-height:60px}
.ops-head{margin-top:2px;padding:4px 1px}
/* 업무 실행 메뉴를 좌측으로 옮겼으므로 본문의 중복 카드 목록은 감춥니다. */
.dashboard-shell .wrap>.ops-head,
.dashboard-shell .wrap>.sec,
.dashboard-shell .wrap>.grid{display:none}
.dashboard-shell .wrap>.mission{margin-bottom:4px}
.dashboard-shell .wrap>.protools{margin-top:10px}

@media(max-width:940px){
  .dashboard-shell{grid-template-columns:230px minmax(0,1fr);column-gap:22px;padding:18px 20px 44px}
  .dashboard-shell .rightcol{grid-column:1;grid-row:1 / span 2;width:230px}
  .dashboard-shell .ph-left{grid-column:2;grid-row:1}
  .dashboard-shell .wrap{grid-column:2;grid-row:2;grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:720px){
  .dashboard-shell{display:flex;flex-direction:column;padding:16px 18px 42px}
  .dashboard-shell .ph-left{order:1;width:100%}
  .dashboard-shell .rightcol{order:2;width:100%;min-height:0;padding:10px;margin:0 0 16px}
  .dashboard-shell .prog{position:static}
  .dashboard-shell .wrap{order:3;width:100%;grid-template-columns:1fr}
  .dashboard-shell .pstep{min-height:46px}
}
</style>

<div class="dashboard-shell">
<header class="page-head">
  <div class="page-head__inner">
    <div class="ph-left">
      <div class="dashboard-head">
        <div class="dashboard-head__copy">
          <div class="badge"><span></span> 건물 소방안전관리자</div>
          <h1>건물 소방안전관리</h1>
          <p>필요한 업무와 진행 상태를 한 화면에서 확인하세요.</p>
        </div>
        <a class="plan-link<?= $isPro ? ' plan-link--on' : ($proStatus === 'pending' ? ' plan-link--wait' : '') ?>"
           href="<?=h($url('/subscribe_page.php'))?>">
          <?= $isPro ? 'PRO 관리' : ($proStatus === 'pending' ? 'PRO 신청 확인' : 'PRO 구독') ?>
        </a>
      </div>
    </div>

    <div class="rightcol">
    <aside class="prog">
      <div class="prog__t">소방안전관리 업무</div>
      <?php
        /* 좌측 업무 메뉴: 각 기록을 합치지 않고 실제 작성 화면 단위로 보여줍니다. */
        $s1 = $biDone;
        $s2 = $hasRoster;
        $s3 = ($doneWorkLog === true);
        $s4 = ($doneJawi === true);
        $s5 = ($doneTrain === true);
        $nowStep = !$s1 ? 1 : (!$s2 ? 2 : (!$s3 ? 3 : (!$s4 ? 4 : (!$s5 ? 5 : 7))));
      ?>
      <?php
        /* 현재 해야 할 단계 바로 위에 클릭 유도 문구를 띄웁니다.
           기존 '여기부터 시작하세요'와 같은 방식이되, 진행에 따라 위치가 내려갑니다. */
        function stepNudge(int $step, int $nowStep, string $text): void {
          if ($step !== $nowStep) return;
          echo '<div class="prog__start">현재 단계 · ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
        }
      ?>

      <?php stepNudge(1, $nowStep, '여기부터 시작하세요'); ?>
      <!-- ① 기본정보 -->
      <a class="pstep <?= $s1 ? 'pstep--done' : 'pstep--now' ?><?= $nowStep===1 ? ' pstep--first' : '' ?>" href="<?= $biDone ? h($url('/building_setup.php')) : h($url('/building_setup_chat.php')) ?>">
        <span class="no"><?= $s1 ? '✓' : '1' ?></span><span class="pstep__label">기본정보</span>
        <?php if ($s1): ?><span class="ptag ptag--done">완료</span>
        <?php elseif ($reviewPending > 0): ?><span class="ptag ptag--review">확인요청 <?=$reviewPending?>건</span>
        <?php elseif ($hasBi): ?><span class="ptag ptag--part"><?=$biProg['percent']?>%</span>
        <?php else: ?><span class="ptag ptag--no">미진행</span><?php endif; ?>
      </a>

      <?php stepNudge(2, $nowStep, '이제 자위소방대를 편성하세요'); ?>
      <!-- ② 자위소방대 편성 — 매월 기록·훈련 기록이 이 명단을 불러 쓰므로 먼저입니다 -->
      <a class="pstep <?= $s2 ? 'pstep--done' : ($nowStep===2 ? 'pstep--now' : '') ?><?= $nowStep===2 ? ' pstep--first' : '' ?><?= $hasBi ? '' : ' pstep--lock' ?>" href="<?=h($url('/fire_plan_jawi.php'))?>">
        <span class="no"><?= $s2 ? '✓' : '2' ?></span><span class="pstep__label">자위소방대 편성</span>
        <?php if ($s2): ?><span class="ptag ptag--done"><?=$rosterCount?>건</span>
        <?php elseif (!$hasBi): ?><span class="ptag ptag--wait">대기</span>
        <?php else: ?><span class="ptag ptag--no">미진행</span><?php endif; ?>
      </a>
      <?php if ($hasBi && !$s2): ?>
        <div class="psub psub--why"></div>
      <?php endif; ?>

      <?php stepNudge(3, $nowStep, '이번 달 기록표를 작성하세요'); ?>
      <!-- ③ 매월 기록 -->
      <a class="pstep <?= $s3 ? 'pstep--done' : ($nowStep===3 ? 'pstep--now' : '') ?><?= $nowStep===3 ? ' pstep--first' : '' ?><?= $hasBi ? '' : ' pstep--lock' ?>" href="<?=h($url('/work_log.php'))?>">
        <span class="no"><?= $s3 ? '✓' : '3' ?></span>매월 기록 (<?=h($mon)?>)
        <?php if ($s3): ?><span class="ptag ptag--done">완료</span>
        <?php elseif (!$hasBi): ?><span class="ptag ptag--wait">대기</span>
        <?php else: ?><span class="ptag ptag--no">미진행</span><?php endif; ?>
      </a>

      <?php stepNudge(4, $nowStep, '자위소방대 교육을 기록하세요'); ?>
      <!-- ④ 자위소방대 교육·훈련 -->
      <a class="pstep <?= $s4 ? 'pstep--done' : ($nowStep===4 ? 'pstep--now' : '') ?><?= $nowStep===4 ? ' pstep--first' : '' ?><?= $hasBi ? '' : ' pstep--lock' ?>" href="<?=h($url('/jawi.php'))?>">
        <span class="no"><?= $s4 ? '✓' : '4' ?></span><span class="pstep__label">자위소방대 교육</span>
        <?php if ($s4): ?><span class="ptag ptag--done">완료</span>
        <?php elseif (!$hasBi): ?><span class="ptag ptag--wait">대기</span>
        <?php else: ?><span class="ptag ptag--no">미진행</span><?php endif; ?>
      </a>

      <?php stepNudge(5, $nowStep, '소방훈련·교육을 기록하세요'); ?>
      <!-- ⑤ 소방훈련·교육 -->
      <a class="pstep <?= $s5 ? 'pstep--done' : ($nowStep===5 ? 'pstep--now' : '') ?><?= $nowStep===5 ? ' pstep--first' : '' ?><?= $hasBi ? '' : ' pstep--lock' ?>" href="<?=h($url('/train.php'))?>">
        <span class="no"><?= $s5 ? '✓' : '5' ?></span><span class="pstep__label">소방훈련·교육</span>
        <?php if ($s5): ?><span class="ptag ptag--done">완료</span>
        <?php elseif (!$hasBi): ?><span class="ptag ptag--wait">대기</span>
        <?php else: ?><span class="ptag ptag--no">미진행</span><?php endif; ?>
      </a>

      <!-- ⑥ 피난계획 -->
      <a class="pstep <?= $hasBi ? '' : 'pstep--lock' ?>" href="<?=h($url('/evacuation_plan_chat.php'))?>">
        <span class="no">6</span><span class="pstep__label">피난계획</span>
        <span class="ptag ptag--always">상시</span>
      </a>

      <?php stepNudge(7, $nowStep, '소방계획서를 확인하세요'); ?>
      <!-- ⑦ 소방계획서 -->
      <a class="pstep <?= $nowStep===7 ? 'pstep--now pstep--first' : '' ?><?= $hasBi ? '' : ' pstep--lock' ?>" href="<?=h($url('/fire_plan.php'))?>">
        <span class="no">7</span><span class="pstep__label">소방계획서</span>
        <span class="ptag ptag--always">상시</span>
      </a>

      <div class="prog__hint"><?= $hasBi ? '업무를 선택하면 작성 화면으로 바로 이동합니다' : '기본정보를 입력하면 나머지 업무가 열립니다' ?></div>
    </aside>
    </div>
  </div>
</header>

                  <main class="wrap">

                    <?php if ($adminView): ?>
                    <div class="section" style="background:#fff;border:1px solid var(--bd);border-radius:14px;padding:18px 20px;margin-bottom:18px">
                      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                        <b>관리자 전용 · <?=h($targetNick)?> 회원 화면</b>
                        <?php if ($adminReviewRows): ?>
                          <span class="setup-badge setup-badge--review">확인요청 <?=count($adminReviewRows)?>건</span>
                        <?php else: ?>
                          <span class="setup-badge setup-badge--ok">대기 요청 없음</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($adminReviewRows): ?>
                        <div style="display:grid;gap:8px;margin:10px 0 12px">
                          <?php foreach (array_slice($adminReviewRows, 0, 5) as $req): ?>
                            <div style="font-size:13px;color:var(--mut2);background:#fff7ed;border:1px solid #fed7aa;border-radius:9px;padding:9px 11px">
                              <b style="color:#92400e"><?=h($req['at'] ?? '')?></b>
                              · <?=h($req['text'] ?? '')?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p style="font-size:13px;color:var(--mut2);margin:4px 0 12px">이 회원의 미처리 확인요청은 없습니다.</p>
                      <?php endif; ?>
                      <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="btn" href="/admin_member_review.php?uid=<?=h(rawurlencode($viewUid))?>">확인요청 처리</a>
                        <a class="btn" href="<?=h($url('/building_setup.php'))?>">기본정보 수정</a>
                        <a class="btn" href="<?=h($url('/building_setup_chat.php'))?>">문답형 수정</a>
                      </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$hasUser): ?>
                    <div class="mission mission--error">
                      <div class="mission__copy">
                        <span class="mission__label">SYSTEM CHECK</span>
                        <span class="mission__text">로그인 정보를 확인할 수 없습니다. 다시 로그인해 주세요.</span>
                      </div>
                    </div>
                    <?php elseif (!$hasBi): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION</span>
                        <span class="mission__text"><b>건물 기본정보</b>를 입력하세요. 한 번 입력하면 모든 서식에 반영됩니다.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/building_setup_chat.php'))?>">기본정보 입력</a>
                    </div>
                    <?php elseif (!$biDone): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION · <?=$biProg['percent']?>%</span>
                        <span class="mission__text"><b><?=h(implode(', ', array_slice($biProg['missing'], 0, 3)))?><?= count($biProg['missing']) > 3 ? ' 외' : '' ?></b> 항목을 이어서 입력하세요.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/building_setup_chat.php'))?>">이어서 입력</a>
                    </div>
                    <?php elseif (!$hasRoster): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION</span>
                        <span class="mission__text"><b>자위소방대 편성표</b>를 먼저 만드세요. 이후 교육·훈련 기록에서 명단을 불러옵니다.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/fire_plan_jawi.php'))?>">편성표 만들기</a>
                    </div>
                    <?php elseif ($doneWorkLog !== true): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION · <?=h($mon)?></span>
                        <span class="mission__text"><b>업무수행 기록표</b>를 작성하세요. 매월 1회 이상 작성해야 합니다.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/work_log.php'))?>">기록표 작성</a>
                    </div>
                    <?php elseif ($doneJawi !== true): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION · 연간 업무</span>
                        <span class="mission__text"><b>자위소방대 교육·훈련 기록부</b>를 작성하세요.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/jawi.php'))?>">교육·훈련 기록</a>
                    </div>
                    <?php elseif ($doneTrain !== true): ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">NEXT ACTION · 연간 업무</span>
                        <span class="mission__text"><b>소방훈련·교육 기록부</b>를 작성하면 올해 교육·훈련 업무가 완료됩니다.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/train.php'))?>">훈련 기록 작성</a>
                    </div>
                    <?php else: ?>
                    <div class="mission">
                      <div class="mission__copy">
                        <span class="mission__label">ALL ROUTINE TASKS COMPLETE</span>
                        <span class="mission__text">정기 업무가 완료되었습니다. <b>소방계획서</b>를 상시 관리하세요.</span>
                      </div>
                      <a class="mission__btn" href="<?=h($url('/fire_plan.php'))?>">소방계획서 열기</a>
                    </div>
                    <?php endif; ?>

                    <div class="ops-head">
                      <b>업무</b>
                      <span>상태를 확인하고 바로 실행하세요</span>
                    </div>

                    <!-- ── STEP 1 · 처음 한 번 ── -->
                    <div class="sec">
                      <span class="sec__chip chip-first">STEP 1 · 처음 한 번</span>
                      <span class="sec__t">건물 기본정보</span>
                      <span class="sec__d">모든 서식의 출발점 — 필수</span>
                    </div>
                    <div class="grid">
                      <a class="card card--link card--setup" href="<?= $biDone ? h($url('/building_setup.php')) : h($url('/building_setup_chat.php')) ?>">
                        <div class="card__top">
                          <span class="badge badge--setup">기본정보</span>
                          <?php if ($reviewPending > 0): ?>
                            <span class="setup-badge setup-badge--review">확인요청 <?=$reviewPending?>건</span>
                          <?php elseif ($biDone): ?>
                            <span class="setup-badge setup-badge--ok">입력 완료</span>
                          <?php elseif ($hasBi): ?>
                            <span class="setup-badge setup-badge--need"><?=$biProg['percent']?>% 입력됨</span>
                          <?php else: ?>
                            <span class="setup-badge setup-badge--need">먼저 입력하세요</span>
                          <?php endif; ?>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3><?= $hasBi ? h($biName) : '건물 기본정보 입력' ?></h3>
                        <p class="card__sub"><?php
                          if ($reviewPending > 0) echo 'YeoHub에 검토요청한 기본정보 항목이 ' . (int)$reviewPending . '건 있습니다. 관리자 확인 후 완료 표시로 바뀝니다.';
                          elseif ($biDone) echo '대상명·주소·등급·소방안전관리자 정보 · 모든 서식에 자동 반영';
                          elseif ($hasBi) echo '남은 항목: ' . h(implode(', ', $biProg['missing']));
                          else echo '한 번만 입력하면 아래 모든 서식에 자동으로 채워집니다';
                        ?></p>
                      </a>
                    </div>

                    <!-- ── 매월 반복 · 매년 업무보다 먼저 확인 ── -->
                    <div class="sec <?= $hasBi ? '' : 'sec--dim' ?>">
                      <span class="sec__chip chip-month">매월</span>
                      <span class="sec__t">정기 기록</span>
                      <span class="sec__d">월 1회 이상 · 매달 반복되는 핵심 업무</span>
                      <?php if (!$hasBi): ?><span class="sec__lock">🔒 기본정보 입력 후 진행하세요</span><?php endif; ?>
                    </div>
                    <div class="grid <?= $hasBi ? '' : 'grid--dim' ?>">
                      <a class="card card--link" href="<?=h($url('/work_log.php'))?>">
                        <div class="card__top">
                          <span class="badge">매월 · 별지 제12호</span>
                          <?php if ($doneWorkLog === true): ?>
                            <span class="due due--ok">✓ <?=h($mon)?> 완료</span>
                          <?php else: ?>
                            <span class="due due--need"><?=h($mon)?> 작성 대상</span>
                          <?php endif; ?>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3>업무수행 기록표</h3>
                        <p class="card__sub">소방안전관리자 업무 수행 기록표 · 월 1회 이상 작성</p>
                      </a>
                    </div>

                    <!-- ── 매년 반복 ── -->
                

                    <div class="sec <?= $hasBi ? '' : 'sec--dim' ?>">
                      <span class="sec__chip chip-year">매년</span>
                      <span class="sec__t">자위소방대 · 훈련·교육</span>
                      <span class="sec__d">먼저 대원을 편성하고, 그 명단으로 훈련·교육을 기록합니다</span>
                      <?php if (!$hasBi): ?><span class="sec__lock">🔒 기본정보 입력 후 진행하세요</span><?php endif; ?>
                    </div>

                    <div class="grid <?= $hasBi ? '' : 'grid--dim' ?>">

                      <!-- ① 편성표 — 나머지의 출발점 -->
                      <a class="card card--link card--roster<?= $hasRoster ? '' : ' card--next' ?>" href="<?=h($url('/fire_plan_jawi.php'))?>">
                        <div class="card__top">
                          <span class="badge badge--roster">매년 · 1단계</span>
                          <?php if ($hasRoster): ?>
                            <span class="due due--ok">✓ 편성 <?=$rosterCount?>건</span>
                          <?php else: ?>
                            <span class="due due--need">먼저 하세요</span>
                          <?php endif; ?>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3><?= $hasRoster && $rosterName !== '' ? h($rosterName) . ' 자위소방대' : '자위소방대 편성표' ?></h3>
                        <p class="card__sub"><?php
                          if ($hasRoster) echo '대장·부대장·활동조 편성이 저장되어 있습니다 · 인원이 바뀌면 여기서 수정하세요';
                          else echo '이름만 붙여넣으면 대장·부대장·활동조로 자동 배치됩니다 · 5분이면 끝납니다';
                        ?></p>
                      </a>

                      <!-- ② 자위소방대 교육·훈련 (편성표 기반) -->
                      <a class="card card--link<?= $hasRoster ? '' : ' card--wait' ?>" href="<?=h($url('/jawi.php'))?>">
                        <div class="card__top">
                          <span class="badge">매년 · 2단계</span>
                          <?php if ($doneJawi === true): ?><span class="due due--ok">✓ 올해 완료</span>
                          <?php elseif (!$hasRoster): ?><span class="due due--wait">편성표 먼저</span><?php endif; ?>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3>자위소방대 교육·훈련 기록부</h3>
                        <p class="card__sub"><?php
                          if (!$hasRoster) echo '편성표를 만들면 대원 명단을 그대로 불러와 참석자로 채울 수 있습니다';
                          else echo '자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부 · 연 1회 이상';
                        ?></p>

                        <?php if ($jawiStatus && $jawiStatus['percent'] < 100): $jm = $jawiStatus['missing']; ?>
                          <div class="cardprog">
                            <div class="cardprog__bar"><i style="width:<?=$jawiStatus['percent']?>%"></i></div>
                            <div class="cardprog__t">
                              <b><?=$jawiStatus['percent']?>%</b>
                              <?php if (count($jm) === 1): ?>
                                <?=h($jm[0])?><?= $jm[0] === '참석자 명단' ? '을 넣으면' : '을(를) 채우면' ?> 완료됩니다
                              <?php else: ?>
                                남은 항목 <?=count($jm)?>개 · <?=h(implode(', ', array_slice($jm, 0, 2)))?><?= count($jm) > 2 ? ' 외' : '' ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        <?php elseif ($jawiStatus && $jawiStatus['percent'] >= 100): ?>
                          <div class="cardprog">
                            <div class="cardprog__bar"><i style="width:100%"></i></div>
                            <div class="cardprog__t cardprog__t--ok"><b>100%</b> 참석 명단까지 모두 채웠습니다</div>
                          </div>
                        <?php endif; ?>
                      </a>

                      <!-- ③ 소방훈련·교육 (별도 서식) -->
                      <a class="card card--link" href="<?=h($url('/train.php'))?>">
                        <div class="card__top">
                          <span class="badge">매년 · 별지 제28·29호</span>
                          <?php if ($doneTrain === true): ?><span class="due due--ok">✓ 올해 완료</span><?php endif; ?>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3>소방훈련·교육 기록부</h3>
                        <p class="card__sub">소방훈련·교육 실시 결과 기록부 · 실시 후 2년 보관</p>

                        <?php if ($trainStatus && $trainStatus['percent'] < 100): $tm = $trainStatus['missing']; ?>
                          <div class="cardprog">
                            <div class="cardprog__bar"><i style="width:<?=$trainStatus['percent']?>%"></i></div>
                            <div class="cardprog__t">
                              <b><?=$trainStatus['percent']?>%</b>
                              <?php if (count($tm) === 1): ?>
                                <?=h($tm[0])?><?= $tm[0] === '훈련·교육 사진' ? '을 첨부하면' : '을(를) 채우면' ?> 완료됩니다
                              <?php else: ?>
                                남은 항목 <?=count($tm)?>개 · <?=h(implode(', ', array_slice($tm, 0, 2)))?><?= count($tm) > 2 ? ' 외' : '' ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        <?php elseif ($trainStatus && $trainStatus['percent'] >= 100): ?>
                          <div class="cardprog">
                            <div class="cardprog__bar"><i style="width:100%"></i></div>
                            <div class="cardprog__t cardprog__t--ok"><b>100%</b> 사진까지 모두 채웠습니다</div>
                          </div>
                        <?php endif; ?>
                      </a>
                    </div>

                    <!-- ── 상시 관리 ── -->
                    <div class="sec <?= $hasBi ? '' : 'sec--dim' ?>">
                      <span class="sec__chip chip-always">상시</span>
                      <span class="sec__t">계획 관리</span>
                      <span class="sec__d">차기년도 소방계획 — 한 번에 끝내는 서식이 아니라 여유 있게 다듬어 가세요</span>
                      <?php if (!$hasBi): ?><span class="sec__lock">🔒 기본정보 입력 후 진행하세요</span><?php endif; ?>
                    </div>
                    <div class="grid <?= $hasBi ? '' : 'grid--dim' ?>">
                      <a class="card card--link" href="<?=h($url('/fire_plan.php'))?>">
                        <div class="card__top">
                          <span class="badge badge--plan">상시 · 시행령 제27조</span>
                          <span class="card__arrow">→</span>
                        </div>
                        <h3>소방계획서</h3>
                        <p class="card__sub">법정 15개 항목에 따라 작성하고 관리합니다 · 천천히 수정해 나가는 문서</p>
                      </a>

                    </div>

                    <section class="protools" aria-label="PRO 도구">
                      <div class="protools__head">
                        <b>PRO TOOLS</b>
                        <span><?= $isPro ? '모든 기능을 이용할 수 있습니다' : '구독하면 세 기능이 열립니다' ?></span>
                      </div>
                      <div class="protools__list">
                        <a class="protool" href="<?=h($proLink('/ar.php'))?>">
                          <span class="protool__ic">🔥</span><b>피난 시뮬레이션</b><span class="protool__go">→</span>
                        </a>
                        <a class="protool" href="<?=h($proLink('/notifications.php'))?>">
                          <span class="protool__ic">🔔</span><b>자동알림</b><span class="protool__go">→</span>
                        </a>
                        <a class="protool" href="<?=h($proLink('/print_all.php'))?>">
                          <span class="protool__ic">🖨</span><b>서류 전체 인쇄</b><span class="protool__go">→</span>
                        </a>
                      </div>
                    </section>

                  </main>
</div>

<!-- 피난 시뮬레이션 배정 신청 — 기본정보를 불러와 확인·수정 -->
<div class="rqov" id="rqov" onclick="if(event.target===this)closeEvacReq()">
  <div class="rqbox">
    <h4>피난 시뮬레이션 배정 신청</h4>
    <p class="rsub">기본정보에 입력하신 내용을 불러왔습니다. 맞는지 확인하고, 다르면 고쳐서 보내주세요.</p>

    <div class="rqerr" id="rqErr"></div>

    <div class="rqf">
      <label for="rqBuilding">건물명</label>
      <input type="text" id="rqBuilding" maxlength="120" value="<?=h($reqPrefill['building_name'])?>" placeholder="예) 두리빌딩">
    </div>
    <div class="rqf">
      <label for="rqAddress">건물 주소<span class="req">*</span></label>
      <input type="text" id="rqAddress" maxlength="200" value="<?=h($reqPrefill['address'])?>" placeholder="도로명 주소를 적어주세요">
      <div class="rqf__hint">도면 확인을 위해 정확한 주소가 필요합니다.</div>
    </div>
    <div class="rqf">
      <label for="rqName">소방안전관리자 이름<span class="req">*</span></label>
      <input type="text" id="rqName" maxlength="60" value="<?=h($reqPrefill['manager_name'])?>" placeholder="예) 홍길동">
    </div>
    <div class="rqf">
      <label for="rqPhone">연락처<span class="req">*</span></label>
      <input type="tel" id="rqPhone" maxlength="30" value="<?=h($reqPrefill['manager_phone'])?>" placeholder="예) 010-1234-5678">
      <div class="rqf__hint">배정 관련 안내를 이 번호로 드립니다.</div>
    </div>

    <div class="rqnote">여기서 고친 내용은 이번 신청에만 쓰입니다. 기본정보를 함께 바꾸려면
      <a href="<?=h($url('/building_setup.php'))?>">기본정보 수정</a>에서 변경해 주세요.</div>

    <div class="rqbtns">
      <button type="button" onclick="closeEvacReq()">취소</button>
      <button type="button" class="rqgo" id="rqGo" onclick="sendEvacReq()">신청 보내기</button>
    </div>
  </div>
</div>

<div class="qrov" id="qrov" onclick="if(event.target===this)this.classList.remove('on')">
  <div class="qrbox">
    <h4 id="qrTitle">피난 시뮬레이션</h4>
    <p class="qsub">QR을 스캔하면 각자 폰에서 대피 시뮬레이션을 볼 수 있습니다</p>
    <div class="qimg" id="qrImg"></div>
    <div class="qurl" id="qrUrl"></div>
    <button class="qclose" onclick="document.getElementById('qrov').classList.remove('on')">닫기</button>
  </div>
</div>
<script>
/* 배정 신청 — 기본정보를 채워둔 팝업을 열고, 확인·수정한 값을 보냅니다 */
function openEvacReq(){
  showReqErr('');
  document.getElementById('rqov').classList.add('on');
  /* 비어 있는 첫 칸에 커서를 둡니다 */
  var ids = ['rqAddress','rqName','rqPhone','rqBuilding'];
  for (var i=0;i<ids.length;i++){
    var el = document.getElementById(ids[i]);
    if (el && el.value.trim() === ''){ el.focus(); return; }
  }
  document.getElementById('rqAddress').focus();
}
function closeEvacReq(){
  document.getElementById('rqov').classList.remove('on');
}
function showReqErr(msg){
  var box = document.getElementById('rqErr');
  box.textContent = msg || '';
  box.classList.toggle('on', !!msg);
}
function sendEvacReq(){
  var building = document.getElementById('rqBuilding').value.trim();
  var address  = document.getElementById('rqAddress').value.trim();
  var name     = document.getElementById('rqName').value.trim();
  var phone    = document.getElementById('rqPhone').value.trim();

  if (!address){ showReqErr('건물 주소를 입력해 주세요.'); document.getElementById('rqAddress').focus(); return; }
  if (!name){ showReqErr('소방안전관리자 이름을 입력해 주세요.'); document.getElementById('rqName').focus(); return; }
  var digits = phone.replace(/[^0-9]/g,'');
  if (digits.length < 8 || digits.length > 11){
    showReqErr('연락 가능한 전화번호를 입력해 주세요. (숫자 8~11자리)');
    document.getElementById('rqPhone').focus(); return;
  }
  showReqErr('');

  var go = document.getElementById('rqGo');
  go.disabled = true; go.textContent = '보내는 중…';

  var fd = new FormData();
  fd.append('act','request');
  fd.append('csrf', <?=json_encode($CSRF)?>);
  fd.append('building_name', building);
  fd.append('address', address);
  fd.append('manager_name', name);
  fd.append('manager_phone', phone);

  fetch('/evac_assign_api.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(j){
      if (j && j.ok){
        closeEvacReq();
        var btn = document.getElementById('evacReqBtn');
        document.getElementById('evacReq').classList.add('evac-req--done');
        document.getElementById('evacReqT').textContent = '✓ 요청 완료 — 관리자 확인 중입니다';
        document.getElementById('evacReqD').textContent = '배정이 완료되면 이 자리에 시뮬레이션이 표시됩니다.';
        if (btn){ btn.disabled = true; btn.textContent = '요청 완료'; }
      } else {
        showReqErr((j && j.error) ? j.error : '요청에 실패했습니다.');
      }
      go.disabled = false; go.textContent = '신청 보내기';
    })
    .catch(function(){
      showReqErr('요청을 보내지 못했습니다. 잠시 후 다시 시도해 주세요.');
      go.disabled = false; go.textContent = '신청 보내기';
    });
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape'){
    closeEvacReq();
    document.getElementById('qrov').classList.remove('on');
  }
});
function showQr(id, name){
  var url = location.origin + '/evac_view.php?id=' + id;
  document.getElementById('qrTitle').textContent = name;
  document.getElementById('qrUrl').textContent = url;
  var box = document.getElementById('qrImg');
  box.innerHTML = '';
  var render = function(){
    new QRCode(box, {text:url, width:200, height:200, correctLevel:QRCode.CorrectLevel.M});
  };
  if (window.QRCode) render();
  else {
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    s.onload = render;
    s.onerror = function(){ box.textContent = 'QR 생성 실패 — 주소를 직접 공유하세요'; };
    document.head.appendChild(s);
  }
  document.getElementById('qrov').classList.add('on');
}
</script>
<!-- 첫 방문 안내 팝업 -->
<div class="gdov" id="gdov">
  <div class="gdbox">
    <div class="gdbox__ico">👋</div>
    <h4>무엇부터 하면 되는지 알려드릴게요</h4>
    <p>
      화면 <span class="gdbox__hi">우측 상단의 진행 현황</span>을 보시면<br>
      지금 해야 할 일이 순서대로 표시됩니다.<br>
      항목을 눌러 하나씩 진행해 주세요.
    </p>
    <button class="gdbox__btn" type="button" id="gdClose">알겠습니다</button>
    <label class="gdbox__again">
      <input type="checkbox" id="gdNever"> 다음부터 보지 않기
    </label>
  </div>
</div>

<script>
(function(){
  /* 첫 방문 안내 — "다음부터 보지 않기"는 쿠키에 1년간 기록합니다. */
  var KEY = 'bm_guide_hide';

  function getCookie(name){
    var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? m.pop() : '';
  }
  function setCookie(name, val, days){
    var d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + val + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  var ov    = document.getElementById('gdov');
  var btn   = document.getElementById('gdClose');
  var never = document.getElementById('gdNever');
  var prog  = document.querySelector('.prog');
  if (!ov || !btn) return;

  if (getCookie(KEY) === '1') return;   // 이미 "보지 않기"를 선택한 경우

  // 화면이 그려진 뒤 잠깐 있다가 띄웁니다.
  setTimeout(function(){ ov.classList.add('on'); }, 400);

  function close(){
    if (never && never.checked) setCookie(KEY, '1', 365);
    ov.classList.remove('on');
    // 닫은 직후, 안내가 가리킨 진행 현황을 잠깐 강조해 줍니다.
    if (prog){
      prog.classList.add('gd-hi');
      prog.scrollIntoView({ behavior:'smooth', block:'nearest' });
      setTimeout(function(){ prog.classList.remove('gd-hi'); }, 2200);
    }
  }

  btn.addEventListener('click', close);
  // 바깥을 눌러도 닫힙니다(체크박스 선택은 그대로 반영).
  ov.addEventListener('click', function(e){ if (e.target === ov) close(); });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && ov.classList.contains('on')) close();
  });
})();
</script>

<?php require __DIR__ . '/memo_widget.php'; ?>
<?php require __DIR__ . '/_footer.php'; ?>
