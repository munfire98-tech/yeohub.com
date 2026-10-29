<?php
declare(strict_types=1);
// CLI only. The default is an audit; --apply never contacts a payment provider.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/annual_billing_engine.php';
$apply = in_array('--apply', $argv, true);
foreach (glob(__DIR__.'/data/subscribe/*/subscription.json') ?: [] as $file) {
    $uid = basename(dirname($file));
    if (!sa_valid_uid($uid)) continue;
    try {
        $result = ab_lock($uid, function(string $dir) use ($uid, $apply): string {
            return mg_member_tx(function(array &$members) use ($uid, $dir, $apply): string {
                $d = ab_read_file($dir.'/subscription.json');
                if (($d['status'] ?? '') === 'withdrawn') return 'already_retired';
                if (isset($members[$uid])) {
                    if (!sa_matches($uid, $d, $members[$uid])) return 'account_review_required';
                    if (!isset($d['account_created'])) {
                        if ($apply) { $d['account_created'] = (string)$members[$uid]['created']; ab_store($dir.'/subscription.json', $d); }
                        return $apply ? 'owner_bound' : 'would_bind_owner';
                    }
                    return 'current_account';
                }
                if (!(glob(__DIR__.'/data/_backups/withdrawn/'.$uid.'_*', GLOB_ONLYDIR) ?: [])) return 'orphan_review_required';
                if (ab_unsettled($d)) return 'payment_review_required';
                if ($apply) ab_retire_locked($uid, $dir, 'withdrawal_repair');
                return $apply ? 'retired_and_archived' : 'would_retire_and_archive';
            });
        });
        echo $uid.' '.$result.PHP_EOL;
    } catch (Throwable $e) {
        // No payment keys or provider responses in the report.
        fwrite(STDERR, $uid." storage_or_validation_error\n");
        exit(1);
    }
}
