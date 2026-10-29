<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
/** Enumerate this building's business records, never account/payment/partner-owned stores. */
function mh_reset_files(string $uid):array {
    $root=__DIR__.'/data';$files=[];
    foreach(['building/'.$uid,'fireplan/'.$uid,'jawi/'.$uid,'train/'.$uid,'worklog/'.$uid,'worklog/'.preg_replace('/[^A-Za-z0-9_]/','_',$uid)] as $relative){
        $dir=$root.'/'.$relative;if(is_link($dir))throw new RuntimeException('자료 경로를 확인해 주세요.');if(!is_dir($dir))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f){if($f->isLink())throw new RuntimeException('자료 경로를 확인해 주세요.');if($f->isFile()&&!str_ends_with($f->getFilename(),'.lock'))$files[]=$f->getPathname();}
    }
    $member=mg_members()[$uid]??[];$falseAlarm=$root.'/false_alarm/'.hash('sha256',$uid.'|'.(string)($member['created']??'')).'.json';
    if(is_link($falseAlarm))throw new RuntimeException('자료 경로를 확인해 주세요.');if(is_file($falseAlarm))$files[]=$falseAlarm;
    $files=array_values(array_unique($files));sort($files,SORT_STRING);return $files;
}
/** Guarded per-file recovery copies; failures restore originals instead of reporting success. */
function mh_reset_archive(string $uid,array $files):array {
    $dir=__DIR__.'/data/building_reset_archives/'.$uid.'/'.date('YmdHis').'-'.bin2hex(random_bytes(8));
    if(!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('초기화 전 보관본을 만들지 못했습니다.');
    $copies=[];
    foreach($files as $i=>$source){
        $dest=$dir.'/'.sprintf('%06d',$i).'.php';$in=fopen($source,'rb');$out=fopen($dest,'xb');
        if(!$in||!$out)throw new RuntimeException('초기화 전 자료를 보관하지 못했습니다.');
        try{if(fwrite($out,"<?php exit; ?>\n")!==15||stream_copy_to_stream($in,$out)===false)throw new RuntimeException('초기화 전 자료를 보관하지 못했습니다.');}
        finally{fclose($in);fclose($out);}
        chmod($dest,0600);$copies[$source]=$dest;
    }
    $manifest=['uid'=>$uid,'at'=>date('c'),'files'=>[]];foreach($copies as $source=>$dest)$manifest['files'][]=['path'=>substr($source,strlen(__DIR__.'/data/')),'copy'=>basename($dest)];
    if(file_put_contents($dir.'/manifest.php',"<?php exit; ?>\n".json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('초기화 전 자료를 보관하지 못했습니다.');
    chmod($dir.'/manifest.php',0600);return $copies;
}
function mh_reset_restore(array $copies):void {
    foreach($copies as $source=>$copy){
        $in=fopen($copy,'rb');$out=fopen($source,'wb');if(!$in||!$out)throw new RuntimeException('초기화 복구 확인이 필요합니다.');
        try{fseek($in,15);if(stream_copy_to_stream($in,$out)===false)throw new RuntimeException('초기화 복구 확인이 필요합니다.');}finally{fclose($in);fclose($out);}
    }
}
/** Internal helper: callers validate login, editing permission, explicit confirmation and CSRF. */
function mh_reset_building_requests(string $uid,callable $resetBuilding):void {
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)||defined('MANAGER_VIEW_UID')||(function_exists('app_user_key')&&app_user_key()!==$uid))throw new RuntimeException('초기화할 회원을 확인하지 못했습니다.');
    $copies=[];
    try{
        mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($uid,$resetBuilding,&$copies){
            $files=mh_reset_files($uid);$copies=mh_reset_archive($uid,$files);
            // Revocation prevents an old QR/link from exposing a newly created plan after reset.
            mg_tx(__DIR__.'/data/fire_plan_shares.php',function(array &$shares)use($uid,$resetBuilding,$files){
                if($resetBuilding()!==true)throw new RuntimeException('기본정보를 초기화하지 못했습니다.');
                $basic=__DIR__.'/data/building/'.$uid.'/info.json';
                foreach($files as $file)if($file!==$basic&&is_file($file)&&!unlink($file))throw new RuntimeException('업무자료를 초기화하지 못했습니다.');
                foreach($shares as &$row)if(is_array($row)&&($row['uid']??'')===$uid)$row['revoked_at']=date('c');unset($row);
                return true;
            },true);
            foreach($rows as $id=>$row)if(is_array($row)&&(string)($row['uid']??'')===$uid)unset($rows[$id]);
            return true;
        },true);
    }catch(Throwable $e){if($copies)mh_reset_restore($copies);throw $e;}
}
