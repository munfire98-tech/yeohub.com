<?php
declare(strict_types=1);
// Pure schedule helpers. Shared/printed documents read only the saved plan snapshot.
function fpsc_today(): string { return (new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d'); }
function fpsc_kinds(): array { return ['fire'=>'소방훈련','education'=>'소방교육','jawi'=>'자위소방대 교육']; }
function fpsc_events(array $plan): array { return (array)($plan['sections']['11']['data']['_training_schedule']['events'] ?? []); }
function fpsc_label(array $event): string {
    $d=(string)($event['date']??'');
    if(preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/',$d,$m))return $m[1].'년 '.(int)$m[2].'월'.(isset($m[3])?' '.(int)$m[3].'일':'').' 예정';
    return '일정 미정';
}
function fpsc_validate($input,int $year): array {
    if(!is_array($input)||!array_is_list($input)||count($input)<3||count($input)>36)throw new RuntimeException('세 가지 교육·훈련의 예정일을 선택해 주세요. 일정은 최대 36개까지 저장할 수 있습니다.');
    $out=[];$ids=[];$kinds=[];
    foreach($input as $e){
        if(!is_array($e))throw new RuntimeException('일정 형식을 확인해 주세요.');
        $id=$e['id']??'';$kind=$e['kind']??'';
        if(!is_string($id)||!preg_match('/^[a-f0-9]{16}$/D',$id)||isset($ids[$id])||!is_string($kind)||!isset(fpsc_kinds()[$kind]))throw new RuntimeException('일정을 다시 열어 확인해 주세요.');
        $ids[$id]=true;$kinds[$kind]=true;$row=['id'=>$id,'kind'=>$kind];
        foreach(['precision','date','same_as','who','place','content'] as $key){
            $v=$e[$key]??'';if(!is_string($v)||strlen($v)>6000)throw new RuntimeException('입력 내용을 확인해 주세요. 너무 긴 내용은 줄여 주세요.');
            $row[$key]=trim($v);
        }
        if($row['who']===''||$row['content']==='')throw new RuntimeException('교육·훈련 대상과 내용을 확인해 주세요.');
        if($row['same_as']!==''){
            if($kind==='fire'||!preg_match('/^[a-f0-9]{16}$/D',$row['same_as']))throw new RuntimeException('같은 날 진행할 소방훈련을 선택해 주세요.');
        }else{
            $p=$row['precision'];$d=$row['date'];
            if(!in_array($p,['month','date'],true)||!preg_match($p==='month'?'/^\d{4}-\d{2}$/D':'/^\d{4}-\d{2}-\d{2}$/D',$d))throw new RuntimeException(fpsc_kinds()[$kind].'의 예정일을 선택해 주세요.');
            $check=DateTimeImmutable::createFromFormat('!Y-m-d',$p==='month'?$d.'-01':$d);
            if(!$check||$check->format($p==='month'?'Y-m':'Y-m-d')!==$d||(int)$check->format('Y')!==$year)throw new RuntimeException($year.'년 안에서 올바른 날짜를 선택해 주세요.');
        }
        $out[]=$row;
    }
    if(count($kinds)!==3)throw new RuntimeException('소방훈련·소방교육·자위소방대 교육을 각각 한 번 이상 계획해 주세요.');
    $byId=array_column($out,null,'id');
    foreach($out as &$e)if($e['same_as']!==''){
        $ref=$byId[$e['same_as']]??null;
        if(!$ref||$ref['kind']!=='fire')throw new RuntimeException('연결한 소방훈련이 없습니다. 날짜를 다시 선택해 주세요.');
        $e['date']=$ref['date'];$e['precision']=$ref['precision'];
    }unset($e);
    return $out;
}
function fpsc_project(array $data,array $events): array {
    $data['_training_schedule']=['version'=>1,'events'=>$events];
    foreach(['fire'=>1,'education'=>2] as $kind=>$num){
        $rows=array_values(array_filter($events,static fn($e)=>$e['kind']===$kind));
        $values=['when'=>implode(' / ',array_map('fpsc_label',$rows)),
          'who'=>implode(' / ',array_unique(array_column($rows,'who'))),
          'how'=>implode("\n",array_map(static fn($e)=>$e['content'].($e['place']!==''?' (장소: '.$e['place'].')':''),$rows))];
        foreach($values as $suffix=>$value){$key='t'.$num.'_'.$suffix;$data[$key]=$value;unset($data['_source_values'][$key]);$data['_chat_answers'][]=$key;}
    }
    $data['_chat_answers']=array_values(array_unique($data['_chat_answers']));
    return $data;
}
function fpsc_record_id(string $uid,string $planId,string $eventId): string {
    // Stable per user/plan/event: retries reuse the draft instead of creating duplicates.
    return '9'.implode('',array_map(static fn($c)=>str_pad((string)hexdec($c),2,'0',STR_PAD_LEFT),str_split(substr(hash('sha256',$uid.'|'.$planId.'|'.$eventId),0,18))));
}
