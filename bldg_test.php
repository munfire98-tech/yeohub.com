<?php
/* bldg_test.php — 건축물대장 총괄표제부 조회 "작동 원리 확인용" 테스트 페이지
 *
 * 흐름:  주소 입력  →  ①juso.go.kr 로 시군구/법정동코드·본번·부번 얻기
 *                     →  ②건축HUB 건축물대장 총괄표제부 API 호출
 *                     →  각 단계 원시 응답(raw)까지 화면에 보여줌
 *
 * 목적: 데이터가 제대로 들어오는지 눈으로 검증한 뒤, 실제 도구에 이식하기 위함.
 *  - 실제 서비스에 붙이기 전, 두 API 키가 살아있는지/응답 필드가 뭔지 확인하는 용도.
 *  - 키는 아래 두 상수에 직접 넣거나, 화면 입력칸에 넣어 테스트할 수 있음.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

/* ────────────────────────────────────────────────────────────
 * 0) API 키
 *   - JUSO_KEY : 주소 → 좌표/코드 변환.  https://business.juso.go.kr 에서 "도로명주소 검색 API" 발급
 *   - HUB_KEY  : 건축물대장 조회.        https://www.data.go.kr/data/15134735/openapi.do 활용신청
 *   테스트 중에는 화면 입력칸으로 덮어쓸 수 있게 해둠(코드에 안 박아도 됨).
 * ──────────────────────────────────────────────────────────── */
const JUSO_KEY = '';   // 예: devU01TX0FVVEg2...  (여기 비워두고 화면에서 입력해도 됨)
const HUB_KEY  = '';   // 공공데이터포털 일반 인증키(Decoding 키 권장)

$jusoKey = trim($_POST['juso_key'] ?? '') ?: JUSO_KEY;
$hubKey  = trim($_POST['hub_key']  ?? '') ?: HUB_KEY;
$addr    = trim($_POST['addr'] ?? '');

/* 공통 GET 요청 (cURL) — 각 단계의 원시 응답과 상태코드를 함께 돌려줌 */
function http_get(string $url): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,   // 공공 API 인증서 이슈 회피(테스트용)
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  ]);
  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['body' => (string)$body, 'err' => $err, 'code' => $code, 'url' => $url];
}

/* ── ① 주소 → 코드 (juso.go.kr) ────────────────────────────────
 *  응답 juso[0] 에서:
 *    admCd(10자리 법정동코드) → 앞 5 = sigunguCd, 뒤 5 = bjdongCd
 *    lnbrMnnm = 본번, lnbrSlno = 부번, mtYn(0/1) = 대지/산 구분
 */
function step_juso(string $key, string $addr): array {
  $url = 'https://business.juso.go.kr/addrlink/addrLinkApi.do?'
       . http_build_query([
           'confmKey'    => $key,
           'currentPage' => 1,
           'countPerPage'=> 5,
           'keyword'     => $addr,
           'resultType'  => 'json',
         ]);
  $res  = http_get($url);
  $json = json_decode($res['body'], true);
  $out  = ['raw' => $res, 'parsed' => null, 'candidates' => [], 'msg' => ''];

  $common = $json['results']['common'] ?? [];
  if (($common['errorCode'] ?? '') !== '0') {
    $out['msg'] = 'juso 오류: ' . ($common['errorCode'] ?? '?') . ' ' . ($common['errorMessage'] ?? '');
    return $out;
  }
  $list = $json['results']['juso'] ?? [];
  if (!$list) { $out['msg'] = '주소 검색 결과가 없습니다.'; return $out; }

  $out['candidates'] = $list;                 // 후보 여러 개 그대로 보관(사용자 선택용)
  $j = $list[0];                              // 테스트는 첫 후보 사용
  $adm = (string)($j['admCd'] ?? '');
  $out['parsed'] = [
    'roadAddr'  => $j['roadAddr']  ?? '',
    'jibunAddr' => $j['jibunAddr'] ?? '',
    'sigunguCd' => substr($adm, 0, 5),
    'bjdongCd'  => substr($adm, 5, 5),
    'platGbCd'  => (($j['mtYn'] ?? '0') === '1') ? '1' : '0',   // 0:대지 1:산
    'bun'       => str_pad((string)($j['lnbrMnnm'] ?? '0'), 4, '0', STR_PAD_LEFT),
    'ji'        => str_pad((string)($j['lnbrSlno'] ?? '0'), 4, '0', STR_PAD_LEFT),
  ];
  return $out;
}

