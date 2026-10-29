<?php
declare(strict_types=1);
require_once __DIR__.'/fire_plan_schedule_common.php';

function bwy_year($value): ?int {
    $year=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
    return $year===false?null:$year;
}
function bwy_date($value): string {
    if(!is_string($value)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/',trim($value),$m))return '';
    return checkdate((int)$m[2],(int)$m[3],(int)$m[1])?substr(trim($value),0,10):'';
}
// Records may contain a drill and education held in different years.
function bwy_record_year(array $record,string $folder,int $year): bool {
    $data=(array)($record['data']??[]);$dates=[];
    foreach($folder==='train'?['fire_date','edu_date']:['edu_date'] as $key){$date=bwy_date($data[$key]??'');if($date!=='')$dates[]=$date;}
    if($dates)return (bool)array_filter($dates,static fn($d)=>(int)substr($d,0,4)===$year);
    return (bwy_year($record['record_year']??null)??(int)substr((string)($record['created_at']??''),0,4))===$year;
}
function bwy_json(string $file): array {
    if(!is_file($file))return [];
    $data=json_decode((string)@file_get_contents($file),true);
    return is_array($data)?$data:[];
}
function bwy_read(string $uid,string $root): array {
    $out=['train'=>[],'jawi'=>[],'months'=>[]];
    if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid))return $out;
    foreach(['train','jawi'] as $folder){
        foreach(bwy_json($root.'/'.$folder.'/'.$uid.'/_index.json') as $row){
            $id=(string)($row['id']??'');if(!preg_match('/^[0-9A-Za-z]{1,100}$/D',$id))continue;
            $rec=bwy_json($root.'/'.$folder.'/'.$uid.'/'.$id.'.json');if($rec)$out[$folder][$id]=$rec;
        }
    }
    $key=preg_replace('/[^A-Za-z0-9_]/','_',$uid);
    foreach(glob($root.'/worklog/'.$key.'/m*.json')?:[] as $file){
        if(preg_match('/^m(\d{4}-(?:0[1-9]|1[0-2]))\.json$/D',basename($file),$m))$out['months'][]=$m[1];
    }
    return $out;
}
function bwy_actual(array $records,int $year,string $today): array {
    $counts=['fire'=>0,'education'=>0,'jawi'=>0];
    foreach(['train','jawi'] as $folder)foreach($records[$folder]??[] as $r){
        $d=(array)($r['data']??[]);$has=static fn($key)=>trim((string)($d[$key]??''))!=='';
        foreach($folder==='train'?['fire','education']:['jawi'] as $kind){
            $date=bwy_date($d[$kind==='fire'?'fire_date':'edu_date']??'');
            if(!$date||(int)substr($date,0,4)!==$year||$date>$today)continue;
            if($kind==='jawi')$complete=$has('edu_place')&&$has('edu_content')&&$has('jawi_total')&&$has('jawi_join');
            elseif($kind==='education')$complete=$has('edu_content')&&$has('edu_join');
            else $complete=$has('fire_join')&&(array_filter((array)($d['fire_types']??[]))||$has('fire_content')||$has('fire_c_sohwa')||$has('fire_c_tongbo')||$has('fire_c_pinan'));
            if($complete)$counts[$kind]++;
        }
    }
    return $counts;
}
function bwy_summary(array $records,?array $plan,int $year,string $today,int $commonDone): array {
    $current=(int)substr($today,0,4);$future=$year>$current;$past=$year<$current;
    $actual=bwy_actual($records,$year,$today);$scheduled=['fire'=>[],'education'=>[],'jawi'=>[]];
    foreach(fpsc_events($plan??[]) as $event){
        $kind=$event['kind']??'';$date=$event['date']??'';
        if(isset($scheduled[$kind])&&is_string($date)&&preg_match('/^'.$year.'-(0[1-9]|1[0-2])(?:-\d{2})?$/D',$date))$scheduled[$kind][]=$event;
    }
    $months=array_values(array_filter($records['months']??[],static fn($m)=>(int)substr($m,0,4)===$year&&$m<=substr($today,0,7)));
    $monthlyDone=$past?count($months)>0:(!$future&&in_array(substr($today,0,7),$months,true));
    $tasks=['monthly'=>['done'=>$monthlyDone,'planned'=>false,'badge'=>$future?'시작 전':($past?count($months).'개월 보관':($monthlyDone?'작성 완료':(int)substr($today,5,2).'월 작성')),
        'description'=>$future?'매월 해당 월에 기록해요':($past?$year.'년 월별 기록':(int)substr($today,5,2).'월 업무수행기록')]];
    foreach(['jawi'=>['jawi'],'train'=>['fire','education']] as $task=>$kinds){
        $done=array_sum(array_intersect_key($actual,array_flip($kinds)))>0;
        $ready=count(array_filter($kinds,static fn($kind)=>count($scheduled[$kind])>0));
        $parts=[];foreach($kinds as $kind)if($scheduled[$kind]){
            $events=$scheduled[$kind];usort($events,static fn($a,$b)=>strcmp($a['date'],$b['date']));
            $e=$events[0];$parts[]=($task==='train'?($kind==='fire'?'훈련 ':'교육 '):'').(int)substr($e['date'],5,2).'월'.(($e['precision']??'')==='date'?' '.(int)substr($e['date'],8,2).'일':'').(count($events)>1?' 외 '.(count($events)-1).'회':'');
        }
        $tasks[$task]=['done'=>!$future&&$done,'planned'=>$future&&$ready===count($kinds),
            'badge'=>$future?($ready===count($kinds)?'일정 저장':($ready?'일정 보완':'예정일 정하기')):($done?($past?'기록 보관':'작성 완료'):'기록 없음'),
            'description'=>$future?($parts?implode(' · ',$parts).' 예정':'예정 월부터 간편하게 선택'):($past?$year.'년 실시기록':($parts?implode(' · ',$parts).' 예정':$year.'년 실시결과 기록'))];
    }
    $planned=count(array_filter($scheduled,static fn($events)=>count($events)>0));
    $done=$future?$planned:(($past?0:$commonDone)+(int)$monthlyDone+(int)$tasks['jawi']['done']+(int)$tasks['train']['done']);
    return ['year'=>$year,'mode'=>$future?'future':($past?'past':'current'),'tasks'=>$tasks,'done'=>$done,'total'=>$past||$future?3:7,
        'title'=>$year.'년 '.($future?'예정 일정 준비':($past?'기록 보관 현황':'업무 작성 현황')),
        'unit'=>$future?'종 준비':($past?'종 보관':'완료'),
        'note'=>$future?'훈련·소방교육·자위소방대 교육 일정은 이 연도 계획서에 저장돼요.':($past?'지난 기록을 확인하세요. 기본정보·시설·편성·피난계획은 공통입니다.':'매월 기록은 '.(int)substr($today,5,2).'월 · 교육·훈련은 '.$year.'년 기준')];
}
