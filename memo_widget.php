<?php
/* =============================================================
   memo_widget.php — 어느 화면에서든 쓰는 "빠른 메모" (우하단 접이식)
   ─────────────────────────────────────────────────────────────
   사용법 : 각 페이지 맨 아래(</body> 직전)에 한 줄만 넣습니다.

       <?php require __DIR__ . '/memo_widget.php'; ?>

   이 파일 하나가 다음을 모두 처리합니다.
     · 권한 확인 (허용 안 된 계정에는 아예 표시하지 않음)
     · 저장 요청(AJAX) 처리
     · 위젯 HTML/CSS/JS 출력

   ★ 나중에 "결제한 계정도 열기" 는 memo_allowed() 한 곳만 고치면 됩니다.

   저장 위치 : data/memo/{회원키}/quick_memo.json
   ============================================================= */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/user_key.php';

/* ── 누가 쓸 수 있는가 ────────────────────────────────────
   지금은 개발 계정만. 결제 연동이 끝나면 아래 주석을 풀면 됩니다. */
if (!function_exists('memo_allowed')) {
  function memo_allowed(): bool {
    $uid = function_exists('app_user_key') ? app_user_key() : '';
    if ($uid === '') return false;

    // (1) 개발/운영 계정 — 항상 허용
    $ALLOW = ['tttt'];                 // ← 계정을 늘리려면 여기에 추가
    if (in_array($uid, $ALLOW, true)) return true;

    // (2) 관리자 허용
    if (!empty($_SESSION['is_admin']) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1)) return true;

    // (3) ★결제 연동 후: 구독 중이면 허용 — 준비되면 주석을 푸세요
    // $subFile = __DIR__ . '/data/subscribe/' . $uid . '/subscription.json';
    // if (is_file($subFile)) {
    //   $s = json_decode((string)@file_get_contents($subFile), true);
    //   if (is_array($s) && ($s['status'] ?? '') === 'active') return true;
    // }

    return false;
  }
}

/* 권한 없으면 아무것도 하지 않는다 (HTML 출력 없음) */
if (!memo_allowed()) return;

/* ── 저장 파일 ── */
if (!function_exists('memo_file')) {
  function memo_file(): string {
    $uid = app_user_key();
    if ($uid === '') return '';
    $dir = __DIR__ . '/data/memo/' . $uid;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/quick_memo.json';
  }
}

/* ── CSRF (호스트 페이지가 이미 만들었으면 그대로 사용) ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$MEMO_CSRF = $_SESSION['csrf'];

/* ── 저장 요청 처리 ──────────────────────────────────────
   호스트 페이지의 다른 action 과 겹치지 않도록 전용 이름을 씁니다. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'memo_widget_save') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals($MEMO_CSRF, (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false, 'msg'=>'세션 만료']); exit;
  }
  $f = memo_file();
  $data = [
    'text'    => (string)($_POST['text'] ?? ''),
    'updated' => date('Y-m-d H:i'),
  ];
  $ok = false;
  if ($f !== '') {
    $tmp = $f . '.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false) {
      $ok = @rename($tmp, $f);
    }
  }
  echo json_encode(['ok'=>$ok, 'updated'=>$data['updated']], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 기존 메모 읽기 ── */
