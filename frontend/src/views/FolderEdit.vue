<template>
  <div class="p-6">
    <div class="max-w-xl mx-auto">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ isNew ? 'Tạo thư mục' : 'Sửa thư mục' }}</h1>
        <router-link :to="`/websites/${websiteId}/folders`" class="text-sm text-primary">Quay lại danh sách</router-link>
      </div>
      <form @submit.prevent="save" class="bg-white border border-gray-200 rounded-md p-4 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Tên thư mục</label>
          <input v-model="name" @input="onNameInput" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Slug</label>
          <input v-model="slug" @input="slugTouched = true" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
          <div class="text-xs text-gray-500 mt-1">Sẽ tự thêm số nếu trùng trong domain</div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Thuộc thư mục</label>
          <select v-model="parentId" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option :value="null">(Không có)</option>
            <option v-for="f in flattenedFolders" :key="f.id" :value="f.id">{{ '↳ '.repeat(f.depth) + f.name }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <router-link :to="`/websites/${websiteId}/folders`" class="px-4 py-2 border border-gray-300 rounded-md">Hủy</router-link>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md disabled:bg-blue-400">{{ isNew ? 'Tạo' : 'Lưu' }}</button>
        </div>
        <div v-if="msg" class="text-sm" :class="msgType === 'error' ? 'text-red-600' : 'text-green-600'">{{ msg }}</div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const websiteId = route.params.websiteId
const folderId = route.params.folderId
const isNew = ref(route.name === 'FolderCreate')
const folders = ref([])
const name = ref('')
const slug = ref('')
const parentId = ref(null)
const slugTouched = ref(false)
const saving = ref(false)
const msg = ref('')
const msgType = ref('')

const refreshFolders = async () => {
  const resp = await axios.get(`/api/websites/${websiteId}/folders`)
  const list = resp.data || []
  const norm = list.map(f => ({
    ...f,
    id: Number(f.id),
    parent_id: f.parent_id == null ? null : Number(f.parent_id)
  }))
  folders.value = norm
}

const nameToSlug = (s) => {
  return (s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
}

const onNameInput = () => {
  if (!slugTouched.value) slug.value = nameToSlug(name.value)
}

const folderName = (id) => {
  if (!id) return ''
  const f = (folders.value || []).find(x => x.id === id)
  return f ? (f.name || '') : ''
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
  const n = folderName(id)
  const d = folderDepth(id)
  return (d > 0 ? '↳ '.repeat(d) : '') + n
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
  const flat = out.filter(f => String(f.id) !== String(folderId))
  return flat
})

const loadEditing = () => {
  if (!folderId || isNew.value) return
  const f = (folders.value || []).find(x => x.id === Number(folderId))
  if (f) {
    name.value = f.name || ''
    slug.value = f.slug || ''
    parentId.value = f.parent_id == null ? null : Number(f.parent_id)
  }
}

const save = async () => {
  saving.value = true
  msg.value = ''
  try {
    const payload = { name: name.value, slug: slug.value, parent_id: parentId.value == null ? null : Number(parentId.value) }
    let resp
    if (isNew.value) {
      resp = await axios.post(`/api/websites/${websiteId}/folders`, payload)
    } else {
      resp = await axios.put(`/api/websites/${websiteId}/folders/${folderId}`, payload)
    }
    msg.value = 'Đã lưu thư mục'
    msgType.value = 'success'
    parentId.value = resp?.data?.parent_id == null ? null : Number(resp.data.parent_id)
    await refreshFolders()
    loadEditing()
  } catch (e) {
    msg.value = e?.response?.data?.message || e?.response?.data?.error || 'Lưu thư mục thất bại'
    msgType.value = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await refreshFolders()
  loadEditing()
})
</script>

<style scoped>
</style>