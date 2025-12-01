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
              <input v-model="form.title" @input="tpl.title = form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
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
                  <div v-for="f in flattenedFolders" :key="f.id" class="flex items-center gap-2 cursor-pointer">
                    <button type="button" class="text-yellow-500 text-xs" :disabled="primaryFolderId && primaryFolderId !== f.id" @click="togglePrimary(f.id)">{{ primaryFolderId === f.id ? '★' : '☆' }}</button>
                    <input type="checkbox" :value="f.id" v-model="selectedFolderIds" @change="onFolderCheckChange(f.id)" class="rounded cursor-pointer" />
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
                <option :value="null">Auto (theo website)</option>
                <option value="blank">Custom HTML</option>
                <optgroup label="Templates trong package">
                  <option value="home">Home Template</option>
                  <option value="listing">Listing Template</option>
                  <option value="detail">Detail Template</option>
                  <option value="page">Page Template</option>
                </optgroup>
              </select>
              <p class="mt-1 text-xs text-gray-500">
                Auto: Tự động chọn template phù hợp. Custom HTML: Tự viết HTML. Templates: Chọn template có sẵn trong package.
              </p>
            </div>
            <div v-if="templateType === 'page'" class="mb-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Nội dung trang</label>
                <textarea id="page-content-editor" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
              </div>
            </div>
            <div v-if="templateType === 'detail'" class="mb-4 space-y-4">
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
                <textarea id="about1-editor" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Amenities</label>
                <div class="mt-1 border border-gray-300 rounded-md p-2 max-h-64 overflow-auto">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <label v-for="opt in AMENITIES_OPTIONS" :key="opt" class="inline-flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" :value="opt" v-model="tpl.amenities" class="rounded cursor-pointer border-gray-300" />
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
            <div v-if="(templateType === 'blank' || (!isNew && !isTemplatePage)) && templateType !== 'page'" class="mb-4">
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
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
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
const tpl = ref({ title: '', location: '', phone: '', galleryRaw: '', about1: '', pageContent: '', amenities: [], info: [{ subject: '', description: '' }, { subject: '', description: '' }], faqs: [{ q: '', a: '' }, { q: '', a: '' }] })
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
    isTemplatePage.value = !!page.template_type
    if (page.template_type === 'page' && page.template_data) {
      tpl.value = {
        ...tpl.value,
        pageContent: page.template_data.content || ''
      }
    } else if ((page.template_type === 'hotel-detail' || page.template_type === 'hotel-detail-1' || page.template_type === 'detail') && page.template_data) {
      let galleryRaw = page.template_data.galleryRaw || ''
      if (!galleryRaw && Array.isArray(page.template_data.gallery)) {
        galleryRaw = page.template_data.gallery.join('\n')
      }
      tpl.value = {
        title: page.template_data.title || '',
        location: page.template_data.location || page.template_data.location_text || '',
        phone: page.template_data.phone || '',
        galleryRaw: galleryRaw,
        about1: page.template_data.about1 || '',
        pageContent: '',
        amenities: Array.isArray(page.template_data.amenities) ? page.template_data.amenities : [],
        info: Array.isArray(page.template_data.info) ? page.template_data.info : [{ subject: '', description: '' }, { subject: '', description: '' }],
        faqs: Array.isArray(page.template_data.faqs) ? page.template_data.faqs : [{ q: '', a: '' }, { q: '', a: '' }]
      }
    }
    templateType.value = page.template_type
  } else {
    templateType.value = 'blank'
    isTemplatePage.value = false
  }
  selectedFolderIds.value = (page.folders || []).map(x => Number(x.id))
  primaryFolderId.value = page.primary_folder_id == null ? null : Number(page.primary_folder_id)
}

