import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  // Relative paths let the site operate in the root folder or in a subfolder.
  base: './',
  server: {
    proxy: {
      // On the host, the API entry file is api/index.php.
      // In the local environment, the PHP container supplies it as /index.php.
      '/api': {
        target: process.env.API_TARGET ?? 'http://localhost:8080',
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
  test: {
    environment: 'node',
  },
})
