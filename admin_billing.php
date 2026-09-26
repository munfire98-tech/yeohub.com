<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Seoul');if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if((empty($_SESSION['is_admin'])&&(empty($_SESSION['ID_OK'])||$_SESSION['ID_OK']!=1))||!empty($_SESSION['_imp'])){http_response_code(403);exit('사이트 관리자만 이용할 수 있습니다.');}
require_once __DIR__.'/annual_billing_engine.php';
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$message='';$token='';$preview=null;
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('새로고침 후 다시 시도해 주세요.');
  $action=(string)($_POST['act']??'');
  if($action==='token'){$token=bin2hex(random_bytes(32));ab_scheduler_update(function($s)use($token){$s['token_hash']=hash('sha256',$token);return $s;});$message='새 실행 토큰을 발급했습니다. 아래 값은 이번에만 표시됩니다.';}
  elseif($action==='settings'){$on=($_POST['enabled']??'')==='1';$mode=ab_mode();if($on&&($_POST['confirm_mode']??'')!==$mode)throw new RuntimeException('현재 결제 모드와 실행 동의를 확인해 주세요.');ab_scheduler_update(function($s)use($on,$mode){$s['enabled']=$on;$s['mode']=$mode;return $s;});$message='자동결제 실행 설정을 저장했습니다.';}
  elseif($action==='preview'){$preview=ab_batch(false);$message='모의 실행 완료 · 결제 요청을 보내지 않았습니다.';}
  elseif($action==='run'){$preview=ab_batch(true);$message='갱신 대상 처리를 실행했습니다. 아래 결과를 확인하세요.';}
  elseif($action==='reset_test'){ab_reset_test_user((string)($_POST['uid']??''));$message='검증된 테스트 이용기록을 별도 보관했습니다. 라이브 전환 후 카드를 다시 등록해 주세요.';}
  elseif($action==='reconcile'){$uid=(string)($_POST['uid']??'');$d=ab_read($uid);$r=in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true)?ab_refund_user($uid,null,true):ab_charge_user($uid,'reconcile');$message=$r['ok']?'기존 결제·환불 결과를 반영했습니다.':$r['error'];}
  else throw new RuntimeException('지원하지 않는 작업입니다.');
 }
}catch(Throwable $e){$message=$e->getMessage();}
$settings=ab_scheduler_settings();try{$mode=ab_mode();}catch(Throwable $e){$mode='설정 확인 필요';}
$members=ab_read_file(__DIR__.'/data/members.json');$records=[];$due=0;$problems=0;
foreach(glob(__DIR__.'/data/subscribe/*/subscription.json')?:[] as $file){$uid=basename(dirname($file));try{$d=ab_read($uid);$why=ab_due_reason($uid,$d);if($why==='')$due++;if(!empty($d['billing_notice'])||($d['charge_attempt']['state']??'')==='unknown'||($d['status']??'')==='refund_pending')$problems++;$records[]=['uid'=>$uid,'d'=>$d,'why'=>$why];}catch(Throwable $e){$problems++;}}
function form_start(string $act):void{global $csrf;echo '<form method="post"><input type="hidden" name="csrf" value="'.h($csrf).'"><input type="hidden" name="act" value="'.h($act).'">';}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>연간 구독 결제 관리</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fa;color:#23364b;font:14px/1.7 system-ui}main{max-width:1120px;margin:auto;padding:32px 20px}h1{font-size:25px;margin:0}h2{font-size:16px;margin:0 0 12px}.muted{color:#738396;font-size:12px}.card{background:white;padding:22px;border:1px solid #e0e7ee;border-radius:14px;margin:18px 0}.stats{display:flex;gap:12px;flex-wrap:wrap}.stats .card{flex:1;min-width:180px}.stats b{display:block;font-size:26px}.tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center}button{background:#136e87;color:white;padding:9px 14px;border:0;border-radius:8px;cursor:pointer;font:600 13px system-ui}form{margin:0}code{overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:12px 8px;border-bottom:1px solid #e9eef3;vertical-align:top}td{overflow-wrap:anywhere}label{display:block;margin:10px 0}.notice{padding:13px;background:#fff5df;color:#825717;border-radius:9px}a{color:#087e98}.scroll{overflow:auto}button:focus-visible{outline:3px solid #79b8d4;outline-offset:3px}</style></head><body><main>
<h1>연간 구독 결제 관리</h1><p class="muted">연 59,000원 · 12개월 자동갱신 · 구독 동의와 결제일을 확인한 대상만 처리합니다.</p>
<?php if($message):?><p class="notice" role="status"><?=h($message)?></p><?php endif;?>
<div class="stats"><div class="card">현재 모드<b><?=h($mode)?></b></div><div class="card">갱신 대상<b><?=$due?>명</b></div><div class="card">확인 필요<b><?=$problems?>명</b></div></div>
<section class="card"><h2>자동 실행 설정</h2><p>실행 상태: <b><?=$settings['enabled']?'켜짐':'꺼짐'?></b> · 최근 실행: <?=h($settings['last_run']?:'아직 없음')?></p>
<?php if(empty($settings['last_run'])||strtotime($settings['last_run'])<time()-86400):?><p class="notice">최근 24시간 실행 기록이 없습니다. 서버 또는 외부 스케줄러가 연결되어 있는지 확인하세요.</p><?php endif;?>
<?php form_start('settings');?><label><input type="checkbox" name="enabled" value="1" <?=$settings['enabled']?'checked':''?>> 자동결제 실행 허용</label><label><input type="checkbox" name="confirm_mode" value="<?=h($mode)?>"> 현재 <?=h($mode)?> 모드로 실행되는 것을 확인했습니다. 라이브 모드는 실제 청구됩니다.</label><button>설정 저장</button></form>
<p class="muted">토스 심사 중에는 test 모드로만 검증하세요. 설정을 켜는 것만으로 스케줄러가 등록되지는 않습니다.</p>
</section>
<section class="card"><h2>실행 연결</h2><p>공유 웹호스팅은 외부 스케줄러에서 아래 경로로 HTTPS POST 요청을 설정하세요. 정기적으로 실행해도 결제일 전에는 청구하지 않습니다.</p><p><code>/annual_billing_run.php</code><br>헤더: <code>Authorization: Bearer 발급받은토큰</code><br>폼 본문: <code>mode=run</code> (점검은 <code>mode=dry-run</code>)</p><p class="muted">권장 주기: 1시간 · 한 실행은 약 5초 이후에는 새 작업을 시작하지 않으며, 남은 회원은 다음 실행에서 이어갑니다. 외부 요청 제한시간은 90초 이상으로 설정하세요. 토큰을 URL에 넣지 마세요.</p>
<?php form_start('token');?><button>실행 토큰 새로 발급</button></form><?php if($token):?><p class="notice"><code><?=h($token)?></code><br>외부 스케줄러의 비밀 헤더에 저장하세요. 토큰 재발급 시 기존 토큰은 무효화됩니다.</p><?php endif;?>
<p>서버 크론을 사용할 수 있다면: <code>php /사이트절대경로/annual_billing_run.php --run</code><br>청구 없는 확인: <code>php /사이트절대경로/annual_billing_run.php --dry-run</code></p>
</section>
<section class="card"><h2>대상 확인과 처리</h2><div class="tools"><?php form_start('preview');?><button>모의 실행 · 청구 없음</button></form><?php form_start('run');?><button onclick="return confirm('현재 설정 모드로 결제일이 된 동의 회원을 처리합니다. 라이브라면 실제 청구됩니다. 계속할까요?')">현재 모드로 갱신 실행</button></form></div>
<?php $results=$preview['results']??$settings['last_results']??[];if($results):?><details><summary>최근 처리 결과</summary><ul><?php foreach($results as $r):?><li><?=h($r['uid'])?> · <?=h($r['message'])?></li><?php endforeach;?></ul></details><?php endif;?></section>
<section class="card scroll"><h2>구독자</h2><table><thead><tr><th>회원</th><th>구독 상태</th><th>자동갱신</th><th>다음 결제일</th><th>처리 상태</th><th>확인</th></tr></thead><tbody><?php foreach($records as $r):$d=$r['d'];?><tr><td><?=h($members[$r['uid']]['nickname']??$r['uid'])?><br><?=h($r['uid'])?></td><td><?=h(ap_status($d))?></td><td><?=ab_auto($d)?'동의 완료':'해제·미동의'?></td><td><?=h($d['next_billing']??'—')?></td><td><?=h($d['billing_notice']??($r['why']?:'갱신 대상'))?><br><?=h($d['billing_mode']??'카드 재등록 필요')?><?php if(!empty($d['charge_attempt']['order_id'])):?><br><small>주문 <?=h($d['charge_attempt']['order_id'])?></small><?php endif;?></td><td><?php if(in_array($d['charge_attempt']['state']??'',['prepared','unknown'],true)||in_array($d['refund_attempt']['state']??'',['prepared','unknown'],true)):form_start('reconcile');?><input type="hidden" name="uid" value="<?=h($r['uid'])?>"><button>기존 결과 조회</button></form><?php endif;?><?php form_start('reset_test');?><input type="hidden" name="uid" value="<?=h($r['uid'])?>"><button style="margin-top:8px;background:#65798a" onclick="return confirm('테스트 결제 기록만 별도 보관하고 이용 상태·등록 카드를 초기화합니다. 실제 결제가 있거나 테스트임을 확인할 수 없으면 중단됩니다. 계속할까요?')">테스트 이용기록 분리</button></form></td></tr><?php endforeach;?></tbody></table></section>
<p class="muted">결제 결과가 불명확한 주문은 재청구하지 않습니다. 조회로 확인되지 않는 경우 토스 관리자에서 주문번호를 확인하고 저장 복구를 진행해야 합니다. 빌링키·결제키는 이 화면에 표시하지 않습니다.</p>
</main></body></html>
