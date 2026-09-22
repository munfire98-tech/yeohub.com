<?php
if (!defined('MANAGER_VIEW_UID')) { http_response_code(403); exit; }
/* Reuse the user's dashboard calculations, output a view with no mutating links/forms. */
$statuses = [
 ['건물 기본정보', $biDone ? '필수 정보 입력 완료' : '입력 필요', implode(', ', (array)($biProg['missing'] ?? []))],
 ['자위소방대 편성', $hasRoster ? '편성 기록 있음' : '편성 기록 없음', $hasRoster ? $rosterCount.'건' : '자위소방대 편성표 작성'],
 [date('n').'월 업무수행 기록', $doneWorkLog ? '저장 기록 있음' : '저장 기록 없음', $doneWorkLog ? '파일 존재 기준이며 내용의 완전성은 별도 검토가 필요합니다.' : '이번 달 업무수행 기록 작성'],
 ['올해 자위소방대 교육·훈련', !function_exists('jw_done_this_year') ? '확인 불가' : ($doneJawi ? '완료' : '미완료'), implode(', ', (array)($jawiStatus['missing'] ?? []))],
 ['올해 소방훈련·교육', !function_exists('tr_list') ? '확인 불가' : ($doneTrain ? '완료' : '미완료'), implode(', ', (array)($trainStatus['missing'] ?? []))],
 ['피난계획', $hasEvacuationPlan ? '작성 기록 있음' : '작성 기록 없음', '기존 화면과 동일한 작성 여부 기준'],
];
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>유저 업무 현황</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#192d43;font:16px/1.7 system-ui,sans-serif}main{max-width:1000px;margin:30px auto;padding:0 20px}.card{background:#fff;border:1px solid #dce5ef;border-radius:16px;padding:24px;margin:20px 0}h1{font-size:28px}h2{font-size:20px}a{color:#155ec2}.muted{color:#566b82}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;text-align:left}td,th{padding:12px;border-bottom:1px solid #e2e8f0}th{white-space:nowrap}.tag{background:#eaf3ff;padding:6px 12px;border-radius:20px;font-size:13px}.missing{color:#a63d16}progress{width:100%;height:18px}</style></head><body><main>
<a href="/manager_portal.php">← 담당 유저 목록</a><p><span class="tag">읽기 전용 · <?=mg_e(MANAGER_VIEW_UID)?></span></p>
<h1><?=mg_e($biName !== '' ? $biName : '건물 정보 미입력')?></h1><p class="muted">유저 화면과 같은 저장 정보를 조회합니다. 조회 시각: <?=mg_e(date('Y-m-d H:i:s'))?> · 최신 상태는 새로고침으로 확인하세요.</p>
<section class="card"><h2>기본정보 입력률 <?=$biProg['percent']?>%</h2><progress max="100" value="<?=$biProg['percent']?>"></progress><p class="missing"><?=mg_e($biDone?'필수 항목이 모두 입력되었습니다.':'부족 항목: '.implode(', ',(array)$biProg['missing']))?></p><div class="scroll"><table>
<?php foreach(['name'=>'대상명','address'=>'소재지','grade'=>'등급','use'=>'용도','floor_a'=>'지상 층수','floor_b'=>'지하 층수','area_t'=>'연면적','tel'=>'건물 연락처'] as $k=>$label): ?><tr><th><?=mg_e($label)?></th><td><?=mg_e(trim((string)($bi[$k]??''))!==''?$bi[$k]:'미입력')?></td></tr><?php endforeach; ?>
</table></div></section><section class="card"><h2>업무별 진행 현황 · 부족 항목</h2><div class="scroll"><table><tr><th>업무</th><th>상태</th><th>확인할 내용</th></tr><?php foreach($statuses as $row): ?><tr><?php foreach($row as $value): ?><td><?=mg_e($value)?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div></section>
<section class="card"><h2>피난 관련 입력 현황</h2><p>집결지 좌표: <?=$hasAssemblyPoint?'입력됨':'미입력'?> · 소방차 진입 경로: <?=$hasFireRoute?'입력됨':'미입력'?></p><p class="muted">표시는 저장·입력 현황입니다. 실제 시설의 적합성이나 점검 완료를 보증하는 판정은 아닙니다.</p></section>
</main></body></html>
