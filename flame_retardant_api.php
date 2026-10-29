<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
function frgp_reply(array $body,int $status=200):void {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
$admin=!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);
if(!$admin&&(empty($_SESSION['is_user'])||($_SESSION['role']??'')!=='building'))frgp_reply(['ok'=>false,'error'=>'로그인한 건물관리 화면에서 다시 열어 주세요.'],403);
if(!empty($_SESSION['_mge_help_review']))frgp_reply(['ok'=>false,'error'=>'작성 요청 창에서는 방염 확인 내용을 변경할 수 없습니다.'],403);
require_once __DIR__.'/fire_plan_share_common.php';
require_once __DIR__.'/flame_retardant_plan_common.php';
require_once __DIR__.'/manager_fire_plan_help.php';
$uid=fp_user_key();if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))frgp_reply(['ok'=>false,'error'=>'건물 계정을 확인해 주세요.'],403);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');frgp_reply(['ok'=>false,'error'=>'지원하지 않는 요청입니다.'],405);}
try{
 $year=filter_var($_GET['year']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1900,'max_range'=>2200]]);
 if($year===false||$year===null)throw new InvalidArgumentException('계획서 연도를 확인해 주세요.');
 $bi=bi_load();$dongs=frgp_dongs($bi);$buildingRevision=frgp_building_revision($bi);
 $current=fps_plans($uid)[$year]??null;
 if($method==='GET')frgp_reply(['ok'=>true,'year'=>$year,'plan_id'=>(string)($current['id']??''),'revision'=>fpo_hash($current??[]),'building_revision'=>$buildingRevision,'checks'=>frgp_checks((array)($current['sections']['6']['data']??[])),'dongs'=>$dongs,'csrf'=>(string)($_SESSION['csrf']??'')]);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)frgp_reply(['ok'=>false,'error'=>'요청 내용이 너무 큽니다.'],413);
 $raw=file_get_contents('php://input',false,null,0,16385);$input=json_decode($raw?:'',true);
 if(!is_array($input))throw new InvalidArgumentException('저장할 답변을 확인해 주세요.');
 $token=$_SESSION['csrf']??'';
 if(!is_string($token)||$token===''||!is_string($input['csrf']??null)||!hash_equals($token,$input['csrf']))frgp_reply(['ok'=>false,'error'=>'세션이 만료되었습니다. 방염 확인을 닫았다가 다시 열어 주세요.'],403);
 foreach(['plan_id','revision','building_revision','dong'] as $field)if(!is_string($input[$field]??null))throw new InvalidArgumentException('저장할 내용을 다시 불러와 주세요.');
 if(!hash_equals($buildingRevision,$input['building_revision']))throw new RuntimeException('건물정보가 변경되었습니다. 방염 확인을 닫았다가 다시 열어 주세요.');
 $dong=null;foreach($dongs as $candidate)if($candidate['id']===$input['dong'])$dong=$candidate;
 if(!$dong)throw new InvalidArgumentException('확인할 동을 다시 선택해 주세요.');
 if(!is_array($input['answers']??null))throw new InvalidArgumentException('확인한 답변을 선택해 주세요.');
 $answers=frgp_validate($input['answers']);$saved=null;
 $ok=mfp_save($uid,$input['plan_id'],'6',[],static function()use($uid,$year,$input,$dong,$answers,$bi,&$saved):bool{
  $plan=fps_plans($uid)[$year]??null;
  if((string)($plan['id']??'')!==$input['plan_id']||!hash_equals(fpo_hash($plan??[]),$input['revision']))throw new RuntimeException('다른 화면에서 계획서가 변경되었습니다. 방염 확인을 닫았다가 다시 열어 주세요.');
  if(!hash_equals(frgp_building_revision(bi_load_uid($uid,false)),$input['building_revision']))throw new RuntimeException('건물정보가 변경되었습니다. 다시 확인해 주세요.');
  if(!$plan){$id=fp_create_plan('business',$year);$plan=fp_load_plan($id);if(!$plan)throw new RuntimeException('계획서를 만들지 못했습니다. 다시 저장해 주세요.');$plan['building_name']=(string)($bi['name']??'');}
  $saved=frgp_apply($plan,$dong,$answers,date('Y-m-d H:i:s'));
  if(!fp_write_json(fp_plan_file((string)$saved['id']),$saved))return false;
  fp_touch_index((string)$saved['id'],$saved);return true;
 });
 if(!$ok||!$saved)throw new RuntimeException('확인 내용을 저장하지 못했습니다. 다시 시도해 주세요.');
 frgp_reply(['ok'=>true,'year'=>$year,'plan_id'=>(string)$saved['id'],'revision'=>fpo_hash($saved),'checks'=>frgp_checks($saved['sections']['6']['data']),'message'=>$year.'년 소방계획서 6번에 저장했습니다.']);
}catch(InvalidArgumentException $e){frgp_reply(['ok'=>false,'error'=>$e->getMessage()],400);}
catch(RuntimeException $e){frgp_reply(['ok'=>false,'error'=>$e->getMessage()],409);}
catch(Throwable $e){error_log('Flame guide save: '.get_class($e));frgp_reply(['ok'=>false,'error'=>'확인 내용을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'],500);}
