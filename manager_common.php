<?php
declare(strict_types=1);
/* No session impersonation. The actor always comes from the authenticated session. */
function mg_uid(): string {
    if (empty($_SESSION['is_user']) || !empty($_SESSION['_imp'])) return '';
    $u = (string)($_SESSION['member_id'] ?? '');
    if ($u === '' && !empty($_SESSION['kakao_id'])) $u = 'kakao_' . $_SESSION['kakao_id'];
    return preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $u) ? $u : '';
}
function mg_read(string $file): array {
    if (!is_file($file)) return [];
    $raw = file_get_contents($file);
    if ($raw === false) throw new RuntimeException('저장 정보를 읽을 수 없습니다.');
    $raw = preg_replace('/^<\?php exit; \?>\s*/', '', $raw);
    $a = json_decode($raw, true);
    if (!is_array($a)) throw new RuntimeException('저장 정보가 손상되었습니다. 관리자에게 문의해 주세요.');
    return $a;
}
function mg_tx(string $file, callable $fn, bool $guard = false) {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('저장 폴더를 만들 수 없습니다.');
    $lock = fopen($file . '.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('저장소를 잠글 수 없습니다.');
    $tmp = false;
    try {
        $a = mg_read($file); $before = $a; $result = $fn($a);
        if ($before !== $a) {
            $json = json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $tmp = tempnam($dir, '.manager_');
            if ($tmp === false || file_put_contents($tmp, ($guard ? "<?php exit; ?>\n" : '') . $json) === false || !rename($tmp, $file)) throw new RuntimeException('저장에 실패했습니다.');
        }
        return $result;
    } finally {
        if ($tmp && is_file($tmp)) unlink($tmp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
function mg_members(): array { return mg_read(__DIR__ . '/data/members.json'); }
function mg_member_tx(callable $fn) { return mg_tx(__DIR__ . '/data/members.json', $fn); }
function mg_state_file(): string { return __DIR__ . '/data/manager_system.php'; }
function mg_state_tx(callable $fn) { return mg_tx(mg_state_file(), $fn, true); }
function mg_active(array $m, string $role): bool { return ($m['role'] ?? '') === $role && ($m['status'] ?? 'active') === 'active'; }
function mg_code(array $members): string {
    do { $code = 'FM-' . strtoupper(bin2hex(random_bytes(5))); }
    while (in_array($code, array_column($members, 'manager_code'), true));
    return $code;
}
function mg_registration(array $members, string $uid, string $role, string $code, bool $consent): array {
    $code = strtoupper(trim($code));
    if ($role === 'agency') return ['ok'=>true, 'fields'=>['manager_code'=>mg_code($members)]];
    if ($code === '') return ['ok'=>true, 'fields'=>[]];
    if (!$consent) return ['ok'=>false, 'error'=>'매니저에게 건물정보와 업무 진행 현황을 공유하는 데 동의해 주세요.'];
    foreach ($members as $id=>$m) {
        if (!is_array($m) || !mg_active($m, 'agency') || ($m['manager_code'] ?? '') !== $code || (string)$id === $uid) continue;
        return ['ok'=>true, 'fields'=>['referrer_uid'=>(string)$id, 'referrer_created'=>(string)($m['created'] ?? ''), 'referral_at'=>date('c'), 'manager_consent_at'=>date('c')]];
    }
    return ['ok'=>false, 'error'=>'사용 가능한 매니저 코드가 아닙니다. 코드를 다시 확인해 주세요.'];
}
function mg_referrer(array $member, array $members): string {
    $id = (string)($member['referrer_uid'] ?? '');
    $m = $members[$id] ?? [];
    return $id !== '' && mg_active($m, 'agency') && (string)($m['created'] ?? '') === (string)($member['referrer_created'] ?? '') ? $id : '';
}
function mg_link_key(string $uid, array $m): string {
    return $uid . ':' . hash('sha256', (string)($m['created'] ?? '')) . (!empty($m['manager_request_id']) ? ':'.$m['manager_request_id'] : '');
}
/* Connection assignment can change; the first reward referral remains immutable. */
function mg_connection_manager(array $m, array $members): string {
    if (!array_key_exists('assigned_manager_uid',$m)) return mg_referrer($m,$members);
    $id = (string)$m['assigned_manager_uid']; $manager = $members[$id] ?? [];
    return mg_active($manager,'agency') && (string)($manager['created'] ?? '') === (string)($m['assigned_manager_created'] ?? '') ? $id : '';
}
function mg_has_paid(string $uid): bool {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)) throw new RuntimeException('회원 정보를 확인해 주세요.');
    $d = mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
    if (!empty($d['manager_first_payment'])) return true;
    $seenTestSuccess = false;
    foreach ((array)($d['history'] ?? []) as $r) {
        if (!is_array($r) || (int)($r['amount'] ?? 0) <= 0 || (array_key_exists('ok',$r) && !$r['ok'])) continue;
        if (in_array((string)($r['type'] ?? ''),['refund','refund_pending','refund_failed','cancel','plan_change'],true)) continue;
        if (!empty($r['test'])) { $seenTestSuccess = true; continue; }
        return true; // unmarked legacy successful payment is conservatively treated as live
    }
    return !$seenTestSuccess && trim((string)($d['paid_at'] ?? '')) !== '';
}
function mg_request_manager(string $actor, string $code, bool $consent): void {
    mg_member_tx(function(array &$members) use ($actor,$code,$consent) {
        $me = $members[$actor] ?? [];
        if (!mg_active($me,'building')) throw new RuntimeException('건물관리자 계정에서 등록해 주세요.');
        $registration = mg_registration($members,$actor,'building',$code,$consent);
        if (!$registration['ok']) throw new RuntimeException($registration['error']);
        if (empty($registration['fields']['referrer_uid'])) throw new RuntimeException('매니저 코드를 입력해 주세요.');
        mg_state_tx(function(array &$state) use (&$members,$actor,$me,$registration) {
            $current = mg_connection_manager($me,$members);
            if ($current !== '' && in_array(mg_link_status($actor,$me,$state),['pending','accepted'],true)) {
                throw new RuntimeException('이미 연결 요청 중이거나 연결된 매니저가 있습니다. 먼저 요청을 취소하거나 연결을 해제해 주세요.');
            }
            $fields = $registration['fields'];
            if (empty($me['referrer_uid'])) {
                $members[$actor] = array_merge($me,$fields);
                $members[$actor]['referral_reward_eligible'] = !mg_has_paid($actor);
            }
            $members[$actor]['assigned_manager_uid'] = $fields['referrer_uid'];
            $members[$actor]['assigned_manager_created'] = $fields['referrer_created'];
            $members[$actor]['manager_requested_at'] = date('c');
            $members[$actor]['manager_consent_at'] = date('c');
            $members[$actor]['manager_request_id'] = bin2hex(random_bytes(16));
            // Fresh request key defaults to pending. No separate partial state write is needed.
        });
    });
}
function mg_link_status(string $uid, array $m, array $state): string {
    return (string)($state['links'][mg_link_key($uid, $m)]['status'] ?? 'pending');
}
function mg_can_view(string $actor, string $target, array $members, array $state): bool {
    $m = $members[$target] ?? [];
    return $actor !== '' && mg_active($m, 'building') && mg_connection_manager($m, $members) === $actor && mg_link_status($target, $m, $state) === 'accepted';
}
function mg_decide(string $actor, string $target, string $action, string $requestKey = ''): void {
    mg_member_tx(function(array &$members) use ($actor,$target,$action,$requestKey) {
        $m = $members[$target] ?? [];
        $isManager = mg_connection_manager($m,$members) === $actor && mg_active($members[$actor] ?? [],'agency');
        $isOwner = $actor === $target && mg_active($m,'building');
        if (!$isManager && !$isOwner) throw new RuntimeException('접근 권한이 없습니다.');
        if ($requestKey !== '' && !hash_equals(mg_link_key($target,$m),$requestKey)) throw new RuntimeException('연결 요청이 변경되었습니다. 새로고침 후 다시 시도해 주세요.');
        mg_state_tx(function(array &$s) use ($target,$m,$action,$isManager,$isOwner,$actor,$members) {
            $old = mg_link_status($target,$m,$s);
            $valid = ($isManager && $old === 'pending' && in_array($action,['accepted','rejected'],true))
                || (($isManager || $isOwner) && in_array($old,['accepted','pending'],true) && $action === 'revoked');
            if (!$valid) throw new RuntimeException('이미 처리되었거나 허용되지 않은 요청입니다.');
            $s['links'][mg_link_key($target,$m)] = ['status'=>$action,'at'=>date('c'),'actor'=>$actor];
            if($isOwner && $action==='revoked'){
                $manager=mg_connection_manager($m,$members);
                if($manager!==''){
                    $id=hash('sha256',mg_link_key($target,$m).':owner_revoked');
                    $s['connection_notifications'][$id]=['id'=>$id,'manager'=>$manager,'manager_created'=>(string)($members[$manager]['created']??''),'user'=>$target,'name'=>(string)($m['nickname']??$target),'kind'=>$old==='accepted'?'disconnected':'request_cancelled','at'=>date('c'),'read_at'=>null];
                }
            }

        });
    });
}
/* Trusted server payment result only. There is deliberately no HTTP award endpoint. */
function mg_award(string $uid, array $receipt): void {
    if (($receipt['live'] ?? false) !== true || ($receipt['status'] ?? '') !== 'DONE' || (int)($receipt['amount'] ?? 0) <= 0 || empty($receipt['payment_key']) || empty($receipt['order_id'])) return;
    $members = mg_members(); $m = $members[$uid] ?? [];
    $manager = mg_referrer($m,$members);
    if (($m['referral_reward_eligible'] ?? true) === false || !mg_active($m,'building') || $manager === '' || empty($m['referral_at']) || empty($m['manager_consent_at'])) return;
    if (strtotime((string)$m['referral_at']) > strtotime((string)($receipt['at'] ?? ''))) return;
    mg_state_tx(function(array &$s) use ($uid,$manager,$receipt,$members) {
        if (isset($s['rewards'][$uid])) return; // lifetime UID dedup, including refunded rewards
        $pk = (string)$receipt['payment_key'];
        foreach ($s['rewards'] ?? [] as $r) if (($r['payment_key'] ?? '') === $pk) return;
        $s['rewards'][$uid] = ['manager'=>$manager,'manager_created'=>(string)($members[$manager]['created'] ?? ''),'payment_key'=>$pk,'order_id'=>$receipt['order_id'],'at'=>$receipt['at'],'reversed'=>isset($s['refunds'][$pk])];
    });
}
function mg_reverse(string $uid, string $paymentKey): void {
    if ($paymentKey === '') return;
    mg_state_tx(function(array &$s) use ($uid,$paymentKey) {
        $s['refunds'][$paymentKey] = ['uid'=>$uid,'at'=>date('c')];
        if (($s['rewards'][$uid]['payment_key'] ?? '') === $paymentKey) $s['rewards'][$uid]['reversed'] = true;
    });
}
function mg_reconcile(string $uid): void {
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid)) return;
    $d = mg_read(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
    foreach ($d['manager_full_refunds'] ?? [] as $pk) mg_reverse($uid,(string)$pk);
    if (!empty($d['manager_first_payment'])) mg_award($uid,$d['manager_first_payment']);
}
function mg_e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
