<?php
// dashboard.php — 지도+달력 메인 + 마을/퀘스트 관리 + 날짜 코멘트
// 일정 타입: visit / inspect / as(카운트) / report / submit
declare(strict_types=1);

/* ── 카카오맵 JavaScript 키 (주소→좌표 변환용) ──
   카카오 콘솔 > 앱 > 앱 키 > JavaScript 키를 넣으세요.
   ※ REST API 키가 아니라 JavaScript 키입니다. */
const KAKAO_JS_KEY = 'b00cf3b12ed8cfeaefbf6d37101d8d81';

/* ── 세션/보안 ── */
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

/* ── 헬퍼 ── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool {
  return is_admin() || !empty($_SESSION['is_user']);
}
/* 현재 사용자의 데이터 폴더 경로.
   - 카카오 로그인 사용자: data/users/u_{카카오ID}/  (각자 분리)
   - 관리자(mhg1234 직접 로그인, 카카오ID 없음): 기존 data/ 폴더 그대로 사용 */
function user_data_dir(): string {
  $base = __DIR__.'/data';
  if (!empty($_SESSION['kakao_id'])) {
    $uid = 'u_' . preg_replace('/[^0-9A-Za-z]/', '', (string)$_SESSION['kakao_id']);
    return $base.'/users/'.$uid;
  }
  if (!empty($_SESSION['member_id'])) {
    $mid = 'm_' . preg_replace('/[^0-9A-Za-z_]/', '', (string)$_SESSION['member_id']);
    return $base.'/users/'.$mid;
  }
  return $base;   // 관리자: 예전 데이터가 있던 그 폴더
}
function uuidv4(): string {
  $d = random_bytes(16); $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function read_json(string $file): array {
  if (!file_exists($file)) return [];
  $r = @file_get_contents($file); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function write_json(string $file, array $arr): bool {
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
  $tmp = $file.'.tmp';
  file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  return @rename($tmp, $file);
}

/* ── 접근 제한 ── */
if (!is_logged_in()) { http_response_code(403);
  echo "<!doctype html><meta charset='utf-8'><body style='background:#f5f7fb;color:#1a2436;font-family:Arial'>로그인이 필요한 구역입니다. <a href='/index.php' style='color:#0891b2'>로그인하러 가기</a></body>"; exit;
}

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 데이터 (관리자는 기존 data/, 카카오 사용자는 data/users/ 분리) ── */
$DATA_DIR = user_data_dir();
$CLIENTS_FILE   = $DATA_DIR.'/clients.json';
$TASKS_FILE     = $DATA_DIR.'/tasks.json';
$DAY_FILE       = $DATA_DIR.'/daynotes.json';
$BUILDINGS_FILE = $DATA_DIR.'/buildings.json';
$QMEMO_FILE     = $DATA_DIR.'/quickmemo.json';
$COLORS_FILE    = $DATA_DIR.'/day_colors.json';
if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0775, true);
$qmemo = read_json($QMEMO_FILE);
if (!file_exists($CLIENTS_FILE))   write_json($CLIENTS_FILE,   []);
if (!file_exists($TASKS_FILE))     write_json($TASKS_FILE,     []);
if (!file_exists($DAY_FILE))       write_json($DAY_FILE,       []);
if (!file_exists($BUILDINGS_FILE)) file_put_contents($BUILDINGS_FILE, '{}');
if (!file_exists($COLORS_FILE))   write_json($COLORS_FILE, []);
$clients   = read_json($CLIENTS_FILE);
$tasks     = read_json($TASKS_FILE);
$daynotes  = read_json($DAY_FILE);
$day_colors = read_json($COLORS_FILE);

/* 공사사진 표시 — data 폴더 직접 URL 대신 로그인 검증 후 파일을 전달한다.
   기존 clients.json의 data/.../photos/파일명 경로도 JS에서 이 주소로 변환한다. */
if (isset($_GET['photo_file'])) {
  $fname = basename((string)$_GET['photo_file']);
  if ($fname === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$/i', $fname)) {
    http_response_code(400); exit;
  }
  $file = $DATA_DIR . '/photos/' . $fname;
  if (!is_file($file)) { http_response_code(404); exit; }
  $mime = function_exists('mime_content_type') ? (string)@mime_content_type($file) : '';
  if (!str_starts_with($mime, 'image/')) {
    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
    $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
             'gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'application/octet-stream';
  }
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . (string)filesize($file));
  header('Cache-Control: private, max-age=3600');
  readfile($file); exit;
}

/* ── 사용자 설정(상호명 등) ── */
$SETTINGS_FILE = $DATA_DIR.'/settings.json';
if (!file_exists($SETTINGS_FILE)) write_json($SETTINGS_FILE, []);
$SETTINGS = read_json($SETTINGS_FILE);
/* 회사명: 사용자가 설정한 값이 있으면 그것, 없으면 일반 기본값 */
$COMPANY_NAME = trim((string)($SETTINGS['company'] ?? '')) !== ''
  ? trim((string)$SETTINGS['company'])
  : '거래처 관리 시스템';
// buildings는 객체형(associative array) — client_id가 키
$_braw = @file_get_contents($BUILDINGS_FILE);
$buildings = ($_braw && trim($_braw)!=='') ? (json_decode($_braw, true) ?? []) : [];

/* ── 파라미터 ── */
$ym = preg_replace('/[^0-9\-]/', '', (string)($_GET['m'] ?? ''));
if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) $ym = date('Y-m');
$monthStart = $ym.'-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$allowedTypes = ['visit','inspect','as','report','submit','plan'];
$type = (string)($_GET['type'] ?? 'visit');
if (!in_array($type, $allowedTypes, true)) $type = 'visit';

/* ── 데이터 정규화 ── */
foreach ($clients as &$c) {
  $c['id']      = (string)($c['id'] ?? uuidv4());
  $c['name']    = (string)($c['name'] ?? '');
  if (!isset($c['addr']) && isset($c['address'])) $c['addr'] = (string)$c['address'];
  if (!isset($c['address']) && isset($c['addr'])) $c['address'] = (string)$c['addr'];
  foreach (['visits','inspects','reports','submits','plans'] as $k) if (!isset($c[$k]) || !is_array($c[$k])) $c[$k] = [];
  if (!isset($c['as']) || !is_array($c['as'])) $c['as'] = [];
  if (!array_key_exists('lat',$c)) $c['lat'] = null;
  if (!array_key_exists('lng',$c)) $c['lng'] = null;
  if (!isset($c['comments']) || !is_array($c['comments'])) $c['comments'] = [];
  if (!isset($c['dday'])) $c['dday'] = null; // D-DAY 날짜
}
unset($c);
write_json($CLIENTS_FILE, $clients);

