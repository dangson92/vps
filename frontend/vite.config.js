import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'url'
import { dirname, resolve, join } from 'path'
import { copyFileSync, mkdirSync, existsSync, readdirSync } from 'fs'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

function copyTinyMCEAssets() {
  return {
    name: 'copy-tinymce-assets',
    writeBundle() {
      const tinymcePath = resolve(__dirname, 'node_modules/tinymce')
      const distPath = resolve(__dirname, '../public/dist/tinymce')

      const copyDir = (src, dest) => {
        if (!existsSync(dest)) {
          mkdirSync(dest, { recursive: true })
        }

        const entries = readdirSync(src, { withFileTypes: true })
        for (const entry of entries) {
          const srcPath = join(src, entry.name)
          const destPath = join(dest, entry.name)

          if (entry.isDirectory()) {
            copyDir(srcPath, destPath)
          } else {
            copyFileSync(srcPath, destPath)
          }
        }
      }

      try {
        if (!existsSync(distPath)) {
          mkdirSync(distPath, { recursive: true })
        }
        copyFileSync(resolve(tinymcePath, 'tinymce.min.js'), resolve(distPath, 'tinymce.min.js'))
        copyDir(resolve(tinymcePath, 'skins'), resolve(distPath, 'skins'))
        copyDir(resolve(tinymcePath, 'themes'), resolve(distPath, 'themes'))
        copyDir(resolve(tinymcePath, 'plugins'), resolve(distPath, 'plugins'))
        copyDir(resolve(tinymcePath, 'icons'), resolve(distPath, 'icons'))
        copyDir(resolve(tinymcePath, 'models'), resolve(distPath, 'models'))
        console.log('TinyMCE assets copied successfully')
      } catch (err) {
        console.error('Failed to copy TinyMCE assets:', err)
      }
    }
  }
}

export default defineConfig({
  plugins: [vue(), copyTinyMCEAssets()],
  build: {
    outDir: '../public/dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'assets/main.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/[name].[ext]',
        manualChunks: {
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