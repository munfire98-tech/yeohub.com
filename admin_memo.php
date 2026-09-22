<?php
// admin_memo.php — 관리자 전용 메모 (목표/프로세스 + 할 일 체크리스트)
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

// 관리자만 접근
if (!is_admin() || !empty($_SESSION['_imp']) || !empty($_SESSION['_manager_edit'])) { header('Location: /admin_login.php'); exit; }

$FILE = __DIR__ . '/data/admin_memo.json';
function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function save_json(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp=tempnam(dirname($f),'.memo-');if($tmp===false)return false;
  $json=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||file_put_contents($tmp,$json,LOCK_EX)===false){@unlink($tmp);return false;}
  $ok=rename($tmp,$f);if(!$ok)@unlink($tmp);return $ok;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$data = load_json($FILE);
$saved = false;
$saveError = false;

// 저장 처리 (전체 폼을 한 번에 저장)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  if (!is_string($_POST['csrf']??null)||!hash_equals($CSRF, $_POST['csrf'])) { http_response_code(403); exit('CSRF'); }

  // 할 일: 제목 배열 + 체크 배열을 합쳐 정리
  $todoTexts = $_POST['todo_text'] ?? [];
  $todoDone  = $_POST['todo_done'] ?? [];   // 체크된 인덱스만 값이 옴
  $todos = [];
  foreach ($todoTexts as $i => $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    $todos[] = ['text' => $t, 'done' => isset($todoDone[$i])];
  }

  $data = array_merge($data,[
    'big_goal'   => trim($_POST['big_goal'] ?? ''),
    'small_goal' => trim($_POST['small_goal'] ?? ''),
    'process'    => trim($_POST['process'] ?? ''),
    'todos'      => $todos,
    'updated'    => date('Y-m-d H:i'),
    'bottleneck'=>trim((string)($_POST['bottleneck']??'')),
    'next_action'=>trim((string)($_POST['next_action']??'')),
    'success_metric'=>trim((string)($_POST['success_metric']??'')),
    'deadline'=>preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)($_POST['deadline']??''))?(string)$_POST['deadline']:'',
  ]);
  $saved = save_json($FILE, $data);
  $saveError = !$saved;
}

