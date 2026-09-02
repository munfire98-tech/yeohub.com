<?php
// building_setup.php — 건물 기본정보 입력 (여러 서식이 공유)
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

require_once __DIR__ . '/building_info.php';

/* 집결지 지도를 그리는 데 쓰는 카카오 JavaScript 키.
   파일이 없거나 키가 비어 있어도 페이지는 정상 동작합니다(지도만 안 보임). */
$__api = @include __DIR__ . '/api_keys.php';
$KAKAO_JS = (is_array($__api) && !empty($__api['kakao_js'])) ? (string)$__api['kakao_js'] : '';
require_once __DIR__ . '/user_key.php';

/* 회원을 특정하지 못하면 남의 데이터를 읽거나 덮어쓸 수 있으므로 여기서 멈춘다 */
if (!app_has_user_key()) {
  http_response_code(409);
  echo '<!doctype html><meta charset="utf-8">'
     . '<div style="max-width:520px;margin:80px auto;padding:0 20px;'
     . 'font-family:system-ui,\'Apple SD Gothic Neo\',sans-serif;line-height:1.7">'
     . '<h1 style="font-size:20px">기본정보를 불러오지 못했습니다</h1><p style="color:#56627a">'
     . h(app_user_key_notice()) . '</p>'
     . '<p><a href="/building_manager.php" style="color:#1d4ed8">← 돌아가기</a></p></div>';
  exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }
  $mgrs = [];
  for ($i = 0; $i < 4; $i++) {
    $mgrs[] = [
      'name' => $_POST['m_name'][$i] ?? '',
      'appt' => $_POST['m_appt'][$i] ?? '',
      'qual' => $_POST['m_qual'][$i] ?? '',
      'type' => $_POST['m_type'][$i] ?? '',
      'tel'  => $_POST['m_tel'][$i]  ?? '',
    ];
  }
  $saved = bi_save(array_merge(bi_load(), $_POST, ['mgrs' => $mgrs]));
  $saveErr = !$saved;
}

/* ── 기본정보 초기화 ──────────────────────────────────────
   저장된 건물 기본정보를 모두 비웁니다.
   신규 사용자가 처음 들어온 상태를 확인할 때, 또는 다른 건물로
   새로 시작할 때 사용합니다. (다른 서식이 참조하는 값이므로 주의) */
$resetDone = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }
  if (trim((string)($_POST['confirm'] ?? '')) === '초기화') {
    $resetDone = bi_save(bi_blank());   // 빈 구조로 덮어써서 전부 비운다
    $resetErr  = !$resetDone;
  } else {
    $resetErr = true;
    $resetMsg = '확인 문구가 일치하지 않아 초기화하지 않았습니다.';
  }
}

$d = bi_load();
$mgrs = $d['mgrs'];
$nick = $_SESSION['nickname'] ?? '사용자';
$viewUid = app_user_key();
$adminView = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '' && $viewUid !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($viewUid)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};
$v  = fn(string $k) => h($d[$k] ?? '');
$mv = fn(int $i, string $k) => h($mgrs[$i][$k] ?? '');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>건물 기본정보 — YeoHub</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);
  backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:880px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;
  font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}

