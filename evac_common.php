<?php
/**
 * evac_common.php — 피난 시뮬레이션 배정 공용 함수
 *   · 모델 저장 위치 : data/evac_models/<modelId>.json
 *   · 배정 저장 위치 : data/evac_assign.json   { "<uid>": ["modelId", ...] }
 */
declare(strict_types=1);

const EVAC_DATA_DIR   = __DIR__ . '/data';
const EVAC_MODEL_DIR  = EVAC_DATA_DIR . '/evac_models';
const EVAC_ASSIGN_FILE= EVAC_DATA_DIR . '/evac_assign.json';
const EVAC_REQUEST_FILE = EVAC_DATA_DIR . '/evac_requests.json';

/* ── 로그인한 회원의 uid 알아내기 ──
   login.php가 세션에 넣는 키 이름이 다르면 여기 목록에 추가하세요. */
function evac_current_uid(): string {
  foreach (['uid','userid','user_id','login_id','member_id'] as $k) {
    if (!empty($_SESSION[$k])) return (string)$_SESSION[$k];
  }
  if (!empty($_SESSION['kakao_id'])) return 'kakao_' . $_SESSION['kakao_id'];
  return '';
}

function evac_is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}

/* 모델 ID는 영문·숫자·-·_ 만 허용 (경로 조작 방지) */
function evac_clean_id(string $id): string {
  return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $id), 0, 60);
}

/* ── 배정 목록 ── */
function evac_load_assign(): array {
  if (!file_exists(EVAC_ASSIGN_FILE)) return [];
  $a = json_decode((string)@file_get_contents(EVAC_ASSIGN_FILE), true);
  return is_array($a) ? $a : [];
}
function evac_save_assign(array $a): bool {
  if (!is_dir(EVAC_DATA_DIR)) @mkdir(EVAC_DATA_DIR, 0775, true);
  $tmp = EVAC_ASSIGN_FILE . '.tmp';
  if (file_put_contents($tmp, json_encode($a, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, EVAC_ASSIGN_FILE);
}

/* ── 회원의 시뮬레이션 배정 요청 ── */
function evac_load_requests(): array {
  if (!is_file(EVAC_REQUEST_FILE)) return [];
  $requests = json_decode((string)@file_get_contents(EVAC_REQUEST_FILE), true);
  return is_array($requests) ? $requests : [];
}

function evac_write_requests_unlocked(array $requests): bool {
  if (!is_dir(EVAC_DATA_DIR) && !@mkdir(EVAC_DATA_DIR, 0775, true) && !is_dir(EVAC_DATA_DIR)) return false;
  $json = json_encode($requests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) return false;
  $tmp = @tempnam(EVAC_DATA_DIR, '.evac_req_');
  if ($tmp === false) return false;
  $ok = @file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, EVAC_REQUEST_FILE);
  if (!$ok && is_file($tmp)) @unlink($tmp);
  return $ok;
}

function evac_store_request(string $uid, array $request): bool {
  if ($uid === '') return false;
  if (!is_dir(EVAC_DATA_DIR) && !@mkdir(EVAC_DATA_DIR, 0775, true) && !is_dir(EVAC_DATA_DIR)) return false;
  $lock = @fopen(EVAC_REQUEST_FILE . '.lock', 'c+');
  if ($lock === false || !@flock($lock, LOCK_EX)) {
    if (is_resource($lock)) @fclose($lock);
    return false;
  }
  try {
    $requests = evac_load_requests();
    $requests[$uid] = $request;
    return evac_write_requests_unlocked($requests);
  } finally {
    @flock($lock, LOCK_UN);
    @fclose($lock);
  }
}

function evac_remove_request(string $uid): bool {
  if ($uid === '' || !is_file(EVAC_REQUEST_FILE)) return true;
  $lock = @fopen(EVAC_REQUEST_FILE . '.lock', 'c+');
  if ($lock === false || !@flock($lock, LOCK_EX)) {
    if (is_resource($lock)) @fclose($lock);
    return false;
  }
  try {
    $requests = evac_load_requests();
    if (!isset($requests[$uid])) return true;
    unset($requests[$uid]);
    return evac_write_requests_unlocked($requests);
  } finally {
    @flock($lock, LOCK_UN);
    @fclose($lock);
  }
}

/* ── 모델 파일 ── */
function evac_model_path(string $id): string {
  return EVAC_MODEL_DIR . '/' . evac_clean_id($id) . '.json';
}
function evac_load_model(string $id): ?array {
  $p = evac_model_path($id);
  if (!file_exists($p)) return null;
  $m = json_decode((string)@file_get_contents($p), true);
  return is_array($m) ? $m : null;
}
function evac_save_model(array $m): bool {
  if (!is_dir(EVAC_MODEL_DIR)) @mkdir(EVAC_MODEL_DIR, 0775, true);
  $p = evac_model_path((string)$m['id']);
  $tmp = $p . '.tmp';
  if (file_put_contents($tmp, json_encode($m, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) return false;
  return @rename($tmp, $p);
}

/* uid에 배정된 모델들 (이름 포함) — 헤더 표시용 */
function evac_models_for(string $uid): array {
  if ($uid === '') return [];
  $assign = evac_load_assign();
  $ids = $assign[$uid] ?? [];
  $out = [];
  foreach ($ids as $id) {
    $m = evac_load_model((string)$id);
    if ($m) $out[] = ['id' => $m['id'], 'name' => (string)($m['name'] ?? '이름 없는 건물'),
                      'updated' => (int)($m['updated'] ?? 0)];
  }
  return $out;
}
