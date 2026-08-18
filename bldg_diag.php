<?php
/* bldg_diag.php — 특정 주소가 왜 건축물대장 조회가 안 되는지 단계별 진단
 *
 * 각 단계의 결과를 전부 화면에 보여줌:
 *   [1] 카카오 장소검색 → 도로명주소·지번주소·좌표
 *   [2] juso 변환 (도로명주소로) → bun/ji  ← 여기서 bun=0 이면 원인
 *   [3] juso 변환 (지번주소로)   → bun/ji  ← 도로명 실패 시 대안
 *   [4] 건축HUB 조회 (총괄표제부 / 표제부) → 실제 응답
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

$API = [
  'kakao' => 'aea180f8a9ccf7395bccfb6dfbede9c6',
  'juso'  => 'U01TX0FVVEgyMDI2MDgwNzE4MzU1MzExOTkzNjg=',
  'hub'   => 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==',
  'kakao_url' => 'https://dapi.kakao.com/v2/local/search/keyword.json',
  'juso_url'  => 'https://business.juso.go.kr/addrlink/addrLinkApi.do',
  'hub_base'  => 'http://apis.data.go.kr/1613000/BldRgstHubService',
];

function hg(string $url, array $headers = []): array {
  $ch=curl_init($url);
  $h=array_merge(['Accept: application/json'],$headers);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_HTTPHEADER=>$h]);
  $b=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['body'=>(string)$b,'code'=>$c];
}

/* juso 변환 (원본 그대로 반환 — bun 이 0인지 보기 위함) */
function juso_raw(array $API, string $addr): array {
  $addr=trim($addr);
  $out=['input'=>$addr,'ok'=>false,'bun'=>'','ji'=>'','sigunguCd'=>'','bjdongCd'=>'','roadAddr'=>'','err'=>'','raw'=>null];
  if($addr===''){ $out['err']='(빈 주소)'; return $out; }
  $url=$API['juso_url'].'?'.http_build_query([
    'confmKey'=>$API['juso'],'currentPage'=>1,'countPerPage'=>1,'keyword'=>$addr,'resultType'=>'json']);
  $res=hg($url); $out['raw']=$res['body'];
  $j=json_decode($res['body'],true);
  $ec=$j['results']['common']['errorCode']??'?';
  if($ec!=='0'){ $out['err']='juso '.$ec.' '.($j['results']['common']['errorMessage']??''); return $out; }
  $a=$j['results']['juso'][0]??null;
  if(!$a){ $out['err']='결과 없음'; return $out; }
  $adm=(string)($a['admCd']??'');
  $out['ok']=true;
  $out['sigunguCd']=substr($adm,0,5);
  $out['bjdongCd']=substr($adm,5,5);
  $out['bun']=str_pad((string)($a['lnbrMnnm']??'0'),4,'0',STR_PAD_LEFT);
  $out['ji']=str_pad((string)($a['lnbrSlno']??'0'),4,'0',STR_PAD_LEFT);
  $out['roadAddr']=$a['roadAddr']??'';
  return $out;
}

/* 건축HUB 조회 */
function hub_call(array $API, string $op, array $code): array {
  $key=$API['hub']; if(strpos($key,'%')!==false)$key=urldecode($key);
  $u=$API['hub_base'].'/'.$op.'?'.http_build_query([
    'serviceKey'=>$key,'sigunguCd'=>$code['sigunguCd'],'bjdongCd'=>$code['bjdongCd'],
    'platGbCd'=>'0','bun'=>$code['bun'],'ji'=>$code['ji'],
    'numOfRows'=>100,'pageNo'=>1,'_type'=>'json']);
  $res=hg($u); $j=json_decode($res['body'],true);
  $items=$j['response']['body']['items']['item']??[];
  if(isset($items['bldNm'])||isset($items['platPlc']))$items=[$items];
  $cnt=is_array($items)?count($items):0;
  $msg=$j['response']['header']['resultMsg']??'';
  return ['op'=>$op,'count'=>$cnt,'msg'=>$msg,'body'=>$res['body'],'items'=>is_array($items)?$items:[]];
}

