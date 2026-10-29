<?php
declare(strict_types=1);

/** Read saved assignments as written; do not invent duties from a person's title. */
function epl_saved_context(array $building, array $rosters): array {
    $text = static fn($value): string => is_scalar($value) ? trim((string)$value) : '';
    $staff = [];
    foreach ((array)($building['mgrs'] ?? []) as $person) {
        if (is_array($person) && ($name = $text($person['name'] ?? '')) !== '') $staff[] = $name;
    }
    $fields = [];
    $put = static function(string $key, string $value, string $source) use (&$fields): void {
        // Keep the full saved assignment. Oversized content remains a question, never silently truncated.
        if ($value !== '' && mb_strlen($value) <= 500) $fields[$key] = ['value'=>$value, 'source'=>$source];
    };
    $assembly = $text($building['assembly_kind'] ?? '');
    if ($assembly === '' && is_numeric($building['assembly_lat'] ?? null) && is_numeric($building['assembly_lng'] ?? null)) {
        $lat = (float)$building['assembly_lat']; $lng = (float)$building['assembly_lng'];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0)) {
            $assembly = '기본정보 지도에 지정한 집결지 ('.round($lat, 6).', '.round($lng, 6).')';
        }
    }
    $put('assembly_confirmed', $assembly, '기본정보 · 집결지');

    $rosters = array_values(array_filter($rosters, static fn($r) => is_array($r) && empty($r['deleted_at']) && (isset($r['cmd']) || isset($r['groups']))));
    $saved = static fn($r) => $text($r['saved'] ?? '') ?: ($text($r['updated'] ?? '') ?: $text($r['created'] ?? ''));
    usort($rosters, static fn($a, $b) => strcmp($saved($b), $saved($a)));
    $roster = $rosters[0] ?? [];
    $source = '자위소방대 편성표';
    if ($saved($roster) !== '') $source .= ' · '.mb_substr($saved($roster), 0, 10);
    // fire_plan_jawi stores cmd/deputy as [name, phone, task, department].
    $person = static function($raw) use ($text): array {
        if (!is_array($raw)) return ['name'=>'', 'task'=>''];
        return ['name'=>$text($raw['name'] ?? $raw[0] ?? ''), 'task'=>$text($raw['task'] ?? $raw[2] ?? '')];
    };
    $cmd = $person($roster['cmd'] ?? []);
    $put('controller', $cmd['name'], $source.' · 대장');
    $people = [];
    foreach (['cmd'=>'대장', 'deputy'=>'부대장'] as $key=>$role) {
        $entry = $person($roster[$key] ?? []);
        if ($entry['name'] !== '') $people[] = $entry + ['role'=>$role];
    }
    foreach ((array)($roster['groups'] ?? []) as $group) {
        if (!is_array($group)) continue;
        foreach ((array)($group['members'] ?? []) as $raw) {
            $entry = $person($raw);
            if ($entry['name'] !== '') $people[] = $entry + ['role'=>$text($group['name'] ?? '')];
        }
    }
    $reporters = []; $headcount = []; $alarms = [];
    foreach ($people as $entry) {
        $staff[] = $entry['name'];
        $who = trim($entry['role'].' '.$entry['name']);
        $task = $entry['task'];
        if (preg_match('/(?:119|소방서).*?(?:신고|통보)|화재\s*신고/u', $task)) $reporters[] = $who;
        if (preg_match('/대피\s*(?:인원|완료)|미대피자|잔류자|인원\s*(?:점검|확인)|미확인\s*인원/u', $task)) $headcount[] = $who.' — '.$task;
        if (preg_match('/경보|비상방송|화재.*방송|방송.*전파|상황.*전파/u', $task)) $alarms[] = $who.' — '.$task;
    }
    $put('reporter', implode(', ', array_unique($reporters)), $source.' · 신고 담당');
    $put('headcount_method', implode("\n", array_unique($headcount)), $source.' · 대피 확인 임무');
    $put('alarm_method', implode("\n", array_unique($alarms)), $source.' · 상황 전파 임무');
    return ['fields'=>$fields, 'staff'=>array_values(array_unique($staff))];
}
