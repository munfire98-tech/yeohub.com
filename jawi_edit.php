<?php
/* =============================================================
   jawi_edit.php — 자위소방대 교육·훈련 기록부 (표로 작성)
   별지 제13호서식의 항목을 그대로 표에 옮겼습니다.
   문답으로 채운 뒤 여기서 다듬는 용도입니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

/* mbstring 이 없는 서버를 위한 대체 (공유 호스팅에 빠져 있는 경우가 있습니다) */
if (!function_exists('mb_substr')) {
  function mb_substr($s, $start, $len = null, $enc = null) {
    preg_match_all('/./us', (string)$s, $m);
    $a = $len === null ? array_slice($m[0], $start) : array_slice($m[0], $start, $len);
    return implode('', $a);
  }
}
if (!function_exists('mb_strpos')) {
  function mb_strpos($h, $n, $o = 0, $e = null) { return strpos((string)$h, (string)$n, $o); }
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

require_once __DIR__ . '/jawi_db.php';
require_once __DIR__ . '/building_info.php';

$id  = (string)($_GET['id'] ?? '');
$rec = $id !== '' ? jw_load($id) : null;
if (!$rec) { $id = jw_create(); header('Location: /jawi_edit.php?id=' . urlencode($id)); exit; }

/* ── 저장 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
  jw_csrf_check();
  $t = fn(string $k, int $n = 200) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n, 'UTF-8');

  $d = [];
  foreach (['write_date','writer','site_name','rep','address','tel',
            'wd_day','wd_night','hd_day','hd_night',
            'jawi_total','jawi_chief','jawi_chief_tel','jawi_vice','jawi_call','jawi_fire',
            'jawi_guide','jawi_emer','jawi_join','jawi_absent',
            'init_org','init_total','init_join','init_absent',
            'edu_date','edu_place'] as $k) $d[$k] = $t($k);

  foreach (['edu_content','edu_fix','edu_action'] as $k) $d[$k] = $t($k, 4000);

  $g = (string)($_POST['grade'] ?? '');
  $d['grade'] = in_array($g, ['특급','1급','2급','3급'], true) ? $g : '';

  /* 소방안전관리자 4줄 */
  $d['mgrs'] = [];
  for ($i = 0; $i < 4; $i++) {
    $nm = mb_substr(trim((string)($_POST['m_name'][$i] ?? '')), 0, 30, 'UTF-8');
    $tl = mb_substr(trim((string)($_POST['m_tel'][$i] ?? '')), 0, 30, 'UTF-8');
    if ($nm === '' && $tl === '') continue;
    $ty = (string)($_POST['m_type'][$i] ?? '');
    $d['mgrs'][] = [
      'name' => $nm,
      'appt' => mb_substr(trim((string)($_POST['m_appt'][$i] ?? '')), 0, 20, 'UTF-8'),
      'qual' => mb_substr(trim((string)($_POST['m_qual'][$i] ?? '')), 0, 30, 'UTF-8'),
      'type' => in_array($ty, ['주','보조'], true) ? $ty : '',
      'tel'  => $tl,
      'note' => mb_substr(trim((string)($_POST['m_note'][$i] ?? '')), 0, 40, 'UTF-8'),
    ];
  }

  /* 참석자 — 한 줄에 한 명 (직책 성명) */
  $d['attend'] = [];
  foreach (preg_split('/\r\n|\n|\r/', (string)($_POST['attend_text'] ?? '')) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $sp = mb_strpos($line, ' ', 0, 'UTF-8');
    if ($sp !== false && $sp > 0) {
      $d['attend'][] = ['role' => mb_substr($line, 0, $sp, 'UTF-8'),
                        'name' => trim(mb_substr($line, $sp + 1, null, 'UTF-8')), 'ok' => ''];
    } else {
      $d['attend'][] = ['role' => '', 'name' => $line, 'ok' => ''];
    }
    if (count($d['attend']) >= 50) break;
  }

  jw_save($id, $d);
  header('Location: /jawi_edit.php?id=' . urlencode($id) . '&saved=1');
  exit;
}

$d = (array)($rec['data'] ?? []);

/* 비어 있으면 건물 기본정보에서 가져옵니다 */
$bi = bi_load();
$fallback = [
  'site_name' => (string)($bi['name'] ?? ''), 'address' => (string)($bi['address'] ?? ''),
  'grade' => (string)($bi['grade'] ?? ''), 'rep' => (string)($bi['rep'] ?? ''),
  'tel' => (string)($bi['tel'] ?? ''),
  'wd_day' => (string)($bi['wd_day'] ?? ''), 'wd_night' => (string)($bi['wd_night'] ?? ''),
  'hd_day' => (string)($bi['hd_day'] ?? ''), 'hd_night' => (string)($bi['hd_night'] ?? ''),
];
foreach ($fallback as $k => $fv) { if (trim((string)($d[$k] ?? '')) === '') $d[$k] = $fv; }
if (!($d['mgrs'] ?? []) && is_array($bi['mgrs'] ?? null)) $d['mgrs'] = $bi['mgrs'];

