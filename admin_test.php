<?php
// admin_test.php — 관리자 전용 기능 점검표
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!is_admin()) {
  header('Location: /index.php');
  exit;
}

$ROOT = __DIR__;
$DATA = $ROOT . '/data';

function read_json_file(string $file): array {
  if (!is_file($file)) return [];
  $data = json_decode((string)@file_get_contents($file), true);
  return is_array($data) ? $data : [];
}
function can_write_dir(string $dir): bool {
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir) || !is_writable($dir)) return false;
  $tmp = $dir . '/.__tworix_test_' . bin2hex(random_bytes(3));
  $ok = @file_put_contents($tmp, 'ok', LOCK_EX) !== false;
  if ($ok) @unlink($tmp);
  return $ok;
}
function file_has(string $file, string $needle): bool {
  return is_file($file) && strpos((string)@file_get_contents($file), $needle) !== false;
}
function file_has_regex(string $file, string $pattern): bool {
  return is_file($file) && preg_match($pattern, (string)@file_get_contents($file)) === 1;
}
function status_class(string $status): string {
  return $status === 'ok' ? 'ok' : ($status === 'warn' ? 'warn' : 'bad');
}
function check_item(string $label, bool $ok, string $okText = '정상', string $badText = '확인 필요', string $link = ''): array {
  return ['label'=>$label, 'status'=>$ok ? 'ok' : 'bad', 'text'=>$ok ? $okText : $badText, 'link'=>$link];
}

$members = read_json_file($DATA . '/members.json');
$reviewRows = read_json_file($DATA . '/assist_log.json');
$useridSeen = [];
$useridCaseConflicts = [];
$emailSeen = [];
$emailConflicts = [];
foreach ($members as $key => $member) {
  $userid = (string)($member['userid'] ?? $key);
  $useridNormalized = strtolower($userid);
  if (isset($useridSeen[$useridNormalized]) && $useridSeen[$useridNormalized] !== $userid) {
    $useridCaseConflicts[] = $useridSeen[$useridNormalized] . ' / ' . $userid;
  } else {
    $useridSeen[$useridNormalized] = $userid;
  }

  $email = strtolower(trim((string)($member['email'] ?? '')));
  if ($email === '') continue;
  if (isset($emailSeen[$email]) && $emailSeen[$email] !== $userid) {
    $emailConflicts[] = $email;
  } else {
    $emailSeen[$email] = $userid;
  }
}
$pendingReviews = 0;
foreach ($reviewRows as $row) {
  if (($row['kind'] ?? '') === 'review' && ($row['status'] ?? 'pending') !== 'resolved') $pendingReviews++;
}

$phpFiles = [
  'index.php','signup.php','email_code.php',
  'user_key.php','admin_members.php','admin_member_review.php',
  'building_manager.php','building_info.php','building_setup.php','building_setup_chat.php',
  'work_log.php','work_log_form.php','work_log_print.php',
  'assist_log.php','evac_common.php','evac_assign_api.php','settings.php','withdraw.php',
];

$groups = [];
$groups['기본 파일'] = [];
foreach ($phpFiles as $file) {
  $groups['기본 파일'][] = check_item($file . ' 존재', is_file($ROOT . '/' . $file), '존재', '파일 없음', '/' . $file);
}

$groups['데이터 저장소'] = [
  check_item('data 폴더 쓰기', can_write_dir($DATA), '쓰기 가능', 'data 폴더 권한 확인'),
  check_item('members.json 읽기', is_file($DATA . '/members.json') && is_array($members), count($members) . '명 확인', 'members.json 없음 또는 오류'),
  check_item('assist_log.json 구조', !is_file($DATA . '/assist_log.json') || is_array($reviewRows), '읽기 가능', 'JSON 구조 오류', '/assist_log_view.php'),
  ['label'=>'미처리 확인요청', 'status'=>$pendingReviews > 0 ? 'warn' : 'ok', 'text'=>$pendingReviews . '건', 'link'=>'/admin_members.php'],
];

