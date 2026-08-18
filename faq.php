<?php
$PAGE_TITLE = 'FAQ';
$ACTIVE     = 'faq';
require __DIR__ . '/_header.php';

/* ── 여기 배열만 고치면 FAQ 항목이 바뀝니다 ── */
$faqs = [
  ['q' => '서비스 이용은 어떻게 시작하나요?',
   'a' => '상단의 로그인 버튼으로 회원가입 또는 카카오 로그인 후 업무페이지에서 바로 이용하실 수 있습니다.'],
  ['q' => '견적은 얼마나 빨리 받을 수 있나요?',
   'a' => '요청 주신 내용을 검토 후 영업일 기준 1~2일 이내에 견적을 전달드립니다. '],
  ['q' => '소방 점검도 대행해주나요?',
   'a' => '네, 점검부터 행정 처리까지 전 과정을 지원합니다. '],
  ['q' => '비용은 어떻게 책정되나요?',
   'a' => '작업 범위와 현장 조건에 따라 책정되며, 견적서를 통해 투명하게 안내드립니다. '],
];
?>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 자주 묻는 질문</div>
    <h1>FAQ</h1>
    <p>TWORIX 이용에 대해 자주 묻는 질문을 모았습니다. 찾는 내용이 없으면 언제든 문의해 주세요.</p>
  </div>
</header>

<main class="wrap">
  <?php foreach ($faqs as $f): ?>
    <div class="card">
      <h3>Q. <?=h($f['q'])?></h3>
      <p><?=h($f['a'])?></p>
    </div>
  <?php endforeach; ?>
</main>

<?php require __DIR__ . '/_footer.php'; ?>
