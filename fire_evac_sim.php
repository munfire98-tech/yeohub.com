<?php
/**
 * ============================================================
 *  건물 화재 대피 시뮬레이터  (FIRE-EVAC-01)
 * ------------------------------------------------------------
 *  단일 PHP 파일. 실행:
 *      php -S localhost:8000 fire_evac_sim.php
 *  브라우저에서 http://localhost:8000 접속.
 *
 *  URL 파라미터로 기본값 변경 가능:
 *      ?people=120&spread=1.5&speed=1.2
 *
 *  [업데이트 노트]  ← 계속 여기에 기록하며 확장하세요
 *   v1.9  열람 전용 임베드 모드: 다른 페이지에서 include 하거나 iframe 으로
 *          띄워 거래처가 편집 없이 볼 수 있다. 이미지(PNG) 저장, 열람용 주소 복사.
 *   v1.8  모델 보관함: 이름 붙여 여러 개 저장·열기·복제·삭제, 파일 내보내기/불러오기.
 *          지하층(B1·B2…) 추가. 서버 없이 브라우저에 저장된다.
 *   v1.7  빈 도면(테두리만 벽) 시작 옵션. 비상구·계단이 없는 도면도 받아들이고
 *          부족한 요소는 진단이 안내. 편집 중 줌 컨트롤을 패널로 옮겨 도면 가림 제거.
 *   v1.6  꺾임계단 돌아가는 쪽(좌/우) 설정, 옥내소화전 배치 도구와
 *          방호거리 25 m 진단, 계단 카드에 층 구간 표시
 *   v1.5  계단 번호를 도면(평면·3D)에 표시하고, 설정 카드에 마우스를 올리면
 *          해당 계단실이 도면에서 강조되도록 연결
 *   v1.4  평면도가 계단을 실제 형상(디딤판선·중간참·UP/DN 화살표)으로 그린다.
 *          설정을 바꿔도 화면이 그대로여서 눌리지 않는 것처럼 보이던 문제 해결.
 *   v1.3  계단실별 설정(꺾임 2개단/직통 1개단, 올라가는 방향 4방위),
 *          편집 내용 자동 저장 + 상단 고정 툴바, 층 선택 즉시 반응
 *   v1.2  편집 엔진 재구성: 도구(무엇을) × 방식(어떻게) 분리,
 *          채우기·직선·사각형·자유 붓, 층 비우기, 적용 전 미리보기,
 *          편집 중 시점 패널을 감춰 도면 가림 제거
 *   v1.1  화면 고정 레이아웃: 페이지 스크롤 제거, 상태 판독을 상단 가로 배치,
 *          시점·층 컨트롤을 모형 위에 띄움, 레일을 시뮬/편집 탭으로 분리,
 *          모형이 항상 화면에 맞도록 자동 배율
 *   v1.0  계단을 꺾임계단(2주형)으로 재구성: 중간참 + 2개 단, 단높이 150mm,
 *          재실자도 같은 형상을 따라 꺾어 오르내림
 *   v0.9  작도 도구화: 실행취소/다시실행, 도구 단축키, 실시간 피난 진단
 *          (보행거리·고립구역), 층 복제, 시작 평면 3종
 *   v0.8  재실자를 채워진 스케일 피규어로 교체(평면은 재실자 기호),
 *          계단 런을 따라 실제로 걸어 내려가는 이동 경로,
 *          드래그 사각형 편집: 구획 도구 + 전 층 동시 계단실 배치
 *   v0.7  건물·계단 재설계: 240mm 연속 칸막이벽(셀 이음새 제거), 실제 디딤판을
 *          쌓아 올린 계단실, 코어 2개소를 갖춘 24×16m 기준층 평면,
 *          고도각 −73°~+87° 전 범위 궤도(아래에서 올려다보기 포함)
 *   v0.6  디자인 정비: 방향광 기반 재질(건축 스터디 모형), 층별 상태 스택,
 *          평면도 포셰 표기, 계기용 타이포그래피, 인터페이스 문안 정리
 *   v0.5  자유 시점: 고도각(pitch) 추가로 앞·뒤·좌·우·위 전 방향 궤도 회전,
 *          시점 프리셋 6종 + 자동 회전, 계단 오르내리는 모습 애니메이션
 *   v0.4  3D 축측투영 뷰: 층을 띄워 쌓은 입체 렌더, 드래그 회전 · 휠 줌,
 *          전체층/현재층 토글, 3D에서 클릭으로 발화 지점 지정,
 *          정적 지오메트리 오프스크린 캐시로 프레임당 비용 16배 감소
 *   v0.3  다층 건물: 층 추가/삭제(최대 6층), 계단(S)으로 층 연결,
 *          3D 경로탐색, 계단 굴뚝효과(연기 상승), 층 전환 탭
 *   v0.2  도면 편집 모드: 마우스로 벽/바닥/비상구 그리기,
 *          텍스트 도면 내보내기/불러오기, 브라우저 저장(localStorage),
 *          가변 격자 크기 지원
 *   v0.1  최초 버전: 격자 건물, 졸라맨 보행자, 화재/연기 확산,
 *          경로 탐색(다익스트라 유동장), 탈출 판정 리포트
 * ============================================================
 */

const APP_VERSION = '1.9';

/* ---------- 설정 (URL 파라미터 → JS로 전달) ---------- */
/* ------------------------------------------------------------
   임베드(열람 전용) 모드
   빌딩매니저 같은 다른 페이지에서 이렇게 불러 쓴다.

       $EVAC_MAP  = $building['evac_map'];   // 저장해 둔 도면 텍스트
       $EVAC_NAME = $building['name'];
       $EVAC_EMBED = true;
       include 'fire_evac_sim.php';

   또는 iframe:  fire_evac_sim.php?embed=1&m=<base64url 도면>
   ------------------------------------------------------------ */
/* ── 관리자 여부 판별 (도면 보관함 사용 권한) ──
   다른 페이지에 include 될 때는 이미 세션이 열려 있을 수 있다. */
if (!isset($EVAC_NO_SESSION) && session_status() !== PHP_SESSION_ACTIVE) {
    $__ttl = 60 * 60 * 24 * 30;
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string)$__ttl);
    ini_set('session.cookie_lifetime', (string)$__ttl);
    $__host = $_SERVER['HTTP_HOST'] ?? '';
    $__base = preg_match('/([^.]+\.[^.]+)$/', $__host, $__m) ? $__m[1] : $__host;
    $__dom  = ($__host === 'localhost' || $__host === '') ? '' : ('.' . $__base);
    $__sec  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $__ttl, 'path' => '/', 'domain' => $__dom,
            'secure' => $__sec, 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    @session_start();
}
$EVAC_IS_ADMIN = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
              || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$EVAC_CSRF = (string)$_SESSION['csrf'];

$EVAC_EMBED = $EVAC_EMBED ?? isset($_GET['embed']);
$EVAC_AUTO  = $EVAC_AUTO  ?? isset($_GET['auto']);  // 임베드 자동 재생 (소개 페이지용)
$EVAC_HOST  = $EVAC_HOST  ?? false;   // TWORIX 페이지 안에 들어갈 때 true
$EVAC_SAVE_URL = $EVAC_SAVE_URL ?? '';// 서버 저장 주소 (비우면 브라우저에만 저장)
$EVAC_SCENARIO = $EVAC_SCENARIO ?? '';
$EVAC_MAP   = $EVAC_MAP   ?? '';
$EVAC_NAME  = $EVAC_NAME  ?? '';
if ($EVAC_EMBED && $EVAC_MAP === '' && isset($_GET['m'])) {
    $raw = base64_decode(strtr((string)$_GET['m'], '-_', '+/'), true);
    if ($raw !== false) {
        $un = @gzinflate($raw);
        $EVAC_MAP = $un !== false ? $un : $raw;
    }
}
if ($EVAC_EMBED && $EVAC_NAME === '' && isset($_GET['n'])) {
    $EVAC_NAME = substr((string)$_GET['n'], 0, 120);
}

function param_num(string $key, float $default, float $min, float $max): float {
    if (!isset($_GET[$key]) || !is_numeric($_GET[$key])) return $default;
    return max($min, min($max, (float)$_GET[$key]));
}

$config = [
    'people'    => (int) param_num('people', 60, 1, 300),   // 인원 수
    'spread'    => param_num('spread', 1.0, 0.2, 3.0),      // 화재 확산 배율
    'walkSpeed' => param_num('speed', 1.0, 0.5, 2.0),       // 보행 속도 배율
    'version'   => APP_VERSION,
    'servedAt'  => date('Y-m-d H:i'),
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>피난 시뮬레이터 · <?php echo APP_VERSION; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+KR:wght@300;400;500;600;700&family=Saira+Condensed:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --void:#131519; --panel:#1A1D23; --panel-2:#212530;
  --line:#2C313B; --line-2:#3A404C;
  --ink:#E7E4DD; --muted:#868D99;
  --signal:#FF4B1F; --exit:#00B67A; --data:#5FC9F8; --warn:#F5A623;
  --rail:320px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:var(--void); color:var(--ink);
  font-family:'IBM Plex Sans KR',system-ui,sans-serif;
  overflow:hidden;                       /* 페이지 스크롤 없음 */
  -webkit-font-smoothing:antialiased;
}
.eyebrow{font-family:'IBM Plex Mono',monospace;font-size:.62rem;font-weight:600;
  letter-spacing:.19em;text-transform:uppercase;color:var(--muted)}
.readout{font-family:'Saira Condensed',sans-serif;font-weight:700;
  font-variant-numeric:tabular-nums;line-height:.9}

/* ── 전체 뼈대: 헤더 + 본문, 화면 높이에 고정 ── */
.app{height:100%;display:grid;grid-template-rows:auto 1fr;gap:0}

/* 헤더 — 상태 판독을 항상 보이게 가로로 편다 */
.topbar{
  display:flex;align-items:center;gap:22px;
  padding:11px 18px;border-bottom:1px solid var(--line);background:var(--panel);
}
.backlink{display:flex;align-items:center;justify-content:center;width:30px;height:30px;
  border:1px solid var(--line-2);color:var(--muted);text-decoration:none;flex:none}
.backlink:hover{border-color:var(--data);color:var(--data)}
.brand{display:flex;align-items:baseline;gap:10px;min-width:0}
.brand input{background:transparent;border:1px solid transparent;color:var(--ink);
  font-family:inherit;font-size:.95rem;font-weight:600;padding:5px 7px;max-width:280px;min-width:120px}
