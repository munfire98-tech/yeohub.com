<?php
declare(strict_types=1);
require_once __DIR__.'/fire_plan_overview_common.php';

/** Read-only projection of the saved annual plan; never imports current source records. */
function fpdc_summary(?array $plan, bool $readError = false): array {
    $out = ['hasPlan'=>!empty($plan), 'id'=>(string)($plan['id']??''), 'known'=>false,
        'state'=>'확인 필요', 'reviewed'=>false, 'percent'=>null, 'filled'=>null,
        'total'=>null, 'remaining'=>null, 'sources'=>[], 'sourceCount'=>0,
        'awaitingImport'=>false, 'complete'=>false, 'nextSection'=>'1', 'sourceSelected'=>!empty($plan['source_selection_set'])];
    if ($readError) return $out;
    try { $status = fpo_status($plan??[]); } catch (Throwable $e) { return $out; }
    $out['known'] = true;
    foreach (['percent','filled','total'] as $key) $out[$key] = (int)$status[$key];
    $out['remaining'] = max(0, $out['total']-$out['filled']);
    $out['reviewed'] = (bool)$status['reviewed'];
    $out['complete'] = (bool)$status['complete'];
    $out['nextSection'] = (string)($status['next']['code']??'1');
    $out['state'] = !$plan ? '작성 전' : ($status['reviewed'] ? '최종 확인 완료' : ($status['complete'] ? '내용 확인 필요' : '작성 중'));
    $labels = ['basic'=>'기본정보', 'monthly'=>'시설·매월 기록', 'team'=>'자위소방대 편성',
        'evacuation'=>'피난계획', 'jawi_education'=>'자위소방대 교육', 'training'=>'소방훈련·교육', 'incidents'=>'비화재보 기록'];
    foreach ((array)($plan['source_bundle']['groups']??[]) as $key=>$group) {
        if (!is_array($group)) continue;
        $title = $labels[$key]??(is_string($group['title']??null) ? trim($group['title']) : '저장된 자료');
        $out['sources'][] = $title !== '' ? $title : '저장된 자료';
    }
    $out['sourceCount'] = count($out['sources']);
    $out['awaitingImport'] = !$out['reviewed'] && $out['filled'] === 0 && $out['sourceCount'] === 0;
    if ($out['awaitingImport']) $out['state'] = $plan ? '자료 반영 전' : '계획서 시작 전';
    return $out;
}
