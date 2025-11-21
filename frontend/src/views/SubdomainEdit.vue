<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="bg-white rounded-lg shadow p-6">
          <h1 class="text-2xl font-bold text-gray-900 mb-4">Create Subdomain</h1>
          <p class="text-sm text-gray-600 mb-4">Parent: {{ parentDomain }}</p>
          <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Subdomain</label>
                <input v-model="form.sub" type="text" required placeholder="hotel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                <p class="text-xs text-gray-500 mt-1">Sẽ tạo domain: {{ fullDomain }}</p>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">VPS Server</label>
                <select v-model="form.vps_server_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                  <option value="">Select VPS Server</option>
                  <option v-for="s in servers" :value="s.id" :key="s.id">{{ s.name }} ({{ s.ip_address }})</option>
                </select>
              </div>
            </div>

            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Template giao diện</label>
              <select v-model="templateType" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="blank">HTML mặc định</option>
                <option value="hotel-detail-1">Hotel Detail 1</option>
              </select>
            </div>

            <div class="mt-6">
              <h2 class="text-lg font-semibold text-gray-900">Nội dung template</h2>
              <div v-if="templateType === 'hotel-detail-1'" class="mt-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Tiêu đề khách sạn</label>
                  <input v-model="tpl.title" type="text" placeholder="Azure Breeze Resort" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Địa điểm</label>
                  <input v-model="tpl.location" type="text" placeholder="Sunny Isles, Florida, United States" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Số điện thoại liên hệ</label>
                  <input v-model="tpl.phone" type="text" placeholder="(305) 555–1234" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                </div>
              </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Ảnh gallery (mỗi dòng 1 URL)</label>
                  <textarea v-model="tpl.galleryRaw" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Giới thiệu (đoạn 1)</label>
                  <textarea v-model="tpl.about1" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Amenities</label>
                  <div class="mt-1 border border-gray-300 rounded-md p-2 max-h-64 overflow-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                      <label v-for="opt in AMENITIES_OPTIONS" :key="opt" class="inline-flex items-center gap-2">
                        <input type="checkbox" :value="opt" v-model="tpl.amenities" />
                        <span class="text-sm">{{ opt }}</span>
                      </label>
                    </div>
                  </div>
                </div>
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">Useful Information</label>
                    <button type="button" @click="addInfo" class="px-3 py-1 text-sm rounded-md border border-gray-300 bg-white">+ Thêm</button>
                  </div>
                  <div v-for="(info, idx) in tpl.info" :key="idx" class="border border-gray-200 rounded-md p-3 space-y-2">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-semibold">{{ idx + 1 }}</span>
                      <span class="text-sm font-medium text-gray-700">Subject - Description</span>
                      <button type="button" @click="removeInfo(idx)" class="ml-auto px-2 py-1 text-sm rounded-md border border-gray-300 bg-white" title="Xóa">−</button>
                    </div>
                    <input v-model="info.subject" type="text" placeholder="Subject" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                    <textarea v-model="info.description" rows="3" placeholder="Description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                  </div>
                </div>
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">FAQs</label>
                    <button type="button" @click="addFaq" class="px-3 py-1 text-sm rounded-md border border-gray-300 bg-white">+ Thêm</button>
                  </div>
                  <div v-for="(item, idx) in tpl.faqs" :key="idx" class="border border-gray-200 rounded-md p-3 space-y-2">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-semibold">{{ idx + 1 }}</span>
                      <span class="text-sm font-medium text-gray-700">Question - Answer</span>
                      <button type="button" @click="removeFaq(idx)" class="ml-auto px-2 py-1 text-sm rounded-md border border-gray-300 bg-white" title="Xóa">−</button>
                    </div>
                    <input v-model="item.q" type="text" placeholder="Question" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                    <textarea v-model="item.a" rows="3" placeholder="Answer" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                  </div>
                </div>
              </div>
              <div v-else-if="templateType === 'blank'" class="mt-4">
                <label class="block text-sm font-medium text-gray-700">HTML</label>
                <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
                  <div ref="gutterRef" class="border border-gray-300 rounded-l-md bg-gray-50 overflow-auto" style="max-height: 360px;">
                    <div v-for="i in lineCount" :key="i" class="px-2 text-xs text-gray-500 leading-6">{{ i }}</div>
                  </div>
                  <textarea ref="codeRef" v-model="htmlRaw" rows="12" required class="block w-full border border-gray-300 rounded-r-md shadow-sm font-mono leading-6" @scroll="syncScroll"></textarea>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <router-link :to="`/websites/${websiteId}/subdomains`" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</router-link>
              <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-blue-400">
                <span v-if="saving" class="inline-flex items-center gap-2">
                  <Loader2 class="size-4 animate-spin" />
                  Creating...
                </span>
                <span v-else>Create Subdomain</span>
              </button>
            </div>
            <p v-if="msg" :class="msgType === 'error' ? 'text-red-600' : 'text-green-600'" class="mt-3 text-sm">{{ msg }}</p>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { Loader2 } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const websiteId = route.params.websiteId