$v     = fn(string $k) => h((string)($d[$k] ?? ''));
$mgrs  = (array)($d['mgrs'] ?? []);
while (count($mgrs) < 4) $mgrs[] = [];
$attTxt = '';
foreach ((array)($d['attend'] ?? []) as $a) {
  $nm = trim((string)($a['name'] ?? ''));
  if ($nm === '') continue;
  $attTxt .= trim(((string)($a['role'] ?? '')) . ' ' . $nm) . "\n";
}
$nick = $_SESSION['nickname'] ?? '사용자';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>자위소방대 교육·훈련 기록부 — 표로 작성</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;font-size:14px}
a{text-decoration:none;color:inherit}
.nav{background:#fff;border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:20}
.nav__in{max-width:900px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.wrap{max-width:900px;margin:0 auto;padding:24px 20px 80px}
h1{font-size:22px;font-weight:800;letter-spacing:-.3px}
.lead{color:var(--mut2);font-size:14px;margin-top:6px}
.toast{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:11px;
  padding:12px 15px;margin:16px 0}
.sec{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-top:16px}
.sec__t{font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:4px}
.sec__n{width:22px;height:22px;border-radius:6px;background:#eef2ff;color:var(--brand2);
  display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.sec__d{font-size:12.5px;color:var(--mut);margin-bottom:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}
label.f{display:flex;flex-direction:column;gap:5px;font-size:12.5px;font-weight:700;color:var(--mut2)}
input,select,textarea{padding:9px 12px;border:1px solid var(--bd2);border-radius:9px;
  font-size:14px;font-family:inherit;background:#fff;width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(37,99,235,.12)}
textarea{resize:vertical;min-height:86px;line-height:1.7}
.ckrow{display:flex;gap:8px;flex-wrap:wrap}
.ck{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border:1px solid var(--bd2);
  border-radius:8px;background:#fff;cursor:pointer;font-size:13px}
.ck input{width:auto;margin:0;accent-color:var(--brand)}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th,.tbl td{border:1px solid var(--bd);padding:6px;text-align:left}
.tbl th{background:#f8fafc;font-size:12px;font-weight:700;color:var(--mut2);text-align:center}
.tbl input{padding:6px 8px;font-size:13px}
.tblwrap{overflow-x:auto}
.save{position:sticky;bottom:0;background:rgba(245,247,251,.95);backdrop-filter:blur(8px);
  border-top:1px solid var(--bd);padding:12px 0;margin-top:20px;display:flex;gap:9px;flex-wrap:wrap}
@media(max-width:600px){.nav__in{height:auto;padding:10px 16px}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn" href="/jawi_chat.php?id=<?=h(rawurlencode($id))?>">💬 문답으로</a>
      <a class="btn" href="/jawi_print.php?id=<?=h(rawurlencode($id))?>">🖨 인쇄</a>
      <a class="btn" href="/jawi.php?stay=1">← 목록</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <h1>자위소방대 교육·훈련 기록부</h1>
  <p class="lead">별지 제13호서식 · 빈칸은 인쇄할 때 비어서 나옵니다.</p>

  <?php if (isset($_GET['saved'])): ?>
    <div class="toast">✓ 저장했습니다.</div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="csrf" value="<?=h(jw_csrf())?>">

    <!-- 1 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">1</span> 작성 정보</div>
      <div class="grid">
        <label class="f">작성일자<input type="date" name="write_date" value="<?=$v('write_date')?>"></label>
        <label class="f">작성자<input name="writer" value="<?=$v('writer')?>" placeholder="홍길동"></label>
      </div>
    </section>

    <!-- 2 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">2</span> 소방안전관리대상물</div>
      <div class="sec__d">건물 기본정보에서 자동으로 채워집니다. 다르면 여기서 고치세요.</div>
      <div class="grid">
        <label class="f">대상명<input name="site_name" value="<?=$v('site_name')?>"></label>
        <label class="f">대표자<input name="rep" value="<?=$v('rep')?>"></label>
        <label class="f">소재지<input name="address" value="<?=$v('address')?>"></label>
        <label class="f">전화번호<input name="tel" value="<?=$v('tel')?>"></label>
      </div>
      <div class="sec__d" style="margin:14px 0 6px">근무인원</div>
      <div class="grid">
        <label class="f">평일 주간<input name="wd_day" value="<?=$v('wd_day')?>" placeholder="00:00~00:00"></label>
        <label class="f">평일 야간<input name="wd_night" value="<?=$v('wd_night')?>"></label>
        <label class="f">휴일 주간<input name="hd_day" value="<?=$v('hd_day')?>"></label>
        <label class="f">휴일 야간<input name="hd_night" value="<?=$v('hd_night')?>"></label>
      </div>
      <div class="sec__d" style="margin:14px 0 6px">등급</div>
      <div class="ckrow">
        <?php foreach (['특급','1급','2급','3급'] as $g): ?>
          <label class="ck"><input type="radio" name="grade" value="<?=$g?>"
            <?= ($d['grade'] ?? '') === $g ? 'checked' : '' ?>><span><?=$g?></span></label>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 3 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">3</span> 소방안전관리자</div>
      <div class="tblwrap">
        <table class="tbl">
          <tr><th>성명</th><th>선임일자</th><th>보유자격</th><th style="width:120px">자격구분</th><th>연락처</th><th>비고</th></tr>
          <?php foreach (array_slice($mgrs, 0, 4) as $i => $m): $ty = (string)($m['type'] ?? ''); ?>
            <tr>
              <td><input name="m_name[]" value="<?=h((string)($m['name'] ?? ''))?>"></td>
              <td><input name="m_appt[]" value="<?=h((string)($m['appt'] ?? ''))?>" placeholder="2025-03-02"></td>
              <td><input name="m_qual[]" value="<?=h((string)($m['qual'] ?? ''))?>"></td>
              <td>
                <select name="m_type[]">
                  <option value="">-</option>
                  <option value="주"   <?= $ty === '주'   ? 'selected' : '' ?>>주</option>
                  <option value="보조" <?= $ty === '보조' ? 'selected' : '' ?>>보조</option>
                </select>
              </td>
              <td><input name="m_tel[]" value="<?=h((string)($m['tel'] ?? ''))?>"></td>
              <td><input name="m_note[]" value="<?=h((string)($m['note'] ?? ''))?>"></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </section>

    <!-- 4 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">4</span> 자위소방대</div>
      <div class="grid">
        <label class="f">총원(명)<input name="jawi_total" inputmode="numeric" value="<?=$v('jawi_total')?>"></label>
        <label class="f">대장 성명<input name="jawi_chief" value="<?=$v('jawi_chief')?>"></label>
        <label class="f">대장 연락처<input name="jawi_chief_tel" value="<?=$v('jawi_chief_tel')?>"></label>
        <label class="f">부대장(명)<input name="jawi_vice" inputmode="numeric" value="<?=$v('jawi_vice')?>"></label>
        <label class="f">통보연락(명)<input name="jawi_call" inputmode="numeric" value="<?=$v('jawi_call')?>"></label>
        <label class="f">초기소화(명)<input name="jawi_fire" inputmode="numeric" value="<?=$v('jawi_fire')?>"></label>
        <label class="f">피난유도(명)<input name="jawi_guide" inputmode="numeric" value="<?=$v('jawi_guide')?>"></label>
        <label class="f">비상연락(명)<input name="jawi_emer" inputmode="numeric" value="<?=$v('jawi_emer')?>"></label>
      </div>
    </section>

    <!-- 5 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">5</span> 초기대응체계</div>
      <div class="grid">
        <label class="f" style="grid-column:1/-1">조직구성
          <input name="init_org" value="<?=$v('init_org')?>" placeholder="방재실 근무자 중심으로 상시 편성"></label>
        <label class="f">총원(명)<input name="init_total" inputmode="numeric" value="<?=$v('init_total')?>"></label>
      </div>
    </section>

    <!-- 6 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">6</span> 교육 · 훈련 결과</div>
      <div class="grid">
        <label class="f">실시 일시<input name="edu_date" value="<?=$v('edu_date')?>" placeholder="2026-06-15 14:00"></label>
        <label class="f">실시 장소<input name="edu_place" value="<?=$v('edu_place')?>"></label>
      </div>
      <div class="sec__d" style="margin:14px 0 6px">참석 인원</div>
      <div class="grid">
        <label class="f">자위소방대 참석<input name="jawi_join" inputmode="numeric" value="<?=$v('jawi_join')?>"></label>
        <label class="f">자위소방대 미참석<input name="jawi_absent" inputmode="numeric" value="<?=$v('jawi_absent')?>"></label>
        <label class="f">초기대응 참석<input name="init_join" inputmode="numeric" value="<?=$v('init_join')?>"></label>
        <label class="f">초기대응 미참석<input name="init_absent" inputmode="numeric" value="<?=$v('init_absent')?>"></label>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;margin-top:14px">
        <label class="f">주요내용<textarea name="edu_content"><?=$v('edu_content')?></textarea></label>
        <label class="f">보완사항<textarea name="edu_fix"><?=$v('edu_fix')?></textarea></label>
        <label class="f">조치사항<textarea name="edu_action"><?=$v('edu_action')?></textarea></label>
      </div>
    </section>

    <!-- 7 -->
    <section class="sec">
      <div class="sec__t"><span class="sec__n">7</span> 교육·훈련 참석확인</div>
      <div class="sec__d">한 줄에 한 명씩, <b>직책 성명</b> 순으로 적어주세요. 최대 50명까지 서식 뒤쪽에 들어갑니다.</div>
      <textarea name="attend_text" rows="8"
        placeholder="지휘조 김철수&#10;비상연락조 이영희&#10;초기소화조 박민수"><?=h(rtrim($attTxt))?></textarea>
    </section>

    <div class="save">
      <button class="btn btn--pri" type="submit">저장</button>
      <a class="btn" href="/jawi_print.php?id=<?=h(rawurlencode($id))?>">🖨 인쇄 · PDF</a>
      <a class="btn" href="/jawi_chat.php?id=<?=h(rawurlencode($id))?>">💬 문답으로 채우기</a>
    </div>
  </form>
</main>

</body>
</html>
