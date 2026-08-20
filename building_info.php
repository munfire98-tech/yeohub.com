<?php
// building_info.php — 건물 기본정보 (여러 법정서식이 공유)
// 한 번 입력하면 12호·28호·13호·소방계획서에서 자동으로 채워집니다.
declare(strict_types=1);

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

require_once __DIR__ . '/user_key.php';

/** 사용자 키.
 *  회원을 특정하지 못하면 '' 를 돌려줍니다.
 *  (예전에는 kakao_guest 로 묶여 여러 회원이 같은 파일을 공유했습니다) */
function bi_user_key(): string {
  return app_user_key();
}

/** 공용 저장 위치. 회원을 특정하지 못하면 '' */
function bi_file(): string {
  $k = bi_user_key();
  if ($k === '') return '';
  $dir = __DIR__ . '/data/building/' . $k;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/info.json';
}

/** 기존 work_log 건물정보 (마이그레이션 원본) */
function bi_legacy_file(): string {
  $k = bi_user_key();
  if ($k === '') return '';
  return __DIR__ . '/data/worklog/' . $k . '/building.json';
}

function bi_read_json(string $f): array {
  if ($f === '' || !is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function bi_write_json(string $f, array $d): bool {
  if ($f === '') return false;
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

/** 표준 구조 (빈 값 기본형) */
function bi_blank(): array {
  return [
    // 대상물
    'name'       => '',   // 대상명(상호)
    'use'        => '',   // 용도
    'grade'      => '',   // 특급/1급/2급/3급
    'address'    => '',   // 소재지
    'rep'        => '',   // 대표자
    'tel'        => '',   // 전화번호
    // 규모
    'floor_b'    => '',   // 지하층
    'floor_a'    => '',   // 지상층
    'area_t'     => '',   // 연면적
    'area_f'     => '',   // 바닥면적
    'dongsu'     => '',   // 동수
    // 소방안전관리자 (최대 4명)
    'mgrs'       => [],   // [{name, appt, qual, type(주/보조), tel}]
    // 근무인원 (13호)
    'wd_day'     => '',   // 평일 주간
    'wd_night'   => '',   // 평일 야간
    'hd_day'     => '',   // 휴일 주간
    'hd_night'   => '',   // 휴일 야간
    // 12호 확인내용 기본값
    'note_sobang'=> '',
    'note_pinan' => '',
    'note_hwagi' => '',
    'note_etc'   => '',
    // ── 위치 좌표 (지도 표시용, 주소 검색 시 자동으로 채워짐) ──
    'bd_lat'      => '',   // 위도
    'bd_lng'      => '',   // 경도
    // ── 집결지 (화재 시 대피 후 모이는 장소) ─────────────────
    'assembly_lat'  => '', // 집결지 위도
    'assembly_lng'  => '', // 집결지 경도
    'assembly_kind' => '', // 어떤 곳인지 (예: 주차장, 앞 공터)
    // ── 건축물대장 상세 (자동조회로 채워짐) ─────────────────
    'bd_struct'   => '',   // 구조 (strctCdNm, 예: 철근콘크리트구조)
    'bd_struct_etc'=> '',  // 기타구조 (etcStrct)
    'bd_height'   => '',   // 높이(m) (heit)
    'bd_area_plat'=> '',   // 대지면적(㎡) (platArea)
    'bd_bcrat'    => '',   // 건폐율(%) (bcRat)
    'bd_vlrat'    => '',   // 용적률(%) (vlRat)
    'bd_area_vl'  => '',   // 용적률산정연면적(㎡) (vlRatEstmTotArea)
    'bd_main_bld' => '',   // 주건축물수 (mainBldCnt)
    'bd_atch_bld' => '',   // 부속건축물수 (atchBldCnt)
    'bd_hhld'     => '',   // 세대수 (hhldCnt)
    'bd_family'   => '',   // 가구수 (fmlyCnt)
    'bd_ho'       => '',   // 호수 (hoCnt)
    'bd_park'     => '',   // 총주차대수 (totPkngCnt)
    'bd_elev'     => '',   // 승용승강기수 (rideUseElvtCnt)
    'bd_use_main' => '',   // 주용도(원문) (mainPurpsCdNm)
    'bd_use_etc'  => '',   // 기타용도 (etcPurps)
    'bd_pms_day'  => '',   // 허가일 (pmsDay)
    'bd_stcns_day'=> '',   // 착공일 (stcnsDay)
    'bd_use_apr'  => '',   // 사용승인일 (useAprDay)
    'bd_seismic'  => '',   // 내진설계 적용여부 (rserthqkDsgnApplyYn)
    'bd_seismic_ablty'=> '', // 내진능력 (rserthqkAblty)
    'bd_energy'   => '',   // 에너지효율등급 (engrGrade)
    'bd_road_addr'=> '',   // 도로명대지위치 (newPlatPlc)
    'bd_dongs'    => '',   // 여러 동일 때 동별 층수·구조 (한 줄에 한 동)
    'bd_looked'   => '',   // 자동조회 시각 (YYYY-mm-dd HH:ii:ss)
    'updated'    => '',
  ];
}

/**
 * 기본정보 읽기.
 * 공용 파일이 없으면 기존 work_log 건물정보를 자동으로 옮겨옵니다.
 */
function bi_load(): array {
  // 회원을 특정하지 못하면 아무것도 읽지 않는다 (남의 데이터 노출 방지)
  if (bi_user_key() === '') return bi_blank();

  $d = bi_read_json(bi_file());

  // 최초 1회: 기존 work_log 건물정보 승계
  if (!$d) {
    $old = bi_read_json(bi_legacy_file());
    if ($old) {
      $d = bi_blank();
      $d['name']    = (string)($old['sangho']  ?? '');
      $d['grade']   = (string)($old['grade']   ?? '');
      $d['address'] = (string)($old['address'] ?? '');
      $d['floor_b'] = (string)($old['floor_b'] ?? '');
      $d['floor_a'] = (string)($old['floor_a'] ?? '');
      $d['area_t']  = (string)($old['area_t']  ?? '');
      $d['area_f']  = (string)($old['area_f']  ?? '');
      $d['dongsu']  = (string)($old['dongsu']  ?? '');
      $d['note_sobang'] = (string)($old['note_sobang'] ?? '');
      $d['note_pinan']  = (string)($old['note_pinan']  ?? '');
      $d['note_hwagi']  = (string)($old['note_hwagi']  ?? '');
      $d['note_etc']    = (string)($old['note_etc']    ?? '');
      // 수행자 성명 → 소방안전관리자 1번(주)
      $perf = trim((string)($old['performer'] ?? ''));
      if ($perf !== '') {
        $d['mgrs'][] = ['name'=>$perf, 'appt'=>'', 'qual'=>'', 'type'=>'주', 'tel'=>''];
      }
      bi_write_json(bi_file(), $d);
    }
  }

  return array_merge(bi_blank(), $d);
}

function bi_save(array $d): bool {
  if (bi_user_key() === '') return false;   // 회원을 모르면 저장하지 않는다

  $base = bi_blank();
  $out  = [];
  foreach ($base as $k => $v) {
    if ($k === 'mgrs') continue;
    $out[$k] = is_string($v) ? trim((string)($d[$k] ?? '')) : ($d[$k] ?? $v);
  }
  // 등급 검증
  if (!in_array($out['grade'], ['특급','1급','2급','3급'], true)) $out['grade'] = '';

  // 소방안전관리자 최대 4명
  $mgrs = [];
  $src = $d['mgrs'] ?? [];
  if (is_array($src)) {
    foreach (array_slice($src, 0, 4) as $m) {
      $nm  = trim((string)($m['name'] ?? ''));
      $tel = trim((string)($m['tel']  ?? ''));
      if ($nm === '' && $tel === '') continue;
      $ty = (string)($m['type'] ?? '');
      $mgrs[] = [
        'name' => $nm,
        'appt' => trim((string)($m['appt'] ?? '')),
        'qual' => trim((string)($m['qual'] ?? '')),
        'type' => in_array($ty, ['주','보조'], true) ? $ty : '',
        'tel'  => $tel,
      ];
    }
  }
  $out['mgrs']    = $mgrs;
  $out['updated'] = date('Y-m-d H:i:s');

  // work_log 쪽도 동기화(기존 화면 호환)
  $legacy = bi_read_json(bi_legacy_file());
  $legacy['sangho']  = $out['name'];
  $legacy['grade']   = $out['grade'];
  $legacy['address'] = $out['address'];
  $legacy['floor_b'] = $out['floor_b'];
  $legacy['floor_a'] = $out['floor_a'];
  $legacy['area_t']  = $out['area_t'];
  $legacy['area_f']  = $out['area_f'];
  $legacy['dongsu']  = $out['dongsu'];
  $legacy['performer'] = $mgrs[0]['name'] ?? ($legacy['performer'] ?? '');
  $legacy['note_sobang'] = $out['note_sobang'];
  $legacy['note_pinan']  = $out['note_pinan'];
  $legacy['note_hwagi']  = $out['note_hwagi'];
  $legacy['note_etc']    = $out['note_etc'];
  bi_write_json(bi_legacy_file(), $legacy);

  return bi_write_json(bi_file(), $out);
}

/** 입력 완료 여부 (대상명 기준 — 기존 호환용) */
function bi_has(): bool {
  if (bi_user_key() === '') return false;
  $d = bi_load();
  return trim((string)$d['name']) !== '';
}

/* ── 얼마나 채워졌는지 ────────────────────────────────────
   이름만 적어도 "완료"로 보이면, 정작 다른 서식이 가져다 쓸
   주소·등급·규모가 비어 있는 채로 진행됩니다.
   진행 현황 표시는 이쪽을 쓰는 편이 정확합니다. */

/** 다른 서식들이 실제로 가져다 쓰는 항목들 */
function bi_required_fields(): array {
  return [
    'name'    => '대상명',
    'address' => '소재지',
    'grade'   => '소방안전관리 등급',
    'use'     => '용도',
    'floor_a' => '지상 층수',
    'area_t'  => '연면적',
  ];
}

/** 채워진 정도. ['filled'=>3,'total'=>6,'percent'=>50,'missing'=>['소재지',...]] */
function bi_progress(): array {
  $req  = bi_required_fields();
  $d    = bi_load();
  $miss = [];
  $n    = 0;

  foreach ($req as $k => $label) {
    if (trim((string)($d[$k] ?? '')) !== '') $n++;
    else $miss[] = $label;
  }
  // 소방안전관리자는 한 명이라도 이름이 있어야 채운 것으로 본다
  $hasMgr = false;
  foreach ((array)($d['mgrs'] ?? []) as $m) {
    if (trim((string)($m['name'] ?? '')) !== '') { $hasMgr = true; break; }
  }
  if ($hasMgr) $n++; else $miss[] = '소방안전관리자';

  $total = count($req) + 1;
  return [
    'filled'  => $n,
    'total'   => $total,
    'percent' => $total ? (int)round($n / $total * 100) : 0,
    'missing' => $miss,
  ];
}

/** 필수 항목이 모두 채워졌는가 */
function bi_is_complete(): bool {
  $p = bi_progress();
  return $p['filled'] >= $p['total'];
}
