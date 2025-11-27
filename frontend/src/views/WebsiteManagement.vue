<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div v-if="uiMsg" class="mb-4">
          <div :class="uiMsgType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'" class="px-4 py-3 rounded-md">
            {{ uiMsg }}
          </div>
        </div>
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">Websites</h1>
          <button
            @click="showAddModal = true"
            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Create Website
          </button>
        </div>

        <div class="bg-white shadow sm:rounded-md">
          <ul class="divide-y divide-gray-200">
            <li v-for="website in websites" :key="website.id" class="px-6 py-4 relative overflow-visible">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center">
                    <h3 class="text-lg font-medium text-gray-900">{{ website.domain }}</h3>
                    <span
                      :class="{
                        'bg-blue-100 text-blue-800': website.type === 'html',
                        'bg-green-100 text-green-800': website.type === 'wordpress',
                        'bg-purple-100 text-purple-800': website.type === 'laravel1'
                      }"
                      class="ml-2 px-2 py-1 text-xs font-medium rounded-full"
                    >
                      {{ website.type }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-500 mt-1">
                    VPS: {{ website.vps_server?.name || 'N/A' }}
                  </p>
                  <div class="mt-2 flex items-center space-x-4 text-sm">
                    <span class="inline-flex items-center"
                      :class="{
                        'text-blue-600': website.status === 'deploying',
                        'text-green-600': website.status === 'deployed',
                        'text-gray-600': website.status === 'pending' || website.status === 'draft',
                        'text-orange-600': website.status === 'suspended',
                        'text-red-600': website.status === 'error',
                      }" :title="website.status">
                      <Loader2 v-if="website.status === 'deploying'" class="size-4 animate-spin" />
                      <CheckCircle v-else-if="website.status === 'deployed'" class="size-4" />
                      <Clock v-else-if="website.status === 'pending' || website.status === 'draft'" class="size-4" />
                      <Power v-else-if="website.status === 'suspended'" class="size-4" />
                      <AlertTriangle v-else class="size-4" />
                    </span>
                    <span class="inline-flex items-center gap-1"
                      :class="website.ssl_enabled ? 'text-green-600' : 'text-gray-400'">
                      <ShieldCheck v-if="website.ssl_enabled" class="size-4" />
                      <ShieldOff v-else class="size-4" />
                      
                    </span>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <a
                    :href="(website.ssl_enabled ? 'https://' : 'http://') + website.domain"
                    target="_blank"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-indigo-600 hover:bg-gray-50"
                    title="View Website"
                  >
                    <Globe class="size-4" />
                  </a>
                  <router-link
                    :to="`/websites/${website.id}/pages`"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-blue-600 hover:bg-gray-50"
                    title="Manage Pages"
                  >
                    <FileText class="size-4" />
                  </router-link>
                  <router-link
                    :to="`/websites/${website.id}/folders`"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-amber-600 hover:bg-gray-50"
                    title="Manage Categories"
                  >
                    <Folder class="size-4" />
                  </router-link>
                  <router-link
                    :to="`/websites/${website.id}/subdomains`"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-indigo-600 hover:bg-gray-50"
                    title="Manage Subdomains"
                  >
                    <LayoutGrid class="size-4" />
                  </router-link>
                  <a
                    :href="previewHomeUrl(website)"
                    target="_blank"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-orange-600 hover:bg-gray-50"
                    title="Preview Draft"
                  >
                    <Clock class="size-4" />
                  </a>
                  <button
                    @click="deployWebsite(website)"
                    :disabled="website.status === 'deploying' || website.status === 'deployed' || loadingDeployIds.includes(website.id)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-green-600 hover:bg-gray-50 disabled:text-gray-400"
                    title="Deploy"
                  >
                    <Loader2 v-if="loadingDeployIds.includes(website.id)" class="size-4 animate-spin" />
                    <Play v-else class="size-4" />
                  </button>
                  <button
                    @click="enableSSL(website)"
                    v-if="website.status === 'deployed'"
                    :disabled="loadingSslIds.includes(website.id)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-purple-600 hover:bg-gray-50 disabled:text-gray-400"
                    title="Enable SSL"
                  >
                    <Loader2 v-if="loadingSslIds.includes(website.id)" class="size-4 animate-spin" />
                    <ShieldCheck v-else class="size-4" />
                  </button>
                  <button
                    @click="deactivateWebsite(website)"
                    v-if="website.status === 'deployed' || website.status === 'error'"
                    :disabled="loadingDeactivateIds.includes(website.id)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-orange-600 hover:bg-gray-50 disabled:text-gray-400"
                    title="Deactivate"
                  >
                    <Loader2 v-if="loadingDeactivateIds.includes(website.id)" class="size-4 animate-spin" />
                    <Power v-else class="size-4" />
                  </button>
                  <div v-if="website.status === 'deployed' && website.type === 'laravel1'" class="relative redeploy-dropdown-container">
                    <button
                      @click="toggleRedeployDropdown(website.id)"
                      :disabled="loadingRedeployIds.includes(website.id)"
                      class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-teal-600 hover:bg-gray-50 disabled:text-gray-400"
                      title="Redeploy Options"
                    >
                      <Loader2 v-if="loadingRedeployIds.includes(website.id)" class="size-4 animate-spin" />
                      <RefreshCw v-else class="size-4" />
                    </button>
                    <div v-if="showRedeployDropdown[website.id]" class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                      <div class="py-1">
                        <button
                          @click="redeployPages(website)"
                          class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                          Redeploy All Pages
                        </button>
                        <button
                          @click="openRedeployAssetsModal(website)"
                          class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                          Redeploy Template Assets
                        </button>
                        <button
                          @click="openUpdateTemplateModal(website)"
                          class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                          Update Pages Template
                        </button>
                      </div>
                    </div>
                  </div>
                  <button
                    @click="deleteWebsite(website)"
                    :disabled="loadingDeleteIds.includes(website.id)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-red-600 hover:bg-gray-50 disabled:text-gray-400"
                    title="Delete"
                  >
                    <Loader2 v-if="loadingDeleteIds.includes(website.id)" class="size-4 animate-spin" />
                    <Trash2 v-else class="size-4" />
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Add Website Modal -->
    <div v-if="showAddModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Create Website</h2>
        <div v-if="availableServers.length === 0" class="mb-4 text-sm text-red-600">Chưa có VPS ở trạng thái active. Vào mục Servers để đặt trạng thái active cho VPS hiện tại.</div>
        
        <form @submit.prevent="createWebsite">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Domain</label>
            <input
              v-model="form.domain"
              type="text"
              required
              placeholder="example.com"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Type</label>
            <select
              v-model="form.type"
              required
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            >
              <option value="html">HTML Website</option>
              <option value="wordpress">WordPress</option>
              <option value="laravel1">Laravel 1</option>
            </select>
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">VPS Server</label>
            <select
              v-model="form.vps_server_id"
              required
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            >
              <option value="">Select VPS Server</option>
              <option v-for="server in availableServers" :key="server.id" :value="server.id">
                {{ server.name }} ({{ server.ip_address }})
              </option>
            </select>
          </div>
          
          <div v-if="form.type === 'wordpress'" class="mb-4">
            <label class="block text-sm font-medium text-gray-700">WordPress Template</label>
            <select
              v-model="form.wordpress_template"
              required
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            >
              <option value="basic">Basic WordPress</option>
              <option value="business">Business Theme</option>
              <option value="ecommerce">E-commerce Theme</option>
            </select>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Create
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Redeploy Template Assets Modal -->
    <div v-if="showRedeployAssetsModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Redeploy Template Assets</h2>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Select Template</label>
          <select
            v-model="redeployAssetsForm.template_name"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
          >
            <option value="home-1">Home 1</option>
            <option value="listing-1">Listing 1</option>
            <option value="hotel-detail-1">Hotel Detail 1</option>
          </select>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4">
          <p class="text-sm text-blue-800">
            Deploys CSS/JS files from the template to all pages. Use this when you update template scripts or styles.
          </p>
        </div>

        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="closeRedeployAssetsModal"
            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="redeployTemplateAssets"
            :disabled="loadingRedeployIds.includes(currentRedeployWebsite?.id)"
            class="px-3 py-2 bg-teal-600 text-white text-sm rounded-md hover:bg-teal-700 disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="loadingRedeployIds.includes(currentRedeployWebsite?.id)" class="size-4 animate-spin" />
            Deploy Assets
          </button>
        </div>
      </div>
    </div>

    <!-- Update Pages Template Modal -->
    <div v-if="showUpdateTemplateModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Update Pages Template</h2>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Select Templates</label>
          <select
            v-model="updateTemplateForm.template_names"
            multiple
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
          >
            <option value="all">All templates</option>
            <option value="home-1">Home 1</option>
            <option value="listing-1">Listing 1</option>
            <option value="hotel-detail-1">Hotel Detail 1</option>
          </select>
          <p class="mt-2 text-xs text-gray-500">Giữ Ctrl/Command để chọn nhiều. Chọn "All templates" để áp dụng cho tất cả.</p>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-md p-3 mb-4">
          <p class="text-sm text-purple-800">
            Cập nhật phần header/footer cho <strong>các trang đang dùng template đã chọn</strong>,
            sau đó tự động deploy các trang đó. Tính năng này <strong>không</strong> cập nhật CSS/JS
            (dùng mục “Redeploy Template Assets” cho CSS/JS). Với website chính, hệ thống sẽ xếp lịch
            redeploy Trang chủ và các trang Category liên quan.
          </p>
        </div>

        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="closeUpdateTemplateModal"
            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="updatePagesTemplate"
            :disabled="loadingRedeployIds.includes(currentRedeployWebsite?.id)"
            class="px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="loadingRedeployIds.includes(currentRedeployWebsite?.id)" class="size-4 animate-spin" />
            Update header/footer
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- Corner Toast -->
  <div v-if="toastMsg" class="fixed bottom-4 right-4 z-50">
    <div :class="toastType === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white'" class="px-4 py-3 rounded-md shadow-lg text-sm">
      {{ toastMsg }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue'
import axios from 'axios'
import { FileText, Play, ShieldCheck, ShieldOff, Trash2, Power, Pencil, Loader2, Globe, CheckCircle, AlertTriangle, Clock, LayoutGrid, RefreshCw, ChevronDown, Folder } from 'lucide-vue-next'

const websites = ref([])
const availableServers = ref([])
const showAddModal = ref(false)
const showPagesModal = ref(false)
const showAddPageModal = ref(false)
const showEditPageModal = ref(false)
const currentWebsite = ref(null)
const currentWebsitePages = ref([])
const editingPage = ref(null)
const uiMsg = ref('')
const uiMsgType = ref('')
const toastMsg = ref('')
const toastType = ref('')
const statusMap = ref({})
let pollTimer = null
const savingPage = ref(false)
const loadingDeployIds = ref([])
const loadingSslIds = ref([])
const loadingDeactivateIds = ref([])
const loadingDeleteIds = ref([])
const showRedeployDropdown = ref({})
const loadingRedeployIds = ref([])
const showRedeployAssetsModal = ref(false)
const showUpdateTemplateModal = ref(false)
const currentRedeployWebsite = ref(null)
const redeployAssetsForm = ref({ template_name: 'hotel-detail-1' })
const updateTemplateForm = ref({ template_names: ['hotel-detail-1'] })

const showToast = (msg, type = 'success') => {
  toastMsg.value = msg
  toastType.value = type
  setTimeout(() => { toastMsg.value = '' }, 3000)
}

const form = ref({
  domain: '',
  type: 'html',
  vps_server_id: '',
  wordpress_template: 'basic'
})

const pageForm = ref({
  path: '',
  filename: '',
  title: '',
  content: ''
})

const fetchWebsites = async () => {
  try {
    const response = await axios.get('/api/websites')
    const list = response.data
    // detect status changes for toast
    list.forEach(w => {
      const prev = statusMap.value[w.id]
      if (prev && prev !== w.status) {
        if (w.status === 'deployed') showToast(`${w.domain} deployed`, 'success')
        else if (w.status === 'error') showToast(`${w.domain} deploy failed`, 'error')
      }
      statusMap.value[w.id] = w.status
    })
    const domains = new Set(list.map(w => w.domain))
    const isSub = (d) => {
      for (const p of domains) {
        if (p === d) continue
        if (d.endsWith('.' + p)) return true
      }
      return false
    }
    websites.value = list.filter(w => !isSub(w.domain))
  } catch (error) {
    showToast('Failed to fetch websites', 'error')
  }
}

const fetchServers = async () => {
  try {
    const response = await axios.get('/api/vps')
    availableServers.value = response.data.filter(s => s.status === 'active')
  } catch (error) {
    // silently fail
  }
}

const createWebsite = async () => {
  try {
    await axios.post('/api/websites', form.value)
    showToast('Website created', 'success')
    uiMsg.value = 'Website created'
    uiMsgType.value = 'success'
    closeModal()
    await fetchWebsites()
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to create website'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
  }
}

const deployWebsite = async (website) => {
  try {
    website.status = 'deploying'
    loadingDeployIds.value = [...loadingDeployIds.value, website.id]
    await axios.post(`/api/websites/${website.id}/deploy`)
    showToast('Deployment started', 'success')
    uiMsg.value = 'Deployment started'
    uiMsgType.value = 'success'
    await fetchWebsites()
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to deploy website'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
    await fetchWebsites()
  } finally {
    loadingDeployIds.value = loadingDeployIds.value.filter(id => id !== website.id)
  }
}

const enableSSL = async (website) => {
  try {
    loadingSslIds.value = [...loadingSslIds.value, website.id]
    await axios.post(`/api/websites/${website.id}/ssl`)
    showToast('SSL generation started', 'success')
    uiMsg.value = 'SSL generation started'
    uiMsgType.value = 'success'
    await fetchWebsites()
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to enable SSL'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    loadingSslIds.value = loadingSslIds.value.filter(id => id !== website.id)
  }
}

const deleteWebsite = async (website) => {
  if (!confirm(`Are you sure you want to delete ${website.domain}?`)) return
  
  try {
    loadingDeleteIds.value = [...loadingDeleteIds.value, website.id]
    await axios.delete(`/api/websites/${website.id}`)
    showToast('Website deleted', 'success')
    await fetchWebsites()
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to delete website'
    showToast(msg, 'error')
  } finally {
    loadingDeleteIds.value = loadingDeleteIds.value.filter(id => id !== website.id)
  }
}

const deactivateWebsite = async (website) => {
  try {
    loadingDeactivateIds.value = [...loadingDeactivateIds.value, website.id]
    await axios.post(`/api/websites/${website.id}/deactivate`)
    showToast('Website deactivated', 'success')
    uiMsg.value = 'Website deactivated'
    uiMsgType.value = 'success'
    fetchWebsites()
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to deactivate website'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    loadingDeactivateIds.value = loadingDeactivateIds.value.filter(id => id !== website.id)
  }
}

const managePages = async (website) => {
  currentWebsite.value = website
  showPagesModal.value = true
  await fetchPages(website.id)
}

const fetchPages = async (websiteId) => {
  try {
    const response = await axios.get(`/api/websites/${websiteId}/pages`)
    currentWebsitePages.value = response.data
  } catch (error) {
    showToast('Failed to fetch pages', 'error')
  }
}

const editPage = (page) => {
  editingPage.value = page
  pageForm.value = { ...page }
  showEditPageModal.value = true
}

const savePage = async () => {
  try {
    savingPage.value = true
    if (showEditPageModal.value) {
      await axios.put(`/api/pages/${editingPage.value.id}`, pageForm.value)
      showToast('Page updated', 'success')
      uiMsg.value = 'Page updated'
      uiMsgType.value = 'success'
    } else {
      await axios.post(`/api/websites/${currentWebsite.value.id}/pages`, pageForm.value)
      showToast('Page created', 'success')
      uiMsg.value = 'Page created'
      uiMsgType.value = 'success'
    }
    
    closePageModal()
    await fetchPages(currentWebsite.value.id)
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to save page'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    savingPage.value = false
  }
}

const loadingPageDeleteIds = ref([])
const deletePage = async (page) => {
  if (!confirm(`Are you sure you want to delete the page ${page.path}?`)) return
  try {
    loadingPageDeleteIds.value = [...loadingPageDeleteIds.value, page.id]
    await axios.delete(`/api/pages/${page.id}`)
    showToast('Page deleted', 'success')
    uiMsg.value = 'Page deleted'
    uiMsgType.value = 'success'
    await fetchPages(currentWebsite.value.id)
  } catch (error) {
    const msg = error?.response?.data?.message || error?.response?.data?.error || 'Failed to delete page'
    showToast(msg, 'error')
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    loadingPageDeleteIds.value = loadingPageDeleteIds.value.filter(id => id !== page.id)
  }
}

const closeModal = () => {
  showAddModal.value = false
  form.value = {
    domain: '',
    type: 'html',
    vps_server_id: '',
    wordpress_template: 'basic'
  }
}

const closePageModal = () => {
  showAddPageModal.value = false
  showEditPageModal.value = false
  editingPage.value = null
  pageForm.value = {
    path: '',
    filename: '',
    title: '',
    content: ''
  }
}

onMounted(() => {
  fetchWebsites()
  fetchServers()
  pollTimer = setInterval(fetchWebsites, 3000)

  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.redeploy-dropdown-container')) {
      showRedeployDropdown.value = {}
    }
  })
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