.brand input:hover{border-color:var(--line-2)}
.brand input:focus{outline:none;border-color:var(--data);background:#0E1013}
.brand em{font-style:normal;color:var(--muted);font-weight:400;font-size:.74rem;
  font-family:'IBM Plex Mono',monospace;white-space:nowrap}
.brand em.dirty{color:var(--warn)} .brand em.failed{color:var(--signal)}
.kpis{display:flex;gap:20px;margin-left:auto;align-items:center}
.kpi{text-align:right;line-height:1}
.kpi .k{font-family:'IBM Plex Mono',monospace;font-size:.57rem;letter-spacing:.13em;
  color:var(--muted);text-transform:uppercase;display:block;margin-bottom:4px}
.kpi .v{font-size:1.45rem}
.kpi.out .v{color:var(--exit)} .kpi.dead .v{color:var(--signal)}
.kpi.fire .v{color:var(--warn)} .kpi.time .v{color:var(--data)}

/* ── 본문: 무대 + 레일 ── */
.main{display:grid;grid-template-columns:1fr var(--rail);min-height:0}
@media(max-width:900px){
  body{overflow:auto}
  .app{height:auto}
  .main{grid-template-columns:1fr}
}

/* ═══ 모바일: 열람 전용 ═══
   편집 도구를 숨기고, 도면을 화면 폭에 맞춰 보여준다. */
@media(max-width:820px){
  body{overflow-y:auto;overflow-x:hidden;-webkit-text-size-adjust:100%}
  .app{height:auto;min-height:100%}

  /* 헤더: 두 줄로 접고 지표는 가로 스크롤 */
  .topbar{flex-wrap:wrap;gap:10px;padding:9px 12px;position:sticky;top:0;z-index:60}
  .brand{flex:1 1 100%;order:1}
  .brand input{max-width:none;width:100%;font-size:.9rem}
  .kpis{order:2;margin-left:0;width:100%;gap:14px;
        overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:2px}
  .kpis::-webkit-scrollbar{display:none}
  .kpi{flex:none;text-align:left}
  .kpi .v{font-size:1.15rem}

  /* 무대: 높이를 명시해야 캔버스가 0이 되지 않는다 */
  .main{grid-template-columns:1fr}
  .stage{height:58vh;min-height:300px;padding:0!important}
  canvas{max-width:100%;max-height:100%;touch-action:pan-y}

  /* 떠있는 패널이 도면을 덮지 않게 축소.
     열람자에게 꼭 필요한 3D/2D 전환(r2)만 남기고 나머지 시점 조절은 숨긴다. */
  .f-view{top:8px;right:8px;width:auto;background:transparent;border:0;padding:0;box-shadow:none}
  .f-view .r3, .f-view .r4, #btnSpin, .f-view .chk{display:none!important}
  .f-view .r2{grid-template-columns:auto auto auto}
  .f-view .r2 button{min-height:40px;padding:8px 16px;font-size:.82rem;
    background:var(--panel);border:1px solid var(--line);box-shadow:0 2px 8px rgba(0,0,0,.25)}
  .f-floors{top:8px;left:8px;width:60px;gap:2px;padding:4px}
  .f-floors button{padding:6px 4px;font-size:.7rem}
  .f-foot{bottom:8px;left:8px;right:8px;padding:5px 8px;font-size:.62rem}

  /* 레일: 도면 아래로 */
  .rail{border-left:0;border-top:1px solid var(--line);max-height:none}

  /* ─ 열람 전용: 편집 관련 전부 숨김 ─ */
  #tabEdit,
  #paneEdit,
  #btnModels{display:none!important}
  .tabs{display:none}                       /* 탭이 하나뿐이라 불필요 */
  .brand input{pointer-events:none;border-color:transparent!important}

  /* 터치 대상 키우기 */
  .rail button{min-height:42px}
  .run{min-height:48px;font-size:.95rem}
}

/* ═══ 모바일 세로: 가로모드 안내 배너 ═══ */
.rotate-hint{display:none}
@media(max-width:820px) and (orientation:portrait){
  .rotate-hint{display:flex;align-items:center;gap:10px;margin:0;
    padding:11px 14px;background:linear-gradient(90deg,#1d4ed8,#2563eb);color:#fff;
    font-size:.82rem;font-weight:600;line-height:1.4}
  .rotate-hint .ph{font-size:1.25rem;animation:rotDemo 2.2s ease-in-out infinite}
  @keyframes rotDemo{0%,25%{transform:rotate(0)}55%,80%{transform:rotate(90deg)}100%{transform:rotate(90deg)}}
}

/* ═══ 모바일 가로: 시뮬을 화면 가득 ═══
   헤더·레일을 숨기고 캔버스가 화면 전체를 쓴다.
   조작은 떠있는 [3D/2D]와 층 버튼, 그리고 재생 플로팅 버튼으로. */
@media(max-width:940px) and (orientation:landscape) and (max-height:520px){
  .rotate-hint{display:none}
  .topbar{display:none!important}
  .rail{display:none!important}
  .main{grid-template-columns:1fr!important}
  .stage{height:100vh!important;height:100dvh!important;min-height:0!important}
  canvas{max-width:100vw;max-height:100vh;max-height:100dvh}
  .f-floors{top:8px;left:8px}
  /* 가로모드 전용 재생 버튼 (플로팅) */
  .land-run{display:flex!important}
}
.land-run{display:none;position:absolute;right:12px;bottom:12px;z-index:80;
  min-height:46px;padding:10px 18px;border-radius:999px;border:0;
  background:#e0431f;color:#fff;font-weight:700;font-size:.92rem;
  box-shadow:0 6px 20px rgba(224,67,31,.4);cursor:pointer}
.land-run:active{transform:scale(.97)}

/* ═══ 전체화면 모드 ═══
   stage 요소를 Fullscreen API 로 띄운다. 배경을 채우고 캔버스를 중앙 배치. */
.stage:fullscreen{background:var(--bg,#0f1420);display:flex;
  align-items:center;justify-content:center;padding:0}
.stage:fullscreen canvas{max-width:100vw;max-height:100vh}
.stage:fullscreen .land-run{display:flex}
/* iOS 사파리는 :fullscreen 미지원 → JS 로 .fs-fallback 클래스를 붙여 흉내낸다 */
.stage.fs-fallback{position:fixed;inset:0;z-index:999;background:var(--bg,#0f1420);
  display:flex;align-items:center;justify-content:center;height:100vh!important;height:100dvh!important}
.stage.fs-fallback canvas{max-width:100vw;max-height:100vh;max-height:100dvh}
body.fs-lock{overflow:hidden}

/* 모바일 안내 문구 — 큰 화면에서는 안 보임 */
.mix-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-top:6px}
.mix-grid label{display:flex;flex-direction:column;gap:3px;font-size:.68rem;color:var(--muted)}
.mix-grid input{width:100%;padding:5px 6px;font-size:.78rem;background:var(--panel-2);
  border:1px solid var(--line-2);border-radius:6px;color:var(--ink)}
.mix-note{margin:6px 0 0;font-size:.64rem;color:var(--muted);line-height:1.5}

/* 출발 층 범례 — 여러 층일 때만 표시 */
.f-origin{left:12px;bottom:12px;display:flex;flex-direction:column;gap:4px;
  font-size:.68rem;color:var(--muted);padding:8px 10px}
.f-origin .t{font-weight:600;color:var(--ink);font-size:.66rem;margin-bottom:1px}
.f-origin .li{display:flex;align-items:center;gap:6px}
.f-origin .sw{width:9px;height:9px;border-radius:50%}

.mobile-note{display:none}
@media(max-width:820px){
  .mobile-note{display:block;margin:10px 12px 14px;padding:9px 11px;
    border:1px solid var(--line-2);background:var(--panel-2);
    color:var(--muted);font-size:.71rem;line-height:1.55}
  .mobile-note b{color:var(--data);font-weight:600}
}

/* 무대 — 모형이 항상 화면에 꽉 맞게 들어간다 */
.stage{
  position:relative;min-width:0;min-height:0;
  background:radial-gradient(120% 90% at 50% 8%, #1D2028 0%, #0E1013 78%);
  display:flex;align-items:center;justify-content:center;overflow:hidden;
}
canvas{display:block;max-width:100%;max-height:100%;cursor:grab}
canvas:active{cursor:grabbing}
body.editing canvas{cursor:crosshair}

/* 무대 위 떠 있는 컨트롤 — 쓰는 자리에 둔다 */
.float{position:absolute;background:rgba(20,23,29,.86);border:1px solid var(--line);
  backdrop-filter:blur(8px);padding:6px}
.f-view{top:12px;right:12px;display:flex;flex-direction:column;gap:5px;width:172px}
body.editing .f-view{display:none}          /* 편집 중에는 도면을 가리지 않게 */
body.editing .f-foot{display:none}
.stage{padding:0}
body.editing .stage{padding:12px 16px 12px 136px}   /* 왼쪽 층 선택 패널 자리만 비운다 */
.modelpanel{position:fixed;inset:0;background:rgba(9,11,14,.72);z-index:100;
  display:flex;align-items:flex-start;justify-content:center;padding:60px 20px}
.modelpanel[hidden]{display:none}
.mp-box{width:min(560px,100%);background:var(--panel);border:1px solid var(--line-2);
  display:flex;flex-direction:column;max-height:80vh}
.mp-head{display:flex;align-items:center;justify-content:space-between;
  padding:13px 15px;border-bottom:1px solid var(--line)}
.mp-head button{padding:4px 9px}
.mp-list{overflow-y:auto;padding:8px;display:flex;flex-direction:column;gap:5px;min-height:80px}
.mp-cnt{color:var(--muted);font-weight:400;margin-left:4px}
.mp-tools{display:flex;gap:6px;padding:8px 8px 0}
.mp-tools input[type=search]{flex:1;min-width:0;padding:7px 10px;border-radius:8px;
  border:1px solid var(--line);background:var(--panel-2);color:var(--ink);font:inherit;font-size:.8rem}
.mp-tools input[type=search]:focus{outline:none;border-color:var(--data)}
.mp-tools select{padding:7px 8px;border-radius:8px;border:1px solid var(--line);
  background:var(--panel-2);color:var(--ink);font:inherit;font-size:.76rem}
.mp-bulk{display:flex;align-items:center;gap:8px;padding:8px;margin:8px 8px 0;
  border:1px solid var(--line);border-radius:8px;background:var(--panel-2);font-size:.76rem}
.mp-bulk .mp-all{display:flex;align-items:center;gap:5px;cursor:pointer}
.mp-bulk #mpSel{color:var(--muted)}
.mp-bulk button{margin-left:auto;padding:5px 10px;font-size:.74rem}
.mp-bulk button+button{margin-left:0}
.mp-bulk button.danger{background:#3a1512;border-color:#7f1d1d;color:#fca5a5}
.mrow .mck{display:flex;align-items:center;padding:0 2px 0 6px;cursor:pointer}
.mrow.sel{outline:1px solid var(--data)}
.mp-empty{padding:14px 10px;color:var(--muted);font-size:.78rem;text-align:center}
.mrow{display:flex;gap:5px;align-items:stretch}
.mrow .mopen{flex:1;display:grid;grid-template-columns:1fr auto;gap:2px 10px;
  text-align:left;padding:9px 11px;align-items:baseline;background:var(--panel-2);
  border:1px solid var(--line-2)}
.mrow.on .mopen{border-color:var(--data)}
.mrow .mn{font-size:.86rem;font-weight:500;color:var(--ink)}
.mrow .mt{font-family:'IBM Plex Mono',monospace;font-size:.63rem;color:var(--muted)}
.mrow .mm{grid-column:1/-1;font-size:.7rem;color:var(--muted)}
.macts{display:flex;flex-direction:column;gap:3px}
.macts button{padding:4px 9px;font-size:.7rem}
.mp-foot{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:11px 15px;
  border-top:1px solid var(--line)}
.mp-foot + .mp-foot{border-top:0;padding-top:0;grid-template-columns:1fr 1fr}
#shareOut{word-break:break-all;line-height:1.5}
.mp-foot button{padding:8px 0;font-size:.76rem;text-align:center}
.mp-foot .primary{background:var(--data);border-color:var(--data);color:#0C1116;font-weight:600}
.modelpanel .note{padding:0 15px 13px;font-size:.7rem}
.backlink{font-size:1rem}
.zoomrow{display:grid;grid-template-columns:auto auto auto 1fr;gap:5px;align-items:center}
.zoomrow button{padding:6px 11px;font-size:.75rem}
.zoomrow .eyebrow{text-align:right;font-size:.6rem}
.f-floors{top:12px;left:12px;display:flex;flex-direction:column-reverse;gap:3px;width:112px}
.f-foot{bottom:12px;left:12px;right:12px;display:flex;justify-content:space-between;
  align-items:center;background:none;border:0;backdrop-filter:none;padding:0;pointer-events:none}
#clock{font-family:'Saira Condensed',sans-serif;font-weight:600;font-size:1rem;
  color:var(--data);font-variant-numeric:tabular-nums}

/* 층 스택 */
.ftab{display:grid;grid-template-columns:26px 1fr auto;align-items:center;gap:7px;
  background:transparent;border:0;border-left:2px solid var(--line-2);
  padding:6px 7px;cursor:pointer;text-align:left;color:var(--muted);transition:.13s}
.ftab:hover{background:var(--panel-2);color:var(--ink)}
.ftab.active{border-left-color:var(--data);background:var(--panel-2);color:var(--ink)}
.ftab .fl{font-family:'Saira Condensed',sans-serif;font-weight:600;font-size:.9rem}
.ftab .bar{height:4px;background:#272C36;position:relative;overflow:hidden}
.ftab .bar i{position:absolute;inset:0 auto 0 0;background:var(--data);opacity:.55;transition:width .3s}
.ftab.active .bar i{opacity:1}
.ftab.burning .bar i{background:var(--signal);opacity:1}
.ftab .n{font-family:'IBM Plex Mono',monospace;font-size:.63rem;min-width:22px;
  text-align:right;font-variant-numeric:tabular-nums}
.ftab.burning .n{color:var(--signal)}
.ftab.basement .fl{color:var(--warn)}
.ftab.basement.active .fl{color:var(--warn)}

/* ── 레일 ── */
.rail{border-left:1px solid var(--line);background:var(--panel);
  display:flex;flex-direction:column;min-height:0}
.tabs{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--line);flex:none}
.tabs button{background:transparent;border:0;border-bottom:2px solid transparent;
  padding:12px 0;font-size:.84rem;color:var(--muted);cursor:pointer;font-family:inherit}
.tabs button:hover{color:var(--ink)}
.tabs button.active{color:var(--ink);border-bottom-color:var(--data);font-weight:600}
.panes{flex:1;overflow-y:auto;min-height:0}
.panes::-webkit-scrollbar{width:9px}
.panes::-webkit-scrollbar-thumb{background:var(--line-2)}
.pane{padding:14px;display:flex;flex-direction:column;gap:13px}
.pane[hidden]{display:none}
.block{display:flex;flex-direction:column;gap:9px}
.block > .eyebrow{padding-bottom:2px}

/* 컨트롤 */
button{font-family:inherit;font-size:.82rem;color:var(--ink);background:var(--panel-2);
  border:1px solid var(--line-2);padding:8px 10px;cursor:pointer;transition:.13s}
button:hover{border-color:#4C5462;background:#272C36}
button:focus-visible{outline:2px solid var(--data);outline-offset:1px}
button.on,button.active{background:var(--data);border-color:var(--data);color:#0C1116;font-weight:600}
.seg{display:grid;grid-template-columns:repeat(3,1fr);gap:3px}
.seg button{padding:5px 2px;font-size:.72rem}
.run{width:100%;padding:13px;font-size:.9rem;font-weight:600;
  background:var(--signal);border-color:var(--signal);color:#fff}
.run:hover{background:#FF6039;border-color:#FF6039}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:5px}
.g4 button,.g3 button{padding:7px 0;font-size:.75rem;text-align:center}
.f-view button{padding:6px 0;font-size:.73rem;text-align:center}
.f-view .row{display:grid;gap:4px}
.f-view .r2{grid-template-columns:1fr 1fr auto}
#btnFS{padding:6px 10px;font-size:.9rem;line-height:1}
.f-view .r3{grid-template-columns:repeat(3,1fr)}
.f-view .r4{grid-template-columns:repeat(4,1fr)}

/* 슬라이더 */
.field label{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px}
.field .name{font-size:.79rem}
.field output{font-family:'Saira Condensed',sans-serif;font-weight:600;font-size:1rem;
  color:var(--data);font-variant-numeric:tabular-nums}
input[type=range]{width:100%;height:3px;-webkit-appearance:none;appearance:none;
  background:var(--line-2);cursor:pointer}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:13px;height:13px;
  background:var(--data);border-radius:50%;cursor:pointer}
input[type=range]::-moz-range-thumb{width:13px;height:13px;background:var(--data);
  border:0;border-radius:50%;cursor:pointer}
.chk{display:flex;align-items:center;gap:7px;font-size:.76rem;cursor:pointer;color:var(--muted)}
.chk input{accent-color:var(--data);cursor:pointer}
.note{font-size:.71rem;line-height:1.6;color:var(--muted)}
.note b{color:var(--ink);font-weight:500}
kbd{font-family:'IBM Plex Mono',monospace;font-size:.67rem;background:var(--panel-2);
  border:1px solid var(--line-2);padding:1px 5px;color:var(--ink)}

/* 판정 */
#verdict{display:none;border:1px solid var(--line-2);padding:12px}
#verdict.show{display:block}
#verdict .badge{display:block;font-family:'Saira Condensed',sans-serif;font-weight:700;
  font-size:1.15rem;padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid var(--line)}
#verdict.ok .badge{color:var(--exit)} #verdict.fail .badge{color:var(--signal)}
#verdict p{font-size:.73rem;line-height:1.8;color:var(--muted);font-family:'IBM Plex Mono',monospace}

/* 편집 도구 */
.toolrow{display:grid;grid-template-columns:repeat(5,1fr);gap:5px}
.toolrow button{position:relative;padding:9px 0 13px;font-size:.75rem;text-align:center}
.toolrow button i{position:absolute;bottom:2px;left:0;right:0;font-style:normal;
  font-family:'IBM Plex Mono',monospace;font-size:.54rem;opacity:.55}
.stickybar{position:sticky;top:0;z-index:5;background:var(--panel);
  margin:-14px -14px 0;padding:12px 14px;border-bottom:1px solid var(--line);
  display:flex;flex-direction:column;gap:7px}
.saved{color:var(--exit)!important} .failed{color:var(--signal)!important}
.scfg{border:1px solid var(--line-2);background:#16191F;padding:9px 10px;margin-bottom:6px}
.scfg .hd{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:7px}
.scfg .nm{font-size:.76rem;color:var(--ink);display:flex;align-items:center;gap:6px}
.scfg .badge{width:17px;height:17px;border-radius:50%;border:1.5px solid var(--data);
  color:var(--data);font-style:normal;font-family:'Saira Condensed',sans-serif;font-weight:700;
  font-size:.72rem;display:inline-flex;align-items:center;justify-content:center;flex:none}
.scfg:hover{border-color:var(--data)}
.scfg .sz{font-family:'IBM Plex Mono',monospace;font-size:.63rem;color:var(--muted)}
.scfg .cur{font-size:.71rem;color:var(--muted);padding:6px 0 2px;
  border-top:1px solid var(--line);margin-top:2px}
.scfg .cur b{color:var(--data);font-weight:600}
.scfg .warn{font-size:.68rem;line-height:1.5;color:var(--warn);margin-top:6px}
.scfg button[disabled]{opacity:.35;cursor:not-allowed}
.scfg .lb{font-family:'IBM Plex Mono',monospace;font-size:.57rem;letter-spacing:.12em;
  color:var(--muted);text-transform:uppercase;margin:6px 0 4px;display:block}
.scfg .g2 button,.scfg .g4 button{padding:6px 0;font-size:.72rem;text-align:center}
.diag{border:1px solid var(--line-2);background:#16191F;padding:10px 11px}
.drow{display:flex;justify-content:space-between;align-items:baseline;
  font-size:.73rem;color:var(--muted);padding:3px 0}
.dv{font-family:'Saira Condensed',sans-serif;font-weight:600;font-size:.95rem;
  color:var(--ink);font-variant-numeric:tabular-nums}
.dv.good{color:var(--exit)} .dv.bad{color:var(--signal)}
.dnote{font-size:.68rem;line-height:1.55;color:var(--muted);
  margin-top:7px;padding-top:7px;border-top:1px solid var(--line)}
#mapText{width:100%;height:110px;resize:vertical;background:#0E1013;color:var(--ink);
  border:1px solid var(--line-2);font-family:'IBM Plex Mono',monospace;
  font-size:10px;line-height:1.2;white-space:pre;padding:8px}
#mapText:focus{outline:1px solid var(--data);border-color:var(--data)}
.legend{display:flex;flex-wrap:wrap;gap:7px 13px;font-size:.7rem;color:var(--muted)}
.legend span{display:flex;align-items:center;gap:5px}
.sw{width:9px;height:9px;flex:none}
@media (prefers-reduced-motion: reduce){*{transition:none!important}}

/* ── 건물 현황 ── */
.binfo{display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:.72rem;line-height:1.5}
.binfo .bk{color:var(--muted);white-space:nowrap}
.binfo .bv{color:var(--ink);text-align:right;word-break:keep-all}

/* ── 자동 재생(소개용) 모드: 시뮬 화면 + [3D/2D] + [화재 발생] 버튼 + 건물 현황만 남긴다 ── */
body.auto{--rail:200px}
body.auto #btnModels,
body.auto .topbar .brand,
body.auto .rail .tabs,
body.auto #paneSim .g2,
body.auto #paneSim > .note,
body.auto #paneSim .block:not(.block--info),
body.auto .f-view .r4,
body.auto .f-view .r3,
body.auto #btnSpin,
body.auto .f-view .chk,
body.auto .f-foot,
body.auto #floorTabs{display:none!important}
</style>
</head>
<body<?php echo ($EVAC_EMBED && $EVAC_AUTO) ? ' class="auto"' : ''; ?>>
<div class="app">

  <!-- 모바일 세로에서만 보이는 가로모드 안내 -->
  <div class="rotate-hint" id="rotateHint">
    <span class="ph">📱</span>
    <span>휴대폰을 <b>가로로 돌리면</b> 시뮬레이션이 화면 가득 크게 보입니다</span>
  </div>

  <header class="topbar">
    <button class="backlink" id="btnModels" title="저장된 모델"<?php echo $EVAC_EMBED ? ' hidden' : ''; ?>>☰</button>
    <div class="brand">
      <input id="modelName" value="새 건물" maxlength="120" aria-label="모델 이름"
             placeholder="모델 이름"<?php echo $EVAC_EMBED ? ' readonly' : ''; ?>>
      <em id="cloudState">저장됨</em>
    </div>
    <div class="kpis">
      <div class="kpi"><span class="k">잔류</span><span class="v readout" id="sIn">0</span></div>
      <div class="kpi out"><span class="k">탈출</span><span class="v readout" id="sOut">0</span></div>
      <div class="kpi dead"><span class="k">사망</span><span class="v readout" id="sDead">0</span></div>
      <div class="kpi"><span class="k">가시거리 / 노출</span><span class="v readout" id="sTen">30m / 0%</span></div>
      <div class="kpi fire"><span class="k">연소면적</span><span class="v readout" id="sFire">0㎡</span></div>
      <div class="kpi time"><span class="k">경과</span><span class="v readout" id="clock">0.0s</span></div>
    </div>
  </header>

  <div class="modelpanel" id="modelPanel" hidden>
    <div class="mp-box">
      <div class="mp-head">
        <span class="eyebrow">저장된 모델 <span id="mpCount" class="mp-cnt"></span></span>
        <button id="btnCloseModels" title="닫기">✕</button>
      </div>

      <div class="mp-tools">
        <input type="search" id="mpSearch" placeholder="이름으로 검색" autocomplete="off">
        <select id="mpSort" title="정렬">
          <option value="recent">최근 수정순</option>
          <option value="name">이름순</option>
          <option value="area">면적순</option>
        </select>
      </div>

      <div class="mp-bulk" id="mpBulk" hidden>
        <label class="mp-all"><input type="checkbox" id="mpAll"> 전체</label>
        <span id="mpSel">0개 선택</span>
        <button id="mpDel" class="danger">선택 삭제</button>
        <button id="mpCancel">취소</button>
      </div>

      <div class="mp-list" id="modelList"></div>
      <div class="mp-foot">
        <button id="btnNewModel" class="primary">＋ 새 모델</button>
        <button id="btnCopyModel">현재 도면 복제</button>
        <button id="btnExport">도면 파일</button>
        <button id="btnImport">불러오기</button>
        <input type="file" id="fileInput" accept=".txt,text/plain" hidden>
      </div>
      <div class="mp-foot">
        <button id="btnPng">이미지 저장</button>
        <button id="btnShare">열람용 주소 복사</button>
      </div>
      <p class="note" id="shareOut">열람용 주소는 편집 없이 보기만 가능합니다. 빌딩매니저 페이지에 링크하거나 iframe 으로 넣으세요.</p>
      <p class="note" id="storeNote"></p>
    </div>
  </div>

  <div class="main">
    <!-- 무대 -->
    <div class="stage" id="stage">
      <canvas id="cv" width="960" height="624" aria-label="건물 3D 모형"></canvas>

      <div class="float f-floors" id="floorTabs"></div>
      <button class="land-run" id="landRun">화재 발생 · 시작</button>
      <div class="float f-origin" id="originLegend" hidden></div>

      <div class="float f-view">
        <div class="row r2">
          <button id="btn3D" class="active">3D</button>
          <button id="btn2D">2D</button>
          <button id="btnFS" title="전체화면">⛶</button>
        </div>
        <div class="row r4">
          <button id="btnRotL" title="왼쪽 회전">⟲</button>
          <button id="btnRotR" title="오른쪽 회전">⟳</button>
          <button id="btnDown" title="시점 낮추기">▼</button>
          <button id="btnUp" title="시점 높이기">▲</button>
        </div>
        <div class="row r3">
          <button id="btnZoomOut" title="축소">−</button>
          <button id="btnFit" title="화면에 맞춤">맞춤</button>
          <button id="btnZoomIn" title="확대">＋</button>
        </div>
        <div class="row r4">
          <button class="preset" data-v="front">앞</button>
          <button class="preset" data-v="back">뒤</button>
          <button class="preset" data-v="left">좌</button>
          <button class="preset" data-v="right">우</button>
        </div>
        <div class="row r3">
          <button class="preset" data-v="iso">기본</button>
          <button class="preset" data-v="top">위</button>
          <button class="preset" data-v="under">아래</button>
        </div>
        <button id="btnSpin">자동 회전</button>
        <div class="seg" style="margin-top:4px">
          <button class="fv active" data-fv="one"   title="현재 층만 — 가장 빠릅니다">현재 층</button>
          <button class="fv"        data-fv="split" title="층을 벌려서 전부 봅니다">분할</button>
          <button class="fv"        data-fv="all"   title="실제 층고대로 쌓아서 봅니다">전체</button>
        </div>
        <label class="chk" style="padding:2px 2px 0" id="spreadWrap" hidden>
          간격 <input type="range" id="rSpread3D" min="1.4" max="4" step="0.1" value="2.2"
                     style="width:100%">
        </label>
      </div>

      <div class="float f-foot">
        <span class="eyebrow" id="viewLabel">축측투영 — 드래그로 회전</span>
      </div>
    </div>

    <!-- 레일 -->
    <aside class="rail">
      <div class="tabs">
        <button id="tabSim" class="active">시뮬레이션</button>
        <button id="tabEdit"<?php echo $EVAC_EMBED ? ' hidden' : ''; ?>>도면 편집</button>
      </div>

      <div class="panes">
        <!-- 시뮬레이션 -->
        <section class="pane" id="paneSim">
          <p class="mobile-note">모바일에서는 <b>열람 전용</b>으로 표시됩니다. 도면 편집은 PC에서 이용해 주세요.</p>
          <button class="run" id="btnStart">화재 발생 · 시작</button>
          <div class="g2">
            <button id="btnPause">일시정지</button>
            <button id="btnReset">초기화</button>
          </div>
          <p class="note">모형을 클릭하면 <b>발화 지점</b>을 지정합니다. 지정하지 않으면 무작위로 발화합니다.</p>

          <div id="verdict">
            <span class="badge" id="vBadge"></span>
            <p id="vDetail"></p>
          </div>

          <div class="block block--info">
            <span class="eyebrow">건물 현황</span>
            <div class="binfo">
              <span class="bk">층수</span><span class="bv" id="biFloors">—</span>
              <span class="bk">비상구</span><span class="bv" id="biExits">—</span>
              <span class="bk">계단</span><span class="bv" id="biStairs">—</span>
              <span class="bk">옥내소화전</span><span class="bv" id="biHyd">—</span>
              <span class="bk">화재층</span><span class="bv" id="biFire">—</span>
            </div>
          </div>

          <div class="block">
            <span class="eyebrow">시나리오</span>
            <div class="field">
              <label><span class="name">재실 인원</span><output id="oPeople"><?php echo $config['people']; ?>명</output></label>
              <input type="range" id="rPeople" min="1" max="300" value="<?php echo $config['people']; ?>">
            </div>
            <div class="field">
              <label><span class="name">재실자 구성</span><output id="oMix">성인 100%</output></label>
              <select id="selMix" style="width:100%">
                <option value="adult" selected>일반 — 성인 100%</option>
                <option value="school">학교 — 어린이 85 · 성인 15</option>
                <option value="kinder">유치원·어린이집 — 유아 80 · 성인 20</option>
                <option value="care">요양시설 — 노약자 75 · 성인 25</option>
                <option value="custom">사용자 지정</option>
              </select>
              <div id="mixCustom" class="mix-grid" hidden>
                <label>성인<input type="number" id="mxAdult" min="0" max="100" value="100"></label>
                <label>어린이<input type="number" id="mxChild" min="0" max="100" value="0"></label>
                <label>유아<input type="number" id="mxToddler" min="0" max="100" value="0"></label>
                <label>노약자<input type="number" id="mxElderly" min="0" max="100" value="0"></label>
              </div>
              <p class="mix-note">유형별 보행속도 — 성인 0.95~1.40 · 어린이 0.75~1.10 · 유아 0.50~0.80 · 노약자 0.55~0.90 m/s</p>
            </div>
            <div class="field">
              <label><span class="name">화재성장률</span><output id="oGrade">Medium</output></label>
              <select id="selGrade" style="width:100%">
                <option value="slow">Slow · tg 600s — 목재 가구, 난연 내장재</option>
                <option value="medium" selected>Medium · tg 300s — 사무실, 주택, 숨박시설</option>
                <option value="fast">Fast · tg 150s — 판매시설, 서고, 커튼·직물</option>
                <option value="ultra">Ultra-fast · tg 75s — 유류, 폴리우레탄, 적층 단열재</option>
              </select>
              <p class="note" id="gradeNote" style="margin-top:6px">
                NFPA 204 · NFPA 92의 t² 화재성장곱선. tg는 열방출률이 1 MW에 도달하는 시간입니다.
              </p>
            </div>
            <div class="field">
              <label><span class="name">성장률 보정(민감도 분석)</span><output id="oSpread">×<?php echo $config['spread']; ?></output></label>
              <input type="range" id="rSpread" min="0.2" max="3" step="0.1" value="<?php echo $config['spread']; ?>">
            </div>
            <div class="field">
              <label><span class="name">보행 속도</span><output id="oSpeed">×<?php echo $config['walkSpeed']; ?></output></label>
              <input type="range" id="rSpeed" min="0.5" max="2" step="0.1" value="<?php echo $config['walkSpeed']; ?>">
            </div>
          </div>

          <div class="block">
            <span class="eyebrow">범례</span>
            <div class="legend">
              <span><i class="sw" style="background:#22201C"></i>재실자</span>
              <span><i class="sw" style="background:var(--signal)"></i>화염</span>
              <span><i class="sw" style="background:#4A4640"></i>연기</span>
              <span><i class="sw" style="background:var(--exit)"></i>비상구</span>
              <span><i class="sw" style="background:var(--data)"></i>계단</span>
              <span><i class="sw" style="background:var(--warn)"></i>고립</span>
              <span><i class="sw" style="background:#C62A12"></i>옥내소화전</span>
            </div>
          </div>
        </section>

        <!-- 편집 -->
        <section class="pane" id="paneEdit" hidden>
          <div class="stickybar">
            <div class="g2">
              <button id="btnUndo" title="Ctrl+Z">↶ 실행취소</button>
              <button id="btnRedo" title="Ctrl+Shift+Z">↷ 다시실행</button>
            </div>
            <div class="zoomrow">
              <button id="btnPZOut" title="축소">−</button>
              <button id="btnPZFit" title="화면에 맞춤 (스페이스+더블클릭)">맞춤</button>
              <button id="btnPZIn" title="확대 (트랙패드 핌치 또는 Ctrl+휠)">＋</button>
              <span class="eyebrow" id="editCoord">—</span>
            </div>
            <span class="eyebrow saved" id="saveState">자동 저장 켜짐</span>
          </div>

          <div class="block">
            <span class="eyebrow">도구</span>
            <div class="toolrow">
              <button data-tool="wall" class="active">벽<i>1</i></button>
              <button data-tool="floor">바닥<i>2</i></button>
              <button data-tool="exit">비상구<i>3</i></button>
              <button data-tool="room">구획<i>4</i></button>
              <button data-tool="stair">계단실<i>5</i></button>
              <button data-tool="door">문<i>7</i></button>
              <button data-tool="erase" title="드래그한 영역을 통째로 지웁니다">지우기<i>8</i></button>
              <button data-tool="poly" title="꼭짓점을 이어서 벽선을 그립니다 · Enter 완료 · Esc 취소">연결선<i>9</i></button>
            </div>
            <button data-tool="hydrant" style="width:100%">옥내소화전 배치<i style="opacity:.55"> 6</i></button>
            <button id="btnDoorLock" class="active" style="width:100%;margin-top:5px"
                    title="문이 놓인 자리를 다른 도구로 덮어쓰지 못하게 합니다">🔒 문 위치 잠금</button>

            <span class="eyebrow">그리는 방식</span>
            <div class="toolrow" id="shapeRow">
              <button data-shape="rect" class="active">사각형<i>E</i></button>
              <button data-shape="line">직선<i>W</i></button>
              <button data-shape="fill">채우기<i>R</i></button>
              <button data-shape="free">자유<i>Q</i></button>
              <button id="btnClearFloor" title="이 층을 빈 껍데기로">층 비우기</button>
              <button id="btnCropMargin" title="비어 있는 바깥 여백을 잘라 도면을 줄입니다">여백 잘라내기</button>
            </div>
            <div id="brushRow" class="g3" hidden>
              <button class="brush active" data-b="1">붓 1칸</button>
              <button class="brush" data-b="3">3칸</button>
              <button class="brush" data-b="5">5칸</button>
            </div>
            <p class="note" id="shapeHint">두 점을 끌어 사각형 영역을 한 번에 채웁니다.</p>
          </div>

          <div class="block" id="traceBlock">
            <span class="eyebrow">밑그림 도면</span>
            <div class="g2">
              <button id="btnTraceLoad">사진 불러오기</button>
              <button id="btnTraceClear" disabled>제거</button>
            </div>
            <input type="file" id="traceFile" accept="image/*" hidden>

            <div id="traceCtl" hidden>
              <div class="g2" style="margin-top:6px">
                <button id="btnTraceCal">축척 맞추기</button>
                <button id="btnTraceMove" title="끌어서 위치 이동">위치 이동</button>
              </div>
              <label class="eyebrow" style="display:block;margin-top:8px">
                회전 <span id="traceRotV" style="float:right;font-weight:600">0°</span>
              </label>
              <input type="range" id="traceRot" min="-180" max="180" step="0.5" value="0" style="width:100%">
              <div class="g3" style="margin-top:4px">
                <button id="btnRotL90" title="왼쪽으로 90°">↶ 90°</button>
                <button id="btnRot0"   title="회전 초기화">0°</button>
                <button id="btnRotR90" title="오른쪽으로 90°">90° ↷</button>
              </div>

              <label class="eyebrow" style="display:block;margin-top:8px">투명도</label>
              <input type="range" id="traceOp" min="0.1" max="1" step="0.05" value="0.55" style="width:100%">
              <p class="note" id="traceInfo">축척을 맞추면 도면 크기를 자동으로 계산합니다.</p>
            </div>
            <p class="note" id="traceHint">종이 도면 사진을 깔고 그 위에 벽을 따라 그릴 수 있습니다. 저장되지는 않습니다.</p>
          </div>

          <div class="block">
            <span class="eyebrow">계단 설정</span>
            <div id="stairCfgList"></div>
          </div>

          <div class="block">
            <span class="eyebrow">피난 진단</span>
            <div class="diag">
              <div class="drow"><span>재실 가능 면적</span><span class="dv" id="dgArea">—</span></div>
              <div class="drow"><span>비상구</span><span class="dv" id="dgExits">—</span></div>
              <div class="drow"><span>계단실</span><span class="dv" id="dgStairs">—</span></div>
              <div class="drow"><span>최장 보행거리</span><span class="dv" id="dgTravel">—</span></div>
              <div class="drow"><span>고립 구역</span><span class="dv" id="dgIso">—</span></div>
              <div class="drow"><span>옥내소화전 방호거리</span><span class="dv" id="dgHyd">—</span></div>
              <p class="dnote" id="dgNote">—</p>
            </div>
          </div>

          <div class="block">
            <span class="eyebrow">층</span>
            <div class="g2">
              <button id="btnFlAdd">지상층 추가</button>
              <button id="btnFlDel">최상층 삭제</button>
            </div>
            <div class="g2">
              <button id="btnBaseAdd">지하층 추가</button>
              <button id="btnBaseDel">최하층 삭제</button>
            </div>
            <button id="btnCopyFloor">이 층을 모든 층에 복제</button>
            <p class="note">현재 <b id="fCount">2층</b>. 그리기는 <b>보고 있는 층</b>에 적용됩니다.</p>
          </div>

          <div class="block">
            <span class="eyebrow">시작 평면</span>
            <div class="g4">
              <button class="tpl" data-t="blank">빈 도면</button>
              <button class="tpl" data-t="double">중복도</button>
              <button class="tpl" data-t="single">편복도</button>
              <button class="tpl" data-t="open">개방형</button>
            </div>
            <p class="note"><b>빈 도면</b>은 테두리만 벽인 빈 껍데기로 시작합니다. 층 수는 그대로 유지됩니다.</p>
          </div>

          <div class="block">
            <span class="eyebrow">도면 텍스트</span>
            <textarea id="mapText" spellcheck="false" aria-label="도면 텍스트"></textarea>
            <div class="g2">
              <button id="btnMapApply">적용</button>
              <button id="btnMapCopy">텍스트 복사</button>
            </div>
            <button id="btnMapSave" hidden></button>
            <button id="btnMapDefault">기본 도면으로 되돌리기</button>
            <p class="note"><b>구획</b>·<b>계단실</b>은 드래그로 그립니다. 둘레에 벽이 서고 트인 쪽으로 1 m 문이 납니다.
              계단실은 <b>모든 층 같은 자리</b>에 함께 놓입니다.<br>
              <kbd>#</kbd> 벽 <kbd>.</kbd> 바닥 <kbd>E</kbd> 비상구 <kbd>S</kbd> 계단 <kbd>H</kbd> 소화전 <kbd>D</kbd> 문</p>
          </div>
          <button id="btnEdit" hidden></button>
          <span id="editHint" hidden></span>
          <span id="editTools" hidden></span>
        </section>
      </div>
    </aside>
  </div>
</div>

<script>
/* =========================================================
   설정 (PHP → JS)
========================================================= */
const CFG = <?php echo json_encode($config); ?>;
const EMBED     = <?php echo $EVAC_EMBED ? 'true' : 'false'; ?>;
const AUTO      = <?php echo ($EVAC_EMBED && $EVAC_AUTO) ? 'true' : 'false'; ?>;
const HOSTED    = <?php echo $EVAC_HOST ? 'true' : 'false'; ?>;
const IS_ADMIN  = <?php echo $EVAC_IS_ADMIN ? 'true' : 'false'; ?>;
const LIB_CSRF  = <?php echo json_encode($EVAC_CSRF); ?>;
const LIB_API   = '/evac_library_api.php';
const SAVE_URL  = <?php echo json_encode((string)$EVAC_SAVE_URL); ?>;
const HOST_SCN  = <?php echo json_encode((string)$EVAC_SCENARIO); ?>;
const EMBED_MAP = <?php echo json_encode((string)$EVAC_MAP); ?>;
const EMBED_NAME= <?php echo json_encode((string)$EVAC_NAME); ?>;

const CELL = 14;                 // px / 셀 (화면 표시용 크기)
const M_PER_CELL  = 1.0;         // 셀 1칸 = 1 m
let GW = 58, GH = 45, FLOORS = 2;
const M2_PER_CELL = M_PER_CELL * M_PER_CELL;   // ㎡ / 셀
const MAX_W = 200, MAX_H = 200;                // 도면 한 변의 최대 칸 수(= m)
const PX_PER_M    = CELL / M_PER_CELL;         // 속도 환산용 (px ↔ m)

const WALL = 0, FLOOR = 1, EXIT = 2, STAIR = 3, HYDRANT = 4, DOOR = 5;
const TILE_CH = ['#','.','E','S','H','D'];
/* 문(DOOR)은 통행 가능한 개구부다. 한번 놓으면 잠금 상태가 되어
   다른 도구로 덮어쓸 수 없다(잠금 해제 후에만 수정 가능). */
let doorLock = true;

const cv  = document.getElementById('cv');
const ctx = cv.getContext('2d');
let PLAN_PAD = 0;             // 평면도 눈금자용 바깥 여백(px)
const RULER_W   = 22;         // 눈금 띠 두께
const RULER_GAP = 10;         // 눈금 띠와 도면 사이 간격

/* =========================================================
   건물: 층별 격자 (grids[f][y][x])
========================================================= */
let grids, smokes, fires, fuels, dist;   // dist는 3D 평탄 Float64Array
let smokeBuf = null;                     // 연기 확산 계산용 재사용 버퍼
let smokeActive = [];                    // 층별로 연기가 있는지 (없으면 계산 생략)
let customMapText = null;
let exitLabels = [];                     // [f] → [[px,py],...]
let viewF = 0;                           // 현재 보고 있는 층

const idx = (f,y,x) => (f*GH + y)*GW + x;
const dAt = (f,y,x) =>
  (f<0||f>=FLOORS||y<0||y>=GH||x<0||x>=GW) ? Infinity : dist[idx(f,y,x)];

function buildDefaultGrids(){
  GW=58; GH=45; FLOORS=2;                       // 58 m × 45 m 기준층 (셀 1칸 = 1 m)
  const CT=21, CB=24;                           // 중앙 복도 y 21~24 (4 m)
  const PARTS=[10,20,29,38,48];                 // 실 구획선
  const DOORW=1;                                // 출입문 1 m

  const mk=()=>Array.from({length:GH},()=>new Array(GW).fill(FLOOR));
  const g=mk();

  // 외벽
  for(let x=0;x<GW;x++){ g[0][x]=WALL; g[GH-1][x]=WALL; }
  for(let y=0;y<GH;y++){ g[y][0]=WALL; g[y][GW-1]=WALL; }

  // 복도 양쪽 벽
  for(let x=1;x<GW-1;x++){ g[CT-1][x]=WALL; g[CB+1][x]=WALL; }

  // 실 구획 (복도 위/아래 각각)
  for(const px of PARTS){
    for(let y=1;y<CT-1;y++)    g[y][px]=WALL;
    for(let y=CB+2;y<GH-1;y++) g[y][px]=WALL;
  }

  // 각 실의 출입문 — 구획 사이 중앙에 1 m 개구부
  const edges=[0,...PARTS,GW-1];
  for(let i=0;i<edges.length-1;i++){
    const mid=Math.round((edges[i]+edges[i+1])/2);
    for(let d=0;d<DOORW;d++){
      g[CT-1][mid+d]=FLOOR;
      g[CB+1][mid+d]=FLOOR;
    }
  }

  // 계단실 두 곳 — 건물 양 끝, 복도에서 직접 진입
  const stairs=[
    {x0:3,  x1:8,  y0:27, y1:40, doorY:CB+1, doorX:5},   // 서측
    {x0:49, x1:54, y0:4,  y1:17, doorY:CT-1, doorX:51},  // 동측
  ];
  for(const s of stairs){
    for(let y=s.y0;y<=s.y1;y++) for(let x=s.x0;x<=s.x1;x++) g[y][x]=STAIR;
    // 계단실 벽으로 감싸고 복도 쪽에만 문
    for(let x=s.x0-1;x<=s.x1+1;x++){
      if(x<0||x>=GW) continue;
      if(s.y0-1>=0)  g[s.y0-1][x]=WALL;
      if(s.y1+1<GH)  g[s.y1+1][x]=WALL;
    }
    for(let y=s.y0-1;y<=s.y1+1;y++){
      if(y<0||y>=GH) continue;
      if(s.x0-1>=0)  g[y][s.x0-1]=WALL;
      if(s.x1+1<GW)  g[y][s.x1+1]=WALL;
    }
    for(let d=0;d<DOORW+1;d++) g[s.doorY][s.doorX+d]=FLOOR;   // 계단실 출입문 2 m
  }

  // 위층은 같은 평면, 지상 비상구는 벽으로
  const upper=g.map(r=>r.map(c=>c===EXIT?WALL:c));

  // 1층 피난구 — 복도 양 끝 직통 출구 + 남측 중앙 보조 출구
  for(let y=CT;y<=CB;y++){ g[y][0]=EXIT; g[y][GW-1]=EXIT; }
  const sx0=28, sx1=30;
  for(let x=sx0;x<=sx1;x++) g[GH-1][x]=EXIT;
  // 남측 출구로 나가는 복도 연결
  for(let y=CB+1;y<GH-1;y++) for(let x=sx0;x<=sx1;x++) g[y][x]=FLOOR;

  grids=[g,upper];
}

/* 도면 텍스트 ↔ 격자  (층 구분: '=== 1F ===' 형식의 = 로 시작하는 줄) */
function serializeMap(){
  const body = grids.map((g,f) =>
    '=== '+(f+1)+'F ===\n' +
    g.map(row => row.map(c => TILE_CH[c]).join('')).join('\n')
  ).join('\n');
  const meta = (BASEMENTS>0 ? ['@B '+BASEMENTS] : []);
  meta.push(...Object.entries(stairCfg)
    .filter(([k,v]) => v && (v.type||v.dir||v.hand))
    .map(([k,v]) => '@S '+k+' '+(v.type||'auto')+' '+(v.dir||'auto')+' '+(v.hand||'auto')));
  return meta.length ? body+'\n'+meta.join('\n') : body;
}
function parseMapText(text){
  const lines = text.replace(/\r/g,'').split('\n');
  const floors=[]; let cur=[]; const cfg={}; let base=0;
  for(const l of lines){
    if(l.startsWith('@B')){ base=Math.max(0,parseInt(l.slice(2))||0); continue; }
    if(l[0]==='@'){                       // 계단 설정 줄
      const p=l.slice(2).trim().split(/\s+/);
      if(p.length>=3){
        const c={};
        if(p[1]!=='auto') c.type=p[1];
        if(p[2]!=='auto') c.dir=p[2];
        if(p[3] && p[3]!=='auto') c.hand=p[3];
        cfg[p[0]]=c;
      }
      continue;
    }
    if(/^\s*=/.test(l)){ if(cur.length) floors.push(cur); cur=[]; continue; }
    if(l.trim().length===0) continue;
    cur.push(l);
  }
  if(cur.length) floors.push(cur);
  if(!floors.length) return '도면이 비어 있습니다.';
  if(floors.length>MAX_FLOORS) return '층 수는 최대 '+MAX_FLOORS+'층입니다.';
  const h = Math.max(...floors.map(fl=>fl.length));
  const w = Math.max(...floors.map(fl=>Math.max(...fl.map(l=>l.length))));
  if(h<5||w<5) return '각 층은 최소 5×5 이상이어야 합니다.';
  if(w>MAX_W||h>MAX_H) return '층당 최대 크기는 '+MAX_W+'×'+MAX_H+' 입니다.';
  for(const fl of floors) for(const l of fl)
    if(/[^#.ESHD ]/.test(l)) return '허용 기호는 # . E S H D (공백은 벽 취급) 뿐입니다.';

  const parsed = floors.map(fl => {
    const g=[];
    for(let y=0;y<h;y++){
      const line = fl[y] ?? '';
      const row=[];
      for(let x=0;x<w;x++){
        const ch = line[x] ?? '#';
        row.push(ch==='#'||ch===' ' ? WALL : ch==='E' ? EXIT :
                 ch==='S' ? STAIR : ch==='H' ? HYDRANT :
                 ch==='D' ? DOOR : FLOOR);
      }
      g.push(row);
    }
    return g;
  });
  // 비상구·계단이 아직 없어도 도면 자체는 받아들인다.
  // (빈 껍데기부터 그려 나갈 수 있어야 하므로, 부족한 부분은 피난 진단이 알려준다)
  FLOORS=parsed.length; GH=h; GW=w; grids=parsed; stairCfg=cfg;
  BASEMENTS=Math.min(base, Math.max(0,FLOORS-1));
  return null;
}

/* 비상구 라벨: 층별로 붙어있는 EXIT 셀 클러스터 중심 */
function computeExitLabels(){
  exitLabels = grids.map(g => {
    const labels=[];
    const seen = Array.from({length:GH}, () => new Uint8Array(GW));
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      if(g[y][x]!==EXIT || seen[y][x]) continue;
      const q=[[x,y]]; seen[y][x]=1; let sx=0,sy=0,n=0;
      while(q.length){
        const [cx,cy]=q.pop(); sx+=cx; sy+=cy; n++;
        for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){
          const nx=cx+dx, ny=cy+dy;
          if(nx<0||ny<0||nx>=GW||ny>=GH||seen[ny][nx]) continue;
          if(g[ny][nx]===EXIT){ seen[ny][nx]=1; q.push([nx,ny]); }
        }
      }
      labels.push([(sx/n+.5)*CELL, (sy/n+.5)*CELL]);
    }
    return labels;
  });
}

function buildMap(){
  if(customMapText){
    const err = parseMapText(customMapText);
    if(err){
      /* 조용히 기본 도면으로 되돌리면 사용자가 이유를 알 수 없다 */
      console.warn('도면을 읽지 못해 기본 도면으로 대체합니다: ' + err);
      try{ setCloud('도면 오류 — ' + err, 'failed'); }catch(e){}
      customMapText=null; buildDefaultGrids();
    }
  }else{
    buildDefaultGrids(); stairCfg={};
  }
  isoCache.yaw=null; gridVer++; updateFloorH(); ftabEls=null;
  viewF = Math.min(viewF, FLOORS-1);
  computeExitLabels();

  const mkF32 = () => Array.from({length:GH}, () => new Float32Array(GW));
  smokes = Array.from({length:FLOORS}, mkF32);
  /* 연기 확산용 예비 버퍼 — 매 프레임 새로 만들지 않고 재사용한다 */
  smokeBuf = mkF32();
  smokeActive = new Array(FLOORS).fill(false);
  fires  = Array.from({length:FLOORS}, () => Array.from({length:GH}, () => new Uint8Array(GW)));
  fuels  = Array.from({length:FLOORS}, () => Array.from({length:GH}, () => new Float32Array(GW).fill(1)));
}

/* =========================================================
   3D 유동장 (이진 힙 다익스트라)
   같은 층 4방향 + 정렬된 계단쌍으로 위아래 층 이동
   주의: dist는 float64 — Float32는 반올림 오차로 경로가 끊김
========================================================= */
function computeDist(){
  const N = FLOORS*GH*GW;
  dist = new Float64Array(N).fill(Infinity);

  const hk=[], hi=[];                    // 힙: 키/노드id 병렬 배열
  const push=(k,id)=>{
    hk.push(k); hi.push(id);
    let i=hk.length-1;
    while(i>0){ const p=(i-1)>>1;
      if(hk[p]<=hk[i]) break;
      [hk[p],hk[i]]=[hk[i],hk[p]]; [hi[p],hi[i]]=[hi[i],hi[p]]; i=p; }
  };
  const pop=()=>{
    const k=hk[0], id=hi[0];
    const lk=hk.pop(), li=hi.pop();
    if(hk.length){
      hk[0]=lk; hi[0]=li;
      let i=0;
      for(;;){ const l=2*i+1, r=l+1; let m=i;
        if(l<hk.length&&hk[l]<hk[m]) m=l;
        if(r<hk.length&&hk[r]<hk[m]) m=r;
        if(m===i) break;
        [hk[m],hk[i]]=[hk[i],hk[m]]; [hi[m],hi[i]]=[hi[i],hi[m]]; i=m; }
    }
    return [k,id];
  };

  for(let f=0;f<FLOORS;f++) for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    if(grids[f][y][x]===EXIT && !fires[f][y][x]){
      dist[idx(f,y,x)]=0; push(0, idx(f,y,x));
    }
  }
  const dirs=[[1,0],[-1,0],[0,1],[0,-1]];

  while(hk.length){
    const [d,id]=pop();
    if(d>dist[id]) continue;
    const x=id%GW, y=(id/GW|0)%GH, f=(id/(GW*GH))|0;
    const g=grids[f], fr=fires[f], sm=smokes[f];

    // 같은 층 4방향
    for(const [dx,dy] of dirs){
      const nx=x+dx, ny=y+dy;
      if(nx<0||ny<0||nx>=GW||ny>=GH) continue;
      if(g[ny][nx]===WALL || fr[ny][nx]) continue;
      let nearFire=0;
      for(const [ax,ay] of dirs){ if(fr[ny+ay]?.[nx+ax]){ nearFire=6; break; } }
      const nd = d + 1 + sm[ny][nx]*8 + nearFire;
      const ni = idx(f,ny,nx);
      if(nd<dist[ni]){ dist[ni]=nd; push(nd,ni); }
    }
    // 계단 수직 이동 (양 층 같은 위치가 모두 S일 때)
    if(g[y][x]===STAIR){
      for(const df of [-1,1]){
        const nf=f+df;
        if(nf<0||nf>=FLOORS) continue;
        if(grids[nf][y][x]!==STAIR || fires[nf][y][x]) continue;
        const nd = d + 2.5 + smokes[nf][y][x]*8;
        const ni = idx(nf,y,x);
        if(nd<dist[ni]){ dist[ni]=nd; push(nd,ni); }
      }
    }
  }
}

/* =========================================================
   재실자 (졸라맨)
========================================================= */
let agents = [];
/* ── 재실자 유형 — 보행속도(m/s)와 체구 비율 ──
   성인   : SFPE·피난안전검증법 평지 0.95~1.40
   어린이 : 초등 연령 0.75~1.10
   유아   : 보호자 동반 전제 0.50~0.80
   노약자 : 고령·거동불편 0.55~0.90 (Boyce 등 장애인 보행 연구 범위) */
const P_TYPES = {
  adult:   { name:'성인',   v:[0.95,1.40], scale:1.00 },
  child:   { name:'어린이', v:[0.75,1.10], scale:0.78 },
  toddler: { name:'유아',   v:[0.50,0.80], scale:0.58 },
  elderly: { name:'노약자', v:[0.55,0.90], scale:0.94 },
};
let MIX = { adult:100, child:0, toddler:0, elderly:0 };   // 가중치 (합계 무관)

function pickType(){
  let tot=0; for(const k in MIX) tot += Math.max(0, +MIX[k]||0);
  if(tot<=0) return 'adult';
  let r=Math.random()*tot;
  for(const k in MIX){ r -= Math.max(0,+MIX[k]||0); if(r<0) return k; }
  return 'adult';
}

/* ── 출발 층 색 — 어느 층에서 내려온 사람인지 몸 색으로 구분 ── */
const FLOOR_COL = ['#3A78C3','#00A67E','#9333EA','#D8536B','#0FA3B1','#B45309','#65A30D','#DB2777'];
function agentCol(a){
  if(a.state==='dead')     return '#E03A12';
  if(a.state==='isolated') return '#E2941A';
  if(FLOORS>1 && a.homeF!=null) return FLOOR_COL[a.homeF % FLOOR_COL.length];
  return '#22201C';
}

function spawnAgents(n){
  agents = [];
  const spots = [];
  for(let f=0;f<FLOORS;f++) for(let y=1;y<GH-1;y++) for(let x=1;x<GW-1;x++)
    if(grids[f][y][x]===FLOOR) spots.push([f,x,y]);
  if(!spots.length) return;
  for(let i=0;i<n;i++){
    const [f,gx,gy] = spots[Math.floor(Math.random()*spots.length)];
    const tk = pickType(), T = P_TYPES[tk];
    agents.push({
      f,
      homeF:f,                      // 출발 층 (색 구분용, 이동해도 안 바뀜)
      type:tk,
      scale:T.scale,
      x:(gx+.5)*CELL + (Math.random()-.5)*8,
      y:(gy+.5)*CELL + (Math.random()-.5)*8,
      hp:100,
      fed:0,                        // 누적 노출량 (1.0 → 행동불능)
      /* 유형별 보행속도 */
      speed:(T.v[0] + Math.random()*(T.v[1]-T.v[0])) * PX_PER_M,
      phase:Math.random()*Math.PI*2,
      dir:1,
      state:'idle',                 // idle | run | escaped | dead | isolated
      stairT:0, stairTo:0, stairFrom:0, stairRun:null, stairLo:0,  // 계단 이동 상태
      tEscape:0,
    });
  }
  updateOriginLegend();
}

/* 출발 층 범례 갱신 — 여러 층일 때만 보인다 */
function updateOriginLegend(){
  const el=document.getElementById('originLegend');
  if(!el) return;
  if(FLOORS<=1 || editMode){ el.hidden=true; return; }
  let html='<span class="t">출발 층</span>';
  for(let f=FLOORS-1; f>=0; f--){
    html += '<span class="li"><span class="sw" style="background:'+FLOOR_COL[f%FLOOR_COL.length]+'"></span>'
          + floorLabel(f) + '</span>';
  }
  el.innerHTML=html; el.hidden=false;
}

/* =========================================================
   시뮬레이션 상태
========================================================= */
let running=false, ended=false, simT=0, ignition=null, distTimer=0, allTrappedT=0;
let ignitionT=0;                     // 발화 시각(s) — t² 성장의 기준점
let fireGrade='medium';              // slow | medium | fast | ultra

/* ── 건물 현황: 층수·비상구(층별/총)·계단·소화전 집계 ── */
function updateBldgInfo(){
  const eF = document.getElementById('biFloors');
  if(!eF) return;
  const perE=[], perH=[]; let tE=0, tH=0;
  for(let f=0; f<FLOORS; f++){
    const e = (exitLabels[f]||[]).length; tE += e;
    let h = 0;
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
      if(grids[f][y][x]===HYDRANT) h++;
    tH += h;
    if(e) perE.push(floorShort(f)+' '+e);
    if(h) perH.push(floorShort(f)+' '+h);
  }
  let st = 0;
  try{ st = (stairRuns()[0]||[]).length; }catch(e){}
  eF.textContent = (BASEMENTS>0 ? '지하 '+BASEMENTS+' · ' : '') + '지상 '+(FLOORS-BASEMENTS)+'층';
  document.getElementById('biExits').textContent =
    tE ? '총 '+tE+'개소' + (FLOORS>1 && perE.length ? ' — '+perE.join(', ') : '') : '없음';
  document.getElementById('biStairs').textContent = st ? st+'개소' : (FLOORS>1 ? '없음' : '—');
  document.getElementById('biHyd').textContent =
    tH ? '총 '+tH+'개' + (FLOORS>1 && perH.length ? ' — '+perH.join(', ') : '') : '없음';
  document.getElementById('biFire').textContent = '—';
}

function reset(){
  running=false; ended=false; simT=0; ignition=null; allTrappedT=0; ignitionT=0;
  buildMap(); computeDist();
  spawnAgents(+rPeople.value);
  document.getElementById('verdict').className='';
  updateFloorTabs();
  updateStats();
  updateBldgInfo();
}

function igniteAt(f,gx,gy){
  const t=grids[f]?.[gy]?.[gx];
  if(t!==FLOOR && t!==STAIR && t!==DOOR) return false;
  fires[f][gy][gx]=1; smokes[f][gy][gx]=1;
  return true;
}

function start(){
  if(ended) reset();
  if(!agents.some(a=>a.state==='idle'||a.state==='run')) reset();
  if(!ignition){
    let tries=0;
    while(tries++<1000){
      const f=Math.floor(Math.random()*FLOORS);
      const gx=1+Math.floor(Math.random()*(GW-2));
      const gy=1+Math.floor(Math.random()*(GH-2));
      if(grids[f][gy][gx]!==FLOOR) continue;   // 무작위 발화는 바닥에서만
      if(igniteAt(f,gx,gy)){ ignition=[f,gx,gy]; ignitionT=simT; break; }
    }
  }
  agents.forEach(a=>{ if(a.state==='idle') a.state='run'; });
  computeDist();
  running=true;
}

/* =========================================================
   화재 · 연기 확산 (+계단 굴뚝 효과)
========================================================= */
/* ============================================================
   화재 모델 — t² 화재성장곱선 (NFPA 204 / NFPA 92, KS F ISO 24679 계열)

     Q(t) = α · t²           Q: 열방출률(kW), t: 발화 후 경과시간(s)
     α = 1055 / tg²          tg: 1055 kW(1 MW)에 도달하는 시간(s)

   연소면적 A = Q / HRRPUA → 등가반경 r = √(A/π)
   즉 불은 확률이 아니라 “반경 r 안쪽이 연소 중”으로 결정된다.
   격자 크기(M_PER_CELL)가 바뀌어도 결과가 변하지 않는다.
   ============================================================ */
const FIRE_TG = { slow:600, medium:300, fast:150, ultra:75 };   // s (1 MW 도달)
const HRRPUA  = 500;        // kW/㎡ — 사무실·판매시설 통상값(NFPA 92 부록 범위 250~500)
const FLASHOVER_Q = 1055;   // kW — 플래시오버 임계 열방출률(약 1 MW)
const FIRE_QMAX = 20000;    // kW — 환기지배 화재로 전환되는 상한(무한 성장 방지)

/* 연기 확산 — 수평 약 0.75 m/s, 계단 수직 상승 약 2.5 m/s
   (SFPE Handbook / 일본 피난안전검증법의 연기선단 이동속도 범위) */
/* ============================================================
   생존 한계(tenability) — ISO 13571 / SFPE Handbook

   연기 농도 s(0~1)를 광학밀도·가시거리·CO 농도로 환산해
   누적 노출량(FED)으로 행동불능을 판정한다.
   화상이 아니라 ‘연기를 얼마나 오래 마셨는가’가 기준이다.
   실제 화재 사망의 60~80%가 연기·유독가스 흡입이다.
   ============================================================ */
const OD_MAX  = 3.0;    // 1/m  — 포화된 연기층의 광학밀도(NFPA 92 설계범위 2~5)
const CO_MAX  = 5000;   // ppm  — 플래시오버 이후 구획 내 CO 농도
const VIS_K   = 8;      // Jin 상관식의 반사형 표지 계수
const RMV     = 25;     // L/min — 분당 호흡량(경작업)
const COHb_D  = 30;     // %     — 행동불능 COHb 농도

/* 가시거리(m) — Jin: S = K / OD */
function visibilityOf(sm){
  const od = sm*OD_MAX;
  return od>0.001 ? Math.min(30, VIS_K/od) : 30;
}
/* FED 증가율(초당) — Purser 식, ISO 13571
   FED/min = 3.317×10⁻⁵ · C_CO^1.036 · RMV / D */
function fedRate(sm){
  const co = sm*CO_MAX;
  if(co<=0) return 0;
  return (3.317e-5*Math.pow(co,1.036)*RMV/COHb_D)/60;
}
/* 가시거리에 따른 보행속도 계수 — Jin(1978), Frantzich & Nilsson(2003)
   연기 속에서는 방향을 잃고 벽을 더듬어 가므로 급격히 느려진다 */
function speedFactor(sm){
  const v = visibilityOf(sm);
  if(v>=10) return 1.00;
  if(v>=5)  return 0.83;   // 1.0 / 1.2
  if(v>=3)  return 0.58;   // 0.7 / 1.2
  if(v>=2)  return 0.42;   // 0.5 / 1.2
  return 0.25;             // 0.3 / 1.2 — 거의 더듬어 이동
}

const SMOKE_V_H  = 0.75;    // m/s 수평
const SMOKE_V_UP = 2.50;    // m/s 계단 상승
const SMOKE_V_DN = 0.40;    // m/s 계단 하강(약함)

function fireAlpha(){
  const key = (typeof fireGrade!=='undefined' && fireGrade) ? fireGrade : 'medium';
  const tg  = FIRE_TG[key] || FIRE_TG.medium;
  return 1055/(tg*tg);                       // kW/s²
}

/* 현재 열방출률과 연소 반경 */
function fireState(){
  const t = Math.max(0, simT - (ignitionT||0));
  const spread = +rSpread.value;             // 사용자 배율(감도분석용)
  let Q = fireAlpha() * t * t * spread;
  Q = Math.min(Q, FIRE_QMAX);

  /* 플래시오버 — 구획 내 열방출률이 임계값을 넘으면
     불꽃이 아니라 공간 전체가 동시에 발화한다.
     간이 기준: Q ≥ FLASHOVER_Q (약 1 MW, 일반 거실·사무실 규모) */
  const flash = Q >= FLASHOVER_Q;
  const A = Q / HRRPUA;                      // ㎡
  let r = Math.sqrt(A/Math.PI);
  if(flash){
    /* 플래시오버 후에는 구획 전체로 빠르게 확대 (초당 약 1 m) */
    r += (Q - FLASHOVER_Q) / FLASHOVER_Q * 1.0 * (t>0 ? 1 : 0);
  }
  return { t, Q, A, r, flash };
}

function stepFire(dt){
  const dirs=[[1,0],[-1,0],[0,1],[0,-1]];
  const st = fireState();
  const rCell = st.r / M_PER_CELL;           // 연소 반경(칸)

  for(let f=0;f<FLOORS;f++){
    const g=grids[f], fr=fires[f], fu=fuels[f], sm=smokes[f];

    /* 연기도 불도 없는 층은 계산할 것이 없다 — 층이 많을수록 효과가 크다 */
    if(!(ignition && ignition[0]===f) && !smokeActive[f]) continue;

    /* ── 화염: 발화점에서 반경 r 이내를 연소 상태로 ──
       벽을 통과하지 않도록 발화점에서 BFS로 거리를 재고,
       실제 보행거리 기준으로 r 이내인 칸만 불이 붙는다. */
    if(ignition && ignition[0]===f && rCell>0){
      const seedX=ignition[1], seedY=ignition[2];
      const q=[[seedY,seedX,0]];
      const seen=new Set([seedY+','+seedX]);
      while(q.length){
        const [y,x,d]=q.shift();
        if(d>rCell) continue;
        if(fu[y][x]>0){ fr[y][x]=1; fu[y][x]-=dt*(st.Q/FIRE_QMAX)*0.6; }
        for(const [dx,dy] of dirs){
          const nx=x+dx, ny=y+dy;
          if(nx<1||ny<1||nx>=GW-1||ny>=GH-1) continue;
          if(g[ny][nx]===WALL) continue;
          const k=ny+','+nx;
          if(seen.has(k)) continue;
          seen.add(k); q.push([ny,nx,d+1]);
        }
      }
    }

    /* ── 연기 확산 ──
       확산계수를 속도(m/s)에서 유도한다.
       인접 칸로 전달되는 비율 = v·dt / 셀크기  (CFL 안정을 위해 0.5로 제한) */
    const kH = Math.min(0.5, SMOKE_V_H*dt/M_PER_CELL);
    let hasSmoke=false;
    const next = smokeBuf;
    /* 테두리는 계산하지 않으므로 그대로 복사해 둔다 */
    for(let y=0;y<GH;y++) next[y].set(sm[y]);
    for(let y=1;y<GH-1;y++) for(let x=1;x<GW-1;x++){
      if(g[y][x]===WALL) continue;
      let sum=0,cnt=0;
      for(const [dx,dy] of dirs){
        if(g[y+dy][x+dx]!==WALL){ sum+=sm[y+dy][x+dx]; cnt++; }
      }
      const avg = cnt?sum/cnt:0;
      let s = sm[y][x] + (avg-sm[y][x])*kH;
      /* 연소 중인 칸은 연기를 공급한다(열방출률에 비례) */
      if(fr[y][x]) s += dt * (0.3 + 0.7*Math.min(1, st.Q/2000));
      const v=Math.min(1,Math.max(0,s));
      next[y][x]=v;
      if(v>0.001) hasSmoke=true;
    }
    smokes[f]=next; smokeBuf=sm;      // 버퍼 교대 사용(스왑)
    smokeActive[f]=hasSmoke;
  }

  /* ── 계단 수직 전파 — 굴뚝 효과 ──
     층고를 3.5 m로 보고, 상승속도로 올라가는 데 걸리는 시간을 비율로 환산 */
  const FLOOR_H = 3.5;                                   // m
  const kUp = Math.min(0.9, SMOKE_V_UP*dt/FLOOR_H);
  const kDn = Math.min(0.5, SMOKE_V_DN*dt/FLOOR_H);
  for(let f=0;f<FLOORS-1;f++){
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      if(grids[f][y][x]!==STAIR || grids[f+1][y][x]!==STAIR) continue;
      const lo=smokes[f][y][x], hi=smokes[f+1][y][x];
      if(lo>hi){
        smokes[f+1][y][x]=Math.min(1, hi+(lo-hi)*kUp);
        if(smokes[f+1][y][x]>0.001) smokeActive[f+1]=true;   // 위층을 깨운다
      }else{
        smokes[f][y][x]  =Math.min(1, lo+(hi-lo)*kDn);
        if(smokes[f][y][x]>0.001) smokeActive[f]=true;       // 아래층을 깨운다
      }
    }
  }
}

/* =========================================================
   재실자 이동 · 피해 판정
========================================================= */
function stepAgents(dt){
  const mul = +rSpeed.value;
  const dirs=[[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]];

  // 공간 해시 (층 포함)
  const bucket = new Map();
  for(const o of agents){
    if(o.state==='escaped') continue;
    const k=o.f+':'+(o.x/CELL|0)+','+(o.y/CELL|0);
    if(!bucket.has(k)) bucket.set(k,[]);
    bucket.get(k).push(o);
  }

  for(const a of agents){
    if(a.state!=='run') continue;
    const gx=Math.floor(a.x/CELL), gy=Math.floor(a.y/CELL);
    const g=grids[a.f], fr=fires[a.f], sm=smokes[a.f];

    // 계단 이동 중: 제자리에서 대기 (피해는 계속)
    if(a.stairT>0){
      a.stairT-=dt;
      const P=stairPos(a);                 // 디딤판 위 실제 위치
      if(P){
        a.dir = P.x>a.x ? 1 : (P.x<a.x ? -1 : a.dir);
        a.x=P.x; a.y=P.y;
      }
      a.phase += dt*11;                    // 계단은 보폭이 짧다
      if(fr[gy]?.[gx]) a.hp -= dt*160;              // 화염 직접 접촉
      a.fed = (a.fed||0) + fedRate(sm[gy]?.[gx]||0)*dt;
      if(a.hp<=0 || a.fed>=1){ a.state='dead'; continue; }
      if(a.stairT<=0){ a.f=a.stairTo; a.stairT=0; }
      continue;
    }

    // 탈출 판정
    if(g[gy]?.[gx]===EXIT){ a.state='escaped'; a.tEscape=simT; continue; }

    // 피해
    if(fr[gy]?.[gx]) a.hp -= dt*160;                // 화염 직접 접촉
    a.fed = (a.fed||0) + fedRate(sm[gy]?.[gx]||0)*dt;   // 연기 누적 노출
    if(a.hp<=0 || a.fed>=1){ a.state='dead'; continue; }

    // 목표: 같은 층 8방향 + 계단 수직 이동 중 최소 비용
    let bd = dAt(a.f,gy,gx), bx=gx, by=gy, stairTo=-1;
    for(const [dx,dy] of dirs){
      const nx=gx+dx, ny=gy+dy;
      if(nx<0||ny<0||nx>=GW||ny>=GH) continue;
      if(g[ny][nx]===WALL || fr[ny][nx]) continue;
      if(dx&&dy && (g[gy][nx]===WALL||g[ny][gx]===WALL)) continue;
      const d=dAt(a.f,ny,nx);
      if(d<bd){ bd=d; bx=nx; by=ny; stairTo=-1; }
    }
    if(g[gy]?.[gx]===STAIR){
      for(const df of [-1,1]){
        const nf=a.f+df;
        if(nf<0||nf>=FLOORS) continue;
        if(grids[nf][gy][gx]!==STAIR || fires[nf][gy][gx]) continue;
        const d=dAt(nf,gy,gx);
        if(d<bd){ bd=d; stairTo=nf; }
      }
    }
    if(stairTo>=0){                       // 계단으로 층 이동 시작
      a.stairTo=stairTo; a.stairFrom=a.f; a.stairT=STAIR_DUR;
      // 실제 계단 런(디딤판이 놓인 구간)을 찾아 그 위를 걸어 내려가게 한다
      const lo=Math.min(a.f,stairTo);
      a.stairRun=(stairRuns()[lo]||[]).find(r=>gx>=r.x0&&gx<=r.x1&&gy>=r.y0&&gy<=r.y1) || null;
      a.stairLo=lo;
      continue;
    }

    let vx=0, vy=0;
    if(bd===Infinity){
      // 고립: 화염 반대 방향으로 회피
      let fx=0, fy=0;
      for(let yy=Math.max(1,gy-3); yy<=Math.min(GH-2,gy+3); yy++)
        for(let xx=Math.max(1,gx-3); xx<=Math.min(GW-2,gx+3); xx++)
          if(fr[yy][xx]){ fx+=gx-xx; fy+=gy-yy; }
      const L=Math.hypot(fx,fy)||1; vx=fx/L; vy=fy/L;
    }else{
      const tx=(bx+.5)*CELL, ty=(by+.5)*CELL;
      const L=Math.hypot(tx-a.x,ty-a.y)||1;
      vx=(tx-a.x)/L; vy=(ty-a.y)/L;
    }

    // 화염 근접 회피력
    for(let yy=Math.max(1,gy-2); yy<=Math.min(GH-2,gy+2); yy++)
      for(let xx=Math.max(1,gx-2); xx<=Math.min(GW-2,gx+2); xx++)
        if(fr[yy][xx]){
          const dx=gx-xx, dy=gy-yy, d=Math.hypot(dx,dy)||1;
          vx += dx/d * (1.4/d); vy += dy/d * (1.4/d);
        }

    // 군중 분리력 (같은 층, 인접 9셀)
    for(let by2=gy-1; by2<=gy+1; by2++) for(let bx2=gx-1; bx2<=gx+1; bx2++){
      const cellAgents = bucket.get(a.f+':'+bx2+','+by2);
      if(!cellAgents) continue;
      for(const o of cellAgents){
        if(o===a) continue;
        const dx=a.x-o.x, dy=a.y-o.y, d2=dx*dx+dy*dy;
        if(d2>0 && d2<100){ const d=Math.sqrt(d2); vx+=dx/d*.5; vy+=dy/d*.5; }
      }
    }
    const L=Math.hypot(vx,vy)||1;
    const smokeSlow = speedFactor(sm[gy]?.[gx]||0);   // 가시거리 기반 감속
    const sp = a.speed*mul*smokeSlow;
    let nx = a.x + vx/L*sp*dt;
    let ny = a.y + vy/L*sp*dt;

    // 벽·화염·도면 밖 통과 금지 (이미 불 속이면 탈출 허용)
    const inFire = !!fr[gy]?.[gx];
    const blocked = (cy,cx)=>{
      const t = g[cy]?.[cx];
      return t===undefined || t===WALL || (!inFire && fr[cy]?.[cx]);
    };
    if(blocked(Math.floor(a.y/CELL), Math.floor(nx/CELL))) nx=a.x;
    if(blocked(Math.floor(ny/CELL), Math.floor(a.x/CELL))) ny=a.y;
    if(nx!==a.x) a.dir = nx>a.x?1:-1;
    a.x=nx; a.y=ny;
    a.phase += dt*sp*0.25;
  }
}

/* =========================================================
   렌더링 (현재 층만)
========================================================= */
function drawStickman(a){
  ctx.save();
  ctx.translate(a.x,a.y);
  drawStickBody(a);
  ctx.restore();
}

/* 원점 기준 졸라맨 (2D/3D 공용) */
let _transitPass=false;

/* ------------------------------------------------------------
   재실자 — 건축 투시도의 스케일 피규어처럼 채워진 실루엣.
   선으로 그린 졸라맨보다 밀집 상황에서 훨씬 잘 읽힌다.
   원점 = 발 밑(접지점). 키 약 32px ≈ 0.67 m 축약 표기.
   ------------------------------------------------------------ */
/* 사람 치수는 미터 기준으로 잡고 축척(PX_PER_M)으로 환산한다.
   상수로 박아두면 셀 크기를 바꿀 때마다 사람만 크기가 어깰난다. */
const FIG_M = { h:1.70, head:0.11, shoulder:0.46, hip:0.36, leg:0.16, arm:0.13 };
const FIG = {
  h:        FIG_M.h        * PX_PER_M,
  head:     FIG_M.head     * PX_PER_M,
  shoulder: FIG_M.shoulder * PX_PER_M,
  hip:      FIG_M.hip      * PX_PER_M,
  leg:      FIG_M.leg      * PX_PER_M,
  arm:      FIG_M.arm      * PX_PER_M,
};

function figurePath(a){
  const F=FIG, sw = a.state==='run' ? Math.sin(a.phase) : 0;
  const legSpread = 0.24*PX_PER_M*sw, armSwing = 0.17*PX_PER_M*sw;
  const hipY=-F.h*0.42, shY=-F.h*0.66, headY=-F.h*0.84;

  // 다리 (걸을 때 앞뒤로 벌어짐)
  ctx.beginPath();
  ctx.moveTo(-F.leg*0.5, hipY); ctx.lineTo(F.leg*0.5, hipY);
  ctx.lineTo(legSpread+F.leg*0.42, 0); ctx.lineTo(legSpread-F.leg*0.42, 0);
  ctx.closePath();
  ctx.moveTo(-F.leg*0.5, hipY); ctx.lineTo(F.leg*0.5, hipY);
  ctx.lineTo(-legSpread+F.leg*0.42, 0); ctx.lineTo(-legSpread-F.leg*0.42, 0);
  ctx.closePath();
  ctx.fill();

  // 팔
  ctx.beginPath();
  ctx.moveTo(-F.shoulder*0.42, shY+1);
  ctx.lineTo(-F.shoulder*0.42-F.arm, shY+1.6);
  ctx.lineTo(-F.shoulder*0.30-F.arm+armSwing*0.5, hipY+1.5);
  ctx.lineTo(-F.shoulder*0.30+armSwing*0.5, hipY+0.6);
  ctx.closePath();
  ctx.moveTo(F.shoulder*0.42, shY+1);
  ctx.lineTo(F.shoulder*0.42+F.arm, shY+1.6);
  ctx.lineTo(F.shoulder*0.30+F.arm-armSwing*0.5, hipY+1.5);
  ctx.lineTo(F.shoulder*0.30-armSwing*0.5, hipY+0.6);
  ctx.closePath();
  ctx.fill();

  // 몸통 — 어깨에서 허리로 좁아지는 사다리꼴
  ctx.beginPath();
  ctx.moveTo(-F.shoulder*0.5, shY+1.4);
  ctx.quadraticCurveTo(-F.shoulder*0.54, shY-1.2, -F.shoulder*0.30, shY-2.2);
  ctx.lineTo(F.shoulder*0.30, shY-2.2);
  ctx.quadraticCurveTo(F.shoulder*0.54, shY-1.2, F.shoulder*0.5, shY+1.4);
  ctx.lineTo(F.hip*0.5, hipY+1.2);
  ctx.lineTo(-F.hip*0.5, hipY+1.2);
  ctx.closePath();
  ctx.fill();

  // 머리
  ctx.beginPath();
  ctx.arc(0, headY, F.head, 0, Math.PI*2);
  ctx.fill();
}

/* 원점 기준 재실자 (2D/3D 공용) */
function drawStickBody(a){
  ctx.save();
  if(a.stairT>0 && !_transitPass) ctx.globalAlpha*=.5;

  // 접지 그림자 — 바닥에 붙어 있다는 느낌을 준다
  if(a.state!=='dead'){
    ctx.save();
    ctx.scale(1,0.34);
    ctx.beginPath(); ctx.arc(0,0,0.37*PX_PER_M,0,Math.PI*2);
    ctx.fillStyle='rgba(40,36,30,0.20)'; ctx.fill();
    ctx.restore();
  }

  const col=agentCol(a);
  if(a.state==='dead'){ ctx.rotate(Math.PI/2); ctx.translate(0,-FIG.h*0.30); }
  ctx.fillStyle=col;
  if(a.scale && a.scale!==1) ctx.scale(a.scale, a.scale);   // 유아·어린이는 작게
  figurePath(a);
  ctx.restore();
}

/* 평면도에서는 입면 실루엣 대신 재실자 평면 기호(원 + 진행방향) */
function drawStickman(a){
  ctx.save(); ctx.translate(a.x,a.y);
  if(a.stairT>0) ctx.globalAlpha*=.55;
  const col=agentCol(a);
  ctx.fillStyle=col;
  const R = 0.23*PX_PER_M*(a.scale||1);       // 어깨폭 ≈ 0.46 m (유형별 축소)
  ctx.beginPath(); ctx.arc(0,0,R,0,Math.PI*2); ctx.fill();
  if(a.state==='run'){                        // 진행 방향 표시
    ctx.strokeStyle=col; ctx.lineWidth=Math.max(1,R*0.35); ctx.lineCap='round';
    ctx.beginPath(); ctx.moveTo(0,0); ctx.lineTo(a.dir*R*1.6,0); ctx.stroke();
  }
  if(a.state==='dead'){                        // 사망 표시
    ctx.strokeStyle='#E03A12'; ctx.lineWidth=Math.max(1,R*0.35);
    const d=R*1.3;
    ctx.beginPath(); ctx.moveTo(-d,-d); ctx.lineTo(d,d); ctx.moveTo(d,-d); ctx.lineTo(-d,d); ctx.stroke();
  }
  ctx.restore();
}

function drawStairCell(px,py){
  ctx.fillStyle='#14355D'; ctx.fillRect(px,py,CELL,CELL);
  ctx.strokeStyle='#63D2FF'; ctx.lineWidth=1;
  ctx.strokeRect(px+1.5,py+1.5,CELL-3,CELL-3);
  ctx.beginPath();                              // 계단 단면
  for(let i=1;i<=3;i++){
    ctx.moveTo(px+4,       py+CELL-5-i*4);
    ctx.lineTo(px+4+i*5,   py+CELL-5-i*4);
    ctx.lineTo(px+4+i*5,   py+CELL-1-i*4);
  }
  ctx.stroke();
}

/* 평면도 계단 기호 — 설정한 형식·방향이 그대로 보이게 실제 형상으로 그린다.
   디딤판선 + 중간참 + 올라가는 방향 화살표(UP/DN). */
let hlStair=null;                 // 설정 카드에 올린 계단 (강조 표시)
function drawPlanStairs(){
  const runs=stairRuns()[viewF]||[];
  runs.forEach((r,idx)=>{
    // 형상은 "위층으로 올라가는" 단이 놓인 층에 정의된다.
    const hasUp = viewF<FLOORS-1 && grids[viewF+1]?.[r.y0]?.[r.x0]===STAIR;
    let G=null, up=true;
    if(hasUp){ G=stairGeom(r); up=true; }
    else if(viewF>0 && grids[viewF-1]?.[r.y0]?.[r.x0]===STAIR){
      const below=(stairRuns()[viewF-1]||[]).find(q=>q.x0===r.x0&&q.y0===r.y0);
      if(below){ G=stairGeom(below); up=false; }
    }
    const isHL = hlStair===cfgKey(r);
    if(isHL){                                  // 카드에서 가리키는 계단
      ctx.fillStyle='rgba(95,201,248,.22)';
      ctx.fillRect(r.x0*CELL,r.y0*CELL,(r.x1-r.x0+1)*CELL,(r.y1-r.y0+1)*CELL);
      ctx.strokeStyle='#5FC9F8'; ctx.lineWidth=2.5;
      ctx.strokeRect(r.x0*CELL+1,r.y0*CELL+1,(r.x1-r.x0+1)*CELL-2,(r.y1-r.y0+1)*CELL-2);
    }
    if(!G){ drawStairBadge(r,idx,isHL); return; }
    const XY=G.XY;

    // 디딤판선
    ctx.strokeStyle='rgba(48,64,80,.9)'; ctx.lineWidth=1.1;
    ctx.beginPath();
    for(const t of G.treads){
      const p1=XY(t.a1,t.b0), p2=XY(t.a1,t.b1);
      ctx.moveTo(p1.x,p1.y); ctx.lineTo(p2.x,p2.y);
    }
    ctx.stroke();

    // 중간참
    if(G.landing){
      const L=G.landing, c1=XY(L.a0,L.b0), c3=XY(L.a1,L.b1);
      ctx.strokeStyle='rgba(48,64,80,.55)'; ctx.lineWidth=1;
      ctx.strokeRect(Math.min(c1.x,c3.x)+1, Math.min(c1.y,c3.y)+1,
                     Math.abs(c3.x-c1.x)-2, Math.abs(c3.y-c1.y)-2);
    }

    // 올라가는(내려가는) 방향 화살표
    const pts=[]; for(let i=0;i<=24;i++) pts.push(stairAt(G,0.06+ (i/24)*0.88));
    if(!up) pts.reverse();
    ctx.strokeStyle='#1F6FA8'; ctx.lineWidth=1.8; ctx.lineJoin='round';
    ctx.beginPath();
    pts.forEach((p,i)=> i?ctx.lineTo(p.x,p.y):ctx.moveTo(p.x,p.y));
    ctx.stroke();
    // 화살촉
    const a=pts[pts.length-2], b=pts[pts.length-1];
    const ang=Math.atan2(b.y-a.y, b.x-a.x);
    ctx.fillStyle='#1F6FA8';
    ctx.beginPath();
    ctx.moveTo(b.x,b.y);
    ctx.lineTo(b.x-9*Math.cos(ang-0.42), b.y-9*Math.sin(ang-0.42));
    ctx.lineTo(b.x-9*Math.cos(ang+0.42), b.y-9*Math.sin(ang+0.42));
    ctx.closePath(); ctx.fill();
    // 시작점 라벨
    const s=pts[0];
    ctx.fillStyle='#1F6FA8'; ctx.font='600 10px "IBM Plex Mono",monospace';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(up?'UP':'DN', s.x, s.y);
    ctx.textBaseline='alphabetic';

    drawStairBadge(r,idx,isHL);
  });
}

/* 계단 번호 — 설정 카드의 "n번 계단실"과 같은 번호 */
function drawStairBadge(r,idx,hl){
  const cx=(r.x0+r.x1+1)/2*CELL, cy=(r.y0+r.y1+1)/2*CELL;
  ctx.beginPath(); ctx.arc(cx,cy,11,0,Math.PI*2);
  ctx.fillStyle = hl ? '#5FC9F8' : 'rgba(20,40,58,.92)'; ctx.fill();
  ctx.strokeStyle = hl ? '#0E1013' : '#5FC9F8'; ctx.lineWidth=1.6; ctx.stroke();
  ctx.fillStyle = hl ? '#0E1013' : '#9FDCFB';
  ctx.font='700 12px "Saira Condensed",sans-serif';
  ctx.textAlign='center'; ctx.textBaseline='middle';
  ctx.fillText(String(idx+1), cx, cy+.5);
  ctx.textBaseline='alphabetic';
}

function renderPlan(){
  /* 눈금자를 도면 바깥에 두기 위한 여백. 편집 중에만 확보한다. */
  /* 여백 = 눈금 띠(RULER_W) + 띠와 도면 사이 간격(RULER_GAP)
     CSS transform 으로 확대하므로 이 비율은 확대해도 그대로 유지된다. */
  PLAN_PAD = editMode ? (RULER_W + RULER_GAP) : 0;
  cv.width=GW*CELL + PLAN_PAD; cv.height=GH*CELL + PLAN_PAD;
  const g=grids[viewF], fr=fires[viewF], sm=smokes[viewF];

  ctx.setTransform(1,0,0,1,0,0);
  ctx.clearRect(0,0,cv.width,cv.height);
  ctx.translate(PLAN_PAD, PLAN_PAD);      // 이후 좌표는 모두 도면 기준

  // 도면 바탕 (여백을 제외한 도면 영역만)
  ctx.fillStyle='#DDD8CC'; ctx.fillRect(0,0,GW*CELL,GH*CELL);

  // 밑그림 사진 (편집 중에만, 격자 아래에 깔린다)
  if(TRACE.img && editMode){
    ctx.save();
    ctx.globalAlpha = TRACE.opacity;
    /* 사진 중심을 기준으로 회전한다 */
    const w=TRACE.img.width*TRACE.scale, h=TRACE.img.height*TRACE.scale;
    ctx.translate(TRACE.x + w/2, TRACE.y + h/2);
    ctx.rotate(TRACE.rot * Math.PI/180);
    ctx.drawImage(TRACE.img, -w/2, -h/2, w, h);
    ctx.restore();
  }

  // 축척 격자 — 1 m 잔선
  ctx.strokeStyle='rgba(120,112,98,.16)'; ctx.lineWidth=1;
  ctx.beginPath();
  for(let x=0;x<=GW;x++){ ctx.moveTo(x*CELL,0); ctx.lineTo(x*CELL,GH*CELL); }
  for(let y=0;y<=GH;y++){ ctx.moveTo(0,y*CELL); ctx.lineTo(GW*CELL,y*CELL); }
  ctx.stroke();

  /* 5 m 굵은 선 — 거리를 눈으로 세기 쉽게 */
  const MAJOR = 5;
  ctx.strokeStyle='rgba(120,112,98,.42)'; ctx.lineWidth=1;
  ctx.beginPath();
  for(let x=0;x<=GW;x+=MAJOR){ ctx.moveTo(x*CELL,0); ctx.lineTo(x*CELL,GH*CELL); }
  for(let y=0;y<=GH;y+=MAJOR){ ctx.moveTo(0,y*CELL); ctx.lineTo(GW*CELL,y*CELL); }
  ctx.stroke();

  // 비상구 · 계단
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    const px=x*CELL, py=y*CELL, t=g[y][x];
    if(t===EXIT){
      ctx.fillStyle='rgba(0,182,122,.28)'; ctx.fillRect(px,py,CELL,CELL);
      ctx.strokeStyle='#00B67A'; ctx.lineWidth=1.5;
      ctx.strokeRect(px+.75,py+.75,CELL-1.5,CELL-1.5);
    }else if(t===STAIR){
      ctx.fillStyle='rgba(95,201,248,.14)'; ctx.fillRect(px,py,CELL,CELL);
    }else if(t===HYDRANT){
      ctx.fillStyle='#C62A12'; ctx.fillRect(px+2,py+2,CELL-4,CELL-4);
      ctx.strokeStyle='#7E1608'; ctx.lineWidth=1; ctx.strokeRect(px+2.5,py+2.5,CELL-5,CELL-5);
      ctx.fillStyle='#fff'; ctx.font='700 11px "IBM Plex Mono",monospace';
      ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillText('H', px+CELL/2, py+CELL/2+.5);
      ctx.textBaseline='alphabetic';
    }else if(t===DOOR){
      /* 문 — 개구부 바닥 + 열림 방향 호(건축 도면 관례) */
      ctx.fillStyle='rgba(214,158,46,.20)'; ctx.fillRect(px,py,CELL,CELL);
      ctx.strokeStyle='#D69E2E'; ctx.lineWidth=1.4;
      ctx.beginPath();
      ctx.arc(px+1.5, py+CELL-1.5, CELL-3, -Math.PI/2, 0);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(px+1.5, py+CELL-1.5); ctx.lineTo(px+1.5, py+1.5);
      ctx.stroke();
      if(doorLock){                       /* 잠금 표시 */
        ctx.fillStyle='rgba(214,158,46,.85)';
        ctx.fillRect(px+CELL-4, py+1, 3, 3);
      }
    }
  }

  // 연기 · 화염 (벽 아래 레이어)
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    const s=sm[y][x];
    if(s>0.03 && g[y][x]!==WALL){
      ctx.fillStyle='rgba(58,54,48,'+(s*0.68).toFixed(2)+')';
      ctx.fillRect(x*CELL,y*CELL,CELL,CELL);
    }
  }
  const t=performance.now()/90;
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    if(!fr[y][x]) continue;
    const f=.80+.20*Math.sin(t+x*1.7+y*2.3);
    ctx.fillStyle='rgba(255,75,31,'+f.toFixed(2)+')';
    ctx.fillRect(x*CELL,y*CELL,CELL,CELL);
  }

  // 벽 — 건축 도면 관행대로 잘린 벽은 포셰(solid)
  ctx.fillStyle='#26231F';
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
    if(g[y][x]===WALL) ctx.fillRect(x*CELL,y*CELL,CELL,CELL);

  drawPlanStairs();

  // 비상구 라벨
  ctx.fillStyle='#00875C';
  ctx.font='600 9px "IBM Plex Mono",monospace'; ctx.textAlign='center';
  for(const [lx,ly] of (exitLabels[viewF]||[])) ctx.fillText('EXIT', lx, ly+3);

  // 편집 중: 탈출 경로가 없는 칸을 붉게 표시 (즉시 확인 가능한 피난 진단)
  if(editMode && dist){
    ctx.fillStyle='rgba(255,75,31,.30)';
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      const t=g[y][x];
      if(t!==FLOOR && t!==STAIR && t!==DOOR) continue;
      if(dAt(viewF,y,x)===Infinity) ctx.fillRect(x*CELL,y*CELL,CELL,CELL);
    }
  }

  // 편집 중 미리보기 — 적용될 칸을 그대로 보여준다
  if(editMode){
    const sh=activeShape();
    let cells=null;
    if(dragA&&dragB){
      if(editTool==='room'||editTool==='stair'||sh==='rect')
        cells=rectCells(dragA[0],dragA[1],dragB[0],dragB[1]);
      else if(sh==='line') cells=lineCells(dragA[0],dragA[1],dragB[0],dragB[1]);
    }else if(hoverCell){
      if(sh==='free') cells=brushCells(hoverCell[0],hoverCell[1]);
      else if(sh==='fill') cells=fillCells(hoverCell[0],hoverCell[1]);
      else cells=[hoverCell];
    }
    if(cells && cells.length){
      const tint = editTool==='erase'? 'rgba(255,75,31,.22)'
                 : editTool==='wall' ? 'rgba(38,35,31,.45)'
                 : editTool==='exit' ? 'rgba(0,182,122,.42)'
                 : editTool==='stair'? 'rgba(95,201,248,.38)'
                 : editTool==='door' ? 'rgba(214,158,46,.40)'
                 : 'rgba(95,201,248,.26)';
      ctx.fillStyle=tint;
      for(const [x,y] of cells) ctx.fillRect(x*CELL,y*CELL,CELL,CELL);
      // 영역 테두리와 치수
      if(dragA&&dragB&&(sh==='rect'||editTool==='room'||editTool==='stair')){
        const x0=Math.min(dragA[0],dragB[0]), x1=Math.max(dragA[0],dragB[0]);
        const y0=Math.min(dragA[1],dragB[1]), y1=Math.max(dragA[1],dragB[1]);
        const ers = (editTool==='erase');
        const hue = ers ? '#FF4B1F' : '#5FC9F8';       /* 지우기는 붉게 */
        if(ers){                                        /* 지워질 영역을 덮어 표시 */
          ctx.fillStyle='rgba(255,75,31,.16)';
          ctx.fillRect(x0*CELL,y0*CELL,(x1-x0+1)*CELL,(y1-y0+1)*CELL);
        }
        ctx.strokeStyle=hue; ctx.lineWidth=2; ctx.setLineDash([6,4]);
        ctx.strokeRect(x0*CELL+1,y0*CELL+1,(x1-x0+1)*CELL-2,(y1-y0+1)*CELL-2);
        ctx.setLineDash([]);
        ctx.fillStyle=hue; ctx.font='600 12px "IBM Plex Mono",monospace'; ctx.textAlign='left';
        ctx.fillText(((x1-x0+1)*M_PER_CELL).toFixed(1)+' × '+((y1-y0+1)*M_PER_CELL).toFixed(1)+' m',
                     x0*CELL+3, Math.max(12, y0*CELL-6));
      }
      if(sh==='fill' && !dragA){
        ctx.fillStyle='#5FC9F8'; ctx.font='600 12px "IBM Plex Mono",monospace'; ctx.textAlign='left';
        ctx.fillText(cells.length+'칸 ('+(cells.length*M2_PER_CELL).toFixed(1)+'㎡)',
                     hoverCell[0]*CELL+8, Math.max(12,hoverCell[1]*CELL-6));
      }
    }
    if(hoverCell && typeof editCoord!=='undefined' && editCoord)
      editCoord.textContent=hoverCell[0]+', '+hoverCell[1];
  }

  // 발화 예정 지점
  if(ignition && !running && !ended && ignition[0]===viewF){
    ctx.strokeStyle='#FF4B1F'; ctx.lineWidth=2;
    ctx.strokeRect(ignition[1]*CELL+2, ignition[2]*CELL+2, CELL-4, CELL-4);
  }

  /* 눈금자 — 도면 바깥에 띄워서 그린다.
     마우스가 있는 줄은 눈금 위에 표시되어 위치를 짚어준다. */
  if(editMode && PLAN_PAD){
    const step  = GW>120 ? 20 : (GW>60 ? 10 : 5);
    const stepY = GH>120 ? 20 : (GH>60 ? 10 : 5);
    const hx = hoverCell ? hoverCell[0] : null;
    const hy = hoverCell ? hoverCell[1] : null;
    const inX = hx!=null && hx>=0 && hx<GW;
    const inY = hy!=null && hy>=0 && hy<GH;

    ctx.save();
    ctx.translate(-PLAN_PAD, -PLAN_PAD);       // 캔버스 원점 기준으로

    /* 눈금 띠 — 도면에서 RULER_GAP 만큼 떨어져 있다 */
    ctx.fillStyle='rgba(243,240,233,.97)';
    ctx.fillRect(0,0,cv.width,RULER_W);
    ctx.fillRect(0,0,RULER_W,cv.height);
    ctx.strokeStyle='rgba(90,84,74,.28)'; ctx.lineWidth=1;
    ctx.beginPath();
    ctx.moveTo(0,RULER_W-.5); ctx.lineTo(cv.width,RULER_W-.5);
    ctx.moveTo(RULER_W-.5,0); ctx.lineTo(RULER_W-.5,cv.height);
    ctx.stroke();

    /* 마우스 위치 — 띠 안에 파란 표식, 도면까지 얇은 안내선 */
    if(inX){
      const px = PLAN_PAD + hx*CELL;
      ctx.fillStyle='rgba(20,120,180,.90)';
      ctx.fillRect(px, RULER_W-4, CELL, 4);
      ctx.strokeStyle='rgba(20,120,180,.30)';
      ctx.beginPath(); ctx.moveTo(px+CELL/2, RULER_W); ctx.lineTo(px+CELL/2, PLAN_PAD); ctx.stroke();
    }
    if(inY){
      const py = PLAN_PAD + hy*CELL;
      ctx.fillStyle='rgba(20,120,180,.90)';
      ctx.fillRect(RULER_W-4, py, 4, CELL);
      ctx.strokeStyle='rgba(20,120,180,.30)';
      ctx.beginPath(); ctx.moveTo(RULER_W, py+CELL/2); ctx.lineTo(PLAN_PAD, py+CELL/2); ctx.stroke();
    }

    /* 눈금 숫자 */
    ctx.fillStyle='#4A443B';
    ctx.font='700 10px "IBM Plex Mono",monospace';
    ctx.textBaseline='middle';
    ctx.strokeStyle='rgba(90,84,74,.45)';

    ctx.textAlign='center';
    for(let x=step;x<=GW;x+=step){
      const px=PLAN_PAD+x*CELL;
      ctx.fillText(String(Math.round(x*M_PER_CELL)), px, RULER_W/2-1);
      ctx.beginPath(); ctx.moveTo(px,RULER_W-8); ctx.lineTo(px,RULER_W-5); ctx.stroke();
    }
    for(let y=stepY;y<=GH;y+=stepY){
      const py=PLAN_PAD+y*CELL;
      ctx.fillText(String(Math.round(y*M_PER_CELL)), RULER_W/2-1, py);
      ctx.beginPath(); ctx.moveTo(RULER_W-8,py); ctx.lineTo(RULER_W-5,py); ctx.stroke();
    }

    /* 모서리 — 현재 좌표(m) */
    ctx.fillStyle = (inX&&inY) ? '#1478B4' : '#8A8175';
    ctx.font='700 9px "IBM Plex Mono",monospace';
    ctx.textAlign='center';
    ctx.fillText((inX&&inY) ? (Math.round(hx*M_PER_CELL)+','+Math.round(hy*M_PER_CELL)) : 'm',
                 RULER_W/2, RULER_W/2);
    ctx.restore();
  }

  drawPolyPreview();

  // 재실자
  for(const a of agents) if(a.f===viewF && a.state==='dead') drawStickman(a);
  for(const a of agents)
    if(a.f===viewF && (a.state==='idle'||a.state==='run'||a.state==='isolated')) drawStickman(a);

  // 축척 맞추기 중 표시
  if(TRACE.mode==='cal'){
    const p0=TRACE.calA, p1=TRACE.calB || TRACE.calHover;
    ctx.save();
    ctx.strokeStyle='#FF4B1F'; ctx.lineWidth=2; ctx.setLineDash([5,4]);
    if(p0 && p1){
      ctx.beginPath(); ctx.moveTo(p0[0],p0[1]); ctx.lineTo(p1[0],p1[1]); ctx.stroke();
    }
    ctx.setLineDash([]);
    for(const pt of [p0, TRACE.calB]){
      if(!pt) continue;
      ctx.beginPath(); ctx.arc(pt[0],pt[1],5,0,Math.PI*2);
      ctx.fillStyle='#FF4B1F'; ctx.fill();
    }
    ctx.restore();
  }
}

/* =========================================================
   3D 축측투영 (exploded axonometric)
   - 층을 띄워 쌓아 각 층 내부가 보이게 함
   - 정적 지오메트리(슬래브·벽·계단)는 층별 오프스크린 캔버스에 캐시.
     매 프레임 다시 그리는 것은 연기/화염/재실자뿐 → 프레임당 비용 최소화
========================================================= */
const WALL_H  = 2.6*CELL;     // 벽 높이 (모형처럼 허리 높이에서 잘라 내부가 보이게)
const SLAB_T  = 5;            // 바닥 슬래브 두께
const MAX_FLOORS = 8;
let BASEMENTS = 0;                     // 지하층 수. 층 배열의 앞쪽이 지하다.
const groundF = () => BASEMENTS;       // 지상 1층의 배열 인덱스 (피난층)
/* 배열 인덱스 → 표기.  f=0..B-1 은 지하, 그 위는 지상 */
function floorLabel(f){
  return f < BASEMENTS ? '지하 '+(BASEMENTS-f)+'층' : '지상 '+(f-BASEMENTS+1)+'층';
}
function floorShort(f){
  return f < BASEMENTS ? 'B'+(BASEMENTS-f) : (f-BASEMENTS+1)+'F';
}

/* 층 간격: 층이 많아질수록 좁혀서 전체가 한 화면에 들어오게 한다.
   (벽 높이 + 슬래브 두께보다는 항상 커야 층끼리 겹치지 않는다) */
let FLOOR_H = 5.4*CELL;
function updateFloorH(){
  FLOOR_H = Math.max(2.95*CELL, 5.4*CELL*Math.sqrt(6/Math.max(6,FLOORS)));
  /* 분할 보기에서는 층 사이를 벌려 각 층 내부가 보이게 한다.
     ISO 가 아직 선언되기 전(초기 buildMap)에 불릴 수 있어 방어한다. */
  try{
    if(typeof ISO!=='undefined' && ISO && ISO.floorView==='split')
      FLOOR_H *= (ISO.spread||2.2);
  }catch(e){}
}

/* floorView: 'one'   현재 층만 (가장 빠름)
              'split' 층을 위아래로 벌려서 전부 (분할 보기)
              'all'   실제 층고대로 쌓아서 전부 */
const ISO = { yaw:0.62, pitch:0.52, zoom:1, all:false, floorView:'one', spread:2.2 };
const SPLIT_MAX = 8;          // 분할 보기로 한 번에 그릴 최대 층수
const PITCH_MIN=-1.28, PITCH_MAX=1.52;                    // 아래에서 올려다보기 ~ 위에서 내려다보기
const STAIR_DUR=2.2;                                      // 계단 통과 시간(초)

let isoCache = { yaw:null, gw:0, gh:0, order:null };
let isoView  = { Z:1, minX:0, minY:0, minVY:0, maxVY:0, pad:40 };
let gridVer  = 0;             // 도면이 바뀔 때마다 증가 → 캐시 무효화

/* --- 그리기 대상 컨텍스트 (메인 or 캐시 레이어) --- */
let X = null;

function isoRX(wx,wy){ return wx*Math.cos(ISO.yaw) - wy*Math.sin(ISO.yaw); }
function isoRY(wx,wy){ return wx*Math.sin(ISO.yaw) + wy*Math.cos(ISO.yaw); }
function isoPY(wx,wy,wz){ return isoRY(wx,wy)*Math.sin(ISO.pitch) - wz*Math.cos(ISO.pitch); }

/* 격자 꼭짓점 투영 캐시.
   py(x,y,z) = ry(x,y)*0.5 - z 이므로 높이는 단순 뺄셈 → 삼각함수/할당 0회 */
let vCache={yaw:null,pitch:null,gw:0,gh:0,VX:null,VY:null,minVY:0,maxVY:0,minVX:0,maxVX:0};
function vertCache(){
  if(vCache.yaw===ISO.yaw && vCache.pitch===ISO.pitch && vCache.gw===GW && vCache.gh===GH) return vCache;
  const n=(GW+1)*(GH+1);
  const VX=new Float64Array(n), VY=new Float64Array(n);
  const c=Math.cos(ISO.yaw), s=Math.sin(ISO.yaw), se=Math.sin(ISO.pitch);
  let mnY=1e18,mxY=-1e18,mnX=1e18,mxX=-1e18;
  for(let y=0;y<=GH;y++) for(let x=0;x<=GW;x++){
    const wx=x*CELL, wy=y*CELL, i=y*(GW+1)+x;
    const px=wx*c-wy*s, py=(wx*s+wy*c)*se;   // 높이(z)는 그릴 때 cos(pitch)만큼 빼줌
    VX[i]=px; VY[i]=py;
    if(py<mnY)mnY=py; if(py>mxY)mxY=py;
    if(px<mnX)mnX=px; if(px>mxX)mxX=px;
  }
  vCache={yaw:ISO.yaw,pitch:ISO.pitch,gw:GW,gh:GH,VX,VY,minVY:mnY,maxVY:mxY,minVX:mnX,maxVX:mxX};
  return vCache;
}
let _VX=null,_VY=null,_W1=0,_c=1,_s=0,_se=0.5,_ce=0.87;
const VI=(gx,gy)=>gy*_W1+gx;
const pxOf=(wx,wy)=>wx*_c-wy*_s;
const pyOf=(wx,wy,wz)=>(wx*_s+wy*_c)*_se-wz*_ce;

/* 격자 꼭짓점 4개로 사각형 (할당 없음) */
function qg(i1,z1,i2,z2,i3,z3,i4,z4){
  X.beginPath();
  X.moveTo(_VX[i1],_VY[i1]-z1*_ce);
  X.lineTo(_VX[i2],_VY[i2]-z2*_ce);
  X.lineTo(_VX[i3],_VY[i3]-z3*_ce);
  X.lineTo(_VX[i4],_VY[i4]-z4*_ce);
  X.closePath();
}
/* 임의 월드 좌표 사각형 */
function qw(a){
  X.beginPath();
  X.moveTo(pxOf(a[0],a[1]), pyOf(a[0],a[1],a[2]));
  for(let i=3;i<a.length;i+=3) X.lineTo(pxOf(a[i],a[i+1]), pyOf(a[i],a[i+1],a[i+2]));
  X.closePath();
}

/* 캔버스 크기·스케일 산출 */
function isoLayout(){
  const vc=vertCache();
  const labelPad = 46;                       // 층 라벨(1F...) 자리
  const ce=Math.cos(ISO.pitch);
  const topZ=((FLOORS-1)*FLOOR_H + WALL_H)*ce;
  const minX=vc.minVX-labelPad, maxX=vc.maxVX;
  const minY=vc.minVY-topZ,     maxY=vc.maxVY+SLAB_T*ce;
  const pad=34;
  // 무대 크기에 맞춰 기본 배율을 잡고, 사용자 확대는 그 위에 곱한다
  const st=document.getElementById('stage');
  let boxW=(st&&st.clientWidth ? st.clientWidth : 1080)-16;
  let boxH=(st&&st.clientHeight? st.clientHeight:  720)-16;
  // 모바일에서 무대 높이가 잡히지 않으면 화면 기준으로 보정
  if(!(boxW>40)) boxW=Math.max(280, (window.innerWidth||360)-16);
  if(!(boxH>40)) boxH=Math.max(260, Math.round((window.innerHeight||640)*0.58));
  // 여백(pad)은 배율과 무관한 화면 픽셀이므로 먼저 빼고 배율을 구한다
  const worldW=Math.max(1,maxX-minX), worldH=Math.max(1,maxY-minY);
  const fit=Math.max(0.05, Math.min((boxW-pad*2)/worldW, (boxH-pad*2)/worldH));
  const Z=fit*ISO.zoom;
  isoView={Z,minX,minY,minVY:vc.minVY,maxVY:vc.maxVY,pad,fit};
  cv.width  = Math.max(1, Math.round((maxX-minX)*Z + pad*2));
  cv.height = Math.max(1, Math.round((maxY-minY)*Z + pad*2));
}

/* 깊이순 셀 순서 (yaw 바뀔 때만 재계산) */
function isoOrder(){
  if(isoCache.yaw===ISO.yaw && isoCache.gw===GW && isoCache.gh===GH) return isoCache.order;
  const arr=[];
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
    arr.push([x,y,isoRY((x+.5)*CELL,(y+.5)*CELL)]);
  arr.sort((a,b)=>a[2]-b[2]);                 // 먼 것부터
  isoCache={yaw:ISO.yaw,gw:GW,gh:GH,order:arr};
  return arr;
}

/* ============================================================
   재질 · 조명
   건축 스터디 모형처럼 중성 석고색 재질에 고정 방향광을 준다.
   면의 월드 노멀에 따라 밝기가 정해지므로, 카메라를 돌려도
   광원은 고정된 채 형태만 다시 읽힌다.
   ============================================================ */
const SHADE = { top:1.00, slab:0.92, litY:0.80, litX:0.66, shX:0.46, shY:0.52, under:0.34 };
const MAT   = { wall:[240,237,229], plate:[221,215,204], soffit:[196,189,176] };

function tone(rgb, k, a){
  return 'rgba('+(rgb[0]*k|0)+','+(rgb[1]*k|0)+','+(rgb[2]*k|0)+','+a+')';
}

/* 층별 색 팔레트 (셀 루프 안에서 문자열을 만들면 매우 느림) */
function isoPalette(dim){
  const a = dim<1 ? 0.52 : 1;              // 비활성 층은 뒤로 물러나게
  const k = dim<1 ? 0.86 : 1;              // 살짝 어둡게
  // 바닥판은 항상 약간 투과시켜 아래층이 비쳐 보이게 한다 (엑스레이 모형)
  const ap = a*0.84;
  const W=MAT.wall, P=MAT.plate, S=MAT.soffit;
  const P_={
    plateTop : tone(P, SHADE.slab*k, ap),
    plateEdgeX:tone(S, SHADE.litX*k, a),
    plateEdgeY:tone(S, SHADE.litY*k, a),
    soffit   : tone(S, SHADE.under*k, ap),
    wallTop  : tone(W, SHADE.top*k,  a),
    wallLitX : tone(W, SHADE.litX*k, a),
    wallLitY : tone(W, SHADE.litY*k, a),
    wallShX  : tone(W, SHADE.shX*k,  a),
    wallShY  : tone(W, SHADE.shY*k,  a),
    wallUnder: tone(MAT.wall, SHADE.under*k, a),
    wallEdge : 'rgba(120,112,100,'+(0.45*a)+')',
    stepTread: tone(MAT.wall, SHADE.top*k*0.97, a),
    stepRiser: tone(MAT.wall, SHADE.litX*k*0.92, a),
    stepUnder: tone(MAT.wall, SHADE.under*k, a),
    stepEdge : 'rgba(110,102,92,'+(0.55*a)+')',
    rail     : 'rgba(95,201,248,'+(0.75*a)+')',
    grid     : 'rgba(130,122,108,'+(0.16*a)+')',
    exit     : 'rgba(0,182,122,'+(0.30*a)+')',
    exitEdge : 'rgba(0,182,122,'+(0.95*a)+')',
    stair    : 'rgba(95,201,248,'+(0.16*a)+')',
    hydTop   : 'rgba(226,74,42,'+a+')',
    hydSide  : 'rgba(160,44,22,'+a+')',
    hydSide2 : 'rgba(190,56,30,'+a+')',
    stairLine: 'rgba(56,74,92,'+(0.85*a)+')',
    shaftA   : 'rgba(95,201,248,'+(0.13*a)+')',
    shaftB   : 'rgba(95,201,248,'+(0.09*a)+')',
    shaftL   : 'rgba(95,201,248,'+(0.55*a)+')',
    flameA   : 'rgba(255,148,32,'+(0.42*a)+')',
    flameB   : 'rgba(255,206,90,'+(0.34*a)+')',
    SMOKE:[], FIRE:[],
  };
  // 연기는 밝은 바닥 위에서 어두운 회색으로 → 즉시 읽힘
  for(let i=0;i<=16;i++) P_.SMOKE.push('rgba(58,54,48,'+((i/16*0.72*a).toFixed(3))+')');
  for(let i=0;i<=8;i++)  P_.FIRE .push('rgba(255,75,31,'+(((0.80+0.20*i/8)*a).toFixed(2))+')');
  return P_;
}

/* ---------- 정적 레이어 1: 슬래브 + 격자 + 비상구/계단 바닥 ---------- */
/* 층별 건물 실제 범위 (벽이 아닌 칸 + 그에 접한 벽) */
const _bbCache={ver:-1,box:[]};
function buildingBox(f){
  if(_bbCache.ver!==gridVer){ _bbCache.ver=gridVer; _bbCache.box=[]; }
  if(_bbCache.box[f]) return _bbCache.box[f];
  const g=grids[f];
  let x0=GW, y0=GH, x1=-1, y1=-1;
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    if(g[y][x]===WALL) continue;
    if(x<x0)x0=x; if(x>x1)x1=x;
    if(y<y0)y0=y; if(y>y1)y1=y;
  }
  if(x1<0){ x0=0; y0=0; x1=GW-1; y1=GH-1; }      // 빈 층
  else { x0=Math.max(0,x0-1); y0=Math.max(0,y0-1);
         x1=Math.min(GW-1,x1+1); y1=Math.min(GH-1,y1+1); }
  return (_bbCache.box[f]={x0,y0,x1,y1});
}

function drawBase(f, C){
  const g=grids[f], z=f*FLOOR_H;
  /* 슬래브는 실제 건물이 차지한 범위까지만 깐다.
     격자 전체로 깔면 도면 밖 여백까지 바닥이 생겨 사각 덩어리로 보인다. */
  const bb=buildingBox(f);
  const X0=bb.x0*CELL, Y0=bb.y0*CELL, W=(bb.x1+1)*CELL, H=(bb.y1+1)*CELL;
  const showX = _s>0 ? 1 : -1, showY = _c>0 ? 1 : -1;

  const below = _se < 0;
  // 위에서 보면 밑면이 먼저(가려짐), 아래에서 보면 윗면이 먼저
  if(!below){
    qw([X0,Y0,z-SLAB_T, W,Y0,z-SLAB_T, W,H,z-SLAB_T, X0,H,z-SLAB_T]);
    X.fillStyle=C.soffit; X.fill();
  }else{
    qw([X0,Y0,z, W,Y0,z, W,H,z, X0,H,z]);
    X.fillStyle=C.plateTop; X.fill();
  }
  // 슬래브 두께 — 카메라를 향한 두 면. 광원이 고정이므로 밝기가 서로 다르다
  const ex = showX>0 ? W : X0, ey = showY>0 ? H : Y0;
  qw([ex,Y0,z, ex,H,z, ex,H,z-SLAB_T, ex,Y0,z-SLAB_T]);
  X.fillStyle = showX>0 ? C.plateEdgeX : C.plateEdgeY; X.fill();
  qw([X0,ey,z, W,ey,z, W,ey,z-SLAB_T, X0,ey,z-SLAB_T]);
  X.fillStyle = showY>0 ? C.plateEdgeY : C.plateEdgeX; X.fill();

  // 실제로 보이는 면을 마지막에
  if(!below){
    qw([X0,Y0,z, W,Y0,z, W,H,z, X0,H,z]);
    X.fillStyle=C.plateTop; X.fill();
  }else{
    qw([0,0,z-SLAB_T, W,0,z-SLAB_T, W,H,z-SLAB_T, 0,H,z-SLAB_T]);
    X.fillStyle=C.soffit; X.fill();
    return;                       // 아래에서는 바닥 위 요소가 슬래브에 가린다
  }

  // 격자 (아주 옅게 — 축척감만)
  X.strokeStyle=C.grid; X.beginPath();
  for(let x=0;x<=GW;x++){ const a=VI(x,0), b=VI(x,GH);
    X.moveTo(_VX[a],_VY[a]-z*_ce); X.lineTo(_VX[b],_VY[b]-z*_ce); }
  for(let y=0;y<=GH;y++){ const a=VI(0,y), b=VI(GW,y);
    X.moveTo(_VX[a],_VY[a]-z*_ce); X.lineTo(_VX[b],_VY[b]-z*_ce); }
  X.stroke();

  // 비상구 / 계단
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    const t=g[y][x];
    if(t!==EXIT && t!==STAIR && t!==HYDRANT && t!==DOOR) continue;
    const i00=VI(x,y), i10=VI(x+1,y), i11=VI(x+1,y+1), i01=VI(x,y+1);
    if(t===HYDRANT){
      // 소화전함 — 바닥에서 솟은 붉은 상자
      const h=1.5*CELL;
      qg(i00,z+h,i10,z+h,i11,z+h,i01,z+h);
      X.fillStyle=C.hydTop; X.fill();
      const sx2=_s>0?1:-1, sy2=_c>0?1:-1;
      const ax=sx2>0?i10:i00, bx=sx2>0?i11:i01;
      X.beginPath();
      X.moveTo(_VX[ax],_VY[ax]-(z+h)*_ce); X.lineTo(_VX[bx],_VY[bx]-(z+h)*_ce);
      X.lineTo(_VX[bx],_VY[bx]-z*_ce);     X.lineTo(_VX[ax],_VY[ax]-z*_ce); X.closePath();
      X.fillStyle=C.hydSide; X.fill();
      const ay=sy2>0?i01:i00, by=sy2>0?i11:i10;
      X.beginPath();
      X.moveTo(_VX[ay],_VY[ay]-(z+h)*_ce); X.lineTo(_VX[by],_VY[by]-(z+h)*_ce);
      X.lineTo(_VX[by],_VY[by]-z*_ce);     X.lineTo(_VX[ay],_VY[ay]-z*_ce); X.closePath();
      X.fillStyle=C.hydSide2; X.fill();
      continue;
    }
    qg(i00,z,i10,z,i11,z,i01,z);
    X.fillStyle = t===EXIT ? C.exit : (t===DOOR ? 'rgba(214,158,46,.45)' : C.stair); X.fill();
    if(t===EXIT){ X.strokeStyle=C.exitEdge; X.stroke(); }
    else {
      // 계단 디딤판 — 도면 기호 그대로
      X.strokeStyle=C.stairLine; X.beginPath();
      for(let k=1;k<=3;k++){
        const u=k/4;
        const ax=_VX[i00]+(_VX[i01]-_VX[i00])*u, ay=_VY[i00]+(_VY[i01]-_VY[i00])*u;
        const bx=_VX[i10]+(_VX[i11]-_VX[i10])*u, by=_VY[i10]+(_VY[i11]-_VY[i10])*u;
        X.moveTo(ax,ay-z*_ce); X.lineTo(bx,by-z*_ce);
      }
      X.stroke();
    }
  }
}

