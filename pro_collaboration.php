<?php
declare(strict_types=1);
/** Read the target account's persisted entitlement; never trust a browser flag. */
function pc_active(string $uid):bool {
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))return false;
 $file=__DIR__.'/data/subscribe/'.$uid.'/subscription.json';
 if(!is_file($file))return false;
 $sub=json_decode((string)file_get_contents($file),true);if(!is_array($sub))return false;
 $status=(string)($sub['status']??'');
 $eligible=$status==='active'||($status==='payment_failed'&&!empty($sub['paid_at'])&&!in_array($sub['failed_from_status']??'',['canceled','refunded','expired'],true));
 if(!$eligible)return false;
 $end=(string)($sub['expires_at']??$sub['next_billing']??$sub['next_at']??'');
 if($end==='')return $status==='active';
 $time=strtotime($end);return $time!==false&&$time>time();
}
function pc_label(string $uid):string {return pc_active($uid)?'PRO 협업 중':'연결 완료 · 협업 시작 전';}
