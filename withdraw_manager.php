<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/annual_billing_engine.php';

/** Called while holding the members lock, before removing the account. */
function wm_queue(array $members,array &$state,string $uid,array $member):void {
    if(!mg_active($member,'building'))return;
    $manager=mg_connection_manager($member,$members);
    if($manager===''||!in_array(mg_link_status($uid,$member,$state),['accepted','pending'],true))return;
    $id=hash('sha256',mg_link_key($uid,$member).':member_withdrawn');
    if(isset($state['connection_notifications'][$id]))return;
    $state['connection_notifications'][$id]=[
        'id'=>$id,'manager'=>$manager,'manager_created'=>(string)($members[$manager]['created']??''),
        'user'=>$uid,'user_created'=>(string)($member['created']??''),
        'name'=>(string)($member['nickname']??$uid),'kind'=>'member_withdrawn',
        'at'=>date('c'),'read_at'=>null,
    ];
}
/** A queued notice stays invisible if removing the member failed. */
function wm_visible(array $notice,array $members):bool {
    if(($notice['kind']??'')!=='member_withdrawn')return true;
    $member=$members[(string)($notice['user']??'')]??null;
    return !is_array($member)||(string)($member['created']??'')!==(string)($notice['user_created']??'');
}
function wm_remove_member(string $uid,string $backupFile):array {
    // Payment -> members -> manager-state. Wait for any in-flight charge before withdrawal.
    // The lock inode remains in place so queued requests cannot revive the retired snapshot.
    $result=ab_lock($uid,function(string $dir)use($uid,$backupFile){
    return mg_member_tx(function(array &$members)use($uid,$backupFile,$dir){
        $record=$members[$uid]??null;
        if(!is_array($record))throw new RuntimeException('회원 정보를 확인할 수 없습니다. 다시 로그인해 주세요.');
        $json=json_encode($record,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(file_put_contents($backupFile,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('탈퇴 자료를 보관하지 못했습니다. 잠시 후 다시 시도해 주세요.');
        ab_retire_locked($uid,$dir,'member_withdrawn',(string)($record['created']??''));
        // Persist the event first. Readers expose it only after the members commit succeeds.
        mg_state_tx(function(array &$state)use($members,$uid,$record){
            wm_queue($members,$state,$uid,$record);
            // A failed QR-signup recovery must never restore a subsequently withdrawn account.
            foreach($state['qr_invites']??[] as $hash=>$invite){
                if(($invite['used_uid']??'')===$uid&&($invite['claim_member']['created']??null)===($record['created']??''))unset($state['qr_invites'][$hash]['claim_member']);
            }
        });
        unset($members[$uid]);
        return $record;
    });
    });
    try{require_once __DIR__.'/visit_push_common.php';vp_wake();}catch(Throwable $e){error_log('Withdrawal push deferred.');}
    return $result;
}
