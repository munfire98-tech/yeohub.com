<?php
/* =============================================================
   notifications.php — 알림 (틀)
   ─────────────────────────────────────────────────────────────
   상단 네비 종 아이콘에서 들어옵니다. 지금은 틀만 있고, 실제 알림을
   쌓는 로직(점검일 임박, 결제 실패, 관리자 확인완료 등)은 나중에
   각 기능에서 notif_push() 를 호출하도록 붙이면 됩니다.

   저장 위치: data/notifications/{회원키}.json

   화면은 _header.php / _footer.php 를 그대로 씁니다 (blog.php·service.php 와 동일 구조).
   ============================================================= */
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); }
session_start();

if (is_file(__DIR__ . '/user_key.php')) require_once __DIR__ . '/user_key.php';
function is_admin(): bool {
  return (!empty($_SESSION['is_admin'])) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function notif_uid(): string { return function_exists('app_user_key') ? app_user_key() : ''; }
function notif_file(): string {
  $uid = notif_uid();
  if ($uid === '') return '';
  $dir = __DIR__ . '/data/notifications';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/' . $uid . '.json';
}
function notif_read(): array {
  $f = notif_file();
  if ($f === '' || !is_file($f)) return [];
  $r = json_decode((string)@file_get_contents($f), true);
  return is_array($r) ? $r : [];
}
function notif_write(array $list): bool {
  $f = notif_file();
  if ($f === '') return false;
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
function sms_center_file(): string {
  $uid = notif_uid();
  if ($uid === '') return '';
  $dir = __DIR__ . '/data/notifications';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/' . $uid . '_sms_center.json';
}
function sms_center_read(): array {
  $f = sms_center_file();
  $data = ($f !== '' && is_file($f)) ? json_decode((string)@file_get_contents($f), true) : [];
  if (!is_array($data)) $data = [];
  $data['settings'] = array_merge(['mode'=>'approval','enabled'=>false,'send_day'=>1,'reminder_day'=>15,'excluded'=>[]], (array)($data['settings'] ?? []));
  $data['campaigns'] = is_array($data['campaigns'] ?? null) ? $data['campaigns'] : [];
  return $data;
}
function sms_center_write(array $data): bool {
  $f = sms_center_file(); if ($f === '') return false;
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
/** 다른 페이지에서 알림을 쌓을 때 쓰는 함수.
 *  예) notif_push($uid, 'D-DAY 임박', '○○빌딩 점검일이 3일 남았습니다.', '/work_log.php'); */
function notif_push(string $uid, string $title, string $body = '', string $link = ''): bool {
  if ($uid === '') return false;
  $dir = __DIR__ . '/data/notifications';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $f = $dir . '/' . $uid . '.json';
  $list = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  array_unshift($list, [
    'id' => bin2hex(random_bytes(8)), 'title' => $title, 'body' => $body, 'link' => $link,
    'read' => false, 'at' => date('Y-m-d H:i:s'),
  ]);
  $list = array_slice($list, 0, 100);   // 최근 100개만 보관
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 액션 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if (hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    if ($act === 'read_all') {
      $list = notif_read();
      foreach ($list as &$n) { $n['read'] = true; }
      unset($n);
      notif_write($list);
    } elseif ($act === 'read_one') {
      $id = (string)($_POST['id'] ?? '');
      $list = notif_read();
      foreach ($list as &$n) { if (($n['id'] ?? '') === $id) $n['read'] = true; }
      unset($n);
      notif_write($list);
    } elseif ($act === 'clear') {
      notif_write([]);
    } elseif ($act === 'kakao_setup') {
      $center = sms_center_read();
      $center['settings']['kakao'] = [
        'channel_name'=>mb_substr(trim((string)($_POST['channel_name'] ?? '')),0,100),
        'channel_id'=>mb_substr(trim((string)($_POST['channel_id'] ?? '')),0,150),
        'status'=>'draft'
      ];
      $_SESSION['kakao_setup_notice'] = sms_center_write($center) ? '카카오톡 연결 정보를 저장했습니다. 실제 연결은 아직 완료되지 않았습니다.' : '저장하지 못했습니다. 다시 시도해 주세요.';
    } elseif ($act === 'sms_settings') {
      $center = sms_center_read();
      /* 문자 API가 연결되기 전에는 승인 후 발송만 허용합니다. */
      $center['settings']['mode'] = 'approval';
      $center['settings']['send_day'] = max(1, min(28, (int)($_POST['send_day'] ?? 1)));
      $center['settings']['reminder_day'] = max(1, min(28, (int)($_POST['reminder_day'] ?? 15)));
      sms_center_write($center);
    } elseif ($act === 'toggle_auto') {
      $center = sms_center_read();
      $center['settings']['enabled'] = empty($center['settings']['enabled']);
      sms_center_write($center);
    } elseif ($act === 'toggle_exclude') {
      $center = sms_center_read();
      $key = trim((string)($_POST['person_key'] ?? ''));
      $excluded = array_values(array_filter((array)($center['settings']['excluded'] ?? []), 'is_string'));
      if ($key !== '') {
        if (in_array($key, $excluded, true)) $excluded = array_values(array_diff($excluded, [$key]));
        else $excluded[] = $key;
      }
      $center['settings']['excluded'] = $excluded;
      sms_center_write($center);
    } elseif ($act === 'sms_mark_sent' || $act === 'sms_reopen') {
      $center = sms_center_read(); $key = preg_replace('/[^0-9-]/', '', (string)($_POST['month'] ?? ''));
      if ($key !== '' && isset($center['campaigns'][$key])) {
        $center['campaigns'][$key]['status'] = $act === 'sms_mark_sent' ? 'sent' : 'draft';
        $center['campaigns'][$key]['sent_at'] = $act === 'sms_mark_sent' ? date('Y-m-d H:i:s') : '';
        sms_center_write($center);
      }
    }
  }
  header('Location: /notifications.php'); exit;
}

$items = notif_read();
$unread = 0; foreach ($items as $n) if (empty($n['read'])) $unread++;

/* ── 문자 발송 센터 데이터 ────────────────────────────────
   외부 발송 채널을 연결하기 전에도 실제 저장 데이터를 확인하고
   카카오 알림톡/SMS 공용 수신자와 사람별 문구를 준비합니다. */
if (is_file(__DIR__ . '/building_info.php')) require_once __DIR__ . '/building_info.php';
$smsBi = function_exists('bi_load') ? (array)bi_load() : [];
$smsSite = trim((string)($smsBi['name'] ?? $smsBi['site_name'] ?? '건물'));
$smsManagers = [];
foreach ((array)($smsBi['mgrs'] ?? []) as $m) {
  $name = trim((string)($m['name'] ?? ''));
  $tel  = preg_replace('/[^0-9+]/', '', (string)($m['tel'] ?? ''));
  if ($name !== '' || $tel !== '') $smsManagers[] = ['name'=>$name, 'tel'=>$tel, 'type'=>trim((string)($m['type'] ?? '안전관리자'))];
}

function sms_person(array $src, string $role = ''): array {
  $isList = function_exists('array_is_list') ? array_is_list($src) : (array_keys($src) === range(0, count($src) - 1));
  if ($isList) {
    return ['name'=>trim((string)($src[0] ?? '')), 'tel'=>preg_replace('/[^0-9+]/','',(string)($src[1] ?? '')),
      'task'=>trim((string)($src[2] ?? '')), 'dept'=>trim((string)($src[3] ?? '')), 'role'=>$role];
  }
  return ['name'=>trim((string)($src['name'] ?? $src['person_name'] ?? $src['nm'] ?? '')),
    'tel'=>preg_replace('/[^0-9+]/','',(string)($src['tel'] ?? $src['phone'] ?? $src['mobile'] ?? $src['contact'] ?? '')),
    'task'=>trim((string)($src['task'] ?? $src['duty'] ?? $src['mission'] ?? $src['job'] ?? '')),
    'dept'=>trim((string)($src['dept'] ?? $src['department'] ?? $src['position'] ?? '')), 'role'=>$role];
}
function sms_href(string $tel, string $body): string {
  return 'sms:' . rawurlencode($tel) . '?body=' . rawurlencode($body);
}
function sms_person_key(array $p): string {
  return hash('sha256', mb_strtolower(trim($p['name'])).'|'.$p['tel']);
}

$smsRoster = [];
$rosterFile = notif_uid() !== '' ? (__DIR__ . '/data/fireplan/' . notif_uid() . '/_jawi.json') : '';
function sms_find_roster_plan($node, array &$best, int &$bestScore): void {
  if (is_string($node)) {
    $decoded = json_decode($node, true);
    if (is_array($decoded)) sms_find_roster_plan($decoded, $best, $bestScore);
    return;
  }
  if (!is_array($node)) return;
  if (isset($node['cmd']) || isset($node['groups']) || isset($node['teams'])) {
    $score = (int)(strtotime((string)($node['saved'] ?? $node['updated_at'] ?? $node['created'] ?? '')) ?: 0);
    // 새 기록은 array_unshift로 저장됩니다. 같은 날짜라면 앞의 기록을 유지합니다.
    if ($score > $bestScore) { $best = $node; $bestScore = $score; }
    return;
  }
  foreach ($node as $child) if (is_array($child) || is_string($child)) sms_find_roster_plan($child, $best, $bestScore);
}
function sms_collect_group_members($groups, array &$out): void {
  if (!is_array($groups)) return;
  foreach ($groups as $g) {
    if (!is_array($g)) continue;
    $role = trim((string)($g['name'] ?? $g['group_name'] ?? $g['role'] ?? '활동조'));
    $members = $g['members'] ?? $g['people'] ?? $g['persons'] ?? $g['rows'] ?? $g['list'] ?? null;
    if (is_array($members)) {
      foreach ($members as $m) if (is_array($m)) $out[] = sms_person($m, $role);
      continue;
    }
    /* 일부 구버전은 그룹 자체를 사람 배열로 저장합니다. */
    foreach ($g as $m) if (is_array($m) && (isset($m['name']) || isset($m['tel']))) $out[] = sms_person($m, $role);
  }
}
function sms_collect_nested_people($node, array &$out, string $role = '활동조'): void {
  if (!is_array($node)) return;
  $hasPerson = isset($node['name']) || isset($node['person_name']) || isset($node['nm']);
  $hasContact = isset($node['tel']) || isset($node['phone']) || isset($node['mobile']) || isset($node['contact']);
  if ($hasPerson && $hasContact) {
    $out[] = sms_person($node, trim((string)($node['role'] ?? $node['group_name'] ?? $role)));
    return;
  }
  $nextRole = trim((string)($node['group_name'] ?? $node['role'] ?? $node['name'] ?? $role));
  foreach ($node as $child) if (is_array($child)) sms_collect_nested_people($child, $out, $nextRole ?: $role);
}
if ($rosterFile !== '' && is_file($rosterFile)) {
  $raw = json_decode((string)@file_get_contents($rosterFile), true);
  if (is_array($raw)) {
    $plan = []; $bestScore = -1;
    sms_find_roster_plan($raw, $plan, $bestScore);
    if (is_array($plan)) {
      foreach ([['cmd','자위소방대장'],['commander','자위소방대장'],['chief','자위소방대장'],['deputy','부대장'],['vice','부대장']] as $pair) {
        if (!empty($plan[$pair[0]]) && is_array($plan[$pair[0]])) $smsRoster[] = sms_person($plan[$pair[0]], $pair[1]);
      }
      sms_collect_group_members($plan['groups'] ?? $plan['teams'] ?? [], $smsRoster);
      /* 알 수 없는 구버전 키가 있어도 이름+전화번호를 가진 모든 인원을 놓치지 않습니다. */
      sms_collect_nested_people($plan['groups'] ?? $plan['teams'] ?? $plan, $smsRoster);
    }
  }
}
/* 같은 사람이 겸임해 여러 줄에 있으면 임무를 한 문자로 합칩니다. */
$smsPeople = [];
foreach ($smsRoster as $p) {
  if ($p['name'] === '' && $p['tel'] === '') continue;
  $key = sms_person_key($p);
  if (!isset($smsPeople[$key])) $smsPeople[$key] = $p + ['roles'=>[], 'tasks'=>[]];
  if ($p['role'] !== '' && !in_array($p['role'], $smsPeople[$key]['roles'], true)) $smsPeople[$key]['roles'][] = $p['role'];
  if ($p['task'] !== '' && !in_array($p['task'], $smsPeople[$key]['tasks'], true)) $smsPeople[$key]['tasks'][] = $p['task'];
}
$smsPeople = array_values($smsPeople);

$assembly = trim((string)($smsBi['assembly_kind'] ?? $smsBi['assembly_place'] ?? $smsBi['assembly_address'] ?? ''));
$hasRoute = trim((string)($smsBi['fire_engine_route'] ?? '')) !== '';
$trainDone = false; $jawiDone = false;
if (is_file(__DIR__ . '/train_db.php')) { require_once __DIR__ . '/train_db.php'; if (function_exists('tr_done_this_year')) $trainDone = (bool)tr_done_this_year(); }
if (is_file(__DIR__ . '/jawi_db.php')) { require_once __DIR__ . '/jawi_db.php'; if (function_exists('jw_done_this_year')) $jawiDone = (bool)jw_done_this_year(); }
$monthlyLines = [date('n').'월 소방안전관리자 업무수행 기록표를 작성해 주세요.'];
if (!$trainDone) $monthlyLines[] = date('Y').'년 소방훈련·교육 기록이 아직 없습니다.';
if (!$jawiDone) $monthlyLines[] = date('Y').'년 자위소방대 교육·훈련 기록이 아직 없습니다.';
$monthlyLines[] = '소방계획서 작성 내용과 변경사항을 확인해 주세요.';
$monthlyMessage = '['.$smsSite.'] ' . implode(' ', $monthlyLines);
$statusMessage = '['.$smsSite.'] 소방안전 현황: 집결지 ' . ($assembly !== '' ? $assembly : '미설정') .
  ', 소방차 진입로 ' . ($hasRoute ? '설정 완료' : '미설정') . '.';

/* 이번 달 발송안은 한 번만 만들고, 발송 전까지는 최신 완료 현황으로 갱신합니다. */
$smsCenter = sms_center_read();
$campaignKey = date('Y-m');
$oldCampaign = (array)($smsCenter['campaigns'][$campaignKey] ?? []);
$campaignStatus = (string)($oldCampaign['status'] ?? 'draft');
if ($campaignStatus !== 'sent') {
  $smsCenter['campaigns'][$campaignKey] = [
    'month'=>$campaignKey, 'status'=>'draft', 'message'=>$monthlyMessage,
    'recipients'=>array_values(array_map(fn($m)=>['name'=>$m['name'],'tel'=>$m['tel']], $smsManagers)),
    'created_at'=>(string)($oldCampaign['created_at'] ?? date('Y-m-d H:i:s')), 'updated_at'=>date('Y-m-d H:i:s'), 'sent_at'=>''
  ];
  sms_center_write($smsCenter);
}
$campaign = (array)($smsCenter['campaigns'][$campaignKey] ?? []);
$campaignSent = ($campaign['status'] ?? '') === 'sent';
$autoEnabled = !empty($smsCenter['settings']['enabled']);
$excludedPeople = array_values(array_filter((array)($smsCenter['settings']['excluded'] ?? []), 'is_string'));
// 기존 번호 기준 제외 설정을 사람별 키로 옮겨 사용자의 제외 선택을 보존합니다.
$legacyExcluded = $excludedPeople;
foreach ($smsPeople as $person) {
  $oldKey = $person['tel'] !== '' ? $person['tel'] : ('name:'.$person['name']);
  if (in_array($oldKey, $legacyExcluded, true)) {
    $excludedPeople = array_values(array_diff($excludedPeople, [$oldKey]));
    $excludedPeople[] = sms_person_key($person);
  }
}
$excludedPeople = array_values(array_unique($excludedPeople));
if ($excludedPeople !== $legacyExcluded) {
  $smsCenter['settings']['excluded'] = $excludedPeople;
  sms_center_write($smsCenter);
}
$kakaoSetup = (array)($smsCenter['settings']['kakao'] ?? []);
$kakaoNotice = (string)($_SESSION['kakao_setup_notice'] ?? '');
unset($_SESSION['kakao_setup_notice']);
$recipientCounts = ['excluded'=>0,'missing'=>0,'ready'=>0];
foreach ($smsPeople as $person) {
  if (in_array(sms_person_key($person), $excludedPeople, true)) $recipientCounts['excluded']++;
  elseif ($person['tel'] === '') $recipientCounts['missing']++;
  else $recipientCounts['ready']++;
}

$PAGE_TITLE = '알림';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;                          // 이 페이지는 이미 위에서 로그인 필수 처리했으므로 항상 true
$ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
$ACCOUNT_IS_ADMIN = is_admin();
$ACCOUNT_UNREAD = $unread;                     // 위에서 이미 계산한 값을 그대로 재사용
require __DIR__ . '/_header.php';
?>
<style>
/* 알림 페이지 전용 — service.php/blog.php 와 같은 방식: 기존 .wrap/.card/.btn 위에 최소한만 더합니다 */
.ntf-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.ntf-cnt{font-size:12px;font-weight:800;background:#fdeceb;color:var(--danger);
  border-radius:999px;padding:3px 10px}
.ntf-actions{margin-left:auto;display:flex;gap:8px}

.ntf-list{background:var(--card);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
.ntf-item{display:flex;gap:12px;padding:15px 16px;border-bottom:1px solid var(--bd);text-decoration:none;
  color:inherit;position:relative}
.ntf-item:last-child{border-bottom:0}
.ntf-item:hover{background:var(--bg2)}
.ntf-item.unread{background:#eff6ff}
.ntf-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);flex-shrink:0;margin-top:6px}
.ntf-dot.read{background:transparent}
.ntf-body{flex:1;min-width:0}
.ntf-title{font-size:13.5px;font-weight:700;margin-bottom:3px;color:var(--fg)}
.ntf-desc{font-size:12.5px;color:var(--mut2);line-height:1.6}
.ntf-time{font-size:11px;color:var(--mut);white-space:nowrap;flex-shrink:0}

.ntf-empty{text-align:center;padding:60px 20px;color:var(--mut)}
.ntf-empty .ico{font-size:30px;margin-bottom:10px}
.ntf-empty h3{font-size:16px;font-weight:800;color:var(--mut2);margin-bottom:6px}
.ntf-empty p{font-size:13px;line-height:1.7}
.sms-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:18px}
.sms-card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:18px}
.sms-card--wide{grid-column:1/-1}
.sms-card h2{font-size:16px;margin-bottom:4px}.sms-card__sub{font-size:12.5px;color:var(--mut2);margin-bottom:14px}
.sms-status{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px}.sms-pill{font-size:11.5px;font-weight:700;padding:4px 9px;border-radius:999px;background:#ecfdf3;color:#15803d}
.sms-pill.no{background:#fff3e8;color:#b45309}
.sms-list{border:1px solid var(--bd);border-radius:11px;overflow:hidden}.sms-row{display:grid;grid-template-columns:auto minmax(100px,1fr) minmax(120px,1fr);gap:10px;align-items:center;padding:11px 12px;border-bottom:1px solid var(--bd)}
.sms-row:last-child{border-bottom:0}.sms-name b{display:block;font-size:13px}.sms-name small,.sms-tel{font-size:11.5px;color:var(--mut2)}
.sms-task{grid-column:1/-1;font-size:11.5px;color:var(--mut2);background:var(--bg2);border-radius:8px;padding:7px 9px;line-height:1.55}
.sms-check{width:17px;height:17px;accent-color:var(--brand)}
.sms-channel{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0 14px;padding:10px 12px;background:#f8fafc;border:1px solid var(--bd);border-radius:10px;font-size:12px;color:var(--mut2)}
.sms-channel b{color:var(--fg)}.sms-channel__wait{margin-left:auto;padding:3px 8px;border-radius:999px;background:#eef2f7;color:var(--mut);font-size:11px;font-weight:700}
.sms-preview{width:100%;min-height:84px;border:1px solid var(--bd);border-radius:10px;padding:11px;font:13px/1.6 inherit;color:var(--fg);background:var(--bg2);resize:vertical}
.sms-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.sms-empty{font-size:12.5px;color:var(--mut);padding:14px;text-align:center;border:1px dashed var(--bd);border-radius:10px}
.sms-api{font-size:11.5px;color:var(--mut);margin-top:10px;line-height:1.55}
.campaign{background:linear-gradient(135deg,#f7fbff,#fff);border:1px solid #cfdff3;border-radius:15px;padding:18px;margin-bottom:16px}
.campaign__head{display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap}.campaign__head h2{font-size:17px}.campaign__head p{font-size:12.5px;color:var(--mut2);margin-top:3px}
.campaign__state{margin-left:auto;border-radius:999px;padding:5px 11px;font-size:11.5px;font-weight:800;background:#fff3e8;color:#b45309}.campaign__state.sent{background:#ecfdf3;color:#15803d}
.campaign__body{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:end;margin-top:14px}.campaign__meta{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:9px}
.campaign__actions{display:flex;gap:7px;flex-wrap:wrap}.campaign__settings{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:13px;padding-top:12px;border-top:1px dashed var(--bd);font-size:12px;color:var(--mut2)}
.campaign__settings input{width:58px;padding:7px;border:1px solid var(--bd);border-radius:8px}.campaign__settings .sp{flex:1}.campaign__note{font-size:11px;color:var(--mut)}
@media(max-width:700px){.sms-grid{grid-template-columns:1fr}.sms-card--wide{grid-column:auto}.sms-row{grid-template-columns:1fr auto}.sms-tel{grid-column:1/2}}
@media(max-width:700px){.campaign__body{grid-template-columns:1fr}.campaign__state{margin-left:0}}
.auto-box{background:#fff;border:1px solid var(--bd);border-radius:16px;margin-bottom:14px;overflow:hidden}
.auto-box__head{display:flex;align-items:center;gap:13px;padding:18px 20px;border-bottom:1px solid var(--bd)}
.auto-box__icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#eff6ff;font-size:21px;flex-shrink:0}
.auto-box__title{flex:1}.auto-box__title h2{font-size:17px}.auto-box__title p{font-size:12.5px;color:var(--mut2);margin-top:2px}
.auto-switch{border:0;border-radius:10px;padding:10px 15px;background:var(--brand);color:#fff;font:700 13px inherit;cursor:pointer;white-space:nowrap}.auto-switch.on{background:#edf8f1;color:#15803d;border:1px solid #b9e1c7}
.auto-box__body{padding:17px 20px}.recipient{display:flex;align-items:center;gap:9px;margin-bottom:12px;font-size:12.5px}.recipient b{color:var(--fg)}.recipient span{color:var(--mut2)}
.simple-list{border-top:1px solid var(--bd)}.simple-person{display:grid;grid-template-columns:minmax(120px,1fr) minmax(110px,.7fr) auto;gap:12px;align-items:center;padding:13px 20px;border-bottom:1px solid var(--bd)}
.simple-person.excluded{background:#fafafa;opacity:.62}.simple-person__name b{display:block;font-size:13.5px}.simple-person__name small{font-size:11.5px;color:var(--mut2)}.simple-person__tel{font-size:12px;color:var(--mut2)}
.simple-person__task{grid-column:1/-1;font-size:11.5px;color:var(--mut2);line-height:1.55}.exclude-btn{border:1px solid var(--bd);background:#fff;border-radius:8px;padding:7px 10px;font:600 11.5px inherit;color:var(--mut2);cursor:pointer}.exclude-btn.restore{color:#15803d;border-color:#b9e1c7}
.simple-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 20px;background:#fafbfd;font-size:11.5px;color:var(--mut)}
@media(max-width:620px){.auto-box__head{align-items:flex-start;flex-wrap:wrap}.auto-switch{width:100%}.simple-person{grid-template-columns:1fr auto}.simple-person__tel{grid-column:1/2}}
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 알림</div>
    <h1>알림</h1>
    <p>점검일이 다가오거나 처리할 일이 생기면 여기로 알려드립니다.</p>
  </div>
</header>

<main class="wrap">
  <section class="auto-box" id="kakaoConnect">
    <div class="auto-box__head">
      <div class="auto-box__icon" style="background:#fee500">💬</div>
      <div class="auto-box__title"><h2>카카오톡으로 알림 보내기</h2><p>안전관리자 업무 알림과 대원별 임무를 카카오톡으로 준비합니다.</p></div>
      <button class="exclude-btn" type="button" onclick="document.getElementById('kakaoFields').hidden=false;document.getElementById('channelName').focus()">카카오톡 연결 시작</button>
    </div>
    <div class="auto-box__body">
      <?php if ($kakaoNotice !== ''): ?><p role="status"><?=h($kakaoNotice)?></p><?php endif; ?>
      <form method="post" id="kakaoFields" <?= !$kakaoSetup ? 'hidden' : '' ?>>
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="kakao_setup">
        <label for="channelName">카카오톡 채널 이름</label>
        <input class="sms-preview" style="min-height:0;margin:6px 0 12px" id="channelName" name="channel_name" maxlength="100" required placeholder="예: 우리건물 소방안전" value="<?=h($kakaoSetup['channel_name'] ?? '')?>">
        <label for="channelId">채널 검색용 ID 또는 채널 주소</label>
        <input class="sms-preview" style="min-height:0;margin:6px 0 12px" id="channelId" name="channel_id" maxlength="150" required placeholder="운영 중인 카카오톡 채널 정보" value="<?=h($kakaoSetup['channel_id'] ?? '')?>">
        <button class="btn" type="submit">연결 정보 저장</button>
      </form>
      <p class="sms-api">연결 준비 단계입니다. 채널 정보를 저장해도 아직 메시지는 발송되지 않습니다.</p>
    </div>
  </section>
  <section class="auto-box">
    <div class="auto-box__head">
      <div class="auto-box__icon">🔔</div>
      <div class="auto-box__title"><h2>자동업무 알림</h2><p>안전관리자에게 매월 해야 할 업무를 알려드립니다.</p></div>
      <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="toggle_auto">
        <button class="auto-switch<?= $autoEnabled?' on':'' ?>" type="submit"><?= $autoEnabled?'알림 신청됨 · 취소':'알림 시작 신청' ?></button></form>
    </div>
    <div class="auto-box__body">
      <div class="recipient"><b>받는 사람</b><span><?php
        $managerNames=array_values(array_filter(array_map(fn($m)=>trim($m['name'].' '.($m['tel']?'('.$m['tel'].')':'')), $smsManagers)));
        echo h($managerNames ? implode(', ', $managerNames) : '등록된 안전관리자가 없습니다');
      ?></span></div>
      <textarea class="sms-preview" id="monthlyMsg"><?=h($monthlyMessage)?></textarea>
      <div class="sms-actions"><button class="btn" type="button" onclick="copySms('monthlyMsg')">문구 복사</button><span class="sms-api">매월 <?=$smsCenter['settings']['send_day']?>일 · 카카오톡 연결 후 발송 예정</span></div>
    </div>
  </section>

  <section class="auto-box">
    <div class="auto-box__head">
      <div class="auto-box__icon">👥</div>
      <div class="auto-box__title"><h2>자위소방대 임무 전송</h2><p>편성된 대원에게 각자의 역할과 임무를 보냅니다.</p></div>
      <span class="sms-pill">전체 <?=count($smsPeople)?>명</span>
    </div>
    <?php if (!$smsPeople): ?><div class="auto-box__body"><div class="sms-empty">편성된 대원을 찾지 못했습니다. 최신 편성표를 다시 저장해 주세요.</div></div>
    <?php else: ?><div class="simple-list">
      <?php foreach ($smsPeople as $p):
        $roleText=implode(' · ',$p['roles']); $taskText=implode(' / ',$p['tasks']);
        $personKey=sms_person_key($p); $isExcluded=in_array($personKey,$excludedPeople,true); ?>
        <div class="simple-person<?= $isExcluded?' excluded':'' ?>">
          <div class="simple-person__name"><b><?=h($p['name'] ?: '이름 미입력')?></b><small><?=h($roleText ?: '역할 미입력')?></small></div>
          <div class="simple-person__tel"><?=h($p['tel'] ?: '전화번호 없음')?></div>
          <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="act" value="toggle_exclude"><input type="hidden" name="person_key" value="<?=h($personKey)?>">
            <button class="exclude-btn<?= $isExcluded?' restore':'' ?>" type="submit"><?= $isExcluded?'다시 포함':'발송 제외' ?></button></form>
          <div class="simple-person__task"><?=h($taskText ?: '등록된 임무가 없습니다.')?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="simple-foot"><span>전체 <?=count($smsPeople)?>명 · 제외 <?=$recipientCounts['excluded']?>명 · 번호 없음 <?=$recipientCounts['missing']?>명 · 발송 대상 <?=$recipientCounts['ready']?>명</span><button class="btn btn--primary" type="button" disabled>카카오톡 연결 후 임무 전송</button></div><?php endif; ?>
  </section>

  <div class="card">
    <div class="ntf-head">
      <?php if ($unread > 0): ?><span class="ntf-cnt"><?=$unread?>개 안 읽음</span><?php endif; ?>
      <div class="ntf-actions">
        <?php if ($items): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input type="hidden" name="act" value="read_all">
            <button class="btn" type="submit">모두 읽음</button></form>
          <form method="post" onsubmit="return confirm('알림을 모두 지울까요?')">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input type="hidden" name="act" value="clear">
            <button class="btn" type="submit">전체 지우기</button></form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$items): ?>
      <div class="ntf-empty">
        <div class="ico">🔔</div>
        <h3>아직 알림이 없습니다</h3>
        <p>점검일이 다가오거나 처리할 일이 생기면 여기로 알려드릴게요.</p>
      </div>
    <?php else: ?>
      <div class="ntf-list">
        <?php foreach ($items as $n): ?>
          <a class="ntf-item<?= empty($n['read']) ? ' unread' : '' ?>"
             href="<?= !empty($n['link']) ? h($n['link']) : '#' ?>"
             <?php if (empty($n['read'])): ?>
               onclick="fetch('/notifications.php',{method:'POST',body:new URLSearchParams({csrf:<?=json_encode($CSRF)?>,act:'read_one',id:<?=json_encode($n['id']??'')?>})})"
             <?php endif; ?>>
            <span class="ntf-dot<?= empty($n['read']) ? '' : ' read' ?>"></span>
            <div class="ntf-body">
              <div class="ntf-title"><?=h($n['title'] ?? '')?></div>
              <?php if (!empty($n['body'])): ?><div class="ntf-desc"><?=h($n['body'])?></div><?php endif; ?>
            </div>
            <span class="ntf-time"><?=h($n['at'] ?? '')?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php require __DIR__ . '/memo_widget.php'; ?>
</main>

<script>
function toggleRoster(checked){
  document.querySelectorAll('.roster-check').forEach(function(el){ el.checked=checked; });
}
function copyText(text){
  navigator.clipboard.writeText(String(text||'')).then(function(){ alert('문구를 복사했습니다.'); });
}
function copySms(id){
  var el=document.getElementById(id); if(!el)return;
  navigator.clipboard.writeText(el.value).then(function(){ alert('문구를 복사했습니다.'); });
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