const parentDomain = ref('')
const parentWebsite = ref(null)
const servers = ref([])
const form = ref({ sub: '', vps_server_id: '' })
const templateType = ref('blank')
const htmlRaw = ref('')
const tpl = ref({ title: '', location: '', phone: '', galleryRaw: '', about1: '', amenities: [], info: [{ subject: '', description: '' }, { subject: '', description: '' }], faqs: [{ q: '', a: '' }, { q: '', a: '' }] })
const AMENITIES_OPTIONS = [
  'Private bathroom',
  'WiFi available in all areas, free',
  'Scenic view',
  'Airport shuttle',
  'Air conditioning',
  'Flat-screen TV',
  'Family rooms',
  'Non-smoking rooms',
  'Spa & wellness centre',
  'Restaurant',
  'Toilet',
  'Toilet paper',
  'Towels',
  'Bidet',
  'Towels/linen (extra fee)',
  'Bathtub or shower',
  'Slippers',
  'Free toiletries',
  'Hairdryer',
  'Shower',
  'Linen',
  'Wardrobe or closet',
  'View',
  'Terrace / patio',
  'Electric kettle',
  'Refrigerator',
  'Bicycle rental (surcharge)',
  'Live music/performance (surcharge)',
  'Cooking class (off-site, surcharge)',
  'Cycling tour (surcharge)',
  'Walking tour (surcharge)',
  'Evening entertainment',
  'Bicycle',
  'Karaoke (surcharge)',
  'Fireplace',
  'Desk',
  'Cable channels',
  'Satellite channels',
  'Telephone',
  'TV',
  'Fruit',
  'Special diet menus (on request)',
  'Snack bar',
  'Breakfast in the room',
  'Minibar',
  'ATM on-site',
  'Lockers',
  'Luggage storage',
  'Tour desk',
  'Currency exchange',
  '24-hour front desk',
  'Daily housekeeping',
  'Ironing service (surcharge)',
  'Dry cleaning (surcharge)',
  'Laundry (surcharge)',
  'Fax/photocopying (surcharge)',
  'Business centre',
  'Meeting/banquet facilities (surcharge)',
  'Fire extinguishers',
  'Smoke alarms',
  'Security alarm',
  'Key card access',
  '24-hour security',
  'Safety deposit box',
  'Shuttle service (surcharge)',
  'Smoking area',
  'Heating',
  'Hardwood floors',
  'Soundproofing',
  'Car rental',
  'Elevator',
  'Iron',
  'Room service',
  'Facilities for disabled guests',
  'Upper floors accessible by elevator'
]
const gutterRef = ref(null)
const codeRef = ref(null)
const saving = ref(false)
const msg = ref('')
const msgType = ref('')

const fullDomain = computed(() => (form.value.sub ? `${form.value.sub}.` : '') + parentDomain.value)

const lineCount = computed(() => {
  const v = htmlRaw.value || ''
  const n = v.split('\n').length
  return n > 0 ? n : 1
})

const syncScroll = () => {
  if (gutterRef.value && codeRef.value) gutterRef.value.scrollTop = codeRef.value.scrollTop
}

