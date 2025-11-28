<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-3xl font-bold text-gray-900">
            Website Settings
            <span v-if="website" class="ml-2 text-lg text-gray-500">{{ website.domain }}</span>
          </h1>
          <router-link to="/websites" class="px-3 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Back</router-link>
        </div>

        <div v-if="uiMsg" class="mb-4">
          <div :class="uiMsgType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'" class="px-4 py-3 rounded-md">
            {{ uiMsg }}
          </div>
        </div>

        <div v-if="loading" class="text-gray-600">Loading...</div>

        <div v-else class="mb-4">
          <div class="inline-flex rounded-md shadow-sm" role="group">
            <button type="button" @click="activeTab = 'general'" :class="activeTab === 'general' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-4 py-2 border border-gray-300 rounded-l-md hover:bg-gray-50">Cài đặt chung</button>
            <button type="button" @click="activeTab = 'navigation'" :class="activeTab === 'navigation' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-4 py-2 border border-gray-300 rounded-r-md hover:bg-gray-50">Menu Header & Footer</button>
          </div>
        </div>

        <div v-if="!loading" class="bg-white shadow sm:rounded-md p-6 space-y-6">
          <div v-if="activeTab === 'general'" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tiêu đề</label>
            <input v-model="form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Website title" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Logo Header</label>
              <div class="mt-2 flex items-center gap-4">
                <div class="h-16 w-48 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                  <img v-if="form.logo_header_url" :src="form.logo_header_url" class="object-contain h-full w-full" />
                  <ImageIcon v-else class="size-6 text-gray-400" />
                </div>
                <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                  <Upload class="size-4 mr-2" />
                  <span>Upload</span>
                  <input type="file" accept=".png,.jpg,.jpeg,.webp,.svg" @change="onUpload($event, 'logo-header')" class="hidden" />
                </label>
                <span v-if="form.logo_header_url" class="text-xs text-gray-500 truncate max-w-[360px]">{{ form.logo_header_url }}</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Logo Footer</label>
              <div class="mt-2 flex items-center gap-4">
                <div class="h-16 w-48 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                  <img v-if="form.logo_footer_url" :src="form.logo_footer_url" class="object-contain h-full w-full" />
                  <ImageIcon v-else class="size-6 text-gray-400" />
                </div>
                <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                  <Upload class="size-4 mr-2" />
                  <span>Upload</span>
                  <input type="file" accept=".png,.jpg,.jpeg,.webp,.svg" @change="onUpload($event, 'logo-footer')" class="hidden" />
                </label>
                <span v-if="form.logo_footer_url" class="text-xs text-gray-500 truncate max-w-[360px]">{{ form.logo_footer_url }}</span>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Favicon</label>
            <div class="mt-2 flex items-center gap-4">
              <div class="h-10 w-10 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                <img v-if="form.favicon_url" :src="form.favicon_url" class="object-contain h-full w-full" />
                <ImageIcon v-else class="size-5 text-gray-400" />
              </div>
              <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                <Upload class="size-4 mr-2" />
                <span>Upload</span>
                <input type="file" accept=".ico,.png,.jpg,.jpeg,.svg" @change="onUpload($event, 'favicon')" class="hidden" />
              </label>
              <span v-if="form.favicon_url" class="text-xs text-gray-500 truncate max-w-[220px]">{{ form.favicon_url }}</span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào header</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterHead" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in headLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeHead" v-model="form.custom_head_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<script>...</script>" @scroll="syncScroll('head')"></textarea>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào body</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterBody" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in bodyLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeBody" v-model="form.custom_body_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<div>...</div>" @scroll="syncScroll('body')"></textarea>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào footer</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterFooter" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in footerLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeFooter" v-model="form.custom_footer_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<script>...</script>" @scroll="syncScroll('footer')"></textarea>
            </div>
          </div>
          </div>
          <div v-if="activeTab === 'navigation'" class="space-y-6">
            <div class="flex items-center justify-between">
              <div class="flex flex-col gap-1">
                <h2 class="text-lg font-semibold text-gray-900">Menu Header</h2>
                <p class="text-sm text-gray-500">Tổ chức và tuỳ chỉnh menu điều hướng.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <div class="flex flex-col gap-4">
                <div class="rounded-lg border border-gray-200 bg-white">
                  <div class="flex items-center justify-between p-4 text-left font-medium text-gray-900">
                    <span>Danh mục</span>
                  </div>
                  <div class="border-t border-gray-200 p-4">
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-medium text-gray-700">Chọn danh mục</label>
                      <select v-model="menuParentFolderId" class="form-select w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Chọn danh mục</option>
                        <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
                      </select>
                      <button type="button" @click="addParentFromFolder" class="w-full flex items-center justify-center gap-2 rounded-lg bg-gray-100 h-9 px-3 text-gray-700 text-sm font-bold hover:bg-gray-200">Thêm vào menu</button>
                    </div>
                  </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white">
                  <div class="flex items-center justify-between p-4 text-left font-medium text-gray-900">
                    <span>Liên kết tuỳ chỉnh</span>
                  </div>
                  <div class="border-t border-gray-200 p-4">
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-medium text-gray-700">Nhãn điều hướng</label>
                      <input v-model="customParentLabel" class="form-input mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" type="text" placeholder="Ví dụ: Khuyến mãi" />
                      <label class="text-sm font-medium text-gray-700">URL</label>
                      <input v-model="customParentUrl" class="form-input mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" type="text" placeholder="/khuyen-mai" />
                      <button type="button" @click="addParentCustom" class="w-full flex items-center justify-center gap-2 rounded-lg bg-gray-100 h-9 px-3 text-gray-700 text-sm font-bold hover:bg-gray-200">Thêm vào menu</button>
                    </div>
                  </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white">
                  <div class="flex items-center justify-between p-4 text-left font-medium text-gray-900">
                    <span>Thêm menu con</span>
                    <span class="text-xs text-gray-500">{{ menu[selectedMenuIndex]?.label || 'Chưa chọn' }}</span>
                  </div>
                  <div class="border-t border-gray-200 p-4">
                    <div class="flex flex-col gap-4">
                      <div>
                        <label class="text-xs font-medium text-gray-700">Chọn danh mục</label>
                        <select v-model="childFolderId" class="form-select mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                          <option value="">Chọn danh mục</option>
                          <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                        <button type="button" @click="addChildFromFolder(selectedMenuIndex)" :disabled="selectedMenuIndex < 0" class="mt-2 w-full px-3 py-2 text-xs rounded-md border border-gray-300 bg-white disabled:opacity-50">+ Thêm</button>
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-700">Liên kết tuỳ chỉnh</label>
                        <input v-model="customChildLabel" placeholder="Label" class="form-input mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <input v-model="customChildUrl" placeholder="URL" class="form-input mt-2 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <button type="button" @click="addChildCustom(selectedMenuIndex)" :disabled="selectedMenuIndex < 0" class="mt-2 w-full px-3 py-2 text-xs rounded-md border border-gray-300 bg-white disabled:opacity-50">+ Thêm</button>
                        <div v-if="selectedMenuIndex < 0" class="mt-2 text-xs text-gray-500">Chọn mục menu ở danh sách bên phải trước khi thêm menu con</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                  <div class="flex items-center justify-between pb-3">
                    <h3 class="text-base font-medium text-gray-900">Cấu trúc menu</h3>
                  </div>
                  <div class="min-h-[200px] rounded-lg border border-dashed border-gray-300 bg-gray-50/50 p-4">
                    <div class="flex flex-col gap-2">
                      <div v-for="(mi, idx) in menu" :key="idx" class="rounded-lg border border-gray-200 bg-white" draggable="true" @dragstart="onParentDragStart(idx)" @dragover.prevent @drop="onParentDrop(idx)">
                        <div class="flex items-center justify-between p-3" @click="selectedMenuIndex = idx" :class="selectedMenuIndex === idx ? 'ring-1 ring-blue-500' : ''">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-800">{{ mi.label }}</span>
                            <span class="text-xs text-gray-500">{{ mi.url }}</span>
                          </div>
                          <div class="flex items-center gap-2">
                            <button type="button" @click="removeParent(idx)" class="text-sm text-red-600">Xoá</button>
                          </div>
                        </div>
                        <div class="border-t border-gray-200 p-3">
                          <div class="text-xs text-gray-600 mb-2">Menu con</div>
                          <ul class="flex flex-wrap items-center gap-2">
                            <li v-for="(ch, cidx) in mi.children" :key="cidx" class="px-2 py-1 text-xs rounded-md border border-gray-300 bg-gray-50 flex items-center gap-2" draggable="true" @dragstart="onChildDragStart(idx, cidx)" @dragover.prevent @drop="onChildDrop(idx, cidx)">
                              <span>↳ {{ ch.label }}</span>
                              <button type="button" @click="removeChild(idx, cidx)" class="text-gray-500">×</button>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex flex-col gap-1">
                <h2 class="text-lg font-semibold text-gray-900">Footer</h2>
                <p class="text-sm text-gray-500">Quản lý các cột và liên kết footer.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <div class="flex flex-col gap-4">
                <div class="rounded-lg border border-gray-200 bg-white">
                  <div class="flex items-center justify-between p-4 text-left font-medium text-gray-900">
                    <span>Cấu hình cột</span>
                  </div>
                  <div class="border-t border-gray-200 p-4">
                    <div class="flex flex-col gap-3">
                      <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Số cột</label>
                        <select v-model.number="footerColumnCount" @change="updateFooterColumnCount" class="form-select rounded-md border-gray-300 text-sm shadow-sm w-24">
                          <option :value="1">1</option>
                          <option :value="2">2</option>
                          <option :value="3">3</option>
                          <option :value="4">4</option>
                        </select>
                      </div>
                      <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Chọn cột</label>
                        <select v-model.number="selectedFooterColumnIndex" class="form-select rounded-md border-gray-300 text-sm shadow-sm w-24">
                          <option v-for="(col, idx) in footerColumns" :key="idx" :value="idx">Cột {{ idx + 1 }}</option>
                        </select>
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-700">Tiêu đề cột</label>
                        <input v-model="footerColumns[selectedFooterColumnIndex].title" class="form-input mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" type="text" placeholder="Ví dụ: Thông tin" />
                      </div>
                      <div>
                        <label class="text-xs font-medium text-gray-700">Thêm liên kết</label>
                        <input v-model="footerLinkLabel" placeholder="Label" class="form-input mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <input v-model="footerLinkUrl" placeholder="URL" class="form-input mt-2 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <button type="button" @click="addFooterLink" class="mt-2 w-full px-3 py-2 text-xs rounded-md border border-gray-300 bg-white">+ Thêm</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                  <div class="flex items-center justify-between pb-3">
                    <h3 class="text-base font-medium text-gray-900">Cấu trúc footer</h3>
                  </div>
                  <div class="min-h-[200px] rounded-lg border border-dashed border-gray-300 bg-gray-50/50 p-4">
                    <div :class="`grid grid-cols-${footerColumns.length} gap-4`">
                      <div v-for="(col, cidx) in footerColumns" :key="cidx" class="rounded-lg border border-gray-200 bg-white">
                        <div class="p-3">
                          <div class="text-sm font-medium text-gray-800">{{ col.title || (`Cột ${cidx + 1}`) }}</div>
                          <ul class="mt-2 flex flex-col gap-1">
                            <li v-for="(lnk, lidx) in col.links" :key="lidx" class="flex items-center justify-between text-sm">
                              <span class="truncate">{{ lnk.label }} — {{ lnk.url }}</span>
                              <button type="button" @click="removeFooterLink(cidx, lidx)" class="text-gray-500">×</button>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-3">
            <button type="button" @click="resetForm" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Reset</button>
            <button type="button" @click="saveSettings" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
              <span v-if="saving" class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
              <span v-else>Save</span>
            </button>
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
import { Upload, Image as ImageIcon } from 'lucide-vue-next'

