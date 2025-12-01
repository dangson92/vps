<template>
  <div class="min-h-screen bg-gray-100">
    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-3xl font-bold text-gray-900">
            Website Settings
            <span v-if="website" class="ml-2 text-lg text-gray-500">{{ website.domain }}</span>
          </h1>
          <router-link to="/websites" class="px-3 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Back</router-link>
        </div>

        <div v-if="uiMsg" class="mb-4">
          <div :class="uiMsgType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'" class="px-4 py-3 rounded-md">
            {{ uiMsg }}
          </div>
        </div>

        <div v-if="loading" class="text-gray-600">Loading...</div>

        <div v-else class="mb-4">
          <div class="inline-flex rounded-md shadow-sm" role="group">
            <button type="button" @click="activeTab = 'general'" :class="activeTab === 'general' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-4 py-2 border border-gray-300 rounded-l-md hover:bg-gray-50">Cài đặt chung</button>
            <button type="button" @click="activeTab = 'navigation'" :class="activeTab === 'navigation' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-4 py-2 border border-gray-300 rounded-r-md hover:bg-gray-50">Menu Header & Footer</button>
          </div>
        </div>

        <div v-if="!loading" class="bg-white shadow sm:rounded-md p-6 space-y-6">
          <div v-if="activeTab === 'general'" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tiêu đề</label>
            <input v-model="form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="Website title" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Cloudflare Account</label>
            <select v-model="selectedCloudflareAccountId" @change="updateCloudflareAccount" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
              <option :value="null">-- Không sử dụng Cloudflare (dùng bản ghi *) --</option>
              <option v-for="acc in cloudflareAccounts" :key="acc.id" :value="acc.id">
                {{ acc.email }}
              </option>
            </select>
            <p class="mt-1 text-sm text-gray-500">Chọn Cloudflare account để quản lý DNS cho website này. Để trống nếu đã có wildcard DNS record</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Logo Header</label>
              <div class="mt-2 flex items-center gap-4">
                <div class="h-16 w-48 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                  <img v-if="form.logo_header_url" :src="form.logo_header_url" class="object-contain h-full w-full" />
                  <ImageIcon v-else class="size-6 text-gray-400" />
                </div>
                <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                  <Upload class="size-4 mr-2" />
                  <span>Upload</span>
                  <input type="file" accept=".png,.jpg,.jpeg,.webp,.svg" @change="onUpload($event, 'logo-header')" class="hidden" />
                </label>
                <span v-if="form.logo_header_url" class="text-xs text-gray-500 truncate max-w-[360px]">{{ form.logo_header_url }}</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Logo Footer</label>
              <div class="mt-2 flex items-center gap-4">
                <div class="h-16 w-48 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                  <img v-if="form.logo_footer_url" :src="form.logo_footer_url" class="object-contain h-full w-full" />
                  <ImageIcon v-else class="size-6 text-gray-400" />
                </div>
                <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                  <Upload class="size-4 mr-2" />
                  <span>Upload</span>
                  <input type="file" accept=".png,.jpg,.jpeg,.webp,.svg" @change="onUpload($event, 'logo-footer')" class="hidden" />
                </label>
                <span v-if="form.logo_footer_url" class="text-xs text-gray-500 truncate max-w-[360px]">{{ form.logo_footer_url }}</span>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Favicon</label>
            <div class="mt-2 flex items-center gap-4">
              <div class="h-10 w-10 rounded-md border border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center">
                <img v-if="form.favicon_url" :src="form.favicon_url" class="object-contain h-full w-full" />
                <ImageIcon v-else class="size-5 text-gray-400" />
              </div>
              <label class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50">
                <Upload class="size-4 mr-2" />
                <span>Upload</span>
                <input type="file" accept=".ico,.png,.jpg,.jpeg,.svg" @change="onUpload($event, 'favicon')" class="hidden" />
              </label>
              <span v-if="form.favicon_url" class="text-xs text-gray-500 truncate max-w-[220px]">{{ form.favicon_url }}</span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào header</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterHead" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in headLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeHead" v-model="form.custom_head_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<script>...</script>" @scroll="syncScroll('head')"></textarea>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào body</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterBody" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in bodyLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeBody" v-model="form.custom_body_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<div>...</div>" @scroll="syncScroll('body')"></textarea>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Chèn mã vào footer</label>
            <div class="mt-1 grid" style="grid-template-columns: 48px 1fr;">
              <div ref="gutterFooter" class="border border-gray-300 border-r-0 rounded-l-md bg-gray-50 text-xs text-gray-500 overflow-hidden p-2">
                <div v-for="n in footerLineCount" :key="n" class="leading-6">{{ n }}</div>
              </div>
              <textarea ref="codeFooter" v-model="form.custom_footer_html" rows="10" class="border border-gray-300 rounded-r-md shadow-sm font-mono leading-6 p-2 w-full" placeholder="<script>...</script>" @scroll="syncScroll('footer')"></textarea>
            </div>
          </div>
          </div>
          <div v-if="activeTab === 'navigation'" class="space-y-6">
            <!-- Menu Header Section -->
            <div>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-lg font-semibold text-gray-900">Menu Header</h2>
                  <p class="text-sm text-gray-500 mt-1">Kéo thả để sắp xếp, click để chỉnh sửa</p>
                </div>
                <button type="button" @click="showAddMenuModal = true" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                  <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Thêm menu
                </button>
              </div>

              <!-- Menu List -->
              <div class="space-y-2">
                <div v-if="menu.length === 0" class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                  <p class="text-gray-500 text-sm">Chưa có menu nào. Click "Thêm menu" để bắt đầu.</p>
                </div>
                <div v-for="(mi, idx) in menu" :key="idx" class="rounded-lg border border-gray-200 bg-white" draggable="true" @dragstart="onParentDragStart(idx)" @dragover.prevent @drop="onParentDrop(idx)">
                  <!-- Parent Menu Item -->
                  <div class="p-4">
                    <div class="flex items-start justify-between gap-4">
                      <div class="flex items-start gap-3 flex-1 min-w-0">
                        <svg class="size-5 text-gray-400 mt-0.5 flex-shrink-0 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        <div class="flex-1 min-w-0 space-y-2">
                          <div class="flex items-center gap-2">
                            <input
                              v-model="mi.label"
                              @input="updateMenu"
                              type="text"
                              class="flex-1 px-2 py-1 text-sm font-medium border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                              placeholder="Tên menu"
                            />
                          </div>
                          <input
                            v-model="mi.url"
                            @input="updateMenu"
                            type="text"
                            class="w-full px-2 py-1 text-xs text-gray-600 border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            placeholder="URL (ví dụ: /khach-san)"
                          />
                        </div>
                      </div>
                      <div class="flex items-center gap-2 flex-shrink-0">
                        <button type="button" @click="openAddChildModal(idx)" class="p-1.5 text-gray-600 hover:bg-gray-100 rounded" title="Thêm menu con">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <button type="button" @click="removeParent(idx)" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Xóa">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </div>
                    </div>

                    <!-- Child Menu Items -->
                    <div v-if="mi.children && mi.children.length > 0" class="mt-4 ml-8 space-y-2 pt-3 border-t border-gray-100">
                      <div v-for="(ch, cidx) in mi.children" :key="cidx" class="flex items-center gap-3 group" draggable="true" @dragstart="onChildDragStart(idx, cidx)" @dragover.prevent @drop="onChildDrop(idx, cidx)">
                        <svg class="size-4 text-gray-300 flex-shrink-0 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        <svg class="size-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <div class="flex-1 min-w-0 space-y-1">
                          <input
                            v-model="ch.label"
                            @input="updateMenu"
                            type="text"
                            class="w-full px-2 py-1 text-sm border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            placeholder="Tên menu con"
                          />
                          <input
                            v-model="ch.url"
                            @input="updateMenu"
                            type="text"
                            class="w-full px-2 py-1 text-xs text-gray-600 border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            placeholder="URL"
                          />
                        </div>
                        <button type="button" @click="removeChild(idx, cidx)" class="opacity-0 group-hover:opacity-100 p-1 text-red-600 hover:bg-red-50 rounded flex-shrink-0">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-lg font-semibold text-gray-900">Footer</h2>
                  <p class="text-sm text-gray-500 mt-1">Kéo thả để sắp xếp, click để chỉnh sửa</p>
                </div>
                <button type="button" @click="showAddFooterColumnModal = true" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                  <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Thêm cột
                </button>
              </div>

              <!-- Footer Columns List -->
              <div class="space-y-3">
                <div v-if="footerColumns.length === 0" class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                  <p class="text-gray-500 text-sm">Chưa có cột footer nào. Click "Thêm cột" để bắt đầu.</p>
                </div>
                <div v-for="(col, cidx) in footerColumns" :key="cidx" class="rounded-lg border border-gray-200 bg-white" draggable="true" @dragstart="onFooterColumnDragStart(cidx)" @dragover.prevent @drop="onFooterColumnDrop(cidx)">
                  <div class="p-4">
                    <div class="flex items-start justify-between gap-4 mb-3">
                      <div class="flex items-start gap-3 flex-1 min-w-0">
                        <svg class="size-5 text-gray-400 mt-0.5 flex-shrink-0 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        <div class="flex-1 min-w-0">
                          <input
                            v-model="col.title"
                            @input="updateFooter"
                            type="text"
                            class="w-full px-2 py-1 text-sm font-medium border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            :placeholder="`Tiêu đề cột ${cidx + 1}`"
                          />
                        </div>
                      </div>
                      <div class="flex items-center gap-2 flex-shrink-0">
                        <button type="button" @click="openAddFooterLinkModal(cidx)" class="p-1.5 text-gray-600 hover:bg-gray-100 rounded" title="Thêm liên kết">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <button type="button" @click="removeFooterColumn(cidx)" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Xóa cột">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </div>
                    </div>

                    <!-- Footer Links in Column -->
                    <div v-if="col.links && col.links.length > 0" class="ml-8 space-y-2 pt-3 border-t border-gray-100">
                      <div v-for="(lnk, lidx) in col.links" :key="lidx" class="flex items-center gap-3 group" draggable="true" @dragstart="onFooterLinkDragStart(cidx, lidx)" @dragover.prevent @drop="onFooterLinkDrop(cidx, lidx)">
                        <svg class="size-4 text-gray-300 flex-shrink-0 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                        <svg class="size-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <div class="flex-1 min-w-0 space-y-1">
                          <input
                            v-model="lnk.label"
                            @input="updateFooter"
                            type="text"
                            class="w-full px-2 py-1 text-sm border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            placeholder="Tên liên kết"
                          />
                          <input
                            v-model="lnk.url"
                            @input="updateFooter"
                            type="text"
                            class="w-full px-2 py-1 text-xs text-gray-600 border border-transparent hover:border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            placeholder="URL"
                          />
                        </div>
                        <button type="button" @click="removeFooterLink(cidx, lidx)" class="opacity-0 group-hover:opacity-100 p-1 text-red-600 hover:bg-red-50 rounded flex-shrink-0">
                          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                    </div>
                    <div v-else class="ml-8 pt-3 border-t border-gray-100">
                      <p class="text-xs text-gray-400 italic">Chưa có liên kết</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-3">
            <button type="button" @click="resetForm" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Reset</button>
            <button type="button" @click="saveSettings" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
              <span v-if="saving" class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
              <span v-else>Save</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Menu Modal -->
    <div v-if="showAddMenuModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showAddMenuModal = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Thêm menu mới</h3>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn từ danh mục</label>
            <select v-model="newMenuFromFolder" class="w-full rounded-md border-gray-300 text-sm">
              <option value="">-- Hoặc nhập thủ công --</option>
              <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="pt-3 border-t border-gray-200">
            <label class="block text-sm font-medium text-gray-700 mb-2">Tên menu</label>
            <input v-model="newMenuLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: Khách sạn" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">URL</label>
            <input v-model="newMenuUrl" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: /khach-san" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex gap-3 justify-end">
          <button type="button" @click="showAddMenuModal = false" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Hủy</button>
          <button type="button" @click="addMenu" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">Thêm</button>
        </div>
      </div>
    </div>

    <!-- Add Child Menu Modal -->
    <div v-if="showAddChildModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showAddChildModal = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Thêm menu con</h3>
          <p class="text-sm text-gray-500 mt-1">Thêm vào: <strong>{{ menu[selectedParentForChild]?.label }}</strong></p>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn từ danh mục</label>
            <select v-model="newChildFromFolder" class="w-full rounded-md border-gray-300 text-sm">
              <option value="">-- Hoặc nhập thủ công --</option>
              <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="pt-3 border-t border-gray-200">
            <label class="block text-sm font-medium text-gray-700 mb-2">Tên menu con</label>
            <input v-model="newChildLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: Khách sạn 5 sao" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">URL</label>
            <input v-model="newChildUrl" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: /khach-san-5-sao" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex gap-3 justify-end">
          <button type="button" @click="showAddChildModal = false" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Hủy</button>
          <button type="button" @click="addChild" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">Thêm</button>
        </div>
      </div>
    </div>

    <!-- Add Footer Column Modal -->
    <div v-if="showAddFooterColumnModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showAddFooterColumnModal = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Thêm cột footer mới</h3>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tiêu đề cột</label>
            <input v-model="newFooterColumnTitle" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: Thông tin" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex gap-3 justify-end">
          <button type="button" @click="showAddFooterColumnModal = false" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Hủy</button>
          <button type="button" @click="addFooterColumn" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">Thêm</button>
        </div>
      </div>
    </div>

    <!-- Add Footer Link Modal -->
    <div v-if="showAddFooterLinkModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showAddFooterLinkModal = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Thêm liên kết footer</h3>
          <p class="text-sm text-gray-500 mt-1">Thêm vào: <strong>{{ footerColumns[selectedFooterColumnForLink]?.title || `Cột ${selectedFooterColumnForLink + 1}` }}</strong></p>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn từ danh mục</label>
            <select v-model="newFooterLinkFromFolder" class="w-full rounded-md border-gray-300 text-sm">
              <option value="">-- Hoặc nhập thủ công --</option>
              <option v-for="f in folders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="pt-3 border-t border-gray-200">
            <label class="block text-sm font-medium text-gray-700 mb-2">Tên liên kết</label>
            <input v-model="newFooterLinkLabel" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: Giới thiệu" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">URL</label>
            <input v-model="newFooterLinkUrl" type="text" class="w-full rounded-md border-gray-300 text-sm" placeholder="Ví dụ: /gioi-thieu" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex gap-3 justify-end">
          <button type="button" @click="showAddFooterLinkModal = false" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Hủy</button>
          <button type="button" @click="addFooterLink" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">Thêm</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { Upload, Image as ImageIcon } from 'lucide-vue-next'

