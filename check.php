<?php
/* =============================================================
   check.php — 사이트 점검 (브라우저에서 실행)
   ─────────────────────────────────────────────────────────────
   관리자로 로그인한 뒤 주소창에 /check.php 를 치면 됩니다.
   명령창(터미널)을 쓰지 않아도 됩니다.

   ★ 안전합니다 ★
     회원 데이터를 읽거나 고치지 않습니다.
     코드 파일을 읽어서 살펴보고, 화면은 "비로그인 상태"로만 열어봅니다.
     저장·삭제를 일으키는 요청은 하지 않습니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();
@set_time_limit(120);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!is_admin()) {
  http_response_code(403);
  echo '<!doctype html><meta charset="utf-8">'
     . '<div style="max-width:480px;margin:80px auto;padding:0 20px;'
     . 'font-family:system-ui,\'Apple SD Gothic Neo\',sans-serif;line-height:1.8">'
     . '<h2 style="font-size:20px">관리자만 볼 수 있습니다</h2>'
     . '<p><a href="/index.php" style="color:#1d4ed8">← 돌아가기</a></p></div>';
  exit;
}

$ROOT = __DIR__;

/* ══════════════════════════════════════════════════════════
   여기를 고치시면 됩니다 — 사라지면 안 되는 것들
   새 기능을 넣을 때마다 한 줄씩 추가해 두세요.
   ★ 소스 파일에 그대로 적혀 있는 글자여야 합니다.
     name="photo_<?=$pk?>" 처럼 PHP로 만들어지는 건 찾지 못합니다.
   ══════════════════════════════════════════════════════════ */
/* ══════════════════════════════════════════════════════════
   기능 목록 — 여기가 이 도구의 핵심입니다.

   기능을 하나 만들 때마다 아래에 한 줄 추가하세요.
   그러면 다음에 누가 그 부분을 지우거나 잘못 고쳤을 때 바로 잡힙니다.

     '기능 이름' => [ '파일.php' => ['소스에 반드시 있어야 할 글자', ...] ],

   ★ 소스 파일에 그대로 적혀 있는 글자여야 합니다.
     name="photo_<?=$pk?>" 처럼 PHP가 만들어내는 건 찾지 못합니다.
     그럴 때는 함수 이름이나 CSS 클래스 이름처럼 고정된 글자를 쓰세요.
   ══════════════════════════════════════════════════════════ */