/* ---------- 정적 레이어 2: 벽 + 계단 + 계단실 ---------- */
const WALL_INSET = 0.26*CELL;   // 셀보다 얇게 그려 진짜 칸막이벽처럼 보이게

/* 계단 덩어리(연결된 STAIR 셀)를 찾아 캐시 */
let stairCache={ver:-1,list:null};
function stairRuns(){
  if(stairCache.ver===gridVer && stairCache.list) return stairCache.list;
  const list=[];
  for(let f=0;f<FLOORS;f++){
    const g=grids[f], seen=Array.from({length:GH},()=>new Uint8Array(GW)), runs=[];
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      if(g[y][x]!==STAIR||seen[y][x]) continue;
      let x0=x,x1=x,y0=y,y1=y; const q=[[x,y]]; seen[y][x]=1;
      while(q.length){
        const [cx,cy]=q.pop();
        x0=Math.min(x0,cx); x1=Math.max(x1,cx); y0=Math.min(y0,cy); y1=Math.max(y1,cy);
        for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){
          const nx=cx+dx, ny=cy+dy;
          if(nx<0||ny<0||nx>=GW||ny>=GH||seen[ny][nx]) continue;
          if(g[ny][nx]===STAIR){ seen[ny][nx]=1; q.push([nx,ny]); }
        }
      }
      runs.push({x0,y0,x1,y1});
    }
    list.push(runs);
  }
  stairCache={ver:gridVer,list};
  return list;
}