const route = useRoute()
const websiteId = route.params.websiteId

const website = ref(null)
const loading = ref(true)
const saving = ref(false)
const uiMsg = ref('')
const uiMsgType = ref('')
const activeTab = ref('general')

const form = ref({
  title: '',
  logo_header_url: '',
  logo_footer_url: '',
  favicon_url: '',
  custom_head_html: '',
  custom_body_html: '',
  custom_footer_html: '',
  menu: [],
  footer_links_html: ''
})

const cloudflareAccounts = ref([])
const selectedCloudflareAccountId = ref(null)

const gutterHead = ref(null)
const gutterBody = ref(null)
const gutterFooter = ref(null)
const codeHead = ref(null)
const codeBody = ref(null)
const codeFooter = ref(null)

const headLineCount = computed(() => (form.value.custom_head_html || '').split('\n').length || 1)
  const bodyLineCount = computed(() => (form.value.custom_body_html || '').split('\n').length || 1)
  const footerLineCount = computed(() => (form.value.custom_footer_html || '').split('\n').length || 1)

const syncScroll = (which) => {
  if (which === 'head' && gutterHead.value && codeHead.value) gutterHead.value.scrollTop = codeHead.value.scrollTop
  if (which === 'body' && gutterBody.value && codeBody.value) gutterBody.value.scrollTop = codeBody.value.scrollTop
  if (which === 'footer' && gutterFooter.value && codeFooter.value) gutterFooter.value.scrollTop = codeFooter.value.scrollTop
}