/* ── POST 액션 ── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $act  = $_POST['action'] ?? '';
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($CSRF, (string)$csrf)) { http_response_code(400); exit('CSRF 검증 실패'); }

  // 빠른 메모 (AJAX — JSON 응답)
  if ($act === 'qmemo_save') {
    $memo = ['text' => (string)($_POST['text'] ?? ''), 'updated' => date('Y-m-d H:i')];
    $ok = write_json($QMEMO_FILE, $memo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'updated' => $memo['updated']], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 퀘스트
  if ($act === 'task_create') {
    $title = trim($_POST['title'] ?? ''); $due = trim($_POST['due'] ?? '');
    if ($title !== '') {
      array_unshift($tasks, ['id'=>uuidv4(),'title'=>$title,'due'=>$due,'done'=>false,'created'=>date('Y-m-d H:i:s')]);
      write_json($TASKS_FILE, $tasks);
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
  if ($act === 'task_toggle') {
    $id = (string)($_POST['id'] ?? '');
    $tasks = read_json($TASKS_FILE);
    foreach ($tasks as &$t) if (($t['id'] ?? '') === $id) {
      $t['done'] = !empty($t['done']) ? false : true;
      if ($t['done']) $t['done_at'] = date('Y-m-d H:i:s'); else unset($t['done_at']);
      break;
    }
    unset($t); write_json($TASKS_FILE, $tasks);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
  if ($act === 'task_delete') {
    $id = (string)($_POST['id'] ?? '');
    $tasks = array_values(array_filter(read_json($TASKS_FILE), fn($t)=> ($t['id'] ?? '') !== $id));
    write_json($TASKS_FILE, $tasks);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
  if ($act === 'task_clear_done') {
    $tasks = array_values(array_filter(read_json($TASKS_FILE), fn($t)=> empty($t['done'])));
    write_json($TASKS_FILE, $tasks);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // 마을 생성/수정/삭제/코멘트
  if ($act === 'client_create') {
    $name = trim($_POST['name'] ?? '');
    if ($name==='') { header('Location: '.$_SERVER['REQUEST_URI'].'&err=noname'); exit; }
    // 한 계정당 거래처 200개 제한
    $existing = read_json($CLIENTS_FILE);
    if (count($existing) >= 200) {
      header('Location: '.$_SERVER['REQUEST_URI'].'&err=limit'); exit;
    }
    $item = [
      'id'=>uuidv4(),
      'name'=>$name,
      'address'=>trim($_POST['address'] ?? ''),
      'addr'=>trim($_POST['address'] ?? ''),
      'phone'=>trim($_POST['phone'] ?? ''),
      'birth'=>trim($_POST['birth'] ?? ''),
      'safety_name'=>trim($_POST['safety_name'] ?? ''),
      'safety_birth'=>trim($_POST['safety_birth'] ?? ''),
      'lat'=>($_POST['lat'] ?? '')!=='' ? (float)$_POST['lat'] : null,
      'lng'=>($_POST['lng'] ?? '')!=='' ? (float)$_POST['lng'] : null,
      'created'=>date('Y-m-d H:i:s'),
      'visits'=>[], 'inspects'=>[], 'as'=>[], 'reports'=>[], 'submits'=>[], 'plans'=>[],
      'comments'=>[]
    ];
    $note0 = trim($_POST['note'] ?? '');
    if ($note0!=='') $item['comments'][] = ['cid'=>uuidv4(),'at'=>date('Y-m-d H:i:s'),'by'=>'admin','text'=>$note0];
    $clients = read_json($CLIENTS_FILE); array_unshift($clients, $item); write_json($CLIENTS_FILE, $clients);
    header('Location: '.$_SERVER['REQUEST_URI'].'#'.$item['id']); exit;
  }
  if ($act === 'client_edit') {
    $id = (string)($_POST['id'] ?? '');
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') === $id) {
        $c['name'] = trim($_POST['name'] ?? $c['name']);
        $c['address'] = $c['addr'] = trim($_POST['address'] ?? ($c['address'] ?? ''));
        $c['phone'] = trim($_POST['phone'] ?? ($c['phone'] ?? ''));
        $c['birth'] = trim($_POST['birth'] ?? ($c['birth'] ?? ''));
        $c['safety_name']  = trim($_POST['safety_name'] ?? ($c['safety_name'] ?? ''));
        $c['safety_birth'] = trim($_POST['safety_birth'] ?? ($c['safety_birth'] ?? ''));
        $lat = $_POST['lat'] ?? ''; $lng = $_POST['lng'] ?? '';
        $c['lat'] = ($lat!=='') ? (float)$lat : null;
        $c['lng'] = ($lng!=='') ? (float)$lng : null;
        // 커스텀 필드 저장
        $ef_keys   = (array)($_POST['ef_key']   ?? []);
        $ef_values = (array)($_POST['ef_value'] ?? []);
        $extra = [];
        for ($ei=0; $ei<count($ef_keys); $ei++) {
          $k2 = trim($ef_keys[$ei] ?? '');
          $v2 = trim($ef_values[$ei] ?? '');
          if ($k2 !== '') $extra[] = ['key'=>$k2, 'value'=>$v2];
        }
        $c['extra_fields'] = $extra;
        break;
      }
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    header('Location: '.$_SERVER['REQUEST_URI'].'#'.$id); exit;
  }
  if ($act === 'client_delete') {
    $id = (string)($_POST['id'] ?? '');
    $clients = array_values(array_filter(read_json($CLIENTS_FILE), fn($c)=> ($c['id'] ?? '') !== $id));
    write_json($CLIENTS_FILE, $clients);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
  if ($act === 'comment_add') {
    $id = (string)($_POST['id'] ?? ''); $text = trim($_POST['text'] ?? '');
    if ($id!=='' && $text!=='') {
      $clients = read_json($CLIENTS_FILE);
      foreach ($clients as &$c) if (($c['id'] ?? '')===$id) { $c['comments'][]=['cid'=>uuidv4(),'at'=>date('Y-m-d H:i:s'),'by'=>'admin','text'=>$text]; break; }
      unset($c); write_json($CLIENTS_FILE, $clients);
    }
    header('Location: '.$_SERVER['REQUEST_URI'].'#'.$id); exit;
  }
  if ($act === 'comment_delete') {
    $id = (string)($_POST['id'] ?? ''); $cid=(string)($_POST['cid'] ?? '');
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) if (($c['id'] ?? '')===$id) {
      $c['comments'] = array_values(array_filter(($c['comments'] ?? []), fn($cm)=> ($cm['cid'] ?? '') !== $cid));
      break;
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    header('Location: '.$_SERVER['REQUEST_URI'].'#'.$id); exit;
  }

  /* ── D-DAY 설정/해제 (AJAX JSON 응답) ── */
  // 거래처 메모 저장 (AJAX)
  if ($act === 'client_memo') {
    $id   = (string)($_POST['id'] ?? '');
    $memo = trim((string)($_POST['memo'] ?? ''));
    $memo = mb_substr($memo, 0, 2000);
    $flag = !empty($_POST['flag']) ? 1 : 0;      // 중요 표시
    $clients = read_json($CLIENTS_FILE);
    $found = false;
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') === $id) {
        $c['memo']    = $memo;
        $c['memo_at'] = $memo === '' ? '' : date('Y-m-d H:i');
        $c['flag']    = $flag;
        $found = true; break;
      }
    }
    unset($c);
    if ($found) write_json($CLIENTS_FILE, $clients);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$found,'memo'=>$memo,'flag'=>$flag,
                      'memo_at'=>$memo===''?'':date('Y-m-d H:i')], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($act === 'client_dday') {
    $id   = (string)($_POST['id'] ?? '');
    $date = trim($_POST['dday'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = null;
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') === $id) { $c['dday'] = $date; break; }
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'dday'=>$date]); exit;
  }

  // ★ 날짜 색상/라벨 저장
  if ($act === 'day_color_save') {
    $d = preg_replace('/[^0-9\-]/','',(string)($_POST['date'] ?? ''));
    if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d)) {
      $day_colors = read_json($COLORS_FILE);
      $color = preg_replace('/[^#a-fA-F0-9]/','',(string)($_POST['color'] ?? ''));
      $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 20);
      if ($color === '' && $label === '') unset($day_colors[$d]);
      else $day_colors[$d] = ['color'=>$color ?: '#3b82f6', 'label'=>$label];
      write_json($COLORS_FILE, $day_colors);
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // ★ 날짜 코멘트 추가/삭제
  if ($act === 'daynote_add') {
    $d = trim((string)($_POST['date'] ?? '')); $text = trim((string)($_POST['text'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $text !== '') {
      $daynotes = read_json($DAY_FILE);
      if (!isset($daynotes[$d]) || !is_array($daynotes[$d])) $daynotes[$d] = [];
      array_unshift($daynotes[$d], ['nid'=>uuidv4(),'at'=>date('Y-m-d H:i:s'),'by'=>'admin','text'=>$text]);
      write_json($DAY_FILE, $daynotes);
    }
    header('Location: '.$_SERVER['REQUEST_URI'].'#dn-'.$d); exit;
  }
  if ($act === 'daynote_delete') {
    $d = trim((string)($_POST['date'] ?? '')); $nid = (string)($_POST['nid'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $nid !== '') {
      $daynotes = read_json($DAY_FILE);
      if (!empty($daynotes[$d]) && is_array($daynotes[$d])) {
        $daynotes[$d] = array_values(array_filter($daynotes[$d], fn($x)=> ($x['nid'] ?? '') !== $nid));
        if (count($daynotes[$d]) === 0) unset($daynotes[$d]);
        write_json($DAY_FILE, $daynotes);
      }
    }
    header('Location: '.$_SERVER['REQUEST_URI'].'#dn-'.$d); exit;
  }

  // ★ 건물/설비 저장
  if ($act === 'building_save') {
    $cid = (string)($_POST['client_id'] ?? '');
    if ($cid !== '') {
      $_braw2 = @file_get_contents($BUILDINGS_FILE);
      $buildings = ($_braw2 && trim($_braw2)!=='') ? (json_decode($_braw2, true) ?? []) : [];
      $fb = (int)($_POST['floors_below'] ?? 0);
      $fa = (int)($_POST['floors_above'] ?? 1);
      $floor_data = [];
      $posted_floors = (array)($_POST['floor'] ?? []);
      foreach ($posted_floors as $flabel => $equips) {
        $flabel = (string)$flabel;
        if (!is_array($equips)) continue;
        $floor_data[$flabel] = array_values(array_filter(array_map('trim', $equips), fn($e)=>$e!==''));
      }
      $custom_equips = (array)($_POST['custom_equips'] ?? []);
      $buildings[$cid] = [
        'floors_below'  => max(0, $fb),
        'floors_above'  => max(1, $fa),
        'floor_data'    => $floor_data,
        'custom_equips' => array_values(array_filter(array_map('trim', $custom_equips), fn($e)=>$e!=='')),
        'updated'       => date('Y-m-d H:i:s'),
      ];
      $tmp = $BUILDINGS_FILE.'.tmp';
      file_put_contents($tmp, json_encode($buildings, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
      @rename($tmp, $BUILDINGS_FILE);
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // ★ 아이템 가방 — 파일 업로드
  if ($act === 'qf_upload') {
    header('Content-Type: application/json');
    $QF_DIR = $DATA_DIR . '/quickfiles';
    if (!is_dir($QF_DIR)) @mkdir($QF_DIR, 0775, true);

    if (empty($_FILES['qf_file']) || $_FILES['qf_file']['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['ok'=>false,'msg'=>'업로드 오류: '.($_FILES['qf_file']['error'] ?? '파일 없음')]); exit;
    }
    $orig = basename($_FILES['qf_file']['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    // 허용 확장자
    $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','hwp','hwpx','png','jpg','jpeg','gif'];
    if (!in_array($ext, $allowed, true)) {
      echo json_encode(['ok'=>false,'msg'=>'허용되지 않는 파일 형식: '.$ext]); exit;
    }
    // 파일 크기 제한 50MB
    if ($_FILES['qf_file']['size'] > 50 * 1024 * 1024) {
      echo json_encode(['ok'=>false,'msg'=>'파일 크기가 50MB를 초과합니다.']); exit;
    }
    // 고유 파일명
    $safe = preg_replace('/[^a-zA-Z0-9가-힣\._\-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = date('Ymd_His') . '_' . $safe . '.' . $ext;
    $dest  = $QF_DIR . '/' . $fname;
    if (!move_uploaded_file($_FILES['qf_file']['tmp_name'], $dest)) {
      echo json_encode(['ok'=>false,'msg'=>'파일 저장 실패']); exit;
    }
    // 웹 접근 경로 (실제 저장 폴더 기준 — 계정별 폴더 대응)
    $webPath = str_replace(__DIR__ . '/', '', $QF_DIR) . '/' . $fname;
    echo json_encode(['ok'=>true,'url'=>$webPath,'name'=>$orig]); exit;
  }

  // ★ 공사사진 보고서 — 이미지 업로드 (AJAX)
  if ($act === 'photo_upload') {
    header('Content-Type: application/json');
    $PHOTO_DIR = $DATA_DIR . '/photos';
    if (!is_dir($PHOTO_DIR)) @mkdir($PHOTO_DIR, 0775, true);
    if (empty($_FILES['photo_file']) || $_FILES['photo_file']['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['ok'=>false,'msg'=>'업로드 오류: '.($_FILES['photo_file']['error'] ?? '파일 없음')]); exit;
    }
    $ext = strtolower(pathinfo(basename($_FILES['photo_file']['name']), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
      echo json_encode(['ok'=>false,'msg'=>'이미지 파일만 허용됩니다.']); exit;
    }
    if ($_FILES['photo_file']['size'] > 20 * 1024 * 1024) {
      echo json_encode(['ok'=>false,'msg'=>'파일 크기가 20MB를 초과합니다.']); exit;
    }
    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = $PHOTO_DIR . '/' . $fname;
    if (!move_uploaded_file($_FILES['photo_file']['tmp_name'], $dest)) {
      echo json_encode(['ok'=>false,'msg'=>'파일 저장 실패']); exit;
    }
    // data 폴더 직접 접근 대신 로그인 검증을 거치는 사진 주소를 반환
    $photoUrl = '/clients_mini.php?photo_file=' . rawurlencode($fname);
    echo json_encode(['ok'=>true,'url'=>$photoUrl,'fname'=>$fname]); exit;
  }

  // ★ 공사사진 보고서 — 보고서 저장
  if ($act === 'photo_report_save') {
    header('Content-Type: application/json');
    $cid = (string)($_POST['client_id'] ?? '');
    $title = mb_substr(trim($_POST['report_title'] ?? ''), 0, 60);
    if ($cid === '' || $title === '') { echo json_encode(['ok'=>false,'msg'=>'필수 값 누락']); exit; }
    $note = mb_substr(trim($_POST['note'] ?? ''), 0, 300);
    // pairs: [{before_url, before_caption, after_url, after_caption, gongong}]
    $pairs_raw = (array)($_POST['pairs'] ?? []);
    $pairs = [];
    foreach ($pairs_raw as $p) {
      if (!is_array($p)) continue;
      $beforeUrl = trim((string)($p['before_url'] ?? ''));
      $afterUrl  = trim((string)($p['after_url'] ?? ''));
      if ($beforeUrl === '' && $afterUrl === '') continue;
      $pairs[] = [
        'before_url'     => $beforeUrl,
        'before_caption' => mb_substr(trim($p['before_caption'] ?? ''), 0, 40),
        'after_url'      => $afterUrl,
        'after_caption'  => mb_substr(trim($p['after_caption'] ?? ''), 0, 40),
        'gongong'        => mb_substr(trim($p['gongong'] ?? ''), 0, 60),
      ];
    }
    if (!$pairs) { echo json_encode(['ok'=>false,'msg'=>'사진을 한 장 이상 올려주세요.']); exit; }
    $report = [
      'rid'    => uuidv4(),
      'title'  => $title,
      'date'   => date('Y-m-d'),
      'note'   => $note,
      'pairs'  => $pairs,
      'created'=> date('Y-m-d H:i:s'),
    ];
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') === $cid) {
        if (!isset($c['photo_reports']) || !is_array($c['photo_reports'])) $c['photo_reports'] = [];
        array_unshift($c['photo_reports'], $report);
        break;
      }
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    echo json_encode(['ok'=>true,'rid'=>$report['rid']]); exit;
  }

  // ★ 공사사진 보고서 — 보고서 삭제
  if ($act === 'photo_report_delete') {
    header('Content-Type: application/json');
    $cid = (string)($_POST['client_id'] ?? '');
    $rid = (string)($_POST['rid'] ?? '');
    if ($cid === '' || $rid === '') { echo json_encode(['ok'=>false]); exit; }
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') === $cid) {
        $c['photo_reports'] = array_values(array_filter($c['photo_reports'] ?? [], fn($r)=> ($r['rid']??'') !== $rid));
        break;
      }
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    echo json_encode(['ok'=>true]); exit;
  }

  // ★ 견적서 — 공종 단가표 저장 (AJAX)
  if ($act === 'estimate_save_chips') {
    header('Content-Type: application/json');
    $arr = json_decode((string)($_POST['chips'] ?? '[]'), true);
    if (!is_array($arr)) { echo json_encode(['ok'=>false,'msg'=>'형식 오류']); exit; }
    file_put_contents($DATA_DIR.'/estimate_chips.json', json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($act === 'estimate_load_chips') {
    header('Content-Type: application/json');
    $f = $DATA_DIR.'/estimate_chips.json';
    $arr = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    echo json_encode(['ok'=>true,'chips'=> is_array($arr) ? $arr : null]); exit;
  }
  if ($act === 'estimate_save_supplier') {
    header('Content-Type: application/json');
    $obj = json_decode((string)($_POST['supplier'] ?? '{}'), true);
    if (!is_array($obj)) { echo json_encode(['ok'=>false]); exit; }
    file_put_contents($DATA_DIR.'/estimate_supplier.json', json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($act === 'estimate_load_supplier') {
    header('Content-Type: application/json');
    $f = $DATA_DIR.'/estimate_supplier.json';
    $obj = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    echo json_encode(['ok'=>true,'supplier'=> is_array($obj) ? $obj : null]); exit;
  }

  // ★ 마을 문서고 — 파일 업로드 (AJAX)
  if ($act === 'cf_upload') {
    header('Content-Type: application/json');
    $cid = (string)($_POST['client_id'] ?? '');
    if ($cid === '' || !preg_match('/^[a-f0-9\-]{8,}$/i', $cid)) {
      echo json_encode(['ok'=>false,'msg'=>'잘못된 마을 ID']); exit;
    }
    $CF_DIR = $DATA_DIR . '/clientfiles/' . $cid;
    if (!is_dir($CF_DIR)) @mkdir($CF_DIR, 0775, true);
    if (empty($_FILES['cf_file']) || $_FILES['cf_file']['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['ok'=>false,'msg'=>'업로드 오류: '.($_FILES['cf_file']['error'] ?? '파일 없음')]); exit;
    }
    if ($_FILES['cf_file']['size'] > 50 * 1024 * 1024) {
      echo json_encode(['ok'=>false,'msg'=>'50MB 초과']); exit;
    }
    $orig = basename($_FILES['cf_file']['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','hwp','hwpx','jpg','jpeg','png','gif','webp','zip','txt','csv'];
    if (!in_array($ext, $allowed, true)) {
      echo json_encode(['ok'=>false,'msg'=>'허용되지 않는 파일 형식: '.$ext]); exit;
    }
    $safe  = preg_replace('/[^a-zA-Z0-9가-힣\._\-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe . '.' . $ext;
    $dest  = $CF_DIR . '/' . $fname;
    if (!move_uploaded_file($_FILES['cf_file']['tmp_name'], $dest)) {
      echo json_encode(['ok'=>false,'msg'=>'파일 저장 실패']); exit;
    }
    $relDir = str_replace(__DIR__ . '/', '', $CF_DIR);   // 계정별 폴더 대응
    echo json_encode(['ok'=>true,'url'=>$relDir.'/'.$fname,'name'=>$orig,'fname'=>$fname,'size'=>$_FILES['cf_file']['size']]); exit;
  }

  // ★ 마을 문서고 — 파일 삭제 (AJAX)
  if ($act === 'cf_delete') {
    header('Content-Type: application/json');
    $cid   = (string)($_POST['client_id'] ?? '');
    $fname = basename((string)($_POST['fname'] ?? ''));
    if ($cid === '' || $fname === '' || !preg_match('/^[a-f0-9\-]{8,}$/i', $cid)) {
      echo json_encode(['ok'=>false,'msg'=>'잘못된 요청']); exit;
    }
    $path = $DATA_DIR . '/clientfiles/' . $cid . '/' . $fname;
    $base = realpath($DATA_DIR . '/clientfiles/' . $cid);
    $real = realpath($path);
    if ($real && $base && strpos($real, $base) === 0) @unlink($real);
    echo json_encode(['ok'=>true]); exit;
  }

  // ★ 마을 문서고 — 파일 목록 조회 (AJAX)
  if ($act === 'cf_list') {
    header('Content-Type: application/json');
    $cid = (string)($_POST['client_id'] ?? '');
    if ($cid === '' || !preg_match('/^[a-f0-9\-]{8,}$/i', $cid)) {
      echo json_encode(['ok'=>false,'files'=>[]]); exit;
    }
    $CF_DIR = $DATA_DIR . '/clientfiles/' . $cid;
    $CF_REL = str_replace(__DIR__ . '/', '', $CF_DIR);   // 계정별 폴더 대응
    $files = [];
    if (is_dir($CF_DIR)) {
      foreach (scandir($CF_DIR) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = $CF_DIR . '/' . $f;
        if (!is_file($fp)) continue;
        // 원본 파일명 추출 (Ymd_His_hex_원본.ext 패턴)
        $display = preg_replace('/^\d{8}_\d{6}_[a-f0-9]{6}_/', '', $f);
        $files[] = [
          'fname'   => $f,
          'display' => $display,
          'url'     => $CF_REL.'/'.$f,
          'size'    => filesize($fp),
          'mtime'   => filemtime($fp),
          'ext'     => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
        ];
      }
      usort($files, fn($a,$b)=> $b['mtime'] <=> $a['mtime']);
    }
    echo json_encode(['ok'=>true,'files'=>$files]); exit;
  }

  // ★ 아이템 가방 — 파일 삭제 (서버 파일)
  if ($act === 'qf_delete_file') {
    header('Content-Type: application/json');
    $fname = basename((string)($_POST['fname'] ?? ''));
    if ($fname !== '') {
      $path = $DATA_DIR . '/quickfiles/' . $fname;
      if (file_exists($path) && strpos(realpath($path), realpath($DATA_DIR.'/quickfiles')) === 0) {
        @unlink($path);
      }
    }
    echo json_encode(['ok'=>true]); exit;
  }

  // ★ 자위소방대 편성표 — 저장 (독립 저장소 fireplans.json, 신규/갱신)
  if ($act === 'fire_save') {
    header('Content-Type: application/json');
    $FIRE_FILE = $DATA_DIR.'/fireplans.json';
    $payload_raw = (string)($_POST['payload'] ?? '');
    $data = json_decode($payload_raw, true);
    if (!is_array($data)) { echo json_encode(['ok'=>false,'msg'=>'데이터 형식 오류']); exit; }
    $pid = trim((string)($_POST['plan_id'] ?? ''));   // 있으면 갱신, 없으면 신규
    $clean = [
      'site_name'  => mb_substr(trim($data['site_name'] ?? ''), 0, 80),
      'work_type'  => mb_substr(trim($data['work_type'] ?? ''), 0, 40),
      'cmd'        => array_map(fn($v)=>mb_substr((string)$v,0,200), (array)($data['cmd'] ?? [])),
      'deputy'     => array_map(fn($v)=>mb_substr((string)$v,0,200), (array)($data['deputy'] ?? [])),
      'groups'     => [],
      'photos'     => array_slice(array_map(fn($v)=>mb_substr((string)$v,0,300), (array)($data['photos'] ?? [])), 0, 2),
      'photo_caps' => array_slice(array_map(fn($v)=>mb_substr((string)$v,0,80),  (array)($data['photo_caps'] ?? [])), 0, 2),
      'edu_title'  => mb_substr(trim($data['edu_title'] ?? ''), 0, 80),
      'edu_date'   => mb_substr(trim($data['edu_date'] ?? ''), 0, 20),
    ];
    foreach ((array)($data['groups'] ?? []) as $g) {
      if (!is_array($g)) continue;
      $members = [];
      foreach ((array)($g['members'] ?? []) as $m) {
        if (!is_array($m)) continue;
        $members[] = [
          'name' => mb_substr(trim($m['name'] ?? ''), 0, 30),
          'tel'  => mb_substr(trim($m['tel']  ?? ''), 0, 30),
          'task' => mb_substr(trim($m['task'] ?? ''), 0, 120),
        ];
      }
      $clean['groups'][] = [
        'name'    => mb_substr(trim($g['name'] ?? ''), 0, 30),
        'members' => $members,
      ];
    }
    $plans = read_json($FIRE_FILE);
    $now = date('Y-m-d H:i:s');
    $updated = false;
    if ($pid !== '') {
      foreach ($plans as &$p) {
        if (($p['id'] ?? '') === $pid) {
          $clean['id'] = $pid;
          $clean['created'] = $p['created'] ?? $now;
          $clean['saved'] = $now;
          $p = $clean; $updated = true; break;
        }
      }
      unset($p);
    }
    if (!$updated) {
      $pid = uuidv4();
      $clean['id'] = $pid;
      $clean['created'] = $now;
      $clean['saved'] = $now;
      array_unshift($plans, $clean);
    }
    if (count($plans) > 300) $plans = array_slice($plans, 0, 300);
    write_json($FIRE_FILE, $plans);
    echo json_encode(['ok'=>true,'id'=>$pid,'saved'=>$now,'updated'=>$updated]); exit;
  }

  // ★ 편성표 — 목록
  if ($act === 'fire_list') {
    header('Content-Type: application/json');
    $FIRE_FILE = $DATA_DIR.'/fireplans.json';
    $plans = read_json($FIRE_FILE);
    $list = array_map(function($p){
      $cnt = 0;
      foreach (($p['groups'] ?? []) as $g) $cnt += count($g['members'] ?? []);
      $cnt += 2; // 대장+부대장
      return [
        'id'        => $p['id'] ?? '',
        'site_name' => $p['site_name'] ?? '',
        'total'     => $cnt,
        'saved'     => $p['saved'] ?? '',
      ];
    }, $plans);
    echo json_encode(['ok'=>true,'list'=>$list]); exit;
  }

  // ★ 편성표 — 1개 불러오기
  if ($act === 'fire_load') {
    header('Content-Type: application/json');
    $FIRE_FILE = $DATA_DIR.'/fireplans.json';
    $pid = (string)($_POST['plan_id'] ?? '');
    $plans = read_json($FIRE_FILE);
    foreach ($plans as $p) {
      if (($p['id'] ?? '') === $pid) { echo json_encode(['ok'=>true,'plan'=>$p]); exit; }
    }
    echo json_encode(['ok'=>true,'plan'=>null]); exit;
  }

  // ★ 편성표 — 삭제
  if ($act === 'fire_delete') {
    header('Content-Type: application/json');
    $FIRE_FILE = $DATA_DIR.'/fireplans.json';
    $pid = (string)($_POST['plan_id'] ?? '');
    $plans = read_json($FIRE_FILE);
    $plans = array_values(array_filter($plans, fn($p)=> ($p['id'] ?? '') !== $pid));
    write_json($FIRE_FILE, $plans);
    echo json_encode(['ok'=>true]); exit;
  }

  // ★ 편성표 — 복제
  if ($act === 'fire_dup') {
    header('Content-Type: application/json');
    $FIRE_FILE = $DATA_DIR.'/fireplans.json';
    $pid = (string)($_POST['plan_id'] ?? '');
    $plans = read_json($FIRE_FILE);
    foreach ($plans as $p) {
      if (($p['id'] ?? '') === $pid) {
        $copy = $p;
        $copy['id'] = uuidv4();
        $copy['site_name'] = mb_substr(($p['site_name'] ?? '').' (사본)', 0, 80);
        $copy['created'] = $copy['saved'] = date('Y-m-d H:i:s');
        array_unshift($plans, $copy);
        write_json($FIRE_FILE, $plans);
        echo json_encode(['ok'=>true,'id'=>$copy['id']]); exit;
      }
    }
    echo json_encode(['ok'=>false,'msg'=>'원본을 찾을 수 없습니다.']); exit;
  }

  // ★ 사용자 설정 저장 (상호명 등)
  if ($act === 'settings_save') {
    header('Content-Type: application/json');
    $company = mb_substr(trim($_POST['company'] ?? ''), 0, 60);
    $s = read_json($SETTINGS_FILE);
    $s['company'] = $company;
    $s['updated'] = date('Y-m-d H:i:s');
    write_json($SETTINGS_FILE, $s);
    echo json_encode(['ok'=>true,'company'=>$company]); exit;
  }

  // ★ 구독 신청 접수 (공용 저장소: __DIR__/data/subscriptions.json)
  if ($act === 'sub_apply') {
    header('Content-Type: application/json');
    $SUB_FILE = __DIR__.'/data/subscriptions.json';
    if (!is_dir(dirname($SUB_FILE))) @mkdir(dirname($SUB_FILE), 0775, true);
    $plan    = mb_substr(trim($_POST['plan'] ?? ''), 0, 40);
    $company = mb_substr(trim($_POST['company'] ?? ''), 0, 80);
    $contact = mb_substr(trim($_POST['contact'] ?? ''), 0, 40);
    $phone   = mb_substr(trim($_POST['phone'] ?? ''), 0, 30);
    $email   = mb_substr(trim($_POST['email'] ?? ''), 0, 80);
    $memo    = mb_substr(trim($_POST['memo'] ?? ''), 0, 300);
    if ($plan === '' || $company === '' || $phone === '') {
      echo json_encode(['ok'=>false,'msg'=>'요금제·사업장명·연락처는 필수입니다.']); exit;
    }
    // 신청자 식별 (로그인 정보)
    $who = '';
    if (!empty($_SESSION['kakao_id']))      $who = 'kakao:'.$_SESSION['kakao_id'];
    elseif (!empty($_SESSION['member_id'])) $who = 'member:'.$_SESSION['member_id'];
    elseif (is_admin())                     $who = 'admin';
    $item = [
      'id'      => uuidv4(),
      'plan'    => $plan,
      'company' => $company,
      'contact' => $contact,
      'phone'   => $phone,
      'email'   => $email,
      'memo'    => $memo,
      'who'     => $who,
      'status'  => 'requested',           // requested → paid → active 등 나중에 관리
      'pay_method' => '',                 // 결제수단 자리 (미정)
      'applied' => date('Y-m-d H:i:s'),
    ];
    $subs = read_json($SUB_FILE);
    array_unshift($subs, $item);
    if (count($subs) > 1000) $subs = array_slice($subs, 0, 1000);
    write_json($SUB_FILE, $subs);
    echo json_encode(['ok'=>true,'id'=>$item['id']]); exit;
  }

  // ★ 구독 신청 목록 (관리자 전용)
  if ($act === 'sub_list') {
    header('Content-Type: application/json');
    if (!is_admin()) { echo json_encode(['ok'=>false,'msg'=>'관리자만 조회할 수 있습니다.']); exit; }
    $SUB_FILE = __DIR__.'/data/subscriptions.json';
    echo json_encode(['ok'=>true,'list'=>read_json($SUB_FILE)]); exit;
  }

  // ★ 구독 신청 상태 변경 / 삭제 (관리자 전용) — 결제관리 기초
  if ($act === 'sub_update') {
    header('Content-Type: application/json');
    if (!is_admin()) { echo json_encode(['ok'=>false,'msg'=>'권한 없음']); exit; }
    $SUB_FILE = __DIR__.'/data/subscriptions.json';
    $sid = (string)($_POST['sub_id'] ?? '');
    $newStatus = mb_substr(trim($_POST['status'] ?? ''), 0, 20);
    $payMethod = mb_substr(trim($_POST['pay_method'] ?? ''), 0, 40);
    $subs = read_json($SUB_FILE);
    foreach ($subs as &$s) {
      if (($s['id'] ?? '') === $sid) {
        if ($newStatus !== '') $s['status'] = $newStatus;
        if (isset($_POST['pay_method'])) $s['pay_method'] = $payMethod;
        $s['updated'] = date('Y-m-d H:i:s');
        break;
      }
    }
    unset($s);
    write_json($SUB_FILE, $subs);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($act === 'sub_delete') {
    header('Content-Type: application/json');
    if (!is_admin()) { echo json_encode(['ok'=>false,'msg'=>'권한 없음']); exit; }
    $SUB_FILE = __DIR__.'/data/subscriptions.json';
    $sid = (string)($_POST['sub_id'] ?? '');
    $subs = array_values(array_filter(read_json($SUB_FILE), fn($s)=> ($s['id'] ?? '') !== $sid));
    write_json($SUB_FILE, $subs);
    echo json_encode(['ok'=>true]); exit;
  }

  // 일정 등록/삭제
  if (in_array($act, ['add','remove','inc','dec','set','toggle'], true)) {
    $pid   = (string)($_POST['id'] ?? '');
    $pdate = trim((string)($_POST['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $pdate)) $pdate = date('Y-m-d');
    $k     = (string)($_POST['kind'] ?? 'visit');
    if (!in_array($k, $allowedTypes, true)) $k = 'visit';

    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      if (($c['id'] ?? '') !== $pid) continue;

      if ($k==='visit'||$k==='inspect'||$k==='report'||$k==='submit'||$k==='plan') {
        $map = ['visit'=>'visits','inspect'=>'inspects','report'=>'reports','submit'=>'submits','plan'=>'plans'];
        $key = $map[$k]; if (!isset($c[$key])||!is_array($c[$key])) $c[$key]=[];
        if     ($act==='add')    { if(!in_array($pdate,$c[$key],true)) $c[$key][]=$pdate; }
        elseif ($act==='remove') { $c[$key] = array_values(array_filter($c[$key], fn($d)=> $d!==$pdate)); }
        elseif ($act==='toggle') { if(in_array($pdate,$c[$key],true)) $c[$key]=array_values(array_filter($c[$key],fn($d)=>$d!==$pdate)); else $c[$key][]=$pdate; }
      } else { // as
        if (!isset($c['as'])||!is_array($c['as'])) $c['as']=[];
        if     ($act==='inc') { $c['as'][$pdate] = max(1,(int)($c['as'][$pdate]??0)+1); }
        elseif ($act==='dec') { $n=(int)($c['as'][$pdate]??0)-1; if($n<=0) unset($c['as'][$pdate]); else $c['as'][$pdate]=$n; }
        elseif ($act==='set' || $act==='add') {
          $cnt = max(0,(int)($_POST['count'] ?? 1));
          if($cnt<=0) unset($c['as'][$pdate]); else $c['as'][$pdate]=$cnt;
        } elseif ($act==='remove') { unset($c['as'][$pdate]); }
      }
      break;
    }
    unset($c); write_json($CLIENTS_FILE, $clients);
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // ── 달력관리: 특정 날짜의 모든 일정 삭제 (모든 거래처, 모든 타입) ──
  if ($act === 'cal_clear_day') {
    $pdate = trim((string)($_POST['date'] ?? ''));
    if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $pdate)) { http_response_code(400); exit('bad date'); }
    $removed = 0;
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      foreach (['visits','inspects','reports','submits','plans'] as $key) {
        if (!empty($c[$key]) && is_array($c[$key])) {
          $before = count($c[$key]);
          $c[$key] = array_values(array_filter($c[$key], fn($d)=> $d !== $pdate));
          $removed += $before - count($c[$key]);
        }
      }
      if (isset($c['as'][$pdate])) { unset($c['as'][$pdate]); $removed++; }
      if (isset($c['auto_visits']) && is_array($c['auto_visits'])) {
        $c['auto_visits'] = array_values(array_filter($c['auto_visits'], fn($d)=> $d !== $pdate));
      }
    }
    unset($c);
    write_json($CLIENTS_FILE, $clients);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true,'removed'=>$removed]); exit;
  }

  // ── 달력관리: 이번 달 전체 일정 삭제 ──
  if ($act === 'cal_clear_month') {
    $ym2 = preg_replace('/[^0-9\-]/', '', (string)($_POST['ym'] ?? ''));
    if (!preg_match('/^\d{4}\-\d{2}$/', $ym2)) { http_response_code(400); exit('bad ym'); }
    $removed = 0;
    $clients = read_json($CLIENTS_FILE);
    foreach ($clients as &$c) {
      foreach (['visits','inspects','reports','submits','plans','auto_visits'] as $key) {
        if (!empty($c[$key]) && is_array($c[$key])) {
          $before = count($c[$key]);
          $c[$key] = array_values(array_filter($c[$key], fn($d)=> substr((string)$d,0,7) !== $ym2));
          if ($key !== 'auto_visits') $removed += $before - count($c[$key]);
        }
      }
      if (!empty($c['as']) && is_array($c['as'])) {
        foreach (array_keys($c['as']) as $d) {
          if (substr((string)$d,0,7) === $ym2) { unset($c['as'][$d]); $removed++; }
        }
      }
    }
    unset($c);
    write_json($CLIENTS_FILE, $clients);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true,'removed'=>$removed]); exit;
  }

  // ── 달력관리: 특정 날짜에 거래처들 방문 일괄 배정 ──
  if ($act === 'cal_assign_day') {
    $pdate = trim((string)($_POST['date'] ?? ''));
    if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $pdate)) { http_response_code(400); exit('bad date'); }
    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
    $added = 0;
    if (is_array($ids) && $ids) {
      $clients = read_json($CLIENTS_FILE);
      $idx = [];
      foreach ($clients as $i=>$c) { $idx[(string)($c['id']??'')] = $i; }
      foreach ($ids as $cid) {
        $cid = (string)$cid;
        if (!isset($idx[$cid])) continue;
        $i = $idx[$cid];
        if (!isset($clients[$i]['visits']) || !is_array($clients[$i]['visits'])) $clients[$i]['visits'] = [];
        if (!in_array($pdate, $clients[$i]['visits'], true)) { $clients[$i]['visits'][] = $pdate; $added++; }
      }
      if ($added > 0) write_json($CLIENTS_FILE, $clients);
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true,'added'=>$added]); exit;
  }
}

/* ── 뷰 데이터 ── */
function monthDays(string $ym): array { $s=$ym.'-01'; $e=date('Y-m-t',strtotime($s)); $o=[]; for($d=$s;$d<=$e;$d=date('Y-m-d',strtotime("$d +1 day"))) $o[]=$d; return $o; }
$days = monthDays($ym);

/* 달력/이벤트 집계 + 이번 달 방문/점검 존재 맵 */
$eventsByDate = [];
$visitedThisMonth = [];
$inspectedThisMonth = [];
$plannedThisMonth = [];
$activeThisMonth = []; // 이번 달 어떤 이벤트라도 있는 마을
foreach ($clients as $c) {
  $cid=(string)($c['id']??''); $name=(string)($c['name']??'');
  foreach ((array)($c['visits']??[]) as $d) {
    if (substr((string)$d,0,7)===$ym) {
      $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'visit','count'=>1];
      $visitedThisMonth[$cid]=true; $activeThisMonth[$cid]=true;
    }
  }
  foreach ((array)($c['inspects']??[]) as $d) {
    if (substr((string)$d,0,7)===$ym) {
      $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'inspect','count'=>1];
      $inspectedThisMonth[$cid]=true; $activeThisMonth[$cid]=true;
    }
  }
  foreach ((array)($c['as']??[]) as $d=>$cnt) {
    if (substr((string)$d,0,7)===$ym) { $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'as','count'=>max(1,(int)$cnt)]; $activeThisMonth[$cid]=true; }
  }
  foreach ((array)($c['reports']??[]) as $d) {
    if (substr((string)$d,0,7)===$ym) { $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'report','count'=>1]; $activeThisMonth[$cid]=true; }
  }
  foreach ((array)($c['submits']??[]) as $d) {
    if (substr((string)$d,0,7)===$ym) { $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'submit','count'=>1]; $activeThisMonth[$cid]=true; }
  }
  foreach ((array)($c['plans']??[]) as $d) {
    if (substr((string)$d,0,7)===$ym) {
      $eventsByDate[$d][]=['id'=>$cid,'name'=>$name,'kind'=>'plan','count'=>1];
      $plannedThisMonth[$cid]=true; $activeThisMonth[$cid]=true;
    }
  }
}

/* 이번 달 미등록 마을 (가나다순) */
$inactiveClients = array_values(array_filter($clients, fn($c)=> !isset($activeThisMonth[(string)($c['id']??'')])));
usort($inactiveClients, fn($a,$b)=> mb_strtolower($a['name']??'') <=> mb_strtolower($b['name']??''));

/* 전체 거래처 (가나다순) — 패널의 '전체' 탭용 */
$allClientsSorted = $clients;
usort($allClientsSorted, fn($a,$b)=> mb_strtolower($a['name']??'') <=> mb_strtolower($b['name']??''));

/* D-DAY 설정된 마을 — D-Day 순서 (오늘→가까운미래→먼미래→지난순) */
$today = date('Y-m-d');
$ddayClients = [];
foreach ($clients as $c) {
  if (!empty($c['dday']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['dday'])) {
    $diff = (int)round((strtotime($c['dday']) - strtotime($today)) / 86400);
    $ddayClients[] = [
      'id'   => (string)($c['id']??''),
      'name' => (string)($c['name']??''),
      'addr' => (string)($c['address']??$c['addr']??''),
      'dday' => $c['dday'],
      'diff' => $diff,
    ];
  }
}
// 정렬: 오늘(0) → 가까운 미래(+1,+2…) → 지난 날짜(-1,-2…)
usort($ddayClients, function($a,$b) {
  $da = $a['diff']; $db = $b['diff'];
  // 미래(>=0) 먼저, 그 다음 과거
  $af = $da >= 0; $bf = $db >= 0;
  if ($af !== $bf) return $af ? -1 : 1;
  return $da <=> $db;
});

/* 좌표 없는 유령 마을 */
$ghostClients = [];
foreach ($clients as $c) {
  if ($c['lat']===null || $c['lng']===null || $c['lat']==='' || $c['lng']==='') {
    $cid = (string)($c['id']??'');
    // 총 이벤트 수 계산
    $totalEvents = count($c['visits']??[]) + count($c['inspects']??[]) + count($c['reports']??[])
                 + count($c['submits']??[]) + count($c['plans']??[]) + count($c['as']??[]);
    $lastEvent = '';
    $allDates = array_merge(
      array_values($c['visits']??[]), array_values($c['inspects']??[]),
      array_values($c['reports']??[]), array_values($c['submits']??[]),
      array_values($c['plans']??[]), array_keys($c['as']??[])
    );
    if ($allDates) { rsort($allDates); $lastEvent = $allDates[0]; }
    $ghostClients[] = [
      'id'   => $cid,
      'name' => (string)($c['name']??''),
      'addr' => (string)($c['address']??$c['addr']??''),
      'phone'=> (string)($c['phone']??''),
      'total'=> $totalEvents,
      'last' => $lastEvent,
      'created' => (string)($c['created']??''),
    ];
  }
}
usort($ghostClients, fn($a,$b)=> mb_strtolower($a['name']) <=> mb_strtolower($b['name']));
$prevYm = date('Y-m', strtotime($monthStart.' -1 month'));
$nextYm = date('Y-m', strtotime($monthStart.' +1 month'));

/* ── 전월 이벤트 집계 ── */
$prevMonthEvents = [];
$typeLabels = ['visit'=>'방문','inspect'=>'점검','as'=>'AS','report'=>'보고서','submit'=>'이행완료','plan'=>'방문예정'];
foreach ($clients as $c) {
  $cid=(string)($c['id']??''); $name=(string)($c['name']??''); $addr=(string)($c['address']??$c['addr']??'');
  $rows = [];
  foreach (['visit'=>'visits','inspect'=>'inspects','report'=>'reports','submit'=>'submits','plan'=>'plans'] as $kind=>$key) {
    foreach ((array)($c[$key]??[]) as $d) {
      if (substr((string)$d,0,7)===$prevYm) $rows[]=['date'=>$d,'kind'=>$kind,'count'=>1];
    }
  }
  foreach ((array)($c['as']??[]) as $d=>$cnt) {
    if (substr((string)$d,0,7)===$prevYm) $rows[]=['date'=>$d,'kind'=>'as','count'=>(int)$cnt];
  }
  if ($rows) {
    usort($rows, fn($a,$b)=>strcmp($a['date'],$b['date']));
    $prevMonthEvents[]=['id'=>$cid,'name'=>$name,'addr'=>$addr,'rows'=>$rows];
  }
}
usort($prevMonthEvents, fn($a,$b)=>strcmp($a['name'],$b['name']));
$tasks_view = $tasks;
usort($tasks_view, function($a,$b){
  $ad=!empty($a['done']); $bd=!empty($b['done']); if($ad!==$bd) return $ad<=>$bd;
  $da=$a['due']??''; $db=$b['due']??''; if($da===$db) return 0; if($da===''||$db==='') return $da===''?1:-1; return strcmp($da,$db);
});
$task_total=count($tasks); $task_open=0; foreach($tasks as $t) if(empty($t['done'])) $task_open++;
/* ── 자위소방대 편성표 전용 페이지 ── */
if (($_GET['view'] ?? '') === 'fire') {
  require __DIR__.'/fire_page.php';
  exit;
}

/* ── 구독/결제 페이지 ── */
if (($_GET['view'] ?? '') === 'subscribe') {
  require __DIR__.'/subscribe_page.php';
  exit;
}





?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($COMPANY_NAME)?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
@font-face {
  font-family: 'DungGeunMo';
  src: url('https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_2007@1.1/DungGeunMo.woff') format('woff');
  font-weight: normal; font-display: swap;
}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<style>
/* ═══════════════════════════════════════════════════
   거래처 관리 시스템 — Premium Dark Theme
   ═══════════════════════════════════════════════════ */

:root {
  /* ── Background ── */
  --bg:   #f5f7fb;
  --bg2:  #eef2f8;
  --bg3:  #e8edf5;
  --card: #ffffff;
  --card2:#f8fafc;

  /* ── Border ── */
  --bd:  #e3e8f0;
  --bd2: #d4dbe6;
  --bd3: #c3ccdb;

  /* ── Typography ── */
  --fg:  #1a2436;
  --fg2: #3a4658;
  --mut: #6a7689;
  --sub: #9aa6b8;

  /* ── Accent ── */
  --accent:     #0891b2;
  --accent-dim: #e0f2fe;
  --accent-glow:rgba(8,145,178,.12);
  --link:       #0e7490;

  /* ── Status ── */
  --visit:    #15803d; --visit-bg: #ecfdf3;   --visit-bd:  #bbf7d0;
  --inspect:  #dc2626; --inspect-bg:#fef2f2;  --inspect-bd:#fecaca;
  --as:       #b45309; --as-bg:    #fffbeb;   --as-bd:     #fde68a;
  --report:   #2563eb; --report-bg:#eff6ff;   --report-bd: #bfdbfe;
  --submit:   #c2410c; --submit-bg:#fff7ed;   --submit-bd: #fed7aa;
  --plan:     #7c3aed; --plan-bg:  #f5f3ff;   --plan-bd:   #ddd6fe;

  /* ── Geometry ── */
  --r-xs:  6px;
  --r-sm:  9px;
  --r-md: 13px;
  --r-lg: 17px;
  --r-xl: 22px;

  /* ── Elevation ── */
  --shadow-xs: 0 1px 4px rgba(20,40,80,.05);
  --shadow-sm: 0 2px 10px rgba(20,40,80,.06);
  --shadow-md: 0 6px 24px rgba(20,40,80,.08);
  --shadow-lg: 0 12px 40px rgba(20,40,80,.12);
  --shadow-accent: 0 4px 24px rgba(8,145,178,.18);

  /* ── Transition ── */
  --t-fast: 120ms ease;
  --t-mid:  200ms ease;
  --t-slow: 320ms ease;
}

/* ══ Reset ══ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ══ Scrollbar ══ */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--bd3); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--mut); }

/* ══ Base ══ */
body {
  background: var(--bg);
  background-image:
    radial-gradient(ellipse 80% 60% at 20% -10%, rgba(8,145,178,.05), transparent),
    radial-gradient(ellipse 60% 40% at 80% 100%, rgba(8,145,178,.04), transparent);
  color: var(--fg);
  font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  line-height: 1.55;
  min-height: 100vh;
}
a { color: var(--link); text-decoration: none; }

/* ══════════════ HEADER ══════════════ */
.new-header {
  position: sticky; top: 0; z-index: 200;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(16px) saturate(1.4);
  -webkit-backdrop-filter: blur(16px) saturate(1.4);
  border-bottom: 1px solid var(--bd);
  box-shadow: 0 1px 0 rgba(255,255,255,.5), 0 4px 16px rgba(20,40,80,.06);
}
.nh-row1 {
  max-width: 1480px; margin: 0 auto;
  padding: 12px 24px;
  display: flex; align-items: center; gap: 16px;
  border-bottom: 1px solid var(--bd);
}
.nh-brand {
  display: flex; align-items: center; gap: 10px;
  font-weight: 700; font-size: 15px; color: var(--fg);
  letter-spacing: -.01em; white-space: nowrap;
}
.nh-icon {
  width: 32px; height: 32px; border-radius: 9px;
  background: linear-gradient(135deg, #0891b2, #0e7490);
  border: 1px solid var(--bd2);
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(8,145,178,.2);
}
.nh-kpis { display: flex; gap: 0; align-items: stretch; margin-left: auto; }
.nh-kpi {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 4px 18px; border-radius: 0;
  background: transparent; border: 0; border-right: 1px solid var(--bd);
  min-width: 64px; transition: background var(--t-fast);
}
.nh-kpis .nh-kpi:first-child { border-left: 1px solid var(--bd); }
.nh-kpi:hover { background: var(--card2); }
.nh-kpi-l { font-size: 10px; color: var(--mut); font-weight: 500; letter-spacing: .04em; }
.nh-kpi-v { font-size: 14px; font-weight: 700; color: var(--fg); margin-top: 2px; font-family: 'JetBrains Mono', monospace; }
.nh-home {
  margin-left: 14px;
  padding: 6px 16px; border-radius: var(--r-sm);
  border: 1px solid var(--bd); background: var(--card);
  color: var(--mut); font-size: 12px; font-weight: 500;
  white-space: nowrap; transition: all var(--t-fast);
}
.nh-home:hover { border-color: var(--accent); color: var(--fg); background: var(--card2); }

.nh-row2 {
  max-width: 1480px; margin: 0 auto;
  padding: 10px 24px;
  display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.nh-vdiv { width: 1px; height: 20px; background: var(--bd); flex-shrink: 0; }
.nh-months { display: flex; gap: 3px; }
.nh-mbtn {
  padding: 5px 13px; border-radius: var(--r-sm); font-size: 12px; font-weight: 500;
  border: 1px solid var(--bd); color: var(--mut); background: transparent;
  text-decoration: none; transition: all var(--t-fast);
}
.nh-mbtn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-dim); }
.nh-mbtn.on {
  background: var(--accent); border-color: var(--accent); color: #fff;
  font-weight: 600; box-shadow: 0 2px 10px rgba(8,145,178,.25);
}
.nh-mbtn--accent { border-color: var(--accent); color: var(--accent); background: var(--accent-dim); font-weight: 600; }
.nh-mbtn--accent:hover { background: var(--accent); color: #fff; }

.nh-pills { display: flex; gap: 4px; flex-wrap: wrap; }
.nh-pill {
  padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 500;
  border: 1px solid var(--bd); color: var(--mut);
  text-decoration: none; transition: all var(--t-fast);
}
.nh-pill:hover { border-color: var(--bd3); color: var(--fg2); }
.nh-pill.on                   { background: var(--visit-bg);   border-color: var(--visit-bd);   color: var(--visit);   font-weight: 600; }
.nh-pill[href*="inspect"].on  { background: var(--inspect-bg); border-color: var(--inspect-bd); color: var(--inspect); }
.nh-pill[href*="as"].on       { background: var(--as-bg);      border-color: var(--as-bd);      color: var(--as); }
.nh-pill[href*="report"].on   { background: var(--report-bg);  border-color: var(--report-bd);  color: var(--report); }
.nh-pill[href*="submit"].on   { background: var(--submit-bg);  border-color: var(--submit-bd);  color: var(--submit); }
.nh-pill[href*="plan"].on     { background: var(--plan-bg);    border-color: var(--plan-bd);    color: var(--plan); }

/* ══════════════ UTIL ══════════════ */
.pill {
  display: inline-flex; align-items: center; gap: 6px;
  border: 1px solid var(--bd2); padding: 5px 10px; border-radius: 999px;
}
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--accent); color: #fff;
  padding: 9px 16px; border-radius: var(--r-sm);
  border: 1px solid transparent; cursor: pointer;
  font-size: 13px; font-weight: 600; font-family: inherit;
  transition: all var(--t-fast);
}
.btn:hover { background: #2d71e0; box-shadow: var(--shadow-accent); }
.btn:active { transform: translateY(1px); }
.btn.ghost {
  background: transparent; color: var(--fg2);
  border: 1px solid var(--bd);
}
.btn.ghost:hover { border-color: var(--bd3); color: var(--fg); background: rgba(20,40,80,.04); }
.btn.warn { background: #c0392b; border-color: #c0392b; }
.btn.warn:hover { background: #e74c3c; }

.badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 9px; border-radius: 999px; font-size: 11px;
  background: var(--card2); border: 1px solid var(--bd2); color: var(--fg);
}
.tabs a {
  padding: 7px 14px; border-radius: 999px;
  border: 1px solid var(--bd2); color: var(--fg);
  text-decoration: none; opacity: .85;
  transition: all var(--t-fast);
}
.tabs a.active { background: var(--card2); border-color: var(--bd3); opacity: 1; }

/* ══════════════ LAYOUT ══════════════ */
.main { max-width: 1480px; margin: 20px auto; padding: 0 24px; }
.main-cols {
  display: grid;
  grid-template-columns: 260px 1fr 280px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 1240px) { .main-cols { grid-template-columns: 240px 1fr; } .inactive-panel { display:none; } }
@media (max-width: 900px)  { .main-cols { grid-template-columns: 1fr; } .dday-panel { display:none; } }

/* ══════════════ DDAY PANEL ══════════════ */
.dday-panel {
  background: var(--card);
  border: 1px solid var(--bd);
  border-radius: var(--r-lg);
  overflow: hidden;
  position: sticky;
  top: 80px;
  max-height: calc(100vh - 100px);
  display: flex;
  flex-direction: column;
}
.dday-panel-head {
  padding: 13px 16px;
  border-bottom: 1px solid var(--bd);
  background: var(--card2);
  flex-shrink: 0;
}
.dday-panel-head h3 {
  font-size: 11px; font-weight: 700; color: var(--mut);
  letter-spacing:.06em; text-transform:uppercase; margin-bottom:3px;
}
.dday-panel-list { overflow-y: auto; flex: 1; }

.dd-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px;
  border-bottom: 1px solid rgba(20,40,80,.06);
  cursor: pointer;
  transition: background var(--t-fast);
}
.dd-row:hover { background: var(--accent-glow); }
.dd-row:last-child { border-bottom: none; }

.dd-badge {
  flex-shrink: 0; min-width: 52px; text-align: center;
  padding: 4px 8px; border-radius: 8px;
  font-size: 12px; font-weight: 800; letter-spacing: .02em;
  line-height: 1.3;
}
.dd-badge.today  { background:#ff2d55;  color:#fff; }
.dd-badge.soon   { background:#cc5500;  color:#ffe0cc; border:1px solid #ff7c38; }
.dd-badge.future { background:var(--report-bg);  color:var(--report); border:1px solid var(--report-bd); }
.dd-badge.past   { background:var(--bg2);  color:var(--mut); border:1px solid var(--bd); }

.dd-info { min-width: 0; flex: 1; }
.dd-name {
  font-size: 12px; font-weight: 600; color: var(--fg2);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dd-date { font-size: 10px; color: var(--sub); margin-top: 2px; }

.dd-edit {
  flex-shrink:0; padding:3px 7px; border-radius:5px;
  border:1px solid var(--bd2); background:transparent; color:var(--mut);
  cursor:pointer; font-size:11px; font-family:inherit;
  transition:all var(--t-fast);
}
.dd-edit:hover { border-color:var(--accent); color:var(--accent); }

.dd-empty {
  padding: 32px 14px; text-align: center;
  font-size: 12px; color: var(--sub); line-height: 1.8;
}
.inactive-panel {
  background: var(--card);
  border: 1px solid var(--bd);
  border-radius: var(--r-lg);
  overflow: hidden;
  position: sticky;
  top: 80px;
  max-height: calc(100vh - 100px);
  display: flex;
  flex-direction: column;
}
.inactive-panel-head {
  padding: 13px 16px;
  border-bottom: 1px solid var(--bd);
  background: var(--card2);
  flex-shrink: 0;
}
.inactive-panel-head h3 {
  font-size: 11px; font-weight: 700; color: var(--mut);
  letter-spacing: .06em; text-transform: uppercase; margin-bottom: 3px;
}
.inactive-panel-head .ip-meta {
  font-size: 11px; color: var(--sub);
}
.inactive-panel-search {
  padding: 8px 10px;
  border-bottom: 1px solid var(--bd);
  flex-shrink: 0;
}
/* 미등록 / 전체 탭 */
.ip-tabs {
  display: flex; gap: 4px; padding: 8px 10px 0;
  flex-shrink: 0;
}
.ip-tab {
  flex: 1; padding: 7px 8px; cursor: pointer;
  background: var(--bg3); color: var(--sub);
  border: 1px solid var(--bd); border-radius: var(--r-sm);
  font-size: 12px; font-weight: 600; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 5px;
  transition: .12s;
}
.ip-tab:hover { border-color: var(--accent); color: var(--fg); }
.ip-tab.active {
  background: #eef4ff; border-color: var(--accent); color: #1d4ed8;
}
.ip-tab-n {
  font-size: 10px; padding: 1px 5px; border-radius: 999px;
  background: rgba(0,0,0,.06); color: inherit;
}
.ip-tab.active .ip-tab-n { background: rgba(37,99,235,.15); }
/* 전체 목록에서 등록 완료 표시 */
.ip-row.ip-done { opacity: .62; }
.ip-check {
  display: inline-block; color: #16a34a; font-weight: 800; margin-right: 3px;
}
.inactive-panel-search input {
  width: 100%; padding: 6px 10px;
  background: var(--bg3); color: var(--fg);
  border: 1px solid var(--bd); border-radius: var(--r-sm);
  font-size: 12px; outline: none;
}
.inactive-panel-search input:focus { border-color: var(--accent); }
.inactive-panel-list {
  overflow-y: auto;
  flex: 1;
  padding: 6px 0;
}
.ip-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 14px; gap: 8px;
  border-bottom: 1px solid rgba(20,40,80,.05);
  cursor: pointer;
  transition: background var(--t-fast);
}
.ip-row:hover { background: var(--card2); }
.ip-row:last-child { border-bottom: none; }
.ip-name {
  font-size: 12px; font-weight: 600; color: var(--fg2);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  flex: 1; min-width: 0;
}
.ip-addr {
  font-size: 10px; color: var(--sub);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ip-actions { display: flex; gap: 4px; flex-shrink: 0; }
.ip-btn {
  padding: 3px 7px; border-radius: 5px; font-size: 10px; font-weight: 600;
  border: 1px solid var(--bd2); background: transparent; color: var(--mut);
  cursor: pointer; font-family: inherit; transition: all var(--t-fast); white-space: nowrap;
}
.ip-btn:hover { border-color: var(--accent); color: var(--link); background: var(--accent-glow); }
.ip-empty {
  padding: 28px 14px; text-align: center;
  font-size: 12px; color: var(--sub);
}

/* ══════════════ MAP ══════════════ */
#map {
  width: 100%; height: 58vh;
  border: 1px solid var(--bd); border-radius: var(--r-lg);
  background: var(--bg3);
  box-shadow: var(--shadow-md), inset 0 0 0 1px rgba(20,40,80,.04);
  overflow: hidden;
}

/* ══════════════ LEAFLET POPUP ══════════════ */
.dark-popup .leaflet-popup-content-wrapper {
  background: var(--card2);
  border: 1px solid var(--bd2);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-lg);
  color: var(--fg);
}
.dark-popup .leaflet-popup-tip { background: var(--card2); }
.dark-popup .leaflet-popup-close-button {
  color: var(--mut) !important; font-size: 16px !important;
  top: 8px !important; right: 10px !important;
}

/* ══════════════ MAP MARKERS ══════════════ */
.leaflet-marker-icon.name-marker { background: transparent; border: none; }
.name-marker .bubble {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 9px; border-radius: 10px;
  border: 1px solid var(--bd2);
  background: rgba(255,255,255,.96);
  backdrop-filter: blur(8px);
  box-shadow: 0 2px 8px rgba(20,40,80,.18);
  font-size: 12px; font-weight: 600; color: var(--fg);
  white-space: nowrap; user-select: none;
  transition: all var(--t-fast);
}
.name-marker .bubble.green   { background: #ffffff;  border-color: var(--visit);   color: var(--visit);   }
.name-marker .bubble.inspect { background: #ffffff;  border-color: var(--inspect); color: var(--inspect); }
.name-marker .bubble.plan    { background: #ffffff;  border-color: var(--plan);    color: var(--plan);    }
/* 미등록(미방문) 강조 — 빨강 + 링 */
.name-marker .bubble.unreg   { background:#fff5f5; border:2px solid #e24b4a; color:#c0322f; font-weight:700;
                               box-shadow:0 0 0 3px rgba(226,75,74,.22), 0 3px 10px rgba(160,45,45,.30); }
.name-marker .bubble.unreg .dot { background:#e24b4a; border-color:#fff; }
.name-marker .bubble.unreg .unreg-badge {
  margin-left:5px; font-size:9px; font-weight:700; line-height:1;
  background:#e24b4a; color:#fff; padding:2px 6px; border-radius:999px; letter-spacing:-.2px;
}
/* 완료된 건 차분하게 */
.name-marker .bubble.green, .name-marker .bubble.inspect { opacity:.82; }
.name-marker .bubble.selected{ background: #ffffff;  border-color: var(--accent);  color: var(--accent);  box-shadow: 0 0 0 2px var(--accent), 0 2px 8px rgba(20,40,80,.18); }
.name-marker .dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--link); border: 1.5px solid rgba(20,40,80,.12);
  display: inline-block; flex-shrink: 0;
}
.name-marker .bubble.green .dot   { background: var(--visit);   }
.name-marker .bubble.inspect .dot { background: var(--inspect); }
.name-marker .bubble.plan .dot    { background: var(--plan);    }
.name-marker .bubble.selected .dot{ background: var(--accent);  }
.name-marker .txt { line-height: 1.2; }

/* ── D-DAY 뱃지 ── */
.name-marker-wrap { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.dday-tag {
  display: inline-block; padding: 2px 7px;
  border-radius: 6px; font-size: 10px; font-weight: 800;
  letter-spacing: .04em; white-space: nowrap;
  box-shadow: 0 1px 6px rgba(20,40,80,.12);
  line-height: 1.4;
  pointer-events: none;
}
.dday-tag.today  { background: #ff2d55; color: #fff; animation: ddayPulse 1.2s ease-in-out infinite; }
.dday-tag.soon   { background: #ff8c00; color: #fff; }  /* D-7 이내 */
.dday-tag.future { background: var(--report-bg); color: var(--report); border: 1px solid var(--report-bd); }
.dday-tag.past   { background: var(--bg2); color: var(--mut); border: 1px solid var(--bd); }
@keyframes ddayPulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(255,45,85,.6); }
  50%      { box-shadow: 0 0 0 5px rgba(255,45,85,0); }
}

/* ══════════════ BULK ACTION BAR ══════════════ */
#bulk-action-bar {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 500;
  background: rgba(9,19,34,.96);
  backdrop-filter: blur(20px);
  border-top: 1px solid var(--bd2);
  padding: 12px 24px 18px;
  display: none; align-items: center; gap: 10px; flex-wrap: wrap;
  box-shadow: 0 -8px 32px rgba(20,40,80,.10);
}
#bulk-action-bar.show { display: flex; }
#bulk-count { font-size: 13px; color: var(--mut); flex: 1; min-width: 80px; }
#bulk-count span { color: var(--fg); font-weight: 700; }
.bulk-act-btn {
  padding: 9px 18px; border-radius: var(--r-sm); border: 1px solid transparent;
  cursor: pointer; font-size: 13px; font-weight: 600; font-family: inherit;
  transition: all var(--t-fast);
}
.bulk-act-btn.visit   { background: var(--visit-bg);   color: var(--visit);   border-color: var(--visit-bd);   }
.bulk-act-btn.inspect { background: var(--inspect-bg); color: var(--inspect); border-color: var(--inspect-bd); }
.bulk-act-btn.as      { background: var(--as-bg);      color: var(--as);      border-color: var(--as-bd);      }
.bulk-act-btn.plan    { background: var(--plan-bg);    color: var(--plan);    border-color: var(--plan-bd);    }
.bulk-act-btn.cancel  { background: var(--card); color: var(--mut); border-color: var(--bd); }
.bulk-act-btn:hover { filter: brightness(1.2); }

/* ══════════════ CALENDAR ══════════════ */
.cal-pro {
  background: var(--card);
  border: 1px solid var(--bd);
  border-radius: var(--r-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.cal-pro .cal-head {
  display: grid; grid-template-columns: repeat(7,1fr);
  border-bottom: 1px solid var(--bd);
  background: linear-gradient(180deg, var(--card2), var(--card));
  position: sticky; top: 0; z-index: 1;
}
.cal-pro .cal-head div {
  padding: 11px 8px; text-align: center;
  font-size: 11px; font-weight: 600;
  color: var(--mut); letter-spacing: .06em; text-transform: uppercase;
}
.cal-pro .grid { display: grid; grid-template-columns: repeat(7,1fr); }
.cal-pro .cell {
  min-height: 120px; border-right: 1px solid var(--bd);
  border-bottom: 1px solid var(--bd);
  padding: 8px; background: var(--card);
  position: relative; display: flex; flex-direction: column; gap: 5px;
  transition: background var(--t-fast);
}
.cal-pro .cell:nth-child(7n) { border-right: none; }
.cal-pro .cell:hover { background: var(--card2); }
.cal-pro .cell.ms-picked { background: rgba(250,199,117,.12) !important; }
.cal-pro .datebar { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--mut); }
.cal-pro .datebar .dnum { font-weight: 700; color: var(--fg2); font-family: 'JetBrains Mono', monospace; }
.cal-pro .datebar .note-dot {
  margin-left: auto;
  border: 1px solid var(--bd2); background: var(--bg3);
  color: var(--fg2); border-radius: 999px;
  padding: 1px 7px; font-size: 10px; cursor: pointer;
  transition: all var(--t-fast);
}
.cal-pro .datebar .note-dot:hover { border-color: var(--accent); color: var(--fg); }
.cal-pro .out-month { background: var(--bg); opacity: .65; }
.cal-pro .out-month:hover { background: var(--bg2); }
.cal-pro .weekend { background: linear-gradient(180deg, var(--card), rgba(20,40,80,.02)); }
.cal-pro .today {
  outline: 2px solid var(--accent);
  outline-offset: -2px;
  background: radial-gradient(160px 80px at 90% -30%, rgba(61,134,245,.14), transparent);
}
.cal-pro .today .dnum { color: var(--accent) !important; }

/* Calendar Events */
.cal-pro .ev {
  font-size: 11.5px; display: flex; gap: 5px;
  align-items: center; justify-content: space-between;
  border: 1px solid var(--bd2); border-radius: var(--r-xs);
  padding: 3px 7px; background: var(--bg3); opacity: .95;
  transition: opacity var(--t-fast);
}
.cal-pro .ev:hover { opacity: 1; }
.cal-pro .ev.visit   { border-color: var(--visit-bd);   background: var(--visit-bg);   color: var(--visit);   }
.cal-pro .ev.inspect { border-color: var(--inspect-bd); background: var(--inspect-bg); color: var(--inspect); }
.cal-pro .ev.plan    { border-color: var(--plan-bd);    background: var(--plan-bg);    color: var(--plan);    border-style: dashed; }
.cal-pro .ev.as      { border-color: var(--as-bd);      background: var(--as-bg);      color: var(--as);      }
.cal-pro .ev.report  { border-color: var(--report-bd);  background: var(--report-bg);  color: var(--report);  }
.cal-pro .ev.submit  { border-color: var(--submit-bd);  background: var(--submit-bg);  color: var(--submit);  }
.cal-pro .more { font-size: 11px; color: var(--link); cursor: pointer; opacity: .85; }
.cal-pro .more:hover { opacity: 1; }
.cal-pro .legend {
  display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
  padding: 10px 14px; font-size: 11px; color: var(--mut);
  border-top: 1px solid var(--bd);
  background: var(--card2);
}
.cal-pro .legend .chip {
  display: inline-flex; gap: 5px; align-items: center;
  padding: 2px 9px; border-radius: 999px; border: 1px solid var(--bd);
  font-weight: 500;
}
.cal-pro .legend .chip.visit   { border-color: var(--visit-bd);   color: var(--visit);   }
.cal-pro .legend .chip.inspect { border-color: var(--inspect-bd); color: var(--inspect); }
.cal-pro .legend .chip.as      { border-color: var(--as-bd);      color: var(--as);      }
.cal-pro .legend .chip.report  { border-color: var(--report-bd);  color: var(--report);  }
.cal-pro .legend .chip.submit  { border-color: var(--submit-bd);  color: var(--submit);  }
@media (max-width: 860px) {
  .cal-pro .cell { min-height: 92px; padding: 5px; }
  .cal-pro .ev { font-size: 11px; padding: 2px 5px; }
}

/* ══════════════ MINI BUTTONS ══════════════ */
.mini-btn {
  border: 1px solid transparent; cursor: pointer;
  border-radius: var(--r-xs); padding: 3px 8px;
  font-size: 11.5px; font-family: inherit; line-height: 1.4;
  transition: all var(--t-fast);
}
.mini-btn.del { background: var(--inspect-bg); border-color: var(--inspect-bd); color: var(--inspect); }
.mini-btn.del:hover { background: #fee2e2; }
.mini-btn.neutral { background: var(--card2); border-color: var(--bd2); color: var(--fg2); }
.mini-btn.neutral:hover { border-color: var(--bd3); color: var(--fg); }

/* ══════════════ CARD ══════════════ */
.card {
  background: var(--card);
  border: 1px solid var(--bd);
  border-radius: var(--r-lg);
  padding: 14px;
  margin-top: 14px;
}

/* ══════════════ DIALOG / MODAL ══════════════ */
dialog {
  color: var(--fg);
  background: var(--card2);
  border: 1px solid var(--bd2);
  border-radius: var(--r-lg);
  padding: 0;
  min-width: min(92vw, 360px);
  box-shadow: var(--shadow-lg), 0 0 0 1px rgba(20,40,80,.04);
}
/* 닫힌 dialog는 무조건 숨김 — inline display:flex 덮어쓰기 방지 */
@media (max-width: 600px) {
  #photoReportModal {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    border: 0 !important;
    top: 0 !important;
    left: 0 !important;
    transform: none !important;
  }
  #photoReportModal .modal-head { padding: 12px 14px; }
  #photoReportModal #prm-panel-list,
  #photoReportModal #prm-panel-new { padding: 10px 12px; }
}
dialog:not([open]) { display: none !important; }
/* 모바일 전체화면 모달 — .fullscreen-mobile 클래스 사용 */
@media (max-width: 600px) {
  dialog.fullscreen-mobile {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    border: 0 !important;
    transform: none !important;
  }
  dialog.fullscreen-mobile[open] { animation: dialogInMobileFull var(--t-mid) ease both; }
  @keyframes dialogInMobileFull {
    from { opacity:0; transform: translateY(18px); }
    to   { opacity:1; transform: translateY(0); }
  }
  dialog.fullscreen-mobile .modal-body {
    max-height: none !important;
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
  }
}
dialog[open].flex-col { display: flex !important; flex-direction: column; }
dialog::backdrop { background: rgba(20,40,80,.35); backdrop-filter: blur(4px); }
dialog[open] { animation: dialogIn var(--t-mid) ease both; }
@keyframes dialogIn {
  from { opacity: 0; transform: scale(.97) translateY(-6px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
@media (max-width: 600px) {
  #photoReportModal[open] { animation: dialogInMobile var(--t-mid) ease both; }
  @keyframes dialogInMobile {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
}

.modal-head {
  padding: 14px 18px;
  border-bottom: 1px solid var(--bd);
  display: flex; justify-content: space-between; align-items: center;
  background: linear-gradient(180deg, var(--card), var(--card2));
}
.modal-head strong { font-size: 14px; font-weight: 700; color: var(--fg); }
.modal-body { padding: 14px 18px; }
.modal-actions {
  padding: 12px 18px;
  border-top: 1px solid var(--bd);
  display: flex; gap: 8px; justify-content: flex-end;
  background: var(--card);
}

/* ══════════════ FORMS ══════════════ */
label {
  color: var(--mut);
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: .03em;
  display: block;
  margin: 10px 0 5px;
}
input, select, textarea {
  width: 100%;
  padding: 10px 13px;
  background: var(--bg3);
  color: var(--fg);
  border: 1px solid var(--bd);
  border-radius: var(--r-sm);
  font-size: 13px;
  font-family: inherit;
  transition: border-color var(--t-fast), box-shadow var(--t-fast);
  outline: none;
  -webkit-appearance: none;
}
input:focus, select:focus, textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}
input::placeholder, textarea::placeholder { color: var(--sub); }
select option { background: var(--bg3); }

/* ══════════════ DRAWER ══════════════ */
.vm-memo{border:1px solid #fde68a;background:#fffbeb;border-radius:10px;padding:10px 12px;margin-top:4px}
.vm-memo__head{display:flex;align-items:center;gap:10px;margin-bottom:7px;font-size:12px;
  font-weight:700;color:#92400e}
.vm-memo__flag{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;
  color:#b45309;cursor:pointer;margin-left:auto}
.vm-memo__flag input{width:14px;height:14px;cursor:pointer}
.vm-memo__st{font-size:11px;color:#b45309;font-weight:500;flex-basis:100%;order:9}
.vm-memo textarea{width:100%;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;
  font-size:13px;font-family:inherit;line-height:1.6;background:#fff;color:#451a03;resize:vertical}
.vm-memo textarea:focus{outline:none;border-color:#f59e0b}
.vm-memo__save{margin-top:7px;border-color:#f59e0b !important;color:#b45309 !important;
  font-size:12px;padding:5px 12px}

/* 지도 마커 — 메모/중요 표시 */
.memo-pin{position:absolute;top:-7px;right:-7px;width:16px;height:16px;border-radius:50%;
  background:#f59e0b;color:#fff;font-size:10px;line-height:16px;text-align:center;
  border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);font-weight:800;z-index:5}
.memo-pin.important{background:#dc2626;animation:memoPulse 1.6s ease-in-out infinite}
@keyframes memoPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.22)}}
.bubble{position:relative}

#clientModal.drawer {
  position: fixed; right: 20px; top: calc(64px + 14px);
  width: 420px; max-width: 92vw;
  height: calc(100vh - 64px - 28px);
  margin: 0;
  box-shadow: var(--shadow-lg);
  z-index: 1000;
  border-radius: var(--r-lg);
}
#clientModal.drawer::backdrop { display: none; }
#clientModal .modal-body { overflow: auto; max-height: calc(100% - 56px - 56px); }
@media (max-width: 720px) {
  #clientModal.drawer {
    right: 50%; transform: translateX(50%);
    top: auto; bottom: 12px;
    width: calc(100vw - 24px);
    height: auto; max-height: 72vh;
    border-radius: var(--r-lg);
  }
}

/* ══════════════ TASK ROW ══════════════ */
.task-row {
  display: flex; align-items: center; gap: 9px;
  margin: 5px 0; padding-bottom: 8px;
  border-bottom: 1px solid var(--bd);
  transition: opacity var(--t-fast);
}
.task-row.done { opacity: .45; }
.task-row.done .task-title { text-decoration: line-through; }
.task-title {
  flex: 1; font-size: 13px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
/* ══════════════ MOBILE ══════════════ */
@media (max-width: 768px) {
  #map { height: 32vh !important; margin-bottom: 10px; }
  .cal-pro { margin-top: 0 !important; border-radius: 11px !important; }
  .cal-pro .cell { min-height: 56px !important; padding: 3px !important; }
  .cal-pro .dnum { font-size: 11px !important; }
  .cal-pro .ev { font-size: 10.5px !important; padding: 2px 4px !important; }
  .main { margin-top: 6px !important; padding: 0 10px !important; }
  .nh-row1 { padding: 8px 14px; gap: 10px; }
  .nh-row2 { padding: 0 14px 8px; gap: 5px; }
  .nh-kpis { gap: 4px; }
  .nh-kpi { padding: 4px 10px; min-width: 56px; }
  .nh-pills { gap: 3px; }
  .nh-pill { padding: 3px 9px; font-size: 11px; }
}
@media (max-width: 768px) {
  .cal-pro { width: 100% !important; overflow: hidden !important; }
  .cal-pro .grid { grid-template-columns: repeat(7,1fr) !important; width: 100% !important; }
  .cal-pro .cell { min-width: 0 !important; min-height: 48px !important; padding: 2px !important; }
  .cal-pro .dnum { font-size: 9px !important; font-weight: 600 !important; }
  .cal-pro .note-dot { font-size: 8px !important; padding: 1px 3px !important; }
  .cal-pro .ev { font-size: 7px !important; line-height: 1.15 !important; padding: 1px 2px !important; border-radius: 3px !important; }
  .cal-pro .ev div { overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
  .cal-pro .more { font-size: 8px !important; }
}

/* ══════════════ MUT HELPER ══════════════ */
.mut { color: var(--mut); font-size: 12px; }

/* ══════════════ QUICK FILE PANEL ══════════════ */
.qf-panel {
  background: var(--card);
  border: 1px solid var(--bd);
  border-radius: var(--r-lg);
  overflow: hidden;
}
.qf-head {
  padding: 13px 16px;
  border-bottom: 1px solid var(--bd);
  background: var(--card2);
  display: flex; align-items: center; justify-content: space-between; gap: 6px;
}
.qf-head h3 {
  font-size: 11px; font-weight: 700; color: var(--mut);
  letter-spacing: .06em; text-transform: uppercase;
  display: flex; align-items: center; gap: 6px;
}
.qf-add-btn {
  padding: 3px 10px; border-radius: var(--r-xs);
  border: 1px solid var(--bd2); background: transparent;
  color: var(--mut); font-size: 11px; font-weight: 600;
  cursor: pointer; font-family: inherit; white-space: nowrap;
  transition: all var(--t-fast);
}
.qf-add-btn:hover { border-color: var(--accent); color: var(--link); }
.qf-groups {
  display: flex; gap: 3px; padding: 6px 10px;
  border-bottom: 1px solid var(--bd);
  overflow-x: auto; scrollbar-width: none; flex-wrap: wrap;
}
.qf-groups::-webkit-scrollbar { display: none; }
.qf-gtab {
  padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600;
  border: 1px solid var(--bd2); color: var(--mut); background: transparent;
  cursor: pointer; white-space: nowrap; transition: all var(--t-fast);
  font-family: inherit;
}
.qf-gtab:hover { border-color: var(--bd3); color: var(--fg2); }
.qf-gtab.active { background: var(--accent-dim); border-color: var(--accent); color: var(--link); }
.qf-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 6px; padding: 8px 10px;
  max-height: 260px; overflow-y: auto;
}
.qf-grid::-webkit-scrollbar { width: 3px; }
.qf-grid::-webkit-scrollbar-thumb { background: var(--bd3); border-radius: 99px; }
.qf-card {
  background: var(--bg3); border: 1px solid var(--bd);
  border-radius: var(--r-sm); padding: 8px 8px 6px;
  cursor: pointer; transition: all var(--t-fast);
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  text-align: center; position: relative; user-select: none;
  text-decoration: none;
}
.qf-card:hover { border-color: var(--accent); background: var(--card2); transform: translateY(-1px); }
.qf-card:active { transform: translateY(0); opacity: .75; }
.qf-icon { font-size: 22px; line-height: 1; }
.qf-name {
  font-size: 10px; font-weight: 600; color: var(--fg2);
  word-break: break-all; line-height: 1.3; width: 100%;
}
.qf-del {
  position: absolute; top: 3px; right: 3px;
  width: 16px; height: 16px; border-radius: 50%;
  background: rgba(239,68,68,.15); border: 1px solid var(--inspect-bd);
  color: var(--inspect); font-size: 9px;
  display: none; align-items: center; justify-content: center;
  cursor: pointer; transition: all var(--t-fast); line-height: 1;
}
.qf-del:hover { background: rgba(239,68,68,.3); }
.qf-editing .qf-card .qf-del { display: flex; }
.qf-card.qf-add-card {
  border-style: dashed; background: transparent;
  color: var(--sub); justify-content: center; font-size: 18px;
  min-height: 52px;
}
.qf-card.qf-add-card:hover { border-color: var(--accent); color: var(--link); background: var(--accent-dim); }
.qf-empty {
  padding: 20px 10px; text-align: center;
  font-size: 11px; color: var(--sub); line-height: 1.8;
  grid-column: 1 / -1;
}
.qf-footer {
  padding: 5px 10px; border-top: 1px solid var(--bd);
  display: flex; align-items: center; gap: 6px;
}
.qf-edit-btn {
  font-size: 10px; padding: 2px 8px; border-radius: var(--r-xs);
  border: 1px solid var(--bd2); background: transparent; color: var(--sub);
  cursor: pointer; font-family: inherit; transition: all var(--t-fast);
}
.qf-edit-btn:hover { border-color: var(--bd3); color: var(--mut); }
.qf-edit-btn.active { background: var(--accent-dim); border-color: var(--accent); color: var(--link); }
.qf-count { font-size: 10px; color: var(--sub); margin-left: auto; }

/* Quick File Modal */
#qfModal {
  min-width: min(92vw, 340px);
}
.qf-modal-icon-grid {
  display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; margin-top: 6px;
}
.qf-modal-icon-opt {
  width: 30px; height: 30px; border-radius: var(--r-xs);
  border: 1px solid var(--bd2); background: var(--bg3);
  cursor: pointer; font-size: 15px;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--t-fast);
}
.qf-modal-icon-opt:hover, .qf-modal-icon-opt.qf-icon-sel {
  border-color: var(--accent); background: var(--accent-dim);
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

</head>
<body>

<header class="new-header">
  <!-- 1행: 브랜드 + KPI + 홈 -->
  <div class="nh-row1">
    <div class="nh-brand">
      <div class="nh-icon">🗺</div>
      <span><?=h($COMPANY_NAME)?></span>
      <button type="button" onclick="editCompanyName()" title="상호명 변경"
        style="margin-left:6px;border:1px solid var(--bd2);background:var(--card2);color:var(--mut);border-radius:6px;cursor:pointer;font-size:11px;padding:2px 7px">✎</button>
    </div>
    <div class="nh-kpis">
      <div class="nh-kpi">
        <span class="nh-kpi-l">월</span>
        <span class="nh-kpi-v"><?=h($ym)?></span>
      </div>
      <div class="nh-kpi">
        <span class="nh-kpi-l">할 일</span>
        <span class="nh-kpi-v"><?=h((string)$task_open)?><span style="opacity:.4;font-weight:400">/</span><?=h((string)$task_total)?></span>
      </div>
      <button type="button" class="nh-kpi" onclick="openClientModal()" title="새 거래처 등록 (최대 200)"
        style="border:1px solid var(--accent)!important;border-radius:var(--r-sm);cursor:pointer;font-family:inherit;background:var(--accent-dim);margin-left:10px">
        <span class="nh-kpi-l" style="color:var(--accent)">+ 거래처등록</span>
        <span class="nh-kpi-v" style="color:var(--accent)"><?= count($clients) ?><span style="opacity:.4;font-weight:400">/200</span></span>
      </button>
    </div>
    <a class="nh-home" href="index.php">← 메인으로</a>
  </div>
  <!-- 2행: 탭 + 월이동 + 강조필터 -->
  <div class="nh-row2">
    <div class="nh-months">
      <a href="?m=<?=h($prevYm)?>&type=<?=h($type)?>" class="nh-mbtn">◀ <?=h(substr($prevYm,5))?>월</a>
      <a href="?m=<?=h(date('Y-m'))?>&type=<?=h($type)?>" class="nh-mbtn on">이번달</a>
      <a href="?m=<?=h($nextYm)?>&type=<?=h($type)?>" class="nh-mbtn"><?=h(substr($nextYm,5))?>월 ▶</a>
      <button type="button" class="nh-mbtn" onclick="openPrevMonthModal()" title="전월 점검/방문 목록">
        📋 전월현황
      </button>
      <button type="button" class="nh-mbtn" onclick="openEstimateModal()" title="견적서 작성">
        📄 견적서
      </button>
      <a href="?view=fire" class="nh-mbtn" title="자위소방대 편성표 작성">
        🧯 소방편성표
      </a>
      <a href="?view=subscribe" class="nh-mbtn nh-mbtn--accent" title="구독 / 결제">
        💳 구독하기
      </a>
      <?php if (count($ghostClients) > 0): ?>
      <button type="button" class="nh-mbtn" onclick="openGhostModal()" title="지도 좌표 없는 거래처">
        👻 유령 <?= count($ghostClients) ?>개
      </button>
      <?php endif; ?>
    </div>
    <div class="nh-vdiv"></div>
    <div class="nh-pills" style="margin-left:auto">
      <?php
        $ftabs = ['visit'=>'방문','inspect'=>'점검','as'=>'AS','report'=>'보고서','submit'=>'이행완료','plan'=>'📅 방문예정'];
        foreach ($ftabs as $t=>$label):
      ?>
        <a class="nh-pill <?= $t===$type?'on':'' ?>"
           href="?m=<?=h($ym)?>&type=<?=h($t)?>"><?=h($label)?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>



<div class="main">
  <div class="main-cols">

    <!-- 좌측: D-DAY 패널 + 아이템 가방 -->
    <div style="display:flex;flex-direction:column;gap:14px">
    <div class="dday-panel">
      <div class="dday-panel-head">
        <h3>📅 D-DAY</h3>
        <div style="font-size:11px;color:var(--sub)"><?= count($ddayClients) ?>개 설정됨</div>
      </div>
      <div class="dday-panel-list" id="dd-list">
        <?php if (empty($ddayClients)): ?>
          <div class="dd-empty">설정된 D-DAY가 없습니다.<br><span style="font-size:10px;color:var(--sub)">거래처 클릭 → 📅 D-DAY</span></div>
        <?php else: ?>
          <?php foreach ($ddayClients as $dd):
            $diff = $dd['diff'];
            $label = $diff===0 ? 'D-DAY' : ($diff>0 ? "D-{$diff}" : "D+".abs($diff));
            $cls   = $diff===0 ? 'today' : ($diff>0 && $diff<=7 ? 'soon' : ($diff>0 ? 'future' : 'past'));
          ?>
          <div class="dd-row" onclick="focusOnClient('<?=h($dd['id'])?>')" title="지도에서 보기">
            <div class="dd-badge <?=h($cls)?>"><?=h($label)?></div>
            <div class="dd-info">
              <div class="dd-name"><?=h($dd['name'])?></div>
              <div class="dd-date"><?=h($dd['dday'])?></div>
            </div>
            <button class="dd-edit" onclick="event.stopPropagation();openDdayModal('<?=h($dd['id'])?>')" title="D-DAY 수정">✏️</button>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div><!-- /dday-panel -->

    <!-- 아이템 가방 -->
    <div class="qf-panel" id="qf-panel">
      <div class="qf-head">
        <h3>📁 자료실</h3>
        <div style="display:flex;gap:5px;align-items:center">
          <button class="qf-add-btn" onclick="qfOpenAddModal()">＋ 추가</button>
          <button class="qf-add-btn" onclick="qfOpenGroupModal()">폴더</button>
        </div>
      </div>
      <div class="qf-groups" id="qf-groups-bar"></div>
      <div class="qf-grid" id="qf-grid"></div>
      <div class="qf-footer">
        <button class="qf-edit-btn" id="qf-edit-btn" onclick="qfToggleEdit()">편집</button>
        <span class="qf-count" id="qf-count"></span>
      </div>
    </div>

    </div><!-- /left-col wrapper -->

    <!-- 가운데: 지도 + 달력 -->
    <div>
      <div id="map" style="border-radius:var(--r-lg);overflow:hidden"></div>

      <!-- 달력 (Pro) -->
      <div class="card cal-pro" style="margin-top:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <button type="button" id="ms-toggle" onclick="msToggle()"
              style="font-size:12px;padding:6px 12px;border-radius:7px;border:2px solid #ddd6fe;background:#f5f3ff;color:#7c3aed;cursor:pointer;font-weight:700">
              📌 여러 날 선택 배정
            </button>
            <span id="ms-info" style="display:none;font-size:12px;color:var(--mut)">
              <b id="ms-count" style="color:var(--accent)">0</b>일 선택됨
              · 하루 <input type="number" id="ms-perday" value="5" min="1" max="30" style="width:50px;padding:4px;border-radius:6px;border:1px solid var(--bd2);background:var(--card2);color:var(--fg)">곳
            </span>
          </div>
          <div style="display:flex;gap:6px">
            <button type="button" id="ms-assign" onclick="msAssign()" style="display:none;font-size:12px;padding:6px 12px;border-radius:7px;border:2px solid #16a34a;background:#ecfdf3;color:#15803d;cursor:pointer;font-weight:700">선택한 날에 배정</button>
            <button type="button" onclick="clearWholeMonth()"
              style="font-size:11px;padding:6px 10px;border-radius:7px;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;cursor:pointer">
              🗑️ <?=h($ym)?> 전체 비우기
            </button>
          </div>
        </div>
        <div class="cal-head">
          <?php foreach (['일','월','화','수','목','금','토'] as $wi=>$w): ?>
            <div style="<?=$wi===0?'color:#ff5252':($wi===6?'color:#60a5fa':'')?>;"><?=h($w)?></div>
          <?php endforeach; ?>
        </div>
        <div id="cal-grid" class="grid"></div>
        <div class="legend">
          <span class="chip visit">방문</span>
          <span class="chip inspect">점검</span>
          <span class="chip as">AS</span>
          <span class="chip report">보고서</span>
          <span class="chip submit">이행완료</span>
          <span style="margin-left:auto;font-size:11px;opacity:.6">클릭 → 날짜 메모 / 이벤트 상세</span>
        </div>
      </div>
    </div>

    <!-- 우측: 이번 달 미등록 마을 -->
    <div class="inactive-panel">
      <div class="inactive-panel-head">
        <h3 id="ip-title">📋 미등록 거래처</h3>
        <div class="ip-meta">
          <?=h($ym)?> · <span id="ip-count"><?= count($inactiveClients) ?></span>개
          <span style="color:var(--sub)"> / 전체 <?= count($clients) ?></span>
        </div>
      </div>
      <div class="ip-tabs">
        <button type="button" class="ip-tab active" data-tab="unreg" onclick="switchIpTab('unreg')">미등록 <span class="ip-tab-n"><?= count($inactiveClients) ?></span></button>
        <button type="button" class="ip-tab" data-tab="all" onclick="switchIpTab('all')">전체 <span class="ip-tab-n"><?= count($clients) ?></span></button>
      </div>
      <div class="inactive-panel-search">
        <input type="text" id="ip-search" placeholder="이름 검색…" oninput="filterInactive(this.value)">
      </div>

      <!-- 미등록 목록 -->
      <div class="inactive-panel-list" id="ip-list">
        <?php if (empty($inactiveClients)): ?>
          <div class="ip-empty">🎉 이번 달 모든 마을에<br>등록이 완료되었습니다!</div>
        <?php else: ?>
          <?php foreach ($inactiveClients as $ic): ?>
            <div class="ip-row" data-id="<?=h($ic['id'])?>" data-name="<?=h(mb_strtolower($ic['name']??''))?>"
                 onclick="focusOnClient('<?=h($ic['id'])?>')" title="지도에서 보기">
              <div style="min-width:0;flex:1">
                <div class="ip-name"><?=h($ic['name']??'')?></div>
                <?php if (!empty($ic['address']??$ic['addr']??'')): ?>
                  <div class="ip-addr"><?=h($ic['address']??$ic['addr']??'')?></div>
                <?php endif; ?>
              </div>
              <div class="ip-actions" onclick="event.stopPropagation()">
                <button class="ip-btn" onclick="openVisitFor('<?=h($ic['id'])?>','<?=h(addslashes($ic['name']??''))?>')" title="일정 등록">＋</button>
                <button class="ip-btn" onclick="openPlanFor('<?=h($ic['id'])?>','<?=h(addslashes($ic['name']??''))?>')" title="방문예정" style="color:#a78bfa;border-color:#2e1570">📅</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- 전체 목록 (기본 숨김) -->
      <div class="inactive-panel-list" id="ip-list-all" style="display:none">
        <?php if (empty($allClientsSorted)): ?>
          <div class="ip-empty">등록된 거래처가 없습니다.</div>
        <?php else: ?>
          <?php foreach ($allClientsSorted as $ac): ?>
            <?php $acId = (string)($ac['id']??''); $isDone = isset($activeThisMonth[$acId]); ?>
            <div class="ip-row<?= $isDone ? ' ip-done' : '' ?>" data-id="<?=h($acId)?>" data-name="<?=h(mb_strtolower($ac['name']??''))?>"
                 onclick="focusOnClient('<?=h($acId)?>')" title="지도에서 보기">
              <div style="min-width:0;flex:1">
                <div class="ip-name">
                  <?php if ($isDone): ?><span class="ip-check" title="이번 달 등록 완료">✓</span><?php endif; ?>
                  <?=h($ac['name']??'')?>
                </div>
                <?php if (!empty($ac['address']??$ac['addr']??'')): ?>
                  <div class="ip-addr"><?=h($ac['address']??$ac['addr']??'')?></div>
                <?php endif; ?>
              </div>
              <div class="ip-actions" onclick="event.stopPropagation()">
                <button class="ip-btn" onclick="openVisitFor('<?=h($acId)?>','<?=h(addslashes($ac['name']??''))?>')" title="일정 등록">＋</button>
                <button class="ip-btn" onclick="openPlanFor('<?=h($acId)?>','<?=h(addslashes($ac['name']??''))?>')" title="방문예정" style="color:#a78bfa;border-color:#2e1570">📅</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- 하단 일괄처리 액션바 -->
<div id="bulk-action-bar">
  <div id="bulk-count"><span id="bulk-count-num">0</span>개 선택됨</div>
  <input type="date" id="bulk-date" style="padding:8px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:13px;">
  <button class="bulk-act-btn visit"  onclick="bulkAction('visit','add')">✓ 방문</button>
  <button class="bulk-act-btn inspect" onclick="bulkAction('inspect','add')">✓ 점검</button>
  <button class="bulk-act-btn as"     onclick="bulkAction('as','inc')">＋ AS</button>
  <button class="bulk-act-btn plan"   onclick="bulkAction('plan','add')">📅 방문예정</button>
  <button class="bulk-act-btn cancel" onclick="exitSelectMode()">취소</button>
</div>


<!-- ★ 방문예정 등록 모달 -->
<dialog id="planModal" style="min-width:min(92vw,340px)">
  <form method="post">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="kind" value="plan">
    <input type="hidden" name="id" id="pm-id">
    <div class="modal-head">
      <strong id="pm-title">📅 방문예정</strong>
      <button type="button" class="btn ghost" onclick="closeModal('planModal')">✕</button>
    </div>
    <div class="modal-body" style="display:grid;gap:12px">
      <label>예정 날짜
        <input type="date" name="date" id="pm-date" required>
      </label>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn ghost" onclick="closeModal('planModal')">취소</button>
      <button class="btn" style="background:var(--plan-bg);border-color:var(--plan-bd);color:var(--plan)">📅 예정 등록</button>
    </div>
  </form>
</dialog>

<!-- 일정 등록 모달 -->
<dialog id="visitModal">
  <form method="post">
    <div class="modal-head">
      <strong id="vm-title">일정 등록</strong>
      <button type="button" class="btn ghost" onclick="closeModal('visitModal')">✕</button>
    </div>
    <div class="modal-body" style="display:grid;gap:10px">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="id" id="vm-id">

      <!-- 방문 이력 -->
      <div id="vm-history" style="display:none">
        <div style="font-size:11px;color:#64748b;margin-bottom:6px;font-weight:600;letter-spacing:.04em">방문 이력</div>
        <div id="vm-history-list" style="display:grid;gap:4px;max-height:140px;overflow-y:auto"></div>
      </div>

      <label style="margin-top:4px">날짜<input type="date" name="date" id="vm-date" value="<?=h(date('Y-m-d'))?>"></label>
      <label>종류
        <select name="kind" id="vm-kind">
          <option value="visit"  <?= $type==='visit'  ?'selected':'' ?>>방문</option>
          <option value="inspect"<?= $type==='inspect'?'selected':'' ?>>점검</option>
          <option value="as"     <?= $type==='as'     ?'selected':'' ?>>AS</option>
          <option value="report" <?= $type==='report' ?'selected':'' ?>>보고서접수</option>
          <option value="submit" <?= $type==='submit' ?'selected':'' ?>>이행완료제출</option>
          <option value="plan"   <?= $type==='plan'   ?'selected':'' ?>>📅 방문예정</option>
        </select>
      </label>
      <label id="vm-as-wrap" style="display:none">AS 수량
        <input type="number" name="count" id="vm-as" min="1" value="1">
      </label>

      <!-- 거래처 메모 — 저장하면 지도 마커에 표시가 붙는다 -->
      <div class="vm-memo">
        <div class="vm-memo__head">
          <span>📌 메모</span>
          <label class="vm-memo__flag" title="지도에 중요 표시를 크게 띄웁니다">
            <input type="checkbox" id="vm-memo-flag"> <span>중요</span>
          </label>
          <span class="vm-memo__st" id="vm-memo-st"></span>
        </div>
        <textarea id="vm-memo-text" rows="3"
          placeholder="이 거래처의 중요한 내용을 적어두세요. 저장하면 지도에 표시됩니다."></textarea>
        <button type="button" class="btn ghost vm-memo__save" id="vm-memo-save">메모 저장</button>
      </div>
    </div>
    <div class="modal-actions" style="justify-content:space-between">
      <div style="display:flex;gap:6px">
        <button type="button" class="btn ghost" id="vm-edit-btn" onclick="openEditClientModal()">✏️ 마을 정보</button>
        <button type="button" class="btn ghost" onclick="openBuildingModal()" style="border-color:#2563eb;color:#93c5fd">🏢 설비현황</button>
        <button type="button" class="btn ghost" id="vm-dday-btn" onclick="openDdayModal(document.getElementById('vm-id').value)"
          style="border-color:#ff6b35;color:#ffad7a">📅 D-DAY</button>
        <button type="button" class="btn ghost" onclick="openPhotoReportModal(document.getElementById('vm-id').value)" style="border-color:#16a34a;color:#15803d">📷 공사사진</button>
        <button type="button" class="btn ghost" onclick="openClientFilesModal(document.getElementById('vm-id').value)" style="border-color:var(--plan-bd);color:var(--plan)">📁 문서고</button>
      </div>
      <div style="display:flex;gap:6px">
        <button type="button" class="btn ghost" onclick="closeModal('visitModal')">취소</button>
        <button class="btn">저장</button>
      </div>
    </div>
  </form>
</dialog>

<!-- 신규 마을: 우측 드로어 -->
<dialog id="clientModal" class="drawer">
  <form method="post" style="height:100%;display:flex;flex-direction:column">
    <div class="modal-head"><strong>신규 마을</strong><button type="button" class="btn ghost" onclick="closeModal('clientModal')">✕</button></div>
    <div class="modal-body" style="display:grid;gap:10px">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="action" value="client_create">
      <label>마을명<input name="name" required></label>
      <label>건물명 · 주소로 검색
        <div style="display:flex;gap:6px">
          <input id="cm-search" placeholder="예) ○○상사, 파주시 ○○로 12" style="flex:1"
                 onkeydown="if(event.key==='Enter'){event.preventDefault();placeSearch();}">
          <button type="button" class="btn" onclick="placeSearch()" style="white-space:nowrap">🔍 검색</button>
        </div>
      </label>
      <div id="cm-results" style="display:none"></div>
      <label>주소<input name="address" id="cm-addr" placeholder="검색 결과에서 선택하면 자동 입력" readonly style="background:var(--bg2)"></label>
      <label>상세주소<input name="address_detail" id="cm-addr-detail" placeholder="동/호수 등 (선택)"></label>
      <div style="display:flex;gap:8px">
        <label style="flex:1">위도(lat)<input name="lat" id="cm-lat" placeholder="선택 시 자동" readonly></label>
        <label style="flex:1">경도(lng)<input name="lng" id="cm-lng" placeholder="선택 시 자동" readonly></label>
      </div>
      <label>첫 코멘트<textarea name="note" rows="3"></textarea></label>
      <div class="mut" id="cm-geohint">건물명이나 상호로 검색하면 정확한 위치가 잡힙니다. 결과를 선택하면 지도에 바로 표시됩니다.</div>
    </div>
    <div class="modal-actions"><button class="btn" style="width:100%">등록</button></div>
  </form>
</dialog>

<!-- ★ 건물 설비현황 모달 -->
<dialog id="buildingModal" class="flex-col" style="min-width:min(96vw,600px);max-height:92vh;padding:0">
  <div class="modal-head">
    <strong id="bm-title">🏢 설비현황</strong>
    <button type="button" class="btn ghost" onclick="closeModal('buildingModal')">✕</button>
  </div>

  <!-- 탭: 설정 / 뷰 -->
  <div style="display:flex;border-bottom:1px solid var(--bd)">
    <button type="button" id="bm-tab-edit" onclick="bmTab('edit')"
      style="flex:1;padding:11px;background:rgba(61,134,245,.1);border:0;color:var(--link);font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid var(--accent);font-family:inherit">
      ✏️ 설비 입력
    </button>
    <button type="button" id="bm-tab-view" onclick="bmTab('view')"
      style="flex:1;padding:11px;background:transparent;border:0;color:var(--mut);font-size:13px;cursor:pointer;border-bottom:2px solid transparent;font-family:inherit;transition:.15s">
      🏗️ 건물 뷰
    </button>
  </div>

  <!-- 설비 입력 탭 -->
  <div id="bm-edit-panel" style="flex:1;overflow-y:auto;display:flex;flex-direction:column">
    <form method="post" id="buildingForm">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="building_save">
      <input type="hidden" name="client_id" id="bm-client-id">

      <!-- 층수 설정 -->
      <div style="padding:14px 16px;border-bottom:1px solid #e3e8f0;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px">
          <label style="margin:0;font-size:12px;color:#94a3b8;white-space:nowrap">지하</label>
          <input type="number" name="floors_below" id="bm-floors-below" min="0" max="10" value="0"
            style="width:64px;padding:7px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:14px;text-align:center"
            oninput="rebuildFloorList()">
          <span style="font-size:12px;color:#64748b">층</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <label style="margin:0;font-size:12px;color:#94a3b8;white-space:nowrap">지상</label>
          <input type="number" name="floors_above" id="bm-floors-above" min="1" max="50" value="5"
            style="width:64px;padding:7px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:14px;text-align:center"
            oninput="rebuildFloorList()">
          <span style="font-size:12px;color:#64748b">층</span>
        </div>
        <button type="button" onclick="rebuildFloorList()" style="padding:7px 14px;border-radius:8px;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;font-size:12px;cursor:pointer">
          층 구성 적용
        </button>
      </div>

      <!-- 커스텀 설비 추가 -->
      <div style="padding:10px 16px;border-bottom:1px solid #e3e8f0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:12px;color:#94a3b8">커스텀 설비:</span>
        <div id="bm-custom-chips" style="display:flex;gap:6px;flex-wrap:wrap;flex:1"></div>
        <div style="display:flex;gap:6px">
          <input id="bm-custom-input" placeholder="설비명 입력"
            style="padding:6px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:12px;width:120px"
            onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomEquip()}">
          <button type="button" onclick="addCustomEquip()"
            style="padding:6px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;font-size:12px;cursor:pointer">추가</button>
        </div>
      </div>

      <!-- 층별 설비 체크리스트 -->
      <div id="bm-floor-list" style="flex:1;overflow-y:auto;padding:12px 16px;display:grid;gap:10px"></div>

      <div style="padding:12px 16px;border-top:1px solid #e3e8f0;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn ghost" onclick="closeModal('buildingModal')">취소</button>
        <button type="submit" class="btn">💾 저장</button>
      </div>
    </form>
  </div>

  <!-- 건물 뷰 탭 -->
  <div id="bm-view-panel" style="flex:1;overflow-y:auto;padding:16px;display:none">
    <div id="bm-building-view"></div>
  </div>
</dialog>


<!-- ★ 공사사진 보고서 모달 -->
<dialog id="photoReportModal" class="flex-col" style="width:min(96vw,660px);max-height:90svh;margin:auto;padding:0">
  <div class="modal-head" style="flex-shrink:0">
    <div>
      <strong id="prm-title" style="font-size:15px">📷 공사사진 보고서</strong>
      <div id="prm-subtitle" style="font-size:11px;color:var(--sub);margin-top:2px"></div>
    </div>
    <button type="button" class="btn ghost" onclick="closeModal('photoReportModal')">✕</button>
  </div>

  <!-- 탭: 목록 / 새 보고서 -->
  <div style="display:flex;border-bottom:1px solid var(--bd);flex-shrink:0">
    <button type="button" id="prm-tab-list" onclick="prmTab('list')"
      style="flex:1;padding:11px;background:rgba(61,134,245,.1);border:0;color:var(--link);font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid var(--accent);font-family:inherit;transition:.15s">
      📋 보고서 목록
    </button>
    <button type="button" id="prm-tab-new" onclick="prmTab('new')"
      style="flex:1;padding:11px;background:transparent;border:0;color:var(--mut);font-size:13px;cursor:pointer;border-bottom:2px solid transparent;font-family:inherit;transition:.15s">
      ＋ 새 보고서 작성
    </button>
  </div>

  <!-- 목록 탭 -->
  <div id="prm-panel-list" style="flex:1;overflow-y:auto;padding:14px 16px">
    <div id="prm-report-list"></div>
  </div>

  <!-- 새 보고서 탭 -->
  <div id="prm-panel-new" style="flex:1;overflow-y:auto;padding:14px 16px;display:none">
    <div style="display:grid;gap:14px">
      <!-- 공사명 -->
      <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">
        공사명
        <input type="text" id="prm-report-title" placeholder="예: 스프링클러 헤드 교체" maxlength="60"
          style="padding:9px 12px;border-radius:var(--r-sm);border:1px solid var(--bd);background:var(--bg3);color:var(--fg);font-size:14px;outline:none">
      </label>
      <!-- 메모 -->
      <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">
        메모 (선택)
        <textarea id="prm-note" rows="2" maxlength="300" placeholder="특이사항, 작업 내용 등"
          style="padding:9px 12px;border-radius:var(--r-sm);border:1px solid var(--bd);background:var(--bg3);color:var(--fg);font-size:13px;resize:vertical;outline:none;font-family:inherit"></textarea>
      </label>

      <!-- 쌍 목록 -->
      <div id="prm-pairs-list" style="display:grid;grid-template-columns:1fr;gap:10px"></div>
      <!-- 쌍 추가 버튼 -->
      <button type="button" onclick="prmAddPair()"
        style="padding:8px;border-radius:var(--r-sm);border:1px dashed var(--bd2);background:transparent;color:var(--sub);font-size:12px;cursor:pointer;font-family:inherit;transition:.15s"
        onmouseenter="this.style.borderColor='var(--accent)';this.style.color='var(--link)'"
        onmouseleave="this.style.borderColor='var(--bd2)';this.style.color='var(--sub)'">
        ＋ 사진 쌍 추가
      </button>
      <!-- 숨겨진 파일 입력 -->
      <input type="file" id="prm-file-input" accept="image/*" style="display:none" onchange="prmHandleFile(this)">
    </div>
  </div>

  <!-- 하단 액션 -->
  <div id="prm-actions-list" class="modal-actions" style="flex-shrink:0;border-top:1px solid var(--bd)">
    <button class="btn ghost" onclick="closeModal('photoReportModal')">닫기</button>
  </div>
  <div id="prm-actions-new" class="modal-actions" style="flex-shrink:0;border-top:1px solid var(--bd);display:none">
    <button type="button" class="btn ghost" onclick="prmTab('list')">← 목록으로</button>
    <button type="button" class="btn" id="prm-save-btn" onclick="prmSaveReport()"
      style="background:#ecfdf3;border-color:#16a34a;color:#15803d">💾 보고서 저장</button>
  </div>
</dialog>

<!-- ★ 공사사진 보고서 — 인쇄용 뷰 -->
<div id="prm-print-area" style="display:none"></div>

<!-- ★ 마을 수정 모달 -->
<dialog id="editClientModal" class="flex-col" style="min-width:min(96vw,480px);max-height:90vh">
  <form method="post" id="editClientForm" style="display:flex;flex-direction:column;height:100%">
    <div class="modal-head">
      <strong id="ecm-title">마을 수정</strong>
      <button type="button" class="btn ghost" onclick="closeModal('editClientModal')">✕</button>
    </div>
    <div class="modal-body" style="overflow-y:auto;flex:1;display:grid;gap:10px">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="client_edit">
      <input type="hidden" name="id" id="ecm-id">

      <div style="font-size:11px;color:#64748b;font-weight:600;letter-spacing:.05em;padding-bottom:4px;border-bottom:1px solid #e3e8f0">기본 정보</div>

      <label>마을명
        <input name="name" id="ecm-name" required>
      </label>
      <label>주소
        <input name="address" id="ecm-address" placeholder="표시용 주소">
      </label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <label>전화번호
          <input name="phone" id="ecm-phone" placeholder="010-0000-0000">
        </label>
        <label>종합정밀점검
          <input name="birth" id="ecm-birth" placeholder="YYYY-MM-DD">
        </label>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <label>안전관리자명
          <input name="safety_name" id="ecm-safety-name">
        </label>
        <label>작동기능점검
          <input name="safety_birth" id="ecm-safety-birth" placeholder="YYYY-MM-DD">
        </label>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <label>위도(lat)
          <input name="lat" id="ecm-lat" placeholder="37.000000">
        </label>
        <label>경도(lng)
          <input name="lng" id="ecm-lng" placeholder="126.000000">
        </label>
      </div>

      <div style="font-size:11px;color:#64748b;font-weight:600;letter-spacing:.05em;padding:8px 0 4px;border-bottom:1px solid #e3e8f0;display:flex;align-items:center;justify-content:space-between">
        <span>추가 필드</span>
        <button type="button" onclick="addExtraField()" style="background:#1e3a5f;border:1px solid #2563eb;color:#93c5fd;border-radius:7px;padding:3px 10px;font-size:12px;cursor:pointer">＋ 필드 추가</button>
      </div>
      <div id="ecm-extra-fields" style="display:grid;gap:8px"></div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn ghost" onclick="closeModal('editClientModal')">취소</button>
      <button class="btn" type="submit">저장</button>
    </div>
  </form>
</dialog>

<!-- 퀘스트 모달 -->
<dialog id="taskModal">
  <div class="modal-head"><strong>할 일</strong><button type="button" class="btn ghost" onclick="closeModal('taskModal')">✕</button></div>
  <div class="modal-body">
    <form method="post" style="display:grid;gap:10px;margin-bottom:10px">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="action" value="task_create">
      <label>제목<input name="title" required></label><label>기한<input type="date" name="due"></label>
      <button class="btn" style="width:100%">추가</button>
    </form>
    <div>
      <div class="mut" style="margin-bottom:6px">총 <?=h((string)$task_total)?> · 미완료 <?=h((string)$task_open)?></div>
      <?php foreach($tasks_view as $t): ?>
        <div style="display:flex;gap:8px;align-items:center;margin:6px 0;<?=!empty($t['done'])?'opacity:.6;text-decoration:line-through':''?>">
          <form method="post" style="margin:0">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="action" value="task_toggle">
            <input type="hidden" name="id" value="<?=h($t['id'])?>"><input type="checkbox" <?=!empty($t['done'])?'checked':''?> onchange="this.form.submit()">
          </form>
          <div style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis"><?=h((string)$t['title'])?></div>
          <div class="mut"><?=h((string)($t['due'] ?? ''))?></div>
          <form method="post" onsubmit="return confirm('삭제할까요?');" style="margin:0">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="action" value="task_delete">
            <input type="hidden" name="id" value="<?=h($t['id'])?>"><button class="btn warn">삭제</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if ($task_total - $task_open > 0): ?>
        <form method="post" style="text-align:right;margin-top:6px">
          <input type="hidden" name="action" value="task_clear_done"><input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <button class="btn ghost">완료 항목 비우기</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="modal-actions"><button class="btn ghost" onclick="closeModal('taskModal')">닫기</button></div>
</dialog>

<!-- 마을 목록 모달 -->
<dialog id="clientListModal">
  <div class="modal-head">
    <strong>마을</strong>
    <button type="button" class="btn ghost" onclick="closeModal('clientListModal')">✕</button>
  </div>
  <div class="modal-body">
    <input id="clm-q" placeholder="검색: 이름/주소" oninput="renderClientList()" />
    <div id="clm-list" style="margin-top:10px;max-height:48vh;overflow:auto;display:grid;gap:8px"></div>
  </div>
  <div class="modal-actions">
    <button class="btn ghost" onclick="closeModal('clientListModal')">닫기</button>
  </div>
</dialog>

<!-- ★ 날짜 코멘트 모달 -->
<dialog id="dayNoteModal">
  <div class="modal-head">
    <strong id="dn-title">날짜 코멘트</strong>
    <button type="button" class="btn ghost" onclick="closeModal('dayNoteModal')">✕</button>
  </div>
  <div class="modal-body">
    <form method="post" style="display:grid;gap:8px;margin-bottom:12px">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="daynote_add">
      <input type="hidden" name="date" id="dn-date">
      <label>코멘트
        <textarea name="text" rows="2" placeholder="메모를 입력하세요" required></textarea>
      </label>
      <button class="btn">추가</button>
    </form>
    <div id="dn-list" style="display:grid;gap:8px"></div>
  </div>
  <div class="modal-actions">
    <button class="btn ghost" onclick="closeModal('dayNoteModal')">닫기</button>
  </div>
</dialog>

<!-- ★ 날짜 이벤트 상세 모달 -->
<dialog id="dayEventsModal">
  <div class="modal-head">
    <strong id="dem-title">이벤트</strong>
    <button type="button" class="btn ghost" onclick="closeModal('dayEventsModal')">✕</button>
  </div>
  <div class="modal-body" id="dem-body" style="display:grid;gap:8px"></div>
  <div style="display:flex;gap:8px;align-items:center;padding:8px 0;border-top:1px solid var(--bd2);margin-top:4px;flex-wrap:wrap">
    <label style="font-size:12px;color:var(--sub);display:flex;align-items:center;gap:5px">
      배정 수 <input type="number" id="dem-perday" value="5" min="1" max="30" style="width:56px;padding:5px;border-radius:6px;border:1px solid var(--bd2);background:var(--card2);color:var(--fg)">
    </label>
    <button class="btn" id="dem-assign-btn" type="button" onclick="demAssignDay()" style="background:#ecfdf3;border-color:#16a34a;color:#15803d">📍 이 날에 배정</button>
    <button class="btn warn" id="dem-clearday-btn" type="button" onclick="demClearDay()" style="margin-left:auto">🗑️ 이 날 전체 삭제</button>
  </div>
  <div class="modal-actions">
    <button class="btn" id="dem-note-btn">💬 코멘트</button>
    <button class="btn ghost" onclick="openDayColorModal(document.getElementById('dayEventsModal').dataset.date);closeModal('dayEventsModal')">🎨 색상</button>
    <button class="btn ghost" onclick="closeModal('dayEventsModal')">닫기</button>
  </div>
</dialog>

<!-- ★ 날짜 색상/라벨 설정 모달 -->
<dialog id="dayColorModal" style="min-width:min(92vw,360px)">
  <div class="modal-head">
    <strong id="dcm-title">🎨 색상 설정</strong>
    <button type="button" class="btn ghost" onclick="closeModal('dayColorModal')">✕</button>
  </div>
  <form method="post" id="dcm-form">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
    <input type="hidden" name="action" value="day_color_save">
    <input type="hidden" name="date" id="dcm-date">
    <div class="modal-body" style="display:grid;gap:14px">
      <!-- 프리셋 -->
      <div>
        <div style="font-size:11px;color:#64748b;margin-bottom:8px;font-weight:600;letter-spacing:.04em">빠른 선택</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach ([
            ['#ef4444','공휴일'],['#f97316','기념일'],['#eab308','주의'],
            ['#22c55e','완료'],['#3b82f6','일정'],['#a855f7','특별'],
            ['#ec4899','행사'],['#14b8a6','점검일'],
          ] as [$col,$lbl]): ?>
          <button type="button" onclick="dcmSet('<?=h($col)?>','<?=h($lbl)?>')"
            style="display:flex;flex-direction:column;align-items:center;gap:3px;background:transparent;border:0;cursor:pointer">
            <span style="width:26px;height:26px;border-radius:50%;background:<?=h($col)?>;display:block;border:2px solid <?=h($col)?>44"></span>
            <span style="font-size:10px;color:#64748b"><?=h($lbl)?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- 직접 입력 -->
      <div style="display:grid;grid-template-columns:36px 1fr;gap:10px;align-items:center">
        <input type="color" name="color" id="dcm-color" value="#3b82f6"
          style="width:36px;height:36px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;cursor:pointer;padding:2px"
          oninput="dcmPreview()">
        <input name="label" id="dcm-label" maxlength="10" placeholder="라벨 (예: 공휴일, 마감…)"
          style="padding:10px 12px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:13px"
          oninput="dcmPreview()">
      </div>
      <!-- 미리보기 -->
      <div id="dcm-preview" style="border:1px solid #e3e8f0;border-radius:10px;padding:10px 14px;background:#f8fafc;display:flex;align-items:center;gap:8px">
        <span id="dcm-prev-num" style="font-size:22px;font-weight:700;color:#3b82f6">15</span>
        <span id="dcm-prev-lbl" style="font-size:11px;padding:2px 8px;border-radius:999px;background:#3b82f633;color:#93c5fd;border:1px solid #3b82f655;display:none"></span>
      </div>
    </div>
    <div class="modal-actions" style="justify-content:space-between">
      <button type="button" onclick="dcmClear()" style="background:transparent;border:1px solid var(--inspect-bd);color:var(--inspect);border-radius:8px;padding:8px 14px;cursor:pointer;font-size:13px">🗑 초기화</button>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn ghost" onclick="closeModal('dayColorModal')">취소</button>
        <button type="submit" class="btn">저장</button>
      </div>
    </div>
  </form>
</dialog>

<!-- ★ 유령 마을 모달 -->
<dialog id="ghostModal" class="flex-col" style="min-width:min(96vw,680px);max-height:90vh;padding:0">
  <div class="modal-head" style="flex-shrink:0">
    <div>
      <strong style="font-size:15px">👻 유령 마을 — 지도 좌표 없음</strong>
      <div style="font-size:11px;color:var(--sub);margin-top:3px">이벤트 0건 = 삭제 권장 / 이벤트 있음 = 좌표만 추가</div>
    </div>
    <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">✕</button>
  </div>

  <!-- 액션 바 -->
  <div style="flex-shrink:0;display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--bd);background:var(--bg3);flex-wrap:wrap">
    <label style="display:flex;align-items:center;gap:6px;margin:0;font-size:12px;color:var(--mut);cursor:pointer;letter-spacing:0;font-weight:500">
      <input type="checkbox" id="ghost-check-all" onchange="ghostCheckAll(this.checked)"
        style="width:14px;height:14px;accent-color:var(--accent);cursor:pointer">
      전체 선택
    </label>
    <div style="flex:1;min-width:120px">
      <input type="text" id="ghost-search" placeholder="이름 검색…" oninput="ghostFilter(this.value)"
        style="width:100%;padding:5px 10px;border-radius:var(--r-sm);border:1px solid var(--bd);background:var(--bg3);color:var(--fg);font-size:12px;outline:none">
    </div>
    <span id="ghost-sel-count" style="font-size:12px;color:var(--mut)">0개 선택</span>
    <button class="btn warn" id="ghost-del-btn" onclick="ghostDeleteSelected()" disabled
      style="padding:7px 14px;font-size:12px;opacity:.4">🗑 선택 삭제</button>
  </div>

  <!-- 목록 -->
  <div id="ghost-list" style="flex:1;overflow-y:auto"></div>

  <div class="modal-actions" style="flex-shrink:0;border-top:1px solid var(--bd);justify-content:space-between">
    <span id="ghost-total-info" style="font-size:12px;color:var(--sub)"></span>
    <button class="btn ghost" onclick="this.closest('dialog').close()">닫기</button>
  </div>
</dialog>

<!-- ★ D-DAY 설정 모달 -->
<dialog id="ddayModal" style="min-width:min(92vw,360px)">
  <div class="modal-head">
    <strong>📅 D-DAY 설정</strong>
    <button type="button" class="btn ghost" onclick="closeModal('ddayModal')">✕</button>
  </div>
  <div class="modal-body" style="display:grid;gap:14px">
    <div id="dday-client-name" style="font-size:14px;font-weight:700;color:var(--fg2)"></div>

    <!-- 현재 D-Day 표시 -->
    <div id="dday-current" style="display:none;padding:10px 14px;border-radius:10px;background:var(--bg3);border:1px solid var(--bd);text-align:center"></div>

    <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">
      날짜 선택
      <input type="date" id="dday-input"
        style="padding:8px 12px;background:var(--bg3);border:1px solid var(--bd);border-radius:var(--r-sm);color:var(--fg);font-size:14px;outline:none">
    </label>
  </div>
  <div class="modal-actions">
    <button class="btn warn" id="dday-clear-btn" onclick="saveDday(null)" style="margin-right:auto">🗑 해제</button>
    <button class="btn ghost" onclick="closeModal('ddayModal')">취소</button>
    <button class="btn" onclick="saveDday(document.getElementById('dday-input').value)">저장</button>
  </div>
</dialog>

<!-- ★ 전월 현황 모달 -->
<dialog id="prevMonthModal" class="flex-col" style="min-width:min(96vw,640px);max-height:90vh;padding:0;border-radius:var(--r-lg);border:1px solid var(--bd2);background:var(--bg2)">
  <div class="modal-head" style="flex-shrink:0;border-bottom:1px solid var(--bd)">
    <strong id="pmm-title" style="font-size:15px">📋 전월 현황</strong>
    <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">✕</button>
  </div>

  <!-- 필터 바 -->
  <div id="pmm-filter-bar" style="flex-shrink:0;display:flex;gap:6px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--bd);background:var(--bg3)">
    <button data-kind="all" class="pmm-btn pmm-active">전체</button>
    <button data-kind="visit"   class="pmm-btn" style="--c:#2dda7e;--cb:#ecfdf3">방문</button>
    <button data-kind="inspect" class="pmm-btn" style="--c:#ff5252;--cb:#fef2f2">점검</button>
    <button data-kind="as"      class="pmm-btn" style="--c:#fbbf24;--cb:#fffbeb">AS</button>
    <button data-kind="report"  class="pmm-btn" style="--c:#60a5fa;--cb:#eff6ff">보고서</button>
    <button data-kind="submit"  class="pmm-btn" style="--c:#fb923c;--cb:#fff7ed">이행완료</button>
    <button data-kind="plan"    class="pmm-btn" style="--c:#a78bfa;--cb:#f5f3ff">방문예정</button>
  </div>

  <!-- 통계 요약 -->
  <div id="pmm-stats" style="flex-shrink:0;display:flex;gap:6px;flex-wrap:wrap;padding:8px 16px;border-bottom:1px solid var(--bd);background:var(--bg)"></div>

  <!-- 목록 -->
  <div id="pmm-body" style="flex:1;overflow-y:auto;padding:14px 16px"></div>

  <div class="modal-actions" style="flex-shrink:0;border-top:1px solid var(--bd)">
    <button class="btn ghost" onclick="this.closest('dialog').close()">닫기</button>
  </div>
</dialog>

<style>
/* 전월현황 필터 버튼 */
.pmm-btn {
  padding:5px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:500;
  border:1px solid var(--bd2);background:transparent;color:var(--mut);cursor:pointer;
  font-family:inherit;transition:all .15s;
}
.pmm-btn:hover { border-color:var(--bd3);color:var(--fg2); }
.pmm-btn.pmm-active {
  background:var(--accent);border-color:var(--accent);color:#fff;
}
</style>

<script>
// ===== 서버 데이터 =====
const CLIENTS = <?= json_encode($clients, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const BUILDINGS = <?= json_encode($buildings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const DAY_COLORS = <?= json_encode($day_colors, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const TYPE = <?= json_encode($type) ?>;
const VISITED_THIS_MONTH = <?= json_encode(array_keys($visitedThisMonth), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>.reduce((m,id)=>{m[id]=true;return m;},{});
const INSPECTED_THIS_MONTH = <?= json_encode(array_keys($inspectedThisMonth), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>.reduce((m,id)=>{m[id]=true;return m;},{});
const PLANNED_THIS_MONTH = <?= json_encode(array_keys($plannedThisMonth), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>.reduce((m,id)=>{m[id]=true;return m;},{});
const CSRF = <?= json_encode($CSRF) ?>;
const COMPANY_NAME = <?= json_encode($COMPANY_NAME) ?>;
async function editCompanyName(){
  const cur = (COMPANY_NAME && COMPANY_NAME !== '거래처 관리 시스템') ? COMPANY_NAME : '';
  const name = prompt('상호명(회사명)을 입력하세요.\n화면 상단·견적서·보고서 표지 등에 표시됩니다.', cur);
  if (name === null) return;                 // 취소
  const fd = new FormData();
  fd.append('csrf', CSRF); fd.append('action','settings_save'); fd.append('company', name.trim());
  try{
    const res = await fetch(location.pathname, {method:'POST', body:fd}).then(r=>r.json());
    if(res.ok){ location.reload(); }
    else alert('저장 실패');
  }catch(e){ alert('네트워크 오류'); }
}
const DAYNOTES = <?= json_encode($daynotes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const EVENTS_BY_DATE = <?= json_encode($eventsByDate, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const PREV_MONTH_EVENTS = <?= json_encode($prevMonthEvents, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const PREV_YM = <?= json_encode($prevYm) ?>;
const TYPE_LABELS = {visit:'방문',inspect:'점검',as:'AS',report:'보고서',submit:'이행완료',plan:'방문예정'};
const GHOST_CLIENTS = <?= json_encode($ghostClients, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) ?>;

// 모달 helpers
function openModal(id){ document.getElementById(id).showModal(); }
function closeModal(id){
  document.getElementById(id).close();
  if (id === 'clientModal' && window.__cmPreviewMarker && typeof map !== 'undefined' && map){
    try { map.removeLayer(window.__cmPreviewMarker); window.__cmPreviewMarker = null; } catch(e){}
  }
}
function openClientModal(){ const el=document.getElementById('clientModal'); el.show(); }
function openTaskModal(){ openModal('taskModal'); }
function openClientListModal(){ openModal('clientListModal'); renderClientList(); }

/* ════════════════════════════════
   👻 유령 마을 모달
════════════════════════════════ */
let ghostData = GHOST_CLIENTS.map(c => ({...c, _deleted: false}));

window.openGhostModal = function() {
  renderGhostList('');
  document.getElementById('ghost-search').value = '';
  document.getElementById('ghost-check-all').checked = false;
  updateGhostSelCount();
  const info = document.getElementById('ghost-total-info');
  if (info) info.textContent = `총 ${ghostData.filter(c=>!c._deleted).length}개`;
  document.getElementById('ghostModal').showModal();
};

function renderGhostList(q) {
  const box = document.getElementById('ghost-list');
  box.innerHTML = '';
  const list = ghostData.filter(c => !c._deleted && (!q || c.name.toLowerCase().includes(q)));

  if (!list.length) {
    box.innerHTML = '<div style="padding:32px;text-align:center;color:#64748b;font-size:13px">' +
      (q ? '검색 결과가 없습니다.' : '👋 유령 마을가 없습니다!') + '</div>';
    return;
  }

  for (const c of list) {
    const hasEvents = c.total > 0;
    const row = document.createElement('div');
    row.id = `ghost-row-${c.id}`;
    row.style.cssText = 'display:grid;grid-template-columns:36px 1fr auto;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid rgba(20,40,80,.05);transition:background .12s';
    row.onmouseenter = () => row.style.background = 'rgba(20,40,80,.04)';
    row.onmouseleave = () => row.style.background = '';

    // 체크박스
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.dataset.id = c.id;
    chk.style.cssText = 'width:15px;height:15px;accent-color:var(--accent);cursor:pointer';
    chk.onchange = updateGhostSelCount;

    // 정보
    const info = document.createElement('div');
    info.style.minWidth = '0';
    const eventBadge = hasEvents
      ? `<span style="display:inline-block;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:600;background:#150f00;color:#fbbf24;border:1px solid #3d2c00">이벤트 ${c.total}건</span>`
      : `<span style="display:inline-block;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:600;background:#1c0606;color:#ff5252;border:1px solid #4a1010">기록 없음</span>`;
    const lastBadge = c.last
      ? `<span style="font-size:10px;color:var(--mut);margin-left:6px">최근 ${c.last}</span>` : '';
    const phoneTxt = c.phone ? `<span style="font-size:10px;color:var(--mut)"> · ${escapeHtml(c.phone)}</span>` : '';
    info.innerHTML = `
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px">
        <span style="font-size:13px;font-weight:600;color:#1a2436">${escapeHtml(c.name)}</span>
        ${eventBadge}${lastBadge}
      </div>
      <div style="font-size:11px;color:var(--mut)">${escapeHtml(c.addr||'주소 없음')}${phoneTxt}</div>
    `;

    // 버튼
    const btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:6px;flex-shrink:0';

    const editBtn = document.createElement('button');
    editBtn.textContent = '📍 좌표';
    editBtn.title = '마을 수정에서 좌표 입력';
    editBtn.style.cssText = 'padding:5px 10px;border-radius:7px;border:1px solid var(--report-bd);background:transparent;color:var(--report);font-size:11px;cursor:pointer;font-family:inherit;white-space:nowrap';
    editBtn.onmouseenter = () => editBtn.style.borderColor = '#3d86f5';
    editBtn.onmouseleave = () => editBtn.style.borderColor = '#1e3251';
    editBtn.onclick = () => {
      document.getElementById('ghostModal').close();
      // visitModal 경유해서 editClientModal 열기
      _editClientId = c.id;
      openEditClientModal();
    };

    const delBtn = document.createElement('button');
    delBtn.textContent = '🗑';
    delBtn.title = '삭제';
    delBtn.style.cssText = 'padding:5px 9px;border-radius:7px;border:1px solid var(--inspect-bd);background:transparent;color:var(--inspect);font-size:11px;cursor:pointer';
    delBtn.onclick = () => ghostDeleteOne(c.id, c.name, row);

    btns.appendChild(editBtn);
    btns.appendChild(delBtn);

    row.appendChild(chk);
    row.appendChild(info);
    row.appendChild(btns);
    box.appendChild(row);
  }
}

function updateGhostSelCount() {
  const checked = document.querySelectorAll('#ghost-list input[type=checkbox]:checked');
  const n = checked.length;
  const el = document.getElementById('ghost-sel-count');
  const btn = document.getElementById('ghost-del-btn');
  if (el) el.textContent = `${n}개 선택`;
  if (btn) { btn.disabled = n === 0; btn.style.opacity = n > 0 ? '1' : '.4'; }
}

function ghostCheckAll(checked) {
  document.querySelectorAll('#ghost-list input[type=checkbox]').forEach(c => c.checked = checked);
  updateGhostSelCount();
}

function ghostFilter(q) {
  q = q.trim().toLowerCase();
  renderGhostList(q);
  document.getElementById('ghost-check-all').checked = false;
  updateGhostSelCount();
}

function ghostDeleteOne(id, name, rowEl) {
  if (!confirm(`'${name}' 마을를 삭제할까요?`)) return;
  const fd = new FormData();
  fd.append('csrf', CSRF); fd.append('action', 'client_delete'); fd.append('id', id);
  fetch(location.href, {method:'POST', body:fd}).then(r => {
    if (r.ok || r.redirected) {
      rowEl.style.transition = 'opacity .2s';
      rowEl.style.opacity = '0';
      setTimeout(() => {
        rowEl.remove();
        const gc = ghostData.find(c => c.id === id);
        if (gc) gc._deleted = true;
        updateGhostSelCount();
        // 미등록 패널에서도 제거
        removeFromInactivePanel(id);
        // 지도 마커 제거
        if (markers && markers.has(id)) { group.removeLayer(markers.get(id)); markers.delete(id); }
        const remaining = ghostData.filter(c=>!c._deleted).length;
        const info = document.getElementById('ghost-total-info');
        if (info) info.textContent = `총 ${remaining}개`;
        if (remaining === 0) {
          document.getElementById('ghost-list').innerHTML =
            '<div style="padding:32px;text-align:center;color:#64748b;font-size:13px">👋 유령 마을가 없습니다!</div>';
          // 헤더 버튼도 숨기기
          document.querySelectorAll('button[onclick="openGhostModal()"]').forEach(b=>b.style.display='none');
        }
      }, 200);
    } else { alert('삭제 실패'); }
  }).catch(() => alert('네트워크 오류'));
}

window.ghostDeleteSelected = function() {
  const checked = [...document.querySelectorAll('#ghost-list input[type=checkbox]:checked')];
  if (!checked.length) return;
  const ids = checked.map(c => c.dataset.id);
  const names = ids.map(id => ghostData.find(c=>c.id===id)?.name||'').join(', ');
  if (!confirm(`${ids.length}개 마을를 삭제할까요?\n\n${names}`)) return;

  let done = 0;
  for (const id of ids) {
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'client_delete'); fd.append('id', id);
    fetch(location.href, {method:'POST', body:fd}).then(() => {
      const rowEl = document.getElementById(`ghost-row-${id}`);
      if (rowEl) rowEl.remove();
      const gc = ghostData.find(c=>c.id===id);
      if (gc) gc._deleted = true;
      removeFromInactivePanel(id);
      if (markers && markers.has(id)) { group.removeLayer(markers.get(id)); markers.delete(id); }
      done++;
      if (done === ids.length) {
        updateGhostSelCount();
        document.getElementById('ghost-check-all').checked = false;
        const remaining = ghostData.filter(c=>!c._deleted).length;
        const info = document.getElementById('ghost-total-info');
        if (info) info.textContent = `총 ${remaining}개`;
        if (remaining === 0) {
          document.getElementById('ghost-list').innerHTML =
            '<div style="padding:32px;text-align:center;color:#64748b;font-size:13px">👋 유령 마을가 없습니다!</div>';
          document.querySelectorAll('button[onclick="openGhostModal()"]').forEach(b=>b.style.display='none');
        }
      }
    });
  }
};

/* ════════════════════════════════
   📅 D-DAY 모달
════════════════════════════════ */
let _ddayClientId = null;

window.openDdayModal = function(clientId) {
  const c = CLIENTS.find(x => x.id === clientId);
  if (!c) return;
  _ddayClientId = clientId;

  document.getElementById('dday-client-name').textContent = c.name;

  // 현재 D-Day 표시
  const curBox = document.getElementById('dday-current');
  const clearBtn = document.getElementById('dday-clear-btn');
  if (c.dday) {
    const diff = calcDday(c.dday);
    const label = ddayLabel(diff);
    const cls = ddayClass(diff);
    const colors = {today:'#ff2d55', soon:'#ff8c00', future:'#90caf9', past:'#7f8fa6'};
    curBox.style.display = '';
    curBox.innerHTML = `<span style="font-size:22px;font-weight:800;color:${colors[cls]||'#aaa'}">${label}</span>
      <div style="font-size:12px;color:var(--sub);margin-top:4px">${c.dday}</div>`;
    document.getElementById('dday-input').value = c.dday;
    clearBtn.style.display = '';
  } else {
    curBox.style.display = 'none';
    document.getElementById('dday-input').value = '';
    clearBtn.style.display = 'none';
  }

  openModal('ddayModal');
};

window.saveDday = function(dateVal) {
  if (!_ddayClientId) return;
  const date = (dateVal && dateVal.match(/^\d{4}-\d{2}-\d{2}$/)) ? dateVal : null;

  const fd = new FormData();
  fd.append('csrf', CSRF); fd.append('action', 'client_dday');
  fd.append('id', _ddayClientId); fd.append('dday', date || '');

  fetch(location.href, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { alert('저장 실패'); return; }
      // CLIENTS 메모리 업데이트
      const c = CLIENTS.find(x => x.id === _ddayClientId);
      if (c) {
        c.dday = data.dday;
        // 마커 아이콘 갱신
        const m = markers && markers.get(_ddayClientId);
        if (m) m.setIcon(rebuildIcon(c, selectedIds.has(_ddayClientId)));
      }
      // D-DAY 패널 갱신
      refreshDdayPanel();
      closeModal('ddayModal');
    })
    .catch(() => alert('네트워크 오류'));
};

/* D-DAY 패널 DOM 실시간 갱신 */
function refreshDdayPanel() {
  const list = document.getElementById('dd-list');
  if (!list) return;

  const today = new Date(); today.setHours(0,0,0,0);
  // CLIENTS에서 dday 있는 것들만 추출, diff 계산
  let rows = CLIENTS
    .filter(c => c.dday && /^\d{4}-\d{2}-\d{2}$/.test(c.dday))
    .map(c => {
      const diff = Math.round((new Date(c.dday) - today) / 86400000);
      return { id:c.id, name:c.name, addr:c.addr||c.address||'', dday:c.dday, diff };
    });

  // 정렬: 미래(오늘 포함) 먼저 오름차순, 그 다음 과거 내림차순
  rows.sort((a,b) => {
    const af = a.diff >= 0, bf = b.diff >= 0;
    if (af !== bf) return af ? -1 : 1;
    return a.diff - b.diff;
  });

  if (!rows.length) {
    list.innerHTML = '<div class="dd-empty">설정된 D-DAY가 없습니다.<br><span style="font-size:10px;color:var(--sub)">거래처 클릭 → 📅 D-DAY</span></div>';
    return;
  }

  const clsMap = d => d===0?'today': d>0&&d<=7?'soon': d>0?'future':'past';
  const lblMap = d => d===0?'D-DAY': d>0?`D-${d}`:`D+${Math.abs(d)}`;

  list.innerHTML = rows.map(r => {
    const cls = clsMap(r.diff);
    const lbl = lblMap(r.diff);
    return `<div class="dd-row" onclick="focusOnClient('${r.id}')" title="지도에서 보기">
      <div class="dd-badge ${cls}">${lbl}</div>
      <div class="dd-info">
        <div class="dd-name">${escapeHtml(r.name)}</div>
        <div class="dd-date">${r.dday}</div>
      </div>
      <button class="dd-edit" onclick="event.stopPropagation();openDdayModal('${r.id}')" title="D-DAY 수정">✏️</button>
    </div>`;
  }).join('');

  // 헤더 카운트 업데이트
  const headSub = document.querySelector('.dday-panel-head div');
  if (headSub) headSub.textContent = `${rows.length}개 설정됨`;
}

/* ─── 미등록/전체 탭 전환 ─── */
let _ipTab = 'unreg';   // unreg | all
function switchIpTab(tab) {
  _ipTab = tab;
  document.querySelectorAll('.ip-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  const unreg = document.getElementById('ip-list');
  const all   = document.getElementById('ip-list-all');
  if (unreg) unreg.style.display = (tab === 'unreg') ? '' : 'none';
  if (all)   all.style.display   = (tab === 'all')   ? '' : 'none';
  const title = document.getElementById('ip-title');
  if (title) title.textContent = (tab === 'all') ? '📋 전체 거래처' : '📋 미등록 거래처';
  const si = document.getElementById('ip-search');
  filterInactive(si ? si.value : '');   // 탭 바꿔도 검색어 유지
}

/* ─── 거래처 패널 검색 (현재 활성 탭 기준) ─── */
function filterInactive(q) {
  q = (q||'').trim().toLowerCase();
  const listId = (_ipTab === 'all') ? '#ip-list-all' : '#ip-list';
  const rows = document.querySelectorAll(listId + ' .ip-row');
  let shown = 0;
  rows.forEach(r => {
    const match = !q || (r.dataset.name||'').includes(q);
    r.style.display = match ? '' : 'none';
    if (match) shown++;
  });
  const cnt = document.getElementById('ip-count');
  if (cnt) cnt.textContent = shown;
}

/* AJAX 등록 후: 미등록 패널에서 제거 + 전체 목록엔 ✓ 표시 */
function removeFromInactivePanel(clientId) {
  const row = document.querySelector(`#ip-list .ip-row[data-id="${clientId}"]`);
  if (row) {
    row.remove();
    const remaining = document.querySelectorAll('#ip-list .ip-row').length;
    if (_ipTab === 'unreg') {
      const cnt = document.getElementById('ip-count');
      if (cnt) cnt.textContent = remaining;
    }
    // 미등록 탭 숫자 갱신
    const tabN = document.querySelector('.ip-tab[data-tab="unreg"] .ip-tab-n');
    if (tabN) tabN.textContent = remaining;
    if (remaining === 0) {
      document.getElementById('ip-list').innerHTML =
        '<div class="ip-empty">🎉 이번 달 모든 마을에<br>등록이 완료되었습니다!</div>';
    }
  }
  // 전체 목록에서 해당 거래처에 ✓ 표시
  const allRow = document.querySelector(`#ip-list-all .ip-row[data-id="${clientId}"]`);
  if (allRow && !allRow.classList.contains('ip-done')) {
    allRow.classList.add('ip-done');
    const nameEl = allRow.querySelector('.ip-name');
    if (nameEl && !nameEl.querySelector('.ip-check')) {
      const chk = document.createElement('span');
      chk.className = 'ip-check';
      chk.title = '이번 달 등록 완료';
      chk.textContent = '✓';
      nameEl.prepend(chk, ' ');
    }
  }
}
function ajaxRemove(clientId, date, kind, onSuccess) {
  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('action', 'remove');
  fd.append('id', clientId);
  fd.append('date', date);
  fd.append('kind', kind);
  fetch(location.href, { method: 'POST', body: fd })
    .then(r => { if (r.ok || r.redirected) { onSuccess(); } else { alert('삭제 실패'); } })
    .catch(() => alert('네트워크 오류'));
}

/* 달력 EVENTS_BY_DATE 메모리 업데이트 */
function removeFromEventsByDate(clientId, date, kind) {
  if (!EVENTS_BY_DATE[date]) return;
  EVENTS_BY_DATE[date] = EVENTS_BY_DATE[date].filter(e => !(e.id === clientId && e.kind === kind));
  if (!EVENTS_BY_DATE[date].length) delete EVENTS_BY_DATE[date];
}

/* 전월 현황 모달 */
window.openPrevMonthModal = function() {
  const modal = document.getElementById('prevMonthModal');
  const body  = document.getElementById('pmm-body');
  const title = document.getElementById('pmm-title');
  const filterEl = document.getElementById('pmm-filter');
  title.textContent = `📋 전월 현황 — ${PREV_YM.replace('-','년 ')}월`;

  function renderPrev(filterKind) {
    body.innerHTML = '';
    let data = PREV_MONTH_EVENTS;
    if (filterKind) data = data.map(c => ({...c, rows: c.rows.filter(r=>r.kind===filterKind)})).filter(c=>c.rows.length);

    if (!data.length) {
      body.innerHTML = '<div style="text-align:center;padding:40px 0;color:#64748b;font-size:14px">해당 월의 기록이 없습니다.</div>';
      return;
    }
    const kindColor = {visit:'#15803d',inspect:'#dc2626',as:'#b45309',report:'#2563eb',submit:'#c2410c',plan:'#7c3aed'};
    const kindBg    = {visit:'#ecfdf3',inspect:'#fef2f2',as:'#fffbeb',report:'#eff6ff',submit:'#fff7ed',plan:'#f5f3ff'};

    for (const c of data) {
      const card = document.createElement('div');
      card.style.cssText = 'border:1px solid var(--bd);border-radius:12px;overflow:hidden;margin-bottom:10px';

      // 마을 헤더
      const head = document.createElement('div');
      head.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--card);border-bottom:1px solid var(--bd)';
      head.innerHTML = `
        <div>
          <div style="font-weight:600;font-size:14px;color:#1a2436">${escapeHtml(c.name)}</div>
          ${c.addr ? `<div style="font-size:11px;color:var(--mut);margin-top:2px">${escapeHtml(c.addr)}</div>` : ''}
        </div>
        <span style="font-size:11px;padding:3px 10px;border-radius:999px;background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe">${c.rows.length}건</span>
      `;

      const rows = document.createElement('div');
      rows.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;padding:10px 14px;background:var(--bg2)';

      for (const r of c.rows) {
        const chip = document.createElement('div');
        const col  = kindColor[r.kind] || '#64748b';
        const bg   = kindBg[r.kind] || '#f1f5f9';
        chip.style.cssText = `display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:8px;border:1px solid ${col}44;background:${bg};font-size:12px`;
        chip.innerHTML = `
          <span style="color:${col};font-weight:600">${TYPE_LABELS[r.kind]||r.kind}</span>
          <span style="color:var(--mut)">${r.date}</span>
          ${r.kind==='as' && r.count>1 ? `<span style="color:${col}">×${r.count}</span>` : ''}
        `;
        rows.appendChild(chip);
      }

      card.appendChild(head);
      card.appendChild(rows);
      body.appendChild(card);
    }
  }

  // 통계 요약 계산
  const statsEl = document.getElementById('pmm-stats');
  if (statsEl) {
    const counts = {};
    for (const c of PREV_MONTH_EVENTS) for (const r of c.rows) counts[r.kind] = (counts[r.kind]||0) + 1;
    const kindColor2 = {visit:'#2dda7e',inspect:'#ff5252',as:'#fbbf24',report:'#60a5fa',submit:'#fb923c',plan:'#a78bfa'};
    statsEl.innerHTML = Object.entries(counts).map(([k,n])=>
      `<span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;padding:3px 9px;border-radius:999px;background:${kindColor2[k]||'#aaa'}18;color:${kindColor2[k]||'#aaa'};border:1px solid ${kindColor2[k]||'#aaa'}33">
        <b>${TYPE_LABELS[k]||k}</b> ${n}건
      </span>`
    ).join('') || '<span style="font-size:12px;color:#64748b">기록 없음</span>';
  }

  // 필터 버튼 (첫 오픈 시 1회만 바인딩)
  const filterBar = document.getElementById('pmm-filter-bar');
  if (filterBar && !filterBar.dataset.init) {
    filterBar.dataset.init = '1';
    filterBar.querySelectorAll('button[data-kind]').forEach(btn => {
      btn.onclick = () => {
        filterBar.querySelectorAll('button').forEach(b=>b.classList.remove('pmm-active'));
        btn.classList.add('pmm-active');
        renderPrev(btn.dataset.kind === 'all' ? '' : btn.dataset.kind);
      };
    });
  }

  // 필터 초기화 → 전체 렌더
  filterBar?.querySelectorAll('button').forEach((b,i) => { if(i===0) b.classList.add('pmm-active'); else b.classList.remove('pmm-active'); });
  renderPrev('');

  modal.showModal();
};

// 마을 목록 렌더링 (+ 삭제)
function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
function renderClientList(){
  const q = (document.getElementById('clm-q')?.value || '').trim().toLowerCase();
  const box = document.getElementById('clm-list');
  if (!box) return;
  box.innerHTML = '';
  const arr = [...CLIENTS].sort((a,b)=> (a.name||'').localeCompare(b.name||'', 'ko'));
  for (const c of arr){
    const hay = `${c.name||''} ${c.address||c.addr||''}`.toLowerCase();
    if (q && !hay.includes(q)) continue;

    const row = document.createElement('div');
    row.style.border = '1px solid #223144';
    row.style.borderRadius = '10px';
    row.style.padding = '8px';
    row.style.display = 'grid';
    row.style.gap = '8px';

    const title = document.createElement('div');
    title.style.display = 'flex';
    title.style.alignItems = 'center';
    title.style.justifyContent = 'space-between';
    title.style.gap = '8px';

    const left = document.createElement('div');
    left.style.minWidth = '0';
    left.innerHTML = `<div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(c.name||'')}</div>
                      <div class="mut" style="font-size:12px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(c.address||c.addr||'')}</div>`;

    const right = document.createElement('div');
    right.style.display = 'flex';
    right.style.gap = '6px';
    right.style.flexShrink = '0';

    const btnVisit = document.createElement('button');
    btnVisit.className = 'btn';
    btnVisit.textContent = '＋ 일정';
    btnVisit.onclick = ()=> { openVisitFor(c.id, c.name||''); };

    const btnPlan = document.createElement('button');
    btnPlan.className = 'btn';
    btnPlan.textContent = '📅 예정';
    btnPlan.style.cssText = 'background:var(--plan-bg);border-color:var(--plan-bd);color:var(--plan)';
    btnPlan.onclick = ()=> { openPlanFor(c.id, c.name||''); };

    const btnFocus = document.createElement('button');
    btnFocus.className = 'btn ghost';
    btnFocus.textContent = '지도';
    btnFocus.title = '지도에서 위치로 이동';
    btnFocus.onclick = ()=> { focusOnClient(c.id); };

    const formDel = document.createElement('form');
    formDel.method = 'post';
    formDel.style.margin = '0';
    formDel.onsubmit = ()=> confirm(`'${c.name||''}' 마을를 삭제할까요?`);
    formDel.innerHTML = `
      <input type="hidden" name="csrf" value="${CSRF}">
      <input type="hidden" name="action" value="client_delete">
      <input type="hidden" name="id" value="${(c.id||'').replace(/"/g,'&quot;')}">
      <button class="btn warn">삭제</button>
    `;

    right.appendChild(btnVisit);
    right.appendChild(btnPlan);
    right.appendChild(btnFocus);
    right.appendChild(formDel);
    title.appendChild(left);
    title.appendChild(right);
    row.appendChild(title);

    box.appendChild(row);
  }
}

// ===== 날짜 코멘트 모달 =====
function openDayNotes(dateStr){
  document.getElementById('dn-title').textContent = `날짜 코멘트 — ${dateStr}`;
  document.getElementById('dn-date').value = dateStr;

  const wrap = document.getElementById('dn-list');
  wrap.innerHTML = '';
  const items = (DAYNOTES[dateStr] || []);
  if (items.length === 0){
    const empty = document.createElement('div');
    empty.className = 'mut';
    empty.textContent = '아직 코멘트가 없습니다.';
    wrap.appendChild(empty);
  } else {
    for (const it of items){
      const card = document.createElement('div');
      card.style.border = '1px solid #223144';
      card.style.borderRadius = '10px';
      card.style.padding = '8px';
      card.style.display = 'flex';
      card.style.gap = '8px';
      card.style.alignItems = 'flex-start';
      card.style.justifyContent = 'space-between';

      const text = document.createElement('div');
      text.style.whiteSpace = 'pre-wrap';
      text.style.flex = '1';
      text.innerHTML = `<div>${escapeHtml(it.text||'')}</div><div class="mut" style="font-size:12px;margin-top:4px">${escapeHtml(it.at||'')}</div>`;

      const form = document.createElement('form');
      form.method = 'post';
      form.style.margin = '0';
      form.onsubmit = ()=> confirm('이 코멘트를 삭제할까요?');
      form.innerHTML = `
        <input type="hidden" name="csrf" value="${CSRF}">
        <input type="hidden" name="action" value="daynote_delete">
        <input type="hidden" name="date" value="${dateStr.replace(/"/g,'&quot;')}">
        <input type="hidden" name="nid" value="${(it.nid||'').replace(/"/g,'&quot;')}">
        <button class="btn warn">삭제</button>
      `;

      card.appendChild(text);
      card.appendChild(form);
      wrap.appendChild(card);
    }
  }

  openModal('dayNoteModal');
}

// ===== 지도 =====
let map, markers, group, CURRENT_EDIT_ID = null;
map = L.map('map', { zoomControl:true, attributionControl:true });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);
markers = new Map(); group = L.featureGroup().addTo(map);

function escapeHtmlLocal(s){ return (s+'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
function clientVisitDates(c) {
  // 방문/점검 날짜를 최신순으로 정리해서 반환
  const entries = [];
  for (const d of (c.visits||[])) entries.push({d, kind:'방문'});
  for (const d of (c.inspects||[])) entries.push({d, kind:'점검'});
  for (const d of (c.reports||[])) entries.push({d, kind:'보고서'});
  for (const d of (c.submits||[])) entries.push({d, kind:'이행완료'});
  for (const d of (c.plans||[])) entries.push({d, kind:'방문예정'});
  for (const [d, cnt] of Object.entries(c.as||{})) entries.push({d, kind:`AS×${cnt}`});
  entries.sort((a,b)=> b.d.localeCompare(a.d));
  return entries;
}

// ── 선택 모드 상태 ──
let selectMode = false;
const selectedIds = new Set();

function getMarkerCls(id) {
  if (INSPECTED_THIS_MONTH[id]) return ' inspect';
  if (VISITED_THIS_MONTH[id]) return ' green';
  if (PLANNED_THIS_MONTH[id]) return ' plan';
  return ' unreg';
}

/* D-Day 계산 */
function calcDday(dateStr) {
  if (!dateStr) return null;
  const today = new Date(); today.setHours(0,0,0,0);
  const target = new Date(dateStr); target.setHours(0,0,0,0);
  return Math.round((target - today) / 86400000); // 양수=미래, 0=오늘, 음수=지남
}
function ddayLabel(diff) {
  if (diff === 0) return 'D-DAY';
  if (diff > 0)  return `D-${diff}`;
  return `D+${Math.abs(diff)}`;
}
function ddayClass(diff) {
  if (diff === 0)        return 'today';
  if (diff > 0 && diff <= 7) return 'soon';
  if (diff > 0)          return 'future';
  return 'past';
}

function rebuildIcon(c, selected) {
  const cls = selected ? ' selected' : getMarkerCls(c.id);
  const unregBadge = (cls === ' unreg') ? '<span class="unreg-badge">미방문</span>' : '';
  /* 메모가 있으면 마커에 표시를 붙인다. '중요'면 붉게 깜빡인다. */
  const hasMemo = !!(c.memo && String(c.memo).trim());
  const memoPin = hasMemo
    ? `<span class="memo-pin${c.flag ? ' important' : ''}" title="${escapeHtmlLocal(String(c.memo).slice(0,60))}">!</span>`
    : '';
  const tipMemo = hasMemo ? '\n📌 '+String(c.memo).slice(0,60) : '';
  const bubble = `<div class="bubble${cls}" title="${escapeHtmlLocal(c.name)}${c.addr?' · '+escapeHtmlLocal(c.addr):''}${escapeHtmlLocal(tipMemo)}">
    <span class="dot"></span><span class="txt">${escapeHtmlLocal(c.name)}</span>${unregBadge}${memoPin}</div>`;

  let html;
  if (c.dday) {
    const diff = calcDday(c.dday);
    const tag = `<div class="dday-tag ${ddayClass(diff)}">${ddayLabel(diff)}</div>`;
    html = `<div class="name-marker-wrap">${tag}${bubble}</div>`;
  } else {
    html = bubble;
  }
  return L.divIcon({ className:'name-marker', html, iconSize:null, iconAnchor:[12,12] });
}

function updateActionBar() {
  const bar = document.getElementById('bulk-action-bar');
  const cnt = document.getElementById('bulk-count-num');
  if (selectedIds.size > 0) {
    bar.classList.add('show');
    cnt.textContent = selectedIds.size;
  } else {
    bar.classList.remove('show');
  }
}

function enterSelectMode(id) {
  selectMode = true;
  // 선택 모드 첫 진입 시 날짜를 오늘로 초기화 (이미 값 있으면 유지)
  const di = document.getElementById('bulk-date');
  if (di && !di.value) di.value = new Date().toISOString().slice(0,10);
  toggleSelect(id);
}

function toggleSelect(id) {
  const c = CLIENTS.find(x => x.id === id);
  if (!c) return;
  const m = markers.get(id);
  if (!m) return;
  if (selectedIds.has(id)) {
    selectedIds.delete(id);
    m.setIcon(rebuildIcon(c, false));
  } else {
    selectedIds.add(id);
    m.setIcon(rebuildIcon(c, true));
  }
  if (selectedIds.size === 0) selectMode = false;
  updateActionBar();
}

window.exitSelectMode = function() {
  selectMode = false;
  selectedIds.forEach(id => {
    const c = CLIENTS.find(x => x.id === id);
    const m = markers.get(id);
    if (c && m) m.setIcon(rebuildIcon(c, false));
  });
  selectedIds.clear();
  updateActionBar();
};

window.bulkAction = function(kind, action) {
  if (!selectedIds.size) return;
  const dateInput = document.getElementById('bulk-date');
  const today = dateInput?.value || new Date().toISOString().slice(0,10);
  const names = [...selectedIds].map(id => CLIENTS.find(x=>x.id===id)?.name||'').join(', ');
  if (!confirm(`${selectedIds.size}개 마을를 ${today} [${kind}] 처리할까요?\n\n${names}`)) return;
  const ids = [...selectedIds];
  let i = 0;
  function next() {
    if (i >= ids.length) { location.reload(); return; }
    const id = ids[i++];
    const form = document.createElement('form');
    form.method = 'post'; form.style.display = 'none';
    form.innerHTML = `<input name="csrf" value="${CSRF}"><input name="action" value="${action}"><input name="id" value="${id}"><input name="date" value="${today}"><input name="kind" value="${kind}"><input name="count" value="1">`;
    document.body.appendChild(form);
    if (i === ids.length) { form.submit(); }
    else { fetch(location.href, {method:'POST', body:new FormData(form)}).then(next); }
  }
  next();
};

function putMarker(c) {
  const {id, lat, lng} = c; if (lat==null||lng==null) return;
  const m = L.marker([lat,lng], { icon: rebuildIcon(c,false), zIndexOffset:200 }).addTo(map);

  let pressTimer = null;
  let longPressed = false;

  // ── PC: mousedown / mouseup ──
  m.on('mousedown', e => {
    longPressed = false;
    pressTimer = setTimeout(() => {
      longPressed = true;
      pressTimer = null;
      enterSelectMode(id);
    }, 500);
  });
  m.on('mouseup', e => {
    if (pressTimer !== null) {
      clearTimeout(pressTimer); pressTimer = null;
      if (!longPressed) {
        if (selectMode) toggleSelect(id);
        else openVisitFor(id, c.name||'');
      }
    }
  });
  m.on('mousemove', () => {
    if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
  });

  // ── 모바일: DOM 레벨 touch 이벤트 (Leaflet 우회) ──
  const el = m.getElement ? null : null; // getElement는 addTo 후 사용
  function attachTouch() {
    const el = m.getElement();
    if (!el) return;
    let tTimer = null;
    let tLong = false;

    el.addEventListener('touchstart', e => {
      tLong = false;
      tTimer = setTimeout(() => {
        tLong = true;
        tTimer = null;
        // 진동 피드백 (지원 기기)
        if (navigator.vibrate) navigator.vibrate(50);
        enterSelectMode(id);
      }, 500);
    }, { passive: true });

    el.addEventListener('touchend', e => {
      if (tTimer !== null) {
        clearTimeout(tTimer); tTimer = null;
        if (!tLong) {
          e.preventDefault();
          if (selectMode) toggleSelect(id);
          else openVisitFor(id, c.name||'');
        }
      }
    });

    el.addEventListener('touchmove', () => {
      if (tTimer) { clearTimeout(tTimer); tTimer = null; }
    }, { passive: true });
  }

  // getElement()는 지도에 추가된 후 사용 가능
  m.on('add', attachTouch);

  markers.set(id, m); group.addLayer(m);
}

CLIENTS.forEach(putMarker);
if (group.getLayers().length) map.fitBounds(group.getBounds().pad(0.2));
else map.setView([37.5665,126.9780], 11);

// 달력→지도 포커스
document.getElementById('cal-grid')?.addEventListener('click', (e)=>{
  const el = e.target.closest('.ev'); if(!el) return;
  const cid = el.dataset?.cid || el.getAttribute('data-cid'); if(cid && markers.has(cid)){ const m=markers.get(cid); map.setView(m.getLatLng(), Math.max(14,map.getZoom()), {animate:true}); }
});

// 지도 클릭 → 신규 마을 드로어 위/경도 채우기
map.on('click', (e)=>{
  const cm = document.getElementById('clientModal');
  if (cm?.open) {
    cm.querySelector('#cm-lat').value = e.latlng.lat.toFixed(6);
    cm.querySelector('#cm-lng').value = e.latlng.lng.toFixed(6);
  }
  const ecm = document.getElementById('editClientModal');
  if (ecm?.open) {
    document.getElementById('ecm-lat').value = e.latlng.lat.toFixed(6);
    document.getElementById('ecm-lng').value = e.latlng.lng.toFixed(6);
  }
  if (CURRENT_EDIT_ID) {
    const latInput = document.getElementById(`edit-lat-${CURRENT_EDIT_ID}`);
    const lngInput = document.getElementById(`edit-lng-${CURRENT_EDIT_ID}`);
    if (latInput && lngInput) {
      latInput.value = e.latlng.lat.toFixed(6);
      lngInput.value = e.latlng.lng.toFixed(6);
    }
  }
});

// 방문 모달
window.openVisitFor = (id, name) => {
  const vm = document.getElementById('visitModal');
  const c  = CLIENTS.find(x => x.id === id);

  _editClientId = id; // ★ 마을 수정 버튼용

  vm.querySelector('#vm-id').value   = id;
  vm.querySelector('#vm-title').textContent = name;
  vm.querySelector('#vm-date').value = new Date().toISOString().slice(0,10);

  /* 거래처 메모 불러오기 */
  const mt = vm.querySelector('#vm-memo-text');
  const mf = vm.querySelector('#vm-memo-flag');
  const ms = vm.querySelector('#vm-memo-st');
  if (mt) {
    mt.value = (c && c.memo) ? c.memo : '';
    if (mf) mf.checked = !!(c && c.flag);
    if (ms) ms.textContent = (c && c.memo_at) ? c.memo_at + ' 저장' : '';
  }

  const kindSel = vm.querySelector('#vm-kind');
  const asWrap  = vm.querySelector('#vm-as-wrap');
  asWrap.style.display = kindSel.value === 'as' ? '' : 'none';
  kindSel.onchange = () => asWrap.style.display = kindSel.value === 'as' ? '' : 'none';

  // 방문 이력 렌더링
  const histBox  = vm.querySelector('#vm-history');
  const histList = vm.querySelector('#vm-history-list');
  histList.innerHTML = '';

  if (c) {
    const entries = clientVisitDates(c);
    if (entries.length > 0) {
      histBox.style.display = '';
      const kindMap = {
        '방문':'visit','점검':'inspect','보고서':'report','이행완료':'submit','방문예정':'plan'
      };
      entries.forEach(e => {
        const kindKey = Object.keys(kindMap).find(k => e.kind.startsWith(k));
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;padding:5px 8px;border-radius:7px;border:1px solid #e3e8f0;background:var(--card);font-size:12px';
        const left = document.createElement('div');
        left.style.cssText = 'display:flex;gap:8px;align-items:center;flex:1';
        const badge = document.createElement('span');
        badge.style.cssText = 'font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;background:#172a47;color:#93c5fd';
        badge.textContent = e.kind;
        const dateSpan = document.createElement('span');
        dateSpan.style.color = '#e5e7eb';
        dateSpan.textContent = e.d;
        left.appendChild(badge);
        left.appendChild(dateSpan);

        // 삭제 버튼 (AJAX — 새로고침 없음)
        if (kindKey && kindMap[kindKey]) {
          const delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.textContent = '✕';
          delBtn.style.cssText = 'border:0;background:transparent;color:#64748b;cursor:pointer;font-size:12px;padding:2px 4px';
          delBtn.title = '이 날짜 기록 삭제';
          delBtn.onclick = () => {
            if (!confirm(`${e.d} ${e.kind} 기록을 삭제할까요?`)) return;
            ajaxRemove(id, e.d, kindMap[kindKey], () => {
              row.remove();
              removeFromEventsByDate(id, e.d, kindMap[kindKey]);
              // 달력 셀 실시간 업데이트
              const calEvs = [...document.querySelectorAll(`#cal-grid .cell[data-date="${e.d}"] .ev`)];
              for (const el of calEvs) {
                if (el.dataset.cid === id && el.classList.contains(kindMap[kindKey])) { el.remove(); break; }
              }
            });
          };
          row.appendChild(left);
          row.appendChild(delBtn);
        } else {
          row.appendChild(left);
        }
        histList.appendChild(row);
      });
    } else {
      histBox.style.display = '';
      histList.innerHTML = '<div style="font-size:12px;color:#64748b;padding:4px 0">방문 기록 없음</div>';
    }
  } else {
    histBox.style.display = 'none';
  }

  openModal('visitModal');
};

// 지도에서 특정 마을로 포커스
window.focusOnClient = (id)=>{
  if (!markers || !markers.has(id)) { alert('지도에 등록된 좌표가 없습니다.'); return; }
  const m = markers.get(id);
  map.setView(m.getLatLng(), Math.max(15, map.getZoom()), { animate:true });
};

/* ===== Pro Calendar Renderer ===== */
(function(){
  const grid = document.getElementById('cal-grid');
  if (!grid) return;

  const ym = <?= json_encode($ym) ?>;         // "YYYY-MM"
  const [Y, M] = ym.split('-').map(n=>parseInt(n,10));
  const first = new Date(Y, M-1, 1);
  const firstDow = first.getDay();            // 0=일
  const startDate = new Date(Y, M-1, 1 - firstDow);

  const cells = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(startDate); d.setDate(startDate.getDate() + i);
    const yyyy = d.getFullYear();
    const mm = ('0' + (d.getMonth()+1)).slice(-2);
    const dd = ('0' + d.getDate()).slice(-2);
    const iso = `${yyyy}-${mm}-${dd}`;
    const inMonth = (d.getMonth()+1) === M;
    const isWeekend = d.getDay() === 0 || d.getDay() === 6;
    const isToday = (new Date()).toISOString().slice(0,10) === iso;
    cells.push({ iso, day:d.getDate(), inMonth, isWeekend, isToday });
  }

  grid.innerHTML = '';
  for (let i=0;i<cells.length;i++){
    const c = cells[i];
    const cell = document.createElement('div');
    cell.className = 'cell';
    if (!c.inMonth) cell.classList.add('out-month');
    if (c.isWeekend) cell.classList.add('weekend');
    if (c.isToday) cell.classList.add('today');
    cell.dataset.date = c.iso;

    const bar = document.createElement('div'); bar.className='datebar';
    const num = document.createElement('span'); num.className='dnum'; num.textContent = c.day;
    bar.appendChild(num);

    // ★ 날짜 색상/라벨 적용
    const dc = DAY_COLORS[c.iso];
    if (dc) {
      if (dc.color) {
        cell.style.background = dc.color + '1a';
        cell.style.borderLeft = `3px solid ${dc.color}`;
        num.style.color = dc.color;
      }
      if (dc.label) {
        const lbl = document.createElement('span');
        lbl.style.cssText = `font-size:10px;font-weight:600;padding:1px 6px;border-radius:999px;background:${dc.color||'#3b82f6'}33;color:${dc.color||'#93c5fd'};border:1px solid ${dc.color||'#3b82f6'}55;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70px`;
        lbl.textContent = dc.label;
        bar.appendChild(lbl);
      }
    }

    // ★ 색상 설정 버튼 (항상 표시)
    const colorBtn = document.createElement('span');
    colorBtn.textContent = '🎨';
    colorBtn.title = '색상/라벨 설정';
    colorBtn.style.cssText = 'margin-left:auto;cursor:pointer;font-size:12px;opacity:0.3;transition:.15s';
    colorBtn.onmouseenter = ()=>{ colorBtn.style.opacity='1'; };
    colorBtn.onmouseleave = ()=>{ colorBtn.style.opacity='0.3'; };
    colorBtn.onclick = (e)=>{ e.stopPropagation(); openDayColorModal(c.iso); };
    bar.appendChild(colorBtn);

    // note count
    const notes = (DAYNOTES[c.iso] || []);
    if (notes.length){
      const noteBtn = document.createElement('span');
      noteBtn.className = 'note-dot';
      noteBtn.textContent = `💬 ${notes.length}`;
      noteBtn.title = '날짜 코멘트 보기/추가';
      noteBtn.onclick = (e)=>{ e.stopPropagation(); openDayNotes(c.iso); };
      bar.appendChild(noteBtn);
    }
    cell.appendChild(bar);

    // events (최대 4개 + more)
    const list = (EVENTS_BY_DATE[c.iso] || []).slice(); // [{id,name,kind,count}]
    list.sort((a,b)=>{
      if (a.name !== b.name) return a.name.localeCompare(b.name,'ko');
      const ord={submit:0,report:1,inspect:2,as:3,visit:4};
      return (ord[a.kind]??9)-(ord[b.kind]??9);
    });
    const maxShow = 4; let shown = 0;

    for (const ev of list){
      if (shown >= maxShow) break;
      const item = document.createElement('div');
      item.className = `ev ${ev.kind}`;
      item.title = `${ev.name} · ${ev.kind}`;
      const left = document.createElement('div');
      left.style.minWidth='0'; left.style.overflow='hidden'; left.style.textOverflow='ellipsis'; left.style.whiteSpace='nowrap';
      left.textContent = ev.name + (ev.kind==='as' && ev.count>1 ? ` ×${ev.count}`:'');
      const right = document.createElement('div'); right.style.display='flex'; right.style.gap='4px';

      // 삭제 버튼 (AJAX — 새로고침 없음)
      const delBtn = document.createElement('button');
      delBtn.className = 'mini-btn del';
      delBtn.title = '삭제';
      delBtn.setAttribute('aria-label', '삭제');
      delBtn.textContent = '🗑';
      delBtn.onclick = (e) => {
        e.stopPropagation();
        ajaxRemove(ev.id, c.iso, ev.kind, () => {
          item.remove();
          removeFromEventsByDate(ev.id, c.iso, ev.kind);
          // more 버튼 갱신
          const moreEl = cell.querySelector('.more');
          if (moreEl) {
            const remaining = (EVENTS_BY_DATE[c.iso]||[]).length - cell.querySelectorAll('.ev').length;
            if (remaining <= 0) moreEl.remove();
            else moreEl.textContent = `외 ${remaining}건…`;
          }
        });
      };
      right.appendChild(delBtn);

      item.appendChild(left); item.appendChild(right);
      item.setAttribute('data-cid', ev.id);
      cell.appendChild(item);
      shown++;
    }
    const remain = list.length - shown;
    if (remain > 0){
      const more = document.createElement('div');
      more.className='more';
      more.textContent = `외 ${remain}건…`;
      more.onclick = (e)=>{ e.stopPropagation(); openDayEventsModal(c.iso, list); };
      cell.appendChild(more);
    }

    // 날짜 클릭: 이벤트 상세 모달 (빈 날도 열어서 배정 가능)
    cell.onclick = ()=> {
      if (window.__msMode){ msPickDate(c.iso, cell); return; }
      openDayEventsModal(c.iso, EVENTS_BY_DATE[c.iso]||[]);
    };

    grid.appendChild(cell);
  }

  // 상세 모달
  window.openDayEventsModal = (dateStr, list)=>{
    document.getElementById('dem-title').textContent = `이벤트 — ${dateStr}`;
    // ✅ 추가: 이벤트 모달에 날짜 저장 (코멘트 버튼에서 사용)
    document.getElementById('dayEventsModal').dataset.date = dateStr;

    const body = document.getElementById('dem-body');
    body.innerHTML = '';

    if (!list || !list.length){
      const empty = document.createElement('div'); empty.className='mut'; empty.textContent='이 날의 이벤트가 없습니다.';
      body.appendChild(empty);
    } else {
      for (const ev of list){
        const row = document.createElement('div');
        row.style.display='grid';
        row.style.gridTemplateColumns='minmax(120px, 1fr) 80px 80px';
        row.style.alignItems='center';
        row.style.gap='8px';
        row.style.border='1px solid var(--bd2)';
        row.style.borderRadius='10px';
        row.style.padding='8px';

        const nm = document.createElement('div'); nm.textContent = ev.name;
        const kd = document.createElement('div'); kd.innerHTML = `<span class="badge">${ev.kind}${ev.kind==='as' && ev.count>1 ? ' ×'+ev.count:''}</span>`;

        const delBtn2 = document.createElement('button');
        delBtn2.className = 'btn warn';
        delBtn2.textContent = '삭제';
        delBtn2.onclick = () => {
          if (!confirm('삭제할까요?')) return;
          ajaxRemove(ev.id, dateStr, ev.kind, () => {
            row.remove();
            removeFromEventsByDate(ev.id, dateStr, ev.kind);
            // 달력 셀의 해당 ev 항목도 제거
            const calEl = document.querySelector(`#cal-grid .cell[data-date="${dateStr}"] .ev[data-cid="${ev.id}"]`);
            if (calEl) {
              // kind가 맞는 것만 제거
              const evKindEls = [...document.querySelectorAll(`#cal-grid .cell[data-date="${dateStr}"] .ev`)];
              for (const el of evKindEls) {
                if (el.dataset.cid === ev.id && el.classList.contains(ev.kind)) { el.remove(); break; }
              }
            }
            if (!body.querySelector('div[style*="grid"]')) {
              body.innerHTML = '<div class="mut">이 날의 이벤트가 없습니다.</div>';
            }
          });
        };

        row.appendChild(nm); row.appendChild(kd); row.appendChild(delBtn2);
        body.appendChild(row);
      }
    }
    openModal('dayEventsModal');
  };

  // ───── 여러 날 선택 배정 모드 ─────
  window.__msMode = false;
  let msDates = [];  // 선택된 날짜 'YYYY-MM-DD'

  window.msToggle = function(){
    window.__msMode = !window.__msMode;
    msDates = [];
    updateMsUI();
    // 선택 표시 초기화
    document.querySelectorAll('#cal-grid .cell.ms-picked').forEach(el=>{
      el.classList.remove('ms-picked'); el.style.boxShadow='';
    });
    const btn = document.getElementById('ms-toggle');
    if (window.__msMode){
      btn.textContent = '✕ 선택 모드 끄기';
      btn.style.background = '#2a1a66';
    } else {
      btn.textContent = '📌 여러 날 선택 배정';
      btn.style.background = '#1d1048';
    }
  };

  window.msPickDate = function(iso, cell){
    const i = msDates.indexOf(iso);
    if (i>=0){
      msDates.splice(i,1);
      cell.classList.remove('ms-picked');
      cell.style.boxShadow='';
    } else {
      msDates.push(iso);
      msDates.sort();
      cell.classList.add('ms-picked');
      cell.style.boxShadow='inset 0 0 0 2px #fac775';
    }
    updateMsUI();
  };

  function updateMsUI(){
    const info = document.getElementById('ms-info');
    const assign = document.getElementById('ms-assign');
    const cnt = document.getElementById('ms-count');
    if (window.__msMode){
      info.style.display=''; assign.style.display = msDates.length? '' : 'none';
      cnt.textContent = msDates.length;
    } else {
      info.style.display='none'; assign.style.display='none';
    }
  }

  window.msAssign = async function(){
    if (!msDates.length){ alert('날짜를 먼저 선택하세요.'); return; }
    const perDay = Math.max(1, parseInt(document.getElementById('ms-perday').value)||5);

    // 미등록(이번 달 활동 없는) + 좌표 있는 거래처
    const activeIds = new Set();
    for (const k in EVENTS_BY_DATE){ for (const ev of EVENTS_BY_DATE[k]){ activeIds.add(ev.id); } }
    const cand = CLIENTS.filter(c=> !activeIds.has(c.id) && c.lat!=null && c.lng!=null && !isNaN(c.lat) && !isNaN(c.lng))
                        .map(c=>({id:c.id,name:c.name,lat:+c.lat,lng:+c.lng}));
    if (!cand.length){ alert('배정할 미등록 거래처가 없습니다. (좌표 있는 거래처 기준)'); return; }

    // 가까운 순 정렬
    function dist(a,b){ const R=6371,r=x=>x*Math.PI/180;
      const dLat=r(b.lat-a.lat),dLng=r(b.lng-a.lng);
      const s=Math.sin(dLat/2)**2+Math.cos(r(a.lat))*Math.cos(r(b.lat))*Math.sin(dLng/2)**2;
      return 2*R*Math.asin(Math.sqrt(s)); }
    const rem=cand.slice(), sorted=[]; let cur={lat:cand[0].lat,lng:cand[0].lng};
    while(rem.length){
      let bi=0,bd=Infinity;
      for(let i=0;i<rem.length;i++){ const d=dist(cur,rem[i]); if(d<bd){bd=d;bi=i;} }
      const nx=rem.splice(bi,1)[0]; sorted.push(nx); cur=nx;
    }

    // 선택한 날짜에 하루 perDay개씩 순서대로 배정
    const cap = msDates.length * perDay;
    const use = sorted.slice(0, cap);
    const overflow = sorted.length - use.length;
    const plan = {}; // date -> [client...]
    for (let i=0;i<use.length;i++){
      const date = msDates[Math.floor(i/perDay)];
      (plan[date]=plan[date]||[]).push(use[i]);
    }

    let msg = msDates.length+'일에 총 '+use.length+'곳을 배정합니다.\n';
    msg += Object.keys(plan).map(d=> d+': '+plan[d].length+'곳').join('\n');
    if (overflow>0) msg += '\n\n⚠️ 선택한 날이 부족해 '+overflow+'곳은 배정에서 빠집니다.';
    msg += '\n\n진행할까요?';
    if (!confirm(msg)) return;

    // 서버 전송 (날짜별로)
    let added = 0;
    for (const date of Object.keys(plan)){
      const fd = new FormData();
      fd.append('csrf', CSRF); fd.append('action','cal_assign_day');
      fd.append('date', date); fd.append('ids', JSON.stringify(plan[date].map(p=>p.id)));
      try{
        const r = await fetch(location.pathname+location.search, {method:'POST', body:fd});
        const j = await r.json(); added += (j.added||0);
      }catch(e){}
    }
    alert('배정 완료 ('+added+'곳)');
    location.reload();
  };

  // 이번 달 전체 일정 삭제
  window.clearWholeMonth = async function(){
    const ym = <?= json_encode($ym) ?>;
    if (!confirm(ym+' 한 달의 모든 일정을 삭제합니다.\n되돌릴 수 없습니다. 정말 진행할까요?')) return;
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action','cal_clear_month'); fd.append('ym', ym);
    try{
      const r = await fetch(location.pathname+location.search, {method:'POST', body:fd});
      const j = await r.json();
      alert('이번 달 전체 삭제 완료 ('+j.removed+'건)');
      location.reload();
    }catch(e){ alert('삭제 실패. 다시 시도하세요.'); }
  };

  // 이 날 전체 삭제 (모든 거래처·모든 타입)
  window.demClearDay = async function(){    const ds = document.getElementById('dayEventsModal').dataset.date;
    if (!ds) return;
    if (!confirm(ds+'의 모든 일정(방문·점검·AS 등)을 삭제합니다.\n진행할까요?')) return;
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action','cal_clear_day'); fd.append('date', ds);
    try{
      const r = await fetch(location.pathname+location.search, {method:'POST', body:fd});
      const j = await r.json();
      alert('삭제 완료 ('+j.removed+'건)');
      location.reload();
    }catch(e){ alert('삭제 실패. 다시 시도하세요.'); }
  };

  // 이 날에 미등록 거래처를 가까운 순으로 N곳 배정
  window.demAssignDay = async function(){
    const ds = document.getElementById('dayEventsModal').dataset.date;
    if (!ds) return;
    const perDay = Math.max(1, parseInt(document.getElementById('dem-perday').value)||5);

    // 이번 달 미등록 거래처 (활동 없는 거래처) + 좌표 있는 것
    const activeIds = new Set();
    for (const k in EVENTS_BY_DATE){
      for (const ev of EVENTS_BY_DATE[k]){ activeIds.add(ev.id); }
    }
    const cand = CLIENTS.filter(c=> !activeIds.has(c.id) && c.lat!=null && c.lng!=null && !isNaN(c.lat) && !isNaN(c.lng))
                        .map(c=>({id:c.id,name:c.name,lat:+c.lat,lng:+c.lng}));
    if (!cand.length){ alert('배정할 미등록 거래처가 없습니다. (좌표 있는 거래처 기준)'); return; }

    // 가까운 순 정렬
    function dist(a,b){ const R=6371,r=x=>x*Math.PI/180;
      const dLat=r(b.lat-a.lat),dLng=r(b.lng-a.lng);
      const s=Math.sin(dLat/2)**2+Math.cos(r(a.lat))*Math.cos(r(b.lat))*Math.sin(dLng/2)**2;
      return 2*R*Math.asin(Math.sqrt(s)); }
    const rem=cand.slice(), picks=[]; let cur={lat:cand[0].lat,lng:cand[0].lng};
    while(rem.length && picks.length<perDay){
      let bi=0,bd=Infinity;
      for(let i=0;i<rem.length;i++){ const d=dist(cur,rem[i]); if(d<bd){bd=d;bi=i;} }
      const nx=rem.splice(bi,1)[0]; picks.push(nx); cur=nx;
    }
    if (!confirm(ds+'에 '+picks.length+'곳을 배정합니다:\n'+picks.map(p=>p.name).join(', ')+'\n\n진행할까요?')) return;

    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action','cal_assign_day');
    fd.append('date', ds); fd.append('ids', JSON.stringify(picks.map(p=>p.id)));
    try{
      const r = await fetch(location.pathname+location.search, {method:'POST', body:fd});
      const j = await r.json();
      alert('배정 완료 ('+j.added+'곳)');
      location.reload();
    }catch(e){ alert('배정 실패. 다시 시도하세요.'); }
  };
})();

// ✅ 이벤트 모달의 “코멘트” 버튼 → 해당 날짜 코멘트 모달 열기
(function(){
  const btn = document.getElementById('dem-note-btn');
  if (btn) {
    btn.onclick = () => {
      const dlg = document.getElementById('dayEventsModal');
      const dateStr = dlg?.dataset?.date;
      if (dateStr) {
        closeModal('dayEventsModal');
        openDayNotes(dateStr);
      }
    };
  }
})();
/* ===== 마을 수정 모달 ===== */
let _editClientId = null;

function openEditClientModal() {
  if (!_editClientId) return;
  const c = CLIENTS.find(x => x.id === _editClientId);
  if (!c) { alert('마을 정보를 찾을 수 없습니다.'); return; }

  document.getElementById('ecm-title').textContent = `마을 수정 — ${c.name||''}`;
  document.getElementById('ecm-id').value          = c.id || '';
  document.getElementById('ecm-name').value        = c.name || '';
  document.getElementById('ecm-address').value     = c.address || c.addr || '';
  document.getElementById('ecm-phone').value       = c.phone || '';
  document.getElementById('ecm-birth').value       = c.birth || '';
  document.getElementById('ecm-safety-name').value = c.safety_name || '';
  document.getElementById('ecm-safety-birth').value= c.safety_birth || '';
  document.getElementById('ecm-lat').value         = (c.lat != null) ? c.lat : '';
  document.getElementById('ecm-lng').value         = (c.lng != null) ? c.lng : '';

  // 커스텀 필드 렌더
  const box = document.getElementById('ecm-extra-fields');
  box.innerHTML = '';
  const fields = Array.isArray(c.extra_fields) ? c.extra_fields : [];
  fields.forEach(f => addExtraField(f.key || '', f.value || ''));

  closeModal('visitModal');
  document.getElementById('editClientModal').showModal();
}

function addExtraField(key='', value='') {
  const box = document.getElementById('ecm-extra-fields');
  const row = document.createElement('div');
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1.5fr auto;gap:6px;align-items:center';
  row.innerHTML = `
    <input name="ef_key[]" placeholder="항목명 (예: 계약일)" value="${escapeHtml(key)}"
           style="padding:8px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:13px;width:100%">
    <input name="ef_value[]" placeholder="내용" value="${escapeHtml(value)}"
           style="padding:8px 10px;border-radius:8px;border:1px solid #e3e8f0;background:#ffffff;color:#1a2436;font-size:13px;width:100%">
    <button type="button" onclick="this.parentNode.remove()"
            style="background:var(--inspect-bg);border:1px solid var(--inspect-bd);color:var(--inspect);border-radius:7px;padding:6px 10px;cursor:pointer;white-space:nowrap;font-size:13px">✕</button>
  `;
  box.appendChild(row);
}

/* ===== 건물 설비현황 모달 ===== */
const DEFAULT_EQUIPS = [
  '소화기','옥내소화전','스프링클러','자동화재탐지기','비상방송설비',
  '유도등','비상조명등','제연설비','연결송수관','피난기구',
  '소화용수설비','통합감시시설'
];

let _bmClientId   = null;
let _bmCustomList = [];   // 이 마을의 커스텀 설비 목록

function openBuildingModal() {
  if (!_editClientId) return;
  const c = CLIENTS.find(x => x.id === _editClientId);
  if (!c) return;
  _bmClientId = _editClientId;

  document.getElementById('bm-title').textContent = `🏢 설비현황 — ${c.name||''}`;
  document.getElementById('bm-client-id').value = _bmClientId;

  // 기존 저장 데이터 불러오기
  const bd = BUILDINGS[_bmClientId] || {};
  const fb = bd.floors_below  ?? 0;
  const fa = bd.floors_above  ?? 5;
  document.getElementById('bm-floors-below').value = fb;
  document.getElementById('bm-floors-above').value = fa;

  _bmCustomList = Array.isArray(bd.custom_equips) ? [...bd.custom_equips] : [];
  renderCustomChips();
  rebuildFloorList(bd.floor_data || {});

  bmTab('edit');
  closeModal('visitModal');
  document.getElementById('buildingModal').showModal();
}

function bmTab(t) {
  const isEdit = t === 'edit';
  document.getElementById('bm-edit-panel').style.display = isEdit ? 'flex' : 'none';
  document.getElementById('bm-view-panel').style.display = isEdit ? 'none'  : 'block';
  document.getElementById('bm-tab-edit').style.background  = isEdit ? '#172a47' : 'transparent';
  document.getElementById('bm-tab-edit').style.color       = isEdit ? '#93c5fd' : '#64748b';
  document.getElementById('bm-tab-edit').style.borderBottom= isEdit ? '2px solid #2563eb' : '2px solid transparent';
  document.getElementById('bm-tab-view').style.background  = isEdit ? 'transparent' : '#172a47';
  document.getElementById('bm-tab-view').style.color       = isEdit ? '#64748b' : '#93c5fd';
  document.getElementById('bm-tab-view').style.borderBottom= isEdit ? '2px solid transparent' : '2px solid #2563eb';
  if (!isEdit) renderBuildingView();
}

function addCustomEquip() {
  const inp = document.getElementById('bm-custom-input');
  const v = inp.value.trim();
  if (!v) return;
  if (!_bmCustomList.includes(v)) {
    _bmCustomList.push(v);
    renderCustomChips();
    rebuildFloorList(); // 새 설비 체크박스 추가
  }
  inp.value = '';
}

function removeCustomEquip(name) {
  _bmCustomList = _bmCustomList.filter(x => x !== name);
  renderCustomChips();
  rebuildFloorList();
}

function renderCustomChips() {
  const box = document.getElementById('bm-custom-chips');
  box.innerHTML = '';
  // hidden inputs for form submission
  _bmCustomList.forEach(name => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'custom_equips[]'; inp.value = name;
    box.appendChild(inp);

    const chip = document.createElement('span');
    chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;font-size:12px';
    chip.innerHTML = `${escapeHtml(name)} <button type="button" onclick="removeCustomEquip('${escapeHtml(name)}')" style="border:0;background:transparent;color:#64748b;cursor:pointer;font-size:12px;padding:0;line-height:1">✕</button>`;
    box.appendChild(chip);
  });
}

function rebuildFloorList(savedData) {
  const fb = parseInt(document.getElementById('bm-floors-below').value) || 0;
  const fa = parseInt(document.getElementById('bm-floors-above').value) || 1;
  const box = document.getElementById('bm-floor-list');

  // 현재 체크 상태 스냅샷 (rebuildFloorList 재호출 시 유지)
  const snap = savedData || captureFloorSnapshot();

  box.innerHTML = '';
  const allEquips = [...DEFAULT_EQUIPS, ..._bmCustomList];

  // 지상층: 옥상 → 1층
  for (let f = fa; f >= 1; f--) {
    const label = f === fa && fa > 1 ? `${f}층 (최상)` : `${f}층`;
    const key   = `${f}F`;
    box.appendChild(buildFloorRow(key, label, allEquips, snap[key] || []));
  }
  // 지하층: B1 → Bn
  for (let f = 1; f <= fb; f++) {
    const key   = `B${f}`;
    const label = `지하 ${f}층`;
    box.appendChild(buildFloorRow(key, label, allEquips, snap[key] || []));
  }
}

function buildFloorRow(key, label, allEquips, checked) {
  const wrap = document.createElement('div');
  wrap.style.cssText = 'border:1px solid var(--bd);border-radius:var(--r-md);overflow:hidden';

  // 헤더
  const head = document.createElement('div');
  head.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:9px 13px;background:var(--card2);cursor:pointer;user-select:none;transition:background .12s';
  head.innerHTML = `
    <span style="font-size:13px;font-weight:600;color:var(--fg)">${escapeHtml(label)}</span>
    <span style="font-size:11px;color:var(--mut)" id="bm-cnt-${key}">${checked.length > 0 ? checked.length+'개 설비' : '없음'}</span>
  `;

  // 체크박스 그리드
  const body = document.createElement('div');
  body.id = `bm-body-${key}`;
  body.style.cssText = 'padding:10px 13px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px;background:var(--bg3)';

  allEquips.forEach(eq => {
    const isChecked = checked.includes(eq);
    const lbl = document.createElement('label');
    lbl.style.cssText = `display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:var(--r-sm);cursor:pointer;border:1px solid ${isChecked?'var(--accent)':'var(--bd)'};background:${isChecked?'var(--accent-dim)':'var(--card)'};transition:.15s`;
    lbl.innerHTML = `
      <input type="checkbox" name="floor[${key}][]" value="${escapeHtml(eq)}" ${isChecked?'checked':''}
        style="width:15px;height:15px;accent-color:var(--accent);cursor:pointer"
        onchange="onFloorCheck(this,'${key}','${escapeHtml(eq)}')">
      <span style="font-size:12px;color:${isChecked?'var(--link)':'var(--fg2)'}">${escapeHtml(eq)}</span>
    `;
    body.appendChild(lbl);
  });

  // 접기/펼치기
  let open = checked.length > 0;
  body.style.display = open ? '' : 'none';
  head.querySelector('span:first-child').insertAdjacentHTML('afterend',
    `<span id="bm-arr-${key}" style="font-size:11px;color:var(--mut);margin-left:6px">${open?'▲':'▼'}</span>`);
  head.addEventListener('click', () => {
    open = !open;
    body.style.display = open ? '' : 'none';
    document.getElementById(`bm-arr-${key}`).textContent = open ? '▲' : '▼';
  });

  wrap.appendChild(head);
  wrap.appendChild(body);
  return wrap;
}

function onFloorCheck(cb, key, eq) {
  const lbl = cb.closest('label');
  lbl.style.border    = cb.checked ? '1px solid var(--accent)' : '1px solid var(--bd)';
  lbl.style.background= cb.checked ? 'var(--accent-dim)' : 'var(--card)';
  lbl.querySelector('span').style.color = cb.checked ? 'var(--link)' : 'var(--fg2)';
  // 카운트 업데이트
  const cnt = document.querySelectorAll(`#bm-body-${key} input[type=checkbox]:checked`).length;
  const cntEl = document.getElementById(`bm-cnt-${key}`);
  if (cntEl) cntEl.textContent = cnt > 0 ? `${cnt}개 설비` : '없음';
}

function captureFloorSnapshot() {
  const snap = {};
  document.querySelectorAll('#bm-floor-list input[type=checkbox]:checked').forEach(cb => {
    const m = cb.name.match(/^floor\[(.+)\]\[\]$/);
    if (m) { const k = m[1]; if (!snap[k]) snap[k]=[]; snap[k].push(cb.value); }
  });
  return snap;
}

/* ── 건물 뷰 렌더링 ── */
function renderBuildingView() {
  const box = document.getElementById('bm-building-view');
  if (!_bmClientId) return;
  const bd = BUILDINGS[_bmClientId] || {};
  const snap = captureFloorSnapshot(); // 저장 전이어도 현재 입력 반영

  const fb = parseInt(document.getElementById('bm-floors-below').value) || bd.floors_below || 0;
  const fa = parseInt(document.getElementById('bm-floors-above').value) || bd.floors_above || 1;

  let html = `<div style="display:flex;flex-direction:column;gap:6px;max-width:520px;margin:0 auto">`;

  // 지상 옥상 → 1층
  for (let f = fa; f >= 1; f--) {
    const key = `${f}F`;
    const equips = snap[key] || bd.floor_data?.[key] || [];
    const isTop = f === fa;
    html += floorViewRow(key, `${f}층${isTop&&fa>1?' (최상)':''}`, equips, false);
  }
  // 지하
  for (let f = 1; f <= fb; f++) {
    const key = `B${f}`;
    const equips = snap[key] || bd.floor_data?.[key] || [];
    html += floorViewRow(key, `지하 ${f}층`, equips, true);
  }
  html += '</div>';
  if (!bd.updated && !Object.keys(snap).length) {
    html = '<div style="text-align:center;color:#64748b;padding:40px 0;font-size:14px">아직 저장된 설비 정보가 없습니다.<br>✏️ 설비 입력 탭에서 입력 후 저장하세요.</div>';
  }
  box.innerHTML = html;
}

function floorViewRow(key, label, equips, isBasement) {
  const hasEquip = equips.length > 0;
  const bg    = isBasement ? '#0c1424' : '#0b1628';
  const bord  = hasEquip ? '#2563eb' : '#1f2a3a';
  const chips = equips.map(e =>
    `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;border:1px solid #2563eb;background:#eff6ff;color:#93c5fd;font-size:11px">🔧 ${escapeHtml(e)}</span>`
  ).join('');

  return `
    <div style="display:grid;grid-template-columns:80px 1fr;border:1px solid ${bord};border-radius:10px;overflow:hidden;background:${bg}">
      <div style="display:flex;align-items:center;justify-content:center;padding:12px 8px;border-right:1px solid ${bord};font-size:13px;font-weight:700;color:${hasEquip?'#e5e7eb':'#374151'}">
        ${escapeHtml(label)}
      </div>
      <div style="padding:10px 12px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:44px">
        ${hasEquip ? chips : '<span style="font-size:12px;color:#374151">설비 없음</span>'}
      </div>
    </div>
  `;
}

/* ===== 방문예정 모달 ===== */
window.openPlanFor = (id, name) => {
  document.getElementById('pm-id').value = id;
  document.getElementById('pm-title').textContent = `📅 방문예정 — ${name}`;
  document.getElementById('pm-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('planModal').showModal();
};

/* ===== 날짜 색상/라벨 모달 ===== */
window.openDayColorModal = (dateStr) => {
  if (!dateStr) return;
  document.getElementById('dcm-date').value = dateStr;
  const d = new Date(dateStr + 'T00:00:00');
  document.getElementById('dcm-title').textContent = `🎨 색상 설정 — ${dateStr}`;
  document.getElementById('dcm-prev-num').textContent = d.getDate();
  const dc = DAY_COLORS[dateStr] || {};
  document.getElementById('dcm-color').value = dc.color || '#3b82f6';
  document.getElementById('dcm-label').value = dc.label || '';
  dcmPreview();
  document.getElementById('dayColorModal').showModal();
};

function dcmSet(color, label) {
  document.getElementById('dcm-color').value = color;
  document.getElementById('dcm-label').value = label;
  dcmPreview();
}

function dcmPreview() {
  const color = document.getElementById('dcm-color').value;
  const label = document.getElementById('dcm-label').value;
  const num = document.getElementById('dcm-prev-num');
  const lbl = document.getElementById('dcm-prev-lbl');
  num.style.color = color;
  lbl.textContent = label;
  lbl.style.background = color + '33';
  lbl.style.color = color;
  lbl.style.borderColor = color + '55';
  lbl.style.display = label ? '' : 'none';
  document.getElementById('dcm-preview').style.borderLeft = `3px solid ${color}`;
  document.getElementById('dcm-preview').style.background = color + '11';
}

function dcmClear() {
  document.getElementById('dcm-color').value = '';
  document.getElementById('dcm-label').value = '';
  document.getElementById('dcm-form').submit();
}

/* ════════════════════════════════════════
   📁 자료실
   localStorage 기반 — 재시작 후에도 유지
════════════════════════════════════════ */
const QF_KEY = 'qf_data_v2';
const QF_ICONS = ['📄','📊','📋','📝','🗂️','📑','📂','💾','🔧','📌','🖨️','📬','🗃️','📎','🔑','📆'];
const QF_EXT_ICON = {pdf:'📄',doc:'📝',docx:'📝',xls:'📊',xlsx:'📊',ppt:'📑',pptx:'📑',
  hwp:'📝',hwpx:'📝',zip:'🗜️',png:'🖼️',jpg:'🖼️',jpeg:'🖼️',gif:'🖼️',txt:'📋',csv:'📊'};
let _qf = { groups: ['전체','보고서','양식','기타'], files: [], activeGroup: '전체', editing: false };
let _qfIcon = '📄';
let _qfTab = 'url';      // 'url' | 'file'
let _qfPendingUrl = '';  // 업로드 완료 후 서버 경로

function qfLoad() {
  try {
    const raw = localStorage.getItem(QF_KEY);
    if (raw) { const d = JSON.parse(raw); if (d.groups) _qf.groups=d.groups; if (d.files) _qf.files=d.files; }
  } catch(e) {}
  qfRender();
}

function qfSave() {
  try { localStorage.setItem(QF_KEY, JSON.stringify({groups:_qf.groups,files:_qf.files})); } catch(e) {}
}

function qfRender() { qfRenderGroups(); qfRenderGrid(); const c=document.getElementById('qf-count'); if(c) c.textContent=_qf.files.length+'개'; }

function qfRenderGroups() {
  const bar = document.getElementById('qf-groups-bar'); if (!bar) return;
  bar.innerHTML = '';
  _qf.groups.forEach(g => {
    const btn = document.createElement('button');
    btn.className = 'qf-gtab'+(g===_qf.activeGroup?' active':'');
    btn.textContent = g;
    btn.onclick = () => { _qf.activeGroup = g; qfRender(); };
    bar.appendChild(btn);
  });
}

function qfRenderGrid() {
  const grid = document.getElementById('qf-grid'); if (!grid) return;
  const panel = document.getElementById('qf-panel');
  if (_qf.editing) panel.classList.add('qf-editing'); else panel.classList.remove('qf-editing');
  const editBtn = document.getElementById('qf-edit-btn');
  if (editBtn) { editBtn.textContent = _qf.editing ? '완료' : '편집'; editBtn.classList.toggle('active', _qf.editing); }
  const shown = _qf.activeGroup==='전체' ? _qf.files : _qf.files.filter(f=>f.group===_qf.activeGroup);
  grid.innerHTML = '';
  if (!shown.length) {
    grid.innerHTML = '<div class="qf-empty">파일 없음<br><span style="font-size:10px">＋ 추가 버튼으로 등록하세요</span></div>';
    return;
  }
  shown.forEach(f => {
    const a = document.createElement('a');
    a.className = 'qf-card';
    a.href = f.url || '#';
    if (f.url) a.download = f.name;
    a.target = '_blank';
    a.innerHTML = `<div class="qf-icon">${f.icon||'📄'}</div><div class="qf-name">${escapeHtml(f.name)}</div><span class="qf-del" title="삭제" onclick="qfDelete('${f.id}',event)">✕</span>`;
    a.onclick = e => { if(e.target.classList.contains('qf-del')) e.preventDefault(); };
    grid.appendChild(a);
  });
}

function qfDelete(id, e) {
  e.preventDefault(); e.stopPropagation();
  if (!confirm('삭제할까요?')) return;
  // 서버 파일이면 서버에서도 삭제
  const f = _qf.files.find(x=>x.id===id);
  if (f && f.url && f.url.indexOf('/quickfiles/') !== -1) {
    const fname = f.url.substring(f.url.lastIndexOf('/') + 1);
    const fd = new FormData(); fd.append('csrf',CSRF); fd.append('action','qf_delete_file'); fd.append('fname',fname);
    fetch(location.href,{method:'POST',body:fd}).catch(()=>{});
  }
  _qf.files = _qf.files.filter(x=>x.id!==id);
  qfSave(); qfRender();
}

function qfToggleEdit() { _qf.editing = !_qf.editing; qfRender(); }

/* ── 모달 열기 ── */
function qfOpenAddModal() {
  const sel = document.getElementById('qf-f-group');
  sel.innerHTML = _qf.groups.filter(g=>g!=='전체').map(g=>`<option>${g}</option>`).join('');
  const picker = document.getElementById('qf-icon-picker');
  picker.innerHTML = QF_ICONS.map(ic=>`<button type="button" class="qf-modal-icon-opt${ic===_qfIcon?' qf-icon-sel':''}" onclick="qfPickIcon('${ic}',this)">${ic}</button>`).join('');
  document.getElementById('qf-f-name').value = '';
  document.getElementById('qf-f-url').value = '';
  _qfPendingUrl = '';
  qfClearFile();
  qfSetTab('url');
  document.getElementById('qfModal').showModal();
  setTimeout(()=>document.getElementById('qf-f-name').focus(),60);
}

function qfPickIcon(ic, el) {
  _qfIcon = ic;
  document.querySelectorAll('.qf-modal-icon-opt').forEach(e=>e.classList.remove('qf-icon-sel'));
  el.classList.add('qf-icon-sel');
}

/* ── 탭 전환 ── */
function qfSetTab(tab) {
  _qfTab = tab;
  document.getElementById('qf-src-url').style.display  = tab==='url'  ? '' : 'none';
  document.getElementById('qf-src-file').style.display = tab==='file' ? '' : 'none';
  const urlBtn  = document.getElementById('qf-tab-url');
  const fileBtn = document.getElementById('qf-tab-file');
  if (tab==='url')  { urlBtn.style.background='var(--accent-dim)'; urlBtn.style.color='var(--link)'; fileBtn.style.background='transparent'; fileBtn.style.color='var(--mut)'; }
  else              { fileBtn.style.background='var(--accent-dim)'; fileBtn.style.color='var(--link)'; urlBtn.style.background='transparent'; urlBtn.style.color='var(--mut)'; }
}

/* ── 파일 선택/드래그앤드롭 ── */
function qfDragOver(e) { e.preventDefault(); document.getElementById('qf-drop-zone').style.borderColor='var(--accent)'; document.getElementById('qf-drop-zone').style.background='var(--accent-glow)'; }
function qfDragLeave(e){ document.getElementById('qf-drop-zone').style.borderColor='var(--bd2)'; document.getElementById('qf-drop-zone').style.background=''; }
function qfDrop(e) {
  e.preventDefault(); qfDragLeave(e);
  const file = e.dataTransfer.files[0];
  if (file) qfFileSelected(file);
}

function qfFileSelected(file) {
  if (!file) return;
  const ext = file.name.split('.').pop().toLowerCase();
  const icon = QF_EXT_ICON[ext] || '📄';
  // 미리보기
  const preview = document.getElementById('qf-file-preview');
  preview.style.display = 'flex';
  document.getElementById('qf-file-preview-icon').textContent = icon;
  document.getElementById('qf-file-preview-name').textContent = file.name;
  document.getElementById('qf-file-preview-size').textContent = (file.size/1024 < 1024)
    ? Math.round(file.size/1024)+'KB' : (file.size/1024/1024).toFixed(1)+'MB';
  // 이름 자동 채우기
  if (!document.getElementById('qf-f-name').value) {
    document.getElementById('qf-f-name').value = file.name.replace(/\.[^/.]+$/,'');
  }
  // 아이콘 자동 선택
  _qfIcon = icon;
  document.querySelectorAll('.qf-modal-icon-opt').forEach(e=>e.classList.remove('qf-icon-sel'));
  const match = document.querySelector(`.qf-modal-icon-opt`);
  // 서버 업로드
  qfUploadFile(file);
}

function qfClearFile() {
  _qfPendingUrl = '';
  document.getElementById('qf-file-input').value = '';
  document.getElementById('qf-file-preview').style.display = 'none';
  document.getElementById('qf-progress-wrap').style.display = 'none';
}

function qfUploadFile(file) {
  const wrap = document.getElementById('qf-progress-wrap');
  const bar  = document.getElementById('qf-progress-bar');
  const lbl  = document.getElementById('qf-progress-label');
  const saveBtn = document.getElementById('qf-save-btn');
  wrap.style.display = ''; bar.style.width = '0%'; lbl.textContent = '업로드 중...';
  if (saveBtn) saveBtn.disabled = true;
  _qfPendingUrl = '';

  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('action', 'qf_upload');
  fd.append('qf_file', file);

  const xhr = new XMLHttpRequest();
  xhr.open('POST', location.href);
  xhr.upload.onprogress = e => {
    if (e.lengthComputable) {
      const pct = Math.round(e.loaded/e.total*100);
      bar.style.width = pct+'%';
      lbl.textContent = '업로드 중... '+pct+'%';
    }
  };
  xhr.onload = () => {
    if (saveBtn) saveBtn.disabled = false;
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.ok) {
        _qfPendingUrl = res.url;
        bar.style.width = '100%';
        bar.style.background = 'var(--visit)';
        lbl.textContent = '✓ 업로드 완료 — 저장 버튼을 눌러주세요';
      } else {
        bar.style.background = 'var(--inspect)';
        lbl.textContent = '✗ 오류: ' + res.msg;
      }
    } catch(e) { lbl.textContent = '✗ 서버 응답 오류'; bar.style.background = 'var(--inspect)'; }
  };
  xhr.onerror = () => {
    if (saveBtn) saveBtn.disabled = false;
    lbl.textContent = '✗ 네트워크 오류'; bar.style.background = 'var(--inspect)';
  };
  xhr.send(fd);
}

/* ── 저장 ── */
function qfSaveFile() {
  const name  = document.getElementById('qf-f-name').value.trim();
  const group = document.getElementById('qf-f-group').value;
  if (!name) { alert('파일 이름을 입력하세요.'); return; }

  let url = '';
  if (_qfTab === 'file') {
    if (!_qfPendingUrl) { alert('파일 업로드가 완료될 때까지 기다려주세요.'); return; }
    url = _qfPendingUrl;
  } else {
    url = document.getElementById('qf-f-url').value.trim();
  }

  const id = Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  _qf.files.push({id, name, url, group, icon:_qfIcon});
  qfSave(); qfRender();
  document.getElementById('qfModal').close();
}

function qfOpenGroupModal() {
  document.getElementById('qf-g-name').value = '';
  document.getElementById('qfGroupModal').showModal();
  setTimeout(()=>document.getElementById('qf-g-name').focus(),60);
}

function qfSaveGroup() {
  const name = document.getElementById('qf-g-name').value.trim();
  if (!name) return;
  if (!_qf.groups.includes(name)) { _qf.groups.push(name); qfSave(); qfRender(); }
  document.getElementById('qfGroupModal').close();
}

document.addEventListener('keydown', e => {
  if (e.key==='Enter') {
    if (document.getElementById('qfModal').open && !document.getElementById('qf-save-btn').disabled) qfSaveFile();
    if (document.getElementById('qfGroupModal').open) qfSaveGroup();
  }
});


/* ════════════════════════════════════════
   📷 공사사진 보고서 모달 JS
════════════════════════════════════════ */
let _prmClientId = '';
let _prmClientName = '';
let _prmPairs       = []; // [{before:{url,caption,_uploading,_localUrl}, after:{...}, gongong:''}]
let _prmEditingRid  = null;
let _prmFileTarget  = null; // {pairIdx, side}

/* 신규·기존 공사사진 URL을 모두 로그인 검증 사진 주소로 통일 */
function prmPhotoUrl(url) {
  const raw = String(url || '').trim();
  if (!raw) return '';
  if (/^blob:/i.test(raw)) return raw;
  try {
    const parsed = new URL(raw, location.href);
    if (parsed.pathname === '/clients_mini.php' && parsed.searchParams.has('photo_file')) return parsed.href;
    if (/(?:^|\/)photos\/[^/]+$/i.test(parsed.pathname)) {
      const fname = parsed.pathname.split('/').pop();
      return new URL('/clients_mini.php?photo_file=' + encodeURIComponent(fname), location.origin).href;
    }
    return parsed.href;
  } catch(e) {
    const fname = raw.split(/[\\/]/).pop();
    return '/clients_mini.php?photo_file=' + encodeURIComponent(fname);
  }
}

window.openPhotoReportModal = function(clientId) {
  const client = CLIENTS.find(c => c.id === clientId);
  if (!client) return;
  _prmClientId   = clientId;
  _prmClientName = client.name || '';
  document.getElementById('prm-title').textContent    = '📷 공사사진 보고서';
  document.getElementById('prm-subtitle').textContent = _prmClientName;
  prmResetNew();
  prmTab('list');
  prmRenderList(client);
  const prModal = document.getElementById('photoReportModal');
  prModal.showModal();
  // iOS Safari: 모달 내부 스크롤 활성화
  prModal.scrollTop = 0;
  setTimeout(() => { prModal.querySelector('#prm-panel-list, #prm-panel-new') && (prModal.scrollTop = 0); }, 50);
};

function prmTab(tab) {
  const isList = tab === 'list';
  document.getElementById('prm-panel-list').style.display   = isList ? '' : 'none';
  document.getElementById('prm-panel-new').style.display    = isList ? 'none' : '';
  document.getElementById('prm-actions-list').style.display = isList ? '' : 'none';
  document.getElementById('prm-actions-new').style.display  = isList ? 'none' : '';
  const tList = document.getElementById('prm-tab-list');
  const tNew  = document.getElementById('prm-tab-new');
  tList.style.background      = isList ? 'rgba(61,134,245,.1)' : 'transparent';
  tList.style.color           = isList ? 'var(--link)' : 'var(--mut)';
  tList.style.borderBottomColor = isList ? 'var(--accent)' : 'transparent';
  tNew.style.background       = !isList ? 'rgba(22,163,74,.1)' : 'transparent';
  tNew.style.color            = !isList ? '#86efac' : 'var(--mut)';
  tNew.style.borderBottomColor = !isList ? '#16a34a' : 'transparent';
}

function prmRenderList(client) {
  const box = document.getElementById('prm-report-list');
  const reports = (client.photo_reports || []);
  if (!reports.length) {
    box.innerHTML = `<div style="padding:40px;text-align:center;color:var(--sub);font-size:13px">
      <div style="font-size:32px;margin-bottom:8px">📷</div>
      아직 작성된 공사사진 보고서가 없습니다.<br>
      <button type="button" onclick="prmTab('new')"
        style="margin-top:12px;padding:7px 16px;border-radius:var(--r-sm);border:1px solid #bbf7d0;background:#ecfdf3;color:#15803d;font-size:12px;cursor:pointer;font-family:inherit">
        ＋ 첫 번째 보고서 작성하기
      </button>
    </div>`;
    return;
  }
  box.innerHTML = '';
  reports.forEach(r => {
    const card = document.createElement('div');
    card.style.cssText = 'background:var(--bg3);border:1px solid var(--bd);border-radius:var(--r-md);padding:12px 14px;margin-bottom:10px;cursor:pointer;transition:border-color .15s';
    card.onmouseenter = () => card.style.borderColor = 'var(--bd3)';
    card.onmouseleave = () => card.style.borderColor = 'var(--bd)';
    card.onclick = (e) => { if (e.target.tagName === 'BUTTON') return; prmEditReport(r); };
    const firstPair   = (r.pairs && r.pairs[0]) || {};
    const thumbBefore = firstPair.before_url ? `<img src="${escapeHtml(prmPhotoUrl(firstPair.before_url))}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bd)">` : (r.before&&r.before[0] ? `<img src="${escapeHtml(prmPhotoUrl(r.before[0].url))}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bd)">` : '<div style="width:48px;height:48px;border-radius:6px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:18px">📷</div>');
    const thumbAfter  = firstPair.after_url  ? `<img src="${escapeHtml(prmPhotoUrl(firstPair.after_url))}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bd)">` : (r.after&&r.after[0] ? `<img src="${escapeHtml(prmPhotoUrl(r.after[0].url))}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bd)">` : '<div style="width:48px;height:48px;border-radius:6px;border:1px solid var(--bd);background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:18px">📷</div>');
    card.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px">
        <div style="display:flex;gap:4px;flex-shrink:0">${thumbBefore}${thumbAfter}</div>
        <div style="flex:1;min-width:0">
          <div style="font-size:14px;font-weight:700;color:var(--fg);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(r.title)}</div>
          <div style="font-size:11px;color:var(--mut)">${escapeHtml(r.date)} · ${(r.pairs||r.before||[]).length}쌍</div>
          ${r.note ? `<div style="font-size:11px;color:var(--sub);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(r.note)}</div>` : ''}
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
          <button type="button" onclick="prmDownloadPdf('${escapeHtml(r.rid)}')"
            style="padding:5px 10px;border-radius:var(--r-sm);border:1px solid #16a34a;background:transparent;color:#15803d;font-size:11px;cursor:pointer;font-family:inherit">
            ⬇ PDF
          </button>
          <button type="button" onclick="prmPrint(${JSON.stringify(r)})"
            style="padding:5px 10px;border-radius:var(--r-sm);border:1px solid #475569;background:transparent;color:#94a3b8;font-size:11px;cursor:pointer;font-family:inherit">
            🖨 미리보기
          </button>
          <button type="button" onclick="prmDeleteReport('${escapeHtml(r.rid)}')"
            style="padding:5px 10px;border-radius:var(--r-sm);border:1px solid var(--inspect-bd);background:transparent;color:var(--inspect);font-size:11px;cursor:pointer;font-family:inherit">
            🗑
          </button>
        </div>
      </div>`;
    box.appendChild(card);
  });
}

function prmEditReport(r) {
  _prmEditingRid = r.rid;
  const titleEl = document.getElementById('prm-report-title');
  const noteEl  = document.getElementById('prm-note');
  if (titleEl) titleEl.value = r.title || '';
  if (noteEl)  noteEl.value  = r.note  || '';

  // pairs 구조로 복원 (구형 before/after 배열도 호환)
  if (r.pairs && r.pairs.length) {
    _prmPairs = r.pairs.map(p => ({
      before: p.before_url ? {url:p.before_url, caption:p.before_caption||'', _uploading:false} : null,
      after:  p.after_url  ? {url:p.after_url,  caption:p.after_caption||'',  _uploading:false} : null,
      gongong: p.gongong || '',
    }));
  } else {
    // 구형 데이터 호환
    const maxLen = Math.max((r.before||[]).length, (r.after||[]).length);
    _prmPairs = [];
    for (let i = 0; i < maxLen; i++) {
      const b = (r.before||[])[i], a = (r.after||[])[i];
      _prmPairs.push({
        before: b ? {url:b.url, caption:b.caption||'', _uploading:false} : null,
        after:  a ? {url:a.url, caption:a.caption||'', _uploading:false} : null,
        gongong: '',
      });
    }
  }
  if (!_prmPairs.length) _prmPairs = [{before:null, after:null, gongong:''}];

  const saveBtn = document.getElementById('prm-save-btn');
  if (saveBtn) saveBtn.textContent = '💾 수정 저장';
  prmRenderPairs();
  prmTab('new');
}

function prmResetNew() {
  _prmPairs      = [{before:null, after:null, gongong:''}];
  _prmEditingRid = null;
  _prmFileTarget = null;
  const titleEl = document.getElementById('prm-report-title');
  if (titleEl) titleEl.value = '';
  const noteEl = document.getElementById('prm-note');
  if (noteEl) noteEl.value = '';
  const saveBtn = document.getElementById('prm-save-btn');
  if (saveBtn) saveBtn.textContent = '💾 보고서 저장';
  prmRenderPairs();
}

function prmAddPair() {
  _prmPairs.push({before:null, after:null, gongong:''});
  prmRenderPairs();
}

function prmRemovePair(idx) {
  _prmPairs.splice(idx, 1);
  if (!_prmPairs.length) _prmPairs = [{before:null, after:null, gongong:''}];
  prmRenderPairs();
}

function prmClickPhoto(pairIdx, side) {
  _prmFileTarget = {pairIdx, side};
  const inp = document.getElementById('prm-file-input');
  inp.value = '';
  inp.click();
}

function prmHandleFile(input) {
  const file = input.files[0];
  if (!file || !_prmFileTarget) return;
  const {pairIdx, side} = _prmFileTarget;
  _prmFileTarget = null;

  const localUrl = URL.createObjectURL(file);
  _prmPairs[pairIdx][side] = {url:'', caption:'', _uploading:true, _localUrl:localUrl};
  prmRenderPairs();

  const fd = new FormData();
  fd.append('csrf', CSRF); fd.append('action', 'photo_upload'); fd.append('photo_file', file);
  fetch(location.href, {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res => {
      if (res.ok) {
        _prmPairs[pairIdx][side].url = res.url;
        _prmPairs[pairIdx][side]._uploading = false;
      } else {
        _prmPairs[pairIdx][side] = null;
        alert('업로드 실패: '+(res.msg||''));
      }
      prmRenderPairs();
    })
    .catch(()=>{ _prmPairs[pairIdx][side]=null; prmRenderPairs(); alert('네트워크 오류'); });
}

function prmRemovePhoto(pairIdx, side) {
  _prmPairs[pairIdx][side] = null;
  prmRenderPairs();
}

function prmUpdateGongong(idx, val) {
  _prmPairs[idx].gongong = val;
}

function prmRenderPairs() {
  const box = document.getElementById('prm-pairs-list');
  if (!box) return;
  box.innerHTML = '';

  _prmPairs.forEach((pair, idx) => {
    const card = document.createElement('div');
    card.style.cssText = 'background:var(--bg3);border:1px solid var(--bd);border-radius:var(--r-sm);padding:8px;display:grid;gap:6px';

    // 전/후 사진 2칸 (정사각형)
    const photoRow = document.createElement('div');
    photoRow.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:4px';

    ['before','after'].forEach(side => {
      const p = pair[side];
      const color = side==='before' ? '#60a5fa' : '#4ade80';
      const label = side==='before' ? '조치 전' : '조치 후';
      const cell = document.createElement('div');
      // 정사각형: width 100%, padding-bottom 100% 트릭으로 강제
      cell.style.cssText = 'position:relative;width:100%;padding-bottom:100%;border-radius:4px;overflow:hidden;border:1px solid var(--bd);background:var(--bg);cursor:pointer;';

      const inner = document.createElement('div');
      inner.style.cssText = 'position:absolute;inset:0;';

      if (p && p._uploading) {
        inner.innerHTML = `<img src="${p._localUrl}" style="width:100%;height:100%;object-fit:cover;opacity:.4;display:block">
          <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px">⏳</div>`;
      } else if (p && p.url) {
        inner.innerHTML = `
          <img src="${escapeHtml(prmPhotoUrl(p.url))}" style="width:100%;height:100%;object-fit:cover;display:block">
          <div style="position:absolute;top:2px;left:2px;font-size:8px;font-weight:700;padding:1px 4px;border-radius:2px;background:${color}cc;color:#fff">${label}</div>
          <button type="button" onclick="event.stopPropagation();prmRemovePhoto(${idx},'${side}')"
            style="position:absolute;top:2px;right:2px;width:16px;height:16px;border-radius:50%;border:0;background:rgba(239,68,68,.9);color:#fff;font-size:9px;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center">✕</button>`;
        cell.onclick = () => prmClickPhoto(idx, side);
      } else {
        inner.innerHTML = `
          <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px">
            <div style="font-size:16px;color:var(--sub)">＋</div>
            <div style="font-size:9px;color:${color};font-weight:600">${label}</div>
          </div>`;
        cell.onmouseenter = () => { cell.style.borderColor = color; };
        cell.onmouseleave = () => { cell.style.borderColor = 'var(--bd)'; };
        cell.onclick = () => prmClickPhoto(idx, side);
      }
      cell.appendChild(inner);
      photoRow.appendChild(cell);
    });

    card.appendChild(photoRow);

    // 공종명 + 삭제 버튼
    const bottom = document.createElement('div');
    bottom.style.cssText = 'display:grid;grid-template-columns:1fr auto;gap:4px;align-items:center';
    bottom.innerHTML = `
      <input type="text" value="${escapeHtml(pair.gongong||'')}" placeholder="공종명"
        maxlength="60"
        style="padding:8px 10px;border-radius:4px;border:1px solid var(--bd);background:var(--bg);color:var(--fg);font-size:13px;outline:none;font-family:inherit;width:100%"
        oninput="prmUpdateGongong(${idx},this.value)">
      ${_prmPairs.length > 1
        ? `<button type="button" onclick="prmRemovePair(${idx})"
            style="padding:3px 6px;border-radius:4px;border:1px solid var(--inspect-bd);background:transparent;color:var(--inspect);font-size:10px;cursor:pointer;line-height:1">🗑</button>`
        : '<span></span>'}`;
    card.appendChild(bottom);
    box.appendChild(card);
  });
}

async function prmSaveReport() {
  const title = (document.getElementById('prm-report-title').value || '').trim();
  if (!title) { alert('공사명을 입력하세요.'); document.getElementById('prm-report-title').focus(); return; }
  if (_prmPairs.some(p => (p.before&&p.before._uploading)||(p.after&&p.after._uploading))) {
    alert('사진 업로드가 완료될 때까지 기다려주세요.'); return;
  }
  if (!_prmPairs.some(p => (p.before&&p.before.url)||(p.after&&p.after.url))) {
    alert('사진을 한 장 이상 올려주세요.'); return;
  }

  const saveBtn = document.getElementById('prm-save-btn');
  saveBtn.disabled = true;
  saveBtn.textContent = '저장 중...';
  const note = document.getElementById('prm-note').value || '';

  const client = CLIENTS.find(c=>c.id===_prmClientId);
  const isEdit = !!_prmEditingRid;

  // 수정 모드면 기존 삭제
  if (isEdit) {
    const fd2 = new FormData();
    fd2.append('csrf', CSRF); fd2.append('action', 'photo_report_delete');
    fd2.append('client_id', _prmClientId); fd2.append('rid', _prmEditingRid);
    await fetch(location.href, {method:'POST', body:fd2}).catch(()=>{});
    if (client) client.photo_reports = (client.photo_reports||[]).filter(r=>r.rid!==_prmEditingRid);
  }

  // pairs를 JSON으로 직렬화해서 전송
  const pairsData = _prmPairs.map(p => ({
    before_url:     p.before?.url     || '',
    before_caption: p.before?.caption || '',
    after_url:      p.after?.url      || '',
    after_caption:  p.after?.caption  || '',
    gongong:        p.gongong         || '',
  }));

  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('action', 'photo_report_save');
  fd.append('client_id', _prmClientId);
  fd.append('report_title', title);
  fd.append('note', note);
  pairsData.forEach((p, i) => {
    fd.append(`pairs[${i}][before_url]`,     p.before_url);
    fd.append(`pairs[${i}][before_caption]`, p.before_caption);
    fd.append(`pairs[${i}][after_url]`,      p.after_url);
    fd.append(`pairs[${i}][after_caption]`,  p.after_caption);
    fd.append(`pairs[${i}][gongong]`,        p.gongong);
  });

  try {
    const res = await fetch(location.href, {method:'POST', body:fd}).then(r=>r.json());
    if (res.ok) {
      if (client) {
        if (!client.photo_reports) client.photo_reports = [];
        client.photo_reports.unshift({
          rid: res.rid, title,
          date: new Date().toISOString().slice(0,10),
          note, pairs: pairsData,
        });
        _prmEditingRid = null;
        prmResetNew();
        prmTab('list');
        prmRenderList(client);
      }
    } else { alert('저장 실패: '+(res.msg||'')); }
  } catch(e) { alert('네트워크 오류'); }
  saveBtn.disabled = false;
  saveBtn.textContent = '💾 보고서 저장';
}


async function prmDownloadPdf(rid) {
  const client = CLIENTS.find(c => c.id === _prmClientId);
  const report = (client?.photo_reports || []).find(r => r.rid === rid);
  if (!report) { alert('보고서를 찾을 수 없습니다.'); return; }

  const btn = event.currentTarget;
  btn.disabled = true;
  btn.textContent = '⏳ 생성 중...';

  try {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

    // 숨겨진 렌더링 컨테이너
    const container = document.createElement('div');
    container.style.cssText = 'position:fixed;left:-9999px;top:0;width:794px;background:#fff;font-family:"Malgun Gothic","맑은 고딕",sans-serif;color:#000';
    document.body.appendChild(container);

    const A4_W = 210, A4_H = 297;
    const PX_W = 794; // A4 96dpi

    async function renderPageToPdf(html, addNew) {
      container.innerHTML = html;
      await new Promise(r => setTimeout(r, 80)); // 렌더 대기
      const canvas = await html2canvas(container, { scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#fff', logging: false });
      const imgData = canvas.toDataURL('image/jpeg', 0.92);
      const ratio = A4_H / A4_W;
      const ch = PX_W * ratio;
      if (addNew) pdf.addPage();
      pdf.addImage(imgData, 'JPEG', 0, 0, A4_W, A4_H);
    }

    // ── 표지 ──
    const coverHtml = `
    <div style="width:794px;height:1123px;border:4px solid #000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:48px;box-sizing:border-box;padding:40px;background:#fff">
      <div style="border:3px double #000;padding:20px 80px;text-align:center">
        <div style="font-size:40px;font-weight:900;letter-spacing:12px">조 치 사 진</div>
      </div>
      <div style="text-align:center;line-height:2.2">
        <div style="font-size:22px;font-weight:700">대상처 : ${escHtml(client.name||'')}</div>
        <div style="font-size:15px;color:#444">공사명 : ${escHtml(report.title||'')}</div>
        <div style="font-size:15px;color:#444">발송일 : ${escHtml(report.date||'')}</div>
      </div>
      <div style="font-size:26px;font-weight:900;letter-spacing:6px;margin-top:40px">${escHtml(COMPANY_NAME)}</div>
    </div>`;
    await renderPageToPdf(coverHtml, false);

    // 1페이지 = 3세트 고정, 정사각형, 여백 포함
    async function addPhotoPagesPaired(pairs) {
      if (!pairs || !pairs.length) return;

      // A4 고정 크기: 794×1123px
      // 레이아웃: 패딩 16px, 헤더 38px, gap 8px
      // 사진 정사각형 크기 계산:
      //   사용가능 높이 = 1123 - 32(패딩) - 38(헤더) - 8(헤더mb) - 8*2(gap) = 1029px
      //   세트당 높이 = 1029 / 3 = 343px
      //   라벨바 22px + 공종명 26px = 48px
      //   사진 높이 = 343 - 48 - 2(border) = 293px
      //   → 정사각형: 사진 너비도 293px, 전체 카드 너비: 293*2+1 = 587px (중앙정렬)
      const IMG_SZ = 293; // px - 정사각형 고정
      const CARD_W = IMG_SZ * 2 + 1; // 587px

      // 컨테이너를 A4 고정 크기로
      container.style.width  = '794px';
      container.style.height = '1123px';
      container.style.overflow = 'hidden';

      for (let i = 0; i < pairs.length; i += 3) {
        const chunk = pairs.slice(i, i+3);
        const loadedB = await Promise.all(chunk.map(p => p.before_url ? loadImgBase64(p.before_url).catch(()=>null) : Promise.resolve(null)));
        const loadedA = await Promise.all(chunk.map(p => p.after_url  ? loadImgBase64(p.after_url).catch(()=>null)  : Promise.resolve(null)));

        let cards = '';
        // 3세트 고정 (빈 세트도 자리 유지)
        for (let r = 0; r < 3; r++) {
          const p  = chunk[r] || null;
          const bi = loadedB[r] || null;
          const ai = loadedA[r] || null;
          const bHtml = bi
            ? `<img src="${bi}" style="width:${IMG_SZ}px;height:${IMG_SZ}px;object-fit:cover;display:block;flex-shrink:0">`
            : `<div style="width:${IMG_SZ}px;height:${IMG_SZ}px;background:#f0f4f8;display:flex;align-items:center;justify-content:center;font-size:11px;color:#bbb;flex-shrink:0">${p?'조치 전':''}</div>`;
          const aHtml = ai
            ? `<img src="${ai}" style="width:${IMG_SZ}px;height:${IMG_SZ}px;object-fit:cover;display:block;flex-shrink:0">`
            : `<div style="width:${IMG_SZ}px;height:${IMG_SZ}px;background:#f0f4f8;display:flex;align-items:center;justify-content:center;font-size:11px;color:#bbb;flex-shrink:0">${p?'조치 후':''}</div>`;

          cards += `
          <div style="width:${CARD_W}px;border:1px solid #d1d5db;border-radius:6px;overflow:hidden;${!p?'visibility:hidden':''}">
            <div style="display:flex">
              ${bHtml}
              <div style="width:1px;background:#d1d5db;flex-shrink:0"></div>
              ${aHtml}
            </div>
            <div style="display:flex;border-top:1px solid #d1d5db">
              <div style="width:${IMG_SZ}px;padding:4px 6px;font-size:9px;font-weight:700;text-align:center;color:#1d4ed8;background:#eff6ff;flex-shrink:0">조치 전</div>
              <div style="width:1px;background:#d1d5db;flex-shrink:0"></div>
              <div style="width:${IMG_SZ}px;padding:4px 6px;font-size:9px;font-weight:700;text-align:center;color:#15803d;background:#f0fdf4;flex-shrink:0">조치 후</div>
            </div>
            <div style="padding:5px 10px;font-size:10px;font-weight:600;text-align:center;background:#f8fafc;border-top:1px solid #e2e8f0;color:#1e293b;letter-spacing:.3px">${escHtml(p?.gongong||'')}</div>
          </div>`;
        }

        const pageHtml = `
        <div style="width:794px;height:1123px;box-sizing:border-box;padding:16px;background:#fff;display:flex;flex-direction:column;gap:0">
          <div style="display:flex;align-items:center;border-bottom:2px solid #111;padding-bottom:6px;margin-bottom:8px;flex-shrink:0">
            <span style="font-size:13px;font-weight:700;flex:1">대상처 : ${escHtml(client.name||'')}</span>
            <span style="font-size:11px;color:#555">${escHtml(report.title||'')}</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;flex:1;align-items:center;justify-content:space-between">
            ${cards}
          </div>
          ${report.note && i===0 ? `<div style="margin-top:6px;font-size:10px;color:#555;border-top:1px dashed #ccc;padding-top:5px;flex-shrink:0">※ ${escHtml(report.note)}</div>` : ''}
        </div>`;
        await renderPageToPdf(pageHtml, true);
      }

      // 컨테이너 크기 원복
      container.style.height = '';
      container.style.overflow = '';
    }

    await addPhotoPagesPaired(report.pairs || []);

    document.body.removeChild(container);

    const safeTitle = (report.title||'report').replace(/[^가-힣a-zA-Z0-9_-]/g,'_');
    pdf.save('조치사진_' + (client.name||'') + '_' + safeTitle + '_' + (report.date||'') + '.pdf');

  } catch(e) {
    console.error(e);
    alert('PDF 생성 중 오류: ' + e.message);
    try { document.body.removeChild(container); } catch(_) {}
  }

  btn.disabled = false;
  btn.textContent = '⬇ PDF';
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function loadImgBase64(url) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      const c = document.createElement('canvas');
      c.width = img.naturalWidth; c.height = img.naturalHeight;
      c.getContext('2d').drawImage(img, 0, 0);
      resolve(c.toDataURL('image/jpeg', 0.88));
    };
    img.onerror = () => reject(new Error('로드 실패: ' + url));
    const src = prmPhotoUrl(url);
    img.src = src + (src.includes('?') ? '&' : '?') + '_=' + Date.now();
  });
}


async function prmDeleteReport(rid) {
  if (!confirm('이 보고서를 삭제할까요?')) return;
  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('action', 'photo_report_delete');
  fd.append('client_id', _prmClientId);
  fd.append('rid', rid);
  try {
    const res = await fetch(location.href, {method:'POST', body:fd}).then(r=>r.json());
    if (res.ok) {
      const client = CLIENTS.find(c=>c.id===_prmClientId);
      if (client) {
        client.photo_reports = (client.photo_reports||[]).filter(r=>r.rid!==rid);
        prmRenderList(client);
      }
    }
  } catch(e) { alert('네트워크 오류'); }
}

function prmPrint(report) {
  const clientName = _prmClientName;

  // 현재 pairs 구조를 사용하고, 예전 before/after 구조도 함께 지원
  const printPairs = (report.pairs && report.pairs.length) ? report.pairs : Array.from(
    {length:Math.max((report.before||[]).length,(report.after||[]).length)},
    (_,i) => ({
      before_url:(report.before?.[i]?.url||''),
      after_url:(report.after?.[i]?.url||''),
      gongong:(report.before?.[i]?.caption||report.after?.[i]?.caption||'')
    })
  );

  // 전/후 각각 페이지 구성: 먼저 전 사진들, 다음 후 사진들
  function makePhotoPagesPaired(pairs) {
    if (!pairs || !pairs.length) return '<div style="padding:16px;text-align:center;color:#888">사진 없음</div>';
    let pages = '';
    // 3세트씩 페이지 분할, 각 사진 85mm 정사각형 고정
    for (let i = 0; i < pairs.length; i += 3) {
      const chunk = pairs.slice(i, i+3);
      let cards = '';
      for (let r = 0; r < 3; r++) {
        const p = chunk[r] || null;
        cards += `
        <div style="border:1px solid #d1d5db;border-radius:4px;overflow:hidden;${!p?'visibility:hidden':''}">
          <div style="display:flex">
            <div style="width:85mm;height:85mm;flex-shrink:0;overflow:hidden;background:#f0f4f8;display:flex;align-items:center;justify-content:center">
              ${p?.before_url ? `<img src="${prmPhotoUrl(p.before_url)}" style="width:85mm;height:85mm;object-fit:cover;display:block">` : `<span style="font-size:10px;color:#bbb">${p?'조치 전':''}</span>`}
            </div>
            <div style="width:1px;background:#d1d5db;flex-shrink:0"></div>
            <div style="width:85mm;height:85mm;flex-shrink:0;overflow:hidden;background:#f0f4f8;display:flex;align-items:center;justify-content:center">
              ${p?.after_url ? `<img src="${prmPhotoUrl(p.after_url)}" style="width:85mm;height:85mm;object-fit:cover;display:block">` : `<span style="font-size:10px;color:#bbb">${p?'조치 후':''}</span>`}
            </div>
          </div>
          <div style="display:flex;border-top:1px solid #d1d5db">
            <div style="width:85mm;padding:3px 6px;font-size:8px;font-weight:700;text-align:center;color:#1d4ed8;background:#eff6ff;flex-shrink:0">조치 전</div>
            <div style="width:1px;background:#d1d5db;flex-shrink:0"></div>
            <div style="width:85mm;padding:3px 6px;font-size:8px;font-weight:700;text-align:center;color:#15803d;background:#f0fdf4;flex-shrink:0">조치 후</div>
          </div>
          <div style="padding:4px 8px;font-size:9px;font-weight:600;text-align:center;background:#f8fafc;border-top:1px solid #e2e8f0;color:#1e293b">${p?.gongong||''}</div>
        </div>`;
      }
      pages += `
      <div class="photo-page">
        <div class="page-header">
          <span class="page-client">대상처 : ${clientName}</span>
          <span style="font-size:11px;color:#555">${report.title||''}</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">${cards}</div>
        ${report.note && i===0 ? `<div class="note-box">※ ${report.note}</div>` : ''}
      </div>`;
    }
    return pages;
  }

  const photoPages = makePhotoPagesPaired(printPairs);

  const html = `<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
<title>조치사진 - ${clientName}</title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Malgun Gothic','맑은 고딕',sans-serif; background:#fff; color:#000; }
@page { size:A4 portrait; margin:10mm 12mm; }
@media print { .no-print{display:none!important} .cover,.photo-page{page-break-after:always} }

/* 인쇄 버튼 */
.btn-print { display:inline-block;margin:12px 16px;padding:10px 24px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit; }

/* 표지 */
.cover {
  width:100%; height:100vh;
  border:3px solid #000;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:40px; page-break-after:always;
  padding:40px;
}
.cover-title-box {
  border:3px double #000; padding:20px 60px; text-align:center;
}
.cover-title { font-size:36px; font-weight:900; letter-spacing:8px; }
.cover-client { font-size:18px; font-weight:600; letter-spacing:2px; }
.cover-company { font-size:22px; font-weight:900; letter-spacing:4px; margin-top:60px; }

/* 사진 페이지 */
.photo-page { width:100%; padding:0; }
.page-header {
  display:flex; align-items:center; justify-content:space-between;
  border-bottom:2px solid #000; padding:6px 4px; margin-bottom:8px;
  font-size:13px; font-weight:700;
}
.page-section-badge {
  font-size:12px; font-weight:700; padding:3px 12px; border-radius:3px; border:1px solid currentColor;
}
.badge-before { color:#1d4ed8; background:#eff6ff; }
.badge-after  { color:#15803d; background:#f0fdf4; }
.note-box { margin-top:8px; font-size:11px; color:#555; border-top:1px dashed #ccc; padding-top:6px; }
</style></head><body>

<button class="btn-print no-print" onclick="window.print()">🖨 인쇄 / PDF 저장</button>

<!-- 표지 -->
<div class="cover">
  <div class="cover-title-box">
    <div class="cover-title">조 치 사 진</div>
  </div>
  <div>
    <div class="cover-client">대상처 : ${clientName}</div>
    <div style="font-size:13px;color:#555;margin-top:6px">공사명 : ${report.title}</div>
    <div style="font-size:13px;color:#555;margin-top:4px">발송일 : ${report.date}</div>
  </div>
  <div class="cover-company">${escHtml(COMPANY_NAME)}</div>
</div>

<!-- 사진 페이지들 -->
${photoPages}

</body></html>`;

  const w = window.open('', '_blank', 'width=900,height=750');
  w.document.write(html);
  w.document.close();
}

qfLoad();</script>

<!-- === Floating Task Panel === -->

<!-- ★ 아이템 가방 — 파일 추가 모달 -->
<dialog id="qfModal">
  <div class="modal-head">
    <strong id="qf-modal-title">📁 파일 추가</strong>
    <button type="button" class="btn ghost" onclick="document.getElementById('qfModal').close()">✕</button>
  </div>
  <div class="modal-body" style="display:grid;gap:12px">

    <!-- 소스 선택 탭 -->
    <div style="display:flex;gap:6px;background:var(--bg3);padding:4px;border-radius:var(--r-sm)">
      <button type="button" id="qf-tab-url" onclick="qfSetTab('url')"
        style="flex:1;padding:6px;border-radius:7px;border:0;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;
               background:var(--accent-dim);color:var(--link);transition:all .15s">
        🔗 URL / 경로
      </button>
      <button type="button" id="qf-tab-file" onclick="qfSetTab('file')"
        style="flex:1;padding:6px;border-radius:7px;border:0;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;
               background:transparent;color:var(--mut);transition:all .15s">
        💾 파일 업로드
      </button>
    </div>

    <!-- URL 입력 영역 -->
    <div id="qf-src-url">
      <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">URL / 서버 경로
        <input type="text" id="qf-f-url" placeholder="/data/files/checklist.xlsx 또는 https://...">
      </label>
    </div>

    <!-- 파일 업로드 영역 -->
    <div id="qf-src-file" style="display:none">
      <div id="qf-drop-zone"
        style="border:2px dashed var(--bd2);border-radius:var(--r-md);padding:20px;text-align:center;cursor:pointer;transition:all .15s"
        onclick="document.getElementById('qf-file-input').click()"
        ondragover="qfDragOver(event)" ondragleave="qfDragLeave(event)" ondrop="qfDrop(event)">
        <div style="font-size:28px;margin-bottom:6px">📂</div>
        <div style="font-size:13px;color:var(--mut)">클릭하거나 파일을 여기에 드래그</div>
        <div style="font-size:11px;color:var(--sub);margin-top:4px">PDF, Word, Excel, PPT, HWP, ZIP 등 · 최대 50MB</div>
      </div>
      <input type="file" id="qf-file-input" style="display:none"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.hwp,.hwpx,.png,.jpg,.jpeg,.gif"
        onchange="qfFileSelected(this.files[0])">
      <!-- 선택된 파일 표시 -->
      <div id="qf-file-preview" style="display:none;margin-top:8px;padding:8px 12px;
        background:var(--bg3);border:1px solid var(--bd);border-radius:var(--r-sm);
        display:none;align-items:center;gap:8px">
        <span id="qf-file-preview-icon" style="font-size:20px">📄</span>
        <div style="flex:1;min-width:0">
          <div id="qf-file-preview-name" style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
          <div id="qf-file-preview-size" style="font-size:10px;color:var(--mut)"></div>
        </div>
        <button type="button" onclick="qfClearFile()"
          style="padding:3px 8px;border-radius:5px;border:1px solid var(--bd);background:transparent;color:var(--mut);cursor:pointer;font-size:11px">✕</button>
      </div>
      <!-- 업로드 진행바 -->
      <div id="qf-progress-wrap" style="display:none;margin-top:8px">
        <div style="font-size:11px;color:var(--mut);margin-bottom:4px" id="qf-progress-label">업로드 중...</div>
        <div style="background:var(--bd);border-radius:99px;height:4px;overflow:hidden">
          <div id="qf-progress-bar" style="height:100%;background:var(--accent);border-radius:99px;width:0%;transition:width .2s"></div>
        </div>
      </div>
    </div>

    <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">파일 이름
      <input type="text" id="qf-f-name" placeholder="예: 안전점검 체크리스트" maxlength="30">
    </label>
    <label style="display:grid;gap:6px;font-size:13px;color:var(--mut)">폴더
      <select id="qf-f-group" style="width:100%;padding:8px 10px;border-radius:var(--r-sm);border:1px solid var(--bd);background:var(--bg3);color:var(--fg);font-size:13px"></select>
    </label>
    <div>
      <div style="font-size:12px;color:var(--mut);margin-bottom:6px">아이콘</div>
      <div class="qf-modal-icon-grid" id="qf-icon-picker"></div>
    </div>
  </div>
  <div class="modal-actions">
    <button type="button" class="btn ghost" onclick="document.getElementById('qfModal').close()">취소</button>
    <button type="button" class="btn" id="qf-save-btn" onclick="qfSaveFile()">저장</button>
  </div>
</dialog>

<!-- ★ 아이템 가방 — 폴더 추가 모달 -->
<dialog id="qfGroupModal">
  <div class="modal-head">
    <strong>📂 폴더 추가</strong>
    <button type="button" class="btn ghost" onclick="document.getElementById('qfGroupModal').close()">✕</button>
  </div>
  <div class="modal-body">
    <label>폴더 이름
      <input type="text" id="qf-g-name" placeholder="예: 보고서 양식" maxlength="15">
    </label>
  </div>
  <div class="modal-actions">
    <button type="button" class="btn ghost" onclick="document.getElementById('qfGroupModal').close()">취소</button>
    <button type="button" class="btn" onclick="qfSaveGroup()">추가</button>
  </div>
</dialog>

<!-- ★ 마을 문서고 모달 -->
<dialog id="clientFilesModal" class="flex-col fullscreen-mobile"
  style="min-width:min(96vw,520px);max-height:90svh;padding:0;width:min(96vw,520px)">
  <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
    <strong id="cfm-title">📁 문서고</strong>
    <button type="button" class="btn ghost" onclick="document.getElementById('clientFilesModal').close()">✕</button>
  </div>

  <!-- 업로드 영역 -->
  <div id="cfm-upload-area" style="padding:12px 16px;border-bottom:1px solid var(--bd2);flex-shrink:0">
    <div id="cfm-drop-zone"
      style="border:2px dashed var(--bd2);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all .15s;background:var(--bg3)"
      onclick="document.getElementById('cfm-file-input').click()"
      ondragover="cfmDragOver(event)" ondragleave="cfmDragLeave(event)" ondrop="cfmDrop(event)">
      <div style="font-size:26px;margin-bottom:4px">📂</div>
      <div style="font-size:13px;color:var(--mut)">클릭하거나 파일을 여기에 드래그</div>
      <div style="font-size:11px;color:var(--sub);margin-top:3px">PDF·Word·Excel·HWP·ZIP 등 · 최대 50MB</div>
    </div>
    <input type="file" id="cfm-file-input" style="display:none"
      accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.hwp,.hwpx,.png,.jpg,.jpeg,.gif,.webp"
      onchange="cfmUpload(this.files[0])">
    <!-- 진행바 -->
    <div id="cfm-progress-wrap" style="display:none;margin-top:8px">
      <div style="font-size:11px;color:var(--mut);margin-bottom:4px" id="cfm-progress-label">업로드 중...</div>
      <div style="background:var(--bd);border-radius:99px;height:4px;overflow:hidden">
        <div id="cfm-progress-bar" style="height:100%;background:#7c3aed;border-radius:99px;width:0%;transition:width .2s"></div>
      </div>
    </div>
  </div>

  <!-- 파일 목록 -->
  <div id="cfm-list"
    style="flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:10px 14px;display:grid;gap:6px;align-content:start;min-height:80px">
    <div id="cfm-empty" style="text-align:center;color:var(--sub);font-size:13px;padding:32px 0">등록된 파일이 없습니다</div>
  </div>

  <div class="modal-actions" style="flex-shrink:0;justify-content:flex-end;border-top:1px solid var(--bd2)">
    <button type="button" class="btn ghost" onclick="document.getElementById('clientFilesModal').close()">닫기</button>
  </div>
</dialog>

<script>
/* ── 마을 문서고 ── */
let _cfmClientId = '';
let _cfmClientName = '';

function openClientFilesModal(clientId) {
  // 마을명 가져오기
  const nameEl = document.getElementById('vm-title');
  _cfmClientName = nameEl ? nameEl.textContent.replace(/\s*\(.*\)/,'').trim() : '';
  _cfmClientId = clientId;
  document.getElementById('cfm-title').textContent = '📁 문서고' + (_cfmClientName ? ' — ' + _cfmClientName : '');
  cfmLoadList();
  document.getElementById('clientFilesModal').showModal();
}

function cfmExtIcon(ext) {
  const map = {
    pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
    ppt:'📊', pptx:'📊', hwp:'📝', hwpx:'📝',
    zip:'🗜', png:'🖼', jpg:'🖼', jpeg:'🖼', gif:'🖼', webp:'🖼',
    txt:'📃', csv:'📋',
  };
  return map[ext] || '📎';
}

function cfmFmtSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1024/1024).toFixed(1) + ' MB';
}

async function cfmLoadList() {
  const list = document.getElementById('cfm-list');
  const empty = document.getElementById('cfm-empty');
  list.innerHTML = '<div id="cfm-empty" style="text-align:center;color:var(--sub);font-size:13px;padding:32px 0">불러오는 중…</div>';

  const fd = new FormData();
  fd.append('action', 'cf_list');
  fd.append('csrf', '<?=h($CSRF)?>');
  fd.append('client_id', _cfmClientId);
  try {
    const res = await fetch(location.pathname + location.search, {method:'POST', body:fd});
    const data = await res.json();
    if (!data.ok || !data.files.length) {
      list.innerHTML = '<div id="cfm-empty" style="text-align:center;color:var(--sub);font-size:13px;padding:32px 0">등록된 파일이 없습니다</div>';
      return;
    }
    list.innerHTML = '';
    data.files.forEach(f => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:var(--bg3);border:1px solid var(--bd)';
      row.innerHTML = `
        <span style="font-size:22px;flex-shrink:0">${cfmExtIcon(f.ext)}</span>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${h(f.display)}</div>
          <div style="font-size:11px;color:var(--sub);margin-top:2px">${cfmFmtSize(f.size)} · ${new Date(f.mtime*1000).toLocaleDateString('ko')}</div>
        </div>
        <div style="display:flex;gap:5px;flex-shrink:0">
          <a href="${h(f.url)}" target="_blank"
            style="padding:5px 10px;border-radius:7px;border:1px solid var(--bd2);background:transparent;color:var(--fg);font-size:12px;text-decoration:none;display:inline-block;line-height:1.6">🔗 열기</a>
          <a href="${h(f.url)}" download="${h(f.display)}"
            style="padding:5px 10px;border-radius:7px;border:1px solid var(--bd2);background:transparent;color:var(--fg);font-size:12px;text-decoration:none;display:inline-block;line-height:1.6">⬇ 저장</a>
          <button onclick="cfmDelete('${h(f.fname)}')"
            style="padding:5px 10px;border-radius:7px;border:1px solid var(--inspect-bd);background:transparent;color:var(--inspect);font-size:12px;cursor:pointer">🗑</button>
        </div>`;
      list.appendChild(row);
    });
  } catch(e) {
    list.innerHTML = '<div style="text-align:center;color:#f87171;font-size:13px;padding:32px 0">불러오기 실패</div>';
  }
}

function h(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function cfmUpload(file) {
  if (!file) return;
  const prog = document.getElementById('cfm-progress-wrap');
  const bar  = document.getElementById('cfm-progress-bar');
  const lbl  = document.getElementById('cfm-progress-label');
  prog.style.display = 'block';
  bar.style.width = '0%';
  lbl.textContent = '업로드 중…';

  return new Promise(resolve => {
    const fd = new FormData();
    fd.append('action','cf_upload');
    fd.append('csrf','<?=h($CSRF)?>');
    fd.append('client_id', _cfmClientId);
    fd.append('cf_file', file);

    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = e => {
      if (e.lengthComputable) bar.style.width = Math.round(e.loaded/e.total*100)+'%';
    };
    xhr.onload = async () => {
      try {
        const data = JSON.parse(xhr.responseText);
        if (data.ok) {
          lbl.textContent = '✅ 업로드 완료!';
          bar.style.width = '100%';
          document.getElementById('cfm-file-input').value = '';
          setTimeout(()=>{ prog.style.display='none'; }, 1200);
          await cfmLoadList();
        } else {
          lbl.textContent = '❌ ' + (data.msg || '오류');
          bar.style.background = '#ef4444';
        }
      } catch(e) { lbl.textContent = '❌ 응답 파싱 오류'; }
      resolve();
    };
    xhr.onerror = () => { lbl.textContent = '❌ 네트워크 오류'; resolve(); };
    xhr.open('POST', location.pathname + location.search);
    xhr.send(fd);
  });
}

async function cfmDelete(fname) {
  if (!confirm('파일을 삭제할까요?')) return;
  const fd = new FormData();
  fd.append('action','cf_delete');
  fd.append('csrf','<?=h($CSRF)?>');
  fd.append('client_id', _cfmClientId);
  fd.append('fname', fname);
  try {
    const res = await fetch(location.pathname + location.search, {method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) cfmLoadList();
    else alert('삭제 실패');
  } catch(e) { alert('오류 발생'); }
}

function cfmDragOver(e) {
  e.preventDefault();
  document.getElementById('cfm-drop-zone').style.borderColor = '#7c3aed';
  document.getElementById('cfm-drop-zone').style.background = '#7c3aed11';
}
function cfmDragLeave(e) {
  document.getElementById('cfm-drop-zone').style.borderColor = '';
  document.getElementById('cfm-drop-zone').style.background = '';
}
function cfmDrop(e) {
  e.preventDefault();
  cfmDragLeave(e);
  const f = e.dataTransfer?.files?.[0];
  if (f) cfmUpload(f);
}
</script>

<script>
/* ── ⚔️ 8비트 게임 효과음 ── */
let _gameCtx = null;
function gameBlip(freq, dur) {
  try {
    if (!_gameCtx) _gameCtx = new (window.AudioContext||window.webkitAudioContext)();
    const o = _gameCtx.createOscillator(), g = _gameCtx.createGain();
    o.type = 'square'; o.frequency.value = freq || 880;
    g.gain.setValueAtTime(.045, _gameCtx.currentTime);
    g.gain.exponentialRampToValueAtTime(.001, _gameCtx.currentTime + (dur || .08));
    o.connect(g); g.connect(_gameCtx.destination);
    o.start(); o.stop(_gameCtx.currentTime + (dur || .08) + .02);
  } catch(e) {}
}
/* 버튼 클릭 = 블립 */
document.addEventListener('click', e => {
  if (e.target.closest('button, .btn, .nh-pill, .nh-mbtn, .cal-pro .cell')) gameBlip(740, .06);
}, true);
/* 모달 열기 = 게임 창 소리 */
(function(){
  const _sm = HTMLDialogElement.prototype.showModal;
  HTMLDialogElement.prototype.showModal = function() {
    gameBlip(523, .07); setTimeout(()=>gameBlip(784, .09), 70);
    return _sm.apply(this, arguments);
  };
})();
/* 체크박스 = 퀘스트 완료음 */
document.addEventListener('change', e => {
  if (e.target.matches('input[type=checkbox]') && e.target.checked) {
    gameBlip(659, .07); setTimeout(()=>gameBlip(880, .07), 80); setTimeout(()=>gameBlip(1319, .12), 160);
  }
}, true);
</script>

<!-- ══════════ 📄 견적서 작성 모달 (게임테마 격리) ══════════ -->
<style>
/* estimateModal 내부는 게임 테마를 완전히 덮어쓰는 독립 디자인 */
#estimateModal {
  border:none !important; box-shadow:0 24px 80px rgba(0,0,0,.5) !important;
  background:#eef1f4 !important; width:min(96vw,1180px) !important; max-width:1180px !important;
  max-height:94vh !important; padding:0 !important; overflow:hidden !important;
  color:#16202b !important;
}
#estimateModal::backdrop { background:rgba(10,15,22,.7) !important; }
#estimateModal * { font-family:'Malgun Gothic','맑은 고딕',sans-serif !important; border-radius:0; box-sizing:border-box; }
#estimateModal .est-wrap { display:flex; flex-direction:column; height:94vh; }
#estimateModal .est-bar {
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:13px 18px; background:#fff; border-bottom:1px solid #e4e9ee; flex-shrink:0;
}
#estimateModal .est-bar h2 { font-size:17px; font-weight:800; letter-spacing:-.3px; color:#16202b; }
#estimateModal .est-bar h2 small { font-weight:400; color:#6b7d92; font-size:12px; margin-left:6px; }
#estimateModal .est-bar-actions { display:flex; gap:7px; flex-wrap:wrap; }
#estimateModal .eb {
  border:1px solid #c9d2da; background:#fff; color:#16202b; padding:8px 14px;
  border-radius:8px !important; font-size:13px; font-weight:600; cursor:pointer;
}
#estimateModal .eb:hover { border-color:#3a4856; }
#estimateModal .eb.primary { background:#0d9488; color:#fff; border-color:#0d9488; }
#estimateModal .eb.danger { color:#b3271e; }
#estimateModal .eb.x { border:none; font-size:20px; padding:4px 10px; color:#6b7d92; background:transparent; }

#estimateModal .est-body { flex:1; overflow-y:auto; -webkit-overflow-scrolling:touch; padding:16px; }
#estimateModal .est-grid { display:grid; grid-template-columns:1fr; gap:14px; max-width:1140px; margin:0 auto; }

#estimateModal .ep {
  background:#fff; border:1px solid #e4e9ee; border-radius:12px !important; padding:15px 17px;
}
#estimateModal .ep-t {
  font-size:13px; font-weight:700; color:#3a4856; margin-bottom:11px;
  display:flex; align-items:center; gap:8px;
}
#estimateModal .ep-t .tg { background:#d7f0eb; color:#0d7d6f; font-size:11px; padding:2px 8px; border-radius:99px !important; font-weight:700; }

/* 칩 */
#estimateModal .echips { display:flex; flex-wrap:wrap; gap:8px; }
#estimateModal .echip {
  border:1px solid #c2d3e6; background:#eef3f9; color:#1f3a5f; border-radius:9px !important;
  padding:8px 12px; cursor:pointer; text-align:left; position:relative; transition:all .12s;
}
#estimateModal .echip:hover { border-color:#1f3a5f; box-shadow:0 2px 8px rgba(31,58,95,.14); transform:translateY(-1px); }
#estimateModal .echip .cn { font-weight:700; font-size:13px; display:block; }
#estimateModal .echip .cm { font-size:11px; color:#6b7d92; margin-top:2px; }
#estimateModal .echip .cp { font-weight:700; color:#b3271e; }
#estimateModal .echip .cx {
  position:absolute; top:-7px; right:-7px; width:19px; height:19px; border-radius:50% !important;
  background:#fff; border:1px solid #c9d2da; color:#97a3b0; font-size:12px; line-height:16px;
  text-align:center; cursor:pointer; display:none;
}
#estimateModal .echip:hover .cx { display:block; }
#estimateModal .echip .cx:hover { background:#b3271e; color:#fff; border-color:#b3271e; }
#estimateModal .echip.add {
  border:1.5px dashed #c2d3e6; background:transparent; color:#6b7d92;
  display:flex; align-items:center; justify-content:center; min-height:46px; font-weight:600;
}
#estimateModal .echip.add:hover { border-color:#1f3a5f; color:#1f3a5f; }

/* 항목 편집 */
#estimateModal .eli-row {
  display:grid; grid-template-columns:28px 1fr 90px 50px 60px 100px 28px;
  gap:6px; align-items:center; padding:5px 0; border-bottom:1px solid #eef1f4;
}
#estimateModal .eli-row.h { font-size:11px; font-weight:700; color:#3a4856; border-bottom:2px solid #c9d2da; }
#estimateModal .eli-row input {
  border:1px solid transparent; border-radius:5px !important; padding:5px 7px; font-size:13px;
  width:100%; background:#f7f9fb; color:#16202b;
}
#estimateModal .eli-row input:focus { outline:none; border-color:#1f3a5f; background:#fff; }
#estimateModal .eli-row .n { text-align:right; }
#estimateModal .eli-x { color:#b0bcc8; cursor:pointer; text-align:center; font-size:16px; }
#estimateModal .eli-x:hover { color:#b3271e; }
#estimateModal .eli-empty { text-align:center; color:#97a3b0; padding:22px; font-size:13px; }

#estimateModal .etot {
  display:flex; justify-content:flex-end; gap:22px; margin-top:12px;
  padding-top:12px; border-top:2px solid #e4e9ee; font-size:14px; flex-wrap:wrap;
}
#estimateModal .etot .tv { font-weight:700; }
#estimateModal .etot .grand .tv { color:#b3271e; font-size:18px; }

