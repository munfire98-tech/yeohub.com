<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

// Account memo, separate from admin notes and per-building manager notes.
require_once __DIR__.'/manager_common.php';

function mqm_account(array $members): array {
    $uid=mg_uid();$member=$members[$uid]??[];
    if($uid===''||!is_array($member)||!mg_active($member,'agency')||!empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']))return [];
    return ['uid'=>$uid,'created'=>(string)($member['created']??''),
        'scope'=>hash('sha256',json_encode([$uid,(string)($member['created']??'')],JSON_THROW_ON_ERROR))];
}
function mqm_file(array $account): string {
    return __DIR__.'/data/manager_quickmemos/'.$account['scope'].'.php';
}
function mqm_legacy(array $account,array $members): array {
    // Keep existing workspace notes, but never inherit a withdrawn account's notes.
    if(sa_legacy_conflict($account['uid'],$members[$account['uid']]))return [];
    $kakao=(string)($_SESSION['kakao_id']??'');
    $folder=$kakao!==''?'u_'.preg_replace('/[^0-9A-Za-z]/','',$kakao):'m_'.preg_replace('/[^0-9A-Za-z_]/','',$account['uid']);
    if($kakao==='')foreach($members as $id=>$member){
        if((string)$id!==$account['uid']&&'m_'.preg_replace('/[^0-9A-Za-z_]/','',(string)$id)===$folder)return [];
    }
    $data=mg_read(__DIR__.'/data/users/'.$folder.'/quickmemo.json');
    return ['text'=>is_string($data['text']??null)?$data['text']:'','updated'=>is_string($data['updated']??null)?$data['updated']:'','revision'=>0];
}
function mqm_load(array $account,array $members): array {
    $file=mqm_file($account);
    $memo=is_file($file)?mg_read($file):mqm_legacy($account,$members);
    return ['text'=>(string)($memo['text']??''),'updated'=>(string)($memo['updated']??''),'revision'=>(int)($memo['revision']??0)];
}
function mqm_save(array $account,array $members,array $input,string $csrf): array {
    if(!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf'])||!is_string($input['scope']??null)||!hash_equals($account['scope'],$input['scope']))throw new RuntimeException('로그인 정보가 바뀌었습니다. 새로고침해 주세요.',403);
    $text=$input['text']??null;$revision=$input['revision']??null;
    if(!is_string($text)||!mb_check_encoding($text,'UTF-8')||mb_strlen($text,'UTF-8')>10000||!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',$revision))throw new RuntimeException('메모는 10,000자 이내로 입력해 주세요.',400);
    return mg_tx(mqm_file($account),static function(array &$data)use($text,$revision,$account,$members):array{
        $current=($data?:mqm_legacy($account,$members))+['text'=>'','updated'=>'','revision'=>0];
        if((int)$revision!==(int)($current['revision']??0)){
            // Repeated transmission after a lost response is safe and idempotent.
            if($text===($current['text']??'')&&(int)($current['revision']??0)===(int)$revision+1)return $current;
            throw new RuntimeException('다른 창에서 메모가 바뀌었습니다. 입력 내용을 복사한 뒤 새로고침해 주세요.',409);
        }
        if($text===($current['text']??''))return $current;
        $data=['text'=>$text,'revision'=>(int)$revision+1,'updated'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Seoul')))->format('Y-m-d H:i')];
        return $data;
    },true);
}

$mqmDirect=realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__;
if($mqmDirect){
    if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    try{
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if(!in_array($method,['GET','POST'],true))throw new RuntimeException('지원하지 않는 요청입니다.',405);
        $members=mg_members();$account=mqm_account($members);
        if(!$account)throw new RuntimeException('파트너로 로그인해 주세요.',403);
        if(empty($_SESSION['manager_quickmemo_csrf']))$_SESSION['manager_quickmemo_csrf']=bin2hex(random_bytes(24));
        $csrf=(string)$_SESSION['manager_quickmemo_csrf'];session_write_close();
        $memo=$method==='POST'?mqm_save($account,$members,$_POST,$csrf):mqm_load($account,$members);
        echo json_encode(['ok'=>true,'scope'=>$account['scope'],'csrf'=>$csrf,'memo'=>$memo],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){
        $code=in_array($e->getCode(),[400,403,405,409],true)?$e->getCode():503;http_response_code($code);
        echo json_encode(['ok'=>false,'error'=>$code===503?'메모를 불러오거나 저장하지 못했습니다. 다시 시도해 주세요.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if(defined('MANAGER_QUICKMEMO_RENDERED')||empty($_SESSION['is_user'])||!empty($_SESSION['_imp']))return;
try{$mqmAccount=mqm_account(mg_members());}catch(Throwable $e){return;}
if(!$mqmAccount)return;
define('MANAGER_QUICKMEMO_RENDERED',true);
?>
<link rel="stylesheet" href="/manager_quickmemo.css?v=20261027-keyboard-cover-3">
<aside id="manager-quickmemo" data-scope="<?=htmlspecialchars($mqmAccount['scope'],ENT_QUOTES,'UTF-8')?>" aria-label="파트너 개인 메모">
  <section id="mqm-panel" class="mqm-panel" aria-labelledby="mqm-title" hidden>
    <header class="mqm-head"><div><strong id="mqm-title">내 메모</strong><p>나만 보는 파트너 메모</p></div><button type="button" class="mqm-close" aria-label="메모 접기">×</button></header>
    <label class="mqm-sr" for="mqm-text">메모 내용</label>
    <textarea id="mqm-text" maxlength="10000" placeholder="중요한 내용을 적어두세요. 입력을 멈추면 자동으로 저장됩니다." disabled></textarea>
    <footer class="mqm-foot"><span class="mqm-state" role="status" aria-live="polite">메모를 불러오는 중…</span><button type="button" class="mqm-save">저장</button></footer>
  </section>
  <button type="button" class="mqm-toggle" aria-expanded="false" aria-controls="mqm-panel"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 3h10l4 4v14H5zM14 3v5h5M8 12h8M8 16h6" stroke-linejoin="round"/></svg><span>내 메모</span><i class="mqm-dot" aria-hidden="true"></i></button>
</aside>
<script src="/manager_quickmemo.js?v=20261027-open-bottom-4" defer></script>
