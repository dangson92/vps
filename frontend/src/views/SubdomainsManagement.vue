<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">Subdomains of {{ parentDomain }}</h1>
          <div class="flex items-center gap-2">
            <button @click="showImportModal = true" class="px-4 py-2 border border-gray-300 rounded-md text-green-600 hover:bg-gray-50 flex items-center gap-2">
              <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Import</span>
            </button>
            <router-link :to="`/websites/${websiteId}/subdomains/new`" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Subdomain</router-link>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <!-- Select All & Bulk Actions Bar -->
          <div v-if="subdomains.length > 0" class="mb-3 flex items-center justify-between pb-3 border-b">
            <div class="flex items-center gap-3">
              <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
              <label class="text-sm text-gray-700 font-medium cursor-pointer" @click="toggleSelectAll">Select All</label>
              <div v-if="selectedIds.length > 0" class="flex items-center gap-2 ml-2 pl-2 border-l border-gray-300">
                <span class="text-sm font-medium text-blue-900">{{ selectedIds.length }} selected</span>
                <button @click="selectedIds = []" class="text-sm text-blue-600 hover:text-blue-800">Clear</button>
              </div>
            </div>
            <div v-if="selectedIds.length > 0" class="flex gap-2">
              <button @click="bulkDeploy" :disabled="bulkActionInProgress" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400 flex items-center gap-1">
                <Loader2 v-if="bulkActionInProgress" class="size-4 animate-spin" />
                <Play v-else class="size-4" />
                Deploy
              </button>
              <button @click="bulkInstallSsl" :disabled="bulkActionInProgress" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:bg-gray-400 flex items-center gap-1">
                <Loader2 v-if="bulkActionInProgress" class="size-4 animate-spin" />
                <ShieldCheck v-else class="size-4" />
                SSL
              </button>
              <button @click="bulkDeactivate" :disabled="bulkActionInProgress" class="px-3 py-1.5 text-sm bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:bg-gray-400 flex items-center gap-1">
                <Loader2 v-if="bulkActionInProgress" class="size-4 animate-spin" />
                <Power v-else class="size-4" />
                Deactivate
              </button>
              <button @click="bulkDelete" :disabled="bulkActionInProgress" class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 disabled:bg-gray-400 flex items-center gap-1">
                <Loader2 v-if="bulkActionInProgress" class="size-4 animate-spin" />
                <Trash2 v-else class="size-4" />
                Delete
              </button>
            </div>
          </div>

          <div class="space-y-4">
            <div v-for="site in subdomains" :key="site.id" class="border rounded-lg p-4">
              <div class="flex items-start gap-3">
                <!-- Checkbox -->
                <input type="checkbox" :value="site.id" v-model="selectedIds" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">

                <div class="flex-1 flex justify-between items-start">
                  <div>
                    <h4 class="font-medium">{{ site.domain }}</h4>
                    <p class="text-sm text-gray-500">Server: {{ site.vps_server?.name || 'N/A' }}</p>
                    <div class="mt-2 flex items-center space-x-4 text-sm">
                      <span class="inline-flex items-center"
                        :class="{
                          'text-blue-600': site.status === 'deploying',
                          'text-green-600': site.status === 'deployed',
                          'text-gray-600': site.status === 'pending' || site.status === 'draft',
                          'text-orange-600': site.status === 'suspended',
                          'text-red-600': site.status === 'error',
                        }" :title="site.status">
                        <Loader2 v-if="site.status === 'deploying'" class="size-4 animate-spin" />
                        <CheckCircle v-else-if="site.status === 'deployed'" class="size-4" />
                        <Clock v-else-if="site.status === 'pending' || site.status === 'draft'" class="size-4" />
                        <Power v-else-if="site.status === 'suspended'" class="size-4" />
                        <AlertTriangle v-else class="size-4" />
                      </span>
                      <span class="inline-flex items-center gap-1"
                        :class="site.ssl_enabled ? 'text-green-600' : 'text-gray-400'">
                        <ShieldCheck v-if="site.ssl_enabled" class="size-4" />
                        <ShieldOff v-else class="size-4" />
                      </span>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <router-link :to="`/websites/${site.id}/pages`" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-blue-600 hover:bg-gray-50" title="Manage Pages">
                      <FileText class="size-4" />
                    </router-link>
                    <button @click="deploy(site)" :disabled="deployingIds.includes(site.id) || site.status === 'deployed'" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-green-600 hover:bg-gray-50 disabled:text-gray-400" title="Deploy">
                      <Loader2 v-if="deployingIds.includes(site.id)" class="size-4 animate-spin" />
                      <Play v-else class="size-4" />
                    </button>
                    <button @click="enableSsl(site)" v-if="site.status === 'deployed'" :disabled="sslIds.includes(site.id)" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-purple-600 hover:bg-gray-50 disabled:text-gray-400" title="Enable SSL">
                      <Loader2 v-if="sslIds.includes(site.id)" class="size-4 animate-spin" />
                      <ShieldCheck v-else class="size-4" />
                    </button>
                    <button @click="deactivate(site)" v-if="site.status === 'deployed' || site.status === 'error'" :disabled="deactivatingIds.includes(site.id)" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-orange-600 hover:bg-gray-50 disabled:text-gray-400" title="Deactivate">
                      <Loader2 v-if="deactivatingIds.includes(site.id)" class="size-4 animate-spin" />
                      <Power v-else class="size-4" />
                    </button>
                    <a :href="(site.ssl_enabled ? 'https://' : 'http://') + site.domain" target="_blank" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-indigo-600 hover:bg-gray-50" title="View">
                      <Globe class="size-4" />
                    </a>
                    <button @click="remove(site)" :disabled="deletingIds.includes(site.id)" class="h-8 w-8 flex items-center justify-center rounded-md border border-gray-300 bg-white text-red-600 hover:bg-gray-50 disabled:text-gray-400" title="Delete">
                      <Loader2 v-if="deletingIds.includes(site.id)" class="size-4 animate-spin" />
                      <Trash2 v-else class="size-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="subdomains.length === 0" class="text-center text-gray-500">No subdomains found</div>
          </div>
        </div>

        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="closeImportModal">
          <div class="bg-white rounded-lg shadow-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-bold">Import Subdomains from JSON</h2>
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
                <p class="text-xs text-gray-500 mt-2">Each item will create a new subdomain with initial page</p>
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
                    Map JSON Fields to Page Template Fields
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
                  <label v-for="f in folders" :key="f.id" class="flex items-center gap-2 cursor-pointer mb-2 hover:bg-gray-50 p-1 rounded">
                    <input type="checkbox" :value="f.id" v-model="selectedFolderIds" class="rounded cursor-pointer border-gray-300" />
                    <span class="text-sm">{{ f.name }}</span>
                  </label>
                  <p v-if="folders.length === 0" class="text-sm text-gray-500">No folders available</p>
                </div>
                <p class="text-xs text-gray-500 mt-1">Select folders to assign imported pages to</p>
              </div>

              <!-- Import Results -->
              <div v-if="importResult" class="p-4 rounded-md border-t pt-4" :class="importResult.errors.length > 0 ? 'bg-yellow-50' : 'bg-green-50'">
                <p class="font-medium mb-2">Import Results:</p>
                <ul class="text-sm space-y-1">
                  <li>Total: {{ importResult.total }}</li>
                  <li class="text-green-600">Created: {{ importResult.created }}</li>
                  <li class="text-gray-600">Skipped: {{ importResult.skipped }}</li>
                </ul>
                <div v-if="importResult.errors.length > 0" class="mt-2">
                  <p class="text-sm font-medium text-red-600">Errors:</p>
                  <ul class="text-xs text-red-600 mt-1 max-h-32 overflow-y-auto">
                    <li v-for="(err, idx) in importResult.errors" :key="idx">
                      {{ err.domain }}: {{ err.error }}
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
                  <span>{{ importing ? 'Importing...' : 'Import Subdomains' }}</span>
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
import { Globe, FileText, Play, Loader2, Trash2, ShieldCheck, ShieldOff, Power, CheckCircle, AlertTriangle, Clock } from 'lucide-vue-next'
import { toast } from 'sonner'

