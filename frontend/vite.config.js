import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: '../public/dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'assets/main.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/[name].[ext]',
        manualChunks: {
          'tinymce': ['tinymce/tinymce'],
          'tinymce-plugins': [
            'tinymce/icons/default',
            'tinymce/themes/silver',
            'tinymce/models/dom/model',
            'tinymce/plugins/link',
            'tinymce/plugins/lists',
            'tinymce/plugins/table',
            'tinymce/plugins/image',
            'tinymce/plugins/code',
            'tinymce/plugins/charmap',
            'tinymce/plugins/anchor',
            'tinymce/plugins/searchreplace',
            'tinymce/plugins/visualblocks',
            'tinymce/plugins/fullscreen',
            'tinymce/plugins/insertdatetime',
            'tinymce/plugins/media',
            'tinymce/plugins/help',
            'tinymce/plugins/wordcount'
          ],
          'tinymce-skins': [
            'tinymce/skins/ui/oxide/skin.js',
            'tinymce/skins/ui/oxide/content.js',
            'tinymce/skins/content/default/content.js'
          ],
          'vendor': ['vue', 'vue-router', 'axios'],
          'icons': ['lucide-vue-next']
        }
      }
    }
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})