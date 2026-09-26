<?php
// fire_plan_db.php — 소방계획서 공통 (JSON 파일 저장 방식)
//   TWORIX의 나머지 기능(회원·거래처·업무일지)과 동일하게 JSON으로 저장합니다.
declare(strict_types=1);

require_once __DIR__ . '/user_key.php';

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

/* ─────────────────────────────────────────────
   1) 현재 로그인 사용자 식별 (work_log.php와 동일 규칙)
      회원가입 사용자 → member_id / 카카오 → kakao_카카오id
   ───────────────────────────────────────────── */
function fp_user_key(): string {
  return app_user_key();
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
  if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
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
  if (($s1['split']  ?? '') === '해당없음') $skips['7']  = '권원분리 해당없음';
  if (($s1['joint']  ?? '') === '해당없음') $skips['8']  = '공동관리 해당없음';
  if (($s1['hazmat'] ?? '') === '해당없음') $skips['12'] = '위험물 해당없음';
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
function fp_plan_year(array $plan): int {
  $year = (int)($plan['plan_year'] ?? 0);
  if ($year >= 1900 && $year <= 2200) return $year;
  foreach (['plan_date','created_at'] as $key) {
    $year = (int)substr((string)($plan[$key] ?? ''),0,4);
    if ($year >= 1900 && $year <= 2200) return $year;
  }
  return (int)date('Y');
}
function fp_create_plan(string $usageCode, ?int $planYear = null): string {
  $id = date('YmdHis') . substr((string)random_int(100,999),0,3);   // 시간기반 고유 id
  $now = date('Y-m-d H:i:s');
  $plan = [
    'id'            => $id,
    'usage_code'    => $usageCode,
    'building_name' => '',
    'plan_date'     => date('Y-m-d'),
    'plan_year'     => $planYear ?? (int)date('Y'),
    'status'        => 'draft',
    'jawi_type'     => null,
    'created_at'    => $now,
    'updated_at'    => $now,
    'sections'      => new stdClass(),  // 빈 객체
  ];
  fp_write_json(fp_plan_file($id), $plan);

  // 목록(_index.json)에 추가
  $idx = fp_read_json(fp_index_file());
  $idx[$id] = ['id'=>$id, 'usage_code'=>$usageCode, 'plan_year'=>$plan['plan_year'], 'plan_date'=>$plan['plan_date'], 'created_at'=>$now, 'building_name'=>'', 'status'=>'draft', 'updated_at'=>$now];
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
  $uid=fp_user_key();
  if(!preg_match('/^[0-9]{1,64}$/D',$planId)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid))throw new RuntimeException('삭제할 계획서를 확인해 주세요.');
  require_once __DIR__.'/manager_common.php';
  mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($uid,$planId){
    $file=fp_plan_file($planId);
    if(file_exists($file)&&!@unlink($file))throw new RuntimeException('계획서를 삭제하지 못했습니다. 다시 시도해 주세요.');
    $prefix='__fp_'.$planId.'_';
    foreach($rows as $id=>$row){
      if(is_array($row)&&($row['uid']??'')===$uid&&strpos((string)($row['field']??''),$prefix)===0)unset($rows[$id]);
    }
  },true);
  $idx=fp_read_json(fp_index_file());unset($idx[$planId]);
  if(!fp_write_json(fp_index_file(),$idx))throw new RuntimeException('계획서 목록을 갱신하지 못했습니다. 다시 시도해 주세요.');
}

/* 섹션 데이터 읽기 */
function fp_get_section(string $planId, string $code): array {
  $plan = fp_load_plan($planId);
  if (!$plan) return [];
  $sec = $plan['sections'][$code] ?? null;
  return is_array($sec) && isset($sec['data']) && is_array($sec['data']) ? $sec['data'] : [];
}