#estimateModal .efields { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px 14px; }
#estimateModal .ef { display:flex; flex-direction:column; gap:4px; }
#estimateModal .ef label { font-size:11px; font-weight:700; color:#3a4856; }
#estimateModal .ef input, #estimateModal .ef select {
  border:1px solid #c9d2da; border-radius:7px !important; padding:7px 9px; font-size:13px;
  background:#fff; color:#16202b;
}
#estimateModal .ef input:focus, #estimateModal .ef select:focus { outline:none; border-color:#1f3a5f; box-shadow:0 0 0 3px #eef3f9; }
#estimateModal .ehint { font-size:12px; color:#8593a1; margin-top:8px; }

/* 견적서 문서 (인쇄 대상) */
#estimateModal .edoc {
  background:#fff; border:1px solid #ddd; padding:14mm 13mm; margin:0 auto;
  font-family:'Batang','바탕',serif !important; color:#000; max-width:210mm;
}
#estimateModal .edoc * { font-family:'Batang','바탕',serif !important; }
#estimateModal .edoc-title {
  text-align:center; font-size:28pt; font-weight:800; letter-spacing:14px;
  padding:2px 0 8px; border-bottom:3px solid #000; margin-bottom:12px;
}
#estimateModal .edoc-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 18px; }
#estimateModal .eil td { padding:4px; font-size:11pt; vertical-align:top; }
#estimateModal .eil .lbl { white-space:nowrap; font-weight:700; letter-spacing:3px; width:90px; }
#estimateModal .eil .val { border-bottom:1px solid #999; }
#estimateModal .eil .amt .val { font-size:14pt; font-weight:800; }
#estimateModal .esup { border:1.5px solid #000; }
#estimateModal .esup table { width:100%; border-collapse:collapse; }
#estimateModal .esup td { border:.5px solid #555; padding:3px 6px; font-size:9.5pt; }
#estimateModal .esup .sh { writing-mode:vertical-rl; text-align:center; font-weight:700; letter-spacing:6px; width:22px; background:#f2f2f2; }
#estimateModal .esup .sl { background:#f7f7f7; font-weight:700; white-space:nowrap; width:50px; text-align:center; }
#estimateModal .estamp { color:#c0392b; }
#estimateModal .edoc-note { font-size:10pt; margin:12px 0 6px; }
#estimateModal table.eitems { width:100%; border-collapse:collapse; }
#estimateModal table.eitems th, #estimateModal table.eitems td { border:1px solid #000; padding:5px 6px; font-size:10pt; text-align:center; }
#estimateModal table.eitems thead th { background:#e9e9e9; font-weight:700; }
#estimateModal table.eitems .tl { text-align:left; }
#estimateModal table.eitems .tr { text-align:right; }
#estimateModal table.eitems .sum td { background:#f0f0f0; font-weight:700; }
#estimateModal table.eitems .tot td { background:#fdeeee; font-weight:700; font-size:11pt; }
#estimateModal .edoc-foot { margin-top:14px; font-size:9.5pt; }
#estimateModal .edoc-foot .ff { display:flex; gap:8px; padding:4px 0; }
#estimateModal .edoc-foot .ff b { white-space:nowrap; letter-spacing:2px; min-width:90px; }
#estimateModal .edoc-foot .ff span { border-bottom:1px solid #aaa; flex:1; }
#estimateModal .edoc-stage { background:#dfe3e8; padding:18px; border-radius:12px !important; overflow-x:auto; }

