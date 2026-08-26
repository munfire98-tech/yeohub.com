<?php
/* =============================================================
   building_setup_chat.php — 건물 기본정보 대화형 입력
   ─────────────────────────────────────────────────────────────
   질문에 하나씩 답하면 building_info.php 에 저장됩니다.
   한 문항 답할 때마다 바로 저장되므로 중간에 나가도 이어서 할 수 있습니다.
   기존 building_setup.php(표 형식)는 그대로 두고, 나중에 고칠 때 씁니다.
   ============================================================= */
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

require_once __DIR__ . '/building_info.php';
require_once __DIR__ . '/user_key.php';
$API = require __DIR__ . '/api_keys.php';   // 카카오·juso·건축물대장 키

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf'];

/* ── 한 문항 저장 (fetch 로 들어옴) ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save_step') {
  header('Content-Type: application/json; charset=utf-8');

  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok' => false, 'error' => '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }
  if (!app_has_user_key()) {
    echo json_encode(['ok' => false, 'error' => app_user_key_notice()]); exit;
  }

  $patch = json_decode((string)($_POST['patch'] ?? '{}'), true);
  if (!is_array($patch)) $patch = [];

  /* 기존 값을 유지한 채 이번 답변만 덮어쓴다.
     bi_save() 는 넘기지 않은 항목을 비워버리므로 반드시 합쳐서 넘겨야 한다. */
  $cur = bi_load();

  $allowed = array_keys(bi_blank());
  foreach ($patch as $k => $v) {
    if (!in_array($k, $allowed, true)) continue;
    if ($k === 'mgrs' || $k === 'raw_bldg') {
      $cur[$k] = is_array($v) ? $v : ($k === 'mgrs' ? [] : $cur[$k]);
    } else {
      $cur[$k] = is_string($v) ? $v : (string)$v;
    }
  }

  $ok = bi_save($cur);
  $p  = bi_progress();
  echo json_encode([
    'ok'      => $ok,
    'percent' => $p['percent'],
    'filled'  => $p['filled'],
    'total'   => $p['total'],
    'error'   => $ok ? '' : '저장하지 못했습니다. data 폴더 쓰기 권한을 확인해 주세요.',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 건물 검색: 카카오 키워드 장소검색 (이름·주소) ─────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'search') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }
  $kw = trim((string)($_POST['keyword'] ?? ''));
  if ($kw === '') { echo json_encode(['ok'=>true,'results'=>[]]); exit; }

  $url = $API['kakao_url'] . '?' . http_build_query(['query'=>$kw, 'size'=>10]);
  $res = bldg_http_get($url, ['Authorization: KakaoAK ' . $API['kakao']]);
  $j   = json_decode($res['body'], true);
  if ($res['code'] === 401 || $res['code'] === 403) {
    echo json_encode(['ok'=>false,'error'=>'카카오 인증 실패 — 앱의 카카오맵 사용설정 ON, REST 키를 확인하세요.']); exit;
  }
  if (!isset($j['documents'])) {
    echo json_encode(['ok'=>false,'error'=>'검색 응답 오류('.$res['code'].')']); exit;
  }
  $out = [];
  foreach ($j['documents'] as $doc) {
    $out[] = [
      'place'    => $doc['place_name']        ?? '',
      'road'     => $doc['road_address_name'] ?? '',
      'jibun'    => $doc['address_name']      ?? '',
      'category' => $doc['category_group_name'] ?? '',
      'lng'      => $doc['x'] ?? '',   // 카카오는 x=경도, y=위도로 내려줍니다
      'lat'      => $doc['y'] ?? '',
    ];
  }
  echo json_encode(['ok'=>true,'results'=>$out], JSON_UNESCAPED_UNICODE); exit;
}

/* ── 대상물 조회: juso(주소→코드) → 건축HUB(대장) → 기본정보 매핑 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }
  $road  = trim((string)($_POST['road']  ?? ''));
  $jibun = trim((string)($_POST['jibun'] ?? ''));
  $place = trim((string)($_POST['place'] ?? ''));
  $lat   = trim((string)($_POST['lat']   ?? ''));   // 검색 결과의 좌표 (지도 표시용)
  $lng   = trim((string)($_POST['lng']   ?? ''));

  // 1) juso 로 시군구·법정동 코드 확보 (지번 우선, 실패 시 도로명)
  $jusoJibun = bldg_juso_code($API, $jibun);
  $jusoRoad  = bldg_juso_code($API, $road);
  $base = $jusoJibun ?: $jusoRoad;
  if (!$base) {
    echo json_encode(['ok'=>true,'partial'=>true,
      'patch'=>['name'=>$place, 'address'=>($road ?: $jibun), 'bd_lat'=>$lat, 'bd_lng'=>$lng],
      'note'=>'주소를 코드로 변환하지 못해 이름·주소만 채웠습니다.']); exit;
  }

  // 2) 시도할 지번 후보를 모은다
  //    ★ 진단 결과: 부번(-1)을 붙이면 0건이고 본번만 넣으면 나오는 경우가 많다.
  //      또 카카오와 juso 가 서로 다른 필지를 가리키기도 한다(한 도로명에 여러 필지).
  //      그래서 "카카오 본번 → juso 본번 → 각 부번 포함" 순으로 모두 시도한다.
  $kj = bldg_parse_jibun($jibun);            // 카카오 지번주소에서 본번/부번 파싱
  $cands = [];
  $addCand = function($label,$bun,$ji,$mtYn,$exact=false) use (&$cands,$base){
    $bun4 = str_pad((string)(int)$bun, 4, '0', STR_PAD_LEFT);
    $ji4  = str_pad((string)(int)$ji,  4, '0', STR_PAD_LEFT);
    if ($bun4 === '0000') return;
    foreach ($cands as $c) { if ($c['bun']===$bun4 && $c['ji']===$ji4 && $c['platGbCd']===($mtYn==='1'?'1':'0')) return; }
    $cands[] = ['label'=>$label,'exact'=>$exact,'sigunguCd'=>$base['sigunguCd'],'bjdongCd'=>$base['bjdongCd'],
                'platGbCd'=>($mtYn==='1'?'1':'0'),'bun'=>$bun4,'ji'=>$ji4];
  };
  /* ★ 정확한 지번(exact=true, 부번까지 그대로)을 최우선으로 시도한다.
     "본번만(ji=0000)"은 juso/카카오가 정확한 부번을 못 줄 때만 쓰는 대안이라
     정확한 지번이 있는데도 밀리면 안 된다(→ 엉뚱한 옆 필지 건물이 뜨는 원인이었음). */
  // 1순위: juso 지번 — 정확한 본번-부번 (juso 가 원래 알려준 그 주소)
  if ($jusoJibun) $addCand('juso 지번', (int)$jusoJibun['bun'], (int)$jusoJibun['ji'], $jusoJibun['platGbCd'], true);
  // 2순위: 카카오 지번 — 정확한 본번-부번 (사용자가 지도에서 고른 그 주소)
  $addCand('카카오 지번', $kj['bun'], $kj['ji'], $kj['mtYn'], true);
  // 이하는 위 정확한 지번이 전부 0건일 때만 쓰는 대안(부번을 모를 때 대비)
  $addCand('카카오 본번', $kj['bun'], 0, $kj['mtYn'], false);
  if ($jusoJibun) $addCand('juso 본번', (int)$jusoJibun['bun'], 0, $jusoJibun['platGbCd'], false);
  if ($jusoRoad)  $addCand('juso 도로명', (int)$jusoRoad['bun'], 0, $jusoRoad['platGbCd'], false);

  // 3) 건축HUB 조회 — 후보를 순서대로 시도, 데이터 나오는 첫 후보 채택
  //    (총괄표제부: 면적·용적률·주차 / 표제부: 층수·구조·높이 — 동별)
  $key = $API['hub']; if (strpos($key,'%')!==false) $key = urldecode($key);
  $fetchItems = function(string $op, array $c) use ($API, $key) {
    $u = $API['hub_base'] . '/' . $op . '?' . http_build_query([
      'serviceKey'=>$key, 'sigunguCd'=>$c['sigunguCd'], 'bjdongCd'=>$c['bjdongCd'],
      'platGbCd'=>$c['platGbCd'], 'bun'=>$c['bun'], 'ji'=>$c['ji'],
      'numOfRows'=>100, 'pageNo'=>1, '_type'=>'json']);
    $r = bldg_http_get($u);
    if (stripos($r['body'],'SERVICE KEY IS NOT REGISTERED') !== false) return 'KEYERR';
    $jj = json_decode($r['body'], true);
    $items = $jj['response']['body']['items']['item'] ?? [];
    if (isset($items['bldNm']) || isset($items['platPlc'])) $items = [$items];
    return is_array($items) ? $items : [];
  };

  /* ★ 정확한 지번(exact)을 먼저, 순서대로 시도해 첫 성공에서 멈춘다.
     (610-8 처럼 정확한 부번이 있으면 그 건물만 나와야 하므로 "많이 나오는 것"으로
      바꿔치기하면 안 된다 — 엉뚱한 옆 필지 건물이 뜨는 원인이었다)
     정확한 지번이 전부 0건일 때만, 대안 후보 중 가장 많이 잡히는 것을 쓴다
     (교하로 677-20 처럼 juso·카카오가 준 부번 자체가 틀렸을 때 대비). */
  $recapList = []; $titleList = []; $code = null; $usedLabel = '';

  foreach ($cands as $c) {
    if (!$c['exact']) continue;
    $rl = $fetchItems('getBrRecapTitleInfo', $c);
    if ($rl === 'KEYERR') { echo json_encode(['ok'=>false,'error'=>'건축물대장 키 미등록/승인대기']); exit; }
    $tl = $fetchItems('getBrTitleInfo', $c);
    if ($tl === 'KEYERR') { echo json_encode(['ok'=>false,'error'=>'건축물대장 키 미등록/승인대기']); exit; }
    if (count($rl) + count($tl) > 0) {
      $recapList = $rl; $titleList = $tl; $code = $c; $usedLabel = $c['label'];
      break;   // 정확한 지번에서 찾았으면 더 안 찾는다
    }
  }

  if (!$code) {   // 정확한 지번이 전부 실패했을 때만 대안을 넓게 탐색
    $bestCnt = -1;
    foreach ($cands as $c) {
      if ($c['exact']) continue;
      $rl = $fetchItems('getBrRecapTitleInfo', $c);
      if ($rl === 'KEYERR') { echo json_encode(['ok'=>false,'error'=>'건축물대장 키 미등록/승인대기']); exit; }
      $tl = $fetchItems('getBrTitleInfo', $c);
      if ($tl === 'KEYERR') { echo json_encode(['ok'=>false,'error'=>'건축물대장 키 미등록/승인대기']); exit; }
      $cnt = count($tl) + count($rl);
      if ($cnt > $bestCnt) {
        $bestCnt = $cnt; $recapList = $rl; $titleList = $tl;
        if ($cnt > 0) { $code = $c; $usedLabel = $c['label']; }
      }
    }
  }
  if (!$code) $code = $cands[0] ?? $base;   // 전부 실패해도 주소 정보는 남긴다
  $code['roadAddr'] = $base['roadAddr'] ?? ($road ?: $jibun);

  // 총괄표제부(단지 요약) — 있으면 첫 건
  $recap = (is_array($recapList) && $recapList) ? $recapList[0] : null;

  // 표제부(동별 상세) — 주건축물만, 연면적 큰 순 정렬
  $titles = [];
  if (is_array($titleList)) {
    foreach ($titleList as $t) {
      // 부속건축물도 포함한다(제외하면 동이 누락되는 경우가 있음)
      $titles[] = $t;
    }
    usort($titles, fn($a,$b)=>((float)($b['totArea']??0))<=>((float)($a['totArea']??0)));
  }

  // 대표 항목: 총괄표제부 우선(면적·용적률·주차), 없으면 표제부 첫 동
  $item = $recap ?: (($titles[0] ?? null));
  // 층수·구조·높이의 대표값은 표제부 첫 동(가장 큰 동)에서
  $head = $titles[0] ?? $recap;

  // 3) 대장이 없으면(학교·신축 등) 이름·주소만이라도 채워 넘긴다
  if (!$item) {
    echo json_encode(['ok'=>true,'partial'=>true,
      'patch'=>['name'=>$place ?: '', 'address'=>($code['roadAddr'] ?: ($road ?: $jibun))],
      'code'=>$code,
      'note'=>'이 건물은 건축물대장 자동조회가 되지 않아 이름·주소만 채웠습니다. 나머지는 직접 입력해 주세요.']); exit;
  }

  // 4) 건축물대장 → building_info 필드 매핑 (총괄표제부 + 표제부 병합)
  //    val(): 총괄표제부($item) 우선, 없으면 표제부 대표동($head)
  $val = function(string $k) use ($item, $head) {
    $v = $item[$k] ?? '';
    if (($v === '' || $v === null || $v === ' ') && $head) $v = $head[$k] ?? '';
    return $v;
  };
  $numOrEmpty = function($v){ return ($v !== '' && $v !== null && $v !== ' ') ? (string)$v : ''; };
  $fmtDay = function($s){ $s=trim((string)$s); return preg_match('/^\d{8}$/',$s) ? substr($s,0,4).'.'.substr($s,4,2).'.'.substr($s,6,2) : $s; };

  // 층수·구조·높이: 표제부 대표동($head)에서
  $floorA = $head ? (int)($head['grndFlrCnt'] ?? 0) : 0;
  $floorB = $head ? (int)($head['ugrndFlrCnt'] ?? 0) : 0;
  $struct = $head ? trim((string)($head['strctCdNm'] ?? '')) : '';
  $height = $head ? trim((string)($head['heit'] ?? '')) : '';

  // 여러 동이면 동별 정보를 구조화해서 담는다 (시뮬레이션이 동별로 꺼내 쓸 수 있게)
  $dongList = [];
  foreach ($titles as $t) {
    $dn = trim((string)($t['dongNm'] ?? ''));
    if ($dn === '' || $dn === ' ') $dn = '(동명 미상)';
    if (($t['mainAtchGbCd'] ?? '0') === '1') $dn .= ' (부속)';
    $dongList[] = [
      'dong'    => $dn,
      'floor_a' => (string)(int)($t['grndFlrCnt'] ?? 0),
      'floor_b' => (string)(int)($t['ugrndFlrCnt'] ?? 0),
      'struct'  => trim((string)($t['strctCdNm'] ?? '')),
      'height'  => (($t['heit'] ?? '') !== '' && ($t['heit'] ?? '0') !== '0') ? (string)$t['heit'] : '',
      'area'    => (($t['totArea'] ?? '') !== '') ? (string)round((float)$t['totArea']) : '',
      'use'     => trim((string)($t['mainPurpsCdNm'] ?? '')),
    ];
  }

  // 사람이 읽는 요약 (기존 유지)
  $dongLines = [];
  if (count($dongList) > 1) {
    foreach ($dongList as $g) {
      $seg = $g['dong'].' : 지상'.$g['floor_a'].'/지하'.$g['floor_b'].'층';
      if ($g['struct'] !== '') $seg .= ' · '.$g['struct'];
      if ($g['height'] !== '') $seg .= ' · '.$g['height'].'m';
      if ($g['area']   !== '') $seg .= ' · '.number_format((float)$g['area']).'㎡';
      $dongLines[] = $seg;
    }
  }
  $dongDetail = implode("\n", $dongLines);

  $useNm  = trim((string)($val('mainPurpsCdNm')));
  $useApr = $fmtDay($val('useAprDay'));
  $pmsDay = $fmtDay($val('pmsDay'));
  $stcns  = $fmtDay($val('stcnsDay'));

  $patch = [
    // 기존 핵심 항목
    'name'    => trim((string)($val('bldNm'))) ?: $place,
    'address' => trim((string)($val('newPlatPlc'))) ?: trim((string)($val('platPlc'))),
    // 지도 표시용 좌표 (카카오 검색 결과에서 그대로 받아옵니다)
    'bd_lat'  => $lat,
    'bd_lng'  => $lng,
    'use'     => bldg_map_use($useNm),
    'floor_a' => (string)$floorA,
    'floor_b' => (string)$floorB,
    'area_t'  => ($val('totArea') !== '') ? (string)round((float)$val('totArea')) : '',
    'area_f'  => ($val('archArea') !== '') ? (string)round((float)$val('archArea')) : '',
    'dongsu'  => $numOrEmpty($val('mainBldCnt')) ?: (string)max(count($titles),0),
    // 건축물대장 상세 (building_info.php 의 bd_* 필드로 저장)
    'bd_struct'    => $struct,
    'bd_struct_etc'=> trim((string)($val('etcStrct'))),
    'bd_height'    => ($height !== '' && $height !== '0') ? $height : '',
    'bd_area_plat' => ($val('platArea') !== '') ? (string)round((float)$val('platArea')) : '',
    'bd_bcrat'     => $numOrEmpty($val('bcRat')),
    'bd_vlrat'     => $numOrEmpty($val('vlRat')),
    'bd_area_vl'   => ($val('vlRatEstmTotArea') !== '') ? (string)round((float)$val('vlRatEstmTotArea')) : '',
    'bd_main_bld'  => $numOrEmpty($val('mainBldCnt')),
    'bd_atch_bld'  => $numOrEmpty($val('atchBldCnt')),
    'bd_hhld'      => $numOrEmpty($val('hhldCnt')),
    'bd_family'    => $numOrEmpty($val('fmlyCnt')),
    'bd_ho'        => $numOrEmpty($val('hoCnt')),
    'bd_park'      => $numOrEmpty($val('totPkngCnt')),
    'bd_elev'      => $numOrEmpty($val('rideUseElvtCnt')),
    'bd_use_main'  => $useNm,
    'bd_use_etc'   => trim((string)($val('etcPurps'))),
    'bd_pms_day'   => $pmsDay,
    'bd_stcns_day' => $stcns,
    'bd_use_apr'   => $useApr,
    'bd_seismic'   => (($val('rserthqkDsgnApplyYn')) === '1') ? '적용' : ((($val('rserthqkDsgnApplyYn')) === '0') ? '미적용' : ''),
    'bd_seismic_ablty'=> trim((string)($val('rserthqkAblty'))),
    'bd_energy'    => trim((string)($val('engrGrade'))),
    'bd_road_addr' => trim((string)($val('newPlatPlc'))),
    'bd_dongs'     => $dongDetail,   // 여러 동일 때 동별 층수·구조(요약 텍스트)
    'bd_dong_list' => $dongList,     // 동별 상세(구조화) — 시뮬레이션용
    'bd_looked'    => date('Y-m-d H:i:s'),
  ];
  echo json_encode(['ok'=>true,'patch'=>$patch,'rawUse'=>$useNm,'code'=>$code,
    'via'=>$usedLabel, 'dongCnt'=>count($dongList), 'dongList'=>$dongList], JSON_UNESCAPED_UNICODE); exit;
}

