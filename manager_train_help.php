<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';

/** Stable question IDs shared with train_chat.php, including grouped answers. */
function mth_questions(): array {
    return [
        'fire_date'=>['fire_date'],
        'fire_place'=>['fire_place'],
        'fire_kind'=>['fire_kind'],
        'fire_teacher'=>['fire_teacher'],
        'fire_types'=>['fire_types'],
        'fire_c_sohwa'=>['fire_c_sohwa'],
        'fire_c_tongbo'=>['fire_c_tongbo'],
        'fire_c_pinan'=>['fire_c_pinan'],
        'fire_material'=>['fire_material'],
        'fire_result'=>['fire_result'],
        'fire_problem'=>['fire_problem'],
        'fire_improve'=>['fire_improve'],
        'edu_skip'=>['edu_skip'],
        'edu_date'=>['edu_date'],
        'edu_place'=>['edu_place'],
        'edu_teacher'=>['edu_teacher'],
        'edu_content'=>['edu_content'],
        'edu_result'=>['edu_result'],
        'edu_problem'=>['edu_problem'],
        'edu_improve'=>['edu_improve'],
        '__firecount'=>['fire_target','fire_join'], '__educount'=>['edu_target','edu_join'],
        '__photo'=>['photos'],
    ];
}
function mth_parse(string $field): ?array {
    if (!preg_match('/^__tr_([0-9]{1,40})_([A-Za-z0-9_]+)$/D', $field, $m)
        || !isset(mth_questions()[$m[2]])) return null;
    return ['id'=>$m[1], 'step'=>$m[2]];
}
function mth_file(string $uid, string $id): string {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid) || !preg_match('/^[0-9]{1,40}$/D',$id)) return '';
    return __DIR__.'/data/train/'.$uid.'/'.$id.'.json';
}
function mth_valid(string $uid, string $field): bool {
    $q=mth_parse($field);
    if (!$q || !($file=mth_file($uid,$q['id'])) || !is_file($file)) return false;
    $record=mg_read($file);
    return (string)($record['id']??'')===$q['id'] && is_array($record['data']??null);
}
function mth_answered(string $step, array $data): bool {
    $keys=mth_questions()[$step]??[];
    $has=static fn($key)=>is_scalar($data[$key]??null) && trim((string)$data[$key])!=='';
    if ($step==='edu_skip') return array_key_exists('edu_skip',$data) && in_array($data['edu_skip'],['','1'],true);
    if (($step==='__educount' || strpos($step,'edu_')===0) && ($data['edu_skip']??'')==='1') return false;
    $types=['fire_c_sohwa'=>'소화훈련','fire_c_tongbo'=>'통보훈련','fire_c_pinan'=>'피난훈련'];
    if (isset($types[$step]) && !in_array($types[$step],(array)($data['fire_types']??[]),true)) return false;
    if ($step==='fire_types') return (bool)array_intersect(['소화훈련','통보훈련','피난훈련'],(array)($data['fire_types']??[]));
    if ($step==='__photo') {
        foreach (['fire1','fire2','edu1','edu2'] as $slot) {
            if (trim((string)($data['photos'][$slot]??''))!=='') return true;
        }
        return false;
    }
    if (!$keys) return false;
    foreach ($keys as $key) if (!$has($key)) return false;
    return true;
}

/** Save and resolve only answered questions, under the existing assignment/store locks. */
function mth_save(string $uid, string $id, array $patch, callable $save): bool {
    $actor=(string)($_SESSION['_mge_actor']??'');
    if ($actor==='' && mg_uid()!==$uid) return $save(); // Existing administrator editing path.
    return mg_member_tx(function(array &$members)use($uid,$id,$patch,$save,$actor){
        if (!mg_active($members[$uid]??[],'building')) throw new RuntimeException('유저 계정을 확인해 주세요.');
        return mg_state_tx(function(array &$state)use($members,$uid,$id,$patch,$save,$actor){
            if ($actor!=='' && (!mg_active($members[$actor]??[],'agency') || !mg_can_view($actor,$uid,$members,$state)))
                throw new RuntimeException('담당 유저 연결을 확인해 주세요.');
            return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$uid,$id,$patch,$save,$actor){
                $file=mth_file($uid,$id);
                if (!$file || !is_file($file)) throw new RuntimeException('삭제된 교육·훈련 기록입니다. 목록에서 다시 열어 주세요.');
                if (!$save()) return false;
                $data=mg_read($file)['data']??[];
                foreach ($rows as &$row) {
                    $q=mth_parse((string)($row['field']??''));
                    if (!$q || $q['id']!==$id || ($row['uid']??'')!==$uid || ($row['status']??'')!=='pending'
                        || ($row['user_created']??null)!==($members[$uid]['created']??null)
                        || ($row['link_key']??'')!==mg_link_key($uid,$members[$uid])
                        || ($row['manager']??'')!==mg_connection_manager($members[$uid],$members)) continue;
                    if (($row['manager_created']??null)!==($members[$row['manager']]['created']??null)) continue;
                    if ($actor!=='' && ($row['manager']??'')!==$actor) continue;
                    if (!array_intersect(array_keys($patch),mth_questions()[$q['step']]) || !mth_answered($q['step'],$data)) continue;
                    $row['status']='resolved'; $row['resolved_at']=date('c'); $row['resolved_by']=$actor?:$uid;
                    $row['resolved_kind']=$actor!==''?'manager':'user';
                    $row['reply']=($actor!==''?'연결 업체가':'유저가').' 요청한 소방훈련·교육 답변을 저장했습니다.';
                }
                unset($row);
                return true;
            },true);
        });
    });
}

/** Prune only a visible record confirmed absent from an accessible account directory. */
function mth_prune(array &$rows, callable $visible): void {
    foreach ($rows as $key=>$row) {
        if (!is_array($row) || !$visible($row) || !($q=mth_parse((string)($row['field']??'')))) continue;
        $file=mth_file((string)($row['uid']??''),$q['id']);
        if (!$file || !is_dir(dirname($file)) || !is_readable(dirname($file))) continue;
        clearstatcache(true,$file);
        if (!file_exists($file)) unset($rows[$key]);
    }
}