/* ── ② 코드 → 건축물대장 총괄표제부 (건축HUB) ───────────────── */
function step_hub(string $key, array $p): array {
  $url = 'https://apis.data.go.kr/1613000/BldRgstService_v2/getBrRecapTitleInfo?'
       . http_build_query([
           'serviceKey' => $key,   // Decoding 키를 쓰면 http_build_query가 다시 인코딩해 정상 동작
           'sigunguCd'  => $p['sigunguCd'],
           'bjdongCd'   => $p['bjdongCd'],
           'platGbCd'   => $p['platGbCd'],
           'bun'        => $p['bun'],
           'ji'         => $p['ji'],
           'numOfRows'  => 10,
           'pageNo'     => 1,
           '_type'      => 'json',
         ]);
  $res  = http_get($url);
  $json = json_decode($res['body'], true);
  $out  = ['raw' => $res, 'items' => [], 'msg' => ''];

  $header = $json['response']['header'] ?? [];
  if (($header['resultCode'] ?? '') !== '00' && ($header['resultCode'] ?? '') !== '0') {
    $out['msg'] = 'HUB 응답코드: ' . ($header['resultCode'] ?? '?') . ' ' . ($header['resultMsg'] ?? '');
  }
  $items = $json['response']['body']['items']['item'] ?? [];
  if (isset($items['mgmBldrgstPk']) || isset($items['bldNm'])) $items = [$items]; // 단건이면 배열로
  $out['items'] = is_array($items) ? $items : [];
  if (!$out['items'] && $out['msg']==='') $out['msg'] = '총괄표제부 결과가 없습니다(해당 지번에 총괄표제부가 없을 수 있음).';
  return $out;
}

