<?php
/**
 * mail_config.php — 이메일 발송 설정 (Resend SMTP)
 *
 * ▸ API 키는 mail_secret.php 에서만 관리합니다. (.gitignore 대상 → 서버에 직접 업로드)
 * ▸ 이 파일에는 키를 절대 적지 마세요. (git에 올라감)
 */

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─────────────────────────────────────────────
// API 키 로드 (mail_secret.php 는 `return '키';` 또는 define() 방식 모두 지원)
$secretFile = __DIR__ . '/mail_secret.php';
if (is_file($secretFile)) {
  $apiKey = require $secretFile;
  if (!defined('RESEND_API_KEY') && is_string($apiKey) && trim($apiKey) !== '') {
    define('RESEND_API_KEY', trim($apiKey));
  }
  unset($apiKey);
} else {
  error_log('메일 설정 오류: mail_secret.php 파일이 서버에 없습니다.');
}
unset($secretFile);

define('SMTP_HOST', 'smtp.resend.com');
define('SMTP_USER', 'resend');                          // Resend는 아이디가 항상 'resend'
define('SMTP_PORT', 587);
define('SMTP_FROM', 'info@xn--989ay50awvdzmk18f.com');  // Resend에서 Verified 된 도메인
define('SMTP_FROM_NAME', '소방계획서.com');
// ─────────────────────────────────────────────

/**
 * 이메일 발송
 * @return bool 성공 여부
 */
function send_mail(string $to, string $subject, string $body): bool {
  if (!defined('RESEND_API_KEY') || !is_string(RESEND_API_KEY) || !str_starts_with(RESEND_API_KEY, 're_')) {
    error_log('메일 발송 실패: mail_secret.php의 API 키 설정을 확인하세요.');
    return false;
  }
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = RESEND_API_KEY;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->Timeout    = 15;

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('메일 발송 실패: ' . $mail->ErrorInfo);
    return false;
  }
}