const fetchWebsite = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}`)
    website.value = resp.data
    selectedCloudflareAccountId.value = resp.data.cloudflare_account_id
  } catch {}
}

const fetchCloudflareAccounts = async () => {
  try {
    const resp = await axios.get('/api/cloudflare-accounts')
    cloudflareAccounts.value = resp.data || []
  } catch (error) {
    console.error('Failed to fetch Cloudflare accounts:', error)
  }
}

const updateCloudflareAccount = async () => {
  try {
    await axios.put(`/api/websites/${websiteId}`, {
      cloudflare_account_id: selectedCloudflareAccountId.value
    })
    uiMsg.value = 'Cloudflare account đã được cập nhật'
    uiMsgType.value = 'success'
  } catch (error) {
    uiMsg.value = error?.response?.data?.message || 'Không thể cập nhật Cloudflare account'
    uiMsgType.value = 'error'
  }
}

const fetchSettings = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/settings`)
    const s = resp.data || {}
    form.value = {
      title: s.title || '',
      logo_header_url: s.logo_header_url || '',
      logo_footer_url: s.logo_footer_url || '',
      favicon_url: s.favicon_url || '',
      custom_head_html: s.custom_head_html || '',
      custom_body_html: s.custom_body_html || '',
      custom_footer_html: s.custom_footer_html || '',
      menu: Array.isArray(s.menu) ? s.menu : [],
      footer_links_html: s.footer_links_html || ''
    }
    menu.value = Array.isArray(s.menu) ? s.menu : []
    if (Array.isArray(s.footer_columns)) {
      footerColumns.value = s.footer_columns
    } else {
      footerColumns.value = []
    }
  } catch (error) {
    uiMsg.value = error?.response?.data?.error || 'Failed to load settings'
    uiMsgType.value = 'error'
  }
}

