<?php
declare(strict_types=1);
const AP_PRICE = 69000;
const AP_PROMO_PRICE = 59000;
const AP_PROMOTION = 'preregistered_continuous_59000_v2';
const AP_MONTHS = 12;
const AP_PLANS = ['yearly'=>['name'=>'연간 구독','price'=>AP_PRICE,'period'=>'년','months'=>AP_MONTHS]];

const AP_OFFER = 'annual_69000_promotion_rejoin_59000_v5';
function ap_status(array $sub): string {
  $status=(string)($sub['status']??'none');
  $end=strtotime((string)($sub['expires_at']??$sub['next_billing']??''));
  return $status==='active'&&$end!==false&&$end<=time()?'expired':$status;
}

function ap_promotion_period_text():string {
 $config=require __DIR__.'/promotion_config.php';$day=(string)($config['ends_on']??'');
 return $day===''?'프로모션 종료일은 추후 안내합니다.':'프로모션 가입·사전등록 연결 마감일: '.$day.' (한국 시간).';
}
