<?php
declare(strict_types=1);
function bed_schema():array{
 $int=['type'=>'integer'];$str=['type'=>'string'];
 $obj=static fn($props)=>['type'=>'object','properties'=>$props,'required'=>array_keys($props),'additionalProperties'=>false];
 return $obj(['rooms'=>['type'=>'array','items'=>$obj(['name'=>$str,'x'=>$int,'y'=>$int,'w'=>$int,'h'=>$int,'kind'=>['type'=>'string','enum'=>['room','stair']]])], 'doors'=>['type'=>'array','items'=>$obj(['x'=>$int,'y'=>$int,'kind'=>['type'=>'string','enum'=>['door','exit']]])], 'questions'=>['type'=>'array','items'=>$str]]);
}
function bed_validate(array $a,int $w,int $h):array{
 foreach(['rooms','doors','questions'] as $k)if(!isset($a[$k])||!is_array($a[$k]))throw new RuntimeException('초안 형식을 확인하지 못했습니다. 설명을 조금 더 구체적으로 적어 주세요.',400);
 if(count($a['rooms'])>24||count($a['doors'])>48||count($a['questions'])>12)throw new RuntimeException('한 번에 방 24개 이하로 설명해 주세요.',400);
 foreach($a['rooms'] as $i=>$r){
  foreach(['x','y','w','h'] as $k)if(!is_int($r[$k]??null))throw new RuntimeException('초안 좌표를 확인하지 못했습니다.',400);
  if(!is_string($r['name']??null)||mb_strlen($r['name'])>60||!in_array($r['kind']??'', ['room','stair'],true)||$r['w']<3||$r['h']<3||$r['x']<1||$r['y']<1||$r['x']+$r['w']>$w-1||$r['y']+$r['h']>$h-1)throw new RuntimeException('방이 바닥 범위를 벗어났습니다. 크기나 방 개수를 조정해 주세요.',400);
  foreach(array_slice($a['rooms'],0,$i) as $b)if($r['x']<$b['x']+$b['w']&&$b['x']<$r['x']+$r['w']&&$r['y']<$b['y']+$b['h']&&$b['y']<$r['y']+$r['h'])throw new RuntimeException('방이 겹친 초안입니다. 복도와 방 위치를 더 구체적으로 적어 주세요.',400);
 }
 foreach($a['doors'] as $d)if(!is_int($d['x']??null)||!is_int($d['y']??null)||$d['x']<0||$d['y']<0||$d['x']>=$w||$d['y']>=$h||!in_array($d['kind']??'', ['door','exit'],true))throw new RuntimeException('문 위치를 확인하지 못했습니다.',400);
 foreach($a['doors'] as $d){
  $outer=$d['x']===0||$d['y']===0||$d['x']===$w-1||$d['y']===$h-1;
  if($d['kind']==='exit'&&!$outer)throw new RuntimeException('출구가 외벽에 있지 않습니다. 실제 출구 방향을 설명해 주세요.',400);
  if($d['kind']==='door'){
   $wall=false;foreach($a['rooms'] as $r)if($d['x']>=$r['x']&&$d['x']<$r['x']+$r['w']&&$d['y']>=$r['y']&&$d['y']<$r['y']+$r['h']&&($d['x']===$r['x']||$d['x']===$r['x']+$r['w']-1||$d['y']===$r['y']||$d['y']===$r['y']+$r['h']-1))$wall=true;
   if(!$wall)throw new RuntimeException('문이 방의 벽에 연결되지 않았습니다. 문 위치를 더 구체적으로 설명해 주세요.',400);
  }
 }
 foreach($a['questions'] as $q)if(!is_string($q)||mb_strlen($q)>250)throw new RuntimeException('확인 사항 형식을 확인하지 못했습니다.',400);
 return $a;
}
