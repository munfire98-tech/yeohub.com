<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function read_json(string $file): array {
  if (!is_file($file)) return [];
  $data = json_decode((string)@file_get_contents($file), true);
  return is_array($data) ? $data : [];
}
function write_json(string $file, array $data): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $file);
}
/* 저장된 영어 키를 사람이 읽는 이름으로 */
function fp_label(string $key): string {
  static $L = [
    'id'=>'문서번호', 'usage_code'=>'용도 구분', 'building_name'=>'대상명',
    'plan_date'=>'작성일', 'status'=>'상태', 'jawi_type'=>'자위소방대 유형',
    'created_at'=>'만든 날', 'updated_at'=>'마지막 수정', 'sections'=>'작성 내용',
    'site_name'=>'대상명', 'total'=>'인원', 'saved'=>'저장일', 'created'=>'만든 날',
    'name'=>'이름', 'tel'=>'연락처', 'role'=>'임무', 'team'=>'조', 'note'=>'비고',
    /* 업무수행 기록표 */
    'date'=>'수행일자', 'performer'=>'수행자', 'result'=>'결과', 'action'=>'조치',
    'sobang'=>'소방시설', 'pinan'=>'피난·방화시설', 'hwagi'=>'화기취급감독', 'etc'=>'기타사항',
    'bcode'=>'건물 고유번호', 'sangho'=>'상호', 'address'=>'소재지', 'grade'=>'등급',
    'floor_a'=>'지상층', 'floor_b'=>'지하층', 'area_t'=>'연면적', 'area_f'=>'바닥면적', 'dongsu'=>'동수',
    'note_sobang'=>'소방시설 확인 기본값', 'note_pinan'=>'피난방화 확인 기본값',
    'note_hwagi'=>'화기취급 확인 기본값', 'note_etc'=>'기타 확인 기본값',
    /* 소방훈련·교육 기록부 */
    't_name'=>'대상명', 't_use'=>'용도', 't_grade'=>'등급', 't_addr'=>'주소',
    't_rep'=>'대표자', 't_tel'=>'전화번호', 'mgrs'=>'소방안전관리자',
    'fire_date'=>'훈련 일시', 'fire_place'=>'훈련 장소', 'fire_kind'=>'훈련 구분',
    'fire_teacher'=>'훈련교관', 'fire_target'=>'참석대상(명)', 'fire_join'=>'참석(명)',
    'fire_absent'=>'미참석(명)', 'fire_material'=>'훈련보조재료', 'fire_types'=>'훈련 종류',
    'fire_c_sohwa'=>'소화훈련 내용', 'fire_c_tongbo'=>'통보훈련 내용', 'fire_c_pinan'=>'피난훈련 내용',
    'fire_content'=>'그 밖의 훈련내용', 'fire_result'=>'훈련성과',
    'fire_problem'=>'훈련 문제점', 'fire_improve'=>'훈련 개선계획',
    'edu_date'=>'교육 일시', 'edu_place'=>'교육 장소', 'edu_teacher'=>'교육강사',
    'edu_target'=>'참석대상(명)', 'edu_join'=>'참석(명)', 'edu_absent'=>'미참석(명)',
    'edu_content'=>'교육내용', 'edu_result'=>'교육성과',
    'edu_problem'=>'교육 문제점', 'edu_improve'=>'교육 개선계획',
    'photos'=>'관련 사진', 'appt'=>'선임일자', 'qual'=>'보유자격', 'type'=>'자격구분',
  ];
  /* 소방계획서 15개 법정 항목은 번호로 저장되어 있습니다 */
  static $ITEMS = [
    '1'=>'① 일반현황', '2'=>'② 소방·방화·전기·가스·위험물시설 현황',
    '3'=>'③ 자체점검계획 및 대응대책', '4'=>'④ 소방·피난·방화시설 점검·정비계획',
    '5'=>'⑤ 피난계획', '6'=>'⑥ 방화구획·마감재·방염물품 유지관리',
    '7'=>'⑦ 관리 권원 분리 대상물 안전관리', '8'=>'⑧ 공동 소방안전관리 협의',
    '9'=>'⑨ 자위소방대 조직 및 임무', '10'=>'⑩ 화기취급 작업 안전조치·감독',
    '11'=>'⑪ 소방훈련 및 교육 계획', '12'=>'⑫ 위험물 저장·취급',
    '13'=>'⑬ 업무수행 기록·유지', '14'=>'⑭ 화재 초기대응',
    '15'=>'⑮ 그 밖에 소방서장 요청사항',
  ];
  if (isset($L[$key]))     return $L[$key];
  if (isset($ITEMS[$key])) return $ITEMS[$key];
  return $key;
}