$groups['회원가입/로그인'] = [
  check_item('회원 화면은 index.php 팝업만 사용',
    file_has($ROOT . '/signup.php', 'index.php?auth=signup') && !file_has($ROOT . '/signup.php', '<!DOCTYPE html>'),
    'signup.php 처리 전용', 'signup.php 단독 화면 확인', '/index.php?auth=signup'),
  check_item('아이디 확인 상태를 가입 버튼에 반영',
    file_has($ROOT . '/index.php', "moUidStatus === 'available'") && file_has($ROOT . '/index.php', 'moUidCheckedValue === value'),
    '확인 완료 후에만 가입 가능', '중복확인 상태 연결 확인', '/index.php?auth=signup'),
  check_item('반복 중복확인 제거',
    !file_has($ROOT . '/index.php', 'moUidTimer') && !file_has($ROOT . '/index.php', 'moChkUid() &&'),
    '입력 완료/버튼 클릭 시 1회', '자동 반복 호출 코드 확인', '/index.php?auth=signup'),
  check_item('아이디 대소문자 중복 차단',
    file_has($ROOT . '/signup.php', 'find_member_key_ci') && file_has($ROOT . '/signup.php', 'normalize_userid'),
    '대소문자 구분 없이 확인', '서버 중복검사 확인'),
  check_item('가입 저장 잠금 및 최종 재검사',
    file_has($ROOT . '/signup.php', 'members_transaction') && file_has($ROOT . '/signup.php', 'flock($lock, LOCK_EX)'),
    '동시 가입 보호 있음', 'members.json 저장 잠금 확인'),
  check_item('이메일 최종 중복검사',
    file_has($ROOT . '/signup.php', 'member_email_taken') && file_has($ROOT . '/email_code.php', 'strtolower(trim'),
    '소문자 정규화 및 재검사', '이메일 중복검사 확인'),
  check_item('새 비밀번호 8자 기준',
    file_has($ROOT . '/signup.php', 'USER_PASSWORD_MIN = 8') && file_has($ROOT . '/index.php', 'minlength="8"'),
    '8자 이상', '비밀번호 길이 기준 확인'),
  check_item('비밀번호 확인을 아이디 상태와 별도 검사',
    file_has($ROOT . '/index.php', 'const passwordMatchOk = moChkPw2()'),
    '입력 즉시 일치 여부 표시', '비밀번호 확인 호출 순서 확인', '/index.php?auth=signup'),
  [
    'label'=>'기존 아이디 대소문자 충돌',
    'status'=>$useridCaseConflicts ? 'warn' : 'ok',
    'text'=>$useridCaseConflicts ? implode(', ', $useridCaseConflicts) : '충돌 없음',
    'link'=>'/admin_members.php',
  ],
  [
    'label'=>'기존 이메일 중복',
    'status'=>$emailConflicts ? 'warn' : 'ok',
    'text'=>$emailConflicts ? implode(', ', array_unique($emailConflicts)) : '중복 없음',
    'link'=>'/admin_members.php',
  ],
];

$groups['관리자 회원 보기'] = [
  check_item('회원 아이디가 building_manager.php?uid 로 연결', file_has($ROOT . '/admin_members.php', 'building_manager.php?uid='), '연결됨', '회원 아이디 링크 확인', '/admin_members.php'),
  check_item('관리자 uid 보기 함수', file_has($ROOT . '/user_key.php', 'app_admin_view_user_key'), '있음', 'user_key.php 확인'),
  check_item('building_manager 관리자 전용 박스', file_has($ROOT . '/building_manager.php', '관리자 전용'), '있음', '관리자 박스 없음', '/building_manager.php'),
  check_item('내부 링크 uid 유지', file_has($ROOT . '/building_manager.php', '$url = function'), '유지됨', 'uid 유지 링크 확인'),
  check_item('문답 저장 fetch가 uid 유지', file_has($ROOT . '/building_setup_chat.php', 'location.pathname + location.search'), '유지됨', '문답 POST uid 누락'),
];

