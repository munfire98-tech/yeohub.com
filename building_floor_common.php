<?php
declare(strict_types=1);
require_once __DIR__.'/building_dong_names.php';
/** Registry rows remain distinct; area is never estimated from total area/floor count. */
function bfl_text($v):string {return is_scalar($v)?trim((string)$v):'';}
function bfl_array($v):array {if(is_string($v))$v=json_decode($v,true);return is_array($v)?$v:[];}
function bfl_decimal($v):string {
 $s=bfl_text($v);if(!preg_match('/^\d{1,10}(?:\.\d{1,6})?$/D',$s))return '';
 return str_contains($s,'.')?rtrim(rtrim($s,'0'),'.'):$s;
}
function bfl_location(array $r):array {
 $out=[];foreach(['sigunguCd'=>5,'bjdongCd'=>5,'platGbCd'=>1,'bun'=>4,'ji'=>4] as $k=>$len){$v=bfl_text($r[$k]??'');$out[$k]=ctype_digit($v)?str_pad($v,$len,'0',STR_PAD_LEFT):'';}return $out;
}
function bfl_ref(array $row,array $code):array {return ['pk'=>bfl_text($row['mgmBldrgstPk']??''),'dong'=>bfl_text($row['dongNm']??''),'attach'=>bfl_text($row['mainAtchGbCd']??''),'location'=>bfl_location($code)];}
function bfl_key(array $r):string {
 $n=bfl_text($r['flrNo']??'');$kind=bfl_text($r['flrGbCd']??'');
 if(ctype_digit($n)&&(int)$n>0&&in_array($kind,['10','20'],true))return $kind==='10'?'B'.(int)$n:(int)$n.'F';
 return 'X'.substr(hash('sha256',$kind.'|'.$n.'|'.bfl_text($r['flrNoNm']??'')),0,16);
}
function bfl_label(string $key):string {return str_starts_with($key,'B')?'지하 '.substr($key,1).'층':'지상 '.rtrim($key,'F').'층';}
function bfl_rows(array $raw):array {
 $rows=[];
 foreach($raw as $r){
  if(!is_array($r))continue;$key=bfl_key($r);$excluded=bfl_text($r['areaExctYn']??'');
  $rows[]=['key'=>$key,'label'=>str_starts_with($key,'X')?(bfl_text($r['flrNoNm']??'')?:bfl_text($r['flrGbCdNm']??'')?:'층명 미상'):bfl_label($key),
   'area'=>bfl_decimal($r['area']??''),'use'=>bfl_text($r['etcPurps']??'')?:bfl_text($r['mainPurpsCdNm']??''),'structure'=>bfl_text($r['strctCdNm']??'')?:bfl_text($r['etcStrct']??''),
   'excluded'=>in_array(strtoupper($excluded),['Y','1'],true),'exclusion_known'=>in_array(strtoupper($excluded),['Y','N','1','0'],true),'source'=>'registry'];
 }
 return $rows;
}
/** Match by registry PK AND exact parcel. Name/order alone is never sufficient. */
function bfl_match(array $titles,array $code,array $raw):array {
 $out=[];$loc=bfl_location($code);$counts=[];foreach($titles as $t){$pk=bfl_text($t['mgmBldrgstPk']??'');$counts[$pk]=($counts[$pk]??0)+1;}
 foreach($titles as $i=>$title){$ref=bfl_ref($title,$code);$matched=[];
  if($ref['pk']!==''&&$counts[$ref['pk']]===1)foreach($raw as $r){
   if(bfl_text($r['mgmBldrgstPk']??'')!==$ref['pk']||bfl_location($r)!==$loc)continue;
   if($ref['dong']!==''&&bfl_text($r['dongNm']??'')!==$ref['dong'])continue;
   if($ref['attach']!==''&&bfl_text($r['mainAtchGbCd']??'')!==$ref['attach'])continue;
   $matched[]=$r;
  }
  $out[$i]=['status'=>$matched?'ready':($ref['pk']===''||$counts[$ref['pk']]!==1?'unmatched':'empty'),'checked_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s'),'rows'=>bfl_rows($matched)];
 }return $out;
}
/** Optional enrichment: bounded pages, no silent partial sums and no credential logging. */
function bfl_fetch(array $api,array $code,?callable $fetch=null):array {
 require_once __DIR__.'/building_registry_http.php';
 $loc=bfl_location($code);if(in_array('',$loc,true))return ['status'=>'unavailable','rows'=>[]];
 $fetch=$fetch??static fn($url)=>br_http_get($url);$rows=[];$seen=[];$total=null;$started=microtime(true);
 for($page=1;$page<=30;$page++){
  if(microtime(true)-$started>12)return ['status'=>'unavailable','rows'=>[]];
  $url=rtrim((string)$api['hub_base'],'/').'/getBrFlrOulnInfo?'.http_build_query(['serviceKey'=>urldecode((string)$api['hub'])]+$loc+['numOfRows'=>100,'pageNo'=>$page,'_type'=>'json']);
  $r=$fetch($url);$state=br_response_state($r);if(!$state['ok']||!empty($r['lookup_error']))return ['status'=>'unavailable','rows'=>[]];
  $j=json_decode((string)$r['body'],true);$b=$j['response']['body']??[];
  if(in_array((string)($j['response']['header']['resultCode']??''),['03','3'],true))return $page===1?['status'=>'ready','rows'=>[]]:['status'=>'unavailable','rows'=>[]];
  if(!isset($b['totalCount'])||!is_numeric($b['totalCount']))return ['status'=>'unavailable','rows'=>[]];
  $count=(int)$b['totalCount'];if($count<0||$count>3000||($total!==null&&$total!==$count))return ['status'=>'unavailable','rows'=>[]];$total=$count;
  $items=$b['items']['item']??[];if(isset($items['mgmBldrgstPk']))$items=[$items];
  if(!is_array($items)||($total>count($rows)&&!$items))return ['status'=>'unavailable','rows'=>[]];
  if(isset($b['pageNo'])&&(int)$b['pageNo']!==$page)return ['status'=>'unavailable','rows'=>[]];
  foreach($items as $item){if(!is_array($item))return ['status'=>'unavailable','rows'=>[]];$rn=bfl_text($item['rnum']??'');
   if($rn!==''&&isset($seen[$rn]))return ['status'=>'unavailable','rows'=>[]];if($rn!=='')$seen[$rn]=true;$rows[]=$item;
  }
  if(count($rows)===$total)return ['status'=>'ready','rows'=>$rows];
  if(count($rows)>$total)return ['status'=>'unavailable','rows'=>[]];
 }return ['status'=>'unavailable','rows'=>[]];
}
function bfl_enrich(array $dongs,array $titles,array $api,array $code,array $previous=[]):array {
 if(!$dongs)return [];
 try{$result=bfl_fetch($api,$code);}catch(Throwable $e){$result=['status'=>'unavailable','rows'=>[]];}$matched=$result['status']==='ready'?bfl_match($titles,$code,$result['rows']):[];
 foreach($dongs as $i=>&$dong){$ref=bfl_ref($titles[$i],$code);$dong['registry']=$ref;
  $info=$matched[$i]??['status'=>'unavailable','rows'=>[],'checked_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i:s')];
  if($info['status']==='unavailable')foreach(bfl_array($previous['bd_dong_list']??[]) as $old){
   if(($old['registry']??null)===$ref&&$ref['pk']!==''&&!empty($old['floor_info']['rows'])){$info=$old['floor_info'];$info['refresh_status']='unavailable';break;}
  }
  $dong['floor_info']=$info;
 }unset($dong);return $dongs;
}
function bfl_entries(array $building):array {
 $entries=bdn_rows($building);if($entries)return $entries;
 return [['key'=>'base_'.substr(hash('sha256',preg_replace('/\s+/u','',bfl_text($building['address']??''))),0,24),'name'=>bfl_text($building['name']??'')?:'내 건물','row'=>['floor_a'=>$building['floor_a']??'','floor_b'=>$building['floor_b']??'','area'=>$building['area_t']??'','struct'=>$building['bd_struct']??'','use'=>$building['bd_use_main']??$building['use']??''],'primary'=>true,'evac_id'=>'base']];
}
function bfl_expected(array $row):array {
 $out=[];for($n=min(30,max(0,(int)($row['floor_b']??0)));$n>0;$n--)$out[]='B'.$n;
 for($n=1;$n<=min(200,max(0,(int)($row['floor_a']??0)));$n++)$out[]=$n.'F';return $out;
}
/** Effective display rows preserve raw registry parts and clearly label manual corrections. */
function bfl_effective(array $building,array $entry):array {
 $row=$entry['row'];if($entry['primary'])foreach(['floor_a','floor_b'] as $k)if(bfl_text($building[$k]??'')!=='')$row[$k]=$building[$k];
 $groups=[];foreach((array)($row['floor_info']['rows']??[]) as $part){if(!is_array($part)||!is_string($part['key']??null))continue;$groups[$part['key']][]=$part;}
 $manual=bfl_array(bfl_array($building['bd_floor_manual']??[])[$entry['key']]??[]);$keys=array_values(array_unique(array_merge(bfl_expected($row),array_keys($groups))));$out=[];
 foreach($keys as $key){$parts=$groups[$key]??[];$unknown=false;$sum=0;$uses=[];$structures=[];$excluded=false;
  foreach($parts as $p){$a=bfl_decimal($p['area']??'');if($a==='')$unknown=true;else $sum+=(float)$a;$uses[]=bfl_text($p['use']??'');$structures[]=bfl_text($p['structure']??'');$excluded=$excluded||!empty($p['excluded']);}
  $m=(array)($manual[$key]??[]);$ma=bfl_decimal($m['area']??'');
  $out[]=['key'=>$key,'label'=>$parts[0]['label']??bfl_label($key),'area'=>$ma!==''?$ma:($parts&&!$unknown?rtrim(rtrim(number_format($sum,6,'.',''),'0'),'.'):''),
   'use'=>bfl_text($m['use']??'')?:implode(' · ',array_unique(array_filter($uses))), 'structure'=>bfl_text($m['structure']??'')?:implode(' · ',array_unique(array_filter($structures))),
   'source'=>array_filter($m,static fn($v)=>bfl_text($v)!=='')?'manual':($parts?'registry':'missing'),'area_source'=>$ma!==''?'manual':($parts&&!$unknown?'registry':'missing'),'excluded'=>$excluded,'parts'=>$parts,'checked_at'=>$row['floor_info']['checked_at']??''];
 }return $out;
}
function bfl_clean_manual(array $building):array {
 $raw=$building['bd_floor_manual']??[];if(is_string($raw))$raw=json_decode($raw,true);if(!is_array($raw))throw new InvalidArgumentException('층별 입력 형식을 확인해 주세요.');$out=[];
 foreach(bfl_entries($building) as $entry){$known=array_column(bfl_effective(array_replace($building,['bd_floor_manual'=>[]]),$entry),'key');$rows=$raw[$entry['key']]??[];if(!is_array($rows))throw new InvalidArgumentException('층별 입력 형식을 확인해 주세요.');
  foreach($rows as $key=>$r){if(!in_array($key,$known,true))continue;if(!is_array($r))throw new InvalidArgumentException('층별 입력 형식을 확인해 주세요.');$clean=[];
   foreach(['area'=>24,'use'=>120,'structure'=>120] as $f=>$max){$v=$r[$f]??'';if(!is_scalar($v)||!preg_match('//u',(string)$v)||mb_strlen((string)$v)>$max)throw new InvalidArgumentException('층별 입력 내용을 확인해 주세요.');$v=trim((string)$v);if($f==='area'&&$v!==''&&bfl_decimal($v)==='')throw new InvalidArgumentException('층 면적은 0 이상의 숫자로 입력해 주세요.');$clean[$f]=$f==='area'?bfl_decimal($v):$v;}
   if(array_filter($clean,static fn($v)=>$v!==''))$out[$entry['key']][$key]=$clean;
  }
 }return $out;
}
