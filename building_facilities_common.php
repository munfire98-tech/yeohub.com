<?php
declare(strict_types=1);
function bf_catalog():array{return [
'fire_ext'=>['소화설비',['소화기구 및 자동소화장치','옥내소화전설비','옥외소화전설비','스프링클러설비','간이스프링클러설비','화재조기진압용 스프링클러설비','물분무소화설비','미분무소화설비','포소화설비','이산화탄소소화설비','할론소화설비','할로겐화합물 및 불활성기체소화설비','분말소화설비','강화액소화설비','고체에어졸소화설비']],
'alarm'=>['경보설비',['단독경보형감지기','비상경보설비','자동화재탐지설비 및 시각경보기','화재알림설비','비상방송설비','통합감시시설','자동화재속보설비','누전경보기','가스누설경보기']],
'escape'=>['피난구조설비',['피난기구','공기안전매트','피난사다리','(간이)완강기','미끄럼대','구조대','다수인피난장비','승강식피난기','하향식피난구용내림식사다리','인명구조기구','피난유도선','유도등','비상조명등','유도표지','휴대용비상조명등']],
'water'=>['소화용수설비',['상수도소화용수설비','소화수조 및 저수조']],
'active'=>['소화활동설비',['거실제연설비','부속실 등 제연설비','연결송수관설비','연결살수설비','비상콘센트설비','무선통신보조설비','연소방지설비']],
'fire_compartment'=>['방화시설',['방화문','방화셔터']]];}
function bf_id(string $name):string{return substr(hash('sha256',$name),0,16);}
function bf_file():string{ $key=bi_user_key();if($key===''||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$key))return '';return __DIR__.'/data/building/'.$key.'/facilities.php';}
function bf_load():array{$f=bf_file();if($f===''||!is_file($f))return ['items'=>[],'revision'=>''];$raw=file_get_contents($f);if($raw===false)throw new RuntimeException('시설현황을 읽을 수 없습니다.');$d=json_decode(preg_replace('/^<\?php exit; \?>\s*/','',$raw),true);if(!is_array($d))throw new RuntimeException('시설현황 저장 파일을 확인해 주세요.');return $d;}
function bf_counts(array $d):array{$r=['total'=>0,'confirmed'=>0,'present'=>0,'unknown'=>0];if(!empty($d['scopes'])){foreach($d['scopes'] as $scope){if(empty($scope['included']))continue;$c=bf_counts(['items'=>$scope['items']??[]]);foreach($r as $k=>$v)$r[$k]+=$c[$k];}return $r;}foreach(bf_catalog() as $group)foreach($group[1] as $name){$r['total']++;$v=$d['items'][bf_id($name)]['status']??'unknown';if(in_array($v,['yes','no'],true))$r['confirmed']++;else $r['unknown']++;if($v==='yes')$r['present']++;}return $r;}
function bf_complete(array $d):bool{$c=bf_counts($d);return $c['confirmed']===$c['total'];}
function bf_dong_options(array $bi):array {
 $pick=trim((string)($bi['bd_dong_pick']??''));
 $options=['base'=>$pick!==''?$pick.' · 기본동':'기본동 · 기존 현황'];
 $list=$bi['bd_dong_list']??[];
 if(is_string($list))$list=json_decode($list,true);
 if(!is_array($list)||!$list){$list=[];foreach(preg_split('/\R/u',(string)($bi['bd_dongs']??''))?:[] as $line){if(preg_match('/^\s*(.*?)\s*:\s*지상/u',$line,$m))$list[]=['dong'=>trim($m[1])];}}
 $seen=[];$skippedPrimary=false;
 foreach($list as $i=>$row){if(!is_array($row))continue;$name=trim((string)($row['dong']??''));if($name==='')$name='동명 미상 '.($i+1);
  if(!$skippedPrimary&&$pick!==''&&$name===$pick){$skippedPrimary=true;continue;}
  $n=($seen[$name]??0)+1;$seen[$name]=$n;$id='dong_'.substr(hash('sha256',$name.'#'.$n),0,20);
  $options[$id]=$name.($n>1?' ('.$n.')':'');
 }
 return $options;
}
function bf_scopes(array $data,array $options=[]):array {
 $scopes=is_array($data['scopes']??null)?$data['scopes']:[];
 if(!$scopes)$scopes=['base'=>['label'=>$options['base']??'기본동 · 기존 현황','included'=>true,'items'=>$data['items']??[]]];
 foreach($options as $id=>$label)if(!isset($scopes[$id]))$scopes[$id]=['label'=>$label,'included'=>$id==='base','items'=>[]];
 return $scopes;
}
function bf_aggregate(array $scopes):array {
 $out=[];
 foreach(bf_catalog() as $g)foreach($g[1] as $name){$id=bf_id($name);$yes=[];$unknown=false;
  foreach($scopes as $scope){if(empty($scope['included']))continue;$v=$scope['items'][$id]??[];if(($v['status']??'unknown')==='yes')$yes[]=$scope['label'];elseif(($v['status']??'unknown')!=='no')$unknown=true;}
  $out[$id]=['status'=>$yes?'yes':($unknown?'unknown':'no'),'quantity'=>'','location'=>implode(' · ',$yes),'note'=>''];
 }
 return $out;
}
function bf_save(array $input,string $revision):array {
 $old=bf_load();$scopes=bf_scopes($old);$included=array_keys(array_filter($scopes,static fn($s)=>!empty($s['included'])));
 return bf_save_multi(['base'=>$input],$included,$revision,[]);
}
function bf_save_multi(array $inputs,array $included,string $revision,array $options,bool $reset=false):array {
 $f=bf_file();if($f==='')throw new RuntimeException('건물 계정을 확인해 주세요.');
 $dir=dirname($f);if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('저장 폴더를 만들 수 없습니다.');
 $lock=fopen($f.'.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('저장 잠금을 얻지 못했습니다.');$tmp=false;
 try{
  $old=bf_load();if(!hash_equals((string)($old['revision']??''),$revision))throw new RuntimeException('다른 화면에서 수정되었습니다. 현재 입력을 확인한 후 새로고침해 주세요.');
  $scopes=bf_scopes($old,$options);$included=array_unique(array_merge(['base'],$included));
  foreach($included as $id)if(!is_string($id)||!isset($scopes[$id]))throw new RuntimeException('동 목록이 변경되었습니다. 다시 열어 주세요.');
  foreach($scopes as $id=>&$scope){if($reset){$scope['items']=[];continue;}$scope['included']=in_array($id,$included,true);if(!$scope['included']||!array_key_exists($id,$inputs))continue;
   if(!is_array($inputs[$id]))throw new RuntimeException('동별 입력 형식을 확인해 주세요.');
   foreach(bf_catalog() as $g)foreach($g[1] as $name){$key=bf_id($name);$v=$inputs[$id][$key]??[];$prior=$scope['items'][$key]??[];
    if(!is_array($v)||!in_array($v['status']??'unknown',['yes','no','unknown'],true))throw new RuntimeException('설치 여부를 확인해 주세요.');
    $row=['status'=>$v['status']??'unknown'];foreach(['quantity'=>40,'location'=>200,'note'=>500] as $field=>$max){$row[$field]=trim((string)($v[$field]??$prior[$field]??''));if(mb_strlen($row[$field])>$max)throw new RuntimeException('시설 정보 길이를 줄여 주세요.');}
    $scope['items'][$key]=$row;
   }
  }unset($scope);
  $out=array_replace($old,['version'=>2,'scopes'=>$scopes,'items'=>bf_aggregate($scopes),'revision'=>bin2hex(random_bytes(16)),'updated'=>date('c')]);
  $tmp=tempnam($dir,'.fac-');if(!$tmp||file_put_contents($tmp,"<?php exit; ?>\n".json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false||!rename($tmp,$f))throw new RuntimeException('저장하지 못했습니다.');return $out;
 }finally{if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
}
function bf_summary(array $d):string{if(!empty($d['scopes'])){$parts=[];foreach($d['scopes'] as $scope){if(empty($scope['included']))continue;$one=['items'=>$scope['items']??[]];$c=bf_counts($one);$parts[]='['.$scope['label'].']'."\n".(bf_summary($one)?:'선택한 시설이 없습니다.');}return implode("\n\n",$parts);}$lines=[];foreach(bf_catalog() as $group)foreach($group[1] as $name){$v=$d['items'][bf_id($name)]??[];if(($v['status']??'')==='yes')$lines[]=$name.(!empty($v['quantity'])?' / '.$v['quantity']:'').(!empty($v['location'])?' / '.$v['location']:'').(!empty($v['note'])?' / 메모: '.$v['note']:'');}return implode("\n",$lines);}
function bf_render_reference():void{require_once __DIR__.'/building_info.php';$d=bf_load();$c=bf_counts($d);$query=[];if(!empty($_GET['embed']))$query['embed']='1';if((!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']))&&is_string($_GET['uid']??null))$query['uid']=$_GET['uid'];$url='/building_facilities.php'.($query?'?'.http_build_query($query):'');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<details class="bf-reference" style="margin:14px auto;padding:12px 16px;max-width:1200px;border:1px solid #dce5ed;border-radius:10px;background:#f7fafc;font:13px/1.7 system-ui"><summary style="cursor:pointer">소방시설 현황</summary><p style="white-space:pre-wrap;margin:10px 0"><?=$e(bf_summary($d)?:'선택한 시설이 없습니다.')?></p><p>설치 현황을 참고해 업무를 작성하세요. 점검 결과나 훈련 실시 여부를 자동으로 판단하지 않습니다.</p><a href="<?=$e($url)?>">소방시설 확인·수정 →</a></details>
<?php }

function bf_reset(string $revision,array $options):array{return bf_save_multi([],['base'],$revision,$options,true);}