/* ── 조회 헬퍼들 ─────────────────────────────────────────── */
function bldg_http_get(string $url, array $headers = []): array {
  $ch = curl_init($url);
  $h  = array_merge(['Accept: application/json'], $headers);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false, CURLOPT_HTTPHEADER=>$h]);
  $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return ['body'=>(string)$b, 'code'=>$c];
}
/** 지번주소 문자열에서 본번/부번을 뽑는다.
 *  예) "경기 파주시 탄현면 문지리 16-1" → bun=16, ji=1
 *      "서울 종로구 세종로 1"          → bun=1,  ji=0
 *      "... 산 20-3"                   → mtYn=1 (산) */
function bldg_parse_jibun(string $addr): array {
  $addr = trim($addr);
  $mtYn = (mb_strpos($addr, '산 ') !== false || preg_match('/\s산\d/u', $addr)) ? '1' : '0';
  if ($addr === '') return ['bun'=>'0','ji'=>'0','mtYn'=>$mtYn];
  // 끝부분의 "숫자-숫자"
  if (preg_match('/(\d+)\s*-\s*(\d+)\s*$/u', $addr, $m)) {
    return ['bun'=>$m[1], 'ji'=>$m[2], 'mtYn'=>$mtYn];
  }
  // 끝부분의 "숫자" (번지 표기 허용)
  if (preg_match('/(\d+)\s*번?지?\s*$/u', $addr, $m)) {
    return ['bun'=>$m[1], 'ji'=>'0', 'mtYn'=>$mtYn];
  }
  // 그 외: 문자열 안 마지막 숫자쌍
  if (preg_match_all('/(\d+)(?:\s*-\s*(\d+))?/u', $addr, $mm, PREG_SET_ORDER)) {
    $last = end($mm);
    return ['bun'=>$last[1], 'ji'=>($last[2] ?? '0'), 'mtYn'=>$mtYn];
  }
  return ['bun'=>'0','ji'=>'0','mtYn'=>$mtYn];
}

function bldg_juso_code(array $API, string $addr): ?array {
  $addr = trim($addr); if ($addr === '') return null;
  $url = $API['juso_url'] . '?' . http_build_query([
    'confmKey'=>$API['juso'], 'currentPage'=>1, 'countPerPage'=>1, 'keyword'=>$addr, 'resultType'=>'json']);
  $res = bldg_http_get($url); $j = json_decode($res['body'], true);
  if (($j['results']['common']['errorCode'] ?? '') !== '0') return null;
  $a = $j['results']['juso'][0] ?? null; if (!$a) return null;
  $adm = (string)($a['admCd'] ?? '');
  $bun = (string)($a['lnbrMnnm'] ?? '0');
  // 번지가 0이어도 시군구·법정동 코드는 유효하므로 반환한다.
  // (번지는 카카오 지번 등 다른 후보로 보완 — 호출부에서 여러 후보를 시도)
  return [
    'roadAddr'  => $a['roadAddr'] ?? $addr,
    'sigunguCd' => substr($adm,0,5),
    'bjdongCd'  => substr($adm,5,5),
    'platGbCd'  => (($a['mtYn'] ?? '0') === '1') ? '1' : '0',
    'bun'       => str_pad($bun, 4, '0', STR_PAD_LEFT),
    'ji'        => str_pad((string)($a['lnbrSlno'] ?? '0'), 4, '0', STR_PAD_LEFT),
  ];
}
/** 건축물대장 주용도 → 이 도구의 용도 선택지로 매핑 */
function bldg_map_use(string $nm): string {
  if ($nm === '') return '';
  $has = function($k) use ($nm){ return mb_strpos($nm, $k) !== false; };
  if ($has('업무'))                                 return '업무시설(사무실)';
  if ($has('판매') || $has('상가') || $has('소매'))  return '판매시설(상가·매장)';
  if ($has('숙박') || $has('생활숙박'))              return '숙박시설';
  if ($has('공장') || $has('창고'))                  return '공장·창고';
  if ($has('의료') || $has('병원'))                  return '의료시설';
  if ($has('교육') || $has('학교') || $has('연구'))  return '교육연구시설';
  if ($has('근린생활'))                              return '판매시설(상가·매장)';
  return $nm;   // 애매하면 원문 그대로 → 사용자가 확인/수정
}

/* ── 처음부터 다시 ────────────────────────────────────────
   입력한 건물 기본정보를 비우고 첫 질문부터 다시 시작합니다. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reset') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
  if (app_has_user_key()) {
    $blank = bi_blank();
    unset($blank['updated']);
    bi_save($blank);
  }
  header('Location: /building_setup_chat.php?reset=1');
  exit;
}

/* ── 화면 ────────────────────────────────────────────────── */
$noUser = !app_has_user_key();
$d      = bi_load();
$prog   = bi_progress();
$nick   = $_SESSION['nickname'] ?? '사용자';
$viewUid = app_user_key();
$adminView = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '' && $viewUid !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($viewUid)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>건물 기본정보 입력 — YeoHub</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
a{text-decoration:none;color:inherit}
button{font:inherit;color:inherit;cursor:pointer}
:focus-visible{outline:2px solid var(--brand);outline-offset:2px}