@media (max-width:760px) {
  #estimateModal .edoc-grid { grid-template-columns:1fr; gap:12px; }
  #estimateModal .edoc-title { font-size:20pt; letter-spacing:7px; }
  #estimateModal .eli-row { grid-template-columns:22px 1fr 56px 64px 24px; }
  #estimateModal .eli-row .c-spec, #estimateModal .eli-row .c-unit { display:none; }
}

/* 견적서 인쇄 전용 — 화면 폼 그대로, 진짜 텍스트로 출력 */
@media print {
  html, body { background:#fff !important; }
  /* 견적서 인쇄 모드: 모달 외 모든 것 숨김 */
  body.est-printing > *:not(#estimateModal) { display:none !important; }
  body.est-printing #estimateModal {
    position:static !important; display:block !important;
    max-height:none !important; max-width:none !important; width:auto !important;
    margin:0 !important; padding:0 !important;
    box-shadow:none !important; border:none !important; background:#fff !important;
    overflow:visible !important;
  }
  body.est-printing #estimateModal::backdrop { display:none !important; }
  /* 편집 영역·툴바 숨김, 견적서 문서만 표시 */
  body.est-printing #estimateModal .est-bar,
  body.est-printing #estimateModal .est-edit-zone { display:none !important; }
  body.est-printing #estimateModal .est-wrap { height:auto !important; display:block !important; }
  body.est-printing #estimateModal .est-body { overflow:visible !important; padding:0 !important; display:block !important; }
  body.est-printing #estimateModal .est-grid { display:block !important; gap:0 !important; }
  body.est-printing #estimateModal .edoc-stage {
    background:#fff !important; padding:0 !important; overflow:visible !important; border-radius:0 !important;
  }
  body.est-printing #estimateModal .edoc {
    border:none !important; padding:8mm 6mm !important; max-width:none !important; margin:0 !important;
    box-shadow:none !important;
  }
  /* 표/배경색이 인쇄에서도 유지되도록 */
  body.est-printing #estimateModal table.eitems th,
  body.est-printing #estimateModal table.eitems .sum td,
  body.est-printing #estimateModal table.eitems .tot td {
    -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
  }
  body.est-printing #estimateModal .edoc-title,
  body.est-printing #estimateModal .esup,
  body.est-printing #estimateModal table.eitems th,
  body.est-printing #estimateModal table.eitems td {
    -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
  }
}
</style>