const saveSettings = async () => {
  saving.value = true
  uiMsg.value = ''
  try {
    form.value.footer_links_html = buildFooterHtml()
    form.value.footer_columns = footerColumns.value
    await axios.put(`/api/websites/${websiteId}/settings`, form.value)
    uiMsg.value = 'Updated settings'
    uiMsgType.value = 'success'
  } catch (error) {
    uiMsg.value = error?.response?.data?.message || error?.response?.data?.error || error?.message || 'Failed to update settings'
    uiMsgType.value = 'error'
  } finally {
    saving.value = false
  }
}

const resetForm = () => {
  form.value = { title: '', logo_header_url: '', logo_footer_url: '', favicon_url: '', custom_head_html: '', custom_body_html: '', custom_footer_html: '', menu: [], footer_links_html: '' }
}

const onUpload = async (e, type) => {
  const files = e?.target?.files || []
  if (!files.length) return
  const fd = new FormData()
  fd.append('file', files[0])
  try {
    let url = `/api/websites/${websiteId}/assets/${type}`
    const resp = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data', 'Accept': 'application/json' }, timeout: 60000 })
    const link = resp?.data?.url || ''
    if (type === 'logo-header') form.value.logo_header_url = link
    else if (type === 'logo-footer') form.value.logo_footer_url = link
    else if (type === 'favicon') form.value.favicon_url = link
    uiMsg.value = 'Uploaded ' + type
    uiMsgType.value = 'success'
  } catch (err) {
    const msg = err?.response?.data?.error || err?.message || 'Upload failed'
    uiMsg.value = msg
    uiMsgType.value = 'error'
  } finally {
    e.target.value = ''
  }
}

