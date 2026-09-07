<?php
// safety_ai_api.php — 소방안전관리 AI 도우미 (조회·안내 전용)
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ai_reply(int $status, array $body): void {
  http_response_code($status);
  echo json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function ai_cut(string $value, int $length): string {
  return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}
function ai_length(string $value): int {
  return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$loggedIn = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
  || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1)
  || !empty($_SESSION['is_user']);
if (!$loggedIn) ai_reply(401, ['ok'=>false, 'message'=>'로그인이 필요합니다.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_reply(405, ['ok'=>false, 'message'=>'잘못된 요청입니다.']);

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) ai_reply(400, ['ok'=>false, 'message'=>'요청 형식을 확인해 주세요.']);
$csrf = (string)($body['csrf'] ?? '');
$savedCsrf = (string)($_SESSION['safety_ai_csrf'] ?? '');
if ($savedCsrf === '' || !hash_equals($savedCsrf, $csrf)) {
  ai_reply(403, ['ok'=>false, 'message'=>'화면을 새로고침한 뒤 다시 시도해 주세요.']);
}

$now = microtime(true);
$last = (float)($_SESSION['safety_ai_last'] ?? 0);
if ($now - $last < 1.2) ai_reply(429, ['ok'=>false, 'message'=>'잠시 후 다시 질문해 주세요.']);
$_SESSION['safety_ai_last'] = $now;

$question = trim((string)($body['message'] ?? ''));
if ($question === '' || ai_length($question) > 800) {
  ai_reply(422, ['ok'=>false, 'message'=>'질문은 800자 이내로 입력해 주세요.']);
}

$rawContext = is_array($body['context'] ?? null) ? $body['context'] : [];
$context = [
  'building_name' => ai_cut((string)($rawContext['building_name'] ?? ''), 120),
  'above_floors' => max(0, min(200, (int)($rawContext['above_floors'] ?? 0))),
  'below_floors' => max(0, min(30, (int)($rawContext['below_floors'] ?? 0))),
  'current_task' => ai_cut((string)($rawContext['current_task'] ?? ''), 120),
  'current_action' => ai_cut((string)($rawContext['current_action'] ?? ''), 80),
  'completed' => array_values(array_slice(array_map('strval', (array)($rawContext['completed'] ?? [])), 0, 10)),
  'pending' => array_values(array_slice(array_map('strval', (array)($rawContext['pending'] ?? [])), 0, 10)),
];

$fallback = static function(string $q, array $ctx): string {
  $name = $ctx['building_name'] !== '' ? $ctx['building_name'] : '이 건물';
  $task = $ctx['current_task'] !== '' ? $ctx['current_task'] : '기본정보 확인';
  if (preg_match('/(완료|진행|상태|뭐.*해야|무엇.*해야)/u', $q)) {
    return $name . '의 다음 권장 업무는 ‘' . $task . '’입니다. 아래 실행 버튼으로 시작하시면 됩니다.';
  }
  if (preg_match('/(층|건물|기본정보)/u', $q)) {
    return $name . '은 현재 지상 ' . (int)$ctx['above_floors'] . '층'
      . ((int)$ctx['below_floors'] > 0 ? '·지하 ' . (int)$ctx['below_floors'] . '층' : '')
      . '으로 확인됩니다. 정보가 다르면 왼쪽 기본정보 메뉴에서 수정해 주세요.';
  }
  if (preg_match('/(법|의무|과태료|기준)/u', $q)) {
    return '법령 적용 여부는 건물 용도와 규모에 따라 달라질 수 있습니다. 현재 화면에서는 확정 판단 대신 필요한 서식 작성 순서를 안내합니다. 중요한 판단은 관할 소방서 또는 전문가에게 확인해 주세요.';
  }
  return '현재 저장 상태를 기준으로 ‘' . $task . '’부터 진행하는 것이 좋습니다. 궁금한 업무 이름이나 “지금 뭘 해야 해?”처럼 질문해 주세요.';
};

$apiKey = trim((string)getenv('OPENAI_API_KEY'));
if ($apiKey === '' || !function_exists('curl_init')) {
  ai_reply(200, ['ok'=>true, 'mode'=>'guide', 'message'=>$fallback($question, $context)]);
}

$model = trim((string)getenv('OPENAI_MODEL')) ?: 'gpt-5.4-nano';
$instructions = '당신은 한국 건물 소방안전관리 업무 안내 도우미입니다. 제공된 현재 상태만 근거로 사용자가 다음 행동을 이해하도록 한국어로 3문장 이내로 답하세요. 법적 확정 판단, 허위 완료 판정, 데이터 저장을 하지 마세요. 모르는 내용은 단정하지 말고 관할 소방서 또는 전문가 확인이 필요하다고 말하세요. 개인정보를 요구하지 마세요.';
$input = "현재 상태:\n" . json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
  . "\n\n사용자 질문:\n" . $question;
$payload = json_encode([
  'model'=>$model, 'instructions'=>$instructions, 'input'=>$input,
  'max_output_tokens'=>300, 'store'=>false,
  'safety_identifier'=>hash('sha256', (string)($_SESSION['nickname'] ?? session_id())),
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
  CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>25,
  CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey, 'Content-Type: application/json'],
  CURLOPT_POSTFIELDS=>$payload,
]);
$responseBody = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
if ($responseBody === false || $curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
  ai_reply(200, ['ok'=>true, 'mode'=>'guide', 'message'=>$fallback($question, $context)]);
}

$response = json_decode((string)$responseBody, true);
$answer = '';
foreach ((array)($response['output'] ?? []) as $item) {
  foreach ((array)($item['content'] ?? []) as $content) {
    if (($content['type'] ?? '') === 'output_text') $answer .= (string)($content['text'] ?? '');
  }
}
$answer = trim($answer);
if ($answer === '') $answer = $fallback($question, $context);
ai_reply(200, ['ok'=>true, 'mode'=>'ai', 'message'=>$answer]);