.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);
  backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:760px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--pri:hover{background:var(--brand2);color:#fff}
.btn--sm{padding:6px 12px;font-size:12.5px}
/* 다음에 눌러야 할 버튼을 살짝 강조합니다 */
.btn--nudge{box-shadow:0 0 0 3px rgba(34,197,94,.25);animation:nudgePulse 1.6s ease-in-out infinite}
@keyframes nudgePulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@media(prefers-reduced-motion:reduce){.btn--nudge{animation:none}}
/* 집결지 장소 유형 — 고른 버튼을 강조 */
.btn.is-on{background:var(--brand);border-color:var(--brand);color:#fff}

/* 진행 막대 */
.prog{position:sticky;top:56px;z-index:45;background:#fff;border-bottom:1px solid var(--bd)}
.prog__in{max-width:760px;margin:0 auto;padding:11px 20px}
.prog__row{display:flex;justify-content:space-between;font-size:12.5px;
  color:var(--mut2);margin-bottom:6px}
.prog__row b{color:var(--brand2)}
.bar{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden}
.bar i{display:block;height:100%;background:var(--brand);width:0;
  transition:width .45s cubic-bezier(.2,.7,.3,1)}

.wrap{max-width:760px;margin:0 auto;padding:24px 20px 60px}

.msg{display:flex;gap:11px;margin-bottom:15px;animation:pop .28s ease both}
@keyframes pop{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.msg__av{width:31px;height:31px;border-radius:9px;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;font-size:15px;background:#eef2ff}
.msg__b{background:var(--card);border:1px solid var(--bd);border-radius:4px 14px 14px 14px;
  padding:14px 17px;max-width:calc(100% - 44px);font-size:14.8px;line-height:1.72}
.msg--me{flex-direction:row-reverse}
.msg--me .msg__av{background:#e6edfb}
.msg--me .msg__b{background:var(--brand);border-color:var(--brand);color:#fff;
  border-radius:14px 4px 14px 14px}
.msg__b b{font-weight:700}
.hint{font-size:12.5px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd)}

.answer{margin:0 0 22px 42px}
.opts{display:flex;flex-wrap:wrap;gap:8px}
.opt{padding:9px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;
  font-size:13.5px;font-weight:500;transition:.14s}
.opt:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
.grade-guide{padding:15px 16px;border:1px solid #bfdbfe;border-radius:13px;background:linear-gradient(135deg,#eff6ff,#fff);margin-bottom:10px}
.grade-guide__label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:800;color:var(--brand2);margin-bottom:7px}
.grade-guide__label::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--brand)}
.grade-guide__result{font-size:16px;font-weight:850;letter-spacing:-.02em;margin-bottom:3px}.grade-guide__hint{font-size:11.5px;color:var(--mut2);margin-bottom:11px}
.opt--recommended{width:100%;justify-content:center;padding:11px 16px;border-radius:10px;background:var(--brand);border-color:var(--brand);color:#fff;font-weight:800;box-shadow:0 7px 16px rgba(37,99,235,.18)}
.opt--recommended:hover{background:var(--brand2);border-color:var(--brand2);color:#fff}.grade-alts{margin-top:8px}.grade-alts__label{width:100%;font-size:11px;color:var(--mut);margin-bottom:1px}
.choice-prompt{display:flex;align-items:center;gap:9px;margin:5px 0 10px;padding:9px 11px;border:1px solid #dbe5f3;border-radius:10px;background:#f8faff;font-size:12px;color:var(--mut2)}
.choice-prompt__badge{flex:0 0 auto;padding:3px 8px;border-radius:999px;background:#dbeafe;color:var(--brand2);font-size:10.5px;font-weight:850}
.choice-prompt__text{font-weight:700;color:var(--fg)}
.inrow{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}
.inrow input{flex:1;min-width:180px;padding:11px 14px;border:1px solid var(--bd2);
  border-radius:11px;background:#fff;font-size:14.8px;font-family:inherit}
.inrow input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.inrow .unit{align-self:center;font-size:13.5px;color:var(--mut2)}
.pair{display:flex;gap:8px;flex-wrap:wrap;width:100%}
.pair label{flex:1;min-width:130px;display:flex;flex-direction:column;gap:5px;
  font-size:12.5px;color:var(--mut2)}
.subrow{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}
.btn--manager{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8;font-weight:700}.btn--manager:hover{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8}
.btn--manager::before{content:'✓';display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#2563eb;color:#fff;font-size:10px}
.btn--back{border-color:#ddd6fe;background:#f5f3ff;color:#6d28d9;font-weight:700}.btn--back:hover{background:#ede9fe;border-color:#c4b5fd;color:#5b21b6}
.lookup-loading{display:flex;align-items:flex-start;gap:12px;min-width:min(420px,100%)}
.lookup-spinner{flex:0 0 24px;width:24px;height:24px;border:3px solid #dbeafe;border-top-color:var(--brand);border-radius:50%;animation:lookupSpin .75s linear infinite}
.lookup-loading b{display:block;font-size:13.5px}.lookup-loading span{display:block;margin-top:2px;font-size:11.5px;color:var(--mut2);line-height:1.55}
@keyframes lookupSpin{to{transform:rotate(360deg)}}
@media(prefers-reduced-motion:reduce){.lookup-spinner{animation-duration:1.6s}}
.lookup-recovery{padding:13px 14px;border:1px solid #fed7aa;background:#fffaf2;border-radius:12px;margin-bottom:8px;font-size:12px;color:#92400e}

.done{background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:22px;margin-left:42px}
.done h2{font-size:18px;font-weight:800;margin-bottom:12px}
.sum{display:flex;justify-content:space-between;gap:14px;padding:8px 0;
  border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}
.sum:first-of-type{border-top:0}
.sum__k{color:var(--mut2);font-size:13px}
.sum__v{font-weight:600;text-align:right}
.sum__v.none{color:var(--mut);font-weight:400}
.doneRow{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}

.alert{display:flex;gap:11px;border-radius:12px;padding:14px 16px;font-size:14px;
  line-height:1.7;margin-bottom:18px}
.alert--bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.typing{display:inline-flex;gap:4px;align-items:center;padding:3px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--mut);display:block;
  animation:blink 1.2s infinite}
.typing i:nth-child(2){animation-delay:.18s}
.typing i:nth-child(3){animation-delay:.36s}
@keyframes blink{0%,60%,100%{opacity:.28}30%{opacity:1}}

@media(max-width:560px){
  .answer,.done{margin-left:0}
  .msg__b{max-width:calc(100% - 42px)}
}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">소방계획서.B_S_CHAT</a>
    <div style="display:flex;gap:8px">
      <form method="post" style="display:inline"
            onsubmit="return confirm('입력한 건물 기본정보를 모두 지우고 처음부터 다시 시작합니다.\n계속할까요?')">
        <input type="hidden" name="act" value="reset">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <button class="btn" type="submit">↺ 처음부터 다시</button>
      </form>
      <a class="btn" href="<?=h($url('/building_setup.php'))?>">표로 입력</a>
      <a class="btn" href="<?=h($url('/building_manager.php'))?>">← 메인</a>
    </div>
  </div>
</nav>

<div class="prog">
  <div class="prog__in">
    <div class="prog__row">
      <span>건물 기본정보</span>
      <span><b id="pPct"><?=$prog['percent']?>%</b> · <span id="pNum"><?=$prog['filled']?>/<?=$prog['total']?></span></span>
    </div>
    <div class="bar"><i id="pBar" style="width:<?=$prog['percent']?>%"></i></div>
  </div>
</div>

<main class="wrap">
  <?php if ($noUser): ?>
    <div class="alert alert--bad">
      <div>✕</div>
      <div><?=h(app_user_key_notice())?></div>
    </div>
  <?php endif; ?>
  <div id="chat"></div>
</main>

<script>
var CSRF   = <?=json_encode($CSRF)?>;
var KAKAO_JS_KEY = <?=json_encode($API['kakao_js'] ?? '')?>;

/* 카카오 지도 SDK 를 필요할 때 한 번만 불러옵니다.
   JavaScript 키가 비어 있거나 로드에 실패하면 onFail 로 알려줍니다. */
var _kakaoMapState = 'idle';   // idle | loading | ready | failed
var _kakaoMapQueue = [];
function loadKakaoMap(onReady, onFail){
  if (!KAKAO_JS_KEY){ if(onFail) onFail(); return; }
  if (_kakaoMapState === 'ready'){ onReady(); return; }
  if (_kakaoMapState === 'failed'){ if(onFail) onFail(); return; }
  _kakaoMapQueue.push({ ok:onReady, fail:onFail });
  if (_kakaoMapState === 'loading') return;
  _kakaoMapState = 'loading';

  var sc = document.createElement('script');
  sc.src = 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' + encodeURIComponent(KAKAO_JS_KEY) + '&autoload=false';
  sc.onload = function(){
    kakao.maps.load(function(){
      _kakaoMapState = 'ready';
      _kakaoMapQueue.forEach(function(q){ q.ok(); }); _kakaoMapQueue = [];
    });
  };
  sc.onerror = function(){
    _kakaoMapState = 'failed';
    _kakaoMapQueue.forEach(function(q){ if(q.fail) q.fail(); }); _kakaoMapQueue = [];
  };
  document.head.appendChild(sc);
}
var SAVED  = <?=json_encode($d, JSON_UNESCAPED_UNICODE)?>;
var NOUSER = <?=$noUser ? 'true' : 'false'?>;
var NICK   = <?=json_encode($nick, JSON_UNESCAPED_UNICODE)?>;

var chat = document.getElementById('chat');
var answers = {};          // 이번 대화에서 새로 받은 값
var step = 0;

/* ── 질문 목록 ────────────────────────────────────────────
   field  : building_info.php 의 항목 이름
   필수 항목을 앞에, 있으면 좋은 것을 뒤에 둡니다. */
var STEPS = [
  { field:'__search', q:'먼저, 건물을 검색해서 한 번에 채워볼까요?', type:'lookup',
    hint:'건물 이름이나 주소로 찾으면 대상명·소재지·용도·층수·연면적을 자동으로 채웁니다. 없는 건물이면 건너뛰고 직접 입력하시면 됩니다.' },

  { field:'name', q:'건물 이름(대상명)이 어떻게 되나요?', type:'text', ph:'예: 트릭스타워',
    hint:'평소 부르는 건물 이름을 그대로 적으시면 됩니다. 나중에 언제든 고칠 수 있습니다.' },

  { field:'address', q:'소재지는 어디인가요?', type:'text', ph:'예: 서울특별시 강남구 테헤란로 123',
    hint:'도로명 주소로 적어주세요. 동·호수까지는 필요 없습니다.' },

  { field:'__assembly', q:'화재 시 모일 집결지는 어디인가요?', type:'assembly', skip:true,
    hint:'대피한 사람들이 모여서 인원을 확인하는 장소입니다. 건물에서 안전하게 떨어져 있고, 소방차 진입에 방해되지 않는 곳이 좋습니다. 검색해서 고르면 위치까지 저장됩니다.' },

  { field:'use', q:'건물을 주로 어떤 용도로 쓰나요?', type:'choice',
    options:['업무시설(사무실)','판매시설(상가·매장)','숙박시설','공장·창고','의료시설','교육연구시설','복합용도','그 밖'],
    dontknow:'use' },

  { field:'__floors', q:'층수가 어떻게 되나요?', type:'pair',
    pair:[{field:'floor_a', label:'지상', ph:'12'}, {field:'floor_b', label:'지하', ph:'2'}],
    hint:'숫자만 적어주세요. 지하가 없으면 0으로 두시면 됩니다. 옥탑은 층수에 넣지 않습니다.' },

  { field:'__areas', q:'면적은 어떻게 되나요?', type:'areas',
    hint:'연면적은 모든 층의 바닥면적을 더한 값이고, 바닥면적은 보통 한 층 또는 건축면적에 가까운 값입니다. 둘 중 하나만 알아도 먼저 넣을 수 있습니다.',
    dontknow:'area' },

  { field:'grade', q:'소방안전관리 등급은 어떻게 되나요?', type:'choice',
    options:['특급','1급','2급','3급'], dontknow:'grade' },

  { field:'__mgr', q:'소방안전관리자로 선임된 분은 누구인가요?', type:'mgr',
    hint:'본인이시면 본인 성함을 적으시면 됩니다. 연락처는 비워두셔도 됩니다.' },

  /* 여기부터는 없어도 서식이 만들어집니다 */
  { field:'rep',      q:'대표자 성함은 어떻게 되나요?', type:'text', ph:'예: 홍길동', skip:true, extra:true,
    hint:'건물주 또는 사업장 대표입니다. 소방안전관리자와 같은 분이어도 됩니다.' },
  { field:'tel',      q:'건물 대표 전화번호를 알려주세요.', type:'text', ph:'예: 02-1234-5678', skip:true, extra:true,
    hint:'관리사무실이나 방재실 번호면 됩니다.' },
  /* 근무인원(__staff)은 자위소방대 작성 단계에서 묻습니다.
     동 수는 건축물대장 조회로 자동으로 채워지므로 묻지 않습니다.
     업무수행 기록표의 확인내용 기본값은 기록표 화면에서 따로 받습니다. */
];

/* ── "잘 모르겠어요"를 눌렀을 때 ─────────────────────────
   다른 데 가서 알아보라고 하지 않고, 여기서 함께 찾습니다. */
var HELP = {

  use: function(){
    bot(md('건물을 **주로 무엇에 쓰는지**로 고르시면 됩니다. 정확한 법적 분류가 아니어도 괜찮습니다.\n\n' +
      '- **업무시설** — 사무실이 대부분인 건물\n' +
      '- **판매시설** — 매장·상가·마트\n' +
      '- **숙박시설** — 호텔·모텔·펜션\n' +
      '- **공장·창고** — 제조하거나 물건을 쌓아두는 곳\n' +
      '- **복합용도** — 아래층은 상가, 위층은 사무실처럼 섞여 있는 건물\n\n' +
      '가장 넓은 면적을 차지하는 용도로 고르시고, 섞여 있으면 **복합용도**를 고르세요.'));
    setTimeout(function(){ ask(STEPS[step]); }, 340);
  },

  area: function(){
    bot(md('연면적은 **각 층 넓이를 전부 더한 값**입니다.\n' +
      '10층 건물의 한 층이 500㎡면 연면적은 5,000㎡입니다.\n\n' +
      '한 층 넓이를 아시면 제가 계산해 드릴게요.'));
    var b = box();
    var row = document.createElement('div'); row.className='inrow';
    var i1 = mkInput('number','한 층 넓이 (㎡)');
    row.appendChild(i1);
    b.appendChild(row);
    var hintEl = document.createElement('div');
    hintEl.style.cssText = 'font-size:12.5px;color:var(--mut2);margin-top:8px';
    hintEl.textContent = '지상 ' + (SAVED.floor_a || '?') + '층 · 지하 ' + (SAVED.floor_b || '0') + '층으로 계산합니다.';
    b.appendChild(hintEl);

    var go = mkGo(b, function(){
      var per = parseFloat(i1.value || '0');
      if (!per || per <= 0){ i1.focus(); return; }
      var fl = (parseInt(SAVED.floor_a || '0', 10) || 0) + (parseInt(SAVED.floor_b || '0', 10) || 0);
      if (fl <= 0) fl = 1;
      var total = Math.round(per * fl);
      clearBox();
      me('한 층 ' + per.toLocaleString() + '㎡');
      bot(md('한 층 ' + per.toLocaleString() + '㎡ × ' + fl + '개 층 = 약 **' +
        total.toLocaleString() + '㎡** 로 보겠습니다.\n\n' +
        '정확한 값은 건축물대장에 있습니다. **정부24에서 무료로 열람**할 수 있으니, ' +
        '나중에 확인하시고 다르면 표에서 고치시면 됩니다.'));
      SAVED.area_t = String(total);
      save({area_t: String(total)}, function(){ step++; next(); });
    });
    var skip = document.createElement('button');
    skip.className='btn btn--sm'; skip.type='button'; skip.textContent='이것도 모르겠어요';
    skip.onclick = function(){
      clearBox(); me('이것도 모르겠어요');
      bot(md('괜찮습니다. 연면적은 **나중에 채우셔도 됩니다.**\n\n' +
        '건축물대장을 떼면 첫 장에 나와 있습니다. **정부24(gov.kr)** 에서 ' +
        '“건축물대장 등본 발급”으로 검색하시면 무료로 볼 수 있습니다.'));
      step++; setTimeout(next, 340);
    };
    b.querySelector('.subrow').appendChild(skip);
    i1.focus();
  },

  grade: function(){
    /* 화재의 예방 및 안전관리에 관한 법률 시행령 [별표 4] 기준.
       ★ 아파트와 그 외 건물의 기준이 서로 다릅니다.
         특급 — 아파트: 50층↑(지하 제외) 또는 높이 200m↑
                그 외 : 30층↑(지하 포함) 또는 높이 120m↑ 또는 연면적 10만㎡↑
         1급  — 아파트: 30층↑(지하 제외) 또는 높이 120m↑
                그 외 : 11층↑(지하 제외) 또는 연면적 1.5만㎡↑
         2급/3급 — 설치된 소방설비로 갈립니다(규모만으론 판단 불가). */
    var flA  = parseInt(SAVED.floor_a || '0', 10) || 0;   // 지상 층수
    var flB  = parseInt(SAVED.floor_b || '0', 10) || 0;   // 지하 층수
    var area = parseFloat(SAVED.area_t || '0') || 0;      // 연면적
    var hgt  = parseFloat(SAVED.bd_height || '0') || 0;   // 높이(m) — 건축물대장에서 옴
    var use  = String(SAVED.use || '') + ' ' + String(SAVED.bd_use_main || '') + ' ' + String(SAVED.bd_use_etc || '');
    var isApt = /아파트|공동주택/.test(use);

    var why = [];
    if (flA) why.push('지상 ' + flA + '층');
    if (hgt)  why.push('높이 ' + hgt + 'm');
    if (area) why.push('연면적 ' + area.toLocaleString() + '㎡');
    var scale = why.join(' · ');

    if (isApt){
      /* ── 아파트 ── */
      if (flA >= 50 || hgt >= 200){
        gradeAnswer('특급', '아파트는 50층 이상이거나 높이 200m 이상이면 특급입니다. (' + scale + ')');
        return;
      }
      if (flA >= 30 || hgt >= 120){
        gradeAnswer('1급', '아파트는 30층 이상이거나 높이 120m 이상이면 1급입니다. (' + scale + ')');
        return;
      }
      /* 그 밖의 아파트는 설비 기준으로 2급·3급이 갈립니다 */
    } else {
      /* ── 아파트 외 ── */
      if ((flA + flB) >= 30 || hgt >= 120 || area >= 100000){
        gradeAnswer('특급',
          '아파트가 아닌 건물은 30층 이상(지하 포함)이거나 높이 120m 이상, ' +
          '또는 연면적 10만㎡ 이상이면 특급입니다. (' + scale +
          (flB ? ' · 지하 ' + flB + '층' : '') + ')');
        return;
      }
      if (flA >= 11 || area >= 15000){
        gradeAnswer('1급',
          (flA >= 11 ? '지상 11층 이상이라' : '연면적이 1만 5천㎡ 이상이라') +
          ' 1급입니다. (' + scale + ')');
        return;
      }
    }

    /* 규모로 안 갈리면 설비를 물어봅니다 */
    bot(md('등급은 건물 규모와 **설치된 소방설비**로 정해집니다.\n' +
      (scale ? '말씀하신 규모(' + scale + ')면 ' : '') +
      '2급 아니면 3급인데, 하나만 더 확인하면 됩니다.'));

    setTimeout(function(){
      bot(md('건물 복도에 **옥내소화전함**(빨간 호스가 든 함)이나 ' +
        '천장에 **스프링클러 헤드**가 있나요?'),
        '소화전함은 보통 계단 옆이나 복도 벽에 있습니다. 스프링클러는 천장에 달린 작은 금속 노즐입니다.');
      var b = box();
      var w = document.createElement('div'); w.className='opts';
      [['있습니다', '2급', '옥내소화전이나 스프링클러가 설치된 건물은 2급입니다.'],
       ['없습니다 · 화재감지기만 있어요', '3급', '간이스프링클러나 자동화재탐지설비만 설치된 건물은 3급입니다.'],
       ['잘 모르겠어요', '', '']
      ].forEach(function(o){
        var btn = document.createElement('button');
        btn.className='opt'; btn.type='button'; btn.textContent=o[0];
        btn.onclick = function(){
          clearBox(); me(o[0]);
          if (o[1] === ''){
            bot(md('지금 확인하기 어려우시면 **비워두고 넘어가셔도 됩니다.**\n\n' +
              '가장 확실한 것은 소방안전관리자 **선임신고 필증**입니다. 거기 등급이 적혀 있습니다. ' +
              '찾으시면 표에서 채워 넣으시면 되고, 그 전까지도 다른 서식은 만들어집니다.'));
            step++; setTimeout(next, 360);
          } else {
            gradeAnswer(o[1], o[2]);
          }
        };
        w.appendChild(btn);
      });
      b.appendChild(w);
    }, 360);
  }
};

/* 규모만으로 등급이 확실히 갈리는 경우에만 결과를 돌려줍니다.
   2급·3급은 설치된 소방설비를 봐야 하므로 여기서는 판정하지 않습니다(null 반환).
   근거: 화재의 예방 및 안전관리에 관한 법률 시행령 [별표 4] */
function guessGrade(){
  var flA  = parseInt(SAVED.floor_a || '0', 10) || 0;
  var flB  = parseInt(SAVED.floor_b || '0', 10) || 0;
  var area = parseFloat(SAVED.area_t || '0') || 0;
  var hgt  = parseFloat(SAVED.bd_height || '0') || 0;
  var use  = String(SAVED.use || '') + ' ' + String(SAVED.bd_use_main || '') + ' ' + String(SAVED.bd_use_etc || '');
  var isApt = /아파트|공동주택/.test(use);

  if (!flA && !area && !hgt) return null;   // 아무 정보도 없으면 추천하지 않음

  if (isApt){
    if (flA >= 50 || hgt >= 200)
      return { grade:'특급', why:'아파트가 50층 이상이거나 높이 200m 이상입니다.' };
    if (flA >= 30 || hgt >= 120)
      return { grade:'1급', why:'아파트가 30층 이상이거나 높이 120m 이상입니다.' };
    return null;   // 그 밖의 아파트는 설비로 갈립니다
  }

  if ((flA + flB) >= 30 || hgt >= 120 || area >= 100000)
    return { grade:'특급', why:'30층 이상(지하 포함)이거나 높이 120m 이상, 또는 연면적 10만㎡ 이상입니다.' };
  if (flA >= 11)
    return { grade:'1급', why:'지상 11층 이상입니다.' };
  if (area >= 15000)
    return { grade:'1급', why:'연면적이 1만 5천㎡ 이상입니다.' };

  return null;   // 2급·3급은 소방설비를 확인해야 합니다
}

/* 추정한 등급을 제시하고 확인받는다 */
function gradeAnswer(grade, why){
  clearBox();
  bot(md('말씀하신 내용으로 보면 **' + grade + '** 입니다.\n\n' + why + '\n\n' +
    '선임신고 필증에 적힌 등급이 최종 기준이니, 나중에 확인해서 다르면 표에서 고치시면 됩니다.'));
  var b = box();
  var guide = document.createElement('div'); guide.className='grade-guide';
  guide.innerHTML='<div class="grade-guide__label">자동 계산 결과</div><div class="grade-guide__result">'+esc(grade)+'</div><div class="grade-guide__hint">아래 파란 버튼을 눌러 저장하고 다음 단계로 이동하세요.</div>';
  var btn = document.createElement('button');
  btn.className='opt opt--recommended'; btn.type='button';
  btn.textContent = '계산된 ' + grade + ' 선택';
  btn.onclick = function(){ submit({field:'grade'}, grade, {}, grade); };
  guide.appendChild(btn); b.appendChild(guide);
  var w = document.createElement('div'); w.className='opts grade-alts';
  var altLabel=document.createElement('div'); altLabel.className='grade-alts__label'; altLabel.textContent='선임신고 필증의 등급이 다르면 직접 선택하세요.'; w.appendChild(altLabel);
  ['특급','1급','2급','3급'].forEach(function(g){
    if (g === grade) return;
    var o = document.createElement('button');
    o.className='opt'; o.type='button'; o.textContent = g;
    o.onclick = function(){ submit({field:'grade'}, g, {}, g); };
    w.appendChild(o);
  });
  b.appendChild(w);
}

function mkInput(type, ph){
  var i = document.createElement('input');
  i.type = type; if (type === 'number'){ i.min='0'; i.inputMode='numeric'; }
  i.placeholder = ph || '';
  return i;
}

/* ── 도구 ─────────────────────────────────────────────── */
function esc(s){ return String(s==null?'':s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function md(s){ return esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>'); }
function down(){ requestAnimationFrame(function(){
  window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'}); }); }

function bot(html, hint){
  var d=document.createElement('div'); d.className='msg';
  d.innerHTML='<div class="msg__av">🏢</div><div class="msg__b">'+html+
    (hint?'<div class="hint">'+esc(hint)+'</div>':'')+'</div>';
  chat.appendChild(d); down(); return d;
}
function me(t){
  var d=document.createElement('div'); d.className='msg msg--me';
  d.innerHTML='<div class="msg__av">🙂</div><div class="msg__b">'+esc(t)+'</div>';
  chat.appendChild(d); down();
}
function typing(cb){ var d=bot('<span class="typing"><i></i><i></i><i></i></span>');
  setTimeout(function(){ d.remove(); cb(); }, 340); }
function clearBox(){ var a=document.getElementById('ansBox'); if(a) a.remove(); }
function box(){ clearBox(); var d=document.createElement('div');
  d.className='answer'; d.id='ansBox'; chat.appendChild(d); down(); return d; }

/* 이미 저장된 값이 있는지 */
function filled(s){
  if (s.field === '__search') return String(SAVED.name||'').trim() !== '';  // 이름이 이미 있으면 검색단계 건너뜀
  if (s.field === '__floors') return String(SAVED.floor_a||'').trim() !== '';
  if (s.field === '__areas')  return String(SAVED.area_t||'').trim() !== '' || String(SAVED.area_f||'').trim() !== '';
  if (s.field === '__mgr')    return (SAVED.mgrs||[]).some(function(m){ return (m.name||'').trim() !== ''; });
  if (s.field === '__staff')  return String(SAVED.wd_day||'').trim() !== '';
  return String(SAVED[s.field]||'').trim() !== '';
}

/* ── 서버에 저장 ──────────────────────────────────────── */
function save(patch, done){
  if (NOUSER){ done(false); return; }
  var fd = new FormData();
  fd.append('act','save_step'); fd.append('csrf',CSRF);
  fd.append('patch', JSON.stringify(patch));
  fetch(location.pathname + location.search, {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(j){
      if (j && j.ok){
        document.getElementById('pPct').textContent = j.percent + '%';
        document.getElementById('pNum').textContent = j.filled + '/' + j.total;
        document.getElementById('pBar').style.width = j.percent + '%';
        done(true);
      } else {
        bot(md('⚠️ ' + ((j && j.error) ? j.error : '저장하지 못했습니다.')));
        done(false);
      }
    })
    .catch(function(){ bot(md('⚠️ 저장 중 연결이 끊겼습니다. 잠시 후 다시 시도해 주세요.')); done(false); });
}

/* ── 시작 ─────────────────────────────────────────────── */
function start(){
  var anyFilled = STEPS.some(filled);
  if (anyFilled){
    bot(md('다시 오셨네요. 이어서 채워보겠습니다.\n이미 답하신 것은 건너뛰겠습니다.'));
  } else {
    bot(md('안녕하세요' + (NICK && NICK!=='사용자' ? ', ' + NICK + '님' : '') +
      '. 건물 기본정보를 함께 채워보겠습니다.\n\n' +
      '여기서 한 번 입력하면 **업무수행 기록표, 훈련 기록부, 자위소방대 편성표, 소방계획서**에 ' +
      '자동으로 들어갑니다. 매번 다시 적지 않으셔도 됩니다.'));
  }
  next();
}

function next(){
  while (step < STEPS.length && filled(STEPS[step])) step++;
  if (step >= STEPS.length){ finish(); return; }

  var s = STEPS[step];
  /* 대표자·전화번호까지 끊김 없이 이어서 묻고, 끝나면 finish() 가 마무리합니다. */
  typing(function(){ ask(s); });
}


function ask(s){
  bot(md(s.q), s.hint||'');
  var b = box();
  /* 각 입력 UI가 모두 그려진 뒤 맨 아래에 검토요청 버튼을 붙인다. */
  if (s.type !== 'lookup') Promise.resolve().then(function(){ addBack(s, b); addYeohubReview(s, b); });

  if (s.type === 'lookup'){
    var wrap = document.createElement('div');
    wrap.style.position = 'relative';
    var inp = document.createElement('input');
    inp.type='text'; inp.autocomplete='off';
    inp.placeholder='예: 트릭스타워 · 성남시청 · 강남구 테헤란로 123';
    inp.style.cssText='width:100%;padding:11px 14px;border:1px solid var(--bd2);border-radius:11px;font-size:14.8px;font-family:inherit';
    var list = document.createElement('div');
    list.style.cssText='position:absolute;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid var(--bd);border-radius:11px;box-shadow:0 8px 24px rgba(16,24,40,.12);z-index:30;max-height:320px;overflow:auto;display:none';
    wrap.appendChild(inp); wrap.appendChild(list); b.appendChild(wrap);

    var lkTimer=null;
    inp.addEventListener('input', function(){
      clearTimeout(lkTimer);
      var kw=inp.value.trim();
      if(kw.length<2){ list.style.display='none'; return; }
      lkTimer=setTimeout(function(){ doLookupSearch(kw, list, inp, s); }, 300);
    });

    var srow=document.createElement('div'); srow.className='subrow';
    var skipBtn=document.createElement('button');
    skipBtn.className='btn btn--sm'; skipBtn.type='button';
    skipBtn.textContent='검색 없이 직접 입력할게요';
    skipBtn.onclick=function(){ clearBox(); me('직접 입력할게요'); step++; next(); };
    srow.appendChild(skipBtn); b.appendChild(srow);
    inp.focus();
    return;
  }

  if (s.type === 'assembly'){
    /* 집결지 — 지도를 눌러 위치를 찍습니다.
       지도가 안 뜨는 환경(키 미설정 등)에서는 아래 검색창으로도 지정할 수 있습니다.
       ★ 저장은 반드시 save() 를 통해야 서버에 반영됩니다(answers 변수는 쓰이지 않습니다). */
    var aLat = parseFloat(SAVED.bd_lat || '') || null;
    var aLng = parseFloat(SAVED.bd_lng || '') || null;

    var picked = {
      lat: SAVED.assembly_lat || '',
      lng: SAVED.assembly_lng || ''
    };

    // 지도 자리
    var mapWrap = document.createElement('div');
    mapWrap.style.cssText='border:1px solid var(--bd2);border-radius:11px;overflow:hidden;margin-bottom:8px';
    var mapEl = document.createElement('div');
    mapEl.style.cssText='width:100%;height:280px;background:#eef2f7';
    mapWrap.appendChild(mapEl); b.appendChild(mapWrap);

    var tip = document.createElement('div');
    tip.style.cssText='font-size:12.5px;color:var(--mut);margin:0 0 12px';
    tip.innerHTML = '<b>①</b> 지도를 눌러 집결지 위치를 찍어주세요.';
    b.appendChild(tip);

    // 장소 검색(지도가 안 뜰 때 대비 + 편의)
    var searchWrap = document.createElement('div');
    searchWrap.style.position = 'relative';
    var sinp = document.createElement('input');
    sinp.type='text'; sinp.autocomplete='off';
    sinp.placeholder='장소를 검색해서 찾을 수도 있습니다 (예: ○○공원)';
    sinp.style.cssText='width:100%;padding:10px 13px;border:1px solid var(--bd2);border-radius:10px;font-size:13.5px;font-family:inherit';
    var slist = document.createElement('div');
    slist.style.cssText='position:absolute;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1px solid var(--bd);border-radius:11px;box-shadow:0 8px 24px rgba(16,24,40,.12);z-index:30;max-height:240px;overflow:auto;display:none';
    searchWrap.appendChild(sinp); searchWrap.appendChild(slist); b.appendChild(searchWrap);

    // 장소 유형
    var kindLabel = document.createElement('div');
    kindLabel.style.cssText='font-size:12.5px;color:var(--mut);margin:14px 0 8px';
    kindLabel.innerHTML='<b>②</b> 집결지 이름을 넣어주세요';
    b.appendChild(kindLabel);

    var kindWrap = document.createElement('div'); kindWrap.className='opts';
    var KINDS = ['주차장','앞 공터','옆 공원','운동장','인근 광장','건물 앞 도로변'];
    var kindBtns = [];
    KINDS.forEach(function(k){
      var btn=document.createElement('button');
      btn.className='btn btn--sm'; btn.type='button'; btn.textContent=k;
      btn.onclick=function(){
        kindBtns.forEach(function(x){ x.classList.remove('is-on'); });
        btn.classList.add('is-on');
        kindInput.value = k;
        kindInput.dispatchEvent(new Event('input'));   // 저장 버튼 강조를 함께 켭니다
        kindInput.style.borderColor = '#22c55e';
        if (picked.lat){
          tip.innerHTML = '✅ 준비됐습니다. 아래 <b>집결지로 저장</b>을 눌러주세요.';
          tip.style.color = '#15803d';
        }
      };
      kindBtns.push(btn); kindWrap.appendChild(btn);
    });
    b.appendChild(kindWrap);

    var kindInput = document.createElement('input');
    kindInput.type='text';
    kindInput.placeholder='또는 직접 입력 (예: 건너편 은행 주차장)';
    kindInput.value = SAVED.assembly_kind || '';
    kindInput.style.cssText='width:100%;padding:11px 14px;border:1px solid var(--bd2);border-radius:11px;font-size:14.8px;font-family:inherit;margin-top:8px';
    b.appendChild(kindInput);

    // 저장 / 건너뛰기
    var srow=document.createElement('div'); srow.className='subrow';
    var okBtn=document.createElement('button');
    okBtn.className='btn btn--primary btn--sm'; okBtn.type='button'; okBtn.textContent='집결지로 저장';

    /* 이름을 적으면 저장 버튼을 눈에 띄게 해서 다음 행동을 유도합니다 */
    function nudgeSave(){
      var ready = kindInput.value.trim() !== '';
      okBtn.classList.toggle('btn--nudge', ready);
      if (ready){
        tip.innerHTML = '✅ 준비됐습니다. 아래 <b>집결지로 저장</b>을 눌러주세요.';
        tip.style.color = '#15803d';
      }
    }
    kindInput.addEventListener('input', nudgeSave);
    nudgeSave();   // 이미 적혀 있으면 바로 표시

    okBtn.onclick=function(){
      var kind = kindInput.value.trim();
      if (!picked.lat && !kind){
        tip.textContent = '지도에서 위치를 찍거나, 어떤 곳인지 적어주세요.';
        tip.style.color = '#b91c1c';
        return;
      }
      var patch = { assembly_kind: kind };
      if (picked.lat && picked.lng){
        patch.assembly_lat = String(picked.lat);
        patch.assembly_lng = String(picked.lng);
      }
      clearBox(); me('집결지: ' + (kind || '지도에 표시한 위치'));
      save(patch, function(){ step++; next(); });
    };
    var skipBtn=document.createElement('button');
    skipBtn.className='btn btn--sm'; skipBtn.type='button'; skipBtn.textContent='나중에 정할게요';
    skipBtn.onclick=function(){ clearBox(); me('나중에 정할게요'); step++; next(); };
    srow.appendChild(okBtn); srow.appendChild(skipBtn); b.appendChild(srow);

    // ── 지도 그리기 ──
    var mapObj = null, marker = null;
    function setMarker(latlng){
      if (!mapObj) return;
      if (marker) marker.setMap(null);
      marker = new kakao.maps.Marker({ map: mapObj, position: latlng });
      picked.lat = latlng.getLat(); picked.lng = latlng.getLng();
      tip.innerHTML = '✅ 위치를 찍었습니다. <b>이제 집결지 이름을 넣어주세요.</b>';
      tip.style.color = '#15803d';
      // 다음에 할 일(이름 넣기)을 눈에 띄게 강조합니다.
      kindLabel.textContent = '집결지 이름을 넣어주세요';
      kindLabel.style.color = '#15803d';
      kindLabel.style.fontWeight = '700';
      kindInput.style.borderColor = '#22c55e';
      kindInput.style.boxShadow = '0 0 0 3px rgba(34,197,94,.15)';
      okBtn.classList.add('btn--primary');
      if (!kindInput.value.trim()) kindInput.focus();
    }

    loadKakaoMap(function(){
      var center = new kakao.maps.LatLng(aLat || 37.5665, aLng || 126.9780);
      mapObj = new kakao.maps.Map(mapEl, { center: center, level: 3 });

      // 건물 위치(파란 점) — 집결지 마커와 구분
      if (aLat && aLng){
        new kakao.maps.Circle({
          map: mapObj, center: center, radius: 6,
          strokeWeight: 2, strokeColor: '#2563eb', strokeOpacity: 1,
          fillColor: '#2563eb', fillOpacity: 0.9
        });
        new kakao.maps.CustomOverlay({
          map: mapObj, position: center, yAnchor: 2.2,
          content: '<div style="background:#2563eb;color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;white-space:nowrap">건물</div>'
        });
      }

      // 이미 저장된 집결지가 있으면 복원
      if (picked.lat && picked.lng){
        setMarker(new kakao.maps.LatLng(parseFloat(picked.lat), parseFloat(picked.lng)));
      }
      kakao.maps.event.addListener(mapObj, 'click', function(e){ setMarker(e.latLng); });
    }, function(){
      // 지도를 못 불러온 경우 — 검색만으로도 지정할 수 있게 안내
      mapWrap.style.display = 'none';
      tip.textContent = '지도를 불러오지 못했습니다. 아래에서 장소를 검색하거나 직접 적어주세요.';
    });

    // ── 검색으로 지정 ──
    var asmTimer=null;
    sinp.addEventListener('input', function(){
      clearTimeout(asmTimer);
      var kw=sinp.value.trim();
      if(kw.length<2){ slist.style.display='none'; return; }
      asmTimer=setTimeout(function(){
        var fd=new FormData(); fd.append('act','search'); fd.append('csrf',CSRF); fd.append('keyword',kw);
        fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(r){return r.json();})
          .then(function(j){
            slist.innerHTML='';
            var rs=(j&&j.results)||[];
            if(!rs.length){
              slist.style.display='block';
              slist.innerHTML='<div style="padding:10px 13px;color:var(--mut)">검색 결과가 없습니다.</div>';
              return;
            }
            rs.forEach(function(a){
              var d=document.createElement('div');
              d.style.cssText='padding:10px 13px;cursor:pointer;border-bottom:1px solid #f0f2f6';
              d.innerHTML='<div style="font-weight:700">'+esc(a.place)+'</div>'+
                          '<div style="font-size:11.5px;color:var(--mut);margin-top:2px">'+esc(a.road||a.jibun||'')+'</div>';
              d.onmouseover=function(){ d.style.background='#f3f6fb'; };
              d.onmouseout=function(){ d.style.background='#fff'; };
              d.onclick=function(){
                slist.style.display='none';
                sinp.value = a.place;
                if (!kindInput.value.trim()) kindInput.value = a.place;
                var la = parseFloat(a.lat||''), ln = parseFloat(a.lng||'');
                if (la && ln){
                  if (mapObj){
                    var pos = new kakao.maps.LatLng(la, ln);
                    mapObj.setCenter(pos);
                    setMarker(pos);
                  } else {
                    picked.lat = la; picked.lng = ln;
                    tip.textContent = '집결지 위치가 지정되었습니다: ' + a.place;
                    tip.style.color = 'var(--mut)';
                  }
                }
              };
              slist.appendChild(d);
            });
            slist.style.display='block';
          })
          .catch(function(){ slist.style.display='none'; });
      }, 300);
    });

    return;
  }

  if (s.type === 'choice'){
    /* 등급 질문이면, 건축물대장으로 이미 알 수 있는 경우 미리 계산해서 추천합니다. */
    var autoGrade = (s.field === 'grade') ? guessGrade() : null;
    if (s.field === 'grade'){
      /* 추천이 왜 안 나오는지 콘솔에서 바로 확인할 수 있게 남깁니다. */
      console.log('[등급추천 진단]', {
        용도: SAVED.use, 대장주용도: SAVED.bd_use_main, 대장기타용도: SAVED.bd_use_etc,
        지상층: SAVED.floor_a, 지하층: SAVED.floor_b,
        연면적: SAVED.area_t, 높이: SAVED.bd_height,
        결과: autoGrade
      });
    }
    if (autoGrade){
      var rec = document.createElement('div');
      rec.style.cssText = 'background:#eefaf1;border:1px solid #bfe6cb;border-radius:11px;' +
        'padding:12px 14px;margin-bottom:12px;font-size:13.5px;color:#15803d;line-height:1.65';
      rec.innerHTML = '건축물대장 정보로 계산하면 <b>' + esc(autoGrade.grade) + '</b>입니다.<br>' +
        '<span style="font-size:12px;color:var(--mut2)">' + esc(autoGrade.why) + '</span>';
      b.appendChild(rec);
    } else if (s.field === 'grade'){
      /* 규모만으로는 2급·3급이 안 갈립니다(법으로 소방설비를 봐야 합니다).
         아무 안내도 없으면 고장처럼 보이므로, 왜 추천이 없는지 알려줍니다. */
      var flA  = parseInt(SAVED.floor_a || '0', 10) || 0;
      var area = parseFloat(SAVED.area_t || '0') || 0;
      var note = document.createElement('div');
      note.style.cssText = 'background:var(--bg2);border:1px solid var(--bd);border-radius:11px;' +
        'padding:12px 14px;margin-bottom:12px;font-size:13px;color:var(--mut2);line-height:1.7';
      if (!flA && !area){
        note.innerHTML = '층수·연면적이 아직 없어서 자동 계산을 못 했습니다. ' +
          '아시는 등급을 고르시거나, 모르시면 <b>잘 모르겠어요</b>를 눌러주세요.';
      } else {
        note.innerHTML = '이 규모(' +
          (flA ? '지상 ' + flA + '층' : '') + (flA && area ? ' · ' : '') +
          (area ? area.toLocaleString() + '㎡' : '') +
          ')는 <b>2급 또는 3급</b>인데, 둘 중 어느 쪽인지는 설치된 소방설비로 갈립니다.<br>' +
          '<b>잘 모르겠어요</b>를 누르시면 몇 가지 여쭤보고 알려드리겠습니다.';
      }
      b.appendChild(note);
    }

    if (s.field === 'grade'){
      var choose=document.createElement('div'); choose.className='choice-prompt';
      choose.innerHTML='<span class="choice-prompt__badge">선택 필요</span><span class="choice-prompt__text">소방안전관리 등급을 선택해 주세요</span>'; b.appendChild(choose);
    }
    var w=document.createElement('div'); w.className='opts';
    s.options.forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
      btn.textContent = o + (autoGrade && autoGrade.grade === o ? ' ✓' : '');
      if (autoGrade && autoGrade.grade === o){
        btn.style.cssText = 'border-color:#22c55e;background:#f0fdf4;color:#15803d;font-weight:700';
      }
      btn.onclick=function(){ submit(s, o, {}, o); };
      w.appendChild(btn);
    });
    b.appendChild(w);
    addSkip(s, b);
    addDontKnow(s, b);
    return;
  }

  if (s.type === 'preset'){
    var presets=document.createElement('div'); presets.className='opts';
    s.options.forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
      btn.textContent=o;
      btn.onclick=function(){ submit(s, o, {}, o); };
      presets.appendChild(btn);
    });
    b.appendChild(presets);
    var custom=document.createElement('div'); custom.className='inrow'; custom.style.marginTop='10px';
    var customInput=document.createElement('input');
    customInput.type='text'; customInput.placeholder='직접 입력';
    custom.appendChild(customInput); b.appendChild(custom);
    var customGo = mkGo(b, function(){
      var v=(customInput.value||'').trim();
      if(v===''){ customInput.focus(); return; }
      var patch={}; patch[s.field]=v;
      submit(s, null, patch, v);
    });
    customInput.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); customGo.click(); } });
    addSkip(s, b);
    customInput.focus();
    return;
  }

  if (s.type === 'areas'){
    var areaRow=document.createElement('div'); areaRow.className='inrow';
    var areaWrap=document.createElement('div'); areaWrap.className='pair';
    var areaInputs={};
    [['area_t','연면적','예: 8500'],['area_f','바닥면적','예: 900']].forEach(function(p){
      var lb=document.createElement('label');
      lb.innerHTML='<span>'+esc(p[1])+' (㎡)</span>';
      var i=document.createElement('input');
      i.type='number'; i.min='0'; i.inputMode='numeric'; i.placeholder=p[2];
      i.value = SAVED[p[0]] || '';
      i.style.padding='11px 14px'; i.style.border='1px solid var(--bd2)';
      i.style.borderRadius='11px'; i.style.fontSize='14.8px';
      lb.appendChild(i); areaWrap.appendChild(lb); areaInputs[p[0]]=i;
    });
    areaRow.appendChild(areaWrap); b.appendChild(areaRow);
    var areaGo = mkGo(b, function(){
      var total=(areaInputs.area_t.value||'').trim();
      var floor=(areaInputs.area_f.value||'').trim();
      if(total==='' && floor===''){ areaInputs.area_t.focus(); return; }
      var patch={area_t:total || (SAVED.area_t || ''), area_f:floor || (SAVED.area_f || '')};
      var shown=[];
      if(patch.area_t!=='') shown.push('연면적 '+Number(patch.area_t).toLocaleString()+'㎡');
      if(patch.area_f!=='') shown.push('바닥면적 '+Number(patch.area_f).toLocaleString()+'㎡');
      submit(s, null, patch, shown.join(' · '));
    });
    areaInputs.area_t.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); areaGo.click(); } });
    areaInputs.area_f.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); areaGo.click(); } });
    addDontKnow(s, b);
    areaInputs.area_t.focus();
    return;
  }

  if (s.type === 'pair'){
    var row=document.createElement('div'); row.className='inrow';
    var wrap=document.createElement('div'); wrap.className='pair';
    var inputs={};
    s.pair.forEach(function(p){
      var lb=document.createElement('label');
      lb.innerHTML='<span>'+esc(p.label)+'</span>';
      var i=document.createElement('input'); i.type='number'; i.min='0'; i.placeholder=p.ph||'';
      i.style.padding='11px 14px'; i.style.border='1px solid var(--bd2)';
      i.style.borderRadius='11px'; i.style.fontSize='14.8px';
      lb.appendChild(i); wrap.appendChild(lb); inputs[p.field]=i;
    });
    row.appendChild(wrap);
    b.appendChild(row);
    var go=mkGo(b, function(){
      var a=(inputs.floor_a.value||'').trim(), g=(inputs.floor_b.value||'').trim();
      if(a===''){ inputs.floor_a.focus(); return; }
      submit(s, null, {floor_a:a, floor_b:g||'0'}, '지상 '+a+'층' + (g&&g!=='0' ? ' · 지하 '+g+'층' : ''));
    });
    inputs.floor_a.focus();
    return;
  }

  if (s.type === 'mgr'){
    var r2=document.createElement('div'); r2.className='inrow';
    var w2=document.createElement('div'); w2.className='pair';
    var f={};
    [['name','성함','홍길동'],['tel','연락처 (선택)','010-1234-5678']].forEach(function(p){
      var lb=document.createElement('label'); lb.innerHTML='<span>'+esc(p[1])+'</span>';
      var i=document.createElement('input'); i.type='text'; i.placeholder=p[2];
      i.style.padding='11px 14px'; i.style.border='1px solid var(--bd2)';
      i.style.borderRadius='11px'; i.style.fontSize='14.8px';
      lb.appendChild(i); w2.appendChild(lb); f[p[0]]=i;
    });
    r2.appendChild(w2); b.appendChild(r2);
    mkGo(b, function(){
      var nm=(f.name.value||'').trim();
      if(nm===''){ f.name.focus(); return; }
      var m=[{name:nm, tel:(f.tel.value||'').trim(), type:'주', appt:'', qual:''}];
      submit(s, null, {mgrs:m}, nm + ((f.tel.value||'').trim() ? ' · '+f.tel.value.trim() : ''));
    });
    f.name.focus();
    return;
  }

  if (s.type === 'staff'){
    var r3=document.createElement('div'); r3.className='inrow';
    var w3=document.createElement('div'); w3.className='pair';
    var g={};
    [['wd_day','평일 주간'],['wd_night','평일 야간'],['hd_day','휴일 주간'],['hd_night','휴일 야간']]
    .forEach(function(p){
      var lb=document.createElement('label'); lb.innerHTML='<span>'+esc(p[1])+'</span>';
      var i=document.createElement('input'); i.type='number'; i.min='0'; i.placeholder='0';
      i.style.padding='11px 14px'; i.style.border='1px solid var(--bd2)';
      i.style.borderRadius='11px'; i.style.fontSize='14.8px';
      lb.appendChild(i); w3.appendChild(lb); g[p[0]]=i;
    });
    r3.appendChild(w3); b.appendChild(r3);
    mkGo(b, function(){
      var v={}, txt=[];
      [['wd_day','평일 주간'],['wd_night','평일 야간'],['hd_day','휴일 주간'],['hd_night','휴일 야간']]
      .forEach(function(p){
        var x=(g[p[0]].value||'').trim(); v[p[0]]=x;
        if(x!=='') txt.push(p[1]+' '+x+'명');
      });
      if(!txt.length){ g.wd_day.focus(); return; }
      submit(s, null, v, txt.join(' · '));
    });
    addSkip(s, b);
    g.wd_day.focus();
    return;
  }

  /* text / number */
  var r=document.createElement('div'); r.className='inrow';
  var inp=document.createElement('input');
  inp.type = (s.type==='number') ? 'number' : 'text';
  if (s.type==='number'){ inp.min='0'; inp.inputMode='numeric'; }
  inp.placeholder = s.ph||'';
  inp.value = String(SAVED[s.field]||'');
  r.appendChild(inp);
  if (s.unit){ var u=document.createElement('span'); u.className='unit'; u.textContent=s.unit; r.appendChild(u); }
  b.appendChild(r);
  var goBtn = mkGo(b, function(){
    var v=(inp.value||'').trim();
    if(v===''){ inp.focus(); return; }
    var patch={}; patch[s.field]=v;
    submit(s, null, patch, v + (s.unit ? ' ' + s.unit : ''));
  });
  inp.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); goBtn.click(); } });

  /* ── 대표자·전화번호 단계: 앞서 답한 소방안전관리자를 버튼으로 ──
     누르면 값이 채워지고 바로 다음 질문으로 넘어갑니다.
     대표자를 고르면 그분 연락처까지 함께 저장해, 전화 질문을 건너뜁니다. */
  if (s.field === 'rep' || s.field === 'tel') addMgrQuickFill(s, b, inp);

  addSkip(s, b);
  addDontKnow(s, b);
  inp.focus();
}