const buildHtml = () => {
  if (templateType.value === 'blank') return htmlRaw.value
  const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
  const tiles = g.slice(0,5)
  const moreCount = Math.max(0, g.length - 4)
  const scriptClose = '</scr' + 'ipt>'
  const lightboxStyles = '<style>#galleryModal .relative{background:#fff;border-radius:0.75rem;box-shadow:0 10px 25px rgba(0,0,0,.2);padding:1rem;width:min(1000px,90vw);height:80vh;}#galleryModal #thumbs{display:flex;flex-wrap:wrap;justify-content:center;gap:.25rem;width:100%;}#galleryModal #closeGallery{height:40px;width:40px;border-radius:9999px;background:#fff;color:#000;box-shadow:0 8px 16px rgba(0,0,0,.15);border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.25rem;}#galleryModal #prev,#galleryModal #next{height:48px;width:48px;border-radius:9999px;background:#fff;color:#000;box-shadow:0 8px 16px rgba(0,0,0,.15);border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.5rem;position:absolute;top:50%;transform:translateY(-50%);}#galleryModal #prev{left:-56px;}#galleryModal #next{right:-56px;}</style>'
  const lightboxEnhanceScript = `<script>(function(){document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.getElementById('galleryModal');if(m) m.remove();}});document.addEventListener('click',function(e){var m=document.getElementById('galleryModal');if(!m) return;var box=m.querySelector('.relative');var path=e.composedPath?e.composedPath():[];var inside=false;for(var i=0;i<path.length;i++){if(path[i]===box){inside=true;break;}}if(!inside && e.button===0){m.remove();}});})();` + scriptClose
  const lightboxAttachScript = `<script>(function(){var attach=function(){var btn=document.getElementById('openGalleryButton');if(btn){btn.addEventListener('click',function(){if(window.__openGallery) window.__openGallery(0);});}document.querySelectorAll('[data-idx]').forEach(function(el){el.style.cursor='pointer';el.addEventListener('click',function(){var i=Number(el.dataset.idx)||0;if(window.__openGallery) window.__openGallery(i);});});};try{attach();}catch(e){}window.addEventListener('load',attach);})();` + scriptClose
  const lightboxScript = `<script>(function(){\n  const images = ${JSON.stringify((tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean))};\n  let idx = 0;\n  const create = (tag, cls) => { const el = document.createElement(tag); if (cls) el.className = cls; return el; };\n  const openAt = (i) => {\n    idx = Math.max(0, Math.min(images.length - 1, i || 0));\n    let modal = document.getElementById('galleryModal');\n    if (!modal) {\n      modal = create('div', 'fixed inset-0 z-50 bg-black/80');\n      modal.id = 'galleryModal';\n      modal.innerHTML = '<div class=\\"absolute inset-0 flex flex-col items-center justify-center p-4\\">'+\n        '<div class=\\"relative max-w-5xl w-full\\">'+\n        '<button id=\\"closeGallery\\" class=\\"absolute -top-3 -right-3 bg-white text-black rounded-full p-1 shadow\\">✕<\\/button>'+\n        '<button id=\\"prev\\" class=\\"absolute left-0 top-1/2 -translate-y-1/2 text-white text-3xl px-3\\">‹<\\/button>'+\n        '<img data-role=\\"main\\" class=\\"w-full max-h-[70vh] object-contain rounded-lg shadow\\" src=\\"\\" />'+\n        '<button id=\\"next\\" class=\\"absolute right-0 top-1/2 -translate-y-1/2 text-white text-3xl px-3\\">›<\\/button>'+\n        '<div id=\\"thumbs\\" class=\\"mt-3 flex gap-2 overflow-x-auto\\"><\\/div>'+\n        '<\\/div>'+\n      '<\\/div>';\n      document.body.appendChild(modal);\n      const close = () => { modal.remove(); };\n      modal.querySelector('#closeGallery').addEventListener('click', close);\n      modal.addEventListener('click', (e) => { if (e.target === modal) close(); });\n      const main = modal.querySelector('img[data-role=\\"main\\"]');\n      const thumbs = modal.querySelector('#thumbs');\n      const render = () => {\n        main.src = images[idx];\n        thumbs.innerHTML = '';\n        images.forEach((src, i) => {\n          const t = create('img', 'h-16 w-24 object-cover rounded cursor-pointer ' + (i===idx ? 'ring-2 ring-white' : 'opacity-70'));\n          t.src = src;\n          t.addEventListener('click', () => { idx = i; render(); });\n          thumbs.appendChild(t);\n        });\n      };\n      modal.querySelector('#prev').addEventListener('click', () => { idx = (idx - 1 + images.length) % images.length; render(); });\n      modal.querySelector('#next').addEventListener('click', () => { idx = (idx + 1) % images.length; render(); });\n      window.__renderGallery = render;\n    }\n    window.__renderGallery();\n  };\n  window.__openGallery = openAt;\n  document.addEventListener('DOMContentLoaded', () => {\n    const btn = document.getElementById('openGalleryButton');\n    if (btn) btn.addEventListener('click', () => openAt(0));\n    document.querySelectorAll('[data-idx]').forEach(el => {\n      el.style.cursor = 'pointer';\n      el.addEventListener('click', () => openAt(Number(el.dataset.idx) || 0));\n    });\n  });\n})();` + scriptClose
  const galleryHtml = tiles.map((url, idx) => {
    const baseClass = `bg-center bg-no-repeat bg-cover`
    const baseStyle = `background-image: url('${url}')`
    if (idx === 0) return `<div data-idx=\"${idx}\" class=\"cursor-pointer col-span-2 row-span-2 ${baseClass}\" style=\"${baseStyle}\"></div>`
    if (idx < 4) return `<div data-idx=\"${idx}\" class=\"cursor-pointer col-span-1 row-span-1 ${baseClass}\" style=\"${baseStyle}\"></div>`
    return `<div data-idx=\"${idx}\" class=\"relative col-span-1 row-span-1 ${baseClass}\" style=\"${baseStyle}\"><div class=\"absolute inset-0 bg-black/50 flex items-center justify-center\"><button id=\"openGalleryButton\" class=\"text-white font-bold text-lg\">+${moreCount} more<\/button><\/div><\/div>`
  const lightboxDelegateScript = `<script>(function(){document.addEventListener('click',function(e){var btn=e.target.closest('#openGalleryButton');if(btn){if(window.__openGallery) window.__openGallery(0);return;}var tile=e.target.closest('[data-idx]');if(tile){var i=Number(tile.dataset.idx)||0;if(window.__openGallery) window.__openGallery(i);}});})();` + scriptClose
  }).join('') + lightboxStyles + lightboxScript + lightboxEnhanceScript + lightboxAttachScript + lightboxDelegateScript
  const amenitiesSelected = (tpl.value.amenities || [])
  const amenitiesHtml = amenitiesSelected.map(item => `<div class=\"flex items-center gap-3\"><span class=\"material-symbols-outlined text-primary\">check_circle<\/span><span class=\"text-neutral-900\">${item}<\/span><\/div>`).join('')
  const faqsHtml = (tpl.value.faqs || []).filter(x => (x.q||'').trim() || (x.a||'').trim()).map((x,i,arr) => `<div class=\"${i < arr.length-1 ? 'border-b border-gray-200 pb-4' : ''}\"><h4 class=\"font-bold text-lg\">${x.q || ''}<\/h4><p class=\"text-neutral-700 mt-2\">${x.a || ''}<\/p><\/div>`).join('')
  const infoBlocks = (tpl.value.info || []).filter(x => (x.subject||'').trim() || (x.description||'').trim())
  const usefulInfoHtml = infoBlocks.map((x) => `<div class=\"flex items-start gap-4\"><span class=\"material-symbols-outlined text-primary mt-1\">description<\/span><div><h4 class=\"font-bold\">${x.subject || ''}<\/h4><p class=\"text-neutral-700 mt-1 text-sm whitespace-pre-line\">${x.description || ''}<\/p><\/div><\/div>`).join('')
  return `<!DOCTYPE html><html class=\\\"light\\\" lang=\\\"en\\\"><head><meta charset=\\\"utf-8\\\"/><meta content=\\\"width=device-width, initial-scale=1.0\\\" name=\\\"viewport\\\"/><title>${tpl.value.title || 'Hotel'}</title><script src=\\\"https://cdn.tailwindcss.com?plugins=forms,container-queries\\\">${scriptClose}<link href=\\\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap\\\" rel=\\\"stylesheet\\\"/><link href=\\\"https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined\\\" rel=\\\"stylesheet\\\"/><script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#137fec','background-light':'#f6f7f8','background-dark':'#101922','neutral-100':'#f8f9fa','neutral-700':'#6c757d','neutral-900':'#212529','accent-gold':'#FFC107'},fontFamily:{display:['Plus Jakarta Sans','sans-serif']},borderRadius:{DEFAULT:'0.5rem',lg:'0.75rem',xl:'1rem',full:'9999px'}}}};${scriptClose}<style>.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24}</style></head><body class=\\\"font-display bg-background-light text-neutral-900\\\"><div class=\\\"relative flex min-h-screen w-full flex-col overflow-x-hidden\\\"><header class=\\\"sticky top-0 z-50 bg-background-light/80 backdrop-blur-sm border-b border-gray-200\\\"><div class=\\\"flex items-center justify-between px-4 sm:px-6 lg:px-8 py-3 max-w-7xl mx-auto\\\"><div class=\\\"flex items-center gap-4\\\"><div class=\\\"size-6 text-primary\\\"><svg fill=\\\"none\\\" viewBox=\\\"0 0 48 48\\\" xmlns=\\\"http://www.w3.org/2000/svg\\\"><path clip-rule=\\\"evenodd\\\" d=\\\"M12.0799 24L4 19.2479L9.95537 8.75216L18.04 13.4961L18.0446 4H29.9554L29.96 13.4961L38.0446 8.75216L44 19.2479L35.92 24L44 28.7521L38.0446 39.2479L29.96 34.5039L29.9554 44H18.0446L18.04 34.5039L9.95537 39.2479L4 28.7521L12.0799 24Z\\\" fill=\\\"currentColor\\\" fill-rule=\\\"evenodd\\\"></path><\/svg><\/div><h2 class=\\\"text-xl font-bold\\\">Voyage<\/h2><\/div><div class=\\\"hidden md:flex flex-1 justify-center items-center gap-9\\\"><a class=\\\"text-sm font-medium hover:text-primary transition-colors\\\" href=\\\"#\\\">Stays<\/a><a class=\\\"text-sm font-medium hover:text-primary transition-colors\\\" href=\\\"#\\\">Flights<\/a><a class=\\\"text-sm font-medium hover:text-primary transition-colors\\\" href=\\\"#\\\">Packages<\/a><a class=\\\"text-sm font-medium hover:text-primary transition-colors\\\" href=\\\"#\\\">Sign In<\/a><\/div><div class=\\\"flex items-center gap-4\\\"><button class=\\\"hidden lg:flex h-10 px-4 rounded-lg bg-primary/20 text-primary text-sm font-bold\\\">List your property<\/button><div class=\\\"bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10\\\" style=\\\"background-image:url('https://lh3.googleusercontent.com/aida-public/AB6AXuDvnz9m5Lcy5st1r4eQ93WeZ3_OZBLEO_dV-B43w096b9LOTk03jISYHHQMNtw3sJOlz250eG1Ek-lCjTkm6VYs5YTqXyY5yidMs2-txDXW2ALbwaZCJqyezel6iTEu5nCXBeY3nAWTTFj6hIp8S53D5F9O6TlycifzBrXL9oURI_4f7EDPKLwWNB0XrD66w5Cel_iAeLA9J8NOp9m7hmy-_cLhgam7nOvUoHC9KL1Lc0tFkgMi_M_2k2UUkhm8uqTBY5jNX-GJ_bw')\\\"></div><\/div><\/div><\/header><main class=\\\"flex grow flex-col\\\"><div class=\\\"px-4 sm:px-6 lg:px-8 py-5\\\"><div class=\\\"max-w-7xl mx-auto\\\"><div class=\\\"flex flex-wrap gap-2 py-4\\\"><a class=\\\"text-neutral-700 text-sm font-medium\\\" href=\\\"#\\\">Home<\/a><span class=\\\"text-neutral-700 text-sm font-medium\\\">/<\/span><a class=\\\"text-neutral-700 text-sm font-medium\\\" href=\\\"#\\\">Stays<\/a><span class=\\\"text-neutral-700 text-sm font-medium\\\">/<\/span><span class=\\\"text-neutral-900 text-sm font-medium\\\">${tpl.value.title || ''}<\/span><\/div><div class=\\\"flex flex-wrap justify-between items-start gap-4 py-4\\\"><div class=\\\"flex flex-col gap-2\\\"><p class=\\\"text-4xl font-extrabold\\\">${tpl.value.title || ''}<\/p><div class=\\\"flex items-center gap-4\\\"><div class=\\\"flex items-center gap-1\\\"><span class=\\\"material-symbols-outlined text-accent-gold\\\" style=\\\"font-variation-settings:'FILL' 1\\\">star<\/span><span class=\\\"material-symbols-outlined text-accent-gold\\\" style=\\\"font-variation-settings:'FILL' 1\\\">star<\/span><span class=\\\"material-symbols-outlined text-accent-gold\\\" style=\\\"font-variation-settings:'FILL' 1\\\">star<\/span><span class=\\\"material-symbols-outlined text-accent-gold\\\" style=\\\"font-variation-settings:'FILL' 1\\\">star<\/span><span class=\\\"material-symbols-outlined text-accent-gold\\\">star_half<\/span><\/div><p class=\\\"text-neutral-700\\\">${tpl.value.location || ''}<\/p><\/div><\/div><button class=\\\"flex h-10 px-4 rounded-lg bg-white text-neutral-900 text-sm font-bold gap-2 border border-gray-200\\\"><span class=\\\"material-symbols-outlined text-sm\\\">map<\/span><span>View on map<\/span><\/button><\/div><div class=\\\"grid grid-cols-4 grid-rows-2 gap-4 h-[500px] rounded-xl overflow-hidden mt-6\\\">${galleryHtml}<\/div><div class=\\\"flex flex-col lg:flex-row gap-8 mt-8\\\"><div class=\\\"w-full lg:w-2/3\\\"><div class=\\\"py-8 bg-white p-6 rounded-xl border border-gray-200\\\"><h3 class=\\\"text-2xl font-bold mb-4\\\">About ${tpl.value.title || ''}<\/h3><div class=\\\"space-y-4 text-neutral-700 leading-relaxed\\\"><p>${tpl.value.about1 || ''}<\/p><\/div><button class=\\\"text-primary font-bold mt-4\\\">Read more<\/button><\/div><div class=\\\"py-8 mt-8 bg-white p-6 rounded-xl border border-gray-200\\\"><h3 class=\\\"text-2xl font-bold mb-6\\\">Amenities<\/h3><div class=\\\"grid grid-cols-2 sm:grid-cols-3 gap-6\\\">${amenitiesHtml}<\/div><\/div><div class=\\\"py-8 mt-8 bg-white p-6 rounded-xl border border-gray-200\\\"><h3 class=\\\"text-2xl font-bold mb-6\\\">FAQs<\/h3><div class=\\\"space-y-4\\\">${faqsHtml}<\/div><\/div><\/div><div class=\\\"w-full lg:w-1/3\\\"><div class=\\\"sticky top-24 space-y-8\\\"><div class=\\\"p-6 bg-white rounded-xl border border-gray-200\\\"><h3 class=\\\"text-xl font-bold mb-4\\\">Check Availability<\/h3><form class=\\\"space-y-4\\\"><div><label class=\\\"block text-sm font-medium mb-1\\\" for=\\\"check-in\\\">Check-in date<\/label><div class=\\\"relative\\\"><span class=\\\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\\\">calendar_month<\/span><input class=\\\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary\\\" id=\\\"check-in\\\" placeholder=\\\"Select date\\\" type=\\\"text\\\"/><\/div><\/div><div><label class=\\\"block text-sm font-medium mb-1\\\" for=\\\"check-out\\\">Check-out date<\/label><div class=\\\"relative\\\"><span class=\\\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\\\">calendar_month<\/span><input class=\\\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary\\\" id=\\\"check-out\\\" placeholder=\\\"Select date\\\" type=\\\"text\\\"/><\/div><\/div><div><label class=\\\"block text-sm font-medium mb-1\\\" for=\\\"guests\\\">Guests<\/label><div class=\\\"relative\\\"><span class=\\\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\\\">group<\/span><select class=\\\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary appearance-none\\\" id=\\\"guests\\\"><option>2 adults, 0 children<\/option><option>1 adult, 0 children<\/option><option>2 adults, 1 child<\/option><\/select><span class=\\\"material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-neutral-700 pointer-events-none\\\">expand_more<\/span><\/div><\/div><button class=\\\"w-full bg-primary text-white font-bold py-3 px-4 rounded-lg\\\" type=\\\"submit\\\">Check available rooms<\/button><\/form><\/div><div class=\\\"p-6 bg-white rounded-xl border border-gray-200\\\"><h3 class=\\\"text-xl font-bold mb-4\\\">Useful Information<\/h3><div class=\\\"space-y-4\\\">${usefulInfoHtml}<\/div><\/div><\/div><\/div><\/div><\/div><\/div><\/main><\/div><\/body></html>`
}

