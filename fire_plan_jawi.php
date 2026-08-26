<?php
// fire_plan_jawi.php — 자위소방대 편성표 (건물 소방안전관리자용, JSON 저장)
//   fire_plan.php 목록에서 링크로 진입. 이름 붙여넣기 → 자동 배치 → 저장/인쇄.
//   2026-08 개편: 교육훈련 사진 섹션 제거, 좌측 목록 + 우측 단계형 작업영역 UI로 재구성.
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

/* 건물 기본정보를 불러와 대상물명·근무형태를 미리 채웁니다.
   같은 내용을 두 번 적지 않도록 하기 위한 것입니다. */
$JW_SITE = '';
$JW_WORK = '';
$JW_MGR  = '';
$JW_MGRTEL = '';
if (is_file(__DIR__ . '/building_info.php')) {
  require_once __DIR__ . '/building_info.php';
  if (function_exists('bi_load')) {
    $jbi = bi_load();
    $JW_SITE = trim((string)($jbi['name'] ?? ''));
    /* 근무인원이 적혀 있으면 근무형태를 추정합니다 */
    $night = trim((string)($jbi['wd_night'] ?? ''));
    $JW_WORK = ($night !== '' && $night !== '0') ? '교대근무(주·야간)' : '단일근무(주간)';
    if (!empty($jbi['mgrs']) && is_array($jbi['mgrs'])) {
      foreach ($jbi['mgrs'] as $m) {
        if (!is_array($m) || trim((string)($m['name'] ?? '')) === '') continue;
        $JW_MGR = trim((string)$m['name']);
        $JW_MGRTEL = trim((string)($m['tel'] ?? ''));
        if (strpos((string)($m['type'] ?? ''), '주') === 0) break;
      }
    }
  }
}
/* 어디서 들어왔는지에 따라 '돌아가기' 목적지를 정합니다 */
$JW_BACK = (strpos((string)($_SERVER['HTTP_REFERER'] ?? ''), 'building_manager.php') !== false)
  ? ['/building_manager.php', '← 건물 관리 홈']
  : ['/fire_plan.php', '← 소방계획서 목록'];

/* 사용자별 편성표 저장 폴더 (fire_plan과 같은 규칙) */
require_once __DIR__ . '/user_key.php';
function jawi_user_key(): string {
  return app_user_key();   // 회원을 특정하지 못하면 '' (예전 kakao_guest 통합 제거)
}
function jawi_file(): string {
  $k = jawi_user_key();
  if ($k === '') return '';
  $dir = __DIR__ . '/data/fireplan/' . $k;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/_jawi.json';   // 편성표 목록 (계획서와 별도)
}
function jawi_read(): array {
  $f = jawi_file();
  if ($f === '' || !file_exists($f)) return [];
  $raw = @file_get_contents($f);
  if ($raw === false || trim($raw) === '') return [];
  $a = json_decode($raw, true);
  return is_array($a) ? $a : [];
}
function jawi_write(array $plans): bool {
  $f = jawi_file();
  if ($f === '') return false;   // 회원을 모르면 저장하지 않는다
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($plans, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
function jawi_uuid(): string {
  return date('YmdHis') . substr((string)random_int(1000,9999), 0, 4);
}

/* CSRF */
if (empty($_SESSION['fp_csrf'])) $_SESSION['fp_csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['fp_csrf'];
function jawi_csrf_ok(): bool { return hash_equals($_SESSION['fp_csrf'] ?? '-', $_POST['csrf'] ?? ''); }

/* ── 저장 API (fetch로 호출) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  if (!jawi_csrf_ok()) { echo json_encode(['ok'=>false,'msg'=>'잘못된 요청']); exit; }
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'fire_save') {
    $data = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($data)) { echo json_encode(['ok'=>false,'msg'=>'형식 오류']); exit; }
    $pid = trim((string)($_POST['plan_id'] ?? ''));
    $clean = [
      'site_name'  => mb_substr(trim($data['site_name'] ?? ''), 0, 80),
      'work_type'  => mb_substr(trim($data['work_type'] ?? ''), 0, 40),
      /* 적어둔 명단 원문 — 불러오기 할 때 그대로 되살립니다 */
      'bulk_text'  => mb_substr((string)($data['bulk_text'] ?? ''), 0, 20000),
      'cmd'        => array_map(fn($v)=>mb_substr((string)$v,0,200), (array)($data['cmd'] ?? [])),
      'deputy'     => array_map(fn($v)=>mb_substr((string)$v,0,200), (array)($data['deputy'] ?? [])),
      'groups'     => [],
    ];
    foreach ((array)($data['groups'] ?? []) as $g) {
      if (!is_array($g)) continue;
      $mem = [];
      foreach ((array)($g['members'] ?? []) as $m) {
        if (!is_array($m)) continue;
        $mem[] = [
          'name' => mb_substr(trim($m['name'] ?? ''), 0, 30),
          'tel'  => mb_substr(trim($m['tel']  ?? ''), 0, 30),
          'task' => mb_substr(trim($m['task'] ?? ''), 0, 120),
        ];
      }
      $clean['groups'][] = ['name'=>mb_substr(trim($g['name'] ?? ''),0,30), 'members'=>$mem];
    }
    $plans = jawi_read();
    $now = date('Y-m-d H:i:s');
    $updated = false;
    if ($pid !== '') {
      foreach ($plans as &$p) {
        if (($p['id'] ?? '') === $pid) {
          $clean['id']=$pid; $clean['created']=$p['created']??$now; $clean['saved']=$now;
          $p = $clean; $updated = true; break;
        }
      }
      unset($p);
    }
    if (!$updated) {
      $pid = jawi_uuid();
      $clean['id']=$pid; $clean['created']=$now; $clean['saved']=$now;
      array_unshift($plans, $clean);
    }
    if (count($plans) > 300) $plans = array_slice($plans, 0, 300);
    jawi_write($plans);
    echo json_encode(['ok'=>true,'id'=>$pid,'saved'=>$now,'updated'=>$updated]); exit;
  }

  if ($act === 'fire_list') {
    $plans = jawi_read();
    $list = array_map(function($p){
      $cnt = 0;
      foreach (($p['groups'] ?? []) as $g) $cnt += count($g['members'] ?? []);
      $cnt += 2;
      return ['id'=>$p['id']??'', 'site_name'=>$p['site_name']??'', 'total'=>$cnt, 'saved'=>$p['saved']??''];
    }, $plans);
    echo json_encode(['ok'=>true,'list'=>$list]); exit;
  }

  if ($act === 'fire_load') {
    $pid = (string)($_POST['plan_id'] ?? '');
    foreach (jawi_read() as $p) if (($p['id']??'')===$pid) { echo json_encode(['ok'=>true,'plan'=>$p]); exit; }
    echo json_encode(['ok'=>true,'plan'=>null]); exit;
  }

  if ($act === 'fire_delete') {
    $pid = (string)($_POST['plan_id'] ?? '');
    $plans = array_values(array_filter(jawi_read(), fn($p)=>($p['id']??'')!==$pid));
    jawi_write($plans);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($act === 'fire_dup') {
    $pid = (string)($_POST['plan_id'] ?? '');
    $plans = jawi_read();
    foreach ($plans as $p) {
      if (($p['id']??'')===$pid) {
        $copy = $p; $copy['id']=jawi_uuid();
        $copy['site_name']=($p['site_name']??'').' (복사본)';
        $copy['created']=$copy['saved']=date('Y-m-d H:i:s');
        array_unshift($plans, $copy); jawi_write($plans);
        echo json_encode(['ok'=>true]); exit;
      }
    }
    echo json_encode(['ok'=>false,'msg'=>'원본 없음']); exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'알 수 없는 요청']); exit;
}

$nick = $_SESSION['nickname'] ?? '사용자';
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
?>

