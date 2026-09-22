<?php
/* =============================================================
   toss_billing.php — 토스페이먼츠 자동결제(빌링) 공통 함수
   ─────────────────────────────────────────────────────────────
   흐름
     1) 카드 등록창 띄우기        … subscribe_page.php (브라우저, 클라이언트 키)
     2) successUrl 로 돌아옴      … toss_billing_return.php
     3) 빌링키 발급              … tb_issue_billing_key()   ★ 이 키를 저장
     4) 매년 결제 승인            … tb_charge()

   중요
     · 빌링키는 한 번 발급되면 다시 조회할 수 없습니다. 반드시 저장하세요.
     · customerKey 는 유추 불가능한 값이어야 합니다(회원아이디 그대로 쓰면 안 됨).
     · 결제 주기 스케줄링은 직접 해야 합니다(크론 등).
   ============================================================= */
declare(strict_types=1);

require_once __DIR__ . '/user_key.php';
require_once __DIR__ . '/manager_common.php';
require_once __DIR__.'/annual_plan.php';

/* ── 설정 읽기 ───────────────────────────────────────────── */
function tb_conf(): array {
  static $c = null;
  if ($c === null) {
    $api = @include __DIR__ . '/api_keys.php';
    $c = [
      'client' => is_array($api) ? (string)($api['toss_client'] ?? '') : '',
      'secret' => is_array($api) ? (string)($api['toss_secret'] ?? '') : '',
      'live'   => is_array($api) ? (bool)($api['toss_live'] ?? false) : false,
    ];
  }
  return $c;
}
function tb_client_key(): string { return tb_conf()['client']; }
function tb_is_live(): bool      { return tb_conf()['live']; }
/** 키가 실제로 채워져 있는지 (자리표시자면 false) */
function tb_ready(): bool {
  $c = tb_conf();
  return $c['client'] !== '' && $c['secret'] !== ''
      && strpos($c['client'], '여기에') === false
      && strpos($c['secret'], '여기에') === false;
}

/* ── 저장 위치 ───────────────────────────────────────────── */
function tb_dir(): string {
  $k = app_user_key();
  if ($k === '') return '';
  $d = __DIR__ . '/data/subscribe/' . $k;
  if (!is_dir($d)) @mkdir($d, 0775, true);
  return $d;
}
function tb_file(): string { $d = tb_dir(); return $d === '' ? '' : $d . '/subscription.json'; }

function tb_read(): array {
  $f = tb_file();
  if ($f === '' || !is_file($f)) return [];
  $a = json_decode((string)@file_get_contents($f), true);
  return is_array($a) ? $a : [];
}
function tb_write(array $d): bool {
  $f = tb_file();
  if ($f === '') return false;
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

/** 구매자 식별자.
 *  회원아이디를 그대로 쓰면 남이 유추할 수 있어 위험합니다.
 *  회원마다 한 번 만들어 저장해 두고 계속 같은 값을 씁니다. */
function tb_customer_key(): string {
  $d = tb_read();
  $ck = trim((string)($d['customer_key'] ?? ''));
  if ($ck !== '') return $ck;

  $ck = 'ck_' . bin2hex(random_bytes(16));   // 유추 불가능한 무작위 값
  $d['customer_key'] = $ck;
  tb_write($d);
  return $ck;
}

/* ── 토스 API 호출 ───────────────────────────────────────── */
/**
 * @return array{ok:bool, code:int, body:array, error:string}
 */
function tb_api(string $path, array $payload): array {
  $secret = tb_conf()['secret'];
  if ($secret === '') return ['ok'=>false,'code'=>0,'body'=>[],'error'=>'시크릿 키가 설정되지 않았습니다.'];

  /* 시크릿 키 뒤에 콜론을 붙여 base64 인코딩 — 콜론을 빠뜨리면 인증 실패합니다 */
  $auth = base64_encode($secret . ':');

  $ch = curl_init('https://api.tosspayments.com' . $path);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Basic ' . $auth,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
  ]);
  $raw  = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $cerr = curl_error($ch);
  curl_close($ch);

  if ($raw === false) return ['ok'=>false,'code'=>0,'body'=>[],'error'=>'통신 실패: ' . $cerr];

  $body = json_decode((string)$raw, true);
  if (!is_array($body)) $body = [];

  if ($code >= 200 && $code < 300) return ['ok'=>true,'code'=>$code,'body'=>$body,'error'=>''];

  $msg = (string)($body['message'] ?? '알 수 없는 오류');
  $ec  = (string)($body['code'] ?? '');
  return ['ok'=>false,'code'=>$code,'body'=>$body,'error'=>($ec !== '' ? "[$ec] " : '') . $msg];
}

