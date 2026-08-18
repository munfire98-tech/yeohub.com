<?php
/* =============================================================
   jawi_db.php — 자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부
   (화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제13호서식])
   ─────────────────────────────────────────────────────────────
   저장 위치: data/jawi/{회원}/{기록ID}.json
   목록:      data/jawi/{회원}/_index.json
   train_db.php 와 같은 방식입니다.
   ============================================================= */
declare(strict_types=1);

require_once __DIR__ . '/user_key.php';

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

function jw_user_key(): string { return app_user_key(); }

function jw_dir(): string {
  $k = jw_user_key();
  if ($k === '') return '';
  $dir = __DIR__ . '/data/jawi/' . $k;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}
function jw_index_file(): string { $d = jw_dir(); return $d === '' ? '' : $d . '/_index.json'; }
function jw_file(string $id): string {
  $d = jw_dir();
  $id = preg_replace('/[^0-9A-Za-z]/', '', $id);
  return ($d === '' || $id === '') ? '' : $d . '/' . $id . '.json';
}

function jw_csrf(): string {
  if (empty($_SESSION['jw_csrf'])) $_SESSION['jw_csrf'] = bin2hex(random_bytes(16));
  return (string)$_SESSION['jw_csrf'];
}
function jw_csrf_check(): void {
  if (!hash_equals(jw_csrf(), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
}

function jw_read_json(string $f): array {
  if ($f === '' || !is_file($f)) return [];
  $a = json_decode((string)@file_get_contents($f), true);
  return is_array($a) ? $a : [];
}
function jw_write_json(string $f, array $d): bool {
  if ($f === '') return false;
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    @unlink($tmp); return false;
  }
  return @rename($tmp, $f);
}

/** 목록 — 최근 고친 것부터 */
function jw_list(): array {
  $idx = jw_read_json(jw_index_file());
  usort($idx, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
  return $idx;
}

function jw_create(): string {
  $id  = date('YmdHis') . substr((string)microtime(), 2, 4);
  $now = date('Y-m-d H:i:s');
  jw_write_json(jw_file($id), ['id'=>$id, 'created_at'=>$now, 'updated_at'=>$now, 'data'=>[]]);
  $idx   = jw_read_json(jw_index_file());
  $idx[] = ['id'=>$id, 'title'=>'(작성 중)', 'edu_date'=>'', 'created_at'=>$now, 'updated_at'=>$now];
  jw_write_json(jw_index_file(), $idx);
  return $id;
}

function jw_load(string $id): ?array {
  $f = jw_file($id);
  if ($f === '' || !is_file($f)) return null;
  $r = jw_read_json($f);
  return $r ?: null;
}

function jw_save(string $id, array $data): bool {
  $f = jw_file($id);
  if ($f === '') return false;
  $rec = jw_load($id) ?? ['id'=>$id, 'created_at'=>date('Y-m-d H:i:s')];
  $rec['data']       = $data;
  $rec['updated_at'] = date('Y-m-d H:i:s');
  if (!jw_write_json($f, $rec)) return false;

  /* 목록 갱신 — 대상명과 실시일자를 제목으로 */
  $title = trim((string)($data['site_name'] ?? '')) ?: '(대상명 미입력)';
  $when  = trim((string)($data['edu_date'] ?? ''));
  $idx = jw_read_json(jw_index_file());
  $hit = false;
  foreach ($idx as &$row) {
    if (($row['id'] ?? '') === $id) {
      $row['title'] = $title; $row['edu_date'] = $when; $row['updated_at'] = $rec['updated_at'];
      $hit = true; break;
    }
  }
  unset($row);
  if (!$hit) {
    $idx[] = ['id'=>$id, 'title'=>$title, 'edu_date'=>$when,
              'created_at'=>$rec['created_at'], 'updated_at'=>$rec['updated_at']];
  }
  return jw_write_json(jw_index_file(), $idx);
}

function jw_delete(string $id): void {
  $f = jw_file($id);
  if ($f !== '' && is_file($f)) @unlink($f);
  $idx  = jw_read_json(jw_index_file());
  $keep = array_values(array_filter($idx, fn($r) => (string)($r['id'] ?? '') !== $id));
  jw_write_json(jw_index_file(), $keep);
}

/* ── 서식이 인정되려면 무엇이 있어야 하는가 ─────────────── */
function jw_data_complete(array $d): bool {
  $has = fn($k) => trim((string)($d[$k] ?? '')) !== '';
  return $has('edu_date')          // 실시 일시
      && $has('edu_place')         // 장소
      && $has('edu_content')       // 주요 내용
      && $has('jawi_total')        // 자위소방대 총원
      && $has('jawi_join');        // 참석 인원
}
function jw_is_complete(string $id): bool {
  $r = jw_load($id);
  return $r ? jw_data_complete((array)($r['data'] ?? [])) : false;
}

/** 올해 제대로 작성된 기록이 있는가 (진행 현황 표시에 씁니다) */
function jw_done_this_year(?string $year = null): bool {
  $y = $year ?: date('Y');
  foreach (jw_list() as $row) {
    $id = (string)($row['id'] ?? '');
    if ($id === '') continue;
    $r = jw_load($id);
    if (!$r) continue;
    $d = (array)($r['data'] ?? []);
    if (strncmp(trim((string)($d['edu_date'] ?? '')), $y, 4) !== 0) continue;
    if (jw_data_complete($d)) return true;
  }
  return false;
}

/** 100% 까지 남은 것 */
function jw_missing(array $d): array {
  $has  = fn($k) => trim((string)($d[$k] ?? '')) !== '';
  $miss = [];
  if (!$has('site_name'))   $miss[] = '대상명';
  if (!$has('edu_date'))    $miss[] = '실시 일시';
  if (!$has('edu_place'))   $miss[] = '실시 장소';
  if (!$has('jawi_total'))  $miss[] = '자위소방대 총원';
  if (!$has('jawi_join'))   $miss[] = '참석 인원';
  if (!$has('edu_content')) $miss[] = '주요 내용';
  if (!$has('edu_fix'))     $miss[] = '보완사항';
  if (!$has('edu_action'))  $miss[] = '조치사항';
  if (!array_filter((array)($d['attend'] ?? []),
        fn($x) => trim((string)($x['name'] ?? '')) !== '')) $miss[] = '참석자 명단';
  return $miss;
}

/** 올해 기록 가운데 가장 많이 채운 것의 상태 */
function jw_year_status(?string $year = null): ?array {
  $y = $year ?: date('Y');
  $best = null;
  foreach (jw_list() as $row) {
    $id = (string)($row['id'] ?? '');
    if ($id === '') continue;
    $r = jw_load($id);
    if (!$r) continue;
    $d = (array)($r['data'] ?? []);
    if (strncmp(trim((string)($d['edu_date'] ?? '')), $y, 4) !== 0) continue;
    $miss  = jw_missing($d);
    $total = 9;
    $cand  = [
      'id'       => $id,
      'title'    => trim((string)($row['title'] ?? '')) ?: '(대상명 미입력)',
      'date'     => (string)($d['edu_date'] ?? ''),
      'percent'  => max(0, min(100, (int)round((($total - count($miss)) / $total) * 100))),
      'missing'  => $miss,
      'complete' => jw_data_complete($d),
    ];
    if ($best === null || $cand['percent'] > $best['percent']) $best = $cand;
  }
  return $best;
}

/* ── 예전 편성표(_jawi.json)에서 대원 명단 가져오기 ─────────
   fire_plan_jawi.php 로 만들어 둔 편성표가 있으면
   참석자 명단 초안으로 씁니다. */
function jw_legacy_members(): array {
  $k = jw_user_key();
  if ($k === '') return [];
  $f = __DIR__ . '/data/fireplan/' . $k . '/_jawi.json';
  $a = jw_read_json($f);
  if (!$a) return [];
  $latest = $a[count($a) - 1] ?? null;
  if (!is_array($latest)) return [];

  $out = [];
  foreach ((array)($latest['groups'] ?? []) as $g) {
    $gname = trim((string)($g['name'] ?? ''));
    foreach ((array)($g['members'] ?? []) as $m) {
      $nm = trim((string)($m['name'] ?? ''));
      if ($nm === '') continue;
      $out[] = ['role' => $gname ?: trim((string)($m['task'] ?? '')), 'name' => $nm, 'ok' => ''];
      if (count($out) >= 50) break 2;
    }
  }
  return $out;
}

/* ── 편성표에서 서식 항목 뽑아내기 ────────────────────────
   fire_plan_jawi.php 로 만들어 둔 편성표에는
     cmd(대장) · deputy(부대장) · groups(활동조)
   가 들어 있습니다. 이것으로 별지 제13호의 칸을 미리 채웁니다. */
function jw_legacy_raw(): ?array {
  $k = jw_user_key();
  if ($k === '') return null;
  $a = jw_read_json(__DIR__ . '/data/fireplan/' . $k . '/_jawi.json');
  if (!$a) return null;
  $last = $a[count($a) - 1] ?? null;
  return is_array($last) ? $last : null;
}

/** 조 이름을 서식의 칸 이름으로 옮깁니다 */
function jw_group_field(string $name): string {
  /* 한글은 strpos 로도 정확히 찾습니다 (mbstring 이 없는 서버 대비) */
  $n = str_replace(' ', '', $name);
  if (strpos($n, '비상연락') !== false || strpos($n, '통보') !== false) return 'jawi_call';
  if (strpos($n, '초기소화') !== false || strpos($n, '소화') !== false)  return 'jawi_fire';
  if (strpos($n, '피난') !== false   || strpos($n, '유도') !== false)    return 'jawi_guide';
  if (strpos($n, '응급') !== false   || strpos($n, '구조') !== false)    return 'jawi_emer';
  if (strpos($n, '방호') !== false   || strpos($n, '안전') !== false)    return 'jawi_emer';
  return '';
}

/**
 * 편성표에서 채울 수 있는 것들.
 * ['found'=>bool, 'total'=>n, 'fields'=>[...], 'attend'=>[...], 'summary'=>'사람이 읽을 요약']
 */
function jw_legacy_summary(): array {
  $p = jw_legacy_raw();
  if (!$p) return ['found' => false];

  $fields = [];
  $attend = [];
  $lines  = [];

  /* 대장 */
  $cmd = (array)($p['cmd'] ?? []);
  $cn  = trim((string)($cmd['name'] ?? ''));
  if ($cn !== '') {
    $fields['jawi_chief'] = $cn;
    $ct = trim((string)($cmd['tel'] ?? ''));
    if ($ct !== '') $fields['jawi_chief_tel'] = $ct;
    $attend[] = ['role' => '대장', 'name' => $cn, 'ok' => ''];
    $lines[]  = '대장 ' . $cn;
  }

  /* 부대장 */
  $dep = (array)($p['deputy'] ?? []);
  $dn  = trim((string)($dep['name'] ?? ''));
  if ($dn !== '') {
    $fields['jawi_vice'] = '1';
    $attend[] = ['role' => '부대장', 'name' => $dn, 'ok' => ''];
    $lines[]  = '부대장 ' . $dn;
  }

  /* 활동조 — 조마다 인원을 세어 해당 칸에 넣습니다 */
  $count = [];
  foreach ((array)($p['groups'] ?? []) as $g) {
    $gname = trim((string)($g['name'] ?? ''));
    $key   = jw_group_field($gname);
    $n     = 0;
    foreach ((array)($g['members'] ?? []) as $m) {
      $nm = trim((string)($m['name'] ?? ''));
      if ($nm === '') continue;
      $n++;
      if (count($attend) < 50) $attend[] = ['role' => $gname, 'name' => $nm, 'ok' => ''];
    }
    if ($n === 0) continue;
    if ($key !== '') $count[$key] = ($count[$key] ?? 0) + $n;
    $lines[] = $gname . ' ' . $n . '명';
  }
  foreach ($count as $k => $n) $fields[$k] = (string)$n;

  $total = count($attend);
  if ($total > 0) $fields['jawi_total'] = (string)$total;

  /* ── 초기대응체계 ────────────────────────────────────────
     소방계획서 Type-Ⅲ(상시근무 50명 미만)에서 초기대응체계는
     화재를 맨 처음 맞닥뜨리는 조 — 비상연락·초기소화·피난유도 입니다.
     자위소방대 전체가 아니라 이 세 조만 세어야 서식과 맞습니다.
     (조직도를 그리는 fire_plan_jawi.php 와 같은 기준입니다.) */
  $initN = 0; $initLines = []; $initNames = [];
  foreach ((array)($p['groups'] ?? []) as $g) {
    $gname = trim((string)($g['name'] ?? ''));
    if (!jw_is_early_group($gname)) continue;
    $n = 0;
    foreach ((array)($g['members'] ?? []) as $m) {
      $nm = trim((string)($m['name'] ?? ''));
      if ($nm === '') continue;
      $n++; $initNames[] = $nm;
    }
    if ($n === 0) continue;
    $initN += $n;
    $initLines[] = $gname . ' ' . $n . '명';
  }
  if ($initN > 0) {
    $fields['init_total'] = (string)$initN;
    /* 구성 설명도 편성표 그대로 한 줄 만들어 둡니다 (그대로 쓰거나 고쳐 쓰면 됩니다) */
    $fields['init_org'] = implode(', ', $initLines) . '으로 편성';
  }

  return [
    'found'      => $total > 0,
    'total'      => $total,
    'fields'     => $fields,
    'attend'     => $attend,
    'summary'    => implode(' · ', $lines),
    'init_total' => $initN,
    'init_desc'  => implode(' · ', $initLines),
    'init_names' => $initNames,
  ];
}

/** 초기대응체계에 드는 조인가 (비상연락 · 초기소화 · 피난유도) */
function jw_is_early_group(string $name): bool {
  $n = str_replace(' ', '', $name);
  foreach (['비상연락', '통보', '초기소화', '소화', '피난', '유도'] as $k) {
    if (strpos($n, $k) !== false) return true;
  }
  return false;
}
