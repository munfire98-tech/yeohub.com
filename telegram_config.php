<?php
/**
 * telegram_config.php — 텔레그램 알림 (관리자 전용)
 *
 * ── 설정할 곳은 아래 두 줄뿐입니다 ──
 *   TG_BOT_TOKEN : BotFather가 준 토큰
 *   TG_CHAT_ID   : getUpdates에서 확인한 "chat":{"id":숫자
 *
 * ※ 아래 함수 안의 코드는 건드리지 마세요.
 */
declare(strict_types=1);

/* ▼▼▼ 여기 두 줄만 수정 ▼▼▼ */
const TG_BOT_TOKEN = '8274355163:AAF6P7GpkLPs2nWn4QUgt1r77-gX57ZDYPI';
const TG_CHAT_ID   = '7117601836';
/* ▲▲▲ 여기까지 ▲▲▲ */


/**
 * 텔레그램 메시지 발송.
 * 실패해도 회원가입 등 본 흐름을 막지 않도록 예외 없이 bool만 반환.
 */
function send_telegram(string $text): bool {
  $token = TG_BOT_TOKEN;
  $chat  = TG_CHAT_ID;

  // 설정이 비었거나 채팅 ID가 숫자가 아니면 발송하지 않음
  if ($token === '' || $chat === '')            return false;
  if (!preg_match('/^\d+:[\w-]+$/', $token))    return false;   // 토큰 형태 확인
  if (!preg_match('/^-?\d+$/', $chat))          return false;   // 채팅 ID는 숫자
  if (!function_exists('curl_init'))            return false;

  $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
      'chat_id'    => $chat,
      'text'       => $text,
      'parse_mode' => 'HTML',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,   // 5초 넘으면 포기 (가입 흐름 지연 방지)
    CURLOPT_CONNECTTIMEOUT => 3,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $code !== 200) return false;

  $arr = json_decode((string)$res, true);
  return !empty($arr['ok']);
}