const route = useRoute()
const websiteId = route.params.websiteId
const parentDomain = ref('')
const subdomains = ref([])
const deployingIds = ref([])
const deletingIds = ref([])
const sslIds = ref([])
const deactivatingIds = ref([])
const selectedIds = ref([])
const bulkActionInProgress = ref(false)

// Import modal state
const showImportModal = ref(false)
const importData = ref(null)
const importing = ref(false)
const importResult = ref(null)
const availableFields = ref([])
const selectedTemplate = ref('detail')
const folders = ref([])
const selectedFolderIds = ref([])

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

const allSelected = computed(() => {
  return subdomains.value.length > 0 && selectedIds.value.length === subdomains.value.length
})

const previewItem = computed(() => {
  return importData.value && importData.value.length > 0 ? importData.value[0] : null
})

const canImport = computed(() => {
  return importData.value && importData.value.length > 0 && fieldMappings.value.name.jsonField
})

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = subdomains.value.map(s => s.id)
  }
}

const fetchAll = async () => {
  const parentResp = await axios.get(`/api/websites/${websiteId}`)
  parentDomain.value = parentResp.data.domain
  const resp = await axios.get('/api/websites')
  const list = resp.data || []
  subdomains.value = list.filter(w => w.domain.endsWith('.' + parentDomain.value))
}