const route = useRoute()
const websiteId = route.params.websiteId

const website = ref(null)
const loading = ref(true)
const saving = ref(false)
const uiMsg = ref('')
const uiMsgType = ref('')
const activeTab = ref('general')

const form = ref({
  title: '',
  logo_header_url: '',
  logo_footer_url: '',
  favicon_url: '',
  custom_head_html: '',
  custom_body_html: '',
  custom_footer_html: '',
  menu: [],
  footer_links_html: ''
})

const gutterHead = ref(null)
const gutterBody = ref(null)
const gutterFooter = ref(null)
const codeHead = ref(null)
const codeBody = ref(null)
const codeFooter = ref(null)

const headLineCount = computed(() => (form.value.custom_head_html || '').split('\n').length || 1)
  const bodyLineCount = computed(() => (form.value.custom_body_html || '').split('\n').length || 1)
  const footerLineCount = computed(() => (form.value.custom_footer_html || '').split('\n').length || 1)

const syncScroll = (which) => {
  if (which === 'head' && gutterHead.value && codeHead.value) gutterHead.value.scrollTop = codeHead.value.scrollTop
  if (which === 'body' && gutterBody.value && codeBody.value) gutterBody.value.scrollTop = codeBody.value.scrollTop
  if (which === 'footer' && gutterFooter.value && codeFooter.value) gutterFooter.value.scrollTop = codeFooter.value.scrollTop
}

