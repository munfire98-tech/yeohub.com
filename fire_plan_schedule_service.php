<?php
declare(strict_types=1);
require_once __DIR__.'/fire_plan_schedule_common.php';
require_once __DIR__.'/fire_plan_overview_common.php';
require_once __DIR__.'/manager_fire_plan_help.php';
require_once __DIR__.'/train_db.php';
require_once __DIR__.'/jawi_db.php';
function fpsc_save(string $id,array $events,string $revision): void {
    if(!empty($_SESSION['_mge_help_review']))throw new RuntimeException('건물관리 화면에서 일정을 수정해 주세요.');
    $initial=fp_load_plan($id);if(!$initial)throw new RuntimeException('계획서를 찾을 수 없습니다.');
    $events=fpsc_validate($events,fp_plan_year($initial));
    $patch=array_intersect_key(fpsc_project([], $events),array_flip(['t1_when','t1_who','t1_how','t2_when','t2_who','t2_how']));
    $ok=mfp_save(fp_user_key(),$id,'11',$patch,static function()use($id,$events,$revision):bool{
        $plan=fp_load_plan($id);
        if(!$plan||!hash_equals(fpo_hash($plan),$revision))throw new RuntimeException('다른 화면에서 계획서가 변경되었습니다. 새로고침 후 다시 확인해 주세요.');
        $plan['sections']['11']['data']=fpsc_project((array)($plan['sections']['11']['data']??[]),$events);
        unset($plan['final_review']);$plan=fpo_import_status($plan);$plan['updated_at']=date('Y-m-d H:i:s');
        if(!fp_write_json(fp_plan_file($id),$plan))return false;
        fp_touch_index($id,$plan);return true;
    });
    if(!$ok)throw new RuntimeException('일정을 저장하지 못했습니다. 입력한 내용을 다시 저장해 주세요.');
}
function fpsc_references(int $year): array {
    $out=[];$today=fpsc_today();
    foreach(['train','jawi'] as $folder){
        $list=$folder==='train'?tr_list():jw_list();
        foreach($list as $row){
            $r=$folder==='train'?tr_load((string)($row['id']??'')):jw_load((string)($row['id']??''));$d=(array)($r['data']??[]);
            foreach($folder==='train'?['fire'=>'fire','education'=>'edu']:['jawi'=>'edu'] as $kind=>$prefix){
                $when=substr((string)($d[$prefix.'_date']??''),0,10);
                if(!fp_source_date($when)||(int)substr($when,0,4)!==$year||$when>$today)continue;
                $content=trim((string)($d[$prefix.'_content']??''));
                if($kind==='fire'&&$content==='')$content=implode(' · ',array_filter(array_map(static fn($k)=>trim((string)($d[$k]??'')),['fire_c_sohwa','fire_c_tongbo','fire_c_pinan'])));
                $complete=$kind==='jawi'?jw_data_complete($d):($content!==''&&trim((string)($d[$prefix.'_join']??''))!=='');
                if(!$complete)continue;
                $out[$kind][]=['date'=>$when,'place'=>(string)($d[$prefix.'_place']??''),'content'=>$content];
            }
        }
    }
    foreach($out as &$rows){usort($rows,static fn($a,$b)=>strcmp($b['date'],$a['date']));$rows=array_slice($rows,0,12);}unset($rows);
    return $out;
}
function fpsc_record(array $plan,array $event): ?array {
    $id=fpsc_record_id(fp_user_key(),(string)$plan['id'],$event['id']);
    $r=$event['kind']==='jawi'?jw_load($id):tr_load($id);
    if(!$r)return null;
    $meta=$r['data']['_planned_schedule']??[];
    return ($meta['plan']??'')===(string)$plan['id']&&($meta['event']??'')===$event['id']?$r:null;
}
function fpsc_start_record(string $id,string $eventId,string $actual,string $revision): array {
    if(!empty($_SESSION['_mge_help_review']))throw new RuntimeException('건물관리 화면에서 기록을 작성해 주세요.');
    $result=[];
    $bi=bi_load_uid(fp_user_key(),false);
    $ok=mfp_save(fp_user_key(),$id,'11',[],static function()use($id,$eventId,$actual,$revision,$bi,&$result):bool{
        $plan=fp_load_plan($id);
        if(!$plan||!hash_equals(fpo_hash($plan),$revision))throw new RuntimeException('계획서가 변경되었습니다. 다시 열어 주세요.');
        $event=null;foreach(fpsc_events($plan) as $e)if($e['id']===$eventId)$event=$e;
        if(!$event)throw new RuntimeException('저장된 일정을 찾지 못했습니다.');
        $recId=fpsc_record_id(fp_user_key(),$id,$eventId);$jawi=$event['kind']==='jawi';
        $result=['page'=>$jawi?'/jawi_chat.php':'/train_chat.php','id'=>$recId];
        $rec=fpsc_record($plan,$event);
        if(!$rec){
            if(!fp_source_date($actual)||strlen($actual)!==10||(int)substr($actual,0,4)!==fp_plan_year($plan)||$actual>fpsc_today())throw new RuntimeException('해당 연도의 실제 실시일을 선택해 주세요. 미래 날짜는 실시기록으로 만들 수 없습니다.');
            $now=date('Y-m-d H:i:s');$prefix=$event['kind']==='fire'?'fire':'edu';
            $d=['_planned_schedule'=>['plan'=>$id,'event'=>$eventId,'kind'=>$event['kind']],$prefix.'_date'=>$actual,$prefix.'_place'=>$event['place'],$prefix.'_content'=>$event['content']];
            $d[$jawi?'site_name':'t_name']=(string)($plan['sections']['1']['data']['name']??$bi['name']??'');
            $d[$jawi?'address':'t_addr']=(string)($plan['sections']['1']['data']['addr']??$bi['address']??'');
            if($event['kind']==='education')$d['edu_skip']='0';
            // Attendance, results and completion are deliberately left for the actual record.
            $rec=['id'=>$recId,'created_at'=>$now,'updated_at'=>$now,'data'=>$d];
            $file=$jawi?jw_file($recId):tr_file($recId);
            if(is_file($file))throw new RuntimeException('기존 기록을 확인해 주세요. 새 기록을 덮어쓰지 않았습니다.');
            if(!($jawi?jw_write_json($file,$rec):tr_write_json($file,$rec)))return false;
        }
        // Also repairs a prior interrupted index write without overwriting the user's draft.
        $file=$jawi?jw_index_file():tr_index_file();$rows=$jawi?jw_read_json($file):tr_read_json($file);
        foreach($rows as $row)if(($row['id']??'')===$recId)return true;
        $d=$rec['data'];$rows[]=['id'=>$recId,'title'=>$d[$jawi?'site_name':'t_name']??'(작성 중)', $jawi?'edu_date':'train_date'=>$d[$event['kind']==='fire'?'fire_date':'edu_date']??'','created_at'=>$rec['created_at'],'updated_at'=>$rec['updated_at']];
        return $jawi?jw_write_json($file,$rows):tr_write_json($file,$rows);
    });
    if(!$ok)throw new RuntimeException('기록을 만들지 못했습니다. 다시 시도해 주세요.');
    return $result;
}
