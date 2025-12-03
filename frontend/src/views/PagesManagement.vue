<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">{{ website?.domain || '...' }}</h1>
          <div class="flex items-center gap-2">
            <router-link :to="backToRoute" class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Back">
              <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            </router-link>
            <input v-model="query" placeholder="Search" class="h-9 w-56 rounded-md border border-gray-300 px-3" />
            <button @click="showImportModal = true" class="h-9 px-3 flex items-center justify-center gap-1 rounded-md border border-gray-300 bg-white text-green-600 hover:bg-gray-50" title="Import Pages">
              <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span class="text-sm">Import</span>
            </button>
            <router-link :to="`/websites/${websiteId}/pages/new`" class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-blue-600 hover:bg-gray-50" title="Add Page">
              <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </router-link>
            <button @click="deleteSelected" :disabled="selectedIds.length === 0 || deletingBulk" class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-red-600 hover:bg-gray-50 disabled:text-gray-400" :title="`Delete Selected (${selectedIds.length})`">
              <Loader2 v-if="deletingBulk" class="size-4 animate-spin" />
              <Trash2 v-else class="size-4" />
            </button>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <div class="space-y-4">
            <div v-for="page in filteredPages" :key="page.id" class="border rounded-lg p-4">
              <div class="flex justify-between items-start">
                <div>
                  <label class="inline-flex items-center gap-2 mb-2">
                    <input type="checkbox" :checked="selectedIds.includes(page.id)" @change="toggleSelect(page)" />
                  </label>
                  <h4 class="font-medium">{{ page.title || '(No title)' }}</h4>
                  <p class="text-sm text-gray-500">Path: {{ page.path }}</p>
                  <p class="text-sm text-gray-500">File: {{ page.filename }}</p>
                  <p class="text-sm text-gray-500">Website: {{ page.__site?.domain }}</p>
                </div>
                <div class="flex gap-2">
                  <a :href="pageUrl(page)" target="_blank" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-indigo-600 hover:bg-gray-50" title="View Live">
                    <ExternalLink class="size-4" />
                  </a>
                  <a :href="previewRoute(page)" target="_blank" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-orange-600 hover:bg-gray-50" title="Preview Draft">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </a>
                  <router-link :to="`/websites/${page.__site?.id || websiteId}/pages/${page.id}`" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-blue-600 hover:bg-gray-50" title="Edit">
                    <Pencil class="size-4" />
                  </router-link>
                  <button @click="deletePage(page)" :disabled="loadingDeleteIds.includes(page.id)" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-red-600 hover:bg-gray-50 disabled:text-gray-400" title="Delete">
                    <Loader2 v-if="loadingDeleteIds.includes(page.id)" class="size-4 animate-spin" />
                    <Trash2 v-else class="size-4" />
                  </button>
                </div>
              </div>
            </div>
            <div v-if="filteredPages.length === 0" class="text-center text-gray-500">No pages found</div>
          </div>
        </div>



        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showImportModal = false">
          <div class="bg-white rounded-lg shadow-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-bold">Import Pages from JSON</h2>
              <button @click="closeImportModal" class="text-gray-500 hover:text-gray-700">
                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="space-y-4">
              <!-- Step 1: Upload File -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <span class="inline-flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">1</span>
                    Upload JSON File
                  </span>
                </label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-md border border-blue-200 text-sm font-semibold cursor-pointer hover:bg-blue-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span>Choose File</span>
                    <input type="file" @change="handleFileUpload" accept=".json" class="hidden"/>
                  </label>
                  <span v-if="!importData" class="text-sm text-gray-500">No file chosen</span>
                  <span v-else class="text-sm text-green-600 font-medium">✓ {{ importData.length }} items loaded</span>
                </div>
              </div>

              <!-- Step 2: Select Template -->
              <div v-if="importData" class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  <span class="inline-flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">2</span>
                    Select Template Type
                  </span>
                </label>
                <select v-model="selectedTemplate" @change="updateFieldMappingsForTemplate" class="w-full md:w-1/2 border-gray-300 rounded-md text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400">
                  <option value="detail">Hotel Detail</option>
                  <option value="blank">Blank (HTML)</option>
                  <option value="home">Home Page</option>
                  <option value="listing">Listing Page</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select which template to use for imported pages</p>
              </div>

              <!-- Step 3: Field Mapping -->
              <div v-if="importData && availableFields.length > 0" class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  <span class="inline-flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">3</span>
                    Map JSON Fields to Template Fields
                  </span>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div v-for="(mapping, key) in visibleFieldMappings" :key="key" class="flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700 w-32 shrink-0">{{ mapping.label }}:</label>
                    <select v-model="mapping.jsonField" class="flex-1 text-sm border-gray-300 rounded-md cursor-pointer focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                      <option :value="null">-- Skip --</option>
                      <option v-for="field in availableFields" :key="field" :value="field">{{ field }}</option>
                    </select>
                  </div>
                </div>

                <!-- Preview First Item -->
                <div v-if="previewItem" class="bg-gray-50 rounded-md p-4 mt-4">
                  <h3 class="text-sm font-semibold text-gray-700 mb-2">Preview (First Item):</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    <div v-for="(mapping, key) in visibleFieldMappings" :key="key" v-show="mapping.jsonField">
                      <span class="font-medium text-gray-600">{{ mapping.label }}:</span>
                      <span class="ml-2 text-gray-800">
                        {{ getPreviewValue(mapping.jsonField) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 4: Select Folders -->
              <div v-if="importData && folders.length > 0" class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <span class="inline-flex items-center gap-2">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">4</span>
                    Assign to Folders (optional)
                  </span>
                </label>
                <div class="border border-gray-300 rounded-md p-3 max-h-48 overflow-auto">
                  <label v-for="f in folders" :key="f.id" class="flex items-center gap-2 cursor-pointer mb-2">
                    <input type="checkbox" :value="f.id" v-model="selectedFolderIds" class="rounded cursor-pointer" />
                    <span class="text-sm">{{ f.name }}</span>
                  </label>
                </div>
              </div>

              <!-- Import Results -->
              <div v-if="importResult" class="p-4 rounded-md border-t pt-4" :class="importResult.stats.errors.length > 0 ? 'bg-yellow-50' : 'bg-green-50'">
                <p class="font-medium mb-2">Import Results:</p>
                <ul class="text-sm space-y-1">
                  <li>Total: {{ importResult.stats.total }}</li>
                  <li class="text-green-600">Created: {{ importResult.stats.created }}</li>
                  <li class="text-blue-600">Updated: {{ importResult.stats.updated }}</li>
                  <li class="text-gray-600">Skipped: {{ importResult.stats.skipped }}</li>
                </ul>
                <div v-if="importResult.stats.errors.length > 0" class="mt-2">
                  <p class="text-sm font-medium text-red-600">Errors:</p>
                  <ul class="text-xs text-red-600 mt-1 max-h-32 overflow-y-auto">
                    <li v-for="(err, idx) in importResult.stats.errors" :key="idx">
                      {{ err.title }}: {{ err.error }}
                    </li>
                  </ul>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex justify-end gap-3 pt-4 border-t">
                <button @click="closeImportModal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                  {{ importResult ? 'Close' : 'Cancel' }}
                </button>
                <button v-if="!importResult" @click="performImport" :disabled="!canImport || importing" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 flex items-center gap-2">
                  <Loader2 v-if="importing" class="size-4 animate-spin" />
                  <span>{{ importing ? 'Importing...' : 'Import' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { Pencil, Trash2, Loader2, ExternalLink } from 'lucide-vue-next'
import { toast } from 'sonner'

const route = useRoute()
const websiteId = route.params.websiteId
const website = ref(null)
const pages = ref([])
const allWebsites = ref([])
const selectedIds = ref([])
const loadingDeleteIds = ref([])
const deletingBulk = ref(false)
const query = ref('')
const showAddForm = ref(false)
const showImportModal = ref(false)
const importData = ref(null)
const importing = ref(false)
const importResult = ref(null)
const folders = ref([])
const selectedFolderIds = ref([])
const availableFields = ref([])
const selectedTemplate = ref('detail')

// Template-specific field mappings
const templateFieldConfigs = {
  detail: {
    name: { label: 'Title', jsonField: 'name' },
    address: { label: 'Địa điểm', jsonField: 'address' },
    about: { label: 'Giới thiệu', jsonField: 'about' },
    images: { label: 'Ảnh gallery', jsonField: 'images' },
    facilities: { label: 'Amenities', jsonField: 'facilities' },
    faqs: { label: 'FAQs', jsonField: 'faqs' },
    houseRules: { label: 'Useful Information', jsonField: 'houseRules' }
  },
  blank: {
    name: { label: 'Title', jsonField: 'name' },
    content: { label: 'Content', jsonField: 'content' }
  },
  home: {
    name: { label: 'Title', jsonField: 'name' },
    about: { label: 'About', jsonField: 'about' },
    services: { label: 'Services', jsonField: 'services' },
    testimonials: { label: 'Testimonials', jsonField: 'testimonials' }
  },
  listing: {
    name: { label: 'Title', jsonField: 'name' },
    items: { label: 'Items', jsonField: 'items' },
    filters: { label: 'Filters', jsonField: 'filters' }
  }
}

const fieldMappings = ref({
  name: { label: 'Title', jsonField: 'name' },
  address: { label: 'Địa điểm', jsonField: 'address' },
  about: { label: 'Giới thiệu', jsonField: 'about' },
  images: { label: 'Ảnh gallery', jsonField: 'images' },
  facilities: { label: 'Amenities', jsonField: 'facilities' },
  faqs: { label: 'FAQs', jsonField: 'faqs' },
  houseRules: { label: 'Useful Information', jsonField: 'houseRules' }
})

// Show all fields from selected template (no filtering needed)
// updateFieldMappingsForTemplate() already sets correct fields based on template
const visibleFieldMappings = computed(() => {
  return fieldMappings.value
})

const filteredPages = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return pages.value
  return pages.value.filter(p =>
    (p.title || '').toLowerCase().includes(q) || (p.path || '').toLowerCase().includes(q)
  )
})

const previewItem = computed(() => {
  return importData.value && importData.value.length > 0 ? importData.value[0] : null
})

const canImport = computed(() => {
  return importData.value && importData.value.length > 0 && fieldMappings.value.name.jsonField
})

const fetchWebsite = async () => {
  const resp = await axios.get(`/api/websites/${websiteId}`)
  website.value = resp.data
}

const fetchPages = async () => {
  const resp = await axios.get(`/api/websites/${websiteId}/pages`)
  pages.value = resp.data.map(p => ({ ...p, __site: website.value }))
}

const fetchAllWebsites = async () => {
  const resp = await axios.get('/api/websites')
  allWebsites.value = resp.data || []
}

const parentDomain = computed(() => {
  const d = (website.value?.domain || '').trim()
  return d.replace(/^[^.]+\./, '')
})

const parentWebsite = computed(() => {
  const pd = parentDomain.value
  if (!pd) return null
  return (allWebsites.value || []).find(w => String(w.domain || '').trim() === pd) || null
})

const backToRoute = computed(() => {
  const w = website.value
  if (!w) return '/websites'
  const parts = String(w.domain || '').trim().split('.')
  const isSub = parts.length > 2
  if (isSub && parentWebsite.value) {
    return `/websites/${parentWebsite.value.id}/subdomains`
  }
  return '/websites'
})

const fetchRelatedPages = async () => {
  if (!website.value) return
  await fetchAllWebsites()
  const pd = parentDomain.value
  const related = (allWebsites.value || []).filter(w => {
    const dom = String(w.domain || '').trim()
    return dom === pd || dom.endsWith('.' + pd)
  })
  const ids = related.map(w => w.id)
  const reqs = ids.map(async (id) => {
    try {
      const r = await axios.get(`/api/websites/${id}/pages`)
      const site = related.find(x => x.id === id)
      return (r.data || []).map(p => ({ ...p, __site: site }))
    } catch {
      return []
    }
  })
  const groups = await Promise.all(reqs)
  const merged = ([]).concat(...groups)
  pages.value = merged
}

const deletePage = async (page) => {
  if (!confirm(`Delete page ${page.path}?`)) return
  try {
    loadingDeleteIds.value = [...loadingDeleteIds.value, page.id]
    await axios.delete(`/api/pages/${page.id}`)
    toast.success('Page deleted')
    selectedIds.value = selectedIds.value.filter(id => id !== page.id)
    await fetchPages()
  } catch (e) {
    toast.error('Failed to delete page')
  } finally {
    loadingDeleteIds.value = loadingDeleteIds.value.filter(id => id !== page.id)
  }
}

// Page creation moved to dedicated route and view

const pageUrl = (page) => {
  const site = page.__site || website.value
  if (!site) return '#'
  const base = (site.ssl_enabled ? 'https://' : 'http://') + site.domain
  const path = page.path || '/'
  const fn = page.filename || ''
  if (fn.toLowerCase() === 'index.html') return base + path
  const sep = path.endsWith('/') ? '' : '/'
  return base + path + sep + fn
}

const previewRoute = (page) => {
  const site = page.__site || website.value
  if (!site) return '#'
  const id = site.id
  const p = (page?.path || '/').trim()
  const base = p === '/' ? `/preview/${id}` : `/preview/${id}${p.startsWith('/') ? '' : '/'}${p}`
  const token = localStorage.getItem('adminToken') || ''
  if (!token) return base
  const sep = base.includes('?') ? '&' : '?'
  return `${base}${sep}token=${encodeURIComponent(token)}`
}

const toggleSelect = (page) => {
  const id = page.id
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(x => x !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

const deleteSelected = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Delete ${selectedIds.value.length} selected pages?`)) return
  deletingBulk.value = true
  try {
    const ids = [...selectedIds.value]
    await Promise.all(ids.map(async (id) => {
      loadingDeleteIds.value = [...loadingDeleteIds.value, id]
      try {
        await axios.delete(`/api/pages/${id}`)
      } finally {
        loadingDeleteIds.value = loadingDeleteIds.value.filter(x => x !== id)
      }
    }))
    toast.success('Selected pages deleted')
    selectedIds.value = []
    await fetchPages()
  } catch (e) {
    toast.error('Failed to delete selected pages')
  } finally {
    deletingBulk.value = false
  }
}

const fetchFolders = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/folders`)
    folders.value = resp.data || []
  } catch (e) {
    console.error('Failed to load folders:', e)
  }
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const json = JSON.parse(e.target.result)

      // Extract the result array from the JSON structure
      let data = json
      if (Array.isArray(json) && json.length > 0 && json[0].result) {
        // If it's an array of objects with 'result' property
        data = json.map(item => item.result)
      }

      importData.value = data
      importResult.value = null

      // Extract available fields from the first item
      if (data.length > 0) {
        const firstItem = data[0]
        const fields = Object.keys(firstItem)
        availableFields.value = fields

        // Auto-map fields based on selected template
        updateFieldMappingsForTemplate()
      }

      toast.success(`Loaded ${data.length} items from file`)
    } catch (error) {
      toast.error('Invalid JSON file')
      console.error(error)
    }
  }
  reader.readAsText(file)
}

