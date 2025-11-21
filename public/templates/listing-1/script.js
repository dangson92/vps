(function(){
  var data={};
  try{
    var pd=document.getElementById('page-data');
    if(pd){data=JSON.parse(pd.textContent||'{}')||{};}
  }catch(e){data={};}

  var siteName=document.getElementById('site-name');
  if(siteName && data.site_name){siteName.textContent=data.site_name;}

  var pages=data.pages||[];
  var grid=document.getElementById('listings-grid');
  var count=document.getElementById('results-count');

  if(count){count.textContent='Showing '+pages.length+' properties';}

  if(grid){
    var html='';
    for(var i=0;i<pages.length;i++){
      var p=pages[i];
      var title=p.title||'Untitled';
      var desc=p.description||p.about1||'';
      var image=p.image||(p.gallery&&p.gallery[0])||'https://via.placeholder.com/400x300?text=No+Image';
      var location=p.location_text||p.location||'';
      var url=p.url||'#';
      var amenities=p.amenities||[];
      var amenityHtml='';
      var showAmenities=amenities.slice(0,3);
      for(var j=0;j<showAmenities.length;j++){
        amenityHtml+='<span class="inline-flex items-center gap-1 text-xs text-neutral-600 bg-gray-100 px-2 py-1 rounded">'+showAmenities[j]+'</span>';
      }
      if(amenities.length>3){
        amenityHtml+='<span class="text-xs text-neutral-500">+'+(amenities.length-3)+' more</span>';
      }

      html+='<a href="'+url+'" class="block bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition group">';
      html+='<div class="flex flex-col md:flex-row">';
      html+='<div class="md:w-72 h-48 md:h-auto shrink-0 bg-cover bg-center" style="background-image:url(\''+image+'\')"></div>';
      html+='<div class="flex-1 p-6">';
      html+='<div class="flex items-start justify-between mb-2">';
      html+='<h3 class="text-xl font-bold group-hover:text-primary transition">'+title+'</h3>';
      html+='<div class="flex items-center gap-1 text-amber-500"><span class="material-symbols-outlined text-sm" style="font-variation-settings:\'FILL\' 1">star</span><span class="text-sm font-medium">4.5</span></div>';
      html+='</div>';
      if(location){html+='<p class="text-neutral-500 text-sm mb-3 flex items-center gap-1"><span class="material-symbols-outlined text-base">location_on</span>'+location+'</p>';}
      if(desc){html+='<p class="text-neutral-600 text-sm mb-4 line-clamp-2">'+desc+'</p>';}
      if(amenityHtml){html+='<div class="flex flex-wrap gap-2">'+amenityHtml+'</div>';}
      html+='</div>';
      html+='</div>';
      html+='</a>';
    }
    grid.innerHTML=html||'<p class="text-neutral-500 text-center py-12">No properties found</p>';
  }
})();
