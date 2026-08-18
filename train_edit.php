<?php
// train_edit.php — 소방훈련·교육 실시 결과 기록부 (별지 제28호서식) 작성
declare(strict_types=1);

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

require_once __DIR__ . '/train_db.php';
/* 훈련내용 칸에 넣을 예시 문구 — 타자 대신 눌러서 채운다 */
const TRI_SAMPLES = [
  '소화훈련' => [
    '소화기 사용법 교육 및 실습(안전핀 제거 → 노즐 조준 → 손잡이 압착)',
    '옥내소화전 전개 및 방수 실습',
    '주방 자동소화장치 작동 상태 확인 및 설명',
  ],
  '통보훈련' => [
    '화재 발견자 → 방재실 통보 → 119 신고 순서 숙지',
    '비상방송설비를 이용한 전관 방송 실시',
    '입주사 비상연락망을 통한 상황 전파 훈련',
  ],
  '피난훈련' => [
    '층별 피난경로를 따라 지정 집결지까지 대피',
    '피난유도등·유도표지 확인 후 계단 이용 대피',
    '대피 완료 후 층별 인원 점검 및 미대피자 확인',
  ],
];
const EDU_SAMPLES = [
  '소화기 등 소방시설의 위치와 사용법',
  '화재 발생 시 신고 요령과 초기 대응 절차',
  '피난경로 및 비상구 위치, 대피 시 유의사항',
  '자위소방대 편성과 각자의 임무',
];
require_once __DIR__ . '/building_info.php';
$bi = bi_load();

$id = (string)($_GET['id'] ?? '');
$rec = tr_load($id);
if (!$rec) { header('Location: /train.php'); exit; }

/* 저장 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  tr_csrf_check();
  $chk = function(string $k): array {
    $v = $_POST[$k] ?? [];
    return is_array($v) ? array_values(array_map('strval',$v)) : [];
  };
  $t = fn(string $k) => trim((string)($_POST[$k] ?? ''));

  // 소방안전관리자 최대 4명 (성명·선임일·자격·구분(주/보조)·연락처)
  $mgrs = [];
  for ($i=0; $i<4; $i++) {
    $nm = trim((string)($_POST["m_name"][$i] ?? ''));
    if ($nm === '' && trim((string)($_POST["m_tel"][$i] ?? '')) === '') continue;
    $mgrs[] = [
      'name' => $nm,
      'appt' => trim((string)($_POST["m_appt"][$i] ?? '')),
      'qual' => trim((string)($_POST["m_qual"][$i] ?? '')),
      'type' => trim((string)($_POST["m_type"][$i] ?? '')),   // 주 / 보조
      'tel'  => trim((string)($_POST["m_tel"][$i] ?? '')),
    ];
  }

  $data = [
    /* 대상물 */
    't_name'=>$t('t_name'),'t_use'=>$t('t_use'),'t_rep'=>$t('t_rep'),'t_tel'=>$t('t_tel'),
    't_addr'=>$t('t_addr'),'t_grade'=>$t('t_grade'),
    'mgrs'=>$mgrs,
    /* 소방훈련 */
    'fire_date'=>$t('fire_date'),'fire_place'=>$t('fire_place'),'fire_kind'=>$t('fire_kind'),
    'fire_teacher'=>$t('fire_teacher'),'fire_target'=>$t('fire_target'),'fire_join'=>$t('fire_join'),'fire_absent'=>$t('fire_absent'),
    'fire_material'=>$t('fire_material'),
    'fire_types'=>$chk('fire_types'),   // 소화·통보·피난
    /* 서식대로 소화·통보·피난 세 칸을 따로 적는다 */
    'fire_c_sohwa'=>$t('fire_c_sohwa'),'fire_c_tongbo'=>$t('fire_c_tongbo'),'fire_c_pinan'=>$t('fire_c_pinan'),
    'fire_content'=>$t('fire_content'),'fire_result'=>$t('fire_result'),
    'fire_problem'=>$t('fire_problem'),'fire_improve'=>$t('fire_improve'),
    /* 소방교육 */
    'edu_date'=>$t('edu_date'),'edu_place'=>$t('edu_place'),
    'edu_teacher'=>$t('edu_teacher'),'edu_target'=>$t('edu_target'),'edu_join'=>$t('edu_join'),'edu_absent'=>$t('edu_absent'),
    'edu_content'=>$t('edu_content'),'edu_result'=>$t('edu_result'),
    'edu_problem'=>$t('edu_problem'),'edu_improve'=>$t('edu_improve'),
  ];
  /* 사진 4장 — 새로 올리면 교체하고, [사진 지우기]를 누르면 삭제한다 */
  $photoErr = '';
  $oldPhotos = ($rec['data']['photos'] ?? []);
  $photos = [];
  foreach (['fire1','fire2','edu1','edu2'] as $pk) {
    $cur = (string)($oldPhotos[$pk] ?? '');
    if (!empty($_POST['photo_del'][$pk])) { tr_photo_delete($cur); $cur = ''; }
    [$new, $err] = tr_photo_save($_FILES['photo_' . $pk] ?? null);
    if ($err !== '') { $photoErr = $err; }
    elseif ($new !== '') { if ($cur !== '') tr_photo_delete($cur); $cur = $new; }
    $photos[$pk] = $cur;
  }
  $data['photos'] = $photos;

  tr_save($id, $data);
  header('Location: /train_edit.php?id=' . urlencode($id) . '&saved=1' . ($photoErr !== '' ? '&perr=' . rawurlencode($photoErr) : ''));
  exit;
}