const addFaq = () => {
  tpl.value.faqs = [...(tpl.value.faqs || []), { q: '', a: '' }]
}

const addInfo = () => {
  tpl.value.info = [...(tpl.value.info || []), { subject: '', description: '' }]
}

const removeFaq = (idx) => {
  tpl.value.faqs = (tpl.value.faqs || []).filter((_, i) => i !== idx)
}

const removeInfo = (idx) => {
  tpl.value.info = (tpl.value.info || []).filter((_, i) => i !== idx)
}


const submit = async () => {
  saving.value = true
  msg.value = ''
  try {
    const primaryName = 'Stays'
    const vpsId = form.value.vps_server_id
    if (!vpsId) {
      msg.value = 'Chưa chọn VPS Server'
      msgType.value = 'error'
      saving.value = false
      return
    }
    const websitePayload = { domain: fullDomain.value, type: 'html', vps_server_id: vpsId }
    let site
    try {
      const resp = await axios.post('/api/websites', websitePayload)
      site = resp.data
    } catch (e) {
      const m = e?.response?.data?.message || e?.response?.data?.error || ''
      if ((e?.response?.status === 409) || /domain/i.test(m)) {
        const listResp = await axios.get('/api/websites')
        site = (listResp.data || []).find(w => w.domain === fullDomain.value)
        if (!site) throw e
      } else {
        throw e
      }
    }

    

    // try saving page content
    const pagePayload = { path: '/', filename: 'index.html', title: templateType.value === 'blank' ? (tpl.value.title || '') : tpl.value.title, content: buildHtml() }
    pagePayload.content = pagePayload.content.replace('<a class=\"text-neutral-700 text-sm font-medium\" href=\"#\">Stays<\/a>', `<a class=\"text-neutral-700 text-sm font-medium\" href=\"#\">${primaryName}<\/a>`)
    try {
      await axios.post(`/api/websites/${site.id}/pages`, pagePayload)
    } catch (err) {
      const pm = err?.response?.data?.message || err?.response?.data?.error || ''
      if ((err?.response?.status === 409) || /path/i.test(pm)) {
        const pr = await axios.get(`/api/websites/${site.id}/pages`)
        const existing = (pr.data || []).find(p => (p.path || '/') === '/' && (p.filename || '').toLowerCase() === 'index.html')
        if (existing) await axios.put(`/api/pages/${existing.id}`, pagePayload)
      } else if (/Failed to write page/i.test(pm)) {
        const key = `subdomainDraft:${site.id}`
        localStorage.setItem(key, JSON.stringify(pagePayload))
      }
    }

    msg.value = 'Subdomain created. Deploy to go online.'
    msgType.value = 'success'
    router.push(`/websites/${websiteId}/subdomains`)
  } catch (e) {
    msg.value = e?.response?.data?.message || e?.response?.data?.error || 'Failed to create subdomain'
    msgType.value = 'error'
  } finally {
    saving.value = false
  }
}

const init = async () => {
  const parentResp = await axios.get(`/api/websites/${websiteId}`)
  parentWebsite.value = parentResp.data
  parentDomain.value = parentWebsite.value.domain
  const sv = await axios.get('/api/vps')
  servers.value = sv.data.filter(s => s.status === 'active')
  if (parentWebsite.value?.vps_server_id) form.value.vps_server_id = parentWebsite.value.vps_server_id
}

onMounted(init)
</script>