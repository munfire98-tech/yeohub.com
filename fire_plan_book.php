<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

/* MGE_APP_GUARD_V2 */ require_once __DIR__.'/manager_edit_guard.php';
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE)alp_session_start();
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_admin():bool{return !empty($_SESSION['is_admin'])||!empty($_SESSION['ID_OK']);}
if(!is_admin()&&empty($_SESSION['is_user'])){header('Location: /index.php');exit;}
if(!is_admin()&&($_SESSION['role']??'agency')!=='building'){header('Location: /partner_center.php');exit;}
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
require_once __DIR__.'/fire_plan_share_common.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
$bookCurrentYear=(int)date('Y');
$rawYear=$_GET['year']??(string)$bookCurrentYear;
if(!is_string($rawYear)||!preg_match('/^(19|20|21)[0-9]{2}$/D',$rawYear)){http_response_code(400);exit('올바른 연도를 선택해 주세요.');}
$bookYear=(int)$rawYear;
// A document view only reads saved snapshots. It never imports or creates a plan.
$bookPlans=fps_plans(fp_user_key());
$plan=$bookPlans[$bookYear]??null;
$bookYears=array_values(array_unique(array_merge([$bookCurrentYear-1,$bookCurrentYear,$bookCurrentYear+1,$bookYear],array_keys($bookPlans))));
sort($bookYears,SORT_NUMERIC);
$bookIndex=array_search($bookYear,$bookYears,true);
$bookContext=[];
foreach(['embed','modal'] as $key)if(($_GET[$key]??'')==='1')$bookContext[$key]='1';
if(is_admin()&&is_string($_GET['uid']??null))$bookContext['uid']=$_GET['uid'];
$bookLink=static function(string $page,array $query=[])use($bookContext):string{return '/'.$page.'?'.http_build_query($query+$bookContext);};
$bookManage=($_GET['print_view']??'')!=='1';$bookSources=null;$bookSourceError='';
$bookError=(string)($_SESSION['fp_book_error'][fp_user_key().':'.$bookYear]??'');unset($_SESSION['fp_book_error'][fp_user_key().':'.$bookYear]);
if($plan&&$bookManage){try{$bookSources=fpo_sources(bi_load(),epc_load(),$bookYear);}catch(Throwable $e){$bookSourceError='기존 자료를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';}}
$bookStatus=$plan?(fpo_status($plan)['reviewed']?'확인 완료':'작성 중'):'작성 전';
$bookView=true;$shareReadOnly=false;
define('FP_DOCUMENT_AUTHORIZED',true);
if($plan){require __DIR__.'/fire_plan_document.php';exit;}
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($bookYear)?>년 소방계획서</title>
<link rel="stylesheet" href="/fire_plan_book.css?v=20261010-2">
<link rel="stylesheet" href="/fire_plan_book_manage.css?v=20261021-1">
</head><body class="fp-book-empty">
<?php require __DIR__.'/fire_plan_book_toolbar.php'; ?>
<main class="fp-book-empty__body">
  <div class="fp-book-empty__icon" aria-hidden="true">▤</div>
  <span class="fp-book-empty__year"><?=h($bookYear)?>년</span>
  <h1>아직 저장된 계획서가 없어요</h1>
  <p>작성한 내용을 저장하면<br>이곳에서 한 권의 소방계획서로 볼 수 있어요.</p>
  <?php if($bookError):?><p role="alert"><?=h($bookError)?></p><?php endif; ?>
  <form method="post" action="<?=h($bookLink('fire_plan.php',['year'=>$bookYear]))?>"><input type="hidden" name="csrf" value="<?=h(fp_csrf())?>"><input type="hidden" name="act" value="create"><input type="hidden" name="return_book" value="1"><button class="fp-book-action is-primary" type="submit">계획서 시작하기 →</button></form>
</main>
</body></html>
