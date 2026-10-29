<?php
declare(strict_types=1);
// Called only after authentication and CSRF checks; mutation runs under mg_member_tx.
function account_change_password(array &$members, string $uid, string $current, string $next, string $confirm, int $now): string {
    if (!isset($members[$uid]) || ($members[$uid]['status'] ?? 'active') !== 'active') return '계정 상태를 확인해 주세요.';
    $m =& $members[$uid];
    if (empty($m['pw_hash'])) return '이 계정은 비밀번호 변경을 지원하지 않습니다. 가입한 로그인 서비스를 이용해 주세요.';
    if (strlen($current)>1024 || strlen($next)<8 || strlen($next)>64 || strpos($next,"\0")!==false) return '새 비밀번호는 8~64바이트로 입력해 주세요. 영문·숫자는 8~64자입니다.';
    if ($next !== $confirm) return '새 비밀번호 확인이 일치하지 않습니다.';
    $limit=$m['_password_change_limit']??[];
    if (($limit['until']??0)>$now && ($limit['failures']??0)>=5) return '확인이 여러 번 실패했습니다. 15분 후 다시 시도해 주세요.';
    if (($limit['until']??0)<=$now) $limit=['until'=>$now+900,'failures'=>0];
    if (!password_verify($current,(string)$m['pw_hash'])) {
        $limit['failures']++; $m['_password_change_limit']=$limit;
        return '현재 비밀번호가 일치하지 않습니다.';
    }
    if (password_verify($next,(string)$m['pw_hash'])) return '현재 비밀번호와 다른 새 비밀번호를 입력해 주세요.';
    $m['pw_hash']=password_hash($next,PASSWORD_DEFAULT);
    $m['password_changed_at']=date('c',$now);
    unset($m['_password_change_limit']);
    return '';
}