/* 저장된 소방안전관리자 목록에서 버튼을 만든다 */
function addMgrQuickFill(s, container, inp){
  var list = (SAVED.mgrs || []).filter(function(m){
    return (m && ((m.name||'').trim() !== '' || (m.tel||'').trim() !== ''));
  });
  if (!list.length) return;

  var row = container.querySelector('.subrow');
  if (!row){ row=document.createElement('div'); row.className='subrow'; container.appendChild(row); }
  var made = 0;

  list.forEach(function(m){
    var nm = (m.name||'').trim(), tl = (m.tel||'').trim();

    if (s.field === 'rep') {
      if (nm === '') return;
      var btn = document.createElement('button');
      btn.className = 'btn btn--sm btn--manager'; btn.type = 'button';
      btn.innerHTML = '관리자 '+esc(nm)+' 선택' + (tl ? ' <span style="opacity:.68;font-size:10.5px">· 연락처 포함</span>' : '');
      btn.title = '앞에서 입력한 소방안전관리자 정보를 대표자 정보로 사용합니다.';
      btn.onclick = function(){
        var patch = { rep: nm };
        var shown = nm;
        if (tl){ patch.tel = tl; shown = nm + ' · ' + tl; }   // 전화까지 한 번에
        clearBox(); me(shown);
        for (var k in patch) SAVED[k] = patch[k];
        save(patch, function(){
          /* 연락처까지 채웠으면 전화 질문은 건너뛴다 */
          step++;
          if (patch.tel){
            var nx = STEPS[step];
            if (nx && nx.field === 'tel') step++;
          }
          next();
        });
      };
      row.appendChild(btn); made++;
    }

    if (s.field === 'tel') {
      if (tl === '') return;
      var b2 = document.createElement('button');
      b2.className = 'btn btn--sm btn--manager'; b2.type = 'button';
      b2.innerHTML = '관리자 연락처 사용' + (nm ? ' <span style="opacity:.68;font-size:10.5px">· '+esc(nm)+'</span>' : '');
      b2.title = tl;
      b2.onclick = function(){ submit(s, tl, null, tl); };
      row.appendChild(b2); made++;
    }
  });

  if (!made && !row.children.length) row.remove();
}

