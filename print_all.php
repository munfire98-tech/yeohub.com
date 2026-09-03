<?php
/* =============================================================
   print_all.php — 전체 서류 인쇄 모음
   ─────────────────────────────────────────────────────────────
   업무수행 기록표 · 소방계획서 · 자위소방대 편성표 · 훈련교육 기록을
   한 화면에 모아 보여주고, 원하는 것을 골라 인쇄합니다.

   각 서식은 저마다 인쇄 페이지가 따로 있습니다(형식이 서로 다르므로
   억지로 한 장에 합치지 않고, 여기서 모아 열어주는 방식입니다).
     · 업무수행 기록표 : work_log_print.php?year=YYYY  (연 단위 묶음)
     · 소방계획서     : fire_plan_print.php?id=...
     · 자위소방대     : jawi_print.php?id=...
     · 훈련·교육      : train_print.php?id=...
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
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

require_once __DIR__ . '/user_key.php';
$uidKey = app_user_key();
if ($uidKey === '') { die('<meta charset="utf-8">' . app_user_key_notice()); }

/* 각 서식 모듈을 불러옵니다. 없더라도 페이지가 죽지 않게 안전하게 처리합니다. */
@require_once __DIR__ . '/building_info.php';
@require_once __DIR__ . '/fire_plan_db.php';
@require_once __DIR__ . '/jawi_db.php';
@require_once __DIR__ . '/train_db.php';

$adminView  = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($uidKey)) : '';
$url = function (string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};

/* 예전 화면마다 회원 폴더 접두어가 달랐던 자료도 같은 회원 범위에서 찾습니다. */
$uidCandidates = [$uidKey];
$memberId = preg_replace('/[^A-Za-z0-9_-]/', '_', trim((string)($_SESSION['member_id'] ?? '')));
$kakaoId  = preg_replace('/[^A-Za-z0-9_-]/', '_', trim((string)($_SESSION['kakao_id'] ?? '')));
if ($memberId !== '') { $uidCandidates[] = $memberId; $uidCandidates[] = 'm_' . $memberId; }
if ($kakaoId  !== '') { $uidCandidates[] = 'kakao_' . $kakaoId; $uidCandidates[] = 'u_' . $kakaoId; }
/* 각 문서 모듈이 실제로 계산한 키도 포함합니다. 구버전은 app_user_key와 다를 수 있습니다. */
if (function_exists('fp_user_key')) $uidCandidates[] = (string)fp_user_key();
if (function_exists('tr_user_key')) $uidCandidates[] = (string)tr_user_key();
if (function_exists('jw_user_key')) $uidCandidates[] = (string)jw_user_key();
$uidCandidates = array_values(array_unique(array_filter($uidCandidates)));

/* 색인이 비어 있거나 오래됐어도 실제 JSON 파일에서 목록을 복구합니다. */
function pa_json_files(string $dir): array {
  if (!is_dir($dir)) return [];
  $out = [];
  foreach (glob($dir . '/*.json') ?: [] as $file) {
    if (strpos(basename($file), '_') === 0 || basename($file) === 'building.json') continue;
    $row = json_decode((string)@file_get_contents($file), true);
    if (is_array($row)) $out[] = $row;
  }
  return $out;
}

/* ── 자료 모으기 ───────────────────────────────────────── */

// 1) 건물 기본정보
$buildingInfo = function_exists('bi_load') ? bi_load() : [];
$hasBuildingInfo = trim((string)($buildingInfo['name'] ?? '')) !== ''
                || trim((string)($buildingInfo['address'] ?? '')) !== '';
