<?php
declare(strict_types=1);
require_once __DIR__.'/user_key.php';
require_once __DIR__.'/annual_billing_engine.php';
function tb_conf():array{return ab_config();}
function tb_client_key():string{return ab_config()['client'];}
function tb_is_live():bool{return ab_config()['live'];}
function tb_ready():bool{try{ab_mode();return true;}catch(Throwable $e){return false;}}
function tb_dir():string{return app_user_key()!==''?ab_dir(app_user_key()):'';}
function tb_file():string{return tb_dir()!==''?tb_dir().'/subscription.json':'';}
function tb_read():array{return app_user_key()!==''?ab_read(app_user_key()):[];}
function tb_customer_key():string{return ab_customer(app_user_key());}
function tb_issue_billing_key(string $authKey,string $customerKey):array {
 if(!empty($_SESSION['_imp'])||defined('MANAGER_VIEW_UID'))return ab_result(false,'대리 보기에서는 카드를 등록할 수 없습니다.',true);
 return ab_register(app_user_key(),$authKey,$customerKey);
}
function tb_charge(int $amount,string $name):array {
 if($amount!==AP_PRICE||!empty($_SESSION['_imp'])||defined('MANAGER_VIEW_UID'))return ab_result(false,'결제 금액 또는 로그인 계정을 확인해 주세요.',true);
 return ab_charge_user(app_user_key());
}
function tb_cancel():bool{ab_renewal(app_user_key(),false);return true;}
function tb_next_billing(string $date,int $months,int $day):string{if($months!==12)throw new RuntimeException('연간 결제만 지원합니다.');return ab_next($date,$day);}
// Old endpoints must not replace a subscription snapshot outside the shared transaction.
function tb_write(array $d):bool{throw new RuntimeException('구독 저장은 새 연간 결제 화면을 이용해 주세요.');}
