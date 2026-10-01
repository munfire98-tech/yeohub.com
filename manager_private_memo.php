<?php
declare(strict_types=1);
// Intentionally use the normal manager session, never the impersonated editing session.
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/manager_common.php';
header('Cache-Control: no-store');header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');
$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
try{
 if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true))throw new RuntimeException('지원하지 않는 요청입니다.',405);
 $actor=mg_uid();$uid=$_GET['uid']??'';
 if(!is_string($uid)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$uid))throw new RuntimeException('건물을 확인해 주세요.',400);
 $members=mg_members();
 if($actor===''||!mg_can_view($actor,$uid,$members,mg_read(mg_state_file())))throw new RuntimeException('담당 매니저만 볼 수 있습니다.',403);
 $key=hash('sha256',json_encode([$actor,$members[$actor]['created']??'', $uid,$members[$uid]['created']??'']));
 $path=__DIR__.'/data/manager_private_memos/'.$key.'.php';
 if(empty($_SESSION['private_memo_csrf']))$_SESSION['private_memo_csrf']=bin2hex(random_bytes(24));
 $csrf=$_SESSION['private_memo_csrf'];
 if($post){
  if(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('화면을 다시 열어 주세요.',403);
  $text=$_POST['text']??null;$rev=$_POST['revision']??'';
  if(!is_string($text)||mb_strlen($text)>10000||!is_string($rev)||!ctype_digit($rev))throw new RuntimeException('메모는 10,000자 이내로 입력해 주세요.',400);
  session_write_close();
  $saved=mg_tx($path,function(array &$s)use($text,$rev){if((int)$rev!==(int)($s['revision']??0))throw new RuntimeException('다른 창에서 변경했습니다. 입력 내용을 복사한 뒤 창을 다시 열어 주세요.',409);$s=['text'=>$text,'revision'=>(int)$rev+1,'updated'=>date('m/d H:i')];return $s;},true);
  header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'revision'=>$saved['revision'],'updated'=>$saved['updated']]);exit;
 }
 $memo=mg_read($path);session_write_close();$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="ko"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;padding:0 12px 12px;background:#fffbeb;color:#664c21;font:12px/1.5 system-ui}p{margin:0 0 8px;color:#8c7958}textarea{width:100%;height:155px;border:1px solid #ead8a7;border-radius:7px;background:#fffdf5;padding:10px;font:13px/1.6 system-ui;resize:none}footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:6px}button{border:0;border-radius:7px;background:#795a26;color:white;padding:7px 12px;cursor:pointer}#status{font-size:11px}</style>
<p>이 건물에 대한 나만의 메모 · 유저에게 보이지 않습니다.</p><textarea aria-label="매니저 전용 메모" maxlength="10000"><?=$e($memo['text']??'')?></textarea><footer><span id="status" role="status">저장 버튼을 눌러 보관하세요.</span><button type="button">저장</button></footer>
<script>
const ta=document.querySelector('textarea'),b=document.querySelector('button'),st=document.querySelector('#status');let revision=<?=json_encode((int)($memo['revision']??0))?>,saved=ta.value;
ta.oninput=()=>st.textContent=ta.value===saved?'저장됨':'저장하지 않은 내용';
b.onclick=async()=>{b.disabled=true;const value=ta.value;st.textContent='저장 중…';try{const r=await fetch(location.href,{method:'POST',body:new URLSearchParams({csrf:<?=json_encode($csrf)?>,revision:String(revision),text:value})});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'저장 실패');revision=j.revision;saved=value;st.textContent=ta.value===saved?j.updated+' 저장됨':'새 입력 내용을 저장해 주세요.';}catch(e){st.textContent=e.message;}finally{b.disabled=false;}};
window.addEventListener('beforeunload',e=>{if(ta.value!==saved){e.preventDefault();e.returnValue='';}});
</script></html>
<?php
}catch(Throwable $e){$code=in_array($e->getCode(),[400,403,405,409],true)?$e->getCode():503;http_response_code($code);$message=$code===503?'메모를 읽거나 저장하지 못했습니다.':$e->getMessage();if($post){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);}else echo htmlspecialchars($message,ENT_QUOTES,'UTF-8');}
