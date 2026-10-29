<?php
// index.php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

require_once __DIR__ . '/annual_plan.php';
/* ─ 카카오 로그인 설정 ─ */
$__oauthKeys = require __DIR__.'/oauth_keys.php';
define('KAKAO_KEY', (string)($__oauthKeys['kakao_key'] ?? ''));   // 카카오 콘솔의 REST API 키
define('KAKAO_SECRET', (string)($__oauthKeys['kakao_secret'] ?? ''));   // 카카오 로그인 클라이언트 시크릿 (콘솔에서 복사한 정확한 값)
define('KAKAO_REDIRECT', 'https://www.yeohub.com/index.php?kakao_callback');

/* ─ Session/Security ─ */
/* ── 30일 로그인 유지 ── */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
alp_session_start();
if(is_file(__DIR__.'/visit_push_common.php')){require_once __DIR__.'/visit_push_common.php';try{vp_observe();}catch(Throwable $e){error_log('Visit push binding check failed');}}


/* ─ 관리자 계정 ─
   mhg1234 은 비상용 최초 계정입니다(코드에 고정). 잠길 걱정 없이 항상 로그인됩니다.
   그 외 관리자는 admin_accounts.php 에서 직접 추가·삭제합니다(data/admins.json). */
const ADMIN_USER = 'mhg1234';
const ADMIN_HASH = '$2y$10$R9E8jYxSPKAp98Urp8Lx6OdMBAwEmxvy9RysAKfe99dpYlZmc8JLK';

function admins_file(): string {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/admins.json';
}
function admins_read(): array {
  $f = admins_file();
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function admins_write(array $list): bool {
  $f = admins_file();
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
/** 아이디·비밀번호가 관리자 계정과 맞는지 확인합니다.
 *  1) 비상용 최초 계정(mhg1234) 먼저 확인
 *  2) 그 다음 admin_accounts.php 로 추가한 계정들을 확인 */
function admin_verify(string $user, string $pass): bool {
  if ($user !== '' && hash_equals($user, ADMIN_USER) && password_verify($pass, ADMIN_HASH)) return true;
  foreach (admins_read() as $a) {
    if (hash_equals((string)($a['username'] ?? ''), $user) && password_verify($pass, (string)($a['hash'] ?? ''))) return true;
  }
  return false;
}

/* ─ Helpers ─ */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true)
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool {
  // 관리자 직접 로그인 또는 카카오 로그인 사용자
  return is_admin() || !empty($_SESSION['is_user']);
}
/* 현재 로그인 사용자의 유형에 맞는 업무페이지 경로 */
function work_page(): string {
  if (is_admin()) return '/admin_manager_payouts.php';
  // 카카오 사용자인데 아직 유형을 안 골랐으면 선택 페이지로
  if (!empty($_SESSION['kakao_id']) && empty($_SESSION['role']) && empty($_SESSION['is_admin'])) {
    return '/select_role.php';
  }
  $role = $_SESSION['role'] ?? 'agency';
  return $role === 'building' ? '/FireDesk.php' : '/partner_center.php';
}
/* 카카오 사용자 유형 저장소 (kakaoId => 'agency'|'building') */
function kakao_roles_file(): string { return __DIR__ . '/data/kakao_roles.json'; }
function load_kakao_roles(): array {
  $f = kakao_roles_file();
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function get_kakao_role(string $kakaoId): string {
  $all = load_kakao_roles();
  $r = $all[$kakaoId] ?? '';
  if (is_array($r)) $r = $r['role'] ?? '';   // 새 형식(객체) 호환
  return $r === 'building' ? 'building' : ($r === 'agency' ? 'agency' : '');  // 없으면 빈 문자열
}
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function assert_csrf(string $tok): void {
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $tok)) {
    http_response_code(403); exit('CSRF 검증 실패');
  }
}

$notice = '';
$CSRF = csrf_token();

/* 알림 미확인 개수 — 종 아이콘의 빨간 점 표시에 씁니다.
   user_key.php 가 없거나 회원을 특정 못 해도 안전하게 0으로 둡니다. */
$unreadCount = 0;
if (is_logged_in()) {
  if (!function_exists('app_user_key') && is_file(__DIR__ . '/user_key.php')) {
    @require_once __DIR__ . '/user_key.php';
  }
  if (function_exists('app_user_key')) {
    $__nUid = app_user_key();
    if ($__nUid !== '') {
      $__nFile = __DIR__ . '/data/notifications/' . $__nUid . '.json';
      if (is_file($__nFile)) {
        $__nList = json_decode((string)@file_get_contents($__nFile), true);
        if (is_array($__nList)) { foreach ($__nList as $__n) { if (empty($__n['read'])) $unreadCount++; } }
      }
    }
  }
}

