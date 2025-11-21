<template>
  <div id="app">
    <div v-if="route.path === '/login'" class="min-h-screen">
      <router-view />
    </div>
    <div v-else class="relative flex min-h-screen w-full bg-background-light">
      <aside class="flex w-64 flex-col border-r border-gray-200 bg-white p-4">
        <div class="flex items-center gap-3 mb-4">
          <div class="rounded-full size-10 bg-primary/10"></div>
          <div class="flex flex-col">
            <h1 class="text-gray-900 text-base font-medium">Admin User</h1>
            <p class="text-gray-500 text-sm">admin@vps.com</p>
          </div>
        </div>
        <nav class="flex flex-col gap-2">
          <router-link to="/" class="flex items-center gap-3 px-3 py-2 rounded-lg"
                       :class="{ 'bg-primary/10 text-primary': route.path === '/', 'hover:bg-gray-100 text-gray-800': route.path !== '/' }">
            <LayoutGrid class="size-4" />
            <span>Dashboard</span>
          </router-link>
          <router-link to="/vps" class="flex items-center gap-3 px-3 py-2 rounded-lg"
                       :class="{ 'bg-primary/10 text-primary': route.path.startsWith('/vps'), 'hover:bg-gray-100 text-gray-800': !route.path.startsWith('/vps') }">
            <Server class="size-4" />
            <span>Servers</span>
          </router-link>
          <router-link to="/websites" class="flex items-center gap-3 px-3 py-2 rounded-lg"
                       :class="{ 'bg-primary/10 text-primary': route.path.startsWith('/websites'), 'hover:bg-gray-100 text-gray-800': !route.path.startsWith('/websites') }">
            <Globe class="size-4" />
            <span>Websites</span>
          </router-link>
          <router-link to="/monitoring" class="flex items-center gap-3 px-3 py-2 rounded-lg"
                       :class="{ 'bg-primary/10 text-primary': route.path.startsWith('/monitoring'), 'hover:bg-gray-100 text-gray-800': !route.path.startsWith('/monitoring') }">
            <BarChart class="size-4" />
            <span>Monitoring</span>
          </router-link>
          <router-link to="/profile" class="flex items-center gap-3 px-3 py-2 rounded-lg"
                       :class="{ 'bg-primary/10 text-primary': route.path.startsWith('/profile'), 'hover:bg-gray-100 text-gray-800': !route.path.startsWith('/profile') }">
            <Users class="size-4" />
            <span>Profile</span>
          </router-link>
          <router-link to="/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-800 hover:bg-gray-100">
            <Settings class="size-4" />
            <span>Settings</span>
          </router-link>
        </nav>
      </aside>
      <main class="flex-1">
        <header class="flex items-center justify-between border-b border-gray-200 px-10 py-4 bg-white">
          <h2 class="text-lg font-bold">Tổng quan Hệ thống</h2>
          <div class="flex items-center gap-4">
            <input class="form-input h-10 w-64 rounded-lg border border-gray-300 bg-gray-50 px-4" placeholder="Search" />
            <button class="h-10 w-10 rounded-lg border border-gray-300 bg-gray-50 text-gray-800">
              <Bell class="mx-auto size-5" />
            </button>
            <button @click="logout" class="h-10 px-3 rounded-lg border border-gray-300 bg-white text-red-600">
              Logout
            </button>
          </div>
        </header>
        <div class="p-6 lg:p-10">
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { LayoutGrid, Server, Globe, BarChart, Settings, Bell, Users } from 'lucide-vue-next'
const route = useRoute()
const router = useRouter()

const logout = () => {
  localStorage.removeItem('adminToken')
  delete axios.defaults.headers.common['X-Admin-Token']
  router.push('/login')
}
</script>

<style>
@import './style.css';
</style>