<dialog id="estimateModal">
 <div class="est-wrap">
  <div class="est-bar">
    <h2>📄 견적서 작성 <small>공종 클릭 → 자동 견적 · 인쇄/PDF</small></h2>
    <div class="est-bar-actions">
      <button class="eb danger" onclick="estReset()">항목 비우기</button>
      <button class="eb primary" onclick="estSavePdf()">🖨 인쇄 / PDF 저장</button>
      <button class="eb x" onclick="document.getElementById('estimateModal').close()">✕</button>
    </div>
  </div>

  <div class="est-body">
   <div class="est-grid">

    <!-- 편집 영역 (인쇄 시 숨김) -->
    <div class="est-edit-zone">
      <!-- 공종 버튼 -->
      <div class="ep">
        <div class="ep-t">공종 버튼 <span class="tg">클릭하면 견적에 자동 추가</span></div>
        <div class="echips" id="estChips"></div>
        <div class="ehint">버튼 클릭 → 아래 견적에 단가까지 자동 입력. 버튼 위 ⋯ 클릭 또는 우클릭으로 편집/삭제. (서버에 저장되어 다른 기기에서도 공유돼요)</div>
      </div>

      <!-- 견적 항목 -->
      <div class="ep">
        <div class="ep-t">견적 항목 <span class="tg" id="estCount">0건</span></div>
        <div class="eli-row h"><span>#</span><span>공종명</span><span>규격</span><span>단위</span><span>수량</span><span class="n">단가</span><span></span></div>
        <div id="estRows"></div>
        <div class="eli-empty" id="estEmpty">위 공종 버튼을 클릭하거나 직접 추가하세요.</div>
        <div style="margin-top:10px"><button class="eb" onclick="estAddRow()">+ 빈 항목 추가</button></div>
        <div class="etot">
          <div><span style="color:#6b7d92">공급가액</span> <span class="tv" id="estSupply">0 원</span></div>
          <div><span style="color:#6b7d92">부가세(10%)</span> <span class="tv" id="estVat">0 원</span></div>
          <div class="grand"><span style="color:#6b7d92">합계</span> <span class="tv" id="estGrand">0 원</span></div>
        </div>
      </div>

      <!-- 견적 정보 -->
      <div class="ep">
        <div class="ep-t">견적 정보</div>
        <div class="efields">
          <div class="ef"><label>제출처</label><input id="e_to" placeholder="예: 녹원씨엔아이"></div>
          <div class="ef"><label>견적명</label><input id="e_title" value="소방설비 보완공사"></div>
          <div class="ef"><label>견적일자</label><input id="e_date" type="date"></div>
          <div class="ef"><label>견적번호</label><input id="e_no" placeholder="예: KH-25-06-012"></div>
          <div class="ef"><label>견적유효기간</label><input id="e_valid" value="견적일로부터 30일 이내"></div>
          <div class="ef"><label>VAT 처리</label>
            <select id="e_vatmode" onchange="estRender()">
              <option value="include">별도 (공급가 + VAT 10%)</option>
              <option value="zero">면세 / VAT 없음</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 공급자 정보 -->
      <div class="ep">
        <div class="ep-t">공급자 정보 <span class="tg">견적서에 인쇄됨 · 자동 저장</span></div>
        <div class="efields">
          <div class="ef"><label>상호</label><input id="e_sname" value="" placeholder="상호를 입력하세요"></div>
          <div class="ef"><label>대표이사</label><input id="e_sceo" value="" placeholder="대표자명"></div>
          <div class="ef"><label>사업장 주소</label><input id="e_saddr" value="" placeholder="사업장 주소"></div>
          <div class="ef"><label>종목</label><input id="e_sbiz" value="" placeholder="업종/종목"></div>
          <div class="ef"><label>전화</label><input id="e_stel" value="" placeholder="전화번호"></div>
          <div class="ef"><label>팩스</label><input id="e_sfax" value="" placeholder="팩스번호"></div>
        </div>
      </div>
    </div>

    <!-- 견적서 미리보기 (인쇄 대상) -->
    <div class="edoc-stage">
      <div class="edoc" id="estDoc"></div>
    </div>

   </div>
  </div>
 </div>