const FEATURES = [

  /* ── 건물 소방안전관리 메인 ── */
  '진행 현황 패널' => [
    'building_manager.php' => ['진행 현황'],
  ],
  '기본정보 채움 정도 표시' => [
    'building_info.php'    => ['bi_progress'],
    'building_manager.php' => ['biProg'],
  ],

  /* ── 건물 기본정보 ── */
  '기본정보 문답 입력' => [
    'building_setup_chat.php' => ["'save_step'"],
    'building_manager.php'    => ['building_setup_chat'],
  ],
  '기본정보 문답 · 모를 때 요청' => [
    'building_setup_chat.php' => ['review'],
  ],
  '기본정보 문답 · 처음부터 다시' => [
    'building_setup_chat.php' => ["=== 'reset'", '처음부터 다시'],
  ],

  /* ── 업무수행 기록표 ── */
  '확인내용 기본값 입력칸' => [
    'work_log.php' => ['note_sobang', '확인내용 기본값 저장'],
  ],
  '확인내용 문답 연결' => [
    'work_log.php' => ['work_log_setup_chat.php'],
  ],
  '확인내용 문답 · 모를 때 요청' => [
    'work_log_setup_chat.php' => ['TWORIX에 요청하기'],
  ],
  '확인내용 문답 · 처음부터 다시' => [
    'work_log_setup_chat.php' => ["=== 'reset'", '처음부터 다시'],
  ],
  '수행일자 달력 (타자 없이 클릭)' => [
    'work_log_form.php' => ['dateInput', 'dpop__grid', 'dcell'],
  ],
  '건물정보 접어두기' => [
    'work_log.php' => ['<details class="section"', 'setupComplete'],
  ],

  /* ── 소방훈련·교육 기록부 ── */
  '훈련 서식 3칸 (소화·통보·피난)' => [
    'train_edit.php'  => ['fire_c_sohwa', 'fire_c_tongbo', 'fire_c_pinan'],
    'train_print.php' => ['fire_c_sohwa'],
  ],
  '훈련 사진 4장' => [
    'train_edit.php'   => ['tr_photo_save', 'multipart/form-data'],
    'train_print.php'  => ['pcell'],
    'train_db.php'     => ['tr_photo_dir'],
    'train_photo.php'  => ['getimagesize'],
  ],
  '훈련 문답 작성' => [
    'train.php'      => ['train_chat.php'],
    'train_chat.php' => ["'save_step'"],
  ],
  '훈련 문답 · 모를 때 요청' => [
    'train_chat.php' => ['requestReview', 'TWORIX에 요청하기'],
  ],
  '훈련 문답 · 모를 때 요청 (버튼 연결)' => [
    'train_chat.php' => ['addReview(b, s);'],  // 정의가 아니라 '호출' 을 봅니다 (세미콜론 포함)
  ],
  '100%까지 남은 항목 안내' => [
    'train_db.php'         => ['tr_missing', 'tr_year_status'],
    'building_manager.php' => ['cardprog', 'tr_year_status'],   // 카드 안에 표시
  ],
  '훈련 문답 · 이어서 쓰기' => [
    'train_chat.php' => ['이어서 쓰시겠어요', "'new'"],
  ],
  '훈련 기록 완료 판정' => [
    'train_db.php'          => ['tr_data_complete', 'tr_done_this_year'],
    'building_manager.php'  => ['tr_done_this_year'],
  ],
  '연간 항목 세부 표시' => [
    'building_manager.php' => ['psub__i', 'ptag--part'],
  ],
  '로그인·가입 처리 (화면은 팝업 하나)' => [
    'auth.php'   => ["mode === 'signup'", "mode === 'login'"],
    'index.php'  => ['action="/auth.php"'],
    'signup.php' => ['auth.php'],                 // 옛 주소는 팝업으로 돌려보냄
  ],
  '가입 아이디 중복 확인' => [
    'auth.php'  => ['check_uid'],                 // 확인 응답
    'index.php' => ['mo-uid-btn', 'moAskUid'],    // 팝업의 중복확인 버튼
  ],
  '탈퇴 시 계정 기록 항상 정리' => [
    'admin_members.php' => ['체크와 상관없이 항상 지웁니다'],
  ],
  '훈련 문답 · 처음부터 다시' => [
    'train_chat.php' => ["=== 'reset'", '처음부터 다시'],
  ],

  /* ── 자위소방대 교육·훈련 기록부 (별지 제13호) ── */
  '자위소방대 기록부 · 별지13호 서식' => [
    'jawi_print.php' => ['별지 제13호서식', '교육·훈련 참석확인', '초기대응체계'],
  ],
  '자위소방대 · 문답 작성' => [
    'jawi.php'      => ['jawi_chat.php'],
    'jawi_chat.php' => ["'save_step'"],
  ],
  '자위소방대 문답 · 모든 질문에 입력칸' => [
    /* 질문 유형에 맞는 처리 분기가 빠지면 그 질문에서 진행이 멈춥니다 */
    'jawi_chat.php' => ["s.type === 'text'", "s.type === 'team'", "s.type === 'attend'"],
  ],
  '자위소방대 · 대장 고르면 연락처 자동' => [
    'jawi_chat.php' => ['MGR_TEL', 'telField'],
  ],

  '자위소방대 문답 · 모를 때 요청' => [
    'jawi_chat.php' => ['addReview(b, s);', 'TWORIX에 요청하기'],
  ],
  '자위소방대 문답 · 처음부터 다시' => [
    'jawi_chat.php' => ["=== 'reset'", '처음부터 다시'],
  ],
  '자위소방대 · 기록 하나면 바로 들어가기' => [
    'jawi.php' => ['stay'],
  ],
  '자위소방대 · 편성표에서 한 번에 가져오기' => [
    'jawi_db.php'   => ['jw_legacy_summary', 'jw_group_field'],
    'jawi_chat.php' => ['askTeamImport', 'TEAM_FIELDS',
                        /* 가져온 항목은 다시 묻지 않아야 합니다 */
                        "s.type === 'team'"],
  ],

  '자위소방대 · 편성표에서 명단 불러오기' => [
    'jawi_db.php'   => ['jw_legacy_members'],
    'jawi_chat.php' => ['LEGACY'],
  ],
  '자위소방대 · 카드 분리와 진행률' => [
    'building_manager.php' => ['jw_year_status', '자위소방대 편성표', "\$jawiStatus"],
  ],

  /* ── 관리자 ── */
  '회원 삭제 시 데이터도 함께 정리' => [
    'admin_members.php' => ['/building/', 'assist_log.json'],
  ],
  '시뮬레이션 배정 QR' => [
    'admin_members.php' => ['evac_qr.php'],
  ],
  '검토요청 없어도 서류 열람' => [
    'admin_members.php' => ['uidlink', 'btn--docs'],
  ],
  '회원 서류 한 화면에서 보기' => [
    'admin_member_review.php' => ['fp_label', '자위소방대 편성표',
                                  '업무수행 기록표', '소방훈련·교육 기록부'],
  ],
  '탈퇴 회원 기록 확인' => [
    'admin_member_review.php' => ['탈퇴함'],
  ],
  '확인요청 위치 표시' => [
    'admin_member_review.php' => ['review_target', 'is-asked'],
  ],
  '관리자 대리 보기' => [
    'impersonate.php'         => ["'_imp'", 'imp_log'],
    '_imp.php'                => ['impbar'],
    'admin_members.php'       => ['impersonate.php'],
    'admin_member_review.php' => ['impersonate.php'],
  ],
  '대리 보기 알림 띠 연결' => [
    'building_info.php'  => ['_imp.php'],
    'train_db.php'       => ['_imp.php'],
    'fire_plan_db.php'   => ['_imp.php'],
    'user_key.php'       => ['_imp.php'],
    'work_log_form.php'  => ['_imp.php'],
  ],
  '회원 데이터 격리' => [
    'user_key.php'      => ['app_user_key'],
    'building_info.php' => ['app_user_key'],
  ],
];