/* 실행 */
$run = ($_SERVER['REQUEST_METHOD'] === 'POST' && $addr !== '');
$s1 = $s2 = null;
if ($run) {
  if ($jusoKey === '' || $hubKey === '') {
    $globalMsg = '두 API 키를 모두 입력해야 합니다.';
  } else {
    $s1 = step_juso($jusoKey, $addr);
    if ($s1['parsed']) $s2 = step_hub($hubKey, $s1['parsed']);
  }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function pretty($body){
  $j = json_decode((string)$body, true);
  return $j !== null ? json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : (string)$body;
}

/* 총괄표제부 주요 필드 라벨 (있는 것만 보여줌) */
$LABELS = [
  'bldNm'=>'건물명','platPlc'=>'대지위치','newPlatPlc'=>'도로명주소',
  'mainPurpsCdNm'=>'주용도','etcPurps'=>'기타용도','archArea'=>'건축면적(㎡)',
  'totArea'=>'연면적(㎡)','vlRatEstmTotArea'=>'용적률산정연면적(㎡)','platArea'=>'대지면적(㎡)',
  'bcRat'=>'건폐율(%)','vlRat'=>'용적률(%)','mainBldCnt'=>'주건축물수','atchBldCnt'=>'부속건축물수',
  'totPkngCnt'=>'총주차대수','hhldCnt'=>'세대수','fmlyCnt'=>'가구수','hoCnt'=>'호수',
  'useAprDay'=>'사용승인일','pmsDay'=>'허가일','strctCdNm'=>'구조',
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>건축물대장 총괄표제부 — 작동 확인 테스트</title>
<style>
  :root{--navy:#3a5572;--red:#c0392b;--ink:#1f2430;--mut:#6b7280;--bg:#eef1f6;
    --card:#fff;--brd:#e3e7ee;--ok:#16a34a;--radius:12px}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Pretendard','Malgun Gothic',sans-serif;background:var(--bg);
    color:var(--ink);font-size:14px;line-height:1.6;padding:22px 16px 80px}
  .wrap{max-width:920px;margin:0 auto}
  h1{font-size:20px;font-weight:800;margin-bottom:4px}
  .sub{color:var(--mut);font-size:13px;margin-bottom:18px}
  .card{background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
    padding:18px;margin-bottom:16px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
  label{display:block;font-size:12px;font-weight:700;color:var(--mut);margin:0 0 5px}
  input[type=text],input[type=password]{width:100%;border:1px solid #d6dce6;border-radius:9px;
    padding:10px 12px;font-size:14px;font-family:inherit;outline:none}
  input:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(58,85,114,.12)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
  @media(max-width:560px){.row{grid-template-columns:1fr}}
  .btn{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:11px 20px;
    font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn:hover{background:#2f465e}
  .step-h{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:800;margin-bottom:12px}
  .no{width:24px;height:24px;border-radius:7px;background:var(--navy);color:#fff;
    display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
  .no.ok{background:var(--ok)}.no.err{background:var(--red)}
  table{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
  th,td{border:1px solid #dbe0e8;padding:7px 10px;text-align:left;vertical-align:top}
  th{background:#f3f6fa;width:38%;font-weight:700;color:#37455e;white-space:nowrap}
  .kv{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:4px}
  .kv div{background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px;padding:8px 10px}
  .kv b{display:block;font-size:11px;color:var(--mut);font-weight:700}
  .kv span{font-size:14px;font-weight:700;color:#2b3446}
  details{margin-top:10px}
  summary{cursor:pointer;font-size:12.5px;color:var(--navy);font-weight:700}
  pre{background:#0f172a;color:#d1e0f5;border-radius:9px;padding:12px;overflow:auto;
    font-size:11.5px;line-height:1.5;margin-top:8px;max-height:340px}
  .msg{background:#fdf1ef;border:1px solid #eebfb8;color:var(--red);border-radius:9px;
    padding:10px 13px;font-size:13px;font-weight:600;margin-top:8px}
  .ok-msg{background:#eefaf1;border-color:#bfe6cb;color:#137a3a}
  .note{font-size:12.5px;color:var(--mut);line-height:1.7;background:#f8fafc;
    border:1px dashed #ccd6e4;border-radius:9px;padding:11px 13px;margin-top:10px}
  code{background:#eef2fa;border-radius:5px;padding:1px 6px;font-size:12.5px;
    font-family:ui-monospace,Consolas,monospace}
</style>
</head>
<body>
<div class="wrap">
  <h1>🏢 건축물대장 총괄표제부 — 작동 확인 테스트</h1>
  <div class="sub">주소를 넣으면 ①주소→코드 변환, ②총괄표제부 조회 두 단계가 실행되고,
    각 단계의 원시 응답까지 그대로 보여줍니다. 데이터가 잘 오는지 확인한 뒤 실제 도구에 이식하세요.</div>

  <form method="post" class="card">
    <div class="row">
      <div>
        <label>juso.go.kr 인증키 (도로명주소 검색 API)</label>
        <input type="text" name="juso_key" value="<?=h($_POST['juso_key'] ?? '')?>" placeholder="devU01TX0FVVEg...">
      </div>
      <div>
        <label>공공데이터포털 인증키 (건축물대장, Decoding 키 권장)</label>
        <input type="text" name="hub_key" value="<?=h($_POST['hub_key'] ?? '')?>" placeholder="xxxx...==">
      </div>
    </div>
    <label>조회할 주소</label>
    <input type="text" name="addr" value="<?=h($addr)?>" placeholder="예: 경기도 성남시 분당구 불정로 6  또는  서울 종로구 세종대로 175">
    <div style="margin-top:12px"><button class="btn" type="submit">🔍 조회 실행</button></div>
    <div class="note">
      키가 아직 없다면: <b>juso</b>는 <code>business.juso.go.kr</code> → 도로명주소 검색 API 신청(즉시 발급),
      <b>건축물대장</b>은 <code>data.go.kr/data/15134735/openapi.do</code> → 활용신청(승인까지 보통 1~2일, 자동승인인 경우 즉시).
      두 키 모두 코드 상단 상수에 넣어두면 입력칸을 비워도 됩니다.
    </div>
  </form>

<?php if (!empty($globalMsg)): ?>
  <div class="card"><div class="msg"><?=h($globalMsg)?></div></div>
<?php endif; ?>

<?php if ($run && $s1): ?>
  <!-- ① 주소 → 코드 -->
  <div class="card">
    <div class="step-h">
      <span class="no <?=$s1['parsed']?'ok':'err'?>">1</span> 주소 → 코드 변환 (juso.go.kr)
    </div>
    <?php if ($s1['parsed']): $p=$s1['parsed']; ?>
      <div class="ok-msg msg">✔ 변환 성공 — 이 코드들이 다음 단계로 넘어갑니다.</div>
      <div class="kv" style="margin-top:10px">
        <div><b>도로명주소</b><span><?=h($p['roadAddr'])?></span></div>
        <div><b>지번주소</b><span><?=h($p['jibunAddr'])?></span></div>
        <div><b>sigunguCd</b><span><?=h($p['sigunguCd'])?></span></div>
        <div><b>bjdongCd</b><span><?=h($p['bjdongCd'])?></span></div>
        <div><b>platGbCd</b><span><?=h($p['platGbCd'])?> <?= $p['platGbCd']==='1'?'(산)':'(대지)'?></span></div>
        <div><b>bun / ji</b><span><?=h($p['bun'])?> / <?=h($p['ji'])?></span></div>
      </div>
      <?php if (count($s1['candidates'])>1): ?>
        <div class="note">후보 주소가 <?=count($s1['candidates'])?>개 검색됐습니다. 테스트는 첫 번째를 사용했습니다.
          실제 도구에서는 사용자가 후보 중 하나를 고르게 만드는 게 안전합니다.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="msg"><?=h($s1['msg'] ?: '변환 실패')?></div>
    <?php endif; ?>
    <details>
      <summary>juso 원시 응답 보기 (HTTP <?=h((string)$s1['raw']['code'])?>)</summary>
      <pre><?=h(pretty($s1['raw']['body']))?></pre>
    </details>
  </div>

  <!-- ② 코드 → 총괄표제부 -->
  <?php if ($s2): ?>
  <div class="card">
    <div class="step-h">
      <span class="no <?=$s2['items']?'ok':'err'?>">2</span> 총괄표제부 조회 (건축HUB)
    </div>
    <?php if ($s2['items']): ?>
      <div class="ok-msg msg">✔ 데이터 <?=count($s2['items'])?>건 수신 — 아래는 주요 필드입니다.</div>
      <?php foreach ($s2['items'] as $it): ?>
        <table>
          <?php foreach ($LABELS as $k=>$lab): if (isset($it[$k]) && $it[$k]!==''): ?>
            <tr><th><?=h($lab)?> <small style="color:#9aa3b2">(<?=h($k)?>)</small></th><td><?=h($it[$k])?></td></tr>
          <?php endif; endforeach; ?>
        </table>
        <details>
          <summary>이 건물의 전체 필드 보기</summary>
          <pre><?=h(json_encode($it, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
        </details>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="msg"><?=h($s2['msg'] ?: '결과 없음')?></div>
      <div class="note">총괄표제부는 <b>여러 동으로 이뤄진 단지</b>에만 존재합니다. 단독 건물 하나면
        총괄표제부가 없고 <b>표제부(getBrTitleInfo)</b>에만 데이터가 있습니다. 결과가 비면
        API를 <code>getBrTitleInfo</code> 로 바꿔서도 테스트해 보세요.</div>
    <?php endif; ?>
    <details>
      <summary>건축HUB 원시 응답 보기 (HTTP <?=h((string)$s2['raw']['code'])?>)</summary>
      <pre><?=h(pretty($s2['raw']['body']))?></pre>
    </details>
  </div>
  <?php endif; ?>
<?php endif; ?>

  <div class="card note" style="margin-top:4px">
    <b>확인 포인트</b><br>
    · 1단계에서 <code>sigunguCd·bjdongCd·bun·ji</code>가 제대로 나오는지 →
      juso 키가 살아있고 주소가 인식된다는 뜻.<br>
    · 2단계 HTTP가 200인데 결과가 비면 → 키 승인은 됐지만 그 지번에 <b>총괄표제부</b>가 없는 경우.
      표제부로 바꿔 확인.<br>
    · HTTP가 200이 아니거나 <code>resultCode</code>가 <code>00</code>이 아니면 → 키 미승인/트래픽초과/파라미터 오류.
      원시 응답의 <code>resultMsg</code>를 보면 원인이 나옵니다.
  </div>
</div>
</body>
</html>