const fetchFolders = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/folders`)
    folders.value = resp.data || []
  } catch (error) {
    console.error('Failed to fetch folders:', error)
    folders.value = []
  }
}

onMounted(() => {
  fetchAll()
  fetchFolders()
})

const deploy = async (site) => {
  try {
    deployingIds.value = [...deployingIds.value, site.id]
    await axios.post(`/api/websites/${site.id}/deploy`)
    // Poll until deployment completes
    let attempts = 0
    while (attempts < 60) {
      await new Promise(r => setTimeout(r, 2000))
      const resp = await axios.get(`/api/websites/${site.id}`)
      if (resp.data.status !== 'deploying') break
      attempts++
    }
    await fetchAll()
    alert(`✓ Deploy thành công cho ${site.domain}`)
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Deploy thất bại'
    alert(`✗ ${msg}`)
  } finally {
    deployingIds.value = deployingIds.value.filter(id => id !== site.id)
  }
}

const remove = async (site) => {
  if (!confirm(`Delete subdomain ${site.domain}?`)) return
  try {
    deletingIds.value = [...deletingIds.value, site.id]
    await axios.delete(`/api/websites/${site.id}`)
    await fetchAll()
  } finally {
    deletingIds.value = deletingIds.value.filter(id => id !== site.id)
  }
}

const enableSsl = async (site) => {
  try {
    sslIds.value = [...sslIds.value, site.id]
    await axios.post(`/api/websites/${site.id}/ssl`)
    await fetchAll()
    alert(`✓ SSL đã được cài đặt thành công cho ${site.domain}`)
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Cài đặt SSL thất bại'
    alert(`✗ ${msg}`)
  } finally {
    sslIds.value = sslIds.value.filter(id => id !== site.id)
  }
}

const deactivate = async (site) => {
  if (!confirm(`Deactivate subdomain ${site.domain}?`)) return
  try {
    deactivatingIds.value = [...deactivatingIds.value, site.id]
    await axios.post(`/api/websites/${site.id}/deactivate`)
    await fetchAll()
  } finally {
    deactivatingIds.value = deactivatingIds.value.filter(id => id !== site.id)
  }
}

// Bulk Actions
const bulkDeploy = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Deploy ${selectedIds.value.length} subdomains?`)) return

  try {
    bulkActionInProgress.value = true
    const response = await axios.post('/api/websites/bulk/deploy', {
      website_ids: selectedIds.value
    })

    selectedIds.value = []
    const message = response.data.message || 'Operations queued successfully'
    alert(`✓ ${message}\n\nWatch the status indicators for each item.`)

    // Start polling to show real-time progress
    let attempts = 0
    while (attempts < 120) { // Poll for up to 4 minutes
      await new Promise(r => setTimeout(r, 2000)) // Wait 2 seconds
      await fetchAll()

      // Check if any subdomain is still deploying
      const stillDeploying = subdomains.value.some(s => s.status === 'deploying')
      if (!stillDeploying) break

      attempts++
    }

    await fetchAll() // Final refresh
  } catch (error) {
    const msg = error?.response?.data?.message || 'Bulk deploy failed'
    alert(`✗ ${msg}`)
  } finally {
    bulkActionInProgress.value = false
  }
}

