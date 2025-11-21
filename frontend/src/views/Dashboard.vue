<template>
  <div class="min-h-screen">
    <main>
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Tổng quan Hệ thống</h2>
            
            <div class="rounded-xl bg-gray-50 p-6 border border-gray-200">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="flex flex-col gap-2 rounded-xl p-6 border border-gray-200 bg-white">
                  <div class="flex items-center justify-between text-gray-500">
                    <p class="text-base font-medium text-gray-800">Máy chủ VPS</p>
                    <Server class="h-6 w-6 text-gray-600" />
                  </div>
                  <p class="text-gray-900 tracking-tight text-3xl font-bold">{{ stats.vpsServers }}</p>
                </div>

                <div class="flex flex-col gap-2 rounded-xl p-6 border border-gray-200 bg-white">
                  <div class="flex items-center justify-between text-gray-500">
                    <p class="text-base font-medium text-gray-800">Trang web</p>
                    <Globe class="h-6 w-6 text-gray-600" />
                  </div>
                  <p class="text-gray-900 tracking-tight text-3xl font-bold">{{ stats.websites }}</p>
                </div>

                <div class="flex flex-col gap-2 rounded-xl p-6 border border-gray-200 bg-white">
                  <div class="flex items-center justify-between text-gray-500">
                    <p class="text-base font-medium text-gray-800">Trang web trực tuyến</p>
                    <BarChart class="h-6 w-6 text-red-500" />
                  </div>
                  <p class="text-red-500 tracking-tight text-3xl font-bold">{{ stats.onlineWebsites }}</p>
                </div>

                <div class="flex flex-col gap-2 rounded-xl p-6 border border-gray-200 bg-white">
                  <div class="flex items-center justify-between text-gray-500">
                    <p class="text-base font-medium text-gray-800">SSL được bật</p>
                    <ShieldCheck class="h-6 w-6 text-gray-600" />
                  </div>
                  <p class="text-gray-900 tracking-tight text-3xl font-bold">{{ stats.sslEnabled }}</p>
                </div>
              </div>
            </div>

            <h2 class="text-gray-900 text-[22px] font-bold tracking-[-0.015em] pb-3 pt-10">Hoạt động gần đây</h2>
            <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="px-6 py-4 text-left text-gray-600 w-[15%] text-sm font-medium">Hoạt động</th>
                    <th class="px-6 py-4 text-left text-gray-600 w-[20%] text-sm font-medium">Người dùng</th>
                    <th class="px-6 py-4 text-left text-gray-600 w-[40%] text-sm font-medium">Đối tượng</th>
                    <th class="px-6 py-4 text-left text-gray-600 w-[25%] text-sm font-medium">Thời gian</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr>
                    <td class="h-[72px] px-6 py-4 w-[15%] text-sm">
                      <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">Tạo</span>
                    </td>
                    <td class="h-[72px] px-6 py-4 w-[20%] text-gray-600 text-sm">admin</td>
                    <td class="h-[72px] px-6 py-4 w-[40%] text-gray-600 text-sm">máy chủ 'web-server-01'</td>
                    <td class="h-[72px] px-6 py-4 w-[25%] text-gray-600 text-sm">5 phút trước</td>
                  </tr>
                  <tr>
                    <td class="h-[72px] px-6 py-4 w-[15%] text-sm">
                      <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">Thêm</span>
                    </td>
                    <td class="h-[72px] px-6 py-4 w-[20%] text-gray-600 text-sm">hệ thống</td>
                    <td class="h-[72px] px-6 py-4 w-[40%] text-gray-600 text-sm">trang web 'example.com'</td>
                    <td class="h-[72px] px-6 py-4 w-[25%] text-gray-600 text-sm">15 phút trước</td>
                  </tr>
                  <tr>
                    <td class="h-[72px] px-6 py-4 w-[15%] text-sm">
                      <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">Khởi động lại</span>
                    </td>
                    <td class="h-[72px] px-6 py-4 w-[20%] text-gray-600 text-sm">dev_user</td>
                    <td class="h-[72px] px-6 py-4 w-[40%] text-gray-600 text-sm">máy chủ 'db-server-02'</td>
                    <td class="h-[72px] px-6 py-4 w-[25%] text-gray-600 text-sm">1 giờ trước</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Server, Globe, BarChart, ShieldCheck } from 'lucide-vue-next'
import axios from 'axios'

const stats = ref({
  vpsServers: 0,
  websites: 0,
  onlineWebsites: 0,
  sslEnabled: 0
})

const fetchStats = async () => {
  try {
    const [vpsResponse, websitesResponse] = await Promise.all([
      axios.get('/api/vps'),
      axios.get('/api/websites')
    ])
    
    const vpsData = Array.isArray(vpsResponse.data) ? vpsResponse.data : []
    const websitesData = Array.isArray(websitesResponse.data) ? websitesResponse.data : []

    stats.value.vpsServers = vpsData.length
    stats.value.websites = websitesData.length
    stats.value.onlineWebsites = websitesData.filter(w => w.status === 'deployed').length
    stats.value.sslEnabled = websitesData.filter(w => w.ssl_enabled).length
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

onMounted(fetchStats)
</script>