function addYeohubReview(s, container){
  if (!container || !container.isConnected || container.querySelector('.yeohub-review')) return;
  var row = document.createElement('div');
  row.className = 'subrow yeohub-review';
  var btn = document.createElement('button');
  btn.className = 'btn btn--sm';
  btn.type = 'button';
  btn.textContent = '잘 모르겠어요 · YeoHub에 요청하기';
  btn.onclick = function(){ requestYeohubReview(s, btn); };
  row.appendChild(btn);
  container.appendChild(row);
}

function requestYeohubReview(s, btn){
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = '요청 중…';
  var fd = new FormData();
  fd.append('kind', 'review');
  var group = s.reviewGroup || '건물 기본정보';
  fd.append('text', group + ': ' + (s.label ? s.label + ' - ' : '') + s.q);
  fetch('/assist_log.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(j){
      if (!j || !j.ok) throw new Error((j && j.error) || 'request');
      btn.textContent = 'YeoHub 요청 완료 ✓';
      clearBox();
      me('YeoHub에 검토요청');
      bot(md('**YeoHub에 검토를 요청했습니다.**\n관리자가 회원님의 입력내용을 확인할 수 있습니다.'));
      step++;
      setTimeout(next, 520);
    })
    .catch(function(){
      btn.disabled = false;
      btn.textContent = '잘 모르겠어요 · YeoHub에 요청하기';
      alert('요청을 접수하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    });
}

function mkGo(b, fn){
  var row=document.createElement('div'); row.className='subrow';
  var go=document.createElement('button'); go.className='btn btn--pri'; go.type='button';
  go.textContent='다음'; go.onclick=fn;
  row.appendChild(go); b.appendChild(row);
  return go;
}
function addSkip(s, b){
  if(!s.skip) return;
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var sk=document.createElement('button'); sk.className='btn btn--sm'; sk.type='button';
  sk.textContent='건너뛰기';
  sk.onclick=function(){ clearBox(); me('(건너뜀)'); step++; next(); };
  row.appendChild(sk);
}

/* 저장된 값은 지우지 않고 이전 질문을 다시 열어 수정합니다. */
function addBack(s,b){
  if(step<=0 || !b || !b.isConnected || b.querySelector('.btn--back')) return;
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var back=document.createElement('button'); back.className='btn btn--sm btn--back'; back.type='button';
  back.textContent='이전 답변 수정';
  back.onclick=function(){
    clearBox(); step=Math.max(0,step-1);
    bot(md('이전 답변을 다시 보여드릴게요. 새로 입력하면 기존 내용이 바뀝니다.'));
    setTimeout(function(){ ask(STEPS[step]); },180);
  };
  row.appendChild(back);
}

/* 모르는 항목은 함께 찾아본다 */
function addDontKnow(s, b){
  if(!s.dontknow || !HELP[s.dontknow]) return;
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var dk=document.createElement('button'); dk.className='btn btn--sm'; dk.type='button';
  dk.style.cssText='border-color:#c7dbff;color:var(--brand2)';
  dk.textContent='🤔 잘 모르겠어요';
  dk.onclick=function(){
    clearBox(); me('잘 모르겠어요');
    setTimeout(function(){ HELP[s.dontknow](); }, 260);
  };
  row.appendChild(dk);
}

function submit(s, val, patch, shown){
  if (val !== null && val !== undefined){ patch = {}; patch[s.field] = val; }
  clearBox();
  me(shown);
  /* 화면상 저장 상태도 갱신해 두어야 '이미 답함' 판정이 맞는다 */
  for (var k in patch) SAVED[k] = patch[k];
  save(patch, function(){ step++; next(); });
}

/* ── 건물 검색 (카카오) → 후보 목록 ───────────────────────── */
function doLookupSearch(kw, list, inp, s){
  var fd=new FormData(); fd.append('act','search'); fd.append('csrf',CSRF); fd.append('keyword',kw);
  fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();})
    .then(function(j){
      list.innerHTML='';
      if(!j.ok){ list.style.display='block';
        list.innerHTML='<div style="padding:10px 13px;color:var(--mut)">'+esc(j.error||'검색 실패')+'</div>';
        appendDirectAddrOption(list, kw, s); return; }
      var rs=j.results||[];
      if(!rs.length){ list.style.display='block';
        list.innerHTML='<div style="padding:10px 13px;color:var(--mut)">검색 결과가 없습니다.</div>';
        appendDirectAddrOption(list, kw, s); return; }
      rs.forEach(function(a){
        var d=document.createElement('div');
        d.style.cssText='padding:10px 13px;cursor:pointer;border-bottom:1px solid #f0f2f6';
        var addr=a.road||a.jibun||'';
        var cat=a.category?' <span style="font-size:11px;color:var(--brand2)">·'+esc(a.category)+'</span>':'';
        d.innerHTML='<div style="font-weight:700">'+esc(a.place)+cat+'</div>'+
                    '<div style="font-size:11.5px;color:var(--mut);margin-top:2px">'+esc(addr)+'</div>';
        d.onmouseover=function(){ d.style.background='#f3f6fb'; };
        d.onmouseout=function(){ d.style.background='#fff'; };
        d.onclick=function(){ doLookupPick(a, s); };
        list.appendChild(d);
      });
      /* 이름으로 찾아도 안 나올 수 있으니, 후보가 있어도 직접 입력 선택지를 항상 열어둔다 */
      appendDirectAddrOption(list, kw, s);
      list.style.display='block';
    })
    .catch(function(){ list.style.display='block';
      list.innerHTML='<div style="padding:10px 13px;color:#991b1b">검색 중 연결이 끊겼습니다.</div>';
      appendDirectAddrOption(list, kw, s); });
}