/* ── 3) 빌링키 발급 ──────────────────────────────────────── */
/** successUrl 로 받은 authKey + customerKey 로 빌링키를 발급받아 저장합니다. */
function tb_issue_billing_key(string $authKey, string $customerKey): array {
  $res = tb_api('/v1/billing/authorizations/issue', [
    'authKey'     => $authKey,
    'customerKey' => $customerKey,
  ]);
  if (!$res['ok']) return $res;

  $b = $res['body'];
  $d = tb_read();
  $d['billing_key']  = (string)($b['billingKey'] ?? '');
  $d['customer_key'] = $customerKey;
  $d['card']         = [
    'company' => (string)($b['card']['issuerCode'] ?? $b['card']['company'] ?? ''),
    'number'  => (string)($b['card']['number'] ?? ''),   // 마스킹된 번호만 옵니다
    'type'    => (string)($b['card']['cardType'] ?? ''),
  ];
  $d['card_registered_at'] = date('Y-m-d H:i:s');
  tb_write($d);

  return $res;
}

/* ── 4) 결제 승인 ────────────────────────────────────────── */
/** 저장해 둔 빌링키로 결제를 냅니다. 매년 이 함수를 호출하면 됩니다. */
function tb_charge(int $amount, string $orderName): array {
  $deny=static fn(string $message)=>['ok'=>false,'blocked'=>true,'code'=>0,'body'=>[],'error'=>$message];
  if($amount!==AP_PRICE)return $deny('연간 결제 금액은 59,000원입니다. 이전 월간/연간 요금 결제는 중단되었습니다.');
  if(!empty($_SESSION['_imp']))return $deny('대리 편집 중에는 결제할 수 없습니다.');
  $dir=tb_dir();if($dir==='')return $deny('로그인 정보를 확인해 주세요.');
  $lock=fopen($dir.'/annual_charge.lock','c+');
  if(!$lock||!flock($lock,LOCK_EX))return $deny('결제 처리 중입니다. 잠시 후 확인해 주세요.');
  try{
    $current=tb_read();$status=(string)($current['status']??'');
    if($status==='refund_pending')return $deny('환불 처리 중에는 새 결제를 할 수 없습니다.');
    $end=(string)($current['expires_at']??$current['next_billing']??$current['next_at']??'');
    if(($status==='active'||($status==='payment_failed'&&!empty($current['paid_at'])&&!in_array($current['failed_from_status']??'', ['canceled','refunded','expired'],true)))&&($end===''||strtotime($end)===false||strtotime($end)>time()))
      return $deny('이미 결제한 이용기간이 남아 있습니다. 만료 후 연간 결제를 진행해 주세요.');
    return tb_charge_annual_locked(AP_PRICE, AP_PLANS['yearly']['name']);
  }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function tb_charge_annual_locked(int $amount, string $orderName): array {
  $d  = tb_read();
  $bk = trim((string)($d['billing_key'] ?? ''));
  $ck = trim((string)($d['customer_key'] ?? ''));
  if ($bk === '' || $ck === '') {
    return ['ok'=>false,'code'=>0,'body'=>[],'error'=>'등록된 카드가 없습니다.'];
  }

  /* 주문번호는 매번 유일해야 합니다(테스트·라이브 통틀어) */
  $orderId = 'od_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

  $res = tb_api('/v1/billing/' . rawurlencode($bk), [
    'customerKey' => $ck,
    'amount'      => $amount,
    'orderId'     => $orderId,
    'orderName'   => $orderName,
  ]);

  if ($res['ok'] && (($res['body']['status'] ?? '') !== 'DONE' || ($res['body']['orderId'] ?? '') !== $orderId || (int)($res['body']['totalAmount'] ?? 0) !== $amount || empty($res['body']['paymentKey']))) {
    $res['ok'] = false; $res['error'] = '결제 응답 확인이 필요합니다. 다시 결제하지 말고 관리자에게 문의해 주세요.';
  }
  /* 성공·실패 모두 이력을 남깁니다 */
  $d = tb_read();
  $hist = is_array($d['history'] ?? null) ? $d['history'] : [];
  array_unshift($hist, [
    'at'      => date('Y-m-d H:i:s'),
    'amount'  => $amount,
    'name'    => $orderName,
    'orderId' => $orderId,
    'paymentKey' => $res['ok'] ? (string)$res['body']['paymentKey'] : '',
    'ok'      => $res['ok'],
    'msg'     => $res['ok'] ? '결제 완료' : $res['error'],
    'test'    => !tb_is_live(),
  ]);
  $d['history'] = array_slice($hist, 0, 50);

  if ($res['ok']) {
    $d['last_payment_key'] = (string)$res['body']['paymentKey'];
    $live = tb_is_live() && strpos(tb_conf()['secret'], 'live_') === 0;
    if ($live && empty($d['manager_first_payment'])) {
      $d['manager_first_payment'] = ['status'=>'DONE','live'=>true,'amount'=>$amount,'payment_key'=>$d['last_payment_key'],'order_id'=>$orderId,'at'=>date('c')];
    }
    $d['status']     = 'active';
    $d['paid_at']    = date('Y-m-d H:i:s');
    $d['plan']='yearly';$d['plan_name']=AP_PLANS['yearly']['name'];$d['price']=AP_PRICE;
    $d['bill_day']=(int)date('j');
    $d['next_at']=tb_next_billing(date('Y-m-d'),AP_MONTHS,$d['bill_day']);
    $d['next_billing']=$d['next_at'];$d['expires_at']=$d['next_at'];
    $d['next_billing_at']=$d['next_at'].' 00:00:00';
    unset($d['plan_change'],$d['failed_from_status']);
    $d['last_error'] = '';
  } else {
    if(($d['status']??'')!=='payment_failed')$d['failed_from_status']=(string)($d['status']??'none');
    $d['status']     = 'payment_failed';
    $d['last_error'] = $res['error'];
  }
  $saved = tb_write($d);
  if (!$saved) error_log('Billing subscription persistence failed; reconciliation required.');
  if ($res['ok'] && !empty($d['manager_first_payment'])) {
    try { mg_award(app_user_key(),$d['manager_first_payment']); }
    catch (Throwable $e) { error_log('Manager reward deferred: '.$e->getMessage()); }
  }
  return $res;
}

/* ── 해지 ────────────────────────────────────────────────── */
/** 다음 결제일에 결제를 내지 않으면 해지됩니다(별도 API 없음). */
function tb_cancel(): bool {
  $d = tb_read();
  $d['status']       = 'canceled';
  $d['canceled_at']  = date('Y-m-d H:i:s');
  return tb_write($d);
}

/* 월말 결제일은 대상 월의 마지막 날로 제한합니다. */
if (!function_exists('tb_next_billing')) {
  function tb_next_billing(string $from, int $months, int $billDay): string {
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    if (!$base || $base->format('Y-m-d') !== $from || $months < 1 || $billDay < 1 || $billDay > 31) {
      throw new InvalidArgumentException('결제일 계산 값이 올바르지 않습니다.');
    }
    $month = $base->modify('first day of this month')->modify('+'.$months.' months');
    return $month->setDate((int)$month->format('Y'),(int)$month->format('m'),min($billDay,(int)$month->format('t')))->format('Y-m-d');
  }
}
