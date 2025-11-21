<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Monitoring</h1>

        <!-- Uptime Overview -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">System Overview</h2>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="flex flex-col items-center gap-2">
              <div class="inline-flex items-center gap-2 text-blue-600">
                <Globe class="size-5" />
                <span class="text-2xl font-bold">{{ uptimeStats.total_websites }}</span>
              </div>
              <p class="text-sm text-gray-500">Total Websites</p>
            </div>
            <div class="flex flex-col items-center gap-2">
              <div class="inline-flex items-center gap-2 text-green-600">
                <CheckCircle class="size-5" />
                <span class="text-2xl font-bold">{{ uptimeStats.online_websites }}</span>
              </div>
              <p class="text-sm text-gray-500">Online</p>
            </div>
            <div class="flex flex-col items-center gap-2">
              <div class="inline-flex items-center gap-2 text-red-600">
                <AlertTriangle class="size-5" />
                <span class="text-2xl font-bold">{{ uptimeStats.offline_websites }}</span>
              </div>
              <p class="text-sm text-gray-500">Offline</p>
            </div>
            <div class="flex flex-col items-center gap-2">
              <div class="inline-flex items-center gap-2 text-purple-600">
                <ShieldCheck class="size-5" />
                <span class="text-2xl font-bold">{{ sslEnabledCount }}</span>
              </div>
              <p class="text-sm text-gray-500">SSL Enabled</p>
            </div>
          </div>
        </div>

        <!-- Website Stats -->
        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-medium text-gray-900">Website Statistics</h2>
            <select
              v-model="selectedPeriod"
              @change="fetchWebsiteStats"
              class="border-gray-300 rounded-md shadow-sm"
            >
              <option value="24hours">Last 24 Hours</option>
              <option value="7days">Last 7 Days</option>
              <option value="30days">Last 30 Days</option>
              <option value="90days">Last 90 Days</option>
            </select>
          </div>

          <div class="space-y-4">
            <div
              v-for="website in websitesWithStats"
              :key="website.id"
              class="border rounded-lg p-4"
            >
              <div class="flex justify-between items-start mb-2">
                <h3 class="font-medium text-gray-900">{{ website.domain }}</h3>
                <span class="inline-flex items-center gap-1"
                  :class="website.current_is_online ? 'text-green-600' : 'text-red-600'">
                  <CheckCircle v-if="website.current_is_online" class="size-4" />
                  <AlertTriangle v-else class="size-4" />
                  {{ website.current_is_online ? 'Online' : 'Offline' }}
                </span>
              </div>
              
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <p class="text-gray-500">Visits</p>
                  <p class="font-medium">{{ website.total_visits }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Unique Visitors</p>
                  <p class="font-medium">{{ website.total_unique_visitors }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Bandwidth</p>
                  <p class="font-medium">{{ website.total_bandwidth }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Avg Response Time</p>
                  <p class="font-medium">{{ website.average_response_time }}ms</p>
                </div>
              </div>
              
              <div v-if="website.daily_stats && website.daily_stats.length > 0" class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Daily Stats</h4>
                <div class="grid grid-cols-7 gap-1 text-xs">
                  <div
                    v-for="day in website.daily_stats"
                    :key="day.date"
                    class="text-center"
                  >
                    <div class="text-gray-500">{{ formatDate(day.date) }}</div>
                    <div class="font-medium">{{ day.visits }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Globe, CheckCircle, AlertTriangle, ShieldCheck } from 'lucide-vue-next'
import axios from 'axios'
import { toast } from 'sonner'

const uptimeStats = ref({
  total_websites: 0,
  online_websites: 0,
  offline_websites: 0
})

const websitesWithStats = ref([])
const selectedPeriod = ref('7days')

const sslEnabledCount = computed(() => {
  return websitesWithStats.value.filter(w => w.ssl_enabled).length
})

const fetchUptimeStats = async () => {
  try {
    const response = await axios.get('/api/monitoring/uptime')
    uptimeStats.value = response.data
  } catch (error) {
    toast.error('Failed to fetch uptime statistics')
  }
}

const fetchWebsiteStats = async () => {
  try {
    const websitesResponse = await axios.get('/api/websites')
    const websites = websitesResponse.data
    
    const statsPromises = websites.map(async (website) => {
      try {
        const statsResponse = await axios.get(`/api/monitoring/stats/${website.id}?period=${selectedPeriod.value}`)
        return {
          ...website,
          ...statsResponse.data.summary
        }
      } catch (error) {
        return {
          ...website,
          total_visits: 0,
          total_unique_visitors: 0,
          total_bandwidth: '0 B',
          average_response_time: 0,
          uptime_percentage: 0,
          daily_stats: []
        }
      }
    })
    
    websitesWithStats.value = await Promise.all(statsPromises)
  } catch (error) {
    toast.error('Failed to fetch website statistics')
  }
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

onMounted(() => {
  fetchUptimeStats()
  fetchWebsiteStats()
})
</script>