/* ============================================================
   계단 형상 — 꺾임계단(2주형)
   층고를 한 번에 오르는 직통계단은 실제 건물에 쓰이지 않는다.
   폭이 확보되면 중간참을 두고 두 개 단으로 접어 올린다.
   렌더러와 재실자 이동이 같은 형상을 공유한다.
   ============================================================ */
const LANDING = 2;              // 중간참 깊이(칸)

/* 계단실별 설정 — 계단 덩어리의 좌상단 칸을 열쇠로 삼는다.
   type: 'dogleg'(중간참을 두고 2개 단) | 'straight'(직통 1개 단)
   dir : '+y','-y','+x','-x'  올라가는 방향.  미지정이면 긴 쪽 +방향 자동. */
let stairCfg = {};
const cfgKey = r => r.x0+','+r.y0;
function getCfg(r){ return stairCfg[cfgKey(r)] || {}; }
function setCfg(r,patch){
  const k=cfgKey(r);
  stairCfg[k]=Object.assign({}, stairCfg[k]||{}, patch);
  gridVer++; stairCache.ver=-1; isoStatic.key=null;
  customMapText=serializeMap();
  if(mapTextEl) mapTextEl.value=customMapText;
}

function stairGeom(r){
  const cfg=getCfg(r);
  const wx=r.x1-r.x0+1, wy=r.y1-r.y0+1;
  const rise=FLOOR_H-SLAB_T;
  const alongY = cfg.dir ? (cfg.dir==='+y'||cfg.dir==='-y') : (wy>=wx);
  const rev    = cfg.dir ? (cfg.dir==='-y'||cfg.dir==='-x') : false;
  const span  = alongY ? wy : wx;      // 긴 쪽 (오르는 방향)
  const width = alongY ? wx : wy;      // 짧은 쪽 (계단 폭)
  const a0    = alongY ? r.y0 : r.x0;
  const b0    = alongY ? r.x0 : r.y0;
  const XY = (a,b)=> alongY ? {x:b*CELL, y:a*CELL} : {x:a*CELL, y:b*CELL};

  const flight = span - LANDING;
  const bHalf  = Math.floor(width/2);
  const forceStraight = cfg.type==='straight';

  // 직통으로 지정했거나, 폭·길이가 모자라 접을 수 없으면 1방향
  if(forceStraight || width<4 || flight<3){
    const bMid=b0+width/2, n=Math.max(3,span);
    const treads=[];
    for(let i=0;i<n;i++)
      treads.push({a0:a0+i, a1:a0+i+1, b0, b1:b0+width, z0:rise*i/n, z1:rise*(i+1)/n});
    return mirror({alongY, straight:true, treads, landing:null, XY, rise,
            path:[ {a:a0,      b:bMid, z:0},
                   {a:a0+span, b:bMid, z:rise} ]}, rev, a0, span);
  }

  // 첫 단을 어느 쪽에 두는가 (=중간참에서 어느 쪽으로 도는가)
  const firstLow = (cfg.hand||'L')==='L';
  const loMid = b0 + bHalf/2;
  const hiMid = b0 + bHalf + (width-bHalf)/2;
  const bA = firstLow ? loMid : hiMid;           // 올라가는 첫 단의 중심
  const bB = firstLow ? hiMid : loMid;           // 되돌아오는 둘째 단의 중심
  const aTurn = a0 + flight + LANDING/2;
  const treads=[];
  const A_b0 = firstLow ? b0        : b0+bHalf;   // 첫 단이 차지하는 폭
  const A_b1 = firstLow ? b0+bHalf   : b0+width;
  const B_b0 = firstLow ? b0+bHalf   : b0;        // 둘째 단
  const B_b1 = firstLow ? b0+width   : b0+bHalf;
  // 1단: a0 → a0+flight, 0 → rise/2
  for(let i=0;i<flight;i++)
    treads.push({a0:a0+i, a1:a0+i+1, b0:A_b0, b1:A_b1,
                 z0:rise*0.5*i/flight, z1:rise*0.5*(i+1)/flight});
  // 2단: 중간참에서 되돌아 a0+flight → a0, rise/2 → rise
  for(let i=0;i<flight;i++)
    treads.push({a0:a0+flight-i-1, a1:a0+flight-i, b0:B_b0, b1:B_b1,
                 z0:rise*(0.5+0.5*i/flight), z1:rise*(0.5+0.5*(i+1)/flight)});

  return mirror({
    alongY, straight:false, XY, rise, treads,
    landing:{a0:a0+flight, a1:a0+span, b0, b1:b0+width, z:rise*0.5},
    path:[ {a:a0,     b:bA, z:0},
           {a:aTurn,  b:bA, z:rise*0.5},
           {a:aTurn,  b:bB, z:rise*0.5},
           {a:a0,     b:bB, z:rise} ],
  }, rev, a0, span);
}