const buildPageHtml = async () => {
  const pageTitle = (form.value.title || '').trim()
  let base = ''
  try {
    const tResp = await axios.get('/templates/laravel-hotel-1/page/index.html')
    base = tResp.data || ''
  } catch (e) {
    base = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{TITLE}}</title></head><body></body></html>'
  }

  let sh = ''
  let sf = ''
  try { const hResp = await axios.get('/templates/_shared/header.html'); sh = hResp.data || '' } catch {}
  try { const fResp = await axios.get('/templates/_shared/footer.html'); sf = fResp.data || '' } catch {}

  if (sh) {
    base = base.replace(/<header[^>]*>[\s\S]*?<\/header>/i, sh)
  }
  if (sf) {
    if (/(?:<!--\s*Footer\s*-->\s*)?<footer[^>]*>[\s\S]*?<\/footer>/i.test(base)) {
      base = base.replace(/(?:<!--\s*Footer\s*-->\s*)?<footer[^>]*>[\s\S]*?<\/footer>/i, sf)
    } else if (/<\/body>/i.test(base)) {
      base = base.replace(/<\/body>/i, sf + '</body>')
    } else {
      base += '\n' + sf
    }
  }

  const dataObj = {
    title: pageTitle || 'Page',
    content: tpl.value.pageContent || ''
  }
  const dataScript = `<script type="application/json" id="page-data">${JSON.stringify(dataObj)}<\/script>`
  base = base.replace(/<\/head>/i, dataScript + '\n</head>')
  base = base.replace(/<title>[^<]*<\/title>/i, `<title>${dataObj.title}<\/title>`)
  return base || '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + (pageTitle || 'Page') + '</title></head><body><div id="page-content">' + (tpl.value.pageContent || '') + '</div></body></html>'
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
  const tResp = await axios.get('/templates/laravel-hotel-1/detail/index.html')
  let base = tResp.data || ''
  let sh = ''
  let sf = ''
  try { const hResp = await axios.get('/templates/_shared/header.html'); sh = hResp.data || '' } catch {}
  try { const fResp = await axios.get('/templates/_shared/footer.html'); sf = fResp.data || '' } catch {}
  if (sh) {
    base = base.replace(/<header[^>]*>[\s\S]*?<\/header>/i, sh)
  }
  if (sf) {
    if (/(?:<!--\s*Footer\s*-->\s*)?<footer[^>]*>[\s\S]*?<\/footer>/i.test(base)) {
      base = base.replace(/(?:<!--\s*Footer\s*-->\s*)?<footer[^>]*>[\s\S]*?<\/footer>/i, sf)
    } else if (/<\/body>/i.test(base)) {
      base = base.replace(/<\/body>/i, sf + '</body>')
    } else {
      base += '\n' + sf
    }
  }
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
    const pageEditor = window.tinymce?.get('page-content-editor')
    if (pageEditor) {
      tpl.value.pageContent = pageEditor.getContent() || ''
    }

    if (isNew.value) {
      let adjusted
      if (templateType.value === 'blank') {
        adjusted = (htmlRaw.value || form.value.content || '')
      } else if (templateType.value === 'page') {
        adjusted = await buildPageHtml()
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
      if (templateType.value === 'page') {
        payload.template_type = 'page'
        payload.template_data = {
          title: (form.value.title || '').trim() || 'Page',
          content: tpl.value.pageContent || ''
        }
      } else if (templateType.value !== 'blank') {
        payload.template_type = templateType.value
        const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
        payload.template_data = {
          title: (tpl.value.title || form.value.title || '').trim() || 'Hotel',
          location: tpl.value.location || '',
          location_text: tpl.value.location || '',
          phone: tpl.value.phone || '',
          about1: tpl.value.about1 || '',
          amenities: tpl.value.amenities || [],
          faqs: tpl.value.faqs || [],
          info: tpl.value.info || [],
          gallery: g,
          galleryRaw: tpl.value.galleryRaw || '',
          breadcrumb_items: ['Home', 'Stays', (tpl.value.title || '').trim()].filter(Boolean)
        }
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
      } else if (templateType.value === 'page') {
        adjusted = await buildPageHtml()
      } else {
        adjusted = await buildHtmlExternal()
      }
      payload.content = adjusted
      payload.filename = (payload.filename || 'index.html').trim()
      if (templateType.value === 'page') {
        payload.template_type = 'page'
        payload.template_data = {
          title: (form.value.title || '').trim() || 'Page',
          content: tpl.value.pageContent || ''
        }
      } else if (templateType.value !== 'blank') {
        payload.template_type = templateType.value
        const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
        payload.template_data = {
          title: (tpl.value.title || form.value.title || '').trim() || 'Hotel',
          location: tpl.value.location || '',
          location_text: tpl.value.location || '',
          phone: tpl.value.phone || '',
          about1: tpl.value.about1 || '',
          amenities: tpl.value.amenities || [],
          faqs: tpl.value.faqs || [],
          info: tpl.value.info || [],
          gallery: g,
          galleryRaw: tpl.value.galleryRaw || '',
          breadcrumb_items: ['Home', 'Stays', (tpl.value.title || '').trim()].filter(Boolean)
        }
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
    const errMsg = e?.response?.data?.message || e?.response?.data?.error || e?.message || 'Lưu trang thất bại'
    alert(errMsg)
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
  // Auto-select parent folders when child is selected
  if (selectedFolderIds.value.includes(fid)) {
    const folder = folders.value.find(f => f.id === fid)
    if (folder && folder.parent_id) {
      // Recursively add all parents
      const addParents = (parentId) => {
        if (!parentId) return
        if (!selectedFolderIds.value.includes(parentId)) {
          selectedFolderIds.value.push(parentId)
        }
        const parent = folders.value.find(f => f.id === parentId)
        if (parent && parent.parent_id) {
          addParents(parent.parent_id)
        }
      }
      addParents(folder.parent_id)
    }
  }

  // Clear primary folder if it's unchecked
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

const ensureTiny = async () => {
  if (window.tinymce) return

  if (!document.querySelector('script[src*="tinymce.min.js"]')) {
    const script = document.createElement('script')
    script.src = '/dist/tinymce/tinymce.min.js'
    document.head.appendChild(script)

    await new Promise((resolve, reject) => {
      script.onload = resolve
      script.onerror = reject
    })
  }

  await new Promise(resolve => {
    const check = () => {
      if (window.tinymce) {
        resolve()
      } else {
        setTimeout(check, 50)
      }
    }
    check()
  })
}

const initPageEditor = async () => {
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 100))
  const pageEl = document.getElementById('page-content-editor')
  if (!pageEl) {
    return
  }
  await ensureTiny()
  const existingEditor = window.tinymce.get('page-content-editor')
  if (existingEditor) {
    existingEditor.remove()
  }
  pageEl.value = tpl.value.pageContent || ''
  window.tinymce.init({
    selector: '#page-content-editor',
    base_url: '/dist/tinymce',
    suffix: '.min',
    menubar: true,
    plugins: 'link lists table image code charmap anchor searchreplace visualblocks fullscreen insertdatetime media help wordcount',
    toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | charmap anchor | code fullscreen | help',
    height: 500,
    table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol',
    image_title: true,
    automatic_uploads: false,
    file_picker_types: 'image',
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
    setup: (editor) => {
      editor.on('Change KeyUp SetContent', () => {
        tpl.value.pageContent = editor.getContent() || ''
      })
    }
  }).then((editors) => {
    const ed = editors && editors[0]
    if (ed && (tpl.value.pageContent || '').trim()) {
      ed.setContent(tpl.value.pageContent)
    }
  })
}

watch(templateType, async (newVal) => {
  if (newVal === 'page') {
    await initPageEditor()
  }
})

onMounted(async () => {
  await fetchSettings()
  await Promise.all([fetchWebsite(), fetchAllWebsites()])
  await fetchFolders()
  await fetchPage()
  await nextTick()
  const el = document.getElementById('about1-editor')
  if (el) {
    await ensureTiny()
    el.value = tpl.value.about1 || ''
    window.tinymce.init({
      selector: '#about1-editor',
      base_url: '/dist/tinymce',
      suffix: '.min',
      menubar: false,
      plugins: 'link lists',
      toolbar: 'bold italic underline | bullist numlist | link',
      height: 320,
      setup: (editor) => {
        editor.on('Change KeyUp SetContent', () => {
          tpl.value.about1 = editor.getContent() || ''
        })
      }
    }).then((editors) => {
      const ed = editors && editors[0]
      if (ed && (tpl.value.about1 || '').trim()) {
        ed.setContent(tpl.value.about1)
      }
    })
  }

  if (templateType.value === 'page') {
    await initPageEditor()
  }
})

onUnmounted(() => {
  if (window.tinymce) {
    const ed1 = window.tinymce.get('about1-editor')
    if (ed1) ed1.remove()
    const ed2 = window.tinymce.get('page-content-editor')
    if (ed2) ed2.remove()
  }
})
</script>