const bulkInstallSsl = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Install SSL for ${selectedIds.value.length} subdomains?`)) return

  try {
    bulkActionInProgress.value = true
    const response = await axios.post('/api/websites/bulk/ssl', {
      website_ids: selectedIds.value
    })

    selectedIds.value = []
    const message = response.data.message || 'Operations queued successfully'
    alert(`✓ ${message}\n\nWatch the status indicators for each item.`)

    // Poll a few times to show progress
    for (let i = 0; i < 10; i++) {
      await new Promise(r => setTimeout(r, 2000)) // Wait 2 seconds
      await fetchAll()
    }
  } catch (error) {
    const msg = error?.response?.data?.message || 'Bulk SSL installation failed'
    alert(`✗ ${msg}`)
  } finally {
    bulkActionInProgress.value = false
  }
}

const bulkDeactivate = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`Deactivate ${selectedIds.value.length} subdomains?`)) return

  try {
    bulkActionInProgress.value = true
    const response = await axios.post('/api/websites/bulk/deactivate', {
      website_ids: selectedIds.value
    })

    selectedIds.value = []
    const message = response.data.message || 'Operations queued successfully'
    alert(`✓ ${message}\n\nWatch the status indicators for each item.`)

    // Poll a few times to show progress
    for (let i = 0; i < 5; i++) {
      await new Promise(r => setTimeout(r, 2000)) // Wait 2 seconds
      await fetchAll()
    }
  } catch (error) {
    const msg = error?.response?.data?.message || 'Bulk deactivate failed'
    alert(`✗ ${msg}`)
  } finally {
    bulkActionInProgress.value = false
  }
}

const bulkDelete = async () => {
  if (selectedIds.value.length === 0) return
  if (!confirm(`DELETE ${selectedIds.value.length} subdomains? This action cannot be undone!`)) return

  try {
    bulkActionInProgress.value = true
    const response = await axios.post('/api/websites/bulk/delete', {
      website_ids: selectedIds.value
    })

    selectedIds.value = []
    const message = response.data.message || 'Operations queued successfully'
    alert(`✓ ${message}\n\nWatch the list update as items are deleted.`)

    // Poll a few times to show progress
    for (let i = 0; i < 5; i++) {
      await new Promise(r => setTimeout(r, 2000)) // Wait 2 seconds
      await fetchAll()
    }
  } catch (error) {
    const msg = error?.response?.data?.message || 'Bulk delete failed'
    alert(`✗ ${msg}`)
  } finally {
    bulkActionInProgress.value = false
  }
}

// Import functions
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
  setTimeout(() => {
    if (!showImportModal.value) {
      importData.value = null
      importResult.value = null
      availableFields.value = []
      selectedTemplate.value = 'detail'
    }
  }, 300)
}

const performImport = async () => {
  if (!importData.value || !parentDomain.value) return

  importing.value = true
  importResult.value = null

  const result = {
    total: importData.value.length,
    created: 0,
    skipped: 0,
    errors: []
  }

  try {
    // Get VPS server ID from parent website
    const parentResp = await axios.get(`/api/websites/${websiteId}`)
    const vpsServerId = parentResp.data.vps_server_id

    for (const item of importData.value) {
      try {
        // Map fields
        const mapped = {}
        Object.keys(fieldMappings.value).forEach(key => {
          const jsonField = fieldMappings.value[key].jsonField
          if (jsonField && item[jsonField] !== undefined) {
            mapped[key] = item[jsonField]
          }
        })

        // Set path to '/' for subdomain homepage
        mapped.path = '/'

        const title = mapped.name
        if (!title) {
          result.skipped++
          result.errors.push({ domain: 'Unknown', error: 'Missing title' })
          continue
        }

        // Generate subdomain name from title
        const slug = title.toLowerCase()
          .replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g, 'a')
          .replace(/[èéẹẻẽêềếệểễ]/g, 'e')
          .replace(/[ìíịỉĩ]/g, 'i')
          .replace(/[òóọỏõôồốộổỗơờớợởỡ]/g, 'o')
          .replace(/[ùúụủũưừứựửữ]/g, 'u')
          .replace(/[ỳýỵỷỹ]/g, 'y')
          .replace(/đ/g, 'd')
          .replace(/[^a-z0-9\s-]/g, '')
          .trim().replace(/\s+/g, '-')
          .replace(/-+/g, '-')

        const subdomainName = `${slug}.${parentDomain.value}`

        // Check if subdomain already exists
        const exists = subdomains.value.some(s => s.domain === subdomainName)
        if (exists) {
          result.skipped++
          result.errors.push({ domain: subdomainName, error: 'Subdomain already exists' })
          continue
        }

        // Create subdomain website
        const websiteResp = await axios.post('/api/websites', {
          domain: subdomainName,
          type: 'laravel1',
          template_package: 'laravel-hotel-1',
          vps_server_id: vpsServerId
        })

        const newWebsiteId = websiteResp.data.id

        // Import page data for the subdomain
        await axios.post(`/api/websites/${newWebsiteId}/pages/import`, {
          data: [mapped],
          folder_ids: selectedFolderIds.value,
          template_type: selectedTemplate.value
        })

        result.created++
      } catch (error) {
        result.skipped++
        result.errors.push({
          domain: mapped.name || 'Unknown',
          error: error.response?.data?.message || error.message
        })
      }
    }

    importResult.value = result
    toast.success(`Import completed: ${result.created} subdomains created`)

    // Refresh subdomains list
    await fetchAll()
  } catch (error) {
    toast.error('Import failed: ' + (error.response?.data?.message || error.message))
    console.error(error)
  } finally {
    importing.value = false
  }
}
</script>