$bigGoal   = $data['big_goal'] ?? '';
$smallGoal = $data['small_goal'] ?? '';
$process   = $data['process'] ?? '';
$todos     = $data['todos'] ?? [];
$updated   = $data['updated'] ?? '';
$nick = $_SESSION['nickname'] ?? '관리자';
$todoCount=count(array_filter($todos,fn($t)=>trim((string)($t['text']??''))!==''));
$doneCount=count(array_filter($todos,fn($t)=>!empty($t['done'])&&trim((string)($t['text']??''))!==''));
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>실행 보드 · YEOHUB</title>
<style>
:root{--bg:#f3f5f7;--ink:#182632;--mut:#637381;--line:#dde4e8;--green:#176c50;--lime:#d7f279}*{box-sizing:border-box}body{margin:0;background:var(--bg);font:14px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI','Apple SD Gothic Neo',sans-serif;color:var(--ink)}button,input,textarea{font:inherit}button,a,input,textarea{outline-offset:4px}a{color:inherit;text-decoration:none}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible,textarea:focus-visible{outline:2px solid var(--green)}
nav{background:#fff;border-bottom:1px solid var(--line)}.nav-inner{max-width:1440px;margin:auto;min-height:60px;padding:12px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{font-size:15px;letter-spacing:.1em;font-weight:800}.brand span{font-weight:500;font-size:10px;letter-spacing:.1em;color:var(--mut);margin-left:12px}.nav-links{display:flex;gap:20px;align-items:center;font-size:12px;color:var(--mut)}.nav-links a:hover{color:var(--green)}main{max-width:1440px;margin:auto;padding:27px 32px 110px}.page-top{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:22px}.eyebrow{font:600 10px/1.5 system-ui;letter-spacing:.18em;color:var(--mut)}h1{font-size:29px;line-height:1.3;letter-spacing:-1px;margin:5px 0}h2{font-size:16px;margin:0;letter-spacing:-.4px}p{margin:0}.subtitle{color:var(--mut);font-size:12px}.date{font-size:12px;color:var(--mut);text-align:right}.date b{display:block;font-size:14px;color:var(--ink)}
.focus{display:grid;grid-template-columns:1fr 200px;gap:32px;background:#192f2b;color:white;padding:25px 28px;border-radius:15px;margin-bottom:20px;box-shadow:0 8px 24px #132d2510}.focus .eyebrow{color:var(--lime)}.focus label{display:block;font-size:11px;color:#bed0c6;margin-bottom:5px}.focus-title{display:flex;align-items:center;gap:8px;margin-bottom:12px}.focus-dot{width:7px;height:7px;border-radius:50%;background:var(--lime)}.focus textarea{display:block;width:100%;height:62px;resize:vertical;border:0;border-bottom:1px solid #49615a;background:transparent;border-radius:0;color:#fff;padding:3px 0;font-size:21px;font-weight:600;line-height:1.5}.focus textarea::placeholder{color:#afc1b8;font-weight:400}.focus-right{border-left:1px solid #49615a;padding-left:26px}.focus input[type=date]{width:100%;background:#253f36;color:#fff;color-scheme:dark;border:1px solid #506459;border-radius:7px;padding:7px 9px;font-size:12px}.progress-number{font-size:30px;line-height:1.2;font-weight:650;margin-top:15px}.progress-number small{font-size:11px;color:#bed0c6;font-weight:400;margin-left:8px}.progress-track{height:4px;border-radius:3px;background:#435a50;margin:9px 0 5px;overflow:hidden}.progress-track span{display:block;height:100%;background:var(--lime);width:0;transition:width .2s}.progress-copy{font-size:10px;color:#bed0c6}
.board{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:20px;align-items:start}.column{display:grid;gap:16px}.panel{padding:20px 22px;background:#fff;border:1px solid var(--line);border-radius:12px}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:16px}.step{font:600 10px/1.5 system-ui;color:#82928f;letter-spacing:.12em;margin-bottom:3px}.panel-note{font-size:11px;color:var(--mut)}.field{display:block;margin-top:13px}.field>span{display:block;font-size:11px;font-weight:600;color:#4b5d68;margin-bottom:6px}textarea,.input{width:100%;border:1px solid #e0e6eb;border-radius:8px;background:#f9fafb;color:var(--ink);padding:10px 12px;font-size:13px}textarea{resize:vertical;min-height:72px}textarea::placeholder,input::placeholder{color:#84929e}textarea:focus,.input:focus{background:#fff;border-color:#6a9b87}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.bottleneck{border-left:3px solid #c68c43}.bottleneck textarea{background:#fcfaf5}.process textarea{min-height:100px}.principles{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.principles span{font-size:10px;padding:4px 8px;background:#f0f4f2;border-radius:5px;color:#5e7568}.principles span:last-child{background:#eaf2e9}
.tasks-panel{padding-bottom:16px}.task-top{display:flex;align-items:center;gap:9px}.task-count{padding:3px 7px;background:#eef4f1;color:#287054;font-size:11px;border-radius:6px}.task-help{font-size:11px;color:var(--mut);margin:-6px 0 15px}.todos{display:flex;flex-direction:column;gap:8px}.todo{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:9px;align-items:center;border:1px solid #e3e8ec;border-radius:9px;padding:12px 10px;background:#fff}.todo:first-child:not(.done){border-color:#8bb49c;background:#f6faf6}.todo input[type=checkbox]{width:18px;height:18px;accent-color:var(--green);cursor:pointer}.todo input[type=text]{min-width:0;width:100%;border:0;border-bottom:1px solid transparent;background:transparent;padding:4px 0;color:var(--ink);font-size:13px}.todo.done input[type=text]{text-decoration:line-through;color:#8a959d}.todo.done{background:#f9fafb}.row-actions{display:flex;gap:1px}.row-actions button{border:0;background:transparent;color:#768593;width:26px;height:28px;border-radius:5px;font-size:14px}.row-actions button:hover{background:#edf1f5;color:#273e4a}.row-actions .del:hover{background:#fff0ef;color:#b0443c}.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 13px;border:1px solid #d9e1e6;border-radius:8px;background:white;color:#38524a;font-size:12px;font-weight:600}.btn:hover{background:#f1f6f3}.add{margin-top:13px;width:100%;border-style:dashed;background:#fafcfb}.small-link{border:0;background:none;color:#6c7d88;font-size:11px;padding:6px}.review{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #e7ecef;margin-top:16px;padding-top:12px;font-size:11px;color:var(--mut)}.savebar{position:fixed;bottom:0;left:0;right:0;z-index:20;background:#ffffffee;backdrop-filter:blur(8px);border-top:1px solid var(--line)}.save-inner{max-width:1440px;margin:auto;padding:13px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px}.save-meta{font-size:11px;color:var(--mut)}.save-meta strong{display:block;color:#415851;font-size:12px}.save{background:var(--green);border-color:var(--green);color:white;padding:11px 25px}.save:hover{background:#10563f}.toast{border:1px solid #a5c9b2;background:#eff8f2;padding:11px 15px;border-radius:8px;margin-bottom:16px;color:#226145;font-size:12px}.error{background:#fff3ef;border-color:#e9bbb0;color:#963e2d}[hidden]{display:none!important}
@media(max-width:900px){.board{grid-template-columns:1fr}.focus{grid-template-columns:1fr 170px;gap:20px}.focus-right{padding-left:20px}.nav-inner,main,.save-inner{padding-left:20px;padding-right:20px}}@media(max-width:550px){main{padding-top:20px}.nav-links{gap:10px}.nav-links .nickname{display:none}.brand span{display:none}.focus{grid-template-columns:1fr;padding:20px}.focus-right{border-left:0;border-top:1px solid #49615a;padding:15px 0 0;display:grid;grid-template-columns:1fr 1fr;column-gap:20px}.progress-number{margin-top:0}.focus-right .deadline{grid-row:1/4}.focus textarea{font-size:18px}.field-grid{grid-template-columns:1fr;gap:0}.page-top .date{display:none}.panel{padding:18px 16px}h1{font-size:25px}.todo{gap:5px;padding:10px 7px}.row-actions button{width:24px}.save{padding:10px 14px}.save-inner{gap:10px}.nav-inner,main,.save-inner{padding-left:14px;padding-right:14px}}@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style></head><body>
<nav><div class="nav-inner"><a class="brand" href="/index.php">YEOHUB <span>ADMIN / EXECUTION</span></a><div class="nav-links"><span class="nickname"><?=h($nick)?>님</span><a href="/admin_manager_payouts.php">매니저 관리 ↗</a><a href="/index.php">메인</a></div></div></nav>
<main><div class="page-top"><div><div class="eyebrow">FOCUS ON WHAT MATTERS</div><h1>생각은 명확하게, 실행은 빠르게.</h1><p class="subtitle">목표를 정하고, 막힌 곳을 찾아, 가장 중요한 일부터 끝내세요.</p></div><div class="date"><b><?=h(date('Y.m.d'))?></b>관리자 실행 보드</div></div>
<?php if($saved):?><p class="toast" role="status">변경사항을 저장했습니다. <?=h($updated)?></p><?php endif;?><?php if($saveError):?><p class="toast error" role="alert">저장하지 못했습니다. 작성 내용은 화면에 남아 있습니다. 다시 저장해 주세요.</p><?php endif;?>
<form method="post" id="memoForm"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><input type="hidden" name="action" value="save">
<section class="focus" aria-label="최우선 행동"><div><div class="focus-title"><span class="focus-dot"></span><span class="eyebrow">ONE THING / 지금 가장 중요한 일</span></div><label for="next_action">오늘 끝낼 단 하나의 행동</label><textarea name="next_action" id="next_action" placeholder="예: 출금 신청부터 지급 완료까지 직접 검증하기"><?=h($data['next_action']??'')?></textarea></div><div class="focus-right"><div class="deadline"><label for="deadline">목표 마감일</label><input type="date" id="deadline" name="deadline" value="<?=h($data['deadline']??'')?>"></div><div class="progress-number"><span id="progress">0%</span><small>실행 완료율</small></div><div class="progress-track" role="progressbar" aria-label="실행 완료율" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progress-fill"></span></div><p id="progress-copy" class="progress-copy"></p></div></section>
<div class="board"><div class="column"><section class="panel"><div class="panel-head"><div><div class="step">01 / DIRECTION</div><h2>무엇을 달성할 것인가</h2></div></div><label class="field"><span>큰 목표 · 도달할 방향</span><textarea name="big_goal" placeholder="사업에서 바꾸고 싶은 결과를 적으세요."><?=h($bigGoal)?></textarea></label><div class="field-grid"><label class="field"><span>이번 단계의 목표</span><textarea name="small_goal" placeholder="이번 주에 만들 구체적인 결과"><?=h($smallGoal)?></textarea></label><label class="field"><span>완료 기준 · 숫자로 확인하기</span><textarea name="success_metric" placeholder="예: 실제 유저 5명이 도움 없이 가입 완료"><?=h($data['success_metric']??'')?></textarea></label></div></section>
<section class="panel bottleneck"><div class="step">02 / FIRST PRINCIPLES</div><h2>지금 막힌 진짜 이유</h2><label class="field"><span>확인된 사실 → 가정 → 가장 작은 해결 실험</span><textarea name="bottleneck" placeholder="무엇이 사실인가? 꼭 필요한 조건인가? 무엇을 먼저 확인할까?"><?=h($data['bottleneck']??'')?></textarea></label></section>
<section class="panel process"><div class="step">03 / SIMPLIFY</div><h2>더 적은 단계로 끝내기</h2><label class="field"><span>진행 방법 · 다음 실험</span><textarea name="process" placeholder="없앨 일과 남길 일을 구분하고, 실행 순서를 적으세요."><?=h($process)?></textarea></label><div class="principles"><span>01 필요성 질문</span><span>02 불필요한 일 제거</span><span>03 단순화</span><span>04 빠른 검증</span><span>05 반복 후 자동화</span></div></section></div>
<section class="panel tasks-panel"><div class="panel-head"><div><div class="step">04 / EXECUTE</div><div class="task-top"><h2>실행 목록</h2><span id="task-count" class="task-count"></span></div></div><button type="button" class="small-link" id="toggle-completed">완료 항목 숨기기</button></div><p class="task-help">위쪽부터 우선순위입니다. ↑ ↓ 버튼으로 순서를 바꿀 수 있습니다.</p><div class="todos" id="todos">
<?php foreach($todos?:[['text'=>'','done'=>false]] as $i=>$t):?><div class="todo <?=!empty($t['done'])?'done':''?>"><input type="checkbox" name="todo_done[<?=$i?>]" aria-label="할 일 완료" <?=!empty($t['done'])?'checked':''?>><input type="text" name="todo_text[<?=$i?>]" value="<?=h($t['text']??'')?>" placeholder="구체적인 행동을 입력하세요" aria-label="할 일"><div class="row-actions"><button type="button" data-move="up" aria-label="우선순위 올리기">↑</button><button type="button" data-move="down" aria-label="우선순위 내리기">↓</button><button type="button" class="del" aria-label="할 일 삭제">×</button></div></div><?php endforeach;?></div><button type="button" class="btn add" id="add-todo">＋ 실행할 일 추가</button><div class="review"><span>완료 체크와 순서 변경 후 저장하세요.</span><span id="remaining"></span></div></section></div>
</form></main><div class="savebar"><div class="save-inner"><div class="save-meta"><strong id="save-state"><?=$saveError?'저장 실패 · 다시 저장해 주세요':'변경사항을 한 번에 저장합니다'?></strong><span><?= $updated?'마지막 저장 '.h($saveError?($updated.' (실패)'):$updated):'아직 저장된 메모가 없습니다'?></span></div><button class="btn save" type="submit" form="memoForm">변경사항 저장 ↗</button></div></div>
<script>
(()=>{const form=document.getElementById('memoForm'),box=document.getElementById('todos'),state=document.getElementById('save-state');let dirty=<?= $saveError?'true':'false' ?>,hideDone=false;function mark(){dirty=true;state.textContent='저장하지 않은 변경사항이 있습니다';}function rows(){return [...box.querySelectorAll('.todo')];}function update(){let total=0,done=0;rows().forEach((row,i)=>{const cb=row.querySelector('[type=checkbox]'),text=row.querySelector('[type=text]');cb.name=`todo_done[${i}]`;text.name=`todo_text[${i}]`;row.classList.toggle('done',cb.checked);row.hidden=hideDone&&cb.checked;if(text.value.trim()){total++;if(cb.checked)done++;}});const pct=total?Math.round(done/total*100):0;document.getElementById('progress').textContent=pct+'%';document.getElementById('progress-fill').style.width=pct+'%';document.querySelector('[role=progressbar]').setAttribute('aria-valuenow',pct);document.getElementById('progress-copy').textContent=done+' / '+total+'개 완료';document.getElementById('task-count').textContent=total+'개';document.getElementById('remaining').textContent='남은 일 '+(total-done)+'개';}
const template=box.firstElementChild.cloneNode(true);function add(){const row=template.cloneNode(true);row.querySelector('[type=checkbox]').checked=false;row.querySelector('[type=text]').value='';row.hidden=false;box.append(row);mark();update();row.querySelector('[type=text]').focus();}document.getElementById('add-todo').addEventListener('click',add);box.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;const row=b.closest('.todo');if(b.classList.contains('del')){row.remove();if(!box.children.length)add();}else if(b.dataset.move==='up'&&row.previousElementSibling)box.insertBefore(row,row.previousElementSibling);else if(b.dataset.move==='down'&&row.nextElementSibling)box.insertBefore(row.nextElementSibling,row);mark();update();});form.addEventListener('input',()=>{mark();update();});form.addEventListener('change',()=>{mark();update();});document.getElementById('toggle-completed').addEventListener('click',e=>{hideDone=!hideDone;e.target.textContent=hideDone?'완료 항목 보기':'완료 항목 숨기기';update();});form.addEventListener('submit',()=>{update();dirty=false;});window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){e.preventDefault();form.requestSubmit();}});update();})();
</script></body></html>