$q = trim($_POST['q'] ?? '');
$run = ($_SERVER['REQUEST_METHOD']==='POST' && $q!=='');
$kakao=[]; $steps=[];
if($run){
  // 1) 카카오 검색
  $url=$API['kakao_url'].'?'.http_build_query(['query'=>$q,'size'=>10]);
  $res=hg($url,['Authorization: KakaoAK '.$API['kakao']]);
  $j=json_decode($res['body'],true);
  $kakao=['code'=>$res['code'],'docs'=>$j['documents']??[],'body'=>$res['body']];
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
/* 지번주소 문자열에서 본번/부번 파싱 (예: "경기 파주시 탄현면 문지리 16-1" → 16,1) */
function parse_jibun(string $addr): array {
  // 끝부분의 "숫자-숫자" 또는 "숫자" 를 지번으로 봄. 산 여부도 감지
  $mtYn = (mb_strpos($addr,'산') !== false) ? '1' : '0';
  if (preg_match('/(\d+)\s*-\s*(\d+)\s*$/u', $addr, $m)) {
    return ['bun'=>$m[1], 'ji'=>$m[2], 'mtYn'=>$mtYn];
  }
  if (preg_match('/(\d+)\s*번?지?\s*$/u', $addr, $m)) {
    return ['bun'=>$m[1], 'ji'=>'0', 'mtYn'=>$mtYn];
  }
  // 중간에 있는 마지막 숫자쌍
  if (preg_match_all('/(\d+)(?:\s*-\s*(\d+))?/u', $addr, $mm, PREG_SET_ORDER)) {
    $last = end($mm);
    return ['bun'=>$last[1], 'ji'=>$last[2] ?? '0', 'mtYn'=>$mtYn];
  }
  return ['bun'=>'0','ji'=>'0','mtYn'=>$mtYn];
}
function pad4($s){ return str_pad((string)(int)$s, 4, '0', STR_PAD_LEFT); }
function pretty($b){$j=json_decode((string)$b,true);return $j!==null?json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):(string)$b;}

