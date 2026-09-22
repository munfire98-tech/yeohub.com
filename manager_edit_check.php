<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
try {
    $actor=mg_uid();$members=mg_members();
    if($actor===''||!mg_active($members[$actor]??[],'agency')){
        http_response_code(403);exit('매니저 계정으로 로그인한 후 다시 열어 주세요.');
    }
    $state=mg_read(mg_state_file());$linked=false;
    foreach($members as $uid=>$member){if(is_array($member)&&mg_can_view($actor,(string)$uid,$members,$state)){$linked=true;break;}}
    if(!$linked){http_response_code(403);exit('수락된 담당 유저가 있는 매니저만 확인할 수 있습니다.');}
    $effective=(string)ini_get('auto_prepend_file');
    $local='(파일 없음)';$file=__DIR__.'/.user.ini';
    if(is_file($file)){
        $raw=file_get_contents($file);
        $parsed=$raw===false?false:parse_ini_string($raw,false,INI_SCANNER_RAW);
        $local=$parsed===false?'(설정 읽기 실패)':(string)($parsed['auto_prepend_file']??'(항목 없음)');
    }
    echo "매니저 편집 진단 — 설정 변경 없음\n";
    echo 'PHP 실행 방식: '.PHP_SAPI."\n";
    echo '서버 자동 실행 설정: '.($effective===''?'(비어 있음)':$effective)."\n";
    echo '현재 폴더 자동 실행 설정: '.$local."\n";
    echo '편집 보호 실행 여부: '.(defined('MG_EDIT_GUARD_READY')?'실행됨':'실행되지 않음')."\n";
    echo '설정 파일명: '.((string)ini_get('user_ini.filename')?:'(사용 안 함)')."\n";
    echo '설정 갱신 간격(초): '.(string)ini_get('user_ini.cache_ttl')."\n";
    echo '사이트 폴더 쓰기 가능: '.(is_writable(__DIR__)?'예':'아니오')."\n";
    echo "\n이 결과만 전달해 주세요. 확인 후 manager_edit_check.php는 삭제해도 됩니다.\n";
}catch(Throwable $e){http_response_code(503);echo '진단 정보를 읽지 못했습니다. 기존 매니저 파일과 같은 폴더에 업로드했는지 확인해 주세요.';}
