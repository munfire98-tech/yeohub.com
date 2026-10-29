<?php
declare(strict_types=1);

/** The saved plan is the document. Viewing it never refreshes its source data. */
require_once __DIR__.'/fire_plan_db.php';
require_once __DIR__.'/fire_plan_chat_import.php';
require_once __DIR__.'/building_info.php';
require_once __DIR__.'/building_facilities_common.php';
require_once __DIR__.'/evacuation_plan_common.php';
require_once __DIR__.'/building_dong_names.php';
require_once __DIR__.'/building_multi_use_common.php';

function fpo_hash(array $value):string {
    return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
}
function fpo_content_hash(array $plan):string {
    return fpo_hash([$plan['sections']??[],$plan['source_bundle']??[],$plan['plan_year']??0,$plan['plan_date']??'']);
}
function fpo_status(array $plan):array {
    $s1=(array)($plan['sections']['1']['data']??[]);$s3=(array)($plan['sections']['3']['data']??[]);
    $skip=fp_skip_rules($s1);$out=['total'=>0,'filled'=>0,'done'=>0,'skipped'=>count($skip),'sections'=>[],'next'=>null];
    foreach(fp_chat_schema() as $code=>$fields){
        $d=(array)($plan['sections'][$code]['data']??[]);$answers=(array)($d['_chat_answers']??[]);
        $state=['total'=>0,'filled'=>0,'missing'=>[],'skip'=>isset($skip[$code]),'source'=>0];
        if(!$state['skip'])foreach($fields as $key=>$field){
            if(!fp_chat_visible((string)$code,$key,$s1,$s3))continue;
            $state['total']++;
            $v=$key==='plan_date'?($plan['plan_date']??''):($d[$key]??null);
            $nonempty=is_array($v)?count($v)>0:($v!==null&&trim((string)$v)!=='');
            $valid=$nonempty||in_array($key,$answers,true);
            // Required identifiers can never be made complete with a blank answer.
            if((string)$code==='1'&&in_array($key,['name','addr','mgr_name','grade'],true)&&!$nonempty)$valid=false;
            if($valid){$state['filled']++;if(array_key_exists($key,(array)($d['_source_values']??[])))$state['source']++;}
            else{$state['missing'][]=$key;if($out['next']===null)$out['next']=['code'=>(string)$code,'key'=>$key];}
        }
        $state['done']=!$state['skip']&&$state['total']>0&&!$state['missing'];
        $out['total']+=$state['total'];$out['filled']+=$state['filled'];if($state['done'])$out['done']++;
        $out['sections'][$code]=$state;
    }
    $out['percent']=$out['total']?(int)floor(100*$out['filled']/$out['total']):0;
    $out['complete']=$out['total']>0&&$out['filled']===$out['total'];
    $out['reviewed']=$out['complete']&&hash_equals(fpo_content_hash($plan),(string)($plan['final_review']['hash']??''));
    return $out;
}
function fpo_dongs(array $bi):array {
    require_once __DIR__.'/building_floor_common.php';
    $out=[];
    foreach(bfl_entries($bi) as $entry){
        $r=$entry['row'];$primary=$entry['primary'];
        $out[]=['name'=>$entry['name'],'primary'=>$primary,'use'=>$r['use']??'','above'=>$r['floor_a']??'',
            'below'=>$r['floor_b']??'','area'=>$r['area']??'','structure'=>$r['struct']??'','floors'=>bfl_effective($bi,$entry)];
    }
    return $out;
}

