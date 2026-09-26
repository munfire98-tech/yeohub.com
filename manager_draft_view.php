<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_draft_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
try{
 if(!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))throw new RuntimeException('매니저 본인 화면에서 확인해 주세요.');
 define('MD_ACTOR',mg_uid());define('MD_ID',(string)($_GET['id']??''));
 md_entry(MD_ACTOR,MD_ID);$d=md_load();
}catch(Throwable $e){http_response_code(403);exit('사전 등록 정보를 확인할 수 없습니다. 연결된 거래처는 담당 유저 화면에서 확인해 주세요.');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$chat='/manager_draft.php?id='.rawurlencode(MD_ID).'&modal=1';
$groups=[
 '건물 기본정보'=>['name'=>'건물명','address'=>'주소','use'=>'용도','grade'=>'관리 등급','rep'=>'대표자','tel'=>'건물 연락처'],
 '건물 규모'=>['floor_b'=>'지하층','floor_a'=>'지상층','area_t'=>'연면적 (㎡)','area_f'=>'바닥면적 (㎡)','bd_area_arch'=>'건축면적 (㎡)','dongsu'=>'동수'],
 '건축물대장'=>['bd_struct'=>'구조','bd_struct_etc'=>'기타 구조','bd_height'=>'높이 (m)','bd_area_plat'=>'대지면적 (㎡)','bd_bcrat'=>'건폐율 (%)','bd_vlrat'=>'용적률 (%)','bd_area_vl'=>'용적률 산정 연면적 (㎡)','bd_main_bld'=>'주건축물 수','bd_atch_bld'=>'부속건축물 수','bd_hhld'=>'세대수','bd_family'=>'가구수','bd_ho'=>'호수','bd_park'=>'주차대수','bd_elev'=>'승용승강기 수','bd_use_main'=>'주용도','bd_use_etc'=>'기타 용도','bd_pms_day'=>'허가일','bd_stcns_day'=>'착공일','bd_use_apr'=>'사용승인일','bd_seismic'=>'내진설계 적용','bd_seismic_ablty'=>'내진능력','bd_energy'=>'에너지효율등급','bd_road_addr'=>'도로명 대지 위치','bd_dongs'=>'동별 현황','bd_dong_pick'=>'대표 기준동','bd_looked'=>'대장 조회일'],
 '근무인원'=>['wd_day'=>'평일 주간','wd_night'=>'평일 야간','hd_day'=>'휴일 주간','hd_night'=>'휴일 야간'],
 '기록 참고사항'=>['note_sobang'=>'소방시설','note_pinan'=>'피난·방화시설','note_hwagi'=>'화기 취급','note_etc'=>'기타 사항']
];
function dv_value($v):string {return is_scalar($v)?trim((string)$v):'';}
?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>사전 등록 기본정보</title><link rel="stylesheet" href="/manager_addresses.css?v=7"><body><main class="ma-page dv-page">
<header><span class="ma-kicker">사전등록 · 저장된 기본정보</span><h1><?=mg_e($d['name']?:'등록된 건물 정보')?></h1><p><?=mg_e($d['address']?:'주소를 아직 입력하지 않았습니다.')?></p><?php if($d['updated']): ?><small class="ma-note">최근 저장 <?=mg_e($d['updated'])?></small><?php endif; ?></header>
<div class="dv-toolbar"><a class="ma-link" href="<?=mg_e($chat)?>">문답으로 수정</a><form method="post" action="<?=mg_e($chat)?>" onsubmit="return confirm('저장된 기본정보를 초기화하고 첫 질문부터 다시 작성합니다. 계속할까요?')"><input type="hidden" name="act" value="reset"><input type="hidden" name="csrf" value="<?=mg_e($_SESSION['csrf'])?>"><button type="submit" class="ma-secondary">처음부터 다시</button></form><a href="/manager_addresses.php" class="dv-back">목록</a></div>
<p class="ma-note">문답으로 수정하면 기존 내용을 이어서 작성합니다. 처음부터 다시는 저장된 내용을 초기화합니다.</p>
<?php foreach($groups as $title=>$fields): ?>
<section class="dv-section"><h2><?=mg_e($title)?></h2><dl><?php foreach($fields as $key=>$label):$value=dv_value($d[$key]??'');if($value===''&&!in_array($title,['건물 기본정보','건물 규모'],true))continue; ?><div><dt><?=mg_e($label)?></dt><dd class="<?=$value===''?'dv-missing':''?>"><?=mg_e($value!==''?$value:'—')?></dd></div><?php endforeach; ?></dl><?php if(!array_filter(array_intersect_key($d,$fields),fn($v)=>dv_value($v)!=='')): ?><p class="ma-note">아직 저장된 정보가 없습니다.</p><?php endif; ?></section>
<?php endforeach; ?>
<section class="dv-section"><h2>소방안전관리자</h2><?php foreach($d['mgrs']??[] as $m): ?><div class="dv-manager"><strong><?=mg_e($m['name']??'')?></strong><dl><?php foreach(['type'=>'구분','tel'=>'연락처','appt'=>'선임일','qual'=>'자격'] as $k=>$label): ?><div><dt><?=mg_e($label)?></dt><dd><?=mg_e(($m[$k]??'')?:'—')?></dd></div><?php endforeach; ?></dl></div><?php endforeach; ?><?php if(empty($d['mgrs'])): ?><p class="ma-note">아직 등록된 안전관리자가 없습니다.</p><?php endif; ?></section>
<section class="dv-section"><h2>집결지 · 소방차 진입로</h2><dl><?php foreach(['assembly_kind'=>'집결지 이름','assembly_lat'=>'집결지 위도','assembly_lng'=>'집결지 경도','fire_engine_route_note'=>'진입로 참고사항','bd_lat'=>'건물 위도','bd_lng'=>'건물 경도'] as $k=>$label): ?><div><dt><?=mg_e($label)?></dt><dd><?=mg_e(dv_value($d[$k]??'')?:'—')?></dd></div><?php endforeach; ?><div><dt>소방차 진입로</dt><dd><?=count((array)json_decode((string)($d['fire_engine_route']??''),true))>=2?'경로 저장됨':'—'?></dd></div></dl></section>
</main></body></html>