/* 올라가는 방향을 뒤집는다 — 높이는 그대로 두고 위치만 접는다 */
function mirror(G, rev, a0, span){
  if(!rev) return G;
  const M = 2*a0 + span;
  for(const t of G.treads){ const p=t.a0; t.a0=M-t.a1; t.a1=M-p; }
  if(G.landing){ const p=G.landing.a0; G.landing.a0=M-G.landing.a1; G.landing.a1=M-p; }
  for(const p of G.path) p.a = M - p.a;
  return G;
}

/* 경로를 따라 u∈[0,1] 위치 (수평 이동 길이 기준) */
function stairAt(G, u){
  const p=G.path;
  const seg=[]; let total=0;
  for(let i=0;i<p.length-1;i++){
    const L=Math.hypot(p[i+1].a-p[i].a, p[i+1].b-p[i].b) || 0.001;
    seg.push(L); total+=L;
  }
  let t=Math.max(0,Math.min(1,u))*total;
  for(let i=0;i<seg.length;i++){
    if(t<=seg[i] || i===seg.length-1){
      const k=Math.max(0,Math.min(1,t/seg[i]));
      const a=p[i].a+(p[i+1].a-p[i].a)*k;
      const b=p[i].b+(p[i+1].b-p[i].b)*k;
      const z=p[i].z+(p[i+1].z-p[i].z)*k;
      const q=G.XY(a,b);
      return {x:q.x, y:q.y, z};
    }
    t-=seg[i];
  }
  const q=G.XY(p[0].a,p[0].b);
  return {x:q.x, y:q.y, z:p[0].z};
}

/* 계단 렌더 — 단(flight)과 중간참을 실제 입체로 쌓는다 */
function drawStairs(f, C){
  if(f>=FLOORS-1) return;
  const z=f*FLOOR_H;
  const below=_se<0;
  for(const r of stairRuns()[f]){
    if(grids[f+1][r.y0][r.x0]!==STAIR) continue;
    const G=stairGeom(r);
    const P=(a,b)=>G.XY(a,b);

    // 중간참 — 두 단을 잇는 수평면
    if(G.landing){
      const L=G.landing;
      const c1=P(L.a0,L.b0), c2=P(L.a1,L.b0), c3=P(L.a1,L.b1), c4=P(L.a0,L.b1);
      if(!below){                                     // 참의 옆면(두께)
        qw([c1.x,c1.y,z+L.z, c2.x,c2.y,z+L.z, c2.x,c2.y,z+L.z-4, c1.x,c1.y,z+L.z-4]);
        X.fillStyle=C.stepRiser; X.fill();
      }
      qw([c1.x,c1.y,z+L.z, c2.x,c2.y,z+L.z, c3.x,c3.y,z+L.z, c4.x,c4.y,z+L.z]);
      X.fillStyle = below ? C.stepUnder : C.stepTread; X.fill();
      X.strokeStyle=C.stepEdge; X.stroke();
    }

    // 디딤판 — 카메라에서 먼 것부터
    const items=G.treads.map(t=>{
      const c=P((t.a0+t.a1)/2,(t.b0+t.b1)/2);
      return {t, d:c.x*_s + c.y*_c};
    }).sort((p,q)=>p.d-q.d);

    for(const {t} of items){
      const c1=P(t.a0,t.b0), c2=P(t.a1,t.b0), c3=P(t.a1,t.b1), c4=P(t.a0,t.b1);
      if(!below){                                     // 챌판
        qw([c1.x,c1.y,z+t.z1, c4.x,c4.y,z+t.z1, c4.x,c4.y,z+t.z0, c1.x,c1.y,z+t.z0]);
        X.fillStyle=C.stepRiser; X.fill();
      }
      qw([c1.x,c1.y,z+t.z1, c2.x,c2.y,z+t.z1, c3.x,c3.y,z+t.z1, c4.x,c4.y,z+t.z1]);
      X.fillStyle = below ? C.stepUnder : C.stepTread; X.fill();
      X.strokeStyle=C.stepEdge; X.stroke();
    }

    // 난간 — 경로를 그대로 따라간다
    X.strokeStyle=C.rail; X.lineWidth=1.7/isoView.Z;
    X.beginPath();
    for(let i=0;i<=40;i++){
      const q=stairAt(G,i/40);
      const sx=pxOf(q.x,q.y), sy=pyOf(q.x,q.y,z+q.z+0.36*CELL);
      i? X.lineTo(sx,sy) : X.moveTo(sx,sy);
    }
    X.stroke();
    X.lineWidth=1/isoView.Z;
  }
}

function drawUpper(f, C){
  const g=grids[f], z=f*FLOOR_H, top=z+WALL_H;
  const showX = _s>0 ? 1 : -1, showY = _c>0 ? 1 : -1;
  const below = _se < 0;
  const order=isoOrder();
  const isW=(yy,xx)=> (g[yy]?.[xx])===WALL;

  /* 실내에 접한 벽만 세운다.
     정사각 격자에서 도면을 그리면 남는 자리가 전부 벽으로 채워지는데,
     그것까지 3D로 세우면 건물이 사각 덩어리처럼 보인다.
     8방향 중 하나라도 실내(벽이 아닌 칸)와 닿은 벽만 실제 외벽·칸막이다. */
  const touchesRoom=(yy,xx)=>{
    for(let dy=-1;dy<=1;dy++) for(let dx=-1;dx<=1;dx++){
      if(!dx && !dy) continue;
      const t=g[yy+dy]?.[xx+dx];
      if(t!==undefined && t!==WALL) return true;
    }
    return false;
  };

  for(const [x,y] of order){
    if(g[y][x]!==WALL) continue;
    if(!touchesRoom(y,x)) continue;      // 도면 밖 여백은 그리지 않는다
    // 이웃이 벽이면 셀 경계까지, 아니면 안쪽으로 물려 얇은 벽면을 만든다
    const x0 = x*CELL       + (isW(y,x-1)?0:WALL_INSET);
    const x1 = (x+1)*CELL   - (isW(y,x+1)?0:WALL_INSET);
    const y0 = y*CELL       + (isW(y-1,x)?0:WALL_INSET);
    const y1 = (y+1)*CELL   - (isW(y+1,x)?0:WALL_INSET);
    if(x1<=x0||y1<=y0) continue;

    const sX = !isW(y, x+showX), sY = !isW(y+showY, x);
    // 옆면 — 광원이 월드 고정이라 +면/-면 밝기가 다르다
    if(sX){
      const xf = showX>0 ? x1 : x0;
      qw([xf,y0,top, xf,y1,top, xf,y1,z, xf,y0,z]);
      X.fillStyle = showX>0 ? C.wallShX : C.wallLitX; X.fill();
    }
    if(sY){
      const yf = showY>0 ? y1 : y0;
      qw([x0,yf,top, x1,yf,top, x1,yf,z, x0,yf,z]);
      X.fillStyle = showY>0 ? C.wallShY : C.wallLitY; X.fill();
    }
    // 마감면: 위에서 보면 윗면, 아래에서 보면 밑면
    const cz = below ? z : top;
    qw([x0,y0,cz, x1,y0,cz, x1,y1,cz, x0,y1,cz]);
    X.fillStyle = below ? C.wallUnder : C.wallTop; X.fill();

    // 외곽선은 노출된 모서리에만 → 벽이 이어져 보인다
    X.strokeStyle=C.wallEdge;
    X.beginPath();
    if(!isW(y-1,x)){ const a=[x0,y0],b=[x1,y0];
      X.moveTo(pxOf(a[0],a[1]),pyOf(a[0],a[1],cz)); X.lineTo(pxOf(b[0],b[1]),pyOf(b[0],b[1],cz)); }
    if(!isW(y+1,x)){ const a=[x0,y1],b=[x1,y1];
      X.moveTo(pxOf(a[0],a[1]),pyOf(a[0],a[1],cz)); X.lineTo(pxOf(b[0],b[1]),pyOf(b[0],b[1],cz)); }
    if(!isW(y,x-1)){ const a=[x0,y0],b=[x0,y1];
      X.moveTo(pxOf(a[0],a[1]),pyOf(a[0],a[1],cz)); X.lineTo(pxOf(b[0],b[1]),pyOf(b[0],b[1],cz)); }
    if(!isW(y,x+1)){ const a=[x1,y0],b=[x1,y1];
      X.moveTo(pxOf(a[0],a[1]),pyOf(a[0],a[1],cz)); X.lineTo(pxOf(b[0],b[1]),pyOf(b[0],b[1],cz)); }
    X.stroke();
  }

  drawStairs(f, C);
}

/* ---------- 정적 레이어 캐시 ---------- */
let isoStatic={key:null, base:[], upper:[], destY:[], lh:0};
function staticKey(){
  return [ISO.yaw.toFixed(4), ISO.pitch.toFixed(4), ISO.zoom.toFixed(3), FLOORS, GW, GH,
          gridVer, ISO.all?1:0, ISO.floorView, ISO.spread, viewF].join('|');
}
function makeLayer(w,h){
  const c=document.createElement('canvas'); c.width=w; c.height=h; return c;
}
function buildStatic(floorsToDraw){
  const key=staticKey();
  if(isoStatic.key===key) return true;

  const {Z,minX,minY,minVY,maxVY,pad}=isoView;
  // 한 층이 차지하는 세로 범위 (모든 층 동일)
  const lh = Math.ceil((maxVY-minVY + (WALL_H+SLAB_T)*Math.cos(ISO.pitch))*Z) + 6;
  const lw = cv.width;
  // 메모리 가드: 과도하면 캐시 없이 직접 그리기
  if(lw*lh*4*2*floorsToDraw.length > 90e6){ isoStatic.key=null; return false; }

  const base=[], upper=[], destY=[];
  for(const f of floorsToDraw){
    const z=f*FLOOR_H;
    const dy = Math.floor((minVY - (z+WALL_H)*Math.cos(ISO.pitch) - minY)*Z + pad);
    const b=makeLayer(lw,lh), u=makeLayer(lw,lh);
    const C=isoPalette((!ISO.all || f===viewF) ? 1 : 0.55);
    for(const [cnv,fn] of [[b,drawBase],[u,drawUpper]]){
      X=cnv.getContext('2d');
      X.setTransform(Z,0,0,Z, pad-minX*Z, pad-minY*Z-dy);
      X.lineWidth=1/Z; X.lineJoin='round';
      fn(f,C);
    }
    base.push(b); upper.push(u); destY.push(dy);
  }
  isoStatic={key, base, upper, destY, lh};
  return true;
}

/* ---------- 프레임 렌더 ---------- */
function renderIso(){
  isoLayout();
  const vc=vertCache(); _VX=vc.VX; _VY=vc.VY; _W1=GW+1;
  _c=Math.cos(ISO.yaw); _s=Math.sin(ISO.yaw);
  _se=Math.sin(ISO.pitch); _ce=Math.cos(ISO.pitch);

  ctx.clearRect(0,0,cv.width,cv.height);
  // 아래에서 올려다볼 때는 바닥층이 카메라에 가장 가까우므로 그리는 순서를 뒤집는다
  const below = _se < 0;
  let floorsToDraw = ISO.all ? [...Array(FLOORS).keys()] : [viewF];
  if(below) floorsToDraw = floorsToDraw.slice().reverse();
  const cached = buildStatic(floorsToDraw);
  const {Z,minX,minY,pad}=isoView;
  const t=performance.now()/90;

  for(let k=0;k<floorsToDraw.length;k++){
    const f=floorsToDraw[k];
    const dim=(!ISO.all || f===viewF) ? 1 : 0.55;
    const C=isoPalette(dim);
    const z=f*FLOOR_H;

    // 메인 캔버스 좌표계로
    ctx.setTransform(1,0,0,1,0,0);
    if(cached) ctx.drawImage(isoStatic.base[k], 0, isoStatic.destY[k]);

    X=ctx;
    ctx.setTransform(Z,0,0,Z, pad-minX*Z, pad-minY*Z);
    ctx.lineWidth=1/Z; ctx.lineJoin='round';
    if(!cached) drawBase(f,C);

    /* --- 동적: 연기 / 화염 --- */
    const fr=fires[f], sm=smokes[f], g=grids[f];
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      const sv=sm[y][x], isFire=fr[y][x];
      if(sv<=0.03 && !isFire) continue;
      const i00=VI(x,y), i10=VI(x+1,y), i11=VI(x+1,y+1), i01=VI(x,y+1);
      if(sv>0.03 && g[y][x]!==WALL){
        qg(i00,z,i10,z,i11,z,i01,z);
        ctx.fillStyle=C.SMOKE[Math.min(16,(sv*16)|0)]; ctx.fill();
      }
      if(isFire){
        const fl=.72+.28*Math.sin(t+x*1.7+y*2.3);
        qg(i00,z,i10,z,i11,z,i01,z);
        ctx.fillStyle=C.FIRE[Math.min(8,(((fl-0.72)/0.28)*8)|0)]; ctx.fill();
        const h=CELL*(0.7+0.5*Math.sin(t*1.3+x));
        qg(i00,z,i10,z,i10,z+h,i00,z+h);
        ctx.fillStyle=C.flameA; ctx.fill();
        qg(i00,z+h*0.5,i11,z+h*0.5,i11,z+h,i00,z+h);
        ctx.fillStyle=C.flameB; ctx.fill();
      }
    }

    // 정적 상단(벽·계단) 덮기
    ctx.setTransform(1,0,0,1,0,0);
    if(cached) ctx.drawImage(isoStatic.upper[k], 0, isoStatic.destY[k]);
    ctx.setTransform(Z,0,0,Z, pad-minX*Z, pad-minY*Z);
    if(!cached) drawUpper(f,C);

    /* --- 동적: 재실자 (깊이순) --- */
    const list=[];
    for(const a of agents)
      if(a.f===f && a.state!=='escaped' && a.stairT<=0) list.push(a);
    list.sort((p,q)=>(p.x*_s+p.y*_c)-(q.x*_s+q.y*_c));
    ctx.globalAlpha=dim;
    for(const a of list){
      ctx.save();
      ctx.translate(pxOf(a.x,a.y), pyOf(a.x,a.y,z));
      drawStickBody(a);
      ctx.restore();
    }
    ctx.globalAlpha=1;

    /* --- 계단 번호 --- */
    (stairRuns()[f]||[]).forEach((r,idx)=>{
      const wx=(r.x0+r.x1+1)/2*CELL, wy=(r.y0+r.y1+1)/2*CELL;
      const sx=pxOf(wx,wy), sy=pyOf(wx,wy,z)-6;
      ctx.globalAlpha=dim;
      ctx.beginPath(); ctx.arc(sx,sy,9/Z,0,Math.PI*2);
      ctx.fillStyle='rgba(16,34,50,.9)'; ctx.fill();
      ctx.strokeStyle='#5FC9F8'; ctx.lineWidth=1.4/Z; ctx.stroke();
      ctx.fillStyle='#9FDCFB';
      ctx.font='700 '+(11/Z)+'px "Saira Condensed",sans-serif';
      ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillText(String(idx+1), sx, sy);
      ctx.textBaseline='alphabetic'; ctx.globalAlpha=1; ctx.lineWidth=1/Z;
    });

    /* --- 발화 예정 지점 --- */
    if(ignition && !running && !ended && ignition[0]===f){
      const gx=ignition[1], gy=ignition[2];
      qg(VI(gx,gy),z, VI(gx+1,gy),z, VI(gx+1,gy+1),z, VI(gx,gy+1),z);
      ctx.strokeStyle='#FF4B1F'; ctx.lineWidth=2.5/Z; ctx.stroke(); ctx.lineWidth=1/Z;
    }
  }

  /* --- 계단 이동 중인 재실자: 층 사이 공간에 실제 높이로 그림 --- */
  const transit = agents.filter(a=>a.stairT>0 && a.state!=='escaped'
                    && (ISO.all || a.f===viewF || a.stairTo===viewF));
  if(transit.length){
    transit.sort((p,q)=>(p.x*_s+p.y*_c)-(q.x*_s+q.y*_c));
    ctx.setTransform(Z,0,0,Z, pad-minX*Z, pad-minY*Z);
    _transitPass=true;
    for(const a of transit){
      const az=agentZ(a);
      ctx.save();
      ctx.translate(pxOf(a.x,a.y), pyOf(a.x,a.y,az));
      ctx.globalAlpha=0.95;
      drawStickBody(a);
      ctx.restore();
    }
    _transitPass=false;
    ctx.globalAlpha=1;
  }
  ctx.setTransform(1,0,0,1,0,0);
}

/* 계단 이동 중인 재실자의 위치 — 디딤판이 놓인 방향을 따라 걷는다.
   u=0 은 아래 참, u=1 은 위 참. 내려갈 때는 u가 1→0 으로 줄어든다. */
function stairPos(a){
  const r=a.stairRun;
  if(!r || a.stairT<=0) return null;
  const p=1-a.stairT/STAIR_DUR;                        // 0 → 1
  const down = a.stairTo < a.stairFrom;
  const u = down ? 1-p : p;                            // 내려갈 때는 위에서부터
  const G=stairGeom(r);
  const q=stairAt(G,u);
  return {x:q.x, y:q.y, z:a.stairLo*FLOOR_H + q.z, u};
}

/* 계단 이동 중인 재실자의 실제 높이 (층 사이를 실시간으로 오르내림) */
function agentZ(a){
  if(a.stairT<=0) return a.f*FLOOR_H;
  const P=stairPos(a);
  if(P) return P.z;
  const p=1-a.stairT/STAIR_DUR;
  return a.stairFrom*FLOOR_H + (a.stairTo-a.stairFrom)*FLOOR_H*p;
}

/* 3D 화면 좌표 → 셀 (현재 층 평면 기준) */
function isoPick(sx,sy){
  const rx = (sx - isoView.pad)/isoView.Z + isoView.minX;
  const py = (sy - isoView.pad)/isoView.Z + isoView.minY;
  const se=Math.sin(ISO.pitch);
  if(Math.abs(se)<0.05) return null;          // 수평선 근처에서는 바닥면이 모로 서서 지정 불가
  const ry = (py + viewF*FLOOR_H*Math.cos(ISO.pitch)) / se;
  const c=Math.cos(ISO.yaw), s=Math.sin(ISO.yaw);
  const gx=Math.floor(( rx*c + ry*s)/CELL);
  const gy=Math.floor((-rx*s + ry*c)/CELL);
  if(gx<0||gy<0||gx>=GW||gy>=GH) return null;
  return [gx,gy];
}

let rectStart=null, rectNow=null;

/* =========================================================
   렌더 디스패처
========================================================= */
let viewMode = 'iso';                 // 'plan' | 'iso'
function render(){
  if(viewMode==='iso' && !editMode) renderIso();
  else renderPlan();
}

/* =========================================================
   층 탭
========================================================= */
let ftabEls=null, ftabSig='';
function updateFloorTabs(){
  const tabs=document.getElementById('floorTabs');
  const sig=FLOORS+'';
  // 매 프레임 innerHTML 을 갈아끼우면 누르는 순간 버튼이 교체돼 클릭이 사라진다.
  // 구조가 바뀔 때만 다시 만들고, 평소에는 내용만 갱신한다.
  if(sig!==ftabSig || !ftabEls){
    ftabSig=sig; tabs.innerHTML=''; ftabEls=[];
    for(let f=0;f<FLOORS;f++){
      const b=document.createElement('button');
      b.className='ftab'; b.dataset.f=f;
      b.innerHTML='<span class="fl"></span><span class="bar"><i></i></span><span class="n"></span>';
      b.addEventListener('pointerdown', ev=>{     // click 대신 pointerdown → 즉시 반응
        ev.preventDefault();
        viewF=+b.dataset.f;
        isoStatic.key=null;                        // 강조 층이 바뀌므로 캐시 무효화
        updateFloorTabs();
        if(viewMode==='plan'||editMode) viewLabel.textContent='평면도 — '+floorLabel(viewF);
        if(editMode && mapTextEl) mapTextEl.value=serializeMap();
        if(editMode) runDiagnostics();
      });
      tabs.appendChild(b);
      ftabEls.push({b, bar:b.querySelector('i'), n:b.querySelector('.n'), fl:b.querySelector('.fl')});
    }
  }
  const total=Math.max(1,agents.length);
  for(let f=0;f<FLOORS;f++){
    const el=ftabEls[f];
    const alive=agents.filter(a=>a.f===f &&
      (a.state==='idle'||a.state==='run'||a.state==='isolated')).length;
    let burning=false;
    for(let y=0;y<GH&&!burning;y++) for(let x=0;x<GW;x++) if(fires[f][y][x]){burning=true;break;}
    if(el.fl.textContent!==floorShort(f)) el.fl.textContent=floorShort(f);
    el.b.classList.toggle('basement', f<BASEMENTS);
    el.b.title=floorLabel(f);
    el.b.classList.toggle('active', f===viewF);
    el.b.classList.toggle('burning', burning);
    el.b.setAttribute('aria-pressed', f===viewF);
    const w=Math.round(alive/total*100)+'%';
    if(el.bar.style.width!==w) el.bar.style.width=w;
    const t=String(alive);
    if(el.n.textContent!==t) el.n.textContent=t;
  }
}

/* =========================================================
   통계 · 종료 판정
========================================================= */
function updateStats(){
  const inB  = agents.filter(a=>a.state==='idle'||a.state==='run').length;
  const out  = agents.filter(a=>a.state==='escaped').length;
  const dead = agents.filter(a=>a.state==='dead').length;
  let fcnt=0; const fireFl=new Set();
  for(let f=0;f<FLOORS;f++) for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
    if(fires[f][y][x]){ fcnt++; fireFl.add(f); }

  sIn.textContent=inB; sOut.textContent=out; sDead.textContent=dead;
  sFire.textContent=(fcnt*M2_PER_CELL).toFixed(1)+'㎡';
  clock.textContent=simT.toFixed(1)+'s';

  /* 생존한계 — 재실자가 서 있는 곳의 최악 가시거리와 최대 누적노출 */
  const tenEl=document.getElementById('sTen');
  if(tenEl){
    let worstVis=30, maxFed=0;
    for(const a of agents){
      if(a.state!=='run' && a.state!=='idle' && a.state!=='isolated') continue;
      const gx=Math.floor(a.x/CELL), gy=Math.floor(a.y/CELL);
      const sv=smokes[a.f]?.[gy]?.[gx]||0;
      const v=visibilityOf(sv);
      if(v<worstVis) worstVis=v;
      if((a.fed||0)>maxFed) maxFed=a.fed;
    }
    tenEl.textContent = worstVis.toFixed(0)+'m / '+(maxFed*100).toFixed(0)+'%';
    tenEl.parentElement.classList.toggle('bad', worstVis<5 || maxFed>0.3);
  }
  const biF=document.getElementById('biFire');
  if(biF){
    const txt = fireFl.size
      ? [...fireFl].sort((a,b)=>a-b).map(floorShort).join(', ')
      : '—';
    if(biF.textContent!==txt) biF.textContent=txt;
  }
  updateFloorTabs();

  if(running && inB===0){
    running=false; ended=true;
    const total=agents.length;
    const iso = agents.filter(a=>a.state==='isolated').length;
    const times=agents.filter(a=>a.state==='escaped').map(a=>a.tEscape);
    const avg = times.length? (times.reduce((s,v)=>s+v,0)/times.length):0;
    const last= times.length? Math.max(...times):0;
    const v=document.getElementById('verdict');
    v.classList.add('show');
    if(dead===0 && iso===0){
      v.classList.add('ok');
      vBadge.textContent='전원 탈출 완료';
    }else{
      v.classList.add('fail');
      vBadge.textContent='탈출 실패 — 사망 '+dead+'명'+(iso?', 고립 '+iso+'명':'');
    }
    vDetail.innerHTML =
      `탈출률 ${(out/total*100).toFixed(1)}% (${out}/${total})`+
      (iso?`<br>고립(구조 필요) ${iso}명`:'')+
      `<br>평균 탈출 소요 ${avg.toFixed(1)}s · 최종 탈출 ${last.toFixed(1)}s<br>`+
      `요구피난시간 RSET ${last.toFixed(1)}s`;
  }
}

/* =========================================================
   메인 루프
========================================================= */
let prev=performance.now(), statTimer=0;
function loop(now){
  const dt=Math.min((now-prev)/1000, .05); prev=now;
  if(running){
    simT+=dt;
    stepFire(dt);
    stepAgents(dt);
    distTimer+=dt;
    if(distTimer>0.5){ computeDist(); distTimer=0; }

    // 남은 전원이 8초 이상 완전 고립이면 '고립(구조 필요)' 종료
    const runners = agents.filter(a=>a.state==='run');
    if(runners.length && runners.every(a=>
      dAt(a.f, a.y/CELL|0, a.x/CELL|0)===Infinity
    )) allTrappedT+=dt; else allTrappedT=0;
    if(allTrappedT>8) runners.forEach(a=>a.state='isolated');
  }
  statTimer+=dt;
  if(statTimer>0.12){ updateStats(); statTimer=0; }
  render();
  requestAnimationFrame(loop);
}

/* =========================================================
   UI 바인딩
========================================================= */
const rPeople=document.getElementById('rPeople'),
      rSpread=document.getElementById('rSpread'),
      rSpeed =document.getElementById('rSpeed');

rPeople.oninput=()=>{ oPeople.textContent=rPeople.value+'명'; if(!running&&!ended) spawnAgents(+rPeople.value); };

/* ── 재실자 구성 ── */
const MIX_PRESET = {
  adult:  { adult:100, child:0,  toddler:0,  elderly:0  },
  school: { adult:15,  child:85, toddler:0,  elderly:0  },
  kinder: { adult:20,  child:0,  toddler:80, elderly:0  },
  care:   { adult:25,  child:0,  toddler:0,  elderly:75 },
};
const selMix=document.getElementById('selMix'), oMix=document.getElementById('oMix');
const mixCustom=document.getElementById('mixCustom');
const mxEls={ adult:document.getElementById('mxAdult'), child:document.getElementById('mxChild'),
              toddler:document.getElementById('mxToddler'), elderly:document.getElementById('mxElderly') };

function mixLabel(){
  const tot=Object.values(MIX).reduce((a,b)=>a+Math.max(0,+b||0),0)||1;
  const parts=[];
  for(const k in MIX){
    const w=Math.max(0,+MIX[k]||0);
    if(w>0) parts.push(P_TYPES[k].name+' '+Math.round(w/tot*100)+'%');
  }
  return parts.join(' · ') || '성인 100%';
}
function applyMixUI(){
  for(const k in mxEls) if(mxEls[k]) mxEls[k].value = MIX[k];
  if(oMix) oMix.textContent = mixLabel();
  if(mixCustom) mixCustom.hidden = (selMix.value!=='custom');
}
function setMix(m, sel){
  MIX = { adult:+(m.adult||0), child:+(m.child||0), toddler:+(m.toddler||0), elderly:+(m.elderly||0) };
  if(sel && selMix) selMix.value = sel;
  applyMixUI();
  if(!running && !ended) spawnAgents(+rPeople.value);
}
if(selMix){
  selMix.onchange=()=>{
    if(selMix.value==='custom'){ mixCustom.hidden=false; oMix.textContent=mixLabel(); return; }
    setMix(MIX_PRESET[selMix.value]||MIX_PRESET.adult, selMix.value);
    autoSave();
  };
  for(const k in mxEls) if(mxEls[k]) mxEls[k].oninput=()=>{
    MIX[k]=Math.max(0,+mxEls[k].value||0);
    if(selMix.value!=='custom') selMix.value='custom';
    mixCustom.hidden=false;
    oMix.textContent=mixLabel();
    if(!running && !ended) spawnAgents(+rPeople.value);
    autoSave();
  };
}
rSpread.oninput=()=>{ oSpread.textContent='×'+(+rSpread.value).toFixed(1); updateFireInfo(); };
const selGrade=document.getElementById('selGrade'), oGrade=document.getElementById('oGrade');
if(selGrade){
  selGrade.onchange=()=>{
    fireGrade=selGrade.value;
    oGrade.textContent=selGrade.options[selGrade.selectedIndex].text.split(' · ')[0];
    updateFireInfo();
  };
}
/* 현재 설정의 물리량을 근거와 함께 보여준다 */
function updateFireInfo(){
  const el=document.getElementById('gradeNote'); if(!el) return;
  const tg=FIRE_TG[fireGrade]||300, a=1055/(tg*tg);
  const q120=(a*120*120*(+rSpread.value)).toFixed(0);
  const A=Math.min(q120,FIRE_QMAX)/HRRPUA;
  el.innerHTML='NFPA 204 · NFPA 92의 t² 화재성장곱선 — α = 1055/tg² = <b>'
    + a.toFixed(4) + '</b> kW/s²<br>발화 2분 후 열방출률 약 <b>' + q120
    + ' kW</b>, 연소면적 약 <b>' + A.toFixed(1) + ' ㎡</b> (HRRPUA ' + HRRPUA + ' kW/㎡ 기준)';
}
updateFireInfo();
rSpeed .oninput=()=>{ oSpeed .textContent='×'+(+rSpeed.value).toFixed(1); };

btnStart.onclick=start;
btnPause.onclick=()=>{ if(!ended){ running=!running; btnPause.textContent=running?'일시정지':'이어서 진행'; } };
btnReset.onclick=()=>{ reset(); btnPause.textContent='일시정지'; };

/* 캔버스 클릭 → 발화 지점 (2D/3D 공용) / 3D 드래그 → 회전 */
let dragY0=null, dragX0=null, dragYaw=0, dragPitch=0, dragged=false;
const clampPitch=p=>Math.max(PITCH_MIN,Math.min(PITCH_MAX,p));

function canvasCell(e){
  const r=cv.getBoundingClientRect();
  const sx=(e.clientX-r.left)*(cv.width /r.width);
  const sy=(e.clientY-r.top )*(cv.height/r.height);
  if(viewMode==='iso' && !editMode) return isoPick(sx,sy);
  const gx=Math.floor((sx-PLAN_PAD)/CELL), gy=Math.floor((sy-PLAN_PAD)/CELL);
  if(gx<0||gy<0||gx>=GW||gy>=GH) return null;
  return [gx,gy];
}

