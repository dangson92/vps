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
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Cloudflare</h2>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">CLOUDFLARE_API_TOKEN</label>
            <input v-model="form.CLOUDFLARE_API_TOKEN" class="w-full rounded-md border border-gray-300 px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">CLOUDFLARE_ZONE_ID</label>
            <input v-model="form.CLOUDFLARE_ZONE_ID" class="w-full rounded-md border border-gray-300 px-3 py-2" />
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
  SSL_EMAIL: '',
  CLOUDFLARE_API_TOKEN: '',
  CLOUDFLARE_ZONE_ID: ''
})

const saving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

const load = async () => {
  try {
    const { data } = await axios.get('/api/settings')
    form.value = { ...form.value, ...data }
  } catch (e) {
    errorMsg.value = 'Không tải được cài đặt'
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

onMounted(load)
</script>