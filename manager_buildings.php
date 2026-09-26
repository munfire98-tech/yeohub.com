<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_subscription.php';
require_once __DIR__.'/manager_addresses_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');echo '{"ok":false}';exit;}
try{
    $actor=mg_uid();$members=mg_members();
    if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);echo '{"ok":false}';exit;}
    mr_sync_manager($actor);
    $state=mg_read(mg_state_file());$rows=[];$pending=[];$pendingRequests=[];
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
        $rows[]=['subscription'=>ms_subscription($uid),'approval_date'=>$date,'approval_month'=>$date!==''?(int)substr($date,5,2):null,'representative'=>(string)($bi['rep']??''),'building_tel'=>(string)($bi['tel']??''),'safety_managers'=>$managers,'uid'=>$uid,'name'=>trim((string)($bi['name']??''))?:((string)($member['nickname']??$uid)), 'address'=>trim((string)($bi['address']??'')),'lat'=>$lat,'lng'=>$lng];
    }
    $rows=array_merge($rows,ma_map_rows($actor,$members,$state));
    echo json_encode(['ok'=>true,'buildings'=>$rows,'pending_keys'=>$pending,'pending_requests'=>$pendingRequests],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false,"error":"연결된 건물 정보를 불러오지 못했습니다."}';}
