<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');throw new RuntimeException('저장 요청만 가능합니다.');}
    $actor=mg_uid();$members=mg_members();
    if($actor===''||!mg_active($members[$actor]??[],'agency')){http_response_code(403);throw new RuntimeException('본인 매니저 계정으로 로그인해 주세요.');}
    if(!is_string($_POST['csrf']??null)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);throw new RuntimeException('화면을 새로고침한 뒤 다시 저장해 주세요.');}
    $name=is_string($_POST['name']??null)?trim($_POST['name']):'';
    if(!preg_match('//u',$name))throw new RuntimeException('올바른 이름을 입력해 주세요.');
    $name=trim((string)preg_replace('/(?:\s*매니저)+$/u','',$name));
    if($name===''||mb_strlen($name,'UTF-8')>30||preg_match('/[\x00-\x1F\x7F<>]/u',$name))throw new RuntimeException('이름은 특수 태그 없이 1~30자로 입력해 주세요.');
    mg_member_tx(function(array &$all)use($actor,$name){if(!mg_active($all[$actor]??[],'agency'))throw new RuntimeException('매니저 권한이 변경되었습니다.');$all[$actor]['nickname']=$name;});
    $_SESSION['nickname']=$name;
    echo json_encode(['ok'=>true,'name'=>$name],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()===false||http_response_code()<400)http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