/** Save the same published documents that the evacuation card prints. */
function fpo_evacuation_documents(array $evac,array $bi):array {
    require_once __DIR__.'/evacuation_plan_list_common.php';
    if(array_key_exists('_print_plans',$evac))$raw=['_epl_version'=>2,'_epl_plans'=>$evac['_print_plans']];
    else $raw=$evac;
    $store=epl_collection($raw,$bi);$rows=[];
    foreach((array)($store['_epl_plans']??[]) as $id=>$row){
        if(!is_array($row)||empty($row['published'])||!empty($row['archived'])||!empty($row['deleted_at']))continue;
        $rows[$id]=array_intersect_key($row,array_flip(['id','title','published_title','scope','published','published_at','archived']));
        $rows[$id]['published']['_building_locations']=epl_plan_locations($row['published'],$bi);
    }
    return ['building'=>['name'=>(string)($bi['name']??''),'address'=>(string)($bi['address']??'')],'plans'=>$rows];
}

function fpo_monthly(int $year):array {
    $key=preg_replace('/[^A-Za-z0-9_]/','_',fp_user_key());$rows=[];
    if($key==='')return [];
    for($m=1;$m<=12;$m++){
        $r=fp_read_json(__DIR__.'/data/worklog/'.$key.'/m'.$year.'-'.sprintf('%02d',$m).'.json');
        if(trim((string)($r['date']??''))===''||trim((string)($r['performer']??''))==='')continue;
        $rows[]=['month'=>$m,'date'=>$r['date'],'performer'=>$r['performer']];
    }
    return $rows;
}

function fpo_sources(array $bi,array $evac,int $year,?array $selected=null):array {
    $sources=fp_chat_sources($bi,$evac,$year,$selected);$schema=fp_chat_schema();
    $inventory=bf_load();$facility=[];
    foreach(bf_named_scopes(bf_scopes($inventory,bf_dong_options($bi)),$bi) as $scope){
        if(empty($scope['included']))continue;
        $counts=bf_counts(['items'=>$scope['items']??[]]);
        $facility[]=['name'=>$scope['label'],'present'=>$counts['present'],'unknown'=>$counts['unknown'],'summary'=>bf_summary(['items'=>$scope['items']??[]])];
    }
    // Empty facility categories are answers only when every installation is explicitly checked.
    if($selected===null||in_array('monthly',$selected,true))foreach(bf_catalog() as $category=>$g){
        if(!isset($schema['2'][$category]))continue;
        $known=true;$yes=[];
        foreach($g[1] as $name){$status=$inventory['items'][bf_id($name)]['status']??'unknown';if(!in_array($status,['yes','no'],true))$known=false;if($status==='yes')$yes[]=$name;}
        if($yes||$known){$sources['data']['2'][$category]=$yes;if($known&&!$yes)$sources['known_empty']['2'][$category]=true;}
    }
    $appendices=['basic'=>['multi_use'=>bmu_snapshot($bi,bmu_load()),'dongs'=>fpo_dongs($bi),'managers'=>array_values(array_filter((array)($bi['mgrs']??[]),'is_array'))],
        'monthly'=>['facilities'=>$facility,'records'=>fpo_monthly($year)],
        'jawi_education'=>['forms'=>fp_training_forms('jawi',$year)],
        'training'=>['forms'=>fp_training_forms('train',$year)],
        'incidents'=>['records'=>fp_incident_source($year)],
        'team'=>array_intersect_key(fp_team_source(),array_flip(['members','document'])),
        'evacuation'=>['floors'=>array_values(array_filter((array)($evac['floors']??[]),'is_array')),'document'=>fpo_evacuation_documents($evac,$bi)]];
    foreach($sources['groups'] as $id=>&$g){
        $g['appendix']=$appendices[$id]??[];
        if(!empty($g['appendix']['forms'])){$g['available']=true;$g['detail']='해당 연도 기록부 '.count($g['appendix']['forms']).'건 · 법정서식 포함';}
        $g['fingerprint']=fpo_hash([$g['data'],$g['appendix']]);
        if($id==='basic'&&count($appendices['basic']['dongs'])>1)$g['detail']='관리하는 '.count($appendices['basic']['dongs']).'개 동 · 관리자 · 건물정보';
    }unset($g);
    $sources['fingerprint']=fpo_hash(array_map(static fn($g)=>$g['fingerprint'],$sources['groups']));
    return $sources;
}
function fpo_import(array $plan,array $sources,array $selected):array {
    $plan=fp_chat_import($plan,$sources,fp_chat_schema());$plan['source_selection']=$selected;
    $bundle=['version'=>1,'imported_at'=>date('Y-m-d H:i:s'),'groups'=>[]];
    foreach($selected as $id){$g=$sources['groups'][$id];if(!$g['available'])continue;
        $bundle['groups'][$id]=['title'=>$g['title'],'detail'=>$g['detail'],'fingerprint'=>$g['fingerprint'],'appendix'=>$g['appendix']??[]];
    }
    $plan['source_bundle']=$bundle;unset($plan['final_review']);
    return fpo_import_status($plan);
}
function fpo_import_status(array $plan):array {
    $status=fpo_status($plan);
    foreach($status['sections'] as $code=>$s){
        $plan['sections'][$code]['is_done']=$s['done']?1:0;
        $plan['sections'][$code]['is_skipped']=$s['skip']?1:0;
        $answers=(array)($plan['sections'][$code]['data']['_chat_answers']??[]);$complete=true;
        foreach(fp_chat_schema()[$code] as $key=>$field)if(fp_chat_visible((string)$code,$key,(array)($plan['sections']['1']['data']??[]),(array)($plan['sections']['3']['data']??[]))&&!in_array($key,$answers,true)){$complete=false;break;}
        $plan['sections'][$code]['data']['_chat_complete']=$complete;
        // Existing manual values still require a click to confirm when they have no answer history.
    }
    $plan['status']='draft';
    return $plan;
}

