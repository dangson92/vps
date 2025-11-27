<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-3xl font-bold text-gray-900">VPS Servers</h1>
          <button
            @click="showAddModal = true"
            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
          >
            Add VPS Server
          </button>
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-md">
          <ul class="divide-y divide-gray-200">
            <li v-for="server in servers" :key="server.id" class="px-6 py-4">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <h3 class="text-lg font-medium text-gray-900">{{ server.name }}</h3>
                  <p class="text-sm text-gray-500">{{ server.ip_address }}</p>
                  <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                    <span>SSH Port: {{ server.ssh_port }}</span>
                    <span>User: {{ server.ssh_user }}</span>
                    <span>Websites: {{ server.websites_count }}</span>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <span class="inline-flex items-center gap-1"
                    :class="{
                      'text-green-600': server.status === 'active',
                      'text-red-600': server.status === 'inactive',
                      'text-yellow-600': server.status === 'error'
                    }">
                    <CheckCircle v-if="server.status === 'active'" class="size-4" />
                    <AlertTriangle v-else-if="server.status === 'error'" class="size-4" />
                    <Clock v-else class="size-4" />
                    {{ server.status }}
                  </span>
                  <button
                    @click="editServer(server)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-blue-600 hover:bg-gray-50"
                    title="Edit"
                  >
                    <Pencil class="size-4" />
                  </button>
                  <button
                    @click="deleteServer(server)"
                    class="h-9 w-9 flex items-center justify-center rounded-md border border-gray-300 bg-white text-red-600 hover:bg-gray-50"
                    title="Delete"
                  >
                    <Trash2 class="size-4" />
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-medium text-gray-900 mb-4">
          {{ showEditModal ? 'Edit VPS Server' : 'Add VPS Server' }}
        </h2>
        
        <form @submit.prevent="saveServer">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">IP Address / Hostname</label>
            <input
              v-model="form.ip_address"
              type="text"
              required
              placeholder="e.g. 203.0.113.10 or worker.example.com"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">SSH User</label>
            <input
              v-model="form.ssh_user"
              type="text"
              required
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">SSH Port</label>
            <input
              v-model.number="form.ssh_port"
              type="number"
              required
              min="1"
              max="65535"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">SSH Key Path</label>
            <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <input
                v-model="form.ssh_key_path"
                type="text"
                placeholder="/path/to/ssh/key"
                class="block w-full border-gray-300 rounded-md shadow-sm"
              />
              <select @change="onSelectKeyPath($event)" class="block w-full border-gray-300 rounded-md shadow-sm">
                <option value="">Chọn nhanh đường dẫn</option>
                <option value="/root/.ssh/id_rsa">/root/.ssh/id_rsa</option>
                <option value="/root/.ssh/id_ed25519">/root/.ssh/id_ed25519</option>
                <option value="/home/ubuntu/.ssh/id_rsa">/home/ubuntu/.ssh/id_rsa</option>
                <option value="/home/ubuntu/.ssh/id_ed25519">/home/ubuntu/.ssh/id_ed25519</option>
                <option value="/home/ec2-user/.ssh/id_rsa">/home/ec2-user/.ssh/id_rsa</option>
                <option value="/home/ec2-user/.ssh/id_ed25519">/home/ec2-user/.ssh/id_ed25519</option>
                <option value="/home/admin/.ssh/id_rsa">/home/admin/.ssh/id_rsa</option>
                <option value="/home/admin/.ssh/id_ed25519">/home/admin/.ssh/id_ed25519</option>
              </select>
            </div>
          </div>

          <div v-if="showEditModal" class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Worker Key</label>
            <div class="mt-1 flex gap-2">
              <input
                :value="workerKey"
                readonly
                class="flex-1 block w-full border-gray-300 rounded-md shadow-sm"
              />
              <button type="button" @click="copyWorkerKey" class="px-3 py-2 rounded-md border border-gray-300 bg-gray-50">Copy</button>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select
              v-model="form.status"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
            >
              <option value="active">active</option>
              <option value="inactive">inactive</option>
              <option value="error">error</option>
            </select>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              {{ showEditModal ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Corner Toast -->
    <div v-if="uiMsg" class="fixed bottom-4 right-4 z-50">
      <div :class="uiMsgType === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white'" class="px-4 py-3 rounded-md shadow-lg text-sm">
        {{ uiMsg }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Pencil, Trash2, CheckCircle, AlertTriangle, Clock } from 'lucide-vue-next'
const uiMsg = ref('')
const uiMsgType = ref('')

const servers = ref([])
const showAddModal = ref(false)
const showEditModal = ref(false)
const editingServer = ref(null)
const workerKey = ref('')

const form = ref({
  name: '',
  ip_address: '',
  ssh_user: 'root',
  ssh_port: 22,
  ssh_key_path: '',
  status: 'inactive'
})

const fetchServers = async () => {
  try {
    const response = await axios.get('/api/vps')
    servers.value = response.data
  } catch (error) {
    uiMsg.value = 'Failed to fetch VPS servers'
    uiMsgType.value = 'error'
  }
}

const sanitizeHost = (h) => {
  if (!h) return ''
  let s = String(h).trim()
  s = s.replace(/^https?:\/\//i, '')
  s = s.split('/')[0]
  s = s.split(':')[0]
  return s.trim()
}

const onSelectKeyPath = (e) => {
  const v = e?.target?.value || ''
  if (v) form.value.ssh_key_path = v
}

const saveServer = async () => {
  try {
    form.value.ip_address = sanitizeHost(form.value.ip_address)
    form.value.ssh_key_path = (form.value.ssh_key_path || '').trim()
    if (showEditModal.value) {
      await axios.put(`/api/vps/${editingServer.value.id}`, form.value)
      uiMsg.value = 'VPS server updated successfully'
      uiMsgType.value = 'success'
    } else {
      const resp = await axios.post('/api/vps', form.value)
      const created = resp.data
      if (form.value.status && form.value.status !== 'inactive') {
        await axios.put(`/api/vps/${created.id}`, { status: form.value.status })
      }
      uiMsg.value = 'VPS server created successfully'
      uiMsgType.value = 'success'
    }
    
    closeModal()
    fetchServers()
  } catch (error) {
    uiMsg.value = error?.response?.data?.message || error?.response?.data?.error || (error?.response?.data?.errors && Object.values(error.response.data.errors).flat().join(', ')) || 'Failed to save VPS server'
    uiMsgType.value = 'error'
  }
}

const editServer = (server) => {
  editingServer.value = server
  form.value = { ...server }
  showEditModal.value = true
  workerKey.value = server.worker_key || ''
}

const deleteServer = async (server) => {
  if (!confirm(`Are you sure you want to delete ${server.name}?`)) return
  
  try {
    await axios.delete(`/api/vps/${server.id}`)
    uiMsg.value = 'VPS server deleted successfully'
    uiMsgType.value = 'success'
    fetchServers()
  } catch (error) {
    uiMsg.value = 'Failed to delete VPS server'
    uiMsgType.value = 'error'
  }
}

const closeModal = () => {
  showAddModal.value = false
  showEditModal.value = false
  editingServer.value = null
  form.value = {
    name: '',
    ip_address: '',
    ssh_user: 'root',
    ssh_port: 22,
    ssh_key_path: ''
  }
  workerKey.value = ''
}

const copyWorkerKey = () => {
  if (!workerKey.value) return
  navigator.clipboard.writeText(workerKey.value)
  uiMsg.value = 'Worker key copied'
  uiMsgType.value = 'success'
}

onMounted(fetchServers)
</script>
