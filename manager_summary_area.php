<?php
declare(strict_types=1);
function mb_summary_area($value): ?float {
    if(!is_string($value)&&!is_int($value)&&!is_float($value))return null;
    $raw=str_replace([',',' '], '', trim((string)$value));
    if(!preg_match('/^\d+(?:\.\d+)?$/D',$raw))return null;
    $number=(float)$raw;
    return is_finite($number)&&$number>0?$number:null;
}
function mb_summary_building_area(array $info): array {
    $rows=$info['bd_dong_list']??[];
    if(is_string($rows))$rows=json_decode($rows,true);
    $rows=is_array($rows)?array_values(array_filter($rows,'is_array')):[];
    if(!$rows){$area=mb_summary_area($info['area_t']??null);return ['total_area'=>$area,'area_missing_dongs'=>0,'area_dong_count'=>0];}
    $pick=(string)($info['bd_dong_pick']??'');$primary=null;
    foreach($rows as $i=>$row)if(($row['dong']??'')===$pick){$primary=$i;break;}
    $sum=0.0;$known=0;$missing=0;
    foreach($rows as $i=>$row){
        // Match the primary-dong value displayed on the basic-information page.
        $raw=$i===$primary&&array_key_exists('area_t',$info)?$info['area_t']:($row['area']??null);
        $area=mb_summary_area($raw);
        if($area===null){$missing++;continue;}
        $sum+=$area;$known++;
    }
    return ['total_area'=>$known?$sum:null,'area_missing_dongs'=>$missing,'area_dong_count'=>count($rows)];
}
