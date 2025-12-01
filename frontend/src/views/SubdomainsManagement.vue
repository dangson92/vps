<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">Subdomains of {{ parentDomain }}</h1>
          <router-link :to="`/websites/${websiteId}/subdomains/new`" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Subdomain</router-link>
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
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { Globe, FileText, Play, Loader2, Trash2, ShieldCheck, ShieldOff, Power, CheckCircle, AlertTriangle, Clock } from 'lucide-vue-next'

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

const allSelected = computed(() => {
  return subdomains.value.length > 0 && selectedIds.value.length === subdomains.value.length
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

onMounted(fetchAll)

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
    alert(`✓ ${message}\n\nThe page will refresh automatically to show progress.`)

    // Refresh after 3 seconds to show updated status
    setTimeout(() => {
      fetchAll()
    }, 3000)
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
    alert(`✓ ${message}\n\nThe page will refresh automatically to show progress.`)

    // Refresh after 3 seconds to show updated status
    setTimeout(() => {
      fetchAll()
    }, 3000)
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
    alert(`✓ ${message}\n\nThe page will refresh automatically to show progress.`)

    // Refresh after 3 seconds to show updated status
    setTimeout(() => {
      fetchAll()
    }, 3000)
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
    alert(`✓ ${message}\n\nThe page will refresh automatically to show progress.`)

    // Refresh after 3 seconds to show updated status
    setTimeout(() => {
      fetchAll()
    }, 3000)
  } catch (error) {
    const msg = error?.response?.data?.message || 'Bulk delete failed'
    alert(`✗ ${msg}`)
  } finally {
    bulkActionInProgress.value = false
  }
}
</script>
