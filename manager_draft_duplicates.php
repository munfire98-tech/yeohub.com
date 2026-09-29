<?php
function mdd_key(string $s):string {
 $s=preg_replace('/\([^)]*\)/u','',trim($s));
 $aliases=['경기'=>'경기도','서울'=>'서울특별시','인천'=>'인천광역시','부산'=>'부산광역시','대구'=>'대구광역시','광주'=>'광주광역시','대전'=>'대전광역시','울산'=>'울산광역시','세종'=>'세종특별자치시','충북'=>'충청북도','충남'=>'충청남도','전북'=>'전북특별자치도','전라북도'=>'전북특별자치도','전남'=>'전라남도','경북'=>'경상북도','경남'=>'경상남도','강원'=>'강원특별자치도','강원도'=>'강원특별자치도','제주'=>'제주특별자치도'];
 $parts=preg_split('/\s+/u',$s);$parts[0]=$aliases[$parts[0]]??$parts[0];return mb_strtolower(preg_replace('/\s+/u','',implode(' ',$parts)));
}
class MdDuplicateException extends RuntimeException {
 public array $matches;
 public function __construct(array $matches){parent::__construct('이미 등록한 건물이 있습니다.');$this->matches=$matches;}
}
function mdd_matches(array $state,array $members,string $actor,string $id,array $info):array {
 $key=mdd_key((string)($info['address']??''));if($key==='')return [];$found=[];
 foreach($state['address_book']??[] as $otherId=>$r){
  if((string)$otherId===$id||!is_array($r)||!ma_owned($r,$actor,$members))continue;
  $d=(array)($r['basic_info']??[]);
  if(mdd_key((string)($d['address']??$r['address']??''))!==$key)continue;
  $linked=ma_linked($r,$actor,$members,$state);
  $found[]=['id'=>(string)$otherId,'name'=>(string)($r['name']??$d['name']??'등록된 건물'),'address'=>(string)($r['address']??$d['address']??''),'dong'=>(string)($d['bd_dong_pick']??''),'area'=>(string)($r['registration_area']??''),'url'=>$linked?'/manager_view.php?uid='.rawurlencode((string)$r['linked_uid']):'/manager_addresses.php?id='.rawurlencode((string)$otherId)];
 }
 return $found;
}
