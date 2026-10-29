<?php
declare(strict_types=1);

/** Source dates may include a time; validate the date without guessing invalid dates. */
function fp_source_date(string $value): string {
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:$|[ T]\d{2}:\d{2})/D', trim($value), $m)) return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $m[1]);
    return $date && $date->format('Y-m-d') === $m[1] ? $m[1] : '';
}

/** Records are references, not an assertion that next year's training has happened. */
function fp_training_reference(array $records, string $folder, ?int $year): array {
    $blocks = []; $suggestions = []; $latest = [];
    usort($records, static fn($a,$b) => strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
    foreach ($records as $record) {
        $d = is_array($record['data']??null) ? $record['data'] : [];
        $types = $folder === 'train' ? ['fire'=>['소방훈련','t1'], 'edu'=>['소방교육','t2']] : ['edu'=>['자위소방대 교육','']];
        foreach ($types as $prefix => [$title,$target]) {
            $date = fp_source_date((string)($d[$prefix.'_date']??''));
            if ($date === '' || ($year !== null && (int)substr($date,0,4) !== $year)) continue;
            $future = $date > date('Y-m-d');
            $lines = [$title.' · '.$date.' ('.($future?'저장된 예정 기록':'저장된 실시 기록').')'];
            $content = [];
            if ($prefix === 'fire') {
                foreach (['fire_kind'=>'방식','fire_c_sohwa'=>'소화','fire_c_tongbo'=>'통보','fire_c_pinan'=>'피난','fire_content'=>'내용'] as $key=>$label) {
                    $v=trim((string)($d[$key]??'')); if($v!=='')$content[]=$label.': '.$v;
                }
                if (!$content && !empty($d['fire_types'])) $content[]='훈련: '.implode(' · ',array_filter((array)$d['fire_types'],'is_string'));
            } elseif (trim((string)($d['edu_content']??''))!=='') $content[]=(string)$d['edu_content'];
            $place=trim((string)($d[$prefix.'_place']??'')); if($place!=='')$lines[]='장소: '.$place;
            $count=trim((string)($d[$prefix.'_target']??''));
            if($count!=='' && is_numeric($count))$lines[]='대상: '.$count.'명';
            foreach($content as $line)$lines[]=$line;
            foreach ([$prefix.'_improve'=>'개선계획', 'edu_fix'=>'보완사항', 'edu_action'=>'조치사항'] as $key=>$label) {
                if($prefix==='fire' && strpos($key,'edu_')===0)continue;
                $v=trim((string)($d[$key]??'')); if($v!=='')$lines[]=$label.': '.$v;
            }
            $blocks[]=implode("\n",$lines);
            // A recorded method can be chosen for the new plan; never infer a future date.
            if($target!=='' && !isset($latest[$target]) && $content){
                $latest[$target]=true;
                $suggestions[$target.'_how']=['label'=>'기존 '.$title.' 내용 사용','value'=>implode("\n",$content)];
                $suggestions[$target.'_when']=['label'=>(int)substr($date,5,2).'월 · 기존 '.$title.' 월','value'=>'매년 '.(int)substr($date,5,2).'월'];
            }
        }
    }
    $memo=implode("\n\n",$blocks);
    // Keep imported text within the existing answer endpoint's 30 KB bound.
    if(strlen($memo)>26000)$memo=mb_strcut($memo,0,25500,'UTF-8')."\n(이하 기록은 원본 교육·훈련 자료에서 확인)";
    return ['memo'=>$memo,'suggestions'=>$suggestions];
}

/** Full records are snapshotted with the annual plan, never reloaded by public readers. */
function fp_training_records(string $folder, ?int $year): array {
    $key=fp_user_key();
    if(!in_array($folder,['train','jawi'],true)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$key))return [];
    $base=__DIR__.'/data/'.$folder.'/'.$key;$records=[];$seen=[];
    foreach(fp_read_json($base.'/_index.json') as $row){
        if(!is_array($row))continue;
        $id=(string)($row['id']??'');if(!preg_match('/^[0-9]{1,64}$/D',$id)||isset($seen[$id]))continue;
        $seen[$id]=true;$record=fp_read_json($base.'/'.$id.'.json');
        if(!$record||!is_array($record['data']??null))continue;
        $match=false;foreach($folder==='train'?['fire_date','edu_date']:['edu_date'] as $field){
            $date=fp_source_date((string)($record['data'][$field]??''));
            if($date!==''&&($year===null||(int)substr($date,0,4)===$year))$match=true;
        }
        if($match)$records[]=$record;
    }
    usort($records,static fn($a,$b)=>strcmp((string)($a['updated_at']??''),(string)($b['updated_at']??'')));
    return $records;
}
function fp_training_source(string $folder, ?int $year): array {
    return fp_training_reference(fp_training_records($folder,$year),$folder,$year);
}
/** A compact image copy keeps printed/shared records independent of login and later photo deletion. */
function fp_training_photo(string $file):string {
    $uid=fp_user_key();
    if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid)||$file===''||basename($file)!==$file)return '';
    $base=realpath(__DIR__.'/data/train/'.$uid.'/photos');
    $path=$base?realpath($base.'/'.$file):false;
    if(!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>8*1024*1024)return '';
    $info=@getimagesize($path);
    $mime=$info?([IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_GIF=>'image/gif',IMAGETYPE_WEBP=>'image/webp'][$info[2]]??''):'';
    if($mime===''||$info[0]*$info[1]>20000000)return '';
    $bytes=file_get_contents($path);if($bytes===false)return '';
    if(function_exists('imagecreatefromstring')){
        $im=@imagecreatefromstring($bytes);
        if($im){$scale=min(1,1200/max($info[0],$info[1]));$copy=imagecreatetruecolor(max(1,(int)round($info[0]*$scale)),max(1,(int)round($info[1]*$scale)));
            imagefill($copy,0,0,imagecolorallocate($copy,255,255,255));imagecopyresampled($copy,$im,0,0,0,0,imagesx($copy),imagesy($copy),$info[0],$info[1]);
            ob_start();imagejpeg($copy,null,85);$bytes=ob_get_clean();unset($copy,$im);$mime='image/jpeg';
        }
    }
    return 'data:'.$mime.';base64,'.base64_encode($bytes);
}
function fp_training_forms(string $folder,int $year):array {
    static $cache=[];$cacheKey=fp_user_key().'|'.$folder.'|'.$year;
    if(isset($cache[$cacheKey]))return $cache[$cacheKey];
    $out=[];
    foreach(fp_training_records($folder,$year) as $record){
        $data=$record['data'];$images=[];$missing=[];
        if($folder==='train')foreach(['fire1','fire2','edu1','edu2'] as $slot){
            $file=(string)($data['photos'][$slot]??'');$images[$slot]=fp_training_photo($file);
            if($file!==''&&$images[$slot]==='')$missing[]=$slot;
        }
        unset($data['photos']);
        $out[]=['id'=>(string)($record['id']??''),'updated_at'=>(string)($record['updated_at']??''),'data'=>$data,'photo_images'=>$images,'missing_photos'=>$missing];
    }
    return $cache[$cacheKey]=$out;
}

/** Read only this account generation's incidents, filtered to the plan year. */
function fp_incident_source(?int $year):array {
    $uid=fp_user_key();if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid)||$year===null)return [];
    require_once __DIR__.'/manager_common.php';$member=mg_members()[$uid]??null;
    if(!is_array($member)||empty($member['created']))return [];
    $path=__DIR__.'/data/false_alarm/'.hash('sha256',$uid.'|'.(string)$member['created']).'.json';$out=[];
    foreach(fp_read_json($path) as $record){
        if(!is_array($record)||(int)substr((string)($record['occurred_at']??''),0,4)!==$year)continue;
        $row=[];foreach(['occurred_at','location','cause','action','status'] as $key)$row[$key]=is_scalar($record[$key]??null)?(string)$record[$key]:'';
        $out[]=$row;
    }
    usort($out,static fn($a,$b)=>strcmp($a['occurred_at'],$b['occurred_at']));return $out;
}