const previewHomeUrl = (website) => {
  const base = `/preview/${website.id}`
  const token = localStorage.getItem('adminToken') || ''
  const params = new URLSearchParams()
  if (token) params.set('token', token)
  params.set('hide_preview_bar', '1')
  const qs = params.toString()
  return qs ? `${base}?${qs}` : base
}

const toggleRedeployDropdown = (websiteId) => {
  showRedeployDropdown.value = {
    ...showRedeployDropdown.value,
    [websiteId]: !showRedeployDropdown.value[websiteId]
  }
}

const redeployPages = async (website) => {
  try {
    loadingRedeployIds.value = [...loadingRedeployIds.value, website.id]
    showRedeployDropdown.value[website.id] = false
    const response = await axios.post(`/api/websites/${website.id}/redeploy-pages`)
    showToast(response.data.message || 'Pages redeployed successfully', 'success')
  } catch (error) {
    const msg = error?.response?.data?.message || 'Failed to redeploy pages'
    showToast(msg, 'error')
  } finally {
    loadingRedeployIds.value = loadingRedeployIds.value.filter(id => id !== website.id)
  }
}

const openRedeployAssetsModal = (website) => {
  currentRedeployWebsite.value = website
  showRedeployDropdown.value[website.id] = false
  showRedeployAssetsModal.value = true
}

