<?php
declare(strict_types=1);
// Public read-only entry point. Deliberately no session, editing guard, user-key or login routing.
date_default_timezone_set('Asia/Seoul');
require_once __DIR__.'/fire_plan_share_common.php';
fps_headers();header('Content-Type: text/html; charset=utf-8');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit('읽기 전용 문서입니다.');}
try{$plan=fps_public_plan(is_string($_GET['t']??null)?$_GET['t']:'');}
catch(Throwable $e){error_log('Shared plan read failed: '.get_class($e));http_response_code(503);exit('계획서를 읽지 못했습니다. 잠시 후 다시 열어 주세요.');}
if(!$plan){http_response_code(404);?><!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>소방계획서 공유</title><body style="margin:0;padding:24px;background:#f4f7fc;font-family:system-ui;color:#233450"><main style="max-width:420px;margin:15vh auto;padding:30px;border:1px solid #dce5f2;border-radius:20px;background:white"><p style="color:#2563eb;font-weight:700">소방계획서.com</p><h1 style="font-size:24px">공유된 계획서를 열 수 없어요</h1><p style="line-height:1.8">공유가 중단되었거나 저장된 계획서가 없습니다.<br>보내주신 분에게 링크를 다시 요청해 주세요.</p></main></body></html><?php exit;}
$shareReadOnly=true;$backUrl='';$planId=(string)$plan['id'];
define('FP_DOCUMENT_AUTHORIZED',true);
require __DIR__.'/fire_plan_document.php';
