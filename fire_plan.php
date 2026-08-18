<?php
// fire_plan.php — 소방계획서 목록 (건물 소방안전관리자 전용)
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }

if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

require_once __DIR__ . '/fire_plan_db.php';
$nick   = $_SESSION['nickname'] ?? '사용자';
$usages = fp_usages();

/* 삭제 처리 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
  fp_csrf_check();
  $plan = fp_load_plan((string)($_POST['id'] ?? ''));
  if ($plan) fp_delete_plan((string)$plan['id']);
  header('Location: /fire_plan.php'); exit;
}

/* 목록 조회 */
$plans = fp_list_plans();

$totalSections = 0;
foreach (fp_sections() as $ch) $totalSections += count($ch['items']);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>소방계획서 — TWORIX</title>
<style>
:root{
  --bg:#f5f7fb; --card:#fff; --bd:#e3e8f0; --bd2:#d4dbe6;
  --fg:#1a2436; --mut:#7a8699; --mut2:#56627a;
  --brand:#2563eb; --brand2:#1d4ed8; --accent:#0891b2;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1120px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{font-weight:800;font-size:22px;color:var(--fg);letter-spacing:.5px}
.nav__right{display:flex;align-items:center;gap:12px;font-size:14px;color:var(--mut2)}
.btn{display:inline-flex;align-items:center;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.btn--danger:hover{border-color:#dc2626;color:#dc2626}
.page-head{position:relative;overflow:hidden;border-bottom:1px solid var(--bd);
  background:linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px) 0 0/100% 28px,
  linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px) 0 0/28px 100%,
  linear-gradient(180deg,#fbfcff,#eef3fb)}
.page-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 760px 320px at 12% 0%,rgba(8,145,178,.10),transparent 70%)}
.page-head__inner{position:relative;max-width:1120px;margin:0 auto;padding:48px 24px 40px}
.crumb{font-size:13px;color:var(--mut2);margin-bottom:12px}
.crumb a{color:var(--mut2)}.crumb a:hover{color:var(--brand2)}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:14px}
.badge span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.page-head h1{font-size:clamp(24px,3.5vw,34px);font-weight:700;letter-spacing:-.5px;margin-bottom:8px}
.page-head p{color:var(--mut2);font-size:15px}
.wrap{max-width:1120px;margin:0 auto;padding:32px 24px 80px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:24px}
.empty{text-align:center;color:var(--mut2);padding:48px 20px}
.empty h3{font-size:18px;color:var(--fg);margin-bottom:8px}
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.toolbar h2{font-size:18px;font-weight:700}
.plan{display:flex;align-items:center;gap:16px;padding:16px 6px;border-top:1px solid var(--bd);flex-wrap:wrap}
.plan:first-of-type{border-top:0}
.plan__main{flex:1;min-width:220px}
.plan__name{font-weight:700;font-size:15.5px}
.plan__name a{color:var(--fg)}.plan__name a:hover{color:var(--brand2)}
.plan__meta{font-size:12.5px;color:var(--mut2);margin-top:2px}
.tag{display:inline-block;font-size:11.5px;border-radius:999px;padding:2px 10px;font-weight:700}
.tag--use{background:#eef2ff;color:var(--brand2)}
.tag--draft{background:#fff7ed;color:#b45309}
.tag--done{background:#ecfdf5;color:#047857}
.prog{width:150px}
.prog__bar{height:7px;border-radius:99px;background:#e9edf4;overflow:hidden}
.prog__bar i{display:block;height:100%;background:var(--brand);border-radius:99px}
.prog__txt{font-size:11.5px;color:var(--mut2);margin-top:3px}

/* ── 법령 근거 안내 (접이식) ── */
.law{margin-bottom:24px;border:1px solid var(--bd);border-radius:14px;background:
  linear-gradient(180deg,#fbfcff,#fff);overflow:hidden}
.law__head{display:flex;align-items:center;gap:14px;padding:18px 22px;cursor:pointer;
  list-style:none;user-select:none}
.law__head::-webkit-details-marker{display:none}
.law__seal{flex-shrink:0;width:42px;height:42px;border-radius:10px;
  background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:20px;
  box-shadow:0 4px 12px rgba(37,99,235,.28)}
.law__htxt{flex:1;min-width:0}
.law__eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--accent);margin-bottom:2px}
.law__title{font-size:15.5px;font-weight:700;color:var(--fg)}
.law__title b{color:var(--brand2)}
.law__chev{flex-shrink:0;color:var(--mut);transition:transform .25s ease;font-size:13px}
details[open] .law__chev{transform:rotate(180deg)}
.law__body{padding:4px 22px 24px;border-top:1px solid var(--bd)}
.law__lead{font-size:13.5px;color:var(--mut2);margin:16px 2px 22px;line-height:1.75}

/* 법 계층 흐름 */
.flow{display:grid;gap:0}
.tier{position:relative;padding:0 0 0 34px}
.tier::before{content:'';position:absolute;left:11px;top:6px;bottom:-6px;width:2px;
  background:linear-gradient(var(--bd2),var(--bd2))}
.tier:last-child::before{display:none}
.tier__dot{position:absolute;left:4px;top:5px;width:16px;height:16px;border-radius:50%;
  background:#fff;border:2px solid var(--brand);z-index:1}
.tier--law .tier__dot{border-color:#1e3a8a}
.tier--ord .tier__dot{border-color:var(--accent)}
.tier__k{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--mut);text-transform:uppercase}
.tier__t{font-size:14.5px;font-weight:700;color:var(--fg);margin:1px 0 4px}
.tier__d{font-size:13px;color:var(--mut2);line-height:1.7;padding-bottom:20px}
.tier__d em{font-style:normal;background:#eef4ff;color:var(--brand2);
  padding:1px 6px;border-radius:5px;font-weight:600;font-size:12.5px}
.tier__quote{display:block;margin-top:8px;padding:10px 14px;border-left:3px solid var(--bd2);
  background:#f8fafc;border-radius:0 8px 8px 0;font-size:12.5px;color:var(--mut2);line-height:1.7}

/* 15항목 그리드 (펼쳤을 때) */
.items15{margin-top:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:8px}
.i15{display:flex;gap:9px;padding:9px 11px;border:1px solid var(--bd);border-radius:9px;
  background:#fff;font-size:12.5px;line-height:1.45}
.i15__n{flex-shrink:0;width:20px;height:20px;border-radius:6px;background:#eef2ff;
  color:var(--brand2);font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center}
.i15__t{color:var(--mut2);padding-top:1px}
/* 법정서식 준수 안내 */
.forms{margin-top:22px;padding-top:20px;border-top:1px dashed var(--bd)}
.forms__t{font-size:13.5px;font-weight:700;margin-bottom:4px}
.forms__d{font-size:12.5px;color:var(--mut2);line-height:1.7;margin-bottom:14px}
.forms__grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.fcol{border:1px solid var(--bd);border-radius:10px;padding:13px 15px;background:#fff}
.fcol__k{font-size:11px;font-weight:700;letter-spacing:.06em;margin-bottom:3px}
.fcol--free .fcol__k{color:var(--brand2)}
.fcol--fixed .fcol__k{color:#0f766e}
.fcol__t{font-size:13px;font-weight:700;margin-bottom:6px}
.fcol__d{font-size:12px;color:var(--mut2);line-height:1.65}
.fcol__list{margin-top:8px;font-size:11.5px;color:var(--mut2);line-height:1.7}
.fcol__list b{color:var(--fg);font-weight:600}
@media(max-width:640px){.forms__grid{grid-template-columns:1fr}}
.law__foot{margin-top:20px;font-size:12px;color:var(--mut);text-align:center;
  padding-top:16px;border-top:1px dashed var(--bd)}

@media(max-width:680px){.nav__inner{padding:0 16px}.page-head__inner{padding:36px 20px 28px}.prog{width:100%}
  .law__head{padding:16px}.law__body{padding:4px 16px 20px}.items15{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">YEOHUB</a>
    <div class="nav__right">
      <span><?=h($nick)?>님</span>
      <a class="btn" href="/building_manager.php">← 메인</a>
      <a class="btn" href="/logout.php">로그아웃</a>
    </div>
  </div>
</nav>

<header class="page-head">
  <div class="page-head__inner">
    <div class="crumb"><a href="/building_manager.php">건물 소방안전관리</a> › 소방계획서</div>
    <div class="badge"><span></span> 소방계획서</div>
    <h1>소방계획서</h1>
    <p>건물의 소방계획서를 작성하고 보관합니다.</p>
  </div>
</header>

<main class="wrap">

  <details class="law">
    <summary class="law__head">
      <div class="law__seal">§</div>
      <div class="law__htxt">
        <div class="law__eyebrow">법령 근거</div>
        <div class="law__title">이 소방계획서는 <b>화재예방법</b>에 따라 작성합니다 — 근거 보기</div>
      </div>
      <div class="law__chev">▼</div>
    </summary>

    <div class="law__body">
      <p class="law__lead">
        소방계획서는 소방안전관리자가 법으로 정해진 업무를 <b>어떻게 수행할지 미리 적어두는 설계도</b>입니다.
        아래처럼 법률이 큰 틀을 정하고, 시행령이 담을 내용을 구체화합니다.
      </p>

      <div class="flow">
        <div class="tier tier--law">
          <div class="tier__dot"></div>
          <div class="tier__k">법률</div>
          <div class="tier__t">화재예방법 제24조 제5항</div>
          <div class="tier__d">
            소방안전관리자가 수행할 <em>업무 9가지</em>를 정합니다. 그 <em>제1호</em>가 “소방계획서의 작성 및 시행”입니다.
            계획서 내용은 “<em>대통령령으로 정하는 사항</em>”으로 넘깁니다.
            <span class="tier__quote">“제36조에 따른 피난계획에 관한 사항과 대통령령으로 정하는 사항이 포함된 소방계획서의 작성 및 시행”</span>
          </div>
        </div>

        <div class="tier tier--ord">
          <div class="tier__dot"></div>
          <div class="tier__k">시행령 (대통령령)</div>
          <div class="tier__t">화재예방법 시행령 제27조 제1항</div>
          <div class="tier__d">
            법률이 넘긴 “대통령령으로 정하는 사항”이 바로 <em>이 15개 항목</em>입니다.
            우리 계획서의 작성 항목이 여기서 나옵니다.
            <span class="tier__quote">“법 제24조제5항제1호에서 ‘대통령령으로 정하는 사항’이란 다음 각 호의 사항을 말한다”</span>
          </div>
        </div>

        <div class="tier">
          <div class="tier__dot"></div>
          <div class="tier__k">작성 항목</div>
          <div class="tier__t">15개 법정 항목</div>
          <div class="tier__d" style="padding-bottom:4px">
            건물에 해당하지 않는 항목(권원분리·위험물 등)은 작성 시 <em>자동으로 생략</em>됩니다.
          </div>
        </div>
      </div>

      <div class="items15">
        <?php
          $lawItems = [
            '위치·구조·연면적·용도·수용인원 등 일반현황',
            '소방·방화·전기·가스·위험물시설의 현황',
            '자체점검계획 및 대응대책',
            '소방·피난·방화시설의 점검·정비계획',
            '피난계획 (화재안전취약자 포함)',
            '방화구획·제연·마감재·방염대상물품 유지관리',
            '관리 권원이 분리된 대상물의 안전관리',
            '소방훈련·교육에 관한 계획',
            '자위소방대 조직과 대원의 임무',
            '화기취급 작업 등 공사 중 안전관리',
            '소화 및 연소 방지에 관한 사항',
            '위험물의 저장·취급에 관한 사항',
            '업무수행에 관한 기록·유지',
            '화재 초기대응 (경보·초기소화·피난유도)',
            '소방본부장·소방서장이 요청하는 사항',
          ];
          foreach ($lawItems as $i => $t):
        ?>
          <div class="i15">
            <div class="i15__n"><?=$i+1?></div>
            <div class="i15__t"><?=h($t)?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="forms">
        <div class="forms__t">서식은 어떻게 따르나요?</div>
        <div class="forms__d">
          법은 문서마다 요구 수준이 다릅니다. <b>내용만 정한 것</b>과 <b>서식(양식)까지 정한 것</b>을
          구분해서, TWORIX는 각각 법이 요구하는 방식 그대로 따릅니다.
        </div>
        <div class="forms__grid">
          <div class="fcol fcol--free">
            <div class="fcol__k">내용만 법정 — 서식 자유</div>
            <div class="fcol__t">소방계획서 (이 페이지)</div>
            <div class="fcol__d">
              시행령 제27조는 "다음 각 호의 <b>사항이 포함</b>되어야 한다"고 하여
              담을 <b>내용</b>만 정하고 양식은 정하지 않았습니다.
              그래서 15개 항목을 빠짐없이 담되, 작성 화면은 쓰기 쉽게 구성했습니다.
            </div>
          </div>
          <div class="fcol fcol--fixed">
            <div class="fcol__k">서식까지 법정 — 원본 그대로</div>
            <div class="fcol__t">별지 서식 (기록·제출 문서)</div>
            <div class="fcol__d">
              시행규칙이 "<b>별지 제○호서식</b>에 기록해야 한다"고 서식 자체를
              지정한 문서는 행정안전부령 <b>원본 양식을 그대로 재현</b>해
              작성·출력합니다.
            </div>
            <div class="fcol__list">
              · <b>별지 제12호</b> 업무수행 기록표 (규칙 제10조)<br>
              · <b>별지 제28호</b> 소방훈련·교육 결과 기록부 (규칙 제36조④, 2년 보관)<br>
              · <b>별지 제29호</b> 소방훈련·교육 결과서 (규칙 제37조, 특급·1급 제출)<br>
              · <b>별지 제13호</b> 자위소방대 교육·훈련 기록부 (규칙 제36조⑦, 2년 보관)
            </div>
          </div>
        </div>
      </div>

      <div class="law__foot">
        근거: 「화재의 예방 및 안전관리에 관한 법률」 제24조 제5항 · 같은 법 시행령 제27조 제1항
      </div>
    </div>
  </details>

  <div class="card">
    <div class="toolbar">
      <h2>소방계획서 목록</h2>
      <a class="btn btn--primary" href="/fire_plan_chat.php">💬 문답으로 작성</a>
      <a class="btn" href="/fire_plan_new.php">＋ 표로 작성</a>
    </div>

    <?php if (!$plans): ?>
      <div class="empty">
        <h3>아직 등록된 소방계획서가 없습니다</h3>
        <p><b>문답으로 작성</b>을 누르면 지금까지 입력해 두신 건물 기본정보·자위소방대 편성표·
          업무수행 기록표 기본값을 먼저 채워 넣고, 남은 것만 하나씩 여쭤봅니다.</p>
      </div>
    <?php else: foreach ($plans as $p):
      $u = $usages[$p['usage_code']] ?? ['nm'=>$p['usage_code']];
      $full = fp_load_plan((string)$p['id']);
      $cnt  = $full ? fp_count_states($full) : ['done'=>0,'skip'=>0];
      $effective = max(1, $totalSections - (int)$cnt['skip']);
      $pct = min(100, (int)round((int)$cnt['done'] / $effective * 100));
    ?>
      <div class="plan">
        <div class="plan__main">
          <div class="plan__name">
            <a href="/fire_plan_edit.php?id=<?=h($p['id'])?>"><?=h($p['building_name'] ?: '(대상명 미입력)')?></a>
            <span class="tag tag--use"><?=h($u['nm'])?></span>
            <?php if (($p['status'] ?? '')==='done'): ?><span class="tag tag--done">완료</span>
            <?php else: ?><span class="tag tag--draft">작성중</span><?php endif; ?>
          </div>
          <div class="plan__meta">
            최근 수정 <?=h(substr((string)($p['updated_at'] ?? ''),0,16))?>
            <?php if ($cnt['skip']): ?> · 자동 생략 <?=$cnt['skip']?>건<?php endif; ?>
          </div>
        </div>
        <div class="prog">
          <div class="prog__bar"><i style="width:<?=$pct?>%"></i></div>
          <div class="prog__txt">작성 진행률 <?=$pct?>%</div>
        </div>
        <a class="btn" href="/fire_plan_chat.php?id=<?=h($p['id'])?>">💬 문답</a>
        <a class="btn" href="/fire_plan_edit.php?id=<?=h($p['id'])?>">이어쓰기</a>
        <form method="post" onsubmit="return confirm('이 계획서를 삭제할까요? 되돌릴 수 없습니다.')">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?=h($p['id'])?>">
          <input type="hidden" name="csrf" value="<?=h(fp_csrf())?>">
          <button class="btn btn--danger" type="submit">삭제</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