$evacPlan = [];
if (function_exists('bi_file')) {
  $biPath = bi_file();
  $evacPath = $biPath !== '' ? dirname($biPath) . '/evacuation_plan.json' : '';
  if ($evacPath !== '' && is_file($evacPath)) {
    $evacRaw = json_decode((string)@file_get_contents($evacPath), true);
    if (is_array($evacRaw)) $evacPlan = $evacRaw;
  }
}
$evacFloors = is_array($evacPlan['floors'] ?? null) ? $evacPlan['floors'] : [];
$evacGroups = is_array($evacPlan['floor_groups'] ?? null) ? $evacPlan['floor_groups'] : [];
foreach ($evacGroups as $group) {
  $groupPlan = is_array($group['plan'] ?? null) ? $group['plan'] : [];
  if (!$groupPlan) continue;
  foreach ((array)($group['floors'] ?? []) as $floorKey) {
    $evacFloors[$floorKey] = array_merge((array)($evacFloors[$floorKey] ?? []), $groupPlan);
  }
}
$hasEvacPlan = !empty($evacFloors)
  || trim((string)($evacPlan['alarm_method'] ?? '')) !== ''
  || trim((string)($evacPlan['assembly_confirmed'] ?? '')) !== '';

// 2) 업무수행 기록표 — 연도별로 몇 건 작성했는지
$safeKey  = preg_replace('/[^A-Za-z0-9_-]/', '_', $uidKey);
$wlYears  = [];
foreach ($uidCandidates as $candidateKey) {
  $wlBase = __DIR__ . '/data/worklog/' . $candidateKey;
  if (!is_dir($wlBase)) continue;
  foreach (glob($wlBase . '/m*.json') ?: [] as $f) {
    if (preg_match('/m(\d{4})-\d{2}\.json$/', basename($f), $m)) {
      $y = $m[1];
      $wlYears[$y] = ($wlYears[$y] ?? 0) + 1;
    }
  }
}
krsort($wlYears);

// 2) 소방계획서
$plans = function_exists('fp_list_plans') ? fp_list_plans() : [];
{
  foreach ($uidCandidates as $candidateKey) foreach (pa_json_files(__DIR__ . '/data/fireplan/' . $candidateKey) as $p) {
    $id = preg_replace('/[^0-9A-Za-z]/', '', (string)($p['id'] ?? ''));
    if ($id === '') continue;
    $plans[] = ['id'=>$id, 'title'=>($p['building_name'] ?? '(제목 없음)'),
      'building_name'=>($p['building_name'] ?? ''), 'updated_at'=>($p['updated_at'] ?? '')];
  }
}

// 3) 자위소방대 편성표 — fire_plan_jawi.php가 저장하는 별도 목록
$formations = [];
foreach ($uidCandidates as $candidateKey) {
  $formationFile = __DIR__ . '/data/fireplan/' . $candidateKey . '/_jawi.json';
  if (!is_file($formationFile)) continue;
  $formationRaw = json_decode((string)@file_get_contents($formationFile), true);
  if (is_array($formationRaw)) $formations = array_merge($formations, $formationRaw);
}

// 4) 자위소방대 교육·훈련 결과 기록
$jawis = function_exists('jw_list') ? jw_list() : [];
{
  foreach ($uidCandidates as $candidateKey) foreach (pa_json_files(__DIR__ . '/data/jawi/' . $candidateKey) as $r) {
    $d = (array)($r['data'] ?? []); $id = preg_replace('/[^0-9A-Za-z]/', '', (string)($r['id'] ?? ''));
    if ($id === '') continue;
    $jawis[] = ['id'=>$id, 'title'=>(trim((string)($d['site_name'] ?? '')) ?: '(대상명 미입력)'),
      'updated_at'=>($r['updated_at'] ?? '')];
  }
}

// 5) 훈련·교육 기록
$trains = function_exists('tr_list') ? tr_list() : [];
{
  foreach ($uidCandidates as $candidateKey) foreach (pa_json_files(__DIR__ . '/data/train/' . $candidateKey) as $r) {
    $d = (array)($r['data'] ?? []); $id = preg_replace('/[^0-9A-Za-z]/', '', (string)($r['id'] ?? ''));
    if ($id === '') continue;
    $trains[] = ['id'=>$id, 'title'=>(trim((string)($d['t_name'] ?? '')) ?: '(대상명 미입력)'),
      'edu_date'=>($d['edu_date'] ?? $d['fire_date'] ?? ''), 'updated_at'=>($r['updated_at'] ?? '')];
  }
}

