/* Edit tour copy and pacing here. Durations are milliseconds at the original pacing. */
(()=>{
const labels=[['건물 사전등록','파트너센터의 이 버튼에서 시작해요.'],['예시 건물 검색','건물명으로 검색하고, 저장할 이름을 확인해요.'],['집결지 지정','지도에서 모일 장소를 누르고 이름을 골라요.'],['소방차 진입로','큰길에서 교차로를 돌아 건물 앞 도로를 따라 눌러요.'],['등급 · 담당자','건물 규모와 설비로 등급을 확인하고, 담당자를 입력해요.'],['저장 완료','대표자는 앞에서 입력한 관리자를 선택하면 돼요.'],['QR 촬영','유저가 휴대폰으로 QR을 촬영해요.'],['유저 가입','아이디와 비밀번호를 만들면 건물·파트너가 자동 연결돼요.'],['카드등록','구독할 카드를 등록해요.'],['구독 완료','등록한 카드로 구독을 시작해요.'],['지도에서 PRO 확인','해당 건물이 S · PRO 이용으로 바뀌었어요.']];
 labels.push(['요약 확인','전체 거래처와 등록 연면적, 가장 큰 거래처를 확인해요.'],['방문일정','지도에서 거래처를 선택해 방문할 날짜에 등록해요.']);
window.partnerTourConfig={labels,entryMs:2400,totalMs:185000,rates:[0.5,0.75,1,1.5,2],timings:{
  "cursorApproach": 250,
  "cursorHover": 650,
  "clickHold": 300,
  "clickRelease": 180,
  "typeLetter": 65,
  "typeDone": 450,
  "registryIntro": 1400,
  "floorTable": 900,
  "equipmentOrScan": 3300,
  "readDetail": 3000,
  "gradeResult": 4500,
  "representative": 1100,
  "savedOrReturn": 2200,
  "userBuilding": 7000,
  "deviceSwitch": 850,
  "transition": 2000,
  "mapOverview": 2100,
  "summaryOverview": 4000,
  "summaryDetail": 5200,
  "areaRanking": 3500,
  "dialogGap": 600,
  "calendarReady": 1200,
  "visitSaved": 2400,
  "calendarClose": 800,
  "backToBuilding": 1300,
  "mapReady": 1500,
  "qrRead": 2800,
  "copyHint": 4200,
  "subscriptionRead": 2700,
  "manualScan": 6000
}};
})();
