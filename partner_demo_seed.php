<?php
/** Fictional data only. This file runs after the private filesystem boundary is set. */
declare(strict_types=1);
function pda_seed(string $dir):void {
    $lock=fopen($dir.'/.seed.lock','c+');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('체험 자료 준비 중입니다.');
    try {
        if(is_file($dir.'/.seeded'))return;
        $created='2026-01-01 00:00:00';$now=date('Y-m-d H:i:s');
        pda_store($dir,'data/members.json',[
            'demo_partner'=>['role'=>'agency','status'=>'active','created'=>$created,'nickname'=>'체험 파트너','manager_code'=>'FM-DEMO'],
            'demo_building'=>['role'=>'building','status'=>'active','created'=>$created,'nickname'=>'소방계획서.com 빌딩','email'=>'demo@example.invalid','email_ok'=>true,'email_verified'=>true,'email_verification_required'=>false,'assigned_manager_uid'=>'demo_partner','assigned_manager_created'=>$created,'referrer_uid'=>'demo_partner','referrer_created'=>$created]
        ]);
        pda_store($dir,'data/manager_system.php',['links'=>['demo_building:'.hash('sha256',$created)=>['status'=>'accepted','created'=>$now,'accepted'=>$now,'manager_uid'=>'demo_partner','uid'=>'demo_building']]],true);
        pda_store($dir,'data/subscribe/demo_building/subscription.json',['status'=>'active','plan_name'=>'PRO','price'=>59000,'amount'=>59000,'test'=>true,'test_mode'=>true,'account_created'=>$created,'started_at'=>date('Y-m-d',strtotime('-1 month')),'expires_at'=>date('Y-m-d H:i:s',strtotime('+1 year'))]);
        require_once __DIR__.'/building_info.php';
        $info=array_replace(bi_blank(),[
            'name'=>'소방계획서.com 빌딩','address'=>'경기도 파주시 · 체험용 가상 주소','use'=>'업무시설','grade'=>'2급','rep'=>'홍길동','tel'=>'',
            'floor_b'=>'1','floor_a'=>'5','area_t'=>'2400','area_f'=>'400','bd_area_arch'=>'400','dongsu'=>'1','bd_dong_pick'=>'본관',
            'bd_struct'=>'철근콘크리트구조','bd_use_apr'=>'20180515','bd_lat'=>'37.7591','bd_lng'=>'126.7794',
            'assembly_kind'=>'건물 앞 지정 집결지','assembly_lat'=>'37.75928','assembly_lng'=>'126.7790',
            'mgrs'=>[['name'=>'홍길동','appt'=>date('Y').'-01-01','qual'=>'2급 소방안전관리자','type'=>'주','tel'=>'']],
            'wd_day'=>'80','wd_night'=>'5','hd_day'=>'10','hd_night'=>'2','note_pinan'=>'주계단과 보조계단을 이용하여 건물 앞 지정 집결지로 대피합니다.','updated'=>$now
        ]);
        pda_store($dir,'data/building/demo_building/info.json',$info);
        pda_store($dir,'data/fireplan/demo_building/_jawi.json',['1'=>['saved'=>$now,'cmd'=>['name'=>'홍길동','tel'=>'','task'=>'초기 상황 판단 및 총괄'],'deputy'=>['name'=>'이연락','tel'=>'','task'=>'119 신고 및 연락'],'groups'=>[['name'=>'피난유도반','members'=>[['name'=>'최유도','tel'=>'','task'=>'대피 안내 및 집결지 인원 확인']]],['name'=>'초기소화반','members'=>[['name'=>'박소화','tel'=>'','task'=>'안전 확보 후 초기 소화']]]]]]);
        require_once __DIR__.'/building_facilities_common.php';
        $yes=['소화기구 및 자동소화장치'=>['quantity'=>'20','location'=>'각 층 복도'],'옥내소화전설비'=>['quantity'=>'6','location'=>'각 층 계단 앞','hydrant_start'=>'auto'],'자동화재탐지설비 및 시각경보기'=>['receiver_location'=>'1층 관리실'],'유도등'=>['location'=>'각 층 피난구 및 복도'],'비상방송설비'=>[],'방화문'=>['location'=>'계단 출입구']];
        $items=[];foreach(bf_catalog() as $group)foreach($group[1] as $name)$items[bf_id($name)]=array_merge(['status'=>array_key_exists($name,$yes)?'yes':'no'],$yes[$name]??[]);
        bf_save_multi(['base'=>$items],['base'],(string)bf_load()['revision'],[]);
        require_once __DIR__.'/fire_plan_overview_common.php';
        $id=fp_create_plan('business',(int)date('Y'));
        $sources=fpo_sources($info,[],(int)date('Y'));
        $plan=fpo_import(fp_load_plan($id),$sources,array_keys($sources['groups']));
        $plan['building_name']='소방계획서.com 빌딩';fp_write_json(fp_plan_file($id),$plan);fp_touch_index($id,$plan);
        file_put_contents($dir.'/.seeded',$now,LOCK_EX);
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
