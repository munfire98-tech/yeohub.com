<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/pro_collaboration.php';
function bes_authorize(string $uid):array {
 $actor=mg_uid();$admin=!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);
 if(!$admin&&$actor==='')throw new RuntimeException('로그인 후 다시 열어 주세요.',401);
 if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))throw new RuntimeException('건물 정보를 확인해 주세요.',400);
 $members=mg_members();$member=$members[$uid]??[];$state=mg_read(mg_state_file());
 if(!mg_active($member,'building')||(!$admin&&$actor!==$uid&&!mg_can_view($actor,$uid,$members,$state)))throw new RuntimeException('이 건물의 시뮬레이션을 편집할 권한이 없습니다.',403);
 if(!$admin&&(!pc_active($uid)||mg_connection_manager($member,$members)===''||mg_link_status($uid,$member,$state)!=='accepted'))throw new RuntimeException('매니저 연결과 PRO 구독 후 이용할 수 있습니다.',403);
 return $member;
}
function bes_path(string $uid,array $member):string {
 return __DIR__.'/data/building_evac/'.hash('sha256',$uid.'|'.(string)($member['created']??'')).'.php';
}
function bes_validate_map(string $map):void {
 if($map===''||strlen($map)>2*1024*1024)throw new RuntimeException('도면은 비어 있지 않은 2MB 이하 자료여야 합니다.',400);
 $floors=[];$current=[];
 foreach(explode("\n",str_replace("\r",'',$map)) as $line){
  if(trim($line)==='')continue;
  if(preg_match('/^@B [0-7]$/D',$line)||preg_match('/^@S \d{1,3},\d{1,3} (auto|straight|dogleg) (auto|[+-][xy]) (auto|L|R)$/D',$line))continue;
  if(preg_match('/^=== (?:B?[0-9]+F?|[0-9]+층) ===$/D',$line)){if($current)$floors[]=$current;$current=[];continue;}
  if(strlen($line)>200||preg_match('/[^#.ESHD ]/',$line))throw new RuntimeException('도면 형식 또는 크기를 확인해 주세요.',400);
  $current[]=$line;if(count($current)>200)throw new RuntimeException('한 층의 세로 크기는 최대 200칸입니다.',400);
 }
 if($current)$floors[]=$current;
 if(!$floors||count($floors)>8)throw new RuntimeException('전체 층수는 1~8층으로 설정해 주세요.',400);
 foreach($floors as $floor)if(count($floor)<5||max(array_map('strlen',$floor))<5)throw new RuntimeException('각 층은 최소 5×5칸이어야 합니다.',400);
}
function bes_save(array &$store,array $input,string $actor):array {
 if(!is_string($input['map']??null)||!is_string($input['meta']??null))throw new RuntimeException('도면 정보를 확인해 주세요.',400);
 $map=$input['map'];bes_validate_map($map);
 $revision=$input['revision']??null;
 if(!is_string($revision)||!ctype_digit($revision)||(int)$revision!==(int)($store['revision']??0))throw new RuntimeException('다른 화면에서 도면이 변경되었습니다. 필요한 도면을 복사한 뒤 창을 다시 열어 주세요.',409);
 $meta=json_decode($input['meta'],true);if(!is_array($meta))throw new RuntimeException('시뮬레이션 설정을 확인해 주세요.',400);
 $scenario=[];
 foreach(['people'=>[1,300,60],'spread'=>[0.2,3,1],'speed'=>[0.5,2,1]] as $key=>$rule){$n=$meta[$key]??$rule[2];if(!is_numeric($n)||!is_finite((float)$n))throw new RuntimeException('시나리오 값을 확인해 주세요.',400);$scenario[$key]=max($rule[0],min($rule[1],(float)$n));}
 $routes=$meta['routes']??[];
 if(!is_array($routes)||count($routes)>30)throw new RuntimeException('안내 경로는 최대 30개입니다.',400);
 foreach($routes as $route){
  if(!is_array($route)||!is_string($route['name']??null)||mb_strlen($route['name'])>60||!is_int($route['floor']??null)||$route['floor']<0||$route['floor']>7||!is_array($route['points']??null)||count($route['points'])<2||count($route['points'])>400)throw new RuntimeException('안내 경로 형식을 확인해 주세요.',400);
  foreach($route['points'] as $point)if(!is_array($point)||count($point)!==2||!is_int($point[0]??null)||!is_int($point[1]??null)||min($point)<0||max($point)>199)throw new RuntimeException('안내 경로 좌표를 확인해 주세요.',400);
 }
 $labels=$meta['room_labels']??[];
 if(!is_array($labels)||count($labels)>192)throw new RuntimeException('방 이름 정보를 확인해 주세요.',400);
 foreach($labels as $l)if(!is_array($l)||!is_string($l['name']??null)||mb_strlen($l['name'])>60||!is_int($l['floor']??null)||$l['floor']<0||$l['floor']>7||!is_numeric($l['x']??null)||!is_numeric($l['y']??null)||$l['x']<0||$l['y']<0||$l['x']>200||$l['y']>200)throw new RuntimeException('방 이름 위치를 확인해 주세요.',400);
 $scenario['room_labels']=array_values($labels);
 $scenario['routes']=array_values($routes);
 $scenario['source_dong']=is_string($meta['source_dong']??null)?mb_substr($meta['source_dong'],0,100):'';
 $scenario['people']=(int)$scenario['people'];$mix=[];
 foreach(['adult','child','toddler','elderly'] as $key){$n=$meta['mix'][$key]??0;if(!is_numeric($n))throw new RuntimeException('재실자 구성을 확인해 주세요.',400);$mix[$key]=max(0,min(100,(float)$n));}
 $scenario['mix']=$mix;$scenario['grade']=in_array($meta['grade']??'', ['slow','medium','fast','ultra'],true)?$meta['grade']:'medium';
 $store=['map'=>$map,'scenario'=>$scenario,'revision'=>(int)($store['revision']??0)+1,'updated_at'=>date('c'),'updated_by'=>$actor];return $store;
}

/** Only structured basic information supplies floors; never infer length from area. */
function bes_building_options(array $info):array {
 $number=static function($v):?int {if(!is_scalar($v))return null;$v=trim((string)$v);return preg_match('/^\d+$/D',$v)?(int)$v:null;};
 $list=$info['bd_dong_list']??[];if(is_string($list))$list=json_decode($list,true);
 $options=[];$pick=trim((string)($info['bd_dong_pick']??''));
 foreach(is_array($list)?$list:[] as $index=>$row){
  if(!is_array($row))continue;$name=trim((string)($row['dong']??''))?:'동명 미상 '.($index+1);
  $above=$number($row['floor_a']??null);$below=$number($row['floor_b']??null);
  if($name===$pick){$above=$number($info['floor_a']??null)??$above;$below=$number($info['floor_b']??null)??$below;}
  $options[]=['id'=>'dong_'.substr(hash('sha256',$name.'|'.$index),0,20),'name'=>$name,'above'=>$above,'below'=>$below,'primary'=>$name===$pick];
 }
 if(!$options)$options[]=['id'=>'base','name'=>$pick?:((string)($info['name']??'')?:'내 건물'),'above'=>$number($info['floor_a']??null),'below'=>$number($info['floor_b']??null),'primary'=>true];
 return $options;
}
