<template>
  <div class="relative flex min-h-screen w-full flex-col bg-gray-100">
    <div class="flex flex-1 items-center justify-center p-4 lg:p-6">
      <div class="flex w-full max-w-6xl justify-center">
        <div class="grid w-full grid-cols-1 gap-8 lg:gap-16">
          <div class="flex flex-col justify-center">
            <div class="mx-auto flex w-full max-w-md flex-col">
              <div class="flex items-center gap-3 pb-8">
                <Cloud class="size-8 text-blue-600" />
                <span class="text-xl font-semibold text-gray-800">VPS Manager</span>
              </div>
              <div class="flex flex-col gap-1.5">
                <p class="text-gray-900 text-3xl font-bold tracking-tight">Đăng nhập vào Bảng điều khiển</p>
                <p class="text-gray-500 text-base">Quản lý các máy chủ ảo của bạn một cách hiệu quả.</p>
              </div>
              <form @submit.prevent="login" class="flex flex-col gap-5 pt-8">
                <label class="flex flex-col gap-2">
                  <p class="text-gray-900 text-sm font-medium">Email / Tên người dùng</p>
                  <div class="relative flex items-center">
                    <User class="absolute left-3.5 size-4 text-gray-400" />
                    <input v-model="username" type="text" required class="form-input flex w-full rounded-lg text-gray-900 focus:outline-0 focus:ring-2 focus:ring-blue-500/50 border border-gray-300 bg-white focus:border-blue-600 h-12 pl-11 pr-4" placeholder="nhapemail@example.com" />
                  </div>
                </label>
                <label class="flex flex-col gap-2">
                  <p class="text-gray-900 text-sm font-medium">Mật khẩu</p>
                  <div class="relative flex items-center">
                    <Lock class="absolute left-3.5 size-4 text-gray-400" />
                    <input :type="showPassword ? 'text' : 'password'" v-model="password" required class="form-input flex w-full rounded-lg text-gray-900 focus:outline-0 focus:ring-2 focus:ring-blue-500/50 border border-gray-300 bg-white focus:border-blue-600 h-12 pl-11 pr-11" placeholder="••••••••" />
                    <button type="button" @click="showPassword = !showPassword" class="absolute right-0 h-full px-3.5 text-gray-400 hover:text-gray-600">
                      <Eye v-if="!showPassword" class="size-5" />
                      <EyeOff v-else class="size-5" />
                    </button>
                  </div>
                </label>
                <button type="submit" :disabled="loading" class="flex h-12 w-full items-center justify-center rounded-lg bg-blue-600 px-6 text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                  <span v-if="loading" class="inline-flex items-center gap-2">
                    <Loader2 class="size-4 animate-spin" />
                    Đang đăng nhập...
                  </span>
                  <span v-else>Đăng Nhập</span>
                </button>
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <footer class="flex justify-center p-4">
      <p class="text-sm text-gray-500">© 2024 VPS Manager. Đã đăng ký bản quyền.</p>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { Loader2, Cloud, User, Lock, Eye, EyeOff } from 'lucide-vue-next'

const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

const login = async () => {
  loading.value = true
  error.value = ''
  try {
    const resp = await axios.post('/api/login', { username: username.value, password: password.value })
    const token = resp.data.token
    localStorage.setItem('adminToken', token)
    axios.defaults.headers.common['X-Admin-Token'] = token
    document.cookie = `X-Admin-Token=${token}; path=/; max-age=${60 * 60 * 24}; samesite=None; secure`
    document.cookie = `adminToken=${token}; path=/; max-age=${60 * 60 * 24}; samesite=None; secure`
    router.push('/')
  } catch (e) {
    error.value = e?.response?.data?.error || 'Đăng nhập thất bại'
  } finally {
    loading.value = false
  }
}
</script>