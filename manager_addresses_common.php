<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
function ma_owned(array $r,string $actor,array $members):bool {
 return mg_active($members[$actor]??[],'agency')&&($r['manager']??'')===$actor&&($r['manager_created']??'')===(string)($members[$actor]['created']??'')&&empty($r['deleted_at']);
}
function ma_linked(array $r,string $actor,array $members,array $state):bool {
 $uid=(string)($r['linked_uid']??'');return $uid!==''&&mg_can_view($actor,$uid,$members,$state)&&($r['linked_key']??'')===mg_link_key($uid,$members[$uid]);
}
function ma_available(string $actor,array $members,array $state):array {
 $rows=[];foreach($state['address_book']??[] as $id=>$r)if(is_array($r)&&ma_owned($r,$actor,$members)&&!ma_linked($r,$actor,$members,$state))$rows[$id]=$r;return $rows;
}
/** An unopened/blank form is a session ticket, not a saved address-book entry. */
function ma_begin(string $actor,array $members):string {
 if(!mg_active($members[$actor]??[],'agency'))throw new RuntimeException('매니저 계정으로 로그인해 주세요.');
 foreach($_SESSION['manager_draft_tickets']??[] as $id=>$ticket)if(($ticket['expires']??0)<time())unset($_SESSION['manager_draft_tickets'][$id]);
 if(count($_SESSION['manager_draft_tickets']??[])>=100)array_shift($_SESSION['manager_draft_tickets']);
 $id=bin2hex(random_bytes(12));
 $_SESSION['manager_draft_tickets'][$id]=['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'expires'=>time()+86400,'created_at'=>date('c'),'name'=>'','address'=>'','jibun'=>'','address_key'=>'draft_'.$id,'codes'=>[],'basic_info'=>[]];
 return $id;
}
function ma_ticket(string $actor,string $id,array $members):array {
 $ticket=$_SESSION['manager_draft_tickets'][$id]??[];
 return ($ticket['expires']??0)>=time()&&ma_owned($ticket,$actor,$members)?$ticket:[];
}
function ma_save(string $actor,array $address):string {
 return mg_member_tx(function(array &$members)use($actor,$address){
  if(!mg_active($members[$actor]??[],'agency'))throw new RuntimeException('매니저 계정으로 로그인해 주세요.');
  return mg_state_tx(function(array &$s)use($actor,$address,$members){
   $count=0;foreach($s['address_book']??[] as $id=>$r){if(!ma_owned($r,$actor,$members))continue;$count++;if(($r['address_key']??'')===$address['address_key'])return (string)$id;}
   if($count>=200)throw new RuntimeException('사전 등록은 최대 200개까지 가능합니다.');
   $id=bin2hex(random_bytes(12));$s['address_book'][$id]=$address+['id'=>$id,'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'created_at'=>date('c')];return $id;
  });
 });
}
function ma_accept(string $actor,string $uid,string $requestKey,string $addressId):void {
 mg_member_tx(function(array &$members)use($actor,$uid,$requestKey,$addressId){
  $m=$members[$uid]??[];
  if(!mg_active($members[$actor]??[],'agency')||!mg_active($m,'building')||mg_connection_manager($m,$members)!==$actor||$requestKey===''||!hash_equals(mg_link_key($uid,$m),$requestKey))throw new RuntimeException('연결 요청이 변경되었습니다. 새로고침해 주세요.');
  mg_state_tx(function(array &$s)use($actor,$uid,$requestKey,$addressId,$members,$m){
   if(mg_link_status($uid,$m,$s)!=='pending')throw new RuntimeException('이미 처리된 연결 요청입니다.');
   if($addressId!==''){
    $r=$s['address_book'][$addressId]??[];
    if(!ma_owned($r,$actor,$members)||ma_linked($r,$actor,$members,$s))throw new RuntimeException('선택한 주소를 사용할 수 없습니다. 새로고침해 주세요.');
    if(trim((string)($r['address']??''))==='')throw new RuntimeException('사전 등록 기본정보에서 주소를 먼저 입력해 주세요.');
    if(!empty($r['basic_info'])&&is_array($r['basic_info'])){
      $handoffKey=$uid.':'.hash('sha256',(string)($m['created']??''));
      $s['draft_handoffs'][$handoffKey]=['id'=>$addressId,'info'=>$r['basic_info'],'manager'=>$actor,'manager_created'=>(string)($members[$actor]['created']??''),'request_key'=>$requestKey,'at'=>date('c')];
    }
    $s['address_book'][$addressId]['linked_uid']=$uid;$s['address_book'][$addressId]['linked_key']=$requestKey;$s['address_book'][$addressId]['linked_at']=date('c');
   }
   $s['links'][$requestKey]=['status'=>'accepted','at'=>date('c'),'actor'=>$actor];
  });
 });
}
function ma_map_rows(string $actor,array $members,array $state):array {
 $out=[];foreach(ma_available($actor,$members,$state) as $id=>$r){
  $d=(array)($r['basic_info']??[]);$lat=is_numeric($d['bd_lat']??null)?(float)$d['bd_lat']:null;$lng=is_numeric($d['bd_lng']??null)?(float)$d['bd_lng']:null;
  if($lat===null||$lng===null||!is_finite($lat)||!is_finite($lng)||abs($lat)>90||abs($lng)>180||($lat==0&&$lng==0)){$lat=null;$lng=null;}
  $date='';$raw=(string)($d['bd_use_apr']??'');if(preg_match('/^(\d{4})[-.\/]?(\d{2})[-.\/]?(\d{2})$/D',$raw,$parts)&&checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))$date=$parts[1].'-'.$parts[2].'-'.$parts[3];
  $out[]=['uid'=>'pre_'.$id,'preregistered'=>true,'address_id'=>$id,'name'=>$r['name']?:($r['address']?:'작성 중인 거래처'),'address'=>$r['address'],'lat'=>$lat,'lng'=>$lng,'approval_date'=>$date,'approval_month'=>$date!==''?(int)substr($date,5,2):null,'representative'=>(string)($d['rep']??''),'building_tel'=>(string)($d['tel']??''),'safety_managers'=>(array)($d['mgrs']??[]),'subscription'=>['active'=>false]];
 }return $out;
}