$__mf = memo_file();
$__memo = ['text'=>'', 'updated'=>''];
if ($__mf !== '' && is_file($__mf)) {
  $__r = json_decode((string)@file_get_contents($__mf), true);
  if (is_array($__r)) $__memo = array_merge($__memo, $__r);
}
if (!function_exists('memo_h')) {
  function memo_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
?>
<!-- ═══ 빠른 메모 (memo_widget.php) ═══ -->
<style>
.mw{position:fixed;right:18px;bottom:18px;z-index:9500;font-family:inherit}
.mw__tab{display:inline-flex;align-items:center;gap:7px;padding:11px 17px;border-radius:999px;
  border:0;background:#1a2436;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;
  box-shadow:0 8px 24px rgba(15,25,50,.28);font-family:inherit;transition:.14s}
.mw__tab:hover{transform:translateY(-1px)}
.mw__tab .dot{width:7px;height:7px;border-radius:50%;background:#fbbf24}
.mw__tab .dot.on{background:#34d399}
.mw__panel{position:absolute;right:0;bottom:54px;width:min(360px,calc(100vw - 36px));
  background:#fffbeb;border:1px solid #fde68a;border-radius:14px;overflow:hidden;
  box-shadow:0 16px 44px rgba(15,25,50,.22);display:none}
.mw.open .mw__panel{display:block}
.mw__head{display:flex;align-items:center;justify-content:space-between;gap:8px;
  padding:10px 14px;background:#fef3c7;border-bottom:1px solid #fde68a}
.mw__head b{font-size:13px;color:#92400e}
.mw__head .st{font-size:11px;color:#b45309;white-space:nowrap}
.mw__body textarea{display:block;width:100%;min-height:200px;max-height:52vh;padding:12px 14px;
  border:0;background:transparent;resize:vertical;font-size:13.5px;line-height:1.75;
  font-family:inherit;color:#451a03;outline:none}
.mw__body textarea::placeholder{color:#d97706;opacity:.55}
.mw__foot{display:flex;align-items:center;gap:8px;padding:8px 12px;
  border-top:1px solid #fde68a;background:#fffdf5}
.mw__hint{font-size:10.5px;color:#b45309;opacity:.85;line-height:1.5}
.mw__btn{margin-left:auto;border:1px solid #fcd34d;background:#fff;color:#92400e;
  border-radius:7px;padding:5px 10px;font-size:11.5px;font-weight:700;cursor:pointer;
  font-family:inherit;white-space:nowrap}
.mw__btn:hover{background:#fef3c7}
@media print { .mw { display:none !important; } }
</style>

<div class="mw" id="mw">
  <div class="mw__panel">
    <div class="mw__head">
      <b>📌 빠른 메모</b>
      <span class="st" id="mwState"><?= $__memo['updated'] !== '' ? memo_h($__memo['updated']).' 저장' : '' ?></span>
    </div>
    <div class="mw__body">
      <textarea id="mwText" placeholder="할 일, 진행 중인 작업, 끝낸 것을 적어두세요.&#10;입력을 멈추면 자동으로 저장됩니다.&#10;&#10;예)&#10;[ ] 결제 연동 — PG 견적 받기&#10;[x] 건축물대장 여러 동 조회 수정&#10;[ ] 구독 페이지 링크 정리"><?= memo_h($__memo['text']) ?></textarea>
    </div>
    <div class="mw__foot">
      <span class="mw__hint">모든 화면에서 같은 메모를 봅니다</span>
      <button type="button" class="mw__btn" id="mwStamp">＋ 오늘 날짜</button>
    </div>
  </div>
  <button type="button" class="mw__tab" id="mwTab"><span class="dot" id="mwDot"></span>메모</button>
</div>

<script>
(function(){
  var mw   = document.getElementById('mw');
  var tab  = document.getElementById('mwTab');
  var ta   = document.getElementById('mwText');
  var st   = document.getElementById('mwState');
  var dot  = document.getElementById('mwDot');
  var stamp= document.getElementById('mwStamp');
  var CSRF = <?= json_encode($MEMO_CSRF) ?>;
  var URL_ = location.pathname + location.search;

  /* 열림/닫힘 상태를 기억한다 (페이지를 옮겨도 유지) */
  try { if (localStorage.getItem('mwOpen') === '1') mw.classList.add('open'); } catch(e){}
  tab.addEventListener('click', function(){
    mw.classList.toggle('open');
    try { localStorage.setItem('mwOpen', mw.classList.contains('open') ? '1' : '0'); } catch(e){}
    if (mw.classList.contains('open')) ta.focus();
  });

  /* 자동 저장 — 입력이 멈추고 0.8초 뒤 */
  var timer = null, last = ta.value;
  function save(){
    if (ta.value === last) return;
    var v = ta.value;
    st.textContent = '저장 중…';
    fetch(URL_, { method:'POST', credentials:'same-origin',
      body: new URLSearchParams({ action:'memo_widget_save', csrf:CSRF, text:v }) })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j && j.ok){ last = v; st.textContent = j.updated + ' 저장';
          dot.classList.add('on'); setTimeout(function(){ dot.classList.remove('on'); }, 1200); }
        else st.textContent = '저장 실패';
      })
      .catch(function(){ st.textContent = '통신 오류'; });
  }
  ta.addEventListener('input', function(){
    clearTimeout(timer); st.textContent = '입력 중…'; timer = setTimeout(save, 800);
  });

  /* 창을 떠나기 전 마지막 저장 */
  window.addEventListener('beforeunload', function(){
    if (ta.value !== last){
      try { navigator.sendBeacon(URL_,
        new URLSearchParams({ action:'memo_widget_save', csrf:CSRF, text:ta.value })); } catch(e){}
    }
  });

  /* 오늘 날짜 넣기 — 기록용으로 유용 */
  stamp.addEventListener('click', function(){
    var d = new Date();
    var s = '\n── ' + d.getFullYear() + '-' +
            String(d.getMonth()+1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0') + ' ──\n';
    ta.value = ta.value.replace(/\s*$/, '') + '\n' + s;
    ta.focus(); ta.selectionStart = ta.selectionEnd = ta.value.length;
    clearTimeout(timer); timer = setTimeout(save, 400);
  });

  /* 단축키: Ctrl(또는 ⌘) + M 으로 열고 닫기 */
  document.addEventListener('keydown', function(e){
    if ((e.ctrlKey || e.metaKey) && (e.key === 'm' || e.key === 'M')){
      e.preventDefault(); tab.click();
    }
  });
})();
</script>