<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>자위소방대 편성표</title>
<style>
  :root{
    --red:#c0392b; --red-d:#a53024; --navy:#3a5572; --ink:#1f2430;
    --line:#9aa0ab; --mut:#6b7280; --bg:#eef1f6; --card:#fff; --tintR:#fbeeec;
    --brd:#e3e7ee; --radius:14px;
    --shadow:0 1px 2px rgba(16,24,40,.04),0 4px 14px rgba(16,24,40,.06);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{-webkit-text-size-adjust:100%}
  body{font-family:'Pretendard','Malgun Gothic','Apple SD Gothic Neo',sans-serif;
    background:var(--bg);color:var(--ink);font-size:14px;line-height:1.55;
    padding-bottom:96px}

  /* ══ 상단 고정 바 ══ */
  .appbar{position:sticky;top:0;z-index:30;background:rgba(255,255,255,.92);
    backdrop-filter:saturate(180%) blur(8px);border-bottom:1px solid var(--brd)}
  .appbar__in{max-width:1240px;margin:0 auto;padding:11px 18px;
    display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .appbar h1{font-size:17px;font-weight:800;letter-spacing:-.2px;display:flex;align-items:center;gap:8px}
  .appbar .status{font-size:12px;font-weight:700;padding:4px 11px;border-radius:999px;
    background:#eef2fa;color:var(--navy)}
  .appbar .status.edit{background:#fdf1ef;color:var(--red)}
  .appbar .sp{margin-left:auto}
  .appbar .back{font-size:13px;color:var(--navy);text-decoration:none;
    border:1px solid #d6dce6;border-radius:9px;padding:7px 12px;background:#fff;font-weight:600}
  .appbar .back:hover{background:#f6f8fb}

  /* 상단 바 바로가기 아이콘 (건물관리·알림·결제) */
  .appnav{display:flex;align-items:center;gap:4px;margin-right:6px}
  .appnav__ic{display:flex;align-items:center;justify-content:center;width:34px;height:34px;
    border-radius:9px;color:var(--mut);border:1px solid transparent;transition:.14s}
  .appnav__ic svg{width:18px;height:18px}
  .appnav__ic:hover{background:#f0f4fa;border-color:#d6dce6;color:var(--navy)}
  @media(max-width:560px){.appnav{display:none}}

  /* ══ 2단 레이아웃 ══ */
  .layout{max-width:1240px;margin:0 auto;padding:18px;
    display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:18px;align-items:start}
  /* 마크업은 그대로 두고 화면 순서만 바꿉니다 — 작업 영역이 왼쪽, 패널이 오른쪽 */
  .layout > main{order:1}
  .layout > .side{order:2}
  @media (max-width:900px){
    .layout{grid-template-columns:1fr;padding:14px}
    .layout > main{order:2}      /* 좁은 화면에선 패널을 위로 올려 먼저 보이게 */
    .layout > .side{order:1}
  }

  .card{background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
    box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px}
  .card__hd{display:flex;align-items:center;gap:9px;padding:13px 16px;border-bottom:1px solid #eef1f5;
    background:linear-gradient(180deg,#fff,#fbfcfe)}
  .card__hd h2{font-size:14.5px;font-weight:750;letter-spacing:-.2px;flex:1;min-width:0}
  .card__hd .sub{font-size:11.5px;color:var(--mut);font-weight:500;margin-top:2px;display:block}
  .card__bd{padding:16px}
  .stepno{width:24px;height:24px;flex:0 0 24px;border-radius:8px;background:var(--navy);color:#fff;
    display:inline-flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800}
  .stepno.done{background:#16a34a}
  .stepno.wait{background:#c9d0dc}

  /* 사이드바 */
  .side{position:sticky;top:66px}

  /* ── 진행 현황 패널 (building_manager.php 와 같은 구성) ── */
  .prog{background:#fff;border:1px solid var(--brd);border-radius:var(--radius);
    padding:14px 16px;margin-bottom:12px;box-shadow:var(--shadow)}
  .prog__t{font-size:12px;font-weight:800;color:var(--mut);letter-spacing:.03em;margin-bottom:10px}
  .pstep{display:flex;align-items:center;gap:9px;padding:8px 4px;font-size:13px;font-weight:700;
    color:var(--mut);border-radius:8px;transition:.12s}
  .pstep .no{width:20px;height:20px;border-radius:50%;background:#e8edf5;color:var(--mut);
    display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
  .pstep__label{min-width:0;flex:1}
  .pstep--done{color:#15803d}
  .pstep--done .no{background:#22c55e;color:#fff}
  .pstep--now{color:var(--navy)}
  .pstep--now .no{background:var(--navy);color:#fff;box-shadow:0 0 0 3px rgba(58,85,114,.16)}
  .pstep+.pstep{border-top:1px dashed var(--brd)}
  .ptag{margin-left:auto;font-size:10px;font-weight:800;padding:2px 8px;border-radius:999px;
    flex-shrink:0;white-space:nowrap}
  .ptag--done{background:#f0fdf4;color:#15803d}
  .ptag--wait{background:#eef1f6;color:#8a94a6}
  .ptag--now{background:#eef4fb;color:var(--navy)}
  .prog__hint{font-size:11px;color:var(--mut);margin-top:10px;text-align:center;line-height:1.6}
  @media (max-width:900px){ .side{position:static} }
  .side .card{margin-bottom:12px}
  .planlist{display:flex;flex-direction:column;gap:0;max-height:52vh;overflow-y:auto;
    padding:6px 12px 12px;background:#fff}
  /* 몇 개 저장돼 있는지 제목 옆에 바로 보여줍니다 (비어 있으면 표시 안 함) */
  .cnt:not(:empty){display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 6px;margin-left:7px;border-radius:999px;
    background:var(--red);color:#fff;font-size:11.5px;font-weight:800;vertical-align:middle}
  @media (max-width:900px){ .planlist{max-height:230px} }

  /* ── 저장된 편성표 목록 (building_manager 의 진행 현황과 같은 방식) ── */
  .plan-card{display:block;padding:10px 6px;border-radius:8px;background:transparent;
    cursor:pointer;transition:.12s;border:0}
  .plan-card + .plan-card{border-top:1px dashed var(--brd)}
  .plan-card:hover{background:#f2f6fd}
  .plan-card.active{background:#fdf5f4;box-shadow:0 0 0 2px #f6d9d4;border-radius:10px}
  .plan-card.active + .plan-card{border-top:0}
  .pc-row{display:flex;align-items:center;gap:9px}
  .pc-no{width:20px;height:20px;border-radius:50%;background:#e8edf5;color:var(--mut);
    display:inline-flex;align-items:center;justify-content:center;font-size:11px;
    font-weight:800;flex-shrink:0}
  .plan-card.active .pc-no{background:var(--red);color:#fff;
    box-shadow:0 0 0 3px rgba(192,57,43,.14)}
  .plan-card .pc-name{font-weight:700;font-size:13px;color:var(--ink);flex:1;min-width:0;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .pc-tag{margin-left:auto;font-size:10px;font-weight:800;padding:2px 8px;border-radius:999px;
    flex-shrink:0;white-space:nowrap;background:#eef1f6;color:#8a94a6}
  .plan-card.active .pc-tag{background:#fdecea;color:var(--red)}
  .plan-card .pc-meta{font-size:11px;color:var(--mut);margin:4px 0 0 29px}
  .plan-card .pc-btns{display:none;gap:5px;margin:8px 0 2px 29px}
  .plan-card.active .pc-btns,.plan-card:hover .pc-btns{display:flex}
  .pcbtn{flex:1;border:1px solid #dde2ea;background:#fff;border-radius:7px;padding:5px 0;
    font-size:11.5px;cursor:pointer;font-family:inherit;color:var(--mut);font-weight:600}
  .pcbtn:hover{background:#f5f7fa;color:var(--ink)}
  .pcbtn.del:hover{background:#fdf1ef;color:var(--red);border-color:#eebfb8}
  .planlist .empty{font-size:12.5px;color:var(--mut);padding:20px 8px;text-align:center;line-height:1.7}

  /* 폼 요소 */
  label.fld{display:block;font-size:12px;color:var(--mut);margin:0 0 5px;font-weight:700}
  input[type=text],textarea,select{width:100%;border:1px solid #d6dce6;border-radius:10px;
    padding:10px 12px;font-size:13.5px;font-family:inherit;background:#fff;color:var(--ink);
    transition:.15s;outline:none}
  input[type=text]:focus,textarea:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(58,85,114,.12)}
  textarea{resize:vertical;line-height:1.7}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:560px){ .grid2{grid-template-columns:1fr} }
  .btn{border:none;border-radius:10px;padding:10px 16px;font-size:13.5px;font-weight:700;
    cursor:pointer;font-family:inherit;transition:.15s;display:inline-flex;align-items:center;gap:6px}
  .btn:active{transform:translateY(1px)}
  .btn-primary{background:var(--red);color:#fff;box-shadow:0 2px 8px rgba(192,57,43,.25)}
  .btn-primary:hover{background:var(--red-d)}
  .btn-navy{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(58,85,114,.22)}
  .btn-navy:hover{background:#2f465e}
  .btn-success{background:#16a34a;color:#fff;box-shadow:0 2px 8px rgba(22,163,74,.22);
    text-decoration:none}
  .btn-success:hover{background:#15803d;color:#fff}
  .btn-ghost{background:#fff;border:1px solid #d6dce6;color:var(--ink)}
  .btn-ghost:hover{background:#f5f7fa}
  .btn-sm{padding:6px 11px;font-size:12px;border-radius:8px}

  /* 접힌 상태의 '명단 고치기' 버튼 — 눈에 띄게 */
  .btn-accent{background:#eff6ff;border-color:#9cc0f5;color:var(--navy);font-weight:700}

  /* 접혔을 때 카드 자리에 나타나는 안내 줄 (누르면 다시 펴집니다) */
  .reopen{display:flex;align-items:center;gap:12px;width:100%;text-align:left;
    background:#f7faff;border:1px dashed #9cc0f5;border-radius:12px;
    padding:14px 16px;margin:0 18px 18px;width:calc(100% - 36px);
    cursor:pointer;font-family:inherit;transition:.14s}
  .reopen:hover{background:#eff6ff;border-color:var(--brand);border-style:solid}
  .reopen__ic{font-size:18px;flex-shrink:0}
  .reopen__tx{flex:1;min-width:0}
  .reopen__tx b{display:block;font-size:13.5px;font-weight:700;color:var(--navy)}
  .reopen__tx small{display:block;font-size:11.5px;color:var(--mut);margin-top:2px}
  .reopen__arrow{font-size:14px;color:var(--brand);flex-shrink:0}
  @media(max-width:560px){.reopen{margin:0 14px 14px;width:calc(100% - 28px)}}

  .btn-block{width:100%;justify-content:center}
  .hint{font-size:12px;color:var(--mut);margin-top:6px}
  .chip{font-size:10.5px;font-weight:700;color:#0369a1;background:#e0f2fe;
    border-radius:999px;padding:2px 8px;margin-left:6px;vertical-align:middle}

  /* 안내 배너 — 전체 흐름을 한눈에 */
  .lead{background:linear-gradient(135deg,#f6f9ff,#fdfdff);border:1px solid #dde5f5;
    border-radius:var(--radius);padding:14px 16px;margin-bottom:16px}
  .lead b{color:var(--navy)}
  .flow{display:flex;align-items:stretch;gap:0;flex-wrap:wrap}
  .flow__i{display:flex;align-items:center;gap:9px;flex:1;min-width:150px;padding:2px 4px}
  .flow__n{width:26px;height:26px;flex:0 0 26px;border-radius:9px;background:var(--navy);
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-size:12.5px;font-weight:800}
  .flow__t{font-size:12.5px;font-weight:700;color:#2b3446;line-height:1.35}
  .flow__t small{display:block;font-size:11px;font-weight:500;color:var(--mut);margin-top:1px}
  .flow__ar{display:flex;align-items:center;color:#c2cbd9;font-size:15px;padding:0 4px}
  @media (max-width:640px){ .flow__ar{display:none} .flow__i{flex:0 0 100%;min-width:0} }

  /* 명단 입력 안내 (이름·전화·직급 3칸을 눈에 보이게) */
  .rosterhead{display:grid;grid-template-columns:1.1fr 1.4fr 1fr;gap:8px;margin-bottom:6px}
  .rosterhead span{font-size:12px;font-weight:800;color:var(--navy);text-align:center;
    background:#eef2fa;border:1px solid #dbe3f0;border-radius:8px 8px 0 0;padding:7px 4px;
    position:relative}
  .rosterhead span small{display:block;font-size:10px;font-weight:600;color:var(--mut);margin-top:1px}
  @media (max-width:560px){ .rosterhead{grid-template-columns:1fr 1fr;gap:6px}
    .rosterhead span:nth-child(3){grid-column:1 / -1} }
  .next-hint{font-size:11.5px;color:var(--mut);text-align:center;margin-top:9px;
    padding-top:9px;border-top:1px dashed #e3e8f0}
  .next-hint b{color:var(--navy)}
  .next-hint.done{color:#15803d}
  /* 명단을 적고 배치 전 — 다음에 눌러야 할 것을 눈에 띄게 */
  .next-hint--ready{color:#15803d;font-weight:700;font-size:12.5px}
  .next-hint--ready b{color:#15803d}
  .btn--nudge{box-shadow:0 0 0 4px rgba(192,57,43,.22);
    animation:nudgePulse 1.5s ease-in-out infinite}
  .btn-navy.btn--nudge{box-shadow:0 0 0 4px rgba(58,85,114,.24),0 2px 8px rgba(58,85,114,.22)}
  .btn-success.btn--nudge{box-shadow:0 0 0 4px rgba(22,163,74,.24),0 2px 8px rgba(22,163,74,.22)}
  @keyframes nudgePulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
  @media(prefers-reduced-motion:reduce){.btn--nudge{animation:none}}

  /* ── 저장 완료 안내 ── */
  .savedone{display:flex;align-items:center;gap:12px;flex-wrap:wrap;
    background:#f6fdf8;border:1px solid #bfe6cb;border-radius:var(--radius);
    padding:14px 16px;margin-bottom:16px;box-shadow:var(--shadow)}
  .savedone__ic{font-size:20px;flex-shrink:0}
  .savedone__tx{flex:1;min-width:0}
  .savedone__tx b{display:block;font-size:14px;font-weight:800;color:#15803d}
  .savedone__tx small{display:block;font-size:12px;color:var(--mut);margin-top:3px;line-height:1.6}
  .savedone__btn{flex-shrink:0;background:#16a34a;color:#fff;border-radius:10px;
    padding:10px 17px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap}
  .savedone__btn:hover{filter:brightness(1.08);color:#fff}
  @media(max-width:560px){.savedone__btn{width:100%;text-align:center}}
  .next-hint.done b{color:#15803d}
  .paste-help__note{font-size:11.5px;color:var(--mut);line-height:1.7;margin-top:9px;
    background:#f8fafc;border:1px dashed #ccd6e4;border-radius:9px;padding:10px 12px}
  .toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}

  /* ══ 처음 방문 가이드 모달 ══ */
  .guide-mask{position:fixed;inset:0;background:rgba(20,26,40,.5);z-index:900;
    display:none;align-items:center;justify-content:center;padding:18px;
    backdrop-filter:blur(2px)}
  .guide-mask.show{display:flex}
  .guide-card{background:#fff;border-radius:18px;max-width:440px;width:100%;
    max-height:88vh;overflow-y:auto;padding:26px 24px 20px;position:relative;
    box-shadow:0 24px 60px rgba(16,24,40,.28);animation:guideIn .22s ease-out}
  @keyframes guideIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
  .guide-x{position:absolute;top:14px;right:14px;width:30px;height:30px;border:0;
    background:#f1f3f7;color:#8a94a6;border-radius:9px;font-size:14px;cursor:pointer}
  .guide-x:hover{background:#e6e9ef;color:#2b3446}
  .guide-hd{text-align:center;margin-bottom:18px}
  .guide-emoji{font-size:32px;display:block;margin-bottom:8px}
  .guide-hd h3{font-size:17px;font-weight:800;color:#12213a;margin-bottom:5px}
  .guide-hd p{font-size:12.5px;color:var(--mut);line-height:1.6}

  .guide-demo{background:#f8fafc;border:1px solid #e6ebf2;border-radius:12px;
    padding:15px 16px;margin-bottom:14px}
  .guide-demo__label{font-size:11px;font-weight:800;color:var(--navy);
    text-transform:uppercase;letter-spacing:.03em;margin-bottom:9px}
  .guide-line{display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap}
  .guide-tag{font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:999px}
  .guide-tag--name{background:#eef2fa;color:var(--navy)}
  .guide-tag--tel{background:#eafaf0;color:#0f7a4c}
  .guide-tag--dept{background:#fff4e6;color:#b25b09}
  .guide-example{font-family:ui-monospace,Consolas,monospace;font-size:13px;
    background:#fff;border:1px solid #dbe3f0;border-radius:8px;padding:9px 12px;
    color:#1f2430;margin-bottom:6px}
  .guide-example--dim{color:#9aa3b2;font-size:12px;padding:7px 12px}
  .guide-note{font-family:'Malgun Gothic',sans-serif;color:#c2482f;font-size:11px}
  .guide-arrow-down{text-align:center;font-size:11px;color:var(--mut);margin:6px 0}

  .guide-rule{background:#eff6ff;border:1px solid #cfe0fb;border-radius:12px;
    padding:14px 16px;margin-bottom:18px}
  .guide-rule b{font-size:12.5px;color:var(--navy);display:block;margin-bottom:10px}
  /* 세 번째 줄처럼 글이 길어 두 줄이 되면, 번호가 가운데로 내려가 첫 글자와 어긋납니다.
     flex-start 로 맞춰 번호를 항상 첫 줄에 붙입니다. */
  .guide-rule__row{display:flex;align-items:flex-start;gap:10px;font-size:12.5px;
    color:#2b3446;padding:4px 0;line-height:1.65}
  .guide-rule__no{min-width:24px;height:20px;flex:0 0 auto;border-radius:6px;padding:0 6px;
    background:var(--navy);color:#fff;font-size:10.5px;font-weight:800;
    display:inline-flex;align-items:center;justify-content:center;
    margin-top:1px}   /* 글자 첫 줄 높이에 살짝 맞춤 */
  /* '첫 줄 / 둘째 줄 / 나머지' 를 같은 폭으로 잡아 세로로 열을 맞춥니다 */
  .guide-rule__from{flex:0 0 52px;color:#5b6577}
  .guide-rule__to{flex:1;min-width:0}
  .guide-rule__to b{display:inline;font-size:12.5px;margin:0}
  .guide-rule__sub{font-size:11.5px;color:#6b7688;line-height:1.6}

  .guide-foot{display:flex;align-items:center;justify-content:space-between;
    gap:10px;flex-wrap:wrap}
  .guide-foot--single{justify-content:center}
  .guide-foot--single .btn{min-width:220px;justify-content:center}
  @media (max-width:480px){ .guide-foot{flex-direction:column-reverse;align-items:stretch}
    .guide-foot .btn{width:100%;justify-content:center} }

  /* ── 실시간 미리보기: 지금 적은 게 어떻게 인식되는지 즉시 확인 ── */
  .live-preview{margin-top:10px;padding:11px 13px;border-radius:10px;
    background:#f8fafc;border:1px dashed #d7dfeb;min-height:20px}
  .live-preview:empty{display:none}
  .live-preview__row{display:flex;align-items:center;gap:7px;font-size:12px;
    padding:3px 0;color:#2b3446}
  .live-preview__row .role{font-size:10px;font-weight:800;color:#fff;
    background:var(--navy);border-radius:5px;padding:1px 6px;flex:0 0 auto}
  .live-preview__row .role.cmd{background:var(--red)}
  .live-preview__row .role.dep{background:#5b7a9e}
  .live-preview__row .role.grp{background:#8a94a6}
  .live-preview__row .nm{font-weight:700}
  .live-preview__row .extra{color:var(--mut);font-size:11px}
  .live-preview__more{font-size:11px;color:var(--mut);padding-top:2px}
  .cnt{font-size:12px;font-weight:800;color:var(--navy);background:#eef2fa;border-radius:999px;padding:5px 12px}
  .cnt.zero{color:var(--mut);background:#f1f3f7}

  /* ══ 편성표 ══ */
  .org table{width:100%;border-collapse:collapse;font-size:13px}
  .org th,.org td{border:1px solid var(--line);padding:6px 8px;vertical-align:middle}
  .org thead th{background:var(--red);color:#fff;text-align:center;font-weight:700;font-size:12.5px}
  .role-cell{background:var(--tintR);font-weight:700;text-align:center;white-space:nowrap;font-size:12.5px}
  .grp-row td{background:var(--navy);color:#fff;font-weight:700}
  .grp-row .gname{display:inline-block;width:auto;min-width:130px;max-width:200px;color:#fff;
    font-weight:700;background:transparent;border:none;border-bottom:1px dashed rgba(255,255,255,.45);
    border-radius:0;padding:3px 4px;font-size:13px}
  .grp-row .gname:focus{background:rgba(255,255,255,.12);box-shadow:none;border-color:#fff}
  .grp-row .gcnt{opacity:.85;font-weight:500;font-size:12px;margin-left:6px}
  .org input[type=text]{border:1px solid transparent;background:transparent;padding:5px 7px;border-radius:7px;font-size:13px}
  .org input[type=text]:hover{background:#f7f9fc}
  .org input[type=text]:focus{border-color:#c9d3e2;background:#fff;box-shadow:0 0 0 3px rgba(58,85,114,.1)}
  td.ctr{text-align:center}
  .rowdel{background:#fff;border:1px solid #e6c3bd;color:var(--red);border-radius:7px;cursor:pointer;
    font-size:11px;padding:3px 8px;font-weight:700;opacity:.55;transition:.15s}
  tr:hover .rowdel{opacity:1}
  .addbtn{background:#f2f6fb;border:1px dashed #a9bcd0;color:var(--navy);border-radius:9px;
    cursor:pointer;font-size:12.5px;padding:7px 13px;font-weight:700;font-family:inherit}
  .addbtn:hover{background:#e8eff7}
  .empty-org{text-align:center;padding:34px 18px;color:var(--mut);font-size:13px;line-height:1.85;
    border:1px dashed #d5dce6;border-radius:12px;background:#fbfcfe}
  .empty-org b{color:#2b3446}

  /* 모바일에서 표를 카드처럼 */
  @media (max-width:640px){
    .org table,.org tbody,.org tr,.org td{display:block;width:100%}
    .org thead{display:none}
    .org tbody tr{border:1px solid var(--line);border-radius:10px;margin-bottom:8px;overflow:hidden}
    .org tbody tr.grp-row{border-radius:10px 10px 0 0;margin-bottom:0}
    .org td{border:none;border-bottom:1px solid #edf0f4;padding:7px 10px;text-align:left!important}
    .org td:last-child{border-bottom:none}
    .org td[data-l]::before{content:attr(data-l);display:block;font-size:10.5px;color:var(--mut);
      font-weight:700;margin-bottom:2px}
    .org td.role-cell{background:var(--tintR)}
    .org input[type=text]{text-align:left!important;background:#fff;border-color:#e6eaf0}
  }

  /* 하단 저장 바 */
  .savebar{position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.95);
    backdrop-filter:blur(8px);border-top:1px solid var(--brd);padding:11px 16px;
    display:flex;gap:10px;justify-content:center;align-items:center;
    box-shadow:0 -2px 14px rgba(16,24,40,.07);z-index:35}
  .savebar .meta{font-size:12px;color:var(--mut);margin-right:6px}
  .savebar .btn{white-space:nowrap}
  .savebar__next[hidden]{display:none}
  @media (max-width:560px){
    .savebar{gap:6px;padding:9px 8px}
    .savebar .meta{display:none}
    .savebar .btn{flex:1;justify-content:center;padding:10px 7px;font-size:12px}
  }

  .toast{position:fixed;bottom:78px;left:50%;transform:translateX(-50%) translateY(8px);
    background:#1f2430;color:#fff;padding:11px 18px;border-radius:11px;font-size:13px;
    opacity:0;transition:.22s;z-index:60;pointer-events:none;max-width:90vw;text-align:center}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

  /* ══ 조직도 (Type-Ⅲ) ══ */
  .org-note{font-size:12.5px;color:var(--mut);background:#f7f9fc;border:1px solid #e6ebf2;
    border-radius:9px;padding:10px 13px;margin-bottom:16px;line-height:1.6}
  .chart{max-width:640px;margin:0 auto}
  .chart__t{font-size:13px;font-weight:800;color:#2b3446;margin:0 0 12px;
    padding-bottom:7px;border-bottom:1.5px solid #d9e0ea}
  .chart__t small{font-weight:600;color:var(--mut);font-size:11.5px;margin-left:6px}
  .cbox{border:1px solid #9aa3b2;background:#fff}
  .cbox__h{background:#f1f4f8;border-bottom:1px solid #9aa3b2;text-align:center;
    font-size:12.5px;font-weight:700;padding:5px 8px;color:#1f2836}
  .ctab{width:100%;border-collapse:collapse;font-size:12px}
  .ctab th,.ctab td{border:1px solid #9aa3b2;padding:4px 7px;text-align:center;
    vertical-align:middle;line-height:1.5}
  .ctab th{background:#fafbfd;font-weight:600;color:#46536a;font-size:11.5px}
  .ctab td.nm{font-weight:700;color:#141c2a}
  .ctab td.ppl{text-align:left}
  .ctab td.empty{color:#b4bcc9}
  .ctab td.task{text-align:left;font-size:11.5px;line-height:1.6;padding:6px 8px}
  /* 상단(대장·부대장) 과 우측(초기대응체계) 2단 배치 */
  .crow{display:grid;grid-template-columns:1fr 34px 1fr;align-items:start;gap:0}
  .ccol{display:flex;flex-direction:column;gap:16px}
  .clink{position:relative}
  .clink::before{content:"";position:absolute;left:0;right:0;top:50%;
    border-top:1px solid #9aa3b2}
  .cstem{height:16px;width:1px;background:#9aa3b2;margin:0 auto}
  .cwide{margin-top:16px}
  @media(max-width:620px){
    .crow{grid-template-columns:1fr}
    .clink{display:none}
    .ccol{gap:12px}
  }

  /* ══ 인쇄 ══ */
  .print-only{display:none}
  @media print{
    @page{ margin:14mm 12mm; }
    .no-print{display:none!important}
    body{background:#fff;padding:0}
    .layout{display:block;max-width:100%;padding:0}
    .side{display:none}
    .card{border:none;box-shadow:none;border-radius:0;margin:0;overflow:visible}
    .card__bd{padding:0}
    .print-only{display:block!important}
    .org table{font-size:12px}
    .org th,.org td{padding:4px 6px}
    .org input[type=text]{font-size:12px;border:none;background:none}
    .print-sign{page-break-inside:avoid}
    /* 조직도는 새 쪽에서 시작 — 편성표와 섞이지 않게 */
    #p4{page-break-before:always}
    .org-note{display:none}
    .chart{max-width:100%}
    .cbox,.crow,.cwide{page-break-inside:avoid}
    .ctab{font-size:11px}
    .org table{page-break-inside:auto}
    .org tr{page-break-inside:avoid}
  }
  .print-head{text-align:center;margin-bottom:8px}
  .print-head .ttl{font-size:24px;font-weight:800;letter-spacing:8px}
  .print-head .ttl-sub{font-size:11px;color:#444;letter-spacing:2px}
  .print-head hr{border:none;border-top:2px solid #2b2b2b;margin:6px 0}
  .print-meta{display:flex;justify-content:space-between;font-weight:700;font-size:12px;margin:4px 2px 8px}
  .print-sign{text-align:right;font-size:12px;margin-top:18px;line-height:2}
</style>
</head>
<body>

<div class="appbar no-print">
  <div class="appbar__in">
    <h1>소방계획서.com JW</h1>
    <span class="status" id="statusChip">🆕 새 편성표</span>
    <span class="sp"></span>
    <nav class="appnav" aria-label="바로가기">
      <a class="appnav__ic" href="/building_manager.php" title="건물 관리">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 8h1M11 8h1M7 12h1M11 12h1M7 16h1M11 16h1M17 14h1M17 18h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
      <a class="appnav__ic" href="/notifications.php" title="알림">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
      <a class="appnav__ic" href="/subscribe_page.php" title="결제·구독">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </a>
    </nav>
    <a class="back" href="<?=h($JW_BACK[0])?>"><?=h($JW_BACK[1])?></a>
  </div>
</div>

<div class="layout">

  <!-- ════ 우측: 진행 현황 · 저장된 편성표 ════ -->
  <aside class="side no-print">

    <!-- 진행 현황 (building_manager 와 같은 방식) -->
    <div class="prog">
      <div class="prog__t">진행 현황</div>
      <div class="pstep" id="ps1">
        <span class="no">1</span>
        <span class="pstep__label">대상물 확인</span>
        <span class="ptag ptag--wait" id="pt1">대기</span>
      </div>
      <div class="pstep" id="ps2">
        <span class="no">2</span>
        <span class="pstep__label">명단 적기</span>
        <span class="ptag ptag--wait" id="pt2">대기</span>
      </div>
      <div class="pstep" id="ps3">
        <span class="no">3</span>
        <span class="pstep__label">편성표 저장</span>
        <span class="ptag ptag--wait" id="pt3">대기</span>
      </div>
      <div class="prog__hint" id="progHint">건물 이름부터 적어주세요</div>
    </div>

    <div class="card">
      <div class="card__hd">
        <h2>저장된 편성표<span class="cnt" id="planCount"></span></h2>
        <button class="btn btn-ghost btn-sm" onclick="newPlan()">＋ 새로</button>
      </div>
      <div id="planList" class="planlist"><div class="empty">불러오는 중…</div></div>
    </div>
    <div class="card">
      <div class="card__bd" style="padding:13px 15px">
        <div style="font-size:12px;color:var(--mut);line-height:1.75">
          💡 여기서 만든 명단은 <b style="color:var(--navy)">교육·훈련 기록부</b>에서
          그대로 불러 씁니다. 대상물마다 하나씩 만들어 두세요.
        </div>
      </div>
    </div>
  </aside>

  <!-- ════ 우측: 작업 영역 ════ -->
  <main>

    <div class="lead no-print">
      <div class="flow">
        <div class="flow__i">
          <span class="flow__n">1</span>
          <span class="flow__t">대상물 확인<small>대개 자동으로 채워집니다</small></span>
        </div>
        <div class="flow__ar">→</div>
        <div class="flow__i">
          <span class="flow__n">2</span>
          <span class="flow__t">명단 적고 <b>자동 배치</b><small>이름만 적어도 됩니다</small></span>
        </div>
        <div class="flow__ar">→</div>
        <div class="flow__i">
          <span class="flow__n">3</span>
          <span class="flow__t">편성표 확인 · 저장<small>표에서 바로 고칠 수 있어요</small></span>
        </div>
      </div>
    </div>


    <!-- 저장이 끝나면 나타나는 안내 (저장 후 다음 할 일) -->
    <div class="savedone no-print" id="savedDone" style="display:none">
      <div class="savedone__ic">✅</div>
      <div class="savedone__tx">
        <b>편성표를 저장했습니다</b>
        <small>건물 관리 페이지에서 남은 항목을 이어서 진행하실 수 있습니다.</small>
      </div>
      <a class="savedone__btn" href="/building_manager.php">건물 관리로 →</a>
    </div>

    <!-- ① 대상물 -->
    <section class="card no-print" id="p1">
      <div class="card__hd">
        <span class="stepno wait" id="sn1">1</span>
        <h2>대상물 정보<span class="sub">건물 기본정보가 있으면 자동으로 채워집니다</span></h2>
      </div>
      <div class="card__bd">
        <div class="grid2">
          <div>
            <label class="fld" for="siteName">대상물명 <span class="chip" id="siteAuto" style="display:none">기본정보</span></label>
            <input type="text" id="siteName" placeholder="예: 투릭스 빌딩" value="<?=h($JW_SITE)?>">
          </div>
          <div>
            <label class="fld" for="workType">근무형태</label>
            <input type="text" id="workType" list="workTypes" value="<?=h($JW_WORK !== '' ? $JW_WORK : '단일근무(주간)')?>">
            <datalist id="workTypes">
              <option value="단일근무(주간)"><option value="교대근무(주·야간)">
              <option value="2교대"><option value="3교대"><option value="상주(24시간)">
            </datalist>
          </div>
        </div>
      </div>
    </section>

    <!-- ② 명단 붙여넣기 -->
    <section class="card no-print" id="p2">
      <div class="card__hd">
        <span class="stepno wait" id="sn2">2</span>
        <h2>명단 적기<span class="sub">한 줄에 한 명 — <b>이름 · 전화번호 · 직급</b> 순서로</span></h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openGuide()" title="예시 다시 보기">❓ 어떻게 적나요?</button>
        <button class="btn btn-ghost btn-sm" type="button" id="foldBtn" onclick="toggleFold()">접기</button>
      </div>

      <!-- 접혔을 때만 보이는 안내 줄 — 눌러서 다시 펼 수 있습니다 -->
      <button type="button" class="reopen" id="p2reopen" style="display:none" onclick="toggleFold(false)">
        <span class="reopen__ic">✏️</span>
        <span class="reopen__tx">
          <b>명단을 고치려면 여기를 누르세요</b>
          <small id="reopenCount">적어둔 명단이 접혀 있습니다</small>
        </span>
        <span class="reopen__arrow">▾</span>
      </button>
      <div class="card__bd" id="p2body">
        <!-- 세 칸이 무엇인지 눈에 보이게: 이 순서로 적으면 됩니다 -->
        <div class="rosterhead" aria-hidden="true">
          <span>이름</span>
          <span>전화번호 <small>(없으면 생략)</small></span>
          <span>직급 <small>(없으면 생략)</small></span>
        </div>

        <label class="fld" for="bulkInput" style="position:absolute;left:-9999px">명단</label>
        <textarea id="bulkInput" rows="7" placeholder="홍길동   010-1234-5678   지점장&#10;김철수   010-2222-3333   차장&#10;이영희&#10;&#10;↑ 한 줄에 한 명씩 · 띄어쓰기로 구분"></textarea>

        <div class="live-preview" id="livePreview" aria-live="polite"></div>

        <div class="paste-help__note">
          <b>적은 순서가 곧 직책이 됩니다.</b>
          첫 줄 <b>대장</b> · 둘째 줄 <b>부대장</b> · 나머지는 활동조(비상연락·초기소화·피난유도…)에 차례로 들어갑니다.
          배치 후에 표에서 얼마든지 바꿀 수 있습니다.
        </div>

        <div class="toolbar">
          <span class="cnt zero" id="bulkCount">0명</span>
          <?php if ($JW_MGR !== ''): ?>
            <button class="btn btn-ghost btn-sm" type="button" onclick="fillMgr()"
              title="기본정보에 등록된 소방안전관리자를 첫 줄(대장)에 넣습니다">
              👤 <?=h($JW_MGR)?> 넣기
            </button>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm" type="button" onclick="fillSample()">예시 채우기</button>
          <span class="sp" style="margin-left:auto"></span>
          <button class="btn btn-ghost btn-sm" type="button" onclick="clearAll()">표 비우기</button>
          <button class="btn btn-primary" id="assignBtn" type="button" onclick="autoAssign()">⚡ 자동 배치</button>
        </div>
        <div class="next-hint" id="nextHint">명단을 적고 <b>⚡ 자동 배치</b>를 누르면 아래 편성표가 채워집니다</div>
      </div>
    </section>

    <!-- ③ 편성표 -->
    <section class="card" id="p3">
      <div class="card__hd no-print">
        <span class="stepno wait" id="sn3">3</span>
        <h2>편성표<span class="sub">칸을 눌러 바로 고칠 수 있습니다</span></h2>
        <span class="cnt" id="totalCnt">0명</span>
      </div>

      <div class="card__bd">
        <div class="print-only print-head">
          <div class="ttl">자 위 소 방 대 편 성 표</div>
          <div class="ttl-sub">自衛消防隊 編成表</div>
          <hr>
          <div class="print-meta">
            <span id="pmSite">대상물명 : </span>
            <span id="pmWork">근무형태 : </span>
          </div>
        </div>

        <div class="org" id="orgArea"><!-- JS 렌더 --></div>

        <div class="toolbar no-print" style="margin-top:14px">
          <button class="addbtn" onclick="addGroup()">＋ 활동조 추가</button>
        </div>

        <div class="print-only print-sign">
          작성일 : <span id="pmDate"></span><br>
          소방안전관리자 : <span id="pmMgr"></span> &nbsp;(서명 또는 인)
        </div>
      </div>
    </section>

    <!-- ── 소방계획서 2.3.3 Type-Ⅲ 조직도 (상시근무 50명 미만) ── -->
    <section class="card" id="p4">
      <div class="card__hd">
        <h2>조직도<span class="sub">소방계획서 2.3.3 Type-Ⅲ · 상시 근무인원 50명 미만</span></h2>
        <span class="cnt" id="orgCnt">0명</span>
      </div>
      <div class="card__bd">
        <div class="org-note no-print">
          위 편성표를 고치면 이 조직도도 함께 바뀝니다. 소방계획서에 그대로 붙여 쓸 수 있습니다.
        </div>
        <div id="chartArea"></div>
      </div>
    </section>

  </main>
</div>

<div class="savebar no-print">
  <span class="meta" id="editingHint" aria-live="polite"></span>
  <button class="btn btn-navy" id="savePlanBtn" type="button" onclick="saveplan()">💾 저장</button>
  <a class="btn btn-success savebar__next" id="buildingManagerBtn" href="/building_manager.php" hidden>🏢 건물 관리로</a>
  <button class="btn btn-primary" type="button" onclick="window.print()">🖨️ 인쇄 / PDF</button>
</div>
<div class="toast no-print" id="toast"></div>

<script>
const CSRF = <?=json_encode($CSRF)?>;
let currentPlanId = null;   // 현재 불러와 수정 중인 편성표 id (null이면 신규)
let finalAction = '';       // autoAssign 뒤 save, 저장 성공 뒤 building
let rosterChanged = false;  // 명단을 고친 뒤 다시 자동 배치해야 하는 상태

/* ---------- 임무 기본 문구 (활동조 자동 배정용) ---------- */
const GROUP_TEMPLATES = [
  { name:"비상연락반", tasks:[
      "소방서(119) 화재신고, 관계기관 통보",
      "관계인·근무자 비상연락, 화재경보 방송 전파"] },
  { name:"초기소화반", tasks:[
      "소화기·옥내소화전을 이용한 초기 진화",
      "초기 진화 보조, 연소 확대 방지",
      "발화설비 전원 차단, 가스밸브 차단"] },
  { name:"피난유도반", tasks:[
      "피난로 확보 및 대피 유도",
      "피난약자 보조, 대피 인원 점검",
      "대피 완료 확인, 미대피자 파악·보고"] },
  { name:"응급구조반", tasks:[
      "부상자 응급처치 및 들것 이송",
      "구급차 유도, 인근 병원 연락"] },
  { name:"방호안전반", tasks:[
      "화재구역 출입통제, 중요물품·서류 반출",
      "현금 등 중요자산 반출 보조, 경계근무"] },
];
const CMD_TASK = "자위소방대 총괄 지휘, 화재상황 판단 및 대응 명령, 소방활동 총지휘";
const DEP_TASK = "대장 보좌, 대장 부재 시 임무 대행, 각 활동조 통제";

/* ---------- 현재 편성 상태 ---------- */
let model = {
  cmd:    { name:"", tel:"", dept:"", task:CMD_TASK },
  deputy: { name:"", tel:"", dept:"", task:DEP_TASK },
  groups: []   // [{name, members:[{name,tel,dept,task}]}]
};

/* 한 줄에서 이름/전화 추출 */
function parseLine(line){
  line = line.trim();
  if(!line) return null;
  let tel = "";
  const telMatch = line.match(/01[016789][-\s]?\d{3,4}[-\s]?\d{4}/);
  if(telMatch){
    tel = telMatch[0].replace(/\s/g,"").replace(/(\d{3})[-]?(\d{3,4})[-]?(\d{4})/,"$1-$2-$3");
    line = line.replace(telMatch[0],"").trim();
  }
  const parts = line.split(/[\s\/,·]+/).filter(Boolean);
  const name = parts[0] || "";
  /* 이름 뒤에 남은 말(부서·직급)을 소속으로 봅니다. 조직도의 '소속' 칸에 들어갑니다. */
  const dept = parts.slice(1).join(" ");
  return { name, tel, dept };
}

/* ── 명단 입력 도우미 ── */
const MGR_NAME = <?=json_encode($JW_MGR)?>;
const MGR_TEL  = <?=json_encode($JW_MGRTEL)?>;

function hideSavedDone(){
  const done = document.getElementById('savedDone');
  if (done) done.style.display = 'none';
}

function markRosterChanged(){
  rosterChanged = true;
  finalAction = '';
  hideSavedDone();
}

function fillMgr(){
  if (!MGR_NAME) return;
  const ta = document.getElementById('bulkInput');
  const line = (MGR_NAME + ' ' + (MGR_TEL || '')).trim();
  const cur = ta.value.split(/\n/).map(s=>s.trim()).filter(Boolean);
  if (cur.some(l => l.indexOf(MGR_NAME) === 0)) { toast('이미 명단에 있습니다.'); return; }
  ta.value = [line].concat(cur).join('\n');
  markRosterChanged();
  countBulk(); ta.focus();
  toast(MGR_NAME + ' 님을 대장 자리(첫 줄)에 넣었습니다.');
}

function fillSample(){
  const ta = document.getElementById('bulkInput');
  if (ta.value.trim() !== '' && !confirm('입력한 내용을 예시로 바꿀까요?')) return;
  ta.value = [
    '홍길동 010-1234-5678 지점장',
    '김철수 010-2222-3333 차장',
    '이영희 010-3333-4444 과장',
    '박민수 010-5555-6666 사원',
    '최수진 010-8865-6666 사원'
  ].join('\n');
  markRosterChanged();
  countBulk(); ta.focus();
  toast('예시를 넣었습니다. 실제 이름으로 바꾼 뒤 자동 배치를 누르세요.');
}

function countBulk(){
  const ta = document.getElementById('bulkInput');
  if (!ta) return;
  const lines = ta.value.split(/\n/).map(s=>s.trim()).filter(Boolean);
  const n = lines.length;
  const el = document.getElementById('bulkCount');
  if (el){ el.textContent = n + '명'; el.classList.toggle('zero', n===0); }
  const btn = document.getElementById('assignBtn');
  if (btn) {
    btn.textContent = n > 0 ? ('⚡ ' + n + '명 자동 배치') : '⚡ 자동 배치';
    /* 명단은 적었는데 아직 배치를 안 했으면 버튼을 눈에 띄게 해서 다음 행동을 유도합니다 */
    const notAssigned = rosterChanged || !(model && (model.cmd && model.cmd.name || (model.groups||[]).length));
    btn.classList.toggle('btn--nudge', n > 0 && notAssigned);
  }
  /* 안내 문구도 상황에 맞게 바꿉니다 */
  const hint = document.getElementById('nextHint');
  if (hint) {
    const notAssigned2 = rosterChanged || !(model && (model.cmd && model.cmd.name || (model.groups||[]).length));
    if (n > 0 && notAssigned2) {
      hint.innerHTML = '✅ ' + n + '명을 적으셨습니다. 이제 <b>⚡ ' + n + '명 자동 배치</b>를 눌러주세요';
      hint.classList.add('next-hint--ready');
    } else {
      hint.innerHTML = '명단을 적고 <b>⚡ 자동 배치</b>를 누르면 아래 편성표가 채워집니다';
      hint.classList.remove('next-hint--ready');
    }
  }
  renderLivePreview(lines);
  markSteps();
}

/* ── 실시간 미리보기: 지금 이 줄이 대장/부대장/활동조 몇 번째로 들어갈지 즉시 보여줍니다 ── */
function renderLivePreview(lines){
  const box = document.getElementById('livePreview');
  if (!box) return;
  if (!lines.length){ box.innerHTML = ''; return; }

  const MAX_SHOW = 4;
  const rows = lines.slice(0, MAX_SHOW).map((line, i) => {
    const p = parseLine(line);
    if (!p || !p.name) return '';
    let roleCls = 'grp', roleTxt = (i-1) + '번째 활동조원';
    if (i === 0) { roleCls = 'cmd'; roleTxt = '대장'; }
    else if (i === 1) { roleCls = 'dep'; roleTxt = '부대장'; }
    const extra = [p.tel, p.dept].filter(Boolean).join(' · ');
    return '<div class="live-preview__row">' +
      '<span class="role ' + roleCls + '">' + esc(roleTxt) + '</span>' +
      '<span class="nm">' + esc(p.name) + '</span>' +
      (extra ? '<span class="extra">' + esc(extra) + '</span>' : '') +
      '</div>';
  }).join('');

  const moreCount = lines.length - MAX_SHOW;
  const more = moreCount > 0 ? '<div class="live-preview__more">+ ' + moreCount + '명 더 있음 — 자동 배치를 누르면 활동조에 순서대로 들어갑니다</div>' : '';
  box.innerHTML = rows + more;
}

/* ── 처음 방문 가이드 모달 — 페이지에 올 때마다 뜹니다(명단이 비어 있으면) ── */
function openGuide(){
  document.getElementById('guideMask').classList.add('show');
}
function closeGuide(focusInput){
  document.getElementById('guideMask').classList.remove('show');
  if (focusInput) {
    const ta = document.getElementById('bulkInput');
    if (ta) { document.getElementById('p2').scrollIntoView({behavior:'smooth', block:'start'}); ta.focus(); }
  }
}
document.addEventListener('DOMContentLoaded', function(){
  const hasData = document.getElementById('bulkInput') &&
                  document.getElementById('bulkInput').value.trim() !== '';
  if (!hasData) openGuide();
});

/* ② 카드 접기/펴기
   접힌 상태에서는 '펴기' 버튼이 작아 눈에 안 띄어, 명단을 고치려는 사용자가 헤맸습니다.
   그래서 접히면 카드 자리에 눌러서 열 수 있는 안내 줄을 대신 보여줍니다. */
function toggleFold(force){
  const body = document.getElementById('p2body');
  const btn  = document.getElementById('foldBtn');
  const bar  = document.getElementById('p2reopen');
  const hide = (typeof force === 'boolean') ? force : (body.style.display !== 'none');
  body.style.display = hide ? 'none' : '';
  btn.textContent = hide ? '✏️ 명단 고치기' : '접기';
  btn.classList.toggle('btn-accent', hide);
  if (bar) {
    bar.style.display = hide ? '' : 'none';
    if (hide) {
      /* 몇 명이 접혀 있는지 알려주면, 열어볼지 판단하기 쉽습니다 */
      const ta = (document.getElementById('bulkInput')||{}).value || '';
      const n  = ta.split('\n').map(s=>s.trim()).filter(Boolean).length;
      const cnt = document.getElementById('reopenCount');
      if (cnt) cnt.textContent = n ? ('적어둔 명단 ' + n + '명이 접혀 있습니다') : '명단이 접혀 있습니다';
    }
  }
}

/* 단계 번호 색을 현재 상태에 맞춥니다 */
function hasAssignedPeople(){
  return !!(model && (model.cmd && model.cmd.name || (model.groups||[]).length));
}

/* 자동 배치 다음에는 저장, 저장 다음에는 건물 관리로 버튼 하나만 강조합니다. */
function updateFinalActions(){
  const saveBtn = document.getElementById('savePlanBtn');
  const buildingBtn = document.getElementById('buildingManagerBtn');
  const meta = document.getElementById('editingHint');
  const needsSave = finalAction === 'save' && hasAssignedPeople();
  const goBuilding = finalAction === 'building' && !!currentPlanId && !rosterChanged;

  if (saveBtn) {
    saveBtn.classList.toggle('btn--nudge', needsSave);
    saveBtn.textContent = needsSave ? '💾 이제 저장' : (currentPlanId ? '💾 다시 저장' : '💾 저장');
  }
  if (buildingBtn) {
    buildingBtn.hidden = !goBuilding;
    buildingBtn.classList.toggle('btn--nudge', goBuilding);
  }
  if (meta && needsSave) meta.textContent = '편성표를 확인한 뒤 저장해 주세요';
  if (meta && goBuilding) meta.textContent = '저장 완료 · 건물 관리에서 계속하세요';
}

function markPlanChanged(){
  if (!hasAssignedPeople()) return;
  finalAction = 'save';
  hideSavedDone();
  markSteps();
}

function markSteps(){
  const site = (document.getElementById('siteName')||{}).value || '';
  const ta   = (document.getElementById('bulkInput')||{}).value || '';
  const hasPeople = hasAssignedPeople() && !rosterChanged;
  const set = (id, cls) => { const el=document.getElementById(id); if(el) el.className='stepno '+cls; };
  set('sn1', site.trim() ? 'done' : '');
  if (hasPeople)      { set('sn2','done'); set('sn3',''); }
  else if (ta.trim()) { set('sn2','');     set('sn3','wait'); }
  else                { set('sn2', site.trim() ? '' : 'wait'); set('sn3','wait'); }

  /* 우측 진행 현황 패널도 같이 갱신합니다 */
  updateProgress(site.trim() !== '', ta.trim() !== '', hasPeople);
  updateFinalActions();
}

/* 우측 진행 현황 — 지금 어디까지 왔고 다음에 뭘 해야 하는지 보여줍니다 */
function updateProgress(hasSite, hasText, hasPeople){
  const saved = !!currentPlanId && finalAction !== 'save' && !rosterChanged;
  /* [단계, 끝났나, 지금 할 차례인가, 배지 글자] */
  const steps = [
    ['1', hasSite,   !hasSite,                       hasSite   ? '완료' : '지금'],
    ['2', hasPeople, hasSite && !hasPeople,          hasPeople ? '완료' : (hasText ? '배치 전' : (hasSite ? '지금' : '대기'))],
    ['3', saved,     hasPeople && !saved,            saved     ? '저장됨' : (hasPeople ? '지금' : '대기')]
  ];
  steps.forEach(([n, done, now, label]) => {
    const row = document.getElementById('ps'+n);
    const tag = document.getElementById('pt'+n);
    if (!row || !tag) return;
    row.className = 'pstep' + (done ? ' pstep--done' : (now ? ' pstep--now' : ''));
    tag.className = 'ptag ' + (done ? 'ptag--done' : (now ? 'ptag--now' : 'ptag--wait'));
    tag.textContent = label;
  });

  const hint = document.getElementById('progHint');
  if (hint) {
    hint.textContent =
      !hasSite   ? '건물 이름부터 적어주세요' :
      !hasText   ? '아래에 명단을 붙여넣어 주세요' :
      !hasPeople ? '자동 배치를 눌러주세요' :
      !saved     ? '확인 후 저장하면 끝납니다' :
      finalAction === 'building' ? '저장되었습니다. 건물 관리로 이동하세요' :
                   '저장되었습니다. 언제든 고칠 수 있습니다';
  }
}

function autoAssign(){
  const lines = document.getElementById('bulkInput').value.split(/\n/).map(s=>s.trim()).filter(Boolean);
  if(lines.length === 0){ toast("붙여넣은 이름이 없습니다."); return; }
  const people = lines.map(parseLine).filter(p=>p && p.name);
  if(people.length === 0){ toast("이름을 인식하지 못했습니다."); return; }

  model.cmd    = { ...people[0], task:CMD_TASK };
  model.deputy = people[1] ? { ...people[1], task:DEP_TASK } : { name:"", tel:"", dept:"", task:DEP_TASK };

  const rest = people.slice(2);
  model.groups = GROUP_TEMPLATES.map(g=>({ name:g.name, members:[] }));
  let gi=0, si=0;
  rest.forEach(p=>{
    let guard=0;
    while(si >= GROUP_TEMPLATES[gi].tasks.length){ gi=(gi+1)%GROUP_TEMPLATES.length; si=0; if(++guard>20)break; }
    const task = GROUP_TEMPLATES[gi].tasks[si] || "";
    model.groups[gi].members.push({ name:p.name, tel:p.tel, dept:p.dept||"", task });
    si++;
  });
  model.groups = model.groups.filter(g=>g.members.length>0);
  rosterChanged = false;
  finalAction = 'save';
  hideSavedDone();
  render();
  countBulk();
  toggleFold(true);                                   // 배치 끝나면 ②는 접어 시야를 넓힙니다
  /* 다음에 뭘 하면 되는지 안내를 바꿔줍니다 */
  var nh = document.getElementById('nextHint');
  if (nh){
    nh.classList.add('done');
    nh.innerHTML = '✓ 배치했습니다. 아래 <b>③편성표</b>에서 확인·수정하고 <b>💾 저장</b>을 누르세요';
  }
  toast(people.length + "명 자동 배치 완료 — 표에서 수정하세요.");
  document.getElementById('p3').scrollIntoView({behavior:'smooth', block:'start'});
}

function clearAll(){
  model = { cmd:{name:"",tel:"",dept:"",task:CMD_TASK}, deputy:{name:"",tel:"",dept:"",task:DEP_TASK}, groups:[] };
  rosterChanged = !!((document.getElementById('bulkInput')||{}).value || '').trim();
  finalAction = currentPlanId ? 'save' : '';
  hideSavedDone();
  render();
  countBulk();
}

const CIRC = ["①","②","③","④","⑤","⑥","⑦","⑧","⑨"];
function esc(s){ return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/"/g,"&quot;"); }

function render(){
  const noOne = !(model.cmd && model.cmd.name) && !(model.deputy && model.deputy.name)
                && (!model.groups || model.groups.length === 0);
  if (noOne) {
    document.getElementById('orgArea').innerHTML =
      '<div class="empty-org">아직 편성된 대원이 없습니다.<br>' +
      '위 <b>②번</b>에 이름을 한 줄에 한 명씩 붙여넣고 <b>⚡ 자동 배치</b>를 누르면<br>' +
      '대장·부대장·활동조가 한 번에 만들어집니다.</div>';
    syncPrintHead(); markSteps(); renderChart();
    return;
  }

  let html = '<table>';
  html += '<thead><tr><th style="width:15%">직책</th><th style="width:14%">성명</th>'
        + '<th style="width:15%">소속</th>'
        + '<th style="width:18%">연락처</th><th>임무</th></tr></thead><tbody>';
  html += cmdRow("대 장","cmd");
  html += cmdRow("부 대 장","deputy");

  model.groups.forEach((g,gi)=>{
    html += `<tr class="grp-row"><td colspan="5">
      ${CIRC[gi]||'•'} <input type="text" class="gname" value="${esc(g.name)}" oninput="upd(${gi},-1,'gname',this.value)" aria-label="활동조 이름">
      <span class="gcnt">${g.members.length}명</span>
      <button class="rowdel no-print" style="float:right;background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.4);color:#fff;opacity:1"
        onclick="delGroup(${gi})">조 삭제</button>
    </td></tr>`;
    g.members.forEach((m,mi)=>{
      html += `<tr>
        <td class="ctr" data-l="직책">${esc(g.name)}</td>
        <td class="ctr" data-l="성명"><input type="text" value="${esc(m.name)}" oninput="upd(${gi},${mi},'name',this.value)" style="text-align:center;font-weight:700"></td>
        <td class="ctr" data-l="소속"><input type="text" value="${esc(m.dept||'')}" oninput="upd(${gi},${mi},'dept',this.value)" style="text-align:center" placeholder="부서/직급"></td>
        <td class="ctr" data-l="연락처"><input type="text" value="${esc(m.tel)}" oninput="upd(${gi},${mi},'tel',this.value)" style="text-align:center"></td>
        <td data-l="임무" style="position:relative">
          <input type="text" value="${esc(m.task)}" oninput="upd(${gi},${mi},'task',this.value)" style="width:calc(100% - 46px)">
          <button class="rowdel no-print" style="position:absolute;right:6px;top:50%;transform:translateY(-50%)"
            onclick="delMember(${gi},${mi})" title="이 사람 삭제">삭제</button>
        </td>
      </tr>`;
    });
    html += `<tr class="no-print"><td colspan="5" style="border-top:none">
      <button class="addbtn" onclick="addMember(${gi})">＋ ${esc(g.name)} 인원 추가</button></td></tr>`;
  });
  html += '</tbody></table>';

  document.getElementById('orgArea').innerHTML = html;
  syncPrintHead();
  markSteps();
  renderChart();          // 편성표가 바뀌면 조직도도 다시 그립니다
}

function cmdRow(label,key){
  const m = model[key];
  return `<tr>
    <td class="role-cell" data-l="직책">${label}${key==='cmd'?'<br><small style="font-weight:400;color:#555">(소방안전관리자)</small>':''}</td>
    <td class="ctr" data-l="성명"><input type="text" value="${esc(m.name)}" oninput="updCmd('${key}','name',this.value)" style="text-align:center;font-weight:700"></td>
    <td class="ctr" data-l="소속"><input type="text" value="${esc(m.dept||'')}" oninput="updCmd('${key}','dept',this.value)" style="text-align:center" placeholder="부서/직급"></td>
    <td class="ctr" data-l="연락처"><input type="text" value="${esc(m.tel)}" oninput="updCmd('${key}','tel',this.value)" style="text-align:center"></td>
    <td data-l="임무"><input type="text" value="${esc(m.task)}" oninput="updCmd('${key}','task',this.value)"></td>
  </tr>`;
}

/* ── 소방계획서 Type-Ⅲ 조직도 ─────────────────────────────
   상시근무 50명 미만 서식입니다.
   현장대응팀 = 편성한 활동조 전체
   초기대응체계 = 그중 비상연락·초기소화·피난유도 (초기 대응 담당)  */
const EARLY_KEYS = ["비상연락", "초기소화", "피난유도"];
function isEarly(name){
  return EARLY_KEYS.some(k => (name || "").indexOf(k) === 0);
}
/* 조직도 칸에 넣을 이름 목록 — 요청대로 인원수 대신 실제 성명을 넣습니다 */
function nameList(members){
  const ns = (members || []).map(m => (m.name || "").trim()).filter(Boolean);
  return ns;
}
function peopleCell(members){
  const ns = nameList(members);
  if (!ns.length) return '<td class="ppl empty">-</td>';
  return '<td class="ppl">' + esc(ns.join(", ")) + ' <span style="color:#7a8699">(' + ns.length + '명)</span></td>';
}

function renderChart(){
  const area = document.getElementById('chartArea');
  if (!area) return;

  const g = model.groups || [];
  const total = (model.cmd.name ? 1 : 0) + (model.deputy.name ? 1 : 0)
              + g.reduce((a, x) => a + x.members.length, 0);
  const cntEl = document.getElementById('orgCnt');
  if (cntEl) cntEl.textContent = total + '명';

  if (total === 0){
    area.innerHTML = '<div class="empty-org">편성표를 채우면 조직도가 자동으로 그려집니다.</div>';
    return;
  }

  const early = g.filter(x => isEarly(x.name));
  const dash  = '<td class="empty">-</td>';

  /* 1) 조직도 — 소속 / 성명 */
  const headBox = (title, m) =>
    '<div class="cbox"><div class="cbox__h">' + title + '</div>' +
    '<table class="ctab"><tr><th style="width:50%">소속</th><th>성명</th></tr>' +
    '<tr>' + (m.dept ? '<td>' + esc(m.dept) + '</td>' : dash) +
             (m.name ? '<td class="nm">' + esc(m.name) + '</td>' : dash) + '</tr></table></div>';

  let earlyRows = early.length
    ? early.map(x => '<tr><td>' + esc(x.name) + '</td>' + peopleCell(x.members).replace('class="ppl"', 'class="ppl"') + '</tr>').join('')
    : '<tr>' + dash + dash + '</tr>';

  let fieldRows = g.length
    ? g.map(x => '<tr><td>' + esc(x.name) + '</td>' + peopleCell(x.members) + '</tr>').join('')
    : '<tr>' + dash + dash + '</tr>';

  let h = '<div class="chart">';
  h += '<div class="chart__t">1. 자위소방대 및 초기대응체계 조직도<small>* 상시 근무인원 50명 미만 권장</small></div>';
  h += '<div class="crow">';
  h +=   '<div class="ccol">' + headBox('자위소방대장', model.cmd)
      +  '<div class="cstem"></div>' + headBox('부대장', model.deputy) + '</div>';
  h +=   '<div class="clink"></div>';
  h +=   '<div class="ccol"><div class="cbox"><div class="cbox__h">초기대응체계</div>'
      +  '<table class="ctab"><tr><th style="width:42%">조</th><th>인원</th></tr>'
      +  earlyRows + '</table></div></div>';
  h += '</div>';
  h += '<div class="cwide"><div class="cbox"><div class="cbox__h">현장대응팀</div>'
    +  '<table class="ctab"><tr><th style="width:42%">소속</th><th>인원</th></tr>'
    +  fieldRows + '</table></div></div>';

  /* 2) 임무 */
  const taskBox = (title, txt) =>
    '<div class="cbox"><div class="cbox__h">' + title + '</div>' +
    '<table class="ctab"><tr><td class="task">' + (txt ? esc(txt) : '-') + '</td></tr></table></div>';

  const groupTasks = (list) => list.length
    ? list.map(x => {
        const ts = [...new Set((x.members || []).map(m => (m.task || "").trim()).filter(Boolean))];
        return '<tr><td class="task"><b>' + esc(x.name) + '</b>'
             + (ts.length ? '<br>' + esc(ts.join(" / ")) : '') + '</td></tr>';
      }).join('')
    : '<tr><td class="task">-</td></tr>';

  h += '<div class="chart__t" style="margin-top:26px">2. 자위소방대 및 초기대응체계 임무</div>';
  h += '<div class="crow">';
  h +=   '<div class="ccol">' + taskBox('자위소방대장', model.cmd.task)
      +  '<div class="cstem"></div>' + taskBox('부대장', model.deputy.task) + '</div>';
  h +=   '<div class="clink"></div>';
  h +=   '<div class="ccol"><div class="cbox"><div class="cbox__h">초기대응체계</div>'
      +  '<table class="ctab">' + groupTasks(early) + '</table></div></div>';
  h += '</div>';
  h += '<div class="cwide"><div class="cbox"><div class="cbox__h">현장대응팀</div>'
    +  '<table class="ctab">' + groupTasks(g) + '</table></div></div>';
  h += '</div>';

  area.innerHTML = h;
}

function syncPrintHead(){
  const site = (document.getElementById('siteName')||{}).value || "";
  const work = (document.getElementById('workType')||{}).value || "";
  const total = 2 + model.groups.reduce((a,g)=>a+g.members.length,0);
  document.getElementById('pmSite').textContent = "대상물명 : " + site;
  document.getElementById('pmWork').textContent = "근무형태 : " + work + " · 편성인원 " + total + "명";
  const d = new Date();
  document.getElementById('pmDate').textContent =
    d.getFullYear() + "년 " + (d.getMonth()+1) + "월 " + d.getDate() + "일";
  document.getElementById('pmMgr').textContent = model.cmd.name || "";
  const tc = document.getElementById('totalCnt');
  if (tc) tc.textContent = total + "명";
}
document.addEventListener('input', e=>{
  if (e.target && (e.target.id==='siteName' || e.target.id==='workType')) {
    syncPrintHead();
    markPlanChanged();
  }
});

/* 표를 고치는 동안 조직도를 다시 그립니다.
   글자마다 그리면 끊기므로 잠깐 멈췄을 때만 갱신합니다. */
let chartT = null;
function chartSoon(){ clearTimeout(chartT); chartT = setTimeout(renderChart, 250); }

function updCmd(key,field,val){ model[key][field]=val; if(field==='name') syncPrintHead(); markPlanChanged(); chartSoon(); }
function upd(gi,mi,field,val){
  if(field==='gname'){ model.groups[gi].name=val; markPlanChanged(); chartSoon(); return; }
  model.groups[gi].members[mi][field]=val;
  markPlanChanged(); chartSoon();
}
function addMember(gi){ model.groups[gi].members.push({name:"",tel:"",dept:"",task:""}); markPlanChanged(); render(); }
function delMember(gi,mi){ model.groups[gi].members.splice(mi,1); markPlanChanged(); render(); }
function delGroup(gi){ if(confirm("이 활동조를 삭제할까요?")){ model.groups.splice(gi,1); markPlanChanged(); render(); } }
function addGroup(){ model.groups.push({name:"새 활동조", members:[{name:"",tel:"",dept:"",task:""}]}); markPlanChanged(); render(); }

/* ---------- 저장 / 불러오기 ---------- */
function collect(){
  return {
    site_name: document.getElementById('siteName').value,
    work_type: document.getElementById('workType').value,
    /* 적어둔 명단 원문도 함께 저장합니다.
       이게 없으면 불러왔을 때 편성 결과만 보이고, 명단을 다시 적어야 합니다. */
    bulk_text: (document.getElementById('bulkInput')||{}).value || '',
    cmd:    [model.cmd.name, model.cmd.tel, model.cmd.task],
    deputy: [model.deputy.name, model.deputy.tel, model.deputy.task],
    groups: model.groups
  };
}

async function saveplan(){
  const fd = new FormData();
  fd.append('csrf',CSRF); fd.append('action','fire_save');
  if(currentPlanId) fd.append('plan_id',currentPlanId);   // 있으면 그 항목 갱신
  fd.append('payload',JSON.stringify(collect()));
  try{
    const res = await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
    if(res.ok){
      currentPlanId = res.id;
      finalAction = 'building';
      toast(res.updated ? "수정 저장됨 ("+res.saved+")" : "새 편성표로 저장됨");
      setEditingHint();
      markSteps();      // 저장되면 3단계가 '저장됨'으로 바뀝니다
      loadList();
      showSavedDone(); // 저장 후 다음에 할 일을 안내합니다
    } else toast("저장 실패: "+(res.msg||""));
  }catch(e){ toast("네트워크 오류"); }
}

/* 저장이 끝나면 안내를 남기고, 하단의 '건물 관리로' 버튼을 강조합니다. */
function showSavedDone(){
  const el = document.getElementById('savedDone');
  if (!el) return;
  el.style.display = '';
  updateFinalActions();
}

function applyPlan(p){
  finalAction = '';
  rosterChanged = false;
  document.getElementById('siteName').value=p.site_name||"";
  document.getElementById('workType').value=p.work_type||"단일근무(주간)";
  model.cmd={name:(p.cmd&&p.cmd[0])||"",tel:(p.cmd&&p.cmd[1])||"",task:(p.cmd&&p.cmd[2])||CMD_TASK};
  model.deputy={name:(p.deputy&&p.deputy[0])||"",tel:(p.deputy&&p.deputy[1])||"",task:(p.deputy&&p.deputy[2])||DEP_TASK};
  model.groups=(p.groups||[]).map(g=>({name:g.name||"",members:(g.members||[]).map(m=>({name:m.name||"",tel:m.tel||"",task:m.task||""}))}));

  /* 적어둔 명단도 되살립니다.
     예전에 저장한 편성표에는 bulk_text 가 없으므로, 그때는 편성 결과에서 거꾸로 만들어 채웁니다. */
  const ta = document.getElementById('bulkInput');
  if (ta) {
    ta.value = (typeof p.bulk_text === 'string' && p.bulk_text !== '')
      ? p.bulk_text
      : rebuildBulkFromModel();
    countBulk();
  }
  render();
}

/* 편성 결과(model)에서 명단 텍스트를 거꾸로 만들어 냅니다.
   순서는 대장 → 부대장 → 활동조 차례이며, 자동 배치가 읽는 형식과 같습니다. */
function rebuildBulkFromModel(){
  const lines = [];
  const push = (m) => {
    if (!m || !m.name) return;
    lines.push([m.name, m.tel || '', m.task || ''].filter(Boolean).join('   '));
  };
  push(model.cmd);
  push(model.deputy);
  (model.groups || []).forEach(g => (g.members || []).forEach(push));
  return lines.join('\n');
}

async function loadPlan(id){
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_load'); fd.append('plan_id',id);
  const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(res && res.plan){
    currentPlanId=id; finalAction=''; applyPlan(res.plan); setEditingHint();
    loadList(); toggleFold(true);
    toast("불러왔습니다. 수정 후 저장하면 이 항목이 갱신됩니다.");
    document.getElementById('p3').scrollIntoView({behavior:'smooth', block:'start'});
  } else toast("불러올 수 없습니다.");
}

async function deletePlan(id, name){
  if(!confirm("'"+(name||'이 편성표')+"' 를 삭제할까요?")) return;
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_delete'); fd.append('plan_id',id);
  await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(currentPlanId===id){ currentPlanId=null; finalAction=''; setEditingHint(); }
  toast("삭제되었습니다."); loadList();
}

async function dupPlan(id){
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_dup'); fd.append('plan_id',id);
  const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(res.ok){ toast("복제되었습니다."); loadList(); }
  else toast("복제 실패: "+(res.msg||""));
}

function newPlan(){
  currentPlanId=null;
  finalAction='';
  rosterChanged=false;
  clearAll();
  document.getElementById('siteName').value="";
  document.getElementById('workType').value="단일근무(주간)";
  document.getElementById('bulkInput').value="";
  rosterChanged=false;
  countBulk(); setEditingHint(); toggleFold(false);
  toast("새 편성표 작성 모드입니다.");
  window.scrollTo({top:0,behavior:'smooth'});
}

function setEditingHint(){
  const el=document.getElementById('editingHint');
  const chip=document.getElementById('statusChip');
  if(currentPlanId){
    el.innerHTML="✏️ 기존 편성표 수정 중";
    chip.textContent="✏️ 수정 중"; chip.className="status edit";
  } else {
    el.innerHTML="🆕 저장하면 목록에 새로 추가됩니다";
    chip.textContent="🆕 새 편성표"; chip.className="status";
  }
  document.querySelectorAll('.plan-card').forEach(c=>c.classList.toggle('active', c.dataset.id===currentPlanId));
  updateFinalActions();
}

async function loadList(){
  const box=document.getElementById('planList');
  try{
    const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_list');
    const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
    const list=(res&&res.list)||[];
    const cntEl=document.getElementById('planCount');
    if(cntEl) cntEl.textContent = list.length ? list.length : '';
    if(list.length===0){
      box.innerHTML='<div class="empty">아직 저장된 편성표가<br>없습니다.<br><br>작성 후 아래 💾 저장을<br>눌러보세요.</div>';
      return;
    }
    box.innerHTML=list.map((p,i)=>`
      <div class="plan-card${p.id===currentPlanId?' active':''}" data-id="${p.id}" onclick="if(!event.target.closest('button'))loadPlan('${p.id}')">
        <div class="pc-row">
          <span class="pc-no">${i+1}</span>
          <span class="pc-name">${esc(p.site_name||'(이름 없음)')}</span>
          <span class="pc-tag">${p.id===currentPlanId?'수정 중':p.total+'명'}</span>
        </div>
        <div class="pc-meta">편성 ${p.total}명 · ${esc((p.saved||'').slice(0,16))}</div>
        <div class="pc-btns">
          <button class="pcbtn" onclick="loadPlan('${p.id}')">불러오기</button>
          <button class="pcbtn" onclick="dupPlan('${p.id}')">복제</button>
          <button class="pcbtn del" onclick="deletePlan('${p.id}','${esc(p.site_name||'').replace(/'/g,'')}')">삭제</button>
        </div>
      </div>`).join('');
  }catch(e){ box.innerHTML='<div class="empty">목록을 불러오지 못했습니다.</div>'; }
}


/* ---------- toast ---------- */
let toastT;
function toast(msg){
  const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
  clearTimeout(toastT); toastT=setTimeout(()=>t.classList.remove('show'),2200);
}

render();
setEditingHint();
loadList();

/* ── 입력에 반응해 안내를 갱신합니다 ── */
(function(){
  const ta = document.getElementById('bulkInput');
  if (ta) ta.addEventListener('input', function(){
    markRosterChanged();
    countBulk();
    updateFinalActions();
  });
  const sn = document.getElementById('siteName');
  if (sn) sn.addEventListener('input', markSteps);
  if (sn && sn.value.trim() !== '') {
    const tag = document.getElementById('siteAuto');
    if (tag) tag.style.display = '';
  }
  countBulk();
  markSteps();
})();

/* ── Ctrl/⌘+S 로 저장 ── */
document.addEventListener('keydown', function(e){
  if ((e.ctrlKey||e.metaKey) && (e.key==='s'||e.key==='S')) { e.preventDefault(); saveplan(); }
});

/* ── 저장하지 않고 나가려 하면 알려줍니다 ── */
(function(){
  let dirty = false;
  document.addEventListener('input', function(e){
    if (e.target && e.target.closest && e.target.closest('.layout')) dirty = true;
  });
  const _save = window.saveplan;
  if (typeof _save === 'function') {
    window.saveplan = function(){
      const r = _save.apply(this, arguments);
      Promise.resolve(r).then(function(){ dirty = false; }).catch(function(){});
      return r;
    };
  }
  window.addEventListener('beforeunload', function(e){
    const hasPeople = !!(model && (model.cmd && model.cmd.name || (model.groups||[]).length));
    if (dirty && hasPeople) { e.preventDefault(); e.returnValue = ''; }
  });
})();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
<!-- ══ 처음 방문 가이드 모달 ══ -->
<div class="guide-mask no-print" id="guideMask">
  <div class="guide-card">
    <button class="guide-x" type="button" onclick="closeGuide()" aria-label="닫기">✕</button>
    <div class="guide-hd">
      <span class="guide-emoji">📋</span>
      <h3>자위소방대 편성표, 이렇게 만들어요</h3>
      <p>3단계면 끝납니다. 명단만 적으면 나머지는 자동으로 채워져요.</p>
    </div>

    <div class="guide-demo">
      <div class="guide-demo__label">② 명단 칸에 이렇게 적어보세요</div>
      <div class="guide-line">
        <span class="guide-tag guide-tag--name">이름</span>
        <span class="guide-tag guide-tag--tel">전화번호</span>
        <span class="guide-tag guide-tag--dept">직급</span>
      </div>
      <div class="guide-example">홍길동&nbsp;&nbsp;010-1234-5678&nbsp;&nbsp;지점장</div>
      <div class="guide-arrow-down">↓ 한 줄에 한 명씩, 줄바꿈만 하면 됩니다</div>
      <div class="guide-example guide-example--dim">김철수&nbsp;&nbsp;010-2222-3333&nbsp;&nbsp;차장</div>
      <div class="guide-example guide-example--dim">이영희 <span class="guide-note">← 전화번호·직급은 없어도 OK</span></div>
    </div>

    <div class="guide-rule">
      <b>💡 적은 순서가 곧 직책이 됩니다</b>
      <div class="guide-rule__row">
        <span class="guide-rule__no">1</span>
        <span class="guide-rule__from">첫 줄</span>
        <span class="guide-rule__to"><b>대장</b></span>
      </div>
      <div class="guide-rule__row">
        <span class="guide-rule__no">2</span>
        <span class="guide-rule__from">둘째 줄</span>
        <span class="guide-rule__to"><b>부대장</b></span>
      </div>
      <div class="guide-rule__row">
        <span class="guide-rule__no">3</span>
        <span class="guide-rule__from">나머지</span>
        <span class="guide-rule__to"><b>활동조</b>에 순서대로<br>
          <span class="guide-rule__sub">비상연락 · 초기소화 · 피난유도 …</span></span>
      </div>
    </div>

    <div class="guide-foot guide-foot--single">
      <button class="btn btn-primary" type="button" onclick="closeGuide(true)">
        확인했어요, 명단 적으러 가기
      </button>
    </div>
  </div>
</div>

</body>
</html>
