<?php
declare(strict_types=1);

require_once __DIR__ . '/building_info.php';

function epc_blank(): array {
  return [
    'alarm_method'=>'', 'reporter'=>'', 'controller'=>'',
    'assembly_confirmed'=>'', 'headcount_method'=>'', 'special_notes'=>'',
    'floors'=>[], 'updated'=>'',
  ];
}

function epc_file(): string {
  $buildingFile = bi_file();
  return $buildingFile === '' ? '' : dirname($buildingFile) . '/evacuation_plan.json';
}

function epc_load(): array {
  $file = epc_file();
  if ($file === '' || !is_file($file)) return epc_blank();

  $decoded = json_decode((string)@file_get_contents($file), true);
  $plan = is_array($decoded) ? array_merge(epc_blank(), $decoded) : epc_blank();
  $plan['floors'] = is_array($plan['floors'] ?? null) ? $plan['floors'] : [];
  return epc_published_list_projection($plan);
}

/** Collect only published dong plans for the existing fire-plan import. Never import drafts. */
function epc_published_list_projection(array $plan): array {
  if (($plan['_epl_version'] ?? 0) !== 2) return $plan;
  $active = array_filter((array)($plan['_epl_plans'] ?? []), static fn($row) => is_array($row) && !empty($row['published']) && empty($row['archived']) && empty($row['deleted_at']));
  $out = epc_blank();
  $out['_print_plans']=[];
  $parts = [];
  foreach ($active as $id => $row) {
    $content = $row['published'];
    // Only published content is exported; drafts and edit history stay private.
    $out['_print_plans'][$id]=array_intersect_key($row,array_flip(['id','title','published_title','scope','published','published_at','archived']));
    $name = (string)($row['scope']['name'] ?? $row['title'] ?? '건물');
    foreach (['alarm_method','reporter','controller','assembly_confirmed','headcount_method','special_notes'] as $field) {
      $text = trim((string)($content[$field] ?? ''));
      if ($text !== '') $parts[$field][] = count($active) > 1 ? $name . ': ' . $text : $text;
    }
    foreach ((array)($content['floors'] ?? []) as $key => $floor) {
      if (!is_array($floor)) continue;
      $floor['label'] = $name . ' · ' . (string)($floor['label'] ?? $key);
      $floor['_epl_floor_key'] = (string)$key;
      $out['floors'][$id . ':' . $key] = $floor;
    }
    if (strcmp((string)($row['published_at'] ?? ''), $out['updated']) > 0) $out['updated'] = (string)$row['published_at'];
  }
  foreach ($parts as $field => $texts) $out[$field] = implode("\n", $texts);
  return $out;
}

function epc_has_content(array $plan): bool {
  foreach (['alarm_method','reporter','controller','assembly_confirmed','headcount_method','special_notes'] as $key) {
    if (trim((string)($plan[$key] ?? '')) !== '') return true;
  }
  return !empty($plan['floors']);
}

function epc_ready(array $plan): bool {
  if (trim((string)($plan['assembly_confirmed'] ?? '')) === '') return false;
  foreach ((array)($plan['floors'] ?? []) as $floor) {
    if (is_array($floor) && trim((string)($floor['primary_route'] ?? '')) !== '') return true;
  }
  return false;
}

function epc_status(array $plan): array {
  $floorCount = 0;
  $occupants = 0;
  foreach ((array)($plan['floors'] ?? []) as $floor) {
    if (!is_array($floor)) continue;
    $floorCount++;
    $occupants += max(0, (int)($floor['occupants'] ?? 0));
  }
  return [
    'has_content'=>epc_has_content($plan),
    'ready'=>epc_ready($plan),
    'floor_count'=>$floorCount,
    'occupants'=>$occupants,
    'updated'=>trim((string)($plan['updated'] ?? '')),
  ];
}

