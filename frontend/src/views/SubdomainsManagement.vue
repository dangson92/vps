<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">Subdomains of {{ parentDomain }}</h1>
          <router-link :to="`/websites/${websiteId}/subdomains/new`" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Subdomain</router-link>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <div class="space-y-4">
            <div v-for="site in subdomains" :key="site.id" class="border rounded-lg p-4">
              <div class="flex justify-between items-start">
                <div>
                  <h4 class="font-medium">{{ site.domain }}</h4>
                  <p class="text-sm text-gray-500">Server: {{ site.vps_server?.name || 'N/A' }}</p>
                  <p class="text-sm text-gray-500">Status: {{ site.status }}</p>
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
            <div v-if="subdomains.length === 0" class="text-center text-gray-500">No subdomains found</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { Globe, FileText, Play, Loader2, Trash2, ShieldCheck, Power } from 'lucide-vue-next'

const route = useRoute()
const websiteId = route.params.websiteId
const parentDomain = ref('')
const subdomains = ref([])
const deployingIds = ref([])
const deletingIds = ref([])
const sslIds = ref([])
const deactivatingIds = ref([])

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
</script>