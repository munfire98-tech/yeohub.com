<?php
/**
 * mail_config.php — 이메일 발송 설정 (Resend SMTP)
 *
 * ▸ RESEND_API_KEY 에 Resend에서 발급받은 API 키(re_로 시작)를 넣으세요.
 *   이 파일은 서버에만 두고 외부에 노출하지 마세요.
 */

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─────────────────────────────────────────────
//  ↓↓↓ 여기 API 키만 넣으세요 ↓↓↓
require_once __DIR__ . '/mail_secret.php';

$resend_api_key = RESEND_API_KEY;   // ← re_로 시작하는 키
//  ↑↑↑ 여기 API 키만 넣으세요 ↑↑↑

define('SMTP_HOST', 'smtp.resend.com');
define('SMTP_USER', 'resend');              // Resend는 아이디가 항상 'resend'
define('SMTP_PORT', 587);
define('SMTP_FROM', 'info@tworix.com');     // DNS 인증된 도메인이어야 함
define('SMTP_FROM_NAME', 'TWORIX');
// ─────────────────────────────────────────────

/**
 * 이메일 발송
 * @return bool 성공 여부
 */
function send_mail(string $to, string $subject, string $body): bool {
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