/** Refresh the published evacuation source without refreshing any other source snapshot. */
function fpo_import_evacuation(array $plan,array $sources):array {
    $group=$sources['groups']['evacuation']??[];
    if(empty($group['available'])||empty($group['appendix']['floors']))throw new RuntimeException('사용 중인 피난계획을 먼저 저장해 주세요.');
    $schema=fp_chat_schema();
    $fields=['5'=>$schema['5'],'14'=>array_intersect_key($schema['14'],array_flip(['s1','s2','s4','s5']))];
    $selection=array_values(array_unique(array_merge((array)($plan['source_selection']??[]),['evacuation'])));
    $plan=fp_chat_import($plan,['data'=>$group['data']],$fields);
    $plan['source_selection']=$selection;
    $plan['source_bundle']['version']=1;
    $plan['source_bundle']['imported_at']=date('Y-m-d H:i:s');
    $plan['source_bundle']['groups']['evacuation']=['title'=>$group['title'],'detail'=>$group['detail'],'fingerprint'=>$group['fingerprint'],'appendix'=>$group['appendix']??[]];
    unset($plan['final_review']);
    return fpo_import_status($plan);
}

/** Existing page and importer share this path; stale overview forms cannot overwrite new answers. */
function fpo_save_import(string $id,array $sources,array $selected,string $expected,bool $evacuationOnly=false):array {
    require_once __DIR__.'/manager_fire_plan_help.php';
    $result=[];
    $ok=mfp_save(fp_user_key(),$id,'0',[],static function()use($id,$sources,$selected,$expected,$evacuationOnly,&$result):bool {
        $current=fp_load_plan($id);
        if(!$current||!hash_equals(fpo_hash($current),$expected))throw new RuntimeException('다른 화면에서 계획서가 변경되었습니다. 새로고침 후 다시 반영해 주세요.');
        $result=$evacuationOnly?fpo_import_evacuation($current,$sources):fpo_import($current,$sources,$selected);
        if(!fp_write_json(fp_plan_file($id),$result))return false;
        fp_touch_index($id,$result);return true;
    });
    if(!$ok)throw new RuntimeException('자료를 저장하지 못했습니다. 다시 시도해 주세요.');
    return $result;
}