/* 상태 값도 한글로 */
function fp_value_ko(string $key, $v) {
  if ($key === 'status') {
    return ['draft'=>'작성 중', 'done'=>'작성 완료', 'complete'=>'작성 완료'][(string)$v] ?? $v;
  }
  return $v;
}
function render_value($value, string $key = ''): string {
  $value = fp_value_ko($key, $value);

  if (is_bool($value)) return $value ? '예' : '아니오';

  if (is_array($value)) {
    if (!$value) return '<span class="empty-value">미입력</span>';
    $isList = array_keys($value) === range(0, count($value) - 1);

    if ($isList) {
      /* 목록 안에 또 표가 들어 있으면 (예: 자위소방대 대원 명단) 하나씩 펼칩니다.
         예전에는 여기서 "Array" 로 깨졌습니다. */
      $hasArray = false;
      foreach ($value as $v) { if (is_array($v)) { $hasArray = true; break; } }
      if (!$hasArray) return h(implode(', ', array_map('strval', $value)));

      $html = '';
      foreach ($value as $i => $item) {
        $html .= '<div class="listitem"><span class="listno">' . ($i + 1) . '</span>'
               . render_value($item) . '</div>';
      }
      return $html;
    }

    $html = '<dl class="fields">';
    foreach ($value as $k => $item) {
      $html .= '<dt>' . h(fp_label((string)$k)) . '</dt><dd>' . render_value($item, (string)$k) . '</dd>';
    }
    return $html . '</dl>';
  }

  if ($value === null || trim((string)$value) === '') return '<span class="empty-value">미입력</span>';
  return nl2br(h((string)$value));
}

/* 검토요청 본문 앞머리로 어느 서식인지 알아냅니다.
   회원 화면에서 "업무수행 기록표: ..." 처럼 서식 이름을 붙여 보냅니다. */
function review_target(string $text): array {
  $t = trim($text);
  $item = '';
  if (preg_match('/(\d{1,2})\s*(?:번|항목|호)/u', $t, $m)) $item = $m[1];

  if (strpos($t, '업무수행') !== false || strpos($t, '기록표') !== false) return ['worklog', $item];
  if (strpos($t, '자위소방대') !== false)                                   return ['jawi', $item];
  if (strpos($t, '훈련') !== false || strpos($t, '교육') !== false)          return ['train', $item];
  if (strpos($t, '소방계획서') !== false || strpos($t, '계획서') !== false)   return ['plan', $item];
  if (strpos($t, '기본정보') !== false || strpos($t, '건물정보') !== false)   return ['building', $item];
  return ['', $item];
}
if (!is_admin()) {
  if (!empty($_SESSION['_imp'])) {
    header('Location: /impersonate.php?stop=1'); exit;   // 대리 보기 중이면 관리자로 되돌립니다
  }
  header('Location: /index.php'); exit;
}

