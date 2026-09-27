<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
function bsa_enforce(): void {
 $page=basename((string)($_SERVER['SCRIPT_FILENAME']??''));
 $basic=['building_setup.php','building_setup_chat.php','building_facilities.php','building_asset_view.php'];
 $paid=['work_log.php','work_log_form.php','work_log_print.php','work_log_setup_chat.php','jawi.php','jawi_edit.php','jawi_chat.php','jawi_print.php','train.php','train_edit.php','train_chat.php','train_print.php','train_photo.php','evacuation_plan.php','evacuation_plan_chat.php','evac_view.php','evac_assign_api.php','fire_plan.php','fire_plan_new.php','fire_plan_edit.php','fire_plan_chat.php','fire_plan_jawi.php','fire_plan_print.php','print_all.php','safety_ai_api.php'];
 if(!in_array($page,array_merge($basic,$paid),true))return;
 if(empty($_SESSION['is_user'])||($_SESSION['role']??'')!=='building')return;
 if(empty($_SESSION['_mge_actor'])&&(!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK'])))return;
 $uid=(string)($_SESSION['member_id']??'');if($uid===''&&!empty($_SESSION['kakao_id']))$uid='kakao_'.$_SESSION['kakao_id'];
 $connected=false;
 try{$members=mg_members();$me=$members[$uid]??[];$connected=mg_active($me,'building')&&mg_connection_manager($me,$members)!==''&&mg_link_status($uid,$me,mg_read(mg_state_file()))==='accepted';$active=pc_active($uid);}catch(Throwable $e){$active=false;}
 if($connected&&(in_array($page,$basic,true)||$active))return;
 $message=$connected?'이 업무는 PRO 구독 후 이용할 수 있습니다. 기본정보와 소방시설 현황은 구독 전에도 작성·수정할 수 있습니다.':'매니저 연결이 완료된 후 이용해 주세요.';
 header('Cache-Control: no-store');http_response_code(403);
 if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)||str_contains($page,'_api.php')||str_contains(strtolower($_SERVER['HTTP_ACCEPT']??''),'application/json')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
 header('Content-Type: text/html; charset=utf-8');
 echo '<!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>서비스 이용 안내</title><body style="font-family:system-ui;background:#f4f8fa;color:#294353;padding:24px"><main style="max-width:480px;margin:10vh auto;background:white;border-radius:18px;padding:28px"><h2>'.($connected?'PRO 이용 안내':'매니저 연결 안내').'</h2><p style="line-height:1.8">'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><a href="/building_manager.php" target="_top">건물관리 화면으로 돌아가기</a></main></body></html>';exit;
}
