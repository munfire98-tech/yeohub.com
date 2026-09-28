<?php
declare(strict_types=1);
/** Bounded, read-only registry transport. No URL or credential is logged. */
function br_response_state(array $r):array {
 $status=(int)($r['code']??0);$body=(string)($r['body']??'');
 if($status<200||$status>=300)return ['ok'=>false,'retry'=>$status===0||$status===408||$status===429||$status>=500,'error'=>'조회 서버와 연결하지 못했습니다. 잠시 후 다시 조회해 주세요.'];
 $j=json_decode($body,true);
 if(!is_array($j))return ['ok'=>false,'retry'=>true,'error'=>'조회 서버가 올바르지 않은 응답을 보냈습니다. 다시 조회해 주세요.'];
 if(!isset($j['response']['header'])&&!isset($j['results']['common'])&&!isset($j['documents']))return ['ok'=>false,'retry'=>true,'error'=>'조회 응답 형식이 올바르지 않습니다. 잠시 후 다시 조회해 주세요.'];
 $code=(string)($j['response']['header']['resultCode']??'00');
 if(!in_array($code,['00','0','03','3'],true))return ['ok'=>false,'retry'=>in_array($code,['01','1','02','2','04','4','05','5','23'],true),'error'=>in_array($code,['20','22','29','30','31','32'],true)?'건축물대장 API의 권한 또는 이용 한도를 확인해 주세요.':'건축물대장 서버에서 조회를 완료하지 못했습니다. 잠시 후 다시 조회해 주세요.'];
 if(isset($j['results']['common']['errorCode'])&&(string)$j['results']['common']['errorCode']!=='0')return ['ok'=>false,'retry'=>false,'error'=>'주소 조회 서비스가 요청을 처리하지 못했습니다. 주소 또는 서비스 설정을 확인해 주세요.'];
 return ['ok'=>true,'retry'=>false,'error'=>''];
}
function br_http_get(string $url,array $headers=[],?callable $transport=null):array {
 static $deadline=null;
 if($deadline===null)$deadline=microtime(true)+24;
 $state=['ok'=>false,'retry'=>true,'error'=>'조회 시간이 길어 중단했습니다. 잠시 후 다시 조회해 주세요.'];$r=['body'=>'','code'=>0];
 for($attempt=0;$attempt<2;$attempt++){
  $remaining=(int)(($deadline-microtime(true))*1000);if($remaining<250)break;
  $timeout=min(8000,$remaining);
  if($transport){$r=$transport($url,$headers,$timeout);}
  else{
   $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT_MS=>$timeout,CURLOPT_CONNECTTIMEOUT_MS=>min(3000,$timeout),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json'],$headers)]);
   $body=curl_exec($ch);$r=['body'=>(string)$body,'code'=>(int)curl_getinfo($ch,CURLINFO_HTTP_CODE)];curl_close($ch);
  }
  $state=br_response_state($r);if($state['ok'])return $r;
  if(empty($state['retry'])||$attempt===1)break;
  usleep(150000);
 }
 return $r+['lookup_error'=>$state['error'],'retryable'=>(bool)($state['retry']??true)];
}
