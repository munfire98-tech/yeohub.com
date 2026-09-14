<?php
// 소방계획서 전체 문답. 원본 업무자료는 읽기만 하고 확인한 답변만 저장합니다.
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');
ini_set('session.cookie_httponly','1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();
function h($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function is_admin(): bool { return !empty($_SESSION['is_admin']) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1); }
if (!is_admin() && empty($_SESSION['is_user'])) { header('Location: /index.php'); exit; }
if (!is_admin() && ($_SESSION['role'] ?? '') !== 'building') { header('Location: /clients_mini.php'); exit; }
require_once __DIR__.'/fire_plan_db.php';
require_once __DIR__.'/building_info.php';
require_once __DIR__.'/evacuation_plan_common.php';
if (fp_user_key() === '') { http_response_code(403); exit('사용자 정보를 확인할 수 없습니다. 다시 로그인해 주세요.'); }
$context = [];
if (($_GET['embed'] ?? '') === '1') $context['embed'] = '1';
if (($_GET['modal'] ?? '') === '1') $context['modal'] = '1';
if (is_admin() && isset($_GET['uid']) && is_string($_GET['uid'])) $context['uid'] = $_GET['uid'];
$url = function(string $path, array $args = []) use ($context): string { $q = array_merge($context,$args); return $path.($q ? '?'.http_build_query($q) : ''); };
$planId = (string)($_GET['id'] ?? '');
$requestedYear = filter_var($_GET['year'] ?? date('Y'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
if ($requestedYear === false) { http_response_code(400); exit('올바른 계획연도를 선택해 주세요.'); }
if ($planId === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  foreach (fp_list_plans() as $row) {
    $existing=fp_load_plan((string)($row['id'] ?? ''));
    if ($existing && fp_plan_year($existing)===$requestedYear) { $planId=(string)$existing['id']; break; }
  }
  if ($planId!=='') { header('Location: '.$url('/fire_plan_chat.php',['id'=>$planId,'year'=>$requestedYear])); exit; }
}
$plan = $planId !== '' ? fp_load_plan($planId) : null;
if (!$plan) {
  if ($planId !== '' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { http_response_code(404); exit('계획서를 찾을 수 없습니다. 목록에서 다시 열어주세요.'); }
  $usage = (string)($_GET['usage'] ?? 'business');
  if (!isset(fp_usages()[$usage])) $usage = 'business';
  $planId = fp_create_plan($usage,$requestedYear);
  if (!fp_load_plan($planId)) { http_response_code(500); exit('계획서를 만들지 못했습니다. 저장 공간을 확인해 주세요.'); }
  header('Location: '.$url('/fire_plan_chat.php',['id'=>$planId,'year'=>$requestedYear])); exit;
}
$planYear=fp_plan_year($plan);
if (isset($_GET['year']) && $requestedYear!==$planYear && ($_SERVER['REQUEST_METHOD'] ?? 'GET')==='GET') {
  header('Location: '.$url('/fire_plan_chat.php',['id'=>$planId,'year'=>$planYear]));exit;
}
$schema = fp_chat_schema();
$bi = bi_load();
$common = epc_load();
$selection=(array)($plan['source_selection'] ?? []);
$sources = fp_chat_sources($bi,$common,$planYear,$selection);
$sectionTitles = [];
foreach (fp_sections() as $chapter) foreach ($chapter['items'] as $c=>$t) $sectionTitles[(string)$c] = $t;
function chat_reply(array $data, int $status = 200): void {
  http_response_code($status); header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function chat_sections(array $plan): array {
  $out = [];
  foreach (fp_chat_schema() as $c=>$fields) $out[$c] = (array)($plan['sections'][$c]['data'] ?? []);
  $out['1']['plan_date'] = (string)($plan['plan_date'] ?? '');
  return $out;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!hash_equals(fp_csrf(),(string)($_POST['csrf'] ?? ''))) chat_reply(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'],403);
  if (($_POST['act'] ?? '') === 'sources') {
    $selected=json_decode((string)($_POST['patch'] ?? '[]'),true);
    if (!is_array($selected)) chat_reply(['ok'=>false,'error'=>'반영할 자료를 선택해 주세요.'],400);
    foreach ($selected as $id) if (!is_string($id) || !isset($sources['groups'][$id])) chat_reply(['ok'=>false,'error'=>'잘못된 자료 선택입니다.'],400);
    $plan['plan_year']=$planYear;$plan['source_selection']=array_values(array_unique($selected));$plan['source_selection_set']=true;
    if (!fp_write_json(fp_plan_file($planId),$plan)) chat_reply(['ok'=>false,'error'=>'선택을 저장하지 못했습니다.'],500);
    fp_touch_index($planId,$plan);
    chat_reply(['ok'=>true,'sections'=>chat_sections($plan),'sources'=>fp_chat_sources($bi,$common,$planYear,$plan['source_selection']),'selection'=>$plan['source_selection']]);
  }
  if (empty($plan['source_selection_set'])) chat_reply(['ok'=>false,'error'=>'먼저 반영할 자료를 선택하거나 직접 작성을 선택해 주세요.'],400);
  $code = (string)($_POST['code'] ?? '');
  if (!isset($schema[$code])) chat_reply(['ok'=>false,'error'=>'잘못된 항목입니다.'],400);
  $cur = fp_get_section($planId,$code);
  $s1 = fp_get_section($planId,'1'); $s3 = fp_get_section($planId,'3');
  $action = (string)($_POST['act'] ?? '');
  if ($action === 'answer') {
    $patch = json_decode((string)($_POST['patch'] ?? ''),true);
    if (!is_array($patch) || !$patch) chat_reply(['ok'=>false,'error'=>'답변을 확인해 주세요.'],400);
    $answered = (array)($cur['_chat_answers'] ?? []);
    foreach ($patch as $key=>$v) {
      if (!isset($schema[$code][$key])) chat_reply(['ok'=>false,'error'=>'저장할 수 없는 필드입니다.'],400);
      $field = $schema[$code][$key];
      if ($field['type'] === 'multi') {
        if (!is_array($v)) chat_reply(['ok'=>false,'error'=>'선택값을 확인해 주세요.'],400);
        foreach ($v as $item) if (!is_string($item)) chat_reply(['ok'=>false,'error'=>'선택값을 확인해 주세요.'],400);
        $allowed = array_merge($field['options'],is_array($cur[$key] ?? null)?$cur[$key]:[]);
        if (array_diff($v,$allowed)) chat_reply(['ok'=>false,'error'=>'지원하지 않는 선택값입니다.'],400);
        if (in_array('해당없음',$v,true) && count($v)>1) chat_reply(['ok'=>false,'error'=>'해당없음은 다른 항목과 함께 선택할 수 없습니다.'],400);
        $v = array_values(array_unique($v));
      } else {
        if (!is_string($v) || strlen($v)>30000) chat_reply(['ok'=>false,'error'=>'답변 형식이나 길이를 확인해 주세요.'],400);
        $v = trim($v);
        if ($field['type'] === 'choice' && $v !== '' && !in_array($v,$field['options'],true)) chat_reply(['ok'=>false,'error'=>'선택지를 확인해 주세요.'],400);
        if ($field['type'] === 'number' && $v !== '' && (!is_numeric($v) || (float)$v<0)) chat_reply(['ok'=>false,'error'=>'0 이상의 숫자를 입력해 주세요.'],400);
        if ($field['type'] === 'date' && $v !== '') {
          $date = DateTimeImmutable::createFromFormat('!Y-m-d',$v);
          if (!$date || $date->format('Y-m-d') !== $v) chat_reply(['ok'=>false,'error'=>'날짜를 확인해 주세요.'],400);
        }
      }
      if ($code === '1' && in_array($key,['name','addr','mgr_name','grade'],true) && $v === '') chat_reply(['ok'=>false,'error'=>'기본 필수정보는 비워둘 수 없습니다. 나중에 답하기를 선택할 수 있습니다.'],400);
      $cur[$key] = $v; $answered[] = $key;
    }
    $cur['_chat_answers'] = array_values(array_unique($answered));
    $cur['_chat_complete'] = false;
    if ($code === '5' && isset($patch['route'])
        && $patch['route'] === ($sources['data']['5']['route'] ?? null)) {
      $cur['common_updated'] = (string)($sources['data']['5']['common_updated'] ?? '');
    }
  } elseif ($action === 'confirm') {
    foreach ($schema[$code] as $key=>$field) {
      if (fp_chat_visible($code,$key,$s1,$s3) && !in_array($key,(array)($cur['_chat_answers'] ?? []),true)) chat_reply(['ok'=>false,'error'=>'아직 확인하지 않은 답변이 있습니다. 문답을 이어가 주세요.'],400);
    }
    $cur['_chat_complete'] = true;
  } else chat_reply(['ok'=>false,'error'=>'잘못된 요청입니다.'],400);
  if (!fp_save_section($planId,$code,$cur,!empty($cur['_chat_complete']))) chat_reply(['ok'=>false,'error'=>'저장하지 못했습니다. 다시 시도해 주세요.'],500);
  if ($code === '1') {
    fp_apply_skips($planId,fp_skip_rules($cur));
    fp_update_shared($planId,(string)($cur['name'] ?? ''),fp_jawi_type($cur),(string)($cur['plan_date'] ?? ''));
    if ($action === 'answer' && (($s1['grade'] ?? '') !== ($cur['grade'] ?? '') || ($s1['approval'] ?? '') !== ($cur['approval'] ?? ''))) {
      $s3['_chat_complete'] = false;
      $s3['_chat_answers'] = array_values(array_diff((array)($s3['_chat_answers'] ?? []),['comprehensive','r1_when','r2_when']));
      if (!fp_save_section($planId,'3',$s3,false)) chat_reply(['ok'=>false,'error'=>'점검계획 확인 상태를 갱신하지 못했습니다. 다시 시도해 주세요.'],500);
    }
  }
  $all = chat_sections(fp_load_plan($planId));
  $complete = true; $skips = fp_skip_rules($all['1']);
  foreach ($schema as $c=>$fields) if (!isset($skips[$c]) && empty($all[$c]['_chat_complete'])) $complete = false;
  fp_set_status($planId,$complete?'done':'draft');
  chat_reply(['ok'=>true,'sections'=>$all,'complete'=>$complete]);
}
$boot = ['csrf'=>fp_csrf(),'year'=>$planYear,'selection'=>$selection,'selectionSet'=>!empty($plan['source_selection_set']),'schema'=>$schema,'titles'=>$sectionTitles,'sections'=>chat_sections($plan),'sources'=>$sources,
  'listUrl'=>$url('/fire_plan.php'),'editUrl'=>$url('/fire_plan_edit.php',['id'=>$planId]),
  'printUrl'=>$url('/fire_plan_print.php',['id'=>$planId])];
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>소방계획서 문답 작성</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
body{margin:0;background:var(--bg);color:var(--fg);line-height:1.65;font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
a{color:inherit;text-decoration:none}button,input,textarea,select{font:inherit;color:inherit}button,a{touch-action:manipulation}button{cursor:pointer}button:disabled{opacity:.55;cursor:wait}:focus-visible{outline:2px solid var(--brand);outline-offset:2px}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:780px;margin:0 auto;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px}.brand{font-weight:800;font-size:21px}.nav__actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:9px;border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}.btn:hover{border-color:var(--brand);color:var(--brand2)}.primary,.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}.primary:hover,.btn--pri:hover{background:var(--brand2);color:#fff}.btn--sm{padding:6px 12px;font-size:12.5px}
.prog{position:sticky;top:56px;z-index:45;background:#fff;border-bottom:1px solid var(--bd)}.prog__in{max-width:780px;margin:0 auto;padding:11px 20px}.prog__row{display:flex;justify-content:space-between;gap:12px;font-size:12.5px;color:var(--mut2);margin-bottom:6px}.prog__row b{color:var(--brand2)}.bar{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden}.bar i{display:block;height:100%;background:var(--brand);width:0;border-radius:3px;transition:width .45s cubic-bezier(.2,.7,.3,1)}
.wrap{max-width:780px;margin:0 auto;padding:24px 20px 70px}.chat-settings{margin-bottom:20px;border:1px solid var(--bd);border-radius:11px;background:rgba(255,255,255,.72);overflow:hidden}.chat-settings>summary{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px 13px;cursor:pointer;list-style:none;color:var(--mut2);font-size:12px}.chat-settings>summary::-webkit-details-marker{display:none}.chat-settings>summary:after{content:'설정';flex-shrink:0;padding:4px 9px;border:1px solid var(--bd2);border-radius:8px;background:#fff;color:var(--mut2);font-size:11px;font-weight:700}.chat-settings[open]>summary:after{content:'접기'}.chat-settings__summary{min-width:0}.chat-settings__summary b{display:block;color:#334155;font-size:12.5px}.chat-settings__summary small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mut);font-size:10.5px;font-weight:400}.chat-settings__body{display:grid;grid-template-columns:1fr auto;gap:9px;padding:11px 13px 13px;border-top:1px solid var(--bd);background:#fff}.toolbar{display:flex;align-items:center;gap:10px;min-width:0;padding:8px 11px;border:1px solid var(--bd);border-radius:9px;background:#fff}.toolbar label{flex-shrink:0;font-size:11.5px;font-weight:700;color:var(--mut2)}.toolbar select{width:100%;min-width:0;border:0;background:transparent;color:#334155;font-size:12px;font-weight:650;outline:0}
.source-bar{display:flex;align-items:center}.source-bar>span{display:none}.source-bar .btn{height:100%;white-space:nowrap}.source-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:16px 0}.source-option{display:flex;align-items:flex-start;gap:9px;border:1px solid var(--bd);background:#fff;border-radius:11px;padding:12px;cursor:pointer;transition:.14s}.source-option:hover{border-color:#b7c8e2}.source-option:has(input:checked){border-color:#8db0e8;background:#f3f7ff;box-shadow:0 0 0 2px rgba(37,99,235,.06)}.source-option:has(input:disabled){opacity:.6;cursor:default}.source-option b{font-size:12.5px}.source-option small{display:block;font-size:10.5px;color:var(--mut);margin-top:4px}.source-option input{margin-top:4px;accent-color:var(--brand)}
.card{background:transparent;border:0;padding:0 0 20px;scroll-margin-top:118px}.msg{display:flex;gap:11px;margin-bottom:15px;animation:pop .28s ease both}@keyframes pop{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}.msg__av{width:31px;height:31px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px;background:#eef2ff}.msg__b{background:var(--card);border:1px solid var(--bd);border-radius:4px 14px 14px 14px;padding:14px 17px;max-width:calc(100% - 44px);font-size:14.8px;line-height:1.72;white-space:pre-wrap;overflow-wrap:anywhere}.msg--me{flex-direction:row-reverse}.msg--me .msg__av{background:#e6edfb}.msg--me .msg__b{background:var(--brand);border-color:var(--brand);color:#fff;border-radius:14px 4px 14px 14px}.question{font-size:14.8px;font-weight:700;line-height:1.72;margin:0}.chat-turn{margin-bottom:5px}
.answer{margin:0 0 22px 42px}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:13px}.actions>.primary,.actions>.btn--pri{margin-left:0;min-width:0}.options{display:flex;gap:8px;flex-wrap:wrap;padding:3px 0}.option{position:relative;display:flex;align-items:center;min-height:41px;padding:9px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;cursor:pointer;font-size:13.5px;font-weight:500;transition:.14s}.option:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}.option:has(input:checked),.option.on{background:var(--brand);border-color:var(--brand);color:#fff}.option:focus-within{outline:2px solid var(--brand);outline-offset:2px}.option input{position:absolute;opacity:0;pointer-events:none}
.quick-answer{margin-bottom:10px}.quick-answer__label{display:block;margin-bottom:7px;color:var(--mut2);font-size:11.5px;font-weight:700}.quick-answer__choices{display:flex;flex-wrap:wrap;gap:7px}.quick-answer .option{min-height:36px;padding:7px 12px;font-size:12.5px}.quick-answer .option--long{width:100%;border-radius:10px;line-height:1.5}.quick-answer__manual{border-style:dashed!important;color:var(--mut2)}.direct-answer-input[hidden],.actions .btn[hidden]{display:none!important}
#inputArea:not(.options) input,#inputArea textarea{width:100%;padding:11px 14px;border:1px solid var(--bd2);border-radius:11px;background:#fff;font-size:14.5px;font-family:inherit}#inputArea textarea{min-height:110px;resize:vertical;line-height:1.6}#inputArea input:focus,#inputArea textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.question-meta{font-size:12px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd)}.hint{font-size:12.5px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd);white-space:normal}.section-note{padding:11px 13px;background:#eff6ff;border:1px solid #d5e5ff;border-radius:10px;color:#4a607d;font-size:12px;margin-bottom:12px;white-space:normal}.answer-extra{margin:14px 0 2px;font-size:12px;color:var(--mut)}.answer-extra summary{cursor:pointer;width:fit-content}.extra-buttons{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}.save-hint{flex-basis:100%;text-align:right;font-size:11px;color:var(--mut)}.error{color:#b42318;white-space:pre-wrap;margin-top:12px;font-size:12.5px}.status{color:var(--mut);font-size:12px;min-height:20px;margin:2px 0 0 42px}
.review{margin:12px 0 4px;max-height:340px;overflow:auto;background:#fff;border:1px solid var(--bd);border-radius:12px;padding:0 14px;white-space:normal}.review div{padding:10px 0;border-bottom:1px solid #eef1f5}.review div:last-child{border-bottom:0}.review dt{color:var(--mut);font-size:11px}.review dd{margin:3px 0 0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;font-weight:600;color:#334155}
.saved-chat{margin-bottom:20px;border:1px solid var(--bd);border-radius:12px;background:rgba(255,255,255,.72);overflow:hidden}.saved-chat>summary{cursor:pointer;padding:11px 14px;color:var(--mut2);font-size:12px;font-weight:700}.saved-chat[open]>summary{border-bottom:1px solid var(--bd)}.saved-chat .chat-turn{padding:14px 14px 0}.saved-chat .msg__b{font-size:13px;padding:11px 14px}.saved-chat .msg:last-child{margin-bottom:0}
@media(max-width:560px){.nav__in{padding:0 14px}.brand{font-size:18px}.nav__actions .btn:first-child{display:none}.prog__in{padding:10px 14px}.wrap{padding:16px 14px 48px}.chat-settings__body{grid-template-columns:1fr}.source-grid{grid-template-columns:1fr}.source-bar .btn{width:100%}.answer{margin-left:0}.msg__b{max-width:calc(100% - 42px);font-size:14px}.actions>.primary,.actions>.btn--pri{margin-left:0}.status{margin-left:0}}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
</style>
<?php if (isset($context['modal'])): ?>
<style>.nav{display:none}.prog{top:0}.wrap{max-width:none;padding:16px 22px 34px}.card{scroll-margin-top:98px}@media(max-width:540px){.wrap{padding:14px}}</style>
<?php endif; ?>
</head><body>
<nav class="nav"><div class="nav__in">
  <a class="brand" href="/index.php" target="_top">소방계획서.com</a>
  <div class="nav__actions"><a class="btn" href="<?=h($url('/fire_plan_edit.php',['id'=>$planId]))?>">표로 작성</a><a class="btn" href="<?=h($url('/fire_plan.php'))?>">← 목록</a></div>
</div></nav>
<div class="prog"><div class="prog__in"><div class="prog__row"><span><?=$planYear?>년 소방계획서</span><span><b id="progressPct">0%</b> · <span id="progressText"></span></span></div><div class="bar"><i id="progressBar"></i></div></div></div>
<main class="wrap">
<details class="chat-settings"><summary><span class="chat-settings__summary"><b>작성 설정</b><small id="sourceSummary">먼저 반영할 자료를 골라주세요.</small></span></summary><div class="chat-settings__body">
  <div class="toolbar"><label for="sectionNav">작성 항목</label><select id="sectionNav" aria-label="작성 항목 선택"></select></div>
  <div class="source-bar"><span aria-hidden="true"></span><button type="button" class="btn btn--sm" id="changeSources">자료 다시 선택</button></div>
</div></details>
<div id="chatLog" aria-label="작성 대화 기록"></div>
<div id="stage"></div><div id="saveStatus" class="status" role="status"></div>
</main><script>
const APP = <?=json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
if(window.parent!==window)window.parent.postMessage({type:'fp-modal-year',year:APP.year},location.origin);
let data = APP.sections, code = '1', fieldIndex = 0, busy = false, dirty = false, reviewAll = false, saveCurrentAnswer = null;
const stage = document.getElementById('stage'), nav = document.getElementById('sectionNav');
const codes = Object.keys(APP.schema), savedStatus = document.getElementById('saveStatus');
const empty = v => Array.isArray(v) ? v.length === 0 : v == null || String(v).trim() === '';
const text = v => Array.isArray(v) ? v.join(' · ') : String(v == null ? '' : v);
const esc = v => text(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const grade3 = () => String(data['1'].grade || '').replace(/\s/g,'') === '3급';
const comp = () => data['3'].comprehensive === '포함' || (data['3'].comprehensive !== '제외' && !grade3());
function skipped(c){return ({'7':'split','8':'joint','12':'hazmat'})[c] && data['1'][({'7':'split','8':'joint','12':'hazmat'})[c]] === '해당없음';}
function visible(f){return !(code === '1' && ['ins_co','ins_term','ins_life','ins_prop'].includes(f.key) && data['1'].ins !== '가입') && !(code === '3' && f.key.startsWith('r2_') && !comp());}
function fields(){return Object.values(APP.schema[code]).filter(visible);}
function answered(k){return (data[code]._chat_answers || []).includes(k);}
function suggestion(k){
  const source = (APP.sources.data[code] || {})[k];
  if (!empty(source)) return source;
  if(code === '1' && k === 'plan_date') return data['1'].plan_date || '';
  if(code === '3') {
    if(k === 'comprehensive' && grade3()) return '제외';
    if(k === 'r3_when') return '매월 1일';
    if(k === 'r3_who') return data['1'].mgr_name || '';
    if(k === 'r1_when' && grade3() && !comp() && /^\d{4}-\d{2}-\d{2}$/.test(data['1'].approval || '')) return '매년 '+Number(data['1'].approval.slice(5,7))+'월 (연 1회, 해당 월 말일까지)';
    if(k === 'memo') return '불량 발견 즉시 관계인에게 보고하고 위험구역 안전조치를 실시한다. 점검업체와 보수 일정·방법을 협의하여 수리하고, 조치 결과를 확인·기록한다.';
  }
  if(code === '4' && k === 'memo') return '이상 발견 → 관계인 보고 → 필요한 안전조치 → 보수 담당 및 일정 협의 → 정비 완료 확인 → 업무기록에 결과를 남긴다.';
  if(code === '10') return '작업 전 책임자에게 작업 내용을 알리고 가연물 제거, 소화기 비치, 불티 비산 방지 등 안전조치를 확인한다. 작업 중 화재감시자를 배치하고, 작업 후 잔불과 주변 이상 유무를 확인한다.';
  if(code === '14' && k === 's3') return '대피로가 확보되고 안전하게 대응할 수 있는 초기 화재에 한해 설치된 소화설비로 대응하며, 연기·화세가 커지면 즉시 대피한다.';
  return '';
}
function value(k){return answered(k) || !empty(data[code][k]) ? data[code][k] : suggestion(k);}
function updateProgress(){
  const active = codes.filter(c=>!skipped(c)), count = active.filter(c=>data[c]._chat_complete).length;
  const pct = active.length ? Math.round(count/active.length*100) : 0;
  document.getElementById('progressText').textContent = count+' / '+active.length+'항목 확인';
  document.getElementById('progressPct').textContent = pct+'%';
  document.getElementById('progressBar').style.width = pct+'%';
  nav.innerHTML = codes.map(c=>'<option value="'+c+'">'+c+'. '+esc(APP.titles[c])+(skipped(c)?' · 해당없음':data[c]._chat_complete?' · 확인 완료':'')+'</option>').join(''); nav.value = code;
}
function card(title, body='') {saveCurrentAnswer=null;stage.innerHTML='<section class="card"><div class="msg"><div class="msg__av" aria-hidden="true">🚒</div><div class="msg__b"><h2 class="question" tabindex="-1">'+esc(title)+'</h2></div></div><div class="answer">'+body+'<div class="actions" id="actions"></div><div class="error" id="error" role="alert"></div></div></section>';const meta=stage.querySelector('.question-meta');if(meta)stage.querySelector('.msg__b').append(meta);requestAnimationFrame(()=>stage.scrollIntoView({block:'start',behavior:'smooth'}));}
function recordTurn(answer){
  const log=document.getElementById('chatLog'),turn=document.createElement('div');turn.className='chat-turn';
  const question=document.createElement('div');question.className='msg';question.innerHTML='<div class="msg__av" aria-hidden="true">🚒</div><div class="msg__b chat-bot"></div>';question.querySelector('.chat-bot').textContent=stage.querySelector('.question')?.textContent || '내용 확인';
  const reply=document.createElement('div');reply.className='msg msg--me';reply.innerHTML='<div class="msg__av" aria-hidden="true">🙂</div><div class="msg__b chat-user"></div>';reply.querySelector('.chat-user').textContent=text(answer)||'해당없음';turn.append(question,reply);log.append(turn);
}
function sourceSummary(){document.getElementById('sourceSummary').textContent=APP.sources.names.length?'반영 자료 · '+APP.sources.names.join(' · '):'자료를 불러오지 않고 직접 작성';}
function chooseSources(){
  updateProgress();nav.disabled=true;
  const html='<p class="hint">필요한 자료만 골라주세요. 선택한 자료는 답변을 확인할 때 반영돼요.</p><div class="source-grid">'+Object.entries(APP.sources.groups).map(([id,g])=>'<label class="source-option"><input type="checkbox" value="'+id+'" '+((APP.selectionSet?APP.selection.includes(id):g.available)?'checked ':'')+(!g.available?'disabled ':'')+'><span><b>'+esc(g.title)+'</b><small>'+esc(g.detail)+(g.available?'':' · 불러올 자료 없음')+'</small></span></label>').join('')+'</div><p class="hint">기본정보·편성·피난계획은 현재 자료입니다. '+APP.year+'년 당시 현황과 맞는지 확인해 주세요. 선택을 해제해도 이미 저장한 답변은 지워지지 않습니다.</p>';
  card(APP.year+'년 계획서에 어떤 자료를 반영할까요?',html);
  stage.querySelectorAll('.source-option input').forEach(el=>el.addEventListener('change',()=>dirty=true));
  const saveSelection=async()=>{
    const selected=[...stage.querySelectorAll('.source-option input:checked:not(:disabled)')].map(el=>el.value);
    if(!await send('sources',selected))return false;
    recordTurn(APP.sources.names.length?APP.sources.names.join(' · ')+' 반영':'직접 작성할게요');sourceSummary();return true;
  };
  saveCurrentAnswer=saveSelection;
  button('선택한 자료로 시작 →',async()=>{if(await saveSelection())resumeChat();},true);
  button('불러오지 않고 직접 작성',async()=>{if(!await send('sources',[]))return;recordTurn('직접 작성할게요');sourceSummary();resumeChat();});
}
function resumeChat(){const resume=codes.find(c=>!skipped(c)&&!data[c]._chat_complete)||'1';enter(resume);if(!data[resume]._chat_complete&&(data[resume]._chat_answers||[]).length){fieldIndex=0;askNext(false);}}
function button(label,fn,primary=false){const b=document.createElement('button');b.type='button';b.className='btn'+(primary?' primary':'');b.textContent=label;b.onclick=()=>{if(!busy)fn();};document.getElementById('actions').appendChild(b);return b;}
function reviewHtml(rows){return '<dl class="review">'+rows.map(f=>'<div><dt>'+esc(f.label)+'</dt><dd>'+esc(empty(value(f.key))?(answered(f.key)?'해당없음 / 추가 내용 없음':'미입력'):value(f.key))+'</dd></div>').join('')+'</dl>';}
function quickPresets(f,current){
  const items=[],seen=new Set(),manager=text(data['1'].mgr_name||'').trim();
  const add=(label,val)=>{val=String(val==null?'':val).trim();if(!val||seen.has(val))return;seen.add(val);items.push({label,value:val});};
  if(!empty(current))add('불러온 내용 사용',text(current));
  const fixed={
    recv_loc:[['1층 방재실','1층 방재실'],['관리사무소','관리사무소'],['경비실','경비실']],
    main_use:[['업무시설','업무시설'],['근린생활시설','근린생활시설'],['공장','공장'],['창고시설','창고시설'],['공동주택','공동주택']],
    structure:[['철근콘크리트조','철근콘크리트조'],['철골철근콘크리트조','철골철근콘크리트조'],['철골조','철골조']],
    roof:[['철근콘크리트 슬래브','철근콘크리트 슬래브'],['평지붕','평지붕'],['경사지붕','경사지붕']],
    finish:[['불연재료','불연재료'],['준불연재료','준불연재료'],['난연재료','난연재료']],
    wd_day:[['일반 주간근무','09:00~18:00'],['조기 근무','08:00~17:00'],['24시간 근무','24시간']],
    wd_night:[['야간 근무 없음','야간 근무 없음'],['일반 야간근무','18:00~09:00'],['24시간 근무','24시간']],
    hd_day:[['휴무','휴무'],['평일과 동일','평일과 동일'],['주간 당직','09:00~18:00']],
    hd_night:[['휴무','휴무'],['야간 근무 없음','야간 근무 없음'],['평일과 동일','평일과 동일']],
    r1_when:[['연 1회','매년 1회'],['상반기','매년 상반기'],['사용승인 월','매년 사용승인 월']],
    r2_when:[['연 1회','매년 1회'],['하반기','매년 하반기'],['사용승인 월','매년 사용승인 월']],
    r3_when:[['매월','매월 1일'],['분기마다','분기 1회'],['반기마다','반기 1회']],
    r1_who:[['소방안전관리자',manager||'소방안전관리자'],['전문점검업체','전문점검업체'],['관리사무소','관리사무소']],
    r2_who:[['소방안전관리자',manager||'소방안전관리자'],['전문점검업체','전문점검업체'],['관리사무소','관리사무소']],
    r3_who:[['소방안전관리자',manager||'소방안전관리자'],['관리사무소','관리사무소'],['시설관리 담당자','시설관리 담당자']],
    floor_exit:[['1층 주출입구','지상 1층 주출입구'],['주출입구 2개소','지상 1층 주출입구 2개소'],['주출입구와 비상구','지상 1층 주출입구 및 비상구']],
    route:[['계단 이용 후 집결지로','각 층 → 가까운 피난계단 → 1층 주출입구 → 외부 집결지'],['비상계단 우선 이용','각 층 → 가까운 비상계단 → 외부 출입구 → 집결지']],
    weak_plan:[['담당자 1:1 지원','층별 피난보조자를 지정하여 피난약자를 1:1로 지원한다.'],['안전구역 우선 이동','피난약자를 가까운 안전구역으로 먼저 이동시킨 후 구조대에 위치를 알린다.']],
    assembly:[['건물 앞 공터','건물 앞 공터'],['옥외 주차장','건물 외부 주차장'],['정문 앞 안전구역','정문 앞 안전구역']],
    t1_when:[['상·하반기','매년 상·하반기 각 1회'],['연 1회','매년 1회'],['분기 1회','분기 1회']],
    t1_who:[['전 직원','전 직원'],['자위소방대','자위소방대 전원'],['근무자 전체','근무자 전체']],
    t1_how:[['자체 종합훈련','자체 종합훈련'],['소방서 합동훈련','소방서 합동훈련'],['도상·실습훈련','도상훈련 및 실습훈련']],
    t2_when:[['연 1회','매년 1회'],['상반기','매년 상반기'],['하반기','매년 하반기']],
    t2_who:[['전 직원','전 직원'],['자위소방대','자위소방대 전원'],['신규 입사자 포함','전 직원 및 신규 입사자']],
    t2_how:[['집합교육','집합교육'],['시청각교육','시청각교육'],['이론·실습 병행','이론교육 및 실습교육']],
    t3_when:[['입사 시','신규 입사 시'],['배치 전','업무 배치 전'],['필요 시','필요 시 수시']],
    t3_who:[['신규 입사자','신규 입사자'],['신규 근무자','신규 배치 근무자'],['협력업체 포함','신규 입사자 및 협력업체 근무자']],
    t3_how:[['기초 안전교육','소방안전 기초교육'],['현장 안내','현장 피난로 및 소방시설 안내'],['이론·실습 병행','이론교육 및 실습교육']],
    s1:[['기본 경보·전파','발신기 작동 → 자동화재탐지설비 경보 → 비상방송으로 상황 전파']],
    s2:[['119 신고와 관계기관 통보','최초 발견자가 119에 신고하고 비상연락반이 관계기관과 관계인에게 통보']],
    s3:[['소화기·옥내소화전 사용','안전한 초기 화재에 한해 초기소화반이 소화기와 옥내소화전으로 대응']],
    s4:[['피난로 확보·대피 유도','피난유도반이 피난경로를 확보하고 가까운 비상구를 통해 집결지로 대피 유도']],
    s5:[['집결지 인원 확인','집결지에서 부서·층별 인원을 확인하고 미대피자를 파악하여 소방대에 보고']]
  };
  (fixed[f.key]||[]).forEach(x=>add(x[0],x[1]));
  const label=String(f.label||''),key=String(f.key||'');
  if(f.type==='date'){
    const d=new Date(),today=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');add('오늘',today);
  }
  if(f.type==='number'&&!/(area|height)/i.test(key)){['0','1','5','10'].forEach(v=>add(v,v));}
  if(f.type==='memo'){
    const suggested=suggestion(f.key);if(!empty(suggested))add('추천 내용 사용',text(suggested));
    const memo={
      '5':'각 층 재실자는 가까운 피난계단을 이용해 외부 집결지로 이동하고, 층별 담당자는 잔류자를 확인한 뒤 인원 현황을 보고한다.',
      '6':'화재신고, 상황전파, 초기소화, 피난유도, 응급처치 순으로 자체 훈련을 실시한다.',
      '7':'관리 구역과 담당 역할을 사전에 구분하고 비상 시 연락체계와 공동 대응 절차에 따라 조치한다.',
      '8':'관계 대상과 연락망을 공유하고 화재 발생 시 상황전파, 초기대응, 피난유도를 공동으로 실시한다.',
      '11':'화재 발생 시 인명안전을 최우선으로 상황을 전파하고 초기 대응 후 신속히 대피한다.',
      '12':'위험물은 지정된 장소에 보관하고 취급 전 안전수칙과 소화설비 위치를 확인한다.',
      '13':'교육·훈련 결과와 개선사항을 기록하고 다음 교육계획에 반영한다.',
      '15':'피난안내도와 소방시설 사용방법을 잘 보이는 장소에 게시하고 정기적으로 상태를 확인한다.'
    };if(memo[code])add('일반적인 내용 사용',memo[code]);
  }
  if(!fixed[f.key]){
    if(/담당|책임|관리자|실시자/.test(label)&&manager)add('소방안전관리자',manager);
    if(/대상|참여/.test(label)){add('전 직원','전 직원');add('자위소방대','자위소방대 전원');}
    if(/방법|방식/.test(label)){add('집합교육','집합교육');add('실습 중심','실습 중심으로 실시');}
    if(/시기|일정|주기/.test(label)){add('연 1회','매년 1회');add('반기 1회','반기 1회');add('필요 시','필요 시 수시');}
    if(/장소|위치|집결/.test(label)){add('관리사무소','관리사무소');add('건물 앞 공터','건물 앞 공터');}
    if(/유무|여부/.test(label)){add('있음','있음');add('없음','없음');}
  }
  return items.slice(0,6);
}
function renderQuickAnswers(area,input,f,current,onPick,onManual){
  const picks=quickPresets(f,current),box=document.createElement('div');box.className='quick-answer';
  const label=document.createElement('span');label.className='quick-answer__label';label.textContent='자주 쓰는 답변';
  const choices=document.createElement('div');choices.className='quick-answer__choices';box.append(label,choices);
  const buttons=[];
  picks.forEach(p=>{const b=document.createElement('button');b.type='button';b.className='option'+(p.value.length>45?' option--long':'');b.textContent=p.label;b.title=p.label===p.value?'':p.value;b.onclick=async()=>{if(busy)return;input.value=p.value;input.dispatchEvent(new Event('input',{bubbles:true}));b.classList.add('on');if(onPick)await onPick();};choices.append(b);buttons.push([b,p.value]);});
  const manual=document.createElement('button');manual.type='button';manual.className='btn btn--sm quick-answer__manual';manual.textContent='✏️ 직접 입력';manual.onclick=()=>{box.hidden=true;input.hidden=false;if(onManual)onManual();input.focus();try{input.select();}catch(e){}};choices.append(manual);
  const sync=()=>{buttons.forEach(([b,val])=>b.classList.toggle('on',input.value.trim()===val));};
  input.classList.add('direct-answer-input');input.hidden=true;input.addEventListener('input',sync);area.insertBefore(box,input);sync();
}
async function send(act,patch={}) {
  if(busy)return false;busy=true;
  stage.querySelectorAll('button,input,textarea').forEach(el=>el.disabled=true);nav.disabled=true;savedStatus.textContent='저장 중…';
  const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),20000);
  try{
    const body = new FormData();body.set('csrf',APP.csrf);body.set('code',code);body.set('act',act);body.set('patch',JSON.stringify(patch));
    const r = await fetch(location.href,{method:'POST',body,credentials:'same-origin',signal:controller.signal});
    const j = await r.json();if(!r.ok || !j.ok)throw new Error(j.error || '저장하지 못했습니다.');
    data = j.sections;if(j.sources){APP.sources=j.sources;APP.selection=j.selection;APP.selectionSet=true;}dirty=false;updateProgress();savedStatus.textContent='저장되었습니다';return true;
  }catch(e){document.getElementById('error').textContent=e.message || '연결을 확인하고 다시 시도해 주세요.';savedStatus.textContent='저장되지 않았습니다. 현재 답변을 다시 저장해 주세요.';return false;}
  finally{clearTimeout(timeout);busy=false;nav.disabled=!APP.selectionSet;stage.querySelectorAll('button,input,textarea').forEach(el=>{if(!el.closest('.source-option') || APP.sources.groups[el.value]?.available)el.disabled=false;});}
}
function enter(c){code=c;fieldIndex=0;dirty=false;reviewAll=false;updateProgress();
  if(skipped(c)){card('일반현황에서 해당없음으로 선택한 항목입니다.');button('다음 항목',nextSection,true);return;}
  const known=fields().filter(f=>!empty(value(f.key)));
  let note='';
  if(code==='3') note='<div class="section-note">3급 정기점검은 사용승인 월의 작동점검을 기본으로 제안합니다. 종합점검은 예외 대상이면 포함으로 바꿔주세요. 외관점검 매월 1일은 자체 관리 일정입니다. 최초점검과 법정 대상 여부는 별도 확인이 필요합니다.</div>';
  card(known.length?'이 내용이 맞나요?':'함께 채워볼까요?',note+'<p class="hint">'+(known.length?'이미 입력한 내용과 제안값을 모았어요. 맞으면 남은 질문만 답하면 됩니다.':'모르는 내용은 나중에 답해도 괜찮아요.')+'</p>'+reviewHtml(known));
  if(known.length)button('네, 맞아요',async()=>{const p={};known.forEach(f=>p[f.key]=value(f.key));if(await send('answer',p)){recordTurn('확인했어요. 이 내용으로 반영할게요.');askNext(false);}},true);
  button(known.length?'수정할게요':'시작하기',()=>{fieldIndex=0;askNext(true);},!known.length);
}
function askNext(all){if(all!==undefined)reviewAll=all;
  const list=fields();while(fieldIndex<list.length && !reviewAll && answered(list[fieldIndex].key))fieldIndex++;
  if(fieldIndex>=list.length){sectionEnd();return;}
  const f=list[fieldIndex], v=value(f.key);
  const prompts={name:'건물 이름이 어떻게 되나요?',addr:'건물 주소를 알려주세요.',grade:'소방안전관리 등급을 선택해 주세요.',approval:'건물 사용승인일은 언제인가요?',mgr_name:'소방안전관리자 이름을 알려주세요.'};
  card(prompts[f.key] || (f.label.endsWith('?')||f.label.endsWith('.')?f.label:f.label+' 내용을 알려주세요.'),'<div class="question-meta">이 항목의 '+(fieldIndex+1)+'번째 질문 / '+list.length+'</div><div id="inputArea"></div>'+(f.hint?'<p class="hint">'+esc(f.hint)+'</p>':'')+(!empty(v)?'<p class="hint">내용이 맞으면 바로 다음으로 넘어가세요.</p>':''));
  const area=document.getElementById('inputArea');let read,quickInput=null;
  if(f.type==='multi' || f.type==='choice'){
    const opts=[...f.options];if(f.type==='multi' && Array.isArray(v))v.forEach(x=>{if(!opts.includes(x))opts.push(x);});
    area.className='options';area.setAttribute('role','group');area.setAttribute('aria-label',f.label);
    opts.forEach(o=>{const label=document.createElement('label');label.className='option';const input=document.createElement('input');input.type=f.type==='multi'?'checkbox':'radio';input.name='answer';input.value=o;input.checked=f.type==='multi'?Array.isArray(v)&&v.includes(o):v===o;label.append(input,document.createTextNode(o));area.append(label);
      input.addEventListener('change',()=>{dirty=true;if(f.type==='multi'&&input.checked){area.querySelectorAll('input').forEach(other=>{if(other!==input&&(o==='해당없음'||other.value==='해당없음'))other.checked=false;});}if(f.type==='choice')setTimeout(()=>submit(),0);});
      label.addEventListener('click',e=>{if(f.type==='choice'&&e.target===label&&input.checked)setTimeout(()=>submit(),0);});
    });read=()=>{const vs=[...area.querySelectorAll('input:checked')].map(i=>i.value);return f.type==='multi'?vs:vs[0]||'';};
  }else{
    const input=document.createElement(f.type==='memo'?'textarea':'input');if(f.type!=='memo')input.type=['date','number'].includes(f.type)?f.type:'text';if(f.type==='number'){input.min='0';input.step='any';}input.value=text(v);input.setAttribute('aria-label',f.label);area.append(input);input.addEventListener('input',()=>dirty=true);quickInput=input;read=()=>input.value.trim();
  }
  const submit=async(blank=false,advance=true)=>{
    const val=blank?(f.type==='multi'?[]:''):read();
    if(!blank && f.type!=='multi' && empty(val)){document.getElementById('error').textContent='답변을 입력하거나 아래에서 해당없음 또는 잘 모르겠어요를 선택해 주세요.';return false;}
    if(!blank && [...area.querySelectorAll('input')].some(i=>!i.checkValidity())){document.getElementById('error').textContent='입력 형식을 확인해 주세요.';return false;}
    if(await send('answer',{[f.key]:val})){recordTurn(empty(val)?'해당없음':val);if(advance){fieldIndex++;askNext();}return true;}return false;
  };
  saveCurrentAnswer=()=>submit(false,false);
  if(fieldIndex>0)button('이전',()=>{if(dirty&&!confirm('입력 중인 답변을 저장하지 않고 이전 질문으로 갈까요?'))return;dirty=false;fieldIndex--;askNext(true);});
  let nextBtn=null;if(f.type!=='choice'){nextBtn=button(f.type==='multi'?'선택 완료 →':'다음 →',()=>submit(),true);if(quickInput)nextBtn.hidden=true;}
  const extra=document.createElement('div');extra.className='answer-extra';extra.setAttribute('role','group');extra.setAttribute('aria-label','다른 답변 선택');
  const extraButtons=document.createElement('div');extraButtons.className='extra-buttons';extra.append(extraButtons);
  document.getElementById('actions').before(extra);
  if(!(code==='1'&&['name','addr','mgr_name','grade'].includes(f.key))){const none=button('해당없음',()=>submit(true));extraButtons.append(none);}
  const later=button('잘 모르겠어요',()=>{if(dirty&&!confirm('아직 저장하지 않은 답변이 있습니다. 건너뛸까요?'))return;dirty=false;recordTurn('잘 모르겠어요. 나중에 답할게요.');fieldIndex++;askNext();});later.title='미확인으로 남겨두고 나중에 답할 수 있어요';extraButtons.append(later);
  const help=document.createElement('span');help.className='save-hint';help.textContent=f.type==='choice'?'답변을 누르면 바로 저장돼요':f.type==='multi'?'여러 답변을 고른 뒤 선택 완료를 눌러주세요':'일반 답변을 누르면 바로 다음으로 넘어가요';document.getElementById('actions').append(help);
  if(quickInput){renderQuickAnswers(area,quickInput,f,v,()=>submit(),()=>{if(nextBtn)nextBtn.hidden=false;help.textContent='내용을 적은 뒤 다음을 눌러주세요';});if(f.type!=='memo')quickInput.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();submit();}});}
  stage.querySelector('.question').focus({preventScroll:true});
}
function sectionEnd(){
  const missing=fields().filter(f=>!answered(f.key));
  card(missing.length?'아직 확인할 내용이 남아 있어요.':'이 항목의 내용을 확인해 주세요.',reviewHtml(fields())+(missing.length?'<p class="hint">미확인: '+missing.map(f=>esc(f.label)).join(' · ')+'</p>':''));
  if(!missing.length)button('이 항목 확인 완료',async()=>{if(await send('confirm'))nextSection();},true);
  else button('남은 질문 답하기',()=>{fieldIndex=0;askNext(false);},true);
  button('처음부터 확인·수정',()=>{fieldIndex=0;askNext(true);});button('다음 항목',nextSection);
}
function nextSection(){reviewAll=false;const i=codes.indexOf(code);const next=codes.slice(i+1).find(c=>!skipped(c));if(next)enter(next);else finish();}
function finish(){const pending=codes.filter(c=>!skipped(c)&&!data[c]._chat_complete);card(pending.length?'미확인 항목을 이어서 작성할 수 있어요.':'전체 문답 내용을 확인했습니다.', '<p class="hint">'+(pending.length?'미확인 항목: '+pending.map(c=>esc(c+'. '+APP.titles[c])).join(' · '):'최종 서류를 확인한 뒤 인쇄해 주세요. 문답 완료가 법정 적합성을 보증하는 것은 아닙니다.')+'</p>');
  pending.forEach(c=>button(c+'. '+APP.titles[c],()=>enter(c)));
  const actions=document.getElementById('actions');[['표에서 최종 확인',APP.editUrl],['인쇄 · PDF',APP.printUrl],['목록으로',APP.listUrl]].forEach(([label,href],index)=>{const a=document.createElement('a');a.className='btn'+(index===0?' btn--pri':'');a.href=href;a.textContent=label;if(label==='인쇄 · PDF'){a.target='_blank';a.rel='noopener';}actions.append(a);});
}
nav.addEventListener('change',()=>{const selected=nav.value;if(dirty&&!confirm('저장하지 않은 답변이 있습니다. 다른 항목으로 이동할까요?')){nav.value=code;return;}enter(selected);});
window.addEventListener('beforeunload',e=>{if(dirty||busy){e.preventDefault();e.returnValue='';}});
function closePopup(){
  window.parent.postMessage({type:'fp-modal-request-close'},location.origin);
}
// 같은 출처의 상위 팝업이 직접 확인합니다. 중첩 iframe의 메시지 발신 창 차이를 피합니다.
window.fpModalState=()=>({busy,dirty});
window.fpModalDiscard=()=>{dirty=false;};
window.fpModalSave=async()=>{if(busy)return false;if(!dirty)return true;return saveCurrentAnswer ? await saveCurrentAnswer() : false;};
window.addEventListener('message',event=>{
  if(event.origin!==location.origin || event.source!==window.parent)return;
  if(event.data && event.data.type==='fp-modal-request-close')closePopup();
});
document.addEventListener('keydown',event=>{
  if(event.key==='Escape' && new URLSearchParams(location.search).get('modal')==='1'){event.preventDefault();closePopup();}
});
document.addEventListener('click',event=>{
  const link=event.target.closest('a[href]');
  if(link && new URLSearchParams(location.search).get('modal')==='1' && new URL(link.href).pathname==='/fire_plan.php'){
    event.preventDefault();closePopup();
  }
});
document.getElementById('changeSources').addEventListener('click',()=>{if(busy)return;if(dirty&&!confirm('저장하지 않은 답변을 두고 자료 선택으로 이동할까요?'))return;dirty=false;chooseSources();});
sourceSummary();
const savedTurns=[];
codes.forEach(c=>(data[c]._chat_answers||[]).forEach(k=>{const f=APP.schema[c][k];if(f)savedTurns.push([f.label,data[c][k]]);}));
if(savedTurns.length){
  const previous=document.createElement('details');previous.className='saved-chat';
  const summary=document.createElement('summary');summary.textContent='이전에 저장한 답변 '+savedTurns.length+'개 보기';previous.append(summary);
  savedTurns.forEach(([q,a])=>{const turn=document.createElement('div');turn.className='chat-turn';const bot=document.createElement('div');bot.className='msg';bot.innerHTML='<div class="msg__av" aria-hidden="true">🚒</div><div class="msg__b chat-bot"></div>';bot.querySelector('.chat-bot').textContent=q;const me=document.createElement('div');me.className='msg msg--me';me.innerHTML='<div class="msg__av" aria-hidden="true">🙂</div><div class="msg__b chat-user"></div>';me.querySelector('.chat-user').textContent=empty(a)?'해당없음':text(a);turn.append(bot,me);previous.append(turn);});document.getElementById('chatLog').append(previous);
}
if(APP.selectionSet)resumeChat();else chooseSources();
</script>
<?php if (is_file(__DIR__.'/admin_quickmemo_widget.php')) require_once __DIR__.'/admin_quickmemo_widget.php'; ?>
</body></html>