$paUnique = function(array $rows): array {
  $out=[];
  foreach ($rows as $row) {
    $id=(string)($row['id'] ?? '');
    if ($id === '' || isset($out[$id])) continue;
    $out[$id]=$row;
  }
  return array_values($out);
};
$plans=$paUnique($plans); $formations=$paUnique($formations); $jawis=$paUnique($jawis); $trains=$paUnique($trains);

$paSort = function(array &$rows): void {
  usort($rows, fn($a,$b)=>strcmp((string)($b['updated_at'] ?? $b['saved'] ?? ''), (string)($a['updated_at'] ?? $a['saved'] ?? '')));
};
$paSort($plans); $paSort($formations); $paSort($jawis); $paSort($trains);

$totalDocs = ($hasBuildingInfo ? 1 : 0) + ($hasEvacPlan ? 1 : 0) + array_sum($wlYears) + count($plans) + count($formations) + count($jawis) + count($trains);

$PAGE_TITLE = '전체 인쇄';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;
$ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
$ACCOUNT_IS_ADMIN = is_admin();
require __DIR__ . '/_header.php';
?>
<style>
/* 전체 인쇄 페이지 전용 — _header.php 의 .wrap/.card/.btn 위에 최소한만 더합니다 */
.pa-sec{margin-bottom:26px}
.pa-sec__head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.pa-chip{font-size:11px;font-weight:800;padding:4px 11px;border-radius:999px;letter-spacing:.02em}
.chip-wl{background:#f0fdf4;color:#15803d}
.chip-bi{background:#eff6ff;color:#1d4ed8}
.chip-ev{background:#ecfdf3;color:#047857}
.chip-fp{background:#eef2ff;color:var(--brand2)}
.chip-jw{background:#fff1f2;color:#be123c}
.chip-tr{background:#fefce8;color:#a16207}
.pa-sec__t{font-size:15px;font-weight:800}
.pa-sec__d{font-size:12.5px;color:var(--mut2)}

.pa-list{display:grid;gap:9px}
.pa-item{display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px 16px}
.pa-item__t{font-size:14px;font-weight:700}
.pa-item__d{font-size:12px;color:var(--mut2);margin-top:2px}
.pa-item__btn{margin-left:auto;display:inline-flex;align-items:center;gap:5px;
  padding:9px 15px;border-radius:10px;background:var(--brand);color:#fff;
  font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap}
.pa-item__btn:hover{filter:brightness(1.08);color:#fff}
@media(max-width:560px){.pa-item__btn{margin-left:0;width:100%;justify-content:center}}

.pa-empty{font-size:13px;color:var(--mut);background:var(--bg2);border-radius:10px;
  padding:14px 16px;line-height:1.7}
.pa-empty a{font-weight:700}

.pa-tip{font-size:12.5px;color:var(--mut2);background:#f0f9ff;border:1px solid #bae6fd;
  border-radius:10px;padding:13px 15px;margin-bottom:24px;line-height:1.75}
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 전체 인쇄</div>
    <h1>서류 인쇄</h1>
    <p>작성한 서류를 모아서 확인하고 인쇄합니다. 지금까지 <?=number_format($totalDocs)?>건이 작성되었습니다.</p>
  </div>
</header>

<main class="wrap">

  <div class="pa-tip">
    각 서식은 양식이 서로 달라 따로 인쇄합니다. 인쇄 버튼을 누르면 새 창에서 열리고,
    <b>대상을 "PDF로 저장"</b>으로 고르시면 파일로 보관하실 수 있습니다.
  </div>

  <!-- 1) 건물 기본정보 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-bi">기본</span>
      <span class="pa-sec__t">건물 기본정보</span>
      <span class="pa-sec__d">대상물·건물 규모·안전관리자·집결지·소방차 진입로</span>
    </div>
    <?php if (!$hasBuildingInfo): ?>
      <div class="pa-empty">
        아직 저장된 건물 기본정보가 없습니다.
        <a href="<?=h($url('/building_setup.php'))?>">기본정보 입력하러 가기 →</a>
      </div>
    <?php else: ?>
      <div class="pa-list">
        <div class="pa-item">
          <div>
            <div class="pa-item__t"><?=h((string)($buildingInfo['name'] ?? '(대상물명 없음)'))?></div>
            <div class="pa-item__d">
              <?=h((string)($buildingInfo['address'] ?? ''))?>
              <?php if (!empty($buildingInfo['updated'])): ?> · 최종 수정 <?=h((string)$buildingInfo['updated'])?><?php endif; ?>
            </div>
          </div>
          <a class="pa-item__btn" target="_top"
             href="<?=h($url('/building_setup.php?print=1'))?>">🖨 기본정보 인쇄</a>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- 피난 대피계획 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-ev">피난</span>
      <span class="pa-sec__t">피난 대피계획</span>
      <span class="pa-sec__d">동일 피난구간별 대피방법·담당자·집결지</span>
    </div>
    <?php if (!$hasEvacPlan): ?>
      <div class="pa-empty">아직 저장된 피난 대피계획이 없습니다. <a href="<?=h($url('/evacuation_plan_chat.php'))?>">피난계획 작성하러 가기 →</a></div>
    <?php else: ?>
      <div class="pa-list"><div class="pa-item"><div>
        <div class="pa-item__t"><?=h((string)($buildingInfo['name'] ?? '건물'))?> 피난 대피계획</div>
        <div class="pa-item__d">설정 층 <?=count($evacFloors)?>개 · 최종 수정 <?=h((string)($evacPlan['updated'] ?? '-'))?></div>
      </div><a class="pa-item__btn" target="_top" href="<?=h($url('/evacuation_plan_chat.php?print=1'))?>">🖨 피난계획 인쇄</a></div></div>
    <?php endif; ?>
  </section>

  <!-- 2) 업무수행 기록표 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-wl">매월</span>
      <span class="pa-sec__t">업무 수행 기록표</span>
      <span class="pa-sec__d">별지 제12호서식 · 연도별로 묶어서 인쇄합니다</span>
    </div>
    <?php if (!$wlYears): ?>
      <div class="pa-empty">
        아직 작성한 기록표가 없습니다.
        <a href="<?=h($url('/work_log.php'))?>">기록표 작성하러 가기 →</a>
      </div>
    <?php else: ?>
      <div class="pa-list">
        <?php foreach ($wlYears as $y => $cnt): ?>
          <div class="pa-item">
            <div>
              <div class="pa-item__t"><?=h((string)$y)?>년 기록표</div>
              <div class="pa-item__d"><?=$cnt?>개월분 작성됨</div>
            </div>
            <a class="pa-item__btn" target="_top"
               href="<?=h($url('/work_log_print.php?year=' . (string)$y))?>">🖨 <?=h((string)$y)?>년 전체 인쇄</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 2) 소방계획서 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-fp">연 1회</span>
      <span class="pa-sec__t">소방계획서</span>
      <span class="pa-sec__d">건물 전체의 소방안전관리 계획</span>
    </div>
    <?php if (!$plans): ?>
      <div class="pa-empty">
        아직 작성한 소방계획서가 없습니다.
        <a href="<?=h($url('/fire_plan.php'))?>">소방계획서 작성하러 가기 →</a>
      </div>
    <?php else: ?>
      <div class="pa-list">
        <?php foreach ($plans as $p): ?>
          <div class="pa-item">
            <div>
              <div class="pa-item__t"><?=h($p['title'] ?? $p['building_name'] ?? '(제목 없음)')?></div>
              <div class="pa-item__d">최종 수정 <?=h($p['updated_at'] ?? '-')?></div>
            </div>
            <a class="pa-item__btn" target="_top"
               href="<?=h($url('/fire_plan_print.php?id=' . rawurlencode((string)($p['id'] ?? ''))))?>">🖨 인쇄</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 3) 자위소방대 편성표 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-jw">수시</span>
      <span class="pa-sec__t">자위소방대 편성표</span>
      <span class="pa-sec__d">대장·부대장·활동조 편성</span>
    </div>
    <?php if (!$formations): ?>
      <div class="pa-empty">
        아직 작성한 편성표가 없습니다.
        <a href="<?=h($url('/fire_plan_jawi.php'))?>">편성표 만들러 가기 →</a>
      </div>
    <?php else: ?>
      <div class="pa-list">
        <?php foreach ($formations as $j): ?>
          <div class="pa-item">
            <div>
              <div class="pa-item__t"><?=h($j['site_name'] ?? '(대상물명 없음)')?></div>
              <div class="pa-item__d">
                편성 인원 <?php
                  $formationCount = (!empty($j['cmd'][0]) ? 1 : 0) + (!empty($j['deputy'][0]) ? 1 : 0);
                  foreach ((array)($j['groups'] ?? []) as $fg) $formationCount += count((array)($fg['members'] ?? []));
                  echo number_format($formationCount);
                ?>명 · 최종 저장 <?=h($j['saved'] ?? '-')?>
              </div>
            </div>
            <a class="pa-item__btn" target="_top"
               href="<?=h($url('/fire_plan_jawi.php?print_id=' . rawurlencode((string)($j['id'] ?? ''))))?>">🖨 편성표 인쇄</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 4) 자위소방대 교육·훈련 결과 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-jw">연간</span>
      <span class="pa-sec__t">자위소방대 교육·훈련 결과</span>
      <span class="pa-sec__d">별지 제13호서식 교육·훈련 실시 결과</span>
    </div>
    <?php if (!$jawis): ?>
      <div class="pa-empty">아직 작성한 자위소방대 교육·훈련 결과가 없습니다.</div>
    <?php else: ?>
      <div class="pa-list">
        <?php foreach ($jawis as $j): ?>
          <div class="pa-item">
            <div>
              <div class="pa-item__t"><?=h($j['title'] ?? '(제목 없음)')?></div>
              <div class="pa-item__d">최종 수정 <?=h($j['updated_at'] ?? '-')?></div>
            </div>
            <a class="pa-item__btn" target="_top"
               href="<?=h($url('/jawi_print.php?id=' . rawurlencode((string)($j['id'] ?? ''))))?>">🖨 결과 기록 인쇄</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- 5) 훈련·교육 기록 -->
  <section class="pa-sec">
    <div class="pa-sec__head">
      <span class="pa-chip chip-tr">연간</span>
      <span class="pa-sec__t">훈련·교육 기록</span>
      <span class="pa-sec__d">소방훈련 및 교육 실시 결과</span>
    </div>
    <?php if (!$trains): ?>
      <div class="pa-empty">
        아직 작성한 훈련·교육 기록이 없습니다.
        <a href="<?=h($url('/train.php'))?>">훈련 기록 작성하러 가기 →</a>
      </div>
    <?php else: ?>
      <div class="pa-list">
        <?php foreach ($trains as $t): ?>
          <div class="pa-item">
            <div>
              <div class="pa-item__t"><?=h($t['title'] ?? '(제목 없음)')?></div>
              <div class="pa-item__d">
                <?php if (!empty($t['edu_date'])): ?>실시일 <?=h($t['edu_date'])?> · <?php endif; ?>
                최종 수정 <?=h($t['updated_at'] ?? '-')?>
              </div>
            </div>
            <a class="pa-item__btn" target="_top"
               href="<?=h($url('/train_print.php?id=' . rawurlencode((string)($t['id'] ?? ''))))?>">🖨 인쇄</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<?php require __DIR__ . '/_footer.php'; ?>