$d = $rec['data'] ?? [];

/* 아직 저장 전이면 건물 기본정보로 미리 채움 */
if (!$d) {
  $d = [
    't_name'  => $bi['name'],
    't_use'   => $bi['use'],
    't_rep'   => $bi['rep'],
    't_tel'   => $bi['tel'],
    't_addr'  => $bi['address'],
    't_grade' => $bi['grade'],
    'mgrs'    => $bi['mgrs'],
  ];
}
$mgrs = $d['mgrs'] ?? [];
$nick = $_SESSION['nickname'] ?? '사용자';
$fireTypes = $d['fire_types'] ?? [];

/* 값 헬퍼 */
$v  = fn(string $k) => h($d[$k] ?? '');
$mv = fn(int $i, string $k) => h($mgrs[$i][$k] ?? '');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>소방훈련·교육 실시 결과 기록부 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:960px;margin:0 auto;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.wrap{max-width:960px;margin:0 auto;padding:26px 20px 90px}
.head{margin-bottom:18px}
.head .law{font-size:12px;color:var(--accent);font-weight:700;letter-spacing:.04em}
.head h1{font-size:22px;font-weight:700;margin-top:3px}
.head p{color:var(--mut2);font-size:13.5px;margin-top:4px}
.saved{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:10px 14px;border-radius:9px;font-size:13.5px;margin-bottom:16px}

.sheet{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px;margin-bottom:18px}
.sheet__t{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:700;
  padding-bottom:12px;margin-bottom:16px;border-bottom:2px solid var(--fg)}
.sheet__t .n{width:24px;height:24px;border-radius:6px;background:var(--fg);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:12px}