/* 예전 방식과 호환 — 아래 코드가 MUST_HAVE 를 그대로 씁니다 */
function must_have_map(): array {
  $out = [];
  foreach (FEATURES as $files) {
    foreach ($files as $file => $needles) {
      foreach ($needles as $n) $out[$file][] = $n;
    }
  }
  foreach ($out as $k => $v) $out[$k] = array_values(array_unique($v));
  return $out;
}

/* 주소를 직접 치고 들어가는 화면 — "들어갈 길이 없다"고 알리지 않습니다 */
const ENTRY_PAGES = [
  'index.php','login.php','logout.php','signup.php','check.php',
  'kakao_callback.php','oauth_callback.php',
  'mail_config.php','telegram_config.php','db_config.php',
  'whoami.php','train_photo.php','evac_qr.php','assist_log_view.php',
  'fire_page.php','subscribe_page.php',
  'admin_test.php','impersonate.php','auth.php','jawi_db.php',   // 주소·버튼으로 직접 여는 화면
];

/* 화면 점검에서 열어볼 주소 — 저장을 일으키지 않는 것만 적으세요.
   ?new=1 처럼 뭔가 만들어지는 주소는 절대 넣지 마세요. */
const PAGES_TO_OPEN = [
  '/index.php'                 => '메인',
  '/building_manager.php'      => '건물 소방안전관리',
  '/work_log.php'              => '업무수행 기록표',
  '/train.php'                 => '훈련·교육 기록부',
  '/building_setup.php'        => '기본정보(표)',
  '/building_setup_chat.php'   => '기본정보(문답)',
  '/work_log_setup_chat.php'   => '확인내용(문답)',
  '/train_chat.php'            => '훈련 기록(문답)',
  '/assist.php'                => '서식 작성 도우미',
];

