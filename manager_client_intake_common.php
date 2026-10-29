<?php
/** Client list intake. Files stay inside an executable PHP exit guard, never at a public upload URL. */
declare(strict_types=1);
require_once __DIR__.'/manager_addresses_common.php';
function ci_file():string{return __DIR__.'/data/client_intakes/requests.php';}
function ci_admin():bool{return empty($_SESSION['_imp'])&&empty($_SESSION['_mge_actor'])&&(!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']));}
function ci_owned(array $r,string $actor,array $members):bool{return mg_active($members[$actor]??[],'agency')&&($r['manager']??'')===$actor&&($r['manager_created']??'')===(string)($members[$actor]['created']??'');}
function ci_text(string $s,int $max=500):string{$s=trim(str_replace("\0",'', $s));if(mb_strlen($s)>$max)throw new RuntimeException('입력 내용이 너무 깁니다.');return $s;}
function ci_decode(string $raw):string{
 if(str_starts_with($raw,"\xFF\xFE")||str_starts_with($raw,"\xFE\xFF"))$raw=mb_convert_encoding($raw,'UTF-8','UTF-16');
 elseif(!mb_check_encoding($raw,'UTF-8'))$raw=mb_convert_encoding($raw,'UTF-8','CP949');
 return preg_replace('/^\xEF\xBB\xBF/','',$raw);
}
function ci_parse(string $raw):array{
 if(strlen($raw)>500000)throw new RuntimeException('붙여넣기는 500KB 이내로 보내 주세요.');
 $raw=ci_decode($raw);$fp=fopen('php://temp','r+');fwrite($fp,$raw);rewind($fp);
 $delimiter=str_contains($raw,"\t")?"\t":',';$rows=[];
 try{while(($cells=fgetcsv($fp,null,$delimiter,'"',''))!==false){
  $cells=array_map(static fn($v)=>trim((string)$v),$cells);if(!array_filter($cells,static fn($v)=>$v!==''))continue;
  if(count($cells)===1&&str_contains($cells[0],' / '))$cells=explode(' / ',$cells[0],2);
  if(count($cells)>2)throw new RuntimeException('거래처명과 주소 두 열만 붙여넣어 주세요. 주소에 쉼표가 있으면 탭으로 구분해 주세요.');
  if(in_array($cells[0],['거래처명','건물명'],true)&&isset($cells[1])&&str_contains($cells[1],'주소'))continue;
  $name=count($cells)>1?$cells[0]:'';$address=count($cells)>1?$cells[1]:$cells[0];
  $rows[]=['id'=>bin2hex(random_bytes(8)),'name'=>ci_text($name,150),'address'=>ci_text($address),'result'=>'','address_id'=>''];
  if(count($rows)>600)throw new RuntimeException('한 번에 최대 600개까지 보내 주세요.');
 }}finally{fclose($fp);}return $rows;
}
function ci_attachment(array $f):array{
 if(($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return [];
 if(($f['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']??''))throw new RuntimeException('파일 업로드에 실패했습니다.');
 if(($f['size']??0)>5*1024*1024)throw new RuntimeException('파일은 5MB 이하로 보내 주세요.');
 $name=ci_text(basename(str_replace('\\','/',(string)$f['name'])),180);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
 if(!in_array($ext,['csv','txt','xlsx','xls'],true))throw new RuntimeException('엑셀·CSV·TXT 파일만 보낼 수 있습니다.');
 $raw=file_get_contents($f['tmp_name']);if($raw===false||strlen($raw)>5*1024*1024)throw new RuntimeException('파일을 읽을 수 없습니다.');
 if(($ext==='xlsx'&&!str_starts_with($raw,"PK\x03\x04"))||($ext==='xls'&&!str_starts_with($raw,"\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")))throw new RuntimeException('파일 형식을 확인해 주세요.');
 return ['name'=>$name,'ext'=>$ext,'data'=>base64_encode($raw)];
}
function ci_key(string $s):string{return mb_strtolower(preg_replace('/\s+/u','',trim($s)));}
/** Called under members -> intake -> manager-state locks. Idempotent even if intake write fails after state commit. */
function ci_register_rows(array &$r,array &$s,array $members,array $selected,array $edits):void{
 $actor=(string)$r['manager'];if(!ci_owned($r,$actor,$members))throw new RuntimeException('업체 계정 상태가 변경되어 등록할 수 없습니다.');
 $count=0;foreach($s['address_book']??[] as $entry)if(ma_owned($entry,$actor,$members))$count++;
 foreach($r['rows'] as &$row){if(!in_array($row['id'],$selected,true)||$row['address_id']!=='')continue;
  $edit=$edits[$row['id']]??[];$row['name']=ci_text((string)($edit['name']??$row['name']),150);$row['address']=ci_text((string)($edit['address']??$row['address']));
  $row['result']='';if($row['name']===''||$row['address']===''){$row['result']='거래처명과 주소를 확인해 주세요.';continue;}
  $found='';foreach($s['address_book']??[] as $id=>$entry){if(!ma_owned($entry,$actor,$members))continue;
   if((($entry['intake_id']??'')===$r['id']&&($entry['intake_row']??'')===$row['id'])||(ci_key((string)($entry['name']??''))===ci_key($row['name'])&&ci_key((string)($entry['address']??''))===ci_key($row['address']))){$found=(string)$id;break;}
  }
  if($found!==''){$row['address_id']=$found;$row['result']='기존 등록 확인';continue;}
  if($count>=600){$row['result']='업체의 사전등록 한도 600개를 초과했습니다.';continue;}
  $id=bin2hex(random_bytes(12));$s['address_book'][$id]=['id'=>$id,'manager'=>$actor,'manager_created'=>$r['manager_created'],'created_at'=>date('c'),'name'=>$row['name'],'address'=>$row['address'],'jibun'=>'','address_key'=>'intake_'.hash('sha256',ci_key($row['name']).'|'.ci_key($row['address'])),'codes'=>[],'basic_info'=>['name'=>$row['name'],'address'=>$row['address']],'intake_id'=>$r['id'],'intake_row'=>$row['id']];
  $row['address_id']=$id;$row['result']='등록 완료';$count++;
 }unset($row);$r['updated_at']=date('c');
}
function ci_status(array $r):string{$total=count($r['rows']);$done=count(array_filter($r['rows'],static fn($v)=>($v['address_id']??'')!==''));return $done===0?'접수됨':($done===$total?'등록 완료':'일부 등록');}