</dialog>

<!-- 공종 추가/편집 미니모달 -->
<dialog id="estChipModal" style="border:none;border-radius:14px;padding:0;width:min(92vw,400px);box-shadow:0 20px 60px rgba(0,0,0,.3)">
  <div style="padding:15px 18px;border-bottom:1px solid #e4e9ee;font-weight:700;font-size:15px;font-family:'Malgun Gothic',sans-serif" id="ecmTitle">공종 버튼 추가</div>
  <div style="padding:16px 18px;display:flex;flex-direction:column;gap:11px;font-family:'Malgun Gothic',sans-serif">
    <div class="ef"><label style="font-size:11px;font-weight:700;color:#3a4856">공종명 *</label><input id="ecm_name" placeholder="예: 감지기 교체" style="border:1px solid #c9d2da;border-radius:7px;padding:8px 10px;font-size:13px"></div>
    <div class="ef"><label style="font-size:11px;font-weight:700;color:#3a4856">규격</label><input id="ecm_spec" placeholder="예: 차동식/광전식" style="border:1px solid #c9d2da;border-radius:7px;padding:8px 10px;font-size:13px"></div>
    <div style="display:flex;gap:10px">
      <div class="ef" style="flex:1"><label style="font-size:11px;font-weight:700;color:#3a4856">단위</label><input id="ecm_unit" placeholder="EA" style="border:1px solid #c9d2da;border-radius:7px;padding:8px 10px;font-size:13px;width:100%"></div>
      <div class="ef" style="flex:1"><label style="font-size:11px;font-weight:700;color:#3a4856">기본수량</label><input id="ecm_qty" type="number" value="1" style="border:1px solid #c9d2da;border-radius:7px;padding:8px 10px;font-size:13px;width:100%"></div>
    </div>
    <div class="ef"><label style="font-size:11px;font-weight:700;color:#3a4856">단가 (원)</label><input id="ecm_price" type="number" value="0" step="1000" style="border:1px solid #c9d2da;border-radius:7px;padding:8px 10px;font-size:13px"></div>
  </div>
  <div style="padding:14px 18px;border-top:1px solid #e4e9ee;display:flex;justify-content:space-between;font-family:'Malgun Gothic',sans-serif">
    <button class="eb danger" id="ecmDel" onclick="estDeleteChip()" style="display:none">삭제</button>
    <div style="display:flex;gap:8px;margin-left:auto">
      <button class="eb" onclick="document.getElementById('estChipModal').close()">취소</button>
      <button class="eb primary" onclick="estSaveChip()">저장</button>
    </div>
  </div>
