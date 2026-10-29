<?php
declare(strict_types=1);

/** Shared, read-only ownership checks. Never acquire a billing lock from a member/state lock. */
function sa_valid_uid(string $uid): bool {
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $uid) === 1;
}
function sa_json(string $file): array {
    if (!is_file($file)) return [];
    $raw = file_get_contents($file);
    if ($raw === false) throw new RuntimeException('구독 정보를 읽지 못했습니다.');
    $data = json_decode(preg_replace('/^<\?php exit; \?>\s*/', '', $raw), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('구독 정보를 확인해 주세요.');
    return $data;
}
function sa_member(string $uid): array {
    if (!sa_valid_uid($uid)) return [];
    $m = sa_json(__DIR__.'/data/members.json')[$uid] ?? [];
    return is_array($m) ? $m : [];
}
function sa_legacy_conflict(string $uid, array $member): bool {
    // Legacy records have no account generation. A known withdrawal forbids silently
    // adopting them. Do not compare old timestamps: some were UTC and some were KST.
    if (!sa_valid_uid($uid)) return true;
    foreach (glob(__DIR__.'/data/_backups/withdrawn/'.$uid.'_*', GLOB_ONLYDIR) ?: [] as $dir) {
        $old = sa_json($dir.'/_member.json');
        if (!$old) $old = sa_json($dir.'/_meta.json')['member'] ?? [];
        // An attempted withdrawal can fail before removing the member. Its backup
        // has the same generation and must not suspend a legitimate subscription.
        if ($old && (string)($old['created'] ?? '') !== (string)($member['created'] ?? '')) return true;
    }
    return false;
}
function sa_matches(string $uid, array $sub, ?array $member = null): bool {
    if (!sa_valid_uid($uid) || !$sub || ($sub['status'] ?? '') === 'withdrawn') return false;
    $member = $member ?? sa_member($uid);
    if (($member['role'] ?? '') !== 'building' || ($member['status'] ?? 'active') !== 'active' || empty($member['created'])) return false;
    if (array_key_exists('account_created', $sub)) {
        return is_string($sub['account_created']) && hash_equals((string)$member['created'], $sub['account_created']);
    }
    return !sa_legacy_conflict($uid, $member);
}
function sa_read(string $uid): array {
    if (!sa_valid_uid($uid)) return [];
    $sub = sa_json(__DIR__.'/data/subscribe/'.$uid.'/subscription.json');
    return sa_matches($uid, $sub) ? $sub : [];
}
