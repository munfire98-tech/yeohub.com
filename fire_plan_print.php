<?php
// fire_plan_print.php — 소방계획서 인쇄 / PDF 저장
//   입력한 15개 법정항목(시행령 제27조 1항)을 하나의 문서로 조립합니다.
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';



/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /partner_center.php'); exit; }

require_once __DIR__ . '/fire_plan_overview_common.php';

$plan = fp_load_plan((string)($_GET['id'] ?? ''));
if (!$plan) { header('Location: /fire_plan.php'); exit; }
$planId  = (string)$plan['id'];
$overview=fpo_status($plan);$bundle=(array)($plan['source_bundle']['groups']??[]);
$printContext=[];foreach(['embed','modal'] as $q)if(($_GET[$q]??'')==='1')$printContext[$q]='1';
if(is_admin()&&is_string($_GET['uid']??null))$printContext['uid']=$_GET['uid'];
$backUrl='/fire_plan.php?'.http_build_query(['id'=>$planId]+$printContext);
$shareReadOnly=false;
define('FP_DOCUMENT_AUTHORIZED',true);
require __DIR__.'/fire_plan_document.php';