onMounted(async () => {
  await Promise.all([fetchWebsite(), fetchSettings(), fetchFolders(), fetchCloudflareAccounts()])
  menu.value = Array.isArray(form.value.menu) ? [...form.value.menu] : []
  loading.value = false
})
 
const folders = ref([])
const menu = ref([])

// Modal states
const showAddMenuModal = ref(false)
const showAddChildModal = ref(false)
const selectedParentForChild = ref(-1)

// New menu form
const newMenuFromFolder = ref('')
const newMenuLabel = ref('')
const newMenuUrl = ref('')

// New child form
const newChildFromFolder = ref('')
const newChildLabel = ref('')
const newChildUrl = ref('')

const fetchFolders = async () => {
  try {
    const resp = await axios.get(`/api/websites/${websiteId}/folders`)
    folders.value = resp.data || []
  } catch {}
}

const urlForFolder = (folder) => {
  const slug = folder?.slug || ''
  return `/${slug}`
}

// Auto-fill from folder selection
const watchMenuFolder = () => {
  if (newMenuFromFolder.value) {
    const f = folders.value.find(x => String(x.id) === String(newMenuFromFolder.value))
    if (f) {
      newMenuLabel.value = f.name
      newMenuUrl.value = urlForFolder(f)
    }
  }
}