cv.addEventListener('click', e=>{
  if(editMode || dragged) return;
  const cell=canvasCell(e);
  if(!cell) return;
  const [gx,gy]=cell;
  const t=grids[viewF]?.[gy]?.[gx];
  if(t!==FLOOR && t!==STAIR && t!==DOOR) return;
  if(running){ igniteAt(viewF,gx,gy); ignition=[viewF,gx,gy]; ignitionT=simT; }
  else { ignition=[viewF,gx,gy]; }
});

// 3D 회전 드래그
cv.addEventListener('pointerdown', e=>{
  if(editMode || viewMode!=='iso') return;
  dragX0=e.clientX; dragY0=e.clientY;
  dragYaw=ISO.yaw; dragPitch=ISO.pitch; dragged=false;
  cv.setPointerCapture(e.pointerId);
});
cv.addEventListener('pointermove', e=>{
  if(dragX0===null) return;
  const dx=e.clientX-dragX0, dy=e.clientY-dragY0;
  if(Math.abs(dx)>3||Math.abs(dy)>3) dragged=true;
  ISO.yaw   = dragYaw + dx*0.007;                                  // 좌우 = 360° 회전
  ISO.pitch = clampPitch(dragPitch + dy*0.005);                    // 위아래 = 올려/내려보기
});
cv.addEventListener('pointerup', ()=>{ dragX0=null; setTimeout(()=>dragged=false,0); });
cv.addEventListener('wheel', e=>{
  if(viewMode!=='iso'||editMode) return;
  e.preventDefault();
  ISO.zoom = Math.max(0.4, Math.min(4, ISO.zoom * (e.deltaY<0?1.12:0.89)));
}, {passive:false});

/* 뷰 모드 · 회전 · 줌 버튼 */
function setViewMode(m){
  viewMode=m;
  btn3D.classList.toggle('active', m==='iso');
  btn2D.classList.toggle('active', m==='plan');
  viewLabel.textContent = m==='iso'
    ? '축측투영 — 드래그로 회전, 휠로 확대'
    : '평면도 — ' + floorLabel(viewF);
}
btn3D.onclick=()=>{ if(editMode) setEditMode(false); setViewMode('iso'); };
btn2D.onclick=()=>setViewMode('plan');

/* ── 전체화면 ──
   가능한 브라우저는 Fullscreen API, iOS 사파리는 고정 오버레이로 흉내낸다. */
(function(){
  const b = document.getElementById('btnFS');
  const st = document.getElementById('stage');
  if(!b || !st) return;

  const hasFS = st.requestFullscreen || st.webkitRequestFullscreen;

  function inFS(){
    return document.fullscreenElement || document.webkitFullscreenElement ||
           st.classList.contains('fs-fallback');
  }
  function enter(){
    if(st.requestFullscreen)            st.requestFullscreen().catch(fallback);
    else if(st.webkitRequestFullscreen) st.webkitRequestFullscreen();
    else fallback();
  }
  function fallback(){
    st.classList.add('fs-fallback');
    document.body.classList.add('fs-lock');
    onChange();
  }
  function leave(){
    if(document.fullscreenElement)            document.exitFullscreen().catch(()=>{});
    else if(document.webkitFullscreenElement) document.webkitExitFullscreen();
    st.classList.remove('fs-fallback');
    document.body.classList.remove('fs-lock');
    onChange();
  }
  function onChange(){
    b.textContent = inFS() ? '✕' : '⛶';
    b.title = inFS() ? '전체화면 종료' : '전체화면';
    /* 크기가 바뀌므로 3D 캐시를 비우고 다시 그린다 */
    isoCache.yaw=null; isoStatic.key=null;
    try{ render(); }catch(e){}
  }
  b.onclick = ()=> inFS() ? leave() : enter();
  document.addEventListener('fullscreenchange', onChange);
  document.addEventListener('webkitfullscreenchange', onChange);
  /* 전체화면에서 Esc 로 나갈 때도 반영된다(fullscreenchange 발생) */
})();
btnRotL.onclick=()=>{ ISO.yaw-=Math.PI/12; };
btnRotR.onclick=()=>{ ISO.yaw+=Math.PI/12; };
btnUp.onclick  =()=>{ ISO.pitch=clampPitch(ISO.pitch+0.12); };
btnDown.onclick=()=>{ ISO.pitch=clampPitch(ISO.pitch-0.12); };

/* 시점 프리셋: 앞 / 뒤 / 좌 / 우 / 위 / 기본 */
const PRESETS={
  front:[0,0.30], back:[Math.PI,0.30], left:[Math.PI/2,0.30], right:[-Math.PI/2,0.30],
  top:[0.62,1.45], under:[0.62,-0.85], iso:[0.62,0.52],
};
document.querySelectorAll('.preset').forEach(b=>{
  b.onclick=()=>{ const p=PRESETS[b.dataset.v]; ISO.yaw=p[0]; ISO.pitch=p[1]; };
});
/* 자동 회전 */
let spin=false;
btnSpin.onclick=()=>{ spin=!spin; btnSpin.classList.toggle('on',spin);
  btnSpin.textContent = spin ? '회전 멈추기' : '자동 회전'; };
setInterval(()=>{ if(spin && viewMode==='iso' && !editMode) ISO.yaw+=0.012; }, 33);
btnZoomIn.onclick =()=>{ ISO.zoom=Math.min(4, ISO.zoom*1.15); };
btnZoomOut.onclick=()=>{ ISO.zoom=Math.max(0.4, ISO.zoom*0.87); };
btnFit.onclick    =()=>{ ISO.zoom=1; };
window.addEventListener('resize', ()=>{ isoCache.yaw=null; isoStatic.key=null; });
/* 모바일: 회전하거나 주소표시줄이 접힐 때 도면 다시 맞춤 */
(function(){
  let t=null;
  const redraw=()=>{ clearTimeout(t); t=setTimeout(()=>{
    isoCache.yaw=null; isoStatic.key=null;
    try{ render(); }catch(e){}
  },180); };
  window.addEventListener('orientationchange', redraw);
  window.addEventListener('resize', redraw);
})();
(function(){
  const btns=[...document.querySelectorAll('button.fv')];
  const wrap=document.getElementById('spreadWrap');
  const rs=document.getElementById('rSpread3D');
  function apply(v){
    ISO.floorView=v;
    ISO.all = (v!=='one');
    btns.forEach(b=>b.classList.toggle('active', b.dataset.fv===v));
    if(wrap) wrap.hidden = (v!=='split');
    isoStatic.key=null; isoCache.yaw=null;      // 캐시 무효화
    updateFloorH();
    try{ render(); }catch(e){}
  }
  btns.forEach(b=>b.onclick=()=>{
    if(b.dataset.fv!=='one' && FLOORS>SPLIT_MAX &&
       !confirm(FLOORS+'개 층을 한 번에 그리면 느려질 수 있습니다.\n계속할까요?')) return;
    apply(b.dataset.fv);
  });
  if(rs) rs.oninput=()=>{
    ISO.spread=+rs.value;
    isoStatic.key=null; isoCache.yaw=null;
    updateFloorH();
    try{ render(); }catch(e){}
  };
  apply('one');
})();

/* =========================================================
   도면 편집 모드
========================================================= */
let editMode=false;

function setEditMode(on){
  editMode=on;
  try{ updateOriginLegend(); }catch(e){}
  if(on){ btn3D.classList.remove('active'); btn2D.classList.add('active'); }
  else if(viewMode==='iso'){ btn3D.classList.add('active'); btn2D.classList.remove('active'); }
  document.body.classList.toggle('editing',on);
  if(on){
    reset();
    mapTextEl.value = serializeMap();
    fCount.textContent = (BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
    runDiagnostics(); syncUndoUI(); renderStairCfg();
  }else{
    customMapText = serializeMap();
    reset();
  }
}

/* ============================================================
   편집 엔진
   "무엇을 그릴지(도구)" 와 "어떻게 그릴지(방식)" 를 분리한다.
   벽·바닥·비상구는 어느 방식으로도 그릴 수 있고,
   구획·계단실은 사각형으로만 성립한다.
   ============================================================ */
let editTool='wall';                    // wall | floor | exit | room | stair
let editShape='rect';                   // free | line | rect | fill
let brushSize=1;                        // 자유 그리기 붓 크기(칸)
let painting=false;
const TOOL_CELL = { wall:WALL, floor:FLOOR, exit:EXIT, stair:STAIR, room:FLOOR, hydrant:HYDRANT, door:DOOR, erase:WALL, poly:WALL };
const SHAPE_FIXED = { room:'rect', stair:'rect', erase:'rect', poly:'poly' };   // 방식이 고정된 도구
const mapTextEl = document.getElementById('mapText');

function activeShape(){ return SHAPE_FIXED[editTool] || editShape; }

function cellFromEvent(e){
  const r=cv.getBoundingClientRect();
  const sx=(e.clientX-r.left)*(cv.width /r.width) - PLAN_PAD;
  const sy=(e.clientY-r.top )*(cv.height/r.height) - PLAN_PAD;
  return [Math.floor(sx/CELL), Math.floor(sy/CELL)];
}
function inBounds(x,y){ return x>=0&&y>=0&&x<GW&&y<GH; }

let doorBlocked = 0;      // 잠금 때문에 무시된 칸 수 (안내용)

function setCell(x,y,t){
  if(!inBounds(x,y)) return;
  /* 문이 놓인 자리는 잠금 중이면 어떤 도구로도 덮어쓰지 않는다.
     단, 문 도구로 문을 다시 칠하거나 지우는 것은 허용한다. */
  if(doorLock && grids[viewF][y][x]===DOOR && t!==DOOR){ doorBlocked++; return; }
  grids[viewF][y][x]=t;
  fires[viewF][y][x]=0; smokes[viewF][y][x]=0;
}
/* ============================================================
   모델 보관함 — 브라우저에 이름 붙여 여러 개 저장
   저장 위치: localStorage.  서버·로그인 없이 동작한다.
   ============================================================ */
const STORE_KEY = 'fireEvac.models.v1';
const CUR_KEY   = 'fireEvac.current.v1';
const CAN_EDIT  = !EMBED;

let models = {};          // { id: {name, map, scenario, stats, updated} }
let curId  = null;

/* 관리자는 서버 보관함(LIB_API)을, 그 외에는 브라우저를 쓴다. */
const USE_LIB = IS_ADMIN && !EMBED;

async function libList(){
  const r = await fetch(LIB_API + '?act=list', {credentials:'same-origin'});
  const j = await r.json();
  if(!j.ok) throw new Error(j.error || '보관함 조회 실패');
  return j.models || [];
}
async function libGet(id){
  const r = await fetch(LIB_API + '?act=get&id=' + encodeURIComponent(id),
    {credentials:'same-origin'});
  const j = await r.json();
  if(!j.ok) throw new Error(j.error || '도면을 불러오지 못했습니다');
  return j.model;
}
async function libPost(data){
  const body = new URLSearchParams(data);
  body.set('csrf', LIB_CSRF);
  const r = await fetch(LIB_API, {method:'POST', credentials:'same-origin', body});
  return r.json();
}

function loadStore(){
  if(USE_LIB){
    /* 목록만 먼저 받고, 도면 본문은 열 때 가져온다.
       Promise 를 돌려주어 시작 코드가 목록을 기다릴 수 있게 한다. */
    models = {};
    try{ curId = localStorage.getItem(CUR_KEY) || null; }catch(e){ curId = null; }
    return libList().then(list => {
      list.forEach(m => { models[m.id] = {name:m.name, map:'', scenario:m.scenario||{},
                                          updated:m.updated, used:m.used, stub:true}; });
      renderModelList();
    }).catch(e => { setCloud('보관함 오류 — ' + e.message, 'failed'); throw e; });
  }
  try{ models = JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {}; }
  catch(e){ models = {}; }
  try{ curId = localStorage.getItem(CUR_KEY) || null; }catch(e){ curId = null; }
}
function writeStore(){
  if(USE_LIB){ try{ if(curId) localStorage.setItem(CUR_KEY, curId); }catch(e){} return true; }
  try{
    localStorage.setItem(STORE_KEY, JSON.stringify(models));
    if(curId) localStorage.setItem(CUR_KEY, curId);
    return true;
  }catch(e){ return false; }
}
const newId = () => 'm' + Date.now().toString(36) + Math.random().toString(36).slice(2,6);

function setCloud(txt, cls){
  if(cloudState){ cloudState.textContent=txt; cloudState.className=cls||''; }
  if(saveState){  saveState.textContent=txt;  saveState.className='eyebrow '+(cls==='failed'?'failed':'saved'); }
}

/* 현재 작업 내용을 기록 — 호스트 페이지 안이면 서버로, 아니면 브라우저에 */
let saveTimer=null;
async function pushToHost(){
  const body=new URLSearchParams();
  body.set('act','save');
  body.set('map', serializeMap());
  body.set('meta', JSON.stringify({
    name: modelName.value.trim(),
    people:+rPeople.value, spread:+rSpread.value, speed:+rSpeed.value,
    mix:{...MIX},
    floors:FLOORS, basements:BASEMENTS,
    area:diag.area, travel:diag.travel, isolated:diag.isolated,
  }));
  try{
    const r=await fetch(SAVE_URL || location.href,
      {method:'POST',credentials:'same-origin',body});
    const j=await r.json();
    setCloud(j.ok ? ('저장됨 · '+(j.saved||'')) : ('저장 실패 — '+(j.error||'')),
             j.ok ? '' : 'failed');
  }catch(e){ setCloud('저장 실패 — 통신 오류','failed'); }
}

/* 저장이 끝나기 전에 또 불리면 같은 도면이 여러 번 생성된다.
   진행 중에는 대기시켜두고, 끝나면 마지막 요청 한 번만 다시 보낸다. */
let libSaving = false, libPending = false;

async function pushSaveLib(){
  if(libSaving){ libPending = true; return; }   // 중복 생성 방지
  libSaving = true;
  const name = modelName.value.trim() || '이름 없는 건물';
  const map  = serializeMap();
  const scen = {people:+rPeople.value, spread:+rSpread.value, speed:+rSpeed.value};
  const isNew = (!curId || curId === 'new');
  try{
    const j = await libPost({act:'save', id:(isNew ? '' : curId),
                             name:name, map:map, scenario:JSON.stringify(scen)});
    if(!j.ok){ setCloud('저장 실패 — ' + (j.error||''), 'failed'); return; }
    /* 임시 항목('new')은 지우고 서버가 발급한 ID로 교체 */
    if(isNew && models['new']) delete models['new'];
    curId = j.id;
    try{ localStorage.setItem(CUR_KEY, curId); }catch(e){}
    models[curId] = {name:name, map:map, scenario:scen, updated:Date.now()};
    setCloud('서버에 저장됨 · ' + (j.saved||''));
    renderModelList();
  }catch(e){ setCloud('저장 실패 — 통신 오류', 'failed'); }
  finally{
    libSaving = false;
    if(libPending){ libPending = false; pushSaveLib(); }
  }
}

function pushSave(){
  if(HOSTED){ pushToHost(); return; }
  if(USE_LIB){ pushSaveLib(); return; }
  if(!curId) return;
  const m = models[curId] || (models[curId] = {name:'새 건물'});
  m.name     = modelName.value.trim() || '이름 없는 건물';
  m.map      = serializeMap();
  m.scenario = { people:+rPeople.value, spread:+rSpread.value, speed:+rSpeed.value,
                 mix:{...MIX}, mixSel:(typeof selMix!=='undefined'&&selMix)?selMix.value:'adult' };
  m.stats    = { floors:FLOORS, basements:BASEMENTS, area:diag.area,
                 travel:diag.travel, isolated:diag.isolated,
                 exits:diag.exits, stairs:diag.stairs };
  m.updated  = Date.now();
  if(writeStore()){
    setCloud('저장됨 · ' + new Date().toLocaleTimeString('ko-KR',{hour:'2-digit',minute:'2-digit'}));
  }else{
    setCloud('저장 실패 — 브라우저 저장 공간이 가득 찼습니다','failed');
  }
  renderModelList();
}
let dirtyFlag = false;

function autoSave(){
  clearTimeout(saveTimer);
  dirtyFlag = true;
  setCloud('변경됨','dirty');
  saveTimer = setTimeout(()=>{ dirtyFlag=false; pushSave(); }, 700);
}

/* ── 저장 전에 페이지를 떠나면 그리던 내용이 사라진다 ──
   대기 중인 저장을 즉시 반영하고, 그래도 남아 있으면 경고한다. */
function flushSave(){
  if(!dirtyFlag) return;
  clearTimeout(saveTimer);
  dirtyFlag = false;
  try{ pushSave(); }catch(e){}
}
/* 탭 전환·최소화 시점에 미리 저장 (모바일·브라우저 종료 대비) */
document.addEventListener('visibilitychange', ()=>{
  if(document.visibilityState==='hidden') flushSave();
});
window.addEventListener('pagehide', flushSave);
window.addEventListener('beforeunload', e=>{
  if(!dirtyFlag) return;
  flushSave();
  e.preventDefault();
  e.returnValue = '';       // 저장이 끝나기 전입니다. 나가시겠습니까?
  return '';
});

/* 모델 열기 */
async function openModel(id){
  const m = models[id];
  if(!m) return;
  /* 보관함 목록은 요약만 있으므로 도면 본문을 가져온다 */
  if(USE_LIB && m.stub){
    setCloud('불러오는 중…');
    try{
      const full = await libGet(id);
      m.map = full.map || ''; m.scenario = full.scenario || {}; m.stub = false;
      setCloud('');
    }catch(e){ setCloud('불러오기 실패 — ' + e.message, 'failed'); return; }
  }
  curId = id;
  try{ localStorage.setItem(CUR_KEY, id); }catch(e){}
  modelName.value = m.name || '이름 없는 건물';
  customMapText = (m.map && !parseMapText(m.map)) ? m.map : null;
  if(m.scenario){
    if(m.scenario.people){ rPeople.value=m.scenario.people; oPeople.textContent=m.scenario.people+'명'; }
    if(m.scenario.spread){ rSpread.value=m.scenario.spread; oSpread.textContent='×'+(+m.scenario.spread).toFixed(1); }
    if(m.scenario.speed ){ rSpeed.value =m.scenario.speed;  oSpeed.textContent ='×'+(+m.scenario.speed ).toFixed(1); }
    if(m.scenario.mix){ try{ setMix(m.scenario.mix, m.scenario.mixSel||'custom'); }catch(e){} }
  }
  viewF = 0; ISO.zoom = 1;
  reset();
  if(editMode){ mapTextEl.value = serializeMap(); runDiagnostics(); renderStairCfg(); }
  setCloud('저장됨');
  renderModelList();
  closeModels();
}

/* 입력받은 가로·세로(m)로 기본 평면을 생성한다.
   배치 방식은 buildDefaultGrids()와 같고, 치수만 비율로 맞춘다. */
function buildSizedGrids(wM, hM, floors){
  GW = wM; GH = hM; FLOORS = Math.max(1, floors|0);

  const mk = () => Array.from({length:GH}, () => new Array(GW).fill(FLOOR));
  const g  = mk();

  // 외벽
  for(let x=0;x<GW;x++){ g[0][x]=WALL; g[GH-1][x]=WALL; }
  for(let y=0;y<GH;y++){ g[y][0]=WALL; g[y][GW-1]=WALL; }

  // 중앙 복도 (폭 2~4 m)
  const corW = Math.max(2, Math.min(4, Math.round(GH*0.09)));
  const CT = Math.floor((GH - corW)/2);
  const CB = CT + corW - 1;
  for(let x=1;x<GW-1;x++){ g[CT-1][x]=WALL; g[CB+1][x]=WALL; }

  // 계단실 — 복도 벽에 바로 붙여 넣는다(사이에 벽이 끼면 고립된다)
  const sw = Math.max(3, Math.min(6, Math.round(GW*0.10)));
  const maxUp = CT-2, maxDn = (GH-1)-(CB+1)-1;
  const shBase = Math.max(3, Math.min(12, Math.round(GH*0.22)));
  const shUp = Math.min(shBase, maxUp), shDn = Math.min(shBase, maxDn);
  const stairs = [];
  if(shDn >= 3) stairs.push({x0:2,         y0:CB+2,      y1:CB+1+shDn, doorY:CB+1, below:true});
  if(shUp >= 3) stairs.push({x0:GW-2-sw,   y0:CT-1-shUp, y1:CT-2,      doorY:CT-1, below:false});

  for(const st of stairs){
    const x1 = Math.min(GW-2, st.x0+sw-1);
    if(st.x0 < 1 || st.y0 < 1 || st.y1 > GH-2) { st._skip = true; continue; }
    for(let y=st.y0;y<=st.y1;y++) for(let x=st.x0;x<=x1;x++) g[y][x]=STAIR;
    // 바깥쪽 면만 벽으로 (복도쪽은 문이 난다)
    const outer = st.below ? st.y1+1 : st.y0-1;
    for(let x=st.x0-1;x<=x1+1;x++){
      if(x<1||x>=GW-1) continue;
      if(outer>0 && outer<GH-1) g[outer][x]=WALL;
    }
    for(let y=st.y0;y<=st.y1;y++){
      if(st.x0-1>0)  g[y][st.x0-1]=WALL;
      if(x1+1<GW-1)  g[y][x1+1]=WALL;
    }
    const dx = st.x0+1;                       // 복도쪽 출입문 2 m
    g[st.doorY][dx]=FLOOR; g[st.doorY][dx+1]=FLOOR;
    st._x1 = x1;
  }

  // 실 구획 — 계단실이 차지한 열은 비한다
  const bays  = Math.max(2, Math.round(GW/10));
  const step  = GW / bays;
  const PARTS = [];
  for(let i=1;i<bays;i++){
    const px = Math.round(i*step);
    if(px<=2 || px>=GW-3) continue;
    let hit=false;
    for(const st of stairs){ if(!st._skip && px>=st.x0-1 && px<=st._x1+1){ hit=true; break; } }
    if(!hit) PARTS.push(px);
  }
  for(const px of PARTS){
    for(let y=1;y<CT-1;y++)    if(g[y][px]===FLOOR) g[y][px]=WALL;
    for(let y=CB+2;y<GH-1;y++) if(g[y][px]===FLOOR) g[y][px]=WALL;
  }

  // 실 출입문 — 구획 사이 중앙, 막힐 경우 옆으로 밀어서 뚫는다
  const openDoor = (row, fromX, toX) => {
    const mid = Math.round((fromX+toX)/2);
    for(let off=0; off<=Math.floor((toX-fromX)/2); off++){
      for(const m of [mid+off, mid-off]){
        if(m<=0 || m>=GW-1) continue;
        const inner = (row===CT-1) ? row-1 : row+1;
        if(inner<1 || inner>GH-2) continue;
        if(g[inner][m]===FLOOR){ g[row][m]=FLOOR; return true; }
      }
    }
    return false;
  };
  const edges=[0,...PARTS,GW-1];
  for(let i=0;i<edges.length-1;i++){
    openDoor(CT-1, edges[i], edges[i+1]);
    openDoor(CB+1, edges[i], edges[i+1]);
  }

  // 1층 피난구 — 복도 양 끝 + 남측 중앙(막혀 있으면 생략)
  for(let y=CT;y<=CB;y++){ g[y][0]=EXIT; g[y][GW-1]=EXIT; }
  const cx = Math.floor(GW/2);
  let southOK = true;
  for(let x=cx-1;x<=cx+1;x++) if(g[GH-2][x]===WALL){ southOK=false; break; }
  if(southOK){
    for(let x=cx-1;x<=cx+1;x++) g[GH-1][x]=EXIT;
    for(let y=CB+1;y<GH-1;y++) for(let x=cx-1;x<=cx+1;x++)
      if(g[y][x]!==STAIR) g[y][x]=FLOOR;
  }

  repairConnectivity(g);

  // 위층은 같은 평면, 지상 비상구는 벽으로
  const upper = g.map(r=>r.map(c=>c===EXIT?WALL:c));
  grids = [g];
  for(let f=1; f<FLOORS; f++) grids.push(upper.map(r=>r.slice()));
  stairCfg = {};
}

/* 출구에서 닿지 않는 구역이 남으면 벽을 뚫어 연결한다. */
function repairConnectivity(g){
  const H=g.length, W=g[0].length;
  const reach = () => {
    const seen = Array.from({length:H}, () => new Array(W).fill(false));
    const q=[];
    for(let y=0;y<H;y++) for(let x=0;x<W;x++)
      if(g[y][x]===EXIT){ q.push([y,x]); seen[y][x]=true; }
    while(q.length){
      const [y,x]=q.pop();
      for(const [dy,dx] of [[1,0],[-1,0],[0,1],[0,-1]]){
        const ny=y+dy, nx=x+dx;
        if(ny<0||nx<0||ny>=H||nx>=W||seen[ny][nx]) continue;
        if(g[ny][nx]===WALL) continue;
        seen[ny][nx]=true; q.push([ny,nx]);
      }
    }
    return seen;
  };

  for(let pass=0; pass<40; pass++){
    const seen = reach();
    let target=null;
    for(let y=1;y<H-1 && !target;y++) for(let x=1;x<W-1;x++){
      if((g[y][x]===FLOOR||g[y][x]===STAIR||g[y][x]===DOOR) && !seen[y][x]){ target=[y,x]; break; }
    }
    if(!target) return;                       // 모두 연결됨

    /* 고립 구역에서 바깥과 밀접한 벽 1칸을 찾아 뚫는다 */
    const q=[target], vis=new Set([target[0]+','+target[1]]);
    let opened=false;
    while(q.length && !opened){
      const [y,x]=q.shift();
      for(const [dy,dx] of [[1,0],[-1,0],[0,1],[0,-1]]){
        const wy=y+dy, wx=x+dx, oy=y+dy*2, ox=x+dx*2;
        if(wy<1||wx<1||wy>H-2||wx>W-2) continue;
        if(g[wy][wx]===WALL && oy>=0 && ox>=0 && oy<H && ox<W &&
           g[oy][ox]!==WALL && seen[oy][ox]){
          g[wy][wx]=FLOOR; opened=true; break;
        }
        const k=wy+','+wx;
        if(g[wy][wx]!==WALL && !vis.has(k) && !seen[wy][wx]){ vis.add(k); q.push([wy,wx]); }
      }
    }
    if(!opened) return;                       // 더 뚫을 곳이 없으면 중단
  }
}

/* 새 모델 — 현재 도면을 그대로 새 이름으로 시작 */
function newModel(fromCurrent){
  const nm = prompt('새 모델 이름', fromCurrent ? (modelName.value+' 사본') : '새 건물');
  if(nm===null) return;

  /* 새로 그릴 때만 건물 크기를 묻는다 (사본은 기존 도면 유지) */
  let sized = null;
  if(!fromCurrent){
    const wIn = prompt('건물 가로 길이 (m)', String(GW));
    if(wIn===null) return;
    const hIn = prompt('건물 세로 길이 (m)', String(GH));
    if(hIn===null) return;

    let w = Math.round(parseFloat(wIn)), h = Math.round(parseFloat(hIn));
    if(!isFinite(w) || !isFinite(h) || w<12 || h<12){
      alert('가로·세로를 12 m 이상으로 입력하세요.');
      return;
    }
    if(w>MAX_W || h>MAX_H){ alert('한 변은 '+MAX_W+' m까지 지원합니다.'); return; }

    const cells = w*h*Math.max(1,FLOORS);
    if(cells > 40000 &&
       !confirm('셀이 ' + cells.toLocaleString() + '개로 많아 느려질 수 있습니다.\n계속할까요?')) return;

    sized = {w, h};
  }

  /* 서버 보관함을 쓸 때는 저장 전까지 임시 항목 1개만 유지 */
  if(USE_LIB && models['new']) delete models['new'];
  const id = USE_LIB ? 'new' : newId();

  if(sized){
    buildSizedGrids(sized.w, sized.h, FLOORS);
    customMapText = serializeMap();
    viewF = 0;
    reset();
    setCloud(sized.w + ' m × ' + sized.h + ' m 로 생성됨');
  }

  models[id] = {
    name: nm.trim() || '새 건물',
    map:  (fromCurrent || sized) ? serializeMap() : '',
    scenario: { people:+rPeople.value, spread:+rSpread.value, speed:+rSpeed.value,
                mix:{...MIX}, mixSel:(typeof selMix!=='undefined'&&selMix)?selMix.value:'adult' },
    updated: Date.now(),
  };
  curId = id;
  try{ localStorage.setItem(CUR_KEY, id); }catch(e){}
  modelName.value = models[id].name;
  if(!fromCurrent && !sized){ customMapText = null; viewF = 0; reset(); }
  if(editMode){ mapTextEl.value = serializeMap(); runDiagnostics(); renderStairCfg(); }
  pushSave();
  closeModels();
}

async function deleteModel(id){
  const m = models[id]; if(!m) return;
  if(!confirm('"' + m.name + '" 을(를) 삭제할까요?\n되돌릴 수 없습니다.')) return;
  if(USE_LIB){
    try{
      const j = await libPost({act:'delete', id:id});
      if(!j.ok){ alert(j.error || '삭제하지 못했습니다.'); return; }
    }catch(e){ alert('통신 오류로 삭제하지 못했습니다.'); return; }
  }
  delete models[id];
  if(curId === id){
    const rest = Object.keys(models);
    if(rest.length){ writeStore(); openModel(rest[0]); return; }
    curId = null;
    try{ localStorage.removeItem(CUR_KEY); }catch(e){}
    customMapText = null; modelName.value = '새 건물'; reset();
    newModel(false);
    return;
  }
  writeStore(); renderModelList();
}

function renameModel(id){
  const m = models[id]; if(!m) return;
  const nm = prompt('모델 이름', m.name);
  if(nm===null) return;
  m.name = nm.trim() || '이름 없는 건물';
  m.updated = Date.now();
  if(id === curId) modelName.value = m.name;
  writeStore(); renderModelList();
}

/* 보관함 화면 */
function fmtTime(t){
  if(!t) return '';
  const d = (Date.now()-t)/1000;
  if(d<60) return '방금 전';
  if(d<3600) return Math.floor(d/60)+'분 전';
  if(d<86400) return Math.floor(d/3600)+'시간 전';
  return new Date(t).toLocaleDateString('ko-KR',{year:'2-digit',month:'2-digit',day:'2-digit'});
}
/* ── 모델 목록 ──
   검색·정렬·다중선택 삭제를 지원한다. */
let mpQuery = '', mpSortBy = 'recent';
const mpPicked = new Set();

function mpVisibleIds(){
  const q = mpQuery.trim().toLowerCase();
  let ids = Object.keys(models);
  if(q) ids = ids.filter(id => String(models[id].name||'').toLowerCase().includes(q));
  ids.sort((a,b)=>{
    const A=models[a], B=models[b];
    if(mpSortBy==='name') return String(A.name||'').localeCompare(String(B.name||''),'ko');
    if(mpSortBy==='area') return ((B.stats&&B.stats.area)||0)-((A.stats&&A.stats.area)||0);
    return (B.updated||0)-(A.updated||0);
  });
  return ids;
}

function mpSyncBulk(){
  const bulk=document.getElementById('mpBulk');
  const sel =document.getElementById('mpSel');
  const all =document.getElementById('mpAll');
  if(!bulk) return;
  bulk.hidden = mpPicked.size===0;
  if(sel) sel.textContent = mpPicked.size + '개 선택';
  const vis=mpVisibleIds();
  if(all) all.checked = vis.length>0 && vis.every(id=>mpPicked.has(id));
}

function renderModelList(){
  const host = document.getElementById('modelList');
  if(!host) return;

  const all = Object.keys(models);
  const ids = mpVisibleIds();

  const cnt=document.getElementById('mpCount');
  if(cnt) cnt.textContent = all.length ? '('+all.length+')' : '';

  if(!all.length){
    host.innerHTML = '<p class="mp-empty">저장된 모델이 없습니다.</p>';
    mpSyncBulk(); return;
  }
  if(!ids.length){
    host.innerHTML = '<p class="mp-empty">검색 결과가 없습니다.</p>';
    mpSyncBulk(); return;
  }

  host.innerHTML = ids.map(id=>{
    const m = models[id], st = m.stats || {};
    const fl = st.floors ? ((st.basements?'지하'+st.basements+'/':'') + '지상'+(st.floors-(st.basements||0))+'층') : '';
    const meta = [fl, st.area?st.area+'㎡':'', st.travel!=null?'보행 '+st.travel+'m':'']
                   .filter(Boolean).join(' · ');
    return '<div class="mrow'+(id===curId?' on':'')+(mpPicked.has(id)?' sel':'')+'" data-id="'+id+'">'+
      '<label class="mck"><input type="checkbox" data-a="pick"'+(mpPicked.has(id)?' checked':'')+'></label>'+
      '<button class="mopen" data-a="open">'+
        '<span class="mn">'+esc(m.name)+'</span>'+
        '<span class="mm">'+meta+'</span>'+
        '<span class="mt">'+fmtTime(m.updated)+'</span>'+
      '</button>'+
      '<div class="macts">'+
        '<button data-a="rename" title="이름 바꾸기">이름</button>'+
        '<button data-a="del" title="삭제">삭제</button>'+
      '</div></div>';
  }).join('');

  host.querySelectorAll('.mrow').forEach(row=>{
    const id = row.dataset.id;
    row.querySelector('[data-a="open"]').onclick   = ()=>openModel(id);
    row.querySelector('[data-a="rename"]').onclick = ()=>renameModel(id);
    row.querySelector('[data-a="del"]').onclick    = ()=>deleteModel(id);
    const ck = row.querySelector('[data-a="pick"]');
    ck.onclick = e=>{
      e.stopPropagation();
      ck.checked ? mpPicked.add(id) : mpPicked.delete(id);
      row.classList.toggle('sel', ck.checked);
      mpSyncBulk();
    };
  });
  mpSyncBulk();
}

/* 저장 위치 안내는 실제 동작에 맞춘다 */
(function(){
  const el=document.getElementById('storeNote'); if(!el) return;
  el.innerHTML = USE_LIB
    ? '모델은 <b>서버 보관함</b>에 저장되어 어느 기기에서나 열 수 있습니다.'
    : '모델은 <b>이 브라우저</b>에만 저장됩니다. 다른 기기로 옮기려면 <b>도면 파일</b>로 내려받아 <b>불러오기</b> 하세요.';
})();

/* 검색·정렬·일괄삭제 */
(function(){
  const q=document.getElementById('mpSearch'), so=document.getElementById('mpSort');
  if(q) q.addEventListener('input', ()=>{ mpQuery=q.value; renderModelList(); });
  if(so) so.addEventListener('change', ()=>{ mpSortBy=so.value; renderModelList(); });

  const all=document.getElementById('mpAll');
  if(all) all.addEventListener('change', ()=>{
    const vis=mpVisibleIds();
    all.checked ? vis.forEach(id=>mpPicked.add(id)) : vis.forEach(id=>mpPicked.delete(id));
    renderModelList();
  });

  const cancel=document.getElementById('mpCancel');
  if(cancel) cancel.addEventListener('click', ()=>{ mpPicked.clear(); renderModelList(); });

  const del=document.getElementById('mpDel');
  if(del) del.addEventListener('click', async ()=>{
    const list=[...mpPicked];
    if(!list.length) return;
    const names=list.slice(0,5).map(id=>models[id] ? models[id].name : id).join('\n· ');
    if(!confirm('모델 '+list.length+'개를 삭제합니다.\n· '+names
        +(list.length>5?'\n… 외 '+(list.length-5)+'개':'')
        +'\n\n되돌릴 수 없습니다. 계속할까요?')) return;

    let ok=0; const fail=[];
    for(const id of list){
      if(USE_LIB){
        try{
          const j=await libPost({act:'delete', id:id});
          if(!j.ok){ fail.push((models[id]?models[id].name:id)+' — '+(j.error||'')); continue; }
        }catch(e){ fail.push((models[id]?models[id].name:id)+' — 통신 오류'); continue; }
      }
      delete models[id];
      if(curId===id) curId=null;
      ok++;
    }
    mpPicked.clear();
    if(!USE_LIB) writeStore();
    renderModelList();
    if(fail.length) alert('일부를 지우지 못했습니다:\n· '+fail.join('\n· '));
    setCloud(ok+'개 삭제됨');
  });
})();
function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function openModels(){ modelPanel.hidden=false; renderModelList(); }
function closeModels(){ modelPanel.hidden=true; }
btnModels.onclick = ()=> modelPanel.hidden ? openModels() : closeModels();
btnNewModel.onclick  = ()=> newModel(false);
btnCopyModel.onclick = ()=> newModel(true);
btnCloseModels.onclick = closeModels;
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModels(); });

