<template>
  <div class="p-8 max-w-7xl mx-auto">
    <div class="flex flex-wrap gap-2 mb-4">
      <router-link :to="'/'" class="text-slate-500 text-sm font-medium hover:text-primary">Bảng điều khiển</router-link>
      <span class="text-slate-500 text-sm font-medium">/</span>
      <span class="text-slate-800 text-sm font-medium">Danh mục</span>
    </div>
    <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-slate-900 text-3xl font-bold leading-tight tracking-tight">Quản lý Danh mục</h1>
        <p class="text-slate-500 text-base">Thêm, sửa đổi và tổ chức các danh mục.</p>
      </div>
      <router-link :to="`/websites/${websiteId}/folders/new`" class="flex items-center justify-center rounded-lg h-10 bg-primary text-white gap-2 text-sm font-bold px-4 hover:bg-primary/90">
        <span>Tạo thư mục</span>
      </router-link>
    </div>
    <div class="flex justify-between items-center gap-4 p-4 bg-white rounded-lg border border-slate-200 mb-6">
      <div class="flex gap-2 items-center">
        <div class="relative w-72">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <Search class="size-4" />
          </span>
          <input v-model="query" class="w-full pl-10 pr-4 py-2 text-sm border border-slate-300 rounded-lg bg-background-light focus:ring-primary focus:border-primary" placeholder="Tìm danh mục..." type="text" />
        </div>
        <button type="button" @click="refresh" class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg">
          <Filter class="size-5" />
        </button>
      </div>
      <router-link :to="`/websites/${websiteId}/pages`" class="text-sm text-primary">Quay lại Pages</router-link>
    </div>

    <div class="bg-white rounded-lg border border-slate-200">
      <div class="grid grid-cols-12 gap-4 px-6 py-3 border-b border-slate-200">
        <div class="col-span-6 text-xs font-semibold text-slate-500 uppercase">Tên danh mục</div>
        <div class="col-span-3 text-xs font-semibold text-slate-500 uppercase">Slug</div>
        <div class="col-span-1 text-xs font-semibold text-slate-500 uppercase">Số page</div>
        <div class="col-span-2 text-xs font-semibold text-slate-500 uppercase text-right">Hành động</div>
      </div>
      <div v-if="loading" class="px-6 py-4 text-sm text-gray-500">Đang tải...</div>
      <div v-else class="flex flex-col">
        <details v-for="root in roots" :key="root.id" class="group border-b border-slate-200" open>
          <summary class="list-none">
            <div class="grid grid-cols-12 gap-4 items-center px-6 py-4 cursor-pointer hover:bg-slate-50">
              <div class="col-span-6 flex items-center gap-3">
                <ChevronRight class="size-4 text-slate-500 group-open:rotate-90 transition-transform" />
                <div class="flex flex-col">
                  <p class="text-slate-800 text-sm font-medium">{{ root.name }}</p>
                  <p class="text-slate-500 text-xs">{{ childrenOf(root.id).length }} danh mục con</p>
                </div>
              </div>
              <div class="col-span-3 text-sm text-slate-600">/{{ fullSlug(root.id) }}</div>
              <div class="col-span-1 text-sm text-slate-600">{{ root.pages_count ?? 0 }}</div>
              <div class="col-span-2 flex justify-end gap-2 text-slate-500">
                <button type="button" class="p-1.5 hover:bg-slate-200 rounded-md" title="Xem trước" @click.stop="preview(root)"><Eye class="size-4" /></button>
                <router-link :to="`/websites/${websiteId}/folders/${root.id}`" class="p-1.5 hover:bg-slate-200 rounded-md" title="Sửa"><Edit class="size-4" /></router-link>
                <button type="button" class="p-1.5 hover:bg-slate-200 rounded-md" title="Xóa" @click.stop="remove(root)"><Trash2 class="size-4" /></button>
              </div>
            </div>
          </summary>
          <div class="pl-10 bg-slate-50/50">
            <div v-for="child in childrenOf(root.id)" :key="child.id" class="grid grid-cols-12 gap-4 items-center px-6 py-3 border-t border-slate-200">
              <div class="col-span-6 flex items-center gap-2 text-sm text-slate-700">
                <CornerDownRight class="size-5 text-slate-400 -ml-1" />
                <span>{{ child.name }}</span>
              </div>
              <div class="col-span-3 text-sm text-slate-600">/{{ fullSlug(child.id) }}</div>
              <div class="col-span-1 text-sm text-slate-600">{{ child.pages_count ?? 0 }}</div>
              <div class="col-span-2 flex justify-end gap-2 text-slate-500">
                <button type="button" class="p-1.5 hover:bg-slate-200 rounded-md" title="Xem trước" @click="preview(child)"><Eye class="size-4" /></button>
                <router-link :to="`/websites/${websiteId}/folders/${child.id}`" class="p-1.5 hover:bg-slate-200 rounded-md" title="Sửa"><Edit class="size-4" /></router-link>
                <button type="button" class="p-1.5 hover:bg-slate-200 rounded-md" title="Xóa" @click="remove(child)"><Trash2 class="size-4" /></button>
              </div>
            </div>
          </div>
        </details>
      </div>
      <div v-if="msg" class="px-6 py-3 text-sm" :class="msgType === 'error' ? 'text-red-600' : 'text-green-600'">{{ msg }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { ChevronRight, Edit, Trash2, Search, Filter, CornerDownRight, Eye } from 'lucide-vue-next'

const route = useRoute()
const websiteId = route.params.websiteId
const folders = ref([])
const loading = ref(false)
const msg = ref('')
const msgType = ref('')
const query = ref('')

const refresh = async () => {
  loading.value = true
  msg.value = ''
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/folders`)
    const list = resp.data || []
    const norm = list.map(f => ({
      ...f,
      id: Number(f.id),
      parent_id: f.parent_id == null ? null : Number(f.parent_id)
    }))
    folders.value = norm
  } finally {
    loading.value = false
  }
}

const preview = (folder) => {
  const id = folder.id
  const slug = fullSlug(folder.id)
  const base = slug ? `/preview/folder/${id}/${slug}` : `/preview/folder/${id}`
  const token = localStorage.getItem('adminToken') || ''
  if (!token) {
    window.open(base, '_blank')
    return
  }
  const url = `${base}?token=${encodeURIComponent(token)}`
  window.open(url, '_blank')
}

const folderName = (id) => {
  if (!id) return ''
  const f = (folders.value || []).find(x => x.id === id)
  return f ? (f.name || '') : ''
}

const fullSlug = (id) => {
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

const roots = computed(() => {
  const q = (query.value || '').trim().toLowerCase()
  const list = flattenedFolders.value
  const rootIds = new Set(list.filter(x => x.depth === 0).map(x => x.id))
  if (!q) return list.filter(x => rootIds.has(x.id))
  const match = list.filter(x => (x.name || '').toLowerCase().includes(q) || (x.slug || '').toLowerCase().includes(q))
  const keepRootIds = new Set(match.map(x => {
    let cur = x
    while (cur && cur.depth > 0) {
      const parent = list.find(y => String(y.id) === String(cur.parent_id))
      if (!parent) break
      cur = parent
    }
    return cur ? cur.id : x.id
  }))
  return list.filter(x => x.depth === 0 && keepRootIds.has(x.id))
})

const childrenOf = (pid) => {
  const q = (query.value || '').trim().toLowerCase()
  const list = flattenedFolders.value
  const children = list.filter(x => String(x.parent_id) === String(pid))
  if (!q) return children
  return children.filter(x => (x.name || '').toLowerCase().includes(q) || (x.slug || '').toLowerCase().includes(q))
}

const remove = async (f) => {
  msg.value = ''
  msgType.value = ''
  try {
    await axios.delete(`/api/websites/${websiteId}/folders/${f.id}`)
    folders.value = (folders.value || []).filter(x => x.id !== f.id)
    msg.value = 'Đã xóa thư mục'
    msgType.value = 'success'
  } catch (e) {
    msg.value = e?.response?.data?.error || 'Không thể xóa thư mục'
    msgType.value = 'error'
  }
}

onMounted(refresh)
</script>

<style scoped>
</style>