</dialog>

<script>
/* ═══════════ 📄 견적서 ═══════════ */
const EST_CSRF = '<?=h($CSRF)?>';
const EST_DEFAULT_CHIPS = [
  { name:'수신기 예비전원 교체', spec:'24V 1.3Ah', unit:'EA', qty:1, price:20000 },
  { name:'감지기 교체',          spec:'차동식/광전식', unit:'EA', qty:1, price:8000 },
  { name:'계단통로유도등 교체',  spec:'소형', unit:'EA', qty:1, price:50000 },
  { name:'유도등 교체',          spec:'중형', unit:'EA', qty:1, price:35000 },
  { name:'피난구유도등 교체',    spec:'대형', unit:'EA', qty:1, price:45000 },
  { name:'소화기 충약',          spec:'3.3kg', unit:'EA', qty:1, price:12000 },
  { name:'발신기 교체',          spec:'P형', unit:'EA', qty:1, price:25000 },
  { name:'경종 교체',            spec:'DC24V', unit:'EA', qty:1, price:18000 },
  { name:'방화셔터 수리',        spec:'전동식', unit:'식', qty:1, price:1050000 },
  { name:'옥내소화전 펌프 점검',  spec:'', unit:'식', qty:1, price:150000 },
  { name:'스프링클러 헤드 교체',  spec:'73℃', unit:'EA', qty:1, price:9000 },
  { name:'비상조명등 교체',      spec:'', unit:'EA', qty:1, price:22000 },
];
let estChips = [];
let estItems = [];
let estEditChip = -1;
let estLoaded = false;

