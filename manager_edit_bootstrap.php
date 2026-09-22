<?php
declare(strict_types=1);
function mge_routes(): array {
    return ['building_facilities.php','building_manager.php','building_setup.php','building_setup_chat.php',
    'work_log.php','work_log_form.php','work_log_print.php','jawi.php','jawi_edit.php','jawi_chat.php','jawi_print.php',
    'train.php','train_edit.php','train_chat.php','train_print.php','train_photo.php',
    'evacuation_plan.php','evacuation_plan_chat.php','evac_view.php','evac_assign_api.php',
    'fire_plan.php','fire_plan_new.php','fire_plan_edit.php','fire_plan_chat.php','fire_plan_jawi.php','fire_plan_print.php',
    'print_all.php','safety_ai_api.php','notifications.php'];
}
function mge_hook(string $source): string {
    $hook="/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';";
    if(strpos($source,$hook)!==false)return $source;
    $tokens=token_get_all($source,TOKEN_PARSE);
    if(!isset($tokens[0])||!is_array($tokens[0])||$tokens[0][0]!==T_OPEN_TAG)throw new RuntimeException('PHP 파일의 시작 형식을 확인해야 합니다.');
    $offset=strlen($tokens[0][1]);$i=1;
    while(isset($tokens[$i])){
        $t=$tokens[$i];
        if(is_array($t)&&in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)){$offset+=strlen($t[1]);$i++;continue;}
        if(is_array($t)&&$t[0]===T_DECLARE){
            do{$t=$tokens[$i++];$text=is_array($t)?$t[1]:$t;if($text==='{'||$text===':')throw new RuntimeException('블록 declare 문은 자동 연결하지 않습니다.');$offset+=strlen($text);}while($text!==';'&&isset($tokens[$i]));
            continue;
        }
        if(is_array($t)&&$t[0]===T_NAMESPACE)throw new RuntimeException('네임스페이스 페이지는 자동 연결하지 않습니다.');
        break;
    }
    $new=substr($source,0,$offset)."\n".$hook."\n".substr($source,$offset);
    token_get_all($new,TOKEN_PARSE);return $new;
}
function mge_prepare(): void {
    $dir=__DIR__;
    if(!is_file($dir.'/manager_edit_guard.php'))throw new RuntimeException('manager_edit_guard.php 파일을 함께 업로드해 주세요.');
    if(!is_dir($dir.'/data')&&!mkdir($dir.'/data',0775,true))throw new RuntimeException('데이터 폴더를 만들 수 없습니다.');
    $lock=fopen($dir.'/data/manager_edit_patch.lock','c+');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('편집 연결이 진행 중입니다. 잠시 후 다시 열어 주세요.');
    try{
        foreach(['building_manager.php','building_setup.php'] as $required)if(!is_file($dir.'/'.$required))throw new RuntimeException($required.' 파일이 같은 폴더에 필요합니다.');
        $changes=[];
        foreach(mge_routes() as $name){
            $path=$dir.'/'.$name;if(!is_file($path))continue;
            if(is_link($path))throw new RuntimeException($name.' 연결 파일은 자동 변경하지 않습니다.');
            $old=file_get_contents($path);if($old===false)throw new RuntimeException($name.' 파일을 읽을 수 없습니다.');
            $new=mge_hook($old);if($new!==$old){if(!is_writable($path)||!is_writable($dir))throw new RuntimeException($name.' 파일 쓰기 권한이 필요합니다.');$changes[$name]=['old'=>$old,'new'=>$new];}
        }
        if(!$changes)return;
        // A protected, durable backup is written before any application source changes.
        mg_tx($dir.'/data/manager_edit_source_backup.php',function(&$backup)use($changes){
            foreach($changes as $name=>$pair){$hash=hash('sha256',$pair['old']);$backup[$name][$hash]=['at'=>date('c'),'source_base64'=>base64_encode($pair['old'])];}
        },true);
        foreach($changes as $name=>$pair){
            $path=$dir.'/'.$name;
            if(file_get_contents($path)!==$pair['old'])throw new RuntimeException($name.' 파일이 변경되었습니다. 다시 열어 주세요.');
            $tmp=tempnam($dir,'.mge_');
            try{
                if(!$tmp||file_put_contents($tmp,$pair['new'],LOCK_EX)===false)throw new RuntimeException($name.' 연결을 저장하지 못했습니다.');
                chmod($tmp,fileperms($path)&0777);
                if(!rename($tmp,$path))throw new RuntimeException($name.' 연결을 적용하지 못했습니다.');
                if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
            }finally{if($tmp&&is_file($tmp))unlink($tmp);}
        }
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