.head{border-bottom:1px solid var(--bd);background:linear-gradient(180deg,#fbfcff,#eef3fb)}
.head__in{max-width:880px;margin:0 auto;padding:36px 20px 30px}
.crumb{font-size:13px;color:var(--mut2);margin-bottom:10px}
.crumb a{color:var(--mut2)}
.head h1{font-size:26px;font-weight:700;letter-spacing:-.3px}
.head p{color:var(--mut2);font-size:14.5px;margin-top:6px}

.wrap{max-width:880px;margin:0 auto;padding:24px 20px 100px}
.toast{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;
  border-radius:10px;padding:12px 15px;font-size:13.5px;margin-bottom:18px}
.info{display:flex;gap:11px;background:#f0f7ff;border:1px solid #cfe0ff;border-radius:12px;
  padding:14px 16px;margin-bottom:20px;font-size:13px;color:var(--brand2);line-height:1.7}

.sec{background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:22px;margin-bottom:16px}
.sec__t{display:flex;align-items:center;gap:9px;font-size:15.5px;font-weight:700;
  padding-bottom:12px;margin-bottom:16px;border-bottom:1px solid var(--bd)}
.sec__t .n{width:24px;height:24px;border-radius:7px;background:var(--fg);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.sec__t small{margin-left:auto;font-weight:400;font-size:11.5px;color:var(--mut);
  background:#f1f5f9;padding:3px 9px;border-radius:999px}

.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:13px}
.row+.row{margin-top:13px}
.fld{display:flex;flex-direction:column;gap:5px}
.fld label{font-size:12px;color:var(--mut2);font-weight:600}
.fld input{padding:10px 12px;border:1px solid var(--bd2);border-radius:9px;
  font-size:14px;font-family:inherit;background:#f8fafc;color:var(--fg)}
.fld input:focus{outline:none;border-color:var(--brand);background:#fff;
  box-shadow:0 0 0 3px rgba(37,99,235,.08)}
.fld--wide{grid-column:1/-1}

.seg{display:flex;gap:0;border:1px solid var(--bd2);border-radius:9px;overflow:hidden;width:fit-content}
.seg label{padding:9px 16px;font-size:13.5px;cursor:pointer;background:#fff;
  border-right:1px solid var(--bd2);transition:.12s}
.seg label:last-child{border-right:0}
.seg input{display:none}
.seg label.on{background:#eef4ff;color:var(--brand2);font-weight:700}

table.mgr{width:100%;border-collapse:collapse;font-size:13px}
table.mgr th{background:#f7f9fc;font-size:11.5px;font-weight:700;color:var(--mut2);
  padding:8px 9px;text-align:left;border-bottom:1px solid var(--bd)}
table.mgr td{padding:6px 5px;border-bottom:1px solid #eef1f6}
table.mgr tr:last-child td{border-bottom:0}
table.mgr input{width:100%;padding:8px 9px;border:1px solid var(--bd2);border-radius:7px;
  font-size:13px;font-family:inherit;background:#fff}
table.mgr input:focus{outline:none;border-color:var(--brand)}
.tseg{display:flex;gap:4px}
.tseg label{padding:6px 9px;font-size:11.5px;border:1px solid var(--bd2);border-radius:7px;
  cursor:pointer;background:#fff;white-space:nowrap}
.tseg label.on{background:#eef4ff;border-color:var(--brand);color:var(--brand2);font-weight:700}
.tseg input{display:none}
.mgr__no{width:26px;height:26px;border-radius:7px;background:#f1f5f9;color:var(--mut2);
  display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700}

.savebar{position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.96);
  backdrop-filter:blur(10px);border-top:1px solid var(--bd);padding:12px 20px;
  display:flex;justify-content:center;gap:10px;z-index:40}
.savebar .btn{padding:12px 30px;font-size:14.5px}

@media(max-width:640px){
  .nav__in,.head__in,.wrap{padding-left:14px;padding-right:14px}
  .head h1{font-size:22px}
  .sec{padding:18px 15px}
  .row{grid-template-columns:1fr;gap:11px}
  table.mgr,table.mgr tbody,table.mgr tr,table.mgr td{display:block;width:100%}
  table.mgr thead{display:none}
  table.mgr tr{border:1px solid var(--bd);border-radius:10px;padding:11px;margin-bottom:10px}
  table.mgr td{border:0;padding:5px 0}
  table.mgr td::before{content:attr(data-l);display:block;font-size:11px;
    color:var(--mut2);font-weight:600;margin-bottom:4px}
  .tseg{gap:6px}
  .tseg label{flex:1;text-align:center;padding:9px}
}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">YEOHUB</a>
    <a class="btn" href="<?=h($url('/building_manager.php'))?>">← 메인</a>
  </div>
</nav>

<header class="head">
  <div class="head__in">
    <div class="crumb"><a href="<?=h($url('/building_manager.php'))?>">건물 소방안전관리</a> › 기본정보</div>
    <h1>건물 기본정보</h1>
    <p>한 번만 입력하면 업무수행 기록표·훈련 기록부·소방계획서에 자동으로 채워집니다.</p>
  </div>
</header>

<main class="wrap">
  <?php if ($saved): ?>
    <div class="toast">✓ 저장되었습니다. 이제 각 서식에서 자동으로 불러옵니다.</div>
  <?php elseif (!empty($saveErr)): ?>
    <div class="toast" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">
      ✕ 저장하지 못했습니다. data 폴더 쓰기 권한을 확인하거나, 다시 로그인 후 시도해 주세요.</div>
  <?php elseif ($resetDone): ?>
    <div class="toast">✓ 기본정보를 모두 비웠습니다. 처음 입력하는 상태가 되었습니다.</div>
  <?php elseif (!empty($resetErr)): ?>
    <div class="toast" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">
      ✕ <?= h($resetMsg ?? '초기화하지 못했습니다. data 폴더 쓰기 권한을 확인해 주세요.') ?></div>
  <?php endif; ?>

  <div class="info">
    <div>💡</div>
    <div>여기 입력한 정보는 <b>별지 제12호(업무수행 기록표)</b>, <b>제28호(훈련·교육 기록부)</b>,
      <b>제13호(자위소방대 교육·훈련)</b>, <b>소방계획서</b>에서 함께 사용됩니다.
      매번 다시 입력할 필요가 없습니다.</div>
  </div>

  <form method="post" id="biForm">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
    <input type="hidden" name="action" value="save">

    <!-- 1. 대상물 -->
    <section class="sec">
      <div class="sec__t"><span class="n">1</span> 소방안전관리대상물 <small>모든 서식 공통</small></div>
      <div class="row">
        <div class="fld fld--wide"><label>대상명 (상호)</label>
          <input type="text" name="name" value="<?=$v('name')?>" placeholder="예: 투릭스빌딩"></div>
      </div>
      <div class="row">
        <div class="fld"><label>용도</label>
          <input type="text" name="use" value="<?=$v('use')?>" placeholder="예: 근린생활시설"></div>
        <div class="fld"><label>대표자</label>
          <input type="text" name="rep" value="<?=$v('rep')?>"></div>
        <div class="fld"><label>전화번호</label>
          <input type="text" name="tel" value="<?=$v('tel')?>" placeholder="02-0000-0000"></div>
      </div>
      <div class="row">
        <div class="fld fld--wide"><label>소재지</label>
          <input type="text" name="address" value="<?=$v('address')?>" placeholder="도로명 주소"></div>
      </div>
      <div class="row">
        <div class="fld fld--wide"><label>등급</label>
          <div class="seg" data-seg>
            <?php foreach (['특급','1급','2급','3급'] as $g): $on = ($d['grade'] ?? '') === $g; ?>
              <label class="<?=$on?'on':''?>">
                <input type="radio" name="grade" value="<?=$g?>" <?=$on?'checked':''?>><?=$g?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. 규모 -->
    <section class="sec">
      <div class="sec__t"><span class="n">2</span> 건물 규모 <small>업무수행 기록표</small></div>
      <div class="row">
        <div class="fld"><label>지하층</label>
          <input type="text" name="floor_b" value="<?=$v('floor_b')?>" placeholder="예: 2"></div>
        <div class="fld"><label>지상층</label>
          <input type="text" name="floor_a" value="<?=$v('floor_a')?>" placeholder="예: 10"></div>
        <div class="fld"><label>동수</label>
          <input type="text" name="dongsu" value="<?=$v('dongsu')?>" placeholder="예: 1"></div>
      </div>
      <div class="row">
        <div class="fld"><label>연면적 (㎡)</label>
          <input type="text" name="area_t" value="<?=$v('area_t')?>" placeholder="예: 12000"></div>
        <div class="fld"><label>바닥면적 (㎡)</label>
          <input type="text" name="area_f" value="<?=$v('area_f')?>" placeholder="예: 1200"></div>
      </div>
    </section>

    <!-- 2-1. 건축물대장 상세 (자동조회) -->
    <section class="sec">
      <div class="sec__t"><span class="n">2</span> 건축물대장 상세
        <small><?= trim((string)($d['bd_looked'] ?? '')) !== '' ? ('자동조회 '.h($d['bd_looked'])) : '검색으로 자동 채움' ?></small></div>
      <div class="row">
        <div class="fld"><label>구조</label>
          <input type="text" name="bd_struct" value="<?=$v('bd_struct')?>" placeholder="예: 철근콘크리트구조"></div>
        <div class="fld"><label>기타구조</label>
          <input type="text" name="bd_struct_etc" value="<?=$v('bd_struct_etc')?>"></div>
        <div class="fld"><label>높이 (m)</label>
          <input type="text" name="bd_height" value="<?=$v('bd_height')?>" placeholder="예: 46.59"></div>
      </div>
      <div class="row">
        <div class="fld"><label>대지면적 (㎡)</label>
          <input type="text" name="bd_area_plat" value="<?=$v('bd_area_plat')?>"></div>
        <div class="fld"><label>건폐율 (%)</label>
          <input type="text" name="bd_bcrat" value="<?=$v('bd_bcrat')?>"></div>
        <div class="fld"><label>용적률 (%)</label>
          <input type="text" name="bd_vlrat" value="<?=$v('bd_vlrat')?>"></div>
      </div>
      <div class="row">
        <div class="fld"><label>주용도 (원문)</label>
          <input type="text" name="bd_use_main" value="<?=$v('bd_use_main')?>" placeholder="예: 공동주택"></div>
        <div class="fld"><label>기타용도</label>
          <input type="text" name="bd_use_etc" value="<?=$v('bd_use_etc')?>"></div>
        <div class="fld"><label>사용승인일</label>
          <input type="text" name="bd_use_apr" value="<?=$v('bd_use_apr')?>" placeholder="예: 2013.08.14"></div>
      </div>
      <div class="row">
        <div class="fld"><label>허가일</label>
          <input type="text" name="bd_pms_day" value="<?=$v('bd_pms_day')?>"></div>
        <div class="fld"><label>착공일</label>
          <input type="text" name="bd_stcns_day" value="<?=$v('bd_stcns_day')?>"></div>
        <div class="fld"><label>세대수</label>
          <input type="text" name="bd_hhld" value="<?=$v('bd_hhld')?>"></div>
      </div>
      <div class="row">
        <div class="fld"><label>가구수</label>
          <input type="text" name="bd_family" value="<?=$v('bd_family')?>"></div>
        <div class="fld"><label>호수</label>
          <input type="text" name="bd_ho" value="<?=$v('bd_ho')?>"></div>
        <div class="fld"><label>총주차대수</label>
          <input type="text" name="bd_park" value="<?=$v('bd_park')?>"></div>
      </div>
      <div class="row">
        <div class="fld"><label>승용승강기</label>
          <input type="text" name="bd_elev" value="<?=$v('bd_elev')?>"></div>
        <div class="fld"><label>주건축물수</label>
          <input type="text" name="bd_main_bld" value="<?=$v('bd_main_bld')?>"></div>
        <div class="fld"><label>부속건축물수</label>
          <input type="text" name="bd_atch_bld" value="<?=$v('bd_atch_bld')?>"></div>
      </div>
      <div class="row">
        <div class="fld"><label>내진설계</label>
          <input type="text" name="bd_seismic" value="<?=$v('bd_seismic')?>" placeholder="적용/미적용"></div>
        <div class="fld"><label>내진능력</label>
          <input type="text" name="bd_seismic_ablty" value="<?=$v('bd_seismic_ablty')?>"></div>
        <div class="fld"><label>에너지효율등급</label>
          <input type="text" name="bd_energy" value="<?=$v('bd_energy')?>"></div>
      </div>
      <div class="row">
        <div class="fld fld--wide"><label>도로명 대지위치</label>
          <input type="text" name="bd_road_addr" value="<?=$v('bd_road_addr')?>"></div>
      </div>
      <?php if (trim((string)($d['bd_dongs'] ?? '')) !== ''): ?>
      <div class="row">
        <div class="fld fld--wide"><label>동별 층수·구조 (여러 동)
          <?php if (trim((string)($d['bd_dong_pick'] ?? '')) !== ''): ?>
            <span style="font-weight:600;color:var(--brand2)">— 선택: <?= h($d['bd_dong_pick']==='ALL' ? '전체 동' : $d['bd_dong_pick']) ?></span>
          <?php endif; ?>
        </label>
          <textarea name="bd_dongs" rows="4" style="padding:10px 12px;border:1px solid var(--bd2);border-radius:9px;font-size:14px;font-family:inherit;background:#f8fafc;color:var(--fg);resize:vertical"><?=$v('bd_dongs')?></textarea></div>
      </div>
      <?php
        /* 동별 상세(구조화)는 시뮬레이션이 쓰는 값이라 화면에서는 읽기 전용으로 보여주고
           원본 배열은 hidden 으로 그대로 되돌려 저장합니다(수정 시 유실 방지). */
        $dl = $d['bd_dong_list'] ?? [];
        if (is_array($dl) && $dl):
      ?>
      <div class="row">
        <div class="fld fld--wide"><label>동별 상세 (시뮬레이션 연동용 · 자동)</label>
          <table style="width:100%;border-collapse:collapse;font-size:12.5px;background:#fff">
            <tr style="background:#f3f6fa">
              <th style="border:1px solid var(--bd);padding:6px 8px;text-align:left">동</th>
              <th style="border:1px solid var(--bd);padding:6px 8px;text-align:left">지상/지하</th>
              <th style="border:1px solid var(--bd);padding:6px 8px;text-align:left">구조</th>
              <th style="border:1px solid var(--bd);padding:6px 8px;text-align:left">높이</th>
              <th style="border:1px solid var(--bd);padding:6px 8px;text-align:left">연면적</th>
            </tr>
            <?php foreach ($dl as $g): ?>
            <tr>
              <td style="border:1px solid var(--bd);padding:6px 8px"><?=h($g['dong'] ?? '')?></td>
              <td style="border:1px solid var(--bd);padding:6px 8px"><?=h($g['floor_a'] ?? '')?>/<?=h($g['floor_b'] ?? '')?>층</td>
              <td style="border:1px solid var(--bd);padding:6px 8px"><?=h($g['struct'] ?? '')?></td>
              <td style="border:1px solid var(--bd);padding:6px 8px"><?=h($g['height'] ?? '')?><?= ($g['height']??'')!=='' ? 'm' : '' ?></td>
              <td style="border:1px solid var(--bd);padding:6px 8px"><?= ($g['area']??'')!=='' ? number_format((float)$g['area']).'㎡' : '' ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>
      <?php else: ?>
        <input type="hidden" name="bd_dongs" value="<?=$v('bd_dongs')?>">
      <?php endif; ?>
      <input type="hidden" name="bd_dong_pick" value="<?=$v('bd_dong_pick')?>">
      <input type="hidden" name="bd_dong_list" value="<?=h(json_encode($d['bd_dong_list'] ?? [], JSON_UNESCAPED_UNICODE))?>">
      <input type="hidden" name="bd_looked" value="<?=$v('bd_looked')?>">
    </section>

    <!-- 3. 소방안전관리자 -->
    <section class="sec">
      <div class="sec__t"><span class="n">3</span> 소방안전관리자 <small>28호 · 13호</small></div>
      <table class="mgr">
        <thead>
          <tr><th style="width:34px"></th><th>성명</th><th>선임일자</th>
              <th>보유자격</th><th style="width:110px">구분</th><th>연락처</th></tr>
        </thead>
        <tbody>
          <?php for ($i=0;$i<4;$i++): $ty = $mgrs[$i]['type'] ?? ''; ?>
          <tr>
            <td><div class="mgr__no"><?=$i+1?></div></td>
            <td data-l="성명"><input type="text" name="m_name[]" value="<?=$mv($i,'name')?>"></td>
            <td data-l="선임일자"><input type="text" name="m_appt[]" value="<?=$mv($i,'appt')?>" placeholder="2026-01-01"></td>
            <td data-l="보유자격"><input type="text" name="m_qual[]" value="<?=$mv($i,'qual')?>" placeholder="예: 2급"></td>
            <td data-l="구분">
              <div class="tseg" data-tseg>
                <label class="<?=$ty==='주'?'on':''?>"><input type="radio" name="m_type[<?=$i?>]" value="주" <?=$ty==='주'?'checked':''?>>주</label>
                <label class="<?=$ty==='보조'?'on':''?>"><input type="radio" name="m_type[<?=$i?>]" value="보조" <?=$ty==='보조'?'checked':''?>>보조</label>
              </div>
            </td>
            <td data-l="연락처"><input type="text" name="m_tel[]" value="<?=$mv($i,'tel')?>"></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </section>

    <!-- 4. 집결지 -->
    <section class="sec">
      <div class="sec__t"><span class="n">4</span> 집결지, 소방차 진입로 <small>화재 시 대피 후 모이는 장소, 소방차 진입로 </small></div>
      <div class="row">
        <div class="fld" style="flex:1 1 100%">
          <label>집결지 이름</label>
          <input type="text" name="assembly_kind" value="<?=$v('assembly_kind')?>"
                 placeholder="예: 앞 주차장, 건너편 공원">
        </div>
      </div>

      <?php
        $asmLat = trim((string)($d['assembly_lat'] ?? ''));
        $asmLng = trim((string)($d['assembly_lng'] ?? ''));
      ?>
      <!-- 좌표는 숨겨서 함께 저장합니다(지도는 이 좌표로 매번 새로 그립니다) -->
      <input type="hidden" name="assembly_lat" id="asmLat" value="<?=h($asmLat)?>">
      <input type="hidden" name="assembly_lng" id="asmLng" value="<?=h($asmLng)?>">
      <input type="hidden" name="fire_engine_route" id="fireEngineRoute" value="<?=h((string)($d['fire_engine_route'] ?? ''))?>">

      <?php if ($asmLat !== '' && $asmLng !== ''): ?>
        <div id="asmMap" style="width:100%;height:240px;border:1px solid var(--bd);
             border-radius:10px;margin-top:10px;background:#eef2f7"></div>
        <div class="hint" style="margin-top:8px">
          기본 상태에서는 지도를 눌러 집결지를 옮길 수 있습니다.
          <span id="asmSaved" style="color:var(--ok);font-weight:700;display:none">위치가 바뀌었습니다 — 아래 저장을 눌러주세요.</span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
          <button class="btn" type="button" id="routeEditBtn">소방차 진입로 그리기</button>
          <button class="btn" type="button" id="routeUndoBtn" style="display:none">마지막 점 취소</button>
          <button class="btn" type="button" id="routeResetBtn" style="display:none">진입로 지우기</button>
        </div>
        <div class="hint" id="routeHint" style="margin-top:8px"></div>
      <?php else: ?>
        <div class="hint" style="margin-top:10px">
          아직 지도에서 위치를 찍지 않았습니다.
          <a href="/building_setup_chat.php">대화형 입력</a>에서 지도를 눌러 지정할 수 있습니다.
        </div>
      <?php endif; ?>
    </section>

  </form>

  <!-- 기본정보 초기화 -->
  <section class="sec" style="border-color:#fecaca;background:#fffafa">
    <div class="sec__t"><span class="n" style="background:#dc2626">↺</span> 기본정보 초기화
      <small>다른 건물로 새로 시작하거나, 처음 상태를 확인할 때</small></div>

    <div style="font-size:12.5px;color:#991b1b;line-height:1.8;background:#fef2f2;
                border:1px solid #fecaca;border-radius:9px;padding:12px 14px;margin-bottom:12px">
      위에 입력한 <b>대상물·규모·건축물대장·소방안전관리자·집결지</b>가 모두 비워집니다.<br>
      이 정보는 <b>업무수행 기록표·훈련 기록부·소방계획서</b>가 함께 쓰므로,
      비우면 그 서식들에서도 불러올 값이 없어집니다.<br>
      <b>되돌릴 수 없으니</b> 필요하면 먼저 내용을 따로 적어두세요.
    </div>

    <form method="post" onsubmit="return confirm('기본정보를 모두 비웁니다.\n되돌릴 수 없습니다. 계속할까요?')">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="action" value="reset">
      <div class="row">
        <div class="fld fld--wide">
          <label>확인을 위해 <b>초기화</b> 를 입력하세요</label>
          <input type="text" name="confirm" placeholder="초기화" autocomplete="off" required>
        </div>
        <div class="fld" style="display:flex;align-items:flex-end">
          <button class="btn" type="submit"
            style="background:#dc2626;color:#fff;border-color:#dc2626;padding:11px 22px;white-space:nowrap">
            전부 비우기
          </button>
        </div>
      </div>
    </form>
  </section>
</main>

<div class="savebar">
  <a class="btn" href="<?=h($url('/building_manager.php'))?>">취소</a>
  <button class="btn btn--primary" onclick="document.getElementById('biForm').requestSubmit()">저장</button>
</div>

<script>
  document.querySelectorAll('[data-seg],[data-tseg]').forEach(function(g){
    g.querySelectorAll('label').forEach(function(lb){
      lb.addEventListener('click', function(){
        g.querySelectorAll('label').forEach(function(x){ x.classList.remove('on'); });
        lb.classList.add('on');
      });
    });
  });
</script>

<?php if ($KAKAO_JS !== '' && $asmLat !== '' && $asmLng !== ''): ?>
<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey=<?=h($KAKAO_JS)?>&autoload=false"></script>
<script>
/* 집결지 지도 — 저장된 좌표로 매번 새로 그립니다(이미지를 따로 저장하지 않습니다).
   지도를 누르면 마커가 옮겨지고, 숨은 입력칸의 좌표가 함께 바뀝니다. */
(function(){
  if (typeof kakao === 'undefined' || !kakao.maps) return;
  kakao.maps.load(function(){
    var el = document.getElementById('asmMap');
    if (!el) return;
    var lat = parseFloat(document.getElementById('asmLat').value);
    var lng = parseFloat(document.getElementById('asmLng').value);
    if (!lat || !lng) return;

    var pos = new kakao.maps.LatLng(lat, lng);
    var map = new kakao.maps.Map(el, { center: pos, level: 3 });
    var marker = new kakao.maps.Marker({ map: map, position: pos });
    var routeMode = false, routeLine = null, routeDots = [], route = [];
    try { var parsed=JSON.parse(document.getElementById('fireEngineRoute').value||'[]'); if(Array.isArray(parsed)) route=parsed; } catch(e){}
    function drawRoute(){
      if(routeLine) routeLine.setMap(null);
      routeDots.forEach(function(x){x.setMap(null);}); routeDots=[];
      var path=route.filter(function(p){return p&&isFinite(p.lat)&&isFinite(p.lng);})
        .map(function(p){return new kakao.maps.LatLng(Number(p.lat),Number(p.lng));});
      if(path.length){
        routeLine=new kakao.maps.Polyline({map:map,path:path,strokeWeight:6,strokeColor:'#dc2626',strokeOpacity:.9});
        path.forEach(function(p){routeDots.push(new kakao.maps.Circle({map:map,center:p,radius:3,strokeWeight:2,strokeColor:'#fff',fillColor:'#dc2626',fillOpacity:1}));});
      }
      document.getElementById('fireEngineRoute').value=path.length?JSON.stringify(route):'';
      var hint=document.getElementById('routeHint');
      hint.textContent=routeMode?'도로에서 건물 입구 방향으로 차례대로 누르세요. 완료되면 아래 저장을 누릅니다.':(path.length?'소방차 진입로 '+path.length+'개 지점이 저장되어 있습니다.':'아직 소방차 진입로를 표시하지 않았습니다.');
    }

    // 건물 위치도 같이 보여주면 거리 감이 잡힙니다.
    var bLat = parseFloat(<?=json_encode((string)($d['bd_lat'] ?? ''))?>);
    var bLng = parseFloat(<?=json_encode((string)($d['bd_lng'] ?? ''))?>);
    if (bLat && bLng){
      var bPos = new kakao.maps.LatLng(bLat, bLng);
      new kakao.maps.Circle({ map: map, center: bPos, radius: 6,
        strokeWeight: 2, strokeColor: '#2563eb', strokeOpacity: 1,
        fillColor: '#2563eb', fillOpacity: 0.9 });
      new kakao.maps.CustomOverlay({ map: map, position: bPos, yAnchor: 2.2,
        content: '<div style="background:#2563eb;color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;white-space:nowrap">건물</div>' });
    }

    kakao.maps.event.addListener(map, 'click', function(e){
      if(routeMode){route.push({lat:e.latLng.getLat(),lng:e.latLng.getLng()});drawRoute();return;}
      marker.setPosition(e.latLng);
      document.getElementById('asmLat').value = e.latLng.getLat();
      document.getElementById('asmLng').value = e.latLng.getLng();
      var s = document.getElementById('asmSaved');
      if (s) s.style.display = 'inline';
    });
    var editBtn=document.getElementById('routeEditBtn'), undoBtn=document.getElementById('routeUndoBtn'), resetBtn=document.getElementById('routeResetBtn');
    editBtn.onclick=function(){routeMode=!routeMode;editBtn.textContent=routeMode?'진입로 그리기 완료':'소방차 진입로 그리기';undoBtn.style.display=resetBtn.style.display=routeMode?'':'none';drawRoute();};
    undoBtn.onclick=function(){route.pop();drawRoute();};
    resetBtn.onclick=function(){route=[];drawRoute();};
    drawRoute();
  });
})();
</script>
<?php endif; ?>
</body>
</html>
