<?php
/* 관리자 공통 빠른 메모 — clients_mini.php와 data/quickmemo.json 공유 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.cookie_httponly', '1');
  session_start();
}

if (!function_exists('aqm_is_admin')) {
  function aqm_is_admin(): bool {
    return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
        || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
  }
}
if (!function_exists('aqm_read')) {
  function aqm_read(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string)@file_get_contents($file), true);
    return is_array($data) ? $data : [];
  }
}
if (!function_exists('aqm_write')) {
  function aqm_write(string $file, array $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
      @unlink($tmp); return false;
    }
    return @rename($tmp, $file);
  }
}

$aqmFile = __DIR__ . '/data/quickmemo.json';
if (empty($_SESSION['admin_qmemo_csrf'])) $_SESSION['admin_qmemo_csrf'] = bin2hex(random_bytes(16));

/* 이 파일로 직접 POST하면 메모 저장 API로 동작 */
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  if (!aqm_is_admin()) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'admin'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (!hash_equals((string)$_SESSION['admin_qmemo_csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'csrf'], JSON_UNESCAPED_UNICODE); exit;
  }
  $text = (string)($_POST['text'] ?? '');
  $text = function_exists('mb_substr') ? mb_substr($text, 0, 20000, 'UTF-8') : substr($text, 0, 60000);
  $memo = ['text'=>$text, 'updated'=>date('Y-m-d H:i')];
  $ok = aqm_write($aqmFile, $memo);
  echo json_encode(['ok'=>$ok, 'updated'=>$memo['updated']], JSON_UNESCAPED_UNICODE); exit;
}

/* 일반 회원에게는 출력하지 않고 한 화면에 한 번만 출력 */
if (!aqm_is_admin() || defined('ADMIN_QUICKMEMO_RENDERED')) return;
define('ADMIN_QUICKMEMO_RENDERED', true);

$aqm = aqm_read($aqmFile);
$aqmCsrf = (string)$_SESSION['admin_qmemo_csrf'];
$aqmH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<style>
#adminQuickMemo{position:fixed;right:18px;bottom:18px;z-index:2147483000;font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
#adminQuickMemo *{box-sizing:border-box}
#adminQuickMemo .aqm-tab{display:inline-flex;align-items:center;gap:7px;padding:11px 17px;border:0;border-radius:999px;background:#1a2436;color:#fff;font:700 13.5px inherit;cursor:pointer;box-shadow:0 8px 24px rgba(15,25,50,.28)}
#adminQuickMemo .aqm-tab:hover{transform:translateY(-1px)}
#adminQuickMemo .aqm-dot{width:7px;height:7px;border-radius:50%;background:#fbbf24}
#adminQuickMemo .aqm-panel{position:absolute;right:0;bottom:54px;width:min(340px,calc(100vw - 36px));display:none;overflow:hidden;background:#fffbeb;border:1px solid #fde68a;border-radius:14px;box-shadow:0 16px 44px rgba(15,25,50,.22)}
#adminQuickMemo.aqm-open .aqm-panel{display:block}
#adminQuickMemo .aqm-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fef3c7;border-bottom:1px solid #fde68a}
#adminQuickMemo .aqm-head b{font-size:13px;color:#92400e}
#adminQuickMemo .aqm-state{font-size:11px;color:#b45309}
#adminQuickMemo textarea{display:block;width:100%;min-height:180px;max-height:50vh;padding:12px 14px;border:0;background:transparent;resize:vertical;outline:none;color:#451a03;font:13.5px/1.7 inherit}
#adminQuickMemo textarea::placeholder{color:#d97706;opacity:.55}
@media print{#adminQuickMemo{display:none!important}}
</style>
<div id="adminQuickMemo">
  <div class="aqm-panel">
    <div class="aqm-head">
      <b>📌 빠른 메모</b>
      <span class="aqm-state"><?=!empty($aqm['updated']) ? $aqmH($aqm['updated']).' 저장' : ''?></span>
    </div>
    <textarea placeholder="중요한 내용을 적어두세요. 자동으로 저장됩니다."><?=$aqmH($aqm['text'] ?? '')?></textarea>
  </div>
  <button type="button" class="aqm-tab"><span class="aqm-dot"></span>메모</button>
</div>
<script>
(function(){
  var root=document.getElementById('adminQuickMemo');
  if(!root||root.dataset.ready==='1')return;
  root.dataset.ready='1';
  var tab=root.querySelector('.aqm-tab'),ta=root.querySelector('textarea'),st=root.querySelector('.aqm-state');
  var csrf=<?=json_encode($aqmCsrf)?>,timer=null,last=ta.value;
  try{if(localStorage.getItem('adminQmOpen')==='1')root.classList.add('aqm-open');}catch(e){}
  tab.addEventListener('click',function(){
    root.classList.toggle('aqm-open');
    try{localStorage.setItem('adminQmOpen',root.classList.contains('aqm-open')?'1':'0');}catch(e){}
    if(root.classList.contains('aqm-open'))ta.focus();
  });
  function save(){
    if(ta.value===last)return;
    var value=ta.value;st.textContent='저장 중…';
    fetch('/admin_quickmemo_widget.php',{method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:new URLSearchParams({csrf:csrf,text:value}).toString()})
      .then(function(r){return r.json();})
      .then(function(j){if(j&&j.ok){last=value;st.textContent=j.updated+' 저장';}else st.textContent='저장 실패';})
      .catch(function(){st.textContent='통신 오류';});
  }
  ta.addEventListener('input',function(){clearTimeout(timer);st.textContent='입력 중…';timer=setTimeout(save,800);});
  window.addEventListener('beforeunload',function(){
    if(ta.value===last)return;
    try{navigator.sendBeacon('/admin_quickmemo_widget.php',new URLSearchParams({csrf:csrf,text:ta.value}));}catch(e){}
  });
})();
</script>
