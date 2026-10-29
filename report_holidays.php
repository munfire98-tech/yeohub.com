<?php
/** Official KASI special-day API. Full-year validation, cache and bounded refresh. */
declare(strict_types=1);
function rh_valid_date(string $d):bool {return (bool)preg_match('/^20\d{2}-\d{2}-\d{2}$/D',$d)&&checkdate((int)substr($d,5,2),(int)substr($d,8,2),(int)substr($d,0,4));}
function rh_file():string {return __DIR__.'/data/report_calendar/holidays.php';}
function rh_months(string $date):array {
 $d=new DateTimeImmutable(substr($date,0,7).'-01',new DateTimeZone('Asia/Seoul'));$out=[];
 for($i=0;$i<3;$i++)$out[]=$d->modify('+'.$i.' months')->format('Y-m');return $out;
}
function rh_parse_year(string $json,int $year):array {
 $j=json_decode($json,true);$header=$j['response']['header']??[];$body=$j['response']['body']??[];
 if((string)($header['resultCode']??'')!=='00'||!isset($body['totalCount'])||!is_numeric($body['totalCount']))throw new RuntimeException('공식 공휴일 API 응답을 확인하지 못했습니다.');
 $total=(int)$body['totalCount'];$items=$body['items']['item']??[];if(isset($items['locdate']))$items=[$items];
 if(!is_array($items)||$total<8||$total>100||count($items)!==$total)throw new RuntimeException('해당 연도의 공식 공휴일 자료가 준비되지 않았습니다.');
 $days=[];foreach($items as $r){$raw=(string)($r['locdate']??'');if(!preg_match('/^'.preg_quote((string)$year,'/').'\d{4}$/D',$raw))throw new RuntimeException('공휴일 응답 연도가 일치하지 않습니다.');
  $date=substr($raw,0,4).'-'.substr($raw,4,2).'-'.substr($raw,6,2);$name=trim((string)($r['dateName']??''));
  if(!rh_valid_date($date)||!in_array($r['isHoliday']??null,['Y','N'],true)||$name==='')throw new RuntimeException('공휴일 자료 형식이 올바르지 않습니다.');
  if($r['isHoliday']==='Y')$days[$date]=isset($days[$date])?$days[$date].' · '.$name:$name;
 }
 foreach(['01-01','03-01','05-05','06-06','08-15','10-03','10-09','12-25'] as $fixed)if(!isset($days[$year.'-'.$fixed]))throw new RuntimeException('공휴일 자료가 일부 누락되었습니다.');
 ksort($days);return $days;
}
function rh_fetch_year(int $year):array {
 $api=is_file(__DIR__.'/api_keys.php')?require __DIR__.'/api_keys.php':[];
 $key=trim((string)($api['holiday']??$api['hub']??''));if($key==='')throw new RuntimeException('특일 정보 API 인증키 설정이 필요합니다.');
 $url='https://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService/getRestDeInfo?'.http_build_query(['serviceKey'=>str_contains($key,'%')?rawurldecode($key):$key,'solYear'=>$year,'numOfRows'=>100,'pageNo'=>1,'_type'=>'json']);
 $c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'Sobangplan-ReportCalendar/1.0']);
 $body=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);unset($c);
 if($code!==200||!is_string($body)||strlen($body)>200000)throw new RuntimeException('공식 공휴일 API 연결 또는 활용 승인을 확인해 주세요.');return rh_parse_year($body,$year);
}
function rh_queue(array $months):void {
 mg_tx(rh_file(),static function(array &$s)use($months){foreach(array_unique($months) as $m)if(preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',(string)$m)){if(time()-(int)($s['wanted'][$m]??0)>86400)$s['wanted'][$m]=time();}},true);
}
function rh_snapshot():array {return mg_read(rh_file());}
function rh_sync(array $months,bool $force=false):array {
 rh_queue($months);$dir=dirname(rh_file());$lock=fopen($dir.'/refresh.lock','c+');if(!$lock)return rh_snapshot();
 if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return rh_snapshot();}
 try{foreach(array_unique(array_map(static fn($m)=>(int)substr($m,0,4),$months)) as $year){
  $cache=rh_snapshot();$needed=array_filter($months,static fn($m)=>(int)substr($m,0,4)===$year);$due=$force;
  foreach($needed as $m)if(time()-(int)($cache['months'][$m]['checked_at']??0)>=21600)$due=true;
  if(!$due||(!$force&&time()-(int)($cache['attempts'][$year]??0)<900))continue;
  try{$days=rh_fetch_year($year);$result=[];
   for($m=1;$m<=12;$m++){$month=sprintf('%04d-%02d',$year,$m);$md=array_filter($days,static fn($d)=>str_starts_with($d,$month),ARRAY_FILTER_USE_KEY);$result[$month]=['days'=>$md,'checked_at'=>time(),'attempted_at'=>time(),'hash'=>hash('sha256',json_encode($md,JSON_UNESCAPED_UNICODE)),'source'=>'https://www.data.go.kr/data/15012690/openapi.do','error'=>''];}
   mg_tx(rh_file(),static function(array &$s)use($result,$year){foreach($result as $m=>$r)$s['months'][$m]=$r;$s['attempts'][$year]=time();},true);
  }catch(Throwable $e){mg_tx(rh_file(),static function(array &$s)use($needed,$year){foreach($needed as $m){$s['months'][$m]=($s['months'][$m]??[])+['days'=>[],'checked_at'=>0];$s['months'][$m]['error']='공식 공휴일 API 갱신 실패 · 활용 승인 또는 연결 확인 필요';}$s['attempts'][$year]=time();},true);}
 }}finally{flock($lock,LOCK_UN);fclose($lock);}return rh_snapshot();
}
/** Day zero is excluded. Every Saturday, Sunday and officially marked holiday is excluded. */
function rh_deadline(string $basis,array $calendar):array {
 if(!rh_valid_date($basis))throw new RuntimeException('점검일을 확인해 주세요.');
 $d=new DateTimeImmutable($basis,new DateTimeZone('Asia/Seoul'));$count=0;$hashes=[];$stale=false;
 for($i=0;$i<120;$i++){
  $d=$d->modify('+1 day');$date=$d->format('Y-m-d');$month=$d->format('Y-m');$row=$calendar['months'][$month]??[];
  if(empty($row['checked_at']))return ['deadline'=>'','ready'=>false,'stale'=>true,'hash'=>''];
  $hashes[$month]=$row['hash'];$stale=$stale||time()-(int)$row['checked_at']>21600||!empty($row['error']);
  if((int)$d->format('N')<6&&!isset($row['days'][$date]))$count++;
  if($count===15)return ['deadline'=>$date,'ready'=>true,'stale'=>$stale,'hash'=>hash('sha256',json_encode($hashes))];
 }throw new RuntimeException('보고 마감일 계산 범위를 초과했습니다.');
}

/** Configurable construction period; filing uses a separate exclusive business-day count. */
function rh_count_days(string $basis,int $days,bool $includeStart,bool $businessOnly,array $calendar):array {
 if(!rh_valid_date($basis)||$days<1||$days>366)throw new RuntimeException('기간 계산 기준을 확인해 주세요.');
 $d=new DateTimeImmutable($basis,new DateTimeZone('Asia/Seoul'));$count=0;$hashes=[];$stale=false;
 for($i=0;$i<1100;$i++){
  if($i>0||!$includeStart)$d=$d->modify('+1 day');$date=$d->format('Y-m-d');
  if(!rh_valid_date($date))throw new RuntimeException('계산 가능한 날짜 범위를 벗어났습니다.');
  if($businessOnly){$m=$d->format('Y-m');$row=$calendar['months'][$m]??[];
   if(empty($row['checked_at']))return ['date'=>'','ready'=>false,'stale'=>true,'hash'=>''];
   $hashes[$m]=$row['hash'];$stale=$stale||time()-(int)$row['checked_at']>21600||!empty($row['error']);
   if((int)$d->format('N')>=6||isset($row['days'][$date]))continue;
  }
  if(++$count===$days)return ['date'=>$date,'ready'=>true,'stale'=>$stale,'hash'=>hash('sha256',json_encode($hashes))];
 }throw new RuntimeException('기간 계산 범위를 초과했습니다.');
}