const openUpdateTemplateModal = (website) => {
  currentRedeployWebsite.value = website
  showRedeployDropdown.value[website.id] = false
  showUpdateTemplateModal.value = true
}

const redeployTemplateAssets = async () => {
  if (!currentRedeployWebsite.value) return
  try {
    loadingRedeployIds.value = [...loadingRedeployIds.value, currentRedeployWebsite.value.id]
    const response = await axios.post(
      `/api/websites/${currentRedeployWebsite.value.id}/redeploy-template-assets`,
      redeployAssetsForm.value
    )
    showToast(response.data.message || 'Template assets deployed', 'success')
    showRedeployAssetsModal.value = false
  } catch (error) {
    const msg = error?.response?.data?.message || 'Failed to deploy template assets'
    showToast(msg, 'error')
  } finally {
    loadingRedeployIds.value = loadingRedeployIds.value.filter(id => id !== currentRedeployWebsite.value.id)
  }
}

const updatePagesTemplate = async () => {
  if (!currentRedeployWebsite.value) return
  try {
    loadingRedeployIds.value = [...loadingRedeployIds.value, currentRedeployWebsite.value.id]
    // Nếu chọn 'all', gửi 'template_names' = ['all'] để backend áp dụng tất cả
    const payload = {
      template_names: (updateTemplateForm.value.template_names || []).length === 0
        ? []
        : updateTemplateForm.value.template_names
    }
    const response = await axios.post(
      `/api/websites/${currentRedeployWebsite.value.id}/update-pages-template`,
      payload
    )
    showToast(response.data.message || 'Header/footer updated', 'success')
    showUpdateTemplateModal.value = false
  } catch (error) {
    const msg = error?.response?.data?.message || 'Failed to update header/footer'
    showToast(msg, 'error')
  } finally {
    loadingRedeployIds.value = loadingRedeployIds.value.filter(id => id !== currentRedeployWebsite.value.id)
  }
}

const closeRedeployAssetsModal = () => {
  showRedeployAssetsModal.value = false
  currentRedeployWebsite.value = null
  redeployAssetsForm.value = { template_name: 'hotel-detail-1' }
}

const closeUpdateTemplateModal = () => {
  showUpdateTemplateModal.value = false
  currentRedeployWebsite.value = null
  updateTemplateForm.value = { template_names: ['hotel-detail-1'] }
}
</script>