const updateFieldMappingsForTemplate = () => {
  // Get template-specific field config
  const templateConfig = templateFieldConfigs[selectedTemplate.value] || templateFieldConfigs.detail

  // Reset field mappings based on template
  fieldMappings.value = JSON.parse(JSON.stringify(templateConfig))

  // Auto-detect and map fields based on template type
  const fields = availableFields.value

  if (selectedTemplate.value === 'detail') {
    const autoMap = {
      name: fields.find(f => f.toLowerCase().includes('name') || f.toLowerCase().includes('title')),
      address: fields.find(f => f.toLowerCase().includes('address') || f.toLowerCase().includes('location')),
      about: fields.find(f => f.toLowerCase().includes('about') || f.toLowerCase().includes('description')),
      images: fields.find(f => f.toLowerCase().includes('image') || f.toLowerCase().includes('photo')),
      facilities: fields.find(f => f.toLowerCase().includes('facilit') || f.toLowerCase().includes('amenity')),
      faqs: fields.find(f => f.toLowerCase().includes('faq') || f.toLowerCase().includes('question')),
      houseRules: fields.find(f => f.toLowerCase().includes('rule') || f.toLowerCase().includes('policy') || f.toLowerCase().includes('info'))
    }
    Object.keys(autoMap).forEach(key => {
      if (autoMap[key] && fieldMappings.value[key]) {
        fieldMappings.value[key].jsonField = autoMap[key]
      }
    })
  } else if (selectedTemplate.value === 'blank') {
    const autoMap = {
      name: fields.find(f => f.toLowerCase().includes('name') || f.toLowerCase().includes('title')),
      content: fields.find(f => f.toLowerCase().includes('content') || f.toLowerCase().includes('description') || f.toLowerCase().includes('body'))
    }
    Object.keys(autoMap).forEach(key => {
      if (autoMap[key] && fieldMappings.value[key]) {
        fieldMappings.value[key].jsonField = autoMap[key]
      }
    })
  } else if (selectedTemplate.value === 'home') {
    const autoMap = {
      name: fields.find(f => f.toLowerCase().includes('name') || f.toLowerCase().includes('title')),
      about: fields.find(f => f.toLowerCase().includes('about') || f.toLowerCase().includes('description')),
      services: fields.find(f => f.toLowerCase().includes('service')),
      testimonials: fields.find(f => f.toLowerCase().includes('testimonial') || f.toLowerCase().includes('review'))
    }
    Object.keys(autoMap).forEach(key => {
      if (autoMap[key] && fieldMappings.value[key]) {
        fieldMappings.value[key].jsonField = autoMap[key]
      }
    })
  } else if (selectedTemplate.value === 'listing') {
    const autoMap = {
      name: fields.find(f => f.toLowerCase().includes('name') || f.toLowerCase().includes('title')),
      items: fields.find(f => f.toLowerCase().includes('item') || f.toLowerCase().includes('list')),
      filters: fields.find(f => f.toLowerCase().includes('filter') || f.toLowerCase().includes('category'))
    }
    Object.keys(autoMap).forEach(key => {
      if (autoMap[key] && fieldMappings.value[key]) {
        fieldMappings.value[key].jsonField = autoMap[key]
      }
    })
  }
}