/* ── 도구 ────────────────────────────────────────────────── */
function php_files(string $root): array {
  $out = [];
  foreach (glob($root . '/*.php') ?: [] as $f) $out[] = basename($f);
  sort($out);
  return $out;
}
function linked_targets(string $src): array {
  $t = [];
  /* 화면 링크와 이동 주소를 찾습니다 */
  if (preg_match_all('#(?:(?:href|action)\s*=\s*["\']?|Location:\s*)/?([A-Za-z0-9_]+\.php)#', $src, $m)) {
    foreach ($m[1] as $x) $t[$x] = true;
  }
  /* 함수로 주소를 만드는 경우도 링크로 봅니다 (예: url 도우미) */
  if (preg_match_all('#[\x27"]/([A-Za-z0-9_]+\.php)[\x27"]#', $src, $m)) {
    foreach ($m[1] as $x) $t[$x] = true;
  }
  return array_keys($t);
}
function fmt_size(int $b): string {
  if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
  if ($b >= 1024)    return number_format($b / 1024, 0) . ' KB';
  return $b . ' B';
}

/* ══════════════════════════════════════════════════════════
   1. 서버 상태
   ══════════════════════════════════════════════════════════ */
$server = [];

$server[] = [
  PHP_VERSION_ID >= 70400 ? 'ok' : 'warn',
  'PHP 버전',
  PHP_VERSION,
  PHP_VERSION_ID >= 70400 ? '' : 'PHP 7.4 이상을 권합니다.',
];

foreach (['mbstring'=>'한글 글자수 처리', 'json'=>'데이터 저장', 'session'=>'로그인 유지', 'gd'=>'사진 크기 줄이기'] as $ext => $why) {
  $has = extension_loaded($ext);
  $server[] = [
    $has ? 'ok' : ($ext === 'gd' || $ext === 'mbstring' ? 'warn' : 'bad'),
    "확장 {$ext}",
    $has ? '설치됨' : '없음',
    $has ? '' : "{$why}에 씁니다." . ($ext === 'mbstring' ? ' 없어도 대체 코드로 돌아갑니다.' : ''),
  ];
}

foreach (['data' => '회원 기록 저장', 'uploads' => '사진 저장'] as $dir => $why) {
  $p = $ROOT . '/' . $dir;
  if (!is_dir($p)) {
    $server[] = ['warn', "{$dir} 폴더", '없음', "{$why}에 씁니다. 아직 안 쓰신다면 괜찮습니다."];
  } else {
    $w = is_writable($p);
    $server[] = [$w ? 'ok' : 'bad', "{$dir} 폴더 쓰기", $w ? '가능' : '불가',
                 $w ? '' : '권한을 755(안 되면 775)로 바꿔주세요. 저장이 안 됩니다.'];
  }
}

/* data 폴더가 웹에서 열리는지 — 열리면 회원 정보가 노출됩니다 */
if (is_dir($ROOT . '/data')) {
  $ht = is_file($ROOT . '/data/.htaccess');
  $server[] = [$ht ? 'ok' : 'bad', 'data 폴더 보호', $ht ? '.htaccess 있음' : '.htaccess 없음',
    $ht ? '' : '회원 정보가 웹에서 그대로 열릴 수 있습니다. data/.htaccess 에 Require all denied 를 넣으세요. (Nginx면 서버 설정에서 /data/ 차단)'];
}

/* 회원 파일 크기 — 커지면 로그인이 느려집니다 */
$mf = $ROOT . '/data/members.json';
if (is_file($mf)) {
  $sz = (int)filesize($mf);
  $cnt = 0;
  $j = json_decode((string)@file_get_contents($mf), true);
  if (is_array($j)) $cnt = count($j);
  $lvl = $sz > 3145728 ? 'bad' : ($sz > 1048576 ? 'warn' : 'ok');
  $server[] = [$lvl, '회원 수 / 파일 크기', number_format($cnt) . '명 · ' . fmt_size($sz),
    $lvl === 'ok' ? '' : '로그인할 때마다 이 파일 전체를 읽고 다시 씁니다. 회원이 더 늘기 전에 저장 방식을 바꾸시는 게 좋습니다.'];
}

