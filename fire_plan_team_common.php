<?php
declare(strict_types=1);
/** Only printable saved fields, never raw pasted roster text. */
function fpt_document(array $row):array {
 $text=static fn($v)=>is_scalar($v)?trim((string)$v):'';
 $person=static function($m)use($text):array{$m=is_array($m)?$m:[];return ['name'=>$text($m['name']??$m[0]??''),'tel'=>$text($m['tel']??$m[1]??''),'task'=>$text($m['task']??$m[2]??''),'dept'=>$text($m['dept']??$m[3]??'')];};
 $out=['id'=>$text($row['id']??''),'site_name'=>$text($row['site_name']??''),'work_type'=>$text($row['work_type']??''),'saved'=>$text($row['saved']??$row['updated_at']??$row['created']??''),'cmd'=>$person($row['cmd']??[]),'deputy'=>$person($row['deputy']??[]),'groups'=>[]];
 foreach((array)($row['groups']??[]) as $g)if(is_array($g))$out['groups'][]=['name'=>$text($g['name']??''),'members'=>array_map($person,array_values(array_filter((array)($g['members']??[]),'is_array')))];
 $key=static function($m){$name=mb_strtolower($m['name']);$tel=preg_replace('/\D/','',$m['tel']);return $name!==''||$tel!==''?$name.'|'.$tel:'';};$counts=[];
 $add=static function($m)use(&$counts,$key){$k=$key($m);if($k!=='')$counts[$k]=($counts[$k]??0)+1;};
 $add($out['cmd']);$add($out['deputy']);foreach($out['groups'] as $g)foreach($g['members'] as $m)$add($m);
 $mark=static function(&$m)use($key,$counts){$m['concurrent']=($counts[$key($m)]??0)>1;};$mark($out['cmd']);$mark($out['deputy']);foreach($out['groups'] as &$g)foreach($g['members'] as &$m)$mark($m);unset($g,$m);
 $out['total']=count($counts);return $out;
}