const getPreviewValue = (jsonField) => {
  if (!previewItem.value || !jsonField) return '-'
  const value = previewItem.value[jsonField]
  if (Array.isArray(value)) {
    return `${value.length} items`
  }
  if (typeof value === 'object' && value !== null) {
    return JSON.stringify(value).substring(0, 50) + '...'
  }
  return String(value || '-').substring(0, 100)
}

const closeImportModal = () => {
  showImportModal.value = false
  // Reset state
  setTimeout(() => {
    if (!showImportModal.value) {
      importData.value = null
      importResult.value = null
      availableFields.value = []
      selectedFolderIds.value = []
      selectedTemplate.value = 'detail'
    }
  }, 300)
}

const performImport = async () => {
  if (!importData.value) return

  importing.value = true
  importResult.value = null

  try {
    // Transform data based on field mappings
    const mappedData = importData.value.map(item => {
      const mapped = {}

      // Map each field based on user's selection
      Object.keys(fieldMappings.value).forEach(key => {
        const jsonField = fieldMappings.value[key].jsonField
        if (jsonField && item[jsonField] !== undefined) {
          mapped[key] = item[jsonField]
        }
      })

      return mapped
    })

    const resp = await axios.post(`/api/websites/${websiteId}/pages/import`, {
      data: mappedData,
      folder_ids: selectedFolderIds.value,
      template_type: selectedTemplate.value
    })

    importResult.value = resp.data
    toast.success(`Import completed: ${resp.data.stats.created} created, ${resp.data.stats.updated} updated`)

    // Refresh pages list
    await fetchPages()
  } catch (error) {
    toast.error('Import failed: ' + (error.response?.data?.message || error.message))
    console.error(error)
  } finally {
    importing.value = false
  }
}

onMounted(async () => {
  await fetchWebsite()
  await fetchPages()
  await fetchAllWebsites()
  await fetchFolders()
})
</script>