/* ══════════════════════════════════════════════════════════
   2. 코드 점검
   ══════════════════════════════════════════════════════════ */
$files = php_files($ROOT);
$code  = [];

/* 2-1 문법 */
$bad = [];
foreach ($files as $f) {
  $src = (string)@file_get_contents($ROOT . '/' . $f);
  try { @token_get_all($src, TOKEN_PARSE); }
  catch (ParseError $e) { $bad[] = "{$f} — {$e->getMessage()} ({$e->getLine()}번째 줄)"; }
  catch (Throwable $e)  { $bad[] = "{$f} — " . $e->getMessage(); }
}
$code[] = ['문법 오류가 없다', $bad, count($files) . '개 파일 검사'];

/* 2-2 없는 파일로 가는 링크 */
$bad = [];
foreach ($files as $f) {
  $src = (string)@file_get_contents($ROOT . '/' . $f);
  foreach (linked_targets($src) as $t) {
    if (!in_array($t, $files, true)) $bad[] = "{$f} → 없는 파일 {$t}";
  }
}
/* list.php · save.php 는 예전 게시판 잔재입니다. 정리하시면 아래 줄을 지우세요. */
$known = ['list.php →', 'save.php →'];
$rest = []; $notes = [];
foreach ($bad as $e) {
  $hit = false;
  foreach ($known as $k) { if (strpos($e, $k) !== false) { $hit = true; break; } }
  $hit ? $notes[] = $e : $rest[] = $e;
}
$code[] = ['없는 파일로 연결하는 링크가 없다', $rest,
  $notes ? ('이미 알고 있는 것 ' . count($notes) . '건 (예전 게시판 잔재): ' . implode(', ', $notes)) : ''];

/* 2-3 들어갈 길이 없는 화면 */
$linked = [];
foreach ($files as $f) {
  $src = (string)@file_get_contents($ROOT . '/' . $f);
  foreach (linked_targets($src) as $t) { if ($t !== $f) $linked[$t] = true; }
}
$bad = [];
foreach ($files as $f) {
  if (isset($linked[$f]) || in_array($f, ENTRY_PAGES, true)) continue;
  if (str_starts_with($f, '_')) continue;
  $src = (string)@file_get_contents($ROOT . '/' . $f);
  if (!preg_match('#<(html|body|form)#i', $src)) continue;
  $bad[] = "{$f} — 아무 화면에서도 연결하지 않습니다";
}
/* sign_check.php 는 파일 첫 줄에 "확인 후 삭제" 라고 적힌 진단용 파일입니다 */
$notes2 = []; $rest2 = [];
foreach ($bad as $e) { strpos($e, 'sign_check.php') !== false ? $notes2[] = $e : $rest2[] = $e; }
$code[] = ['들어갈 길이 없는 화면이 없다', $rest2,
  $notes2 ? 'sign_check.php 는 진단용 파일입니다. 확인 끝났으면 서버에서 지우세요.'
          : '주소로 직접 여는 화면은 check.php 위쪽 ENTRY_PAGES 에 적어두세요'];

/* 2-4 사라지면 안 되는 것 */
$bad = [];
$featOk = 0; $featBad = 0;
$srcCache = [];
foreach (FEATURES as $featName => $fileMap) {
  $miss = [];
  foreach ($fileMap as $file => $needles) {
    if (!in_array($file, $files, true)) { $miss[] = "{$file} 파일이 없습니다"; continue; }
    if (!isset($srcCache[$file])) $srcCache[$file] = (string)@file_get_contents($ROOT . '/' . $file);
    foreach ($needles as $n) {
      if (strpos($srcCache[$file], $n) === false) $miss[] = "{$file} 에 \"{$n}\" 없음";
    }
  }
  if ($miss) { $featBad++; $bad[] = "[{$featName}] " . implode(' · ', $miss); }
  else $featOk++;
}
$code[] = ["기능 {$featOk}개가 그대로 살아 있다", $bad,
           '기능을 새로 만들면 check.php 위쪽 FEATURES 에 한 줄 추가하세요'];

/* 2-5 서로 부르는 파일끼리 같은 이름의 함수를 두 번 만들면 화면이 통째로 죽습니다.
      (페이지마다 h() 를 따로 두는 것은 서로 부르지 않으므로 문제가 아닙니다) */
