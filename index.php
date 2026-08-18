<?php
// index.php
declare(strict_types=1);
/* ─ 카카오 로그인 설정 ─ */
define('KAKAO_KEY',      'aea180f8a9ccf7395bccfb6dfbede9c6');   // 카카오 콘솔의 REST API 키
define('KAKAO_SECRET',   '7s3nrcwrdf5Hp5CxmlX3zOpWXfKy7IiJ');   // 카카오 로그인 클라이언트 시크릿 (콘솔에서 복사한 정확한 값)
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
session_start();

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
  // 카카오 사용자인데 아직 유형을 안 골랐으면 선택 페이지로
  if (!empty($_SESSION['kakao_id']) && empty($_SESSION['role']) && empty($_SESSION['is_admin'])) {
    return '/select_role.php';
  }
  $role = $_SESSION['role'] ?? 'agency';
  return $role === 'building' ? '/building_manager.php' : '/clients_mini.php';
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

/* 알림 미확인 개수 — notifications.php 가 쌓아둔 데이터를 그대로 씁니다 */
$unreadCount = 0;
if (is_logged_in()) {
  $__nUid = function_exists('app_user_key') ? app_user_key() : '';
  if ($__nUid !== '') {
    $__nFile = __DIR__ . '/data/notifications/' . $__nUid . '.json';
    if (is_file($__nFile)) {
      $__nList = json_decode((string)@file_get_contents($__nFile), true);
      if (is_array($__nList)) { foreach ($__nList as $__n) { if (empty($__n['read'])) $unreadCount++; } }
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
      session_regenerate_id(true);
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

  // 로그인 후 이동: 유형이 있으면 업무페이지로, 없으면(첫 로그인) 유형 선택 페이지로
  if (!empty($_SESSION['is_user'])) {
    if (is_admin()) {
      header('Location: /index.php'); exit;
    }
    if (!empty($_SESSION['role'])) {
      header('Location: ' . work_page()); exit;
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
    session_regenerate_id(true);
    $_SESSION['is_admin']   = true;
    $_SESSION['ID_OK']      = 1;
    $_SESSION['login_type'] = 'admin';
    $notice = '로그인 성공';
    if ($fromAdminPage) { header('Location: /building_manager.php'); exit; }
  } else {
    $notice = '아이디 또는 비밀번호가 올바르지 않습니다.';
    if ($fromAdminPage) { header('Location: /admin_login.php?err=1'); exit; }
  }
}

/* ─ 로그아웃 ─ */
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
  assert_csrf((string)($_GET['csrf'] ?? ''));
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'] ?? false, $p['httponly'] ?? true);
  }
  session_destroy();
  header('Location: /'); exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="naver-site-verification" content="08ff3d5a27a9c771ad785abfa862bd9f69a87ba9" />
<title>YEOHUB(요허브) | 소방안전관리 플랫폼</title>
<meta name="description" content="YeoHub(요허브)는 소방안전관리자를 위한 소방안전관리 플랫폼입니다. 업무 수행 기록과 소방계획서를 한 곳에서 관리하세요." />
<meta name="keywords" content="요허브,Yeohub,요허브 소방,소방안전관리,소방안전관리 플랫폼,소방안전관리자,업무수행기록,소방계획서,소방행정" />
<meta property="og:type" content="website" />
<meta property="og:title" content="YeoHub(요허브) | 소방안전관리 플랫폼" />
<meta property="og:description" content="요허브는 소방안전관리자를 위한 플랫폼입니다. 업무 수행 기록부터 소방계획서까지 한 곳에서 관리하세요." />
<meta property="og:url" content="https://www.yeohub.com" />
<meta property="og:site_name" content="YeoHub(요허브)" />
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

/* ── 상단 아이콘 (결제 · 알림 · 프로필) — building_manager.php 와 동일 톤 ── */
.nav__icons{display:flex;align-items:center;gap:6px}
.nav__icobtn{position:relative;display:flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--mut2);cursor:pointer;font-family:inherit;transition:.14s}
.nav__icobtn:hover{background:var(--bg2);border-color:var(--bd)}
.nav__icobtn svg{width:19px;height:19px}
.nav__dot{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;
  background:#ef4444;border:1.5px solid #fff}

.nav__profile{position:relative}
.nav__avatar{width:36px;height:36px;border-radius:50%;border:0;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,var(--brand),var(--accent));color:#fff;font-size:13px;font-weight:800;
  display:flex;align-items:center;justify-content:center;transition:.14s}
.nav__avatar:hover{filter:brightness(1.06)}
.nav__avatar.admin{background:linear-gradient(135deg,#f59e0b,#ea580c)}

.nav__pop{position:absolute;top:calc(100% + 10px);right:0;width:220px;background:#fff;
  border:1px solid var(--bd);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,38,.14);
  padding:8px;z-index:60;display:none}
.nav__pop.show{display:block}
.nav__pop__head{padding:11px 12px 12px;border-bottom:1px solid var(--bd)}
.nav__pop__name{font-size:14px;font-weight:800;color:var(--fg)}
.nav__pop__sub{font-size:11.5px;color:var(--mut);margin-top:2px}
.nav__pop__list{padding:6px 0 0}
.nav__pop__item{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;
  border:0;background:transparent;color:var(--fg);font-size:13px;font-weight:600;font-family:inherit;
  cursor:pointer;text-align:left;text-decoration:none}
.nav__pop__item:hover{background:var(--bg2)}
.nav__pop__item svg{width:16px;height:16px;color:var(--mut2);flex-shrink:0}
.nav__pop__item--danger{color:#dc2626}
.nav__pop__item--danger svg{color:#dc2626}
.nav__pop__div{height:1px;background:var(--bd);margin:6px 2px}
@media(max-width:680px){ .nav__pop{right:-8px} }

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

/* ══════════ 로그인 팝업: 2단 레이아웃 (YeoHub 밝은 톤에 맞춤) ══════════
   왼쪽 = 로그인/회원가입 폼, 오른쪽 = 제공 기능 안내.
   전부 사이트 전역 색상변수(--bg·--card·--brand 등)를 그대로 씁니다.
   flex 대신 grid-template-columns 로 폭을 못 박아 비율이 안 무너지게 했습니다. */
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
}
.nav__links .mobile-only{display:none}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav__inner">
    <div class="nav__brand">
      YEOHUB
      <?php if (is_admin()): ?>
        <span class="nav__badge">관리자</span>
      <?php endif; ?>
    </div>
    <ul class="nav__links" id="navLinks">
      <li><a href="/faq.php">FAQ</a></li>
      <li><a href="/service.php">서비스</a></li>
      <li><a href="/blog.php">블로그</a></li>
      <li><a href="/ar.php">피난시뮬레이터</a></li>
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
        <?php else: ?>
          <a class="btn btn--primary" href="<?=h(work_page())?>"><?= (($_SESSION['role'] ?? 'agency') === 'building') ? '건물 관리' : '업무페이지' ?></a>
        <?php endif; ?>

        <div class="nav__icons">
          <a class="nav__icobtn" href="/subscribe_page.php" title="결제·구독">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </a>
          <a class="nav__icobtn" href="/notifications.php" title="알림">
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <?php if ($unreadCount > 0): ?><span class="nav__dot"></span><?php endif; ?>
          </a>
          <div class="nav__profile" id="navProfile">
            <button type="button" class="nav__avatar<?= is_admin() ? ' admin' : '' ?>" id="navAvatarBtn"
              onclick="document.getElementById('navPop').classList.toggle('show')">
              <?= h(mb_substr((string)($_SESSION['nickname'] ?? '유'), 0, 1)) ?>
            </button>
            <div class="nav__pop" id="navPop">
              <?php if (!empty($_SESSION['nickname'])): ?>
                <div class="nav__pop__head">
                  <div class="nav__pop__name"><?=h($_SESSION['nickname'])?>님</div>
                  <div class="nav__pop__sub"><?= is_admin() ? '관리자' : '건물 소방안전관리자' ?></div>
                </div>
              <?php endif; ?>
              <div class="nav__pop__list">
                <a class="nav__pop__item" href="/settings.php">
                  <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1-1.56 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                  내 정보
                </a>
                <a class="nav__pop__item" href="/subscribe_page.php">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/></svg>
                  결제·구독
                </a>
                <a class="nav__pop__item" href="/notifications.php">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                  알림
                </a>
                <div class="nav__pop__div"></div>
                <a class="nav__pop__item nav__pop__item--danger" href="/?logout=1&csrf=<?=h($CSRF)?>"
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
    var pop = document.getElementById('navPop');
    if (wrap && pop && !wrap.contains(e.target)) pop.classList.remove('show');
  });
</script>


<!-- HERO -->
<section class="hero-wrap">
  <div class="hero">
   <div class="hero__grid">
    <div class="hero__col">
      <div class="hero__label"><span></span>요허브</div>
                    <h1> YEOHUB <em>업무지원</em> 플렛폼 </h1>
                    <p class="hero__sub">소방안전관리, 수행에서 증명까지</p>
                    <p class="hero__note">
                      매월 수행한 안전관리 업무를 간편하게 기록하고 체계적으로 보관하세요.
                      TWORIX가 반복 입력은 줄이고, 필요한 순간 확인할 수 있는 업무수행 근거를 남겨드립니다.
                    </p>
      <?php if ($notice !== ''): ?>
        <div class="alert <?= is_admin() ? 'alert--ok' : 'alert--warn' ?>"
             style="max-width:400px;margin:16px 0 0"><?=h($notice)?></div>
      <?php endif; ?>
    </div>

    <!-- 홍보 슬라이드: [1] 시뮬 화면  [2] 분석 보고서 -->
    <div class="promo" id="promo">
      <div class="promo__track" id="promoTrack">

        <!-- 슬라이드 1 : 실제 시뮬 화면 -->
        <a class="promo__slide" href="<?= is_admin() ? '/fire_evac_sim.php' : '/fire_evac_sim.php?embed=1' ?>">
          <div class="promo__top">
            <span class="promo__tag"><span class="dot"></span>피난 시뮬레이션</span>
            <span class="promo__meta">대피 동선 검증</span>
          </div>
          <div class="promo__stage">
            <img src="/evac_demo.png" alt="피난 시뮬레이션 화면" loading="lazy">
          </div>
          <p class="promo__cap">화재 발생 시 재실자의 대피 흐름을 한눈에</p>
        </a>

        <!-- 슬라이드 2 : 분석 보고서 -->
        <a class="promo__slide" href="<?= is_admin() ? '/fire_evac_sim.php' : '/fire_evac_sim.php?embed=1' ?>">
          <div class="promo__top">
            <span class="promo__tag"><i>📄</i> 피난 분석 보고서</span>
            <span class="promo__meta">자동 생성</span>
          </div>
          <div class="promo__stage promo__stage--rpt">
            <svg viewBox="0 0 340 210" preserveAspectRatio="xMidYMid meet" role="img" aria-label="분석 보고서 예시">
              <rect x="0" y="0" width="340" height="210" fill="#fff"/>
              <rect x="20" y="16" width="120" height="9" rx="2" fill="#1a2436"/>
              <rect x="20" y="30" width="80" height="6" rx="2" fill="#b8c0cc"/>
              <rect x="250" y="16" width="70" height="20" rx="4" fill="#e6f1fb"/>
              <text x="285" y="30" font-size="9" fill="#185fa5" text-anchor="middle" font-family="sans-serif">적합</text>
              <line x1="20" y1="48" x2="320" y2="48" stroke="#e3e8f0"/>
              <rect x="20"  y="60" width="88" height="46" rx="5" fill="#f5f7fb"/>
              <rect x="126" y="60" width="88" height="46" rx="5" fill="#f5f7fb"/>
              <rect x="232" y="60" width="88" height="46" rx="5" fill="#f5f7fb"/>
              <text x="30" y="76" font-size="7" fill="#7a8699" font-family="sans-serif">총 대피시간</text>
              <text x="30" y="96" font-size="17" fill="#1a2436" font-family="sans-serif" font-weight="600">4:12</text>
              <text x="136" y="76" font-size="7" fill="#7a8699" font-family="sans-serif">대피 완료</text>
              <text x="136" y="96" font-size="17" fill="#0f9d63" font-family="sans-serif" font-weight="600">312</text>
              <text x="242" y="76" font-size="7" fill="#7a8699" font-family="sans-serif">최대 밀집</text>
              <text x="242" y="96" font-size="17" fill="#d8471f" font-family="sans-serif" font-weight="600">3.4</text>
              <text x="20" y="128" font-size="8" fill="#56627a" font-family="sans-serif">층별 대피 소요시간</text>
              <rect x="20"  y="150" width="24" height="44" rx="2" fill="#85b7eb"/>
              <rect x="58"  y="136" width="24" height="58" rx="2" fill="#378add"/>
              <rect x="96"  y="162" width="24" height="32" rx="2" fill="#85b7eb"/>
              <rect x="134" y="144" width="24" height="50" rx="2" fill="#85b7eb"/>
              <rect x="172" y="158" width="24" height="36" rx="2" fill="#85b7eb"/>
              <line x1="20" y1="194" x2="320" y2="194" stroke="#d4dbe6"/>
              <rect x="220" y="150" width="100" height="7" rx="2" fill="#eef2f8"/>
              <rect x="220" y="164" width="100" height="7" rx="2" fill="#eef2f8"/>
              <rect x="220" y="178" width="72"  height="7" rx="2" fill="#eef2f8"/>
            </svg>
          </div>
          <p class="promo__cap">시뮬 결과를 분석 보고서로 자동 정리</p>
        </a>
      </div>

      <div class="promo__dots" role="tablist">
        <button class="promo__dot is-on" data-i="0" aria-label="1번 슬라이드"></button>
        <button class="promo__dot" data-i="1" aria-label="2번 슬라이드"></button>
      </div>
      <button class="promo__arw promo__arw--l" aria-label="이전">‹</button>
      <button class="promo__arw promo__arw--r" aria-label="다음">›</button>
    </div>
   </div>
  </div>
</section>

<!-- MAIN -->
<main class="main">
  <?php if (is_logged_in()): ?>
    <?php if (is_admin()): ?>
      <div class="cta-box">
        <p class="cta-box__msg">관리자 <span class="accent">메모</span>에서 목표·프로세스·할 일을 관리하세요.</p>
        <div class="cta-box__btns">
          <a class="btn btn--primary btn--lg" href="/admin_memo.php">📝 메모 열기 →</a>
          <a class="btn btn--lg btn--admin" href="/admin_members.php">👥 회원 관리 →</a>
          <a class="btn btn--lg btn--admin" href="/fire_evac_sim.php">👥 시뮬레이터 →</a>
        </div>
      </div>
    <?php else: ?>
      <?php $workLabel = (($_SESSION['role'] ?? 'agency') === 'building') ? '건물 관리 페이지 들어가기 →' : '업무페이지 들어가기 →'; ?>
      <div class="cta-box">
        <div class="cta-box__btns">
          <a class="btn btn--primary btn--lg" href="<?=h(work_page())?>"><?=h($workLabel)?></a>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="cta-box">
      <p class="cta-box__msg">지금 가입하고 <span class="accent">업무페이지</span>를 시작해보세요.</p>
      <button class="btn btn--primary btn--lg" id="openAuth2">회원가입 · 로그인</button>
    </div>
  <?php endif; ?>
</main>

<!-- FOOTER -->
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
        <label>닉네임 (표시 이름)</label>
        <input class="inp" name="nickname" maxlength="20" value="<?=$ov('nickname')?>"
               placeholder="비워두면 아이디로 표시" autocomplete="nickname">
      </div>
      <div class="field">
        <label>사용자 유형</label>
        <div class="roles">
          <label class="role"><input type="radio" name="role" value="building" <?= $oldRole !== 'agency' ? 'checked' : '' ?>> 건물 소방안전관리자</label>
          <label class="role"><input type="radio" name="role" value="agency" <?= $oldRole === 'agency' ? 'checked' : '' ?>> 대행업체</label>
        </div>
      </div>
      <button class="btn btn--primary" type="submit" id="mo-submit" disabled
              style="width:100%;justify-content:center;margin-top:4px;">가입하고 시작하기</button>
      <div class="code-msg" id="mo-submit-hint" style="text-align:center;margin-top:6px;color:var(--mut)">이메일 인증을 완료하면 가입할 수 있습니다.</div>
    </form>
    </div><!-- /amodal__left -->

    <div class="amodal__right">
      <h4>지금 가입하고<br>바로 시작하세요</h4>
      <?php
        $amFeats = [
          ['🏢', '건축물대장 자동 조회'],
          ['🧯', '자위소방대 편성'],
          ['📋', '소방계획서'],
          ['🚒', '점검·훈련 기록'],
          ['📅', '일정·D-DAY 알림'],
          ['🗺️', '거래처 지도'],
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
        <div class="tx"><b>무료로 시작 가능</b><span>가입만 해도 기본 기능을 바로 써볼 수 있어요</span></div>
      </div>
      <div class="amodal__check">
        <span class="ico">✓</span>
        <div class="tx"><b>연 구독 2개월 무료</b><span>월 1,900원, 연 결제 시 19,000원</span></div>
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
  const openAuthBtns = [document.getElementById('openAuth'), document.getElementById('openAuth2')];
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
      dots.forEach((d,j)=>d.classList.toggle('is-on', j===i));
    }
    function auto(){ stop(); timer = setInterval(()=>go(i+1), 4500); }
    function stop(){ if(timer){ clearInterval(timer); timer=null; } }

    dots.forEach(d=> d.addEventListener('click', e=>{ e.preventDefault(); go(+d.dataset.i); auto(); }));
    document.querySelector('.promo__arw--l')?.addEventListener('click', e=>{ e.preventDefault(); go(i-1); auto(); });
    document.querySelector('.promo__arw--r')?.addEventListener('click', e=>{ e.preventDefault(); go(i+1); auto(); });

    /* 손으로 스크롤하면 점 동기화 */
    let st;
    track.addEventListener('scroll', ()=>{ clearTimeout(st); st=setTimeout(()=>{
      const k = Math.round(track.scrollLeft / track.clientWidth);
      if(k!==i){ i=k; dots.forEach((d,j)=>d.classList.toggle('is-on', j===i)); }
    }, 80); });

    const promo = document.getElementById('promo');
    promo.addEventListener('mouseenter', stop);
    promo.addEventListener('mouseleave', auto);
    if(!matchMedia('(prefers-reduced-motion: reduce)').matches) auto();
  })();
</script>
</body>
</html>
