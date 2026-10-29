<?php
declare(strict_types=1);
/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
// Old open editors must not overwrite the new collection with the old single-plan format.
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    http_response_code(409);header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'피난계획 작성 화면이 새로워졌습니다. 입력 내용을 복사해 두고 피난계획 카드를 다시 열어 주세요.'],JSON_UNESCAPED_UNICODE);exit;
}
$query=[];foreach(['uid','modal','embed','print','id','return']as$key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=$_GET[$key];
header('Location: /evacuation_plan.php'.($query?'?'.http_build_query($query):''));exit;