const fetchWebsite = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}`)
    website.value = resp.data
  } catch {}
}

const fetchSettings = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/settings`)
    const s = resp.data || {}
    form.value = {
      title: s.title || '',
      logo_header_url: s.logo_header_url || '',
      logo_footer_url: s.logo_footer_url || '',
      favicon_url: s.favicon_url || '',
      custom_head_html: s.custom_head_html || '',
      custom_body_html: s.custom_body_html || '',
      custom_footer_html: s.custom_footer_html || '',
      menu: Array.isArray(s.menu) ? s.menu : [],
      footer_links_html: s.footer_links_html || ''
    }
    if (Array.isArray(s.footer_columns)) {
      footerColumns.value = s.footer_columns
      footerColumnCount.value = s.footer_columns.length || 1
      if (selectedFooterColumnIndex.value >= footerColumns.value.length) selectedFooterColumnIndex.value = 0
    }
  } catch (error) {
    uiMsg.value = error?.response?.data?.error || 'Failed to load settings'
    uiMsgType.value = 'error'
  }
}

const saveSettings = async () => {
  saving.value = true
  uiMsg.value = ''
  try {
    form.value.footer_links_html = buildFooterHtml()
    form.value.footer_columns = footerColumns.value
    await axios.put(`/api/websites/${websiteId}/settings`, form.value)
    uiMsg.value = 'Updated settings'
    uiMsgType.value = 'success'
  } catch (error) {
    uiMsg.value = error?.response?.data?.message || error?.response?.data?.error || error?.message || 'Failed to update settings'
    uiMsgType.value = 'error'
  } finally {
    saving.value = false
  }
}

