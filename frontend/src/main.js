import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Dashboard from './views/Dashboard.vue'
import VpsManagement from './views/VpsManagement.vue'
import WebsiteManagement from './views/WebsiteManagement.vue'
import Monitoring from './views/Monitoring.vue'
import FoldersManagement from './views/FoldersManagement.vue'
import FolderEdit from './views/FolderEdit.vue'
import Settings from './views/Settings.vue'
import PagesManagement from './views/PagesManagement.vue'
import PageEdit from './views/PageEdit.vue'
import Login from './views/Login.vue'
import AdminProfile from './views/AdminProfile.vue'
import SubdomainsManagement from './views/SubdomainsManagement.vue'
import SubdomainEdit from './views/SubdomainEdit.vue'
import axios from 'axios'

const routes = [
  { path: '/login', name: 'Login', component: Login },
  { path: '/', name: 'Dashboard', component: Dashboard },
  { path: '/vps', name: 'VpsManagement', component: VpsManagement },
  { path: '/websites', name: 'WebsiteManagement', component: WebsiteManagement },
  { path: '/websites/:websiteId/pages', name: 'PagesManagement', component: PagesManagement },
  { path: '/websites/:websiteId/pages/new', name: 'PageCreate', component: PageEdit },
  { path: '/websites/:websiteId/pages/:pageId', name: 'PageEdit', component: PageEdit },
  { path: '/websites/:websiteId/folders', name: 'FoldersManagement', component: FoldersManagement },
  { path: '/websites/:websiteId/folders/new', name: 'FolderCreate', component: FolderEdit },
  { path: '/websites/:websiteId/folders/:folderId', name: 'FolderEdit', component: FolderEdit },
  { path: '/websites/:websiteId/subdomains', name: 'SubdomainsManagement', component: SubdomainsManagement },
  { path: '/websites/:websiteId/subdomains/new', name: 'SubdomainEdit', component: SubdomainEdit },
  { path: '/profile', name: 'AdminProfile', component: AdminProfile },
  { path: '/monitoring', name: 'Monitoring', component: Monitoring },
  { path: '/settings', name: 'Settings', component: Settings },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

const token = localStorage.getItem('adminToken')
if (token) axios.defaults.headers.common['X-Admin-Token'] = token

axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    if (status === 401) {
      try {
        localStorage.removeItem('adminToken')
        delete axios.defaults.headers.common['X-Admin-Token']
      } catch {}
      if (router.currentRoute.value.path !== '/login') router.push('/login')
    }
    return Promise.reject(error)
  }
)

router.beforeEach((to, from, next) => {
  const isAuth = !!localStorage.getItem('adminToken')
  if (!isAuth && to.path !== '/login') return next('/login')
  if (isAuth && to.path === '/login') return next('/')
  next()
})

createApp(App).use(router).mount('#app')