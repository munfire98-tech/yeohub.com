<?php
/**
 * evac_view.php — 피난 시뮬레이션 공개 열람 (QR 스캔용)
 *
 *   fire_evac_sim.php 의 열람 전용(embed) 모드를 로그인 없이 보여준다.
 *   도면은 ID 로만 불러오고, 공유가 켜진(share=true) 모델만 열린다.
 *
 *     /evac_view.php?id=모델ID
 *
 *   여러 사람이 각자 폰으로 QR 을 찍어 대피 동선을 볼 수 있다.
 */
declare(strict_types=1);

require_once __DIR__ . '/evac_common.php';

$id = evac_clean_id((string)($_GET['id'] ?? ''));
$model = $id !== '' ? evac_load_model($id) : null;

/* 공유가 허용된 모델만 공개한다. share 플래그가 없으면 막는다. */
$shared = $model && !empty($model['share']);

if (!$shared) {
    http_response_code(404);
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>열람할 수 없습니다 · TWORIX</title>
    <style>
      body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
           background:#0f1420;color:#c8d0dc;font-family:-apple-system,'Apple SD Gothic Neo',sans-serif}
      .box{text-align:center;padding:32px}
      h1{font-size:1.15rem;margin:0 0 8px}
      p{color:#8a93a6;font-size:.9rem;margin:0}
    </style></head><body>
      <div class="box">
        <h1>열람할 수 없는 도면입니다</h1>
        <p>공유가 종료되었거나 주소가 올바르지 않습니다.</p>
      </div>
    <?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body></html><?php
    exit;
}

/* 열람 전용 임베드 모드로 fire_evac_sim.php 를 띄운다 */
$EVAC_EMBED = true;
$EVAC_AUTO  = isset($_GET['auto']) ? (bool)$_GET['auto'] : true;   // 기본 자동 재생
$EVAC_MAP   = (string)($model['map']  ?? '');
$EVAC_NAME  = (string)($model['name'] ?? '피난 시뮬레이션');
if (!empty($model['scenario']) && is_array($model['scenario'])) {
    $EVAC_SCENARIO = json_encode($model['scenario']);
}

include __DIR__ . '/fire_evac_sim.php';
