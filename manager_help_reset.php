<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
/** Internal helper: caller must validate login, editing permission and CSRF first. */
function mh_reset_building_requests(string $uid, callable $resetBuilding): void {
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)) throw new RuntimeException('초기화할 회원을 확인하지 못했습니다.');
    // Same request-store lock as creation/resolution: other users and concurrent writes are preserved.
    mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($uid,$resetBuilding){
        if($resetBuilding()!==true) throw new RuntimeException('기본정보를 초기화하지 못했습니다. 다시 시도해 주세요.');
        foreach($rows as $id=>$row){
            if(is_array($row)&&(string)($row['uid']??'')===$uid&&strpos((string)($row['field']??''),'__fp_')!==0) unset($rows[$id]);
        }
    },true);
}