$groups['건물 기본정보 문답'] = [
  check_item('문답형 기본정보 화면', is_file($ROOT . '/building_setup_chat.php'), '있음', '파일 없음', '/building_setup_chat.php'),
  check_item('연면적/바닥면적 한 질문', file_has($ROOT . '/building_setup_chat.php', "field:'__areas'"), '묶음 질문 있음', '면적 질문 분리됨', '/building_setup_chat.php'),
  check_item('업무수행 확인내용 기본값 포함', file_has($ROOT . '/building_setup_chat.php', "field:'note_sobang'") && file_has($ROOT . '/building_setup_chat.php', "field:'note_etc'"), '포함됨', 'note_* 질문 누락', '/building_setup_chat.php'),
  check_item('모르면 TWORIX 요청 후 다음 질문', file_has($ROOT . '/building_setup_chat.php', 'step++') && file_has($ROOT . '/building_setup_chat.php', 'TWORIX에 검토요청'), '흐름 있음', '검토요청 흐름 확인'),
];

$groups['업무수행 기록표'] = [
  check_item('기본정보 자동 동기화', file_has($ROOT . '/work_log.php', 'sync_worklog_fixed_with_basic'), '동기화 있음', '기본정보 연동 누락', '/work_log.php'),
  check_item('건물정보 완료 시 접힘', file_has($ROOT . '/work_log.php', '<details class="section"') && file_has($ROOT . '/work_log.php', '$setupComplete'), '접힘 처리 있음', '접힘 처리 누락', '/work_log.php'),
  check_item('업무수행 요청 상태 표시', file_has($ROOT . '/work_log.php', '업무수행 기록표:') && file_has($ROOT . '/work_log.php', '관리자 확인완료'), '표시 있음', '요청 상태 누락', '/work_log.php'),
  check_item('월별 작성 uid 유지', file_has($ROOT . '/work_log_form.php', 'app_user_key()') && file_has($ROOT . '/work_log_form.php', '$url = function'), '유지됨', '작성 화면 uid 누락', '/work_log_form.php'),
  check_item('연간 출력 uid 유지', file_has($ROOT . '/work_log_print.php', 'app_user_key()') && file_has($ROOT . '/work_log_print.php', '$url = function'), '유지됨', '출력 화면 uid 누락', '/work_log_print.php'),
];

$groups['확인요청 처리'] = [
  check_item('assist_log.php review 저장', file_has($ROOT . '/assist_log.php', "'review'") && file_has($ROOT . '/assist_log.php', "row['status']"), '확인', 'status 저장 확인 필요', '/assist_log.php'),
  check_item('관리자 확인완료 처리 화면', file_has($ROOT . '/admin_member_review.php', "act' value=\"resolve\"") || file_has($ROOT . '/admin_member_review.php', 'save_building'), '있음', '처리 화면 확인', '/admin_member_review.php'),
  check_item('회원 목록 확인요청 배지', file_has($ROOT . '/admin_members.php', 'reviewCount') && file_has($ROOT . '/admin_members.php', 'tag--review'), '있음', '배지 누락', '/admin_members.php'),
  check_item('기본정보 완료 우선순위', file_has($ROOT . '/building_manager.php', '$reviewResolvedRecent') && file_has($ROOT . '/building_manager.php', '관리자 확인완료'), '있음', '완료 표시 흐름 확인', '/building_manager.php'),
];

$groups['시뮬레이션 배정요청'] = [
  check_item('신청 전 건물·관리자 정보 확인창',
    file_has($ROOT . '/building_manager.php', 'evacReqForm')
      && file_has($ROOT . '/building_manager.php', 'manager_phone')
      && file_has($ROOT . '/building_manager.php', 'evacFormAddress'),
    '기본정보 자동입력 및 수정 가능', '신청 확인창 누락', '/building_manager.php'),
  check_item('신청정보 서버 검증 및 저장',
    file_has($ROOT . '/evac_assign_api.php', 'manager_name')
      && file_has($ROOT . '/evac_assign_api.php', 'evac_store_request')
      && file_has($ROOT . '/evac_assign_api.php', 'hash_equals'),
    '주소·이름·전화번호 저장', '신청정보 저장 확인'),
  check_item('동시 신청 저장 보호',
    file_has($ROOT . '/evac_common.php', 'EVAC_REQUEST_FILE')
      && file_has($ROOT . '/evac_common.php', 'evac_store_request')
      && file_has($ROOT . '/evac_common.php', 'flock($lock, LOCK_EX)'),
    '요청 파일 잠금 있음', '요청 저장 잠금 확인'),
  check_item('회원 관리에 배정요청 표시',
    file_has($ROOT . '/admin_members.php', 'evacRequestCount')
      && file_has($ROOT . '/admin_members.php', 'evac-request-info')
      && file_has($ROOT . '/admin_members.php', '시뮬레이션 요청'),
    '목록 배지 및 신청정보 표시', '관리자 표시 누락', '/admin_members.php'),
  check_item('배정 완료 시 요청 제거',
    file_has($ROOT . '/evac_assign_api.php', 'evac_remove_request($uid)'),
    '자동 제거', '요청 제거 확인'),
];

