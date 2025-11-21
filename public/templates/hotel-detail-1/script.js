(function(){
  function buildNav(){
    var nav=document.getElementById('dynamic-nav');
    if(!nav) return;
    var items=[];
    if(document.getElementById('section-about')) items.push({id:'section-about',label:'About'});
    if(document.getElementById('section-amenities')) items.push({id:'section-amenities',label:'Amenities'});
    if(document.getElementById('section-location')) items.push({id:'section-location',label:'Location'});
    if(document.getElementById('section-faqs')) items.push({id:'section-faqs',label:'FAQs'});
    nav.innerHTML=items.map(function(s){return '<a class="section-link text-sm font-medium text-neutral-700 hover:text-primary transition-colors pb-2" href="#'+s.id+'">'+s.label+'</a>';}).join('');
    var links=document.querySelectorAll('.section-link');
    var map={};
    for(var i=0;i<links.length;i++){var a=links[i];var href=a.getAttribute('href')||'';map[href.replace('#','')]=a;}
    var active=null;function setActive(a){if(active){active.classList.remove('text-primary','border-b-2','border-primary');}if(a){a.classList.add('text-primary','border-b-2','border-primary');active=a;}}
    var observer=new IntersectionObserver(function(entries){for(var j=0;j<entries.length;j++){var ent=entries[j];if(ent.isIntersecting){var id=ent.target.id;var l=map[id];if(l){setActive(l);}}}},{rootMargin:'-50% 0px -40% 0px',threshold:0.1});
    var secs=document.querySelectorAll('[id^=section-]');
    for(var k=0;k<secs.length;k++){observer.observe(secs[k]);}
    document.addEventListener('click',function(e){var a=e.target.closest('.section-link');if(a){var href=a.getAttribute('href')||'';if(href.indexOf('#')===0){e.preventDefault();var id=href.slice(1);var el=document.getElementById(id);if(el){el.scrollIntoView({behavior:'smooth',block:'start'});setActive(a);history.replaceState(null,'','#'+id);}}}});
  }
  var data={};try{var pd=document.getElementById('page-data');if(pd){data=JSON.parse(pd.textContent||'{}')||{};}}catch(e){data={}};
  var images=[];try{if(data.gallery){images=data.gallery;}else{var gd=document.getElementById('gallery-data');if(gd){images=JSON.parse(gd.textContent||'[]')||[];}}}catch(e){images=[]}
  var idx=0;
  var t=document.getElementById('hotel-title');if(t){t.textContent=(data.title||'Hotel');}
  var at=document.getElementById('about-title');if(at){at.textContent=(data.title||'Hotel');}
  var sa=document.getElementById('section-address');if(sa){sa.textContent=(data.location_text||'');}
  var bc=document.getElementById('breadcrumb');if(bc){var items=(data.breadcrumb_items||[]);var h='';for(var i=0;i<items.length;i++){var it=items[i];var last=i===items.length-1;var sep=i>0?'<span class="text-neutral-700 text-sm font-medium">/</span>':'';var node=last?'<span class="text-neutral-900 text-sm font-medium">'+it+'</span>':'<a class="text-neutral-700 text-sm font-medium" href="#">'+it+'</a>';h+=sep+node;}bc.innerHTML=h;}
  var gg=document.getElementById('gallery-grid');if(gg){var tiles=images.slice(0,5);var moreCount=Math.max(0,images.length-4);var gh='';for(var j=0;j<tiles.length;j++){var url=tiles[j];var baseClass='bg-center bg-no-repeat bg-cover';var baseStyle="background-image: url('"+url+"')";if(j===0){gh+='<div data-idx="'+j+'" class="cursor-pointer col-span-2 row-span-2 '+baseClass+'" style="'+baseStyle+'"></div>';}else if(j<4){gh+='<div data-idx="'+j+'" class="cursor-pointer col-span-1 row-span-1 '+baseClass+'" style="'+baseStyle+'"></div>';}else{gh+='<div data-idx="'+j+'" class="relative col-span-1 row-span-1 '+baseClass+'" style="'+baseStyle+'"><div class="absolute inset-0 bg-black/50 flex items-center justify-center"><button id="openGalleryButton" class="text-white font-bold text-lg">+'+moreCount+' more</button></div></div>';}}
    gg.innerHTML=gh;}
  var ac=document.getElementById('about-content');if(ac){var p=(data.about1||'');ac.innerHTML=p?('<p>'+p+'</p>'):'';}
  var am=document.getElementById('amenities-grid');if(am){var arr=(data.amenities||[]);var icon=function(s){var v=(s||'').toLowerCase();if(/wifi|internet/.test(v)) return 'wifi';if(/air|conditioning|ac/.test(v)) return 'air';if(/tv|television|cable|satellite/.test(v)) return 'tv';if(/spa|wellness/.test(v)) return 'spa';if(/restaurant|snack|breakfast|minibar|bar/.test(v)) return 'restaurant';if(/security|alarm|extinguisher|smoke/.test(v)) return 'security';if(/shuttle|airport/.test(v)) return 'airport_shuttle';if(/elevator|lift/.test(v)) return 'elevator';if(/disabled|accessible|wheelchair/.test(v)) return 'accessible';if(/heating|thermo/.test(v)) return 'thermostat';if(/laundry|ironing|dry cleaning/.test(v)) return 'laundry';if(/meeting|business|fax|photocopying/.test(v)) return 'business_center';if(/lockers|safe|deposit/.test(v)) return 'lock';if(/view|scenic|terrace|patio|balcony/.test(v)) return 'landscape';if(/room service/.test(v)) return 'room_service';if(/refrigerator|kettle|electric/.test(v)) return 'kitchen';return 'check_circle';};var ah='';for(var a=0;a<arr.length;a++){var it=arr[a];ah+='<div class="flex items-center gap-3"><span class="material-symbols-outlined text-primary">'+icon(it)+'</span><span class="text-neutral-900">'+it+'</span></div>';}
    am.innerHTML=ah;}
  var fq=document.getElementById('faqs-list');if(fq){var list=(data.faqs||[]);var hf='';for(var f=0;f<list.length;f++){var x=list[f]||{};var q=(x.q||'').trim();var a=(x.a||'').trim();if(!q&&!a) continue;var border=f<list.length-1?'border-b border-gray-200 pb-4':'';hf+='<div class="'+border+'"><h4 class="font-bold text-lg">'+(x.q||'')+'</h4><p class="text-neutral-700 mt-2">'+(x.a||'')+'</p></div>';}
    fq.innerHTML=hf;}
  var ui=document.getElementById('useful-info');if(ui){var info=(data.info||[]);var hu='';for(var u=0;u<info.length;u++){var y=info[u]||{};var sj=(y.subject||'').trim();var ds=(y.description||'').trim();if(!sj&&!ds) continue;hu+='<div class="flex items-start gap-4"><span class="material-symbols-outlined text-primary mt-1">description</span><div><h4 class="font-bold">'+(sj||'')+'</h4><p class="text-neutral-700 mt-1 text-sm whitespace-pre-line">'+(ds||'')+'</p></div></div>'}
    ui.innerHTML=hu;}
  var lc=document.getElementById('location-card');if(lc){var loc=data.location||'';var phone=data.phone||'';var isIframe=/^<iframe[\s\S]*<\/iframe>$/i.test(loc);var isEmbedUrl=/https?:\/\/(?:www\.)?google\.com\/maps\/embed\?pb=/.test(loc);var me='';if(isIframe){me=loc;}else if(isEmbedUrl){me='<iframe src="'+loc+'" width="100%" height="100%" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';}else if(loc){me='<iframe title="Google Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q='+encodeURIComponent(loc)+'&output=embed" width="100%" height="100%" style="border:0"></iframe>';}var details='';var addr=(data.location_text||'').trim();var ph=(phone||'').trim();if(addr||ph){details='<div class="flex flex-col gap-3 mb-4">'+(addr?'<div class="flex items-center gap-3"><span class="material-symbols-outlined text-primary">location_on</span><span class="text-neutral-900">'+addr+'</span></div>':'')+(ph?'<div class="flex items-center gap-3"><span class="material-symbols-outlined text-primary">call</span><span class="text-neutral-900">'+ph+'</span></div>':'')+'</div>';}lc.innerHTML=me?('<div class="py-8 mt-8 bg-white p-6 rounded-xl border border-gray-200" id="section-location"><h3 class="text-2xl font-bold mb-6">Location</h3>'+details+'<div class="relative w-full h-96 rounded-lg overflow-hidden">'+me+'</div></div>'):'';}
  
  function closeModal(){
    var m=document.getElementById('galleryModal');
    if(m){m.remove();}
  }
  
  function open(i){
    idx=Math.max(0,Math.min(images.length-1,i||0));
    var m=document.getElementById('galleryModal');
    if(!m){
      m=document.createElement('div');
      m.id='galleryModal';
      m.className='fixed inset-0 z-50 flex items-center justify-center p-8';
      m.style.backgroundColor='rgba(0,0,0,0.85)';
      
      var html='<div class="relative flex items-center justify-center w-full max-w-6xl">';
      html+='<button id="prev" class="absolute -left-16 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition"><span style="font-size:40px;font-weight:bold;line-height:1;margin-top:-10px">&#8249;</span></button>';
      html+='<div class="bg-white rounded-lg shadow-2xl p-6 w-full relative">';
      html+='<button id="closeGallery" class="absolute top-4 right-4 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded shadow-lg transition z-10 flex items-center gap-2"><span style="font-weight:600">Đóng</span><span style="font-size:20px;line-height:1">×</span></button>';
      html+='<img data-role="main" class="w-full object-contain rounded mb-6" style="max-height:calc(100vh - 280px)" src="" />';
      html+='<div id="thumbs" class="flex gap-3 overflow-x-auto justify-center pb-2 pt-2" style="scrollbar-width:thin"></div>';
      html+='</div>';
      html+='<button id="next" class="absolute -right-16 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition"><span style="font-size:40px;font-weight:bold;line-height:1;margin-top:-10px">&#8250;</span></button>';
      html+='</div>';
      
      m.innerHTML=html;
      document.body.appendChild(m);
      
      m.querySelector('#closeGallery').addEventListener('click',closeModal);
      m.addEventListener('click',function(e){
        if(e.target===m){closeModal();}
      });
      
      document.addEventListener('keydown',function escHandler(e){
        if(e.key==='Escape'){
          closeModal();
          document.removeEventListener('keydown',escHandler);
        }
      });
    }
  }
  
  function render(){
    var m=document.getElementById('galleryModal');
    if(!m) return;
    var main=m.querySelector('img[data-role="main"]');
    var thumbs=m.querySelector('#thumbs');
    main.src=images[idx];
    thumbs.innerHTML='';
    
    images.forEach(function(src,i){
      var t=document.createElement('img');
      t.className='h-20 w-28 object-cover rounded cursor-pointer transition-all hover:opacity-100 '+(i===idx?'ring-4 ring-blue-500 opacity-100':'opacity-60');
      t.src=src;
      t.addEventListener('click',function(){idx=i;render();});
      thumbs.appendChild(t);
    });
    
    var prev=m.querySelector('#prev');
    var next=m.querySelector('#next');
    prev.style.opacity=idx===0?'0.4':'1';
    prev.style.cursor=idx===0?'default':'pointer';
    next.style.opacity=idx===images.length-1?'0.4':'1';
    next.style.cursor=idx===images.length-1?'default':'pointer';
    
    prev.onclick=function(){
      if(idx>0){idx--;render();}
    };
    next.onclick=function(){
      if(idx<images.length-1){idx++;render();}
    };
  }
  
  window.__openGallery=function(i){open(i);render();};
  function openMap(){
    var locRaw=(data.location||'').trim();
    var locTxt=(data.location_text||'').trim();
    var isIframe=/^<iframe[\s\S]*<\/iframe>$/i.test(locRaw);
    var isEmbedUrl=/https?:\/\/(?:www\.)?google\.com\/maps\/embed\?pb=/.test(locRaw);
    var content='';
    if(isIframe){
      content=locRaw;
    }else if(isEmbedUrl){
      content='<iframe src="'+locRaw+'" width="100%" height="100%" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }else{
      var q=encodeURIComponent(locTxt||locRaw);
      if(!q) return;
      var iframeSrc='https://www.google.com/maps?q='+q+'&output=embed';
      content='<iframe title="Google Map" src="'+iframeSrc+'" width="100%" height="100%" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }
    var m=document.getElementById('mapModal');
    if(!m){
      m=document.createElement('div');
      m.id='mapModal';
      m.className='fixed inset-0 z-50 flex items-center justify-center p-6';
      m.style.backgroundColor='rgba(0,0,0,0.85)';
      var html='<div class="relative w-full max-w-6xl bg-white rounded-lg shadow-2xl overflow-hidden">';
      html+='<button id="closeMap" class="absolute top-4 right-4 bg-neutral-800 hover:bg-black text-white px-3 py-1 rounded">Close</button>';
      html+='<div class="w-full" style="height:70vh">'+content+'</div>';
      html+='</div>';
      m.innerHTML=html;
      document.body.appendChild(m);
      m.querySelector('#closeMap').addEventListener('click',function(){m.remove();});
      m.addEventListener('click',function(e){if(e.target===m){m.remove();}});
      document.addEventListener('keydown',function esc(e){if(e.key==='Escape'){m.remove();document.removeEventListener('keydown',esc);}});
    }
  }
  window.__openMap=openMap;
  buildNav();
  (function(){
    var s=document.getElementById('nav-sentinel');
    var nav=document.getElementById('nav-bar');
    if(!s||!nav) return;
    var obs=new IntersectionObserver(function(entries){var e=entries&&entries[0];if(!e) return; if(e.isIntersecting){nav.classList.remove('is-stuck');}else{nav.classList.add('is-stuck');}}, {threshold:0});
    obs.observe(s);
  })();
  document.addEventListener('click',function(e){
    var btn=e.target.closest('#openGalleryButton');
    if(btn){window.__openGallery(0);}
    var tile=e.target.closest('[data-idx]');
    if(tile){
      var i=Number(tile.dataset.idx)||0;
      window.__openGallery(i);
    }
    var mapBtn=e.target.closest('#openMapButton');
    if(mapBtn){e.preventDefault();window.__openMap();}
  });
})();