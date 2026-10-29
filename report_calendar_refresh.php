<?php
/** CLI only. Run every 15 minutes; official data is re-fetched at most every six hours/year. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Asia/Seoul');
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/manager_visit_store.php';
$dir=__DIR__.'/data/report_calendar';if(!is_dir($dir))mkdir($dir,0775,true);
$lock=fopen($dir.'/worker.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
try{
 $months=rh_months(date('Y-m-d'));$cache=rh_snapshot();
 foreach($cache['wanted']??[] as $m=>$at)if($at>time()-86400*400)$months[]=$m;
 $files=glob(__DIR__.'/data/manager_visits/*.php')?:[];
 foreach($files as $f)try{$months=array_merge($months,rc_months(mg_read($f)));}catch(Throwable $e){error_log('Report calendar: unreadable visit store '.basename($f));}
 $calendar=rh_sync(array_values(array_unique($months)),in_array('--force',$argv,true));$updated=0;
 foreach($files as $f)try{$changed=mg_tx($f,static function(array &$s)use($calendar){$before=$s;rc_refresh($s,$calendar);return $before!==$s;},true);if($changed)$updated++;}catch(Throwable $e){error_log('Report calendar: reconciliation failed '.basename($f));}
 $bad=0;foreach(array_unique($months) as $m)if(empty($calendar['months'][$m]['checked_at'])||!empty($calendar['months'][$m]['error']))$bad++;
 file_put_contents($dir.'/worker_status.php',"<?php exit; ?>\n".json_encode(['at'=>date('c'),'updated_stores'=>$updated,'unavailable_months'=>$bad],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
 echo json_encode(['updated_stores'=>$updated,'unavailable_months'=>$bad],JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{flock($lock,LOCK_UN);fclose($lock);}
