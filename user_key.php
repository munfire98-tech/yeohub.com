<?php
/* =============================================================
   user_key.php — 회원을 구분하는 키를 한 곳에서 결정합니다.
   ─────────────────────────────────────────────────────────────
   왜 필요한가
     예전에는 각 서식이 이렇게 키를 만들었습니다.

       $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest'))

     로그인이 member_id 나 kakao_id 를 세션에 넣지 않으면
     모든 사람이 "kakao_guest" 하나로 묶여 버립니다.
     그러면 data/building/kakao_guest/ 를 여러 회원이 공유하게 되어
     남이 입력한 건물정보가 내 화면에 "입력 완료"로 뜹니다.

     이 파일은 그 fallback 을 없앱니다. 회원을 특정하지 못하면
     빈 문자열을 돌려주고, 각 서식은 아무것도 읽거나 쓰지 않습니다.
     데이터가 섞이느니 안 보이는 편이 낫습니다.
   ============================================================= */
declare(strict_types=1);

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

/* ── 세션에서 찾아볼 키 이름들 ────────────────────────────
   로그인(login.php)이 어떤 이름으로 회원 아이디를 넣는지에 따라
   달라집니다. whoami.php 로 확인한 뒤, 실제 쓰는 이름을
   맨 앞에 두세요. 없는 이름이 섞여 있어도 무해합니다. */
const UK_SESSION_KEYS = [
  'member_id',   // 기존 코드가 쓰던 이름
  'uid',
  'userid',
  'user_id',
  'login_id',
  'mb_id',       // 그누보드 계열
  'idx',
];

/* 카카오 로그인은 별도로 접두어를 붙여 구분합니다. */
const UK_KAKAO_KEYS = ['kakao_id', 'kakaoid', 'kakao_uid'];

/**
 * 회원 구분 키.
 * 찾지 못하면 '' 를 돌려줍니다. (예전처럼 guest 로 묶지 않습니다)
 */
function app_user_key(): string {
  $adminViewKey = app_admin_view_user_key();
  if ($adminViewKey !== '') return $adminViewKey;

  foreach (UK_SESSION_KEYS as $k) {
    $v = trim((string)($_SESSION[$k] ?? ''));
    if ($v !== '' && $v !== '0') return uk_clean($v);
  }
  foreach (UK_KAKAO_KEYS as $k) {
    $v = trim((string)($_SESSION[$k] ?? ''));
    if ($v !== '' && $v !== '0') return 'kakao_' . uk_clean($v);
  }
  return '';
}

/** 관리자 화면에서 특정 회원의 데이터를 볼 때 쓰는 uid */
function app_admin_view_user_key(): string {
  $isAdmin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
          || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
  if (!$isAdmin) return '';

  $uid = trim((string)($_GET['uid'] ?? $_POST['uid'] ?? ''));
  if ($uid === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $uid)) return '';

  $membersFile = __DIR__ . '/data/members.json';
  if (is_file($membersFile)) {
    $members = json_decode((string)@file_get_contents($membersFile), true);
    if (is_array($members) && !isset($members[$uid])) return '';
  }
  return uk_clean($uid);
}

/** 폴더 이름으로 써도 안전하게 다듬습니다. */
function uk_clean(string $s): string {
  $s = preg_replace('/[^A-Za-z0-9_-]/', '_', $s);
  return substr((string)$s, 0, 64);
}

/** 회원을 특정할 수 있는가 */
function app_has_user_key(): bool { return app_user_key() !== ''; }

/**
 * 회원을 특정하지 못했을 때 화면에 띄울 안내.
 * 각 서식 맨 위에서 부르면 됩니다.
 */
function app_user_key_notice(): string {
  return '로그인 정보를 확인할 수 없어 이 화면의 내용을 불러오지 못했습니다. '
       . '다시 로그인해 주세요. 계속 같은 화면이 나오면 관리자에게 알려주세요.';
}

/**
 * 진단용 — 지금 세션에 어떤 키가 들어 있는지.
 * whoami.php 에서 씁니다.
 */
function app_user_key_debug(): array {
  $found = [];
  foreach (array_merge(UK_SESSION_KEYS, UK_KAKAO_KEYS) as $k) {
    if (isset($_SESSION[$k]) && trim((string)$_SESSION[$k]) !== '') {
      $found[$k] = (string)$_SESSION[$k];
    }
  }
  return [
    'key'     => app_user_key(),
    'matched' => $found,
    'all'     => array_keys($_SESSION),
  ];
}