/* 섹션 데이터 저장 */
function fp_save_section(string $planId, string $code, array $data, bool $done = true): bool {
  $plan = fp_load_plan($planId);
  if (!$plan) return false;
  if (!isset($plan['sections']) || !is_array($plan['sections'])) $plan['sections'] = [];
  $skipped = $plan['sections'][$code]['is_skipped'] ?? 0;
  $plan['sections'][$code] = ['data'=>$data, 'is_done'=>$done?1:0, 'is_skipped'=>$skipped];
  $plan['updated_at'] = date('Y-m-d H:i:s');
  if (!fp_write_json(fp_plan_file($planId), $plan)) return false;
  fp_touch_index($planId, $plan);
  return true;
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
    'plan_year'     => fp_plan_year($plan),
    'plan_date'     => $plan['plan_date'] ?? '',
    'created_at'    => $plan['created_at'] ?? '',
    'usage_code'    => $plan['usage_code'] ?? '',
    'building_name' => $plan['building_name'] ?? '',
    'status'        => $plan['status'] ?? 'draft',
    'updated_at'    => $plan['updated_at'] ?? date('Y-m-d H:i:s'),
  ];
  fp_write_json(fp_index_file(), $idx);
}

/* 진행률 계산용: 완료/생략 개수 */
/* 문답과 표 편집이 동일한 필드명을 사용합니다. 빈 값은 '없음'으로 추정하지 않습니다. */
function fp_chat_schema(): array {
  $s = [];
  $add = function(string $code, string $key, string $label, string $type = 'text', array $options = [], string $hint = '') use (&$s): void {
    $s[$code][$key] = ['key'=>$key, 'label'=>$label, 'type'=>$type, 'options'=>$options, 'hint'=>$hint];
  };
  foreach (['name'=>'건물 이름','addr'=>'소재지','rep_name'=>'대표자 이름','rep_tel'=>'대표자 연락처','mgr_name'=>'소방안전관리자 이름','mgr_tel'=>'관리자 연락처','recv_loc'=>'화재수신기 위치','main_use'=>'건물 주용도','floors'=>'지상·지하 층수','structure'=>'건물 구조','roof'=>'지붕 형태'] as $k=>$l) $add('1',$k,$l);
  $add('1','grade','소방안전관리 등급','choice',['특급','1급','2급','3급']);
  $add('1','approval','사용승인일','date');
  $add('1','plan_date','계획서 작성일','date');
  foreach (['area'=>'연면적(㎡)','bld_area'=>'건축면적(㎡)','height'=>'높이(m)','staff'=>'근무인원(명)','resident'=>'거주인원(명)','use_cnt'=>'최대 수용인원(명)'] as $k=>$l) $add('1',$k,$l,'number');
  $add('1','elev','설치된 승강기','multi',['승용','비상용','피난용']);
  $add('1','park','주차장 형태','multi',['옥내','옥외','자주식','기계식']);
  $add('1','ev','전기차 충전소','choice',['있음','없음']);
  $add('1','stairs','계단 종류','multi',['특별피난계단','직통계단','피난계단','옥외계단']);
  foreach (['wd_day'=>'평일 주간 운영시간','wd_night'=>'평일 야간 운영시간','hd_day'=>'휴일 주간 운영시간','hd_night'=>'휴일 야간 운영시간'] as $k=>$l) $add('1',$k,$l,'text',[],'인원수가 아닌 운영시간입니다. 운영하지 않으면 휴무라고 적어주세요.');
  foreach (['public'=>'공공기관에 해당하나요?','split'=>'관리 권원이 나뉘어 있나요?','joint'=>'공동으로 소방안전관리를 하나요?','hazmat'=>'위험물을 저장·취급하나요?'] as $k=>$l) $add('1',$k,$l,'choice',['해당','해당없음']);
  $add('1','ins','화재보험 가입 여부','choice',['가입','미가입']);
  foreach (['ins_co'=>'보험사','ins_term'=>'보험 가입기간','ins_life'=>'대인 보상금액','ins_prop'=>'대물 보상금액'] as $k=>$l) $add('1',$k,$l);
  $groups = [
    'fire_ext'=>['소화설비',['소화기구 및 자동소화장치','옥내소화전설비','옥외소화전설비','스프링클러설비','간이스프링클러설비','화재조기진압용 스프링클러설비','물분무소화설비','미분무소화설비','포소화설비','이산화탄소소화설비','할론소화설비','할로겐화합물 및 불활성기체소화설비','분말소화설비','강화액소화설비','고체에어졸소화설비']],
    'alarm'=>['경보설비',['단독경보형감지기','비상경보설비','자동화재탐지설비 및 시각경보기','화재알림설비','비상방송설비','통합감시시설','자동화재속보설비','누전경보기','가스누설경보기']],
    'escape'=>['피난구조설비',['피난기구','공기안전매트','피난사다리','(간이)완강기','미끄럼대','구조대','다수인피난장비','승강식피난기','하향식피난구용내림식사다리','인명구조기구','피난유도선','유도등','비상조명등','유도표지','휴대용비상조명등']],
    'water'=>['소화용수설비',['상수도소화용수설비','소화수조 및 저수조']],
    'active'=>['소화활동설비',['거실제연설비','부속실 등 제연설비','연결송수관설비','연결살수설비','비상콘센트설비','무선통신보조설비','연소방지설비']],
    'etc_fac'=>['기타시설',['전기시설','가스시설','위험물시설','방화시설']],
  ];
  foreach ($groups as $k=>$g) $add('2',$k,$g[0],'multi',$g[1]);
  $add('2','memo','설치장소·수량·규격 등 특이사항','memo');
  $add('3','comprehensive','종합점검도 계획에 포함하나요?','choice',['제외','포함'], '3급의 일반적인 정기점검은 제외로 제안합니다. 최초점검·설비·용도에 따른 예외는 관할 소방서나 점검업체에 확인하세요.');
  foreach (['3'=>['작동점검','종합점검','외관점검'],'4'=>['소방시설','피난시설','방화시설']] as $code=>$labels) {
    foreach ($labels as $i=>$l) foreach (['when'=>'시기','who'=>'담당자','note'=>'비고'] as $f=>$label) $add((string)$code,'r'.($i+1).'_'.$f,$l.' '.$label);
    $add((string)$code,'memo',$code == '3' ? '불량 발견 시 어떻게 조치하나요?' : '고장·불량을 어떻게 정비하나요?','memo');
  }
  foreach (['floor_exit'=>'피난층·출구','route'=>'층별 피난경로','weak_loc'=>'화재안전취약자 위치','weak_plan'=>'취약자 피난보조 방법','assembly'=>'대피 후 집결지'] as $k=>$l) $add('5',$k,$l,in_array($k,['route','weak_plan'],true)?'memo':'text');
  $add('5','weak_cnt','화재안전취약자 인원(명)','number');
  $add('5','evac','피난기구','multi',['완강기','구조대','피난사다리','공기안전매트','승강식피난기','유도등·유도표지']);
  foreach (['bkchk'=>['방화구획 기준',['면적별','층별','용도별']], 'bkdoor'=>['방화구획 설비',['방화문','자동폐쇄장치','방화셔터','방화스크린']], 'smoke'=>['제연설비',['거실제연','부속실제연','전실제연','해당없음']], 'flame'=>['방염물품',['커튼류','카펫','벽지류','합판·목재','무대막','섬유판','해당없음']]] as $k=>$g) $add('6',$k,$g[0],'multi',$g[1]);
  $add('6','finish','내부 마감재');
  $add('6','flame_cert','방염성능검사 필증','choice',['있음','없음']);
  $add('6','memo','방화구획·마감재·방염물품을 어떻게 유지관리하나요?','memo');
  foreach (['7'=>'권원별 관리 범위와 공용부 책임자는 누구인가요?','8'=>'공동관리 협의 구성·주기·협의사항은 무엇인가요?','9'=>'자위소방대 조직과 각 대원의 임무를 확인해 주세요.','10'=>'화기취급 작업 전·중·후 안전조치는 어떻게 하나요?','12'=>'위험물의 종류·수량·위치·관리방법은 무엇인가요?','13'=>'월별 업무기록의 작성자·보관장소·관리방법은 무엇인가요?','15'=>'관할 소방서에서 추가로 요청한 사항이 있나요?'] as $c=>$l) $add((string)$c,'memo',$l,'memo');
  foreach (['소방훈련','소방교육','신규자 교육'] as $i=>$l) foreach (['when'=>'시기','who'=>'대상','how'=>'방법'] as $f=>$label) $add('11','t'.($i+1).'_'.$f,$l.' '.$label);
  $add('11','memo','훈련·교육 내용과 기존 실시기록','memo');
  foreach (['화재경보','119 신고','초기소화','피난유도','집결지 인원확인'] as $i=>$l) $add('14','s'.($i+1),$l.'는 누가 어떻게 하나요?','memo');
  $add('14','memo','소방차 진입경로·야간·휴일 등 추가 대응사항','memo');
  ksort($s, SORT_NUMERIC);
  return $s;
}