/* 이름 입력 → 저장 */
modelName.addEventListener('input', autoSave);

/* 도면 내려받기 / 불러오기 (백업·공유용) */
function dl(blob, filename){
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob); a.download=filename; a.click();
  setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
}
/* 도면 텍스트 */
btnExport.onclick = ()=>
  dl(new Blob([serializeMap()],{type:'text/plain;charset=utf-8'}),
     (modelName.value.trim()||'모델')+'.txt');

/* 지금 화면 그대로 이미지로 */
btnPng.onclick = ()=>{
  render();
  cv.toBlob(b=>{
    if(!b){ alert('이미지를 만들 수 없습니다.'); return; }
    dl(b, (modelName.value.trim()||'모델')+(viewMode==='iso'?'_3D':'_평면')+'.png');
  }, 'image/png');
};

/* 빌딩매니저에 넣을 열람용 주소 — 도면을 압축해 URL 에 담는다 */
function embedUrl(){
  const txt = serializeMap();
  let payload;
  if(window.CompressionStream){
    payload = null;            // 아래 async 경로에서 처리
  }
  const b64 = btoa(unescape(encodeURIComponent(txt)))
                .replace(/\+/g,'-').replace(/\//g,'_');
  const base = location.pathname;
  return base + '?embed=1&n=' + encodeURIComponent(modelName.value.trim()||'') +
         '&m=' + b64;
}
btnShare.onclick = async ()=>{
  const url = embedUrl();
  const abs = location.origin + url;
  try{
    await navigator.clipboard.writeText(abs);
    shareOut.textContent = '주소를 복사했습니다 (' + abs.length + '자)';
  }catch(e){
    shareOut.textContent = abs;
  }
  if(abs.length > 7000)
    shareOut.textContent += ' — 너무 길면 도면을 파일로 저장해 서버에 두고 include 하세요.';
};
btnImport.onclick = ()=> fileInput.click();
fileInput.onchange = async ()=>{
  const f = fileInput.files[0]; if(!f) return;
  const txt = await f.text();
  const err = parseMapText(txt);
  if(err){ alert('도면을 읽을 수 없습니다: '+err); fileInput.value=''; return; }
  const id = newId();
  models[id] = { name: f.name.replace(/\.txt$/,''), map: txt, updated: Date.now() };
  writeStore(); openModel(id);
  fileInput.value='';
};


function afterEdit(){
  computeExitLabels(); gridVer++; stairCache.ver=-1;
  if(mapTextEl) mapTextEl.value=serializeMap();
  runDiagnostics();
  renderStairCfg();
  updateBldgInfo();
  /* 잠긴 문을 덮어쓰려 한 경우 한 번만 알려준다 */
  if(doorBlocked>0){
    setCloud('문 '+doorBlocked+'칸은 잠겨 있어 그대로 두었습니다','dirty');
    doorBlocked=0;
  }
  autoSave();
}

/* 문 위치 잠금 토글 */
(function(){
  const b=document.getElementById('btnDoorLock'); if(!b) return;
  const sync=()=>{
    b.classList.toggle('active', doorLock);
    b.textContent = doorLock ? '🔒 문 위치 잠금' : '🔓 문 잠금 해제됨';
    b.title = doorLock
      ? '문이 놓인 자리를 다른 도구로 덮어쓰지 못하게 합니다'
      : '잠금이 풀려 문 자리도 덮어쓸 수 있습니다';
  };
  b.addEventListener('click', ()=>{ doorLock=!doorLock; sync(); });
  sync();
})();

/* ---------- 칸 목록 계산 (미리보기와 적용이 같은 함수를 쓴다) ---------- */
function brushCells(x,y){
  const out=[], r=Math.floor(brushSize/2);
  for(let dy=-r;dy<=r;dy++) for(let dx=-r;dx<=r;dx++)
    if(inBounds(x+dx,y+dy)) out.push([x+dx,y+dy]);
  return out;
}
function lineCells(x0,y0,x1,y1){                       // 브레젠험 + 붓 두께
  const out=[], seen=new Set();
  let dx=Math.abs(x1-x0), dy=Math.abs(y1-y0);
  const sx=x0<x1?1:-1, sy=y0<y1?1:-1;
  let err=dx-dy, x=x0, y=y0;
  for(;;){
    for(const [cx,cy] of brushCells(x,y)){
      const k=cx+','+cy; if(!seen.has(k)){ seen.add(k); out.push([cx,cy]); }
    }
    if(x===x1&&y===y1) break;
    const e2=2*err;
    if(e2>-dy){ err-=dy; x+=sx; }
    if(e2< dx){ err+=dx; y+=sy; }
  }
  return out;
}
function rectCells(x0,y0,x1,y1){
  const out=[];
  const ax=Math.min(x0,x1), bx=Math.max(x0,x1);
  const ay=Math.min(y0,y1), by=Math.max(y0,y1);
  for(let y=ay;y<=by;y++) for(let x=ax;x<=bx;x++) if(inBounds(x,y)) out.push([x,y]);
  return out;
}
/* 채우기 — 클릭한 칸과 같은 종류로 이어진 영역 전체 */
function fillCells(x,y){
  if(!inBounds(x,y)) return [];
  const g=grids[viewF], src=g[y][x], out=[];
  const seen=Array.from({length:GH},()=>new Uint8Array(GW));
  const q=[[x,y]]; seen[y][x]=1;
  while(q.length){
    const [cx,cy]=q.pop(); out.push([cx,cy]);
    for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){
      const nx=cx+dx, ny=cy+dy;
      if(!inBounds(nx,ny)||seen[ny][nx]) continue;
      if(g[ny][nx]===src){ seen[ny][nx]=1; q.push([nx,ny]); }
    }
  }
  return out;
}

/* 드래그 중 미리보기용 칸 목록 */
let dragA=null, dragB=null, hoverCell=null;
function previewCells(){
  if(!dragA||!dragB) return null;
  const sh=activeShape();
  if(sh==='rect') return rectCells(dragA[0],dragA[1],dragB[0],dragB[1]);
  if(sh==='line') return lineCells(dragA[0],dragA[1],dragB[0],dragB[1]);
  return null;
}

/* ---------- 사각형 도구: 구획 / 계단실 ---------- */
function openDoor(g,x0,y0,x1,y1){
  /* 이 구획 둘레에 이미 사용자가 놓은 문이 있으면 자동 개구부를 만들지 않는다 */
  if(doorLock){
    for(let x=x0-1;x<=x1+1;x++){
      if(g[y0-1] && g[y0-1][x]===DOOR) return;
      if(g[y1+1] && g[y1+1][x]===DOOR) return;
    }
    for(let y=y0-1;y<=y1+1;y++){
      if(g[y] && (g[y][x0-1]===DOOR || g[y][x1+1]===DOOR)) return;
    }
  }
  const cnt=(f)=>{let s=0;f(v=>{if(v===FLOOR)s++;});return s;};
  const sides=[
    {c:()=>{let s=0;for(let x=x0;x<=x1;x++) if(g[y0-2]?.[x]===FLOOR)s++;return s},
     put:()=>{const m=Math.floor((x0+x1)/2); if(g[y0-1]){g[y0-1][m]=FLOOR; g[y0-1][m+1]=FLOOR;}}},
    {c:()=>{let s=0;for(let x=x0;x<=x1;x++) if(g[y1+2]?.[x]===FLOOR)s++;return s},
     put:()=>{const m=Math.floor((x0+x1)/2); if(g[y1+1]){g[y1+1][m]=FLOOR; g[y1+1][m+1]=FLOOR;}}},
    {c:()=>{let s=0;for(let y=y0;y<=y1;y++) if(g[y]?.[x0-2]===FLOOR)s++;return s},
     put:()=>{const m=Math.floor((y0+y1)/2); if(g[m])g[m][x0-1]=FLOOR; if(g[m+1])g[m+1][x0-1]=FLOOR;}},
    {c:()=>{let s=0;for(let y=y0;y<=y1;y++) if(g[y]?.[x1+2]===FLOOR)s++;return s},
     put:()=>{const m=Math.floor((y0+y1)/2); if(g[m])g[m][x1+1]=FLOOR; if(g[m+1])g[m+1][x1+1]=FLOOR;}},
  ];
  let best=null, bc=0;
  for(const s of sides){ const c=s.c(); if(c>bc){bc=c;best=s;} }
  if(best) best.put();
}
function buildEnclosure(g,x0,y0,x1,y1,inner){
  /* 잠긴 문은 건너뛴다 — setCell 을 거치지 않으므로 여기서 직접 확인해야 한다 */
  const put=(yy,xx,v)=>{
    if(!g[yy]) return;
    if(doorLock && g[yy][xx]===DOOR && v!==DOOR){ doorBlocked++; return; }
    g[yy][xx]=v;
  };
  for(let y=y0;y<=y1;y++) for(let x=x0;x<=x1;x++) put(y,x,inner);
  for(let x=x0-1;x<=x1+1;x++){ put(y0-1,x,WALL); put(y1+1,x,WALL); }
  for(let y=y0-1;y<=y1+1;y++){ put(y,x0-1,WALL); put(y,x1+1,WALL); }
  openDoor(g,x0,y0,x1,y1);
}

/* ---------- 적용 ---------- */
function applyEdit(){
  const sh=activeShape();

  /* 지우기 — 드래그한 사각 영역을 통째로 비운다(벽으로).
     문 잠금과 무관하게 지운다. 지우려고 고른 영역이기 때문이다. */
  if(editTool==='erase'){
    let x0=Math.min(dragA[0],dragB[0]), x1=Math.max(dragA[0],dragB[0]);
    let y0=Math.min(dragA[1],dragB[1]), y1=Math.max(dragA[1],dragB[1]);
    x0=Math.max(0,x0); y0=Math.max(0,y0); x1=Math.min(GW-1,x1); y1=Math.min(GH-1,y1);
    if(x1<x0||y1<y0) return;

    const g=grids[viewF];
    let lostExit=0;
    for(let y=y0;y<=y1;y++) for(let x=x0;x<=x1;x++){
      if(g[y][x]===EXIT) lostExit++;
    }
    if(lostExit && !confirm('선택한 영역에 비상구 '+lostExit+'칸이 포함되어 있습니다.\n함께 지울까요?')) return;

    for(let y=y0;y<=y1;y++) for(let x=x0;x<=x1;x++){
      g[y][x]=WALL;
      fires[viewF][y][x]=0; smokes[viewF][y][x]=0;
    }
    setCloud((x1-x0+1)+'×'+(y1-y0+1)+' 영역을 지웠습니다','dirty');
    afterEdit(); return;
  }

  if(editTool==='room' || editTool==='stair'){
    let x0=Math.min(dragA[0],dragB[0]), x1=Math.max(dragA[0],dragB[0]);
    let y0=Math.min(dragA[1],dragB[1]), y1=Math.max(dragA[1],dragB[1]);
    x0=Math.max(1,x0); y0=Math.max(1,y0); x1=Math.min(GW-2,x1); y1=Math.min(GH-2,y1);
    if(x1<x0||y1<y0) return;
    if(editTool==='room') buildEnclosure(grids[viewF],x0,y0,x1,y1,FLOOR);
    else for(let f=0;f<FLOORS;f++) buildEnclosure(grids[f],x0,y0,x1,y1,STAIR);
    afterEdit(); return;
  }
  const t=TOOL_CELL[editTool];
  let cells;
  if(sh==='fill')      cells=fillCells(dragB[0],dragB[1]);
  else if(sh==='rect') cells=rectCells(dragA[0],dragA[1],dragB[0],dragB[1]);
  else if(sh==='line') cells=lineCells(dragA[0],dragA[1],dragB[0],dragB[1]);
  else return;
  for(const [x,y] of cells) setCell(x,y,t);
  afterEdit();
}

/* ---------- 포인터 ---------- */
/* ============================================================
   연결선(폴리라인) — 꼭짓점을 이어서 벽선을 만든다.
     클릭      : 점 추가 (이전 점과 자동 연결)
     Shift+클릭: 직각(수평·수직)으로 스냅
     시작점 클릭 / Enter / 더블클릭 : 완료
     Esc       : 전체 취소,  Ctrl+Z : 마지막 점 취소
   ============================================================ */
let polyPts = [], polyHover = null;

/* 직전 점 기준으로 수평·수직 중 가까운 쪽에 맞춘다 */
function orthoSnap(from, to){
  const dx=Math.abs(to[0]-from[0]), dy=Math.abs(to[1]-from[1]);
  return dx>=dy ? [to[0], from[1]] : [from[0], to[1]];
}

/* 점들을 벽으로 굳힌다. close=true 면 시작점까지 잇는다 */
function polyCommit(close){
  if(polyPts.length<2){ polyPts=[]; polyHover=null; render(); return; }
  const pts = close ? [...polyPts, polyPts[0]] : polyPts;
  for(let i=0;i<pts.length-1;i++){
    for(const [x,y] of lineCells(pts[i][0],pts[i][1],pts[i+1][0],pts[i+1][1]))
      setCell(x,y,WALL);
  }
  const n=polyPts.length;
  polyPts=[]; polyHover=null;
  afterEdit();
  setCloud('연결선 '+n+'점을 벽으로 그렸습니다','dirty');
}

/* 진행 중인 선 미리보기 */
function drawPolyPreview(){
  if(editTool!=='poly' || !polyPts.length) return;
  const pts = polyHover ? [...polyPts, polyHover] : polyPts;
  ctx.save();
  ctx.strokeStyle='#5FC9F8'; ctx.lineWidth=2; ctx.lineJoin='round';
  ctx.beginPath();
  ctx.moveTo(pts[0][0]*CELL+CELL/2, pts[0][1]*CELL+CELL/2);
  for(let i=1;i<pts.length;i++) ctx.lineTo(pts[i][0]*CELL+CELL/2, pts[i][1]*CELL+CELL/2);
  ctx.stroke();

  /* 꼭짓점 */
  ctx.fillStyle='#5FC9F8';
  for(const p of polyPts){
    ctx.beginPath(); ctx.arc(p[0]*CELL+CELL/2, p[1]*CELL+CELL/2, 3.2, 0, Math.PI*2); ctx.fill();
  }
  /* 시작점은 크게 — 여기를 누르면 닫힘 */
  if(polyPts.length>2){
    ctx.strokeStyle='#00B67A'; ctx.lineWidth=2;
    ctx.beginPath(); ctx.arc(polyPts[0][0]*CELL+CELL/2, polyPts[0][1]*CELL+CELL/2, 6, 0, Math.PI*2);
    ctx.stroke();
  }
  /* 현재 구간 길이 */
  if(polyHover){
    const a=polyPts[polyPts.length-1], b=polyHover;
    const m=Math.hypot(b[0]-a[0], b[1]-a[1])*M_PER_CELL;
    ctx.fillStyle='#5FC9F8'; ctx.font='600 12px "IBM Plex Mono",monospace';
    ctx.textAlign='left'; ctx.textBaseline='bottom';
    ctx.fillText(m.toFixed(1)+' m', b[0]*CELL+8, b[1]*CELL-4);
  }
  ctx.restore();
}

cv.addEventListener('pointerdown', e=>{
  if(!editMode) return;
  const c=cellFromEvent(e);

  /* ── 연결선(폴리라인) ── 클릭할 때마다 이전 점과 이어 벽을 놓는다 */
  if(editTool==='poly'){
    let p=c;
    if(polyPts.length && e.shiftKey) p=orthoSnap(polyPts[polyPts.length-1], c);
    /* 시작점을 다시 클릭하면 닫고 종료 */
    if(polyPts.length>2 && p[0]===polyPts[0][0] && p[1]===polyPts[0][1]){
      polyCommit(true);
      return;
    }
    if(!polyPts.length) snapshot();
    polyPts.push(p);
    polyHover=null;
    return;
  }

  snapshot();
  dragA=c; dragB=c;
  const sh=activeShape();
  if(sh==='free'){
    painting=true;
    for(const [x,y] of brushCells(c[0],c[1])) setCell(x,y,TOOL_CELL[editTool]);
    afterEdit();
  }else if(sh==='fill'){
    applyEdit(); dragA=dragB=null;
  }
  cv.setPointerCapture(e.pointerId);
});
cv.addEventListener('pointermove', e=>{
  if(!editMode) return;
  if(editTool==='poly' && polyPts.length){
    let p=cellFromEvent(e);
    if(e.shiftKey) p=orthoSnap(polyPts[polyPts.length-1], p);
    polyHover=p;
  }
  hoverCell=cellFromEvent(e);
  if(painting){
    const c=hoverCell;
    for(const [x,y] of brushCells(c[0],c[1])) setCell(x,y,TOOL_CELL[editTool]);
    afterEdit();
  }else if(dragA){ dragB=hoverCell; }
});
cv.addEventListener('pointerup', ()=>{
  if(editMode && dragA && dragB && !painting && activeShape()!=='fill') applyEdit();
  dragA=dragB=null; painting=false;
});
cv.addEventListener('pointerleave', ()=>{ hoverCell=null; });

/* ---------- 층 비우기 ---------- */
function clearFloor(){
  snapshot();
  const g=grids[viewF];
  for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
    g[y][x] = (x===0||y===0||x===GW-1||y===GH-1) ? WALL : FLOOR;
  afterEdit();
}

/* 여백 잘라내기 — 바깥쪽의 빈 테두리를 제거해 격자를 줄인다.
   모든 층을 함께 보고 잘라야 층끼리 위치가 어기지 않는다. */
function cropMargins(){
  let minX=GW, minY=GH, maxX=-1, maxY=-1;
  for(let f=0;f<FLOORS;f++){
    const g=grids[f];
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      if(g[y][x]===WALL) continue;
      if(x<minX)minX=x; if(x>maxX)maxX=x;
      if(y<minY)minY=y; if(y>maxY)maxY=y;
    }
  }
  if(maxX<0){ alert('도면이 비어 있습니다.'); return; }

  const x0=Math.max(0,minX-1), y0=Math.max(0,minY-1);
  const x1=Math.min(GW-1,maxX+1), y1=Math.min(GH-1,maxY+1);
  const nw=x1-x0+1, nh=y1-y0+1;

  if(nw<5||nh<5){ alert('잘라내면 너무 작아집니다(최소 5×5).'); return; }
  if(nw===GW && nh===GH){ setCloud('잘라낼 여백이 없습니다.'); return; }
  if(!confirm(GW+' × '+GH+' m → '+nw+' × '+nh+' m 로 줄입니다.\n계속할까요?')) return;

  snapshot();
  grids = grids.map(g => {
    const ng=[];
    for(let y=y0;y<=y1;y++) ng.push(g[y].slice(x0,x1+1));
    for(let x=0;x<nw;x++){
      if(ng[0][x]!==EXIT)    ng[0][x]=WALL;
      if(ng[nh-1][x]!==EXIT) ng[nh-1][x]=WALL;
    }
    for(let y=0;y<nh;y++){
      if(ng[y][0]!==EXIT)    ng[y][0]=WALL;
      if(ng[y][nw-1]!==EXIT) ng[y][nw-1]=WALL;
    }
    return ng;
  });

  const nc={};
  for(const [k,v] of Object.entries(stairCfg||{})){
    const q=k.split(',').map(Number);
    if(q.length!==2 || !isFinite(q[0]) || !isFinite(q[1])) continue;
    const ny=q[0]-y0, nx=q[1]-x0;
    if(ny>=0 && nx>=0 && ny<nh && nx<nw) nc[ny+','+nx]=v;
  }
  stairCfg = nc;

  GW=nw; GH=nh;
  viewF = Math.min(viewF, FLOORS-1);
  customMapText = serializeMap();
  reset();
  if(editMode){ mapTextEl.value=serializeMap(); runDiagnostics(); renderStairCfg(); }
  setCloud(nw+' × '+nh+' m 로 잘라냈습니다');
  autoSave();
}

