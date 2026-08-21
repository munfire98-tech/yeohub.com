<!-- FOOTER -->
<style>
/* ── 푸터 전용 스타일 ─────────────────────────────────────────
   index.php 와 같은 팔레트(밝은 배경 + 파란 포인트)를 씁니다.
   페이지마다 CSS 변수 정의가 달라서, 푸터는 같은 값을 자체적으로 갖습니다. */
.site-footer{
  --ft-bg:#f8fafc; --ft-line:#e3e8f0; --ft-fg:#1a2436;
  --ft-mut:#56627a; --ft-dim:#7a8699; --ft-brand:#2563eb;
  background:var(--ft-bg); color:var(--ft-fg); margin-top:64px;
  font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;
  border-top:1px solid var(--ft-line);
}
.site-footer *{box-sizing:border-box}
.footer-inner{max-width:1120px;margin:0 auto;padding:44px 24px 28px}

/* 상단: 브랜드 + 링크 */
.footer-top{display:flex;gap:40px;flex-wrap:wrap;align-items:flex-start;margin-bottom:30px}
.footer-brand{flex:1 1 280px;min-width:0}
.footer-logo{font-size:21px;font-weight:800;letter-spacing:.06em;color:var(--ft-fg);margin-bottom:9px}
.footer-desc{font-size:13.5px;line-height:1.75;color:var(--ft-mut);margin:0;max-width:340px}

.footer-cols{display:flex;gap:56px;flex-wrap:wrap}
.footer-col{min-width:120px}
.footer-col__t{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
  color:var(--ft-dim);margin-bottom:13px}
.footer-col a{display:block;font-size:13.5px;color:var(--ft-mut);text-decoration:none;
  padding:5px 0;transition:color .14s}
.footer-col a:hover{color:var(--ft-brand)}
.footer-col a:focus-visible{outline:2px solid var(--ft-brand);outline-offset:3px;border-radius:3px}

.footer-divider{height:1px;background:var(--ft-line);margin:0 0 22px}

/* 사업자 정보 — 라벨과 값을 붙여 읽기 쉽게 */
.footer-info{display:flex;flex-wrap:wrap;gap:6px 26px;font-size:12.5px;
  color:var(--ft-dim);line-height:1.9;margin-bottom:4px}
.footer-info b{font-weight:600;color:var(--ft-mut);margin-right:5px}

.footer-bottom{display:flex;justify-content:space-between;align-items:center;
  gap:14px;flex-wrap:wrap;margin-top:20px;padding-top:18px;border-top:1px solid var(--ft-line)}
.footer-bottom p{margin:0;font-size:12.5px;color:var(--ft-dim)}
.footer-pay{display:inline-flex;align-items:center;gap:7px;font-size:12px;color:var(--ft-mut)}
.footer-pay svg{width:14px;height:14px;color:var(--ft-brand);flex-shrink:0}

@media(max-width:680px){
  .footer-inner{padding:36px 20px 24px}
  .footer-top{gap:28px;margin-bottom:24px}
  .footer-cols{gap:32px}
  .footer-bottom{flex-direction:column;align-items:flex-start;gap:8px}
}
</style>

<footer class="site-footer">
  <div class="footer-inner">

    <div class="footer-top">
      <div class="footer-brand">
        <div class="footer-logo">YEOHUB</div>
        <p class="footer-desc">
          소방계획서부터 점검 기록까지, 건물 정보를 한 번만 입력하면
          필요한 서식이 이어서 만들어집니다.
        </p>
      </div>

      <nav class="footer-cols" aria-label="푸터 메뉴">
        <div class="footer-col">
          <div class="footer-col__t">서비스</div>
          <a href="/service.php">서비스 안내</a>
          <a href="/blog.php">블로그</a>
          <a href="/faq.php">자주 묻는 질문</a>
        </div>
        <div class="footer-col">
          <div class="footer-col__t">고객지원</div>
          <a href="mailto:YEOHUB@YEOHUB.com">문의하기</a>
          <a href="/privacy.php">개인정보처리방침</a>
          <a href="/terms.php">이용약관</a>
          <a href="/business_info.php">사업자정보</a>
        </div>
      </nav>
    </div>

    <div class="footer-divider"></div>

    <div class="footer-info">
      <span><b>상호</b>YEOHUB</span>
      <span><b>대표</b>문현권</span>
      <span><b>사업자등록번호</b>751-38-01677</span>
      <span><b>주소</b>경기도 파주시 운정중앙로</span>
    </div>
    <div class="footer-info">
      <span><b>이메일</b>YEOHUB@YEOHUB.com</span>
      <span><b>통신판매업</b>신고 준비중</span>
    </div>

    <div class="footer-bottom">
      <p>© <?= date('Y') ?> YEOHUB. All rights reserved.</p>
      <span class="footer-pay">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5.5c0 4.2-2.9 8.1-7 9.5-4.1-1.4-7-5.3-7-9.5V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12.2l1.8 1.8 3.4-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        토스페이먼츠 안전결제
      </span>
    </div>

  </div>
</footer>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