table.ft{width:100%;border-collapse:collapse;font-size:13px}
table.ft th,table.ft td{border:1px solid var(--bd2);padding:7px 9px;vertical-align:middle}
table.ft th{background:#f2f5fa;font-weight:700;white-space:nowrap;width:96px;text-align:center;font-size:12.5px}
table.ft input[type=text],table.ft input[type=date],table.ft input[type=number]{
  width:100%;padding:7px 8px;border:1px solid var(--bd2);border-radius:6px;font-size:13px;font-family:inherit;background:#fff}
table.ft input:focus,table.ft textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 2px rgba(37,99,235,.1)}
table.ft textarea{width:100%;padding:8px 9px;border:1px solid var(--bd2);border-radius:6px;font-size:13px;font-family:inherit;resize:vertical;min-height:60px}
.seg{display:inline-flex;gap:0;border:1px solid var(--bd2);border-radius:7px;overflow:hidden}
.seg label{padding:6px 13px;font-size:12.5px;cursor:pointer;background:#fff;border-right:1px solid var(--bd2)}
.seg label:last-child{border-right:0}
.seg input{display:none}
.seg label.on{background:#eef4ff;color:var(--brand2);font-weight:700}
.ckrow{display:flex;flex-wrap:wrap;gap:8px}
.ck{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border:1px solid var(--bd2);border-radius:7px;background:#fff;cursor:pointer;font-size:12.5px}
.ck.on{background:#eef4ff;border-color:var(--brand);color:var(--brand2);font-weight:700}
.ck input{margin:0;accent-color:var(--brand)}
.two{display:flex;gap:6px}.two>*{flex:1}
.mini{font-size:12px;color:var(--mut2);font-weight:600;white-space:nowrap}
.mgr-row td{padding:5px 6px}
.mgr-row input{font-size:12.5px}
.mgr-type{display:flex;gap:4px}
.mgr-type label{padding:5px 8px;font-size:11.5px;border:1px solid var(--bd2);border-radius:6px;cursor:pointer;background:#fff}
.mgr-type label.on{background:#eef4ff;border-color:var(--brand);color:var(--brand2);font-weight:700}
.mgr-type input{display:none}
.w110{width:120px}
.savebar{position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);
  border-top:1px solid var(--bd);padding:12px 20px;display:flex;justify-content:center;gap:10px;z-index:40}
.savebar .btn{padding:11px 26px;font-size:14px}
@media(max-width:720px){
  table.ft,table.ft tbody,table.ft tr,table.ft td,table.ft th{display:block;width:auto !important}
  table.ft tr{margin-bottom:8px;border:1px solid var(--bd2);border-radius:8px;overflow:hidden}
  table.ft th{text-align:left;border:0;border-bottom:1px solid var(--bd)}
  table.ft td{border:0;border-bottom:1px solid #eef1f6}
}
/* 훈련내용 3칸 (서식과 같은 구성) */
.tri{padding:0 !important}
.tricol{display:grid;grid-template-columns:repeat(3,1fr)}
.tri__cell{padding:10px 12px;border-right:1px solid var(--bd)}
.tri__cell:last-child{border-right:0}
.tri__cell.is-on{background:#f7faff}
.ck--head{margin-bottom:7px}
.tri__cell textarea{min-height:96px}
.tri__ex{display:flex;flex-direction:column;gap:4px;margin-top:6px}
.tri__ex--wide{flex-direction:row;flex-wrap:wrap;margin:0 0 7px}
.exbtn{text-align:left;border:1px solid var(--bd2);background:#fff;border-radius:6px;
  padding:5px 9px;font-size:11.5px;color:var(--mut2);cursor:pointer;font-family:inherit;line-height:1.45}
.exbtn:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
@media(max-width:760px){
  .tricol{grid-template-columns:1fr}
  .tri__cell{border-right:0;border-bottom:1px solid var(--bd)}
  .tri__cell:last-child{border-bottom:0}
}

/* 일시 — 눌러서 고르기 */
.dt{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.dt__d,.dt__t{padding:7px 9px;border:1px solid var(--bd2);border-radius:7px;
  font-size:13px;font-family:inherit;background:#fff;cursor:pointer}
.dt__d:focus,.dt__t:focus{outline:none;border-color:var(--brand)}
.dt__v{font-size:12.5px;color:var(--brand2);font-weight:700}

/* 인원 — 눌러서 올리고 내리기 */
.nstep{display:inline-flex;align-items:center;gap:4px;margin-right:12px}
.nbtn{width:28px;height:28px;border:1px solid var(--bd2);background:#fff;border-radius:7px;
  font-size:15px;line-height:1;cursor:pointer;font-family:inherit;color:var(--mut2)}
.nbtn:hover{border-color:var(--brand);color:var(--brand2)}
.ncell{width:60px;text-align:center;padding:6px 4px;border:1px solid var(--bd2);
  border-radius:7px;font-size:13.5px;font-family:inherit}
.ncell--auto{background:#f1f5f9;color:var(--mut2)}
.nauto{font-size:10.5px;color:var(--mut);font-weight:700}

/* 사진 */
.phelp{font-size:12.5px;color:var(--mut2);margin:-4px 0 12px;line-height:1.6}
.pgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.pslot{display:flex;flex-direction:column;gap:6px}
.pslot__t{font-size:12.5px;font-weight:700;color:var(--mut2)}
.pdrop{display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;
  border:1.5px dashed var(--bd2);border-radius:10px;background:#f8fafc;cursor:pointer;
  overflow:hidden;transition:.15s}
.pdrop:hover{border-color:var(--brand);background:#f7faff}
.pdrop img{width:100%;height:100%;object-fit:cover}
.pdrop__ph{font-size:12.5px;color:var(--mut);text-align:center;line-height:1.8}
.pfile{display:none}
.pdel{font-size:12px;color:#b45309;display:flex;align-items:center;gap:5px;cursor:pointer}</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div style="display:flex;gap:8px">
      <a class="btn" href="/train.php">← 목록</a>
      <a class="btn" href="/train_print.php?id=<?=h($id)?>">🖨️ 인쇄/PDF</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <div class="head">
    <div class="law">화재예방법 시행규칙 별지 제28호서식</div>
    <h1>소방훈련·교육 실시 결과 기록부</h1>
    <p>실시한 소방훈련과 교육의 결과를 기록합니다. 작성 후 <b>2년간 보관</b>해야 합니다.</p>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="saved">✅ 저장되었습니다.</div>
  <?php endif; ?>

  <?php if (!empty($_GET['perr'])): ?>
  <div class="toast" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">✕ <?=h($_GET['perr'])?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(tr_csrf())?>">

    <!-- ① 대상물 -->
    <section class="sheet">
      <div class="sheet__t"><span class="n">1</span> 소방안전관리대상물</div>
      <table class="ft">
        <tr><th>대상명</th><td><input type="text" name="t_name" value="<?=$v('t_name')?>" placeholder="예: 투릭스빌딩"></td>
            <th>용도</th><td><input type="text" name="t_use" value="<?=$v('t_use')?>" placeholder="예: 근린생활시설"></td></tr>
        <tr><th>대표자</th><td><input type="text" name="t_rep" value="<?=$v('t_rep')?>"></td>
            <th>전화번호</th><td><input type="text" name="t_tel" value="<?=$v('t_tel')?>" placeholder="010-0000-0000"></td></tr>
        <tr><th>주소</th><td colspan="3"><input type="text" name="t_addr" value="<?=$v('t_addr')?>"></td></tr>
        <tr><th>등급</th><td colspan="3">
          <div class="seg" data-seg="t_grade">
            <?php foreach (['특급','1급','2급','3급'] as $g): $on=($d['t_grade']??'')===$g; ?>
              <label class="<?=$on?'on':''?>"><input type="radio" name="t_grade" value="<?=$g?>" <?=$on?'checked':''?>><?=$g?></label>
            <?php endforeach; ?>
          </div>
        </td></tr>
      </table>
    </section>

    <!-- ② 소방안전관리자 -->
    <section class="sheet">
      <div class="sheet__t"><span class="n">2</span> 소방안전관리자</div>
      <table class="ft">
        <tr>
          <th style="width:auto">성명</th><th style="width:auto">선임일자</th>
          <th style="width:auto">보유자격</th><th style="width:90px">구분</th>
          <th style="width:auto">연락처</th>
        </tr>
        <?php for ($i=0;$i<4;$i++): $ty=$mgrs[$i]['type']??''; ?>
        <tr class="mgr-row">
          <td><input type="text" name="m_name[]" value="<?=$mv($i,'name')?>"></td>
          <td><input type="text" name="m_appt[]" value="<?=$mv($i,'appt')?>" placeholder="YYYY-MM-DD"></td>
          <td><input type="text" name="m_qual[]" value="<?=$mv($i,'qual')?>" placeholder="예: 2급"></td>
          <td>
            <div class="mgr-type" data-mtype="<?=$i?>">
              <label class="<?=$ty==='주'?'on':''?>"><input type="radio" name="m_type[<?=$i?>]" value="주" <?=$ty==='주'?'checked':''?>>주</label>
              <label class="<?=$ty==='보조'?'on':''?>"><input type="radio" name="m_type[<?=$i?>]" value="보조" <?=$ty==='보조'?'checked':''?>>보조</label>
            </div>
          </td>
          <td><input type="text" name="m_tel[]" value="<?=$mv($i,'tel')?>"></td>
        </tr>
        <?php endfor; ?>
      </table>
    </section>

    <!-- ③ 소방훈련 결과 -->
    <section class="sheet">
      <div class="sheet__t"><span class="n">3</span> 소방훈련 결과</div>
      <table class="ft">
        <tr><th>일시</th><td><div class="dt"><input type="date" class="dt__d" data-t="fire_date"><input type="time" class="dt__t" data-t="fire_date" step="300"><input type="hidden" name="fire_date" id="fire_date" value="<?=$v('fire_date')?>"><span class="dt__v" id="fire_date_v"><?=$v('fire_date')?></span></div></td>
            <th>장소</th><td><input type="text" name="fire_place" value="<?=$v('fire_place')?>"></td></tr>
        <tr><th>구분</th><td colspan="3">
          <div class="seg" data-seg="fire_kind">
            <?php foreach (['자체훈련','합동훈련'] as $k): $on=($d['fire_kind']??'')===$k; ?>
              <label class="<?=$on?'on':''?>"><input type="radio" name="fire_kind" value="<?=$k?>" <?=$on?'checked':''?>><?=$k?></label>
            <?php endforeach; ?>
          </div>
        </td></tr>
        <tr><th>훈련교관</th><td><input type="text" name="fire_teacher" value="<?=$v('fire_teacher')?>"></td>
            <th>참석현황</th><td>
              <div class="ckrow" style="align-items:center;gap:8px">
                <div class="nstep"><span class="mini">대상</span><button type="button" class="nbtn" data-n="fire_target" data-v="-1">−</button><input type="number" name="fire_target" id="fire_target" class="ncell" min="0" value="<?=$v('fire_target')?>"><button type="button" class="nbtn" data-n="fire_target" data-v="1">＋</button></div><div class="nstep"><span class="mini">참석</span><button type="button" class="nbtn" data-n="fire_join" data-v="-1">−</button><input type="number" name="fire_join" id="fire_join" class="ncell" min="0" value="<?=$v('fire_join')?>"><button type="button" class="nbtn" data-n="fire_join" data-v="1">＋</button></div><div class="nstep"><span class="mini">미참석</span><input type="number" name="fire_absent" id="fire_absent" class="ncell ncell--auto" min="0" value="<?=$v('fire_absent')?>" readonly><span class="nauto">자동</span></div>
              </div>
            </td></tr>
        <tr><th>훈련보조<br>재료</th><td colspan="3"><input type="text" name="fire_material" value="<?=$v('fire_material')?>" placeholder="예: 소화기, 소화전, 방연마스크"></td></tr>
        <tr><th>훈련내용</th><td colspan="3" class="tri">
          <div class="tricol">
            <?php
              $triFields = ['소화훈련'=>'fire_c_sohwa', '통보훈련'=>'fire_c_tongbo', '피난훈련'=>'fire_c_pinan'];
              $triHint = [
                '소화훈련'  => '소화기·옥내소화전 사용법 실습 등',
                '통보훈련'  => '119 신고, 비상방송, 관계인 연락 등',
                '피난훈련'  => '피난경로 안내, 대피 후 인원 확인 등',
              ];
              foreach ($triFields as $label => $fname):
                $on = in_array($label, $fireTypes, true);
            ?>
              <div class="tri__cell <?=$on?'is-on':''?>" data-tri="<?=h($fname)?>">
                <label class="ck ck--head <?=$on?'on':''?>">
                  <input type="checkbox" name="fire_types[]" value="<?=$label?>" <?=$on?'checked':''?>>
                  <span><?=$label?></span>
                </label>
                <textarea name="<?=h($fname)?>" placeholder="<?=h($triHint[$label])?>"><?=$v($fname)?></textarea>
                <div class="tri__ex">
                  <?php foreach ((TRI_SAMPLES[$label] ?? []) as $sm): ?>
                    <button type="button" class="exbtn" data-for="<?=h($fname)?>"><?=h($sm)?></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </td></tr>
        <tr><th>훈련성과</th><td colspan="3"><textarea name="fire_result"><?=$v('fire_result')?></textarea></td></tr>
        <tr><th>문제점</th><td colspan="3"><textarea name="fire_problem"><?=$v('fire_problem')?></textarea></td></tr>
        <tr><th>개선계획</th><td colspan="3"><textarea name="fire_improve"><?=$v('fire_improve')?></textarea></td></tr>
      </table>
    </section>

    <!-- ④ 소방교육 결과 -->
    <section class="sheet">
      <div class="sheet__t"><span class="n">4</span> 소방교육 결과</div>
      <table class="ft">
        <tr><th>일시</th><td><div class="dt"><input type="date" class="dt__d" data-t="edu_date"><input type="time" class="dt__t" data-t="edu_date" step="300"><input type="hidden" name="edu_date" id="edu_date" value="<?=$v('edu_date')?>"><span class="dt__v" id="edu_date_v"><?=$v('edu_date')?></span></div></td>
            <th>장소</th><td><input type="text" name="edu_place" value="<?=$v('edu_place')?>"></td></tr>
        <tr><th>교육강사</th><td><input type="text" name="edu_teacher" value="<?=$v('edu_teacher')?>"></td>
            <th>참석현황</th><td>
              <div class="ckrow" style="align-items:center;gap:8px">
                <div class="nstep"><span class="mini">대상</span><button type="button" class="nbtn" data-n="edu_target" data-v="-1">−</button><input type="number" name="edu_target" id="edu_target" class="ncell" min="0" value="<?=$v('edu_target')?>"><button type="button" class="nbtn" data-n="edu_target" data-v="1">＋</button></div><div class="nstep"><span class="mini">참석</span><button type="button" class="nbtn" data-n="edu_join" data-v="-1">−</button><input type="number" name="edu_join" id="edu_join" class="ncell" min="0" value="<?=$v('edu_join')?>"><button type="button" class="nbtn" data-n="edu_join" data-v="1">＋</button></div><div class="nstep"><span class="mini">미참석</span><input type="number" name="edu_absent" id="edu_absent" class="ncell ncell--auto" min="0" value="<?=$v('edu_absent')?>" readonly><span class="nauto">자동</span></div>
              </div>
            </td></tr>
        <tr><th>교육내용</th><td colspan="3"><div class="tri__ex tri__ex--wide"><?php foreach (EDU_SAMPLES as $sm): ?><button type="button" class="exbtn" data-for="edu_content"><?=h($sm)?></button><?php endforeach; ?></div><textarea id="edu_content" name="edu_content"><?=$v('edu_content')?></textarea></td></tr>
        <tr><th>교육성과</th><td colspan="3"><textarea name="edu_result"><?=$v('edu_result')?></textarea></td></tr>
        <tr><th>문제점</th><td colspan="3"><textarea name="edu_problem"><?=$v('edu_problem')?></textarea></td></tr>
        <tr><th>개선계획</th><td colspan="3"><textarea name="edu_improve"><?=$v('edu_improve')?></textarea></td></tr>
      </table>
    </section>
    <!-- ⑤ 소방훈련·교육 관련사진 -->
    <section class="sheet">
      <div class="sheet__t"><span class="n">5</span> 소방훈련·교육 관련사진</div>
      <div class="phelp">서식 뒤쪽에 들어갈 사진입니다. 훈련 2장, 교육 2장까지 올릴 수 있습니다.
        휴대폰으로 찍은 사진을 그대로 올리시면 됩니다.</div>
      <div class="pgrid">
        <?php
          $photos = $d['photos'] ?? [];
          $pLabels = ['fire1'=>'소방훈련 ①','fire2'=>'소방훈련 ②','edu1'=>'소방교육 ①','edu2'=>'소방교육 ②'];
          foreach ($pLabels as $pk => $plabel):
            $cur = (string)($photos[$pk] ?? '');
            $url = $cur !== '' ? tr_photo_url($cur) : '';
        ?>
          <div class="pslot">
            <div class="pslot__t"><?=h($plabel)?></div>
            <label class="pdrop" for="photo_<?=h($pk)?>">
              <?php if ($url !== ''): ?>
                <img src="<?=h($url)?>" alt="<?=h($plabel)?>">
              <?php else: ?>
                <span class="pdrop__ph">📷<br>눌러서 사진 넣기</span>
              <?php endif; ?>
            </label>
            <input type="file" id="photo_<?=h($pk)?>" name="photo_<?=h($pk)?>"
                   accept="image/*" class="pfile" data-slot="<?=h($pk)?>">
            <?php if ($url !== ''): ?>
              <label class="pdel"><input type="checkbox" name="photo_del[<?=h($pk)?>]" value="1"> 이 사진 지우기</label>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </form>
</main>

<div class="savebar">
  <a class="btn" href="/train.php">취소</a>
  <button class="btn btn--primary" onclick="document.querySelector('form').submit()">저장</button>
</div>

<script>
// 세그먼트 (라디오) 토글
document.querySelectorAll('.seg').forEach(seg=>{
  seg.querySelectorAll('label').forEach(lb=>{
    lb.addEventListener('click',()=>{
      seg.querySelectorAll('label').forEach(x=>x.classList.remove('on'));
      lb.classList.add('on');
    });
  });
});
// 관리자 주/보조 토글
document.querySelectorAll('.mgr-type').forEach(mt=>{
  mt.querySelectorAll('label').forEach(lb=>{
    lb.addEventListener('click',()=>{
      mt.querySelectorAll('label').forEach(x=>x.classList.remove('on'));
      lb.classList.add('on');
    });
  });
});
// 체크박스 토글
document.querySelectorAll('.ck input[type=checkbox]').forEach(cb=>{
  cb.addEventListener('change',()=>cb.closest('.ck').classList.toggle('on',cb.checked));
});
</script>
<script>
(function(){
  /* ── 일시: 날짜·시간 고르면 하나로 합친다 ── */
  function syncDT(key){
    var d = document.querySelector('.dt__d[data-t="'+key+'"]');
    var t = document.querySelector('.dt__t[data-t="'+key+'"]');
    var hid = document.getElementById(key);
    var out = document.getElementById(key+'_v');
    if(!d||!t||!hid) return;
    var v = (d.value||'') + (t.value ? ' ' + t.value : '');
    hid.value = v.trim();
    if(out) out.textContent = hid.value;
  }
  ['fire_date','edu_date'].forEach(function(key){
    var hid = document.getElementById(key);
    var d = document.querySelector('.dt__d[data-t="'+key+'"]');
    var t = document.querySelector('.dt__t[data-t="'+key+'"]');
    if(!hid||!d||!t) return;
    /* 이미 저장된 값이 있으면 나눠서 채워둔다 */
    var m = /^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}:\d{2}))?/.exec(hid.value||'');
    if(m){ d.value = m[1]; if(m[2]) t.value = m[2]; }
    d.addEventListener('change', function(){ syncDT(key); });
    t.addEventListener('change', function(){ syncDT(key); });
  });

  /* ── 인원: 버튼으로 올리고 내리기, 미참석은 자동 ── */
  function recalc(p){
    var tg = document.getElementById(p+'_target');
    var jn = document.getElementById(p+'_join');
    var ab = document.getElementById(p+'_absent');
    if(!tg||!jn||!ab) return;
    var a = parseInt(tg.value||'0',10)||0, b = parseInt(jn.value||'0',10)||0;
    ab.value = Math.max(0, a-b);
  }
  document.addEventListener('click', function(e){
    var b = e.target.closest('.nbtn');
    if(!b) return;
    var el = document.getElementById(b.getAttribute('data-n'));
    if(!el) return;
    var cur = parseInt(el.value||'0',10)||0;
    el.value = Math.max(0, cur + parseInt(b.getAttribute('data-v'),10));
    recalc(b.getAttribute('data-n').split('_')[0]);
  });
  ['fire','edu'].forEach(function(p){
    ['_target','_join'].forEach(function(sfx){
      var el = document.getElementById(p+sfx);
      if(el) el.addEventListener('input', function(){ recalc(p); });
    });
  });

  /* ── 예시 문구: 눌러서 넣기 ── */
  document.addEventListener('click', function(e){
    var b = e.target.closest('.exbtn');
    if(!b) return;
    var el = document.getElementsByName(b.getAttribute('data-for'))[0]
          || document.getElementById(b.getAttribute('data-for'));
    if(!el) return;
    var cur = (el.value||'').trim();
    el.value = cur === '' ? b.textContent : cur + '\n' + b.textContent;
    el.focus();
    /* 예시를 넣으면 그 훈련을 했다는 뜻이니 체크도 같이 켠다 */
    var cell = b.closest('.tri__cell');
    if(cell){
      var cb = cell.querySelector('input[type=checkbox]');
      if(cb && !cb.checked){ cb.checked = true; cell.classList.add('is-on');
        cb.closest('.ck').classList.add('on'); }
    }
  });

  /* ── 체크 표시 켜고 끄기 ── */
  document.addEventListener('change', function(e){
    var cb = e.target;
    if(cb.type !== 'checkbox') return;
    var lb = cb.closest('.ck'); if(lb) lb.classList.toggle('on', cb.checked);
    var cell = cb.closest('.tri__cell'); if(cell) cell.classList.toggle('is-on', cb.checked);
  });

  /* ── 사진: 고르면 바로 보여주기 ── */
  document.addEventListener('change', function(e){
    var inp = e.target;
    if(!inp.classList || !inp.classList.contains('pfile')) return;
    var file = inp.files && inp.files[0];
    if(!file) return;
    var slot = inp.closest('.pslot');
    var drop = slot && slot.querySelector('.pdrop');
    if(!drop) return;
    var r = new FileReader();
    r.onload = function(ev){ drop.innerHTML = '<img alt="">'; drop.querySelector('img').src = ev.target.result; };
    r.readAsDataURL(file);
    var del = slot.querySelector('.pdel input');
    if(del) del.checked = false;
  });
})();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
