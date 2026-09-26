<?php
declare(strict_types=1);
require_once __DIR__.'/annual_plan.php';
const AB_CONSENT='annual_59000_autorenew_v1';
function ab_dir(string $uid):string {
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||$uid[0]==='_')throw new RuntimeException('회원 식별정보를 확인해 주세요.');
 $dir=__DIR__.'/data/subscribe/'.$uid;
 if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('결제 저장 폴더를 만들지 못했습니다.');
 return $dir;
}
function ab_read_file(string $file):array {
 if(!is_file($file))return [];
 $raw=file_get_contents($file);if($raw===false)throw new RuntimeException('결제 정보를 읽지 못했습니다.');
 $raw=preg_replace('/^<\?php exit; \?>\s*/','',$raw);$d=json_decode($raw,true);
 if(!is_array($d))throw new RuntimeException('결제 정보가 손상되었습니다. 관리자 확인이 필요합니다.');return $d;
}
function ab_store(string $file,array $data):void {
 $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
 if(substr($file,-4)==='.php')$json="<?php exit; ?>\n".$json;
 $tmp=tempnam(dirname($file),'.annual-');if($tmp===false)throw new RuntimeException('결제 저장 공간을 확인해 주세요.');
 try{if(file_put_contents($tmp,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('결제 정보 저장 실패');chmod($tmp,0600);if(!@rename($tmp,$file))throw new RuntimeException('결제 정보 교체 실패');}finally{if(is_file($tmp))unlink($tmp);}
}
function ab_read(string $uid):array{return ab_read_file(ab_dir($uid).'/subscription.json');}
function ab_lock(string $uid,callable $fn){
 $dir=ab_dir($uid);$lock=fopen($dir.'/annual_charge.lock','c+');if(!$lock)throw new RuntimeException('결제 잠금을 만들지 못했습니다.');
 try{if(!flock($lock,LOCK_EX))throw new RuntimeException('결제 처리 중입니다.');return $fn($dir);}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function ab_config():array {
 $a=is_file(__DIR__.'/api_keys.php')?require __DIR__.'/api_keys.php':[];
 return ['client'=>(string)($a['toss_client']??''),'secret'=>(string)($a['toss_secret']??''),'live'=>!empty($a['toss_live'])];
}
function ab_mode():string {
 $c=ab_config();$mode=$c['live']?'live':'test';
 if(strpos($c['client'],$mode.'_')!==0||strpos($c['secret'],$mode.'_')!==0)throw new RuntimeException('토스 키와 테스트/라이브 설정이 일치하지 않습니다.');return $mode;
}
function ab_http(string $method,string $path,array $body=[],string $idem=''):array {
 ab_mode();$c=ab_config();$headers=['Authorization: Basic '.base64_encode($c['secret'].':'),'Content-Type: application/json'];
 if($idem!=='')$headers[]='Idempotency-Key: '.$idem;
 $ch=curl_init('https://api.tosspayments.com'.$path);
 $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CUSTOMREQUEST=>$method];
 if($method!=='GET')$options[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 curl_setopt_array($ch,$options);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=is_string($raw)?json_decode($raw,true):[];if(!is_array($data))$data=[];
 return ['ok'=>$code>=200&&$code<300,'code'=>$code,'body'=>$data,'error'=>(string)($data['message']??'결제 응답을 확인하지 못했습니다.')];
}
function ab_api(?callable $transport,string $method,string $path,array $body=[],string $idem=''):array {return $transport?$transport($method,$path,$body,$idem):ab_http($method,$path,$body,$idem);}
function ab_result(bool $ok,string $error='',bool $blocked=false):array{return ['ok'=>$ok,'error'=>$error,'blocked'=>$blocked,'body'=>[]];}
function ab_end(array $d):int {return (int)(strtotime((string)($d['expires_at']??$d['next_billing']??''))?:0);}
function ab_auto(array $d):bool{return ($d['auto_renew']??false)===true&&($d['renewal_consent']['version']??'')===AB_CONSENT;}
function ab_member(string $uid):bool {
 $m=ab_read_file(__DIR__.'/data/members.json')[$uid]??[];
 return ($m['role']??'')==='building'&&($m['status']??'active')==='active';
}
function ab_next(string $date,int $day):string {
 $base=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$base||$base->format('Y-m-d')!==$date||$day<1||$day>31)throw new RuntimeException('결제일 형식을 확인해 주세요.');
 $month=$base->modify('first day of this month')->modify('+12 months');return $month->setDate((int)$month->format('Y'),(int)$month->format('m'),min($day,(int)$month->format('t')))->format('Y-m-d');
}
function ab_customer(string $uid):string {
 return ab_lock($uid,function($dir)use($uid){$d=ab_read($uid);if(empty($d['customer_key'])){$d['customer_key']='ck_'.bin2hex(random_bytes(16));ab_store($dir.'/subscription.json',$d);}return (string)$d['customer_key'];});
}
function ab_register(string $uid,string $auth,string $customer,?callable $transport=null):array {
 return ab_lock($uid,function($dir)use($uid,$auth,$customer,$transport){
  $d=ab_read($uid);if($auth===''||!hash_equals((string)($d['customer_key']??''),$customer))return ab_result(false,'카드 등록 요청이 일치하지 않습니다.');
  if(in_array($d['charge_attempt']['state']??'',['prepared','unknown'],true)||($d['refund_attempt']['state']??'')==='unknown')return ab_result(false,'이전 결제 결과를 확인한 뒤 카드를 변경해 주세요.');
  $mode=ab_mode();$res=ab_api($transport,'POST','/v1/billing/authorizations/issue',['authKey'=>$auth,'customerKey'=>$customer]);
  if(!$res['ok'])return $res;$b=$res['body'];
  if(empty($b['billingKey'])||($b['customerKey']??'')!==$customer)return ab_result(false,'카드 등록 응답을 확인하지 못했습니다.');
  $d['billing_key']=(string)$b['billingKey'];$d['billing_mode']=$mode;
  $d['billing_merchant']=hash('sha256',ab_config()['client']);
  $d['card']=['company'=>(string)($b['card']['issuerCode']??''),'number'=>(string)($b['card']['number']??''),'type'=>(string)($b['card']['cardType']??'')];$d['card_registered_at']=date('Y-m-d H:i:s');
  // A separate receipt retains the issued key if the primary snapshot cannot be saved.
  ab_store($dir.'/card_receipt.php',$d);ab_store($dir.'/subscription.json',$d);return $res;
 });
}
function ab_valid_payment(array $b,array $a):bool {
 return ($b['status']??'')==='DONE'&&($b['orderId']??'')===$a['order_id']&&(int)($b['totalAmount']??0)===AP_PRICE&&!empty($b['paymentKey'])&&($b['currency']??'KRW')==='KRW';
}
function ab_commit_payment(string $uid,string $dir,array $d,array $a,array $b):array {
 if(!ab_valid_payment($b,$a))throw new RuntimeException('결제 결과의 주문번호·금액·상태가 일치하지 않습니다.');
 // Receipt is written before changing entitlement. Failed snapshot writes leave a recoverable receipt.
 ab_store($dir.'/charge_receipt.php',['attempt'=>$a,'payment'=>$b]);
 $d['charge_attempt']=$a;$d['charge_attempt']['state']='done';$d['charge_attempt']['payment_key']=$b['paymentKey'];
 $paid=strtotime((string)($b['approvedAt']??''));$at=$paid?date('Y-m-d H:i:s',$paid):date('Y-m-d H:i:s');
 $d['status']='active';$d['plan']='yearly';$d['plan_name']=AP_PLANS['yearly']['name'];$d['price']=AP_PRICE;$d['paid_at']=$at;$d['started_at']=$d['started_at']??$at;
 $d['bill_day']=$a['anchor_day'];$d['next_billing']=$a['period_end'];$d['next_at']=$a['period_end'];$d['expires_at']=$a['period_end'];$d['next_billing_at']=$a['period_end'].' 00:00:00';
 $d['last_payment_key']=$b['paymentKey'];$d['last_error']='';$d['retry_count']=0;unset($d['retry_after'],$d['billing_notice'],$d['refund'],$d['refund_attempt'],$d['failed_from_status']);
 if($a['mode']==='live'&&empty($d['manager_first_payment']))$d['manager_first_payment']=['status'=>'DONE','live'=>true,'amount'=>AP_PRICE,'payment_key'=>$b['paymentKey'],'order_id'=>$a['order_id'],'at'=>$at];
 $history=(array)($d['history']??[]);$exists=false;foreach($history as $r)if(($r['orderId']??'')===$a['order_id'])$exists=true;
 if(!$exists)$history[]=['at'=>$at,'type'=>$a['kind']==='renewal'?'renewal':'payment','amount'=>AP_PRICE,'orderId'=>$a['order_id'],'paymentKey'=>$b['paymentKey'],'ok'=>true,'memo'=>'연간 59,000원 결제 완료','test'=>$a['mode']!=='live'];
 $d['history']=array_slice($history,-100);ab_store($dir.'/subscription.json',$d);return ['ok'=>true,'body'=>$b,'error'=>'','recovered'=>true];
}
function ab_reconcile_locked(string $uid,string $dir,array $d,?callable $transport):array {
 $a=$d['charge_attempt']??[];if(!in_array($a['state']??'',['prepared','unknown'],true))return ab_result(false,'확인할 결제가 없습니다.',true);
 $receipt=ab_read_file($dir.'/charge_receipt.php');
 if(($receipt['attempt']['order_id']??'')===($a['order_id']??'')&&ab_valid_payment($receipt['payment']??[],$a))return ab_commit_payment($uid,$dir,$d,$a,$receipt['payment']);
 if(($a['mode']??'')!==ab_mode()||($a['merchant']??'')!==hash('sha256',ab_config()['client']))return ab_result(false,'이전 결제와 현재 토스 상점 설정이 다릅니다. 관리자 확인이 필요합니다.',true);
 $r=ab_api($transport,'GET','/v1/payments/orders/'.rawurlencode($a['order_id']));
 if($r['ok']&&ab_valid_payment($r['body'],$a))return ab_commit_payment($uid,$dir,$d,$a,$r['body']);
 $d['charge_attempt']['state']='unknown';$d['billing_notice']='결제 결과를 확인 중입니다. 추가 결제는 중단되어 있습니다.';ab_store($dir.'/subscription.json',$d);
 return ab_result(false,$d['billing_notice'],true); // Even NOT_FOUND is not permission to make another charge.
}
function ab_charge_user(string $uid,string $kind='manual',?callable $transport=null):array {
 $res=ab_lock($uid,function($dir)use($uid,$kind,$transport){
  $d=ab_read($uid);$a=$d['charge_attempt']??[];
  if(in_array($a['state']??'',['prepared','unknown'],true))return ab_reconcile_locked($uid,$dir,$d,$transport);
  if($kind==='reconcile')return ab_result(false,'확인할 결제가 없습니다.',true);
  if(!ab_member($uid))return ab_result(false,'활성 건물관리자 계정만 결제할 수 있습니다.',true);
  if(in_array($d['status']??'',['refund_pending'],true)||in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true))return ab_result(false,'환불 결과 확인 중에는 결제할 수 없습니다.',true);
  if(in_array($d['status']??'',['active','payment_failed'],true)&&ab_end($d)>time())return ab_result(false,'이미 결제한 이용기간이 남아 있습니다.',true);
  if(empty($d['billing_key'])||empty($d['customer_key']))return ab_result(false,'먼저 결제 카드를 등록해 주세요.',true);
  $mode=ab_mode();if(($d['billing_mode']??'')!==$mode||($d['billing_merchant']??'')!==hash('sha256',ab_config()['client']))return ab_result(false,'현재 토스 상점에서 결제 카드를 다시 등록해 주세요.',true);
  if(!ab_auto($d))return ab_result(false,'연간 자동결제 안내를 확인하고 동의해 주세요.',true);
  if($kind==='renewal'){
   if(!in_array($d['status']??'',['active','payment_failed'],true)||ab_end($d)===0)return ab_result(false,'자동결제 대상이 아닙니다.',true);
   if((int)($d['retry_count']??0)>=3||strtotime((string)($d['retry_after']??''))>time())return ab_result(false,'재시도 대기 또는 관리자 확인 대상입니다.',true);
  }
  $date=date('Y-m-d');$day=(int)date('j');
  // Scheduled renewals preserve the anniversary. A job delayed >= one year is held for review.
  if($kind==='renewal'){$date=date('Y-m-d',ab_end($d));$day=(int)($d['bill_day']??date('j',ab_end($d)));if(strtotime(ab_next($date,$day))<=time())return ab_result(false,'장기간 미처리된 갱신입니다. 관리자 확인이 필요합니다.',true);}
  $a=['state'=>'prepared','order_id'=>'annual_'.bin2hex(random_bytes(16)),'idem'=>bin2hex(random_bytes(24)),'mode'=>$mode,'merchant'=>hash('sha256',ab_config()['client']),'kind'=>$kind,'created_at'=>date('c'),'period_end'=>ab_next($date,$day),'anchor_day'=>$day];
  $d['charge_attempt']=$a;ab_store($dir.'/subscription.json',$d); // Must succeed BEFORE an external charge.
  $r=ab_api($transport,'POST','/v1/billing/'.rawurlencode($d['billing_key']),['customerKey'=>$d['customer_key'],'amount'=>AP_PRICE,'orderId'=>$a['order_id'],'orderName'=>AP_PLANS['yearly']['name']],$a['idem']);
  if($r['ok']&&ab_valid_payment($r['body'],$a))return ab_commit_payment($uid,$dir,$d,$a,$r['body']);
  // Only explicit payment declines are retryable; transport/API ambiguity is reconciled by GET.
  $declines=['EXCEED_MAX_CARD_INSTALLMENT_PLAN','EXCEED_MAX_DAILY_PAYMENT_COUNT','EXCEED_MAX_PAYMENT_AMOUNT','EXCEED_MAX_ONE_DAY_AMOUNT','EXCEED_MAX_MONTHLY_PAYMENT_AMOUNT','EXCEED_MAX_CARD_LIMIT','EXCEED_MAX_AMOUNT','NOT_ENOUGH_BALANCE','INVALID_CARD_EXPIRATION','INVALID_CARD_NUMBER','REJECT_CARD_PAYMENT','REJECT_ACCOUNT_PAYMENT','STOPPED_CARD','INVALID_BILL_KEY'];
  $failed=in_array($r['body']['code']??'',$declines,true)&&$r['code']>=400&&$r['code']<500;
  $d['charge_attempt']['state']=$failed?'failed':'unknown';$d['last_error']=$r['error'];
  if($failed){$d['failed_from_status']=$d['status']??'none';$d['status']='payment_failed';$n=(int)($d['retry_count']??0)+1;$d['retry_count']=$n;$d['retry_after']=date('c',time()+($n===1?86400:3*86400));}
  $d['billing_notice']=$failed?'결제하지 못했습니다. 카드 정보와 한도를 확인해 주세요.':'결제 결과 확인 중입니다. 다시 청구하지 않고 기존 주문을 조회합니다.';ab_store($dir.'/subscription.json',$d);
  return ab_result(false,$d['billing_notice'],!$failed);
 });
 if($res['ok'])ab_rewards($uid);return $res;
}
function ab_rewards(string $uid):void {
 try{$d=ab_read($uid);if(!empty($d['manager_first_payment'])){require_once __DIR__.'/manager_common.php';mg_award($uid,$d['manager_first_payment']);}}catch(Throwable $e){error_log('Annual billing reward reconciliation required');}
}
function ab_renewal(string $uid,bool $enabled):void {
 ab_lock($uid,function($dir)use($uid,$enabled){$d=ab_read($uid);if($enabled&&($d['status']??'')==='refund_pending')throw new RuntimeException('환불 확인 중에는 자동갱신을 켤 수 없습니다.');
 $d['auto_renew']=$enabled;$d['renewal_changed_at']=date('c');if(!$enabled&&($d['notice_kind']??'')==='upcoming'){unset($d['billing_notice'],$d['notice_kind']);}
 if($enabled)$d['renewal_consent']=['version'=>AB_CONSENT,'at'=>date('c'),'price'=>AP_PRICE,'months'=>12];
 ab_store($dir.'/subscription.json',$d);});
}

function ab_latest_payment(array $sub): array {
  $candidates = [];
  foreach ((array)($sub['history'] ?? []) as $row) {
    if (!is_array($row) || (int)($row['amount'] ?? 0) <= 0) continue;
    $type = (string)($row['type'] ?? '');
    if (in_array($type, ['refund','refund_pending','refund_failed','cancel','resubscribe'], true) || (array_key_exists('ok',$row) && !$row['ok'])) continue;
    $candidates[] = $row;
  }
  usort($candidates, fn($a,$b) => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
  $last = $candidates[0] ?? [];
  return [
    'at' => (string)($last['at'] ?? $sub['paid_at'] ?? $sub['started_at'] ?? ''),
    'amount' => (int)($last['amount'] ?? $sub['price'] ?? 0),
    'payment_key' => trim((string)($last['paymentKey'] ?? $last['payment_key']
      ?? $sub['last_payment_key'] ?? $sub['payment_key'] ?? '')),
    'order_id' => (string)($last['orderId'] ?? $last['order_id'] ?? ''),
  ];
}
function ab_refund_quote(array $sub, ?int $nowTs = null): array {
  $nowTs = $nowTs ?? time();
  $pay = ab_latest_payment($sub);
  $startTs = strtotime($pay['at']);
  $endRaw = (string)($sub['expires_at'] ?? $sub['next_billing'] ?? '');
  $endTs = strtotime($endRaw);
  if ($startTs === false || $endTs === false || $pay['amount'] <= 0) {
    return ['ok'=>false,'amount'=>0,'full'=>false,'reason'=>'최근 결제정보 또는 이용기간을 확인할 수 없습니다.','payment'=>$pay,'start'=>'','end'=>'','total_days'=>0,'remaining_days'=>0];
  }
  $totalDays = max(1, (int)ceil(($endTs - $startTs) / 86400));
  $remainingDays = max(0, min($totalDays, (int)ceil(($endTs - $nowTs) / 86400)));
  $full = ($nowTs - $startTs) <= 7 * 86400;
  $amount = $full ? $pay['amount'] : (int)floor($pay['amount'] * $remainingDays / $totalDays);
  return ['ok'=>true,'amount'=>max(0,min($pay['amount'],$amount)),'full'=>$full,
    'reason'=>$full?'결제 후 7일 이내 전액 환불':'남은 기간 일할 계산', 'payment'=>$pay,
    'start'=>date('Y-m-d',$startTs),'end'=>date('Y-m-d',$endTs),
    'total_days'=>$totalDays,'remaining_days'=>$remainingDays];
}
function ab_refund_match(array $payment,array $attempt):bool {
 if(($payment['paymentKey']??'')!==$attempt['payment_key'])return false;
 foreach((array)($payment['cancels']??[]) as $c)if(($c['cancelReason']??'')===$attempt['reason']&&(int)($c['cancelAmount']??0)===$attempt['amount'])return true;
 return false;
}
function ab_commit_refund(string $dir,array $d,array $r,array $body):array {
 if(!ab_refund_match($body,$r))throw new RuntimeException('환불 응답 확인이 필요합니다.');
 ab_store($dir.'/refund_receipt.php',['attempt'=>$r,'payment'=>$body]);
 $d['refund_attempt']=$r;$d['refund_attempt']['state']='done';$d['status']='refunded';$d['auto_renew']=false;$d['canceled_at']=date('Y-m-d H:i:s');$d['expires_at']=date('Y-m-d');
 $d['refund']=['status'=>'done','amount'=>$r['amount'],'payment_key'=>$r['payment_key'],'refunded_at'=>date('c')];unset($d['billing_notice']);
 $d['history'][]=['at'=>date('Y-m-d H:i:s'),'type'=>'refund','amount'=>$r['amount'],'memo'=>'해지·환불 완료'];
 if(($body['status']??'')==='CANCELED'){$d['manager_full_refunds'][]=$r['payment_key'];$d['manager_full_refunds']=array_values(array_unique($d['manager_full_refunds']));}
 ab_store($dir.'/subscription.json',$d);return ['ok'=>true,'error'=>'','body'=>$body];
}
function ab_refund_user(string $uid,?callable $transport=null,bool $reconcileOnly=false):array {
 $res=ab_lock($uid,function($dir)use($uid,$transport,$reconcileOnly){
  $d=ab_read($uid);$r=$d['refund_attempt']??[];
  if(in_array($d['charge_attempt']['state']??'',['prepared','unknown'],true))return ab_result(false,'결제 결과를 먼저 확인해야 합니다.',true);
  if(in_array($r['state']??'',['prepared','unknown'],true)){
   $saved=ab_read_file($dir.'/refund_receipt.php');if(($saved['attempt']['id']??'')===($r['id']??'')&&ab_refund_match($saved['payment']??[],$r))return ab_commit_refund($dir,$d,$r,$saved['payment']);
   if(($r['mode']??'')!==ab_mode()||($r['merchant']??'')!==hash('sha256',ab_config()['client']))return ab_result(false,'환불 요청 당시 토스 상점 설정으로 확인해야 합니다.',true);
   $q=ab_api($transport,'GET','/v1/payments/'.rawurlencode($r['payment_key']));
   if($q['ok']&&ab_refund_match($q['body'],$r))return ab_commit_refund($dir,$d,$r,$q['body']);
   return ab_result(false,'환불 결과를 확인 중입니다. 추가 환불 요청은 보내지 않았습니다.',true);
  }
  if($reconcileOnly)return ab_result(false,'확인할 환불이 없습니다.',true);
  if(!in_array($d['status']??'',['active','payment_failed'],true))return ab_result(false,'현재 구독 상태에서는 자동 환불할 수 없습니다.',true);
  $quote=ab_refund_quote($d);if(!$quote['ok']||$quote['payment']['payment_key']==='')return ab_result(false,'결제 식별정보를 확인할 수 없습니다. 관리자에게 문의해 주세요.',true);
  if($quote['amount']<=0){$d['auto_renew']=false;$d['status']='canceled';ab_store($dir.'/subscription.json',$d);return ab_result(true);}
  $key=$quote['payment']['payment_key'];$q=ab_api($transport,'GET','/v1/payments/'.rawurlencode($key));
  if(!$q['ok']||($q['body']['paymentKey']??'')!==$key||(int)($q['body']['totalAmount']??0)!==$quote['payment']['amount']||(int)($q['body']['balanceAmount']??-1)<$quote['amount'])return ab_result(false,'현재 결제 잔액을 확인하지 못해 환불을 중단했습니다.',true);
  $id=bin2hex(random_bytes(16));$r=['id'=>$id,'state'=>'prepared','payment_key'=>$key,'amount'=>$quote['amount'],'reason'=>'연간 구독 환불 '.$id,'mode'=>ab_mode(),'merchant'=>hash('sha256',ab_config()['client']),'created_at'=>date('c')];
  $d['refund_attempt']=$r;$d['auto_renew']=false;$d['status']='refund_pending';$d['refund']=['status'=>'pending','amount'=>$r['amount']];ab_store($dir.'/subscription.json',$d);
  $q=ab_api($transport,'POST','/v1/payments/'.rawurlencode($key).'/cancel',['cancelReason'=>$r['reason'],'cancelAmount'=>$r['amount']],'refund-'.$id);
  if($q['ok']&&ab_refund_match($q['body'],$r))return ab_commit_refund($dir,$d,$r,$q['body']);
  $d['refund_attempt']['state']='unknown';$d['billing_notice']='환불 결과를 확인 중입니다. 추가 청구와 환불 요청은 중단되어 있습니다.';ab_store($dir.'/subscription.json',$d);return ab_result(false,$d['billing_notice'],true);
 });
 if($res['ok']){try{$d=ab_read($uid);require_once __DIR__.'/manager_common.php';foreach($d['manager_full_refunds']??[] as $key)mg_reverse($uid,$key);}catch(Throwable $e){error_log('Annual billing refund reward reconciliation required');}}
 return $res;
}
function ab_scheduler_settings():array{return ab_read_file(__DIR__.'/data/subscribe/_annual_scheduler.php')+['enabled'=>false,'mode'=>'test','token_hash'=>'','last_run'=>''];}
function ab_scheduler_update(callable $fn):array {
 $base=__DIR__.'/data/subscribe';if(!is_dir($base)&&!mkdir($base,0750,true)&&!is_dir($base))throw new RuntimeException('저장 폴더 생성 실패');
 $h=fopen($base.'/_scheduler.lock','c+');if(!$h)throw new RuntimeException('스케줄러 잠금 실패');
 try{flock($h,LOCK_EX);$d=ab_scheduler_settings();$d=$fn($d);ab_store($base.'/_annual_scheduler.php',$d);return $d;}finally{flock($h,LOCK_UN);fclose($h);}
}
function ab_due_reason(string $uid,array $d):string {
 if(!ab_member($uid))return '비활성 또는 건물관리자 계정 아님';
 if(!ab_auto($d))return '자동갱신 미동의 또는 해제';
 if(!in_array($d['status']??'',['active','payment_failed'],true))return '구독 상태 제외';
 if(!ab_end($d)||ab_end($d)>time())return '결제일 전';
 if((int)($d['retry_count']??0)>=3)return '실패 3회 · 카드 확인 필요';
 if(strtotime((string)($d['retry_after']??''))>time())return '재시도 대기';
 return '';
}
function ab_batch(bool $execute=false,?callable $transport=null):array {
 $settings=ab_scheduler_settings();$mode=ab_mode();
 if($execute&&(!$settings['enabled']||$settings['mode']!==$mode))throw new RuntimeException('자동결제 실행이 비활성화되어 있거나 결제 모드가 변경되었습니다.');
 $base=__DIR__.'/data/subscribe';$lock=fopen($base.'/_batch.lock','c+');if(!$lock)throw new RuntimeException('실행 잠금 실패');
 if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return ['running'=>true];}
 $results=[];$start=microtime(true);
 try{
  $dirs=glob($base.'/*',GLOB_ONLYDIR)?:[];sort($dirs);$cursor=(string)($settings['cursor']??'');$dirs=array_merge(array_filter($dirs,fn($x)=>basename($x)>$cursor),array_filter($dirs,fn($x)=>basename($x)<=$cursor));
  $last='';foreach($dirs as $dir){if(microtime(true)-$start>5)break;if($execute){$currentSettings=ab_scheduler_settings();if(!$currentSettings['enabled']||$currentSettings['mode']!==$mode)break;}$uid=basename($dir);if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$uid))continue;$last=$uid;
   try{$d=ab_read($uid);
    if($execute&&ab_auto($d)&&($d['status']??'')==='active'&&ab_end($d)>time()&&ab_end($d)<=time()+7*86400){ab_lock($uid,function($dir)use($uid){$fresh=ab_read($uid);if(ab_auto($fresh)&&($fresh['status']??'')==='active'&&ab_end($fresh)>time()&&ab_end($fresh)<=time()+7*86400){$fresh['billing_notice']=''.($fresh['next_billing']??'').'에 연간 구독료 59,000원이 자동결제될 예정입니다.';$fresh['notice_kind']='upcoming';ab_store($dir.'/subscription.json',$fresh);}});}
    if(in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true)){$r=$execute?ab_refund_user($uid,$transport,true):ab_result(false,'환불 결과 조회 예정',true);}
    elseif(in_array($d['charge_attempt']['state']??'',['prepared','unknown'],true)){$r=$execute?ab_charge_user($uid,'reconcile',$transport):ab_result(false,'결제 결과 조회 예정',true);}
    else{$reason=ab_due_reason($uid,$d);if($reason!==''){$r=ab_result(false,$reason,true);}else{$r=$execute?ab_charge_user($uid,'renewal',$transport):ab_result(false,'59,000원 갱신 대상 · 모의 실행',true);}}
    $results[]=['uid'=>$uid,'ok'=>$r['ok'],'message'=>$r['ok']?'처리 완료':$r['error']];
   }catch(Throwable $e){$results[]=['uid'=>$uid,'ok'=>false,'message'=>'저장 또는 처리 오류 · 관리자 확인 필요'];}
  }
  if($execute)ab_scheduler_update(function($s)use($results,$last){$s['last_run']=date('c');$s['cursor']=$last;$s['last_results']=array_slice($results,-100);return $s;});return ['dry_run'=>!$execute,'mode'=>$mode,'results'=>$results];
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
/** Explicit administrator action: archive proven test-only subscription data before going live. */
function ab_reset_test_user(string $uid):void {
 ab_lock($uid,function($dir)use($uid){
  $d=ab_read($uid);if(in_array($d['charge_attempt']['state']??'',['prepared','unknown'],true)||in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true))throw new RuntimeException('진행 중인 결제 결과를 먼저 확인해 주세요.');
  if(!empty($d['manager_first_payment']['live']))throw new RuntimeException('실결제 내역이 있어 테스트 초기화를 중단했습니다.');
  $found=false;foreach($d['history']??[] as $row){if(!empty($row['paymentKey'])||!empty($row['payment_key'])){if(($row['test']??null)!==true)throw new RuntimeException('테스트 여부가 불명확한 결제가 있습니다.');$found=true;}}
  if(!$found)throw new RuntimeException('테스트 결제임을 확인할 수 없어 초기화하지 않았습니다.');
  ab_store($dir.'/test_archive_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.php',$d);
  $new=['status'=>'none','customer_key'=>$d['customer_key']??'','auto_renew'=>false,'history'=>[],'test_reset_at'=>date('c')];ab_store($dir.'/subscription.json',$new);
 });
}
