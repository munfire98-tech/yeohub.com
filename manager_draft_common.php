<?php
declare(strict_types=1);
require_once __DIR__.'/manager_addresses_common.php';
require_once __DIR__.'/building_info.php';
function md_entry(string $actor,string $id):array {
 $members=mg_members();$state=mg_read(mg_state_file());$row=$state['address_book'][$id]??ma_ticket($actor,$id,$members);
 if(!preg_match('/^[a-f0-9]{24}$/D',$id)||!ma_owned($row,$actor,$members))throw new RuntimeException('사전 등록 정보를 확인할 수 없습니다.');
 if(ma_linked($row,$actor,$members,$state))throw new RuntimeException('이미 유저에게 연결되었습니다. 담당 유저 화면에서 기본정보를 수정해 주세요.');
 return $row;
}
function md_load():array {
 $row=md_entry(MD_ACTOR,MD_ID);$data=(array)($row['basic_info']??[]);
 if(!$data)$data=['name'=>$row['name']??'','address'=>$row['address']??''];
 return array_merge(bi_blank(),$data);
}
function md_save(array $data):bool {
 $out=md_normalize($data);
 return mg_member_tx(function(array &$members)use($out){return mg_state_tx(function(array &$s)use($members,$out){
  $exists=isset($s['address_book'][MD_ID]);
  $row=$s['address_book'][MD_ID]??ma_ticket(MD_ACTOR,MD_ID,$members);
  if(!ma_owned($row,MD_ACTOR,$members)||ma_linked($row,MD_ACTOR,$members,$s))throw new RuntimeException('연결 상태가 변경되어 저장을 중단했습니다.');
  if(!$exists){
   $hasContent=false;
   $nonempty=function($v)use(&$nonempty):bool{if(is_array($v)){foreach($v as $x)if($nonempty($x))return true;return false;}return trim((string)$v)!=='';};
   foreach($out as $field=>$value)if($field!=='updated'&&$nonempty($value)){$hasContent=true;break;}
   if(!$hasContent)return true;
   $count=0;foreach($s['address_book']??[] as $entry)if(ma_owned($entry,MD_ACTOR,$members))$count++;
   if($count>=200)throw new RuntimeException('사전 등록은 최대 200개까지 가능합니다.');
   unset($row['expires']);$s['address_book'][MD_ID]=$row;
  }
  $s['address_book'][MD_ID]['basic_info']=$out;
  $s['address_book'][MD_ID]['name']=$out['name'];$s['address_book'][MD_ID]['address']=$out['address'];
  $s['address_book'][MD_ID]['updated_at']=date('c');return true;
 });});
}
function md_progress():array {
 $d=md_load();$missing=[];$n=0;foreach(bi_required_fields() as $key=>$label){if(trim((string)($d[$key]??''))!=='')$n++;else $missing[]=$label;}
 $manager=false;foreach($d['mgrs']??[] as $m)if(trim((string)($m['name']??''))!=='')$manager=true;
 if($manager)$n++;else $missing[]='소방안전관리자';$total=count(bi_required_fields())+1;
 return ['filled'=>$n,'total'=>$total,'percent'=>(int)round($n/$total*100),'missing'=>$missing];
}

function md_normalize(array $d):array {
  $base = bi_blank();
  $out  = [];
  foreach ($base as $k => $v) {
    if ($k === 'mgrs') continue;
    $out[$k] = is_string($v) ? trim((string)($d[$k] ?? '')) : ($d[$k] ?? $v);
  }
  // 등급 검증
  if (!in_array($out['grade'], ['특급','1급','2급','3급'], true)) $out['grade'] = '';

  // 소방안전관리자 최대 4명
  $mgrs = [];
  $src = $d['mgrs'] ?? [];
  if (is_array($src)) {
    foreach (array_slice($src, 0, 4) as $m) {
      $nm  = trim((string)($m['name'] ?? ''));
      $tel = trim((string)($m['tel']  ?? ''));
      if ($nm === '' && $tel === '') continue;
      $ty = (string)($m['type'] ?? '');
      $mgrs[] = [
        'name' => $nm,
        'appt' => trim((string)($m['appt'] ?? '')),
        'qual' => trim((string)($m['qual'] ?? '')),
        'type' => in_array($ty, ['주','보조'], true) ? $ty : '',
        'tel'  => $tel,
      ];
    }
  }
  $out['mgrs']    = $mgrs;
  $out['updated'] = date('Y-m-d H:i:s');

  return $out;
}
