(function(){
  var data={};
  try{
    var pd=document.getElementById('page-data');
    if(pd){data=JSON.parse(pd.textContent||'{}')||{};}
  }catch(e){data={};}

  // Render categories
  var categories=data.categories||[];
  var catGrid=document.getElementById('categories-grid');
  if(catGrid && categories.length>0){
    var catHtml='';
    for(var i=0;i<categories.length;i++){
      var cat=categories[i];
      var name=cat.name||'Category';
      var image=cat.image||'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
      var url=cat.url||'#';
      var count=cat.count||0;
      catHtml+='<a href="'+url+'" class="group relative rounded-xl overflow-hidden aspect-[4/3]">';
      catHtml+='<div class="absolute inset-0 bg-cover bg-center transition-transform group-hover:scale-105" style="background-image:url(\''+image+'\')"></div>';
      catHtml+='<div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>';
      catHtml+='<div class="absolute bottom-0 left-0 right-0 p-4">';
      catHtml+='<h3 class="text-white font-bold text-lg">'+name+'</h3>';
      catHtml+='<p class="text-white/80 text-sm">'+count+' properties</p>';
      catHtml+='</div>';
      catHtml+='</a>';
    }
    catGrid.innerHTML=catHtml;
  }

  // Render featured properties
  var featured=data.featured||[];
  var featGrid=document.getElementById('featured-grid');
  if(featGrid && featured.length>0){
    var featHtml='';
    for(var j=0;j<featured.length;j++){
      var p=featured[j];
      var title=p.title||'Property';
      var image=p.image||'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
      var location=p.location_text||'';
      var url=p.url||'#';
      featHtml+='<a href="'+url+'" class="group bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition">';
      featHtml+='<div class="aspect-[4/3] bg-cover bg-center" style="background-image:url(\''+image+'\')"></div>';
      featHtml+='<div class="p-4">';
      featHtml+='<h3 class="font-bold text-lg group-hover:text-primary transition">'+title+'</h3>';
      if(location){featHtml+='<p class="text-neutral-500 text-sm flex items-center gap-1 mt-1"><span class="material-symbols-outlined text-base">location_on</span>'+location+'</p>';}
      featHtml+='</div>';
      featHtml+='</a>';
    }
    featGrid.innerHTML=featHtml;
  }
})();
