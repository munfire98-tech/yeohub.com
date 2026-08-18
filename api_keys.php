<?php
/* =============================================================
   api_keys.php — 외부 API 키를 한 곳에 모읍니다.
   ─────────────────────────────────────────────────────────────
   · 카카오  : 장소검색(이름·주소) — REST API 키
   · juso    : 도로명주소 → 코드 변환 — 검색 API confmKey
   · hub     : 건축물대장 조회 — 공공데이터포털 Decoding 인증키

   보안: 이 파일은 git 에 올리지 마세요(.gitignore 권장).
        키가 노출되면 각 포털에서 재발급하면 됩니다.
   사용: require 해서 배열로 받습니다.
        $API = require __DIR__.'/api_keys.php';
        $API['kakao'] / $API['juso'] / $API['hub']
   ============================================================= */
declare(strict_types=1);

return [
  'kakao' => 'aea180f8a9ccf7395bccfb6dfbede9c6',
  'juso'  => 'U01TX0FVVEgyMDI2MDgwNzE4MzU1MzExOTkzNjg=',
  'hub'   => 'Bgl2NmDmpeG5hvoX7LxHR8Zdsz1oI6F63aCuHumXF7OlzZiIx3QitUGVVklUe/NXW1WIHjewqbeTgz2QllSNQQ==',

  // 엔드포인트(확정된 정답 경로) — 바꿀 일 없으면 그대로 둡니다.
  'kakao_url' => 'https://dapi.kakao.com/v2/local/search/keyword.json',
  'juso_url'  => 'https://business.juso.go.kr/addrlink/addrLinkApi.do',
  'hub_base'  => 'http://apis.data.go.kr/1613000/BldRgstHubService',
];