$groups['설정/탈퇴'] = [
  check_item('설정 화면', is_file($ROOT . '/settings.php'), '있음', '파일 없음', '/settings.php'),
  check_item('회원탈퇴 화면', is_file($ROOT . '/withdraw.php') && file_has($ROOT . '/settings.php', 'withdraw.php'), '연결됨', '탈퇴 링크 확인', '/settings.php'),
];

$summary = ['ok'=>0, 'warn'=>0, 'bad'=>0];
foreach ($groups as $items) {
  foreach ($items as $item) $summary[$item['status']]++;
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>기능 테스트 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--fg:#1a2436;--mut:#64748b;--brand:#2563eb;--ok:#047857;--warn:#b45309;--bad:#b91c1c}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none;color:inherit}.nav{height:56px;background:#fff;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;padding:0 22px;position:sticky;top:0;z-index:10}.brand{font-weight:800;font-size:21px}.btn{display:inline-flex;align-items:center;padding:8px 14px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font-size:13px;font-weight:700}.btn:hover{border-color:var(--brand);color:var(--brand)}
.wrap{max-width:1120px;margin:auto;padding:28px 22px 70px}h1{margin:0;font-size:25px}.lead{margin:6px 0 18px;color:var(--mut)}
.summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}.pill{border-radius:999px;padding:6px 12px;font-size:13px;font-weight:800}.pill.ok{background:#ecfdf5;color:var(--ok)}.pill.warn{background:#fff7ed;color:var(--warn)}.pill.bad{background:#fef2f2;color:var(--bad)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px}.card{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:18px}.card h2{font-size:16px;margin:0 0 12px}.item{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:start;border-top:1px solid var(--bd);padding:10px 0}.item:first-of-type{border-top:0}.name{font-size:13px;font-weight:700}.txt{font-size:12px;color:var(--mut);margin-top:2px}.status{font-size:11px;font-weight:900;border-radius:999px;padding:3px 8px;white-space:nowrap}.status.ok{background:#ecfdf5;color:var(--ok)}.status.warn{background:#fff7ed;color:var(--warn)}.status.bad{background:#fef2f2;color:var(--bad)}.open{font-size:12px;color:var(--brand);font-weight:800}
</style>
</head>
<body>
<nav class="nav">
  <a class="brand" href="/">TWORIX</a>
  <div style="display:flex;gap:8px"><a class="btn" href="/admin_members.php">회원 관리</a><a class="btn" href="/clients_mini.php">대시보드</a></div>
</nav>
<main class="wrap">
  <h1>기능 테스트</h1>
  <p class="lead">기능을 추가한 뒤 빠진 파일, 링크, 저장 흐름을 빠르게 확인하는 관리자 전용 점검표입니다.</p>
  <div class="summary">
    <span class="pill ok">정상 <?=$summary['ok']?></span>
    <span class="pill warn">주의 <?=$summary['warn']?></span>
    <span class="pill bad">확인 필요 <?=$summary['bad']?></span>
  </div>
  <div class="grid">
    <?php foreach ($groups as $title => $items): ?>
      <section class="card">
        <h2><?=h($title)?></h2>
        <?php foreach ($items as $item): ?>
          <div class="item">
            <div>
              <div class="name"><?=h($item['label'])?></div>
              <div class="txt"><?=h($item['text'])?><?php if (!empty($item['link'])): ?> · <a class="open" href="<?=h($item['link'])?>">열기</a><?php endif; ?></div>
            </div>
            <span class="status <?=h(status_class($item['status']))?>"><?=h($item['status'] === 'ok' ? '정상' : ($item['status'] === 'warn' ? '주의' : '확인'))?></span>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