/* ── 카카오맵에 없는 곳 대비: 입력한 글자를 '주소'로 보고 juso 로 바로 조회 ──
   카카오 키워드검색은 상호(장소) 위주라, 등록 안 된 업체·건물은 이름으로도
   주소로도 결과가 0건일 수 있다. 이때는 카카오를 건너뛰고 juso 로 직접 넘긴다. */
function appendDirectAddrOption(list, kw, s){
  var d=document.createElement('div');
  d.style.cssText='padding:11px 13px;cursor:pointer;background:#fafbfd';
  d.innerHTML='<div style="font-weight:700;color:var(--navy)">📍 "'+esc(kw)+'" 를 주소로 직접 찾기</div>'+
              '<div style="font-size:11.5px;color:var(--mut);margin-top:2px">카카오맵에 없는 곳이면 이렇게 시도해 보세요</div>';
  d.onmouseover=function(){ d.style.background='#eef4ff'; };
  d.onmouseout=function(){ d.style.background='#fafbfd'; };
  d.onclick=function(){
    /* road·jibun 둘 다 같은 문자열을 넘긴다 — 서버가 juso 로 알아서 코드 변환한다 */
    doLookupPick({ place: kw, road: kw, jibun: kw }, s);
  };
  list.appendChild(d);
}

