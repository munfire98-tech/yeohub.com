<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';

/** Stable question IDs shared with jawi_chat.php, including grouped answers. */
function mjh_questions(): array {
    return [
        'edu_date'=>['edu_date'], 'edu_place'=>['edu_place'],
        'jawi_chief'=>['jawi_chief'], 'jawi_chief_tel'=>['jawi_chief_tel'],
        '__jawicount'=>['jawi_total','jawi_join'],
        '__jawiteam'=>['jawi_vice','jawi_call','jawi_fire','jawi_guide','jawi_emer'],
        'init_org'=>['init_org'], '__initcount'=>['init_total','init_join'],
        'edu_content'=>['edu_content'], 'edu_fix'=>['edu_fix'],
        'edu_action'=>['edu_action'], '__attend'=>['attend'],
    ];
}
function mjh_parse(string $field): ?array {
    if (!preg_match('/^__jw_([A-Za-z0-9]{1,40})_([A-Za-z0-9_]+)$/D', $field, $m)
        || !isset(mjh_questions()[$m[2]])) return null;
    return ['id'=>$m[1], 'step'=>$m[2]];
}
function mjh_file(string $uid, string $id): string {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid) || !preg_match('/^[A-Za-z0-9]{1,40}$/D',$id)) return '';
    return __DIR__.'/data/jawi/'.$uid.'/'.$id.'.json';
}
function mjh_valid(string $uid, string $field): bool {
    $q=mjh_parse($field);
    if (!$q || !($file=mjh_file($uid,$q['id'])) || !is_file($file)) return false;
    $record=mg_read($file);
    return (string)($record['id']??'')===$q['id'] && is_array($record['data']??null);
}
function mjh_answered(string $step, array $data): bool {
    $keys=mjh_questions()[$step]??[];
    $has=static fn($key)=>is_scalar($data[$key]??null) && trim((string)$data[$key])!=='';
    if ($step==='__attend') {
        foreach ((array)($data['attend']??[]) as $person) {
            if (is_array($person) && trim((string)($person['name']??''))!=='') return true;
        }
        return false;
    }
    if ($step==='__jawiteam') {
        foreach ($keys as $key) if ($has($key)) return true;
        return false;
    }
    if (!$keys) return false;
    foreach ($keys as $key) if (!$has($key)) return false;
    return true;
}

/** Save and resolve only answered questions, under the existing assignment/store locks. */
function mjh_save(string $uid, string $id, array $patch, callable $save): bool {
    $actor=(string)($_SESSION['_mge_actor']??'');
    if ($actor==='' && mg_uid()!==$uid) return $save(); // Existing administrator editing path.
    return mg_member_tx(function(array &$members)use($uid,$id,$patch,$save,$actor){
        if (!mg_active($members[$uid]??[],'building')) throw new RuntimeException('유저 계정을 확인해 주세요.');
        return mg_state_tx(function(array &$state)use($members,$uid,$id,$patch,$save,$actor){
            if ($actor!=='' && (!mg_active($members[$actor]??[],'agency') || !mg_can_view($actor,$uid,$members,$state)))
                throw new RuntimeException('담당 유저 연결을 확인해 주세요.');
            return mg_tx(__DIR__.'/data/manager_help_requests.php',function(array &$rows)use($members,$uid,$id,$patch,$save,$actor){
                $file=mjh_file($uid,$id);
                if (!$file || !is_file($file)) throw new RuntimeException('삭제된 교육·훈련 기록입니다. 목록에서 다시 열어 주세요.');
                if (!$save()) return false;
                $data=mg_read($file)['data']??[];
                foreach ($rows as &$row) {
                    $q=mjh_parse((string)($row['field']??''));
                    if (!$q || $q['id']!==$id || ($row['uid']??'')!==$uid || ($row['status']??'')!=='pending'
                        || ($row['user_created']??null)!==($members[$uid]['created']??null)
                        || ($row['link_key']??'')!==mg_link_key($uid,$members[$uid])
                        || ($row['manager']??'')!==mg_connection_manager($members[$uid],$members)) continue;
                    if (($row['manager_created']??null)!==($members[$row['manager']]['created']??null)) continue;
                    if ($actor!=='' && ($row['manager']??'')!==$actor) continue;
                    if (!array_intersect(array_keys($patch),mjh_questions()[$q['step']]) || !mjh_answered($q['step'],$data)) continue;
                    $row['status']='resolved'; $row['resolved_at']=date('c'); $row['resolved_by']=$actor?:$uid;
                    $row['resolved_kind']=$actor!==''?'manager':'user';
                    $row['reply']=($actor!==''?'연결 업체가':'유저가').' 요청한 자위소방대 교육·훈련 답변을 저장했습니다.';
                }
                unset($row);
                return true;
            },true);
        });
    });
}

/** Prune only a visible record confirmed absent from an accessible account directory. */
function mjh_prune(array &$rows, callable $visible): void {
    foreach ($rows as $key=>$row) {
        if (!is_array($row) || !$visible($row) || !($q=mjh_parse((string)($row['field']??'')))) continue;
        $file=mjh_file((string)($row['uid']??''),$q['id']);
        if (!$file || !is_dir(dirname($file)) || !is_readable(dirname($file))) continue;
        clearstatcache(true,$file);
        if (!file_exists($file)) unset($rows[$key]);
    }
}
