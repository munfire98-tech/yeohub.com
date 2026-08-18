<?php
// fire_plan_db.php — 소방계획서 공통 (JSON 파일 저장 방식)
//   TWORIX의 나머지 기능(회원·거래처·업무일지)과 동일하게 JSON으로 저장합니다.
declare(strict_types=1);

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

/* ─────────────────────────────────────────────
   1) 현재 로그인 사용자 식별 (work_log.php와 동일 규칙)
      회원가입 사용자 → member_id / 카카오 → kakao_카카오id
   ───────────────────────────────────────────── */
function fp_user_key(): string {
  return $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? 'guest'));
}

/* 계획서 저장 폴더 (사용자별) */
function fp_base_dir(): string {
  $dir = __DIR__ . '/data/fireplan/' . fp_user_key();
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}
function fp_index_file(): string { return fp_base_dir() . '/_index.json'; }          // 계획서 목록
function fp_plan_file(string $planId): string { return fp_base_dir() . '/' . $planId . '.json'; } // 계획서 1건

/* ── JSON 읽기/쓰기 (원자적 저장) ── */
function fp_read_json(string $file): array {
  if (!file_exists($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || trim($raw) === '') return [];
  $a = json_decode($raw, true);
  return is_array($a) ? $a : [];
}
function fp_write_json(string $file, array $data): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp = $file . '.tmp';
  if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $file);
}