/* ── 후보 클릭 → juso+건축HUB 조회 → 기본정보 한번에 저장 ── */
function doLookupPick(a, s){
  clearBox(); me(a.place + (a.road?(' · '+a.road):''));
  typing(function(){
    var loading=bot('<div class="lookup-loading"><span class="lookup-spinner" aria-hidden="true"></span><div><b>건축물대장을 가져오는 중입니다</b><span>주소 확인 후 건축물 정보를 조회하고 있습니다. 잠시만 기다려 주세요.</span></div></div>');
    var loadingBody=loading.querySelector('.msg__b');
    var slowTimer=setTimeout(function(){
      if(loadingBody) loadingBody.innerHTML='<div class="lookup-loading"><span class="lookup-spinner" aria-hidden="true"></span><div><b>조회가 평소보다 오래 걸리고 있습니다</b><span>공공데이터 응답을 기다리는 중입니다. 화면을 닫지 않아도 됩니다.</span></div></div>';
    },4500);
    var verySlowTimer=setTimeout(function(){
      if(loadingBody) loadingBody.innerHTML='<div class="lookup-loading"><span class="lookup-spinner" aria-hidden="true"></span><div><b>여러 건축물 정보를 확인하고 있습니다</b><span>최대 30초 정도 걸릴 수 있습니다. 응답이 없으면 재시도할 수 있게 안내해 드립니다.</span></div></div>';
    },12000);
    var controller=window.AbortController?new AbortController():null;
    var abortTimer=controller?setTimeout(function(){controller.abort();},35000):null;
    function endLoading(){ clearTimeout(slowTimer); clearTimeout(verySlowTimer); if(abortTimer) clearTimeout(abortTimer); if(loading&&loading.isConnected) loading.remove(); }
    var fd=new FormData();
    fd.append('act','lookup'); fd.append('csrf',CSRF);
    fd.append('place',a.place||''); fd.append('road',a.road||''); fd.append('jibun',a.jibun||'');
    fd.append('lat',a.lat||''); fd.append('lng',a.lng||'');
    fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin',signal:controller?controller.signal:undefined})
      .then(function(r){return r.json();})
      .then(function(j){
        endLoading();
        if(!j || !j.ok){ showLookupRecovery(a,s,(j&&j.error)||'건축물대장 조회에 실패했습니다.'); return; }
        var patch=j.patch||{};
        var lines=[];
        if(patch.name)    lines.push('**대상명** '+patch.name);
        if(patch.address) lines.push('**소재지** '+patch.address);
        if(patch.use)     lines.push('**용도** '+patch.use);
        if(patch.area_t)  lines.push('**연면적** '+Number(patch.area_t).toLocaleString()+'㎡ (전체)');

        // 동이 2개 이상이면 → 어느 동인지 사용자가 고르게 한다
        if (j.dongCnt > 1 && j.dongList && j.dongList.length > 1) {
          bot(md('건축물대장에서 **'+j.dongCnt+'개 동**을 찾았습니다.\n'+lines.join('\n')));
          askDongPick(j, patch);
          return;
        }

        if(patch.floor_a) lines.push('**층수** 지상 '+patch.floor_a+'층'+((patch.floor_b&&patch.floor_b!=='0')?(' · 지하 '+patch.floor_b+'층'):''));
        if(patch.bd_struct) lines.push('**구조** '+patch.bd_struct);
        bot(md((j.partial ? '일부만 찾았습니다.\n' : '건축물대장에서 찾았습니다. 아래 내용으로 채웠습니다.\n')
               + lines.join('\n') + (j.note ? '\n\n'+j.note : '')));
        for(var k in patch){ if(patch[k]!=='' && patch[k]!==null) SAVED[k]=patch[k]; }
        save(patch, function(){ step++; setTimeout(next,500); });
      })
      .catch(function(err){ endLoading(); showLookupRecovery(a,s,(err&&err.name==='AbortError')?'조회 시간이 35초를 초과했습니다.':'조회 중 연결이 끊겼습니다.'); });
  });
}

