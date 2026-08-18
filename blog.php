<?php
$PAGE_TITLE = '블로그';
$ACTIVE     = 'blog';
require __DIR__ . '/_header.php';

/* ── 여기 배열만 고치면 글 목록이 바뀝니다. 나중에 DB로 교체 가능 ── */
$posts = [
  ['title' => '소방 점검, 왜 미리 받아야 할까?', 'date' => '2026-06-20',
   'excerpt' => '정기 점검을 미루면 생기는 문제와 미리 받았을 때의 이점을 정리했습니다. ',
   'href' => '#'],
  ['title' => '소방 행정 서류, 한눈에 정리하기', 'date' => '2026-06-12',
   'excerpt' => '자주 헷갈리는 소방 관련 신고와 서류를 단계별로 안내합니다. ',
   'href' => '#'],
  ['title' => 'YEOHUB 서비스 시작 안내', 'date' => '2026-06-01',
   'excerpt' => 'YEOHUB를 처음 이용하시는 분들을 위한 시작 가이드입니다. ',
   'href' => '#'],
];
?>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 블로그</div>
    <h1>블로그</h1>
    <p>소방 업무에 도움이 되는 소식과 안내를 전합니다.</p>
  </div>
</header>

<main class="wrap">
  <?php foreach ($posts as $p): ?>
    <a class="card" href="<?=h($p['href'])?>" style="display:block;color:inherit">
      <div class="muted" style="font-size:13px;margin-bottom:6px"><?=h($p['date'])?></div>
      <h3><?=h($p['title'])?></h3>
      <p><?=h($p['excerpt'])?></p>
    </a>
  <?php endforeach; ?>
</main>

<?php require __DIR__ . '/_footer.php'; ?>
