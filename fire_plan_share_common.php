<?php
declare(strict_types=1);
require_once __DIR__.'/manager_common.php';
require_once __DIR__.'/fire_plan_overview_common.php';
/** Annual capability links. No session is started or impersonated by this module. */
function fps_store():string { return __DIR__.'/data/fire_plan_shares.php'; }
function fps_uid(string $uid):bool { return (bool)preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid); }
function fps_plans(string $uid):array {
    if(!fps_uid($uid))return [];
    $base=__DIR__.'/data/fireplan/'.$uid.'/';$plans=[];
    foreach(mg_read($base.'_index.json') as $row){
        if(!is_array($row))continue;
        $id=(string)($row['id']??'');
        if(!preg_match('/^[0-9]{1,64}$/D',$id))continue;
        $plan=mg_read($base.$id.'.json');
        if(!$plan||(string)($plan['id']??'')!==$id)continue;
        $year=fp_plan_year($plan);$old=$plans[$year]??null;
        $rank=[(string)($plan['updated_at']??''),$id];
        if(!$old||$rank>[(string)($old['updated_at']??''),(string)$old['id']])$plans[$year]=$plan;
    }
    krsort($plans,SORT_NUMERIC);return $plans;
}
function fps_key(string $uid,string $created,int $year):string {return hash('sha256',$uid."\0".$created."\0".$year);}
function fps_active(array $row,string $uid,string $created,int $year):bool {
    return ($row['uid']??'')===$uid&&($row['account_created']??'')===$created
        &&($row['year']??0)===$year&&empty($row['revoked_at'])
        &&is_string($row['token']??null)&&(bool)preg_match('/^[a-f0-9]{64}$/D',$row['token']);
}
function fps_url(string $token):string {return 'https://www.xn--989ay50awvdzmk18f.com/fire_plan_shared.php?t='.$token;}
function fps_qr(string $url):string {
    require_once __DIR__.'/invite_vendor/qrcode.php';
    $q=new QRcode($url,'M');$b=$q->getBarcodeArray();$n=(int)$b['num_rows'];$path='';
    foreach($b['bcode'] as $y=>$line)foreach($line as $x=>$on)if($on)$path.='M'.($x+4).' '.($y+4).'h1v1h-1z';
    $size=$n+8;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="소방계획서 공유 QR" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><path d="'.$path.'" fill="#14243d"/></svg>';
}
function fps_headers():void {
    header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
}
function fps_public_plan(string $token):?array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    // Read the protected ledger once; the public token never becomes a filesystem path.
    foreach(mg_read(fps_store()) as $row){
        if(!is_array($row)||!is_string($row['token']??null)||!hash_equals($row['token'],$token))continue;
        $uid=(string)($row['uid']??'');$created=(string)($row['account_created']??'');$year=(int)($row['year']??0);
        if(!fps_uid($uid)||!fps_active($row,$uid,$created,$year))return null;
        $member=mg_members()[$uid]??[];
        if(!mg_active($member,'building')||$created===''||!hash_equals($created,(string)($member['created']??'')))return null;
        return fps_plans($uid)[$year]??null;
    }
    return null;
}