const watchChildFolder = () => {
  if (newChildFromFolder.value) {
    const f = folders.value.find(x => String(x.id) === String(newChildFromFolder.value))
    if (f) {
      newChildLabel.value = f.name
      newChildUrl.value = urlForFolder(f)
    }
  }
}

// Watch folder selections
watch(newMenuFromFolder, watchMenuFolder)
watch(newChildFromFolder, watchChildFolder)

// Add menu
const addMenu = () => {
  if (!newMenuLabel.value || !newMenuUrl.value) {
    uiMsg.value = 'Vui lòng điền đầy đủ thông tin'
    uiMsgType.value = 'error'
    return
  }
  menu.value.push({
    label: newMenuLabel.value,
    url: newMenuUrl.value,
    children: []
  })
  form.value.menu = menu.value

  // Reset and close
  newMenuFromFolder.value = ''
  newMenuLabel.value = ''
  newMenuUrl.value = ''
  showAddMenuModal.value = false
  uiMsg.value = 'Đã thêm menu'
  uiMsgType.value = 'success'
}

// Open add child modal
const openAddChildModal = (parentIdx) => {
  selectedParentForChild.value = parentIdx
  showAddChildModal.value = true
}

// Add child
const addChild = () => {
  if (!newChildLabel.value || !newChildUrl.value) {
    uiMsg.value = 'Vui lòng điền đầy đủ thông tin'
    uiMsgType.value = 'error'
    return
  }
  const item = menu.value[selectedParentForChild.value]
  if (!item) return

  item.children = item.children || []
  item.children.push({
    label: newChildLabel.value,
    url: newChildUrl.value
  })
  form.value.menu = menu.value

  // Reset and close
  newChildFromFolder.value = ''
  newChildLabel.value = ''
  newChildUrl.value = ''
  showAddChildModal.value = false
  uiMsg.value = 'Đã thêm menu con'
  uiMsgType.value = 'success'
}

// Update menu on inline edit
const updateMenu = () => {
  form.value.menu = menu.value
}

const removeParent = (idx) => {
  menu.value.splice(idx, 1)
  form.value.menu = menu.value
}

const removeChild = (pidx, cidx) => {
  const item = menu.value[pidx]
  if (!item) return
  item.children.splice(cidx, 1)
  form.value.menu = menu.value
}

let draggingParentIndex = -1
let draggingChild = { parent: -1, index: -1 }

const onParentDragStart = (idx) => {
  draggingParentIndex = idx
}

const onParentDrop = (dropIdx) => {
  if (draggingParentIndex === -1 || dropIdx === draggingParentIndex) return
  const items = menu.value
  const [moved] = items.splice(draggingParentIndex, 1)
  items.splice(dropIdx, 0, moved)
  draggingParentIndex = -1
  form.value.menu = items
}

const onChildDragStart = (parentIdx, childIdx) => {
  draggingChild = { parent: parentIdx, index: childIdx }
}

const onChildDrop = (parentIdx, dropChildIdx) => {
  if (draggingChild.parent !== parentIdx) return
  const children = menu.value[parentIdx].children || []
  const from = draggingChild.index
  if (from === dropChildIdx || from < 0) return
  const [moved] = children.splice(from, 1)
  children.splice(dropChildIdx, 0, moved)
  draggingChild = { parent: -1, index: -1 }
  form.value.menu = menu.value
}

// Footer management
const footerColumns = ref([])

// Footer modal states
const showAddFooterColumnModal = ref(false)
const showAddFooterLinkModal = ref(false)
const selectedFooterColumnForLink = ref(-1)

// New footer column form
const newFooterColumnTitle = ref('')

