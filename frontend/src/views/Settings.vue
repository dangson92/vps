<template>
  <div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Cài đặt hệ thống</h1>
    <div v-if="successMsg" class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-700">{{ successMsg }}</div>
    <div v-if="errorMsg" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ errorMsg }}</div>
    <form @submit.prevent="save">
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Ứng dụng</h2>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">APP_NAME</label>
            <input v-model="form.APP_NAME" class="w-full rounded-md border border-gray-300 px-3 py-2" placeholder="VPS Manager" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">APP_URL</label>
            <input v-model="form.APP_URL" class="w-full rounded-md border border-gray-300 px-3 py-2" placeholder="https://vps.yourdomain.com" />
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">SSL</h2>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">SSL_EMAIL</label>
            <input v-model="form.SSL_EMAIL" class="w-full rounded-md border border-gray-300 px-3 py-2" placeholder="admin@yourdomain.com" />
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Cloudflare Accounts</h2>
          <button type="button" @click="showAddAccount = true" class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm">+ Thêm Account</button>
        </div>

        <div v-if="cfAccounts.length === 0" class="text-sm text-gray-500">Chưa có Cloudflare account nào</div>

        <div v-else class="space-y-3">
          <div v-for="acc in cfAccounts" :key="acc.id" class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <h3 class="font-semibold text-gray-900">{{ acc.name }}</h3>
                  <span v-if="acc.is_default" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Default</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ acc.email }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button v-if="!acc.is_default" type="button" @click="setDefaultAccount(acc.id)" class="text-xs text-blue-600 hover:text-blue-800">Set Default</button>
                <button type="button" @click="deleteAccount(acc.id)" class="text-xs text-red-600 hover:text-red-800">Xóa</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Account Modal -->
      <div v-if="showAddAccount" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Thêm Cloudflare Account</h3>
            <button type="button" @click="showAddAccount = false" class="text-gray-500">✕</button>
          </div>
          <div class="space-y-3">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Tên tài khoản</label>
              <input v-model="newAccount.name" class="w-full rounded-md border border-gray-300 px-3 py-2" placeholder="Cloudflare Account 1" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Email</label>
              <input v-model="newAccount.email" type="email" class="w-full rounded-md border border-gray-300 px-3 py-2" placeholder="admin@example.com" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">API Key</label>
              <input v-model="newAccount.api_key" type="password" class="w-full rounded-md border border-gray-300 px-3 py-2" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="newAccount.is_default" type="checkbox" id="is-default" class="rounded" />
              <label for="is-default" class="text-sm text-gray-700">Set làm account mặc định</label>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button type="button" @click="showAddAccount = false" class="px-4 py-2 rounded-md border border-gray-300">Hủy</button>
            <button type="button" @click="addAccount" class="px-4 py-2 bg-blue-600 text-white rounded-md">Thêm</button>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" @click.prevent="save" :disabled="saving" class="bg-blue-600 text-white px-4 py-2 rounded-md" :class="{ 'opacity-50 cursor-not-allowed': saving }">{{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}</button>
      </div>
    </form>
  </div>
  </template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const form = ref({
  APP_NAME: '',
  APP_URL: '',
  SSL_EMAIL: ''
})

const saving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')
const cfAccounts = ref([])
const showAddAccount = ref(false)
const newAccount = ref({
  name: '',
  email: '',
  api_key: '',
  is_default: false
})

const load = async () => {
  try {
    const { data } = await axios.get('/api/settings')
    form.value = { ...form.value, ...data }
  } catch (e) {
    errorMsg.value = 'Không tải được cài đặt'
  }
}

const loadCloudflareAccounts = async () => {
  try {
    const { data } = await axios.get('/api/cloudflare-accounts')
    cfAccounts.value = data
  } catch (e) {
    console.error('Failed to load Cloudflare accounts', e)
  }
}

const addAccount = async () => {
  try {
    await axios.post('/api/cloudflare-accounts', newAccount.value)
    successMsg.value = 'Đã thêm Cloudflare account thành công'
    showAddAccount.value = false
    newAccount.value = { name: '', email: '', api_key: '', is_default: false }
    await loadCloudflareAccounts()
  } catch (e) {
    const msg = e?.response?.data?.error || 'Thêm account thất bại'
    errorMsg.value = msg
  }
}

const setDefaultAccount = async (id) => {
  try {
    await axios.post(`/api/cloudflare-accounts/${id}/set-default`)
    successMsg.value = 'Đã set account mặc định'
    await loadCloudflareAccounts()
  } catch (e) {
    errorMsg.value = 'Set default thất bại'
  }
}

const deleteAccount = async (id) => {
  if (!confirm('Bạn có chắc muốn xóa account này?')) return
  try {
    await axios.delete(`/api/cloudflare-accounts/${id}`)
    successMsg.value = 'Đã xóa account'
    await loadCloudflareAccounts()
  } catch (e) {
    const msg = e?.response?.data?.error || 'Xóa account thất bại'
    errorMsg.value = msg
  }
}

const save = async () => {
  saving.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    await axios.put('/api/settings', form.value)
    successMsg.value = 'Đã lưu cài đặt thành công'
  } catch (e) {
    const msg = e?.response?.data?.error || 'Lưu cài đặt thất bại'
    errorMsg.value = msg
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await load()
  await loadCloudflareAccounts()
})
</script>