function epc_to_fire_section(array $plan): array {
  if (!epc_has_content($plan)) return [];

  $routes = [];
  $weakLocations = [];
  $weakPlans = [];
  $hasFirstFloor = false;
  $floorCount = 0;
  $noSupportCount = 0;

  foreach ((array)($plan['floors'] ?? []) as $key => $floor) {
    if (!is_array($floor)) continue;
    $floorCount++;
    $label = trim((string)($floor['label'] ?? $key));
    if ((string)($floor['_epl_floor_key'] ?? $key) === '1F') $hasFirstFloor = true;

    $routeParts = [];
    $primary = trim((string)($floor['primary_route'] ?? ''));
    $alternate = trim((string)($floor['alternate_route'] ?? ''));
    if ($primary !== '') $routeParts[] = '주 경로 ' . $primary;
    if ($alternate !== '') $routeParts[] = '대체 경로 ' . $alternate;
    foreach (['guide'=>'피난유도','checker'=>'잔류자 확인'] as $field=>$title) {
      $person = trim((string)($floor[$field] ?? ''));
      if ($person !== '') $routeParts[] = $title . ' ' . $person;
    }
    if ($routeParts) $routes[] = $label . ': ' . implode(' / ', $routeParts);

    $vulnerable = trim((string)($floor['vulnerable'] ?? ''));
    $noSupport = in_array(preg_replace('/\s+/u', '', $vulnerable), ['없음','해당없음','특이사항없음'], true);
    if ($noSupport) $noSupportCount++;
    $hasVulnerable = $vulnerable !== '' && !$noSupport;
    if ($hasVulnerable) {
      $weakLocations[] = $label;
      $parts = [$label . ': ' . $vulnerable];
      $guide = trim((string)($floor['guide'] ?? ''));
      $checker = trim((string)($floor['checker'] ?? ''));
      if ($guide !== '') $parts[] = '피난유도 ' . $guide;
      if ($checker !== '') $parts[] = '잔류자 확인 ' . $checker;
      $weakPlans[] = implode(' / ', $parts);
    }
  }

  $operations = [];
  $alarm = trim((string)($plan['alarm_method'] ?? ''));
  $reporter = trim((string)($plan['reporter'] ?? ''));
  $controller = trim((string)($plan['controller'] ?? ''));
  $headcount = trim((string)($plan['headcount_method'] ?? ''));
  $notes = trim((string)($plan['special_notes'] ?? ''));
  if ($alarm !== '') $operations[] = '상황 전파: ' . $alarm;
  if ($reporter !== '') $operations[] = '119 신고·안내: ' . $reporter;
  if ($controller !== '') $operations[] = '피난 지휘: ' . $controller;
  if ($headcount !== '') $operations[] = '인원 확인: ' . $headcount;
  if ($notes !== '') $operations[] = '특별 주의사항: ' . $notes;

  // Zero is an answer only when every saved floor explicitly says no support is needed.
  $noSupport = $floorCount > 0 && $noSupportCount === $floorCount;
  $section = [
    'floor_exit'=>$hasFirstFloor ? '지상 1층' : '',
    'route'=>implode("\n", array_merge($routes, $operations)),
    'weak_loc'=>$noSupport ? '해당없음' : implode(', ', $weakLocations),
    'weak_plan'=>$noSupport ? '해당없음' : implode("\n", $weakPlans),
    'assembly'=>trim((string)($plan['assembly_confirmed'] ?? '')),
    'common_updated'=>trim((string)($plan['updated'] ?? '')),
  ];
  if ($noSupport) $section['weak_cnt'] = '0';
  return $section;
}

function epc_value_empty($value): bool {
  return is_array($value) ? count($value) === 0 : trim((string)$value) === '';
}

function epc_merge_empty(array $current, array $common): array {
  foreach ($common as $key => $value) {
    if (!array_key_exists($key, $current) || epc_value_empty($current[$key])) {
      if (!epc_value_empty($value)) $current[$key] = $value;
    }
  }
  return $current;
}

function epc_missing_patch(array $current, array $common): array {
  $patch = [];
  foreach ($common as $key => $value) {
    if ((!array_key_exists($key, $current) || epc_value_empty($current[$key])) && !epc_value_empty($value)) {
      $patch[$key] = $value;
    }
  }
  return $patch;
}

function epc_apply_common(array $current, array $common): array {
  foreach ($common as $key => $value) {
    if (!epc_value_empty($value)) $current[$key] = $value;
  }
  return $current;
}
