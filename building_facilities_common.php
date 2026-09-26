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
/** Read-only source snapshot. Never call bi_load() here: callers may hold member/state locks. */
function bf_source():array {
 $key=bi_user_key();$bi=[];
 if($key!==''&&preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$key)){
  $file=__DIR__.'/data/building/'.$key.'/info.json';
  if(is_file($file)){
   $raw=file_get_contents($file);$bi=$raw===false?null:json_decode($raw,true);
   if(!is_array($bi))throw new RuntimeException('기본정보를 읽지 못했습니다. 잠시 후 다시 열어 주세요.');
  }else{
   $legacy=__DIR__.'/data/worklog/'.$key.'/building.json';
   if(is_file($legacy)){$raw=file_get_contents($legacy);$old=$raw===false?null:json_decode($raw,true);if(!is_array($old))throw new RuntimeException('기본정보를 읽지 못했습니다.');$bi=['address'=>$old['address']??''];}
  }
 }
 $address=preg_replace('/\s+/u','',trim((string)($bi['address']??'')));
 $options=bf_dong_options($bi);$sorted=$options;ksort($sorted);
 $source=['reset_token'=>(string)($bi['_facility_reset_token']??''),'address'=>$address,'name_key'=>$address===''?trim((string)($bi['name']??'')):'','options'=>$sorted];
 $source['signature']=hash('sha256',json_encode($source,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 return $source;
}
function bf_read_stored():array {
 $f=bf_file();if($f===''||!is_file($f))return ['items'=>[],'revision'=>''];
 $raw=file_get_contents($f);if($raw===false)throw new RuntimeException('시설현황을 읽을 수 없습니다.');
 $d=json_decode(preg_replace('/^<\?php exit; \?>\s*/','',$raw),true);
 if(!is_array($d))throw new RuntimeException('시설현황 저장 파일을 확인해 주세요.');return $d;
}
/** Project onto current basic information without changing any files. */
function bf_align(array $stored,array $source):array {
 $prior=$stored['basic_source']??null;$options=$source['options'];
 if(is_array($prior)&&($prior['signature']??'')===$source['signature'])return $stored;
 $oldScopes=bf_scopes($stored);$matched=[];
 // Only carry installations between explicitly identified scopes of the same address.
 $wasReset=$source['reset_token']!==($prior['reset_token']??'');
 $sameBuilding=!$wasReset&&is_array($prior)&&($prior['address']??null)===$source['address']&&($prior['name_key']??'')===$source['name_key'];
 if($sameBuilding){
  foreach($oldScopes as $id=>$scope){$label=(string)($prior['options'][$id]??$scope['label']??'');
   if($id==='base')$label=preg_replace('/ · 기본동$/u','',$label);
   $matched[$label][]=$scope;
  }
 }
 $scopes=[];
 foreach($options as $id=>$label){
  $key=$id==='base'?preg_replace('/ · 기본동$/u','',$label):$label;
  $scope=!empty($matched[$key])?array_shift($matched[$key]):['included'=>false,'items'=>[]];
  $scope['label']=$label;$scope['included']=$id==='base'||!empty($scope['included']);$scopes[$id]=$scope;
 }
 $out=$stored;$out['scopes']=$scopes;$out['items']=bf_aggregate($scopes);$out['basic_source']=$source;
 // Changes to basic information invalidate previously opened facility forms as well.
 $out['revision']=hash('sha256',(string)($stored['revision']??'').'|'.$source['signature']);
 if(!empty($stored['revision'])||!empty($stored['items'])||!empty($stored['scopes'])){
  $out['_sync_notice']=$wasReset?'기본정보 초기화에 따라 소방시설 현황도 초기화되었습니다. 기본정보를 등록한 후 시설을 다시 선택해 주세요.':(!is_array($prior)?'기존 현황에는 기준 건물 정보가 없어 현재 건물의 설비를 다시 확인해야 합니다. 기존 기록은 보존되며, 저장 시 백업됩니다.':($sameBuilding?'기본정보의 동 목록을 반영했습니다. 유지된 동의 기록은 보존하고 새 동은 비워 두었습니다.':'기본정보의 주소가 변경되어 시설 선택을 비웠습니다. 새 건물의 설비를 확인해 주세요. 이전 기록은 저장 시 백업됩니다.'));
 }
 return $out;
}
function bf_load():array {return bf_align(bf_read_stored(),bf_source());}
/** Keep a guarded recovery copy before a reset or source change is committed. */
function bf_backup(string $file,array $stored):void {
 if(!is_file($file))return;
 $dir=dirname($file).'/facility_backups';
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('이전 현황을 백업하지 못해 저장을 중단했습니다.');
 $raw="<?php exit; ?>\n".json_encode($stored,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 $target=$dir.'/'.hash('sha256',$raw).'.php';if(is_file($target))return;
 $tmp=tempnam($dir,'.backup-');
 try{if(!$tmp||file_put_contents($tmp,$raw)!==strlen($raw)||!rename($tmp,$target))throw new RuntimeException('이전 현황을 백업하지 못해 저장을 중단했습니다.');}
 finally{if($tmp&&is_file($tmp))unlink($tmp);}
}
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
 $lock=fopen($f.'.lock','c+');if(!$lock)throw new RuntimeException('저장 잠금을 얻지 못했습니다.');
 $deadline=microtime(true)+3;while(!flock($lock,LOCK_EX|LOCK_NB)){if(microtime(true)>=$deadline){fclose($lock);throw new RuntimeException('다른 저장이 진행 중입니다. 잠시 후 다시 저장해 주세요.');}usleep(20000);}$tmp=false;
 try{
  $source=bf_source();$stored=bf_read_stored();$old=bf_align($stored,$source);if(!hash_equals((string)($old['revision']??''),$revision))throw new RuntimeException('다른 화면에서 수정되었습니다. 현재 입력을 확인한 후 새로고침해 주세요.');
  if($options){$check=$options;ksort($check);if($check!==$source['options'])throw new RuntimeException('기본정보가 변경되었습니다. 시설현황을 다시 열어 주세요.');}
  $scopes=bf_scopes($old,$source['options']);$included=array_unique(array_merge(['base'],$included));
  foreach($included as $id)if(!is_string($id)||!isset($scopes[$id]))throw new RuntimeException('동 목록이 변경되었습니다. 다시 열어 주세요.');
  foreach($scopes as $id=>&$scope){if($reset){$scope['items']=[];$scope['included']=$id==='base';continue;}$scope['included']=in_array($id,$included,true);if(!$scope['included']||!array_key_exists($id,$inputs))continue;
   if(!is_array($inputs[$id]))throw new RuntimeException('동별 입력 형식을 확인해 주세요.');
   foreach(bf_catalog() as $g)foreach($g[1] as $name){$key=bf_id($name);$v=$inputs[$id][$key]??[];$prior=$scope['items'][$key]??[];
    if(!is_array($v)||!in_array($v['status']??'unknown',['yes','no','unknown'],true))throw new RuntimeException('설치 여부를 확인해 주세요.');
    $row=['status'=>$v['status']??'unknown'];foreach(['quantity'=>40,'location'=>200,'note'=>500] as $field=>$max){$row[$field]=trim((string)($v[$field]??$prior[$field]??''));if(mb_strlen($row[$field])>$max)throw new RuntimeException('시설 정보 길이를 줄여 주세요.');}
    $scope['items'][$key]=$row;
   }
  }unset($scope);
  $out=array_replace($old,['version'=>2,'scopes'=>$scopes,'items'=>bf_aggregate($scopes),'revision'=>bin2hex(random_bytes(16)),'updated'=>date('c')]);
  unset($out['_sync_notice']);
  if(bf_source()['signature']!==$source['signature'])throw new RuntimeException('저장 중 기본정보가 변경되었습니다. 다시 열어 주세요.');
  if($reset||($stored['basic_source']['signature']??'')!==$source['signature'])bf_backup($f,$stored);
  $tmp=tempnam($dir,'.fac-');if(!$tmp||file_put_contents($tmp,"<?php exit; ?>\n".json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false||!rename($tmp,$f))throw new RuntimeException('저장하지 못했습니다.');return $out;
 }finally{if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
}
function bf_summary(array $d):string{if(!empty($d['scopes'])){$parts=[];foreach($d['scopes'] as $scope){if(empty($scope['included']))continue;$one=['items'=>$scope['items']??[]];$c=bf_counts($one);$parts[]='['.$scope['label'].']'."\n".(bf_summary($one)?:'선택한 시설이 없습니다.');}return implode("\n\n",$parts);}$lines=[];foreach(bf_catalog() as $group)foreach($group[1] as $name){$v=$d['items'][bf_id($name)]??[];if(($v['status']??'')==='yes')$lines[]=$name.(!empty($v['quantity'])?' / '.$v['quantity']:'').(!empty($v['location'])?' / '.$v['location']:'').(!empty($v['note'])?' / 메모: '.$v['note']:'');}return implode("\n",$lines);}
function bf_render_reference():void{require_once __DIR__.'/building_info.php';$d=bf_load();$c=bf_counts($d);$query=[];if(!empty($_GET['embed']))$query['embed']='1';if((!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']))&&is_string($_GET['uid']??null))$query['uid']=$_GET['uid'];$url='/building_facilities.php'.($query?'?'.http_build_query($query):'');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<details class="bf-reference" style="margin:14px auto;padding:12px 16px;max-width:1200px;border:1px solid #dce5ed;border-radius:10px;background:#f7fafc;font:13px/1.7 system-ui"><summary style="cursor:pointer">소방시설 현황</summary><p style="white-space:pre-wrap;margin:10px 0"><?=$e(bf_summary($d)?:'선택한 시설이 없습니다.')?></p><p>설치 현황을 참고해 업무를 작성하세요. 점검 결과나 훈련 실시 여부를 자동으로 판단하지 않습니다.</p><a href="<?=$e($url)?>">소방시설 확인·수정 →</a></details>
<?php }

function bf_reset(string $revision,array $options):array{return bf_save_multi([],['base'],$revision,$options,true);}
