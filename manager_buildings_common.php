<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_subscription.php';
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/manager_summary_area.php';
/** Only expose completion, never the contents of a user's monthly record. */
function mb_monthly_worklog(string $uid,string $month):array {
    $unknown=['month'=>$month,'completed'=>null];
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))return $unknown;
    // Match the directory key used by work_log_form.php, including hyphenated IDs.
    $workUid=preg_replace('/[^A-Za-z0-9_]/','_',$uid);
    try{
        $record=mg_read(__DIR__.'/data/worklog/'.$workUid.'/m'.$month.'.json');
        foreach(['date','performer'] as $key)if(isset($record[$key])&&!is_string($record[$key]))return $unknown;
        foreach(['sobang','pinan','hwagi','etc'] as $key){
            if(isset($record[$key])&&!is_array($record[$key]))return $unknown;
            foreach(['note','result'] as $field)if(isset($record[$key][$field])&&!is_string($record[$key][$field]))return $unknown;
        }
        return ['month'=>$month,'completed'=>mr_worklog_complete($record,$month)];
    }catch(Throwable $e){return $unknown;} // An unreadable record is not "unwritten".
}
/** Shared read-only building rows for the manager and administrator maps. */
function mb_manager_rows(string $actor,array $members,array $state,?int $now=null):array {
    $month=(new DateTimeImmutable('@'.($now??time())))->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m');
    $rows=[];$pending=[];$pendingRequests=[];
    foreach($members as $uid=>$member){
        $uid=(string)$uid;
        if(is_array($member)&&mg_active($member,'building')&&mg_connection_manager($member,$members)===$actor&&mg_link_status($uid,$member,$state)==='pending'){$pending[]=mg_link_key($uid,$member);$pendingRequests[]=['name'=>trim((string)($member['nickname']??''))?:$uid];}
        if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||!is_array($member)||!mg_can_view($actor,$uid,$members,$state))continue;
        $bi=mg_read(__DIR__.'/data/building/'.$uid.'/info.json');
        if(!$bi){$old=mg_read(__DIR__.'/data/worklog/'.$uid.'/building.json');$bi=['name'=>$old['sangho']??'','address'=>$old['address']??''];}
        foreach($state['address_book']??[] as $savedAddress){
            if(ma_owned($savedAddress,$actor,$members)&&($savedAddress['linked_uid']??'')===$uid&&ma_linked($savedAddress,$actor,$members,$state)){
                if(trim((string)($bi['address']??''))===''){$bi['address']=$savedAddress['address'];unset($bi['bd_lat'],$bi['bd_lng']);}
                if(trim((string)($bi['name']??''))==='')$bi['name']=$savedAddress['name'];break;
            }
        }
        $lat=is_numeric($bi['bd_lat']??null)?(float)$bi['bd_lat']:null;$lng=is_numeric($bi['bd_lng']??null)?(float)$bi['bd_lng']:null;
        if($lat===null||$lng===null||!is_finite($lat)||!is_finite($lng)||abs($lat)>90||abs($lng)>180||($lat===0.0&&$lng===0.0)){$lat=null;$lng=null;}
        $raw=trim((string)($bi['bd_use_apr']??''));$date='';
        if(preg_match('/^(\d{4})[-.\/]?(\d{2})[-.\/]?(\d{2})$/D',$raw,$parts)&&checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))$date=$parts[1].'-'.$parts[2].'-'.$parts[3];
        $managers=[];foreach((array)($bi['mgrs']??[]) as $m){if(!is_array($m)||trim((string)($m['name']??''))==='')continue;$managers[]=['name'=>(string)$m['name'],'type'=>(string)($m['type']??''),'tel'=>(string)($m['tel']??'')];}
        $subscription=ms_subscription($uid);
        $rows[]=mb_summary_building_area($bi)+['building_use'=>trim((string)($bi['bd_use_main']??''))?:trim((string)($bi['use']??'')),'subscription'=>$subscription,'monthly_record'=>!empty($subscription['active'])?mb_monthly_worklog($uid,$month):null,'approval_date'=>$date,'approval_month'=>$date!==''?(int)substr($date,5,2):null,'representative'=>(string)($bi['rep']??''),'building_tel'=>(string)($bi['tel']??''),'safety_managers'=>$managers,'uid'=>$uid,'name'=>trim((string)($bi['name']??''))?:((string)($member['nickname']??$uid)), 'address'=>trim((string)($bi['address']??'')),'lat'=>$lat,'lng'=>$lng];
    }
    $preregistered=ma_map_rows($actor,$members,$state);
    foreach($preregistered as &$draft){$info=(array)($state['address_book'][$draft['address_id']]['basic_info']??[]);$draft['building_use']=trim((string)($info['bd_use_main']??''))?:trim((string)($info['use']??''));$draft=array_merge($draft,mb_summary_building_area((array)($state['address_book'][$draft['address_id']]['basic_info']??[])));}unset($draft);
    $rows=array_merge($rows,$preregistered);
    return ['ok'=>true,'buildings'=>$rows,'pending_keys'=>$pending,'pending_requests'=>$pendingRequests];
}