$defs = [];   // 파일 → 그 파일이 만드는 함수 (function_exists 로 감싼 건 제외)
$incs = [];   // 파일 → 그 파일이 직접 부르는 파일
foreach ($files as $f) {
  $src = (string)@file_get_contents($ROOT . '/' . $f);

  /* 토큰으로 읽어야 자바스크립트 안의 function 을 PHP 함수로 착각하지 않습니다 */
  $d = [];
  $tk = @token_get_all($src);
  $depth = 0;
  for ($i = 0, $n = count($tk); $i < $n; $i++) {
    $t = $tk[$i];
    if ($t === '{') { $depth++; continue; }
    if ($t === '}') { $depth--; continue; }
    if (!is_array($t) || $t[0] !== T_FUNCTION) continue;
    /* function 다음에 오는 이름 찾기 (익명 함수면 이름이 없습니다) */
    for ($j = $i + 1; $j < $n; $j++) {
      if (is_array($tk[$j]) && $tk[$j][0] === T_WHITESPACE) continue;
      if (is_array($tk[$j]) && $tk[$j][0] === T_STRING) {
        $fn = $tk[$j][1];
        $guarded = strpos($src, "function_exists('{$fn}')") !== false
                || strpos($src, "function_exists(\"{$fn}\")") !== false;
        if (!$guarded) $d[strtolower($fn)] = $fn;
      }
      break;
    }
  }
  $defs[$f] = $d;

  $i = [];
  if (preg_match_all('/(?:require|include)(?:_once)?\s*[( ]?\s*__DIR__\s*\.\s*[\'"]\/?([A-Za-z0-9_]+\.php)/i', $src, $m)) {
    foreach ($m[1] as $t) $i[$t] = true;
  }
  if (preg_match_all('/(?:require|include)(?:_once)?\s*[( ]?\s*[\'"]\.?\/?([A-Za-z0-9_]+\.php)[\'"]/i', $src, $m)) {
    foreach ($m[1] as $t) $i[$t] = true;
  }
  $incs[$f] = array_keys($i);
}

/* 한 파일이 결국 끌어오는 파일 전부 */
$pullAll = function (string $f, array $incs, array $seen = []) use (&$pullAll): array {
  foreach ($incs[$f] ?? [] as $t) {
    if (isset($seen[$t])) continue;
    $seen[$t] = true;
    $seen = $pullAll($t, $incs, $seen);
  }
  return $seen;
};

$bad = [];
foreach ($files as $f) {
  $group = array_merge([$f], array_keys($pullAll($f, $incs)));
  $where = [];
  foreach ($group as $g) {
    foreach ($defs[$g] ?? [] as $lc => $name) {
      if (isset($where[$lc]) && $where[$lc] !== $g) {
        $bad["{$lc}|{$where[$lc]}|{$g}"] =
          "{$name}() 가 {$where[$lc]} 와 {$g} 양쪽에 있는데 {$f} 가 둘을 함께 부릅니다";
      } else {
        $where[$lc] = $g;
      }
    }
  }
}
$code[] = ['함께 부르는 파일끼리 함수 이름이 겹치지 않는다', array_values($bad),
           '겹치면 그 화면이 통째로 죽습니다. function_exists 로 감싸거나 이름을 바꾸세요'];
