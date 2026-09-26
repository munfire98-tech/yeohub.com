<?php
declare(strict_types=1);
// Uses the normal login session, never the isolated editing session.
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
require_once __DIR__.'/manager_plan_request_cleanup.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function mh_fail(string $message,int $code=400): void {http_response_code($code);echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
$actor=mg_uid();
if($actor==='') mh_fail('로그인 후 이용해 주세요.',401);
if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
$csrf=(string)$_SESSION['csrf'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)) mh_fail('허용되지 않은 요청입니다.',405);
if($method==='POST'&&!hash_equals($csrf,(string)($_POST['csrf']??''))) mh_fail('새로고침 후 다시 시도해 주세요.',403);
$action=$method==='POST'?(string)($_POST['action']??''):'';
if($method==='POST'&&!in_array($action,['create','resolve'],true)) mh_fail('지원하지 않는 작업입니다.');
try {
    // Connect only after explicit consent. Existing pending connections are retained.
    if($action==='create') {
        if(!pc_active($actor))mh_fail('작성 도움 요청은 PRO 구독 후 이용할 수 있습니다.',403);
        $text=trim((string)($_POST['text']??''));$field=trim((string)($_POST['field']??''));
        if($text===''||strlen($text)>3000||!preg_match('/^[A-Za-z0-9_]{1,80}$/D',$field)) mh_fail('요청 항목을 확인해 주세요.');
        if(strpos($field,'__fp_')===0){
          if(!preg_match('/^__fp_([0-9]+)_([0-9]+)_([A-Za-z0-9_]+)$/D',$field,$fm))mh_fail('소방계획서 질문을 확인해 주세요.');
          require_once __DIR__.'/fire_plan_db.php';
          if(!fp_load_plan($fm[1])||!isset(fp_chat_schema()[$fm[2]][$fm[3]]))mh_fail('현재 계정의 소방계획서 질문을 확인해 주세요.');
        }
        $members=mg_members();$me=$members[$actor]??[];$state=mg_read(mg_state_file());
        if(!mg_active($me,'building')) mh_fail('건물관리자만 요청할 수 있습니다.',403);
        $manager=mg_connection_manager($me,$members);$status=mg_link_status($actor,$me,$state);
        if($manager===''||!in_array($status,['accepted','pending'],true)) {
            if(($_POST['consent']??'')!=='1') mh_fail('로컬매니저 연결과 정보 공유에 동의해 주세요.');
            mg_request_manager($actor,'FM-61A8AD50E2',true);
        }
    }
    $result=mg_member_tx(function(array &$members)use($actor,$action,$csrf){
      return mg_state_tx(function(array &$state)use(&$members,$actor,$action,$csrf){
        $me=$members[$actor]??[];
        $isManager=mg_active($me,'agency');$isUser=mg_active($me,'building');
        if(!$isManager&&!$isUser) throw new RuntimeException('이 계정에서는 사용할 수 없습니다.');
        $target=$isUser?$actor:trim((string)($_GET['uid']??$_POST['uid']??''));
        if($target!==''&&$isManager&&!mg_can_view($actor,$target,$members,$state)) throw new RuntimeException('수락된 담당 유저만 확인할 수 있습니다.');
        $manager=$isUser?mg_connection_manager($me,$members):$actor;
        $status=$isUser?mg_link_status($actor,$me,$state):'accepted';
        $mode=$isUser?($manager!==''&&in_array($status,['pending','accepted'],true)?$status:'local'):'manager';
        return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$state,$me,$actor,$action,$csrf,$target,$manager,$mode,$isManager,$isUser){
          $visible=static function(array $r)use($members,$state,$actor,$target,$isManager):bool{
            $u=(string)($r['uid']??'');$m=$members[$u]??[];
            if(($r['user_created']??null)!==($m['created']??null))return false;
            if(!$isManager)return $u===$actor;
            return ($target===''||$target===$u)&&mg_can_view($actor,$u,$members,$state)
              &&($r['manager']??'')===$actor&&($r['manager_created']??null)===($members[$actor]['created']??null)
              &&($r['link_key']??'')===mg_link_key($u,$m);
          };
          if($action==='create'){
            if(!$isUser||!in_array($mode,['accepted','pending'],true))throw new RuntimeException('매니저 연결 상태를 확인해 주세요.');
            $text=trim((string)($_POST['text']??''));$field=trim((string)($_POST['field']??''));
            if($text===''||strlen($text)>3000||!preg_match('/^[A-Za-z0-9_]{1,80}$/D',$field))throw new RuntimeException('요청 항목을 확인해 주세요.');
            // Recheck while holding the same request-store lock used by plan deletion.
            if(strpos($field,'__fp_')===0){
              if(!preg_match('/^__fp_([0-9]+)_([0-9]+)_([A-Za-z0-9_]+)$/D',$field,$fm)||!fp_load_plan($fm[1])||!isset(fp_chat_schema()[$fm[2]][$fm[3]]))throw new RuntimeException('삭제되었거나 변경된 소방계획서입니다. 목록에서 다시 열어 주세요.');
            }
            $key=mg_link_key($actor,$me);$duplicate=false;$open=0;
            foreach($rows as $r){if(($r['uid']??'')!==$actor||($r['status']??'')!=='pending')continue;$open++;
              if(($r['link_key']??'')===$key&&($r['field']??'')===$field)$duplicate=true;}
            if(!$duplicate){
              if($open>=100)throw new RuntimeException('미해결 요청이 많습니다. 기존 요청부터 확인해 주세요.');
              $id=bin2hex(random_bytes(16));$rows[$id]=['id'=>$id,'uid'=>$actor,'user_created'=>$me['created']??'',
                'manager'=>$manager,'manager_created'=>$members[$manager]['created']??'','link_key'=>$key,'field'=>$field,'text'=>$text,
                'status'=>'pending','created_at'=>date('c'),'resolved_at'=>null,'reply'=>''];
            }
          }
          if($action==='resolve'){
            $id=(string)($_POST['id']??'');
            if(!$isManager||!isset($rows[$id])||!$visible($rows[$id]))throw new RuntimeException('처리할 권한이 없습니다.');
            if(strpos((string)($rows[$id]['field']??''),'__fp_')===0)throw new RuntimeException('소방계획서 문답에서 답변을 저장해 주세요.');
            if(($rows[$id]['field']??'')==='__facilities')throw new RuntimeException('소방시설 현황 화면에서 설치 여부를 확인하고 저장해 주세요.');
            $reply=trim((string)($_POST['reply']??''));
            if($reply===''||strlen($reply)>3000)throw new RuntimeException('처리 내용을 1~1,000자 정도로 입력해 주세요.');
            if($rows[$id]['status']==='pending'){$rows[$id]['status']='resolved';$rows[$id]['reply']=$reply;$rows[$id]['resolved_at']=date('c');$rows[$id]['resolved_by']=$actor;}
          }
          mh_prune_deleted_plans($rows,$visible,__DIR__.'/data/fireplan');
          $list=[];$buildingNames=[];
          foreach($rows as $r)if($visible($r)){$r['name']=(string)($members[$r['uid']]['nickname']??$r['uid']);
            if(!array_key_exists($r['uid'],$buildingNames)){
              $bi=mg_read(__DIR__.'/data/building/'.$r['uid'].'/info.json');
              $buildingNames[$r['uid']]=trim((string)($bi['name']??''));
            }
            $r['building_name']=$buildingNames[$r['uid']]?:($r['name'].'님의 건물');
            $r['connection_active']=($r['link_key']??'')===mg_link_key($r['uid'],$members[$r['uid']])&&in_array(mg_link_status($r['uid'],$members[$r['uid']],$state),['pending','accepted'],true);$list[]=$r;}
          usort($list,static function($a,$b){return (($a['status']==='resolved')<=>($b['status']==='resolved'))?:strcmp($b['created_at'],$a['created_at']);});
          return ['ok'=>true,'csrf'=>$csrf,'mode'=>$mode,'pro_active'=>pc_active($target),'rows'=>$list,'manager_name'=>$members[$manager]['nickname']??''];
        },true);
      });
    });
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_THROW_ON_ERROR);
}catch(Throwable $e){error_log('Manager help: '.$e->getMessage());mh_fail($e instanceof RuntimeException?$e->getMessage():'요청을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.');}