// New footer link form
const newFooterLinkFromFolder = ref('')
const newFooterLinkLabel = ref('')
const newFooterLinkUrl = ref('')

// Auto-fill from folder selection for footer link
const watchFooterLinkFolder = () => {
  if (newFooterLinkFromFolder.value) {
    const f = folders.value.find(x => String(x.id) === String(newFooterLinkFromFolder.value))
    if (f) {
      newFooterLinkLabel.value = f.name
      newFooterLinkUrl.value = urlForFolder(f)
    }
  }
}

watch(newFooterLinkFromFolder, watchFooterLinkFolder)

// Add footer column
const addFooterColumn = () => {
  footerColumns.value.push({
    title: newFooterColumnTitle.value || '',
    links: []
  })

  // Reset and close
  newFooterColumnTitle.value = ''
  showAddFooterColumnModal.value = false
  uiMsg.value = 'Đã thêm cột footer'
  uiMsgType.value = 'success'
}

// Open add footer link modal
const openAddFooterLinkModal = (colIdx) => {
  selectedFooterColumnForLink.value = colIdx
  showAddFooterLinkModal.value = true
}

// Add footer link
const addFooterLink = () => {
  if (!newFooterLinkLabel.value || !newFooterLinkUrl.value) {
    uiMsg.value = 'Vui lòng điền đầy đủ thông tin'
    uiMsgType.value = 'error'
    return
  }

  const col = footerColumns.value[selectedFooterColumnForLink.value]
  if (!col) return

  col.links.push({
    label: newFooterLinkLabel.value,
    url: newFooterLinkUrl.value
  })

  // Reset and close
  newFooterLinkFromFolder.value = ''
  newFooterLinkLabel.value = ''
  newFooterLinkUrl.value = ''
  showAddFooterLinkModal.value = false
  uiMsg.value = 'Đã thêm liên kết footer'
  uiMsgType.value = 'success'
}

// Remove footer column
const removeFooterColumn = (idx) => {
  footerColumns.value.splice(idx, 1)
}

// Update footer on inline edit
const updateFooter = () => {
  // Trigger reactivity
}

const removeFooterLink = (cidx, lidx) => {
  const col = footerColumns.value[cidx]
  if (!col) return
  col.links.splice(lidx, 1)
}

// Drag & Drop for footer columns
let draggingFooterColumnIndex = -1

const onFooterColumnDragStart = (idx) => {
  draggingFooterColumnIndex = idx
}

const onFooterColumnDrop = (dropIdx) => {
  if (draggingFooterColumnIndex === -1 || dropIdx === draggingFooterColumnIndex) return
  const cols = footerColumns.value
  const [moved] = cols.splice(draggingFooterColumnIndex, 1)
  cols.splice(dropIdx, 0, moved)
  draggingFooterColumnIndex = -1
}

// Drag & Drop for footer links
let draggingFooterLink = { column: -1, index: -1 }

const onFooterLinkDragStart = (colIdx, linkIdx) => {
  draggingFooterLink = { column: colIdx, index: linkIdx }
}

const onFooterLinkDrop = (colIdx, dropLinkIdx) => {
  if (draggingFooterLink.column !== colIdx) return
  const links = footerColumns.value[colIdx].links || []
  const from = draggingFooterLink.index
  if (from === dropLinkIdx || from < 0) return
  const [moved] = links.splice(from, 1)
  links.splice(dropLinkIdx, 0, moved)
  draggingFooterLink = { column: -1, index: -1 }
}

const buildFooterHtml = () => {
  const cols = footerColumns.value
  if (!cols.length) return ''
  let out = '<div class="grid grid-cols-' + cols.length + ' gap-6">';
  for (const col of cols) {
    out += '<div>';
    if (col.title) {
      out += '<div class="font-medium mb-2">' + (col.title) + '</div>';
    }
    out += '<ul class="space-y-1">';
    const links = col.links || [];
    for (const lnk of links) {
      out += '<li><a href="' + (lnk.url || '#') + '" class="text-gray-600 hover:text-gray-900">' + (lnk.label || '') + '</a></li>';
    }
    out += '</ul>';
    out += '</div>';
  }
  out += '</div>';
  return out
}
</script>
