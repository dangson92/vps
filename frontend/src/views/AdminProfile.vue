<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="bg-white rounded-lg shadow p-6">
          <h1 class="text-2xl font-bold text-gray-900 mb-4">Thông tin quản trị</h1>
          <form @submit.prevent="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Tên</label>
                <input v-model="form.ADMIN_NAME" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input v-model="form.ADMIN_EMAIL" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Tài khoản</label>
                <input v-model="form.ADMIN_USERNAME" type="text" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Mật khẩu</label>
                <input v-model="form.ADMIN_PASSWORD" type="password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
              </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" @click="logout" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Logout</button>
              <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-blue-400">
                <span v-if="saving" class="inline-flex items-center gap-2">
                  <Loader2 class="size-4 animate-spin" />
                  Đang lưu...
                </span>
                <span v-else>Lưu</span>
              </button>
            </div>
            <p v-if="msg" :class="msgType === 'error' ? 'text-red-600' : 'text-green-600'" class="mt-3 text-sm">{{ msg }}</p>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'

const router = useRouter()
const form = ref({ ADMIN_NAME: '', ADMIN_EMAIL: '', ADMIN_USERNAME: '', ADMIN_PASSWORD: '' })
const saving = ref(false)
const msg = ref('')
const msgType = ref('success')

const fetchSettings = async () => {
  const resp = await axios.get('/api/settings')
  const s = resp.data || {}
  form.value.ADMIN_NAME = s.ADMIN_NAME || ''
  form.value.ADMIN_EMAIL = s.ADMIN_EMAIL || ''
  form.value.ADMIN_USERNAME = s.ADMIN_USERNAME || ''
  form.value.ADMIN_PASSWORD = s.ADMIN_PASSWORD || ''
}

const save = async () => {
  saving.value = true
  msg.value = ''
  try {
    await axios.put('/api/settings', form.value)
    msg.value = 'Đã lưu, vui lòng đăng nhập lại'
    msgType.value = 'success'
    localStorage.removeItem('adminToken')
    delete axios.defaults.headers.common['X-Admin-Token']
    router.push('/login')
  } catch (e) {
    msg.value = e?.response?.data?.error || 'Lưu thất bại'
    msgType.value = 'error'
  } finally {
    saving.value = false
  }
}

const logout = () => {
  localStorage.removeItem('adminToken')
  delete axios.defaults.headers.common['X-Admin-Token']
  router.push('/login')
}

onMounted(fetchSettings)
</script>