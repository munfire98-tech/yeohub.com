// Resolve the confirmed address before saving it or opening its maps.
window.buildingAddressCoordinates=function(address,loadMap){
  return new Promise(function(resolve){
    var settled=false;
    function finish(value){if(settled)return;settled=true;clearTimeout(timer);resolve(value);}
    var timer=setTimeout(function(){finish(null);},8000);
    var query=String(address||'').replace(/\([^)]*\)/g,'').trim();
    if(!query){finish(null);return;}
    try{
      loadMap(function(){
        if(settled)return;
        try{
          var svc=window.kakao.maps.services;
          new svc.Geocoder().addressSearch(query,function(rows,status){
            if(settled)return;
            if(status!==svc.Status.OK || !Array.isArray(rows) || rows.length!==1){finish(null);return;}
            var row=rows[0],lat=Number(row.y),lng=Number(row.x);
            if(!String(row.y||'').trim()||!String(row.x||'').trim()||!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180){finish(null);return;}
            finish({bd_lat:String(lat),bd_lng:String(lng)});
          },{analyze_type:'exact'});
        }catch(e){finish(null);}
      },function(){finish(null);});
    }catch(e){finish(null);}
  });
};