const resetForm = () => {
  form.value = { title: '', logo_header_url: '', logo_footer_url: '', favicon_url: '', custom_head_html: '', custom_body_html: '', custom_footer_html: '', menu: [], footer_links_html: '' }
}

const onUpload = async (e, type) => {
  const files = e?.target?.files || []
  if (!files.length) return
  const fd = new FormData()
  fd.append('file', files[0])
  try {
    let url = `/api/websites/${websiteId}/assets/${type}`
    const resp = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data', 'Accept': 'application/json' }, timeout: 60000 })
    const link = resp?.data?.url || ''
    if (type === 'logo-header') form.value.logo_header_url = link
    else if (type === 'logo-footer') form.value.logo_footer_url = link
    else if (type === 'favicon') form.value.favicon_url = link
    uiMsg.value = 'Uploaded ' + type
    uiMsgType.value = 'success'
  } catch (err) {
    const msg = err?.response?.data?.error || err?.message || 'Upload failed'
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    e.target.value = ''
  }
}

onMounted(async () => {
  await Promise.all([fetchWebsite(), fetchSettings(), fetchFolders()])
  menu.value = Array.isArray(form.value.menu) ? [...form.value.menu] : []
  if (!footerColumns.value.length) {
    updateFooterColumnCount()
  }
  loading.value = false
})
 
const folders = ref([])
const menu = ref([])
const selectedMenuIndex = ref(-1)
const menuParentFolderId = ref('')
const customParentLabel = ref('')
const customParentUrl = ref('')
const childFolderId = ref('')
const customChildLabel = ref('')
const customChildUrl = ref('')