/* CSRF */
function fp_csrf(): string {
  if (empty($_SESSION['fp_csrf'])) $_SESSION['fp_csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['fp_csrf'];
}
function fp_csrf_check(): void {
  if (!hash_equals($_SESSION['fp_csrf'] ?? '-', $_POST['csrf'] ?? '')) { http_response_code(403); exit('잘못된 요청'); }
}

/* ─────────────────────────────────────────────
   2) 용도 10종 (소방청 용도별 서식)
   ───────────────────────────────────────────── */
function fp_usages(): array {
  return [
    'gathering' => ['cat'=>'집회용도',      'nm'=>'집회시설',        'ex'=>'문화·집회·운동·종교·장례시설 등'],
    'commercial'=> ['cat'=>'상업용도',      'nm'=>'상업시설',        'ex'=>'근린생활·판매·위락시설, 지하상가'],
    'residence' => ['cat'=>'주거·숙박용도', 'nm'=>'주거·숙박시설',   'ex'=>'공동주택, 숙박시설, 수련시설'],
    'education' => ['cat'=>'교육·연구용도', 'nm'=>'교육·연구시설',   'ex'=>'학교, 학원, 연구소 등'],
    'medical'   => ['cat'=>'의료·보호용도', 'nm'=>'의료·보호시설',   'ex'=>'병원, 요양·노유자시설 등'],
    'business'  => ['cat'=>'업무·관리용도', 'nm'=>'업무·관리시설',   'ex'=>'오피스, 관공서, 방송통신시설'],
    'industrial'=> ['cat'=>'공업용도',      'nm'=>'공업시설',        'ex'=>'공장, 발전·위험물·자동차 관련시설'],
    'storage'   => ['cat'=>'창고용도',      'nm'=>'창고시설',        'ex'=>'물류창고 등'],
    'tunnel'    => ['cat'=>'지하·터널용도', 'nm'=>'지하·터널',       'ex'=>'지하구, 지하가(터널)'],
    'special'   => ['cat'=>'특수용도',      'nm'=>'특수시설',        'ex'=>'교정 및 군사시설'],
  ];
}

/* ─────────────────────────────────────────────
   3) 소방계획서 필수 항목 (시행령 제27조 제1항, 15개)
      V1: 법정 필수 항목만. V2에서 용도별 세부서식으로 확장.
   ───────────────────────────────────────────── */
function fp_sections(): array {
  return [
    '1' => ['title'=>'소방계획서 필수 항목', 'items'=>[
      '1'  => '일반현황 (위치·구조·연면적·용도·수용인원)',
      '2'  => '소방·방화·전기·가스·위험물시설 현황',
      '3'  => '자체점검계획 및 대응대책',
      '4'  => '소방·피난·방화시설 점검·정비계획',
      '5'  => '피난계획 (피난경로·화재안전취약자)',
      '6'  => '방화구획·마감재·방염물품 유지관리',
      '7'  => '관리 권원 분리 대상물 안전관리',
      '8'  => '공동 소방안전관리 협의',
      '9'  => '자위소방대 조직 및 임무',
      '10' => '화기취급 작업 안전조치·감독',
      '11' => '소방훈련 및 교육 계획',
      '12' => '위험물 저장·취급',
      '13' => '업무수행 기록·유지',
      '14' => '화재 초기대응 (경보·초기소화·피난유도)',
      '15' => '그 밖에 소방서장 요청사항',
    ]],
  ];
}

/* 항목별 근거 조항·도움말 (화면 안내용) */
function fp_item_help(): array {
  return [
    '1'  => '건축물의 위치, 구조, 연면적, 용도, 수용인원 등 기본 현황입니다.',
    '2'  => '설치된 소방시설·방화시설·전기·가스·위험물시설의 종류와 수량을 적습니다.',
    '3'  => '화재 예방을 위한 자체점검 계획과 화재 발생 시 대응대책입니다.',
    '4'  => '소방시설·피난시설·방화시설의 점검 주기와 정비 계획입니다.',
    '5'  => '피난층·피난시설 위치, 피난경로, 노약자 등 화재안전취약자 피난계획입니다.',
    '6'  => '방화구획·제연구획, 내부 마감재료, 방염물품 현황과 유지관리 계획입니다.',
    '7'  => '건물의 관리 권원이 나뉜 경우(임대 등)의 소방안전관리 사항입니다. 해당 없으면 비워두세요.',
    '8'  => '여러 관리자가 공동으로 관리하는 경우의 협의 사항입니다. 해당 없으면 비워두세요.',
    '9'  => '자위소방대 편성과 대원별 임무입니다. (편성표 입력)',
    '10' => '용접 등 화기취급 작업 시 사전 안전조치와 감독 방법입니다.',
    '11' => '연간 소방훈련 및 교육 실시 계획입니다.',
    '12' => '위험물을 저장·취급하는 경우의 관리 사항입니다. 해당 없으면 비워두세요.',
    '13' => '소방안전관리 업무 수행 기록의 유지 방법입니다. (업무수행 기록표와 연계)',
    '14' => '화재 발생 시 화재경보, 초기소화, 피난유도 등 초기대응 방법입니다.',
    '15' => '관할 소방서장이 추가로 요청한 사항이 있으면 적습니다. 없으면 비워두세요.',
  ];
}

/* ─────────────────────────────────────────────
   4) 분기 규칙: 항목1(일반현황) 데이터 → 생략 항목 [코드 => 사유]
      해당 없는 항목은 자동으로 "해당없음" 표시
   ───────────────────────────────────────────── */
function fp_skip_rules(array $s1): array {
  $skips = [];
  if (($s1['split']  ?? '해당없음') === '해당없음') $skips['7']  = '권원분리 해당없음';
  if (($s1['joint']  ?? '해당없음') === '해당없음') $skips['8']  = '공동관리 해당없음';
  if (($s1['hazmat'] ?? '해당없음') === '해당없음') $skips['12'] = '위험물 해당없음';
  return $skips;
}

/* 자위소방대 Type 자동 추천 */
function fp_jawi_type(array $s1): ?string {
  $area  = (float)($s1['area']  ?? 0);
  $staff = (int)  ($s1['staff'] ?? 0);
  if (($s1['public'] ?? '') === '해당') return 'PUBLIC';
  if ($area >= 30000) return 'I';
  if ($staff >= 50)   return 'II';
  if ($area || $staff) return 'III';
  return null;
}

/* ─────────────────────────────────────────────
   5) 계획서 CRUD (JSON)
      계획서 1건 구조:
      { id, usage_code, building_name, plan_date, status, jawi_type,
        created_at, updated_at,
        sections: { "1.1": {data:{...}, is_done:1, is_skipped:0}, ... } }
   ───────────────────────────────────────────── */

/* 새 계획서 생성 → 새 id 반환 */
function fp_create_plan(string $usageCode): string {
  $id = date('YmdHis') . substr((string)random_int(100,999),0,3);   // 시간기반 고유 id
  $now = date('Y-m-d H:i:s');
  $plan = [
    'id'            => $id,
    'usage_code'    => $usageCode,
    'building_name' => '',
    'plan_date'     => date('Y-m-d'),
    'status'        => 'draft',
    'jawi_type'     => null,
    'created_at'    => $now,
    'updated_at'    => $now,
    'sections'      => new stdClass(),  // 빈 객체
  ];
  fp_write_json(fp_plan_file($id), $plan);

  // 목록(_index.json)에 추가
  $idx = fp_read_json(fp_index_file());
  $idx[$id] = ['id'=>$id, 'usage_code'=>$usageCode, 'building_name'=>'', 'status'=>'draft', 'updated_at'=>$now];
  fp_write_json(fp_index_file(), $idx);
  return $id;
}

/* 계획서 로드 (없으면 null) */
function fp_load_plan(string $planId): ?array {
  if ($planId === '' || !preg_match('/^[0-9]+$/', $planId)) return null;
  $file = fp_plan_file($planId);
  if (!file_exists($file)) return null;
  $plan = fp_read_json($file);
  return $plan ?: null;
}

/* 계획서 목록 (최근 수정순) */
function fp_list_plans(): array {
  $idx = fp_read_json(fp_index_file());
  $list = array_values($idx);
  usort($list, fn($a,$b)=> strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
  return $list;
}

/* 계획서 삭제 */
function fp_delete_plan(string $planId): void {
  $file = fp_plan_file($planId);
  if (file_exists($file)) @unlink($file);
  $idx = fp_read_json(fp_index_file());
  unset($idx[$planId]);
  fp_write_json(fp_index_file(), $idx);
}

/* 섹션 데이터 읽기 */
function fp_get_section(string $planId, string $code): array {
  $plan = fp_load_plan($planId);
  if (!$plan) return [];
  $sec = $plan['sections'][$code] ?? null;
  return is_array($sec) && isset($sec['data']) && is_array($sec['data']) ? $sec['data'] : [];
}

/* 섹션 데이터 저장 */
function fp_save_section(string $planId, string $code, array $data, bool $done = true): void {
  $plan = fp_load_plan($planId);
  if (!$plan) return;
  if (!isset($plan['sections']) || !is_array($plan['sections'])) $plan['sections'] = [];
  $skipped = $plan['sections'][$code]['is_skipped'] ?? 0;
  $plan['sections'][$code] = ['data'=>$data, 'is_done'=>$done?1:0, 'is_skipped'=>$skipped];
  $plan['updated_at'] = date('Y-m-d H:i:s');
  fp_write_json(fp_plan_file($planId), $plan);
  fp_touch_index($planId, $plan);
}

/* 분기 규칙에 따라 생략 서식 표시 */
function fp_apply_skips(string $planId, array $skips): void {
  $plan = fp_load_plan($planId);
  if (!$plan) return;
  if (!isset($plan['sections']) || !is_array($plan['sections'])) $plan['sections'] = [];
  // 전체 생략 해제 후 다시 지정
  foreach ($plan['sections'] as $code => &$sec) {
    if (is_array($sec)) $sec['is_skipped'] = 0;
  }
  unset($sec);
  foreach (array_keys($skips) as $code) {
    if (!isset($plan['sections'][$code]) || !is_array($plan['sections'][$code])) {
      $plan['sections'][$code] = ['data'=>[], 'is_done'=>0, 'is_skipped'=>1];
    } else {
      $plan['sections'][$code]['is_skipped'] = 1;
    }
  }
  $plan['updated_at'] = date('Y-m-d H:i:s');
  fp_write_json(fp_plan_file($planId), $plan);
}

/* 공유필드(건물명·작성일)·자위소방대 Type 갱신 */
function fp_update_shared(string $planId, string $buildingName, ?string $jawiType, ?string $planDate = null): void {
  $plan = fp_load_plan($planId);
  if (!$plan) return;
  $plan['building_name'] = $buildingName;
  $plan['jawi_type']     = $jawiType;
  if ($planDate !== null && $planDate !== '') $plan['plan_date'] = $planDate;   // 작성일 (사용자 지정)
  $plan['updated_at']    = date('Y-m-d H:i:s');
  fp_write_json(fp_plan_file($planId), $plan);
  fp_touch_index($planId, $plan);
}

/* 계획서 상태(완료/작성중) 변경 */
function fp_set_status(string $planId, string $status): void {
  $plan = fp_load_plan($planId);
  if (!$plan) return;
  $plan['status'] = ($status === 'done') ? 'done' : 'draft';
  $plan['updated_at'] = date('Y-m-d H:i:s');
  fp_write_json(fp_plan_file($planId), $plan);
  fp_touch_index($planId, $plan);
}

/* 목록 캐시(_index.json) 동기화 */
function fp_touch_index(string $planId, array $plan): void {
  $idx = fp_read_json(fp_index_file());
  $idx[$planId] = [
    'id'            => $planId,
    'usage_code'    => $plan['usage_code'] ?? '',
    'building_name' => $plan['building_name'] ?? '',
    'status'        => $plan['status'] ?? 'draft',
    'updated_at'    => $plan['updated_at'] ?? date('Y-m-d H:i:s'),
  ];
  fp_write_json(fp_index_file(), $idx);
}

/* 진행률 계산용: 완료/생략 개수 */
function fp_count_states(array $plan): array {
  $done = 0; $skip = 0;
  foreach (($plan['sections'] ?? []) as $sec) {
    if (!is_array($sec)) continue;
    if (!empty($sec['is_skipped'])) { $skip++; continue; }
    if (!empty($sec['is_done']))    $done++;
  }
  return ['done'=>$done, 'skip'=>$skip];
}
