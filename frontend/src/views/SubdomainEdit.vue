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

const buildHtmlExternal = async () => {
  const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
  const pageTitle = (tpl.value.title || '').trim()
  const crumbItems = ['Home', 'Stays', pageTitle]
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
    const websitePayload = { domain: fullDomain.value, type: parentWebsite.value?.type || 'html', vps_server_id: vpsId }
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
    let adjusted
    let processedData = null
    if (templateType.value === 'blank') {
      adjusted = htmlRaw.value
    } else {
      adjusted = await buildHtmlExternal()
      // Build processed template data
      const g = (tpl.value.galleryRaw || '').split('\n').map(s => s.trim()).filter(Boolean)
      processedData = {
        title: (tpl.value.title || '').trim() || 'Hotel',
        location: tpl.value.location || '',
        location_text: tpl.value.location || '',
        phone: tpl.value.phone || '',
        about1: tpl.value.about1 || '',
        amenities: tpl.value.amenities || [],
        faqs: tpl.value.faqs || [],
        info: tpl.value.info || [],
        gallery: g,
        breadcrumb_items: ['Home', 'Stays', (tpl.value.title || '').trim()].filter(Boolean)
      }
    }
    const pagePayload = { path: '/', filename: 'index.html', title: templateType.value === 'blank' ? (tpl.value.title || '') : tpl.value.title, content: adjusted }
    if (templateType.value !== 'blank') {
      pagePayload.template_type = templateType.value
      pagePayload.template_data = processedData
    }
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