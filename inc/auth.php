<?php
// /inc/auth.php — shared session, auth, CSRF helpers
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once dirname(__DIR__).'/login_persistence.php';


const SESSION_TTL = 60 * 60 * 24 * 30;

function start_app_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  ini_set('session.cookie_httponly', '1');
  ini_set('session.gc_maxlifetime', (string)SESSION_TTL);
  ini_set('session.cookie_lifetime', (string)SESSION_TTL);

  $host = $_SERVER['HTTP_HOST'] ?? '';
  $cookieDomain = '';
  if ($host !== '' && $host !== 'localhost' && strpos($host, '.') !== false) {
    if (preg_match('/([^.]+\\.[^.]+)$/', $host, $m)) {
      $cookieDomain = '.'.$m[1];
    }
  }

  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  if (PHP_VERSION_ID >= 70300) {
    $params = [
      'lifetime' => SESSION_TTL,
      'path'     => '/',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ];
    if ($cookieDomain !== '') $params['domain'] = $cookieDomain;
    session_set_cookie_params($params);
  }

  alp_session_start();
}

start_app_session();

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

if (!function_exists('is_admin')) {
  function is_admin(): bool {
    return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
        || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
}

if (!function_exists('assert_csrf')) {
  function assert_csrf(string $tok): void {
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $tok)) {
      http_response_code(403);
      exit('CSRF 검증 실패');
    }
  }
}

if (!function_exists('logout_current_user')) {
  function logout_current_user(string $redirect = 'index.php'): void {
    alp_forget_current();$_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
    header('Location: '.$redirect);
    exit;
  }
}