function fp_is_grade3(array $s1): bool { return preg_replace('/\s+/u','',(string)($s1['grade'] ?? '')) === '3급'; }
function fp_comprehensive(array $s1, array $s3): bool {
  if (($s3['comprehensive'] ?? '') === '포함') return true;
  if (($s3['comprehensive'] ?? '') === '제외') return false;
  return !fp_is_grade3($s1);
}
function fp_inspection_defaults(array $s1, array $s3 = []): array {
  $d = ['r3_when'=>'매월 1일']; // 사용자 관리 일정이며 법정 일자로 표기하지 않습니다.
  if (fp_is_grade3($s1) && !fp_comprehensive($s1,$s3)) {
    $d['comprehensive'] = '제외';
    $date = preg_replace('/[^0-9]/','',(string)($s1['approval'] ?? ''));
    if (strlen($date) === 8 && checkdate((int)substr($date,4,2),(int)substr($date,6,2),(int)substr($date,0,4))) {
      $d['r1_when'] = '매년 '.(int)substr($date,4,2).'월 (연 1회, 해당 월 말일까지)';
    }
  }
  if (trim((string)($s1['mgr_name'] ?? '')) !== '') $d['r3_who'] = $s1['mgr_name'];
  return $d;
}
function fp_chat_visible(string $code, string $key, array $s1, array $s3): bool {
  if (isset(fp_skip_rules($s1)[$code])) return false;
  if ($code === '1' && in_array($key,['ins_co','ins_term','ins_life','ins_prop'],true)) return ($s1['ins'] ?? '') === '가입';
  if ($code === '3' && strpos($key,'r2_') === 0) return fp_comprehensive($s1,$s3);
  return true;
}

