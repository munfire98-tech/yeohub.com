<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
function pci_file(string $uid,array $members):string{
 if(!mg_active($members[$uid]??[],'agency'))throw new RuntimeException('파트너 계정으로 로그인해 주세요.',403);
 return __DIR__.'/data/partner_company/'.hash('sha256',$uid.'|'.(string)$members[$uid]['created']).'.php';
}
function pci_read(string $uid,array $members):array{
 $saved=mg_read(pci_file($uid,$members));if($saved)return $saved;
 // Accept an explicit legacy company only when it belongs to this account generation.
 $dir=str_starts_with($uid,'kakao_')?'u_'.preg_replace('/[^0-9A-Za-z]/','',substr($uid,6)):'m_'.preg_replace('/[^0-9A-Za-z_]/','',$uid);
 $legacy=mg_read(__DIR__.'/data/users/'.$dir.'/settings.json');$company=trim((string)($legacy['company']??''));
 $at=strtotime((string)($legacy['updated']??''));$created=strtotime((string)($members[$uid]['created']??''));
 if($company!==''&&$at&&$created&&$at>=$created)return ['mode'=>'company','company'=>$company,'phone'=>'','saved_at'=>$legacy['updated']];
 return [];
}
function pci_validate(array $input):array{
 $mode=$input['mode']??'';if(!in_array($mode,['company','personal'],true))throw new RuntimeException('소속 업체 또는 개인 활동을 선택해 주세요.',400);
 $company=is_string($input['company']??null)?trim($input['company']):'';$phone=is_string($input['phone']??null)?trim($input['phone']):'';
 if($mode==='company'&&($company===''||!preg_match('//u',$company)||mb_strlen($company,'UTF-8')>80||preg_match('/[\x00-\x1f\x7f<>]/u',$company)))throw new RuntimeException('업체명을 1~80자로 입력해 주세요.',400);
 if(!preg_match('/^[0-9+() -]{0,30}$/D',$phone))throw new RuntimeException('대표전화 형식을 확인해 주세요.',400);
 return ['mode'=>$mode,'company'=>$mode==='company'?$company:'','phone'=>$mode==='company'?$phone:'','saved_at'=>date('c')];
}
