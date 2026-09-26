<?php
declare(strict_types=1);
/** Called under the request-store lock, scoped to requests this actor may see. */
function mh_prune_deleted_plans(array &$rows,callable $visible,string $base):void {
 $missing=[];
 foreach($rows as $id=>$r){
  if(!is_array($r)||!$visible($r)||!preg_match('/^__fp_([0-9]{1,64})_[0-9]+_[A-Za-z0-9_]+$/D',(string)($r['field']??''),$m))continue;
  $uid=(string)($r['uid']??'');if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$uid))continue;
  $dir=$base.'/'.$uid;
  // An inaccessible/missing account directory may be a hosting problem, not a deleted plan.
  if(!is_dir($dir)||!is_readable($dir))continue;
  $file=$dir.'/'.$m[1].'.json';
  if(!array_key_exists($file,$missing)){clearstatcache(true,$file);$missing[$file]=!file_exists($file);}
  if($missing[$file])unset($rows[$id]);
 }
}