/* 층 추가/삭제: 새 층은 맨 위층을 복제 (비상구는 벽으로 변환) */
btnFlAdd.onclick=()=>{
  snapshot();
  if(FLOORS>=MAX_FLOORS){ alert('최대 '+MAX_FLOORS+'층까지 만들 수 있습니다.'); return; }
  const top=grids[FLOORS-1];
  grids.push(top.map(r=>r.map(c=>c===EXIT?WALL:c)));
  FLOORS=grids.length;
  customMapText=serializeMap();
  viewF=FLOORS-1;
  reset();
  if(editMode) mapTextEl.value=serializeMap();
  fCount.textContent=(BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
};
btnFlDel.onclick=()=>{
  snapshot();
  if(FLOORS<=1){ alert('최소 1층은 필요합니다.'); return; }
  grids.pop();
  FLOORS=grids.length;
  customMapText=serializeMap();
  viewF=Math.min(viewF,FLOORS-1);
  reset();
  if(editMode) mapTextEl.value=serializeMap();
  fCount.textContent=(BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
};

/* 지하층 — 배열 앞쪽에 넣는다. 지상 1층 평면을 복제하되 비상구는 벽으로. */
btnBaseAdd.onclick=()=>{
  if(FLOORS>=MAX_FLOORS){ alert('전체 층 수는 최대 '+MAX_FLOORS+'층입니다.'); return; }
  snapshot();
  const src0=grids[groundF()] || grids[0];
  grids.unshift(src0.map(r=>r.map(c=>c===EXIT?WALL:c)));
  FLOORS=grids.length; BASEMENTS++;
  customMapText=serializeMap();
  viewF=0; reset();
  if(editMode){ mapTextEl.value=serializeMap(); renderStairCfg(); }
  fCount.textContent=(BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
  autoSave();
};
btnBaseDel.onclick=()=>{
  if(BASEMENTS<=0){ alert('지하층이 없습니다.'); return; }
  snapshot();
  grids.shift(); FLOORS=grids.length; BASEMENTS--;
  customMapText=serializeMap();
  viewF=Math.min(viewF,FLOORS-1); reset();
  if(editMode){ mapTextEl.value=serializeMap(); renderStairCfg(); }
  fCount.textContent=(BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
  autoSave();
};

btnMapApply.onclick=()=>{
  snapshot();
  const err = parseMapText(mapTextEl.value);
  if(err){ alert('도면 오류: '+err); return; }
  customMapText = serializeMap();
  reset();
  if(editMode){ mapTextEl.value = serializeMap(); fCount.textContent=FLOORS+'층'; }
};
btnMapCopy.onclick=()=>{
  mapTextEl.select();
  navigator.clipboard?.writeText(mapTextEl.value);
};
btnMapSave.onclick=()=>{
  try{
    localStorage.setItem('fireEvacMap', serializeMap());
    btnMapSave.textContent='저장됨';
    setTimeout(()=>btnMapSave.textContent='저장',1200);
  }catch(e){ alert('저장 실패: '+e.message); }
};
btnMapDefault.onclick=()=>{
  snapshot();
  customMapText=null;
  viewF=0;
  reset();
  mapTextEl.value = serializeMap();
  fCount.textContent=(BASEMENTS?'지하 '+BASEMENTS+'층 + ':'')+'지상 '+(FLOORS-BASEMENTS)+'층';
};

/* =========================================================
   편집 지원 — 실행취소 · 피난 진단 · 층 복제 · 시작 평면
========================================================= */

/* ---------- 실행취소 / 다시실행 ---------- */
const UNDO_MAX = 60;
let undoStack=[], redoStack=[];
function snapshot(){
  undoStack.push(serializeMap());
  if(undoStack.length>UNDO_MAX) undoStack.shift();
  redoStack.length=0;
  syncUndoUI();
}
function restore(txt){
  const err=parseMapText(txt);
  if(err) return;
  customMapText=txt;
  reset();
  if(editMode) mapTextEl.value=serializeMap();
  syncUndoUI(); runDiagnostics();
}
function undo(){
  if(!undoStack.length) return;
  redoStack.push(serializeMap());
  restore(undoStack.pop());
}
function redo(){
  if(!redoStack.length) return;
  undoStack.push(serializeMap());
  restore(redoStack.pop());
}
function syncUndoUI(){
  btnUndo.disabled = !undoStack.length;
  btnRedo.disabled = !redoStack.length;
  btnUndo.style.opacity = undoStack.length?1:.4;
  btnRedo.style.opacity = redoStack.length?1:.4;
}

/* ---------- 피난 진단 ----------
   dist 는 한 칸당 비용 1 로 계산되므로 그대로 보행거리(칸)가 된다.
   건축물의 피난·방화구조 기준상 거실에서 직통계단까지 보행거리는
   일반적으로 30 m 이하(내화구조·불연재 등은 50 m)로 본다. */
const TRAVEL_LIMIT_M = 30;
const HYDRANT_LIMIT_M = 25;      // 화재안전기준: 각 부분에서 방수구까지 수평거리 25 m 이하
let diag={area:0,exits:0,stairs:0,travel:0,isolated:0};

/* 보행거리: 각 층에서 거실 → 가장 가까운 직통계단 또는 피난구까지 (수평 이동만) */
function travelDistance(){
  let worst=0;
  for(let f=0;f<FLOORS;f++){
    const d=Array.from({length:GH},()=>new Float64Array(GW).fill(Infinity));
    const q=[];
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
      const t=grids[f][y][x];
      if(t===EXIT||t===STAIR){ d[y][x]=0; q.push(x,y); }
    }
    for(let i=0;i<q.length;i+=2){
      const x=q[i], y=q[i+1], nd=d[y][x]+1;
      for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){
        const nx=x+dx, ny=y+dy;
        if(nx<0||ny<0||nx>=GW||ny>=GH) continue;
        if(grids[f][ny][nx]===WALL) continue;
        if(nd<d[ny][nx]){ d[ny][nx]=nd; q.push(nx,ny); }
      }
    }
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
      if(grids[f][y][x]===FLOOR && d[y][x]!==Infinity && d[y][x]>worst) worst=d[y][x];
  }
  return worst;
}

/* 소화전 방호거리 — 각 부분에서 가장 가까운 옥내소화전까지 (수평 이동) */
function hydrantCoverage(){
  let worst=0, count=0; const missing=[];
  for(let f=0;f<FLOORS;f++){
    const d=Array.from({length:GH},()=>new Float64Array(GW).fill(Infinity));
    const q=[];
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
      if(grids[f][y][x]===HYDRANT){ d[y][x]=0; q.push(x,y); count++; }
    if(!q.length){ missing.push(f+1); continue; }
    for(let i=0;i<q.length;i+=2){
      const x=q[i], y=q[i+1], nd=d[y][x]+1;
      for(const [dx,dy] of [[1,0],[-1,0],[0,1],[0,-1]]){
        const nx=x+dx, ny=y+dy;
        if(nx<0||ny<0||nx>=GW||ny>=GH) continue;
        if(grids[f][ny][nx]===WALL) continue;
        if(nd<d[ny][nx]){ d[ny][nx]=nd; q.push(nx,ny); }
      }
    }
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
      if(grids[f][y][x]===FLOOR && d[y][x]!==Infinity && d[y][x]>worst) worst=d[y][x];
  }
  return {count, worst, missing};
}

function runDiagnostics(){
  computeDist();
  let cells=0, iso=0;
  for(let f=0;f<FLOORS;f++) for(let y=0;y<GH;y++) for(let x=0;x<GW;x++){
    const t=grids[f][y][x];
    if(t===WALL) continue;
    cells++;
    if(dAt(f,y,x)===Infinity) iso++;
  }
  const worst=travelDistance();
  const hyd=hydrantCoverage();
  // 비상구·계단실 개소 (붙어 있는 덩어리 단위)
  let exits=0;
  for(let f=0;f<FLOORS;f++) exits+=(exitLabels[f]||[]).length;
  let stairCells=0;
  for(let f=0;f<FLOORS;f++) for(let y=0;y<GH;y++) for(let x=0;x<GW;x++)
    if(grids[f][y][x]===STAIR) stairCells++;
  const runs0=stairRuns()[0]||[];
  const stairs=runs0.length;
  const dogleg=runs0.length? runs0.every(r=>!stairGeom(r).straight) : false;

  diag={ area:+(cells*M2_PER_CELL).toFixed(1), exits, stairs, dogleg,
         travel:+(worst*M_PER_CELL).toFixed(1), isolated:iso,
         hydN:hyd.count, hydD:+(hyd.worst*M_PER_CELL).toFixed(1), hydMiss:hyd.missing };

  if(typeof dgArea!=='undefined' && dgArea){
    dgArea.textContent   = diag.area.toLocaleString()+' ㎡';
    dgExits.textContent  = diag.exits+' 개소';
    dgStairs.textContent = diag.stairs+' 개소'+(diag.dogleg?' · 꺾임':'');
    dgTravel.textContent = diag.travel+' m';
    dgTravel.className   = 'dv '+(diag.travel>TRAVEL_LIMIT_M?'bad':'good');
    dgIso.textContent    = diag.isolated ? diag.isolated+' 칸' : '없음';
    dgIso.className      = 'dv '+(diag.isolated?'bad':'good');
    if(!diag.hydN){ dgHyd.textContent='없음'; dgHyd.className='dv'; }
    else if(diag.hydMiss.length){
      dgHyd.textContent=diag.hydN+'개소 · '+diag.hydMiss.map(f=>floorShort(f-1)).join(',')+' 미배치';
      dgHyd.className='dv bad';
    }else{
      dgHyd.textContent=diag.hydN+'개소 · 최장 '+diag.hydD+' m';
      dgHyd.className='dv '+(diag.hydD>HYDRANT_LIMIT_M?'bad':'good');
    }
    dgNote.textContent   = (exits===0)
      ? '비상구가 없습니다. 비상구 도구로 외벽에 피난구를 배치하세요.'
      : (FLOORS>1 && stairCells===0)
      ? '계단실이 없어 위층에서 내려올 수 없습니다. 계단실을 배치하세요.'
      : diag.isolated
      ? '탈출 경로가 없는 구역이 있습니다. 붉게 표시된 칸을 확인하세요.'
      : (diag.travel>TRAVEL_LIMIT_M
          ? '보행거리가 '+TRAVEL_LIMIT_M+' m를 넘습니다. 계단이나 출구를 추가하세요.'
          : (diag.hydN && diag.hydMiss.length
             ? '옥내소화전이 없는 층이 있습니다 ('+diag.hydMiss.map(f=>floorLabel(f-1)).join(', ')+').'
             : diag.hydN && diag.hydD>HYDRANT_LIMIT_M
             ? '옥내소화전 방호거리가 '+HYDRANT_LIMIT_M+' m를 넘습니다. 소화전을 추가하세요.'
             : '모든 구역에서 피난 경로가 확보되어 있습니다.'));
  }
}

/* ---------- 계단 설정 목록 ---------- */
const DIR_LABEL={'+y':'↓ 아래로','-y':'↑ 위로','+x':'→ 오른쪽','-x':'← 왼쪽'};
function renderStairCfg(){
  const host=document.getElementById('stairCfgList');
  if(!host) return;
  const runs=stairRuns()[viewF]||[];
  if(!runs.length){ host.innerHTML='<p class="note">이 층에 계단실이 없습니다. 계단실 도구로 배치하세요.</p>'; return; }
  host.innerHTML=runs.map((r,i)=>{
    const c=getCfg(r), G=stairGeom(r);
    const wx=r.x1-r.x0+1, wy=r.y1-r.y0+1;
    const width=Math.min(wx,wy), span=Math.max(wx,wy);
    const canFold = width>=4 && (span-LANDING)>=3;      // 접을 수 있는 크기인가
    const wantDog = (c.type||'dogleg')==='dogleg';
    const auto=!c.dir;
    const cur=c.dir || (G.alongY?'+y':'+x');
    const btn=(d)=>'<button data-sd="'+cfgKey(r)+'|'+d+'"'+
      (!auto&&cur===d?' class="active"':'')+'>'+DIR_LABEL[d]+'</button>';
    return '<div class="scfg" data-k="'+cfgKey(r)+'">'+
      '<div class="hd"><span class="nm"><i class="badge">'+(i+1)+'</i>번 계단실</span>'+
      '<span class="sz">'+(wx*M_PER_CELL).toFixed(1)+'×'+(wy*M_PER_CELL).toFixed(1)+'m</span></div>'+
      '<div class="cur">현재 <b>'+(G.straight?'직통 1개단':'꺾임 2개단')+'</b> · '+
        G.treads.length+'단 · '+DIR_LABEL[cur].slice(2)+(auto?' (자동)':'')+
        '<br>'+(viewF<FLOORS-1 ? floorLabel(viewF)+' → '+floorLabel(viewF+1)+' 구간'
                               : floorLabel(viewF)+'은 최상층 (아래층에서 올라오는 계단)')+
        ' · 설정은 이 계단실 전 층에 적용</div>'+
      '<span class="lb">형식</span>'+
      '<div class="g2">'+
        '<button data-st="'+cfgKey(r)+'|dogleg"'+(G.straight?'':' class="active"')+
          (canFold?'':' disabled title="접을 공간이 부족합니다"')+'>꺾임 2개단</button>'+
        '<button data-st="'+cfgKey(r)+'|straight"'+(G.straight?' class="active"':'')+'>직통 1개단</button>'+
      '</div>'+
      (!canFold && wantDog
        ? '<p class="warn">계단실이 좁아 꺾을 수 없어 직통으로 그립니다. 꺾으려면 짧은 쪽 <b>2 m(4칸)</b>, 긴 쪽 <b>2.5 m(5칸)</b> 이상이어야 합니다.</p>'
        : '')+
      (G.straight ? '' :
        '<span class="lb">돌아가는 쪽 (첫 단 위치)</span>'+
        '<div class="g2">'+
          '<button data-sh="'+cfgKey(r)+'|L"'+((c.hand||'L')==='L'?' class="active"':'')+'>좌 → 좌</button>'+
          '<button data-sh="'+cfgKey(r)+'|R"'+((c.hand||'L')==='R'?' class="active"':'')+'>우 → 우</button>'+
        '</div>')+
      '<span class="lb">올라가는 방향</span>'+
      '<div class="g2">'+btn('-y')+btn('+y')+'</div>'+
      '<div class="g2" style="margin-top:5px">'+btn('-x')+btn('+x')+'</div>'+
      '<button data-sd="'+cfgKey(r)+'|auto" style="width:100%;margin-top:5px"'+
        (auto?' class="active"':'')+'>자동</button>'+
    '</div>';
  }).join('');
  // 카드에 마우스를 올리면 도면에서 해당 계단을 밝게 표시
  host.querySelectorAll('.scfg').forEach(el=>{
    el.addEventListener('pointerenter', ()=>{ hlStair=el.dataset.k; });
    el.addEventListener('pointerleave', ()=>{ if(hlStair===el.dataset.k) hlStair=null; });
  });
  host.querySelectorAll('[data-st]').forEach(b=>b.onclick=()=>{
    const [k,t]=b.dataset.st.split('|');
    snapshot(); stairCfg[k]=Object.assign({},stairCfg[k]||{},{type:t});
    afterStairCfg();
  });
  host.querySelectorAll('[data-sh]').forEach(b=>b.onclick=()=>{
    const [k,h]=b.dataset.sh.split('|');
    snapshot(); stairCfg[k]=Object.assign({},stairCfg[k]||{},{hand:h});
    afterStairCfg();
  });
  host.querySelectorAll('[data-sd]').forEach(b=>b.onclick=()=>{
    const [k,d]=b.dataset.sd.split('|');
    snapshot();
    stairCfg[k]=Object.assign({},stairCfg[k]||{});
    if(d==='auto') delete stairCfg[k].dir; else stairCfg[k].dir=d;
    afterStairCfg();
  });
}
function afterStairCfg(){
  gridVer++; stairCache.ver=-1; isoStatic.key=null;
  customMapText=serializeMap();
  if(mapTextEl) mapTextEl.value=customMapText;
  renderStairCfg(); autoSave();
}

/* ---------- 층 복제 ---------- */
function copyFloorToAll(){
  snapshot();
  const srcF=viewF;
  for(let f=0;f<FLOORS;f++){
    if(f===srcF) continue;
    grids[f]=grids[srcF].map(r=>r.map(c=> f===groundF() ? c : (c===EXIT?WALL:c)));
  }
  // 1층은 비상구를 유지해야 하므로, 원본이 1층이 아니면 1층 출구를 되살린다
  const gf=groundF();
  if(srcF!==gf){
    let has=false;
    for(let y=0;y<GH;y++) for(let x=0;x<GW;x++) if(grids[gf][y][x]===EXIT) has=true;
    if(!has){ const CT=Math.floor(GH/2);
      for(let y=CT-1;y<=CT+1;y++){ if(grids[gf][y]) { grids[gf][y][0]=EXIT; grids[gf][y][GW-1]=EXIT; } } }
  }
  customMapText=serializeMap(); reset();
  mapTextEl.value=serializeMap(); runDiagnostics(); autoSave();
}

/* ---------- 시작 평면 ---------- */
function template(kind){
  snapshot();
  if(kind==='blank'){                     // 테두리만 벽인 빈 껍데기 — 현재 층 수 유지
    const W=58,H=45, nf=Math.max(1,FLOORS);
    const mk=()=>Array.from({length:H},(_,y)=>
      Array.from({length:W},(_,x)=> (x===0||y===0||x===W-1||y===H-1) ? WALL : FLOOR));
    GW=W; GH=H; FLOORS=nf; grids=Array.from({length:nf},mk); stairCfg={};
    customMapText=serializeMap(); reset();
    if(mapTextEl) mapTextEl.value=serializeMap();
    runDiagnostics(); renderStairCfg(); autoSave();
    return;
  }
  const W=58,H=45;
  const g=Array.from({length:H},()=>new Array(W).fill(FLOOR));
  for(let x=0;x<W;x++){ g[0][x]=WALL; g[H-1][x]=WALL; }
  for(let y=0;y<H;y++){ g[y][0]=WALL; g[y][W-1]=WALL; }

  if(kind==='open'){
    // 개방형 — 칸막이 없이 코어 2개소만
    for(const c of [{x:8,y:18},{x:42,y:18}]){
      for(let y=c.y;y<=c.y+7;y++) for(let x=c.x;x<=c.x+7;x++) g[y][x]=WALL;
      for(let y=c.y+1;y<=c.y+6;y++) for(let x=c.x+1;x<=c.x+5;x++) g[y][x]=STAIR;
      g[c.y+7][c.x+2]=FLOOR; g[c.y+7][c.x+3]=FLOOR;
    }
  }else if(kind==='double'){
    // 중복도 — 양쪽에 실, 코어 2개소
    const CT=21,CB=24;
    for(let x=1;x<W-1;x++){ g[CT-1][x]=WALL; g[CB+1][x]=WALL; }
    for(const px of [10,20,29,38,48]){
      for(let y=1;y<CT-1;y++) g[y][px]=WALL;
      for(let y=CB+2;y<H-1;y++) g[y][px]=WALL;
    }
    const edges=[0,10,20,29,38,48,W-1];
    for(let i=0;i<edges.length-1;i++){
      const m=Math.round((edges[i]+edges[i+1])/2);
      g[CT-1][m]=FLOOR; g[CT-1][m+1]=FLOOR;
      g[CB+1][m]=FLOOR; g[CB+1][m+1]=FLOOR;
    }
    for(const s of [{x0:3,x1:8,y0:27,y1:40,dy:CB+1,dx:5},
                    {x0:49,x1:54,y0:4,y1:17,dy:CT-1,dx:51}]){
      for(let y=s.y0;y<=s.y1;y++) for(let x=s.x0;x<=s.x1;x++) g[y][x]=STAIR;
      for(let x=s.x0-1;x<=s.x1+1;x++){ g[s.y0-1][x]=WALL; g[s.y1+1][x]=WALL; }
      for(let y=s.y0-1;y<=s.y1+1;y++){ g[y][s.x0-1]=WALL; g[y][s.x1+1]=WALL; }
      g[s.dy][s.dx]=FLOOR; g[s.dy][s.dx+1]=FLOOR;
    }
  }else{
    // 편복도 — 북측에 계단 코어, 그 아래로 복도가 끊김 없이 관통, 남측에 실
    const COR_T=12, COR_B=15;                // 복도 y 12~15 (4 m)
    const DIV=16;                            // 복도/실 구획선
    // 계단 코어 2개소 (y 1~11) — 복도보다 위쪽에만 놓아 복도를 막지 않는다
    for(const c of [{x0:3,x1:8},{x0:49,x1:54}]){
      for(let y=2;y<=10;y++) for(let x=c.x0;x<=c.x1;x++) g[y][x]=STAIR;
      for(let x=c.x0-1;x<=c.x1+1;x++){ g[1][x]=WALL; g[11][x]=WALL; }
      for(let y=1;y<=11;y++){ g[y][c.x0-1]=WALL; g[y][c.x1+1]=WALL; }
      const m=Math.floor((c.x0+c.x1)/2);
      g[11][m]=FLOOR; g[11][m+1]=FLOOR;      // 복도로 내려오는 출입문
    }
    // 코어 사이 북측 여유 공간은 벽으로 막아 복도만 남긴다
    for(let x=1;x<GW-1;x++) if(g[COR_T-1][x]!==FLOOR) g[COR_T-1][x]=WALL;
    for(let y=1;y<COR_T-1;y++) for(let x=1;x<GW-1;x++)
      if(g[y][x]===FLOOR) g[y][x]=WALL;
    // 실 구획
    for(let x=1;x<GW-1;x++) g[DIV][x]=WALL;
    for(const px of [15,29,43]) for(let y=DIV+1;y<GH-1;y++) g[y][px]=WALL;
    for(const m of [7,22,36,50]){ g[DIV][m]=FLOOR; g[DIV][m+1]=FLOOR; }
  }

  const upper=g.map(r=>r.map(c=>c===EXIT?WALL:c));
  // 1층 피난구 — 복도가 외벽에 닿는 지점에 낸다
  for(let y=1;y<H-1;y++){
    if(g[y][1]===FLOOR)   g[y][0]=EXIT;
    if(g[y][W-2]===FLOOR) g[y][W-1]=EXIT;
  }
  GW=W; GH=H; FLOORS=2; grids=[g,upper];
  customMapText=serializeMap(); reset();
  mapTextEl.value=serializeMap(); runDiagnostics(); autoSave();
}

/* ---------- 탭 ---------- */
function showPane(edit){
  if(edit && !CAN_EDIT) return;             // 회원은 편집 탭에 들어갈 수 없다
  if(!edit && typeof planFit==='function'){ planFit(); }   // 편집을 나가면 화면에 맞춤
  tabSim.classList.toggle('active',!edit);
  tabEdit.classList.toggle('active',edit);
  paneSim.hidden=edit; paneEdit.hidden=!edit;
  setEditMode(edit);
}
tabSim.onclick =()=>showPane(false);
tabEdit.onclick=()=>showPane(true);
/* 모바일은 열람 전용: 편집 모드로 들어가지 않도록 강제 */
(function(){
  const isMobile = window.matchMedia && window.matchMedia('(max-width:820px)').matches;
  if(isMobile){
    try{ showPane(false); }catch(e){}
    tabEdit.onclick = null;
  }
})();

/* ---------- 편집 컨트롤 결선 ---------- */
btnUndo.onclick=undo;
btnRedo.onclick=redo;
btnCopyFloor.onclick=copyFloorToAll;
document.querySelectorAll('.tpl').forEach(b=>{ b.onclick=()=>template(b.dataset.t); });

/* ---------- 도구 · 방식 · 붓 ---------- */
const SHAPE_HINT={
  rect:'두 점을 끌어 사각형 영역을 한 번에 채웁니다.',
  line:'두 점을 끌어 직선으로 그립니다. 긴 벽을 한 번에 세울 때.',
  fill:'클릭한 칸과 이어진 같은 종류 영역을 통째로 바꿉니다. 방 하나를 비울 때.',
  free:'끌면서 자유롭게 칠합니다. 붓 크기를 키우면 넓게 지워집니다.',
};
function selectTool(name){
  editTool=name;
  document.querySelectorAll('[data-tool]').forEach(b=>
    b.classList.toggle('active', b.dataset.tool===name));
  syncShapeUI();
}
function selectShape(sh){
  editShape=sh;
  document.querySelectorAll('[data-shape]').forEach(b=>
    b.classList.toggle('active', b.dataset.shape===sh));
  syncShapeUI();
}
function syncShapeUI(){
  const fixed=SHAPE_FIXED[editTool];
  shapeRow.style.opacity = fixed ? .4 : 1;
  shapeRow.style.pointerEvents = fixed ? 'none' : 'auto';
  brushRow.hidden = fixed || editShape!=='free';
  shapeHint.textContent = fixed
    ? '이 도구는 드래그한 사각형에 벽을 두르고 문을 냅니다.'
    : SHAPE_HINT[editShape];
}
document.querySelectorAll('[data-tool]').forEach(b=>{ b.onclick=()=>selectTool(b.dataset.tool); });
document.querySelectorAll('[data-shape]').forEach(b=>{ b.onclick=()=>selectShape(b.dataset.shape); });
document.querySelectorAll('.brush').forEach(b=>{
  b.onclick=()=>{ brushSize=+b.dataset.b;
    document.querySelectorAll('.brush').forEach(o=>o.classList.toggle('active',o===b)); };
});
btnClearFloor.onclick=clearFloor;
btnCropMargin.onclick=cropMargins;

/* ============================================================
   밑그림 도면 — 종이 도면 사진을 깔고 그 위에 벽을 따라 그린다.
   사진은 화면에만 표시되며 도면 데이터에는 들어가지 않는다.
   ============================================================ */
const TRACE = { img:null, x:0, y:0, scale:1, rot:0, opacity:0.55,
                mode:null,            // null | 'cal' | 'move'
                calA:null, calB:null, calHover:null,
                mvFrom:null };

function traceUI(){
  const on = !!TRACE.img;
  traceCtl.hidden = !on;
  btnTraceClear.disabled = !on;
  traceHint.hidden = on;
  btnTraceCal.classList.toggle('active',  TRACE.mode==='cal');
  btnTraceMove.classList.toggle('active', TRACE.mode==='move');
}

function traceLoad(file){
  if(!file) return;
  const fr = new FileReader();
  fr.onload = () => {
    const im = new Image();
    im.onload = () => {
      TRACE.img = im;
      /* 처음엔 도면 전체가 보이도록 맞춤 */
      TRACE.scale = Math.min(GW*CELL/im.width, GH*CELL/im.height);
      TRACE.x = 0; TRACE.y = 0; TRACE.rot = 0;
      TRACE.mode = null; TRACE.calA = TRACE.calB = null;
      if(typeof traceRot!=='undefined'){ traceRot.value=0; traceRotV.textContent='0°'; }
      traceInfo.textContent = '사진을 불러왔습니다. ‘축척 맞추기’로 실제 길이를 지정하세요.';
      traceUI(); render();
    };
    im.src = fr.result;
  };
  fr.readAsDataURL(file);
}

btnTraceLoad.onclick = () => traceFile.click();
traceFile.onchange = e => { traceLoad(e.target.files[0]); e.target.value=''; };

btnTraceClear.onclick = () => {
  TRACE.img=null; TRACE.mode=null; TRACE.calA=TRACE.calB=null;
  traceUI(); render();
};

traceOp.oninput = e => { TRACE.opacity = +e.target.value; render(); };

function setRot(deg){
  /* -180~180 범위로 정규화 */
  while(deg >  180) deg -= 360;
  while(deg < -180) deg += 360;
  TRACE.rot = deg;
  traceRot.value = deg;
  traceRotV.textContent = (Math.round(deg*10)/10) + '\u00b0';
  render();
}
traceRot.oninput = e => setRot(+e.target.value);
btnRotL90.onclick = () => setRot(TRACE.rot - 90);
btnRotR90.onclick = () => setRot(TRACE.rot + 90);
btnRot0.onclick   = () => setRot(0);

btnTraceCal.onclick = () => {
  if(!TRACE.img) return;
  TRACE.mode = TRACE.mode==='cal' ? null : 'cal';
  TRACE.calA = TRACE.calB = TRACE.calHover = null;
  traceInfo.textContent = TRACE.mode==='cal'
    ? '도면에서 길이를 아는 구간의 양 끝을 차례로 클릭하세요.'
    : '축척을 맞추면 도면 크기를 자동으로 계산합니다.';
  traceUI(); render();
};

btnTraceMove.onclick = () => {
  if(!TRACE.img) return;
  TRACE.mode = TRACE.mode==='move' ? null : 'move';
  traceInfo.textContent = TRACE.mode==='move'
    ? '사진을 끌어서 도면 위치에 맞추세요.'
    : '축척을 맞추면 도면 크기를 자동으로 계산합니다.';
  traceUI(); render();
};

/* 캠버스 좌표(px) — 셀이 아니라 정밀 좌표가 필요하다 */
function tracePx(e){
  const r=cv.getBoundingClientRect();
  /* 밑그림은 여백 안쪽(도면 기준)에 그려지므로 같은 기준으로 환산한다 */
  return [(e.clientX-r.left)*(cv.width/r.width)  - PLAN_PAD,
          (e.clientY-r.top )*(cv.height/r.height) - PLAN_PAD];
}

/* 축척·이동 모드일 때는 편집 대신 밑그림을 조작한다.
   capture 단계에서 가로채 그리기 핸들러로 넘어가지 않게 막는다. */
cv.addEventListener('pointerdown', e=>{
  if(!TRACE.img || !TRACE.mode) return;
  e.stopPropagation(); e.preventDefault();
  const [px,py]=tracePx(e);

  if(TRACE.mode==='move'){ TRACE.mvFrom=[px,py,TRACE.x,TRACE.y]; return; }

  if(!TRACE.calA){ TRACE.calA=[px,py]; render(); return; }

  TRACE.calB=[px,py];
  const distPx = Math.hypot(TRACE.calB[0]-TRACE.calA[0], TRACE.calB[1]-TRACE.calA[1]);
  render();
  if(distPx < 5){ TRACE.calA=TRACE.calB=null; render(); return; }

  const ans = prompt('이 구간의 실제 길이는 몇 m 입니까?', '');
  if(ans===null){ TRACE.calA=TRACE.calB=null; render(); return; }
  const meters = parseFloat(ans);
  if(!isFinite(meters) || meters<=0){
    alert('숫자를 입력하세요.');
    TRACE.calA=TRACE.calB=null; render(); return;
  }

  /* 도면 1 m = CELL px 가 되도록 사진을 확대·축소.
     회전이 있어도 두 점 사이 거리는 변하지 않으므로 비율 k는 그대로 쓴다.
     기준점(calA)을 제자리에 두려면 중심 기준으로 확대해야 한다. */
  const want = meters * PX_PER_M;              // 이 구간의 목표 화면 길이(px)
  const k = want / distPx;
  const cx0 = TRACE.x + TRACE.img.width *TRACE.scale/2;
  const cy0 = TRACE.y + TRACE.img.height*TRACE.scale/2;
  const ncx = TRACE.calA[0] + (cx0-TRACE.calA[0])*k;   // 중심도 같은 비율로 이동
  const ncy = TRACE.calA[1] + (cy0-TRACE.calA[1])*k;
  TRACE.scale *= k;
  TRACE.x = ncx - TRACE.img.width *TRACE.scale/2;
  TRACE.y = ncy - TRACE.img.height*TRACE.scale/2;

  const wM = (TRACE.img.width  * TRACE.scale / PX_PER_M);
  const hM = (TRACE.img.height * TRACE.scale / PX_PER_M);
  traceInfo.innerHTML = '축척 적용됨 · 사진 전체가 약 <b>'
    + wM.toFixed(1) + ' × ' + hM.toFixed(1) + ' m</b> 입니다.'
    + ((wM>GW || hM>GH)
        ? '<br>현재 도면(' + GW + '×' + GH + ' m)보다 큽니다. 새 모델을 더 크게 만드세요.'
        : '');

  TRACE.mode=null; TRACE.calA=TRACE.calB=null;
  traceUI(); render();
}, true);

cv.addEventListener('pointermove', e=>{
  if(!TRACE.img || !TRACE.mode) return;
  const [px,py]=tracePx(e);
  if(TRACE.mode==='move' && TRACE.mvFrom){
    e.stopPropagation(); e.preventDefault();
    TRACE.x = TRACE.mvFrom[2] + (px-TRACE.mvFrom[0]);
    TRACE.y = TRACE.mvFrom[3] + (py-TRACE.mvFrom[1]);
    render(); return;
  }
  if(TRACE.mode==='cal' && TRACE.calA && !TRACE.calB){
    e.stopPropagation();
    TRACE.calHover=[px,py]; render();
  }
}, true);

cv.addEventListener('pointerup', e=>{
  if(TRACE.mvFrom){ e.stopPropagation(); TRACE.mvFrom=null; }
}, true);

traceUI();

/* 평면 확대·이동 — 트랙패드 핌치와 드래그로 자유롭게 움직인다.
   CSS transform 으로 처리하므로 캔버스 내부 좌표계는 그대로이고,
   클릭 지점 계산(getBoundingClientRect)도 자동으로 맞는다. */
const PZ = { z:1, x:0, y:0, min:0.4, max:8 };

function applyPlanZoom(){
  cv.style.maxWidth  = PZ.z<=1 ? '100%' : 'none';
  cv.style.maxHeight = PZ.z<=1 ? '100%' : 'none';
  cv.style.transformOrigin = '0 0';
  cv.style.transform = 'translate('+PZ.x+'px,'+PZ.y+'px) scale('+PZ.z+')';
  cv.style.cursor = PZ.panning ? 'grabbing' : '';
}

/* 화면의 한 점(sx,sy)을 기준으로 확대·축소 — 보던 자리가 그대로 유지된다 */
function zoomAt(sx, sy, factor){
  const r  = cv.getBoundingClientRect();
  const px = sx - r.left, py = sy - r.top;        // 캔버스 좌상단 기준
  const nz = Math.max(PZ.min, Math.min(PZ.max, PZ.z*factor));
  const k  = nz / PZ.z;
  PZ.x -= px*(k-1);
  PZ.y -= py*(k-1);
  PZ.z  = nz;
  applyPlanZoom();
}

function planFit(){ PZ.z=1; PZ.x=0; PZ.y=0; applyPlanZoom(); }

btnPZIn.onclick = ()=>{ const r=cv.getBoundingClientRect();
  zoomAt(r.left+r.width/2, r.top+r.height/2, 1.25); };
btnPZOut.onclick= ()=>{ const r=cv.getBoundingClientRect();
  zoomAt(r.left+r.width/2, r.top+r.height/2, 1/1.25); };
btnPZFit.onclick= planFit;

/* 트랙패드 핌치 / Ctrl+휠 → 확대·축소
   그냥 휠 / 두 손가락 스와이프 → 이동
   (브라우저 기본 확대를 막아야 UI 전체가 커지지 않는다) */
const stageEl = document.querySelector('.stage');
stageEl.addEventListener('wheel', e=>{
  if(viewMode!=='plan' && !editMode) return;     // 3D는 기존 핸들러가 처리
  e.preventDefault();
  if(e.ctrlKey || e.metaKey){
    zoomAt(e.clientX, e.clientY, e.deltaY<0 ? 1.08 : 1/1.08);
  }else if(e.shiftKey){
    PZ.x -= (e.deltaY || e.deltaX);              // Shift+휠 → 좌우 이동
    applyPlanZoom();
  }else{
    PZ.x -= e.deltaX; PZ.y -= e.deltaY;          // 휠·두 손가락 → 이동
    applyPlanZoom();
  }
}, {passive:false});

/* 스페이스 드래그 · 가운데 버튼 드래그 → 이동 */
let _space=false;
window.addEventListener('keydown', e=>{
  if(e.code==='Space' && !e.repeat &&
     !/^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName||''))){
    _space=true; cv.style.cursor='grab'; e.preventDefault();
  }
});
window.addEventListener('keyup', e=>{
  if(e.code==='Space'){ _space=false; PZ.panning=false; applyPlanZoom(); }
});

stageEl.addEventListener('pointerdown', e=>{
  if(!(_space || e.button===1)) return;
  e.preventDefault(); e.stopPropagation();
  PZ.panning=true; PZ.from=[e.clientX,e.clientY,PZ.x,PZ.y];
  applyPlanZoom();
}, true);
window.addEventListener('pointermove', e=>{
  if(!PZ.panning || !PZ.from) return;
  PZ.x = PZ.from[2] + (e.clientX-PZ.from[0]);
  PZ.y = PZ.from[3] + (e.clientY-PZ.from[1]);
  applyPlanZoom();
});
window.addEventListener('pointerup', ()=>{
  if(PZ.panning){ PZ.panning=false; PZ.from=null; applyPlanZoom(); }
});

/* ── 방향키로 도면 이동 (윈도우에서 마우스만으로 불편한 경우) ──
   Shift 를 같이 누르면 크게 이동한다. */
window.addEventListener('keydown', e=>{
  const tag = (e.target && e.target.tagName) || '';
  if(/^(INPUT|TEXTAREA|SELECT)$/.test(tag)) return;   // 글 쓰는 중이면 개입 안 함
  if(e.ctrlKey || e.metaKey || e.altKey) return;

  const step = e.shiftKey ? 120 : 40;
  let dx=0, dy=0;
  switch(e.key){
    case 'ArrowLeft':  dx =  step; break;
    case 'ArrowRight': dx = -step; break;
    case 'ArrowUp':    dy =  step; break;
    case 'ArrowDown':  dy = -step; break;
    default: return;
  }
  e.preventDefault();          // 브라우저 기본 스크롤 차단
  PZ.x += dx; PZ.y += dy;
  applyPlanZoom();
});

/* 더블클릭으로 화면에 맞춤 */
stageEl.addEventListener('dblclick', e=>{
  if(_space){ planFit(); e.preventDefault(); }
});


/* 연결선 전용 키 · 더블클릭 완료 */
cv.addEventListener('dblclick', e=>{
  if(editMode && editTool==='poly' && polyPts.length>1){
    e.preventDefault(); polyCommit(false);
  }
});
window.addEventListener('keydown', e=>{
  if(!editMode || editTool!=='poly' || !polyPts.length) return;
  const tag=(e.target&&e.target.tagName)||'';
  if(/^(INPUT|TEXTAREA|SELECT)$/.test(tag)) return;

  if(e.key==='Enter'){ e.preventDefault(); polyCommit(false); }
  else if(e.key==='Escape'){ e.preventDefault(); polyPts=[]; polyHover=null; render();
                             setCloud('연결선을 취소했습니다'); }
  else if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='z'){
    e.preventDefault(); e.stopPropagation();
    polyPts.pop(); render();            // 마지막 점만 취소
  }
}, true);

/* 단축키 */
const TOOL_KEYS={'1':'wall','2':'floor','3':'exit','4':'room','5':'stair','6':'hydrant','7':'door','8':'erase','9':'poly'};
const SHAPE_KEYS={'q':'free','w':'line','e':'rect','r':'fill'};
window.addEventListener('keydown', e=>{
  const typing = /^(INPUT|TEXTAREA)$/.test(e.target.tagName);
  if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='z' && !typing){
    e.preventDefault(); e.shiftKey ? redo() : undo(); return;
  }
  if(!editMode || typing) return;
  if(TOOL_KEYS[e.key]){ e.preventDefault(); selectTool(TOOL_KEYS[e.key]); }
  const k=e.key.toLowerCase();
  if(SHAPE_KEYS[k]){ e.preventDefault(); selectShape(SHAPE_KEYS[k]); }
  if(e.key==='Escape'){ dragA=dragB=null; painting=false; }
});

/* ---------- 시작 ---------- */
if(HOSTED){
  // TWORIX 건물 페이지 안 — 그 건물의 도면 하나만 다룬다
  if(EMBED_MAP && !parseMapText(EMBED_MAP)) customMapText = EMBED_MAP;
  if(EMBED_NAME) modelName.value = EMBED_NAME;
  try{
    const s=JSON.parse(HOST_SCN||'{}');
    if(s.people){ rPeople.value=s.people; oPeople.textContent=s.people+'명'; }
    if(s.spread){ rSpread.value=s.spread; oSpread.textContent='×'+(+s.spread).toFixed(1); }
    if(s.speed ){ rSpeed.value =s.speed;  oSpeed.textContent ='×'+(+s.speed ).toFixed(1); }
  }catch(e){}
  modelName.readOnly = true;              // 건물명은 기본정보에서 관리
  btnModels.hidden = true;                // 보관함 대신 이 건물 하나
  setCloud(EMBED_MAP ? '저장됨' : '아직 저장 안 됨');
  reset();
  requestAnimationFrame(loop);
  [rPeople,rSpread,rSpeed].forEach(el=>el.addEventListener('change', autoSave));
}else if(EMBED){
  // 열람 전용: 보관함·자동저장 없이 전달받은 도면만 띄운다
  if(EMBED_MAP && !parseMapText(EMBED_MAP)) customMapText = EMBED_MAP;
  if(EMBED_NAME) modelName.value = EMBED_NAME;
  setCloud('열람 전용');
  reset();
  requestAnimationFrame(loop);
  if(AUTO){
    // 소개용 자동 재생: 3D로 시작한다. 열람자는 [3D/2D] 버튼으로 바꿀 수 있다.
    // 자동 재생: 열리자마자 화재 시작, 끝나면 잠시 후 처음부터 반복
    setTimeout(start, 900);
    let restarting=false;
    setInterval(()=>{
      if(ended && !restarting){
        restarting=true;
        setTimeout(()=>{ reset(); start(); restarting=false; }, 3000);
      }
    }, 500);
  }
}else{
/* 보관함(서버)일 때는 목록을 받은 뒤에 판단해야 한다.
   기다리지 않으면 models 가 비어 있어 매번 '새 건물'이 만들어진다. */
Promise.resolve(loadStore()).catch(()=>{}).then(startup);

function startup(){
let _fresh = false;
const _ids = Object.keys(models);
if(curId && models[curId]){
  const m = models[curId];
  modelName.value = m.name || '새 건물';
  /* 서버 보관함은 목록에 도면 본문이 없다(stub). 마지막에 열었던 모델을 불러온다. */
  if(USE_LIB && m.stub){
    openModel(curId).catch(()=>{});
  }else if(m.map && !parseMapText(m.map)) customMapText = m.map;
  if(m.scenario){
    if(m.scenario.people){ rPeople.value=m.scenario.people; oPeople.textContent=m.scenario.people+'명'; }
    if(m.scenario.spread){ rSpread.value=m.scenario.spread; oSpread.textContent='×'+(+m.scenario.spread).toFixed(1); }
    if(m.scenario.speed ){ rSpeed.value =m.scenario.speed;  oSpeed.textContent ='×'+(+m.scenario.speed ).toFixed(1); }
    if(m.scenario.mix){ try{ setMix(m.scenario.mix, m.scenario.mixSel||'custom'); }catch(e){} }
  }
}else if(_ids.length){
  curId = _ids[0];
  const m = models[curId];
  modelName.value = m.name || '새 건물';
  if(USE_LIB && m.stub){
    openModel(curId).catch(()=>{});
  }else if(m.map && !parseMapText(m.map)) customMapText = m.map;
}else{
  /* 정말로 아무것도 없을 때만 새 건물을 만든다 */
  curId = USE_LIB ? 'new' : newId();
  models[curId] = { name:'새 건물', map:'', updated:Date.now() };
  modelName.value = '새 건물';
  _fresh = true;
}
reset();
requestAnimationFrame(loop);
[rPeople,rSpread,rSpeed].forEach(el=>el.addEventListener('change', autoSave));
renderModelList();
/* 새로 만든 경우에만 저장한다. 기존 모델을 연 것뿐이라면 저장할 이유가 없다. */
if(_fresh) pushSave();
}
}
</script>
<script>
(function(){
  const b=document.getElementById('landRun');
  if(!b) return;
  b.addEventListener('click', ()=>{
    try{
      if(typeof ended!=='undefined' && ended){ reset(); }
      start();
      b.textContent='재생 중…';
      setTimeout(()=>{ b.textContent='다시 시작'; }, 1200);
    }catch(e){}
  });
})();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
