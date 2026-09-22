<?php
declare(strict_types=1);
const AP_PRICE = 59000;
const AP_MONTHS = 12;
const AP_PLANS = ['yearly'=>['name'=>'연간 구독','price'=>AP_PRICE,'period'=>'년','months'=>AP_MONTHS]];

const AP_OFFER = 'annual_59000_v1';
function ap_status(array $sub): string {
  $status=(string)($sub['status']??'none');
  $end=strtotime((string)($sub['expires_at']??$sub['next_billing']??''));
  return $status==='active'&&$end!==false&&$end<=time()?'expired':$status;
}
