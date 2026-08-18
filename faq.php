<?php
$PAGE_TITLE = 'FAQ';
$ACTIVE     = 'faq';
require __DIR__ . '/_header.php';

/* ── 여기 배열만 고치면 FAQ 항목이 바뀝니다 ── */
$faqs = [
  ['q' => '서비스 이용은 어떻게 시작하나요?',
   'a' => '상단의 로그인 버튼으로 로그인 후 업무페이지에서 바로 이용하실 수 있습니다.'],
  ['q' => '건물 안전관리자는 어떤 업무를 해야하나요?',
   'a' => '로그인 후 건물관리 페이지에 들어가서 우측 진행상황을 확인하시면 안내 받으실 수 있습니다.'],
  ['q' => '소방계획서 전체를 대신 작성해 주시나요?',
   'a' => '아닙니다. 자동처리 과정과 문답을 통하여 서비스를 지원합니다. '],
  ['q' => '비용은 어떻게 책정되나요?',
   'a' => '기본적인 기능들은 무료로 제공되나 유료 서비스도 있다는 점을 안내드립니다. '],
];
?>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 자주 묻는 질문</div>
    <h1>FAQ</h1>
    <p>소방계획서.com 이용에 대해 자주 묻는 질문을 모았습니다. 찾는 내용이 없으면 언제든 문의해 주세요.</p>
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