/* 특정 후보를 선택했을 때 상세 진단 — 여러 지번 후보를 다 시도 */
$pick = $_POST['pick'] ?? '';
$diag = null;
if($run && $pick!==''){
  $p=json_decode($pick,true);
  if(is_array($p)){
    $road=$p['road']??''; $jibun=$p['jibun']??'';
    $byRoad = juso_raw($API,$road);
    $byJibun= juso_raw($API,$jibun);

    // 시군구·법정동 코드는 juso 결과에서 (지번 성공 우선, 없으면 도로명)
    $base = $byJibun['ok'] ? $byJibun : ($byRoad['ok'] ? $byRoad : null);

    // 시도할 지번 후보들을 모은다 (중복 제거)
    $cands = [];
    $addCand = function($label,$bun,$ji,$mtYn) use (&$cands,$base){
      if(!$base) return;
      $bun4=pad4($bun); $ji4=pad4($ji);
      if($bun4==='0000') return;
      $key=$bun4.'-'.$ji4.'-'.$mtYn;
      foreach($cands as $c){ if($c['key']===$key) return; }
      $cands[]=['key'=>$key,'label'=>$label,
        'sigunguCd'=>$base['sigunguCd'],'bjdongCd'=>$base['bjdongCd'],
        'platGbCd'=>$mtYn==='1'?'1':'0','bun'=>$bun4,'ji'=>$ji4];
    };

    // 1) juso 지번 (본번-부번)
    if($byJibun['ok']) $addCand('juso 지번', (int)$byJibun['bun'], (int)$byJibun['ji'], '0');
    // 2) 카카오 지번 파싱
    $kj = parse_jibun($jibun);
    $addCand('카카오 지번', $kj['bun'], $kj['ji'], $kj['mtYn']);
    // 3) 카카오 지번의 본번만 (부번 제거 → 그 본번 아래 전체)
    $addCand('카카오 본번만', $kj['bun'], 0, $kj['mtYn']);
    // 4) juso 도로명 결과 지번
    if($byRoad['ok']) $addCand('juso 도로명', (int)$byRoad['bun'], (int)$byRoad['ji'], '0');
    // 5) juso 지번의 본번만
    if($byJibun['ok']) $addCand('juso 본번만', (int)$byJibun['bun'], 0, '0');

    // 각 후보로 건축HUB 조회 (총괄+표제부). 데이터 나오는 첫 후보가 정답
    $tries=[]; $winner=null;
    foreach($cands as $c){
      $recap=hub_call($API,'getBrRecapTitleInfo',$c);
      $title=hub_call($API,'getBrTitleInfo',$c);
      $found=($recap['count']>0)||($title['count']>0);
      $tries[]=['cand'=>$c,'recap'=>$recap,'title'=>$title,'found'=>$found];
      if($found && !$winner){ $winner=end($tries); }
    }
    $diag=['road'=>$road,'jibun'=>$jibun,'byRoad'=>$byRoad,'byJibun'=>$byJibun,
           'base'=>$base,'tries'=>$tries,'winner'=>$winner];
  }
}
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>건축물대장 조회 진단</title>
<style>
  body{font-family:'Malgun Gothic',sans-serif;background:#eef1f6;color:#1f2430;font-size:14px;line-height:1.6;padding:20px 14px 80px}
  .wrap{max-width:820px;margin:0 auto}
  h1{font-size:19px;font-weight:800;margin-bottom:12px}
  .card{background:#fff;border:1px solid #e3e7ee;border-radius:11px;padding:16px;margin-bottom:14px}
  input[type=text]{width:100%;border:1px solid #d6dce6;border-radius:9px;padding:11px 13px;font-size:14px;margin-bottom:10px}
  .btn{background:#3a5572;color:#fff;border:none;border-radius:9px;padding:10px 18px;font-size:14px;font-weight:700;cursor:pointer}
  .cand{border:1px solid #e3e7ee;border-radius:9px;padding:10px 12px;margin-bottom:7px;display:flex;justify-content:space-between;align-items:center;gap:10px}
  .cand .info{font-size:13px}
  .cand .road{font-weight:700}
  .cand .jibun{font-size:11.5px;color:#6b7280}
  .cand form{margin:0}
  .cand .btn{padding:7px 13px;font-size:12.5px}
  .ok{color:#137a3a;font-weight:700}.no{color:#c0392b;font-weight:700}
  table{width:100%;border-collapse:collapse;font-size:13px;margin:6px 0}
  th,td{border:1px solid #dbe0e8;padding:6px 9px;text-align:left}
  th{background:#f3f6fa;white-space:nowrap;width:32%}
  pre{background:#0f172a;color:#d1e0f5;border-radius:8px;padding:10px;overflow:auto;font-size:11px;max-height:260px;margin-top:6px}
  details{margin-top:6px}summary{cursor:pointer;color:#3a5572;font-weight:700;font-size:12px}
  .verdict{padding:11px 14px;border-radius:9px;margin-bottom:10px;font-weight:700}
  .verdict.g{background:#eefaf1;border:1px solid #bfe6cb;color:#137a3a}
  .verdict.r{background:#fdf1ef;border:1px solid #eebfb8;color:#a83224}
</style></head><body>
<div class="wrap">
  <h1>🔬 건축물대장 조회 진단</h1>
  <form method="post" class="card">
    <label>주소 또는 건물명</label>
    <input type="text" name="q" value="<?=h($q)?>" placeholder="예: 경기도 파주시 탄현면 방촌로995번길 56">
    <button class="btn" type="submit">1단계: 카카오 검색</button>
  </form>

<?php if($run): ?>
  <div class="card">
    <b>1단계 — 카카오 검색 결과</b> (HTTP <?=h((string)$kakao['code'])?>, <?=count($kakao['docs'])?>건)<br>
    <div style="margin-top:8px">
    <?php if(!$kakao['docs']): ?>
      <span class="no">카카오 결과 없음</span> — 이름/주소를 바꿔보세요.
    <?php else: foreach($kakao['docs'] as $d):
      $pk=json_encode(['road'=>$d['road_address_name']??'','jibun'=>$d['address_name']??''],JSON_UNESCAPED_UNICODE); ?>
      <div class="cand">
        <div class="info">
          <div class="road"><?=h($d['place_name']??'')?></div>
          <div class="jibun">도로명: <?=h($d['road_address_name']??'(없음)')?></div>
          <div class="jibun">지번: <?=h($d['address_name']??'(없음)')?></div>
        </div>
        <form method="post">
          <input type="hidden" name="q" value="<?=h($q)?>">
          <input type="hidden" name="pick" value='<?=h($pk)?>'>
          <button class="btn" type="submit">이걸로 진단 →</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if($diag): ?>
  <div class="card">
    <b>2단계 — juso 코드 변환</b> (시군구·법정동 코드 확보)
    <table>
      <tr><th></th><th>도로명주소로</th><th>지번주소로</th></tr>
      <tr><td>입력</td><td><?=h($diag['road'])?></td><td><?=h($diag['jibun'])?></td></tr>
      <tr><td>성공?</td>
        <td><?=$diag['byRoad']['ok']?'<span class=ok>OK</span>':'<span class=no>실패</span>'?></td>
        <td><?=$diag['byJibun']['ok']?'<span class=ok>OK</span>':'<span class=no>실패</span>'?></td></tr>
      <tr><td>sigunguCd / bjdongCd</td>
        <td><?=h($diag['byRoad']['sigunguCd'])?> / <?=h($diag['byRoad']['bjdongCd'])?></td>
        <td><?=h($diag['byJibun']['sigunguCd'])?> / <?=h($diag['byJibun']['bjdongCd'])?></td></tr>
    </table>
  </div>

  <div class="card">
    <b>3단계 — 여러 지번 후보로 건축HUB 조회</b>
    <?php if($diag['winner']): $w=$diag['winner']; ?>
      <div class="verdict g">✔ 정답 지번을 찾았습니다 —
        <b><?=h($w['cand']['label'])?></b> (bun=<?=h($w['cand']['bun'])?>, ji=<?=h($w['cand']['ji'])?>)
        · 총괄 <?=$w['recap']['count']?>건 / 표제부 <?=$w['title']['count']?>건</div>
    <?php else: ?>
      <div class="verdict r">✘ 모든 지번 후보로도 대장을 못 찾았습니다.
        이 건물은 API에 대장이 없거나, 지번이 목록 밖일 수 있습니다(직접 입력 필요).</div>
    <?php endif; ?>

    <table>
      <tr><th>시도한 지번</th><th>bun-ji</th><th>총괄</th><th>표제부</th><th>결과</th></tr>
      <?php foreach($diag['tries'] as $t): ?>
      <tr<?= $t['found']?' style="background:#f2fbf5"':'' ?>>
        <td><?=h($t['cand']['label'])?></td>
        <td><?=h($t['cand']['bun'])?>-<?=h($t['cand']['ji'])?></td>
        <td><?=$t['recap']['count']?></td>
        <td><?=$t['title']['count']?></td>
        <td><?= $t['found']?'<span class=ok>✔ 있음</span>':'<span class=no>0건</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php if($diag['winner']): $w=$diag['winner'];
      $items = $w['title']['count']>0 ? $w['title']['items'] : $w['recap']['items']; ?>
      <div style="margin-top:10px"><b>찾은 건물</b> (<?=count($items)?>동)</div>
      <table>
        <tr><th>동명칭</th><th>주용도</th><th>구조</th><th>지상/지하</th><th>연면적</th></tr>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?=h(trim((string)($it['dongNm']??'')) ?: '-')?></td>
          <td><?=h($it['mainPurpsCdNm']??'')?></td>
          <td><?=h($it['strctCdNm']??'')?></td>
          <td><?=h((string)(int)($it['grndFlrCnt']??0))?>/<?=h((string)(int)($it['ugrndFlrCnt']??0))?>층</td>
          <td><?=h($it['totArea']??'')?>㎡</td>
        </tr>
        <?php endforeach; ?>
      </table>
      <details><summary>찾은 응답 원문</summary><pre><?=h(pretty($w['title']['count']>0?$w['title']['body']:$w['recap']['body']))?></pre></details>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
</body></html>
