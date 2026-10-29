<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/visit_push_common.php';require_once __DIR__.'/push_vendor/vendor/autoload.php';
if(is_file(vp_dir().'/config.php')){echo "VAPID keys already exist; preserved.\n";exit;}
$dir=vp_dir();if(!is_dir($dir))mkdir($dir,0700,true);$old=umask(0077);
try{$keys=Minishlink\WebPush\VAPID::createVapidKeys();$keys['subject']='https://www.xn--989ay50awvdzmk18f.com';$keys['origin']='https://www.xn--989ay50awvdzmk18f.com';$file=fopen($dir.'/config.php','x');if(!$file)throw new RuntimeException('Cannot create keys');fwrite($file,"<?php exit; ?>\n".json_encode($keys,JSON_THROW_ON_ERROR));fclose($file);echo "VAPID keys created in guarded server storage. No private key was printed.\n";}finally{umask($old);}