/* 요약 */
$codeFail = 0; foreach ($code as $c) $codeFail += count($c[1]);
$srvFail  = 0; foreach ($server as $s) if ($s[0] === 'bad') $srvFail++;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>사이트 점검 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif}
a{text-decoration:none;color:inherit}
.nav{background:#fff;border-bottom:1px solid var(--bd)}
.nav__in{max-width:860px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13.5px;font-weight:600;
  cursor:pointer;font-family:inherit;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--pri:hover{background:var(--brand2);color:#fff}
.wrap{max-width:860px;margin:0 auto;padding:26px 20px 80px}
h1{font-size:25px;font-weight:800;letter-spacing:-.3px}
.lead{color:var(--mut2);font-size:14.5px;margin-top:7px;max-width:62ch}

.banner{display:flex;gap:12px;border-radius:14px;padding:18px 20px;margin:20px 0;
  font-size:15px;line-height:1.7;align-items:center;flex-wrap:wrap}
.banner--ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.banner--bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.banner b{font-size:17px}

.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:20px 22px;margin-bottom:16px}
.card h2{font-size:16px;font-weight:800;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.card .note{font-size:12.5px;color:var(--mut);margin-top:4px;line-height:1.6}

.row{display:flex;gap:12px;align-items:flex-start;padding:10px 0;
  border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}
.row:first-of-type{border-top:0}
.ic{width:20px;flex-shrink:0;text-align:center;font-weight:800}
.ic--ok{color:#16a34a}.ic--bad{color:#dc2626}.ic--warn{color:#d97706}.ic--skip{color:var(--mut)}
.row__k{flex:0 0 150px;font-weight:600}
.row__v{flex:1;min-width:150px}
.row__d{width:100%;padding-left:32px;font-size:12.5px;color:var(--mut2);line-height:1.6}
.detail{padding-left:32px;font-size:13px;color:#b91c1c;line-height:1.8;word-break:break-all}
.okline{padding-left:32px;font-size:13px;color:var(--mut);}
code{background:#eef2f7;padding:2px 6px;border-radius:5px;font-size:12.5px;
  font-family:ui-monospace,monospace}
.safe{display:flex;gap:11px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;
  padding:14px 16px;font-size:13.5px;color:#1e40af;line-height:1.7;margin-top:16px}
@media(max-width:600px){.row__k{flex:0 0 100%}.row__d,.detail,.okline{padding-left:0}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div style="display:flex;gap:8px">
      <a class="btn" href="/check.php">다시 검사</a>
      <a class="btn" href="/admin_members.php">← 관리자</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <h1>사이트 점검</h1>
  <p class="lead">파일을 올린 뒤 여기서 한 번 확인하세요.
    코드가 깨지지 않았는지, 넣어둔 기능이 사라지지 않았는지 살펴봅니다.</p>

  <?php if ($codeFail === 0 && $srvFail === 0): ?>
    <div class="banner banner--ok"><span style="font-size:22px">✓</span>
      <div><b>이상 없습니다.</b><br>코드 점검 <?=count($code)?>가지를 모두 통과했습니다.</div></div>
  <?php else: ?>
    <div class="banner banner--bad"><span style="font-size:22px">✕</span>
      <div><b>확인이 필요한 곳이 <?=$codeFail + $srvFail?>군데 있습니다.</b><br>
        아래 빨간 줄을 보세요.</div></div>
  <?php endif; ?>

  <!-- 서버 상태 -->
  <div class="card">
    <h2>🖥 서버 상태</h2>
    <?php foreach ($server as [$lvl, $k, $v, $d]): ?>
      <div class="row">
        <span class="ic ic--<?=h($lvl)?>"><?= $lvl==='ok'?'✓':($lvl==='bad'?'✕':'!') ?></span>
        <span class="row__k"><?=h($k)?></span>
        <span class="row__v"><?=h($v)?></span>
        <?php if ($d !== ''): ?><span class="row__d"><?=h($d)?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- 코드 점검 -->
  <div class="card">
    <h2>📄 코드 점검</h2>
    <p class="note">파일을 읽어서 살펴봅니다. 아무것도 고치지 않습니다.</p>
    <?php foreach ($code as [$name, $bad, $note]): ?>
      <div class="row">
        <span class="ic ic--<?= $bad ? 'bad' : 'ok' ?>"><?= $bad ? '✕' : '✓' ?></span>
        <span class="row__v" style="font-weight:600"><?=h($name)?>
          <?php if ($bad): ?><span style="color:#dc2626">(<?=count($bad)?>건)</span><?php endif; ?>
        </span>
      </div>
      <?php foreach (array_slice($bad, 0, 12) as $b): ?>
        <div class="detail">· <?=h($b)?></div>
      <?php endforeach; ?>
      <?php if (count($bad) > 12): ?>
        <div class="detail">· 외 <?=count($bad) - 12?>건 더</div>
      <?php endif; ?>
      <?php if ($note !== ''): ?><div class="okline"><?=h($note)?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>

    <!-- 화면 점검 -->
  <div class="card">
    <h2>🌐 화면 점검</h2>
    <p class="note">화면이 오류 없이 열리는지 브라우저가 직접 열어봅니다.
      <b>로그인 정보를 빼고</b> 요청하므로 저장되거나 바뀌는 것이 없습니다.</p>

    <div style="margin-top:14px">
      <button class="btn btn--pri" type="button" id="openBtn">화면 열어보기 (<?=count(PAGES_TO_OPEN)?>개)</button>
      <span id="openProg" style="font-size:13px;color:var(--mut2);margin-left:10px"></span>
    </div>
    <div id="openList" style="margin-top:10px"></div>
  </div>

  <script>
  (function(){
    var PAGES = <?=json_encode(PAGES_TO_OPEN, JSON_UNESCAPED_UNICODE)?>;
    var btn  = document.getElementById('openBtn');
    var prog = document.getElementById('openProg');
    var list = document.getElementById('openList');

    function row(level, label, path, msg){
      var ic = level === 'ok' ? '✓' : (level === 'bad' ? '✕' : '!');
      var d = document.createElement('div');
      d.className = 'row';
      d.innerHTML = '<span class="ic ic--' + level + '">' + ic + '</span>' +
        '<span class="row__k"></span><span class="row__v"><code></code> </span>';
      d.querySelector('.row__k').textContent = label;
      d.querySelector('code').textContent = path;
      d.querySelector('.row__v').appendChild(document.createTextNode(' ' + msg));
      list.appendChild(d);
    }

    btn.addEventListener('click', function(){
      btn.disabled = true;
      list.innerHTML = '';
      var paths = Object.keys(PAGES);
      var i = 0, bad = 0;

      function next(){
        if (i >= paths.length){
          prog.textContent = bad === 0 ? '모두 정상입니다' : (bad + '군데 확인이 필요합니다');
          btn.disabled = false;
          return;
        }
        var path = paths[i], label = PAGES[path];
        prog.textContent = (i + 1) + ' / ' + paths.length;

        /* credentials:'omit' → 로그인 쿠키를 보내지 않습니다 (저장이 일어나지 않음) */
        fetch(path, { credentials: 'omit', cache: 'no-store' })
          .then(function(res){
            return res.text().then(function(body){ return { res: res, body: body }; });
          })
          .then(function(r){
            var body = r.body || '';
            var err = '';
            ['Fatal error','Parse error','Warning:','Notice:','Deprecated:'].forEach(function(k){
              if (!err && body.indexOf(k) >= 0){
                var m = body.substr(body.indexOf(k), 120).split('<')[0].split('\n')[0];
                err = m.trim();
              }
            });

            if (err){ bad++; row('bad', label, path, 'PHP 오류: ' + err); }
            else if (r.res.status >= 500){ bad++; row('bad', label, path, '서버 오류 ' + r.res.status); }
            else if (r.res.status === 404){ bad++; row('bad', label, path, '파일이 없습니다 (404)'); }
            else if (r.res.redirected){
              var to = r.res.url.replace(location.origin, '');
              row('ok', label, path, '로그인 화면으로 넘김 → ' + to);
            }
            else if (r.res.status === 200){ row('ok', label, path, '정상'); }
            else { row('warn', label, path, '응답 ' + r.res.status); }
            i++; next();
          })
          .catch(function(e){
            row('warn', label, path, '열어보지 못했습니다 (' + e.message + ')');
            i++; next();
          });
      }
      next();
    });
  })();
  </script>


  <div class="safe">
    <div>🔒</div>
    <div>
      이 화면은 <b>관리자만</b> 볼 수 있습니다.
      회원 기록을 읽거나 고치지 않고, 저장·삭제를 일으키는 주소도 열지 않습니다.
      점검 항목을 늘리시려면 <code>check.php</code> 위쪽의
      <code>MUST_HAVE</code> 에 한 줄씩 추가하세요.
    </div>
  </div>
</main>

</body>
</html>
