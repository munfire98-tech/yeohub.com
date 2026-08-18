<?php
/** sign_view.php — 서명이 화면에 왜 안 나오는지 확인 (확인 후 삭제) */
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
if (!(is_admin() || !empty($_SESSION['is_user']))) { exit('로그인 후 이용하세요.'); }

$uidKey = $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest'));
$uidKey = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uidKey);
$BASE   = __DIR__ . '/data/worklog/' . $uidKey;

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$REC_FILE = $BASE . '/m' . $month . '.json';

function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}

$rec = load_json($REC_FILE);
$curSign = $rec['sign'] ?? '';

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:system-ui,'Malgun Gothic',sans-serif;max-width:820px;margin:26px auto;padding:0 16px;line-height:1.7}
.b{background:#f8fafc;border:1px solid #dde3ec;border-radius:9px;padding:11px 13px;margin:8px 0;font-size:13px;word-break:break-all}
.ok{color:#059669;font-weight:700}.bad{color:#dc2626;font-weight:700}
code{background:#f2f4f8;padding:2px 5px;border-radius:4px;font-size:12px}</style>";

echo "<h2>서명 표시 진단 &mdash; " . h($month) . "</h2>";

echo "<h3>1. 파일 읽기</h3><div class='b'>";
echo "경로: " . h($REC_FILE) . "<br>";
echo "파일 존재: " . (is_file($REC_FILE) ? "<span class='ok'>예</span>" : "<span class='bad'>아니오</span>") . "<br>";
echo "rec 항목 수: " . count($rec) . "<br>";
echo "rec 키: " . h(implode(', ', array_keys($rec)));
echo "</div>";

echo "<h3>2. 서명 데이터</h3><div class='b'>";
echo "sign 키 존재: " . (array_key_exists('sign', $rec) ? "<span class='ok'>예</span>" : "<span class='bad'>아니오</span>") . "<br>";
echo "curSign 길이: <b>" . number_format(strlen($curSign)) . "</b><br>";
echo "앞 60자: <code>" . h(substr($curSign, 0, 60)) . "</code><br>";
echo "형식 검사: " . (preg_match('#^data:image/png;base64,#', $curSign) ? "<span class='ok'>정상</span>" : "<span class='bad'>비정상</span>");
echo "</div>";

echo "<h3>3. 실제 렌더링 테스트</h3>";
if ($curSign === '') {
  echo "<p class='bad'>curSign이 비어 있어 이미지를 표시할 수 없습니다.</p>";
} else {
  echo "<p>아래에 서명이 보이면 데이터는 정상입니다.</p>";
  echo "<div style='border:1px solid #ddd;border-radius:8px;padding:12px;display:inline-block'>";
  echo "<img src='" . h($curSign) . "' style='height:50px' alt='서명'>";
  echo "</div>";
  echo "<h4 style='margin-top:18px'>work_log_form.php와 동일한 방식</h4>";
  echo "<div style='border:1px solid #ddd;border-radius:8px;padding:12px;display:inline-block'>";
  echo "<img id='signImg' src='" . h($curSign) . "' alt='' style='" . ($curSign === '' ? 'display:none' : '') . "'>";
  echo "</div>";
  echo "<p style='font-size:12px;color:#666'>위 두 개가 모두 보이면 데이터는 정상이고, work_log_form.php의 CSS나 다른 부분이 문제입니다.</p>";
}

echo "<h3>4. 저장된 모든 달</h3>";
$files = glob($BASE . '/m*.json') ?: [];
if (!$files) {
  echo "<p class='bad'>저장된 기록이 없습니다.</p>";
}
foreach ($files as $f) {
  $d  = load_json($f);
  $sl = strlen((string)($d['sign'] ?? ''));
  $mk = basename($f, '.json');
  echo "<div class='b'><b>" . h($mk) . "</b> &mdash; 서명 " .
       ($sl ? "<span class='ok'>" . number_format($sl) . "자</span>" : "<span class='bad'>없음</span>");
  if ($sl) echo " <img src='" . h($d['sign']) . "' style='height:30px;vertical-align:middle;margin-left:8px'>";
  echo " <a href='?month=" . h(substr($mk, 1)) . "' style='margin-left:8px'>이 달 보기</a></div>";
}
?>
<hr style="margin:24px 0;border:0;border-top:1px solid #e5e7eb">
<p style="color:#b45309;font-size:13px">확인 후 <code>sign_view.php</code>를 서버에서 삭제하세요.</p>
