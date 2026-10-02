<?php
// This partial uses only the dashboard's already-authorized building data.
if (!isset($bi) || !is_array($bi)) return;
$fmbManagers = array_values(array_filter($bi['mgrs'] ?? [], static fn($m) => is_array($m) && trim((string)($m['name'] ?? '')) !== ''));
$fmbInitial = ['name'=>(string)($bi['name']??''),'grade'=>(string)($bi['grade']??''),'managers'=>$fmbManagers];
?>
<link rel="stylesheet" href="/fire_manager_board.css?v=20261002-a4">
<dialog id="fire-manager-board" aria-labelledby="fmb-title">
 <header class="fmb-head"><div><small>게시용 출력물</small><h2 id="fmb-title">소방안전관리자 현황표</h2></div><button type="button" data-fmb-close aria-label="현황표 닫기">닫기 ×</button></header>
 <div class="fmb-body"><section class="fmb-editor" aria-label="출력 정보">
 <p>기본정보를 불러왔습니다. 수정 내용은 이번 출력에만 적용되며, 기본정보에는 저장되지 않습니다.</p>
 <label>담당자 선택<select data-fmb-manager></select></label>
 <label>대상명<input data-fmb-field="name" maxlength="80"></label>
 <div class="fmb-pair"><label>소방안전관리자<input data-fmb-field="manager" maxlength="40"></label><label>선임일자<input type="date" data-fmb-field="appt"></label></div>
 <div class="fmb-pair"><label>대상물 등급<select data-fmb-field="grade"><option value="">선택</option><option>특급</option><option>1급</option><option>2급</option><option>3급</option></select></label><label>연락처<input type="tel" data-fmb-field="tel" maxlength="40"></label></div>
 <label>근무 위치 · 화재 수신기 위치<input data-fmb-field="location" maxlength="100" placeholder="예: 1층 방재실"></label>
 <p class="fmb-note">「화재의 예방 및 안전관리에 관한 법률 시행규칙」 별표 2에 따라 대상물의 특성을 고려하여 크기·재질·글씨체를 정할 수 있습니다. 일반적인 출력 편의를 위해 기본 용지는 A4 가로로 설정했습니다.</p>
 <p class="fmb-note">A4 가로 출력 · 인쇄 창에서 PDF로 저장할 수 있습니다.<br>인쇄 배율 100%, 머리글·바닥글 해제, 배경 그래픽 켜기를 권장합니다.</p>
 <p data-fmb-status role="status"></p>
 <button type="button" class="fmb-print" data-fmb-print>인쇄 · PDF 저장</button>
 </section><section class="fmb-preview" aria-label="현황표 미리보기"><iframe title="A4 현황표 인쇄 미리보기"></iframe></section></div>
</dialog>
<script type="application/json" id="fmb-data"><?=json_encode($fmbInitial,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<script src="/fire_manager_board.js?v=20261002-a4" defer></script>