const fetchFolders = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/folders`)
    folders.value = resp.data || []
  } catch {}
}

const urlForFolder = (folder) => {
  const slug = folder?.slug || ''
  return `/${slug}`
}

  const addParentFromFolder = () => {
    const f = folders.value.find(x => String(x.id) === String(menuParentFolderId.value))
    if (!f) return
    menu.value.push({ label: f.name, url: urlForFolder(f), children: [] })
    menuParentFolderId.value = ''
    selectedMenuIndex.value = menu.value.length - 1
    uiMsg.value = 'Đã thêm danh mục vào menu'
    uiMsgType.value = 'success'
    form.value.menu = menu.value
  }

  const addParentCustom = () => {
    if (!customParentLabel.value || !customParentUrl.value) return
    menu.value.push({ label: customParentLabel.value, url: customParentUrl.value, children: [] })
    customParentLabel.value = ''
    customParentUrl.value = ''
    selectedMenuIndex.value = menu.value.length - 1
    uiMsg.value = 'Đã thêm liên kết vào menu'
    uiMsgType.value = 'success'
    form.value.menu = menu.value
  }

  const addChildFromFolder = (idx) => {
    if (idx < 0) { uiMsg.value = 'Chọn mục menu trước'; uiMsgType.value = 'error'; return }
    const f = folders.value.find(x => String(x.id) === String(childFolderId.value))
    if (!f) return
    const item = menu.value[idx]
    if (!item) return
    item.children = item.children || []
    item.children.push({ label: f.name, url: urlForFolder(f) })
    childFolderId.value = ''
    uiMsg.value = 'Đã thêm menu con từ danh mục'
    uiMsgType.value = 'success'
    form.value.menu = menu.value
  }

  const addChildCustom = (idx) => {
    if (!customChildLabel.value || !customChildUrl.value) return
    if (idx < 0) { uiMsg.value = 'Chọn mục menu trước'; uiMsgType.value = 'error'; return }
    const item = menu.value[idx]
    if (!item) return
    item.children = item.children || []
    item.children.push({ label: customChildLabel.value, url: customChildUrl.value })
    customChildLabel.value = ''
    customChildUrl.value = ''
    uiMsg.value = 'Đã thêm menu con tuỳ chỉnh'
    uiMsgType.value = 'success'
    form.value.menu = menu.value
  }

const removeParent = (idx) => {
  menu.value.splice(idx, 1)
  form.value.menu = menu.value
}

const removeChild = (pidx, cidx) => {
  const item = menu.value[pidx]
  if (!item) return
  item.children.splice(cidx, 1)
  form.value.menu = menu.value
}

let draggingParentIndex = -1
let draggingChild = { parent: -1, index: -1 }

const onParentDragStart = (idx) => {
  draggingParentIndex = idx
}

const onParentDrop = (dropIdx) => {
  if (draggingParentIndex === -1 || dropIdx === draggingParentIndex) return
  const items = menu.value
  const [moved] = items.splice(draggingParentIndex, 1)
  items.splice(dropIdx, 0, moved)
  draggingParentIndex = -1
  form.value.menu = items
}

const onChildDragStart = (parentIdx, childIdx) => {
  draggingChild = { parent: parentIdx, index: childIdx }
}

const onChildDrop = (parentIdx, dropChildIdx) => {
  if (draggingChild.parent !== parentIdx) return
  const children = menu.value[parentIdx].children || []
  const from = draggingChild.index
  if (from === dropChildIdx || from < 0) return
  const [moved] = children.splice(from, 1)
  children.splice(dropChildIdx, 0, moved)
  draggingChild = { parent: -1, index: -1 }
  form.value.menu = menu.value
}

const footerColumnCount = ref(2)
const footerColumns = ref([])
const selectedFooterColumnIndex = ref(0)
const footerLinkLabel = ref('')
const footerLinkUrl = ref('')

const updateFooterColumnCount = () => {
  const n = Number(footerColumnCount.value) || 1
  while (footerColumns.value.length < n) footerColumns.value.push({ title: '', links: [] })
  while (footerColumns.value.length > n) footerColumns.value.pop()
  if (selectedFooterColumnIndex.value >= footerColumns.value.length) selectedFooterColumnIndex.value = 0
}

const addFooterLink = () => {
  if (!footerLinkLabel.value || !footerLinkUrl.value) return
  const col = footerColumns.value[selectedFooterColumnIndex.value]
  if (!col) return
  col.links.push({ label: footerLinkLabel.value, url: footerLinkUrl.value })
  footerLinkLabel.value = ''
  footerLinkUrl.value = ''
}

const removeFooterLink = (cidx, lidx) => {
  const col = footerColumns.value[cidx]
  if (!col) return
  col.links.splice(lidx, 1)
}

const buildFooterHtml = () => {
  const cols = footerColumns.value
  if (!cols.length) return ''
  let out = '<div class="grid grid-cols-' + cols.length + ' gap-6">'
  cols.forEach(col => {
    out += '<div>'
    if (col.title) out += '<div class="font-medium mb-2">' + (col.title) + '</div>'
    out += '<ul class="space-y-1">'
    (col.links || []).forEach(lnk => {
      out += '<li><a href="' + (lnk.url || '#') + '" class="text-gray-600 hover:text-gray-900">' + (lnk.label || '') + '</a></li>'
    })
    out += '</ul>'
    out += '</div>'
  })
  out += '</div>'
  return out
}
</script>
