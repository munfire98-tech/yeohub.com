<?php
declare(strict_types=1);
/** Run after outer help/member transactions release their locks. */
function fpa_queue():void {
    static $queued=false;
    if($queued||defined('MANAGER_VIEW_UID')||defined('PARTNER_DEMO_RUNTIME'))return;
    $queued=true;
    register_shutdown_function(static function():void {
        try{fpa_sync();}catch(Throwable $e){error_log('Fire plan auto sync failed: '.$e->getMessage());}
    });
}
/** Only source-owned or empty answers change; manually edited plan answers stay intact. */
function fpa_merge(array $plan,array $sources):array {
    $selected=array_values(array_unique(array_merge((array)($plan['source_selection']??[]),['basic','monthly'])));
    $data=(array)($sources['groups']['basic']['data']??[]);
    $data['1']['recv_loc']=$sources['groups']['monthly']['data']['1']['recv_loc']??'';
    $data['2']=$sources['groups']['monthly']['data']['2']??[];
    $before=$plan;
    $all=fp_chat_schema();$schema=[];
    $owned=['1'=>array_unique(array_merge(array_keys($data['1']??[]),['approval','elev','recv_loc'])),
        '2'=>array_keys($all['2']),'3'=>array_keys($all['3']),'14'=>['memo']];
    foreach($owned as $code=>$keys)$schema[$code]=array_intersect_key($all[$code],array_flip($keys));
    $plan=fp_chat_import($plan,['data'=>$data,'known_empty'=>$sources['known_empty']??[]],$schema);
    $plan['source_selection']=$selected;
    $plan['source_bundle']['version']=1;
    foreach(['basic','monthly'] as $key){
        $g=$sources['groups'][$key]??[];
        // Facilities and work logs share an import group; a facility save must not import work logs.
        if($key==='monthly'){
            $g['data']=array_intersect_key((array)($g['data']??[]),array_flip(['1','2']));
            $g['appendix']['records']=$before['source_bundle']['groups']['monthly']['appendix']['records']??[];
        }
        $fingerprint=fpo_hash([$g['data']??[],$g['appendix']??[]]);
        $plan['source_bundle']['groups'][$key]=['title'=>$g['title']??$key,'detail'=>$g['detail']??'',
            'fingerprint'=>$fingerprint,'appendix'=>$g['appendix']??[]];
    }
    // Repeated dashboard loads/saves must not reset final review or churn timestamps.
    if(fpo_content_hash($before)===fpo_content_hash($plan))return $before;
    $plan['source_bundle']['imported_at']=date('Y-m-d H:i:s');
    unset($plan['final_review']);
    return fpo_import_status($plan);
}
function fpa_sync():void {
    if(defined('MANAGER_VIEW_UID')||defined('PARTNER_DEMO_RUNTIME'))return;
    require_once __DIR__.'/fire_plan_overview_common.php';
    require_once __DIR__.'/manager_fire_plan_help.php';
    require_once __DIR__.'/fire_plan_share_common.php';
    $uid=fp_user_key();if($uid==='')return;
    // Same account/help lock order as plan answer saving, including delegated saves.
    $ok=mfp_save($uid,'0','0',[],static function():bool {
        $year=(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y');
        $bi=is_file(bi_file())?bi_read_saved():bi_blank();$inventory=bf_load();
        if(trim((string)($bi['name']??''))===''&&bf_counts($inventory)['confirmed']===0)return true;
        $plans=fps_plans(fp_user_key());
        if(!isset($plans[$year])){$id=fp_create_plan('business',$year);$plans[$year]=fp_load_plan($id);}
        foreach([$year,$year+1] as $target){
            $plan=$plans[$target]??null;if(!$plan)continue;
            $sources=fpo_sources($bi,epc_load(),$target,['basic','monthly']);
            $next=fpa_merge($plan,$sources);
            if($next===$plan)continue;
            if(!fp_write_json(fp_plan_file((string)$plan['id']),$next))return false;
            fp_touch_index((string)$plan['id'],$next);
        }
        return true;
    });
    if(!$ok)throw new RuntimeException('소방계획서 자동 반영을 저장하지 못했습니다.');
}
