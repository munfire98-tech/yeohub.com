<?php
/* =============================================================
   toss_billing.php — 토스페이먼츠 자동결제(빌링) 공통 함수
   ─────────────────────────────────────────────────────────────
   흐름
     1) 카드 등록창 띄우기        … subscribe_page.php (브라우저, 클라이언트 키)
     2) successUrl 로 돌아옴      … toss_billing_return.php
     3) 빌링키 발급              … tb_issue_billing_key()   ★ 이 키를 저장
     4) 매달 결제 승인            … tb_charge()

   중요
     · 빌링키는 한 번 발급되면 다시 조회할 수 없습니다. 반드시 저장하세요.
     · customerKey 는 유추 불가능한 값이어야 합니다(회원아이디 그대로 쓰면 안 됨).
     · 결제 주기 스케줄링은 직접 해야 합니다(크론 등).
   ============================================================= */
declare(strict_types=1);

require_once __DIR__ . '/user_key.php';

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
/** 저장해 둔 빌링키로 결제를 냅니다. 매달 이 함수를 호출하면 됩니다. */
function tb_charge(int $amount, string $orderName): array {
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

  /* 성공·실패 모두 이력을 남깁니다 */
  $d = tb_read();
  $hist = is_array($d['history'] ?? null) ? $d['history'] : [];
  array_unshift($hist, [
    'at'      => date('Y-m-d H:i:s'),
    'amount'  => $amount,
    'name'    => $orderName,
    'orderId' => $orderId,
    'ok'      => $res['ok'],
    'msg'     => $res['ok'] ? '결제 완료' : $res['error'],
    'test'    => !tb_is_live(),
  ]);
  $d['history'] = array_slice($hist, 0, 50);

  if ($res['ok']) {
    $d['status']     = 'active';
    $d['paid_at']    = date('Y-m-d H:i:s');
    $d['next_at']    = date('Y-m-d', strtotime('+1 month'));
    $d['last_error'] = '';
  } else {
    $d['status']     = 'payment_failed';
    $d['last_error'] = $res['error'];
  }
  tb_write($d);

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
