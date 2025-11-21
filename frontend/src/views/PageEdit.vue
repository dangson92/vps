<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">{{ isNew ? 'Create Page' : 'Edit Page' }}</h1>
          <div class="flex items-center gap-2">
              <a v-if="!isNew" :href="pageUrl()" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-indigo-600 hover:bg-gray-50" title="View">
                <ExternalLink class="size-4" />
              </a>
              <a v-if="!isNew" :href="previewUrl()" target="_blank" class="px-3 h-9 inline-flex items-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">Preview</a>
              <router-link :to="backLink" class="px-4 py-2 rounded-md border border-gray-300 bg-white">Back</router-link>
            </div>
          </div>

        <div class="bg-white shadow rounded-lg p-6">
          <form @submit.prevent="save" novalidate>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Path</label>
              <input v-model="form.path" type="text" placeholder="/about-us" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Filename</label>
              <input v-model="form.filename" type="text" placeholder="index.html" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Title</label>
              <input v-model="form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
            </div>
            <div class="mb-4">
              <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700">Thư mục</label>
                <div class="flex items-center gap-3">
                  <router-link :to="`/websites/${websiteId}/folders`" class="text-sm text-primary">Quản lý thư mục</router-link>
                  <button type="button" @click="showFolderModal = true" class="text-sm text-primary">+ Tạo nhanh</button>
                </div>
              </div>
              <div class="mt-1 border border-gray-300 rounded-md p-3 space-y-3">
                <div class="space-y-1">
                  <label class="text-sm text-gray-700">Chọn thư mục</label>
                  <div v-for="f in flattenedFolders" :key="f.id" class="flex items-center gap-2">
                    <button type="button" class="text-yellow-500 text-xs" :disabled="primaryFolderId && primaryFolderId !== f.id" @click="togglePrimary(f.id)">{{ primaryFolderId === f.id ? '★' : '☆' }}</button>
                    <input type="checkbox" :value="f.id" v-model="selectedFolderIds" @change="onFolderCheckChange(f.id)" />
                    <span class="text-sm" :style="{ paddingLeft: (f.depth > 0 ? f.depth * 16 : 0) + 'px' }">{{ '↳ '.repeat(f.depth) }}{{ f.name }}</span>
                  </div>
                </div>
              </div>
              <div v-if="showFolderModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow p-6 w-full max-w-md">
                  <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Tạo thư mục</h3>
                    <button type="button" @click="showFolderModal = false" class="text-gray-500">✕</button>
                  </div>
                  <div class="space-y-3">
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Tên thư mục</label>
                      <input v-model="newFolderName" @input="onNewFolderNameInput" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Slug</label>
                      <input v-model="newFolderSlug" @input="slugTouched = true" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Thuộc thư mục</label>
                      <select v-model="newFolderParentId" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option :value="null">(Không có)</option>
                        <option v-for="f in flattenedFolders" :key="f.id" :value="f.id">{{ '↳ '.repeat(f.depth) + f.name }}</option>
                      </select>
                    </div>
                    <div class="pt-2 border-t border-gray-200">
                      <div class="text-xs text-gray-600">Sửa/Xóa chi tiết vui lòng vào trang <router-link :to="`/websites/${websiteId}/folders`" class="text-primary">Quản lý thư mục</router-link></div>
                    </div>
                  </div>
                  <div class="flex justify-end gap-2 mt-6">
                    <button type="button" @click="showFolderModal = false" class="px-4 py-2 rounded-md border border-gray-300">Hủy</button>
                    <button type="button" @click="createFolderInModal" class="px-4 py-2 bg-blue-600 text-white rounded-md">Tạo</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Template giao diện</label>
              <select v-model="templateType" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="blank">HTML trống</option>
                <option value="hotel-detail-1">Hotel Detail 1</option>
              </select>
            </div>
            <div v-if="templateType === 'hotel-detail' || templateType === 'hotel-detail-1'" class="mb-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Địa điểm</label>
                <input v-model="tpl.location" type="text" placeholder="Sunny Isles, Florida, United States hoặc iframe Google Maps embed" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
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
            <div v-if="isNew && templateType === 'blank'" class="mb-4">
              <label class="block text-sm font-medium text-gray-700">HTML</label>
              <textarea v-model="htmlRaw" rows="12" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono"></textarea>
            </div>
            <div v-if="templateType === 'blank' || (!isNew && !isTemplatePage)" class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Content</label>
              <div v-if="website?.type === 'html' && (!isNew || templateType === 'blank')" class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
                <div ref="gutterRef" class="border border-gray-300 rounded-l-md bg-gray-50 overflow-auto" style="max-height: 360px;">
                  <div v-for="i in lineCount" :key="i" class="px-2 text-xs text-gray-500 leading-6">{{ i }}</div>
                </div>
                <textarea ref="codeRef" v-model="form.content" rows="12" required class="block w-full border border-gray-300 rounded-r-md shadow-sm font-mono leading-6" @scroll="syncScroll"></textarea>
              </div>
              <textarea v-else v-model="form.content" rows="12" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
            </div>
            <div class="flex justify-end gap-3">
              <router-link :to="backLink" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</router-link>
              <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-blue-400">
                <span v-if="saving" class="inline-flex items-center gap-2">
                  <Loader2 class="size-4 animate-spin" />
                  {{ isNew ? 'Creating...' : 'Saving...' }}
                </span>
                <span v-else>{{ isNew ? 'Create' : 'Save' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { Loader2, ExternalLink } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const websiteId = route.params.websiteId
const pageId = route.params.pageId
const isNew = ref(route.name === 'PageCreate' || pageId === 'new' || !pageId)
const templateType = ref('blank')
const isTemplatePage = ref(false)
const htmlRaw = ref('')
const tpl = ref({ title: '', location: '', phone: '', galleryRaw: '', about1: '', amenities: [], info: [{ subject: '', description: '' }, { subject: '', description: '' }], faqs: [{ q: '', a: '' }, { q: '', a: '' }] })
const folders = ref([])
const selectedFolderIds = ref([])
const primaryFolderId = ref(null)
const newFolderName = ref('')
const newFolderParentId = ref(null)
const showFolderModal = ref(false)
const newFolderSlug = ref('')
const slugTouched = ref(false)
const editingFolderId = ref(null)
const editFolderName = ref('')
const editFolderSlug = ref('')
const editFolderParentId = ref(null)
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
const form = ref({ path: '', filename: '', title: '', content: '' })
const website = ref(null)
const allWebsites = ref([])
const settings = ref(null)
const saving = ref(false)
const gutterRef = ref(null)
const codeRef = ref(null)

const fetchPage = async () => {
  if (isNew.value) return
  const resp = await axios.get(`/api/pages/${pageId}`)
  const page = resp.data
  form.value = {
    path: page.path,
    filename: page.filename,
    title: page.title || '',
    content: page.content || ''
  }
  if (page.template_type) {
    templateType.value = page.template_type
    isTemplatePage.value = !!page.template_type
    if ((page.template_type === 'hotel-detail' || page.template_type === 'hotel-detail-1') && page.template_data) {
      tpl.value = {
        title: page.template_data.title || '',
        location: page.template_data.location || '',
        phone: page.template_data.phone || '',
        galleryRaw: page.template_data.galleryRaw || '',
        about1: page.template_data.about1 || '',
        amenities: Array.isArray(page.template_data.amenities) ? page.template_data.amenities : [],
        info: Array.isArray(page.template_data.info) ? page.template_data.info : [{ subject: '', description: '' }, { subject: '', description: '' }],
        faqs: Array.isArray(page.template_data.faqs) ? page.template_data.faqs : [{ q: '', a: '' }, { q: '', a: '' }]
      }
    }
  } else {
    templateType.value = 'blank'
    isTemplatePage.value = false
  }
  selectedFolderIds.value = (page.folders || []).map(x => Number(x.id))
  primaryFolderId.value = page.primary_folder_id == null ? null : Number(page.primary_folder_id)
}

const buildHtmlExternal = async () => {
  const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
  const selectedIds = selectedFolderIds.value || []
  const deepestId = selectedIds.reduce((acc, id) => {
    if (acc == null) return id
    const da = folderDepth(acc)
    const db = folderDepth(id)
    return db > da ? id : acc
  }, null)
  const chain = folderChain(deepestId)
  const chainReduced = chain.length > 1 ? [chain[0], chain[chain.length - 1]] : chain
  const pageTitle = (form.value.title || '').trim()
  const crumbItems = ['Home', ...chainReduced.map(c => c.name || ''), pageTitle]
  const tResp = await axios.get('/templates/hotel-detail-1/index.html')
  let base = tResp.data || ''
  const amenitiesSelected = (tpl.value.amenities || [])
  const imagesEscaped = g.map(u => String(u).replace(/<\/script>/gi,'<\\/script>'))
  const dataObj = {
    title: pageTitle || 'Hotel',
    location: tpl.value.location || '',
    location_text: tpl.value.location || '',
    phone: tpl.value.phone || '',
    about1: tpl.value.about1 || '',
    amenities: amenitiesSelected,
    faqs: tpl.value.faqs || [],
    info: tpl.value.info || [],
    gallery: imagesEscaped,
    breadcrumb_items: crumbItems.filter(Boolean)
  }
  const dataScript = `<script type="application/json" id="page-data">${JSON.stringify(dataObj)}<\/script>`
  base = base.replace('{{GALLERY_DATA_SCRIPT}}', dataScript)
  base = base.replace(/<title>[^<]*<\/title>/i, `<title>${dataObj.title}<\/title>`)
  return base
}

const __unused_applyHeaderNavAdjustments = (html) => {
  return html
  let out = html
  const pageTitle = (form.value.title || '').trim()
  out = out.replace(
    '<header class="sticky top-0 z-50 bg-background-light/80 backdrop-blur-sm border-b border-gray-200">',
    '<header class="sticky top-0 z-50 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">'
  )
  out = out.replace('<div class="hidden md:flex flex-1 justify-center items-center gap-9">', '<div class="hidden">')
  out = out.replace('<a class="text-sm font-medium hover:text-primary transition-colors" href="#">Stays</a>', '<a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors" href="#section-about">About</a>')
  out = out.replace('<a class="text-sm font-medium hover:text-primary transition-colors" href="#">Flights</a>', '<a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors" href="#section-amenities">Amenities</a>')
  out = out.replace('<a class="text-sm font-medium hover:text-primary transition-colors" href="#">Packages</a>', '<a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors" href="#section-address">Location</a>')
  out = out.replace('<a class="text-sm font-medium hover:text-primary transition-colors" href="#">Sign In</a>', '<a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors" href="#section-faqs">FAQs</a>')
  out = out.replace('<p class="text-neutral-700">', '<p id="section-address" class="text-neutral-700 dark:text-neutral-300">')
  out = out.replace('<h3 class="text-2xl font-bold mb-4">About ', '<h3 id="section-about" class="text-2xl font-bold mb-4">About ')
  out = out.replace('<h3 class="text-2xl font-bold mb-6">Amenities', '<h3 id="section-amenities" class="text-2xl font-bold mb-6">Amenities')
  out = out.replace('<h3 class="text-2xl font-bold mb-6">FAQs', '<h3 id="section-faqs" class="text-2xl font-bold mb-6">FAQs')
  if (pageTitle) {
    out = out.replace(/<title>[^<]*<\/title>/i, `<title>${pageTitle}<\/title>`)
    out = out.replace(/<p class=\"text-4xl font-extrabold\">[^<]*<\/p>/, `<p class=\"text-4xl font-extrabold\">${pageTitle}<\/p>`)
    out = out.replace(/<h3[^>]*id=\"section-about\"[^>]*>About [^<]*<\/h3>/, `<h3 id=\"section-about\" class=\"text-2xl font-bold mb-4\">About ${pageTitle}<\/h3>`) 
  }
  const navHtml = '<nav class="hidden md:block bg-background-light dark:bg-background-dark border-t border-gray-200 dark:border-gray-800"><div class="max-w-7xl mx-auto flex justify-start gap-8 px-4 sm:px-6 lg:px-8 py-3"><a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors pb-2" href="#section-about">About</a><a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors pb-2" href="#section-amenities">Amenities</a><a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors pb-2" href="#section-address">Location</a><a class="section-link text-sm font-medium text-neutral-700 dark:text-gray-300 hover:text-primary dark:hover:text-primary transition-colors pb-2" href="#section-faqs">FAQs</a></div></nav>'
  out = out.replace('</header>', navHtml + '</header>')
  const ioScript2 = '<script>(function(){var links=document.querySelectorAll(".section-link");var map={};for(var i=0;i<links.length;i++){var a=links[i];var href=a.getAttribute("href")||"";map[href.replace("#","")]=a;}var active=null;var setActive=function(a){if(active){active.classList.remove("text-primary","border-b-2","border-primary");}if(a){a.classList.add("text-primary","border-b-2","border-primary");active=a;}};var observer=new IntersectionObserver(function(entries){for(var j=0;j<entries.length;j++){var ent=entries[j];if(ent.isIntersecting){var id=ent.target.id;var l=map[id];if(l){setActive(l);}}}},{rootMargin:"-50% 0px -40% 0px",threshold:0.1});var secs=document.querySelectorAll("[id^=section-]");for(var k=0;k<secs.length;k++){observer.observe(secs[k]);}})();</scr' + 'ipt>'
  out = out.replace('</body></html>', ioScript2 + '</body></html>')
  const footer = '<footer class="bg-neutral-900 text-neutral-100 dark:bg-background-dark/90 py-12"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8"><div class="flex flex-col gap-4"><div class="flex items-center gap-2 text-neutral-100"><div class="size-6 text-primary"><svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" d="M12.0799 24L4 19.2479L9.95537 8.75216L18.04 13.4961L18.0446 4H29.9554L29.96 13.4961L38.0446 8.75216L44 19.2479L35.92 24L44 28.7521L38.0446 39.2479L29.96 34.5039L29.9554 44H18.0446L18.04 34.5039L9.95537 39.2479L4 28.7521L12.0799 24Z" fill="currentColor" fill-rule="evenodd"></path></svg></div><h3 class="text-xl font-bold tracking-[-0.015em]">Voyage</h3></div><p class="text-sm text-neutral-400 leading-relaxed">Voyage helps you find the best deals on hotels, flights, and packages for your next adventure. Explore the world with ease and comfort.</p><p class="text-xs text-neutral-500 mt-4">© 2023 Voyage. All rights reserved.</p></div><div><h4 class="text-lg font-semibold text-white mb-4">Explore</h4><ul class="space-y-2"><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Home</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Stays</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Flights</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Packages</a></li></ul></div><div><h4 class="text-lg font-semibold text-white mb-4">Support</h4><ul class="space-y-2"><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Help Center</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Contact Us</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Privacy Policy</a></li><li><a class="text-sm text-neutral-400 hover:text-primary transition-colors" href="#">Terms of Service</a></li></ul></div><div><h4 class="text-lg font-semibold text-white mb-4">Connect</h4><div class="flex gap-4"><a aria-label="Facebook" class="text-neutral-400 hover:text-primary transition-colors" href="#"><svg class="size-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h3V2h-3c-3.18 0-4 1.25-4 4v3.5H7v4h3v8h4v-8z"></path></svg></a><a aria-label="Twitter" class="text-neutral-400 hover:text-primary transition-colors" href="#"><svg class="size-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.46 6c-.85.37-1.77.62-2.73.73.96-.58 1.7-1.5 2.04-2.52-.9.53-1.89.92-2.93 1.12C18.6 4.75 17.33 4 15.93 4c-2.73 0-4.94 2.21-4.94 4.94 0 .39.04 .77 .12 1.13C7.54 9.11 4.07 7.23 1.83 4.34c-.45 .77 -.7 1.67 -.7 2.64 0 1.71 .87 3.22 2.19 4.1 -.8 -.02 -1.55 -.24 -2.2 -.6v .06 c0 2.39 1.7 4.38 3.95 4.83 -.41 .11 -.85 .17 -1.29 .17 -.32 0 -.63 -.03 -.93 -.09 .63 1.96 2.44 3.38 4.6 3.42 -1.68 1.32 -3.8 2.1 -6.1 2.1 -.4 0 -.79 -.02 -1.18 -.07 C3.17 21.01 5.6 22 8.1 22c7.32 0 12.35 -6.4 12.07 -12.8 .76 -.55 1.4 -1.23 1.94 -2.01z"></path></svg></a><a aria-label="Instagram" class="text-neutral-400 hover:text-primary transition-colors" href="#"><svg class="size-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4c0 3.2 -2.6 5.8 -5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8C2 4.6 4.6 2 7.8 2m -.2 2.2V7.8c0 3.1 2.5 5.6 5.6 5.6s5.6 -2.5 5.6 -5.6V4.2H7.6zM12 15.6c-2 0 -3.6 1.6 -3.6 3.6 s1.6 3.6 3.6 3.6 3.6 -1.6 3.6 -3.6 -1.6 -3.6 -3.6 -3.6zM16.8 5.4a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0 -2.4z"></path></svg></a></div></div></div></footer>'
  out = out.replace('</body></html>', footer + '</body></html>')
  return out
}

const fetchWebsite = async () => {
  const resp = await axios.get(`/api/websites/${websiteId}`)
  website.value = resp.data
}
const fetchAllWebsites = async () => {
  try {
    const resp = await axios.get('/api/websites')
    allWebsites.value = resp.data || []
  } catch (e) {
    allWebsites.value = []
  }
}

const backLink = computed(() => {
  const w = website.value
  if (!w) return '/websites'
  const domain = (w.domain || '').trim()
  const parentDomain = domain.replace(/^[^.]+\./, '')
  if (parentDomain && parentDomain !== domain) {
    const parent = (allWebsites.value || []).find(x => String(x.domain).trim() === parentDomain)
    if (parent && parent.id) return `/websites/${parent.id}/subdomains`
  }
  return '/websites'
})
const fetchSettings = async () => {
  try {
    const resp = await axios.get('/api/settings')
    settings.value = resp.data || {}
  } catch (e) {
    settings.value = {}
  }
}

const fetchFolders = async () => {
  const resp = await axios.get(`/api/websites/${websiteId}/folders`)
  const list = resp.data || []
  const norm = list.map(f => ({
    ...f,
    id: Number(f.id),
    parent_id: f.parent_id == null ? null : Number(f.parent_id)
  }))
  folders.value = norm
}

const createFolderInModal = async () => {
  const name = (newFolderName.value || '').trim()
  if (!name) return
  let slug = (newFolderSlug.value || '').trim()
  if (!slug) slug = nameToSlug(name)
  const payload = { name, slug }
  if (newFolderParentId.value) payload.parent_id = Number(newFolderParentId.value)
  const resp = await axios.post(`/api/websites/${websiteId}/folders`, payload)
  const f = resp.data
  folders.value = [...folders.value, f]
  selectedFolderIds.value = [...selectedFolderIds.value, f.id]
  newFolderName.value = ''
  newFolderParentId.value = null
  newFolderSlug.value = ''
  slugTouched.value = false
  showFolderModal.value = false
}

const save = async () => {
  saving.value = true
  try {
    if (isNew.value) {
      let adjusted
      if (templateType.value === 'blank') {
        adjusted = (htmlRaw.value || form.value.content || '')
      } else {
        adjusted = await buildHtmlExternal()
      }
      form.value.content = adjusted
      form.value.filename = (form.value.filename || 'index.html').trim()
      const pathTrim = (form.value.path || '').trim()
      if (!pathTrim) {
        const selectedIds = selectedFolderIds.value || []
        const deepestId = selectedIds.reduce((acc, id) => {
          if (acc == null) return id
          const da = folderDepth(acc)
          const db = folderDepth(id)
          return db > da ? id : acc
        }, null)
        const baseId = primaryFolderId.value || deepestId
        if (baseId) {
          const full = folderFullSlug(baseId)
          if (full) form.value.path = `/${full}`
        }
      }
      if (!form.value.path) form.value.path = '/'
      const payload = { ...form.value }
      if (templateType.value !== 'blank') {
        payload.template_type = templateType.value
        payload.template_data = { ...tpl.value }
      } else {
        payload.template_type = 'blank'
        payload.template_data = null
      }
      payload.folder_ids = [...selectedFolderIds.value]
      payload.primary_folder_id = primaryFolderId.value
      await axios.post(`/api/websites/${websiteId}/pages`, payload)
    } else {
      const payload = { ...form.value }
      let adjusted
      if (templateType.value === 'blank') {
        adjusted = (form.value.content || '')
      } else {
        adjusted = await buildHtmlExternal()
      }
      payload.content = adjusted
      payload.filename = (payload.filename || 'index.html').trim()
      if (templateType.value !== 'blank') {
        payload.template_type = templateType.value
        payload.template_data = { ...tpl.value }
      } else {
        payload.template_type = 'blank'
        payload.template_data = null
      }
      const pathTrimU = (payload.path || '').trim()
      if (!pathTrimU) {
        const selectedIdsU = selectedFolderIds.value || []
        const deepestIdU = selectedIdsU.reduce((acc, id) => {
          if (acc == null) return id
          const da = folderDepth(acc)
          const db = folderDepth(id)
          return db > da ? id : acc
        }, null)
        const baseIdU = primaryFolderId.value || deepestIdU
        if (baseIdU) {
          const fullU = folderFullSlug(baseIdU)
          if (fullU) payload.path = `/${fullU}`
        }
      }
      if (!payload.path) payload.path = '/'
      payload.folder_ids = [...selectedFolderIds.value]
      payload.primary_folder_id = primaryFolderId.value
      await axios.put(`/api/pages/${pageId}`, payload)
    }
    router.push(`/websites/${websiteId}/pages`)
  } catch (e) {
    alert(e?.response?.data?.error || e?.message || 'Lưu trang thất bại')
  } finally {
    saving.value = false
  }
}

const pageUrl = () => {
  if (!website.value) return '#'
  const base = (website.value.ssl_enabled ? 'https://' : 'http://') + website.value.domain
  const path = form.value.path || '/'
  const fn = form.value.filename || ''
  const sep = path.endsWith('/') ? '' : '/'
  return fn.toLowerCase() === 'index.html' ? (base + path) : (base + path + sep + fn)
}

const previewUrl = () => {
  if (!website.value) return '#'
  const base = (settings.value?.APP_URL || ((website.value.ssl_enabled ? 'https://' : 'http://') + website.value.domain)).replace(/\/$/, '')
  const path = (form.value.path || '/').trim()
  let url
  if (path === '/' || path === '') {
    url = `${base}/preview/${website.value.id}`
  } else {
    const p = path.replace(/^\//, '')
    url = `${base}/preview/${website.value.id}/${encodeURIComponent(p)}`
  }
  const token = localStorage.getItem('adminToken')
  const params = new URLSearchParams()
  if (token) params.set('token', token)
  params.set('hide_preview_bar', '1')
  params.set('v', String(Date.now()))
  const qs = params.toString()
  return qs ? `${url}?${qs}` : url
}

const folderName = (id) => {
  if (!id) return ''
  const f = (folders.value || []).find(x => x.id === id)
  return f ? (f.name || '') : ''
}

const folderSlug = (id) => {
  if (!id) return ''
  const f = (folders.value || []).find(x => x.id === id)
  return f ? (f.slug || '') : ''
}

const folderFullSlug = (id) => {
  if (!id) return ''
  const map = new Map((folders.value || []).map(x => [String(x.id), x]))
  let cur = map.get(String(id))
  const parts = []
  while (cur) {
    if (cur.slug) parts.unshift(cur.slug)
    if (!cur.parent_id) break
    cur = map.get(String(cur.parent_id))
    if (cur && String(cur.id) === String(id)) break
  }
  return parts.join('/')
}

const folderChain = (id) => {
  if (!id) return []
  const map = new Map((folders.value || []).map(x => [String(x.id), x]))
  const out = []
  const seen = new Set()
  let cur = map.get(String(id))
  while (cur && !seen.has(cur.id)) {
    out.unshift(cur)
    seen.add(cur.id)
    if (!cur.parent_id) break
    cur = map.get(String(cur.parent_id))
  }
  return out
}

const folderDepth = (id) => {
  let d = 0
  const list = folders.value || []
  const map = new Map(list.map(x => [String(x.id), x]))
  let cur = list.find(x => String(x.id) === String(id))
  while (cur && cur.parent_id != null) {
    d += 1
    cur = map.get(String(cur.parent_id))
  }
  return d
}

const displayName = (id) => {
  const name = folderName(id)
  const d = folderDepth(id)
  return (d > 0 ? '↳ '.repeat(d) : '') + name
}

const flattenedFolders = computed(() => {
  const list = [...(folders.value || [])]
  const map = new Map(list.map(f => [String(f.id), f]))
  const sortByName = (arr) => arr.sort((a,b) => (a.name||'').localeCompare(b.name||''))
  const childrenOf = (pid) => list.filter(x => String(x.parent_id) === String(pid))
  const isRoot = (f) => !f.parent_id || !map.has(String(f.parent_id)) || String(f.parent_id) === String(f.id)
  const roots = sortByName(list.filter(isRoot))
  const out = []
  const visit = (node, depth) => {
    out.push({ ...node, depth })
    sortByName(childrenOf(node.id)).forEach(ch => visit(ch, depth + 1))
  }
  roots.forEach(r => visit(r, 0))
  return out
})

const nameToSlug = (s) => {
  const v = (s || '').toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-')
    .replace(/-+/g, '-')
  return v
}

const onNewFolderNameInput = () => {
  if (!slugTouched.value) newFolderSlug.value = nameToSlug(newFolderName.value)
}

const togglePrimary = (fid) => {
  if (primaryFolderId.value === fid) {
    primaryFolderId.value = null
    return
  }
  if (!primaryFolderId.value) primaryFolderId.value = fid
}

const onFolderCheckChange = (fid) => {
  if (primaryFolderId.value && !selectedFolderIds.value.includes(primaryFolderId.value)) {
    primaryFolderId.value = null
  }
}

const startEditFolder = (f) => {
  editingFolderId.value = f.id
  editFolderName.value = f.name || ''
  editFolderSlug.value = f.slug || ''
  editFolderParentId.value = f.parent_id || null
}

const cancelEditFolder = () => {
  editingFolderId.value = null
  editFolderName.value = ''
  editFolderSlug.value = ''
  editFolderParentId.value = null
}

const saveEditFolder = async () => {
  if (!editingFolderId.value) return
  const payload = {
    name: (editFolderName.value || '').trim(),
    slug: (editFolderSlug.value || '').trim(),
    parent_id: editFolderParentId.value || null,
  }
  const resp = await axios.put(`/api/websites/${websiteId}/folders/${editingFolderId.value}`, payload)
  const updated = resp.data
  folders.value = (folders.value || []).map(x => x.id === updated.id ? updated : x)
  // adjust selections if parent change
  if (!folders.value.find(x => x.id === editingFolderId.value)) {
    selectedFolderIds.value = selectedFolderIds.value.filter(id => id !== editingFolderId.value)
    if (primaryFolderId.value === editingFolderId.value) primaryFolderId.value = null
  }
  cancelEditFolder()
}

const deleteFolder = async (f) => {
  try {
    await axios.delete(`/api/websites/${websiteId}/folders/${f.id}`)
    folders.value = (folders.value || []).filter(x => x.id !== f.id)
    selectedFolderIds.value = selectedFolderIds.value.filter(id => id !== f.id)
    if (primaryFolderId.value === f.id) primaryFolderId.value = null
  } catch (e) {
    alert(e?.response?.data?.error || 'Không thể xóa thư mục')
  }
}

const __unused_buildHtml = () => {
  return ''
  if (templateType.value === 'blank') return htmlRaw.value
  const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
  const tiles = g.slice(0,5)
  const moreCount = Math.max(0, g.length - 4)
  const selectedIds = selectedFolderIds.value || []
  const deepestId = selectedIds.reduce((acc, id) => {
    if (acc == null) return id
    const da = folderDepth(acc)
    const db = folderDepth(id)
    return db > da ? id : acc
  }, null)
  const catId = deepestId
  const chain = folderChain(catId)
  const chainReduced = chain.length > 1 ? [chain[0], chain[chain.length - 1]] : chain
  const crumbItems = ['Home', ...chainReduced.map(c => c.name || ''), (form.value.title || '').trim()]
  const amenityIcon = (s) => {
    const v = (s || '').toLowerCase()
    if (/wifi|internet/.test(v)) return 'wifi'
    if (/air|conditioning|ac/.test(v)) return 'air'
    if (/tv|television|cable|satellite/.test(v)) return 'tv'
    if (/spa|wellness/.test(v)) return 'spa'
    if (/restaurant|snack|breakfast|minibar|bar/.test(v)) return 'restaurant'
    if (/security|alarm|extinguisher|smoke/.test(v)) return 'security'
    if (/shuttle|airport/.test(v)) return 'airport_shuttle'
    if (/elevator|lift/.test(v)) return 'elevator'
    if (/disabled|accessible|wheelchair/.test(v)) return 'accessible'
    if (/heating|thermo/.test(v)) return 'thermostat'
    if (/laundry|ironing|dry cleaning/.test(v)) return 'laundry'
    if (/meeting|business|fax|photocopying/.test(v)) return 'business_center'
    if (/lockers|safe|deposit/.test(v)) return 'lock'
    if (/view|scenic|terrace|patio|balcony/.test(v)) return 'landscape'
    if (/room service/.test(v)) return 'room_service'
    if (/refrigerator|kettle|electric/.test(v)) return 'kitchen'
    return 'check_circle'
  }
  const breadcrumbHtml = crumbItems.filter(Boolean).map((text, idx, arr) => {
    const isLast = idx === arr.length - 1
    const sep = idx > 0 ? '<span class=\"text-neutral-700 text-sm font-medium\">/</span>' : ''
    const item = isLast ? `<span class=\"text-neutral-900 text-sm font-medium\">${text}</span>` : `<a class=\"text-neutral-700 text-sm font-medium\" href=\"#\">${text}</a>`
    return sep + item
  }).join('')
  const scriptClose = '</scr' + 'ipt>'
  const lightboxStyles = '<style>#galleryModal .relative{background:#fff;border-radius:0.75rem;box-shadow:0 10px 25px rgba(0,0,0,.2);padding:1rem;width:min(1000px,90vw);height:80vh;}#galleryModal #thumbs{display:flex;flex-wrap:wrap;justify-content:center;gap:.25rem;width:100%;}#galleryModal #closeGallery{height:40px;width:40px;border-radius:9999px;background:#fff;color:#000;box-shadow:0 8px 16px rgba(0,0,0,.15);border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.25rem;}#galleryModal #prev,#galleryModal #next{height:48px;width:48px;border-radius:9999px;background:#fff;color:#000;box-shadow:0 8px 16px rgba(0,0,0,.15);border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.5rem;position:absolute;top:50%;transform:translateY(-50%);}#galleryModal #prev{left:-56px;}#galleryModal #next{right:-56px;}</style>'
  const lightboxEnhanceScript = `<script>(function(){document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.getElementById('galleryModal');if(m) m.remove();}});document.addEventListener('click',function(e){var m=document.getElementById('galleryModal');if(!m) return;var box=m.querySelector('.relative');var path=e.composedPath?e.composedPath():[];var inside=false;for(var i=0;i<path.length;i++){if(path[i]===box){inside=true;break;}}if(!inside && e.button===0){m.remove();}});})();` + scriptClose
  const lightboxAttachScript = `<script>(function(){var attach=function(){var btn=document.getElementById('openGalleryButton');if(btn){btn.addEventListener('click',function(){if(window.__openGallery) window.__openGallery(0);});}document.querySelectorAll('[data-idx]').forEach(function(el){el.style.cursor='pointer';el.addEventListener('click',function(){var i=Number(el.dataset.idx)||0;if(window.__openGallery) window.__openGallery(i);});});};try{attach();}catch(e){};window.addEventListener('load',attach);})();` + scriptClose
  const lightboxScript = `<script>(function(){\n  const images = ${JSON.stringify((tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean))};\n  let idx = 0;\n  const create = (tag, cls) => { const el = document.createElement(tag); if (cls) el.className = cls; return el; };\n  const openAt = (i) => {\n    idx = Math.max(0, Math.min(images.length - 1, i || 0));\n    let modal = document.getElementById('galleryModal');\n    if (!modal) {\n      modal = create('div', 'fixed inset-0 z-50 bg-black/80');\n      modal.id = 'galleryModal';\n      modal.innerHTML = '<div class=\\"absolute inset-0 flex flex-col items-center justify-center p-4\\">'+\n        '<div class=\\"relative max-w-5xl w-full\\">'+\n        '<button id=\\"closeGallery\\" class=\\"absolute -top-3 -right-3 bg-white text-black rounded-full p-1 shadow\\">✕<\\/button>'+\n        '<button id=\\"prev\\" class=\\"absolute left-0 top-1/2 -translate-y-1/2 text-white text-3xl px-3\\">‹<\\/button>'+\n        '<img data-role=\\"main\\" class=\\"w-full max-h-[70vh] object-contain rounded-lg shadow\\" src=\\"\\" />'+\n        '<button id=\\"next\\" class=\\"absolute right-0 top-1/2 -translate-y-1/2 text-white text-3xl px-3\\">›<\\/button>'+\n        '<div id=\\"thumbs\\" class=\\"mt-3 flex gap-2 overflow-x-auto\\"><\\/div>'+\n        '<\\/div>'+\n      '<\\/div>';\n      document.body.appendChild(modal);\n      const close = () => { modal.remove(); };\n      modal.querySelector('#closeGallery').addEventListener('click', close);\n      modal.addEventListener('click', (e) => { if (e.target === modal) close(); });\n      const main = modal.querySelector('img[data-role=\\"main\\"]');\n      const thumbs = modal.querySelector('#thumbs');\n      const render = () => {\n        main.src = images[idx];\n        thumbs.innerHTML = '';\n        images.forEach((src, i) => {\n          const t = create('img', 'h-16 w-24 object-cover rounded cursor-pointer ' + (i===idx ? 'ring-2 ring-white' : 'opacity-70'));\n          t.src = src;\n          t.addEventListener('click', () => { idx = i; render(); });\n          thumbs.appendChild(t);\n        });\n      };\n      modal.querySelector('#prev').addEventListener('click', () => { idx = (idx - 1 + images.length) % images.length; render(); });\n      modal.querySelector('#next').addEventListener('click', () => { idx = (idx + 1) % images.length; render(); });\n      window.__renderGallery = render;\n    }\n    window.__renderGallery();\n  };\n  window.__openGallery = openAt;\n  const attach = () => {\n    const btn = document.getElementById('openGalleryButton');\n    if (btn) btn.addEventListener('click', () => openAt(0));\n    document.querySelectorAll('[data-idx]').forEach(el => {\n      el.style.cursor = 'pointer';\n      el.addEventListener('click', () => openAt(Number(el.dataset.idx) || 0));\n    });\n  };\n  try { attach(); } catch(e) {}\n  window.addEventListener('load', attach);\n})();` + scriptClose
  const galleryHtml = tiles.map((url, idx) => {
    const baseClass = `bg-center bg-no-repeat bg-cover`
    const baseStyle = `background-image: url('${url}')`
    if (idx === 0) return `<div data-idx=\"${idx}\" onclick=\"window.__openGallery && window.__openGallery(Number(this.dataset.idx)||0)\" class=\"cursor-pointer col-span-2 row-span-2 ${baseClass}\" style=\"${baseStyle}\"></div>`
    if (idx < 4) return `<div data-idx=\"${idx}\" onclick=\"window.__openGallery && window.__openGallery(Number(this.dataset.idx)||0)\" class=\"cursor-pointer col-span-1 row-span-1 ${baseClass}\" style=\"${baseStyle}\"></div>`
    return `<div data-idx=\"${idx}\" class=\"relative col-span-1 row-span-1 ${baseClass}\" style=\"${baseStyle}\"><div class=\"absolute inset-0 bg-black/50 flex items-center justify-center\"><button id=\"openGalleryButton\" onclick=\"window.__openGallery && window.__openGallery(0)\" class=\"text-white font-bold text-lg\">+${moreCount} more<\/button><\/div><\/div>`
  }).join('')
  const lightboxDelegateScript = `<script>(function(){document.addEventListener('click',function(e){var btn=e.target.closest('#openGalleryButton');if(btn){if(window.__openGallery) window.__openGallery(0);return;}var tile=e.target.closest('[data-idx]');if(tile){var i=Number(tile.dataset.idx)||0;if(window.__openGallery) window.__openGallery(i);}});})();` + scriptClose
  const galleryBlockHtml = galleryHtml + lightboxStyles + lightboxScript + lightboxEnhanceScript + lightboxAttachScript + lightboxDelegateScript
  const amenitiesSelected = (tpl.value.amenities || [])
  const amenitiesHtml = amenitiesSelected.map(item => `<div class=\"flex items-center gap-3\"><span class=\"material-symbols-outlined text-primary\">${amenityIcon(item)}<\/span><span class=\"text-neutral-900\">${item}<\/span><\/div>`).join('')
  const faqsHtml = (tpl.value.faqs || []).filter(x => (x.q||'').trim() || (x.a||'').trim()).map((x,i,arr) => `<div class=\"${i < arr.length-1 ? 'border-b border-gray-200 pb-4' : ''}\"><h4 class=\"font-bold text-lg\">${x.q || ''}<\/h4><p class=\"text-neutral-700 mt-2\">${x.a || ''}<\/p><\/div>`).join('')
  const infoBlocks = (tpl.value.info || []).filter(x => (x.subject||'').trim() || (x.description||'').trim())
  const usefulInfoHtml = infoBlocks.map((x) => `<div class=\"flex items-start gap-4\"><span class=\"material-symbols-outlined text-primary mt-1\">description<\/span><div><h4 class=\"font-bold\">${x.subject || ''}<\/h4><p class=\"text-neutral-700 mt-1 text-sm whitespace-pre-line\">${x.description || ''}<\/p><\/div><\/div>`).join('')
  const sections = []
  if ((g || []).length > 0) sections.push({ id: 'gallery', label: 'Gallery' })
  if ((tpl.value.about1 || '').trim()) sections.push({ id: 'about', label: 'About' })
  if ((amenitiesSelected || []).length > 0) sections.push({ id: 'amenities', label: 'Amenities' })
  if ((infoBlocks || []).length > 0) sections.push({ id: 'info', label: 'Useful Info' })
  if (((tpl.value.faqs || []).filter(x => (x.q||'').trim() || (x.a||'').trim())).length > 0) sections.push({ id: 'faqs', label: 'FAQs' })
  const tocHtml = sections.map(s => `<a href=\"#section-${s.id}\" class=\"text-xs sm:text-sm font-medium px-2 py-1 rounded-md border border-gray-200 bg-white hover:bg-gray-50\">${s.label}<\/a>`).join('')
  return `<!DOCTYPE html><html class=\"light\" lang=\"en\"><head><meta charset=\"utf-8\"/><meta content=\"width=device-width, initial-scale=1.0\" name=\"viewport\"/><title>${tpl.value.title || 'Hotel'}<\/title><script src=\"https://cdn.tailwindcss.com?plugins=forms,container-queries\">${scriptClose}<link href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\"/><link href=\"https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined\" rel=\"stylesheet\"/><script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#137fec','background-light':'#f6f7f8','background-dark':'#101922','neutral-100':'#f8f9fa','neutral-700':'#6c757d','neutral-900':'#212529','accent-gold':'#FFC107'},fontFamily:{display:['Plus Jakarta Sans','sans-serif']},borderRadius:{DEFAULT:'0.5rem',lg:'0.75rem',xl:'1rem',full:'9999px'}}}};${scriptClose}<style>.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24}</style><\/head><body class=\"font-display bg-background-light text-neutral-900\"><div class=\"relative flex min-h-screen w-full flex-col overflow-x-hidden\"><header class=\"sticky top-0 z-50 bg-background-light/80 backdrop-blur-sm border-b border-gray-200\"><div class=\"flex items-center justify-between px-4 sm:px-6 lg:px-8 py-3 max-w-7xl mx-auto\"><div class=\"flex items-center gap-4\"><div class=\"size-6 text-primary\"><svg fill=\"none\" viewBox=\"0 0 48 48\" xmlns=\"http://www.w3.org/2000/svg\"><path clip-rule=\"evenodd\" d=\"M12.0799 24L4 19.2479L9.95537 8.75216L18.04 13.4961L18.0446 4H29.9554L29.96 13.4961L38.0446 8.75216L44 19.2479L35.92 24L44 28.7521L38.0446 39.2479L29.96 34.5039L29.9554 44H18.0446L18.04 34.5039L9.95537 39.2479L4 28.7521L12.0799 24Z\" fill=\"currentColor\" fill-rule=\"evenodd\"></path><\/svg><\/div><h2 class=\"text-xl font-bold\">Voyage<\/h2><\/div><div class=\"hidden md:flex flex-1 justify-center items-center gap-9\"><a class=\"text-sm font-medium hover:text-primary transition-colors\" href=\"#\">Stays<\/a><a class=\"text-sm font-medium hover:text-primary transition-colors\" href=\"#\">Flights<\/a><a class=\"text-sm font-medium hover:text-primary transition-colors\" href=\"#\">Packages<\/a><a class=\"text-sm font-medium hover:text-primary transition-colors\" href=\"#\">Sign In<\/a><\/div><div class=\"flex items-center gap-4\"><button class=\"hidden lg:flex h-10 px-4 rounded-lg bg-primary/20 text-primary text-sm font-bold\">List your property<\/button><div class=\"bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10\" style=\"background-image:url('https://lh3.googleusercontent.com/aida-public/AB6AXuDvnz9m5Lcy5st1r4eQ93WeZ3_OZBLEO_dV-B43w096b9LOTk03jISYHHQMNtw3sJOlz250eG1Ek-lCjTkm6VYs5YTqXyY5yidMs2-txDXW2ALbwaZCJqyezel6iTEu5nCXBeY3nAWTTFj6hIp8S53D5F9O6TlycifzBrXL9oURI_4f7EDPKLwWNB0XrD66w5Cel_iAeLA9J8NOp9m7hmy-_cLhgam7nOvUoHC9KL1Lc0tFkgMi_M_2k2UUkhm8uqTBY5jNX-GJ_bw')\"></div><\/div><\/div><\/header><main class=\"flex grow flex-col\"><div class=\"px-4 sm:px-6 lg:px-8 py-5\"><div class=\"max-w-7xl mx-auto\"><div class=\"flex flex-wrap gap-2 py-4\">${breadcrumbHtml}<\/div><div class=\"flex flex-wrap justify-between items-start gap-4 py-4\"><div class=\"flex flex-col gap-2\"><p class=\"text-4xl font-extrabold\">${tpl.value.title || ''}<\/p><div class=\"flex items-center gap-4\"><div class=\"flex items-center gap-1\"><span class=\"material-symbols-outlined text-accent-gold\" style=\"font-variation-settings:'FILL' 1\">star<\/span><span class=\"material-symbols-outlined text-accent-gold\" style=\"font-variation-settings:'FILL' 1\">star<\/span><span class=\"material-symbols-outlined text-accent-gold\" style=\"font-variation-settings:'FILL' 1\">star<\/span><span class=\"material-symbols-outlined text-accent-gold\" style=\"font-variation-settings:'FILL' 1\">star<\/span><span class=\"material-symbols-outlined text-accent-gold\">star_half<\/span><\/div><p class=\"text-neutral-700\">${tpl.value.location || ''}<\/p><\/div><\/div><button class=\"flex h-10 px-4 rounded-lg bg-white text-neutral-900 text-sm font-bold gap-2 border border-gray-200\"><span class=\"material-symbols-outlined text-sm\">map<\/span><span>View on map<\/span><\/button><\/div><div class=\"grid grid-cols-4 grid-rows-2 gap-4 h-[500px] rounded-xl overflow-hidden mt-6\">${galleryBlockHtml}<\/div><div class=\"flex flex-col lg:flex-row gap-8 mt-8\"><div class=\"w-full lg:w-2/3\"><div class=\"py-8 bg-white p-6 rounded-xl border border-gray-200\"><h3 class=\"text-2xl font-bold mb-4\">About ${tpl.value.title || ''}<\/h3><div class=\"space-y-4 text-neutral-700 leading-relaxed\"><p>${tpl.value.about1 || ''}<\/p><\/div><button class=\"text-primary font-bold mt-4\">Read more<\/button><\/div><div class=\"py-8 mt-8 bg-white p-6 rounded-xl border border-gray-200\"><h3 class=\"text-2xl font-bold mb-6\">Amenities<\/h3><div class=\"grid grid-cols-2 sm:grid-cols-3 gap-6\">${amenitiesHtml}<\/div><\/div><div class=\"py-8 mt-8 bg-white p-6 rounded-xl border border-gray-200\"><h3 class=\"text-2xl font-bold mb-6\">FAQs<\/h3><div class=\"space-y-4\">${faqsHtml}<\/div><\/div><\/div><div class=\"w-full lg:w-1/3\"><div class=\"sticky top-24 space-y-8\"><div class=\"p-6 bg-white rounded-xl border border-gray-200\"><h3 class=\"text-xl font-bold mb-4\">Check Availability<\/h3><form class=\"space-y-4\"><div><label class=\"block text-sm font-medium mb-1\" for=\"check-in\">Check-in date<\/label><div class=\"relative\"><span class=\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\">calendar_month<\/span><input class=\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary\" id=\"check-in\" placeholder=\"Select date\" type=\"text\"/><\/div><\/div><div><label class=\"block text-sm font-medium mb-1\" for=\"check-out\">Check-out date<\/label><div class=\"relative\"><span class=\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\">calendar_month<\/span><input class=\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary\" id=\"check-out\" placeholder=\"Select date\" type=\"text\"/><\/div><\/div><div><label class=\"block text-sm font-medium mb-1\" for=\"guests\">Guests<\/label><div class=\"relative\"><span class=\"material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-neutral-700\">group<\/span><select class=\"w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white focus:ring-primary focus:border-primary appearance-none\" id=\"guests\"><option>2 adults, 0 children<\/option><option>1 adult, 0 children<\/option><option>2 adults, 1 child<\/option><\/select><span class=\"material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-neutral-700 pointer-events-none\">expand_more<\/span><\/div><\/div><button class=\"w-full bg-primary text-white font-bold py-3 px-4 rounded-lg\" type=\"submit\">Check available rooms<\/button><\/form><\/div><div class=\"p-6 bg-white rounded-xl border border-gray-200\"><h3 class=\"text-xl font-bold mb-4\">Useful Information<\/h3><div class=\"space-y-4\">${usefulInfoHtml}<\/div><\/div><\/div><\/div><\/div><\/div><\/div><\/main><\/div><\/body><\/html>`
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

const lineCount = computed(() => {
  const v = form.value.content || ''
  const n = v.split('\n').length
  return n > 0 ? n : 1
})

const syncScroll = () => {
  if (gutterRef.value && codeRef.value) gutterRef.value.scrollTop = codeRef.value.scrollTop
}

onMounted(async () => {
  await fetchSettings()
  await Promise.all([fetchWebsite(), fetchAllWebsites()])
  await fetchFolders()
  await fetchPage()
})
</script>