/* 최신 저장순으로 읽고, 대장/부대장의 객체형·구형 배열형을 모두 지원합니다. */
function fp_team_source(): array {
  $key = fp_user_key();
  $out = ['memo'=>'','total'=>0];
  if ($key === '') return $out;
  $rows = array_values(array_filter(fp_read_json(__DIR__.'/data/fireplan/'.$key.'/_jawi.json'),'is_array'));
  usort($rows,fn($a,$b)=>strcmp((string)($b['saved'] ?? $b['updated_at'] ?? $b['created'] ?? ''),(string)($a['saved'] ?? $a['updated_at'] ?? $a['created'] ?? '')));
  $p = $rows[0] ?? [];
  $lines = []; $people = [];
  $person = function(array $m, string $role) use (&$lines,&$people): void {
    $name = trim((string)($m['name'] ?? $m[0] ?? ''));
    if ($name === '') return;
    $tel = preg_replace('/\D/','',(string)($m['tel'] ?? $m[1] ?? ''));
    $people[$name.'|'.$tel] = true;
    $task = trim((string)($m['task'] ?? $m[2] ?? ''));
    $lines[] = $role.' : '.$name.($task !== '' ? ' — '.$task : ' — 임무 확인 필요');
  };
  foreach (['cmd'=>'대장','deputy'=>'부대장'] as $k=>$l) if (is_array($p[$k] ?? null)) $person($p[$k],$l);
  foreach ((array)($p['groups'] ?? []) as $g) {
    if (!is_array($g)) continue;
    foreach ((array)($g['members'] ?? []) as $m) if (is_array($m)) $person($m,(string)($g['name'] ?? '활동조'));
  }
  $out['total'] = count($people);
  if ($lines) $out['memo'] = "자위소방대는 다음과 같이 편성하고 각 대원은 지정된 임무를 수행한다.\n\n".implode("\n",$lines)."\n\n편성 변경 시 편성표와 소방계획서를 함께 갱신한다.";
  return $out;
}

