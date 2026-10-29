<?php
declare(strict_types=1);
require_once __DIR__.'/building_dong_names.php';
require_once __DIR__.'/evacuation_plan_routes.php';
require_once __DIR__.'/evacuation_location_common.php';

function epl_text($value, int $length=500): string {
    if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('입력 형식을 확인해 주세요.');
    $value=trim((string)$value);
    if (!preg_match('//u',$value) || mb_strlen($value,'UTF-8')>$length) throw new InvalidArgumentException('입력한 내용이 너무 길거나 올바르지 않습니다.');
    return $value;
}
function epl_scopes(array $building): array {
    $rows=$building['bd_dong_list']??[];
    if (is_string($rows)) $rows=json_decode($rows,true);
    if (!is_array($rows)) $rows=[];
    $options=[];$seen=[];$primary=trim((string)($building['bd_dong_pick']??''));
    foreach ($rows as $i=>$row) {
        if (!is_array($row)) continue;
        $name=trim((string)($row['dong']??''))?:'동명 미상 '.($i+1);
        $n=($seen[$name]??0)+1;$seen[$name]=$n;
        $id='dong_'.substr(hash('sha256',$name.'#'.$n),0,20);
        $base=$name===$primary&&$n===1;
        $options[$id]=['id'=>$id,'name'=>$name.($n>1?' ('.$n.')':''),'above'=>$row['floor_a']??'','below'=>$row['floor_b']??'','primary'=>$base,'use'=>$row['use']??$building['use']??''];
        if ($base) foreach (['above'=>'floor_a','below'=>'floor_b'] as $k=>$f) if (trim((string)($building[$f]??''))!=='') $options[$id][$k]=$building[$f];
    }
    foreach(bdn_rows($building) as $entry)if(isset($options[$entry['evac_id']]))$options[$entry['evac_id']]['name']=$entry['name'];
    if (!$options) $options['base']=['id'=>'base','name'=>$primary?:((string)($building['name']??'')?:'내 건물'),'above'=>$building['floor_a']??'','below'=>$building['floor_b']??'','primary'=>true,'use'=>$building['use']??''];
    return $options;
}
function epl_floor_keys(int $above,int $below): array {
    $keys=[];for($n=$below;$n>=1;$n--) $keys[]='B'.$n;
    for($n=1;$n<=$above;$n++) $keys[]=$n.'F';return $keys;
}
function epl_floor_label(string $key): string {return str_starts_with($key,'B')?'지하 '.substr($key,1).'층':'지상 '.rtrim($key,'F').'층';}
function epl_content(array $raw): array {
    $out=[];
    foreach (['alarm_method','reporter','controller','assembly_confirmed','headcount_method','special_notes'] as $key) $out[$key]=(string)($raw[$key]??'');
    $out['floors']=is_array($raw['floors']??null)?$raw['floors']:[];
    foreach ((array)($raw['floor_groups']??[]) as $group) {
        if (!is_array($group)||!is_array($group['plan']??null)) continue;
        foreach ((array)($group['floors']??[]) as $key) $out['floors'][$key]=array_merge((array)($out['floors'][$key]??[]),$group['plan']);
    }
    return $out;
}
/** Legacy data becomes a list item in memory. Opening a page never rewrites a plan. */
function epl_collection(array $raw,array $building): array {
    if (($raw['_epl_version']??0)===2) {
        if (!is_array($raw['_epl_plans']??null)) throw new RuntimeException('피난계획 목록을 읽지 못했습니다.');
        // Refresh only presentation labels. IDs, answers, revisions and history stay intact.
        $scopes=epl_scopes($building);
        foreach($raw['_epl_plans'] as &$row){
            $scopeId=$row['scope']['id']??'';
            if(!isset($scopes[$scopeId]))continue;
            $oldName=(string)($row['scope']['name']??'');$newName=$scopes[$scopeId]['name'];
            foreach(['title','published_title'] as $field)if(($row[$field]??'')===$oldName.' 피난계획')$row[$field]=$newName.' 피난계획';
            $row['scope']['name']=$newName;
        }unset($row);
        return $raw;
    }
    $out=$raw;$out['_epl_version']=2;$out['_epl_plans']=[];
    $content=epl_content($raw);
    if ($content['floors']||array_filter(array_diff_key($content,['floors'=>1]),static fn($v)=>trim($v)!=='')) {
        $scopes=epl_scopes($building);$scope=reset($scopes);
        foreach($scopes as $option) if($option['primary']) {$scope=$option;break;}
        // Preserve floors in old plans even if current basic information has changed.
        $a=(int)$scope['above'];$b=(int)$scope['below'];
        foreach(array_keys($content['floors']) as $key){if(preg_match('/^B(\d+)$/',$key,$m))$b=max($b,(int)$m[1]);elseif(preg_match('/^(\d+)F$/',$key,$m))$a=max($a,(int)$m[1]);}
        $scope['above']=$a;$scope['below']=$b;
        $revision=substr(hash('sha256',json_encode($raw)),0,32);
        $out['_epl_plans']['legacy']=['id'=>'legacy','title'=>$scope['name'].' 피난계획','scope'=>$scope,'draft'=>$content,'published'=>$content,'archived'=>false,'legacy'=>true,'revision'=>$revision,'published_revision'=>$revision,'created'=>(string)($raw['updated']??''),'updated'=>(string)($raw['updated']??''),'published_at'=>(string)($raw['updated']??'')];
    }
    return $out;
}
function epl_read(string $file,array $building): array {
    $raw=[];
    if(is_file($file)){$text=file_get_contents($file);$raw=$text===false?null:json_decode($text,true);if(!is_array($raw))throw new RuntimeException('저장된 피난계획을 읽지 못했습니다. 기존 자료를 보호하기 위해 저장을 중단합니다.');}
    return epl_collection($raw,$building);
}
function epl_missing(array $plan,array $scope): array {
    $missing=[];
    foreach(['alarm_method'=>'상황 전파','reporter'=>'119 신고 담당','controller'=>'피난 지휘','assembly_confirmed'=>'최종 집결지','headcount_method'=>'인원 확인 방법'] as $key=>$label) if(trim((string)($plan[$key]??''))==='')$missing[]=$label;
    $keys=epl_floor_keys((int)$scope['above'],(int)$scope['below']);
    if(!$keys)$missing[]='건물 층수';
    foreach($keys as $key){$floor=$plan['floors'][$key]??[];$label=epl_floor_label($key);
        $routeProblem=epl_route_problem($floor,$plan['stairs']??null);if($routeProblem!=='')$missing[]=$label.' · '.$routeProblem;
        foreach(['use'=>'용도','occupants'=>'최대 재실인원','vulnerable'=>'피난 지원 대상','primary_route'=>'주 대피경로','alternate_route'=>'대체 대피경로','guide'=>'피난유도 담당','checker'=>'잔류자 확인 담당'] as $field=>$name)if(trim((string)($floor[$field]??''))==='')$missing[]=$label.' · '.$name;
    }
    return $missing;
}
function epl_clean(array $input,array $scope): array {
    $out=[];foreach(['alarm_method','reporter','controller','assembly_confirmed','headcount_method','special_notes']as$key)$out[$key]=epl_text($input[$key]??'',$key==='special_notes'?1200:500);
    $stairs=epl_stairs($input['stairs']??null);if($stairs!==null)$out['stairs']=$stairs;
    $floors=$input['floors']??[];if(!is_array($floors))throw new InvalidArgumentException('층별 정보를 확인해 주세요.');
    $allowed=epl_floor_keys((int)$scope['above'],(int)$scope['below']);
    foreach(array_keys($floors)as$key)if(!in_array((string)$key,$allowed,true))throw new InvalidArgumentException('선택한 동의 층수와 입력한 층이 다릅니다.');
    $out['floors']=[];
    foreach($allowed as$key){$row=$floors[$key]??[];if(!is_array($row))throw new InvalidArgumentException('층별 정보를 확인해 주세요.');$clean=['label'=>epl_floor_label($key)];
        foreach(['use'=>120,'vulnerable'=>300,'primary_route'=>300,'alternate_route'=>300,'guide'=>100,'checker'=>100]as$field=>$max)$clean[$field]=epl_text($row[$field]??'',$max);
        foreach(['primary_route','alternate_route'] as $field){
            $ref=epl_text($row[$field.'_ref']??'',16);
            if($ref==='')continue;
            if(!preg_match('/^(stair_([1-9]|1[0-9]|20)|direct_[12]|pending)$/D',$ref)||($ref==='pending'&&$field!=='alternate_route'))throw new InvalidArgumentException('대피경로 선택을 확인해 주세요.');
            $clean[$field.'_ref']=$ref;$routeText=epl_route_text($ref,$stairs);
            if($routeText!==null)$clean[$field]=$routeText;
        }
        $count=epl_text($row['occupants']??'',6);if($count!==''&&(!ctype_digit($count)||(int)$count>99999))throw new InvalidArgumentException('재실인원은 0~99,999명으로 입력해 주세요.');
        $clean['occupants']=$count===''?'':(int)$count;$out['floors'][$key]=$clean;
    }
    return $out;
}
/** Exclude deleted records from every published-plan projection. */
function epl_project(array $store,array $rows):array {
    $store['_epl_plans']=$rows;
    $active=array_filter($rows,static fn($r)=>!empty($r['published'])&&empty($r['archived'])&&empty($r['deleted_at']));$projection=[];
    foreach($active as$r){if(!$projection||!empty($r['scope']['primary'])){$projection=$r;if(!empty($r['scope']['primary']))break;}}
    foreach(['alarm_method','reporter','controller','assembly_confirmed','headcount_method','special_notes','floors','floor_groups','stairs','updated']as$key)unset($store[$key]);
    if($projection)$store=array_replace($store,$projection['published'],['floor_groups'=>[],'updated'=>$projection['published_at']]);
    return $store;
}
function epl_backup_legacy(string $file,array $raw):void {
// Keep the exact pre-list data as a protected backup on the first explicit save.
        if(($raw['_epl_version']??0)!==2&&$raw){$backup=dirname($file).'/evacuation_plan_legacy_backup.php';if(!is_file($backup)){$bytes="<?php exit; ?>\n".json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);$temp=tempnam(dirname($file),'.evac_backup_');try{if($temp===false||file_put_contents($temp,$bytes)!==strlen($bytes)||!rename($temp,$backup))throw new RuntimeException('기존 계획 백업을 완료하지 못했습니다.');}finally{if($temp&&is_file($temp))unlink($temp);}}}
}
/** One locked atomic file contains both drafts and published plans plus the legacy projection. */
function epl_save(string $file,array $building,array $input): array {
    if($file==='')throw new RuntimeException('로그인 정보를 확인해 주세요.');
    $id=epl_text($input['id']??'',32);$revision=epl_text($input['revision']??'',64);$request=epl_text($input['request_id']??'',64);
    if(!preg_match('/^(legacy|[a-f0-9]{24})$/D',$id)||!preg_match('/^[a-f0-9]{32}$/D',$request))throw new InvalidArgumentException('저장 요청을 확인해 주세요.');
    $action=$input['action']??'';if(!in_array($action,['draft','publish','delete','restore'],true))throw new InvalidArgumentException('저장 방식을 확인해 주세요.');
    return mg_tx($file,function(array &$raw)use($file,$building,$input,$id,$revision,$request,$action):array{
        $store=epl_collection($raw,$building);$rows=$store['_epl_plans'];$prior=$rows[$id]??null;
        if($prior&&($prior['last_request']??'')===$request)return $prior;
        if(($prior&&!hash_equals((string)$prior['revision'],$revision))||(!$prior&&$revision!==''))throw new DomainException('다른 화면에서 이 계획이 변경되었습니다. 입력 내용을 복사해 둔 뒤 목록을 다시 열어 주세요.');
        if(in_array($action,['delete','restore'],true)){
            if(!$prior)throw new InvalidArgumentException('이 계획을 찾을 수 없습니다. 목록을 다시 열어 주세요.');
            $row=$prior;$now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s');
            if($action==='delete'){
                if(!empty($row['deleted_at']))throw new DomainException('이미 삭제된 계획입니다. 목록을 다시 열어 주세요.');
                $row['deleted_at']=$now;
            }else{
                if(empty($row['deleted_at']))throw new DomainException('이미 복원된 계획입니다. 목록을 다시 열어 주세요.');
                if(count(array_filter($rows,static fn($r)=>empty($r['deleted_at'])))>=100)throw new InvalidArgumentException('목록은 최대 100개까지 보관할 수 있습니다.');
                unset($row['deleted_at']);
                // A newer active plan must never be replaced by a restore operation.
                foreach($rows as$otherId=>$other)if($otherId!==$id&&empty($other['deleted_at'])&&empty($other['archived'])&&!empty($other['published'])&&($other['scope']['id']??'')===($row['scope']['id']??''))$row['archived']=true;
            }
            $row['revision']=bin2hex(random_bytes(16));$row['last_request']=$request;$row['updated']=$now;
            epl_backup_legacy($file,$raw);$rows[$id]=$row;$raw=epl_project($store,$rows);return $row;
        }
        if($prior&&!empty($prior['deleted_at']))throw new DomainException('삭제된 계획입니다. 휴지통에서 복원한 뒤 수정해 주세요.');
        if(!$prior&&$id==='legacy')throw new InvalidArgumentException('기존 계획을 찾을 수 없습니다. 목록을 다시 열어 주세요.');
        if(!$prior&&count(array_filter($rows,static fn($r)=>empty($r['deleted_at'])))>=100)throw new InvalidArgumentException('계획은 최대 100개까지 보관할 수 있습니다.');
        if($prior){$scope=$prior['scope'];}else{
            $options=epl_scopes($building);$scopeId=epl_text($input['scope_id']??'',64);$scope=$options[$scopeId]??null;
            if(!$scope)throw new InvalidArgumentException('동 목록이 변경되었습니다. 목록에서 다시 시작해 주세요.');
            foreach(['above'=>100,'below'=>30]as$key=>$max){$number=epl_text($input[$key]??'',3);if($number===''||!ctype_digit($number)||(int)$number>$max)throw new InvalidArgumentException('지상·지하 층수를 확인해 주세요.');$scope[$key]=(int)$number;}
            if($scope['above']+$scope['below']<1)throw new InvalidArgumentException('하나 이상의 층을 입력해 주세요.');
        }
        $title=epl_text($input['title']??'',100);if($title==='')throw new InvalidArgumentException('계획 이름을 입력해 주세요.');
        $data=$input['plan']??null;if(!is_array($data))throw new InvalidArgumentException('계획 내용을 확인해 주세요.');
        $plan=epl_clean($data,$scope);
        // Coordinates come from this account's saved basic information, never from the browser.
        $plan['_building_locations']=epl_location_snapshot($building);
        if($plan['assembly_confirmed']===''&&$plan['_building_locations']['assembly_name']!=='')$plan['assembly_confirmed']=$plan['_building_locations']['assembly_name'];
        if($action==='publish'){
            $missing=epl_missing($plan,$scope);if($missing)throw new InvalidArgumentException('아직 입력하지 않은 항목이 있습니다: '.implode(', ',array_slice($missing,0,4)));
            if(($input['confirmed']??false)!==true)throw new InvalidArgumentException('현장과 계획 내용을 확인한 뒤 체크해 주세요.');
        }
        epl_backup_legacy($file,$raw);
        $now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s');
        $row=$prior??['id'=>$id,'created'=>$now,'published'=>null,'published_revision'=>'','published_at'=>'','archived'=>false,'legacy'=>false];
        $row=array_replace($row,['title'=>$title,'scope'=>$scope,'draft'=>$plan,'revision'=>bin2hex(random_bytes(16)),'updated'=>$now,'last_request'=>$request]);
        if($action==='publish'){
            foreach($rows as$otherId=>&$other)if($otherId!==$id&&($other['scope']['id']??'')===$scope['id']&&!empty($other['published'])&&empty($other['archived'])&&empty($other['deleted_at'])){$other['archived']=true;$other['revision']=bin2hex(random_bytes(16));}unset($other);
            if(!empty($row['published'])){$row['history'][]=['at'=>$row['published_at'],'plan'=>$row['published'],'title'=>$prior['published_title']??$prior['title'],'scope'=>$scope];}
            $row['published']=$plan;$row['published_title']=$title;$row['published_at']=$now;$row['published_revision']=$row['revision'];$row['archived']=false;$row['legacy']=false;
        }
        $rows[$id]=$row;$raw=epl_project($store,$rows);return $row;
    });
}