/* ─ 카카오 콜백 처리 ─ */
if (isset($_GET['kakao_callback'])) {
  $code  = $_GET['code'] ?? '';
  $debug = isset($_GET['debug']);   // ?kakao_callback&debug=1 로 들어오면 진단 출력

  // 토큰 요청 파라미터
  $tokenParams = [
    'grant_type'   => 'authorization_code',
    'client_id'    => KAKAO_KEY,
    'redirect_uri' => KAKAO_REDIRECT,
    'code'         => $code,
  ];
  // 클라이언트 시크릿을 콘솔에서 "사용함"으로 켰다면 아래 값을 채우세요.
  if (defined('KAKAO_SECRET') && KAKAO_SECRET !== '') {
    $tokenParams['client_secret'] = KAKAO_SECRET;
  }

  // 토큰 받기 (curl)
  $ch = curl_init('https://kauth.kakao.com/oauth/token');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tokenParams),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $res      = curl_exec($ch);
  $tokenErr = curl_error($ch);
  curl_close($ch);
  $tokenJson = json_decode($res, true);
  $token     = $tokenJson['access_token'] ?? '';

  $info = ''; $user = [];
  if ($token) {
    // 사용자 정보 받기 (curl)
    $ch = curl_init('https://kapi.kakao.com/v2/user/me');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $info = curl_exec($ch);
    curl_close($ch);
    $user = json_decode($info, true) ?: [];

    $kakaoId  = (string)($user['id'] ?? '');
    $acc = $user['kakao_account'] ?? [];
    $nickname = trim((string)(
        ($acc['profile']['nickname'] ?? '')
        ?: ($user['properties']['nickname'] ?? '')
    ));
    if ($nickname === '') $nickname = '사용자';

    if ($kakaoId) {
      vp_detach();session_regenerate_id(true);
      // 관리자로 인정할 카카오 ID 목록 (필요하면 여기에 본인 카카오 숫자 ID 추가)
      $ADMIN_KAKAO_IDS = [
        // 예) '1234567890',
      ];
      $isAdminKakao = in_array($kakaoId, $ADMIN_KAKAO_IDS, true);

      $_SESSION['is_admin']   = $isAdminKakao;      // 카카오 일반 사용자는 false
      $_SESSION['ID_OK']      = $isAdminKakao ? 1 : 0;
      $_SESSION['is_user']    = true;               // 로그인한 일반 사용자 표시
      $_SESSION['kakao_id']   = $kakaoId;
      $_SESSION['nickname']   = $nickname;
      $_SESSION['login_type'] = 'kakao';

      // 통합 관리용 사용자 키 (members.json 과 동일)
      $_SESSION['member_id'] = 'kakao_' . preg_replace('/[^0-9A-Za-z_]/', '', $kakaoId);

      /* ── 관리자가 삭제한 회원이면 완전히 초기화해서 재가입시킨다.
         admin_members.php 가 삭제 시 data/kakao_killed.json 에 기록한다.
         이 블록이 없으면 지우셔도 그대로 로그인됩니다. */
      $KILLF = __DIR__ . '/data/kakao_killed.json';
      if (file_exists($KILLF)) {
        $kjs = @file_get_contents($KILLF);
        $kar = ($kjs !== false && trim((string)$kjs) !== '') ? json_decode((string)$kjs, true) : [];
        if (is_array($kar) && isset($kar[$kakaoId])) {
          // 잔존 데이터 제거
          $mk0 = $_SESSION['member_id'];
          foreach ([__DIR__.'/data/users/m_'.$mk0,
                    __DIR__.'/data/worklog/'.$mk0,
                    __DIR__.'/data/fireplan/'.$mk0,
                    __DIR__.'/data/building/'.$mk0,
                    __DIR__.'/data/train/'.$mk0] as $dd) {
            if (is_dir($dd)) {
              $it = new RecursiveIteratorIterator(
                      new RecursiveDirectoryIterator($dd, FilesystemIterator::SKIP_DOTS),
                      RecursiveIteratorIterator::CHILD_FIRST);
              foreach ($it as $fi) { $fi->isDir() ? @rmdir($fi->getPathname()) : @unlink($fi->getPathname()); }
              @rmdir($dd);
            }
          }
          // 피난 시뮬레이션 배정도 해제 (관리자 삭제 누락 대비 안전망)
          $af = __DIR__ . '/data/evac_assign.json';
          $aj = @file_get_contents($af);
          $aa = ($aj !== false && trim((string)$aj) !== '') ? json_decode((string)$aj, true) : [];
          if (is_array($aa) && isset($aa[$mk0])) {
            unset($aa[$mk0]);
            $atf = $af . '.tmp';
            if (@file_put_contents($atf, json_encode($aa, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false) {
              @rename($atf, $af);
            }
          }

          // 키야항 해제(1회성) → 이번 로그인부터 신규 가입 흐름
          unset($kar[$kakaoId]);
          $ktf = $KILLF . '.tmp';
          if (@file_put_contents($ktf, json_encode($kar, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false) {
            @rename($ktf, $KILLF);
          }
          unset($_SESSION['role']);
          header('Location: /select_role.php'); exit;
        }
      }

      // 저장된 사용자 유형 불러오기 (없으면 빈 값 → 유형 선택 필요)
      $savedRole = get_kakao_role($kakaoId);
      if ($savedRole !== '') {
        $_SESSION['role'] = $savedRole;

        // 재방문: members.json 의 최근 로그인 갱신
        $mf = __DIR__ . '/data/members.json';
        $mj = @file_get_contents($mf);
        $ma = ($mj !== false && trim((string)$mj) !== '') ? json_decode((string)$mj, true) : [];
        if (is_array($ma)) {
          $mk = $_SESSION['member_id'];
          if (!isset($ma[$mk])) {
            // 기존 카카오 사용자가 아직 members.json 에 없으면 보정
            $ma[$mk] = [
              'userid' => $mk, 'nickname' => $nickname, 'role' => $savedRole,
              'email' => '', 'email_ok' => false, 'phone' => '', 'phone_ok' => false,
              'joined_via' => 'kakao', 'kakao_id' => $kakaoId, 'status' => 'active',
              'created' => date('Y-m-d H:i:s'), 'last_login' => date('Y-m-d H:i:s'), 'pw_hash' => '',
            ];
          } else {
            $ma[$mk]['last_login'] = date('Y-m-d H:i:s');
            $ma[$mk]['nickname']   = $nickname;
          }
          $tmpf = $mf . '.tmp';
          if (@file_put_contents($tmpf, json_encode($ma, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false) {
            @rename($tmpf, $mf);
          }
        }
      } else {
        unset($_SESSION['role']);                   // 첫 로그인: 아직 유형 없음
      }
      $notice = $nickname . '님, 환영합니다!';
    }
  }

  // 진단 모드: 카카오가 실제로 뭘 돌려줬는지 그대로 보여줌
  if ($debug) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== 카카오 로그인 진단 ===\n\n";
    echo "[1] 인가코드 code: " . ($code !== '' ? '받음 ('.substr($code,0,10).'...)' : '없음!') . "\n";
    echo "[2] curl 에러: " . ($tokenErr ?: '없음') . "\n\n";
    echo "[3] 토큰 응답(raw):\n" . $res . "\n\n";
    echo "[4] access_token: " . ($token ? '발급됨' : '★ 발급 실패 — 위 응답의 error/error_description 확인') . "\n\n";
    echo "[5] 사용자 정보 응답(raw):\n" . $info . "\n";
    exit;
  }

  alp_login();
  // 로그인 후 이동: 첫 로그인(유형 미선택)만 유형 선택 페이지로 보내고,
  // 그 외에는 메인(index.php)에 머무릅니다. 업무페이지는 상단 버튼으로 직접 들어갑니다.
  if (!empty($_SESSION['is_user'])) {
    if (is_admin()) {
      header('Location: /index.php'); exit;
    }
    if (!empty($_SESSION['role'])) {
      header('Location: /index.php'); exit;
    }
    header('Location: /select_role.php'); exit;   // 유형 미선택 → 선택 페이지
  }
  header('Location: /index.php'); exit;
}

/* ─ 관리자 직접 로그인 처리 ─ */
if (($_POST['action'] ?? '') === 'login') {
  assert_csrf((string)($_POST['csrf'] ?? ''));
  $u = trim($_POST['user'] ?? '');
  $p = (string)($_POST['pass'] ?? '');
  /* admin_login.php 에서 넘어온 요청이면, 성공/실패 모두 그 페이지 톤에 맞게 돌려보낸다.
     화이트리스트 값만 허용해 오픈 리다이렉트를 막는다. */
  $fromAdminPage = (($_POST['redirect_back'] ?? '') === '/admin_login.php?err=1');
  if (admin_verify($u, $p)) {
    vp_detach();session_regenerate_id(true);
    $_SESSION['is_admin']   = true;
    $_SESSION['ID_OK']      = 1;
    $_SESSION['login_type'] = 'admin';
    alp_login($u);
    $notice = '로그인 성공';
    if ($fromAdminPage) { header('Location: /admin_manager_payouts.php'); exit; }
  } else {
    $notice = '아이디 또는 비밀번호가 올바르지 않습니다.';
    if ($fromAdminPage) { header('Location: /admin_login.php?err=1'); exit; }
  }
}

/* ─ 로그아웃 ─ */
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
  assert_csrf((string)($_GET['csrf'] ?? ''));
  alp_forget_current();vp_detach();$_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'] ?? false, $p['httponly'] ?? true);
  }
  session_destroy();
  header('Location: /'); exit;
}

if(!empty($_SESSION['visit_push_return'])&&vp_actor(mg_members())!==''){header('Location: /visit_push_open.php?message='.rawurlencode($_SESSION['visit_push_return']));exit;}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ko">
<head>
<!-- Home-screen icon -->
<link rel="apple-touch-icon" sizes="180x180" href="/assets/app-icons/sobang-180-v1.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/app-icons/sobang-32-v1.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="apple-mobile-web-app-title" content="소방계획서">

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="naver-site-verification" content="08ff3d5a27a9c771ad785abfa862bd9f69a87ba9" />
<meta name="naver-site-verification" content="6345bf3b5d83c352d473eb9f72377eb4bbb38253" /><!-- 소방계획서.com -->
<title>소방계획서.com | 소방안전 기록관리</title>
<meta name="description" content="안전관리자는 건물정보·업무 기록·소방계획서를 직접 관리하고, 업체는 거래처 지도·방문일정·알림과 작성 문의를 한곳에서 확인합니다. PC와 모바일에서 각자의 업무를 이어가세요." />
<meta name="keywords" content="소방계획서,Yeohub,소방계획서 소방,소방안전관리,소방안전관리 플랫폼,소방안전관리자,업무수행기록,소방계획서,소방행정" />
<meta property="og:type" content="website" />
<meta property="og:title" content="소방계획서.com | 소방안전 기록관리" />
<meta property="og:description" content="소방안전관리 통합 업무 시스템. 건물정보·방문일정·업무수행기록·소방계획서를 하나의 시스템에서 관리합니다. 소방안전관리자의 직접 작성과 관리를 지원하며, 업체 연결을 통해 업무 현황과 작성 문의를 함께 확인할 수 있습니다." />
<meta property="og:url" content="https://www.xn--989ay50awvdzmk18f.com/" />
<meta property="og:site_name" content="소방계획서.com" />
<meta property="og:locale" content="ko_KR" />
<style>
:root{
  --bg:#f5f7fb; --bg2:#eef2f8; --card:#ffffff; --card2:#ffffff;
  --bd:#e3e8f0; --bd2:#d4dbe6;
  --fg:#1a2436; --mut:#7a8699; --mut2:#56627a;
  --brand:#2563eb; --brand2:#1d4ed8;
  --accent:#0891b2;
  --ok:#16a34a; --warn:#d97706; --danger:#dc2626;
  --fire:#ea580c;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{color:var(--brand2);text-decoration:none}
a:hover{color:#1e40af}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.85);backdrop-filter:blur(12px) saturate(150%);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1120px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:22px;color:var(--fg);letter-spacing:.5px}
.nav__links{display:flex;align-items:center;gap:28px;list-style:none;margin:0 auto}
.nav__links a{color:var(--fg);font-size:16px;font-weight:600;transition:.15s}
.nav__links a:hover{color:var(--brand2)}
.nav__badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:#ecfdf3;border:1px solid #bbf7d0;color:#15803d}
.nav__actions{display:flex;gap:8px;align-items:center}

/* ── 계정 아이콘 (건물관리 · 결제 · 알림 · 프로필) — 다른 페이지와 같은 모양 ── */
.nw-icons{display:flex;align-items:center;gap:6px}
.nw-icobtn{position:relative;display:flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--mut2);cursor:pointer;font-family:inherit;transition:.14s;text-decoration:none}
.nw-icobtn:hover{background:var(--bg2);border-color:var(--bd)}
.nw-icobtn svg{width:19px;height:19px}
.nw-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;
  background:#ef4444;border:1.5px solid #fff}
.nw-profile{position:relative}
.nw-avatar{width:36px;height:36px;border-radius:50%;border:0;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,var(--brand),var(--accent));color:#fff;font-size:13px;font-weight:800;
  display:flex;align-items:center;justify-content:center;transition:.14s}
.nw-avatar:hover{filter:brightness(1.06)}
.nw-avatar.admin{background:linear-gradient(135deg,#f59e0b,#ea580c)}
.nw-pop{position:absolute;top:calc(100% + 10px);right:0;width:220px;background:var(--card);
  border:1px solid var(--bd);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,38,.14);
  padding:8px;z-index:90;display:none}
.nw-pop.show{display:block}
.nw-pop__head{padding:11px 12px 12px;border-bottom:1px solid var(--bd)}
.nw-pop__name{font-size:14px;font-weight:800;color:var(--fg)}
.nw-pop__sub{font-size:11.5px;color:var(--mut);margin-top:2px}
.nw-pop__list{padding:6px 0 0}
.nw-pop__item{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;
  border:0;background:transparent;color:var(--fg);font-size:13px;font-weight:600;font-family:inherit;
  cursor:pointer;text-align:left;text-decoration:none}
.nw-pop__item:hover{background:var(--bg2)}
.nw-pop__item svg{width:16px;height:16px;color:var(--mut2);flex-shrink:0}
.nw-pop__item--danger{color:var(--danger)}
.nw-pop__item--danger svg{color:var(--danger)}
.nw-pop__div{height:1px;background:var(--bd);margin:6px 2px}
@media(max-width:680px){ .nw-pop{right:-8px} }
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:var(--card);color:var(--fg);font-size:13px;cursor:pointer;transition:.15s;text-decoration:none;font-family:inherit}
.btn:hover{border-color:var(--brand);background:#f0f5ff;color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff;font-weight:600}
.btn--primary:hover{background:var(--brand2);border-color:var(--brand2);color:#fff}
.btn--ghost{background:transparent;border-color:transparent;color:var(--mut2)}
.btn--ghost:hover{background:#eef2f8;border-color:var(--bd);color:var(--fg)}
.btn--kakao{background:#FEE500;border-color:#FEE500;color:#000;font-weight:700}
.btn--kakao:hover{background:#f5dc00;border-color:#f5dc00;color:#000}
.nickname{font-size:13px;color:var(--mut2);padding:0 4px}
.hero-wrap{position:relative;overflow:hidden;border-bottom:1px solid var(--bd);
  background:
    linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px) 0 0/100% 28px,
    linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px) 0 0/28px 100%,
    linear-gradient(180deg,#fbfcff,#eef3fb)}
.hero-wrap::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 760px 320px at 12% 0%,rgba(8,145,178,.10),transparent 70%)}
.hero{padding:72px 24px 56px;text-align:left;max-width:1120px;margin:0 auto;position:relative}
.hero__label{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:var(--card);color:var(--mut2);font-size:12px;margin-bottom:20px}
.hero__label span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.hero h1{font-size:clamp(28px,4.5vw,44px);font-weight:700;letter-spacing:-.5px;line-height:1.2;margin-bottom:14px}
.hero h1 em{font-style:normal;color:var(--accent)}
.hero__sub{color:var(--mut2);font-size:16px;max-width:520px;margin:0}
.hero__note{color:var(--mut);font-size:13px;max-width:520px;margin:12px 0 0;padding-left:12px;border-left:2px solid var(--bd2)}
.accent{color:var(--accent);font-weight:600}
.hero__cta{display:flex;gap:10px;flex-wrap:wrap}

/* 헤더 2단 레이아웃 + 홍보 슬라이드 */
.hero__grid{display:grid;grid-template-columns:1fr;gap:28px;align-items:center}
@media(min-width:900px){ .hero__grid{grid-template-columns:1fr .92fr} }
.hero__col{min-width:0}

.promo{position:relative;max-width:480px;margin-left:auto}
.promo__track{display:flex;overflow:hidden;border-radius:14px;
  scroll-behavior:smooth;scroll-snap-type:x mandatory}
.promo__slide{flex:0 0 100%;scroll-snap-align:start;text-decoration:none;
  background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:14px 14px 12px;box-shadow:0 6px 24px rgba(20,40,80,.06)}
.promo__top{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px}
.promo__tag{font-size:12.5px;font-weight:600;color:var(--fg);display:flex;align-items:center;gap:6px}
.promo__tag i{font-style:normal}
.promo__tag .dot{width:6px;height:6px;border-radius:50%;background:#e0431f;
  box-shadow:0 0 0 0 rgba(224,67,31,.5);animation:live 1.8s ease-out infinite}
@keyframes live{0%{box-shadow:0 0 0 0 rgba(224,67,31,.5)}70%{box-shadow:0 0 0 6px rgba(224,67,31,0)}100%{box-shadow:0 0 0 0 rgba(224,67,31,0)}}
.promo__meta{font-size:11px;color:var(--mut);font-family:ui-monospace,monospace}
.promo__stage{background:#0f1420;border:1px solid var(--bd);border-radius:10px;
  overflow:hidden;aspect-ratio:3/2;display:flex;align-items:center;justify-content:center}
.promo__stage img{width:100%;height:100%;object-fit:cover;display:block}
.promo__stage--rpt{background:#fff}
.promo__stage--rpt svg{width:100%;height:100%;display:block}
.promo__cap{margin:10px 2px 0;font-size:12.5px;color:var(--mut2);text-align:center}

.promo__dots{display:flex;gap:6px;justify-content:center;margin-top:12px}
.promo__dot{width:7px;height:7px;padding:0;border:0;border-radius:50%;
  background:var(--bd2);cursor:pointer;transition:.15s}
.promo__dot.is-on{background:var(--accent);width:18px;border-radius:4px}
.promo__arw{position:absolute;top:44%;transform:translateY(-50%);width:30px;height:30px;
  border-radius:50%;border:1px solid var(--bd);background:var(--card);color:var(--mut2);
  font-size:17px;line-height:1;cursor:pointer;opacity:0;transition:.15s;z-index:3}
.promo:hover .promo__arw{opacity:.95}
.promo__arw--l{left:-12px}
.promo__arw--r{right:-12px}
.promo__arw:hover{color:var(--fg);border-color:var(--bd2)}
@media(max-width:520px){ .promo__arw{display:none} }
.main{max-width:1120px;margin:0 auto;padding:8px 24px 80px}
.cta-box{text-align:left;display:flex;flex-direction:column;align-items:flex-start;gap:16px}
.cta-box__msg{color:var(--mut2);font-size:15px}
.cta-box__btns{display:flex;gap:10px;flex-wrap:wrap}
.cta-box--work{position:relative;display:grid;width:100%;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:18px;padding:18px 18px 18px 20px;border:1px solid #274c88;border-radius:16px;background:linear-gradient(125deg,#172554,#1e3a8a);box-shadow:0 12px 30px rgba(23,37,84,.2);animation:workEntryGlow 3.2s ease-in-out infinite}
.work-entry__body{min-width:0}
.cta-box--work .cta-box__eyebrow{display:flex;align-items:center;gap:7px;margin-bottom:5px;color:#93c5fd;font-size:10px;font-weight:800;letter-spacing:.06em}
.cta-box--work .cta-box__eyebrow::before{width:6px;height:6px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 3px rgba(74,222,128,.13);content:''}
.cta-box--work .cta-box__title{color:#fff;font-size:17px;font-weight:850;letter-spacing:-.03em;line-height:1.4;word-break:keep-all}
.cta-box--work .cta-box__msg{margin:4px 0 0;color:#cbd5e1;font-size:11.5px;line-height:1.55;word-break:keep-all}
.cta-box--work>.btn--primary{min-width:142px;padding:12px 17px;border-color:#fff;border-radius:9px;background:#fff;color:#1d4ed8;font-size:13.5px;font-weight:850;box-shadow:0 8px 18px rgba(3,12,35,.18);white-space:nowrap}
.cta-box--work>.btn--primary:hover{border-color:#dbeafe;background:#eff6ff;color:#1d4ed8;transform:translateY(-1px)}
.hero .cta-box--work{max-width:540px;margin-top:18px}
@keyframes workEntryGlow{0%,100%{border-color:#274c88;box-shadow:0 12px 30px rgba(23,37,84,.2),0 0 0 0 rgba(59,130,246,0)}50%{border-color:#60a5fa;box-shadow:0 14px 34px rgba(23,37,84,.25),0 0 0 4px rgba(59,130,246,.14)}}
@media(max-width:520px){.cta-box--work{grid-template-columns:1fr;gap:13px;padding:17px 16px}.cta-box--work .cta-box__title{font-size:16px}.cta-box--work>.btn--primary{width:100%}}
@media(prefers-reduced-motion:reduce){.cta-box--work{animation:none}}
.service-guide{position:relative;margin-top:72px;padding:52px 46px 42px;border:1px solid #dce5f1;border-radius:26px;overflow:hidden;background:linear-gradient(155deg,#fff 0%,#f8faff 58%,#f2f6fc 100%);box-shadow:0 24px 60px rgba(30,41,59,.07)}
.service-guide::before{content:'';position:absolute;width:360px;height:360px;border-radius:50%;right:-160px;top:-210px;background:radial-gradient(circle,rgba(37,99,235,.13),rgba(37,99,235,0) 70%);pointer-events:none}
.service-guide__head{position:relative;text-align:left;max-width:720px;margin:0 0 36px}
.service-guide__eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:10.5px;font-weight:850;color:var(--brand);letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px}
.service-guide__eyebrow::before{content:'';width:18px;height:2px;border-radius:2px;background:var(--brand)}
.service-guide h2{font-size:clamp(26px,3.6vw,38px);font-weight:850;letter-spacing:-.045em;line-height:1.25;margin-bottom:12px}
.service-guide__lead{font-size:14px;color:var(--mut2);line-height:1.8;max-width:650px}
.service-flow{position:relative;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;counter-reset:flow}
.service-flow::before{content:'';position:absolute;left:9%;right:9%;top:24px;height:1px;background:linear-gradient(90deg,#93c5fd,#cbd5e1);z-index:0}
.service-step{position:relative;z-index:1;min-width:0;padding:0 8px;counter-increment:flow;background:transparent}
.service-step:not(:last-child)::after{display:none}
.service-step__num{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border:6px solid #f8faff;border-radius:50%;background:#fff;color:var(--brand2);font-size:11px;font-weight:900;margin-bottom:17px;box-shadow:0 0 0 1px #bfd2ef,0 6px 16px rgba(30,64,175,.12)}
.service-step__num::before{content:counter(flow,decimal-leading-zero)}
.service-step h3{font-size:14px;font-weight:850;margin-bottom:7px;letter-spacing:-.015em}.service-step p{font-size:11.5px;color:var(--mut2);line-height:1.7;word-break:keep-all}
.service-guide__result{position:relative;display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:34px;padding:21px 23px 21px 25px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:linear-gradient(120deg,#172554,#1e3a8a);color:#fff;box-shadow:0 12px 30px rgba(23,37,84,.18)}
.service-guide__result b{display:block;font-size:15px;font-weight:800}.service-guide__result span{display:block;color:#bfdbfe;font-size:11.5px;margin-top:4px}
.service-guide__result .btn{background:#fff;border-color:#fff;color:#1d4ed8;white-space:nowrap;font-weight:800}.service-guide__result .btn:hover{background:#eff6ff;transform:translateY(-1px)}
.btn--admin{background:#0f766e;border-color:#0f766e;color:#fff;font-weight:600}
.btn--admin:hover{background:#0d5f59;border-color:#0d5f59;color:#fff}
.btn--lg{padding:14px 28px;font-size:15px;border-radius:11px}
.alert{padding:10px 14px;border-radius:10px;font-size:13px;margin-top:10px}
.alert--ok{background:#ecfdf3;border:1px solid #bbf7d0;color:#15803d}
.alert--warn{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c}
footer{border-top:1px solid var(--bd);padding:28px 24px;color:var(--mut);font-size:13px;line-height:1.8}
footer .inner{max-width:1120px;margin:0 auto}
footer a{color:var(--mut2)}
footer a:hover{color:var(--fg)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:200;padding:16px}
.modal{width:min(92vw,420px);max-height:90vh;overflow-y:auto;background:#ffffff;border:1px solid var(--bd2);border-radius:18px;padding:24px;box-shadow:0 24px 60px rgba(20,40,80,.18)}

/* ══════════ 로그인 팝업: 2단 레이아웃 (소방계획서.com/소방계획서 메인화면과 같은 톤) ══════════
   왼쪽 = 로그인/회원가입 폼, 오른쪽 = 제공 기능 안내.
   메인화면과 같은 전역 색상변수(--card·--brand·--bg2 등)를 그대로 씁니다.
   flex 대신 grid-template-columns 로 폭을 못 박고, min-height:0 으로 내부
   스크롤이 실제로 작동하게 했습니다(회원가입 폼이 길어도 안 잘림). */
#authModal .modal{
  position:relative;box-sizing:border-box;width:min(94vw,860px);max-width:860px;padding:0;
  background:var(--card);border:1px solid var(--bd);border-radius:20px;
  display:grid;grid-template-columns:1fr 320px;grid-template-rows:minmax(0,1fr);align-items:stretch;
  overflow:hidden;max-height:min(90vh,720px)
}
#authModal .amodal__left{
  box-sizing:border-box;min-width:0;min-height:0;padding:34px 32px;overflow-y:auto;background:var(--card)
}
#authModal .amodal__right{
  box-sizing:border-box;min-width:0;min-height:0;background:var(--bg2);border-left:1px solid var(--bd);
  padding:30px 28px;overflow-y:auto;display:flex;flex-direction:column
}
@media (max-width:720px){
  #authModal .modal{grid-template-columns:1fr;max-height:92vh}
  #authModal .amodal__right{display:none}   /* 좁은 화면에선 폼에 집중, 혜택 패널은 숨김 */
}

#authModal .modal__head{margin-bottom:22px}
#authModal .modal__head h3{font-size:20px;font-weight:800;color:var(--fg);letter-spacing:-.01em}
#authModal #closeAuth{
  position:absolute;top:16px;right:18px;background:transparent;border:0;color:var(--mut);
  font-size:18px;padding:6px;z-index:2
}
#authModal #closeAuth:hover{color:var(--fg)}

#authModal .amodal__err{color:#b91c1c;background:#fdeceb;border:1px solid #f6c7c2;
  border-radius:10px;padding:10px 13px;font-size:12.5px;margin-bottom:16px}

/* 오른쪽: 제공 기능 (정적 목록, 단색 톤) */
#authModal .amodal__right h4{color:var(--fg);font-size:16.5px;font-weight:800;line-height:1.4;
  margin-bottom:20px;letter-spacing:-.01em}
#authModal .amodal__mlabel{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;
  color:var(--mut);margin-bottom:12px;text-transform:uppercase;letter-spacing:.04em}
#authModal .amodal__mlabel .dot{width:5px;height:5px;border-radius:50%;background:var(--accent)}
#authModal .amodal__flist{display:flex;flex-direction:column;gap:3px;margin-bottom:22px}
#authModal .amodal__frow{display:flex;align-items:center;gap:11px;padding:6px 0}
#authModal .amodal__ftile{flex:0 0 auto;width:32px;height:32px;border-radius:9px;
  background:var(--card);border:1px solid var(--bd);display:flex;align-items:center;
  justify-content:center;font-size:14.5px}
#authModal .amodal__flabel{font-size:12.5px;font-weight:600;color:var(--fg)}
#authModal .amodal__check{display:flex;gap:11px;margin-bottom:16px}
#authModal .amodal__check .ico{flex-shrink:0;width:18px;height:18px;border-radius:50%;background:var(--ok);
  color:#fff;display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:900;margin-top:1px}
#authModal .amodal__check .tx b{display:block;color:var(--fg);font-size:13px;font-weight:700;margin-bottom:2px}
#authModal .amodal__check .tx span{display:block;color:var(--mut);font-size:11.5px;line-height:1.6}
#authModal .amodal__more{margin-top:auto;padding-top:16px;text-align:right}
#authModal .amodal__more a{color:var(--brand2);font-size:12.5px;font-weight:700;text-decoration:none}
#authModal .amodal__more a:hover{text-decoration:underline}
.modal__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.modal__head h3{font-size:16px;font-weight:600}
.auth-tabs{display:flex;gap:4px;margin-bottom:18px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:4px}
.auth-divider{display:flex;align-items:center;text-align:center;color:var(--mut);font-size:12px;margin:0 0 16px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:var(--bd)}
.auth-divider span{padding:0 12px}
.auth-tab{flex:1;text-align:center;padding:9px;border:0;border-radius:7px;background:transparent;color:var(--mut2);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:.15s}
.auth-tab.active{background:var(--brand);color:#fff}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.field label{font-size:12px;color:var(--mut2);font-weight:500}
/* 이메일 인증 UI */
.email-row{display:flex;gap:6px;align-items:stretch}
.email-row .inp{flex:1}
.mini-btn{padding:0 13px;white-space:nowrap;background:#eef4ff;color:var(--brand);
  border:1px solid #c7dbff;border-radius:9px;font-size:12px;font-weight:700;
  cursor:pointer;font-family:inherit;flex-shrink:0}
.mini-btn:hover:not(:disabled){background:#dbe8ff}
.mini-btn:disabled{opacity:.5;cursor:not-allowed}
.code-box{margin-top:8px}
.code-timer{font-size:11px;color:var(--mut);margin-top:5px}
.code-msg{font-size:12px;margin-top:6px;min-height:15px}
.code-msg.ok{color:#16a34a}
.code-msg.err{color:#dc2626}
/* 실시간 입력 검증 안내 */
.chk-msg{font-size:11px;color:var(--mut);margin-top:4px;min-height:14px}
.chk-msg.ok{color:#16a34a}
.chk-msg.err{color:#dc2626}
.btn:disabled{opacity:.45;cursor:not-allowed}
.roles{display:flex;flex-direction:column;gap:8px}
.role{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--fg);font-weight:500;
      background:#f8fafc;border:1px solid var(--bd2);border-radius:10px;padding:11px 13px;cursor:pointer}
.role:hover{border-color:var(--brand)}
.role input{width:auto;margin:0}
.inp{width:100%;padding:11px 13px;border-radius:10px;border:1px solid var(--bd2);background:#f8fafc;color:var(--fg);font-size:14px;outline:none;transition:.15s}
.inp:focus{border-color:var(--brand)}
.nav__toggle{display:none;background:none;border:0;cursor:pointer;padding:8px;font-size:22px;line-height:1;color:var(--fg)}
@media(max-width:680px){
  .nav__inner{padding:0 14px;gap:8px}
  .hero{padding:48px 20px 36px}
  .nav__brand{font-size:19px;flex-shrink:0}
  .nav__toggle{display:block;flex-shrink:0}
  .nav__links{
    position:absolute;top:56px;left:0;right:0;margin:0;
    flex-direction:column;align-items:stretch;gap:0;
    background:#fff;border-bottom:1px solid var(--bd);
    box-shadow:0 12px 24px rgba(20,40,80,.08);
    display:none;
  }
  .nav__links.open{display:flex}
  .nav__links li{width:100%}
  .nav__links a{display:block;padding:14px 20px;border-top:1px solid var(--bd)}
  .nav__links li:first-child a{border-top:0}

  /* 헤더 우측: 핵심 버튼만 남기고 나머지는 메뉴로 */
  .nav__actions{gap:6px;flex-shrink:0;margin-left:auto}
  .nav__actions .nickname{display:none}
  .nav__actions .btn--ghost{display:none}
  .nav__actions .btn{padding:7px 11px;font-size:12.5px;white-space:nowrap}

  .nav__links .mobile-only{display:block}
  .nav__links .m-user{padding:12px 20px;font-size:13px;color:var(--mut2);
    background:#f7f9fc;border-top:1px solid var(--bd)}
  .nav__links .m-logout a{color:#dc2626}
  .cta-box--work{padding:19px 18px}.cta-box--work .btn{width:100%;justify-content:center}
  .service-guide{margin-top:48px;padding:34px 20px 22px;border-radius:20px}.service-guide__head{margin-bottom:28px}.service-guide h2 br{display:none}.service-flow{grid-template-columns:1fr;gap:8px}.service-flow::before{left:23px;right:auto;top:20px;bottom:20px;width:1px;height:auto;background:linear-gradient(#93c5fd,#cbd5e1)}
  .service-step{padding:10px 8px 10px 62px;min-height:58px}.service-step__num{position:absolute;left:0;top:7px;margin:0;width:46px;height:46px}
  .service-step:not(:last-child)::after{display:none}.service-guide__result{align-items:flex-start;flex-direction:column}.service-guide__result .btn{width:100%;justify-content:center}
}
@media(max-width:520px){.cta-box--work{padding:17px 15px 16px}}
.nav__links .mobile-only{display:none}

.hero__note--collaboration{padding:0;border:0;margin-top:22px;max-width:520px;word-break:keep-all}
.hero__note--collaboration>strong{display:block;color:var(--fg);font-size:clamp(20px,2.2vw,24px);line-height:1.5;font-weight:700;letter-spacing:-.7px}
.hero__note--collaboration>p{margin:10px 0 0;color:var(--mut2);font-size:13px;line-height:1.85;letter-spacing:-.1px}
@media(max-width:520px){.hero__note--collaboration{margin-top:18px}.hero__note--collaboration>strong{font-size:21px}.collaboration-break{display:none}}
/* 메인 서비스 미리보기: 실제 기능의 항목과 흐름을 축약해 표시합니다. */
.promo{width:100%;min-width:0}.promo__slide{min-width:0}.promo__top{gap:8px;flex-wrap:wrap}.promo__meta{font-family:inherit}.promo__cap{min-height:3.2em;line-height:1.6;word-break:keep-all;overflow-wrap:anywhere}.promo__sample-note{text-align:center;font-size:10.5px;color:var(--mut);margin-top:5px;line-height:1.6}
.promo__dots{gap:4px;margin-top:7px}.promo__dot{width:24px;height:28px;border-radius:8px;background:transparent;display:grid;place-items:center}.promo__dot::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--bd2);transition:.15s}.promo__dot.is-on{width:24px;background:transparent}.promo__dot.is-on::before{width:18px;border-radius:4px;background:var(--accent)}
.promo__dot:focus-visible,.promo__slide:focus-visible,.promo__arw:focus-visible{outline:2px solid var(--brand);outline-offset:-2px}.promo__arw:focus-visible{opacity:1}
.home-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:30px;padding-top:26px;border-top:1px solid #dfe7f1}.home-tool{min-width:0;background:#fff;border:1px solid #e0e7f0;border-radius:13px;padding:20px}.home-tool--manager{background:#f3f8f6;border-color:#d8e8e0}.home-tool__badge{display:inline-block;background:#e9f3ee;color:#25725c;font-size:10px;font-weight:750;border-radius:5px;padding:3px 7px;margin-bottom:10px}.home-tool__badge--pro{background:#eaf1ff;color:#2563eb}.home-tool h3{font-size:15px;letter-spacing:-.3px;margin-bottom:6px}.home-tool p{font-size:12px;color:var(--mut2);line-height:1.8;word-break:keep-all;overflow-wrap:anywhere}.home-tools__note{font-size:11px;line-height:1.8;color:var(--mut2);margin-top:14px;word-break:keep-all}.home-tools__note a{white-space:nowrap;font-weight:650}
@media(max-width:680px){.home-tools{grid-template-columns:1fr;margin-top:24px;padding-top:22px}.home-tool{padding:17px}.promo__tag{font-size:12px}.promo__meta{font-size:10px}}

</style>
<link rel="stylesheet" href="/home_screen_add.css?v=20261001-1">
<script src="/home_screen_add.js?v=20261028-kakao-1" defer></script>
<style>
/* Mobile auth inputs: avoid focus zoom and horizontal overflow. */
@media(max-width:768px),(hover:none) and (pointer:coarse){
 #authModal input:not([type=hidden]):not([type=checkbox]):not([type=radio]),
 #authModal select,#authModal textarea{font-size:16px!important;box-sizing:border-box;min-width:0;max-width:100%}
 #authModal .auth-form,#authModal .field,#authModal .email-row{min-width:0;max-width:100%}
 #authModal .email-row .inp{flex:1 1 0;width:0;min-width:0}
 #authModal .mini-btn{padding-inline:10px}
 #authModal{box-sizing:border-box;padding:12px}
 #authModal .modal{max-width:100%;max-height:min(90vh,720px);max-height:min(90dvh,720px)}
 #authModal .amodal__left{overscroll-behavior:contain;overflow-x:hidden}
}
@media(max-width:720px){
 #authModal .modal{width:100%;grid-template-columns:minmax(0,1fr);max-height:calc(100vh - 24px);max-height:calc(100dvh - 24px)}
 #authModal .amodal__left{padding:28px 20px 22px}
 #authModal .roles{flex-wrap:wrap}
 #authModal .role{min-width:0;max-width:100%;box-sizing:border-box}
}
</style>
<script src="/visit_badge.js?v=20261021-1" defer></script>
<link rel="stylesheet" href="/home_product.css?v=20261026-partner-mobile-18">
<link rel="stylesheet" href="/kakao_install_guide.css?v=20261028-1"><script src="/kakao_install_guide.js?v=20261028-1" defer></script>
</head>
<body class="product-home">

<!-- NAV -->
<nav class="nav">
  <div class="nav__inner">
    <div class="nav__brand">
      <svg class="hanok-brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M9 5h12l5 5v18H9V5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M20 5v6h6M13 16h9M13 20h9M13 24h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M5 5v23" stroke="#9c5141" stroke-width="2" stroke-linecap="round"/></svg><span>소방계획서.com</span>
      <?php if (is_admin()): ?>
        <span class="nav__badge">관리자</span>
      <?php endif; ?>
    </div>
    <ul class="nav__links" id="navLinks">
      <?php if(is_admin()): ?>
      <li><a href="/admin_manager_payouts.php">파트너·출금 관리</a></li>
      <li><a href="/admin_members.php">회원 관리</a></li>
      <li><a href="/admin_accounts.php">관리자 계정</a></li>
      <?php else: ?>
      <li><a href="/faq.php">FAQ</a></li>
      <li><a href="/service.php">서비스</a></li>
      <?php endif; ?>
      <?php if (is_logged_in()): ?>
        <?php if (!empty($_SESSION['nickname'])): ?>
          <li class="mobile-only m-user"><?=h($_SESSION['nickname'])?>님으로 로그인됨</li>
        <?php endif; ?>
        <li class="mobile-only m-logout">
          <a href="/?logout=1&csrf=<?=h($CSRF)?>" onclick="return confirm('로그아웃할까요?');">로그아웃</a>
        </li>
      <?php endif; ?>
    </ul>
    <div class="nav__actions">
      <?php if (!is_logged_in()): ?>
        <button class="btn btn--primary" id="openAuth">로그인</button>
      <?php else: ?>
        <?php if (is_admin()): ?>
          <a class="btn btn--primary" href="/admin_memo.php">📝 메모</a>
        <?php endif; ?>

        <div class="nw-icons">
          <a class="nw-icobtn" href="<?=h(work_page())?>" title="<?= is_admin() ? '파트너 관리' : ((($_SESSION['role'] ?? 'agency') === 'building') ? '건물 관리' : '파트너 센터') ?>">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 8h1M11 8h1M7 12h1M11 12h1M7 16h1M11 16h1M17 14h1M17 18h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </a>
          <?php if(!is_admin()): ?>
          <a class="nw-icobtn" href="/subscribe_page.php" title="결제·구독">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </a>
          <a class="nw-icobtn" href="/notifications.php" title="알림">
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <?php if ($unreadCount > 0): ?><span class="nw-dot"></span><?php endif; ?>
          </a>
          <?php endif; ?>
          <div class="nw-profile" id="navProfile">
            <button type="button" class="nw-avatar<?= is_admin() ? ' admin' : '' ?>" id="navAvatarBtn"
              onclick="document.getElementById('navPop').classList.toggle('show')">
              <?=h(mb_substr((string)($_SESSION['nickname'] ?? '유'), 0, 1))?>
            </button>
            <div class="nw-pop" id="navPop">
              <div class="nw-pop__head">
                <div class="nw-pop__name"><?=h($_SESSION['nickname'] ?? '사용자')?>님</div>
                <div class="nw-pop__sub"><?= is_admin() ? '관리자' : ((($_SESSION['role'] ?? '') === 'building') ? '건물 소방안전관리자' : '파트너') ?></div>
              </div>
              <div class="nw-pop__list">
                <a class="nw-pop__item" href="<?=h(work_page())?>">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 8h1M11 8h1M7 12h1M11 12h1M7 16h1M11 16h1M17 14h1M17 18h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  <?= is_admin() ? '파트너 관리' : ((($_SESSION['role'] ?? 'agency') === 'building') ? '건물 관리' : '파트너 센터') ?>
                </a>
                <?php if(is_admin()): ?>
                <a class="nw-pop__item" href="/admin_members.php">회원 관리</a>
                <a class="nw-pop__item" href="/admin_accounts.php">관리자 계정 관리</a>
                <?php else: ?>
                <a class="nw-pop__item" href="/settings.php">
                  <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1-1.56 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                  내 정보
                </a>
                <a class="nw-pop__item" href="/subscribe_page.php">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/></svg>
                  결제·구독
                </a>
                <a class="nw-pop__item" href="/notifications.php">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                  알림
                </a>
                <?php endif; ?>
                <div class="nw-pop__div"></div>
                <a class="nw-pop__item nw-pop__item--danger" href="/?logout=1&csrf=<?=h($CSRF)?>"
                   onclick="return confirm('로그아웃할까요?');">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  로그아웃
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <button class="nav__toggle" id="navToggle" aria-label="메뉴 열기" aria-expanded="false">☰</button>
    </div>
  </div>
</nav>

<script>
  /* 프로필 드롭다운: 바깥을 누르면 닫힘 */
  document.addEventListener('click', function(e){
    var wrap = document.getElementById('navProfile');
    var pop  = document.getElementById('navPop');
    if (wrap && pop && !wrap.contains(e.target)) pop.classList.remove('show');
  });
</script>

<!-- HERO -->
<section class="hero-wrap">
  <div class="hero">
   <div class="hero__grid">
    <div class="hero__col">
      <div class="hero__label"><span></span>건물과 업무, 사람을 연결하는 통합 시스템</div>
                    <div class="hero__title-row"><h1><span class="home-purpose">소방계획서</span><em>소방안전관리<br><span class="hero__title-accent">통합 업무 시스템</span></em></h1></div>
                    <p class="hero__sub">건물정보·방문일정·업무수행기록·소방계획서를 하나의 시스템에서 관리합니다.<br>소방안전관리자의 직접 작성과 관리를 지원하며, 업체 연결을 통해 업무 현황과 작성 문의를 함께 확인할 수 있습니다.</p>
      <?php if (!is_logged_in()): ?><div class="hanok-hero-actions"><button class="btn btn--primary" type="button" id="openAuth2">시작하기 <span aria-hidden="true">→</span></button><a class="hanok-text-link" href="#home-preview">역할별 예시 체험 <span aria-hidden="true">→</span></a></div><?php elseif (is_admin()): ?><div class="hanok-hero-actions"><a class="btn btn--primary" href="/admin_manager_payouts.php">관리 화면으로 <span aria-hidden="true">↗</span></a></div><?php endif; ?>
      <?php if (is_logged_in() && !is_admin()): ?>
        <?php
          $isBuildingRole=(($_SESSION['role'] ?? 'agency') === 'building');
          $workLabel=$isBuildingRole?'건물 관리하기':'거래처·건물 관리하기';
          $workTitle=$isBuildingRole?'오늘의 소방안전관리 업무를 확인하세요':'거래처별 업무 현황을 확인하세요';
        ?>
        <div class="cta-box cta-box--work">
          <div class="work-entry__body">
            <div class="cta-box__eyebrow"><?=h($_SESSION['nickname'] ?? '회원')?>님 로그인 완료</div>
            <div class="cta-box__title"><?=h($workTitle)?></div>
            <p class="cta-box__msg"><?=$isBuildingRole?'작성 중인 업무와 다음 할 일을 바로 이어서 진행할 수 있습니다.':'거래처와 건물별 진행 업무를 한 화면에서 확인할 수 있습니다.'?></p>
          </div>
          <a class="btn btn--primary" href="<?=h(work_page())?>"><?=h($workLabel)?></a>
        </div>
      <?php endif; ?>
      <div class="hero__note hero__note--collaboration"><span>PC · 모바일 지원</span><i aria-hidden="true"></i><span>건물정보 연동</span><i aria-hidden="true"></i><span>궁금한 내용은 작성 문의</span><i aria-hidden="true"></i><span>인쇄 · PDF</span><button type="button" id="home-screen-add" aria-label="홈 화면에 추가하기" aria-haspopup="dialog" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M9 11h6m-3-3v6m-1 5h2"/></svg><span>홈 화면 추가</span></button></div>
      <?php if ($notice !== ''): ?>
        <div class="alert <?= is_admin() ? 'alert--ok' : 'alert--warn' ?>"
             style="max-width:400px;margin:16px 0 0"><?=h($notice)?></div>
      <?php endif; ?>
    </div>



    
<section id="home-preview" class="home-audiences" aria-labelledby="homeAudiencesTitle">
  <div class="home-audiences-heading"><h2 id="homeAudiencesTitle">안전관리자는 스스로, 업체는 더 효율적으로.</h2><p>각자의 업무를 편리하게, 필요한 정보는 연결해서.</p></div>
  <div class="home-audience-grid">
    <article class="home-audience-card home-company-example-card"><div class="home-audience-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5H5v16h14V5h-3"/><rect x="8" y="3" width="8" height="4" rx="1"/><path d="m8 12 2 2 5-5M8 18h7"/></svg></div><div class="home-audience-copy"><span class="home-audience-for">소방안전관리자</span><h3>소방계획서 작성부터 일상 업무 관리까지.</h3><p>건물정보와 업무 기록을 직접 확인하고 소방계획서를 작성하세요.<br>필요할 때 업체를 연결해 작성 내용을 문의할 수 있습니다.</p><div class="home-audience-features"><span>업무 기록</span><span>소방계획서 작성</span><span>작성 문의</span></div><button type="button" class="home-company-preview-open" id="homeSafetyPreviewOpen" aria-haspopup="dialog"><span class="home-company-card-hit" aria-hidden="true"></span>건물관리 예시 체험 <span aria-hidden="true">→</span></button></div></article>
    <article class="home-audience-card home-company-example-card"><div class="home-audience-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Zm6-3v15m6-12v15"/><path d="m6 10 1 1 5-1 5 3 1-1"/></svg></div><div class="home-audience-copy"><span class="home-audience-for">소방 관련 업체 · 파트너 센터</span><h3>여러 거래처의 관리를 한곳에서.</h3><p>거래처 지도·방문일정·업무 알림으로 일정을 관리하세요.<br>연결된 건물의 작성 현황과 문의도 함께 확인할 수 있습니다.</p><div class="home-audience-features"><span>거래처 지도</span><span>방문일정·알림</span><span>작성 문의 응대</span></div><button type="button" class="home-company-preview-open" id="homePartnerPreviewOpen" aria-haspopup="dialog"><span class="home-company-card-hit" aria-hidden="true"></span>파트너센터 예시 체험 <span aria-hidden="true">→</span></button></div></article>
  </div>
</section>
   </div>
  </div>
</section>



<aside class="home-service-principle" aria-labelledby="homePrincipleTitle"><h2 id="homePrincipleTitle">서비스 운영 원칙</h2><p><span class="principle-line">소방계획서.com은 <strong>관련 법령에서 정한 필수 사항을 정확히 반영</strong>하는 것을 기본 원칙으로 삼고,</span> <span class="principle-line"><strong>소방청의 권장사항에 최대한 부합</strong>하도록 서비스를 지속적으로 점검하고 개선하고 있습니다.</span></p></aside>

<dialog id="homeSafetyPreviewDialog" class="home-partner-preview-dialog" aria-labelledby="homeSafetyPreviewTitle"><header><div><span>FIREDESK · 예시 체험</span><h2 id="homeSafetyPreviewTitle">우리 건물의 소방안전관리를 직접 체험하세요.</h2></div><div class="home-example-dialog-actions"><button type="button" id="homeMobilePreviewOpen" aria-haspopup="dialog">모바일 화면 확인 ↗</button><button type="button" id="homeSafetyPreviewClose" aria-label="건물관리 예시 닫기">닫기 ×</button></div></header><iframe id="homeFireDeskFrame" data-src="/firedesk_example.php/FireDesk.php" title="가상 건물 FireDesk 화면 체험" sandbox="allow-scripts allow-same-origin allow-modals" referrerpolicy="same-origin"></iframe></dialog>
<dialog class="home-mobile-preview-dialog" id="homeMobilePreviewDialog" aria-labelledby="homeMobilePreviewTitle"><header><div><span>FIREDESK · MOBILE</span><h2 id="homeMobilePreviewTitle">모바일 화면 미리보기</h2></div><button type="button" id="homeMobilePreviewClose" aria-label="모바일 미리보기 닫기">닫기 ×</button></header><iframe id="homeFireDeskMobileFrame" data-src="/firedesk_example.php/FireDesk.php" title="가상 건물 FireDesk 모바일 화면 체험" sandbox="allow-scripts allow-same-origin allow-modals" referrerpolicy="same-origin"></iframe></dialog>
<dialog id="homePartnerPreviewDialog" class="home-partner-preview-dialog" aria-labelledby="homePartnerPreviewTitle"><header><div><span>PARTNER CENTER · 예시 체험</span><h2 id="homePartnerPreviewTitle">지도와 업무모드를 직접 눌러보세요.</h2></div><div class="home-example-dialog-actions"><button type="button" id="homePartnerMobilePreviewOpen" aria-haspopup="dialog">모바일 화면 확인 ↗</button><button type="button" id="homePartnerPreviewClose" aria-label="파트너센터 예시 닫기">닫기 ×</button></div></header><iframe id="homePartnerPreviewFrame" data-src="/partner_center_example.php" title="파트너센터 지도와 업무모드 예시" sandbox="allow-scripts allow-same-origin allow-modals" referrerpolicy="same-origin"></iframe></dialog>
<dialog class="home-mobile-preview-dialog" id="homePartnerMobilePreviewDialog" aria-labelledby="homePartnerMobilePreviewTitle"><header><div><span>PARTNER CENTER · MOBILE</span><h2 id="homePartnerMobilePreviewTitle">파트너센터 모바일 예시</h2></div><button type="button" id="homePartnerMobilePreviewClose" aria-label="파트너센터 모바일 예시 닫기">닫기 ×</button></header><iframe id="homePartnerMobilePreviewFrame" data-src="/partner_center_example.php" title="파트너센터 모바일 지도와 업무모드 예시" sandbox="allow-scripts allow-same-origin allow-modals" referrerpolicy="same-origin"></iframe></dialog>
<script src="/home_firedesk_preview.js?v=20261026-partner-mobile-10" defer></script>
<!-- MAIN -->
<main class="main">
  <?php if (is_admin()): ?>
      <div class="cta-box">
        <p class="cta-box__msg"><span class="accent">파트너 관리</span>에서 가입 현황·파이어 마일리지·출금 신청을 확인하세요.</p>
        <div class="cta-box__btns">
          <a class="btn btn--primary btn--lg" href="/admin_manager_payouts.php">파트너 관리 →</a>
          <a class="btn btn--lg btn--admin" href="/admin_memo.php">메모 열기 →</a>
          <a class="btn btn--lg btn--admin" href="/admin_members.php">👥 회원 관리 →</a>
          <a class="btn btn--lg btn--admin" href="/admin_accounts.php">관리자 계정 관리 →</a>
        </div>
      </div>
  <?php endif; ?>

  <section class="service-guide" aria-labelledby="serviceGuideTitle">
    <div class="service-guide__head">
      <div class="service-guide__eyebrow">직접 작성하고, 필요한 내용을 문의하세요.</div>
      <h2 id="serviceGuideTitle">내 건물 확인부터<br>소방계획서 완성까지</h2>
      <p class="service-guide__lead">업체의 안내로 가입해도, 직접 가입해도 관리의 중심은 소방안전관리자입니다. 내 건물의 현황을 확인하고, 수행한 업무를 기록하며, 저장한 자료로 소방계획서를 완성해 가세요.</p>
    </div>
    <div class="home-start-paths" aria-label="두 가지 가입 경로">
      <article><span>업체 안내로 시작</span><h3>초대받은 건물정보를 확인하세요.</h3><p>업체에서 받은 초대 QR·링크로 가입하고, 사전등록된 정보를 확인해 직접 관리를 이어가세요.</p></article>
      <article><span>직접 가입해 시작</span><h3>내 건물정보부터 준비하세요.</h3><p>직접 가입한 뒤 안내에 따라 내 건물 등록을 진행하세요. 작성 중 문의할 곳이 필요하면 로컬 연결을 이용할 수 있습니다.</p></article>
    </div>
    <div class="service-flow">
      <article class="service-step"><span class="service-step__num"></span><h3>내 건물 확인</h3><p>초대받은 건물정보를 확인하거나, 직접 가입 후 내 건물을 등록할 정보를 준비합니다.</p></article>
      <article class="service-step"><span class="service-step__num"></span><h3>기본정보 준비</h3><p>조회한 건물정보를 확인하고 동별로 설치된 소방시설을 선택·저장합니다.</p></article>
      <article class="service-step"><span class="service-step__num"></span><h3>편성·피난계획</h3><p>근무자 명단으로 자위소방대를 편성하고, 대피방법과 집결지 등을 준비합니다.</p></article>
      <article class="service-step"><span class="service-step__num"></span><h3>업무 기록</h3><p>수행한 업무와 조치 내용, 자위소방대 교육 및 소방훈련 실시 결과를 남깁니다.</p></article>
      <article class="service-step"><span class="service-step__num"></span><h3>소방계획서 완성</h3><p>저장한 자료를 불러와 계획서를 작성·수정하고 필요한 서류를 인쇄·PDF 저장합니다.</p></article>
    </div>
    <div class="home-tools" aria-label="함께 이용하는 서비스 기능">
      <article class="home-tool"><span class="home-tool__badge">무료</span><h3>소방안전관리자 현황표</h3><p>기본정보에 입력한 대상명·관리자·연락처·등급을 불러와 현황표를 인쇄합니다.</p></article>
      <article class="home-tool"><span class="home-tool__badge home-tool__badge--pro">PRO</span><h3>비화재보 기록</h3><p>발생 상황과 조치 내용을 기록하고, 저장한 내용을 다시 확인합니다.</p></article>
      <article class="home-tool"><span class="home-tool__badge home-tool__badge--pro">PRO</span><h3>소방교육자료</h3><p>소화기 사용법과 건물에 설정한 옥내소화전 기동 방식에 맞는 교육영상을 확인합니다.</p></article>
      <article class="home-tool"><span class="home-tool__badge home-tool__badge--pro">PRO</span><h3>궁금한 내용은 작성 문의</h3><p>직접 작성하다 궁금한 항목을 연결 업체 또는 연결된 로컬에 문의하고, 받은 답변을 확인합니다.</p></article>
    </div>
    <p class="home-tools__note">건물관리 회원은 업체 연결 후 기본정보·시설 현황을 이용할 수 있습니다. PRO 업무 작성과 작성 문의는 해당 건물의 구독 상태에 따라 제공됩니다. 문의에 대한 지원 범위와 일정은 연결 업체와 확인해 주세요. <a href="/service.php#service-compare">무료·PRO 기능 비교 →</a></p>
    <div class="service-guide__result">
      <div><b>스스로 관리하고, 궁금할 때 문의하세요.</b><span>업무의 확인·작성·관리는 소방안전관리자가, 작성 문의의 답변은 연결 업체 또는 로컬이 맡습니다.</span></div>
      <?php if (is_logged_in()): ?><a class="btn" href="<?=h(work_page())?>">건물 관리 시작하기 →</a><?php else: ?><button class="btn" type="button" id="openAuth3">무료로 시작하기 →</button><?php endif; ?>
    </div>
  </section>
</main>

<!-- FOOTER -->
<script src="/admin_logo_entry.js?v=1" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>

<?php
/* 가입에 실패해 되돌아온 경우, signup.php 가 세션에 남겨둔 입력값을 꺼냅니다.
   (한 번 쓰고 지웁니다. 비밀번호는 애초에 담기지 않습니다.) */
$oldSignup = $_SESSION['signup_old'] ?? [];
unset($_SESSION['signup_old']);
$oldLogin  = $_SESSION['login_old'] ?? [];
unset($_SESSION['login_old']);
$ov = function(string $k) use ($oldSignup): string {
  return h((string)($oldSignup[$k] ?? ''));
};
/* 이미 인증을 마친 이메일이면 인증 상태를 그대로 유지합니다 */
$oldEmail    = (string)($oldSignup['email'] ?? '');
$oldVerified = $oldEmail !== '' && (
  !empty($_SESSION['email_verified'][$oldEmail]) ||
  !empty($_SESSION['email_verified'][strtolower(trim($oldEmail))])
);
$oldRole = (string)($oldSignup['role'] ?? '');
?>
<!-- 통합 로그인 팝업 (회원가입 / 로그인) -->
<div class="modal-backdrop" id="authModal">
  <div class="modal" role="dialog" aria-modal="true">
    <button class="btn btn--ghost" id="closeAuth" style="padding:4px 8px;">✕</button>

    <div class="amodal__left">
    <div class="modal__head">
      <h3>로그인 · 회원가입</h3>
    </div>

    <?php if (!empty($_GET['err'])): ?>
      <div class="amodal__err"><?=h((string)$_GET['err'])?></div>
    <?php endif; ?>

    <!-- 탭 -->
    <div class="auth-tabs">
      <button type="button" class="auth-tab active" data-tab="login">로그인</button>
      <button type="button" class="auth-tab" data-tab="signup">회원가입</button>
    </div>

    <!-- 로그인 폼 -->
    <form method="post" action="/signup.php" class="auth-form" data-form="login">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="mode" value="login">
      <input type="hidden" name="from" value="modal">
      <div class="field">
        <label>아이디</label>
        <input class="inp" name="userid" required value="<?=h((string)($oldLogin['userid'] ?? ''))?>"
               placeholder="아이디" autocomplete="username" autocapitalize="none" spellcheck="false">
      </div>
      <div class="field">
        <label>비밀번호</label>
        <input class="inp" type="password" name="password" required placeholder="비밀번호" autocomplete="current-password">
      </div>
      <button class="btn btn--primary" type="submit"
              style="width:100%;justify-content:center;margin-top:4px;">로그인</button>
      <a href="/find_account.php" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:var(--mut2);">아이디·비밀번호를 잊으셨나요?</a>
    </form>

    <!-- 회원가입 폼 -->
    <form method="post" action="/signup.php" class="auth-form" data-form="signup" style="display:none;">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="mode" value="signup">
      <input type="hidden" name="from" value="modal">
      <div class="field">
        <label>아이디</label>
        <div class="email-row">
          <input class="inp" id="mo-uid" name="userid" required maxlength="20"
                 value="<?=$ov('userid')?>"
                 placeholder="영문으로 시작 · 영문/숫자/밑줄 4~20자"
                 autocomplete="username" autocapitalize="none" spellcheck="false">
          <button type="button" id="mo-uid-btn" class="mini-btn" onclick="moAskUid(0)">중복확인</button>
        </div>
        <div class="chk-msg" id="mo-uid-msg"></div>
      </div>
      <div class="field">
        <label>비밀번호</label>
        <input class="inp" id="mo-pw" type="password" name="password" required minlength="8"
               placeholder="8자 이상" autocomplete="new-password">
        <div class="chk-msg" id="mo-pw-msg"></div>
      </div>
      <div class="field">
        <label>비밀번호 확인</label>
        <input class="inp" id="mo-pw2" type="password" name="password2" required minlength="8"
               placeholder="비밀번호 다시 입력" autocomplete="new-password">
        <div class="chk-msg" id="mo-pw2-msg"></div>
      </div>
      <div class="field">
        <label>이메일 (필수)</label>
        <div class="email-row">
          <input class="inp" type="email" id="mo-email" name="email" required
                 value="<?=$ov('email')?>"
                 placeholder="인증번호를 받을 이메일" autocomplete="email" autocapitalize="none" spellcheck="false">
          <button type="button" id="mo-send" class="mini-btn" onclick="moSendCode()">인증번호 받기</button>
        </div>
        <div id="mo-code-box" class="code-box" style="display:none">
          <div class="email-row">
            <input class="inp" type="text" id="mo-code" inputmode="numeric" maxlength="6"
                   placeholder="인증번호 6자리" autocomplete="one-time-code">
            <button type="button" id="mo-verify" class="mini-btn" onclick="moVerifyCode()">확인</button>
          </div>
          <div class="code-timer" id="mo-timer"></div>
        </div>
        <div id="mo-msg" class="code-msg"></div>
      </div>
      <div class="field">
        <label>휴대폰 번호 (선택)</label>
        <input class="inp" type="tel" id="mo-phone" name="phone" value="<?=$ov('phone')?>"
               placeholder="010-1234-5678" autocomplete="tel" inputmode="numeric" maxlength="13">
        <div class="chk-msg" id="mo-phone-msg"></div>
      </div>
      <div class="field">
        <label>닉네임 (건물 이름)</label>
        <input class="inp" name="nickname" maxlength="20" value="<?=$ov('nickname')?>"
               placeholder="건물 이름 입력" autocomplete="nickname">
      </div>
      <div class="field">
        <label>사용자 유형</label>
        <div class="roles">
          <label class="role"><input type="radio" name="role" value="building" <?= $oldRole !== 'agency' ? 'checked' : '' ?>> 건물 소방안전관리자</label>
          <label class="role"><input type="radio" name="role" value="agency" <?= $oldRole === 'agency' ? 'checked' : '' ?>> 파트너</label>
        </div>
      </div>
      <button class="btn btn--primary" type="submit" id="mo-submit" disabled
              style="width:100%;justify-content:center;margin-top:4px;">가입하고 시작하기</button>
      <div class="code-msg" id="mo-submit-hint" style="text-align:center;margin-top:6px;color:var(--mut)">이메일 인증을 완료하면 가입할 수 있습니다.</div>
    </form>
    </div><!-- /amodal__left -->

    <div class="amodal__right">
      <h4>내 건물의 관리,<br>직접 시작하세요</h4>
      <?php
        $amFeats = [
          ['📋', '소방계획서 작성'],
          ['🏢', '건축물대장 자동 조회'],
          ['🧯', '자위소방대 편성'],
          ['🚒', '점검·훈련 기록'],
          ['📅', '파트너 방문일정·메모'],
          ['🗺️', '거래처 지도·초대 QR'],
        ];
      ?>
      <div class="amodal__mlabel"><span class="dot"></span>제공하는 기능</div>
      <div class="amodal__flist">
        <?php foreach ($amFeats as $f): ?>
          <div class="amodal__frow">
            <span class="amodal__ftile"><?=$f[0]?></span>
            <span class="amodal__flabel"><?=h($f[1])?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="amodal__check">
        <span class="ico">✓</span>
        <div class="tx"><b>무료로 시작 가능</b><span>기본정보·시설 현황은 업체 또는 로컬 연결 후 이용할 수 있어요</span></div>
      </div>
      <div class="amodal__check">
        <span class="ico">✓</span>
        <div class="tx"><b>PRO 12개월 이용권 · <?=number_format(AP_PROMO_PRICE)?>원</b><span>사전등록·로컬 파트너 연결 프로모션 대상 고객 기준 · 로그인 후 적용 금액을 확인하세요.</span></div>
      </div>
      <div class="amodal__more"><a href="/service.php">자세히 보기 →</a></div>
    </div>
  </div>
</div>

<script>
  // 모바일 햄버거 메뉴
  (function(){
    var t = document.getElementById('navToggle');
    var m = document.getElementById('navLinks');
    if (t && m) {
      t.addEventListener('click', function(){
        var open = m.classList.toggle('open');
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
        t.textContent = open ? '✕' : '☰';
      });
    }
  })();

  const authModal = document.getElementById('authModal');
  const openAuthBtns = [document.getElementById('openAuth'), document.getElementById('openAuth2'), document.getElementById('openAuth3')];
  const closeAuth = document.getElementById('closeAuth');

  function switchAuthTab(tab){
    document.querySelectorAll('.auth-tab').forEach(b =>
      b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.auth-form').forEach(f =>
      f.style.display = (f.dataset.form === tab) ? 'block' : 'none');
  }
  function openAuthModal(tab){
    authModal.style.display = 'flex';
    switchAuthTab(tab || 'login');
  }
  function closeAuthModal(){ authModal.style.display = 'none'; }

  openAuthBtns.forEach(b => b && b.addEventListener('click', () => openAuthModal('login')));
  if (closeAuth) closeAuth.addEventListener('click', closeAuthModal);
  authModal?.addEventListener('click', e => { if (e.target === authModal) closeAuthModal(); });
  document.querySelectorAll('.auth-tab').forEach(b =>
    b.addEventListener('click', () => switchAuthTab(b.dataset.tab)));

  // 관리자 로그인 시도 후 실패하면 팝업을 관리자 탭으로 다시 열기
  <?php if (is_admin() && $notice !== ''): ?>window.scrollTo({top:0,behavior:'smooth'});<?php endif; ?>

  // ?auth=login / ?auth=signup 으로 들어오면 자동으로 열기
  <?php if (isset($_GET['auth']) && !is_logged_in()):
        $a = $_GET['auth'];
        $authTab = in_array($a, ['login','signup'], true) ? $a : 'login'; ?>
  openAuthModal('<?=$authTab?>');
  <?php endif; ?>

  /* ── 팝업 회원가입: 이메일 인증번호 ── */
  const MO_CSRF = <?=json_encode($CSRF)?>;
  let moTimer = null, moVerified = false;

  /* 가입 실패로 되돌아왔을 때, 이미 마친 이메일 인증을 다시 시키지 않습니다. */
  const MO_WAS_VERIFIED = <?= $oldVerified ? 'true' : 'false' ?>;

  /* 휴대폰은 서버에 보내기 전에 여기서 먼저 확인합니다.
     이렇게 하면 형식이 틀렸을 때 화면을 떠나지 않아 입력이 보존됩니다. */
  function moPhoneDigits(){
    const el = document.getElementById('mo-phone');
    return el ? el.value.replace(/[^0-9]/g, '') : '';
  }
  function moCheckPhone(silent){
    const d = moPhoneDigits();
    if (d === '') { moSetChk('mo-phone-msg', '', ''); return true; }   // 선택 항목
    const ok = /^01[016789][0-9]{7,8}$/.test(d);
    if (!ok && !silent) moSetChk('mo-phone-msg', '휴대폰 번호 형식을 확인해 주세요. (예: 010-1234-5678)', 'err');
    else if (ok) moSetChk('mo-phone-msg', '사용할 수 있는 번호입니다.', 'ok');
    else moSetChk('mo-phone-msg', '', '');
    return ok;
  }
  /* 입력하는 동안 자동으로 하이픈을 넣어 줍니다 */
  function moFormatPhone(){
    const el = document.getElementById('mo-phone');
    if (!el) return;
    const d = el.value.replace(/[^0-9]/g, '').slice(0, 11);
    let out = d;
    if (d.length > 7)      out = d.slice(0,3) + '-' + d.slice(3, d.length === 10 ? 6 : 7) + '-' + d.slice(d.length === 10 ? 6 : 7);
    else if (d.length > 3) out = d.slice(0,3) + '-' + d.slice(3);
    el.value = out;
  }

  /* 아이디는 입력 중 형식만 보고, 입력 완료/버튼 클릭 때 서버에 한 번 확인합니다. */
  const MO_UID_RE = /^[a-z][a-z0-9_]{3,19}$/;
  let moUidStatus = 'idle'; // idle | checking | available | taken | error
  let moUidCheckedValue = '';
  let moUidSeq = 0;

  function moSetChk(id, text, state) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.className = 'chk-msg' + (state ? ' ' + state : '');
  }

  function moUidValue() {
    const el = document.getElementById('mo-uid');
    return el ? el.value.trim().toLowerCase() : '';
  }

  function moUidFormatOk(showMessage) {
    const v = moUidValue();
    if (v === '') { moSetChk('mo-uid-msg', '', ''); return false; }
    if (v.length < 4)  { moSetChk('mo-uid-msg', '4자 이상 입력하세요. (현재 ' + v.length + '자)', 'err'); return false; }
    if (v.length > 20) { moSetChk('mo-uid-msg', '20자를 넘을 수 없습니다.', 'err'); return false; }
    if (!/^[a-z]/.test(v)) { moSetChk('mo-uid-msg', '아이디는 영문으로 시작해야 합니다.', 'err'); return false; }
    if (!MO_UID_RE.test(v)) { moSetChk('mo-uid-msg', '영문, 숫자, 밑줄(_)만 사용할 수 있습니다.', 'err'); return false; }
    if (showMessage) moSetChk('mo-uid-msg', '중복확인을 해주세요.', '');
    return true;
  }

  function moResetUidCheck() {
    const el = document.getElementById('mo-uid');
    if (!el) return;
    const lower = el.value.toLowerCase();
    if (el.value !== lower) el.value = lower;
    moUidSeq++;
    moUidStatus = 'idle';
    moUidCheckedValue = '';
    const btn = document.getElementById('mo-uid-btn');
    if (btn) { btn.disabled = false; btn.textContent = '중복확인'; }
    moUidFormatOk(true);
    moUpdateSubmit();
  }

  async function moAskUid() {
    const el = document.getElementById('mo-uid');
    const btn = document.getElementById('mo-uid-btn');
    if (!el || !moUidFormatOk(false)) {
      if (el && moUidValue() === '') {
        moSetChk('mo-uid-msg', '아이디를 먼저 입력해 주세요.', 'err');
        el.focus();
      }
      return;
    }

    const value = moUidValue();
    el.value = value;
    const my = ++moUidSeq;
    moUidStatus = 'checking';
    moUidCheckedValue = '';
    if (btn) { btn.disabled = true; btn.textContent = '확인 중…'; }
    moSetChk('mo-uid-msg', '중복 확인 중…', '');
    moUpdateSubmit();

    try {
      const response = await fetch('/signup.php?check_uid=' + encodeURIComponent(value), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const data = await response.json();
      if (my !== moUidSeq || value !== moUidValue()) return;

      if (!response.ok || !data || !data.ok) {
        moUidStatus = 'error';
        moSetChk('mo-uid-msg', data?.msg || '중복확인을 완료하지 못했습니다. 다시 시도해 주세요.', 'err');
      } else if (data.taken) {
        moUidStatus = 'taken';
        moSetChk('mo-uid-msg', '이미 사용 중인 아이디입니다. 다른 아이디를 입력해 주세요.', 'err');
      } else {
        moUidStatus = 'available';
        moUidCheckedValue = value;
        moSetChk('mo-uid-msg', '사용할 수 있는 아이디입니다.', 'ok');
      }
    } catch (e) {
      if (my !== moUidSeq || value !== moUidValue()) return;
      moUidStatus = 'error';
      moSetChk('mo-uid-msg', '네트워크 오류로 확인하지 못했습니다. 다시 시도해 주세요.', 'err');
    } finally {
      if (my === moUidSeq) {
        if (btn) { btn.disabled = false; btn.textContent = '중복확인'; }
        moUpdateSubmit();
      }
    }
  }

  function moChkPw() {
    const el = document.getElementById('mo-pw');
    if (!el) return false;
    const v = el.value;
    if (v === '') { moSetChk('mo-pw-msg', '', ''); return false; }
    if (v.length < 8) { moSetChk('mo-pw-msg', '8자 이상 입력하세요. (현재 ' + v.length + '자)', 'err'); return false; }
    moSetChk('mo-pw-msg', '✓ 사용 가능한 비밀번호입니다.', 'ok');
    return true;
  }
  function moChkPw2() {
    const a = document.getElementById('mo-pw'), b = document.getElementById('mo-pw2');
    if (!a || !b) return false;
    if (b.value === '') { moSetChk('mo-pw2-msg', '', ''); return false; }
    if (a.value !== b.value) { moSetChk('mo-pw2-msg', '비밀번호가 일치하지 않습니다.', 'err'); return false; }
    moSetChk('mo-pw2-msg', '✓ 비밀번호가 일치합니다.', 'ok');
    return true;
  }
  function moUpdateSubmit() {
    const sb = document.getElementById('mo-submit');
    const hint = document.getElementById('mo-submit-hint');
    if (!sb) return;
    const value = moUidValue();
    const uidOk = MO_UID_RE.test(value)
      && moUidStatus === 'available'
      && moUidCheckedValue === value;
    const passwordOk = moChkPw();
    const passwordMatchOk = moChkPw2();
    const allOk = uidOk && passwordOk && passwordMatchOk && moVerified;
    sb.disabled = !allOk;
    if (!hint) return;
    if (allOk) { hint.textContent = '✓ 모든 항목이 준비되었습니다.'; hint.style.color = '#16a34a'; }
    else if (!moVerified) { hint.textContent = '이메일 인증을 완료하면 가입할 수 있습니다.'; hint.style.color = ''; }
    else if (!uidOk) { hint.textContent = '아이디 중복확인을 완료해 주세요.'; hint.style.color = ''; }
    else { hint.textContent = '위 항목을 확인해 주세요.'; hint.style.color = ''; }
  }

  document.getElementById('mo-uid')?.addEventListener('input', moResetUidCheck);
  document.getElementById('mo-uid')?.addEventListener('blur', event => {
    if (event.relatedTarget?.id === 'mo-uid-btn') return;
    if (moUidStatus === 'idle' && moUidFormatOk(false)) moAskUid();
  });
  ['mo-pw','mo-pw2'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', moUpdateSubmit);
  });
  document.querySelector('[data-form="signup"]')?.addEventListener('submit', event => {
    if (moUidStatus !== 'available' || moUidCheckedValue !== moUidValue()) {
      event.preventDefault();
      moSetChk('mo-uid-msg', '아이디 중복확인을 완료해 주세요.', 'err');
      moUpdateSubmit();
      return;
    }
    /* 휴대폰 형식은 여기서 막습니다. 서버까지 갔다 오면 화면이 새로 그려져
       입력한 내용이 사라지기 때문입니다. */
    if (!moCheckPhone(false)) {
      event.preventDefault();
      document.getElementById('mo-phone')?.focus();
    }
  });

  /* 휴대폰 입력 보조 */
  document.getElementById('mo-phone')?.addEventListener('input', () => { moFormatPhone(); moCheckPhone(true); });
  document.getElementById('mo-phone')?.addEventListener('blur',  () => moCheckPhone(false));

  /* 되돌아온 화면 초기 상태 맞추기 */
  (function(){
    if (MO_WAS_VERIFIED) { moLock(true); moMsg('이메일 인증이 완료된 상태입니다.', true); }
    if (document.getElementById('mo-phone')?.value) { moFormatPhone(); moCheckPhone(true); }
    const uid = document.getElementById('mo-uid');
    if (uid && uid.value.trim() !== '') moAskUid(1);   // 아이디 중복확인 자동 재실행
    moUpdateSubmit();
  })();

  function moMsg(t, ok){
    const el = document.getElementById('mo-msg');
    if (!el) return;
    el.textContent = t;
    el.className = 'code-msg ' + (ok ? 'ok' : 'err');
  }
  function moLock(v){
    moVerified = v;
    const em = document.getElementById('mo-email');
    const sd = document.getElementById('mo-send');
    if (em) em.readOnly = v;
    if (sd) sd.disabled = v;
    if (v) {
      const box = document.getElementById('mo-code-box');
      if (box) box.style.display = 'none';
      if (moTimer) { clearInterval(moTimer); moTimer = null; }
    }
    moUpdateSubmit();
  }
  function moStartTimer(sec){
    const el = document.getElementById('mo-timer');
    if (!el) return;
    if (moTimer) clearInterval(moTimer);
    const tick = () => {
      if (sec <= 0) { clearInterval(moTimer); moTimer = null; el.textContent = '인증번호가 만료되었습니다. 다시 받아주세요.'; return; }
      const m = Math.floor(sec/60), s = sec%60;
      el.textContent = `남은 시간 ${m}:${String(s).padStart(2,'0')}`;
      sec--;
    };
    tick();
    moTimer = setInterval(tick, 1000);
  }
  async function moSendCode(){
    const emailInput = document.getElementById('mo-email');
    const email = emailInput.value.trim().toLowerCase();
    emailInput.value = email;
    if (!email) { moMsg('이메일을 입력하세요.', false); return; }
    const btn = document.getElementById('mo-send');
    btn.disabled = true; btn.textContent = '발송 중…';
    try {
      const fd = new FormData();
      fd.append('csrf', MO_CSRF); fd.append('action','send'); fd.append('email', email);
      const r = await fetch('/email_code.php', { method:'POST', body: fd });
      const d = await r.json();
      moMsg(d.msg, d.ok);
      if (d.ok) {
        document.getElementById('mo-code-box').style.display = 'block';
        document.getElementById('mo-code').focus();
        moStartTimer(300);
        btn.textContent = '재발송';
      } else { btn.textContent = '인증번호 받기'; }
    } catch(e) {
      moMsg('네트워크 오류가 발생했습니다.', false);
      btn.textContent = '인증번호 받기';
    }
    btn.disabled = false;
  }
  async function moVerifyCode(){
    const email = document.getElementById('mo-email').value.trim().toLowerCase();
    const code  = document.getElementById('mo-code').value.trim();
    if (code.length !== 6) { moMsg('인증번호 6자리를 입력하세요.', false); return; }
    const btn = document.getElementById('mo-verify');
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('csrf', MO_CSRF); fd.append('action','verify');
      fd.append('email', email); fd.append('code', code);
      const r = await fetch('/email_code.php', { method:'POST', body: fd });
      const d = await r.json();
      moMsg(d.msg, d.ok);
      if (d.ok) moLock(true);
    } catch(e) { moMsg('네트워크 오류가 발생했습니다.', false); }
    btn.disabled = false;
  }
  document.getElementById('mo-email')?.addEventListener('input', () => { if (moVerified) moLock(false); });

  /* 홍보 슬라이드 — 점·화살표 조작 + 자동 넘김 */
  (function(){
    const track = document.getElementById('promoTrack');
    if(!track) return;
    const dots = [...document.querySelectorAll('.promo__dot')];
    const n = dots.length;
    let i = 0, timer = null;

    function go(k){
      i = (k + n) % n;
      track.scrollTo({ left: track.clientWidth * i, behavior:'smooth' });
      dots.forEach((d,j)=>{d.classList.toggle('is-on', j===i);d.setAttribute('aria-pressed',String(j===i));});
    }
    function auto(){ stop(); if(matchMedia('(prefers-reduced-motion: reduce)').matches || document.hidden || promo.matches(':hover') || promo.contains(document.activeElement)) return; timer = setInterval(()=>go(i+1), 6500); }
    function stop(){ if(timer){ clearInterval(timer); timer=null; } }

    dots.forEach(d=> d.addEventListener('click', e=>{ e.preventDefault(); go(+d.dataset.i); auto(); }));
    document.querySelector('.promo__arw--l')?.addEventListener('click', e=>{ e.preventDefault(); go(i-1); auto(); });
    document.querySelector('.promo__arw--r')?.addEventListener('click', e=>{ e.preventDefault(); go(i+1); auto(); });

    /* 손으로 스크롤하면 점 동기화 */
    let st;
    track.addEventListener('scroll', ()=>{ clearTimeout(st); st=setTimeout(()=>{
      const k = Math.round(track.scrollLeft / track.clientWidth);
      if(k!==i){ i=k; dots.forEach((d,j)=>{d.classList.toggle('is-on', j===i);d.setAttribute('aria-pressed',String(j===i));}); }
    }, 80); });

    const promo = document.getElementById('promo');
    promo.addEventListener('mouseenter', stop);
    promo.addEventListener('mouseleave', auto);
    promo.addEventListener('focusin', stop);
    promo.addEventListener('focusout', ()=>setTimeout(auto,0));
    document.addEventListener('visibilitychange', ()=>document.hidden?stop():auto());
    window.addEventListener('resize', ()=>track.scrollTo({left:track.clientWidth*i,behavior:'instant'}));
    if(!matchMedia('(prefers-reduced-motion: reduce)').matches) auto();
  })();
</script>
</body>
</html>