/* 원본은 읽기만 합니다. 문답에서 확인한 값만 계획서에 저장됩니다. */
function fp_chat_sources(array $bi, array $evac, ?int $year = null, ?array $selected = null): array {
  $d = []; $names = []; $mgr = [];
  foreach ((array)($bi['mgrs'] ?? []) as $m) {
    if (!is_array($m) || trim((string)($m['name'] ?? '')) === '') continue;
    if (!$mgr) $mgr = $m;
    if (strpos((string)($m['type'] ?? ''),'주') === 0) { $mgr = $m; break; }
  }
  foreach (['name'=>'name','addr'=>'address','rep_name'=>'rep','rep_tel'=>'tel','grade'=>'grade','main_use'=>'use','area'=>'area_t','bld_area'=>'bd_area_arch','structure'=>'bd_struct','height'=>'bd_height'] as $to=>$from) $d['1'][$to] = (string)($bi[$from] ?? '');
  $d['1']['mgr_name'] = (string)($mgr['name'] ?? ''); $d['1']['mgr_tel'] = (string)($mgr['tel'] ?? '');
  $date = preg_replace('/\D/','',(string)($bi['bd_use_apr'] ?? ''));
  if (strlen($date) === 8 && checkdate((int)substr($date,4,2),(int)substr($date,6,2),(int)substr($date,0,4))) $d['1']['approval'] = substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2);
  $floors = [];
  foreach (['floor_b'=>'지하','floor_a'=>'지상'] as $k=>$label) if (trim((string)($bi[$k] ?? '')) !== '') $floors[] = $label.' '.$bi[$k].'층';
  $d['1']['floors'] = implode(' / ',$floors);
  // area_f는 바닥면적, wd_day 등은 시간대별 인원으로 편집 화면의 다른 의미 필드에 복사하지 않습니다.
  if ((int)($bi['bd_elev'] ?? 0) > 0) $d['1']['elev'] = ['승용'];
  if (trim((string)($bi['name'] ?? '')) !== '') $names[] = '건물 기본정보·건축물대장';
  $d['5'] = function_exists('epc_to_fire_section') ? epc_to_fire_section($evac) : [];
  if (trim((string)($d['5']['assembly'] ?? '')) === '') $d['5']['assembly'] = (string)($bi['assembly_kind'] ?? '');
  if (!empty($d['5']['route']) || !empty($d['5']['assembly'])) $names[] = '피난계획·집결지';
  foreach (['alarm_method'=>'s1','reporter'=>'s2','headcount_method'=>'s5'] as $from=>$to) if (trim((string)($evac[$from] ?? '')) !== '') $d['14'][$to] = (string)$evac[$from];
  $routeNote = trim((string)($bi['fire_engine_route_note'] ?? ''));
  if ($routeNote !== '') { $d['14']['memo'] = '소방차 진입 시 주의사항: '.$routeNote; $names[] = '소방차 진입로'; }
  $team = fp_team_source();
  if ($team['memo'] !== '') { $d['9']['memo'] = $team['memo']; $names[] = '자위소방대 편성·대원별 임무'; }
  $histories = []; $months = [];
  $key = fp_user_key();
  if ($key !== '') {
    $fixed = fp_read_json(__DIR__.'/data/worklog/'.preg_replace('/[^A-Za-z0-9_]/','_',$key).'/building.json');
    foreach (['sprinkler'=>'스프링클러설비','hydrant'=>'옥내소화전설비'] as $k=>$label) if (($fixed[$k] ?? '') === 'yes') $d['2']['fire_ext'][] = $label;
    $noteLines = [];
    foreach (['note_sobang'=>'소방시설','note_pinan'=>'피난·방화시설','note_hwagi'=>'화기취급','note_etc'=>'기타'] as $k=>$label) {
      $v = trim((string)($fixed[$k] ?? $bi[$k] ?? ''));
      if ($v !== '') $noteLines[] = $label.': '.$v;
    }
    if ($noteLines) { $d['13']['memo'] = "매월 업무수행 기록표를 작성하고 관리한다.\n작성자: ".($mgr['name'] ?? '소방안전관리자')."\n\n".implode("\n",$noteLines); $names[] = '월별기록 기본값'; }
    if ($year !== null) {
      $base = __DIR__.'/data/worklog/'.preg_replace('/[^A-Za-z0-9_]/','_',$key);
      for ($month=1;$month<=12;$month++) {
        $file=$base.'/m'.$year.'-'.sprintf('%02d',$month).'.json';
        if (is_file($file) && fp_read_json($file)) $months[]=$month.'월';
      }
      if ($months) $d['13']['memo'] = trim(($d['13']['memo'] ?? '')."\n\n".$year.'년 저장된 월별 기록: '.implode(', ',$months));
    }
    $history = [];
    foreach (['train'=>['train_date','소방훈련·교육'],'jawi'=>['edu_date','자위소방대 교육']] as $folder=>$meta) {
      $dates = [];
      foreach (fp_read_json(__DIR__.'/data/'.$folder.'/'.$key.'/_index.json') as $r) {
        if (!is_array($r)) continue;
        $v = (string)($r[$meta[0]] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$v) && $v <= date('Y-m-d') && ($year === null || (int)substr($v,0,4)===$year)) $dates[] = $v;
      }
      if ($dates) { rsort($dates); $histories[$folder]=$meta[1].' 실시 기록: '.implode(', ',array_unique($dates)); $history[] = $histories[$folder]; $names[] = $meta[1].' 기록'; }
    }
    if ($history) $d['11']['memo'] = implode("\n",$history); // 과거 실시일을 미래 계획일로 간주하지 않습니다.
  }
  require_once __DIR__.'/building_info.php';require_once __DIR__.'/building_facilities_common.php';
  $inventory=bf_load();
  if(!empty($inventory['revision'])){
    foreach(bf_catalog() as $category=>$group){
      $values=(array)($d['2'][$category]??[]);
      foreach($group[1] as $name){$v=$inventory['items'][bf_id($name)]['status']??'unknown';if($v==='yes')$values[]=$name;elseif($v==='no')$values=array_values(array_diff($values,[$name]));}
      $d['2'][$category]=array_values(array_unique($values));
    }
    $counts=bf_counts($inventory);
    $d['2']['memo']='시설현황: 설치 있음 '.$counts['present'].'종 / 미확인 '.$counts['unknown'].'종'."\n".bf_summary($inventory);
    $names[]='건물 시설현황 체크리스트';
  }
  $groups = [
    'basic'=>['title'=>'기본정보','detail'=>'현재 건물정보·관리자·진입로 메모','data'=>['1'=>$d['1'] ?? [],'14'=>isset($d['14']['memo'])?['memo'=>$d['14']['memo']]:[]]],
    'team'=>['title'=>'자위소방대 편성','detail'=>'현재 편성된 인원과 대원별 임무','data'=>['9'=>$d['9'] ?? []]],
    'monthly'=>['title'=>'시설현황·매월 기록','detail'=>($year ?? date('Y')).'년 기록 '.count($months).'개월 · 공통 시설현황 포함','data'=>['2'=>$d['2'] ?? [],'13'=>$d['13'] ?? []]],
    'jawi_education'=>['title'=>'자위소방대 교육','detail'=>isset($histories['jawi'])?'선택 연도의 실시 기록':'선택 연도의 기록 없음','data'=>['11'=>isset($histories['jawi'])?['memo'=>$histories['jawi']]:[]]],
    'training'=>['title'=>'소방훈련·교육','detail'=>isset($histories['train'])?'선택 연도의 실시 기록':'선택 연도의 기록 없음','data'=>['11'=>isset($histories['train'])?['memo'=>$histories['train']]:[]]],
    'evacuation'=>['title'=>'피난계획','detail'=>'현재 층별 피난경로·집결지·대응방법','data'=>['5'=>$d['5'] ?? [],'14'=>array_intersect_key($d['14'] ?? [],array_flip(['s1','s2','s5']))]],
  ];
  $merged=[]; $names=[];
  foreach ($groups as $id=>&$group) {
    $group['available']=false;
    foreach ($group['data'] as $fields) foreach ($fields as $v) if (is_array($v)?count($v)>0:trim((string)$v)!=='') $group['available']=true;
    if ($selected!==null && !in_array($id,$selected,true)) continue;
    if ($group['available']) $names[]=$group['title'];
    foreach ($group['data'] as $code=>$fields) foreach ($fields as $key=>$value) {
      if ($code==11 && $key==='memo' && isset($merged[$code][$key])) $merged[$code][$key].="\n".$value;
      else $merged[$code][$key]=$value;
    }
  }
  unset($group);
  return ['data'=>$merged,'names'=>$names,'groups'=>$groups];
}

function fp_count_states(array $plan): array {
  $done = 0; $skip = 0;
  foreach (($plan['sections'] ?? []) as $sec) {
    if (!is_array($sec)) continue;
    if (!empty($sec['is_skipped'])) { $skip++; continue; }
    if (!empty($sec['is_done']))    $done++;
  }
  return ['done'=>$done, 'skip'=>$skip];
}