$dataDir = __DIR__ . '/data';
$membersFile = $dataDir . '/members.json';
$logFile = $dataDir . '/assist_log.json';
$members = read_json($membersFile);
$uid = trim((string)($_GET['uid'] ?? $_POST['uid'] ?? ''));
/* 탈퇴한 회원의 기록도 확인할 수 있어야 하므로 회원 목록에 없어도 막지 않습니다 */
if ($uid === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $uid)) {
  http_response_code(404);
  exit('회원을 찾을 수 없습니다.');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save_building') {
  if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }

  $buildingFile = $dataDir . '/building/' . $uid . '/info.json';
  $building = read_json($buildingFile);
  $fields = ['name','use','address','rep','tel','floor_b','floor_a','area_t','area_f','dongsu',
             'wd_day','wd_night','hd_day','hd_night'];
  foreach ($fields as $field) $building[$field] = trim((string)($_POST[$field] ?? ''));
  $grade = trim((string)($_POST['grade'] ?? ''));
  $building['grade'] = in_array($grade, ['특급','1급','2급','3급'], true) ? $grade : '';

  $names = (array)($_POST['m_name'] ?? []);
  $appts = (array)($_POST['m_appt'] ?? []);
  $quals = (array)($_POST['m_qual'] ?? []);
  $types = (array)($_POST['m_type'] ?? []);
  $tels  = (array)($_POST['m_tel'] ?? []);
  $building['mgrs'] = [];
  for ($i = 0; $i < 4; $i++) {
    $name = trim((string)($names[$i] ?? ''));
    $tel  = trim((string)($tels[$i] ?? ''));
    if ($name === '' && $tel === '') continue;
    $type = (string)($types[$i] ?? '');
    $building['mgrs'][] = [
      'name' => $name,
      'appt' => trim((string)($appts[$i] ?? '')),
      'qual' => trim((string)($quals[$i] ?? '')),
      'type' => in_array($type, ['주','보조'], true) ? $type : '',
      'tel'  => $tel,
    ];
  }
  $building['updated'] = date('Y-m-d H:i:s');

  $legacyFile = $dataDir . '/worklog/' . $uid . '/building.json';
  $legacy = read_json($legacyFile);
  $legacy['sangho'] = $building['name'];
  $legacy['grade'] = $building['grade'];
  $legacy['address'] = $building['address'];
  $legacy['floor_b'] = $building['floor_b'];
  $legacy['floor_a'] = $building['floor_a'];
  $legacy['area_t'] = $building['area_t'];
  $legacy['area_f'] = $building['area_f'];
  $legacy['dongsu'] = $building['dongsu'];
  $legacy['performer'] = $building['mgrs'][0]['name'] ?? ($legacy['performer'] ?? '');
  foreach (['note_sobang','note_pinan','note_hwagi','note_etc'] as $field) {
    $legacy[$field] = (string)($building[$field] ?? $legacy[$field] ?? '');
  }

  if (!write_json($buildingFile, $building) || !write_json($legacyFile, $legacy)) {
    http_response_code(500); exit('기본정보를 저장하지 못했습니다.');
  }
  header('Location: /admin_member_review.php?uid=' . rawurlencode($uid) . '&saved=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'resolve') {
  if (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
  $targetId = (string)($_POST['request_id'] ?? '');
  $logs = read_json($logFile);
  $resolvedTexts = [];
  foreach ($logs as &$row) {
    if (($row['kind'] ?? '') === 'review' && ($row['uid'] ?? '') === $uid
        && ($targetId === 'all' || ($row['id'] ?? '') === $targetId)) {
      /* 이미 처리된 건은 다시 알리지 않습니다 */
      if (($row['status'] ?? '') !== 'resolved') {
        $resolvedTexts[] = (string)($row['text'] ?? '');
      }
      $row['status'] = 'resolved';
      $row['resolved_at'] = date('Y-m-d H:i:s');
    }
  }
  unset($row);
  write_json($logFile, $logs);

  /* 회원에게 알림을 보냅니다 (notifications.php 와 같은 저장 방식) */
  if ($resolvedTexts) {
    $ndir = $dataDir . '/notifications';
    if (!is_dir($ndir)) @mkdir($ndir, 0775, true);
    $nfile = $ndir . '/' . $uid . '.json';
    $nlist = is_file($nfile) ? (json_decode((string)@file_get_contents($nfile), true) ?: []) : [];

    $cnt  = count($resolvedTexts);
    $body = $cnt === 1
      ? mb_substr($resolvedTexts[0], 0, 120)
      : (mb_substr($resolvedTexts[0], 0, 80) . ' 외 ' . ($cnt - 1) . '건');

    array_unshift($nlist, [
      'id'    => bin2hex(random_bytes(8)),
      'title' => '요청하신 내용을 확인했습니다',
      'body'  => $body,
      'link'  => '/building_manager.php',
      'read'  => false,
      'at'    => date('Y-m-d H:i:s'),
    ]);
    $nlist = array_slice($nlist, 0, 100);
    $ntmp  = $nfile . '.tmp';
    if (file_put_contents($ntmp, json_encode($nlist, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false) {
      @rename($ntmp, $nfile);
    }
  }

  header('Location: /admin_member_review.php?uid=' . rawurlencode($uid)); exit;
}

$requests = [];
foreach (array_reverse(read_json($logFile)) as $row) {
  if (($row['kind'] ?? '') === 'review' && ($row['uid'] ?? '') === $uid) $requests[] = $row;
}

/* 이 아이디가 아직 살아있는 회원인지 — 탈퇴했다면 확인할 자료가 없습니다 */
$memberGone = !isset($members[$uid]);

$planDir = $dataDir . '/fireplan/' . $uid;
$plans = [];      // 소방계획서
$jawis = [];      // 자위소방대 편성표 (계획서와 다른 서식이라 따로 봅니다)
if (is_dir($planDir)) {
  foreach (glob($planDir . '/*.json') ?: [] as $file) {
    $base = basename($file);
    if ($base === '_index.json') continue;
    $row = read_json($file);
    if (!$row) continue;
    if ($base === '_jawi.json') { $jawis = $row; continue; }
    $row['_file'] = $base;
    $plans[] = $row;
  }
}
usort($plans, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
/* 업무수행 기록표 — data/worklog/{uid}/mYYYY-MM.json */
/* 어느 서식에 확인요청이 걸려 있는지 모읍니다 */
$reviewBy = ['worklog'=>[], 'plan'=>[], 'jawi'=>[], 'train'=>[], 'building'=>[], ''=>[]];
foreach ($requests as $rq) {
  if (($rq['status'] ?? 'pending') === 'resolved') continue;
  [$form, $item] = review_target((string)($rq['text'] ?? ''));
  $rq['_item'] = $item;
  $reviewBy[$form][] = $rq;
}
/* 화면에 붙일 표시 조각 */
function review_flag(array $list): string {
  if (!$list) return '';
  return '<span class="badge pending" style="margin-left:7px">확인요청 ' . count($list) . '건</span>';
}
function review_notes(array $list): string {
  if (!$list) return '';
  $h = '<div class="rvbox"><div class="rvbox__t">🙋 회원이 물어본 내용</div>';
  foreach ($list as $r) {
    $h .= '<div class="rvbox__q">' . nl2br(h((string)($r['text'] ?? ''))) . '</div>'
        . '<div class="rvbox__at">' . h(substr((string)($r['at'] ?? ''), 0, 16)) . '</div>';
  }
  return $h . '</div>';
}
$logDir  = $dataDir . '/worklog/' . $uid;
$workLogs = [];
$workFixed = read_json($logDir . '/building.json');
if (is_dir($logDir)) {
  foreach (glob($logDir . '/m*.json') ?: [] as $file) {
    $row = read_json($file);
    if (!$row) continue;
    $month = preg_replace('/^m|\.json$/', '', basename($file));
    $workLogs[$month] = $row;
  }
  krsort($workLogs);
}

/* 소방훈련·교육 기록부 — data/train/{uid}/{id}.json */
$trainDir = $dataDir . '/train/' . $uid;
$trains = [];
if (is_dir($trainDir)) {
  foreach (glob($trainDir . '/*.json') ?: [] as $file) {
    if (basename($file) === '_index.json') continue;
    $row = read_json($file);
    if (!$row) continue;
    $row['_file'] = basename($file);
    $trains[] = $row;
  }
  usort($trains, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
}
$left   = !isset($members[$uid]);          // 탈퇴했거나 목록에 없는 회원
$member = $members[$uid] ?? ['nickname'=>'(탈퇴한 회원)', 'email'=>''];
$buildingInfo = read_json($dataDir . '/building/' . $uid . '/info.json');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($uid)?> 검토요청 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--fg:#1a2436;--mut:#64748b;--brand:#2563eb}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,"Malgun Gothic",sans-serif;line-height:1.6}
a{text-decoration:none;color:inherit}.nav{background:#fff;border-bottom:1px solid var(--bd)}.nav__in{max-width:1100px;margin:auto;height:56px;padding:0 22px;display:flex;align-items:center;justify-content:space-between}
.brand{font-size:21px;font-weight:800}.wrap{max-width:1100px;margin:auto;padding:28px 22px 70px}h1{margin:0;font-size:25px}.lead{margin:6px 0 22px;color:var(--mut)}
.card{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:20px;margin-bottom:16px}.card h2{margin:0 0 12px;font-size:18px}
.request{border-top:1px solid var(--bd);padding:14px 0}.request:first-of-type{border-top:0}.meta{font-size:12px;color:var(--mut);margin-bottom:5px}.question{font-size:15px;white-space:pre-wrap}
.badge{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700}.pending{background:#fff7ed;color:#c2410c}.resolved{background:#ecfdf5;color:#047857}
.btn{display:inline-flex;align-items:center;padding:8px 14px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.plan summary{cursor:pointer;font-weight:800;padding:4px 0}.plan+.plan{border-top:1px solid var(--bd);margin-top:15px;padding-top:15px}.plan-meta{font-size:12px;color:var(--mut);font-weight:400;margin-left:8px}
.fields{display:grid;grid-template-columns:minmax(110px,180px) 1fr;margin:14px 0 0;border:1px solid var(--bd);border-radius:10px;overflow:hidden}.fields dt,.fields dd{margin:0;padding:9px 11px;border-bottom:1px solid var(--bd)}.fields dt{font-size:12px;font-weight:700;background:#f8fafc}.fields dd{font-size:13px;word-break:break-word}.fields dt:last-of-type,.fields dd:last-of-type{border-bottom:0}.fields .fields{margin:0;border-radius:6px}.empty-value{color:#94a3b8}.empty{padding:25px;text-align:center;color:var(--mut)}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.edit-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:5px}.field.wide{grid-column:1/-1}.field label{font-size:12px;font-weight:700;color:var(--mut)}.field input,.field select{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:13px;background:#fff}.mgr-table{width:100%;border-collapse:collapse;margin-top:16px;font-size:12px}.mgr-table th,.mgr-table td{padding:7px 5px;border-bottom:1px solid var(--bd);text-align:left}.mgr-table input,.mgr-table select{width:100%;min-width:80px;padding:7px;border:1px solid #cbd5e1;border-radius:7px}.saved{padding:11px 14px;margin-bottom:16px;border:1px solid #a7f3d0;border-radius:9px;background:#ecfdf5;color:#047857;font-size:13px;font-weight:700}.gone-notice{padding:12px 15px;margin-bottom:16px;border:1px solid #e2e8f0;border-radius:9px;background:#f8fafc;color:#475569;font-size:13px;line-height:1.7}.gone-notice b{display:block;color:#334155;margin-bottom:3px}.gone-notice code{background:#e2e8f0;padding:1px 6px;border-radius:5px;font-size:12px}@media(max-width:720px){.edit-grid{grid-template-columns:1fr 1fr}.field.wide{grid-column:1/-1}.mgr-scroll{overflow-x:auto}}
.listitem{display:flex;gap:9px;align-items:flex-start;padding:8px 0;border-top:1px dashed var(--bd)}
.listitem:first-of-type{border-top:0}
.listno{flex:0 0 22px;height:22px;border-radius:50%;background:#eef2ff;color:#1d4ed8;
  display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800}
.listitem > .fields{flex:1;margin:0}
.dimnote{font-size:12px;color:var(--mut);font-weight:400}
.secbox{border:1px solid var(--bd);border-radius:11px;padding:13px 15px;margin-top:10px;background:#fff}
.secbox.is-blank{background:#fbfcfe}
.secbox.is-asked{border-color:#fdba74;background:#fffbf5;box-shadow:0 0 0 3px rgba(251,146,60,.10)}
.secbox__h{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.secbox__t{font-size:13.5px;font-weight:800}
.secform textarea,.wlform textarea,.wlform input,.wlform select{
  width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;
  font:inherit;font-size:13.5px;line-height:1.6;background:#fff;resize:vertical}
.secform textarea:focus,.wlform textarea:focus,.wlform input:focus,.wlform select:focus{
  outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.secform__r{display:flex;gap:9px;align-items:center;margin-top:8px;flex-wrap:wrap}
.rvbox{background:#fff7ed;border:1px solid #fed7aa;border-radius:9px;padding:11px 13px;margin-bottom:10px}
.rvbox__t{font-size:12px;font-weight:800;color:#b45309;margin-bottom:5px}
.rvbox__q{font-size:13.5px;color:#7c2d12;white-space:pre-wrap;line-height:1.7}
.rvbox__at{font-size:11px;color:#a16207;margin-top:3px}
.wlform{margin-top:12px}
.wlhead{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.wlhead label{flex:1;min-width:150px;display:flex;flex-direction:column;gap:5px;
  font-size:12px;font-weight:700;color:var(--mut)}
.wlrow{border:1px solid var(--bd);border-radius:10px;padding:11px 13px;margin-bottom:9px}
.wlrow__t{font-size:13px;font-weight:800;margin-bottom:6px}
.wlrow__b{display:flex;gap:8px;margin-top:7px;flex-wrap:wrap}
.wlrow__b select{flex:0 0 130px}
.wlrow__b input{flex:1;min-width:160px}.gobox{display:flex;gap:16px;align-items:center;flex-wrap:wrap;
  background:#fffbf5;border:1px solid #fed7aa;border-radius:13px;padding:16px 18px;margin-bottom:16px}
.gobox__l{flex:1;min-width:230px}
.gobox__t{font-size:15px;font-weight:800;color:#b45309}
.gobox__d{font-size:13px;color:#92400e;margin-top:4px;line-height:1.65}
.gobox__b{white-space:nowrap}
.goinline{margin-left:9px;font-size:12px;font-weight:700;color:#b45309;
  border:1px solid #fed7aa;border-radius:7px;padding:3px 9px;background:#fffbf5;white-space:nowrap}
.goinline:hover{background:#fef3c7}</style>
</head>
<body>
<nav class="nav"><div class="nav__in"><a class="brand" href="/">TWORIX</a><a class="btn" href="/admin_members.php">← 회원 관리</a></div></nav>
<main class="wrap">
  <h1><?=h($uid)?> 회원 검토</h1>
  <p class="lead">
    <?=h($member['nickname'] ?? '')?><?= !empty($member['email']) ? ' · ' . h($member['email']) : '' ?>
    <?php if (!$left): ?>
  <div class="gobox">
    <div class="gobox__l">
      <div class="gobox__t">👤 이 회원 화면에서 직접 고치기</div>
      <div class="gobox__d">회원이 쓰는 화면 그대로 들어가서 서류를 고칠 수 있습니다.
        화면 위에 주황색 띠가 떠 있고, 다 끝나면 <b>관리자로 돌아가기</b>를 누르면 됩니다.</div>
    </div>
    <a class="btn btn--primary gobox__b" href="/impersonate.php?uid=<?=urlencode($uid)?>">
      이 회원으로 들어가기 →</a>
  </div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') !== ''): ?>
    <div class="saved">✓ 저장했습니다. 회원 화면에도 반영되었습니다.</div>
  <?php endif; ?>
  <?php if ($left): ?>
      <span class="badge pending" style="margin-left:6px">탈퇴함</span>
    <?php endif; ?>
    · 검토요청과 저장된 소방계획서를 함께 확인합니다.
  </p>
  <?php if ($left): ?>
    <div class="saved" style="background:#fff7ed;border-color:#fed7aa;color:#b45309">
      회원 목록에 없는 아이디입니다. 탈퇴했는데 기록만 남아 있는 경우입니다.
      더 필요 없으면 <b>회원 관리 → 남아 있는 데이터 폴더</b>에서 정리하세요.
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="saved">건물 기본정보를 저장했습니다.</div><?php endif; ?>

  <?php if ($memberGone): ?>
    <div class="gone-notice">
      <b>탈퇴한 회원입니다.</b>
      이 아이디(<code><?=h($uid)?></code>)는 삭제되어 확인할 자료가 없습니다.
      아래 요청 기록은 참고용으로만 남아 있습니다.
    </div>
  <?php endif; ?>

  <section class="card">
    <h2>검토요청 <?=count($requests)?>건</h2>
    <?php if (!$requests): ?>
      <div class="empty">
        <?= $memberGone ? '해당 아이디가 삭제되어, 확인요청이 없습니다.' : '검토요청이 없습니다.' ?>
      </div>
    <?php endif; ?>
    <?php foreach ($requests as $request): $pending = ($request['status'] ?? 'pending') !== 'resolved'; ?>
      <div class="request">
        <div class="meta">
          <?=h($request['at'] ?? '')?>
          <span class="badge <?=$pending ? 'pending' : 'resolved'?>"><?=$pending ? '확인 필요' : '확인 완료'?></span>
        </div>
        <div class="question"><?=h($request['text'] ?? '')?></div>
        <?php if ($pending): ?>
          <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
            <input type="hidden" name="act" value="resolve">
            <input type="hidden" name="uid" value="<?=h($uid)?>">
            <input type="hidden" name="request_id" value="<?=h($request['id'] ?? '')?>">
            <button class="btn" type="submit">확인 완료 처리</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2 id="building">건물 기본정보 수정<?=review_flag($reviewBy['building'])?></h2>
    <?=review_notes($reviewBy['building'])?>
    <?php
      $bi = array_merge([
        'name'=>'','use'=>'','grade'=>'','address'=>'','rep'=>'','tel'=>'',
        'floor_b'=>'','floor_a'=>'','area_t'=>'','area_f'=>'','dongsu'=>'',
        'mgrs'=>[],'wd_day'=>'','wd_night'=>'','hd_day'=>'','hd_night'=>''
      ], $buildingInfo);
      $mgrs = is_array($bi['mgrs']) ? $bi['mgrs'] : [];
    ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
      <input type="hidden" name="act" value="save_building">
      <input type="hidden" name="uid" value="<?=h($uid)?>">
      <div class="edit-grid">
        <div class="field"><label>대상명</label><input name="name" value="<?=h($bi['name'])?>"></div>
        <div class="field"><label>용도</label><input name="use" value="<?=h($bi['use'])?>"></div>
        <div class="field"><label>등급</label><select name="grade"><option value="">선택</option><?php foreach (['특급','1급','2급','3급'] as $grade): ?><option value="<?=$grade?>" <?=$bi['grade']===$grade?'selected':''?>><?=$grade?></option><?php endforeach; ?></select></div>
        <div class="field wide"><label>소재지</label><input name="address" value="<?=h($bi['address'])?>"></div>
        <div class="field"><label>대표자</label><input name="rep" value="<?=h($bi['rep'])?>"></div>
        <div class="field"><label>전화번호</label><input name="tel" value="<?=h($bi['tel'])?>"></div>
        <div class="field"><label>동수</label><input name="dongsu" value="<?=h($bi['dongsu'])?>"></div>
        <div class="field"><label>지하층</label><input name="floor_b" value="<?=h($bi['floor_b'])?>"></div>
        <div class="field"><label>지상층</label><input name="floor_a" value="<?=h($bi['floor_a'])?>"></div>
        <div class="field"><label>연면적(㎡)</label><input name="area_t" value="<?=h($bi['area_t'])?>"></div>
        <div class="field"><label>바닥면적(㎡)</label><input name="area_f" value="<?=h($bi['area_f'])?>"></div>
        <div class="field"><label>평일 주간 인원</label><input name="wd_day" value="<?=h($bi['wd_day'])?>"></div>
        <div class="field"><label>평일 야간 인원</label><input name="wd_night" value="<?=h($bi['wd_night'])?>"></div>
        <div class="field"><label>휴일 주간 인원</label><input name="hd_day" value="<?=h($bi['hd_day'])?>"></div>
        <div class="field"><label>휴일 야간 인원</label><input name="hd_night" value="<?=h($bi['hd_night'])?>"></div>
      </div>
      <div class="mgr-scroll">
        <table class="mgr-table">
          <thead><tr><th>소방안전관리자</th><th>선임일자</th><th>보유자격</th><th>구분</th><th>연락처</th></tr></thead>
          <tbody>
          <?php for ($i=0; $i<4; $i++): $manager = $mgrs[$i] ?? []; ?>
            <tr>
              <td><input name="m_name[]" value="<?=h($manager['name'] ?? '')?>"></td>
              <td><input name="m_appt[]" value="<?=h($manager['appt'] ?? '')?>" placeholder="2026-01-01"></td>
              <td><input name="m_qual[]" value="<?=h($manager['qual'] ?? '')?>"></td>
              <td><select name="m_type[]"><option value="">선택</option><option value="주" <?=($manager['type']??'')==='주'?'selected':''?>>주</option><option value="보조" <?=($manager['type']??'')==='보조'?'selected':''?>>보조</option></select></td>
              <td><input name="m_tel[]" value="<?=h($manager['tel'] ?? '')?>"></td>
            </tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <div class="actions"><button class="btn btn--primary" type="submit">기본정보 저장</button></div>
    </form>
  </section>

  <section class="card">
    <h2 id="plan">소방계획서 <?=count($plans)?>개<?=review_flag($reviewBy['plan'])?><a class="goinline" href="/impersonate.php?uid=<?=urlencode($uid)?>&amp;to=%2Ffire_plan.php">회원 화면에서 고치기 ↗</a></h2>
    <?php if (!$plans): ?><div class="empty">저장된 소방계획서가 없습니다.</div><?php endif; ?>
    <?php foreach ($plans as $plan):
      /* 15개 항목 중 몇 개나 채웠는지 */
      $sec = (array)($plan['sections'] ?? []);
      $filled = 0;
      foreach ($sec as $sv) {
        if (is_array($sv)) { if ($sv) $filled++; }
        elseif (trim((string)$sv) !== '') $filled++;
      }
      $statusKo = ['draft'=>'작성 중','done'=>'작성 완료','complete'=>'작성 완료'][(string)($plan['status'] ?? '')] ?? '';
    ?>
      <details class="plan" id="plan-<?=h((string)($plan['id'] ?? ''))?>"
               <?= (count($plans) === 1 || $reviewBy['plan']) ? 'open' : '' ?>>
        <summary>
          <?=h($plan['building_name'] ?: '(대상명 미입력)')?>
          <span class="badge <?= $filled >= 15 ? 'resolved' : 'pending' ?>" style="margin-left:7px">
            15개 항목 중 <?=$filled?>개 작성
          </span>
          <span class="plan-meta">
            <?= $statusKo !== '' ? h($statusKo) . ' · ' : '' ?>
            마지막 수정 <?=h($plan['updated_at'] ?? '-')?>
          </span>
        </summary>

        <?php
          /* 화면에 보여줄 순서: 개요 먼저, 그다음 15개 항목 */
          $head = [];
          foreach (['building_name','plan_date','usage_code','status','created_at','updated_at'] as $k) {
            if (array_key_exists($k, $plan)) $head[$k] = $plan[$k];
          }
        ?>
        <?=render_value($head)?>

        <h3 style="font-size:14px;margin:18px 0 8px">작성 내용</h3>
        <?php
          $allItems = ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15'];
          foreach ($allItems as $no):
            $cur   = $sec[$no] ?? '';
            $mine  = array_values(array_filter($reviewBy['plan'], fn($r) => (string)$r['_item'] === $no));
            $blank = is_array($cur) ? !$cur : trim((string)$cur) === '';
            if ($blank && !$mine) continue;         // 비었고 질문도 없으면 굳이 보여주지 않습니다
        ?>
          <div class="secbox <?= $mine ? 'is-asked' : '' ?>">
            <div class="secbox__h">
              <span class="secbox__t"><?=h(fp_label($no))?></span>
              <?php if ($mine): ?><span class="badge pending">확인요청</span><?php endif; ?>
              <?php if ($blank): ?><span class="badge" style="background:#f1f5f9;color:#64748b">미작성</span><?php endif; ?>
            </div>
            <?=review_notes($mine)?>
            <?php if (!$blank): ?><?=render_value($cur, $no)?><?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php
          $shown = 0;
          foreach ($allItems as $no) { $c = $sec[$no] ?? ''; if (is_array($c) ? $c : trim((string)$c) !== '') $shown++; }
        ?>
        <?php if ($shown === 0): ?>
          <div class="empty" style="padding:16px">아직 아무 항목도 작성하지 않았습니다.</div>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2 id="jawi">자위소방대 편성표 <?=count($jawis)?>개<?=review_flag($reviewBy['jawi'])?><a class="goinline" href="/impersonate.php?uid=<?=urlencode($uid)?>&amp;to=%2Ffire_plan_jawi.php">회원 화면에서 고치기 ↗</a></h2>
    <p class="lead" style="margin:0 0 10px">소방계획서와 별도로 저장되는 별지 제13호서식입니다.</p>
    <?php if (!$jawis): ?><div class="empty">작성된 편성표가 없습니다.</div><?php endif; ?>
    <?php foreach ($jawis as $jw): ?>
      <details class="plan">
        <summary>
          <?=h($jw['site_name'] ?? '(대상명 미입력)')?>
          <span class="plan-meta">
            <?= isset($jw['total']) ? h((string)$jw['total']) . '명 · ' : '' ?>
            <?=h($jw['saved'] ?? $jw['created'] ?? '')?>
          </span>
        </summary>
        <?=render_value($jw)?>
      </details>
    <?php endforeach; ?>
  </section>

  <!-- 업무수행 기록표 -->
  <section class="card">
    <h2 id="worklog">업무수행 기록표 <?=count($workLogs)?>개월<?=review_flag($reviewBy['worklog'])?><a class="goinline" href="/impersonate.php?uid=<?=urlencode($uid)?>&amp;to=%2Fwork_log.php">회원 화면에서 고치기 ↗</a></h2>
    <p class="lead" style="margin:0 0 10px">별지 제12호서식 · 월 1회 이상 작성</p>
    <?=review_notes($reviewBy['worklog'])?>

    <?php if ($workFixed): ?>
      <details class="plan">
        <summary>매월 반복되는 기본값
          <span class="plan-meta">상호·등급·확인내용 기본값</span></summary>
        <?=render_value($workFixed)?>
      </details>
    <?php endif; ?>

    <?php if (!$workLogs): ?>
      <div class="empty">작성된 월별 기록이 없습니다.</div>
    <?php else: foreach ($workLogs as $month => $rec):
      $ymd = explode('-', (string)$month);
      $label = count($ymd) === 2 ? ($ymd[0] . '년 ' . (int)$ymd[1] . '월') : $month;
      /* 네 항목 중 몇 개나 채웠는지 */
      $done = 0;
      foreach (['sobang','pinan','hwagi','etc'] as $k) {
        $c = $rec[$k] ?? null;
        if (is_array($c) && trim((string)($c['note'] ?? '')) !== '') $done++;
      }
    ?>
      <details class="plan" <?= $reviewBy['worklog'] ? 'open' : '' ?>>
        <summary><?=h($label)?>
          <span class="badge <?= $done >= 4 ? 'resolved' : 'pending' ?>" style="margin-left:7px">
            4개 항목 중 <?=$done?>개 작성
          </span>
          <span class="plan-meta">
            <?= !empty($rec['date']) ? '수행일자 ' . h((string)$rec['date']) : '수행일자 미입력' ?>
            <?= !empty($rec['edited_by']) ? ' · 관리자가 고침' : '' ?>
          </span>
        </summary>

        <?=render_value($rec)?>
      </details>
    <?php endforeach; endif; ?>
  </section>

  <!-- 소방훈련·교육 기록부 -->
  <section class="card">
    <h2 id="train">소방훈련·교육 기록부 <?=count($trains)?>건<?=review_flag($reviewBy['train'])?><a class="goinline" href="/impersonate.php?uid=<?=urlencode($uid)?>&amp;to=%2Ftrain.php">회원 화면에서 고치기 ↗</a></h2>
    <p class="lead" style="margin:0 0 10px">별지 제28호서식 · 연 1회 이상 실시 · 실시일부터 2년 보관</p>

    <?php if (!$trains): ?>
      <div class="empty">작성된 훈련·교육 기록이 없습니다.</div>
    <?php else: foreach ($trains as $tr):
      $td = $tr['data'] ?? $tr;      // 저장 구조가 data 안에 들어 있는 경우 대응
      $types = $td['fire_types'] ?? [];
      $photoN = 0;
      foreach ((array)($td['photos'] ?? []) as $pv) { if (trim((string)$pv) !== '') $photoN++; }
    ?>
      <details class="plan">
        <summary>
          <?= !empty($td['fire_date']) ? h((string)$td['fire_date']) : '(일시 미입력)' ?>
          <?php if ($types): ?>
            <span class="badge resolved" style="margin-left:7px"><?=h(implode('·', (array)$types))?></span>
          <?php endif; ?>
          <span class="plan-meta">
            <?= !empty($td['t_name']) ? h((string)$td['t_name']) . ' · ' : '' ?>
            참석 <?=h((string)($td['fire_join'] ?? '-'))?>명
            <?= $photoN ? ' · 사진 ' . $photoN . '장' : ' · 사진 없음' ?>
          </span>
        </summary>

        <?php if ($photoN): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">
            <?php foreach ((array)($td['photos'] ?? []) as $slot => $pv):
              if (trim((string)$pv) === '') continue; ?>
              <a href="/train_photo.php?uid=<?=urlencode($uid)?>&f=<?=urlencode(basename((string)$pv))?>" target="_blank">
                <img src="/train_photo.php?uid=<?=urlencode($uid)?>&f=<?=urlencode(basename((string)$pv))?>"
                     alt="<?=h((string)$slot)?>" style="width:130px;height:98px;object-fit:cover;
                     border:1px solid var(--bd);border-radius:8px">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?=render_value(array_filter($td, fn($k) => $k !== '_file' && $k !== 'photos', ARRAY_FILTER_USE_KEY))?>
      </details>
    <?php endforeach; endif; ?>
  </section>
</main>
</body>
</html>
