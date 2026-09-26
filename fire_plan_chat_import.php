<?php
declare(strict_types=1);
/** Pure merge: only schema-valid source values; never overwrite an explicit answer. */
function fp_chat_import(array $plan,array $sources,array $schema):array {
 $plan['sections']=is_array($plan['sections']??null)?$plan['sections']:[];
 foreach($schema as $code=>$fields){
  $section=$plan['sections'][$code]??['data'=>[],'is_done'=>0,'is_skipped'=>0];
  $data=(array)($section['data']??[]);$answers=(array)($data['_chat_answers']??[]);$auto=(array)($data['_source_values']??[]);$changed=false;
  foreach($fields as $key=>$field){
   $current=$data[$key]??null;$wasAuto=array_key_exists($key,$auto)&&$current===$auto[$key];
   $empty=is_array($current)?!$current:$current===null||trim((string)$current)==='';
   if(!$wasAuto&&(in_array($key,$answers,true)||!$empty))continue;
   $value=$sources['data'][$code][$key]??null;
   $valid=false;
   if(($field['type']??'')==='multi'){
    if(is_array($value)&&($value||!empty($sources['known_empty'][$code][$key]))){$value=array_values(array_unique(array_filter($value,static fn($v)=>is_string($v)&&in_array($v,$field['options'],true))));$valid=(bool)$value||!empty($sources['known_empty'][$code][$key]);}
   }elseif(is_scalar($value)){
    $value=trim((string)$value);$valid=$value!==''&&strlen($value)<=30000;
    if($valid&&$field['type']==='choice')$valid=in_array($value,$field['options'],true);
    if($valid&&$field['type']==='number'){$value=str_replace(',','',$value);$valid=is_numeric($value)&&is_finite((float)$value)&&(float)$value>=0;}
    if($valid&&$field['type']==='date'){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);$valid=$date&&$date->format('Y-m-d')===$value;}
   }
   if(!$valid){
    if($wasAuto){unset($data[$key],$auto[$key]);$answers=array_values(array_diff($answers,[$key]));$changed=true;}
    continue;
   }
   if($current!==$value||!in_array($key,$answers,true))$changed=true;
   $data[$key]=$value;$auto[$key]=$value;$answers[]=$key;
  }
  $data['_chat_answers']=array_values(array_unique($answers));$data['_source_values']=$auto;
  if($changed){$plan['status']='draft';$data['_chat_complete']=false;$section['is_done']=0;}
  $section['data']=$data;$plan['sections'][$code]=$section;
 }
 $plan['source_selection']=array_keys(array_filter($sources['groups']??[],static fn($g)=>!empty($g['available'])));
 $plan['source_selection_set']=true;
 $plan['building_name']=(string)($plan['sections']['1']['data']['name']??$plan['building_name']??'');
 $plan['updated_at']=date('Y-m-d H:i:s');
 return $plan;
}