function estPost(action, data) {
  const fd = new FormData();
  fd.append('action', action); fd.append('csrf', EST_CSRF);
  for (const k in data) fd.append(k, data[k]);
  return fetch(location.pathname + location.search, {method:'POST', body:fd}).then(r=>r.json());
}
function estEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function estFmt(n){ return (n||0).toLocaleString('ko-KR'); }

async function openEstimateModal() {
  document.getElementById('estimateModal').showModal();
  if (!estLoaded) {
    // 공종 단가표 로드
    try {
      const r = await estPost('estimate_load_chips', {});
      estChips = (r.ok && r.chips) ? r.chips : EST_DEFAULT_CHIPS.slice();
    } catch(e) { estChips = EST_DEFAULT_CHIPS.slice(); }
    // 공급자 정보 로드
    try {
      const s = await estPost('estimate_load_supplier', {});
      if (s.ok && s.supplier) for (const k in s.supplier){ const el=document.getElementById(k); if(el) el.value=s.supplier[k]; }
    } catch(e){}
    if (!document.getElementById('e_date').value) document.getElementById('e_date').value = new Date().toISOString().slice(0,10);
    // 정보/공급자 입력 → 렌더 + 공급자 자동저장
    document.querySelectorAll('#estimateModal .est-edit-zone input, #estimateModal .est-edit-zone select').forEach(el=>{
      el.addEventListener('input', ()=>{ estRender(); estSaveSupplierDebounced(); });
    });
    estLoaded = true;
  }
  estRenderChips(); estRender();
}

let _estSupT;
function estSaveSupplierDebounced() {
  clearTimeout(_estSupT);
  _estSupT = setTimeout(()=>{
    const sup = {};
    ['e_sname','e_sceo','e_saddr','e_sbiz','e_stel','e_sfax'].forEach(id=> sup[id]=document.getElementById(id).value);
    estPost('estimate_save_supplier', {supplier: JSON.stringify(sup)});
  }, 800);
}
function estSaveChips() { estPost('estimate_save_chips', {chips: JSON.stringify(estChips)}); }

function estRenderChips() {
  const box = document.getElementById('estChips'); box.innerHTML='';
  estChips.forEach((c,i)=>{
    const b=document.createElement('button'); b.className='echip'; b.onclick=()=>estAddFromChip(i);
    b.innerHTML = `<span class="cx" onclick="event.stopPropagation();estEditChipOpen(${i})">⋯</span>
      <span class="cn">${estEsc(c.name)}</span>
      <span class="cm">${c.spec?estEsc(c.spec)+' · ':''}${c.unit||''} · <span class="cp">${estFmt(c.price)}원</span></span>`;
    box.appendChild(b);
  });
  const add=document.createElement('button'); add.className='echip add'; add.innerHTML='+ 공종 추가';
  add.onclick=()=>estEditChipOpen(-1); box.appendChild(add);
}
document.getElementById('estChips').addEventListener('contextmenu', e=>{
  const chip=e.target.closest('.echip:not(.add)'); if(!chip) return; e.preventDefault();
  const i=[...e.currentTarget.children].indexOf(chip); estEditChipOpen(i);
});

function estEditChipOpen(idx) {
  estEditChip=idx;
  document.getElementById('ecmTitle').textContent = idx<0?'공종 버튼 추가':'공종 버튼 편집';
  document.getElementById('ecmDel').style.display = idx<0?'none':'block';
  const c = idx<0?{name:'',spec:'',unit:'EA',qty:1,price:0}:estChips[idx];
  ecm_name.value=c.name; ecm_spec.value=c.spec||''; ecm_unit.value=c.unit||''; ecm_qty.value=c.qty??1; ecm_price.value=c.price||0;
  document.getElementById('estChipModal').showModal();
}
function estSaveChip() {
  const name=ecm_name.value.trim(); if(!name){ alert('공종명을 입력하세요.'); return; }
  const obj={name,spec:ecm_spec.value.trim(),unit:ecm_unit.value.trim(),qty:+ecm_qty.value||1,price:+ecm_price.value||0};
  if(estEditChip<0) estChips.push(obj); else estChips[estEditChip]=obj;
  estSaveChips(); estRenderChips(); document.getElementById('estChipModal').close();
}
function estDeleteChip() {
  if(estEditChip<0) return;
  if(confirm(`"${estChips[estEditChip].name}" 버튼을 삭제할까요?`)){
    estChips.splice(estEditChip,1); estSaveChips(); estRenderChips(); document.getElementById('estChipModal').close();
  }
}

function estAddFromChip(i) {
  const c=estChips[i];
  const ex=estItems.find(it=>it.name===c.name && it.spec===c.spec && it.price===c.price);
  if(ex) ex.qty+=(c.qty||1);
  else estItems.push({name:c.name,spec:c.spec||'',unit:c.unit||'',qty:c.qty||1,price:c.price||0});
  estRender();
}
function estAddRow(){ estItems.push({name:'',spec:'',unit:'',qty:1,price:0}); estRender(); }
function estDelItem(i){ estItems.splice(i,1); estRender(); }
function estUpd(i,k,v){ estItems[i][k]=(k==='qty'||k==='price')?(+v||0):v; estRender(true); }
function estReset(){ if(confirm('견적 항목을 모두 비울까요? (공종 버튼은 유지)')){ estItems=[]; estRender(); } }

function estRender(skipRows) {
  const rows=document.getElementById('estRows');
  document.getElementById('estEmpty').style.display=estItems.length?'none':'block';
  if(!skipRows){
    rows.innerHTML='';
    estItems.forEach((it,i)=>{
      const d=document.createElement('div'); d.className='eli-row';
      d.innerHTML=`<span style="color:#97a3b0;font-size:12px">${i+1}</span>
        <input value="${estEsc(it.name)}" oninput="estUpd(${i},'name',this.value)" placeholder="공종명">
        <input class="c-spec" value="${estEsc(it.spec)}" oninput="estUpd(${i},'spec',this.value)" placeholder="규격">
        <input class="c-unit" value="${estEsc(it.unit)}" oninput="estUpd(${i},'unit',this.value)" placeholder="단위">
        <input class="n" type="number" value="${it.qty}" oninput="estUpd(${i},'qty',this.value)">
        <input class="n" type="number" step="1000" value="${it.price}" oninput="estUpd(${i},'price',this.value)">
        <span class="eli-x" onclick="estDelItem(${i})">×</span>`;
      rows.appendChild(d);
    });
  }
  document.getElementById('estCount').textContent=estItems.length+'건';
  const supply=estItems.reduce((s,it)=>s+it.qty*it.price,0);
  const vatMode=document.getElementById('e_vatmode').value;
  const vat=vatMode==='zero'?0:Math.round(supply*0.1);
  const grand=supply+vat;
  document.getElementById('estSupply').textContent=estFmt(supply)+' 원';
  document.getElementById('estVat').textContent=estFmt(vat)+' 원';
  document.getElementById('estGrand').textContent=estFmt(grand)+' 원';
  estRenderDoc(supply,vat,grand,vatMode);
}

function estRenderDoc(supply,vat,grand,vatMode) {
  const g=id=>(document.getElementById(id)?.value||'');
  const dateStr=g('e_date')?g('e_date').replace(/-/g,'. ')+'.':'';
  const MIN=8; let rowsHtml='';
  estItems.forEach((it,i)=>{
    rowsHtml+=`<tr><td>${i+1}</td><td class="tl">${estEsc(it.name)}</td><td>${estEsc(it.spec)}</td><td>${estEsc(it.unit)}</td>
      <td class="tr">${it.qty?estFmt(it.qty):''}</td><td class="tr">${it.price?estFmt(it.price):''}</td><td class="tr">${(it.qty*it.price)?estFmt(it.qty*it.price):''}</td></tr>`;
  });
  for(let i=estItems.length;i<MIN;i++) rowsHtml+=`<tr><td>${i+1}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>`;
  const vatRow=vatMode==='zero'?'':`<tr class="tot"><td></td><td class="tl">부가가치세</td><td>공급가의 10%</td><td>식</td><td class="tr">1</td><td></td><td class="tr">${estFmt(vat)}</td></tr>`;
  document.getElementById('estDoc').innerHTML=`
    <div class="edoc-title">견 적 서</div>
    <div class="edoc-grid">
      <div><table class="eil">
        <tr><td class="lbl">提 出 處</td><td class="val">${estEsc(g('e_to'))}</td></tr>
        <tr><td class="lbl">提 出 日</td><td class="val">${dateStr}</td></tr>
        <tr><td class="lbl">見 積 名</td><td class="val">${estEsc(g('e_title'))}</td></tr>
        <tr class="amt"><td class="lbl">見積金額</td><td class="val">${estFmt(grand)} 원整</td></tr>
        <tr><td></td><td style="font-size:9pt;color:#444">${vatMode==='zero'?'(VAT 면세)':'(V.A.T. 포함)'}${g('e_no')?' · '+estEsc(g('e_no')):''}</td></tr>
      </table></div>
      <div><div class="esup"><table>
        <tr><td class="sh" rowspan="5">공 급 자</td><td class="sl">상호</td><td>${estEsc(g('e_sname'))}</td></tr>
        <tr><td class="sl">대표</td><td>${estEsc(g('e_sceo'))} <span class="estamp">(인)</span></td></tr>
        <tr><td class="sl">사업장</td><td>${estEsc(g('e_saddr'))}</td></tr>
        <tr><td class="sl">종목</td><td>${estEsc(g('e_sbiz'))}</td></tr>
        <tr><td class="sl">전화</td><td>${estEsc(g('e_stel'))} / FAX ${estEsc(g('e_sfax'))}</td></tr>
      </table></div></div>
    </div>
    <div class="edoc-note">- 아래와 같이 견적합니다.</div>
    <table class="eitems">
      <thead><tr><th style="width:7%">번호</th><th style="width:30%">공 종 명</th><th style="width:17%">규 격</th><th style="width:8%">단위</th><th style="width:9%">수량</th><th style="width:14%">단 가</th><th style="width:15%">금 액</th></tr></thead>
      <tbody>${rowsHtml}</tbody>
      <tfoot>
        <tr class="sum"><td></td><td class="tl">소 계 (공급가액)</td><td colspan="4"></td><td class="tr">${estFmt(supply)}</td></tr>
        ${vatRow}
        <tr class="tot"><td></td><td class="tl">합 계 금 액</td><td colspan="4"></td><td class="tr">${estFmt(grand)}</td></tr>
      </tfoot>
    </table>
    <div class="edoc-foot">
      <div class="ff"><b>견적유효기간</b><span>${estEsc(g('e_valid'))}</span></div>
      <div class="ff"><b>견적조건</b><span></span></div>
    </div>`;
}

// 📥 견적서를 PDF로 저장 — 브라우저 인쇄 기능 사용 (진짜 텍스트, 복사·검색 가능)
function estSavePdf() {
  if (!estItems.length) { alert('견적 항목이 없습니다. 공종 버튼을 눌러 항목을 추가해주세요.'); return; }

  // 인쇄 모드 진입: 견적서 폼만 깨끗하게 출력
  document.body.classList.add('est-printing');

  const cleanup = () => {
    document.body.classList.remove('est-printing');
    window.removeEventListener('afterprint', cleanup);
  };
  window.addEventListener('afterprint', cleanup);

  // 렌더 안정화 후 인쇄창 호출
  setTimeout(() => {
    window.print();
    // afterprint를 못 받는 일부 브라우저 대비 (안전망)
    setTimeout(() => { if (document.body.classList.contains('est-printing')) cleanup(); }, 1500);
  }, 80);
}
</script>

<!-- 건물명·주소 검색: 카카오 장소(키워드) 검색 -->
<div id="cm-pop" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);
     align-items:flex-start;justify-content:center;padding:8vh 16px">
  <div style="background:#16203a;border:1px solid #2a4a7a;border-radius:14px;width:100%;max-width:460px;
       box-shadow:0 24px 60px rgba(0,0,0,.5);overflow:hidden;display:flex;flex-direction:column;max-height:78vh">
    <div style="display:flex;align-items:center;gap:8px;padding:14px 16px;border-bottom:1px solid #243456">
      <input id="cm-pop-input" placeholder="상호명 또는 주소 입력 후 Enter"
             style="flex:1;background:#ffffff;border:1px solid #2a4a7a;border-radius:9px;padding:10px 12px;color:#1a2436;font-size:14px">
      <button type="button" onclick="cmPopSearch()" style="background:#2563eb;color:#fff;border:0;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer;white-space:nowrap">검색</button>
      <button type="button" onclick="cmPopClose()" style="background:transparent;color:#8a97ad;border:0;font-size:22px;cursor:pointer;line-height:1">×</button>
    </div>
    <div id="cm-pop-list" style="overflow-y:auto;padding:6px"></div>
    <div id="cm-pop-hint" style="padding:10px 16px;font-size:12px;color:#8a97ad;border-top:1px solid #243456">상호명이나 주소를 입력하고 검색하세요.</div>
  </div>
</div>
<script src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?=h(KAKAO_JS_KEY)?>&libraries=services&autoload=false"></script>
<script>
(function(){
  var places = null, geocoder = null;

  // 거래처 200개 제한 안내
  try {
    var q = new URLSearchParams(location.search);
    if (q.get('err') === 'limit') {
      alert('거래처는 한 계정당 최대 200개까지 등록할 수 있습니다.\n기존 거래처를 정리한 뒤 다시 시도해 주세요.');
    }
  } catch(e){}

  function initKakao(){
    if (typeof kakao === 'undefined' || !kakao.maps){ return false; }
    if (places) return true;
    try {
      kakao.maps.load(function(){
        if (kakao.maps.services){
          places   = new kakao.maps.services.Places();
          geocoder = new kakao.maps.services.Geocoder();
        }
      });
    } catch(e){ console.error('[kakao init]', e); return false; }
    return true;
  }
  initKakao();
  // SDK가 늦게 로드되는 경우 대비해 잠깐 재시도
  var __tries = 0;
  var __iv = setInterval(function(){
    __tries++;
    if (places || __tries > 20){ clearInterval(__iv); return; }
    initKakao();
  }, 300);

  // 선택한 좌표/주소를 폼+지도에 반영
  function applyPlace(name, addr, la, ln){
    var addrEl = document.getElementById('cm-addr');
    var latEl  = document.getElementById('cm-lat');
    var lngEl  = document.getElementById('cm-lng');
    var nameEl = document.querySelector('#clientModal input[name="name"]');
    var hint   = document.getElementById('cm-geohint');
    if (addrEl) addrEl.value = addr || '';
    if (latEl)  latEl.value  = la;
    if (lngEl)  lngEl.value  = ln;
    if (nameEl && !nameEl.value && name) nameEl.value = name;
    if (hint){ hint.textContent = '✅ "'+(name||addr)+'" 위치가 지정되었습니다.'; hint.style.color = '#7fb069'; }
    try {
      if (typeof map !== 'undefined' && map){
        map.setView([la, ln], 17);
        if (window.__cmPreviewMarker){ map.removeLayer(window.__cmPreviewMarker); }
        window.__cmPreviewMarker = L.marker([la, ln]).addTo(map).bindPopup('📍 '+(name||'등록할 위치')).openPopup();
      }
    } catch(e){}
    cmPopClose();
  }

  // 팝업 열기/닫기
  window.cmPopClose = function(){
    var p = document.getElementById('cm-pop'); if (p) p.style.display='none';
  };
  function cmPopOpen(initialKeyword){
    var p = document.getElementById('cm-pop'); if (!p) return;
    p.style.display='flex';
    var inp = document.getElementById('cm-pop-input');
    if (inp){ inp.value = initialKeyword || ''; setTimeout(function(){ inp.focus(); }, 50); }
    var list = document.getElementById('cm-pop-list'); if (list) list.innerHTML='';
    setPopHint('상호명이나 주소를 입력하고 검색하세요.', '#8a97ad');
    if (initialKeyword) cmPopSearch();
  }
  function setPopHint(msg, color){
    var h = document.getElementById('cm-pop-hint');
    if (h){ h.textContent = msg; h.style.color = color || '#8a97ad'; }
  }

  // 팝업 안 결과 렌더
  function renderResults(list){
    var box = document.getElementById('cm-pop-list');
    if (!box) return;
    box.innerHTML='';
    if (!list || !list.length){
      setPopHint('검색 결과가 없습니다. 다른 상호명/주소로 시도해보세요.', '#d99a3a');
      return;
    }
    setPopHint('아래에서 정확한 위치를 선택하세요. ('+list.length+'건)', '#8a97ad');
    list.forEach(function(p){
      var addr = p.road_address_name || p.address_name || '';
      var row = document.createElement('div');
      row.style.cssText='padding:11px 12px;border-radius:9px;cursor:pointer;margin:2px 0';
      row.onmouseover=function(){row.style.background='rgba(37,99,235,.18)';};
      row.onmouseout =function(){row.style.background='';};
      row.innerHTML='<div style="font-weight:700;font-size:14px;color:#1a2436">'+(p.place_name||addr)+'</div>'+
                    '<div style="font-size:12px;color:#9aa0a8;margin-top:3px">'+addr+
                    (p.phone?(' · '+p.phone):'')+'</div>';
      row.onclick=function(){ applyPlace(p.place_name, addr, parseFloat(p.y), parseFloat(p.x)); };
      box.appendChild(row);
    });
  }

  // 팝업 안 검색 실행
  window.cmPopSearch = function(){
    var kw = (document.getElementById('cm-pop-input')||{}).value || '';
    kw = kw.trim();
    if (!kw){ setPopHint('검색어를 입력하세요.', '#d99a3a'); return; }
    if (typeof kakao === 'undefined' || !kakao.maps){
      setPopHint('⚠ 카카오 지도 SDK가 로드되지 않았습니다. (F12 콘솔 확인)', '#d99a3a');
      return;
    }
    if (!places){ initKakao(); setPopHint('지도 모듈 준비 중… 1~2초 후 다시 검색하세요.', '#d99a3a'); return; }
    setPopHint('검색 중…', '#9aa0a8');
    places.keywordSearch(kw, function(data, status){
      if (status === kakao.maps.services.Status.OK){
        renderResults(data);
      } else if (geocoder){
        geocoder.addressSearch(kw, function(res, st){
          if (st === kakao.maps.services.Status.OK && res[0]){
            renderResults([{ place_name: kw, address_name: res[0].address_name,
                             road_address_name: (res[0].road_address&&res[0].road_address.address_name)||'',
                             x: res[0].x, y: res[0].y }]);
          } else { renderResults([]); }
        });
      } else { renderResults([]); }
    });
  };

  // 폼의 "검색" 버튼 → 팝업 오픈 (입력칸 값 있으면 바로 검색)
  window.placeSearch = function(){
    var kw = (document.getElementById('cm-search')||{}).value || '';
    cmPopOpen(kw.trim());
  };

  // 팝업 입력칸 Enter
  document.addEventListener('keydown', function(e){
    if (e.key === 'Enter'){
      var pop = document.getElementById('cm-pop');
      if (pop && pop.style.display === 'flex' && document.activeElement &&
          document.activeElement.id === 'cm-pop-input'){
        e.preventDefault(); cmPopSearch();
      }
    }
    if (e.key === 'Escape'){ cmPopClose(); }
  });
})();
</script>

<!-- ═══ 빠른 메모 — 우하단 접이식 ═══ -->
<style>
.qm{position:fixed;right:18px;bottom:18px;z-index:90;font-family:inherit}
.qm__tab{display:inline-flex;align-items:center;gap:7px;padding:11px 17px;border-radius:999px;
  border:0;background:#1a2436;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;
  box-shadow:0 8px 24px rgba(15,25,50,.28);font-family:inherit}
.qm__tab:hover{transform:translateY(-1px)}
.qm__tab .dot{width:7px;height:7px;border-radius:50%;background:#fbbf24}
.qm__panel{position:absolute;right:0;bottom:54px;width:min(340px,calc(100vw - 36px));
  background:#fffbeb;border:1px solid #fde68a;border-radius:14px;overflow:hidden;
  box-shadow:0 16px 44px rgba(15,25,50,.22);display:none}
.qm.open .qm__panel{display:block}
.qm__head{display:flex;align-items:center;justify-content:space-between;
  padding:10px 14px;background:#fef3c7;border-bottom:1px solid #fde68a}
.qm__head b{font-size:13px;color:#92400e}
.qm__head .st{font-size:11px;color:#b45309}
.qm__body textarea{display:block;width:100%;min-height:180px;max-height:50vh;padding:12px 14px;
  border:0;background:transparent;resize:vertical;font-size:13.5px;line-height:1.7;
  font-family:inherit;color:#451a03;outline:none}
.qm__body textarea::placeholder{color:#d97706;opacity:.55}
</style>

<div class="qm" id="qm">
  <div class="qm__panel">
    <div class="qm__head">
      <b>📌 빠른 메모</b>
      <span class="st" id="qmState"><?= !empty($qmemo['updated']) ? h($qmemo['updated']).' 저장' : '' ?></span>
    </div>
    <div class="qm__body">
      <textarea id="qmText" placeholder="중요한 내용을 적어두세요. 자동으로 저장됩니다."><?=h($qmemo['text'] ?? '')?></textarea>
    </div>
  </div>
  <button type="button" class="qm__tab" id="qmTab"><span class="dot"></span>메모</button>
</div>

<script>
(function(){
  const qm=document.getElementById('qm'), tab=document.getElementById('qmTab');
  const ta=document.getElementById('qmText'), st=document.getElementById('qmState');
  const CSRF=<?=json_encode($CSRF)?>;

  /* 접힘 상태 기억 */
  try{ if(localStorage.getItem('qmOpen')==='1') qm.classList.add('open'); }catch(e){}
  tab.addEventListener('click', ()=>{
    qm.classList.toggle('open');
    try{ localStorage.setItem('qmOpen', qm.classList.contains('open')?'1':'0'); }catch(e){}
    if(qm.classList.contains('open')) ta.focus();
  });

  /* 자동 저장 — 입력이 멈추고 0.8초 뒤 */
  let t=null, last=ta.value;
  function save(){
    if(ta.value===last) return;
    const v=ta.value;
    st.textContent='저장 중…';
    fetch(location.pathname, {method:'POST', credentials:'same-origin',
      body:new URLSearchParams({action:'qmemo_save', csrf:CSRF, text:v})})
      .then(r=>r.json())
      .then(j=>{ if(j.ok){ last=v; st.textContent=j.updated+' 저장'; }
                 else st.textContent='저장 실패'; })
      .catch(()=>{ st.textContent='통신 오류'; });
  }
  ta.addEventListener('input', ()=>{ clearTimeout(t); st.textContent='입력 중…'; t=setTimeout(save,800); });
  /* 떠나기 전 마지막 저장 */
  window.addEventListener('beforeunload', ()=>{ if(ta.value!==last){
    try{ navigator.sendBeacon(location.pathname,
      new URLSearchParams({action:'qmemo_save', csrf:CSRF, text:ta.value})); }catch(e){}
  }});
})();
</script>

<script>
/* ── 거래처 메모 저장 ── 저장하면 지도 마커에 표시가 붙는다 */
(function(){
  const btn=document.getElementById('vm-memo-save');
  if(!btn) return;
  btn.addEventListener('click', ()=>{
    const id=document.getElementById('vm-id').value;
    if(!id){ alert('거래처를 먼저 선택하세요.'); return; }
    const text=document.getElementById('vm-memo-text').value;
    const flag=document.getElementById('vm-memo-flag').checked;
    const st=document.getElementById('vm-memo-st');
    btn.disabled=true; st.textContent='저장 중…';

    const body=new URLSearchParams({action:'client_memo', csrf:CSRF, id:id, memo:text});
    if(flag) body.append('flag','1');

    fetch(location.pathname, {method:'POST', credentials:'same-origin', body})
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){ st.textContent='저장 실패'; btn.disabled=false; return; }
        st.textContent = j.memo_at ? (j.memo_at+' 저장') : '메모 없음';
        /* 메모리 + 마커 갱신 (새로고침 없이 바로 반영) */
        const c=CLIENTS.find(x=>x.id===id);
        if(c){
          c.memo=j.memo; c.flag=j.flag; c.memo_at=j.memo_at;
          const m=markers && markers.get(id);
          if(m) m.setIcon(rebuildIcon(c, selectedIds.has(id)));
        }
        btn.disabled=false;
      })
      .catch(()=>{ st.textContent='통신 오류'; btn.disabled=false; });
  });
})();
</script>
</body>
</html>
