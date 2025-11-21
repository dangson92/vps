<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">{{ website?.domain || '...' }}</h1>
          <div class="flex items-center gap-2">
            <router-link to="/websites" class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Back">
              <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            </router-link>
            <input v-model="query" placeholder="Search" class="h-9 w-56 rounded-md border border-gray-300 px-3" />
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

const filteredPages = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return pages.value
  return pages.value.filter(p =>
    (p.title || '').toLowerCase().includes(q) || (p.path || '').toLowerCase().includes(q)
  )
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

onMounted(async () => {
  await fetchWebsite()
  await fetchPages()
})
</script>