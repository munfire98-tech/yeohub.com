<?php
// train_db.php — 소방훈련·교육 실시 결과 기록부 (별지 제28호서식) 데이터 처리
declare(strict_types=1);

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

/* 사용자 키: 회원가입 → member_id / 카카오 → kakao_카카오id */
function tr_user_key(): string {
  return $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest'));
}
function tr_dir(): string {
  $dir = __DIR__ . '/data/train/' . tr_user_key();
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}
function tr_index_file(): string { return tr_dir() . '/_index.json'; }
function tr_file(string $id): string { return tr_dir() . '/' . $id . '.json'; }

function tr_csrf(): string {
  if (empty($_SESSION['tr_csrf'])) $_SESSION['tr_csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['tr_csrf'];
}
function tr_csrf_check(): void {
  if (!hash_equals($_SESSION['tr_csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403); exit('세션이 만료되었습니다. 새로고침 후 다시 시도하세요.');
  }
}

function tr_read_json(string $f): array {
  if (!is_file($f)) return [];
  $s = @file_get_contents($f);
  if ($s === false || $s === '') return [];
  $d = json_decode($s, true);
  return is_array($d) ? $d : [];
}
function tr_write_json(string $f, array $data): bool {
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

/* 목록 (최근순) */
function tr_list(): array {
  $idx = tr_read_json(tr_index_file());
  usort($idx, fn($a,$b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
  return $idx;
}

/* 새 기록 생성 → id 반환 */
function tr_create(): string {
  $id  = date('YmdHis') . substr((string)microtime(), 2, 4);
  $now = date('Y-m-d H:i:s');
  $rec = [
    'id'         => $id,
    'created_at' => $now,
    'updated_at' => $now,
    'data'       => [],
  ];
  tr_write_json(tr_file($id), $rec);
  $idx = tr_read_json(tr_index_file());
  $idx[] = ['id'=>$id, 'title'=>'(작성 중)', 'train_date'=>'', 'created_at'=>$now, 'updated_at'=>$now];
  tr_write_json(tr_index_file(), $idx);
  return $id;
}

function tr_load(string $id): ?array {
  if ($id === '' || !preg_match('/^[0-9]+$/', $id)) return null;
  $rec = tr_read_json(tr_file($id));
  return $rec ?: null;
}

/* 저장 (data 전체 갱신) */
function tr_save(string $id, array $data): bool {
  $rec = tr_load($id);
  if (!$rec) return false;
  $rec['data']       = $data;
  $rec['updated_at'] = date('Y-m-d H:i:s');
  if (!tr_write_json(tr_file($id), $rec)) return false;

  // 색인 갱신 (대상명 + 훈련일자를 제목으로)
  $title = trim((string)($data['t_name'] ?? '')) ?: '(대상명 미입력)';
  $tdate = trim((string)($data['fire_date'] ?? '')) ?: trim((string)($data['edu_date'] ?? ''));
  $idx = tr_read_json(tr_index_file());
  foreach ($idx as &$row) {
    if (($row['id'] ?? '') === $id) {
      $row['title']      = $title;
      $row['train_date'] = $tdate;
      $row['updated_at'] = $rec['updated_at'];
      break;
    }
  }
  unset($row);
  return tr_write_json(tr_index_file(), $idx);
}

function tr_delete(string $id): void {
  @unlink(tr_file($id));
  $idx = array_values(array_filter(tr_read_json(tr_index_file()), fn($r) => ($r['id'] ?? '') !== $id));
  tr_write_json(tr_index_file(), $idx);
}

/* 훈련·교육 관련 사진 저장 폴더 */
function tr_photo_dir(): string {
  $dir = tr_dir() . '/photos';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}
function tr_photo_url(string $file): string {
  $file = basename($file);
  if ($file === '' || !is_file(tr_photo_dir() . '/' . $file)) return '';
  return 'train_photo.php?f=' . rawurlencode($file);
}
/** 업로드 1건 처리 → 저장된 파일명 (실패하면 '') */
function tr_photo_save(?array $f): array {
  if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', ''];
  if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) return ['', '사진을 올리지 못했습니다.'];
  if (($f['size'] ?? 0) > 8 * 1024 * 1024) return ['', '사진 용량이 8MB를 넘습니다.'];
  $info = @getimagesize($f['tmp_name']);
  if (!$info) return ['', '이미지 파일이 아닙니다.'];
  $ext = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'][$info[2]] ?? '';
  if ($ext === '') return ['', 'JPG·PNG·GIF·WebP 만 올릴 수 있습니다.'];
  $name = date('Ymd') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
  if (!@move_uploaded_file($f['tmp_name'], tr_photo_dir() . '/' . $name)) {
    return ['', '사진을 저장하지 못했습니다. 폴더 권한을 확인해 주세요.'];
  }
  return [$name, ''];
}
function tr_photo_delete(string $file): void {
  $file = basename($file);
  if ($file !== '' && is_file(tr_photo_dir() . '/' . $file)) @unlink(tr_photo_dir() . '/' . $file);
}

/* ── 기록이 제대로 작성되었는가 ────────────────────────────
   서식이 인정되려면 아래가 모두 있어야 합니다.
     · 훈련 일시
     · 어떤 훈련을 했는지 (소화·통보·피난 중 하나 이상, 또는 훈련내용 글)
     · 참석 인원
   교육만 실시한 경우는 교육 일시·내용·인원으로 봅니다. */
function tr_data_complete(array $d): bool {
  $has = fn($k) => trim((string)($d[$k] ?? '')) !== '';

  /* 훈련 쪽 */
  $types   = array_filter((array)($d['fire_types'] ?? []), fn($x) => trim((string)$x) !== '');
  $content = $has('fire_c_sohwa') || $has('fire_c_tongbo') || $has('fire_c_pinan') || $has('fire_content');
  $fireOk  = $has('fire_date') && ($types || $content) && $has('fire_join');

  /* 교육만 한 경우 */
  $eduOk = $has('edu_date') && $has('edu_content') && $has('edu_join');

  return $fireOk || $eduOk;
}

/** 저장된 기록 하나가 완료 상태인가 */
function tr_is_complete(string $id): bool {
  $rec = tr_load($id);
  if (!$rec) return false;
  return tr_data_complete((array)($rec['data'] ?? []));
}

/** 올해 제대로 작성된 기록이 하나라도 있는가 (진행 현황 표시에 씁니다) */
function tr_done_this_year(?string $year = null): bool {
  $y = $year ?: date('Y');
  foreach (tr_list() as $row) {
    $id = (string)($row['id'] ?? '');
    if ($id === '') continue;
    $rec = tr_load($id);
    if (!$rec) continue;
    $d = (array)($rec['data'] ?? []);
    $when = trim((string)($d['fire_date'] ?? '')) ?: trim((string)($d['edu_date'] ?? ''));
    if (strncmp($when, $y, 4) !== 0) continue;
    if (tr_data_complete($d)) return true;
  }
  return false;
}

/* ── 100% 까지 남은 것 ─────────────────────────────────────
   진행률에 잡히지 않는 항목을 사람 말로 돌려줍니다.
   메인 화면에서 "사진을 넣으면 완료됩니다" 처럼 안내할 때 씁니다. */
function tr_missing(array $d): array {
  $has  = fn($k) => trim((string)($d[$k] ?? '')) !== '';
  $miss = [];

  if (!$has('fire_date'))                                   $miss[] = '훈련 일시';
  if (!array_filter((array)($d['fire_types'] ?? []),
        fn($x) => trim((string)$x) !== ''))                  $miss[] = '훈련 종류';
  if (!$has('fire_join'))                                   $miss[] = '참석 인원';
  if (!$has('fire_result'))                                 $miss[] = '훈련 성과';
  if (!$has('fire_problem') && !$has('fire_improve'))        $miss[] = '문제점·개선계획';

  if (($d['edu_skip'] ?? '') !== '1') {
    if (!$has('edu_date'))    $miss[] = '교육 일시';
    if (!$has('edu_content')) $miss[] = '교육 내용';
    if (!$has('edu_join'))    $miss[] = '교육 참석 인원';
  }

  $photos = (array)($d['photos'] ?? []);
  $hasPhoto = false;
  foreach ($photos as $p) { if (trim((string)$p) !== '') { $hasPhoto = true; break; } }
  if (!$hasPhoto) $miss[] = '훈련·교육 사진';

  return $miss;
}

/**
 * 올해 기록 가운데 가장 최근 것의 상태.
 * ['id'=>..., 'title'=>..., 'percent'=>0~100, 'missing'=>[...], 'complete'=>bool]
 * 올해 기록이 없으면 null.
 */
function tr_year_status(?string $year = null): ?array {
  $y = $year ?: date('Y');
  $best = null;
  foreach (tr_list() as $row) {
    $id  = (string)($row['id'] ?? '');
    if ($id === '') continue;
    $rec = tr_load($id);
    if (!$rec) continue;
    $d = (array)($rec['data'] ?? []);
    $when = trim((string)($d['fire_date'] ?? '')) ?: trim((string)($d['edu_date'] ?? ''));
    if (strncmp($when, $y, 4) !== 0) continue;

    $miss  = tr_missing($d);
    $total = 9;                                   // 위에서 보는 항목 수
    $pct   = (int)round((($total - count($miss)) / $total) * 100);
    $cand  = [
      'id'       => $id,
      'title'    => trim((string)($row['title'] ?? '')) ?: '(대상명 미입력)',
      'date'     => $when,
      'percent'  => max(0, min(100, $pct)),
      'missing'  => $miss,
      'complete' => tr_data_complete($d),
    ];
    /* 가장 많이 채운 기록을 대표로 보여줍니다 */
    if ($best === null || $cand['percent'] > $best['percent']) $best = $cand;
  }
  return $best;
}