function showLookupRecovery(a,s,message){
  bot(md('건축물 정보를 가져오지 못했습니다.\n\n'+message));
  var b=box();
  var notice=document.createElement('div'); notice.className='lookup-recovery';
  notice.textContent='공공데이터가 일시적으로 늦거나 해당 주소의 대장이 제공되지 않을 수 있습니다.';
  b.appendChild(notice);
  var row=document.createElement('div'); row.className='subrow';
  var retry=document.createElement('button'); retry.className='btn btn--pri'; retry.type='button'; retry.textContent='다시 조회하기';
  retry.onclick=function(){ clearBox(); doLookupPick(a,s); };
  var manual=document.createElement('button'); manual.className='btn'; manual.type='button'; manual.textContent='직접 입력으로 계속';
  manual.onclick=function(){ clearBox(); me('직접 입력으로 계속'); step++; next(); };
  row.appendChild(retry); row.appendChild(manual); b.appendChild(row);
}

/* ── 동 선택 — 여러 동일 때 어느 동(들)을 대상으로 할지 고른다 ──
   모든 동은 bd_dong_list 에 저장되고, 고른 동은 bd_dong_pick 에 기록됩니다.
   (시뮬레이션은 저장된 동별 정보를 그대로 꺼내 쓸 수 있습니다) */
function askDongPick(j, patch){
  bot(md('어느 동을 기준으로 할까요? **여러 개 선택**할 수 있고, 전체를 고르면 합산해서 채웁니다.\n모든 동 정보는 어차피 저장되니, 시뮬레이션은 동별로 그릴 수 있습니다.'), '선택한 동 기준으로 층수·구조가 채워집니다.');
  var b = box();
  var list = j.dongList || [];
  var picked = {};

  var w = document.createElement('div'); w.className='opts';
  list.forEach(function(g, i){
    var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
    var sub = '지상'+g.floor_a+'/지하'+g.floor_b+'층';
    if (g.struct) sub += ' · '+g.struct;
    if (g.area)   sub += ' · '+Number(g.area).toLocaleString()+'㎡';
    btn.innerHTML = '<b>'+esc(g.dong)+'</b><br><span style="font-size:11.5px;color:var(--mut)">'+esc(sub)+'</span>';
    btn.onclick=function(){
      if(picked[i]){ delete picked[i]; btn.style.background=''; btn.style.borderColor=''; }
      else { picked[i]=true; btn.style.background='#eef4ff'; btn.style.borderColor='var(--brand)'; }
    };
    w.appendChild(btn);
  });
  b.appendChild(w);

  var row=document.createElement('div'); row.className='subrow';
  var okBtn=document.createElement('button');
  okBtn.className='btn btn--primary btn--sm'; okBtn.type='button';
  okBtn.textContent='선택한 동으로 진행';
  okBtn.onclick=function(){
    var idx=Object.keys(picked).map(Number);
    if(!idx.length){ return; }          // 아무것도 안 고르면 무시
    applyDongPick(j, patch, idx);
  };
  var allBtn=document.createElement('button');
  allBtn.className='btn btn--sm'; allBtn.type='button';
  allBtn.textContent='전체 '+list.length+'개 동';
  allBtn.onclick=function(){
    applyDongPick(j, patch, list.map(function(_,i){return i;}), true);
  };
  row.appendChild(okBtn); row.appendChild(allBtn);
  b.appendChild(row);
}

/* 고른 동을 대표값에 반영하고 저장 */
function applyDongPick(j, patch, idx, isAll){
  var list=j.dongList||[];
  var sel=idx.map(function(i){return list[i];}).filter(Boolean);
  if(!sel.length) return;

  // 대표값: 층수는 최대, 구조·높이는 가장 큰 동 기준, 연면적은 합
  var maxA=0, maxB=0, maxH=0, sumArea=0, struct='', biggest=null;
  sel.forEach(function(g){
    maxA=Math.max(maxA, parseInt(g.floor_a||0,10));
    maxB=Math.max(maxB, parseInt(g.floor_b||0,10));
    maxH=Math.max(maxH, parseFloat(g.height||0));
    sumArea+=parseFloat(g.area||0);
    if(!biggest || parseFloat(g.area||0)>parseFloat(biggest.area||0)) biggest=g;
  });
  if(biggest && biggest.struct) struct=biggest.struct;

  patch.floor_a   = String(maxA);
  patch.floor_b   = String(maxB);
  patch.bd_struct = struct;
  patch.bd_height = maxH ? String(maxH) : '';
  if(sumArea>0) patch.area_t = String(Math.round(sumArea));
  patch.bd_dong_pick = isAll ? 'ALL' : sel.map(function(g){return g.dong;}).join(',');

  var label = isAll ? ('전체 '+sel.length+'개 동') : sel.map(function(g){return g.dong;}).join(', ');
  clearBox(); me(label);
  typing(function(){
    var lines=[];
    lines.push('**선택** '+label);
    lines.push('**층수** 지상 '+patch.floor_a+'층'+((patch.floor_b&&patch.floor_b!=='0')?(' · 지하 '+patch.floor_b+'층'):''));
    if(patch.bd_struct) lines.push('**구조** '+patch.bd_struct);
    if(patch.area_t)    lines.push('**연면적** '+Number(patch.area_t).toLocaleString()+'㎡');
    bot(md('이 내용으로 채웠습니다.\n'+lines.join('\n')+
           '\n\n동별 상세는 모두 저장되어 있어, 피난 시뮬레이션은 동마다 따로 만들 수 있습니다.'));
    for(var k in patch){ if(patch[k]!=='' && patch[k]!==null) SAVED[k]=patch[k]; }
    save(patch, function(){ step++; setTimeout(next,500); });
  });
}

/* ── 마무리 ───────────────────────────────────────────── */
function finish(){
  clearBox();
  typing(function(){
    bot(md('건물 기본정보를 저장했습니다. 👏\n\n아래 내용으로 저장되었고, 잘못된 곳은 표에서 고치실 수 있습니다.'));

    var d=document.createElement('div'); d.className='done';
    var rows=[
      ['대상명', SAVED.name], ['소재지', SAVED.address], ['용도', SAVED.use],
      ['등급', SAVED.grade],
      ['규모', (SAVED.floor_a ? '지상 '+SAVED.floor_a+'층' : '') +
               (SAVED.floor_b && SAVED.floor_b!=='0' ? ' · 지하 '+SAVED.floor_b+'층' : '')],
      ['연면적', SAVED.area_t ? Number(SAVED.area_t).toLocaleString()+'㎡' : ''],
      ['소방안전관리자', (SAVED.mgrs&&SAVED.mgrs[0]) ? SAVED.mgrs[0].name : ''],
      ['대표자', SAVED.rep], ['전화번호', SAVED.tel]
    ];
    if (SAVED.assembly_kind) rows.push(['집결지', SAVED.assembly_kind]);
    var html='<h2>건물 기본정보</h2>';
    rows.forEach(function(r){
      var v=String(r[1]||'').trim();
      html += '<div class="sum"><span class="sum__k">'+esc(r[0])+'</span>'+
              '<span class="sum__v'+(v?'':' none')+'">'+esc(v||'아직 비어 있음')+'</span></div>';
    });
    html += '<div class="doneRow">' +
      '<a class="btn btn--pri" href="<?=h($url('/building_manager.php'))?>">메인으로 →</a>' +
      '<a class="btn" href="<?=h($url('/building_setup.php'))?>">표에서 자세히 고치기</a>' +
      '<a class="btn" href="<?=h($url('/work_log.php'))?>">이번 달 기록표 쓰러 가기</a></div>';
    d.innerHTML=html;
    chat.appendChild(d); down();
  });
}

start();
</script>
<?php require __DIR__ . '/memo_widget.php'; ?>
</body>
</html>
