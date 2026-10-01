<?php
declare(strict_types=1);
function bes_document_path(string $base,string $id):string{return substr($base,0,-4).'-'.$id.'.php';}
function bes_library_page(string $path,string $uid,string $csrf,string $actor,bool $post):void{
 if($post&&(!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf'])))throw new RuntimeException('창을 다시 열어 주세요.',403);
 session_write_close();
 $result=mg_tx($path,function(array &$s)use($path,$post,$actor){
  if(!isset($s['documents'])){
   $id=bin2hex(random_bytes(12));$old=$s;
   if(!empty($old['map']))mg_tx(bes_document_path($path,$id),function(array &$d)use($old){$d=$old;return $d;},true);
   $s['documents']=[$id=>['name'=>'기존 도면','legacy'=>true,'created'=>date('c')]];
  }
  if(!$post)return $s['documents'];
  $action=$_POST['action']??'';$id=$_POST['id']??'';
  if(!is_string($id)||($id!==''&&!isset($s['documents'][$id])))throw new RuntimeException('도면 목록을 새로 열어 주세요.',409);
  if(!in_array($action,['create','copy','rename','delete'],true))throw new RuntimeException('요청을 확인해 주세요.',400);
  if($action!=='create'&&$id==='')throw new RuntimeException('도면을 선택해 주세요.',400);
  if($action==='delete'){unset($s['documents'][$id]);return '';}
  $name=is_string($_POST['name']??null)?trim($_POST['name']):'';
  if($name===''||mb_strlen($name)>60)throw new RuntimeException('도면 이름은 1~60자로 입력해 주세요.',400);
  if($action==='rename'){$s['documents'][$id]['name']=$name;return $id;}
  if(count($s['documents'])>=30)throw new RuntimeException('도면은 최대 30개까지 보관할 수 있습니다.',400);
  $new=bin2hex(random_bytes(12));$entry=['name'=>$name,'created'=>date('c')];
  if($action==='copy'){
   $data=mg_read(bes_document_path($path,$id));
   if(empty($data['map']))throw new RuntimeException('먼저 원본 도면을 만들고 저장해 주세요.',400);
   $data['revision']=0;$data['updated_by']=$actor;
   mg_tx(bes_document_path($path,$new),function(array &$d)use($data){$d=$data;return $d;},true);
  }
  $s['documents'][$new]=$entry;return $new;
 },true);
 if($post){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'id'=>$result]);return;}
 $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
 ?>
 <!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>내 도면</title>
 <style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#23354a;font:15px/1.6 system-ui}main{max-width:960px;margin:40px auto;padding:24px}header{display:flex;justify-content:space-between;align-items:center;gap:20px}h1{margin:4px 0}p{color:#61738b}button,input{font:inherit;border-radius:10px;padding:11px 16px;border:1px solid #d7e0ec;background:white;color:#304968;cursor:pointer}button.primary{background:#285fe8;color:white;border:0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;margin-top:26px}article{padding:22px;background:white;border:1px solid #dde5f0;border-radius:18px}h2{font-size:18px;overflow-wrap:anywhere}article div{display:flex;gap:7px;flex-wrap:wrap;margin-top:16px}article div button{font-size:13px;padding:7px 10px}dialog{margin:auto;border:0;border-radius:18px;padding:28px;width:min(440px,90vw)}dialog::backdrop{background:#172d4e88}input{width:100%;cursor:text}footer{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}#error{color:#b42318}small{color:#61738b}</style></head><body><main>
 <header><div><small>MY DRAWINGS</small><h1>내 도면 보관함</h1></div><button id="close">닫기</button></header>
 <p>도면을 만들어 보관하고, 준비되면 시뮬레이션을 실행하세요.<br>하나의 도면에 같은 건물의 여러 층을 함께 만들 수 있습니다.</p>
 <button class="primary" id="new">＋ 새 도면 만들기</button><p id="error" role="alert"></p><div class="grid">
 <?php foreach($result as $id=>$row): ?><article data-id="<?=$esc($id)?>"><small>내 건물 도면</small><h2><?=$esc($row['name'])?></h2><button class="primary" data-open>도면 열기</button><div><button data-action="copy">복사</button><button data-action="rename">이름 변경</button><button data-action="delete">삭제</button></div></article><?php endforeach; ?>
 </div><dialog id="edit"><form><h2 id="heading"></h2><label>도면 이름<input maxlength="60" required placeholder="예: 본관 · 전체 층"></label><footer><button type="button" id="cancel">취소</button><button class="primary">확인</button></footer></form></dialog></main>
 <script>
 const csrf=<?=json_encode($csrf)?>,dialog=document.querySelector('dialog'),input=dialog.querySelector('input'),error=document.querySelector('#error');let action='',id='',busy=false;
 async function submit(){if(busy)return;busy=true;try{const r=await fetch(location.href,{method:'POST',body:new URLSearchParams({csrf,action,id,name:input.value})});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'저장하지 못했습니다.');if(action==='create'||action==='copy')openDoc(d.id);else location.reload();}catch(e){error.textContent=e.message;dialog.close();}finally{busy=false;}}
 function openDoc(id){const u=new URL(location.href);u.searchParams.set('doc',id);location.href=u;}
 function edit(a,i,name){action=a;id=i;input.value=name;document.querySelector('#heading').textContent={create:'새 도면 만들기',copy:'도면 복사',rename:'이름 변경'}[a];dialog.showModal();input.focus();}
 document.querySelector('#new').onclick=()=>edit('create','','');document.querySelector('#cancel').onclick=()=>dialog.close();dialog.querySelector('form').onsubmit=e=>{e.preventDefault();submit();};
 document.querySelectorAll('article').forEach(card=>{card.querySelector('[data-open]').onclick=()=>openDoc(card.dataset.id);card.querySelectorAll('[data-action]').forEach(b=>b.onclick=()=>{const a=b.dataset.action;if(a==='delete'){if(!confirm('이 도면을 보관함에서 삭제할까요? 다른 도면에는 영향이 없습니다.'))return;action=a;id=card.dataset.id;submit();}else edit(a,card.dataset.id,card.querySelector('h2').textContent+(a==='copy'?' 복사본':''));});});
 document.querySelector('#close').onclick=()=>{if(parent!==window)parent.postMessage({type:'building-evac-close'},location.origin);else location.href='/building_manager.php';};
 </script></body></html>
 <?php
}
